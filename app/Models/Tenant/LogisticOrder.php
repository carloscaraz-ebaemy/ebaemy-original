<?php

namespace App\Models\Tenant;

use App\Enums\DeliveryTypeEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Inventory\Models\Warehouse;

class LogisticOrder extends ModelTenant
{
    protected $table = 'logistic_orders';

    protected $fillable = [
        'document_id',
        'sale_note_id',
        'customer_id',
        'user_id',
        'warehouse_user_id',
        'warehouse_id',
        'delivery_type',
        'status',
        'destination_district',
        'destination_address',
        'recipient_name',
        'recipient_phone',
        'shipping_guide_id',
        'subtotal',
        'igv',
        'total',
        'currency_type_id',
        'source',
        'notes',
        'cancel_reason',
        'confirmed_at',
        'preparation_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'delivery_type'   => DeliveryTypeEnum::class,
        'status'          => OrderStatusEnum::class,
        'confirmed_at'    => 'datetime',
        'preparation_at'  => 'datetime',
        'dispatched_at'   => 'datetime',
        'delivered_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'subtotal'        => 'float',
        'igv'             => 'float',
        'total'           => 'float',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(LogisticOrderItem::class);
    }

    public function shippingGuide(): HasOne
    {
        return $this->hasOne(LogisticShippingGuide::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Pedidos pendientes de atención en el almacén */
    public function scopeWarehouseQueue($query)
    {
        return $query->whereIn('status', array_map(
            fn(OrderStatusEnum $s) => $s->value,
            OrderStatusEnum::warehouseActiveStatuses()
        ))->where('delivery_type', DeliveryTypeEnum::PROVINCE->value)
          ->orderBy('confirmed_at');
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopePendingProvince($query)
    {
        return $query->where('delivery_type', DeliveryTypeEnum::PROVINCE->value)
                     ->where('status', OrderStatusEnum::CONFIRMED->value);
    }

    // ─── Helpers de estado ─────────────────────────────────────────────────────

    public function isProvince(): bool
    {
        return $this->delivery_type === DeliveryTypeEnum::PROVINCE;
    }

    public function canBeCancelledBy(): bool
    {
        return !in_array($this->status, [
            OrderStatusEnum::DISPATCHED,
            OrderStatusEnum::DELIVERED,
            OrderStatusEnum::CANCELLED,
        ]);
    }

    public function isActive(): bool
    {
        return !$this->status->isFinal();
    }
}
