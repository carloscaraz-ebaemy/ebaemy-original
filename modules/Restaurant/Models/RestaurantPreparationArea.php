<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;

class RestaurantPreparationArea extends ModelTenant
{
    protected $fillable = [
        'name',
        'printer'
    ];

    public function items()
    {
        return $this->hasMany(\App\Models\Tenant\Item::class, 'preparation_area_id');
    }
}
