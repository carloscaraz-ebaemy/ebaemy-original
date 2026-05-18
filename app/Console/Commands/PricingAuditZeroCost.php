<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reporta productos sin costo registrado (purchase_unit_price = 0 o NULL)
 * para que el seller/admin decida qué hacer antes de activar el bloqueo
 * BLOCK_MARKETPLACE_WITHOUT_COST.
 *
 * Es NO-DESTRUCTIVO. Solo lee.
 *
 * Para cada tenant, lista:
 *  - cantidad total de items
 *  - cantidad sin costo
 *  - cuántos están publicados en marketplace sin costo (riesgo alto)
 *  - cuántos están en tienda virtual sin costo
 *  - top 10 items sin costo con más ventas históricas (los más críticos)
 *
 * Uso:
 *   php artisan pricing:audit-zero-cost                    # todos los tenants
 *   php artisan pricing:audit-zero-cost --tenant=alasitas  # un solo tenant
 *   php artisan pricing:audit-zero-cost --csv              # exporta CSV detallado
 */
class PricingAuditZeroCost extends Command
{
    protected $signature = 'pricing:audit-zero-cost
        {--tenant= : UUID del tenant especifico (ej. ebaemy_alasitas) o nombre corto (alasitas)}
        {--csv : Generar CSV detallado en storage/app/pricing-audit-zero-cost.csv}';

    protected $description = 'Audita productos sin costo registrado en todos los tenants (reporte no-destructivo)';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');
        $csv          = (bool) $this->option('csv');

        $hostnames = Hostname::with('website')->get();
        if ($tenantFilter) {
            $hostnames = $hostnames->filter(function ($hn) use ($tenantFilter) {
                if (!$hn->website) return false;
                return str_contains($hn->website->uuid, $tenantFilter) || str_contains($hn->fqdn, $tenantFilter);
            });
            if ($hostnames->isEmpty()) {
                $this->error("No se encontró tenant '{$tenantFilter}'");
                return 1;
            }
        }

        $this->info("Auditando " . $hostnames->count() . " tenant(s) — productos sin costo registrado");
        $this->line('');

        $tenancy   = app(Environment::class);
        $originalT = $tenancy->tenant();
        $globalTotals = ['items' => 0, 'zero' => 0, 'zero_marketplace' => 0, 'zero_store' => 0];
        $csvRows = [];

        foreach ($hostnames as $hn) {
            if (!$hn->website) continue;

            try {
                $tenancy->tenant($hn->website);
                $stats = $this->auditTenant($hn->fqdn, $csv, $csvRows);

                $globalTotals['items']            += $stats['items'];
                $globalTotals['zero']             += $stats['zero'];
                $globalTotals['zero_marketplace'] += $stats['zero_marketplace'];
                $globalTotals['zero_store']       += $stats['zero_store'];
            } catch (\Throwable $e) {
                $this->error("  {$hn->fqdn}: ERROR — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);

        $this->line('');
        $this->info('═══ TOTALES GLOBALES ═══');
        $this->line("Items totales:                       <comment>{$globalTotals['items']}</comment>");
        $this->line("Sin costo (cost = 0 o NULL):         <fg=yellow>{$globalTotals['zero']}</>");
        $this->line("Sin costo + publicados marketplace:  <fg=red>{$globalTotals['zero_marketplace']}</>  ← prioridad ALTA");
        $this->line("Sin costo + apply_store=true:        <fg=yellow>{$globalTotals['zero_store']}</>");

        if ($csv && !empty($csvRows)) {
            $path = storage_path('app/pricing-audit-zero-cost.csv');
            $fp = fopen($path, 'w');
            fputcsv($fp, ['tenant', 'item_id', 'description', 'sale_price', 'apply_store', 'marketplace_publishable', 'ventas_historicas']);
            foreach ($csvRows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
            $this->line('');
            $this->info("CSV generado: {$path}");
        }

        return 0;
    }

    /**
     * @return array{items:int, zero:int, zero_marketplace:int, zero_store:int}
     */
    private function auditTenant(string $fqdn, bool $csv, array &$csvRows): array
    {
        $totalItems = (int) DB::connection('tenant')->table('items')->count();

        $zeroBase = DB::connection('tenant')->table('items')
            ->where(function ($q) {
                $q->whereNull('purchase_unit_price')->orWhere('purchase_unit_price', '<=', 0);
            });

        $totalZero        = (clone $zeroBase)->count();
        $zeroMarketplace  = (clone $zeroBase)->where('marketplace_publishable', true)->count();
        $zeroStore        = (clone $zeroBase)->where('apply_store', true)->count();

        if ($totalZero === 0) {
            $this->line("  <fg=green>✓</> {$fqdn}: 0 items sin costo (total {$totalItems})");
            return ['items' => $totalItems, 'zero' => 0, 'zero_marketplace' => 0, 'zero_store' => 0];
        }

        $color = $zeroMarketplace > 0 ? 'red' : 'yellow';
        $this->line(sprintf(
            "  <fg=%s>○</> %s: <fg=%s>%d</> sin costo (de %d total) — %d en marketplace · %d en tienda",
            $color, $fqdn, $color, $totalZero, $totalItems, $zeroMarketplace, $zeroStore
        ));

        // Top 10 sin costo con más ventas (más críticos)
        if ($totalZero > 0) {
            $topRisky = DB::connection('tenant')->table('items as i')
                ->leftJoin('document_items as di', 'di.item_id', '=', 'i.id')
                ->where(function ($q) {
                    $q->whereNull('i.purchase_unit_price')->orWhere('i.purchase_unit_price', '<=', 0);
                })
                ->groupBy('i.id', 'i.description', 'i.sale_unit_price', 'i.apply_store', 'i.marketplace_publishable')
                ->select(
                    'i.id',
                    'i.description',
                    'i.sale_unit_price',
                    'i.apply_store',
                    'i.marketplace_publishable',
                    DB::raw('COUNT(di.id) as ventas')
                )
                ->orderByDesc('ventas')
                ->limit(10)
                ->get();

            foreach ($topRisky as $row) {
                $flag = $row->marketplace_publishable ? '🌐MP' : ($row->apply_store ? '🏪Tienda' : '·');
                $this->line(sprintf(
                    "      [%5d] %-40s S/ %7.2f · %s · ventas: %d",
                    $row->id,
                    mb_strimwidth($row->description ?? '(sin nombre)', 0, 40),
                    (float) $row->sale_unit_price,
                    $flag,
                    (int) $row->ventas
                ));

                if ($csv) {
                    $csvRows[] = [
                        $fqdn,
                        $row->id,
                        $row->description,
                        (float) $row->sale_unit_price,
                        (bool) $row->apply_store ? 'si' : 'no',
                        (bool) $row->marketplace_publishable ? 'si' : 'no',
                        (int) $row->ventas,
                    ];
                }
            }
        }

        return [
            'items'            => $totalItems,
            'zero'             => $totalZero,
            'zero_marketplace' => $zeroMarketplace,
            'zero_store'       => $zeroStore,
        ];
    }
}
