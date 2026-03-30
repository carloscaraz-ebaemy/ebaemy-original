<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'channel_id', 'external_order_id', 'status', 'customer_data',
        'items_data', 'shipping_data', 'total', 'currency',
        'order_id', 'sale_note_id', 'ordered_at', 'processed_at',
    ];

    protected $casts = [
        'customer_data' => 'array',
        'items_data' => 'array',
        'shipping_data' => 'array',
        'total' => 'float',
        'ordered_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function saleNote() { return $this->belongsTo(SaleNote::class); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
}
