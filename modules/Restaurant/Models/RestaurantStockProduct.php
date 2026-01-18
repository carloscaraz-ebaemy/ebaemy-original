<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;

class RestaurantStockProduct extends ModelTenant
{
    public $timestamps = false;
    
    protected $fillable = [
        'item_id',
        'stock',
        'quantity_reserved',
        'has_supplies',
    ];
}