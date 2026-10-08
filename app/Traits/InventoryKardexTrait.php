<?php

namespace App\Traits;

use App\Models\Tenant\InventoryKardex;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\Warehouse;

trait InventoryKardexTrait
{

    public function saveInventoryKardex($model, $item_id, $establishment_id, $quantity,$warehouse_id = null) {


        $inventory_kardex = $model->inventory_kardex()->create([
            'date_of_issue' => date('Y-m-d'),
            'item_id' => $item_id,
            'warehouse_id' => ($warehouse_id) ? $warehouse_id : $this->getWarehouseId($establishment_id),
            'quantity' => $quantity,
        ]);

        return $inventory_kardex;

    }

    public function updateStock($item_id, $establishment_id, $quantity, $is_sale, $warehouse_id = null){

        $delta = $is_sale ? -$quantity : $quantity;

        // Productos CON variantes: no se escribe el nivel derivado.
        //
        // `item_warehouse` se reescribe entero desde `item_variant_warehouse`
        // en `ItemVariantService::propagateStock()`, así que sumar aquí se
        // pierde. Y no se delega al enrutador de variantes porque el dueño del
        // movimiento es `modules/Inventory/Traits/InventoryTrait::updateStock`,
        // que sí recibe la variante de la línea: delegar desde los dos sitios
        // descontaría dos veces.
        //
        // Hoy este método además no está en el camino caliente: en
        // `App\Providers\InventoryKardexServiceProvider::boot()`, `sale()` y
        // `purchase()` están comentados y solo corre `sale_note()`, que va por
        // `SaleNoteStockService`. La guarda se deja puesta para que reactivar
        // esos dos no rompa el stock de variantes en silencio.
        //
        // Ver la skill `ebaemy-stock-flow`.
        $item = \App\Models\Tenant\Item::find($item_id);
        if ($item && $item->has_variants) return;

        $item_warehouse = $this->getItemWarehouse($item_id, $establishment_id, $warehouse_id);
        if (!$item_warehouse) return;

        // Campo legacy
        $item_warehouse->stock = max(0, $item_warehouse->stock + $delta);

        // Campos del sistema de stock inteligente
        $item_warehouse->stock_physical  = max(0, ($item_warehouse->stock_physical ?? $item_warehouse->stock) + $delta);

        $item_warehouse->save();

    }


    public function getWarehouseId($establishment_id): ?int
    {
        $warehouse = Warehouse::where('establishment_id', $establishment_id)->first();
        return $warehouse?->id;
    }

    public function getItemWarehouse($item_id, $establishment_id, $warehouse_id = null){

        $w_id = ($warehouse_id) ? $warehouse_id : $this->getWarehouseId($establishment_id);
        $item_warehouse = ItemWarehouse::where([['item_id',$item_id],['warehouse_id',$w_id]])->first();
        return $item_warehouse;
    }

    public function saveItemWarehouse($item_id, $establishment_id, $stock, $warehouse_id = null){

        // Mantenemos `stock` (legacy) y `stock_physical` (nuevo sistema) sincronizados
        // al crear el registro inicial. Sin esto el ecommerce veía stock=0 aunque el
        // admin hubiera configurado inventario inicial.
        $item_warehouse = ItemWarehouse::create([
            'item_id' => $item_id,
            'warehouse_id' => ($warehouse_id) ? $warehouse_id : $this->getWarehouseId($establishment_id),
            'stock' => $stock,
            'stock_physical' => $stock,
            'stock_committed' => 0,
            ]);

    }


}
