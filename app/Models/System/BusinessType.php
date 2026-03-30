<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'name', 'label', 'description', 'recommended_theme_id',
        'suggested_categories', 'required_fields', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'suggested_categories' => 'array',
        'required_fields'      => 'array',
        'is_active'            => 'boolean',
    ];

    public function recommendedTheme() { return $this->belongsTo(Theme::class, 'recommended_theme_id'); }
    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }
}
