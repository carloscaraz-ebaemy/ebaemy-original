<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\Item;
use App\Models\Tenant\PricingSettings;
use App\Services\Tenant\Pricing\PriceCalculator;
use App\Services\Tenant\WhatsAppOfferCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FlashSaleController extends Controller
{
    public function index()
    {
        return view('ecommerce::flash_sales.index');
    }

    public function records()
    {
        try {
            // withoutGlobalScopes() se mantiene: hyn/multi-tenant usa DB-per-tenant,
            // no hay riesgo de cross-tenant. Evita que algún global scope externo
            // (ej. una fecha legacy) oculte flash sales válidas al admin.
            $sales = FlashSale::withoutGlobalScopes()
                ->with(['items:id,description,sale_unit_price,image'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($fs) {
                    return [
                        'id'          => $fs->id,
                        'title'       => $fs->title,
                        'subtitle'    => $fs->subtitle,
                        'starts_at'   => optional($fs->starts_at)->format('Y-m-d H:i'),
                        // ends_at null-safe: si la migración antigua permitió nulls,
                        // no queremos que la UI explote con un TypeError.
                        'ends_at'     => optional($fs->ends_at)->format('Y-m-d H:i'),
                        'active'      => (bool) $fs->active,
                        'is_live'     => (bool) ($fs->is_active_now ?? false),
                        'items_count' => $fs->items->count(),
                        'items'       => $fs->items->map(fn($i) => [
                            'id'            => $i->id,
                            'description'   => $i->description,
                            'regular_price' => $i->sale_unit_price,
                            'flash_price'   => optional($i->pivot)->flash_price,
                            'image_url'     => ($i->image && $i->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/' . $i->image)
                                : asset('logo/imagen-no-disponible.jpg'),
                        ]),
                    ];
                });

            return response()->json(['data' => $sales]);
        } catch (\Throwable $e) {
            // Antes se devolvía array vacío en silencio — ocultaba fallos reales.
            // Ahora se loguea para diagnóstico. UI sigue recibiendo data=[] para
            // no romper el render.
            \Log::warning('FlashSale records fetch failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['data' => [], 'error' => 'No se pudieron cargar las flash sales']);
        }
    }

    /**
     * Evalúa la salud del margen de cada precio flash propuesto, reusando el
     * mismo PriceCalculator que el chip del formulario de producto. Así el
     * vendedor ve si una oferta flash queda bajo costo o bajo el margen mínimo
     * (piso) ANTES de guardar.
     *
     * Body: { items: [{ id, flash_price }] }
     * Respuesta: { data: { <id>: { status, margin_actual_pct, floor_price, ... } } }
     */
    public function priceCheck(Request $request)
    {
        $rows = $request->input('items', []);
        if (!is_array($rows) || empty($rows)) {
            return response()->json(['data' => []]);
        }

        $ids      = collect($rows)->pluck('id')->filter()->unique()->values();
        $models   = Item::whereIn('id', $ids)->get()->keyBy('id');
        $settings = PricingSettings::find(1);

        $out = [];
        foreach ($rows as $row) {
            $id    = $row['id'] ?? null;
            $flash = isset($row['flash_price']) ? (float) $row['flash_price'] : null;
            $item  = $id !== null ? ($models[$id] ?? null) : null;
            if (!$item || $flash === null) {
                continue;
            }

            $cost = (float) $item->purchase_unit_price;
            if ($cost <= 0) {
                // Sin costo no se puede validar margen — el front muestra "sin costo".
                $out[$id] = ['has_cost' => false];
                continue;
            }

            // Margen mínimo efectivo: override del item > override de categoría > default del tenant.
            $minMargin = $item->min_margin_pct !== null
                ? (float) $item->min_margin_pct
                : ($settings ? $settings->minMarginFor($item->category_id !== null ? (int) $item->category_id : null) : 0.0);

            $snap = PriceCalculator::snapshot(
                $cost,
                (float) ($item->landed_cost_extra_pct ?? 0),
                null,            // target_margin: no aplica a flash (solo piso/costo)
                $minMargin,
                $flash,          // sale_price = precio flash propuesto
                0,
                null
            );

            $out[$id] = [
                'has_cost'          => true,
                'status'            => $snap['status'],   // ok | warn_below_min | block_below_cost
                'margin_actual_pct' => round($snap['margin_actual_pct'], 1),
                'profit_per_unit'   => round($snap['profit_per_unit'], 2),
                'floor_price'       => round($snap['floor_price'], 2),
                'effective_cost'    => round($snap['effective_cost'], 2),
                'min_margin_pct'    => round($minMargin, 2),
                'liquidation_mode'  => (bool) ($item->liquidation_mode ?? false),
            ];
        }

        return response()->json(['data' => $out]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:100',
            'ends_at'  => 'required|date|after:now',
            'items'    => 'required|array|min:1',
            'items.*.id'          => 'required|integer',
            'items.*.flash_price' => 'required|numeric|min:0.01',
        ]);

        $fs = FlashSale::create([
            'title'      => $request->title,
            'subtitle'   => $request->subtitle,
            'starts_at'  => $request->starts_at,
            'ends_at'    => $request->ends_at,
            'active'     => $request->boolean('active', true),
        ]);

        $pivotData = collect($request->items)->mapWithKeys(function ($item) {
            return [$item['id'] => ['flash_price' => $item['flash_price']]];
        })->toArray();

        $fs->items()->sync($pivotData);
        Cache::forget('ecommerce_flash_sale');

        return response()->json(['success' => true, 'message' => 'Flash sale creada']);
    }

    public function update(Request $request, $id)
    {
        $fs = FlashSale::withoutGlobalScopes()->findOrFail($id);

        $request->validate([
            'title'   => 'required|string|max:100',
            'ends_at' => 'required|date',
        ]);

        $fs->update([
            'title'     => $request->title,
            'subtitle'  => $request->subtitle,
            'starts_at' => $request->starts_at,
            'ends_at'   => $request->ends_at,
            'active'    => $request->boolean('active', true),
        ]);

        if ($request->has('items')) {
            $pivotData = collect($request->items)->mapWithKeys(function ($item) {
                return [$item['id'] => ['flash_price' => $item['flash_price']]];
            })->toArray();
            $fs->items()->sync($pivotData);
        }

        Cache::forget('ecommerce_flash_sale');

        return response()->json(['success' => true, 'message' => 'Flash sale actualizada']);
    }

    public function destroy($id)
    {
        FlashSale::withoutGlobalScopes()->findOrFail($id)->delete();
        Cache::forget('ecommerce_flash_sale');
        return response()->json(['success' => true, 'message' => 'Flash sale eliminada']);
    }

    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'limit_customers' => 'nullable|integer|min:1|max:2000',
            'max_products' => 'nullable|integer|min:1|max:8',
            'cooldown_hours' => 'nullable|integer|min:0|max:720',
            'force' => 'nullable|boolean',
        ]);

        $result = (new WhatsAppOfferCampaignService())->runForFlashSale((int)$id, [
            'campaign_name' => 'Flash Sale #' . (int)$id,
            'limit_customers' => (int)$request->input('limit_customers', 200),
            'max_products' => (int)$request->input('max_products', 3),
            'cooldown_hours' => (int)$request->input('cooldown_hours', 48),
            'force' => (bool)$request->input('force', false),
            'base_ecommerce_url' => url('/ecommerce'),
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
