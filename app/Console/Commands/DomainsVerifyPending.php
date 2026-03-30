<?php

namespace App\Console\Commands;

use App\Jobs\VerifyDomainDns;
use App\Models\System\DomainVerification;
use Illuminate\Console\Command;

/**
 * Verificar todos los dominios pendientes.
 * Programar cada 30 minutos en Kernel.
 *
 * Uso: php artisan domains:verify-pending
 */
class DomainsVerifyPending extends Command
{
    protected $signature = 'domains:verify-pending';
    protected $description = 'Verificar DNS de todos los dominios pendientes';

    public function handle(): int
    {
        $pending = DomainVerification::pending()
            ->where('attempts', '<', 20)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No hay dominios pendientes de verificación.');
            return 0;
        }

        $this->info("Verificando {$pending->count()} dominios...");

        foreach ($pending as $verification) {
            VerifyDomainDns::dispatch($verification->id);
            $this->line("  Despachado: {$verification->domain} (intento #{$verification->attempts})");
        }

        $this->info('Jobs despachados.');
        return 0;
    }
}
