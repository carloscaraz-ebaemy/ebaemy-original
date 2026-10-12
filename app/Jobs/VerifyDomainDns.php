<?php

namespace App\Jobs;

use App\Models\System\DomainVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * VerifyDomainDns — Verifica registro DNS de un dominio personalizado.
 *
 * Métodos soportados:
 *  - dns_cname: Verifica que el CNAME apunte al dominio base
 *  - dns_txt: Verifica registro TXT con token de verificación
 *
 * Uso:
 *   VerifyDomainDns::dispatch($verificationId);
 */
class VerifyDomainDns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(protected int $verificationId) {}

    public function handle(): void
    {
        $verification = DomainVerification::find($this->verificationId);
        if (!$verification || $verification->status === 'verified') {
            return;
        }

        $verification->increment('attempts');

        try {
            $result = match ($verification->method) {
                'dns_cname' => $this->verifyCname($verification),
                'dns_txt'   => $this->verifyTxt($verification),
                default     => false,
            };

            if ($result) {
                $verification->update([
                    'status'      => 'verified',
                    'verified_at' => now(),
                    'last_error'  => null,
                ]);
                Log::info("Domain verified: {$verification->domain}");
            } else {
                $verification->update([
                    'status'     => $verification->attempts >= 10 ? 'failed' : 'pending',
                    'last_error' => 'Registro DNS no encontrado',
                ]);
            }
        } catch (\Throwable $e) {
            $verification->update([
                'status'     => 'pending',
                'last_error' => $e->getMessage(),
            ]);
            Log::warning("DNS verification failed for {$verification->domain}: {$e->getMessage()}");
        }
    }

    /**
     * Verificar CNAME: el dominio debe apuntar al dominio base.
     */
    protected function verifyCname(DomainVerification $v): bool
    {
        $records = dns_get_record($v->domain, DNS_CNAME);
        if (!$records) return false;

        // Destinos que valen: el de verificacion y el dominio base.
        //
        // ESTO APROBABA CUALQUIER COSA. La version anterior comparaba con
        // `str_contains($target, config('tenancy.hostname.default', ''))`, y
        // esa clave de configuracion no existe: vale NULL. En PHP 8
        // `str_contains($loQueSea, '')` devuelve **true**, asi que bastaba con
        // que el dominio tuviera UN CNAME apuntando a donde fuese para darlo
        // por verificado. Y lo que hay al otro lado de esta comprobacion es
        // dar de alta un hostname que sirve la tienda de un tenant.
        //
        // No llego a explotarse —la funcion nunca se uso, 0 filas en
        // `domain_verifications`— pero se iba a encender ahora.
        //
        // Comparacion EXACTA y en minusculas, no `str_contains`: un
        // `verify.ebaemy.com.atacante.net` contiene la cadena buena y no es el
        // destino bueno.
        $destinosValidos = array_filter(array_map(
            fn ($d) => strtolower(rtrim((string) $d, '.')),
            [
                $v->getDnsCnameTarget(),
                config('tenant.base_domain'),
            ]
        ));

        foreach ($records as $record) {
            $target = strtolower(rtrim($record['target'] ?? '', '.'));
            if ($target !== '' && in_array($target, $destinosValidos, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar TXT: debe existir un registro TXT con el token.
     */
    protected function verifyTxt(DomainVerification $v): bool
    {
        $records = dns_get_record($v->domain, DNS_TXT);
        if (!$records) return false;

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if ($txt === $v->verification_token) {
                return true;
            }
        }

        return false;
    }
}
