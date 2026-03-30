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

        $baseDomain = config('tenancy.hostname.default', '');

        foreach ($records as $record) {
            $target = rtrim($record['target'] ?? '', '.');
            if (str_contains($target, $baseDomain)) {
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
