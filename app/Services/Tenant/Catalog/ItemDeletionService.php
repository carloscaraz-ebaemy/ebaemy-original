<?php

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado y retiro de productos.
 *
 * El botón "Eliminar" fallaba para casi cualquier producto real: `items` tiene
 * ~30 tablas hijas con FK `NO ACTION`, y el controlador sólo limpiaba el kardex
 * inicial y el costo promedio. Cualquier imagen, unidad alternativa, lote o reseña
 * ya hacía que MySQL rechazara el DELETE, y el usuario recibía el mensaje genérico
 * "está siendo usado por otros registros" sin saber por qué ni qué hacer.
 *
 * Aquí se separan los dos casos:
 *  - Tablas que PERTENECEN al producto (imágenes, lotes, kardex propio...): se purgan.
 *  - Tablas TRANSACCIONALES (una venta, una compra, la receta de otro producto): son
 *    hechos de negocio y no se tocan nunca. Si existen, el producto no se puede borrar
 *    y la única salida correcta es retirarlo.
 */
class ItemDeletionService
{
    /**
     * Tablas cuya fila es un hecho de negocio. Si el producto aparece en una,
     * borrarlo falsearía el histórico: se bloquea y se nombra el motivo.
     *
     * tabla => [columna, etiqueta para el usuario]
     */
    private const BLOCKING = [
        'document_items'            => ['item_id',            'comprobantes emitidos'],
        'sale_note_items'           => ['item_id',            'notas de venta'],
        'purchase_items'            => ['item_id',            'compras'],
        'purchase_order_items'      => ['item_id',            'órdenes de compra'],
        'purchase_quotation_items'  => ['item_id',            'cotizaciones de compra'],
        'purchase_settlement_items' => ['item_id',            'liquidaciones de compra'],
        'quotation_items'           => ['item_id',            'cotizaciones'],
        'order_note_items'          => ['item_id',            'pedidos'],
        'order_form_items'          => ['item_id',            'formularios de pedido'],
        'sale_opportunity_items'    => ['item_id',            'oportunidades de venta'],
        'contract_items'            => ['item_id',            'contratos'],
        'devolution_items'          => ['item_id',            'devoluciones'],
        'dispatch_items'            => ['item_id',            'guías de remisión'],
        'guide_items'               => ['item_id',            'guías'],
        'item_supplies'             => ['individual_item_id', 'recetas de otros productos'],
        'restaurant_item_supplies'  => ['supply_id',          'recetas de restaurante'],
    ];

    /**
     * Tablas hijas que sólo existen por el producto. Se borran antes del DELETE
     * porque su FK es NO ACTION y, si no, MySQL rechaza la operación.
     *
     * [tabla, columna]
     */
    private const OWNED = [
        ['item_images',               'item_id'],
        ['item_unit_types',           'item_id'],
        ['item_modifier_group',       'item_id'],
        ['items_rating',              'item_id'],
        ['restaurant_stock_products', 'item_id'],
        ['restaurant_item_supplies',  'item_id'],
        ['item_lots',                 'item_id'],
        ['item_lots_group',           'item_id'],
        ['kardex',                    'item_id'],
        ['weighted_average_costs',    'item_id'],
    ];

    /** Conexión del tenant sobre la que se opera; la fija `useConnectionOf()`. */
    private $connection = null;

    /** @var array<string,bool> */
    private static $schemaCache = [];

