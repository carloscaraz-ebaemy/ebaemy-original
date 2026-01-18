<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;

class RestaurantTableGroup extends ModelTenant
{
    public $timestamps = true;

    protected $fillable = [
        'main_table_id',
        'status',
        'opening_date',
        'total',
    ];

    protected $casts = [
        'main_table_id' => 'integer',
        'total' => 'decimal:2',
        'opening_date' => 'datetime',
    ];

    public function mainTable()
    {
        return $this->belongsTo(RestaurantTable::class, 'main_table_id');
    }

    public function tables()
    {
        return $this->hasMany(RestaurantTable::class, 'group_id');
    }
}
