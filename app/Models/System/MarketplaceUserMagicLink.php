<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserMagicLink extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_magic_links';

    public $timestamps = false;

    protected $fillable = [
        'email', 'token_hash', 'code_hash',
        'expires_at', 'consumed_at',
        'ip', 'user_agent', 'attempts',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
