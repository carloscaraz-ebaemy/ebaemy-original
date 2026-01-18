<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Restaurant\Models\RestaurantTable;
use App\Models\Tenant\Item;

class RestaurantItemOrderStatus extends ModelTenant
{

    protected $fillable = [
        'table_id',
        'item_id',
        'item',
        'quantity',
        'note',
        'status',
        'status_description'
    ];

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function itemModel()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
