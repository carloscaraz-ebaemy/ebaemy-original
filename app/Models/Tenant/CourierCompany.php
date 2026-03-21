<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class CourierCompany extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'bool'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
