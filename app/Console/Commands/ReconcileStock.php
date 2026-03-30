<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariantWarehouse;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detecta y opcionalmente corrige divergencias entre items.stock
 * y la suma real de item_variant_warehouse.stock_physical.
 *
 * Uso:
 *   php artisan stock:reconcile               -- muestra divergencias (dry-run)
 *   php artisan stock:reconcile --fix         -- corrige los valores divergentes
 *   php artisan stock:reconcile --tenant=uuid -- solo un tenant específico
 */
class ReconcileStock extends Command
{
    protected $signature = 'stock:reconcile
                            {--fix        : Corregir las divergencias encontradas}
                            {--tenant=    : UUID del tenant a revisar (omitir = todos)}
                            {--threshold= : Solo reportar divergencias > N unidades (default: 0)}';

    protected $description = 'Detecta divergencias entre items.stock y la suma de stock en variantes/almacenes';

    private int $totalDivergences = 0;
    private int $totalFixed       = 0;

    public function handle(Environment $tenancy): int
    {
        $fix       = $this->option('fix');
        $tenantUuid = $this->option('tenant');
        $threshold = (float) ($this->option('threshold') ?? 0);

        $query = Website::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $query->chunk(20, function ($websites) use ($tenancy, $fix, $threshold) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $this->reconcileTenant($website->uuid, $fix, $threshold);
                } catch (\Throwable $e) {
                    $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
                    Log::error('[stock:reconcile] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->newLine();
        $this->info("── Resumen ────────────────────────────────────");
        $this->line("Divergencias encontradas : {$this->totalDivergences}");
        if ($fix) {
            $this->line("Divergencias corregidas  : {$this->totalFixed}");
        } else {
            $this->comment("Ejecutar con --fix para corregir automáticamente.");
        }

        return 0;
    }

    private function reconcileTenant(string $uuid, bool $fix, float $threshold): void
    {
        // Solo productos con variantes activas
        $items = Item::where('has_variants', true)->get();

        if ($items->isEmpty()) return;

        $this->line("\nTenant: {$uuid} — {$items->count()} producto(s) con variantes");

        foreach ($items as $item) {
            $this->reconcileItem($item, $fix, $threshold);
        }
    }

    private function reconcileItem(Item $item, bool $fix, float $threshold): void
    {
        // Suma real: stock_physical - stock_committed de todas las variantes en todos los almacenes
        $realStock = ItemVariantWarehouse::whereHas('itemVariant', function ($q) use ($item) {
                $q->where('item_id', $item->id)->where('is_active', true);
            })
            ->sum(DB::raw('stock_physical - stock_committed'));

        $realStock  = max(0, (float) $realStock);
        $storedStock = (float) $item->attributes['stock']; // leer campo directo, no accessor

        $diff = abs($realStock - $storedStock);
        if ($diff <= $threshold) return;

        $this->totalDivergences++;

        $this->warn(sprintf(
            '  [DIVERGENCIA] Item #%d "%s" | stored: %.4f | real: %.4f | diff: %.4f',
            $item->id,
            \Illuminate\Support\Str::limit($item->name, 40),
            $storedStock,
            $realStock,
            $diff
        ));

        Log::warning('[stock:reconcile] Divergencia detectada', [
            'item_id'      => $item->id,
            'item_name'    => $item->name,
            'stock_stored' => $storedStock,
            'stock_real'   => $realStock,
            'diff'         => $diff,
        ]);

        if ($fix) {
            DB::transaction(function () use ($item, $realStock) {
                Item::where('id', $item->id)->update(['stock' => $realStock]);
            });
            $this->totalFixed++;
            $this->info(sprintf('    ✓ Corregido → stock = %.4f', $realStock));

            Log::info('[stock:reconcile] Divergencia corregida', [
                'item_id'    => $item->id,
                'stock_old'  => (float) $item->attributes['stock'],
                'stock_new'  => $realStock,
            ]);
        }
    }
}
