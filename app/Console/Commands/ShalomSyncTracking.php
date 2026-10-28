<?php

namespace App\Console\Commands;

use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\ShalomTracking;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Actualiza el seguimiento de Shalom de las guías en camino, en todas las
 * tiendas. Solo las que tienen N° de guía y código, no están entregadas y
 * salieron en los últimos 30 días.
 */
class ShalomSyncTracking extends Command
{
    protected $signature = 'shalom:sync-tracking';

    protected $description = 'Consulta en Shalom el estado de las guías en camino';

    public function handle(Environment $tenancy, ShalomTracking $tracking): int
    {
        $total = 0;
        foreach (Website::all() as $website) {
            try {
                $tenancy->tenant($website);
                if (!ShalomTracking::supported()) {
                    continue;
                }
                // Las creadas por API aún no tienen `sent_at`: se cuenta desde
                // que se creó el envío. Las menos consultadas, primero.
                $guias = ShippingRequest::where('shipping_agency', 'Shalom')
                    ->whereNotNull('tracking_number')->whereNotNull('tracking_code')
                    ->where(fn ($q) => $q->whereNull('carrier_status')->orWhere('carrier_status', '!=', 'entregado'))
                    ->whereRaw('COALESCE(sent_at, created_at) >= ?', [now()->subDays(30)])
                    ->orderByRaw('carrier_checked_at IS NOT NULL, carrier_checked_at')
                    ->limit(300)->get();
                foreach ($guias as $s) {
                    try {
                        $tracking->refresh($s);
                        $tracking->watch($s);     // reintenta la suscripción si falló
                    } catch (\Throwable $e) {
                        $this->warn("{$website->uuid} #{$s->id}: " . $e->getMessage());
                    }
                    $total++;
                    usleep(1000000);   // ~60 consultas/min
                }
            } catch (\Throwable $e) {
                $this->warn($website->uuid . ': ' . $e->getMessage());
            } finally {
                $tenancy->tenant(null);
            }
        }

        $this->info("Guías consultadas: {$total}");

        return self::SUCCESS;
    }
}
