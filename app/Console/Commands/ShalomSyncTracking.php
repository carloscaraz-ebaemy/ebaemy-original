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
                $guias = ShippingRequest::where('shipping_agency', 'Shalom')
                    ->whereNotNull('tracking_number')->whereNotNull('tracking_code')
                    ->where(fn ($q) => $q->whereNull('carrier_status')->orWhere('carrier_status', '!=', 'entregado'))
                    ->where('sent_at', '>=', now()->subDays(30))
                    ->limit(300)->get();
                foreach ($guias as $s) {
                    $tracking->refresh($s);
                    $total++;
                    usleep(300000);   // 60 consultas/min en el plan
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
