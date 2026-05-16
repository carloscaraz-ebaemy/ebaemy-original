<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserCoupon extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_coupons';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'coupon_id', 'scope', 'tenant_id',
        'assigned_at', 'used_at', 'expires_at',
        'redeemed_hostname_id', 'redeemed_order_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'used_at'     => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(MarketplaceCoupon::class, 'coupon_id');
    }
}
