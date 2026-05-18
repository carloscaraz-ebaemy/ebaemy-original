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
        'old_cost',
        'new_cost',
        'margin_at_change',
        'change_type',
        'changed_by',
        'source',
        'created_at',
    ];

    protected $casts = [
        'old_price'        => 'float',
        'new_price'        => 'float',
        'old_cost'         => 'float',
        'new_cost'         => 'float',
        'margin_at_change' => 'float',
        'created_at'       => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Registrar un cambio de precio (legacy — solo audita venta).
     * Mantener por compatibilidad. Para auditoría completa usar trackChange().
     */
    public static function track(int $itemId, float $oldPrice, float $newPrice, ?string $changedBy = null, string $source = 'manual'): ?self
    {
        if (abs($oldPrice - $newPrice) < 0.01) {
            return null;
        }

        return self::create([
            'item_id'     => $itemId,
            'old_price'   => $oldPrice,
            'new_price'   => $newPrice,
            'change_type' => 'price',
            'changed_by'  => $changedBy ?? (auth()->user()->email ?? 'system'),
            'source'      => $source,
            'created_at'  => now(),
        ]);
    }

    /**
     * Registrar un cambio completo (venta y/o costo) con margen efectivo.
     * Detecta automáticamente qué cambió. Usado por el hook saving de Item.
     */
    public static function trackChange(
        int $itemId,
        ?float $oldPrice,
        ?float $newPrice,
        ?float $oldCost,
        ?float $newCost,
        ?float $marginAtChange,
        ?string $changedBy = null,
        string $source = 'manual'
    ): ?self {
        $priceChanged = $oldPrice !== null && $newPrice !== null && abs($oldPrice - $newPrice) >= 0.01;
        $costChanged  = $oldCost  !== null && $newCost  !== null && abs($oldCost  - $newCost)  >= 0.01;

        if (!$priceChanged && !$costChanged) {
            return null;
        }

        $changeType = ($priceChanged && $costChanged) ? 'both' : ($priceChanged ? 'price' : 'cost');

        return self::create([
            'item_id'          => $itemId,
            'old_price'        => $priceChanged ? $oldPrice : null,
            'new_price'        => $priceChanged ? $newPrice : null,
            'old_cost'         => $costChanged  ? $oldCost  : null,
            'new_cost'         => $costChanged  ? $newCost  : null,
            'margin_at_change' => $marginAtChange,
            'change_type'      => $changeType,
            'changed_by'       => $changedBy ?? (auth()->user()->email ?? 'system'),
            'source'           => $source,
            'created_at'       => now(),
        ]);
    }
}
