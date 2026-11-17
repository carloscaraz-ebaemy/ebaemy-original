<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Extra que se vende aparte del plan (p. ej. `promo_videos`), con el precio y
 * el cupo que se proponen al activarlo en una tienda.
 */
class Addon extends Model
{
    use UsesSystemConnection;

    public const PROMO_VIDEOS = 'promo_videos';

    protected $fillable = ['key', 'name', 'description', 'default_monthly_price', 'default_quota', 'is_active'];

    protected $casts = [
        'default_monthly_price' => 'float',
        'default_quota'         => 'integer',
        'is_active'             => 'boolean',
    ];
}
