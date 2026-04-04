<?php

namespace App\Models\Tenant;

class Referral extends ModelTenant
{
    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code',
        'status',
        'referrer_coupon_id',
        'referred_coupon_id',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referrerCoupon()
    {
        return $this->belongsTo(Coupon::class, 'referrer_coupon_id');
    }

    public function referredCoupon()
    {
        return $this->belongsTo(Coupon::class, 'referred_coupon_id');
    }
}
