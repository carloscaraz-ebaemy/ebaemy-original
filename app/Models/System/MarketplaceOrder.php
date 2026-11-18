<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Cabecera de un pedido multi-tienda creado desde ebaemy.com/marketplace.
 *
 * Una sola compra del cliente puede generar varios subpedidos (uno por
 * tenant/seller) que viven en `tenant_marketplace_orders`. Cada subpedido
 * apunta a un Order real dentro de la BD del tenant respectivo.
 */
class MarketplaceOrder extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_orders';

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_doc_type',
        'customer_doc_number',
        'customer_phone',
        'customer_email',
        'marketplace_user_id',
        'delivery_address',
        'delivery_department',
        'delivery_province',
        'delivery_district',
        'delivery_notes',
        'subtotal',
        'discount_total',
        'total',
        'items_count',
        'stores_count',
        'status',
        'payment_status',
        'source',
        'session_token',
        'source_ip',
        'source_ua',
        'payment_provider',
        'mp_preference_id',
        'mp_payment_id',
        'mp_init_point',
        'mp_payment_status',
        'payment_attempted_at',
        'payment_paid_at',
        'reminder_sent_at',
        'reminder_count',
    ];

    protected $casts = [
        'subtotal'             => 'float',
        'discount_total'       => 'float',
        'total'                => 'float',
        'items_count'          => 'integer',
        'stores_count'         => 'integer',
        'payment_attempted_at' => 'datetime',
        'payment_paid_at'      => 'datetime',
        'reminder_sent_at'     => 'datetime',
        'reminder_count'       => 'integer',
    ];

    public const STATUS_PENDING               = 'pending';
    public const STATUS_PARTIALLY_CONFIRMED   = 'partially_confirmed';
    public const STATUS_CONFIRMED             = 'confirmed';
    public const STATUS_PARTIALLY_CANCELLED   = 'partially_cancelled';
    public const STATUS_COMPLETED             = 'completed';
    public const STATUS_CANCELLED             = 'cancelled';

    public function items()
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'marketplace_order_id');
    }

    public function tenantOrders()
    {
        return $this->hasMany(TenantMarketplaceOrder::class, 'marketplace_order_id');
    }

    /**
     * Genera un order_number humano único: MP-YYYY-XXXXXX.
     */
    public static function generateOrderNumber(): string
    {
        do {
            $candidate = sprintf('MP-%s-%s', now()->format('Y'), strtoupper(\Illuminate\Support\Str::random(6)));
        } while (self::where('order_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Recalcula el status global a partir de los subpedidos por tienda.
     *  - todos dispatched     → confirmed
     *  - algunos dispatched   → partially_confirmed
     *  - todos failed         → cancelled (lo tenemos como fallido pero lo
     *                            marcamos cancelled para no dejarlo "vivo")
     *  - algunos failed/cancelled mezclados → partially_cancelled
     */
    public function recomputeStatus(): void
    {
        $children = $this->tenantOrders()->get(['status']);
        if ($children->isEmpty()) {
            return;
        }

        $statuses = $children->pluck('status');

        $allDispatched = $statuses->every(fn ($s) => in_array($s, ['dispatched', 'delivered']));
        $allFailed     = $statuses->every(fn ($s) => in_array($s, ['failed', 'cancelled']));
        $hasDispatched = $statuses->contains(fn ($s) => in_array($s, ['dispatched', 'delivered']));
        $hasFailed     = $statuses->contains(fn ($s) => in_array($s, ['failed', 'cancelled']));

        if ($allDispatched) {
            $newStatus = self::STATUS_CONFIRMED;
        } elseif ($allFailed) {
            $newStatus = self::STATUS_CANCELLED;
        } elseif ($hasDispatched && $hasFailed) {
            $newStatus = self::STATUS_PARTIALLY_CANCELLED;
        } elseif ($hasDispatched) {
            $newStatus = self::STATUS_PARTIALLY_CONFIRMED;
        } else {
            $newStatus = self::STATUS_PENDING;
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }
}
