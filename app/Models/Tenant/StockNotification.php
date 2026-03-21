<?php

namespace App\Models\Tenant;

class StockNotification extends ModelTenant
{
    protected $fillable = ['item_id', 'email', 'name', 'notified', 'notified_at'];

    protected $casts = [
        'notified'     => 'boolean',
        'notified_at'  => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
