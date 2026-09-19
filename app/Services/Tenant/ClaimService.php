<?php

namespace App\Services\Tenant;

use App\Mail\Tenant\ClaimAnsweredMail;
use App\Mail\Tenant\ClaimRegisteredCustomerMail;
use App\Mail\Tenant\ClaimRegisteredTenantMail;
use App\Models\Tenant\Claim;
use App\Models\Tenant\ClaimEvent;
use App\Models\Tenant\Company;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Establishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Único punto de alta y de cambio de estado del Libro de Reclamaciones.
 *
 * Todo lo que escribe en `claims` pasa por aquí: el controlador público, el
 * panel del tenant y cualquier reenvío de correo. Así el correlativo, la
 * bitácora y el plazo no dependen de que cada llamador se acuerde.
 *
 * Multi-tenant: no recibe ni acepta un tenant como parámetro a propósito.
 * Trabaja siempre sobre la conexión `tenant` activa, que es la del dominio por
 * el que entró la petición. Un reclamo no puede acabar en otra BD.
 */
class ClaimService
{
    /**
     * Datos oficiales del proveedor, tal como deben figurar en la hoja.
     * Se leen de donde ya viven (companies / establishments /
     * configuration_ecommerce); el Libro no duplica ninguno.
     */
    public function provider(): array
    {
        $company       = Company::first();
        $establishment = Establishment::first();
        $config        = ConfigurationEcommerce::firstCached();

        return [
            'name'       => optional($company)->name ?? '',
            'trade_name' => optional($company)->trade_name ?: optional($company)->name,
            'ruc'        => optional($company)->number ?? '',
            'address'    => optional($config)->information_contact_address
                            ?: (optional($establishment)->address ?? ''),
            'phone'      => optional($config)->information_contact_phone
                            ?: (optional($establishment)->telephone ?? ''),
            'email'      => optional($config)->information_contact_email
                            ?: (optional($establishment)->email ?? ''),
            'logo'       => optional($company)->logo,
            'domain'     => request()->getSchemeAndHttpHost(),
        ];
    }

