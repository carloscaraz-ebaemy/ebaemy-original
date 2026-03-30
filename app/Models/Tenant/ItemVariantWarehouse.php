<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de una variante en un almacén específico.
 * Espejo de item_warehouse pero por variante.
 *
 * stock_available se calcula: max(0, stock_physical - stock_committed)
 *
 * @property int $id
 * @property int $item_variant_id
 * @property int $warehouse_id
 * @property float $stock             legacy, sincronizado con stock_physical
 * @property float $stock_physical    stock real en el almacén
 * @property float $stock_committed   reservado por pedidos pendientes
 */
class ItemVariantWarehouse extends ModelTenant
{
    protected $table = 'item_variant_warehouse';

    protected $fillable = [
        'item_variant_id',
        'warehouse_id',
        'stock',
        'stock_physical',
        'stock_committed',
    ];

    protected $casts = [
        'stock'           => 'float',
        'stock_physical'  => 'float',
        'stock_committed' => 'float',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\Warehouse::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Stock disponible: físico menos comprometido.
     */
    public function getStockAvailableAttribute(): float
    {
        return max(0, $this->stock_physical - $this->stock_committed);
    }
}
