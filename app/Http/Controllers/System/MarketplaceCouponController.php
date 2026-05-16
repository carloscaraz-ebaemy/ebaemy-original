<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceCoupon;
use App\Models\System\MarketplaceUser;
use App\Models\System\MarketplaceUserCoupon;
use App\Services\Marketplace\MarketplaceCouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SuperAdmin: gestion de cupones de plataforma.
 */
class MarketplaceCouponController extends Controller
{
    public function __construct(private MarketplaceCouponService $service) {}

    public function index()
    {
        return view('system.marketplace_coupons.index');
    }

    /** JSON list para la tabla del admin. */
    public function records(Request $request)
    {
        $rows = MarketplaceCoupon::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($c) {
                $assigned = MarketplaceUserCoupon::where('coupon_id', $c->id)->count();
                $used     = MarketplaceUserCoupon::where('coupon_id', $c->id)
                                                  ->whereNotNull('used_at')->count();
                return [
                    'id'             => $c->id,
                    'code'           => $c->code,
                    'name'           => $c->name,
                    'type'           => $c->type,
                    'value'          => $c->value,
                    'scope'          => $c->scope,
                    'tenant_id'      => $c->tenant_id,
                    'is_active'      => $c->is_active,
                    'valid_until'    => optional($c->valid_until)->toDateString(),
                    'assigned_count' => $assigned,
                    'used_count'     => $used,
                ];
            });
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:40|unique:marketplace_coupons,code',
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:percent,fixed',
            'value'           => 'required|numeric|min:0.01',
            'min_subtotal'    => 'nullable|numeric|min:0',
            'max_discount'    => 'nullable|numeric|min:0',
            'scope'           => 'required|in:platform,tenant',
            'tenant_id'       => 'nullable|integer',
            'valid_from'      => 'nullable|date',
            'valid_until'     => 'nullable|date|after:valid_from',
            'max_redemptions' => 'nullable|integer|min:1',
            'max_per_user'    => 'required|integer|min:1',
            'is_active'       => 'boolean',
        ]);

        if ($data['scope'] === 'tenant' && empty($data['tenant_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Si scope=tenant debes indicar tenant_id (hostname_id).',
            ], 422);
        }

        $data['code'] = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper($data['code'])));
        $data['created_by_admin_id'] = auth('admin')->id();
        $coupon = MarketplaceCoupon::create($data);

        return response()->json(['success' => true, 'data' => $coupon]);
    }

    public function update(Request $request, int $id)
    {
        $coupon = MarketplaceCoupon::findOrFail($id);
        $data = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'value'           => 'sometimes|numeric|min:0.01',
            'min_subtotal'    => 'nullable|numeric|min:0',
            'max_discount'    => 'nullable|numeric|min:0',
            'valid_from'      => 'nullable|date',
            'valid_until'     => 'nullable|date|after:valid_from',
            'max_redemptions' => 'nullable|integer|min:1',
            'max_per_user'    => 'sometimes|integer|min:1',
            'is_active'       => 'sometimes|boolean',
        ]);
        $coupon->update($data);
        return response()->json(['success' => true, 'data' => $coupon]);
    }

    public function toggle(int $id)
    {
        $coupon = MarketplaceCoupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        return response()->json(['success' => true, 'is_active' => $coupon->is_active]);
    }

    public function destroy(int $id)
    {
        // No borramos si tiene redenciones — auditoria. Solo desactivamos.
        $hasRedemptions = MarketplaceUserCoupon::where('coupon_id', $id)
            ->whereNotNull('used_at')->exists();
        if ($hasRedemptions) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede borrar: el cupon ya fue usado. Desactivalo en su lugar.',
            ], 409);
        }
        MarketplaceCoupon::destroy($id);
        return response()->json(['success' => true]);
    }

    /** POST /admin/marketplace/coupons/{id}/assign — bulk assign por emails. */
    public function assign(Request $request, int $id)
    {
        $coupon = MarketplaceCoupon::findOrFail($id);
        $data = $request->validate([
            'emails'     => 'required|array|max:500',
            'emails.*'   => 'email:rfc',
            'expires_at' => 'nullable|date',
        ]);

        $emails = array_map(fn ($e) => strtolower(trim($e)), $data['emails']);
        $users = MarketplaceUser::whereIn('email', $emails)->where('status', 'active')->get();
        $assigned = 0;
        $missingEmails = array_diff($emails, $users->pluck('email')->all());

        $expiresAt = !empty($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null;
        foreach ($users as $user) {
            $this->service->assignToUser($user, $coupon, $expiresAt);
            $assigned++;
        }

        return response()->json([
            'success'        => true,
            'assigned'       => $assigned,
            'missing_emails' => array_values($missingEmails),
        ]);
    }

    /** GET /admin/marketplace/coupons/{id}/assignments — lista de a quien fue asignado. */
    public function assignments(int $id)
    {
        $rows = DB::connection('system')->table('marketplace_user_coupons as uc')
            ->join('marketplace_users as u', 'u.id', '=', 'uc.user_id')
            ->where('uc.coupon_id', $id)
            ->orderByDesc('uc.id')
            ->limit(500)
            ->select(
                'uc.id', 'u.email', 'u.name',
                'uc.assigned_at', 'uc.used_at', 'uc.expires_at',
                'uc.redeemed_hostname_id', 'uc.redeemed_order_id'
            )
            ->get();
        return response()->json(['data' => $rows]);
    }
}
