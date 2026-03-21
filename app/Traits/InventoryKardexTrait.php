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

        $item_warehouse = $this->getItemWarehouse($item_id, $establishment_id, $warehouse_id);
        if (!$item_warehouse) return;

        $delta = $is_sale ? -$quantity : $quantity;

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

        $item_warehouse = ItemWarehouse::create([
            'item_id' => $item_id,
            'warehouse_id' => ($warehouse_id) ? $warehouse_id : $this->getWarehouseId($establishment_id),
            'stock' => $stock
            ]);

    }


}
