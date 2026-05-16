<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserView extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_views';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'hostname_id', 'listing_id',
        'viewed_at', 'session_id', 'referrer',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];
}
