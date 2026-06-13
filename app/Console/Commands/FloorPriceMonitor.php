<?php

namespace App\Console\Commands;

use App\Models\Tenant\PricingSettings;
use App\Services\Tenant\Pricing\PriceCalculator;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 4 rediseño precios: monitor nocturno de piso de precio.
 *
 * Para cada tenant:
 *  1. Recalcula items.floor_price respetando el min_margin aplicable
 *     (item.min_margin_pct > categoría > default del tenant). Mantiene el
 *     guardrail consistente aunque cambien costos/settings sin re-guardar el item.
 *  2. Detecta items con margen erosionado y los snapshotea en
 *     pricing_margin_alerts (reemplazo total por corrida):
 *       - 'loss'        → sale_price < effective_cost (vende con pérdida)
 *       - 'below_floor' → sale_price < floor_price (rompe margen mínimo)
 *
 * Solo escribe floor_price vía query directa (bypass del ItemPriceObserver para
 * no generar eventos masivos); el cálculo es determinista e idempotente.
 *
 * Uso:
 *   php artisan pricing:monitor-floor                      # todos los tenants
 *   php artisan pricing:monitor-floor --tenant=alasitas    # un solo tenant
 *   php artisan pricing:monitor-floor --no-recalc          # solo reporta, no toca floor_price
 *   php artisan pricing:monitor-floor --dry                # no escribe nada (preview)
 *   php artisan pricing:monitor-floor --csv                # exporta CSV detallado
 */
class FloorPriceMonitor extends Command
{
    protected $signature = 'pricing:monitor-floor
        {--tenant= : UUID o nombre corto del tenant (ej. alasitas)}
        {--no-recalc : No recalcular floor_price, solo detectar erosión}
        {--dry : No escribir nada (preview en consola)}
        {--csv : Generar CSV en storage/app/pricing-margin-alerts.csv}';

    protected $description = 'Recalcula floor_price y detecta items con margen erosionado en todos los tenants (Fase 4)';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');
        $noRecalc     = (bool) $this->option('no-recalc');
        $dry          = (bool) $this->option('dry');
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

        $mode = $dry ? 'DRY (sin escribir)' : ($noRecalc ? 'solo erosión' : 'recalc + erosión');
        $this->info('Monitor de piso de precio — ' . $hostnames->count() . " tenant(s) — modo: {$mode}");
        $this->line('');

        $tenancy   = app(Environment::class);
        $originalT = $tenancy->tenant();
        $totals    = ['items' => 0, 'recalculated' => 0, 'loss' => 0, 'below_floor' => 0];
        $csvRows   = [];

