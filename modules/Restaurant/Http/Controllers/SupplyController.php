<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Exception;
use Modules\Restaurant\Models\Supply;
use App\Models\Tenant\Catalogs\UnitType;

/**
 * Controlador para la gestión de insumos del restaurante
 */
class SupplyController extends Controller
{
    /**
     * Muestra la vista principal de insumos
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('restaurant::supplies.index');
    }

    /**
     * Obtiene todos los registros de insumos
     *
     * @return array
     */
    public function records()
    {
        $records = Supply::with('unitType')->get();

        return compact('records');
    }

    /**
     * Obtiene los tipos de unidad disponibles
     *
     * @return array
     */
    public function getUnitTypes()
    {
        $unitTypes = UnitType::whereActive()->get();

        return compact('unitTypes');
    }

    /**
     * Almacena o actualiza un insumo
     *
     * @param Request $request
     * @return array
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'unit_type_id' => 'required',
            'waste_percentage' => 'nullable|numeric|min:0|max:100',
            'stock' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
        ]);

        $id = $request->input('id');
        $supply = Supply::firstOrNew(['id' => $id]);
        $supply->fill($request->all());
        $supply->save();

        return [
            'success' => true,
            'message' => ($id) ? 'Insumo actualizado con éxito' : 'Insumo registrado con éxito',
            'data' => $supply->load('unitType')
        ];
    }

    /**
     * Elimina un insumo
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        try {
            $supply = Supply::findOrFail($id);
            $supply->delete();

            return [
                'success' => true,
                'message' => 'Insumo eliminado con éxito'
            ];
        } catch (Exception $e) {
            return ($e->getCode() == '23000')
                ? ['success' => false, 'message' => 'El insumo está siendo usado por otros registros, no se puede eliminar']
                : ['success' => false, 'message' => 'Error inesperado, no se pudo eliminar el insumo'];
        }
    }

    /**
     * Actualiza el stock de un insumo
     *
     * @param Request $request
     * @param int $id
     * @return array
     */
    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'stock' => 'required|numeric|min:0',
        ]);

        try {
            $supply = Supply::findOrFail($id);
            $supply->stock = $request->stock;
            $supply->save();

            return [
                'success' => true,
                'message' => 'Stock actualizado con éxito',
                'data' => $supply->load('unitType')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el stock'
            ];
        }
    }
}
