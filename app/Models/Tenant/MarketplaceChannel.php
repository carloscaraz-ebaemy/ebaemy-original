<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceChannel extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'platform', 'name', 'status', 'credentials', 'settings',
        'last_sync_at', 'last_error_at', 'last_error_message',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'last_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function products() { return $this->hasMany(MarketplaceProduct::class, 'channel_id'); }
    public function orders() { return $this->hasMany(MarketplaceOrder::class, 'channel_id'); }
    public function syncLogs() { return $this->hasMany(MarketplaceSyncLog::class, 'channel_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopePlatform($q, string $platform) { return $q->where('platform', $platform); }

    public function getCredential(string $key, $default = null)
    {
        return data_get($this->credentials, $key, $default);
    }

    public function markSynced(): void
    {
        $this->update(['last_sync_at' => now(), 'last_error_at' => null, 'last_error_message' => null]);
    }

    public function markError(string $message): void
    {
        $this->update(['last_error_at' => now(), 'last_error_message' => $message, 'status' => 'error']);
    }
}
