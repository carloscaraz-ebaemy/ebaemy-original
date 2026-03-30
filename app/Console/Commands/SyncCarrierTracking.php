<?php

namespace App\Console\Commands;

use App\Models\Tenant\CourierCompany;
use App\Models\Tenant\SaleNote;
use App\Services\Tenant\Carrier\CarrierServiceFactory;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza el estado de tracking con los carriers integrados via API.
 *
 * Itera todos los tenants y actualiza el `logistic_status` de las SaleNotes
 * despachadas cuyo carrier tiene API, consultando el estado actual en la API.
 *
 * Uso:
 *   php artisan carrier:sync-tracking              -- todos los tenants
 *   php artisan carrier:sync-tracking --tenant=uuid -- un tenant específico
 *   php artisan carrier:sync-tracking --dry-run    -- solo reporta, sin actualizar
 *
 * Programar en el Kernel para correr cada 30-60 minutos:
 *   $schedule->command('carrier:sync-tracking')->everyThirtyMinutes();
 */
class SyncCarrierTracking extends Command
{
    protected $signature = 'carrier:sync-tracking
                            {--tenant=  : UUID del tenant específico}
                            {--dry-run  : Solo reportar, no actualizar}';

    protected $description = 'Sincroniza tracking de envíos con APIs de carriers (Chazki, 99Minutos, etc.)';

    private int $synced  = 0;
    private int $updated = 0;
    private int $errors  = 0;

    public function handle(Environment $tenancy): int
    {
        $tenantUuid = $this->option('tenant');
        $dryRun     = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo DRY-RUN — no se actualizará ningún registro.');
        }

        $query = Website::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $query->chunk(10, function ($websites) use ($tenancy, $dryRun) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $this->syncTenant($website->uuid, $dryRun);
                } catch (\Throwable $e) {
                    $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
                    Log::error('[carrier:sync-tracking] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Envíos consultados',  $this->synced],
                ['Estados actualizados', $this->updated],
                ['Errores de API',       $this->errors],
            ]
        );

        return 0;
    }

    private function syncTenant(string $uuid, bool $dryRun): void
    {
        // Buscar SaleNotes despachadas con tracking_number y courier con API
        $dispatched = SaleNote::where('logistic_status', 'DESPACHADO')
            ->whereNotNull('tracking_number')
            ->whereNotNull('courier_name')
            ->where('tracking_number', '!=', '')
            ->get();

        if ($dispatched->isEmpty()) {
            return;
        }

        $this->line("  Tenant [{$uuid}]: {$dispatched->count()} envíos activos.");

        foreach ($dispatched as $saleNote) {
            $this->syncSaleNote($saleNote, $dryRun);
        }
    }

    private function syncSaleNote(SaleNote $saleNote, bool $dryRun): void
    {
        try {
            $carrier = CarrierServiceFactory::makeByName($saleNote->courier_name);

            if (!$carrier->hasApiIntegration()) {
                return; // carrier manual, no hay tracking API
            }

            $this->synced++;
            $status = $carrier->getTracking($saleNote->tracking_number);

            // Actualizar logistic_status si fue entregado o devuelto
            $newLogisticStatus = match ($status->status) {
                'delivered' => 'ENTREGADO',
                'returned'  => 'DEVUELTO',
                default     => null,
            };

            if ($newLogisticStatus) {
                $this->line("    [{$saleNote->tracking_number}] {$status->statusLabel} → {$newLogisticStatus}");

                if (!$dryRun) {
                    $saleNote->logistic_status = $newLogisticStatus;
                    $saleNote->save();
                    $this->updated++;
                }
            } else {
                $this->line("    [{$saleNote->tracking_number}] {$status->statusLabel} (sin cambio)");
            }

        } catch (\Throwable $e) {
            $this->errors++;
            Log::warning('[carrier:sync-tracking] Error consultando tracking.', [
                'sale_note_id' => $saleNote->id,
                'tracking'     => $saleNote->tracking_number,
                'courier'      => $saleNote->courier_name,
                'error'        => $e->getMessage(),
            ]);
            $this->warn("    Error [{$saleNote->tracking_number}]: {$e->getMessage()}");
        }
    }
}
