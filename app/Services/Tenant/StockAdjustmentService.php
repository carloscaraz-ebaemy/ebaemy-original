<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementTypeEnum;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Inventory\Models\Inventory;

/**
 * Ajuste manual de stock «pongo el número que conté».
 *
 * Es el único camino por el que una pantalla fija el stock de un producto a un
 * valor absoluto: la pestaña de variantes y el panel de la Tienda virtual pasan
 * por aquí. Cada ajuste deja su movimiento en el kardex (`inventories` +
 * `inventory_kardex`) con el stock anterior y el contado, igual que el ajuste
 * «Stock Real» del módulo de Inventario.
 *
 *  - Producto SIN variantes: el movimiento lo aplica el observer de Inventory
 *    (InventoryTrait::updateStock, dueño de `item_warehouse.stock`) y después se
 *    alinea `stock_physical`, que es lo que lee la venta. Sin ese segundo paso
 *    un ajuste sobre una fila existente dejaba el físico viejo y la venta
 *    rechazaba unidades que la pantalla sí mostraba.
 *  - Producto CON variantes: la verdad es `item_variant_warehouse`;
 *    updateVariantStock() la escribe y propaga, y el kardex se deja con
 *    Inventory::soloKardex() para que el observer no lo sume otra vez.
 *
 * Ver la skill `ebaemy-stock-flow`.
 */
class StockAdjustmentService
{
    private ItemVariantService $variants;

    public function __construct(ItemVariantService $variants)
    {
        $this->variants = $variants;
    }

    /**
     * Por qué este producto no se puede ajustar con un número suelto, o null.
     */
    public function motivoNoAjustable(Item $item): ?string
    {
        if ($item->unit_type_id === 'ZZ') {
            return 'Es un servicio: no lleva stock.';
        }
        if ($item->is_set) {
            return 'Es un pack: su stock sale de los productos que lo forman.';
        }
        if ($item->lots_enabled || $item->series_enabled) {
            return 'Lleva lotes o series: ajústalo desde Inventario para indicar cuáles.';
        }

        return null;
    }

