<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de zonas de envío por tenant. Cada tenant define sus propias zonas
 * con costos y tiempos estimados. Los IDs de distrito se guardan en un JSON
 * editable desde el formulario — una zona puede cubrir muchos distritos.
 *
 * Reglas:
 *   - Solo debe haber UNA zona con is_default=true a la vez
 *   - Recojo en tienda (is_pickup=true) fuerza cost=0
 */
class ShippingZoneController extends Controller
{
    public function index()
    {
        return view('tenant.shipping_zones.index');
    }

    public function records()
    {
        return ShippingZone::orderBy('sort_order')->orderBy('id')->get();
    }

    public function tables()
    {
        // Catálogo oficial de distritos (Peru). La tabla vive en la BD tenant
        // precargada con todos los ubigeo. Lo servimos en una sola carga para
        // el selector multi-select del form.
        $districts = \App\Models\Tenant\Catalogs\District::where('active', 1)
            ->orderBy('description')
            ->get(['id', 'description', 'province_id']);

        return ['districts' => $districts];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:80',
            'cost'           => 'required|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:0|max:60',
            'district_ids'   => 'nullable|array',
            'district_ids.*' => 'string|max:10',
            'is_default'     => 'nullable|boolean',
            'is_pickup'      => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        return DB::connection('tenant')->transaction(function () use ($data) {
            $isDefault = (bool) ($data['is_default'] ?? false);
            $isPickup = (bool) ($data['is_pickup'] ?? false);

            // Solo una zona default a la vez
            if ($isDefault) {
                ShippingZone::where('is_default', true)->update(['is_default' => false]);
            }

            $zone = ShippingZone::create([
                'name'           => $data['name'],
                'cost'           => $isPickup ? 0 : $data['cost'],
                'estimated_days' => $data['estimated_days'] ?? 2,
                'district_ids'   => $data['district_ids'] ?? [],
                'is_default'     => $isDefault,
                'is_pickup'      => $isPickup,
                'is_active'      => $data['is_active'] ?? true,
                'sort_order'     => $data['sort_order'] ?? 0,
            ]);

            return ['success' => true, 'message' => 'Zona creada', 'id' => $zone->id];
        });
    }

    public function record($id)
    {
        return ShippingZone::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:80',
            'cost'           => 'required|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:0|max:60',
            'district_ids'   => 'nullable|array',
            'district_ids.*' => 'string|max:10',
            'is_default'     => 'nullable|boolean',
            'is_pickup'      => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        return DB::connection('tenant')->transaction(function () use ($data, $id) {
            $zone = ShippingZone::findOrFail($id);
            $isDefault = (bool) ($data['is_default'] ?? false);
            $isPickup = (bool) ($data['is_pickup'] ?? false);

            if ($isDefault && !$zone->is_default) {
                ShippingZone::where('is_default', true)->update(['is_default' => false]);
            }

            $zone->update([
                'name'           => $data['name'],
                'cost'           => $isPickup ? 0 : $data['cost'],
                'estimated_days' => $data['estimated_days'] ?? $zone->estimated_days,
                'district_ids'   => $data['district_ids'] ?? [],
                'is_default'     => $isDefault,
                'is_pickup'      => $isPickup,
                'is_active'      => $data['is_active'] ?? true,
                'sort_order'     => $data['sort_order'] ?? $zone->sort_order,
            ]);

            return ['success' => true, 'message' => 'Zona actualizada', 'id' => $zone->id];
        });
    }

    public function destroy($id)
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->delete();
        return ['success' => true, 'message' => 'Zona eliminada'];
    }
}
