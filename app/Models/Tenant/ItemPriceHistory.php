<?php

namespace App\Models\Tenant;

class ItemPriceHistory extends ModelTenant
{
    public $timestamps = false;

    protected $table = 'item_price_history';

    protected $fillable = [
        'item_id',
        'old_price',
        'new_price',
        'changed_by',
        'source',
        'created_at',
    ];

    protected $casts = [
        'old_price'  => 'float',
        'new_price'  => 'float',
        'created_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Registrar un cambio de precio.
     */
    public static function track(int $itemId, float $oldPrice, float $newPrice, ?string $changedBy = null, string $source = 'manual'): ?self
    {
        if (abs($oldPrice - $newPrice) < 0.01) {
            return null; // No hubo cambio real
        }

        return self::create([
            'item_id'    => $itemId,
            'old_price'  => $oldPrice,
            'new_price'  => $newPrice,
            'changed_by' => $changedBy ?? (auth()->user()->email ?? 'system'),
            'source'     => $source,
            'created_at' => now(),
        ]);
    }
}
