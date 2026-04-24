<?php

namespace App\Services\System;

use App\Mail\SellerApplicationApprovedMail;
use App\Mail\SellerApplicationDocumentsRequestedMail;
use App\Mail\SellerApplicationReceivedMail;
use App\Mail\SellerApplicationRejectedMail;
use App\Models\System\Plan;
use App\Models\System\SellerApplication;
use App\Models\System\SellerApplicationLog;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Orquesta el workflow de solicitudes de onboarding de sellers.
 *
 * Responsabilidades:
 *   - createApplication: recibe datos del form público /seller/register,
 *     valida RUC, crea la solicitud y notifica al seller.
 *   - approve: al confirmar el SuperAdmin, invoca TenantCreationService
 *     para materializar el tenant y actualiza la aplicación + log + mail.
 *   - reject: marca como rechazada + mail al seller.
 *   - requestDocuments: cambia a requires_documents + mail con lista.
 *   - addNote / markUnderReview: operaciones ligeras de workflow.
 *
 * Cada operación registra una entrada en seller_application_logs. Las
 * operaciones destructivas (approve, reject) son transaccionales.
 */
class SellerApplicationService
{
    public function __construct(
        private RucValidationService $rucValidator,
    ) {}

    /**
     * Crea una solicitud nueva desde el form público.
     *
     * @param array $input  keys esperadas: ruc, business_name, trade_name,
     *   category_id, fiscal_address, department_id, province_id, district_id,
     *   legal_representative_name, legal_representative_dni,
     *   legal_representative_position, email, phone, requested_subdomain,
     *   store_name, store_description, password (plain),
     *   logo_path, facebook_url, instagram_url, tiktok_url, website_url,
     *   source_ip, source_ua
     *
     * @return array{success: bool, message: string, application?: SellerApplication}
     */
    public function createApplication(array $input): array
    {
        try {
            // 1. Validar RUC (formato + API si está configurada)
            $rucResult = $this->rucValidator->validate($input['ruc'] ?? '');

            if (!$rucResult['valid']) {
                return [
                    'success' => false,
                    'message' => $rucResult['error'] ?? 'RUC inválido',
                ];
            }

            // 2. Determinar status inicial según validación RUC
            $initialStatus = $this->rucValidator->canAutoAdvance($rucResult)
                ? SellerApplication::STATUS_PENDING
                : SellerApplication::STATUS_REQUIRES_REVIEW;

            // 3. Persistir en transacción + crear log inicial
            $application = DB::connection('system')->transaction(function () use ($input, $rucResult, $initialStatus) {
                $app = SellerApplication::create([
                    'ruc'                           => $input['ruc'],
                    'business_name'                 => $rucResult['business_name'] ?: ($input['business_name'] ?? ''),
                    'trade_name'                    => $input['trade_name'] ?? null,
                    'category_id'                   => $input['category_id'] ?? null,
                    'fiscal_address'                => $rucResult['fiscal_address'] ?: ($input['fiscal_address'] ?? null),
                    'department_id'                 => $input['department_id'] ?? null,
                    'province_id'                   => $input['province_id'] ?? null,
                    'district_id'                   => $input['district_id'] ?? null,
                    'legal_representative_name'     => $input['legal_representative_name'] ?? '',
                    'legal_representative_dni'      => $input['legal_representative_dni'] ?? '',
                    'legal_representative_position' => $input['legal_representative_position'] ?? null,
                    'email'                         => strtolower($input['email'] ?? ''),
                    'phone'                         => $input['phone'] ?? '',
                    'requested_subdomain'           => strtolower($input['requested_subdomain'] ?? ''),
                    'store_name'                    => $input['store_name'] ?? null,
                    'store_description'             => $input['store_description'] ?? null,
                    'password_hash'                 => Hash::make($input['password'] ?? ''),
                    'logo_path'                     => $input['logo_path'] ?? null,
                    'facebook_url'                  => $input['facebook_url']  ?? null,
                    'instagram_url'                 => $input['instagram_url'] ?? null,
                    'tiktok_url'                    => $input['tiktok_url']    ?? null,
                    'website_url'                   => $input['website_url']   ?? null,
                    'ruc_status'                    => $rucResult['status']    ?? null,
                    'ruc_condition'                 => $rucResult['condition'] ?? null,
                    'ruc_validation_response'       => $rucResult['raw'],
                    'status'                        => $initialStatus,
                    'tracking_token'                => SellerApplication::generateTrackingToken(),
                    'source_ip'                     => $input['source_ip'] ?? null,
                    'source_ua'                     => $input['source_ua'] ?? null,
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $app->id,
                    'action'                => SellerApplicationLog::ACTION_CREATED,
                    'new_status'            => $initialStatus,
                    'notes'                 => 'Solicitud registrada desde el formulario público.',
                    'user_id'               => null,
                    'created_at'            => now(),
                ]);

                return $app;
            });

            // 4. Notificar al seller (fuera de transacción — fallo de mail no
            //    debe revertir la solicitud).
            $this->safeSendMail(
                $application->email,
                new SellerApplicationReceivedMail($application),
                'received',
                $application->id
            );

            return [
                'success'     => true,
                'message'     => 'Solicitud registrada correctamente',
                'application' => $application,
            ];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::createApplication error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Aprueba una solicitud: materializa el tenant vía TenantCreationService,
     * vincula tenant_id, actualiza estado + log + mail al seller.
     *
     * @param SellerApplication $application
     * @param int               $reviewerId  ID del SuperAdmin que aprueba
     * @param int               $planId      plan asignado (admin puede escoger)
     * @param array             $options     modules/levels/type opcionales
     *
     * @return array{success: bool, message: string, tenant?: \App\Models\System\Client}
     */
    public function approve(SellerApplication $application, int $reviewerId, int $planId, array $options = []): array
    {
        if (!$application->isReviewable()) {
            return [
                'success' => false,
                'message' => "La solicitud no está en un estado revisable (actual: {$application->status})",
            ];
        }

        // Validar que no exista otro tenant con el mismo RUC/subdominio/email
        $dupeError = $this->checkDuplicatesAtApproval($application);
        if ($dupeError !== null) {
            return ['success' => false, 'message' => $dupeError];
        }

        // Plan válido
        if (!Plan::query()->whereKey($planId)->exists()) {
            return ['success' => false, 'message' => 'Plan seleccionado no existe'];
        }

        try {
            // Invocar TenantCreationService con los datos de la solicitud.
            // La contraseña del admin tenant se toma del hash de la solicitud:
            // para no reabrir el hash original ponemos una contraseña temporal
            // y se envía por mail al seller (que podrá cambiarla al primer login).
            $temporaryPassword = $this->generateTemporaryPassword();

            $payload = $this->buildTenantPayload($application, $temporaryPassword, $planId, $options);

            $result = app(TenantCreationService::class)->create($payload);

            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'No se pudo crear el tenant: ' . ($result['message'] ?? 'error desconocido'),
                ];
            }

            $client = $result['client'] ?? null;
            if (!$client) {
                return ['success' => false, 'message' => 'TenantCreationService no retornó el client creado'];
            }

            // Marcar cliente como seller aprobado + actualizar aplicación + log
            DB::connection('system')->transaction(function () use ($application, $client, $reviewerId) {
                $client->update([
                    'is_verified'             => true,
                    'verified_at'             => now(),
                    'marketplace_enabled'     => true,
                    'seller_status'           => 'active',
                    'marketplace_approved_at' => now(),
                    'marketplace_approved_by' => $reviewerId,
                ]);

                $previousStatus = $application->status;
                $application->update([
                    'status'      => SellerApplication::STATUS_APPROVED,
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'approved_at' => now(),
                    'tenant_id'   => $client->id,
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $application->id,
                    'action'                => SellerApplicationLog::ACTION_APPROVED,
                    'old_status'            => $previousStatus,
                    'new_status'            => SellerApplication::STATUS_APPROVED,
                    'notes'                 => "Tenant #{$client->id} creado y marcado como seller activo.",
                    'user_id'               => $reviewerId,
                    'created_at'            => now(),
                ]);
            });

            // Mail al seller con credenciales temporales
            $this->safeSendMail(
                $application->email,
                new SellerApplicationApprovedMail($application, $temporaryPassword),
                'approved',
                $application->id
            );

            return [
                'success' => true,
                'message' => 'Solicitud aprobada y tenant creado.',
                'tenant'  => $client,
            ];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::approve error', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Error al aprobar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Rechaza una solicitud con motivo obligatorio.
     */
    public function reject(SellerApplication $application, int $reviewerId, string $reason): array
    {
        if (!$application->isReviewable()) {
            return [
                'success' => false,
                'message' => "La solicitud no está en un estado revisable (actual: {$application->status})",
            ];
        }

        try {
            DB::connection('system')->transaction(function () use ($application, $reviewerId, $reason) {
                $previousStatus = $application->status;
                $application->update([
                    'status'           => SellerApplication::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                    'reviewed_by'      => $reviewerId,
                    'reviewed_at'      => now(),
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $application->id,
                    'action'                => SellerApplicationLog::ACTION_REJECTED,
                    'old_status'            => $previousStatus,
                    'new_status'            => SellerApplication::STATUS_REJECTED,
                    'notes'                 => $reason,
                    'user_id'               => $reviewerId,
                    'created_at'            => now(),
                ]);
            });

            $this->safeSendMail(
                $application->email,
                new SellerApplicationRejectedMail($application, $reason),
                'rejected',
                $application->id
            );

            return ['success' => true, 'message' => 'Solicitud rechazada.'];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::reject error', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Error al rechazar: ' . $e->getMessage()];
        }
    }

    /**
     * Solicita documentos adicionales al seller.
     * La lista de documentos/explicación se guarda en review_notes.
     */
    public function requestDocuments(SellerApplication $application, int $reviewerId, string $documentsRequested): array
    {
        if (!$application->isReviewable()) {
            return [
                'success' => false,
                'message' => "La solicitud no está en un estado revisable (actual: {$application->status})",
            ];
        }

        try {
            DB::connection('system')->transaction(function () use ($application, $reviewerId, $documentsRequested) {
                $previousStatus = $application->status;
                $application->update([
                    'status'       => SellerApplication::STATUS_REQUIRES_DOCUMENTS,
                    'review_notes' => $documentsRequested,
                    'reviewed_by'  => $reviewerId,
                    'reviewed_at'  => now(),
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $application->id,
                    'action'                => SellerApplicationLog::ACTION_DOCS_REQUESTED,
                    'old_status'            => $previousStatus,
                    'new_status'            => SellerApplication::STATUS_REQUIRES_DOCUMENTS,
                    'notes'                 => $documentsRequested,
                    'user_id'               => $reviewerId,
                    'created_at'            => now(),
                ]);
            });

            $this->safeSendMail(
                $application->email,
                new SellerApplicationDocumentsRequestedMail($application, $documentsRequested),
                'docs_requested',
                $application->id
            );

            return ['success' => true, 'message' => 'Solicitud de documentos enviada al seller.'];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::requestDocuments error', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Cambia la aplicación a under_review sin notificar al seller.
     */
    public function markUnderReview(SellerApplication $application, int $reviewerId): array
    {
        if ($application->status !== SellerApplication::STATUS_PENDING
            && $application->status !== SellerApplication::STATUS_REQUIRES_REVIEW) {
            return [
                'success' => false,
                'message' => "Solo se puede pasar a 'en revisión' desde pending o requires_review",
            ];
        }

        try {
            DB::connection('system')->transaction(function () use ($application, $reviewerId) {
                $previousStatus = $application->status;
                $application->update([
                    'status'      => SellerApplication::STATUS_UNDER_REVIEW,
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $application->id,
                    'action'                => SellerApplicationLog::ACTION_STATUS_CHANGED,
                    'old_status'            => $previousStatus,
                    'new_status'            => SellerApplication::STATUS_UNDER_REVIEW,
                    'user_id'               => $reviewerId,
                    'created_at'            => now(),
                ]);
            });

            return ['success' => true, 'message' => 'Marcada como en revisión.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Agrega una nota interna sin cambiar el estado.
     */
    public function addNote(SellerApplication $application, int $reviewerId, string $note): array
    {
        try {
            SellerApplicationLog::create([
                'seller_application_id' => $application->id,
                'action'                => SellerApplicationLog::ACTION_NOTE_ADDED,
                'notes'                 => $note,
                'user_id'               => $reviewerId,
                'created_at'            => now(),
            ]);

            return ['success' => true, 'message' => 'Nota agregada.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Helpers privados
    // ─────────────────────────────────────────────────────────

    /**
     * Revisa si ya existe un tenant con el mismo RUC, email o subdominio
     * al momento de aprobar (doble-check por race conditions entre el
     * momento de la solicitud y la aprobación).
     *
     * @return string|null  mensaje de error o null si todo OK
     */
    private function checkDuplicatesAtApproval(SellerApplication $application): ?string
    {
        $uuid = config('tenant.prefix_database') . '_' . $application->requested_subdomain;

        $websiteExists = DB::connection('system')
            ->table('websites')
            ->where('uuid', $uuid)
            ->exists();
        if ($websiteExists) {
            return "El subdominio '{$application->requested_subdomain}' ya está en uso.";
        }

        $clientWithRuc = DB::connection('system')
            ->table('clients')
            ->where('number', $application->ruc)
            ->exists();
        if ($clientWithRuc) {
            return "Ya existe un tenant con el RUC {$application->ruc}.";
        }

        $clientWithEmail = DB::connection('system')
            ->table('clients')
            ->where('email', strtolower($application->email))
            ->exists();
        if ($clientWithEmail) {
            return "Ya existe un tenant con el email {$application->email}.";
        }

        return null;
    }

    private function buildTenantPayload(
        SellerApplication $application,
        string $temporaryPassword,
        int $planId,
        array $options
    ): array {
        $subDom = strtolower($application->requested_subdomain);

        return [
            'subdomain'           => $subDom,
            'uuid'                => config('tenant.prefix_database') . '_' . $subDom,
            'fqdn'                => $subDom . '.' . config('tenant.app_url_base'),
            'token'               => \Illuminate\Support\Str::random(50),
            'email'               => $application->email,
            'name'                => $application->business_name,
            'number'              => $application->ruc,
            'plan_id'             => $planId,
            'locked_emission'     => false,
            'enable_list_product' => true,
            'price'               => null,
            'plan_period_id'      => null,
            'client_name'         => $application->trade_name ?: $application->business_name,
            'phone_ws'            => $application->phone,
            'contact_email'       => $application->email,
            'certificate_name'    => null,   // el seller sube certificado luego
            'soap_type_id'        => '01',   // default: sistema (sin PSE todavía)
            'soap_send_id'        => null,
            'soap_username'       => null,
            'soap_password'       => null,
            'soap_url'            => null,
            'config_system_env'   => null,
            'password'            => $temporaryPassword,
            'type'                => $options['type'] ?? 'admin',
            'modules'             => $options['modules'] ?? [],
            'levels'              => $options['levels'] ?? [],
            'from_guest_register' => false,
        ];
    }

    private function generateTemporaryPassword(): string
    {
        // 12 chars, mezcla alfanumérica + 2 símbolos
        return \Illuminate\Support\Str::random(10) . rand(10, 99);
    }

    private function safeSendMail(string $to, $mailable, string $context, int $applicationId): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (Exception $e) {
            Log::warning('SellerApplicationService: envío de mail falló', [
                'context'        => $context,
                'application_id' => $applicationId,
                'to'             => $to,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
