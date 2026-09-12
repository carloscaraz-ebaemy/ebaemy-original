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

            // Hashes válidos según las opciones actuales — mismo cálculo que usa
            // reactivate() para decidir si una variante desactivada puede volver.
            $validHashes = $this->validHashesFor($item);

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
     * Los variant_hash que son válidos con las opciones que el producto tiene
     * AHORA. Sirve para saber si una variante desactivada puede volver: si su
     * hash ya no está en esta lista, su combinación dejó de existir (alguien
     * borró el color o la talla) y reactivarla solo conseguiría que el próximo
     * syncVariants() la desactivara otra vez.
     *
     * @return string[]
     */
    public function validHashesFor(Item $item): array
    {
        $item->loadMissing('itemOptions.values');

        if ($item->itemOptions->isEmpty()) {
            return [];
        }

        $combinations = $this->cartesianProduct(
            $item->itemOptions->map(fn($opt) => $opt->values)->toArray()
        );

        return collect($combinations)->map(function ($valueSet) {
            $ids = collect($valueSet)->pluck('id')->sort()->values()->toArray();
            return ItemVariant::buildHash($ids);
        })->all();
    }

    /**
     * Mueve todo el stock físico de una variante a otra, almacén por almacén.
     *
     * Es la salida para el stock atrapado en una combinación que ya no existe:
     * la variante desactivada queda a cero y sus unidades se suman a la variante
     * de destino en el MISMO almacén, de modo que el total del producto no
     * cambia y el inventario físico sigue cuadrando.
     *
     * Lo comprometido no se mueve: pertenece a pedidos concretos que apuntan a
     * la variante original. Si los hubiera, se avisa por log y se mueve solo la
     * diferencia libre.
     *
     * @return float Unidades efectivamente movidas
     */
    public function moveStockBetweenVariants(ItemVariant $from, ItemVariant $to): float
    {
        return DB::connection('tenant')->transaction(function () use ($from, $to) {
            $moved = 0.0;

            $rows = ItemVariantWarehouse::where('item_variant_id', $from->id)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                // Solo lo libre. Lo comprometido sigue apuntando a esta variante
                // desde pedidos pendientes y moverlo dejaría esas reservas sin
                // respaldo físico.
                $free = max(0.0, (float) $row->stock_physical - (float) $row->stock_committed);
                if ($free <= 0) continue;

                $target = ItemVariantWarehouse::lockForUpdate()->firstOrCreate(
                    ['item_variant_id' => $to->id, 'warehouse_id' => $row->warehouse_id],
                    ['stock' => 0, 'stock_physical' => 0, 'stock_committed' => 0]
                );

                $target->stock_physical = (float) $target->stock_physical + $free;
                $target->stock          = $target->stock_physical;
                $target->save();

                $row->stock_physical = (float) $row->stock_physical - $free;
                $row->stock          = $row->stock_physical;
                $row->save();

                $moved += $free;
            }

            if ($moved <= 0) {
                return 0.0;
            }

            foreach ([$from, $to] as $variant) {
                $variant->stock = ItemVariantWarehouse::where('item_variant_id', $variant->id)
                    ->sum('stock_physical');
                $variant->save();
            }

            Log::info('[ItemVariantService] stock movido entre variantes', [
                'item_id' => $from->item_id,
                'from'    => $from->id,
                'to'      => $to->id,
                'moved'   => $moved,
            ]);

            // El producto padre siempre existe (FK con cascade), pero si la fila
            // faltara preferimos devolver el movimiento hecho antes que reventar
            // con un null: el stock ya se movió y está consistente entre variantes.
            if ($item = $from->item()->first()) {
                $this->propagateStock($item);
            }

            return $moved;
        });
    }

    // ────────────────────────────────────────────────────────────────────────
    // Cálculo de stock — FUENTE ÚNICA DE VERDAD
    // ────────────────────────────────────────────────────────────────────────

    /**
     * ¿El stock de una variante DESACTIVADA cuenta en el total del producto?
     *
     * No. Una variante desactivada es una combinación que el negocio retiró:
     * no se puede vender ni desde el panel ni desde la tienda, así que contarla
     * en el total del producto publica una disponibilidad que no existe.
     *
     * El stock de esas variantes no se pierde ni se borra — sigue en
     * item_variant_warehouse y la pestaña de variantes las muestra con el
     * interruptor "ver desactivadas", desde donde se reactivan o se mueve su
     * stock a otra variante.
     */
    public const COUNT_INACTIVE = false;

    /**
     * Lo que se publica al ecommerce y al marketplace, ¿es físico o disponible?
     *
     * Disponible = físico − comprometido. Publicar el físico sobrevende: el
     * comprador se lleva la última unidad que ya estaba apartada para un pedido
     * pendiente de otro cliente.
     */
    public const PUBLISH_AVAILABLE = true;

    /**
     * Cuánto stock tiene un producto con variantes. ÚNICO lugar donde se decide.
     *
     * Antes de esta función había cuatro respuestas distintas para la misma
     * pregunta, y se sobrescribían entre sí: propagateStock() sumaba todas las
     * variantes, stock:sync-variants solo las activas, stock:reconcile restaba
     * lo comprometido, y el sync del marketplace leía la columna agregada en un
     * sitio y la tabla legacy en otro. El comando de reconciliación "corregía"
     * y el siguiente guardado lo volvía a romper.
     *
     * Lee SIEMPRE item_variant_warehouse.stock_physical — nunca la columna
     * `stock` legacy ni el agregado item_variants.stock, que son derivados.
     *
     * @param  bool $includeInactive   Contar variantes desactivadas (ver COUNT_INACTIVE)
     * @param  bool $subtractCommitted Descontar lo reservado por pedidos pendientes
     * @param  int|null $warehouseId   Acotar a un almacén; null = todos
     *
     * @return array{
     *   total: float,
     *   physical: float,
     *   committed: float,
     *   by_warehouse: array<int, array{physical: float, committed: float, available: float}>
     * }
     */
    public function computeStock(
        Item $item,
        ?int $warehouseId = null,
        bool $includeInactive = self::COUNT_INACTIVE,
        bool $subtractCommitted = false
    ): array {
        $query = DB::connection('tenant')->table('item_variant_warehouse as ivw')
            ->join('item_variants as iv', 'iv.id', '=', 'ivw.item_variant_id')
            ->where('iv.item_id', $item->id);

        if (!$includeInactive) {
            $query->where('iv.is_active', 1);
        }

        if ($warehouseId !== null) {
            $query->where('ivw.warehouse_id', $warehouseId);
        }

        $rows = $query->groupBy('ivw.warehouse_id')
            ->selectRaw('ivw.warehouse_id,
                         COALESCE(SUM(ivw.stock_physical), 0)  AS physical,
                         COALESCE(SUM(ivw.stock_committed), 0) AS committed')
            ->get();

        $byWarehouse   = [];
        $totalPhysical = 0.0;
        $totalCommitted = 0.0;

        foreach ($rows as $row) {
            $physical  = (float) $row->physical;
            $committed = (float) $row->committed;

            $byWarehouse[(int) $row->warehouse_id] = [
                'physical'  => $physical,
                'committed' => $committed,
                // Nunca negativo: si lo comprometido supera al físico (puede pasar
                // tras un ajuste manual a la baja) el disponible es cero, no deuda.
                'available' => max(0.0, $physical - $committed),
            ];

            $totalPhysical  += $physical;
            $totalCommitted += $committed;
        }

        $total = $subtractCommitted
            ? max(0.0, $totalPhysical - $totalCommitted)
            : $totalPhysical;

        return [
            'total'        => $total,
            'physical'     => $totalPhysical,
            'committed'    => $totalCommitted,
            'by_warehouse' => $byWarehouse,
        ];
    }

    /**
     * Stock publicable de un producto con variantes: lo que se le puede ofrecer
     * al comprador en el ecommerce, el marketplace y los feeds externos.
     * Atajo sobre computeStock() con la política de publicación ya aplicada.
     */
    public function publishableStock(Item $item, ?int $warehouseId = null): float
    {
        return $this->computeStock(
            $item,
            $warehouseId,
            self::COUNT_INACTIVE,
            self::PUBLISH_AVAILABLE
        )['total'];
    }

    /**
     * Propaga el stock de item_variant_warehouse → item_warehouse → items.
     * Mantiene retrocompatibilidad con todo el código que lee item_warehouse.stock.
     *
     * Pública porque ItemController::store() la invoca como red de seguridad
     * cuando el seller guarda el form del producto padre y este tiene variantes
     * — para garantizar que items.stock siempre queda derivado y no sobrescrito
     * por el form. Ver skill ebaemy-stock-flow.
     */
    public function propagateStock(Item $item): void
    {
        try {
            // Una sola fuente de verdad — ver computeStock(). Aquí se guarda el
            // FÍSICO (no el disponible): item_warehouse.stock_committed lleva
            // aparte lo reservado, y quien publica al comprador resta por su
            // cuenta con publishableStock(). Guardar ya restado contaría dos veces.
            $stock = $this->computeStock($item);

            foreach ($stock['by_warehouse'] as $warehouseId => $row) {
                ItemWarehouse::updateOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                    [
                        'stock'           => $row['physical'],
                        'stock_physical'  => $row['physical'],
                        'stock_committed' => $row['committed'],
                    ]
                );
            }

            // Almacenes que ya no reciben stock de ninguna variante activa: hay
            // que ponerlos a cero explícitamente. Sin esto, desactivar la última
            // variante de un almacén dejaba su fila con el stock viejo para
            // siempre, porque el bucle de arriba ya no la visita.
            $touched = array_keys($stock['by_warehouse']);
            ItemWarehouse::where('item_id', $item->id)
                ->when($touched, fn($q) => $q->whereNotIn('warehouse_id', $touched))
                ->update(['stock' => 0, 'stock_physical' => 0, 'stock_committed' => 0]);

            $item->update(['stock' => $stock['physical']]);
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
