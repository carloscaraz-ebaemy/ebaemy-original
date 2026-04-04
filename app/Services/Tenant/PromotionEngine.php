<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\DiscountRule;
use App\Models\Tenant\Person;

/**
 * PromotionEngine — Motor unificado de promociones y descuentos.
 *
 * Orden de aplicación:
 *   1. Cupones manuales (código del cliente) — máxima prioridad
 *   2. Reglas automáticas (volumen, monto mínimo, canal, flash sale, bundle)
 *   3. Canje de puntos de fidelidad — mínima prioridad
 */
class PromotionEngine
{
    private array   $cart;
    private float   $subtotal;
    private ?string $couponCode     = null;
    private ?int    $channelId      = null;
    private ?string $channelType    = null;
    private ?Person $user           = null;
    private float   $pointsRequested = 0;

    private function __construct(array $cart, float $subtotal)
    {
        $this->cart     = $cart;
        $this->subtotal = max(0, $subtotal);
    }

    public static function make(array $cart, float $subtotal): self
    {
        return new self($cart, $subtotal);
    }

    public function withCoupon(?string $code): self
    {
        $this->couponCode = $code ? strtoupper(trim($code)) : null;
        return $this;
    }

    public function withChannel(?int $channelId, ?string $channelType = null): self
    {
        $this->channelId   = $channelId;
        $this->channelType = $channelType;
        return $this;
    }

    public function withPointRedemption(?Person $user, float $requested = 0): self
    {
        $this->user           = $user;
        $this->pointsRequested = $requested;
        return $this;
    }

    /**
     * Ejecutar el motor y retornar el resultado de todos los descuentos.
     */
    public function calculate(): array
    {
        $breakdown = [];
        $remaining = $this->subtotal;

        // ── 1. Cupón manual (máxima prioridad) ───────────────────────────────
        [$couponDiscount, $appliedCoupon, $couponError] = $this->resolveCoupon($remaining);
        if ($couponError) {
            throw new \InvalidArgumentException($couponError);
        }
        if ($couponDiscount > 0) {
            $breakdown[] = ['label' => 'Cupón ' . $this->couponCode, 'amount' => -$couponDiscount, 'type' => 'coupon'];
            $remaining  -= $couponDiscount;
        }

        // ── 2. Reglas automáticas ────────────────────────────────────────────
        [$ruleDiscount, $appliedRules, $ruleBreakdown] = $this->resolveRules($remaining, $appliedCoupon);
        $breakdown = array_merge($breakdown, $ruleBreakdown);
        if ($ruleDiscount > 0) {
            $remaining -= $ruleDiscount;
        }

        // ── 3. Canje de puntos ───────────────────────────────────────────────
        [$pointsDiscount, $pointsEarned] = $this->resolvePoints($remaining);
        if ($pointsDiscount > 0) {
            $breakdown[] = ['label' => 'Puntos canjeados', 'amount' => -$pointsDiscount, 'type' => 'points'];
            $remaining  -= $pointsDiscount;
        }

        $finalTotal    = max(0, round($remaining, 2));
        $totalDiscount = round($this->subtotal - $finalTotal, 2);

        return [
            'subtotal'        => $this->subtotal,
            'coupon_discount' => round($couponDiscount, 2),
            'rule_discount'   => round($ruleDiscount, 2),
            'points_discount' => round($pointsDiscount, 2),
            'total_discount'  => $totalDiscount,
            'final_total'     => $finalTotal,
            'applied_coupon'  => $appliedCoupon,
            'applied_rules'   => $appliedRules,
            'points_redeemed' => round($pointsDiscount, 2),
            'points_earned'   => round($pointsEarned, 2),
            'breakdown'       => $breakdown,
        ];
    }

    // ─── Resolvers privados ──────────────────────────────────────────────────