    /**
     * Buzón del tenant que recibe el reclamo.
     *
     * Cadena explícita: correo del Libro → contacto del ecommerce → correo del
     * establecimiento. Si no hay ninguno devuelve null y el reclamo queda
     * marcado `missing_recipient`: preferimos un aviso visible en el panel
     * antes que mandar los datos personales de un consumidor a un buzón
     * inventado.
     */
    public function recipientEmail(): ?string
    {
        $config        = ConfigurationEcommerce::firstCached();
        $establishment = Establishment::first();

        $candidates = [
            optional($config)->claims_email,
            optional($config)->information_contact_email,
            optional($establishment)->email,
        ];

        foreach ($candidates as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Registra la hoja y devuelve el reclamo ya persistido.
     *
     * El correlativo se toma dentro de la transacción con `lockForUpdate()`
     * sobre las filas del año: dos consumidores enviando a la vez no pueden
     * llevarse el mismo número, que es justo lo que el formato no perdona.
     */
    public function create(array $data): Claim
    {
        $now  = now();
        $year = (int) $now->format('Y');

        $claim = DB::connection('tenant')->transaction(function () use ($data, $now, $year) {
            $last = Claim::withTrashed()
                ->where('year', $year)
                ->lockForUpdate()
                ->max('correlative');

            $correlative = ((int) $last) + 1;

            return Claim::create(array_merge($data, [
                'year'         => $year,
                'correlative'  => $correlative,
                'code'         => sprintf('LR-%d-%06d', $year, $correlative),
                // 32 bytes al azar: el código es correlativo y por tanto
                // adivinable, así que no puede ser la llave de consulta.
                'public_token' => Str::random(48),
                'status'       => Claim::STATUS_REGISTERED,
                'due_date'     => Claim::dueDateFrom($now)->toDateString(),
            ]));
        });

        $this->log($claim, ClaimEvent::ACTION_CREATED, [
            'to_status'   => Claim::STATUS_REGISTERED,
            'description' => 'Hoja registrada desde la tienda',
        ]);

        return $claim;
    }

    // ── Correos ───────────────────────────────────────────────────────────

    /**
     * Envía las dos copias del registro: consumidor y tenant.
     *
     * Se llama SIEMPRE después del commit y nunca revierte nada: si el correo
     * falla, el reclamo ya existe y lo que queda es un estado de envío que el
     * panel puede reintentar. Un fallo de SMTP no puede borrar una hoja de
     * reclamación.
     */
    public function sendRegistrationMails(Claim $claim): void
    {
        $this->sendCustomerMail($claim);
        $this->sendTenantMail($claim);
    }

    public function sendCustomerMail(Claim $claim): bool
    {
        return $this->deliver($claim, 'customer_mail', $claim->email, function () use ($claim) {
            return new ClaimRegisteredCustomerMail($claim, $this->provider());
        });
    }

    public function sendTenantMail(Claim $claim): bool
    {
        $to = $this->recipientEmail();

        if (!$to) {
            $claim->forceFill([
                'tenant_mail_status' => 'missing_recipient',
                'tenant_mail_error'  => 'No hay correo configurado para el Libro de Reclamaciones.',
            ])->save();

            $this->log($claim, ClaimEvent::ACTION_MAIL_FAILED, [
                'description' => 'Sin buzón configurado para reclamaciones; no se envió nada.',
            ]);

            return false;
        }

        $claim->forceFill(['tenant_mail_to' => $to])->save();

        return $this->deliver($claim, 'tenant_mail', $to, function () use ($claim) {
            return new ClaimRegisteredTenantMail($claim, $this->provider());
        });
    }

    public function sendAnswerMail(Claim $claim): bool
    {
        return $this->deliver($claim, 'answer_mail', $claim->email, function () use ($claim) {
            return new ClaimAnsweredMail($claim, $this->provider());
        });
    }

    /**
     * Envía y deja constancia del resultado en las tres columnas del canal
     * (`*_status`, `*_error`, `*_sent_at`) y en la bitácora.
     */
    private function deliver(Claim $claim, string $channel, ?string $to, callable $factory): bool
    {
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $claim->forceFill([
                $channel . '_status' => 'invalid_address',
                $channel . '_error'  => 'Dirección de correo no válida.',
            ])->save();

            return false;
        }

        try {
            Mail::to($to)->send($factory());

            $claim->forceFill([
                $channel . '_status'  => 'sent',
                $channel . '_error'   => null,
                $channel . '_sent_at' => now(),
            ])->save();

            $this->log($claim, ClaimEvent::ACTION_MAIL_SENT, [
                'description' => 'Correo enviado (' . $channel . ')',
                // El destinatario del tenant sí se registra; el del consumidor
                // ya está en la propia hoja y no se repite en la bitácora.
                'payload'     => ['channel' => $channel],
            ]);

            return true;
        } catch (\Throwable $e) {
            $claim->forceFill([
                $channel . '_status' => 'failed',
                $channel . '_error'  => Str::limit($e->getMessage(), 500),
            ])->save();

            // El log técnico lleva el código, no los datos personales.
            Log::error('Libro de Reclamaciones: fallo de correo', [
                'claim'   => $claim->code,
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);

            $this->log($claim, ClaimEvent::ACTION_MAIL_FAILED, [
                'description' => 'Fallo al enviar (' . $channel . ')',
                'payload'     => ['channel' => $channel],
            ]);

            return false;
        }
    }

    // ── Gestión desde el panel ────────────────────────────────────────────

    public function changeStatus(Claim $claim, string $status, $user = null): Claim
    {
        if (!array_key_exists($status, Claim::STATUSES) || $status === $claim->status) {
            return $claim;
        }

        $from = $claim->status;
        $claim->forceFill(['status' => $status])->save();

        $this->log($claim, ClaimEvent::ACTION_STATUS_CHANGED, [
            'from_status'  => $from,
            'to_status'    => $status,
            'description'  => 'Estado: ' . (Claim::STATUSES[$from] ?? $from) . ' → ' . Claim::STATUSES[$status],
        ], $user);

        return $claim;
    }

    /**
     * Respuesta del proveedor. Guarda, deja historial y manda la copia al
     * consumidor; el estado pasa a Respondido salvo que ya estuviera cerrado.
     */
    public function answer(Claim $claim, array $data, $user = null): Claim
    {
        $from = $claim->status;

        $claim->forceFill([
            'answer'              => $data['answer'],
            'actions_taken'       => $data['actions_taken'] ?? null,
            'request_accepted'    => $data['request_accepted'] ?? null,
            'rejection_grounds'   => $data['rejection_grounds'] ?? null,
            'answered_at'         => now(),
            'answered_by_user_id' => $user->id ?? null,
            'status'              => Claim::STATUS_ANSWERED,
        ])->save();

        $this->log($claim, ClaimEvent::ACTION_ANSWERED, [
            'from_status' => $from,
            'to_status'   => Claim::STATUS_ANSWERED,
            'description' => 'Respuesta del proveedor registrada',
            'payload'     => [
                'request_accepted' => $claim->request_accepted,
            ],
        ], $user);

        $this->sendAnswerMail($claim);

        return $claim;
    }

    /**
     * Apunte en la bitácora. Nunca se actualiza ni se borra una fila de
     * `claim_events`: sólo se añaden.
     */
    public function log(Claim $claim, string $action, array $attributes = [], $user = null): ClaimEvent
    {
        $user = $user ?: auth()->user();

        return ClaimEvent::create(array_merge([
            'claim_id'  => $claim->id,
            'action'    => $action,
            'user_id'   => $user->id ?? null,
            'user_name' => $user->name ?? null,
            'ip'        => request()->ip(),
        ], $attributes));
    }
}
