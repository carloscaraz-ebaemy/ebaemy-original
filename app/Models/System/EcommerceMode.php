<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class EcommerceMode extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'name', 'label', 'description',
        'default_features', 'default_settings', 'is_active',
    ];

    protected $casts = [
        'default_features' => 'array',
        'default_settings' => 'array',
        'is_active'        => 'boolean',
    ];

    public function scopeActive($q) { return $q->where('is_active', true); }
}
