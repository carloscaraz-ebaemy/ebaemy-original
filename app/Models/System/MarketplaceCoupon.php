<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceCoupon extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_coupons';

    protected $fillable = [
        'code', 'name', 'description',
        'type', 'value', 'min_subtotal', 'max_discount',
        'scope', 'tenant_id',
        'valid_from', 'valid_until',
        'max_redemptions', 'max_per_user',
        'created_by_admin_id', 'is_active',
    ];

    protected $casts = [
        'value'           => 'float',
        'min_subtotal'    => 'float',
        'max_discount'    => 'float',
        'valid_from'      => 'datetime',
        'valid_until'     => 'datetime',
        'is_active'       => 'boolean',
        'max_redemptions' => 'integer',
        'max_per_user'    => 'integer',
    ];

    public function userAssignments()
    {
        return $this->hasMany(MarketplaceUserCoupon::class, 'coupon_id');
    }

    /** ¿Esta vigente en este momento (independiente de redenciones)? */
    public function isWithinWindow(): bool
    {
        $now = now();
        if (!$this->is_active) return false;
        if ($this->valid_from  && $this->valid_from->isFuture()) return false;
        if ($this->valid_until && $this->valid_until->isPast())  return false;
        return true;
    }

    /**
     * Calcula el descuento a aplicar dado un subtotal. Aplica el cap
     * max_discount cuando es porcentual. Si min_subtotal no se cumple,
     * devuelve 0.
     */
    public function discountFor(float $subtotal): float
    {
        if ($this->min_subtotal !== null && $subtotal < $this->min_subtotal) return 0;
        $raw = $this->type === 'percent'
            ? round($subtotal * ($this->value / 100), 2)
            : (float) $this->value;
        if ($this->type === 'percent' && $this->max_discount !== null) {
            $raw = min($raw, $this->max_discount);
        }
        return min($raw, $subtotal);
    }
}
