<?php

namespace App\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StockQueryService — stock e importes a nivel VARIANTE, para reportes.
 *
 * Ningún reporte del sistema era consciente de variantes: todos trabajaban con
 * `items.stock` y el costo del padre. Con variantes de costos distintos —lo
 * normal en producto importado— la valorización de inventario salía mal, y la
 * pregunta de reposición («¿a qué modelo le falta una talla?») no se podía
 * responder porque el dato estaba agregado antes de llegar al informe.
 *
 * La unidad física real es la variante: nadie cuenta "zapatillas", cuenta
 * 38-negro. Así que el dato base son filas por variante y la agregación al
 * producto es una vista, no el origen. Esta clase da esas filas; cada reporte
 * decide si las muestra planas o plegadas.
 *
 * Comparte la semántica de stock con ItemVariantService::computeStock(): se lee
 * item_variant_warehouse.stock_physical de las variantes ACTIVAS, nunca la
 * columna `stock` legacy ni el agregado item_variants.stock, que son derivados.
 */
class StockQueryService
{
    /**
     * Filas por variante para los items pedidos.
     *
     * Una consulta para todo el lote, no una por producto: los reportes pintan
     * cientos de filas y el N+1 aquí se nota de inmediato.
     *
     * El COALESCE contra items es la regla de herencia del modelo: precio y
     * costo en null en la variante significan "los del padre". Sin él, la
     * valorización contaría a costo 0 toda variante que no tenga costo propio,
     * que son la mayoría.
     *
     * @param  int[]    $itemIds        Vacío = todos los productos con variantes
     * @param  int|null $warehouseId    Acotar a un almacén; null = todos
     * @return array<int, array<int, array{
     *   variant_id:int, display_name:?string, sku:?string, barcode:?string,
     *   warehouse_id:?int, stock:float, committed:float, available:float,
     *   cost:float, price:float, valuation_cost:float, valuation_price:float
     * }>>  indexado por item_id
     */
    public function variantRowsFor(array $itemIds = [], ?int $warehouseId = null): array
    {
        try {
            $query = DB::connection('tenant')
                ->table('item_variants as iv')
                ->join('items as i', 'i.id', '=', 'iv.item_id')
                ->leftJoin('item_variant_warehouse as ivw', 'ivw.item_variant_id', '=', 'iv.id')
                ->where('iv.is_active', 1);

            if (!empty($itemIds)) {
                $query->whereIn('iv.item_id', $itemIds);
            }
            if ($warehouseId !== null) {
                $query->where('ivw.warehouse_id', $warehouseId);
            }

            $rows = $query
                ->groupBy('iv.item_id', 'iv.id', 'iv.display_name', 'iv.sku', 'iv.barcode', 'ivw.warehouse_id')
                ->selectRaw('iv.item_id,
                             iv.id AS variant_id,
                             iv.display_name,
                             iv.sku,
                             iv.barcode,
                             ivw.warehouse_id,
                             COALESCE(SUM(ivw.stock_physical), 0)  AS stock,
                             COALESCE(SUM(ivw.stock_committed), 0) AS committed,
                             COALESCE(iv.purchase_unit_price, i.purchase_unit_price, 0) AS cost,
                             COALESCE(iv.sale_unit_price, i.sale_unit_price, 0)         AS price')
                ->orderBy('iv.item_id')
                ->orderBy('iv.id')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $stock     = (float) $row->stock;
                $committed = (float) $row->committed;
                $cost      = (float) $row->cost;
                $price     = (float) $row->price;

                $out[(int) $row->item_id][] = [
                    'variant_id'      => (int) $row->variant_id,
                    'display_name'    => $row->display_name,
                    'sku'             => $row->sku,
                    'barcode'         => $row->barcode,
                    'warehouse_id'    => $row->warehouse_id !== null ? (int) $row->warehouse_id : null,
                    'stock'           => $stock,
                    'committed'       => $committed,
                    'available'       => max(0.0, $stock - $committed),
                    'cost'            => $cost,
                    'price'           => $price,
                    'valuation_cost'  => round($stock * $cost, 4),
                    'valuation_price' => round($stock * $price, 4),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            // Un tenant sin las tablas de variantes no debe tumbar un reporte:
            // se comporta como si ningún producto tuviera variantes.
            Log::warning('StockQueryService::variantRowsFor failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Valorización correcta de un producto: suma de (stock × costo) de cada
     * variante con SU costo, no `stock_del_padre × costo_del_padre`.
     *
     * Devuelve null si el producto no tiene variantes activas, para que el
     * llamador use su cálculo de producto simple y no confunda "sin variantes"
     * con "vale cero".
     *
     * @param  array $variantRows Filas de variantRowsFor()[item_id]
     * @return array{stock:float, available:float, cost:float, price:float}|null
     */
    public function aggregate(?array $variantRows): ?array
    {
        if (empty($variantRows)) {
            return null;
        }

        $stock = $available = $cost = $price = 0.0;

        foreach ($variantRows as $row) {
            $stock     += $row['stock'];
            $available += $row['available'];
            $cost      += $row['valuation_cost'];
            $price     += $row['valuation_price'];
        }

        return [
            'stock'     => round($stock, 4),
            'available' => round($available, 4),
            'cost'      => round($cost, 4),
            'price'     => round($price, 4),
        ];
    }
}
