<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Modules\Restaurant\Models\RestaurantPreparationArea;

class PreparationAreaController extends Controller
{
    public function index()
    {
        try {
            $preparationAreas = RestaurantPreparationArea::orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $preparationAreas
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'printer' => 'required|string|max:255',
        ]);

        try {
            $preparationArea = RestaurantPreparationArea::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Área de preparación creada correctamente',
                'data' => $preparationArea
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'printer' => 'required|string|max:255',
        ]);

        try {
            $preparationArea = RestaurantPreparationArea::findOrFail($id);
            $preparationArea->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Área de preparación actualizada correctamente',
                'data' => $preparationArea
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $preparationArea = RestaurantPreparationArea::findOrFail($id);
            $preparationArea->delete();

            return response()->json([
                'success' => true,
                'message' => 'Área de preparación eliminada correctamente'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
