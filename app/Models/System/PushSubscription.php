<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

/**
 * Suscripción Web Push de un comprador del marketplace (system DB).
 * Ver migración 2026_05_18_200001_create_push_subscriptions_table.
 */
class PushSubscription extends Model
{
    protected $connection = 'system';
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'marketplace_user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MarketplaceUser::class, 'marketplace_user_id');
    }

    /**
     * Crea o actualiza la suscripción por su endpoint (idempotente).
     * El navegador puede re-suscribir el mismo endpoint; no duplicamos.
     */
    public static function store(array $sub, ?int $userId, ?string $userAgent = null): self
    {
        $endpoint = $sub['endpoint'] ?? '';
        $hash = hash('sha256', $endpoint);

        return self::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'marketplace_user_id' => $userId,
                'endpoint'            => $endpoint,
                'public_key'          => $sub['keys']['p256dh'] ?? '',
                'auth_token'          => $sub['keys']['auth'] ?? '',
                'content_encoding'    => $sub['contentEncoding'] ?? 'aes128gcm',
                'user_agent'          => $userAgent,
            ]
        );
    }
}
