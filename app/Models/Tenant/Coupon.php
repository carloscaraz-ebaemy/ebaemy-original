<?php

namespace App\Models\Tenant;

class Coupon extends ModelTenant
{
    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_amount',
        'max_uses',
        'used_count',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'active'     => 'boolean',
        'value'      => 'float',
        'min_amount' => 'float',
    ];

    /**
     * Calculate discount amount for a given subtotal.
     */
    public function calculateDiscount(float $amount): float
    {
        $discount = $this->type === 'percentage'
            ? round($amount * $this->value / 100, 2)
            : $this->value;

        return min($discount, $amount);
    }

    /**
     * Validate the coupon against amount and return error string or null.
     */
    public function validate(float $amount): ?string
    {
        if (!$this->active) {
            return 'El cupón no está activo.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'El cupón ha expirado.';
        }
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return 'El cupón ha alcanzado su límite de usos.';
        }
        if ($this->min_amount && $amount < $this->min_amount) {
            return 'Monto mínimo de compra: S/ ' . number_format($this->min_amount, 2);
        }
        return null;
    }
}
