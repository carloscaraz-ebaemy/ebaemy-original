<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\ItemWarehouse;

/**
 * Detecta y corrige inconsistencias de stock entre productos y sus variantes.
 *
 * Lógica:
 *   item_warehouse.stock = SUMA de item_variant_warehouse.stock_physical (variantes activas)
 *   item.stock = SUMA de item_warehouse.stock (todos los almacenes)
 *
 * Uso:
 *   php artisan stock:sync-variants              → corrige todo
 *   php artisan stock:sync-variants --check       → solo reporta sin corregir
 *   php artisan stock:sync-variants --item=TECH-001  → solo un producto
 */
class SyncVariantStock extends Command
{
    protected $signature = 'stock:sync-variants
                            {--check : Solo reportar sin corregir}
                            {--item= : Código interno de un producto específico}';

    protected $description = 'Sincronizar stock de almacén con stock de variantes';

    public function handle(): int
    {
        $checkOnly = $this->option('check');
        $itemCode  = $this->option('item');

        $query = Item::where('has_variants', true)->with(['warehouses', 'variants.warehouseStocks']);

        if ($itemCode) {
            $query->where('internal_id', $itemCode);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('No hay productos con variantes' . ($itemCode ? " con código {$itemCode}" : '') . '.');
            return 0;
        }

        $this->info(($checkOnly ? 'VERIFICANDO' : 'SINCRONIZANDO') . " {$items->count()} productos con variantes...\n");

        $fixed     = 0;
        $consistent = 0;
        $errors    = [];

        foreach ($items as $item) {
            // Calcular stock correcto por almacén
            $activeVariantIds = $item->variants->where('is_active', true)->pluck('id');

            // Agrupar stock de variantes por warehouse_id
            $variantStockByWarehouse = ItemVariantWarehouse::whereIn('item_variant_id', $activeVariantIds)
                ->select('warehouse_id', DB::raw('SUM(stock_physical) as total_physical'))
                ->groupBy('warehouse_id')
                ->pluck('total_physical', 'warehouse_id');

            $totalItemStock = 0;
            $hasInconsistency = false;

            foreach ($item->warehouses as $iw) {
                $correctStock = $variantStockByWarehouse[$iw->warehouse_id] ?? 0;
                $totalItemStock += $correctStock;

                if (abs($iw->stock - $correctStock) >= 0.01) {
                    $hasInconsistency = true;

                    if ($checkOnly) {
                        $this->warn("  ❌ {$item->internal_id} | Almacén #{$iw->warehouse_id}: tiene {$iw->stock}, debería ser {$correctStock}");
                    } else {
                        $iw->stock = $correctStock;
                        $iw->save();
                    }
                }
            }

            // Verificar item.stock total
            if (abs($item->stock - $totalItemStock) >= 0.01) {
                $hasInconsistency = true;

                if ($checkOnly) {
                    $this->warn("  ❌ {$item->internal_id} | item.stock: tiene {$item->stock}, debería ser {$totalItemStock}");
                } else {
                    $item->stock = $totalItemStock;
                    $item->save();
                }
            }

            // También sincronizar el campo stock de cada variante con su suma de warehouses
            foreach ($item->variants->where('is_active', true) as $variant) {
                $variantCorrectStock = $variant->warehouseStocks->sum('stock_physical');
                if (abs($variant->stock - $variantCorrectStock) >= 0.01) {
                    $hasInconsistency = true;
                    if (!$checkOnly) {
                        $variant->stock = $variantCorrectStock;
                        $variant->save();
                    }
                }
            }

            if ($hasInconsistency) {
                $fixed++;
                if (!$checkOnly) {
                    $this->info("  ✅ {$item->internal_id} | {$item->description} → corregido (stock: {$totalItemStock})");
                }
            } else {
                $consistent++;
                $this->line("  ✅ {$item->internal_id} | OK (stock: {$totalItemStock})");
            }
        }

        $this->info("\nResultado: {$consistent} correctos, {$fixed} " . ($checkOnly ? 'con inconsistencia' : 'corregidos') . ".");

        return 0;
    }
}
