<?php

namespace App\Console\Commands;

use App\Models\Tenant\AbandonedCart;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Elimina carritos expirados o recuperados más antiguos de N días.
 * Se ejecuta diariamente a las 3am.
 *
 * Uso:
 *   php artisan abandoned-carts:purge            -- borra los expirados (default 7 días)
 *   php artisan abandoned-carts:purge --days=30  -- retiene 30 días de historial
 */
class PurgeAbandonedCarts extends Command
{
    protected $signature   = 'abandoned-carts:purge {--days=7 : Días de retención de carritos expirados}';
    protected $description = 'Elimina carritos abandonados expirados o ya recuperados';

    public function handle(Environment $tenancy): int
    {
        $days  = (int) $this->option('days');
        $total = 0;

        Website::chunk(20, function ($websites) use ($tenancy, $days, &$total) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $deleted = AbandonedCart::expired()
                        ->orWhere(function ($q) use ($days) {
                            $q->whereNotNull('recovered_at')
                              ->where('updated_at', '<', now()->subDays($days));
                        })
                        ->delete();

                    $total += $deleted;

                    if ($deleted > 0) {
                        $this->line("Tenant [{$website->uuid}]: {$deleted} carrito(s) eliminado(s).");
                    }
                } catch (\Throwable $e) {
                    Log::error('[PurgeAbandonedCarts] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->info("Total carritos eliminados: {$total}");
        return 0;
    }
}
