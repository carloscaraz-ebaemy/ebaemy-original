<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\Item;
use App\Models\Tenant\ModelTenant;

class RestaurantItemSupply extends ModelTenant
{
    protected $fillable = [
        'item_id',
        'supply_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    /**
     * Relación con el item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relación con el insumo
     */
    public function supply()
    {
        return $this->belongsTo(Supply::class);
    }
}
