<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature — catálogo de capacidades opcionales del sistema.
 *
 * Los features se asignan a planes via la tabla pivot plan_features.
 * Para verificar si el tenant activo tiene un feature:
 *   app(FeatureGate::class)->has('smart_stock')
 *   // o via macro de Gate:
 *   Gate::allows('feature:smart_stock')
 */
class Feature extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['key', 'name', 'description', 'category', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['limit', 'meta'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
