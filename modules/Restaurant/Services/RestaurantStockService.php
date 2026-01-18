<?php

namespace Modules\Restaurant\Services;

use App\Models\Tenant\Item;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Restaurant\Models\RestaurantItemSupply;
use Modules\Restaurant\Models\RestaurantStockProduct;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para gestión de stock en vivo del módulo Restaurant
 *
 * Gestiona el stock disponible considerando cantidades reservadas en mesas activas
 */
class RestaurantStockService
{
    /**
     * Calcula y actualiza el stock de un item en la tabla restaurant_stock_products
     *
     * Determina el stock base desde:
     * - Item::getRestaurantStock() si tiene supplies
     * - Item::getRestaurantStockSet() si es un set
     * - ItemWarehouse (primer warehouse) para items normales
     *
     * @param int $item_id
     * @param bool $updateHasSupplies Si es true, recalcula y actualiza el campo has_supplies
     * @return bool
     */
    public function calculateAndUpdateStock($item_id, $updateHasSupplies = false)
    {
        try {
            $item = Item::find($item_id);

            if (!$item) {
                return false;
            }

            // Obtener registro existente para verificar has_supplies
            $stockProduct = RestaurantStockProduct::where('item_id', $item_id)->first();
            $has_supplies = $stockProduct ? $stockProduct->has_supplies : false;

            // Recalcular has_supplies solo si se solicita explícitamente (syncAll)
            if ($updateHasSupplies) {
                $has_supplies = RestaurantItemSupply::where('item_id', $item_id)->exists();
            }

            // Calcular stock según tipo de item
            $stock = 0;

            if ($has_supplies) {
                // Item con insumos: usar método del modelo
                $stock = $item->getRestaurantStock();
            } elseif ($item->has_igv) {
                // Verificar si es un set (combo)
                $isSet = $item->sets()->exists();

                if ($isSet) {
                    $stock = $item->getRestaurantStockSet();
                } else {
                    // Item normal: usar primer warehouse
                    $itemWarehouse = ItemWarehouse::where('item_id', $item_id)->first();
                    $stock = $itemWarehouse ? $itemWarehouse->stock : 0;
                }
            } else {
                // Item normal: usar primer warehouse
                $itemWarehouse = ItemWarehouse::where('item_id', $item_id)->first();
                $stock = $itemWarehouse ? $itemWarehouse->stock : 0;
            }

            // Actualizar o crear registro en restaurant_stock_products
            RestaurantStockProduct::updateOrCreate(
                ['item_id' => $item_id],
                [
                    'stock' => $stock,
                    'has_supplies' => $has_supplies
                ]
            );

            return true;
        } catch (\Exception $e) {
            \Log::error("Error calculando stock para item {$item_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reserva una cantidad de un item cuando se agrega a una orden de mesa
     *
     * @param int $item_id
     * @param float $quantity
     * @return bool
     */
    public function reserveQuantity($item_id, $quantity)
    {
        try {
            // Asegurarse de que existe el registro en restaurant_stock_products
            RestaurantStockProduct::firstOrCreate(
                ['item_id' => $item_id],
                [
                    'stock' => 0,
                    'quantity_reserved' => 0,
                    'has_supplies' => false
                ]
            );

            // Incrementar cantidad reservada usando DB::raw para operación atómica
            RestaurantStockProduct::where('item_id', $item_id)
                ->update([
                    'quantity_reserved' => DB::raw("quantity_reserved + {$quantity}")
                ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Error reservando stock para item {$item_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Libera una cantidad reservada de un item cuando se cierra la mesa
     *
     * Decrementa el campo quantity_reserved con safeguard para no ser negativo
     *
     * @param int $item_id
     * @param float $quantity
     * @return bool
     */
    public function releaseQuantity($item_id, $quantity)
    {
        try {
            // Decrementar cantidad reservada con safeguard
            RestaurantStockProduct::where('item_id', $item_id)
                ->where('quantity_reserved', '>=', $quantity)
                ->update([
                    'quantity_reserved' => DB::raw("quantity_reserved - {$quantity}")
                ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Error liberando stock para item {$item_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el stock disponible de un item (stock - cantidades reservadas)
     *
     * @param int $item_id
     * @return object|null Objeto con propiedades: stock, quantity_reserved, available
     */
    public function getAvailableStock($item_id)
    {
        try {
            $stockProduct = RestaurantStockProduct::where('item_id', $item_id)->first();

            if (!$stockProduct) {
                // Si no existe registro, calcularlo primero
                $this->calculateAndUpdateStock($item_id);
                $stockProduct = RestaurantStockProduct::where('item_id', $item_id)->first();
            }

            if (!$stockProduct) {
                return (object) [
                    'stock' => 0,
                    'quantity_reserved' => 0,
                    'available' => 0
                ];
            }

            return (object) [
                'stock' => $stockProduct->stock,
                'quantity_reserved' => $stockProduct->quantity_reserved,
                'available' => $stockProduct->stock - $stockProduct->quantity_reserved
            ];
        } catch (\Exception $e) {
            \Log::error("Error obteniendo stock disponible para item {$item_id}: " . $e->getMessage());
            return (object) [
                'stock' => 0,
                'quantity_reserved' => 0,
                'available' => 0
            ];
        }
    }

    /**
     * Sincroniza el stock de todos los items del restaurante
     *
     * Itera sobre todos los items que pertenecen al restaurant y calcula su stock
     * Recalcula el campo has_supplies para cada item
     *
     * @return array Array con count de items procesados
     */
    public function syncAllItems()
    {
        try {
            // Obtener todos los items del restaurant
            $items = Item::where('apply_restaurant', 1)
                ->whereIsActive()
                ->whereNotService()
                ->get();

            $processed = 0;
            $errors = 0;

            foreach ($items as $item) {
                // Pasar true para recalcular has_supplies
                if ($this->calculateAndUpdateStock($item->id, true)) {
                    $processed++;
                } else {
                    $errors++;
                }
            }

            return [
                'processed' => $processed,
                'errors' => $errors,
                'total' => $items->count()
            ];
        } catch (\Exception $e) {
            \Log::error("Error sincronizando todos los items: " . $e->getMessage());
            return [
                'processed' => 0,
                'errors' => 0,
                'total' => 0,
                'error_message' => $e->getMessage()
            ];
        }
    }
}
