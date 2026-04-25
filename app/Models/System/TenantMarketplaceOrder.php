<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Subpedido por tienda dentro de un MarketplaceOrder padre. Cada tenant que
 * vendió en la compra recibe uno de estos. `tenant_order_id` apunta al Order
 * real creado dentro de la BD del tenant cuando el dispatch fue exitoso.
 */
class TenantMarketplaceOrder extends Model
{
    use UsesSystemConnection;

    protected $table = 'tenant_marketplace_orders';

    protected $fillable = [
        'marketplace_order_id',
        'hostname_id',
        'tenant_fqdn',
        'client_id',
        'subtotal',
        'item_count',
        'tenant_order_id',
        'tenant_order_external_id',
        'status',
        'sync_error',
        'retry_count',
        'dispatched_at',
    ];

    protected $casts = [
        'subtotal'      => 'float',
        'item_count'    => 'integer',
        'retry_count'   => 'integer',
        'dispatched_at' => 'datetime',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_DELIVERED  = 'delivered';

    public function marketplaceOrder()
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
