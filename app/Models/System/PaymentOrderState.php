<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class PaymentOrderState extends Model
{
    use UsesSystemConnection;

    public function payment_orders()
    {
        return $this->hasMany(PaymentOrder::class, 'order_state_id');
    }
}
