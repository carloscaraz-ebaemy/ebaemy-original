<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceCoupon;
use App\Models\System\MarketplaceUser;
use App\Models\System\MarketplaceUserCoupon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de cupones de PLATAFORMA.
 *
 * Tiene dos responsabilidades:
 *  1. Asignar cupones a usuarios (admin manual o automatico via jobs).
 *  2. Resolver cupones aplicables a un user en un contexto (tienda + subtotal).
 *
 * El checkout consume `availableForUser($user, $hostnameId, $subtotal)`
 * para listar los que aplican; al confirmar el pedido, `redeem(...)` marca
 * la fila usada y registra la referencia al order.
 *
 * NO interfiere con `tenant.coupons` (codigos publicos por tienda). Las
 * dos capas coexisten en el checkout — el descuento total es la suma.
 */
class MarketplaceCouponService
{
    /**
     * Asigna un coupon a un user (asignacion explicita desde admin o job).
     * Idempotente: si ya existe asignacion vigente sin uso, no duplica.
     */
    public function assignToUser(MarketplaceUser $user, MarketplaceCoupon $coupon, ?\DateTimeInterface $expiresAt = null): MarketplaceUserCoupon
    {
        $existing = MarketplaceUserCoupon::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->first();
        if ($existing) return $existing;

        return MarketplaceUserCoupon::create([
            'user_id'     => $user->id,
            'coupon_id'   => $coupon->id,
            'scope'       => $coupon->scope,
            'tenant_id'   => $coupon->tenant_id,
            'assigned_at' => now(),
            'expires_at'  => $expiresAt ?: $coupon->valid_until,
        ]);
    }

    /**
     * Devuelve cupones aplicables a este user para una compra en
     * (hostnameId, subtotal). Aplica filtros: ventana de validez,
     * is_active, scope, min_subtotal, max_per_user, max_redemptions.
     *
     * @return \Illuminate\Support\Collection<int, array{coupon: MarketplaceCoupon, discount: float, assignment_id: int}>
     */
    public function availableForUser(?MarketplaceUser $user, ?int $hostnameId, float $subtotal)
    {
        if (!$user) return collect();

        $assignments = MarketplaceUserCoupon::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->with('coupon')
            ->get();

        return $assignments
            ->filter(function ($asg) use ($hostnameId, $subtotal, $user) {
                $c = $asg->coupon;
                if (!$c || !$c->isWithinWindow()) return false;
                if ($asg->expires_at && $asg->expires_at->isPast())  return false;
                // Scope
                if ($c->scope === 'tenant' && (int) $c->tenant_id !== (int) $hostnameId) return false;
                // Subtotal minimo
                if ($c->min_subtotal !== null && $subtotal < $c->min_subtotal) return false;
                // Limite global del coupon
                if ($c->max_redemptions !== null) {
                    $used = MarketplaceUserCoupon::where('coupon_id', $c->id)
                        ->whereNotNull('used_at')->count();
                    if ($used >= $c->max_redemptions) return false;
                }
                // Limite por user
                $usedByUser = MarketplaceUserCoupon::where('user_id', $user->id)
                    ->where('coupon_id', $c->id)
                    ->whereNotNull('used_at')->count();
                if ($usedByUser >= $c->max_per_user) return false;
                return true;
            })
            ->map(function ($asg) use ($subtotal) {
                return [
                    'coupon'        => $asg->coupon,
                    'discount'      => $asg->coupon->discountFor($subtotal),
                    'assignment_id' => $asg->id,
                ];
            })
            ->filter(fn ($x) => $x['discount'] > 0)
            ->values();
    }

    /**
     * Marca la asignacion como usada y deja la referencia al order del tenant.
     * Idempotente: si ya esta used_at, no la pisa.
     */
    public function redeem(int $assignmentId, int $hostnameId, int $orderId): bool
    {
        return (bool) DB::connection('system')->table('marketplace_user_coupons')
            ->where('id', $assignmentId)
            ->whereNull('used_at')
            ->update([
                'used_at'              => now(),
                'redeemed_hostname_id' => $hostnameId,
                'redeemed_order_id'    => $orderId,
            ]);
    }
}