        foreach ($hostnames as $hn) {
            if (!$hn->website) continue;

            try {
                $tenancy->tenant($hn->website);
                $stats = $this->processTenant($hn->fqdn, $noRecalc, $dry, $csv, $csvRows);

                $totals['items']        += $stats['items'];
                $totals['recalculated'] += $stats['recalculated'];
                $totals['loss']         += $stats['loss'];
                $totals['below_floor']  += $stats['below_floor'];
            } catch (\Throwable $e) {
                $this->error("  {$hn->fqdn}: ERROR — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);

        $this->line('');
        $this->info('═══ TOTALES GLOBALES ═══');
        $this->line("Items con costo evaluados:   <comment>{$totals['items']}</comment>");
        if (!$noRecalc && !$dry) {
            $this->line("floor_price recalculados:    <comment>{$totals['recalculated']}</comment>");
        }
        $this->line("Vendiendo con PÉRDIDA:       <fg=red>{$totals['loss']}</>  ← prioridad ALTA");
        $this->line("Bajo margen mínimo (floor):  <fg=yellow>{$totals['below_floor']}</>");

        if ($csv && !empty($csvRows)) {
            $path = storage_path('app/pricing-margin-alerts.csv');
            $fp = fopen($path, 'w');
            fputcsv($fp, ['tenant', 'item_id', 'descripcion', 'severidad', 'costo_efectivo', 'precio_venta', 'floor', 'margen_pct', 'deficit_unidad', 'liquidacion', 'marketplace']);
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
     * @return array{items:int, recalculated:int, loss:int, below_floor:int}
     */
    private function processTenant(string $fqdn, bool $noRecalc, bool $dry, bool $csv, array &$csvRows): array
    {
        $settings = PricingSettings::find(1);
        $defaultMin = $settings ? (float) ($settings->default_min_margin_pct ?? 0) : 0.0;

        $stats = ['items' => 0, 'recalculated' => 0, 'loss' => 0, 'below_floor' => 0];
        $alerts = [];
        $now = now();

        // Solo items con costo registrado (los sin costo los cubre pricing:audit-zero-cost)
        DB::connection('tenant')->table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.purchase_unit_price', '>', 0)
            ->select(
                'i.id', 'i.description', 'i.category_id', 'i.purchase_unit_price',
                'i.landed_cost_extra_pct', 'i.min_margin_pct', 'i.sale_unit_price',
                'i.compare_at_price', 'i.liquidation_mode', 'i.apply_store',
                'i.marketplace_publishable', 'i.floor_price',
                'c.name as category_name'
            )
            ->orderBy('i.id')
            ->chunk(500, function ($rows) use (&$stats, &$alerts, $settings, $defaultMin, $noRecalc, $dry, $now) {
                $floorUpdates = [];

                foreach ($rows as $r) {
                    $stats['items']++;

                    $cost     = (float) $r->purchase_unit_price;
                    $extraPct = (float) ($r->landed_cost_extra_pct ?? 0);
                    $effective = PriceCalculator::effectiveCost($cost, $extraPct);

                    // Margen mínimo aplicable: item > categoría > default tenant
                    $minMargin = $r->min_margin_pct !== null
                        ? (float) $r->min_margin_pct
                        : ($settings ? $settings->minMarginFor($r->category_id !== null ? (int) $r->category_id : null) : $defaultMin);

                    $floor = PriceCalculator::floorPrice($effective, $minMargin);

                    // 1. Recalcular floor_price si difiere del almacenado
                    if (!$noRecalc) {
                        $storedFloor = $r->floor_price !== null ? round((float) $r->floor_price, 4) : null;
                        if ($storedFloor === null || abs($storedFloor - $floor) > 0.0001) {
                            $floorUpdates[$r->id] = $floor;
                            $stats['recalculated']++;
                        }
                    }

                    // 2. Detectar erosión
                    $sale = (float) $r->sale_unit_price;
                    if ($sale <= 0) {
                        continue;
                    }

                    $severity = null;
                    $deficit = 0.0;
                    if ($sale < $effective) {
                        $severity = 'loss';
                        $deficit = round($effective - $sale, 4);
                        $stats['loss']++;
                    } elseif ($minMargin > 0 && $sale < $floor) {
                        $severity = 'below_floor';
                        $deficit = round($floor - $sale, 4);
                        $stats['below_floor']++;
                    }

                    if ($severity !== null) {
                        $alerts[] = [
                            'item_id'                 => $r->id,
                            'item_description'        => $r->description ? mb_substr($r->description, 0, 250) : null,
                            'category_id'             => $r->category_id,
                            'category_name'           => $r->category_name ? mb_substr($r->category_name, 0, 250) : null,
                            'severity'                => $severity,
                            'effective_cost'          => $effective,
                            'sale_price'              => $sale,
                            'floor_price'             => $floor,
                            'compare_at_price'        => $r->compare_at_price !== null ? (float) $r->compare_at_price : null,
                            'margin_pct'              => PriceCalculator::marginPct($sale, $effective),
                            'applied_min_margin_pct'  => $minMargin,
                            'loss_per_unit'           => $deficit,
                            'liquidation_mode'        => (bool) $r->liquidation_mode,
                            'apply_store'             => (bool) $r->apply_store,
                            'marketplace_publishable' => (bool) $r->marketplace_publishable,
                            'detected_at'             => $now,
                            'created_at'              => $now,
                            'updated_at'              => $now,
                        ];
                    }
                }

                // Persistir recálculos de floor de este chunk
                if (!$dry && !empty($floorUpdates)) {
                    foreach ($floorUpdates as $id => $floorVal) {
                        DB::connection('tenant')->table('items')
                            ->where('id', $id)
                            ->update(['floor_price' => $floorVal, 'floor_price_recalc_at' => $now]);
                    }
                }
            });

        // Reemplazo total del snapshot de alerts para este tenant
        if (!$dry) {
            DB::connection('tenant')->table('pricing_margin_alerts')->delete();
            foreach (array_chunk($alerts, 200) as $batch) {
                DB::connection('tenant')->table('pricing_margin_alerts')->insert($batch);
            }
        }

        // Salida por tenant
        $totalAlerts = $stats['loss'] + $stats['below_floor'];
        if ($totalAlerts === 0) {
            $this->line("  <fg=green>✓</> {$fqdn}: sin erosión ({$stats['items']} items con costo · {$stats['recalculated']} floors recalc)");
        } else {
            $color = $stats['loss'] > 0 ? 'red' : 'yellow';
            $this->line(sprintf(
                "  <fg=%s>○</> %s: <fg=red>%d con pérdida</> · <fg=yellow>%d bajo floor</> (de %d items · %d floors recalc)",
                $color, $fqdn, $stats['loss'], $stats['below_floor'], $stats['items'], $stats['recalculated']
            ));

            // Top 10 más críticos (mayor déficit primero, pérdidas arriba)
            $top = collect($alerts)
                ->sortByDesc(fn ($a) => ($a['severity'] === 'loss' ? 1e9 : 0) + $a['loss_per_unit'])
                ->take(10);
            foreach ($top as $a) {
                $tag = $a['severity'] === 'loss' ? '🔴PÉRDIDA' : '🟡FLOOR';
                $liq = $a['liquidation_mode'] ? ' [liquidación]' : '';
                $ch  = $a['marketplace_publishable'] ? '🌐MP' : ($a['apply_store'] ? '🏪' : '·');
                $this->line(sprintf(
                    "      [%6d] %-36s %s %s · venta S/%.2f vs floor S/%.2f · margen %.1f%%%s",
                    $a['item_id'],
                    mb_strimwidth($a['item_description'] ?? '(sin nombre)', 0, 36),
                    $tag, $ch,
                    $a['sale_price'], $a['floor_price'], $a['margin_pct'], $liq
                ));

                if ($csv) {
                    $csvRows[] = [
                        $fqdn, $a['item_id'], $a['item_description'], $a['severity'],
                        $a['effective_cost'], $a['sale_price'], $a['floor_price'],
                        $a['margin_pct'], $a['loss_per_unit'],
                        $a['liquidation_mode'] ? 'si' : 'no',
                        $a['marketplace_publishable'] ? 'si' : 'no',
                    ];
                }
            }
        }

        return $stats;
    }
}
