<?php

namespace App\Traits;
use App\Models\Tenant\Item;
use App\Models\Tenant\Kardex;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Inventory\Models\InventoryConfiguration;



trait KardexTrait
{

    public function saveKardex($type, $item_id, $id, $quantity, $relation) {

        $kardex = Kardex::create([
            'type' => $type,
            'date_of_issue' => date('Y-m-d'),
            'item_id' => $item_id,
            'document_id' => ($relation == 'document') ? $id : null,
            'purchase_id' => ($relation == 'purchase') ? $id : null,
            'purchase_settlement_id' => ($relation == 'purchase_settlement') ? $id : null,
            'sale_note_id' => ($relation == 'sale_note') ? $id : null,
            'quantity' => $quantity,
        ]);

        return $kardex;

    }

    public function updateStock($item_id, $quantity, $is_sale){

        $item = Item::find($item_id);
        if (!$item) return;

        $delta = ($is_sale) ? -$quantity : $quantity;

        // Productos CON variantes: no se toca nada aquí.
        //
        // `items.stock` es un valor DERIVADO de `item_variant_warehouse`, y
        // `ItemVariantService::propagateStock()` lo reescribe entero. Restar
        // aquí se perdía en la siguiente propagación —que ocurre al guardar el
        // producto, al editar cualquier variante y al correr `stock:reconcile`—
        // y el stock vendido reaparecía.
        //
        // Tampoco se delega al enrutador de variantes desde aquí: este trait no
        // es el dueño del movimiento. Sobre el mismo evento hay otro listener
        // (`modules/Inventory/.../InventoryKardexServiceProvider`) que sí sabe
        // el almacén y la variante de la línea; si los dos aplicaran el delta,
        // la variante se descontaría dos veces. Ese listener propaga, así que
        // `items.stock` queda correcto sin que este trait escriba.
        //
        // Ver la skill `ebaemy-stock-flow`.
        if ($item->has_variants) return;

        $item->stock = $item->stock + $delta;
        $item->save();

    }

    public function restoreStockInWarehpuse($item_id, $warehouse_id, $quantity)
    {
        $item_warehouse = ItemWarehouse::firstOrNew(['item_id' => $item_id, 'warehouse_id' => $warehouse_id]);
        $item_warehouse->stock = $item_warehouse->stock + $quantity;
        $item_warehouse->save();
    }

}
