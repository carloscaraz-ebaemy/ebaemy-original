<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Models\RestaurantItemSupply;
use Modules\Restaurant\Models\Supply;

class RestaurantItemSupplyController extends Controller
{
    /**
     * Obtiene los insumos asociados a un item
     */
    public function getItemSupplies($itemId)
    {
        try {
            $item = Item::findOrFail($itemId);

            $supplies = $item->restaurantItemSupplies()
                ->with(['supply.unitType'])
                ->get()
                ->map(function ($itemSupply) {
                    $supply = $itemSupply->supply;
                    if (!$supply) return null;

                    return [
                        'id' => $itemSupply->id,
                        'supply_id' => $supply->id,
                        'supply_name' => $supply->name,
                        'unit_type' => $supply->unitType ? $supply->unitType->description : '',
                        'quantity' => $itemSupply->quantity,
                        'cost' => $supply->cost,
                        'waste_percentage' => $supply->waste_percentage,
                    ];
                })->filter();

            return response()->json([
                'success' => true,
                'data' => $supplies
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guarda o actualiza los insumos de un item
     * Recibe: { item_id: int, supplies: [{supply_id: int, quantity: float}] }
     */
    public function storeItemSupplies(Request $request)
    {
        $request->validate([
            'item_id' => 'required',
            'supplies' => 'required|array',
            'supplies.*.supply_id' => 'required',
            'supplies.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::connection('tenant')->beginTransaction();

            $itemId = $request->item_id;
            $supplies = $request->supplies;

            // Eliminar insumos anteriores
            RestaurantItemSupply::where('item_id', $itemId)->delete();

            // Crear nuevos registros
            foreach ($supplies as $supplyData) {
                RestaurantItemSupply::create([
                    'item_id' => $itemId,
                    'supply_id' => $supplyData['supply_id'],
                    'quantity' => $supplyData['quantity'],
                ]);
            }

            DB::connection('tenant')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Insumos guardados correctamente'
            ]);
        } catch (Exception $e) {
            DB::connection('tenant')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar insumos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descuenta el stock de los insumos basado en la cantidad de items vendidos
     * Recibe: { item_id: int, quantity: int }
     *
     * Ejemplo: Si se vende 1 plato que usa 0.1kg de arroz, descuenta 0.1 del stock del insumo arroz
     */
    public function discountSuppliesStock(Request $request)
    {
        $request->validate([
            'item_id' => 'required',
            'quantity' => 'required|numeric|min:1',
        ]);

        try {
            DB::connection('tenant')->beginTransaction();

            $item = Item::findOrFail($request->item_id);
            $quantitySold = $request->quantity;

            $itemSupplies = $item->restaurantItemSupplies()->with('supply')->get();

            if ($itemSupplies->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'El item no tiene insumos asignados',
                    'discounted' => []
                ]);
            }

            $discounted = [];

            foreach ($itemSupplies as $itemSupply) {
                $supply = $itemSupply->supply;
                if (!$supply) continue;

                // Calcular cantidad a descontar
                $quantityToDiscount = $itemSupply->quantity * $quantitySold;

                // Verificar si hay suficiente stock
                if ($supply->stock < $quantityToDiscount) {
                    DB::connection('tenant')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuficiente del insumo '{$supply->name}'. Disponible: {$supply->stock}, Requerido: {$quantityToDiscount}"
                    ], 400);
                }

                // Descontar stock
                $supply->stock -= $quantityToDiscount;
                $supply->save();

                $discounted[] = [
                    'supply_id' => $supply->id,
                    'supply_name' => $supply->name,
                    'quantity_discounted' => $quantityToDiscount,
                    'remaining_stock' => $supply->stock,
                ];
            }

            DB::connection('tenant')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock de insumos descontado correctamente',
                'discounted' => $discounted
            ]);
        } catch (Exception $e) {
            DB::connection('tenant')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al descontar stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los insumos disponibles para agregar a un item
     */
    public function getAvailableSupplies(Request $request)
    {
        try {
            $query = Supply::with('unitType');

            // Aplicar filtro de búsqueda si existe
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where('name', 'like', '%' . $search . '%');
            }

            $supplies = $query->orderBy('name')
                ->get()
                ->map(function ($supply) {
                    return [
                        'id' => $supply->id,
                        'name' => $supply->name,
                        'unit_type' => $supply->unitType ? $supply->unitType->description : '',
                        'unit_type_id' => $supply->unit_type_id,
                        'cost' => $supply->cost,
                        'waste_percentage' => $supply->waste_percentage,
                        'stock' => $supply->stock,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $supplies
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
