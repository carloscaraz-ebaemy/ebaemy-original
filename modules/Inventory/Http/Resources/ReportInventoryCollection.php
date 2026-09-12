<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Tenant\ExchangeRate;

class ReportInventoryCollection extends ResourceCollection
{

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        // Filas por variante de todos los productos de la página, en UNA consulta.
        // Ningún reporte era consciente de variantes: la valorización usaba el
        // costo del padre, así que con variantes de costos distintos daba mal.
        // Ver App\Services\Tenant\StockQueryService.
        $itemIds = $this->collection
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $variantRows = app(\App\Services\Tenant\StockQueryService::class)
            ->variantRowsFor($itemIds);

        $stockQuery = app(\App\Services\Tenant\StockQueryService::class);

        return $this->collection->transform(function($row, $key) use ($variantRows, $stockQuery) {
            $item = $row->item;

            $sale_unit_price = $item->sale_unit_price;
            $purchase_unit_price = $item->purchase_unit_price;
            if($item->currency_type_id === 'USD') {
                $exchange = ExchangeRate::get()->last();
                $sale_unit_price = $sale_unit_price * $exchange->sale;
                $purchase_unit_price = $purchase_unit_price * $exchange->sale;
            }
            // Valorización. Con variantes es la suma de (stock × costo) de cada
            // una con SU costo; el producto entero valorado al costo del padre
            // era incorrecto en cuanto una talla costaba distinto.
            $rowsDeEsteItem = $variantRows[$item->id] ?? null;
            $agregado       = $stockQuery->aggregate($rowsDeEsteItem);

            $valuationCost  = $agregado
                ? $agregado['cost']
                : round((float) $row->stock * (float) $purchase_unit_price, 4);
            $valuationPrice = $agregado
                ? $agregado['price']
                : round((float) $row->stock * (float) $sale_unit_price, 4);

            return [
                'barcode' => $item->barcode,
                'internal_id' => $item->internal_id,
                'name' => $item->description,
                'description' => $item->name,
                'item_category_name' => optional($item->category)->name,
                'stock_min' => $item->stock_min,
                'stock' => $row->stock,
                'sale_unit_price' => $sale_unit_price,
                'purchase_unit_price' => $purchase_unit_price,
                'profit'=>number_format($sale_unit_price-$purchase_unit_price,2,'.',''),
                'model' => $item->model,
                'brand_name' => $item->brand->name,
                'date_of_due' => optional($item->date_of_due)->format('d/m/Y'),
                'warehouse_name' => $row->warehouse->description,
                'currency_type_id' => $item->currency_type_id,

                // Valorización correcta, por variante cuando las hay.
                'valuation_cost'  => $valuationCost,
                'valuation_price' => $valuationPrice,

                // Desglose por variante: la unidad de conteo físico real. Nadie
                // cuenta "zapatillas", cuenta 38-negro.
                'has_variants' => !empty($item->has_variants),
                'variants'     => $rowsDeEsteItem ?? [],
                // Lo comprometido por pedidos pendientes, para distinguir
                // "hay 17 en el estante" de "puedo vender 11".
                'available'    => $agregado ? $agregado['available'] : null,
            ];

        });
    }




}
