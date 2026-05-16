<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserInterest extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_interests';
    public $incrementing = false;
    public $timestamps = false;
    // PK compuesta — Eloquent no la maneja nativamente, pero usamos
    // solo upserts/queries directos asi que no es problema.
    protected $primaryKey = null;

    protected $fillable = [
        'user_id', 'category_id', 'score', 'last_recalculated_at',
    ];

    protected $casts = [
        'score'                => 'float',
        'last_recalculated_at' => 'datetime',
    ];
}
