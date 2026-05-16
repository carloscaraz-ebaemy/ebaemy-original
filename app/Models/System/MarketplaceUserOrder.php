<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserOrder extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_orders';

    protected $fillable = [
        'user_id', 'hostname_id', 'order_id',
        'total', 'currency', 'status',
        'confirmed_at', 'cancelled_at',
        'items_count', 'product_categories',
    ];

    protected $casts = [
        'total'              => 'float',
        'confirmed_at'       => 'datetime',
        'cancelled_at'       => 'datetime',
        'product_categories' => 'array',
    ];
}
