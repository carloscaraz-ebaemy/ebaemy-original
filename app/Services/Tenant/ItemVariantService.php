<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemOption;
use App\Models\Tenant\ItemOptionValue;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\ItemWarehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ItemVariantService
 *
 * Gestiona el ciclo de vida completo de variantes de producto.
 *
 * RESPONSABILIDADES:
 *   - Generar el producto cartesiano de opciones → variantes
 *   - Sincronizar variantes cuando el usuario edita opciones
 *   - Propagar stock hacia item_warehouse y items (retrocompat)
 *   - Activar / desactivar variantes sin borrar historia
 *
 * CONTRATO:
 *   - Toda escritura ocurre dentro de DB::transaction()
 *   - Nunca borra variantes con stock > 0 (las desactiva)
 *   - Mantiene items.stock y item_warehouse.stock sincronizados
 */
class ItemVariantService
{
    // ────────────────────────────────────────────────────────────────────────
    // API pública
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Dado un Item con sus opciones y valores ya guardados, genera o actualiza
     * todas las combinaciones posibles (producto cartesiano).
     *
     * Usa upsert por variant_hash: si la variante ya existe la actualiza,
     * si no la crea.
     *
     * @param  Item  $item
     * @param  array $overrides  Opcional: ['<hash>' => ['sale_unit_price' => X, ...]]
     * @return Collection<ItemVariant>  Las variantes activas resultantes
     */
    public function generateVariants(Item $item, array $overrides = []): Collection
    {
        return DB::connection('tenant')->transaction(function () use ($item, $overrides) {
            $item->load('itemOptions.values');

            $options = $item->itemOptions;

            if ($options->isEmpty()) {
                return collect();
            }

            // Producto cartesiano de los valores de todas las opciones
            $combinations = $this->cartesianProduct(
                $options->map(fn($opt) => $opt->values)->toArray()
            );

            $createdVariants = collect();

            foreach ($combinations as $valueSet) {
                /** @var ItemOptionValue[] $valueSet */
                $ids  = collect($valueSet)->pluck('id')->sort()->values()->toArray();
                $hash = ItemVariant::buildHash($ids);

                $displayName = collect($valueSet)
                    ->pluck('value')
                    ->implode(' / ');

                $extra = $overrides[$hash] ?? [];

                $variant = ItemVariant::firstOrNew([
                    'item_id'      => $item->id,
                    'variant_hash' => $hash,
                ]);

                $variant->fill(array_merge([
                    'display_name' => $displayName,
                    'is_active'    => true,
                ], $extra));

                $variant->save();

                // Pivot: asociar valores a la variante (idempotente)
                $variant->optionValues()->syncWithoutDetaching($ids);

                // Asegurar fila en item_variant_warehouse por cada almacén
                $this->ensureWarehouseRows($variant, $item);

                $createdVariants->push($variant);
            }

            // Activar / marcar has_variants
            $item->update(['has_variants' => true]);

            // Propagar stock agregado hacia item_warehouse e items
            $this->propagateStock($item);

            return $createdVariants;
        });
    }

    /**
     * Sincroniza variantes cuando el usuario cambia opciones/valores.
     *
     * - Nuevas combinaciones → se crean
     * - Combinaciones eliminadas sin stock → se borran
     * - Combinaciones eliminadas con stock → se desactivan (is_active=false)
     * - Combinaciones existentes → no se tocan (conservan precio, imagen, etc.)
     *
     * @param  Item  $item
     * @return array{created: int, deactivated: int, deleted: int}
     */
    public function syncVariants(Item $item): array
    {
        return DB::connection('tenant')->transaction(function () use ($item) {
            $item->load('itemOptions.values');

            $options = $item->itemOptions;

            if ($options->isEmpty()) {
                // Sin opciones → desactivar todo y quitar flag
                $this->deactivateAll($item);
                $item->update(['has_variants' => false]);
                return ['created' => 0, 'deactivated' => 0, 'deleted' => 0];
            }

            $combinations = $this->cartesianProduct(
                $options->map(fn($opt) => $opt->values)->toArray()
            );

            // Hashes válidos según las opciones actuales
            $validHashes = collect($combinations)->map(function ($valueSet) {
                $ids = collect($valueSet)->pluck('id')->sort()->values()->toArray();
                return ItemVariant::buildHash($ids);
            })->toArray();

            // Variantes obsoletas (su hash ya no existe en las opciones actuales)
            $obsolete = ItemVariant::where('item_id', $item->id)
                ->whereNotIn('variant_hash', $validHashes)
                ->get();

            $deactivated = 0;
            $deleted     = 0;

            foreach ($obsolete as $variant) {
                if ($variant->stock > 0) {
                    $variant->update(['is_active' => false]);
                    $deactivated++;
                } else {
                    $variant->delete();
                    $deleted++;
                }
            }

            // Crear las que falten (generate solo crea, no toca existentes)
            $created = 0;
            foreach ($combinations as $valueSet) {
                $ids  = collect($valueSet)->pluck('id')->sort()->values()->toArray();
                $hash = ItemVariant::buildHash($ids);

                $exists = ItemVariant::where('item_id', $item->id)
                    ->where('variant_hash', $hash)
                    ->exists();

                if (!$exists) {
                    $displayName = collect($valueSet)->pluck('value')->implode(' / ');
                    $variant = ItemVariant::create([
                        'item_id'      => $item->id,
                        'variant_hash' => $hash,
                        'display_name' => $displayName,
                        'is_active'    => true,
                        'stock'        => 0,
                    ]);
                    $variant->optionValues()->syncWithoutDetaching($ids);
                    $this->ensureWarehouseRows($variant, $item);
                    $created++;
                }
            }

            $item->update(['has_variants' => true]);
            $this->propagateStock($item);

            return compact('created', 'deactivated', 'deleted');
        });
    }

