<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DiscountRule;
use App\Models\Tenant\Item;
use App\Models\Tenant\SalesChannel;
use Illuminate\Http\Request;

/**
 * CRUD para reglas de descuento automático (DiscountRule).
 *
 * Accesible desde el panel de administración del tenant.
 * Rutas:
 *   GET  /discount-rules                → index (Vue SPA)
 *   GET  /discount-rules/records        → lista paginada (JSON)
 *   GET  /discount-rules/tables         → datos para select/combos
 *   POST /discount-rules                → crear/actualizar
 *   DELETE /discount-rules/{id}         → eliminar
 *   POST /discount-rules/{id}/toggle    → activar/desactivar
 */
class DiscountRuleController extends Controller
{
    public function index()
    {
        return view('tenant.discount_rules.index');
    }

    public function records(Request $request)
    {
        $query = DiscountRule::query()->orderByDesc('priority')->orderByDesc('id');

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $query->paginate(config('tenant.items_per_page'))
        );
    }

    public function tables()
    {
        return response()->json([
            'channels' => SalesChannel::active()->get(['id', 'name', 'type', 'code']),
            'types'    => [
                ['id' => 'volume',     'label' => 'Descuento por volumen'],
                ['id' => 'auto',       'label' => 'Descuento automático (monto mínimo)'],
                ['id' => 'channel',    'label' => 'Descuento por canal'],
                ['id' => 'flash_sale', 'label' => 'Flash Sale (tiempo limitado)'],
                ['id' => 'bundle',     'label' => 'Descuento por pack/bundle'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        // FIX BUG #2: limitar discount_value según tipo
        $maxDiscount = $request->discount_type === 'percentage' ? 100 : 99999;

        $request->validate([
            'name'           => 'required|string|max:100',
            'type'           => 'required|in:volume,auto,channel,flash_sale,bundle',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => "required|numeric|min:0.01|max:{$maxDiscount}",
            'applies_to'     => 'required|in:all,item,bundle,category',
            'priority'       => 'integer|min:0|max:999',
            'max_uses'       => 'nullable|integer|min:0',
            // FIX BUG #5: validar rango de fechas
            'starts_at'      => 'nullable|date',
            'ends_at'        => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data = $request->only([
            'name', 'type', 'trigger_json', 'discount_type', 'discount_value',
            'applies_to', 'apply_item_id', 'channel_id', 'max_uses',
            'starts_at', 'ends_at', 'is_active', 'priority', 'stackable',
        ]);

        if (isset($data['trigger_json']) && is_string($data['trigger_json'])) {
            $data['trigger_json'] = json_decode($data['trigger_json'], true);
        }

        if ($request->id) {
            $rule = DiscountRule::findOrFail($request->id);
            $rule->update($data);
            $msg = 'Regla actualizada';
        } else {
            $rule = DiscountRule::create($data);
            $msg = 'Regla creada';
        }

        return response()->json(['success' => true, 'message' => $msg, 'rule' => $rule]);
    }

    public function destroy($id)
    {
        DiscountRule::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Regla eliminada']);
    }

    public function toggle($id)
    {
        $rule = DiscountRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        $state = $rule->is_active ? 'activada' : 'desactivada';
        return response()->json(['success' => true, 'message' => "Regla {$state}", 'is_active' => $rule->is_active]);
    }
}
