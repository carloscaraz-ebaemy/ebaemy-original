<?php

namespace App\Models\Tenant;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends ModelTenant
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'user_id',
        'type',
        'qty_physical',
        'qty_committed',
        'stock_physical_after',
        'stock_committed_after',
        'stock_available_after',
        'reference_id',
        'reference_type',
        'notes',
    ];

    protected $casts = [
        'type'                  => StockMovementTypeEnum::class,
        'qty_physical'          => 'float',
        'qty_committed'         => 'float',
        'stock_physical_after'  => 'float',
        'stock_committed_after' => 'float',
        'stock_available_after' => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Fábrica estática para crear un movimiento con snapshot post-operación.
     */
    public static function record(
        ItemWarehouse       $iw,
        StockMovementTypeEnum $type,
        float               $qty,
        ?int                $userId = null,
        mixed               $reference = null,
        ?string             $notes = null
    ): self {
        return self::create([
            'item_id'               => $iw->item_id,
            'warehouse_id'          => $iw->warehouse_id,
            'user_id'               => $userId ?? auth()->id(),
            'type'                  => $type,
            'qty_physical'          => $type->physicalDelta($qty),
            'qty_committed'         => $type->committedDelta($qty),
            'stock_physical_after'  => $iw->stock_physical,
            'stock_committed_after' => $iw->stock_committed,
            'stock_available_after' => $iw->stock_available,
            'reference_id'          => $reference?->id,
            'reference_type'        => $reference ? get_class($reference) : null,
            'notes'                 => $notes,
        ]);
    }

    public function scopeByItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
