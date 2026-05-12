<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return view('ecommerce::coupons.index');
    }

    public function records()
    {
        try {
            $coupons = Coupon::orderBy('created_at', 'desc')->get();

            // Resolver hostname_id del tenant actual para joinar con
            // tenant_marketplace_orders (system DB) y contar usos vía
            // marketplace por cupón. Si falla, dejamos en 0 (no rompemos).
            $mpUsesByCode = [];
            $mpRevenueByCode = [];
            try {
                $tenancy = app(\Hyn\Tenancy\Environment::class);
                $website = $tenancy->tenant();
                if ($website) {
                    $hostnameId = \DB::connection('system')
                        ->table('hostnames')
                        ->where('website_id', $website->id)
                        ->value('id');
                    if ($hostnameId) {
                        $rows = \DB::connection('system')
                            ->table('tenant_marketplace_orders')
                            ->where('hostname_id', $hostnameId)
                            ->whereNotNull('coupon_code')
                            ->where('coupon_code', '!=', '')
                            ->selectRaw('coupon_code, COUNT(*) AS uses, COALESCE(SUM(discount_amount), 0) AS revenue_discount')
                            ->groupBy('coupon_code')
                            ->get();
                        foreach ($rows as $r) {
                            $code = strtoupper($r->coupon_code);
                            $mpUsesByCode[$code]    = (int) $r->uses;
                            $mpRevenueByCode[$code] = (float) $r->revenue_discount;
                        }
                    }
                }
            } catch (\Throwable $_) {
                // Silenciamos: el coupon list debe seguir funcionando aunque
                // el conteo cross-DB falle (e.g. system DB inaccesible).
            }

            $data = $coupons->map(function ($c) use ($mpUsesByCode, $mpRevenueByCode) {
                $code = strtoupper($c->code);
                $mpUses = $mpUsesByCode[$code] ?? 0;
                return [
                    'id'              => $c->id,
                    'code'            => $c->code,
                    'type'            => $c->type,
                    'value'           => $c->value,
                    'min_amount'      => $c->min_amount,
                    'max_uses'        => $c->max_uses,
                    'used_count'      => $c->used_count,
                    'expires_at'      => $c->expires_at ? $c->expires_at->format('Y-m-d H:i') : null,
                    'active'          => $c->active,
                    'is_expired'      => $c->expires_at && $c->expires_at->isPast(),
                    'is_maxed'        => $c->max_uses && $c->used_count >= $c->max_uses,
                    // Métricas marketplace: cuántos pedidos del marketplace
                    // central usaron este código + cuánto descuento total acumulado.
                    'marketplace_uses'         => $mpUses,
                    'marketplace_discount_sum' => round($mpRevenueByCode[$code] ?? 0, 2),
                ];
            });

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['data' => []]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'       => 'required|string|max:50|unique:tenant.coupons,code',
            'type'       => 'required|in:percentage,fixed',
            'value'      => 'required|numeric|min:0.01',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $coupon = Coupon::create([
            'code'       => strtoupper(trim($request->code)),
            'type'       => $request->type,
            'value'      => $request->value,
            'min_amount' => $request->min_amount,
            'max_uses'   => $request->max_uses,
            'expires_at' => $request->expires_at,
            'active'     => $request->boolean('active', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Cupón creado', 'id' => $coupon->id]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $request->validate([
            'code'       => 'required|string|max:50|unique:tenant.coupons,code,' . $id,
            'type'       => 'required|in:percentage,fixed',
            'value'      => 'required|numeric|min:0.01',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $coupon->update([
            'code'       => strtoupper(trim($request->code)),
            'type'       => $request->type,
            'value'      => $request->value,
            'min_amount' => $request->min_amount,
            'max_uses'   => $request->max_uses,
            'expires_at' => $request->expires_at,
            'active'     => $request->boolean('active', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Cupón actualizado']);
    }

    public function destroy($id)
    {
        Coupon::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Cupón eliminado']);
    }

    /**
     * PATCH /ecommerce/coupons/{id}/toggle-active
     *
     * Endpoint dedicado que solo cambia el flag `active`. Más seguro que el
     * update() genérico porque NO expone ni permite modificar code/value/type.
     */
    public function toggleActive(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $validated = $request->validate([
            'active' => 'required|boolean',
        ]);
        $coupon->update(['active' => $validated['active']]);

        return response()->json([
            'success' => true,
            'message' => $coupon->active ? 'Cupón activado' : 'Cupón desactivado',
            'active'  => (bool) $coupon->active,
        ]);
    }
}
