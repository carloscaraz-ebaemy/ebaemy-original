<?php

namespace App\Jobs;

use Hyn\Tenancy\Models\Hostname;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateSslCertificate — Genera certificado SSL via certbot para un hostname.
 */
class GenerateSslCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(protected int $hostnameId) {}

    public function handle(): void
    {
        $hostname = Hostname::find($this->hostnameId);
        if (!$hostname) return;

        $domain = $hostname->fqdn;

        try {
            // Ejecutar certbot
            $cmd = sprintf(
                'certbot certonly --nginx --non-interactive --agree-tos --email %s -d %s 2>&1',
                escapeshellarg(config('tenant.ssl_email', 'admin@ebaemy.com')),
                escapeshellarg($domain)
            );

            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0) {
                // Actualizar estado SSL en hostname (si soporta los campos)
                try {
                    $hostname->ssl_status = 'active';
                    $hostname->ssl_expires_at = now()->addDays(90); // Let's Encrypt = 90 días
                    $hostname->save();
                } catch (\Throwable $e) {
                    // Campos pueden no existir aún
                }

                // Recargar nginx
                exec('nginx -s reload 2>&1');

                Log::info("SSL certificate generated for {$domain}");
            } else {
                $errorMsg = implode("\n", $output);
                Log::error("SSL generation failed for {$domain}: {$errorMsg}");

                try {
                    $hostname->ssl_status = 'failed';
                    $hostname->save();
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::error("SSL generation exception for {$domain}: {$e->getMessage()}");
        }
    }
}