    /**
     * Elimina o desactiva una variante específica.
     * Si tiene stock físico > 0 la desactiva, si no la borra.
     *
     * @param  ItemVariant $variant
     * @return string  'deleted' | 'deactivated'
     */
    public function deleteVariant(ItemVariant $variant): string
    {
        return DB::connection('tenant')->transaction(function () use ($variant) {
            $item = $variant->item;

            if ($variant->stock > 0) {
                $variant->update(['is_active' => false]);
                $result = 'deactivated';
            } else {
                $variant->delete();
                $result = 'deleted';
            }

            $this->propagateStock($item);

            return $result;
        });
    }

    /**
     * Actualiza stock_physical de una variante en un almacén y propaga
     * los totales hacia item_variant.stock, item_warehouse.stock e items.stock.
     *
     * @param  ItemVariant $variant
     * @param  int         $warehouseId
     * @param  float       $newPhysical   Stock físico nuevo (valor absoluto)
     */
    public function updateVariantStock(
        ItemVariant $variant,
        int $warehouseId,
        float $newPhysical
    ): void {
        DB::connection('tenant')->transaction(function () use ($variant, $warehouseId, $newPhysical) {
            $row = ItemVariantWarehouse::lockForUpdate()
                ->firstOrCreate(
                    ['item_variant_id' => $variant->id, 'warehouse_id' => $warehouseId],
                    ['stock' => 0, 'stock_physical' => 0, 'stock_committed' => 0]
                );

            $row->update([
                'stock_physical' => $newPhysical,
                'stock'          => $newPhysical,  // legacy sync
            ]);

            // Actualizar stock agregado en la variante
            $totalVariant = ItemVariantWarehouse::where('item_variant_id', $variant->id)
                ->sum('stock_physical');

            $variant->update(['stock' => $totalVariant]);

            // Propagar hacia item_warehouse e items
            $this->propagateStock($variant->item);
        });
    }

    // ────────────────────────────────────────────────────────────────────────
    // Lógica interna
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Propaga el stock de item_variant_warehouse → item_warehouse → items.
     * Mantiene retrocompatibilidad con todo el código que lee item_warehouse.stock.
     */
    private function propagateStock(Item $item): void
    {
        try {
            // Por almacén: SUM(ivw.stock_physical) agrupado por warehouse_id
            $stockByWarehouse = ItemVariantWarehouse::selectRaw(
                'warehouse_id, SUM(stock_physical) as total_physical, SUM(stock_committed) as total_committed'
            )
                ->whereHas('variant', fn($q) => $q->where('item_id', $item->id))
                ->groupBy('warehouse_id')
                ->get();

            foreach ($stockByWarehouse as $row) {
                ItemWarehouse::updateOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $row->warehouse_id],
                    [
                        'stock'           => $row->total_physical,
                        'stock_physical'  => $row->total_physical,
                        'stock_committed' => $row->total_committed,
                    ]
                );
            }

            // Total global en items.stock
            $totalStock = $stockByWarehouse->sum('total_physical');
            $item->update(['stock' => $totalStock]);
        } catch (\Throwable $e) {
            Log::error('ItemVariantService::propagateStock error', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Asegura que exista una fila en item_variant_warehouse
     * para cada almacén donde el producto ya tiene registro en item_warehouse.
     */
    private function ensureWarehouseRows(ItemVariant $variant, Item $item): void
    {
        $warehouseIds = ItemWarehouse::where('item_id', $item->id)
            ->pluck('warehouse_id');

        foreach ($warehouseIds as $whId) {
            ItemVariantWarehouse::firstOrCreate(
                ['item_variant_id' => $variant->id, 'warehouse_id' => $whId],
                ['stock' => 0, 'stock_physical' => 0, 'stock_committed' => 0]
            );
        }
    }

    /**
     * Desactiva todas las variantes activas de un producto.
     */
    private function deactivateAll(Item $item): void
    {
        ItemVariant::where('item_id', $item->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Producto cartesiano de N arrays de ItemOptionValue.
     *
     * Entrada:  [[Rojo, Azul], [S, M, L]]
     * Salida:   [[Rojo,S], [Rojo,M], [Rojo,L], [Azul,S], [Azul,M], [Azul,L]]
     *
     * @param  array  $sets  Array de arrays de ItemOptionValue
     * @return array         Array de arrays de ItemOptionValue
     */
    private function cartesianProduct(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $set) {
            $append = [];
            foreach ($result as $product) {
                foreach ($set as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;
        }

        return $result;
    }
}
