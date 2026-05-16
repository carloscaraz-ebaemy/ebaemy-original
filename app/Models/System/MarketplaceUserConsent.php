<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. Cada grant/revoke crea una fila nueva.
 * Para conocer el estado actual: ultima fila por (user_id, channel, purpose).
 */
class MarketplaceUserConsent extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_consents';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'channel', 'purpose',
        'granted_at', 'revoked_at', 'source', 'ip', 'user_agent',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(MarketplaceUser::class, 'user_id');
    }
}
