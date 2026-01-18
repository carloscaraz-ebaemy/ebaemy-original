<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Models\RestaurantTableGroup;

class TableGroupController extends Controller
{
    /**
     * Crea un nuevo grupo y asigna la mesa principal.
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'main_table_id' => [
                'required',
                'integer',
                'exists:tenant.restaurant_tables,id' // Especificar conexión tenant
            ],
        ]);

        $mainTable = RestaurantTable::find($request->main_table_id);

        // La mesa debe estar activa
        if (!$mainTable->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes crear un grupo con una mesa fuera de servicio'
            ], 400);
        }

        // La mesa no debe pertenecer a otro grupo
        if ($mainTable->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta mesa ya pertenece a un grupo'
            ], 400);
        }

        // Crear grupo
        $group = RestaurantTableGroup::create([
            'main_table_id' => $request->main_table_id,
            'status'        => 'open',
            'opening_date'  => now(),
            'total'         => 0,
        ]);

        // Mesa principal entra al grupo
        RestaurantTable::where('id', $request->main_table_id)
            ->update(['group_id' => $group->id]);

        return response()->json([
            'success' => true,
            'group'   => $group
        ]);
    }

    /**
     * Agregar mesa secundaria
     */
    public function addTable(Request $request)
    {
        $request->validate([
            'group_id' => [
                'required',
                'integer',
                'exists:tenant.restaurant_table_groups,id'
            ],
            'table_id' => [
                'required',
                'integer',
                'exists:tenant.restaurant_tables,id' 
            ],
        ]);

        $group = RestaurantTableGroup::find($request->group_id);
        $table = RestaurantTable::find($request->table_id);

        // La mesa secundaria debe estar activa
        if (!$table->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes agregar una mesa fuera de servicio'
            ], 400);
        }

        // La mesa secundaria DEBE estar disponible 
        if ($table->status === 'busy' || $table->status === 'no_disponible') {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes agregar mesas disponibles'
            ], 400);
        }

        // La mesa secundaria no debe pertenecer a otro grupo
        if ($table->group_id && $table->group_id !== $request->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta mesa ya pertenece a otro grupo'
            ], 400);
        }

        // No agregar la misma mesa principal
        if ($table->id === $group->main_table_id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes agregar la mesa principal como secundaria'
            ], 400);
        }

        // La mesa ya está en el grupo
        if ($table->group_id === $request->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta mesa ya está en el grupo'
            ], 400);
        }

        // Agregar mesa al grupo
        $table->update(['group_id' => $request->group_id]);

        return response()->json([
            'success' => true,
            'message' => "Mesa {$table->label} agregada al grupo exitosamente"
        ]);
    }

    /**
     * Separar mesa del grupo
     */
    public function removeTable(Request $request)
    {
        $request->validate([
            'table_id' => [
                'required',
                'integer',
                'exists:tenant.restaurant_tables,id' 
            ],
        ]);

        $table = RestaurantTable::find($request->table_id);

        // No se puede separar si tiene pedidos
        if ($table->status === 'busy' || $table->status === 'no_disponible') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes separar una mesa que tiene pedidos activos'
            ], 400);
        }

        // Obtener el grupo
            $group = RestaurantTableGroup::find($table->group_id);

            // No permitir separar la mesa principal
            if ($group && $group->main_table_id === $table->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes separar la mesa principal. Disuelve el grupo completo.'
                ], 400);
            }

            // Las mesas secundarias NO deberían tener pedidos pero igualmente validar
            if ($table->status === 'busy' || $table->status === 'notavailable') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta mesa tiene pedidos. No debería ocurrir en una mesa secundaria.'
                ], 400);
            }

        // Obtener el grupo antes de separar
        $groupId = $table->group_id;

        // Separar mesa
        $table->update(['group_id' => null, 'status' => 'available']);

        // Verificar si el grupo quedó vacío o solo con la mesa principal
        if ($groupId) {
            $remainingTables = RestaurantTable::where('group_id', $groupId)->count();
            
            // Si solo queda 1 mesa (la principal), disolver el grupo automáticamente
            if ($remainingTables <= 1) {
                RestaurantTable::where('group_id', $groupId)->update(['group_id' => null]);
                RestaurantTableGroup::where('id', $groupId)->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => "Mesa {$table->label} separada. El grupo fue disuelto automáticamente."
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Mesa {$table->label} separada del grupo"
        ]);
    }

    /**
     * Disolver grupo completo
     */
    public function disbandGroup(Request $request)
    {
        $group = RestaurantTableGroup::find($request->group_id);
        
        if (!$group) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado'
            ], 404);
        }

        $tables = RestaurantTable::where('group_id', $request->group_id)->get();

        $tablesWithProducts = $tables->filter(function ($table) use ($group) {
            return ($table->id !== $group->main_table_id) && 
                (is_array($table->products) && count($table->products) > 0);
        });

        if ($tablesWithProducts->isNotEmpty()) {
            $labels = $tablesWithProducts->pluck('label')->join(', ');
            return response()->json([
                'success' => false,
                'message' => "Las siguientes mesas secundarias tienen productos: {$labels}. Esto no debería ocurrir."
            ], 400);
        }

        // Separar todas las mesas
        foreach ($tables as $table) {
            if ($table->id === $group->main_table_id) {
                $table->update(['group_id' => null]);
            } else {
                $table->update([
                    'group_id' => null,
                    'status' => 'available',
                    'total' => 0,
                    'products' => [],
                    'opening_date' => null,
                    'personas' => 1,
                    'cliente' => null,
                    'waiter' => null,
                    'comentarios' => null,
                ]);
            }
        }

        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Grupo disuelto exitosamente'
        ]);
    }


    /**
     * Recalcular el total de todas las mesas del grupo
     */
    public function calculateTotal(Request $request)
    {
        $request->validate([
            'group_id' => [
                'required',
                'integer',
                'exists:tenant.restaurant_table_groups,id'
            ],
        ]);

        $group = RestaurantTableGroup::find($request->group_id);

        $total = $group->tables()->sum('total');

        $group->update(['total' => $total]);

        return response()->json([
            'success' => true,
            'total'   => $total,
        ]);
    }
}
