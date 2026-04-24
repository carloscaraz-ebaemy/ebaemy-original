<?php

namespace App\Services\System;

use App\Mail\SellerActivationApprovedMail;
use App\Mail\SellerApplicationApprovedMail;
use App\Mail\SellerApplicationDocumentsRequestedMail;
use App\Mail\SellerApplicationReceivedMail;
use App\Mail\SellerApplicationRejectedMail;
use App\Models\System\Client;
use App\Models\System\Module;
use App\Models\System\ModuleLevel;
use App\Models\System\Plan;
use App\Models\System\SellerApplication;
use App\Models\System\SellerApplicationLog;
use Hyn\Tenancy\Environment;
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
    /**
     * Paquete de módulos "solo tienda virtual" — lo que un seller aprobado
     * necesita para operar su ecommerce sin facturación electrónica.
     *
     * Se referencian por `value` (no por ID numérico) porque los IDs de
     * `modules` pueden variar entre entornos y migraciones.
     *
     * Incluye:
     *   - dashboard       → Home del panel
     *   - persons         → Clientes (auto-creados por órdenes del marketplace)
     *   - items           → Productos
     *   - configuration   → Configuración básica (incluye Ecommerce > ajustes)
     *   - ecommerce       → Tienda virtual (módulo core del seller)
     *
     * EXCLUYE por defecto:
     *   - documents, pos, purchases, advanced     → facturación
     *   - accounting, finance, reports            → gestión financiera
     *   - preventa, guia, comprobante             → operaciones adicionales
     *
     * Si el seller solicita facturación, el SuperAdmin activa esos módulos
     * manualmente desde el panel de Clientes (/clients/{id}/domains-panel).
     */
    public const SELLER_DEFAULT_MODULES = [
        'dashboard',
        'persons',
        'items',
        'configuration',
        'ecommerce',
    ];

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
            // 0. Detectar RUC/email/subdominio ya registrados ANTES de consultar SUNAT.
            //    Evitamos crear solicitudes duplicadas o conflictivas.
            $existing = $this->findExistingRegistration([
                'ruc'       => $input['ruc'] ?? null,
                'email'     => $input['email'] ?? null,
                'subdomain' => $input['requested_subdomain'] ?? null,
            ]);
            if ($existing !== null) {
                return array_merge([
                    'success' => false,
                ], $existing);
            }

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
     * Crea una solicitud de ACTIVACIÓN de tienda virtual para un tenant
     * que ya es cliente (usa facturación/POS) pero sin marketplace_enabled.
     *
     * A diferencia de createApplication, este flujo:
     *   - NO crea tenant nuevo al aprobarse (sería duplicar)
     *   - NO pide subdominio ni contraseña (ya existen)
     *   - Al aprobar, solo activa flags del client existente + agrega módulo
     *     ecommerce al usuario admin del tenant
     *
     * Se valida que el RUC corresponda efectivamente a un client existente
     * SIN marketplace activo. Si no es el caso, se rechaza.
     */
    public function createActivationRequest(array $input): array
    {
        try {
            $ruc = trim((string) ($input['ruc'] ?? ''));

            if (!preg_match('/^(10|15|17|20)\d{9}$/', $ruc)) {
                return [
                    'success' => false,
                    'message' => 'RUC inválido.',
                ];
            }

            // Verificar que el RUC corresponda a un cliente SIN marketplace.
            $client = DB::connection('system')->table('clients')
                ->where('number', $ruc)
                ->select(['id', 'name', 'email', 'marketplace_enabled', 'seller_status'])
                ->first();

            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'El RUC no corresponde a un cliente existente. Si quieres crear una tienda nueva, usa el formulario de registro de vendedor.',
                ];
            }

            $alreadyActive = (bool) $client->marketplace_enabled
                          || $client->seller_status === 'active';
            if ($alreadyActive) {
                return [
                    'success' => false,
                    'message' => 'Tu tienda virtual ya está activa. Inicia sesión desde el subdominio de tu empresa.',
                ];
            }

            // Evitar solicitudes duplicadas de activación
            $duplicate = SellerApplication::query()
                ->active()
                ->where('ruc', $ruc)
                ->exists();
            if ($duplicate) {
                return [
                    'success' => false,
                    'message' => 'Ya tienes una solicitud de activación en revisión. Te notificaremos por correo cuando haya novedades.',
                ];
            }

            $application = DB::connection('system')->transaction(function () use ($input, $client, $ruc) {
                $app = SellerApplication::create([
                    'ruc'                           => $ruc,
                    'business_name'                 => $client->name,
                    'legal_representative_name'     => $input['legal_representative_name'] ?? '',
                    'legal_representative_dni'      => $input['legal_representative_dni'] ?? '',
                    'legal_representative_position' => $input['legal_representative_position'] ?? null,
                    'email'                         => strtolower($input['email'] ?? ''),
                    'phone'                         => $input['phone'] ?? '',
                    // Activación: NO hay subdominio nuevo. Usamos un valor
                    // centinela único para no romper la columna unique.
                    'requested_subdomain'           => 'activation-' . $client->id,
                    'store_description'             => $input['activation_reason'] ?? null,
                    'logo_path'                     => $input['logo_path'] ?? null,
                    // No pedimos contraseña — el tenant ya la tiene. Guardamos
                    // un hash vacío marker porque la columna es NOT NULL.
                    'password_hash'                 => '__activation_request__',
                    'is_activation_request'         => true,
                    'status'                        => SellerApplication::STATUS_PENDING,
                    'tracking_token'                => SellerApplication::generateTrackingToken(),
                    'tenant_id'                     => $client->id, // ya lo conocemos
                    'source_ip'                     => $input['source_ip'] ?? null,
                    'source_ua'                     => $input['source_ua'] ?? null,
                ]);

                SellerApplicationLog::create([
                    'seller_application_id' => $app->id,
                    'action'                => SellerApplicationLog::ACTION_CREATED,
                    'new_status'            => SellerApplication::STATUS_PENDING,
                    'notes'                 => "Solicitud de activación de tienda virtual para client #{$client->id}",
                    'user_id'               => null,
                    'created_at'            => now(),
                ]);

                return $app;
            });

            $this->safeSendMail(
                $application->email,
                new SellerApplicationReceivedMail($application),
                'activation_received',
                $application->id
            );

            return [
                'success'     => true,
                'message'     => 'Solicitud de activación registrada correctamente',
                'application' => $application,
            ];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::createActivationRequest error', ['error' => $e->getMessage()]);
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
     * @param array             $options     keys opcionales:
     *                                       - modules, levels, type
     *                                       - email_override    (string) corrige email del seller
     *                                       - password_override (string plain) reemplaza el hash guardado
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

        // Si es solicitud de activación, no creamos tenant nuevo —
        // solo habilitamos marketplace en el client existente.
        if ($application->isActivationRequest()) {
            return $this->approveActivation($application, $reviewerId);
        }

        // Aplicar overrides del SuperAdmin ANTES del check de duplicados
        // (si el admin cambió el email, los duplicados se chequean contra
        // el nuevo). Persiste en la propia SellerApplication para que el
        // historial refleje lo que realmente se usó.
        $this->applyApproverOverrides($application, $options, $reviewerId);

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
            // Resolver qué módulos recibirá el usuario admin del tenant.
            // Si el SuperAdmin no mandó listas explícitas, aplicamos el
            // paquete "solo tienda virtual" (sin facturación).
            $resolvedPermissions = $this->resolveSellerPermissions($options);

            // Reutilizar la contraseña que el seller eligió al registrarse
            // (su password_hash está guardado en seller_applications).
            // Si el SuperAdmin ingresó password_override, applyApproverOverrides
            // ya reemplazó ese hash arriba.
            $payload = $this->buildTenantPayload(
                $application,
                $planId,
                array_merge($options, [
                    'modules' => $resolvedPermissions['modules'],
                    'levels'  => $resolvedPermissions['levels'],
                ])
            );

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

            // Si el seller subió logo en el form, copiarlo al storage del
            // tenant + actualizar companies.logo para que aparezca en su
            // panel desde el día uno. Best-effort — si falla no revierte
            // la creación del tenant (el seller puede subirlo luego).
            $this->copyLogoToTenantIfPresent($application, $client);

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

            // Mail al seller confirmando aprobación.
            // NO enviamos contraseña: el seller la eligió al registrarse y
            // ya la conoce. El mail solo muestra la URL de su tienda.
            $this->safeSendMail(
                $application->email,
                new SellerApplicationApprovedMail($application),
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
     * Aprueba una solicitud de ACTIVACIÓN: habilita marketplace en un
     * tenant que ya existe (no crea tenant nuevo).
     *
     * Pasos:
     *   1. Resolver el Client por RUC o por tenant_id ya vinculado
     *   2. Verificar que no está activo ya (race-safe)
     *   3. Setear flags marketplace_enabled/seller_status
     *   4. Agregar módulo ecommerce al usuario admin del tenant
     *   5. Actualizar SellerApplication + log + mail al seller
     */
    private function approveActivation(SellerApplication $application, int $reviewerId): array
    {
        try {
            $client = $application->tenant_id
                ? Client::query()->find($application->tenant_id)
                : Client::query()->where('number', $application->ruc)->first();

            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'El cliente al que intentas activarle la tienda ya no existe en el sistema.',
                ];
            }

            if ($client->marketplace_enabled || $client->seller_status === 'active') {
                return [
                    'success' => false,
                    'message' => 'La tienda virtual de este cliente ya está activa.',
                ];
            }

            DB::connection('system')->transaction(function () use ($application, $client, $reviewerId) {
                $client->update([
                    'is_verified'             => true,
                    'verified_at'             => $client->verified_at ?: now(),
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
                    'notes'                 => "Activación de tienda virtual para client #{$client->id} ({$client->name}).",
                    'user_id'               => $reviewerId,
                    'created_at'            => now(),
                ]);
            });

            // Activar módulo ecommerce en la BD del tenant (fuera de la
            // transacción system — es otra conexión).
            $this->ensureEcommerceModuleForTenantUser($client);

            // Si el seller envió un logo con la solicitud de activación,
            // copiarlo al tenant. Respetamos el logo actual si el tenant
            // ya tenía uno (no sobreescribir sin pedir).
            $this->copyLogoToTenantIfPresent($application, $client, false);

            // Mail específico de activación (distinto del approved normal:
            // no entrega credenciales, confirma que ya puede usar su tienda).
            $this->safeSendMail(
                $application->email,
                new SellerActivationApprovedMail($application, $client),
                'activation_approved',
                $application->id
            );

            return [
                'success' => true,
                'message' => 'Tienda virtual activada correctamente para el tenant existente.',
                'tenant'  => $client,
            ];
        } catch (Exception $e) {
            Log::error('SellerApplicationService::approveActivation error', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'Error al activar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Copia el archivo de logo subido por el seller (storage/app/public/
     * seller-logos/*) al directorio de logos del tenant (storage/app/
     * public/uploads/logos/logo_{ruc}.{ext}) y actualiza companies.logo.
     *
     * Best-effort: si falla (archivo no existe, error de disco, etc.) se
     * loguea warning y continúa — el seller puede subir el logo después
     * desde su panel.
     *
     * @param bool $overwriteIfExists  Si false y el tenant ya tiene logo,
     *                                 respeta el existente. Default true
     *                                 (onboarding nuevo no tiene nada).
     */
    private function copyLogoToTenantIfPresent(
        SellerApplication $application,
        Client $client,
        bool $overwriteIfExists = true
    ): void {
        try {
            $logoPath = $application->logo_path;
            if (empty($logoPath)) {
                return;
            }

            // Ruta absoluta del archivo subido por el seller
            $sourceAbs = storage_path('app/public/' . $logoPath);
            if (!is_file($sourceAbs)) {
                Log::warning('copyLogoToTenant: archivo fuente no encontrado', [
                    'logo_path' => $logoPath,
                    'abs'       => $sourceAbs,
                ]);
                return;
            }

            // Contexto del tenant para leer/escribir companies + su disco
            $tenancy = app(\Hyn\Tenancy\Environment::class);
            $website = $client->hostname?->website ?? optional($client->hostname()->first())->website;
            if (!$website) {
                Log::warning('copyLogoToTenant: sin website', ['client_id' => $client->id]);
                return;
            }
            $tenancy->tenant($website);

            // Respetar logo existente si el caller lo pidió
            if (!$overwriteIfExists) {
                $current = DB::connection('tenant')->table('companies')->value('logo');
                if (!empty($current)) {
                    Log::info('copyLogoToTenant: tenant ya tiene logo, respetando', [
                        'client_id' => $client->id,
                        'current'   => $current,
                    ]);
                    return;
                }
            }

            $ext = strtolower(pathinfo($sourceAbs, PATHINFO_EXTENSION) ?: 'png');
            $newName = 'logo_' . $application->ruc . '.' . $ext;
            $destRel = 'public/uploads/logos/' . $newName;
            $destAbs = storage_path('app/' . $destRel);

            // Asegurar directorio destino (multi-tenant comparte el disco
            // public de Hyn — mismo disco físico para todos los tenants)
            if (!is_dir(dirname($destAbs))) {
                mkdir(dirname($destAbs), 0755, true);
            }

            if (!copy($sourceAbs, $destAbs)) {
                Log::warning('copyLogoToTenant: copy() falló', [
                    'src' => $sourceAbs,
                    'dst' => $destAbs,
                ]);
                return;
            }

            DB::connection('tenant')->table('companies')->update(['logo' => $newName]);

            Log::info('copyLogoToTenant: logo copiado y companies.logo actualizado', [
                'client_id' => $client->id,
                'ruc'       => $application->ruc,
                'logo'      => $newName,
            ]);
        } catch (Exception $e) {
            Log::warning('copyLogoToTenant: excepción', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Se asegura de que el usuario admin del tenant tenga el módulo
     * `ecommerce` activo (y sus levels). Si ya lo tiene, no duplica.
     *
     * Se ejecuta en la conexión tenant del client — usa Hyn\Environment
     * para cambiar contexto igual que TenantCreationService.
     */
    private function ensureEcommerceModuleForTenantUser(Client $client): void
    {
        try {
            $ecommerceModule = Module::query()->where('value', 'ecommerce')->first();
            if (!$ecommerceModule) {
                Log::warning('ensureEcommerceModuleForTenantUser: módulo ecommerce no encontrado en system');
                return;
            }

            $ecommerceLevelIds = ModuleLevel::query()
                ->where('module_id', $ecommerceModule->id)
                ->pluck('id')
                ->toArray();

            $tenancy = app(Environment::class);
            $website = $client->hostname?->website ?? optional($client->hostname()->first())->website;
            if (!$website) {
                Log::warning('ensureEcommerceModuleForTenantUser: sin website asociado', ['client_id' => $client->id]);
                return;
            }
            $tenancy->tenant($website);

            // Usuario admin del tenant (primer usuario creado con type=admin).
            $adminUser = DB::connection('tenant')->table('users')
                ->where('type', 'admin')
                ->orderBy('id', 'asc')
                ->select(['id'])
                ->first();
            if (!$adminUser) {
                Log::warning('ensureEcommerceModuleForTenantUser: sin usuario admin en tenant', ['client_id' => $client->id]);
                return;
            }

            $userId = $adminUser->id;

            // Insertar módulo (si no existe ya)
            $hasModule = DB::connection('tenant')->table('module_user')
                ->where('module_id', $ecommerceModule->id)
                ->where('user_id', $userId)
                ->exists();
            if (!$hasModule) {
                DB::connection('tenant')->table('module_user')->insert([
                    'module_id' => $ecommerceModule->id,
                    'user_id'   => $userId,
                ]);
            }

            // Insertar levels faltantes
            if (!empty($ecommerceLevelIds)) {
                $existingLevels = DB::connection('tenant')->table('module_level_user')
                    ->where('user_id', $userId)
                    ->whereIn('module_level_id', $ecommerceLevelIds)
                    ->pluck('module_level_id')
                    ->toArray();

                $missingLevels = array_diff($ecommerceLevelIds, $existingLevels);
                if (!empty($missingLevels)) {
                    $rows = [];
                    foreach ($missingLevels as $levelId) {
                        $rows[] = [
                            'module_level_id' => $levelId,
                            'user_id'         => $userId,
                        ];
                    }
                    DB::connection('tenant')->table('module_level_user')->insert($rows);
                }
            }

            Log::info('ensureEcommerceModuleForTenantUser: módulo ecommerce asegurado', [
                'client_id'  => $client->id,
                'tenant_uuid'=> $website->uuid,
                'user_id'    => $userId,
            ]);
        } catch (Exception $e) {
            // No lanzamos — la aprobación en system ya sucedió. El SuperAdmin
            // puede activar el módulo manualmente desde el panel del tenant
            // si este paso fallara.
            Log::error('ensureEcommerceModuleForTenantUser failed', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
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
     * Busca si un RUC, email o subdominio ya están registrados en el sistema
     * — sea como tenant activo o como solicitud de seller en pipeline.
     *
     * Retorna null si nada existe, o un array con:
     *   [
     *     'type'    => 'tenant' | 'application',
     *     'message' => string legible para el seller (sin exponer subdominio
     *                  real por privacidad del tenant preexistente)
     *     'detail'  => string interno para logs/debug
     *   ]
     *
     * Se usa tanto en createApplication (server-side al enviar form) como
     * en validateRuc (AJAX al tipear) para avisar al seller antes de que
     * complete toda la solicitud.
     */
    public function findExistingRegistration(array $criteria): ?array
    {
        $ruc       = isset($criteria['ruc'])       ? trim((string) $criteria['ruc'])       : null;
        $email     = isset($criteria['email'])     ? strtolower(trim((string) $criteria['email'])) : null;
        $subdomain = isset($criteria['subdomain']) ? strtolower(trim((string) $criteria['subdomain'])) : null;

        // ── 1. Tenant (clients) ya existente ─────────────────
        // Distinguimos dos subtipos:
        //   - active_seller      → el tenant ya tiene marketplace_enabled=true
        //                          (tienda virtual activa). Debe iniciar sesión.
        //   - needs_activation   → es cliente pero sin marketplace habilitado
        //                          (p.ej. solo usa facturación/POS). Debe
        //                          solicitar activación al equipo de soporte.
        $clientsTable = DB::connection('system')->table('clients');

        if (!empty($ruc)) {
            $client = $clientsTable->where('number', $ruc)
                ->select(['id', 'marketplace_enabled', 'seller_status'])
                ->first();
            if ($client) {
                return $this->buildTenantConflict($client, 'RUC', $ruc);
            }
        }

        if (!empty($email)) {
            $client = $clientsTable->whereRaw('LOWER(email) = ?', [$email])
                ->select(['id', 'marketplace_enabled', 'seller_status'])
                ->first();
            if ($client) {
                return $this->buildTenantConflict($client, 'correo', $email);
            }
        }

        if (!empty($subdomain)) {
            $uuid = config('tenant.prefix_database') . '_' . $subdomain;
            $websiteTaken = DB::connection('system')->table('websites')->where('uuid', $uuid)->exists();
            if ($websiteTaken) {
                return [
                    'type'    => 'tenant',
                    'subtype' => 'active_seller',
                    'message' => "El subdominio '{$subdomain}' ya está en uso por otra tienda.",
                    'detail'  => "Subdomain {$subdomain} uuid ya existe",
                ];
            }
        }

        // ── 2. Solicitud de seller en pipeline activo ────────
        $activeStatuses = SellerApplication::ACTIVE_STATUSES;

        if (!empty($ruc)) {
            $app = SellerApplication::query()
                ->whereIn('status', $activeStatuses)
                ->where('ruc', $ruc)
                ->first();
            if ($app) {
                return [
                    'type'    => 'application',
                    'subtype' => 'active_application',
                    'message' => $this->messageForExistingApplication($app),
                    'detail'  => "RUC {$ruc} tiene solicitud {$app->id} en estado {$app->status}",
                ];
            }
        }

        if (!empty($subdomain)) {
            $app = SellerApplication::query()
                ->whereIn('status', $activeStatuses)
                ->where('requested_subdomain', $subdomain)
                ->first();
            if ($app) {
                return [
                    'type'    => 'application',
                    'subtype' => 'active_application',
                    'message' => "El subdominio '{$subdomain}' está reservado por otra solicitud en revisión.",
                    'detail'  => "Subdomain {$subdomain} reservado por solicitud {$app->id}",
                ];
            }
        }

        return null;
    }

    /**
     * Construye el payload de conflicto para un tenant preexistente
     * distinguiendo si ya tiene marketplace activo o solo es cliente de
     * otros módulos (facturación/POS sin ecommerce).
     */
    private function buildTenantConflict(object $client, string $field, string $value): array
    {
        $hasMarketplace = (bool) ($client->marketplace_enabled ?? false)
                       || ($client->seller_status ?? null) === 'active';

        if ($hasMarketplace) {
            return [
                'type'    => 'tenant',
                'subtype' => 'active_seller',
                'message' => "Ya existe una tienda registrada con este {$field}. Inicia sesión desde el subdominio de tu empresa.",
                'detail'  => "{$field} {$value} ya es seller activo (client #{$client->id})",
            ];
        }

        return [
            'type'    => 'tenant',
            'subtype' => 'needs_activation',
            'message' => "Ya eres cliente de " . config('app.name', 'ebaemy')
                       . " con este {$field}, pero tu tienda virtual no está habilitada. "
                       . "Puedes solicitar la activación al equipo de soporte.",
            'detail'  => "{$field} {$value} es client #{$client->id} sin marketplace_enabled",
        ];
    }

    /**
     * Mensaje legible según el estado de la solicitud existente. No exponemos
     * el tracking_token por privacidad — si el seller es el dueño, puede
     * pedir reenvío del link a su correo desde el frontend.
     */
    private function messageForExistingApplication(SellerApplication $app): string
    {
        switch ($app->status) {
            case SellerApplication::STATUS_APPROVED:
                return 'Ya existe una tienda aprobada con este RUC. Inicia sesión desde el subdominio de tu empresa.';
            case SellerApplication::STATUS_REQUIRES_DOCUMENTS:
                return 'Tienes una solicitud en revisión que requiere documentos adicionales. Revisa el correo que te enviamos.';
            case SellerApplication::STATUS_REQUIRES_REVIEW:
                return 'Ya tienes una solicitud en revisión manual por nuestro equipo.';
            default:
                return 'Ya tienes una solicitud de vendedor en revisión con este RUC. Te notificaremos por correo cuando haya novedades.';
        }
    }

    /**
     * Aplica overrides de email/password del SuperAdmin al SellerApplication.
     * Persiste los cambios en BD antes de crear el tenant, de modo que el
     * tenant se crea con los datos nuevos y el historial queda consistente.
     *
     * Solo escribe cambios si realmente hay algo distinto que guardar —
     * si el SuperAdmin no envió overrides, no toca la aplicación.
     */
    private function applyApproverOverrides(SellerApplication $application, array $options, int $reviewerId): void
    {
        $changes = [];
        $logMessages = [];

        $emailOverride = isset($options['email_override']) ? trim($options['email_override']) : null;
        if (!empty($emailOverride) && strtolower($emailOverride) !== strtolower((string) $application->email)) {
            $changes['email'] = strtolower($emailOverride);
            $logMessages[] = "Email corregido por SuperAdmin: {$application->email} → {$emailOverride}";
        }

        $passwordOverride = $options['password_override'] ?? null;
        if (!empty($passwordOverride)) {
            $changes['password_hash'] = Hash::make($passwordOverride);
            $logMessages[] = 'Contraseña reemplazada por SuperAdmin antes de aprobar.';
        }

        if (empty($changes)) {
            return;
        }

        $application->update($changes);

        // Registrar la acción en el historial con motivo (sin exponer la
        // contraseña nueva por razones obvias).
        SellerApplicationLog::create([
            'seller_application_id' => $application->id,
            'action'                => SellerApplicationLog::ACTION_NOTE_ADDED,
            'notes'                 => implode("\n", $logMessages),
            'user_id'               => $reviewerId,
            'created_at'            => now(),
        ]);
    }

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

    /**
     * Decide qué módulos + levels se asignan al usuario admin del tenant
     * cuando se aprueba una solicitud.
     *
     * Política por defecto: "solo tienda virtual" — ver constante
     * SELLER_DEFAULT_MODULES arriba.
     *
     * Si el SuperAdmin envía `modules` o `levels` explícitamente desde
     * el panel (ApproveSellerApplicationRequest), respetamos su elección
     * — útil si quiere otorgar facturación directo en la aprobación.
     *
     * @return array{modules: int[], levels: int[]}
     */
    private function resolveSellerPermissions(array $options): array
    {
        $hasExplicitModules = !empty($options['modules']);
        $hasExplicitLevels  = !empty($options['levels']);

        if ($hasExplicitModules || $hasExplicitLevels) {
            return [
                'modules' => $options['modules'] ?? [],
                'levels'  => $options['levels']  ?? [],
            ];
        }

        $moduleIds = Module::query()
            ->whereIn('value', self::SELLER_DEFAULT_MODULES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($moduleIds)) {
            // Fallback defensivo: si la tabla modules no tuviera los values
            // esperados (entorno atípico), no bloqueamos el approve.
            // El SuperAdmin puede activar módulos manualmente luego desde
            // /clients/{id}/domains-panel.
            Log::warning('resolveSellerPermissions: no se encontraron módulos seller default', [
                'expected_values' => self::SELLER_DEFAULT_MODULES,
            ]);
            return ['modules' => [], 'levels' => []];
        }

        $levelIds = ModuleLevel::query()
            ->whereIn('module_id', $moduleIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return [
            'modules' => $moduleIds,
            'levels'  => $levelIds,
        ];
    }

    private function buildTenantPayload(
        SellerApplication $application,
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
            // Reusar el hash que el seller generó al registrarse —
            // TenantCreationService detecta password_hash y lo inserta
            // directo sin re-hashear (ver doc del service).
            'password_hash'       => $application->password_hash,
            'type'                => $options['type'] ?? 'admin',
            'modules'             => $options['modules'] ?? [],
            'levels'              => $options['levels'] ?? [],
            'from_guest_register' => false,
        ];
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
