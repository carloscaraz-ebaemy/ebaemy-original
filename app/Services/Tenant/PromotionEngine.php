<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\DiscountRule;
use App\Models\Tenant\Person;

/**
 * PromotionEngine — Motor unificado de promociones y descuentos.
 *
 * Centraliza TODA la lógica de descuentos:
 *   1. Cupones manuales (código del cliente)
 *   2. Reglas automáticas (volumen, monto mínimo, canal, flash sale)
 *   3. Canje de puntos de fidelidad
 *
 * Uso:
 *   $result = PromotionEngine::make($cart, $subtotal)
 *       ->withCoupon('PROMO10')
 *       ->withChannel($channelId, 'ecommerce')
 *       ->withPointRedemption($user, $requestedPoints)
 *       ->calculate();
 *
 * Retorna:
 *   [
 *     'subtotal'          => 150.00,
 *     'coupon_discount'   => 15.00,
 *     'rule_discount'     => 7.50,
 *     'points_discount'   => 10.00,
 *     'total_discount'    => 32.50,
 *     'final_total'       => 117.50,
 *     'applied_coupon'    => Coupon|null,
 *     'applied_rules'     => [DiscountRule, ...],
 *     'points_redeemed'   => 10.00,
 *     'points_earned'     => 5.00,
 *     'breakdown'         => [...],  // para mostrar al cliente
 *   ]
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
        $this->subtotal = $subtotal;
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

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ejecutar el motor y retornar el resultado de todos los descuentos.
     *
     * @return array
     * @throws \InvalidArgumentException si el cupón no es válido
     */
    public function calculate(): array
    {
        $breakdown  = [];
        $remaining  = $this->subtotal;

        // ── 1. Cupón manual (máxima prioridad) ───────────────────────────────
        [$couponDiscount, $appliedCoupon, $couponError] = $this->resolveCoupon($remaining);
        if ($couponError) {
            throw new \InvalidArgumentException($couponError);
        }
        if ($couponDiscount > 0) {
            $breakdown[]    = ['label' => 'Cupón ' . $this->couponCode, 'amount' => -$couponDiscount, 'type' => 'coupon'];
            $remaining     -= $couponDiscount;
        }

        // ── 2. Reglas automáticas ────────────────────────────────────────────
        [$ruleDiscount, $appliedRules] = $this->resolveRules($remaining, $appliedCoupon);
        foreach ($appliedRules as $rule) {
            $d = $rule->calculateDiscount($remaining);
            $breakdown[] = ['label' => $rule->name, 'amount' => -$d, 'type' => $rule->type];
        }
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

        $appliedRules    = [];
        $totalRuleDiscount = 0;
        $remaining       = $amountAfterCoupon;

        foreach ($rules as $rule) {
            // Si no es apilable y ya hay un cupón o regla anterior, saltar
            if (!$rule->stackable && ($appliedCoupon || !empty($appliedRules))) {
                continue;
            }

            if (!$rule->matches($this->cart, $remaining, $this->channelId, $this->channelType)) {
                continue;
            }

            $d = $rule->calculateDiscount($remaining);
            if ($d <= 0) {
                continue;
            }

            $appliedRules[]    = $rule;
            $totalRuleDiscount += $d;
            $remaining         -= $d;

            if ($remaining <= 0) {
                break;
            }
        }

        return [$totalRuleDiscount, $appliedRules];
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

        // Puntos disponibles del usuario
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

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Preview de descuentos para el carrito (sin lanzar excepción en cupón inválido).
     * Útil para el frontend: muestra qué descuentos aplican sin procesar el pago.
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
