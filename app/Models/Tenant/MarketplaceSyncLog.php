<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncLog extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'channel_id', 'action', 'status', 'direction',
        'items_processed', 'items_success', 'items_failed',
        'details', 'duration_ms',
    ];

    protected $casts = ['details' => 'array'];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }

    public static function log(int $channelId, string $action, string $direction, callable $fn): self
    {
        $start = microtime(true);
        $log = new static([
            'channel_id' => $channelId,
            'action' => $action,
            'direction' => $direction,
            'status' => 'processing',
        ]);

        try {
            $result = $fn($log);
            $log->status = 'success';
            if (is_array($result)) {
                $log->items_processed = $result['processed'] ?? 0;
                $log->items_success = $result['success'] ?? 0;
                $log->items_failed = $result['failed'] ?? 0;
                $log->details = $result['details'] ?? null;
            }
        } catch (\Throwable $e) {
            $log->status = 'error';
            $log->details = ['error' => $e->getMessage(), 'trace' => substr($e->getTraceAsString(), 0, 1000)];
        }

        $log->duration_ms = (int) ((microtime(true) - $start) * 1000);
        $log->save();
        return $log;
    }
}
