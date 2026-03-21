<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticReturnItem extends ModelTenant
{
    protected $table = 'logistic_return_items';

    protected $fillable = [
        'logistic_return_id',
        'item_id',
        'warehouse_id',
        'quantity_returned',
        'quantity_restocked',
        'condition',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity_returned'  => 'float',
        'quantity_restocked' => 'float',
        'unit_price'         => 'float',
    ];

    public function logisticReturn(): BelongsTo
    {
        return $this->belongsTo(LogisticReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function conditionLabel(): string
    {
        return match($this->condition) {
            'BUENO'   => 'Buen estado',
            'DANADO'  => 'Dañado',
            'PARCIAL' => 'Parcialmente dañado',
            default   => $this->condition,
        };
    }

    public function conditionColor(): string
    {
        return match($this->condition) {
            'BUENO'   => 'success',
            'DANADO'  => 'danger',
            'PARCIAL' => 'warning',
            default   => 'secondary',
        };
    }

    public function getSubtotalAttribute(): float
    {
        return round($this->quantity_returned * $this->unit_price, 2);
    }
}
