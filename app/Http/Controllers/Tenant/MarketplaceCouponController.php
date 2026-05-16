<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceCoupon;
use App\Models\System\MarketplaceUser;
use App\Models\System\MarketplaceUserCoupon;
use App\Services\Marketplace\MarketplaceCouponService;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin del TENANT: cupones propios del seller que aplican SOLO a su
 * tienda dentro del marketplace ebaemy.com. Comparte tabla
 * marketplace_coupons (system) con los del SuperAdmin pero filtrados
 * por scope=tenant + tenant_id=this hostname.
 *
 * El seller NO puede crear platform-wide ni cupones para otras tiendas.
 */
class MarketplaceCouponController extends Controller
{
    public function __construct(private MarketplaceCouponService $service) {}

    private function hostnameId(): ?int
    {
        return optional(app(Environment::class)->hostname())->id;
    }

    /** Guard: este cupon pertenece a este tenant? */
    private function ownsCoupon(int $couponId): ?MarketplaceCoupon
    {
        $hostnameId = $this->hostnameId();
        if (!$hostnameId) return null;
        $coupon = MarketplaceCoupon::find($couponId);
        if (!$coupon) return null;
        if ($coupon->scope !== 'tenant' || (int) $coupon->tenant_id !== (int) $hostnameId) {
            return null;
        }
        return $coupon;
    }

    public function index()
    {
        if (!$this->hostnameId()) abort(404);
        return view('tenant.marketplace_coupons.index');
    }

    public function records(Request $request)
    {
        $hostnameId = $this->hostnameId();
        if (!$hostnameId) return response()->json(['data' => []]);

        $rows = MarketplaceCoupon::query()
            ->where('scope', 'tenant')
            ->where('tenant_id', $hostnameId)
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
                    'min_subtotal'   => $c->min_subtotal,
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
        $hostnameId = $this->hostnameId();
        if (!$hostnameId) return response()->json(['success' => false, 'message' => 'Sin contexto de tienda.'], 422);

        $data = $request->validate([
            'code'            => 'required|string|max:40|unique:system.marketplace_coupons,code',
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:percent,fixed',
            'value'           => 'required|numeric|min:0.01',
            'min_subtotal'    => 'nullable|numeric|min:0',
            'max_discount'    => 'nullable|numeric|min:0',
            'valid_from'      => 'nullable|date',
            'valid_until'     => 'nullable|date|after:valid_from',
            'max_redemptions' => 'nullable|integer|min:1',
            'max_per_user'    => 'required|integer|min:1',
            'is_active'       => 'boolean',
        ]);

        $data['code']      = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper($data['code'])));
        $data['scope']     = 'tenant';
        $data['tenant_id'] = $hostnameId;
        $coupon = MarketplaceCoupon::create($data);

        return response()->json(['success' => true, 'data' => $coupon]);
    }

    public function update(Request $request, int $id)
    {
        $coupon = $this->ownsCoupon($id);
        if (!$coupon) abort(404);

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
        $coupon = $this->ownsCoupon($id);
        if (!$coupon) abort(404);
        $coupon->update(['is_active' => !$coupon->is_active]);
        return response()->json(['success' => true, 'is_active' => $coupon->is_active]);
    }

    public function destroy(int $id)
    {
        $coupon = $this->ownsCoupon($id);
        if (!$coupon) abort(404);
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

    public function assign(Request $request, int $id)
    {
        $coupon = $this->ownsCoupon($id);
        if (!$coupon) abort(404);

        $data = $request->validate([
            'emails'     => 'required|array|max:500',
            'emails.*'   => 'email:rfc',
            'expires_at' => 'nullable|date',
        ]);

        $emails = array_map(fn ($e) => strtolower(trim($e)), $data['emails']);
        $users = MarketplaceUser::whereIn('email', $emails)->where('status', 'active')->get();
        $assigned = 0;
        $missing = array_diff($emails, $users->pluck('email')->all());

        $expiresAt = !empty($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null;
        foreach ($users as $user) {
            $this->service->assignToUser($user, $coupon, $expiresAt);
            $assigned++;
        }

        return response()->json([
            'success'        => true,
            'assigned'       => $assigned,
            'missing_emails' => array_values($missing),
        ]);
    }

    public function assignments(int $id)
    {
        $coupon = $this->ownsCoupon($id);
        if (!$coupon) abort(404);
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