    private function resolveCoupon(float $amount): array
    {
        if (!$this->couponCode) {
            return [0, null, null];
        }

        $coupon = Coupon::where('code', $this->couponCode)->first();

        if (!$coupon) {
            return [0, null, 'El cupón "' . $this->couponCode . '" no existe.'];
        }

        $error = $coupon->validate($amount);
        if ($error) {
            return [0, null, $error];
        }

        return [$coupon->calculateDiscount($amount), $coupon, null];
    }

    private function resolveRules(float $amountAfterCoupon, ?Coupon $appliedCoupon): array
    {
        $rules = DiscountRule::active()
                             ->forChannel($this->channelId, $this->channelType)
                             ->byPriority()
                             ->get();

        $appliedRules      = [];
        $totalRuleDiscount = 0;
        $remaining         = $amountAfterCoupon;
        $breakdown         = [];
        $hasExclusiveApplied = false;

        foreach ($rules as $rule) {
            if ($remaining <= 0) break;

            // FIX BUG #3: Lógica stackable corregida
            // Si ya se aplicó una regla exclusiva, saltar todo lo demás
            if ($hasExclusiveApplied) {
                continue;
            }
            // Si esta regla no es stackable y ya hay cupón u otras reglas, saltar
            if (!$rule->stackable && ($appliedCoupon || !empty($appliedRules))) {
                continue;
            }

            if (!$rule->matches($this->cart, $this->subtotal, $this->channelId, $this->channelType)) {
                continue;
            }

            $d = $rule->calculateDiscount($remaining);
            if ($d <= 0) {
                continue;
            }

            $appliedRules[]    = $rule;
            $totalRuleDiscount += $d;
            $remaining         -= $d;

            // FIX BUG #4: breakdown usa el descuento real calculado en cada paso
            $breakdown[] = ['label' => $rule->name, 'amount' => -$d, 'type' => $rule->type];

            // FIX BUG #7: incrementar used_count
            $rule->increment('used_count');

            // Si la regla no es stackable, marcar como exclusiva
            if (!$rule->stackable) {
                $hasExclusiveApplied = true;
            }
        }

        return [$totalRuleDiscount, $appliedRules, $breakdown];
    }

    private function resolvePoints(float $amountAfterDiscounts): array
    {
        if (!$this->user || $this->pointsRequested <= 0) {
            return [0, 0];
        }

        $ptConfig = Configuration::getDataPointSystem();

        if (!$ptConfig->enabled_point_system) {
            return [0, 0];
        }

        $balance = (float) $this->user->accumulated_points;

        // Máximo canjeable: 50% del monto actual
        $maxByPercent = $amountAfterDiscounts * 0.5;

        $pointsDiscount = min($balance, $maxByPercent, $this->pointsRequested);
        $pointsDiscount = max(0, round($pointsDiscount, 2));

        // Puntos a ganar con esta compra
        $pointsEarned = 0;
        if ($ptConfig->point_system_sale_amount > 0) {
            $rawEarned    = ($amountAfterDiscounts / $ptConfig->point_system_sale_amount) * $ptConfig->quantity_of_points;
            $pointsEarned = $ptConfig->round_points_of_sale ? intval($rawEarned) : round($rawEarned, 2);
        }

        return [$pointsDiscount, $pointsEarned];
    }

    /**
     * Preview sin lanzar excepción en cupón inválido.
     */
    public function preview(): array
    {
        try {
            return $this->calculate();
        } catch (\InvalidArgumentException $e) {
            return [
                'subtotal'        => $this->subtotal,
                'coupon_discount' => 0,
                'rule_discount'   => 0,
                'points_discount' => 0,
                'total_discount'  => 0,
                'final_total'     => $this->subtotal,
                'applied_coupon'  => null,
                'applied_rules'   => [],
                'points_redeemed' => 0,
                'points_earned'   => 0,
                'breakdown'       => [],
                'coupon_error'    => $e->getMessage(),
            ];
        }
    }
}
