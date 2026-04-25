<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Línea de un MarketplaceOrder. Snapshot del listing al momento del checkout
 * (precio, título, imagen) — no se actualiza si el listing cambia luego.
 */
class MarketplaceOrderItem extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_order_items';

    protected $fillable = [
        'marketplace_order_id',
        'listing_id',
        'hostname_id',
        'tenant_fqdn',
        'remote_item_id',
        'title',
        'slug',
        'image_url',
        'unit_price',
        'quantity',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'quantity'   => 'integer',
        'total'      => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function listing()
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }
}
