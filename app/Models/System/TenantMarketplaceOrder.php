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
        'coupon_code',
        'discount_amount',
        'platform_coupon_code',
        'platform_discount_amount',
        'platform_coupon_assignment_id',
        'item_count',
        'tenant_order_id',
        'tenant_order_external_id',
        'status',
        'sync_error',
        'retry_count',
        'dispatched_at',
        'reminder_sent_at',
        'reminder_count',
    ];

    protected $casts = [
        'subtotal'                 => 'float',
        'discount_amount'          => 'float',
        'platform_discount_amount' => 'float',
        'item_count'               => 'integer',
        'retry_count'              => 'integer',
        'dispatched_at'            => 'datetime',
        'reminder_sent_at'         => 'datetime',
        'reminder_count'           => 'integer',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_DELIVERED  = 'delivered';

    /**
     * Reintentos de dispatch antes de dar el subpedido por muerto.
     *
     * Vive aqui y no en la opcion del comando porque tres sitios necesitan la
     * MISMA cifra: el reintento programado, el contador del panel SuperAdmin y
     * el aviso de agotamiento. Con el numero suelto en cada uno, un subpedido
     * podia quedar fuera del reintento y a la vez no contarse como muerto.
     */
    public const MAX_DISPATCH_RETRIES = 3;

    /** Subpedidos que fallaron y ya no se van a reintentar solos. */
    public function scopeDead($q)
    {
        return $q->where('status', self::STATUS_FAILED)
                 ->where('retry_count', '>=', self::MAX_DISPATCH_RETRIES);
    }

    public function marketplaceOrder()
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
