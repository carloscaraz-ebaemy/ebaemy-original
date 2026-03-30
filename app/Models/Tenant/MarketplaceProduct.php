<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceProduct extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'channel_id', 'item_id', 'item_variant_id', 'external_sku',
        'external_id', 'sync_status', 'external_data', 'last_error', 'synced_at',
    ];

    protected $casts = [
        'external_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }
    public function item() { return $this->belongsTo(Item::class); }
    public function variant() { return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }

    public function scopeSynced($q) { return $q->where('sync_status', 'synced'); }
    public function scopePending($q) { return $q->where('sync_status', 'pending'); }
    public function scopeError($q) { return $q->where('sync_status', 'error'); }
}
