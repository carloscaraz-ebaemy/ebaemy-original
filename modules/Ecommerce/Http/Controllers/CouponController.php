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
            $coupons = Coupon::orderBy('created_at', 'desc')->get()->map(function ($c) {
                return [
                    'id'          => $c->id,
                    'code'        => $c->code,
                    'type'        => $c->type,
                    'value'       => $c->value,
                    'min_amount'  => $c->min_amount,
                    'max_uses'    => $c->max_uses,
                    'used_count'  => $c->used_count,
                    'expires_at'  => $c->expires_at ? $c->expires_at->format('Y-m-d H:i') : null,
                    'active'      => $c->active,
                    'is_expired'  => $c->expires_at && $c->expires_at->isPast(),
                    'is_maxed'    => $c->max_uses && $c->used_count >= $c->max_uses,
                ];
            });
            return response()->json(['data' => $coupons]);
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
}
