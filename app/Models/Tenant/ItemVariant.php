<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Variante de producto (ej: "Rojo / M").
 *
 * Campos nullable heredan del Item padre cuando son null:
 *   - sale_unit_price → item->sale_unit_price
 *   - purchase_unit_price → item->purchase_unit_price
 *   - sku / barcode → item->internal_id / item->barcode
 *   - image → item->image
 *
 * @property int $id
 * @property int $item_id
 * @property string|null $sku
 * @property string|null $barcode
 * @property float|null $sale_unit_price
 * @property float|null $purchase_unit_price
 * @property string|null $image
 * @property string $variant_hash  MD5 de option_value_ids ordenados
 * @property string|null $display_name  "Rojo / M"
 * @property bool $is_active
 * @property float $stock  agregado de todos los almacenes
 */
class ItemVariant extends ModelTenant
{
    protected $table = 'item_variants';

    protected $fillable = [
        'item_id',
        'sku',
        'barcode',
        'sale_unit_price',
        'purchase_unit_price',
        'image',
        'variant_hash',
        'display_name',
        'is_active',
        'is_primary',
        'stock',
    ];

    protected $casts = [
        'sale_unit_price'     => 'float',
        'purchase_unit_price' => 'float',
        'stock'               => 'float',
        'is_active'           => 'boolean',
        'is_primary'          => 'boolean',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemOptionValue::class,
            'item_variant_value_map',
            'item_variant_id',
            'item_option_value_id'
        )->withPivot([])->orderBy('item_option_values.item_option_id');
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ItemVariantWarehouse::class, 'item_variant_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Precio de venta efectivo: propio si existe, sino hereda del padre.
     */
    public function getEffectiveSalePrice(): float
    {
        return $this->sale_unit_price ?? $this->item->sale_unit_price;
    }

    /**
     * Stock disponible en un almacén específico.
     * Retorna 0 si no existe registro para ese almacén.
     */
    public function stockAvailableIn(int $warehouseId): float
    {
        $wh = $this->warehouseStocks->firstWhere('warehouse_id', $warehouseId);
        if (!$wh) return 0.0;
        return max(0, $wh->stock_physical - $wh->stock_committed);
    }

    /**
     * Genera el hash MD5 a partir de un array de option_value_ids.
     */
    public static function buildHash(array $optionValueIds): string
    {
        sort($optionValueIds);
        return md5(implode(',', $optionValueIds));
    }
}
