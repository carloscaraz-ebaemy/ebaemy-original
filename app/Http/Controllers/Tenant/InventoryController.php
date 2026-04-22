<?php
namespace App\Http\Controllers\Tenant;

use App\Enums\StockMovementTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\InventoryCollection;
use App\Http\Resources\Tenant\InventoryResource;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index() {
        return view('tenant.inventories.index');
    }
    
    public function columns() {
        return [
            'item_id' => 'Producto'
        ];
    }
    
    public function records(Request $request) {
        $item_description = $request->input('value');
        $records = ItemWarehouse::with(['item', 'warehouse'])
                                ->whereHas('item', function($query) use($item_description) {
                                    $query->where('description', 'like', '%' . $item_description . '%');
                                })->orderBy('item_id');

//        dd($records);
        return new InventoryCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function tables() {
        return [
            'items' => Item::with(['unit_type', 'currency_type'])->where('item_type_id', '01')->select('id', 'description', 'internal_id', 'unit_type_id', 'currency_type_id')->orderBy('description')->limit(500)->get(),
            'warehouses' => Warehouse::all()
        ];
    }

    public function record($id)
    {
        $record = new InventoryResource(ItemWarehouse::with(['item', 'warehouse'])->findOrFail($id));

        return $record;
    }

    public function store(Request $request)
    {
        $result = DB::connection('tenant')->transaction(function () use ($request) {
            $item_id = (int) $request->input('item_id');
            $warehouse_id = (int) $request->input('warehouse_id');
            $quantity = (float) $request->input('quantity');

            $item_warehouse = ItemWarehouse::firstOrNew([
                'item_id'      => $item_id,
                'warehouse_id' => $warehouse_id,
            ]);
            if ($item_warehouse->id) {
                return [
                    'success' => false,
                    'message' => 'El producto ya se encuentra registrado en el almacén indicado.',
                ];
            }

            // 1. Crear la fila del almacén con stock en 0; applyStockMovement
            //    se encarga de los deltas y de dejar `stock = stock_physical`.
            $item_warehouse->stock = 0;
            $item_warehouse->stock_physical = 0;
            $item_warehouse->stock_committed = 0;
            $item_warehouse->save();

            // 2. Aplicar el ingreso inicial como ADJUSTMENT_IN si hay cantidad.
            //    Esto actualiza stock_physical + registra en stock_movements.
            if ($quantity > 0) {
                $item_warehouse->applyStockMovement(StockMovementTypeEnum::ADJUSTMENT_IN, $quantity);
                StockMovement::record(
                    iw: $item_warehouse,
                    type: StockMovementTypeEnum::ADJUSTMENT_IN,
                    qty: $quantity,
                    notes: 'Stock inicial registrado desde panel de inventario',
                );
            }

            // 3. Mantener el log legacy en `inventories` para reportes que lo usan.
            $inventory = new Inventory();
            $inventory->type = 1;
            $inventory->description = 'Stock inicial';
            $inventory->item_id = $item_id;
            $inventory->warehouse_id = $warehouse_id;
            $inventory->quantity = $quantity;
            $inventory->save();

            return [
                'success' => true,
                'message' => 'Producto registrado en almacén',
            ];
        });

        return $result;
    }

    public function move(Request $request)
    {
        $result = DB::connection('tenant')->transaction(function () use ($request) {
            $item_id = (int) $request->input('item_id');
            $warehouse_id = (int) $request->input('warehouse_id');
            $warehouse_new_id = (int) $request->input('warehouse_new_id');
            $quantity_move = (float) $request->input('quantity_move');

            if ($warehouse_id === $warehouse_new_id) {
                return [
                    'success' => false,
                    'message' => 'El almacén destino no puede ser igual al de origen',
                ];
            }

            // Lock origen y destino ordenados por warehouse_id para evitar deadlocks.
            [$firstWid, $secondWid] = $warehouse_id < $warehouse_new_id
                ? [$warehouse_id, $warehouse_new_id]
                : [$warehouse_new_id, $warehouse_id];

            ItemWarehouse::where('item_id', $item_id)
                ->whereIn('warehouse_id', [$firstWid, $secondWid])
                ->lockForUpdate()->get();

            $origin = ItemWarehouse::where('item_id', $item_id)
                ->where('warehouse_id', $warehouse_id)->first();
            if (!$origin) {
                return ['success' => false, 'message' => 'Origen no encontrado'];
            }
            if ($origin->stock_physical < $quantity_move) {
                return [
                    'success' => false,
                    'message' => 'La cantidad a trasladar no puede ser mayor al que se tiene en el almacén.',
                ];
            }

            $destination = ItemWarehouse::firstOrNew([
                'item_id' => $item_id,
                'warehouse_id' => $warehouse_new_id,
            ]);
            if (!$destination->id) {
                $destination->stock = 0;
                $destination->stock_physical = 0;
                $destination->stock_committed = 0;
                $destination->save();
            }

            // Salida del origen + entrada al destino (auditadas)
            $origin->applyStockMovement(StockMovementTypeEnum::TRANSFER_OUT, $quantity_move);
            StockMovement::record(
                iw: $origin,
                type: StockMovementTypeEnum::TRANSFER_OUT,
                qty: $quantity_move,
                notes: "Traslado a almacén #{$warehouse_new_id}",
            );

            $destination->applyStockMovement(StockMovementTypeEnum::TRANSFER_IN, $quantity_move);
            StockMovement::record(
                iw: $destination,
                type: StockMovementTypeEnum::TRANSFER_IN,
                qty: $quantity_move,
                notes: "Traslado desde almacén #{$warehouse_id}",
            );

            // Log legacy en `inventories` (reports antiguos lo usan).
            $inventory = new Inventory();
            $inventory->type = 2;
            $inventory->description = 'Traslado';
            $inventory->item_id = $item_id;
            $inventory->warehouse_id = $warehouse_id;
            $inventory->warehouse_destination_id = $warehouse_new_id;
            $inventory->quantity = $quantity_move;
            $inventory->save();

            return [
                'success' => true,
                'message' => 'Producto trasladado con éxito',
            ];
        });

        return $result;
    }

    public function remove(Request $request)
    {
        $result = DB::connection('tenant')->transaction(function () use ($request) {
            $item_id = (int) $request->input('item_id');
            $warehouse_id = (int) $request->input('warehouse_id');
            $quantity_remove = (float) $request->input('quantity_remove');

            $item_warehouse = ItemWarehouse::where('item_id', $item_id)
                ->where('warehouse_id', $warehouse_id)
                ->lockForUpdate()
                ->first();
            if (!$item_warehouse) {
                return [
                    'success' => false,
                    'message' => 'El producto no se encuentra en el almacén indicado',
                ];
            }

            if ($item_warehouse->stock_physical < $quantity_remove) {
                return [
                    'success' => false,
                    'message' => 'La cantidad a retirar no puede ser mayor al que se tiene en el almacén.',
                ];
            }

            // Egreso auditado (stock_physical - qty + registro en stock_movements)
            $item_warehouse->applyStockMovement(StockMovementTypeEnum::ADJUSTMENT_OUT, $quantity_remove);
            StockMovement::record(
                iw: $item_warehouse,
                type: StockMovementTypeEnum::ADJUSTMENT_OUT,
                qty: $quantity_remove,
                notes: 'Retiro manual desde panel de inventario',
            );

            // Log legacy en `inventories`
            $inventory = new Inventory();
            $inventory->type = 3;
            $inventory->description = 'Retirar';
            $inventory->item_id = $item_id;
            $inventory->warehouse_id = $warehouse_id;
            $inventory->quantity = $quantity_remove;
            $inventory->save();

            return [
                'success' => true,
                'message' => 'Stock retirado con éxito',
            ];
        });

        return $result;
    }
}
