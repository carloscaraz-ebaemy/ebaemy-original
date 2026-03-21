<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\Item;
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
        $sales = FlashSale::withoutGlobalScopes()
            ->with(['items:id,description,sale_unit_price,image'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($fs) {
                return [
                    'id'          => $fs->id,
                    'title'       => $fs->title,
                    'subtitle'    => $fs->subtitle,
                    'starts_at'   => $fs->starts_at?->format('Y-m-d H:i'),
                    'ends_at'     => $fs->ends_at->format('Y-m-d H:i'),
                    'active'      => $fs->active,
                    'is_live'     => $fs->is_active_now,
                    'items_count' => $fs->items->count(),
                    'items'       => $fs->items->map(fn($i) => [
                        'id'          => $i->id,
                        'description' => $i->description,
                        'regular_price' => $i->sale_unit_price,
                        'flash_price' => $i->pivot->flash_price,
                    ]),
                ];
            });

        return response()->json(['data' => $sales]);
        } catch (\Exception $e) {
            return response()->json(['data' => []]);
        }
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
}