    /**
     * Fija el stock de un producto SIN variantes en un almacén.
     *
     * @return float el movimiento aplicado (contado − anterior)
     */
    public function ajustarProducto(Item $item, int $warehouseId, float $contado, ?string $motivo = null): float
    {
        if ($item->has_variants) {
            throw new InvalidArgumentException('El producto tiene variantes: se ajusta por variante.');
        }
        if ($motivoNo = $this->motivoNoAjustable($item)) {
            throw new InvalidArgumentException($motivoNo);
        }
        if ($contado < 0) {
            throw new InvalidArgumentException('El stock no puede ser negativo.');
        }

        return DB::connection('tenant')->transaction(function () use ($item, $warehouseId, $contado, $motivo) {
            $fila = ItemWarehouse::where('item_id', $item->id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            // `stock` es el número que llevan el kardex y el módulo de
            // Inventario: el movimiento se mide contra él.
            $anterior = $fila ? (float) $fila->stock : 0.0;
            $delta = $contado - $anterior;

            if (abs($delta) >= 0.0001) {
                $this->registrarKardex($item, $warehouseId, $delta, $anterior, $contado, 'Ajuste de stock', $motivo);
            }

            // El observer ya dejó `stock` en el contado; el físico se alinea
            // aquí. También cura filas en las que los dos ya no coincidían.
            $fila = ItemWarehouse::firstOrNew(['item_id' => $item->id, 'warehouse_id' => $warehouseId]);
            $fila->stock = $contado;
            $fila->stock_physical = $contado;
            $fila->save();

            $this->registrarMovimiento($fila, $delta, $motivo);

            return $delta;
        });
    }

    /**
     * Fija el stock físico de una variante en un almacén.
     *
     * @return float el movimiento aplicado (contado − anterior)
     */
    public function ajustarVariante(Item $item, ItemVariant $variant, int $warehouseId, float $contado, ?string $motivo = null): float
    {
        if ((int) $variant->item_id !== (int) $item->id) {
            throw new InvalidArgumentException('La variante no pertenece al producto.');
        }
        if ($contado < 0) {
            throw new InvalidArgumentException('El stock no puede ser negativo.');
        }

        $anterior = (float) (ItemVariantWarehouse::where('item_variant_id', $variant->id)
            ->where('warehouse_id', $warehouseId)
            ->value('stock_physical') ?? 0);

        $delta = $contado - $anterior;

        $this->variants->updateVariantStock($variant, $warehouseId, $contado);

        if (abs($delta) >= 0.0001) {
            // Best-effort: si el kardex falla, el ajuste ya está hecho y es
            // correcto. Perder la anotación no justifica deshacerlo.
            try {
                Inventory::soloKardex(function () use ($item, $variant, $warehouseId, $delta, $anterior, $contado, $motivo) {
                    $this->registrarKardex(
                        $item, $warehouseId, $delta, $anterior, $contado,
                        'Ajuste de variante ' . ($variant->display_name ?: ('#' . $variant->id)),
                        $motivo
                    );
                });
            } catch (\Throwable $e) {
                Log::warning('[StockAdjustmentService] no se pudo registrar el ajuste de variante en kardex', [
                    'item_id'    => $item->id,
                    'variant_id' => $variant->id,
                    'delta'      => $delta,
                    'error'      => $e->getMessage(),
                ]);
            }

            $fila = ItemWarehouse::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->first();
            if ($fila) {
                $this->registrarMovimiento($fila, $delta, 'Variante ' . ($variant->display_name ?: ('#' . $variant->id)) . ($motivo ? ' — ' . $motivo : ''));
            }
        }

        return $delta;
    }

    /**
     * Lo que necesita una pantalla para ajustar: almacenes y stock actual.
     */
    public function estado(Item $item): array
    {
        $almacenes = Warehouse::orderBy('id')->get(['id', 'description']);

        $base = [
            'id'           => $item->id,
            'description'  => $item->description,
            'has_variants' => (bool) $item->has_variants,
            'bloqueo'      => $this->motivoNoAjustable($item),
            'warehouses'   => $almacenes->map(fn ($w) => ['id' => $w->id, 'description' => $w->description])->values(),
        ];

        if ($item->has_variants) {
            $item->load('variants.warehouseStocks');
            $base['variants'] = $item->variants->map(fn ($v) => [
                'id'           => $v->id,
                'display_name' => $v->display_name ?: ('Variante #' . $v->id),
                'sku'          => $v->sku,
                'stock'        => $v->warehouseStocks->mapWithKeys(fn ($s) => [
                    $s->warehouse_id => [
                        'physical'  => (float) $s->stock_physical,
                        'committed' => (float) $s->stock_committed,
                    ],
                ]),
            ])->values();

            return $base;
        }

        $filas = ItemWarehouse::where('item_id', $item->id)->get()->keyBy('warehouse_id');
        $base['stock'] = $almacenes->mapWithKeys(fn ($w) => [
            $w->id => [
                'physical'  => (float) optional($filas->get($w->id))->stock,
                'committed' => (float) optional($filas->get($w->id))->stock_committed,
            ],
        ]);

        return $base;
    }

    /**
     * Republica el producto en el marketplace sin esperar al cron: un ajuste
     * puede dejarlo en cero (hay que dejar de ofrecerlo) o sacarlo de cero.
     */
    public function sincronizarMarketplace(Item $item): void
    {
        try {
            if (!$item->marketplace_publishable || ($item->mp_status ?? '') === 'rejected') {
                return;
            }
            $hostname = app(\Hyn\Tenancy\Contracts\CurrentHostname::class);
            if (!$hostname) {
                return;
            }

            app(\App\Services\System\MarketplaceListingSyncService::class)
                ->syncItem($hostname->id, (int) $item->id);
        } catch (\Throwable $e) {
            Log::warning('[StockAdjustmentService] sincronizarMarketplace failed', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function registrarKardex(
        Item $item,
        int $warehouseId,
        float $delta,
        float $anterior,
        float $contado,
        string $que,
        ?string $motivo
    ): void {
        $quien = optional(auth()->user())->name ?? 'sistema';
        $signo = $delta > 0 ? '+' : '';

        Inventory::create([
            'type'         => $delta > 0 ? 1 : 3,   // 1 entrada, 3 salida
            'description'  => mb_substr($que . ($motivo ? ' — ' . $motivo : ''), 0, 255),
            'item_id'      => $item->id,
            'warehouse_id' => $warehouseId,
            'quantity'     => abs($delta),
            'system_stock' => $anterior,
            'real_stock'   => $contado,
            'comments'     => mb_substr(sprintf(
                'Ajuste manual de stock (%s%s) por %s',
                $signo,
                rtrim(rtrim(number_format($delta, 4, '.', ''), '0'), '.'),
                $quien
            ), 0, 255),
        ]);
    }

    /**
     * El mismo ajuste en el libro de movimientos (`stock_movements`), que es
     * el que lleva el físico/comprometido. Best-effort, como el kardex.
     */
    private function registrarMovimiento(ItemWarehouse $fila, float $delta, ?string $notas): void
    {
        if (abs($delta) < 0.0001) {
            return;
        }

        try {
            StockMovement::record(
                $fila,
                $delta > 0 ? StockMovementTypeEnum::ADJUSTMENT_IN : StockMovementTypeEnum::ADJUSTMENT_OUT,
                abs($delta),
                auth()->id(),
                null,
                $notas ? mb_substr('Ajuste manual — ' . $notas, 0, 255) : 'Ajuste manual'
            );
        } catch (\Throwable $e) {
            Log::warning('[StockAdjustmentService] no se pudo registrar el movimiento', [
                'item_id' => $fila->item_id,
                'delta'   => $delta,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