    /**
     * Motivos por los que el producto no se puede borrar, en lenguaje del usuario.
     *
     * @return string[]
     */
    public function blockers(Item $item): array
    {
        $this->useConnectionOf($item);
        $reasons = [];

        foreach (self::BLOCKING as $table => $definition) {
            [$column, $label] = $definition;

            if (!$this->hasColumn($table, $column)) {
                continue;
            }

            if ($this->db()->table($table)->where($column, $item->id)->exists()) {
                $reasons[] = $label;
            }
        }

        // Los lotes del producto pueden estar citados por una transferencia entre
        // almacenes; esa referencia también es NO ACTION y bloquearía el DELETE.
        if ($this->hasColumn('inventory_transfer_items', 'item_lot_id') && $this->hasColumn('item_lots', 'item_id')) {
            $lotIds = $this->db()->table('item_lots')->where('item_id', $item->id)->pluck('id');

            if ($lotIds->isNotEmpty() && $this->db()->table('inventory_transfer_items')->whereIn('item_lot_id', $lotIds)->exists()) {
                $reasons[] = 'transferencias entre almacenes';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Borra el producto de verdad, o explica por qué no se puede.
     *
     * @return array{success: bool, message: string, can_retire?: bool, blockers?: string[]}
     */
    public function delete(Item $item): array
    {
        $this->useConnectionOf($item);
        $blockers = $this->blockers($item);

        if ($blockers) {
            return [
                'success'    => false,
                'can_retire' => true,
                'blockers'   => $blockers,
                'message'    => 'No se puede eliminar: el producto ya figura en '
                    . $this->enumerate($blockers)
                    . '. Borrarlo alteraría esos registros. Usa «Retirar del sistema» para dejar de verlo y de venderlo sin tocar el histórico.',
            ];
        }

        try {
            $this->db()->transaction(function () use ($item) {
                foreach (self::OWNED as $owned) {
                    [$table, $column] = $owned;

                    if ($this->hasColumn($table, $column)) {
                        $this->db()->table($table)->where($column, $item->id)->delete();
                    }
                }

                $item->delete();
            });
        } catch (\Throwable $e) {
            // Queda alguna FK que no contemplamos: no se pierde nada, pero hay que verlo.
            Log::error('[ItemDeletionService] no se pudo eliminar el producto', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success'    => false,
                'can_retire' => true,
                'blockers'   => [],
                'message'    => 'No se pudo eliminar el producto porque otro registro del sistema lo referencia. '
                    . 'Usa «Retirar del sistema» para dejar de verlo y de venderlo.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Producto eliminado con éxito',
        ];
    }

    /**
     * Saca el producto de circulación sin borrarlo: deja de estar activo, deja de
     * aparecer en la tienda y deja de publicarse en el marketplace. El histórico
     * (ventas, compras, kardex) queda intacto.
     */
    public function retire(Item $item): array
    {
        $this->useConnectionOf($item);
        $item->active = false;
        $item->apply_store = false;
        $item->marketplace_publishable = false;

        if ($this->hasColumn('items', 'mp_status')) {
            $item->mp_status = 'paused';
        }

        $item->save();

        // Las variantes NO se tocan a propósito.
        //
        // Aquí había un bloque que ponía a 0 una columna `active` de
        // `item_variants`. Esa columna no existe — la real es `is_active` — así
        // que el `hasColumn()` devolvía false y el bloque nunca llegó a correr.
        //
        // No se arregló, se quitó, porque retirar el producto ya cierra las tres
        // puertas por las que sale a la calle: `active=false` y
        // `marketplace_publishable=false` hacen que el sync marque el listing
        // como inactivo, y `apply_store=false` lo saca del escaparate y de los
        // feeds, que filtran por esa columna. La variante no tiene ninguna vía
        // propia hacia el comprador.
        //
        // Y desactivarlas sería irreversible en el sentido que importa: una
        // variante desactivada a mano por el negocio (porque guarda stock de una
        // combinación retirada) es indistinguible de una desactivada por este
        // retiro, así que `restore()` las reactivaría todas y devolvería ese
        // stock a `items.stock` sin que nadie lo haya pedido.

        return [
            'success' => true,
            'message' => 'Producto retirado: ya no se muestra ni se puede vender. Su histórico se conserva.',
        ];
    }

    /**
     * Devuelve el producto a circulación en el ERP. No lo republica solo: la tienda
     * y el marketplace se vuelven a activar desde sus propios interruptores.
     */
    public function restore(Item $item): array
    {
        $this->useConnectionOf($item);
        $item->active = true;
        $item->save();

        // Simétrico a retire(): las variantes no se tocan. Ver el comentario de
        // allí — reactivarlas en bloque resucitaría las que el negocio había
        // desactivado por su cuenta.

        return [
            'success' => true,
            'message' => 'Producto reactivado. Revisa si quieres volver a mostrarlo en la tienda o en el marketplace.',
        ];
    }

    /**
     * Nada de `DB::` ni `Schema::` a secas: los modelos del tenant viven en la
     * conexión `tenant`, y la conexión por defecto no siempre es ésa (en consola
     * no lo es). Se toma la del propio producto.
     */
    private function useConnectionOf(Item $item): void
    {
        $this->connection = $item->getConnectionName() ?: config('database.default');
    }

    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection($this->connection);
    }

    /** El esquema no es idéntico en todos los tenants: nunca asumir que la tabla existe. */
    private function hasColumn(string $table, string $column): bool
    {
        // La caché se indexa por base de datos: el mismo nombre de conexión
        // (`tenant`) apunta a una BD distinta en cada tenant.
        $key = $this->db()->getDatabaseName() . '|' . $table . '.' . $column;

        if (!array_key_exists($key, self::$schemaCache)) {
            $builder = Schema::connection($this->connection);
            self::$schemaCache[$key] = $builder->hasTable($table) && $builder->hasColumn($table, $column);
        }

        return self::$schemaCache[$key];
    }

    /** @param string[] $items */
    private function enumerate(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' y ' . $last;
    }
}
