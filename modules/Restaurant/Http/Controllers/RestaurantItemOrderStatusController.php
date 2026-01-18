<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Exception;
use App\Models\Tenant\Item;
use Modules\Restaurant\Models\RestaurantItemOrderStatus;
use Modules\Restaurant\Services\RestaurantStockService;


class RestaurantItemOrderStatusController extends Controller
{
    const STATUS_RECEIVED = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_TO_DELIVER = 3;
    const STATUS_DELIVERED = 4;

    public function saveItemOrder(Request $request) {

        $itemData = $request->item;
        $stockService = app(RestaurantStockService::class);

        // Descontar supplies inmediatamente al crear la orden
        try {
            // Si el item es un set, descontar supplies de cada componente
            if (isset($itemData['has_sets']) && $itemData['has_sets']) {
                foreach ($itemData['items_sets'] as $itemSet) {
                    $item_model = Item::find($itemSet['id']);
                    if (!$item_model) continue;

                    $item_supplies = $item_model->restaurantItemSupplies;
                    foreach ($item_supplies as $item_supply) {
                        $supply_quantity = $item_supply->quantity;
                        $order_quantity = $request->quantity * $itemSet['pivot']['quantity'];
                        $total_to_discount = $supply_quantity * $order_quantity;
                        $supply = $item_supply->supply;
                        $supply->stock -= $total_to_discount;
                        $supply->save();
                    }

                    // Recalcular stock del componente después de descontar supplies
                    $stockService->calculateAndUpdateStock($itemSet['id']);
                }
            } else if (isset($itemData['has_supplies']) && $itemData['has_supplies']) {
                // Item con supplies: descontar insumos
                $item_model = Item::find($request->item_id);
                if ($item_model) {
                    $item_supplies = $item_model->restaurantItemSupplies;
                    foreach ($item_supplies as $item_supply) {
                        $supply_quantity = $item_supply->quantity;
                        $order_quantity = $request->quantity;
                        $total_to_discount = $supply_quantity * $order_quantity;
                        $supply = $item_supply->supply;
                        $supply->stock -= $total_to_discount;
                        $supply->save();
                    }
                }
            }

        } catch (Exception $e) {
            \Log::error("Error descontando supplies: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al descontar insumos.'
            ];
        }

        // Recalcular stock después de descontar supplies
        $stockService->calculateAndUpdateStock($request->item_id);

        // Reservar cantidades después de descontar supplies y recalcular stock
        try {
            // Si el item es un set, reservar cada componente
            if (isset($itemData['has_sets']) && $itemData['has_sets']) {
                foreach ($itemData['items_sets'] as $itemSet) {
                    $componentQuantity = $itemSet['pivot']['quantity'] * $request->quantity;
                    $stockService->reserveQuantity($itemSet['id'], $componentQuantity);
                }
            } else {
                // Item simple: reservar cantidad directamente
                $stockService->reserveQuantity($request->item_id, $request->quantity);
            }

            // Reservar stock de modificadores aplicados (si tienen type: "item" y item_id)
            if (isset($itemData['modifiersApplied']) && is_array($itemData['modifiersApplied'])) {
                foreach ($itemData['modifiersApplied'] as $group) {
                    if (isset($group['items']) && is_array($group['items'])) {
                        foreach ($group['items'] as $modifierItem) {
                            // Solo reservar si es de tipo "item" y tiene item_id
                            if (isset($modifierItem['type']) && $modifierItem['type'] === 'item'
                                && isset($modifierItem['item_id']) && $modifierItem['item_id']) {
                                $stockService->reserveQuantity($modifierItem['item_id'], $request->quantity);
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            \Log::error("Error reservando stock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al reservar stock.'
            ];
        }

        // Crear la orden
        $orderStatus = new RestaurantItemOrderStatus();
        $orderStatus->table_id = $request->table_id;
        $orderStatus->item_id = $request->item_id;
        $orderStatus->item = json_encode($itemData);
        $orderStatus->quantity = $request->quantity;
        $orderStatus->note = $request->note;
        $orderStatus->status = $request->status;
        $orderStatus->status_description = $request->status_description;
        $orderStatus->save();

        return [
            'success' => true,
            'message' => 'Producto agregado con éxito.'
        ];

    }

    public function getStatusItems($id)
    {
        $data = [
            'productsStatusReceived' => $this->getItemsByStatus(self::STATUS_RECEIVED,$id),
            'productsStatusProcessing' => $this->getItemsByStatus(self::STATUS_PROCESSING,$id),
            'productsStatusToDeliver' => $this->getItemsByStatus(self::STATUS_TO_DELIVER,$id),
            'productsStatusDelivered' => $this->getItemsByStatus(self::STATUS_DELIVERED,$id, 20,'desc'),
        ];

        return [
            'success' => true,
            'data' => $data,
            'message' => 'Listado de productos por estados.',
            'id' =>$id
        ];
    }

    private function getItemsByStatus($status, $table_id = 0, $limit = null, $desc = null)
    {
        $query = RestaurantItemOrderStatus::where('status', $status)
            ->with(['table', 'itemModel.preparationArea']);

        if ($table_id>0) {
            $query->where('table_id',$table_id);
        }

        if ($limit) {
            $query->take($limit);
        }

        if ($desc) {
            $query->orderBy('updated_at',$desc);
        }

        return $query->get()->transform(function ($order) {
            return $this->transformOrderData($order);
        });
    }

    public function isProductsCommandStatusServer($tableId)
    {
        // Contar cuántos productos NO están en estado 4
        $notCompleted = RestaurantItemOrderStatus::where('table_id', $tableId)
            ->where('status', '!=', 4)
            ->count();

        // Si hay alguno que no esté en 4 => false
        return $notCompleted === 0;
    }


    private function transformOrderData($order)
    {
        $itemData = json_decode($order->item);

        return [
            'id' => $order->id,
            'name' => $itemData->name ?? null,
            'quantity' => $order->quantity,
            'note' => $order->note ?? null,
            'modifiers_applied' => $itemData->modifiersApplied ?? [],
            'status' => $order->status,
            'status_description' => $order->status_description,
            'mesa_id' => $order->table_id,
            'mesa' => $order->table->label ?? null,
            'environment_id' => $order->table->environment_id ?? null,
            'environment' => $order->table->environment ?? null,
            'preparation_area_id' => $order->itemModel->preparation_area_id ?? null,
            'preparation_area_name' => $order->itemModel->preparationArea->name ?? null,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    public function setStatusItem($id)
    {
        $order = RestaurantItemOrderStatus::where('id', $id)->first();

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Orden no encontrada'
            ];
        }

        // Solo incrementar el estado (supplies ya fueron descontados en saveItemOrder)
        if ($order->status < 4) {
            $order->status += 1;
        }
        $order->save();

        return [
            'success' => true,
            'message' => 'Estado cambiado con éxito'
        ];
    }

}
