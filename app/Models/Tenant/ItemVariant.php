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
        // Campos con herencia del padre (migración 2026_09_11_000002).
        // NULL = usar el del producto, igual que precio, costo, sku e imagen.
        'compare_at_price',
        'compare_at_from',
        'compare_at_until',
        'stock_min',
        'min_margin_pct',
        'weight',
        'length',
        'width',
        'height',
    ];

    protected $casts = [
        'sale_unit_price'     => 'float',
        'purchase_unit_price' => 'float',
        'stock'               => 'float',
        'is_active'           => 'boolean',
        'is_primary'          => 'boolean',
        'compare_at_price'    => 'float',
        'compare_at_from'     => 'date',
        'compare_at_until'    => 'date',
        'stock_min'           => 'float',
        'min_margin_pct'      => 'float',
        'weight'              => 'float',
        'length'              => 'float',
        'width'               => 'float',
        'height'              => 'float',
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
     * Valor efectivo de un campo con herencia: el propio si existe, el del
     * producto padre si es null.
     *
     * Centraliza la regla en un sitio. Repartida por el código, cada llamador
     * la escribía con `??` y alguno se equivocaba: con `0` como valor propio,
     * `$v->campo ?? $item->campo` funciona, pero `$v->campo ?: $item->campo`
     * cae al padre silenciosamente — y 0 es un valor legítimo para un peso o un
     * margen mínimo.
     */
    public function inherited(string $field)
    {
        if ($this->{$field} !== null) {
            return $this->{$field};
        }

        return $this->item ? $this->item->{$field} : null;
    }

    /**
     * ¿Esta variante tiene una oferta vigente propia? Si no la tiene, quien
     * pregunte debe caer a la del producto padre.
     */
    public function hasOwnActiveOffer(): bool
    {
        if ($this->compare_at_price === null || $this->compare_at_price <= 0) {
            return false;
        }

        $precio = $this->getEffectiveSalePrice();
        if ($this->compare_at_price <= $precio) {
            return false;
        }

        if ($this->compare_at_from && $this->compare_at_from->startOfDay()->gt(now())) {
            return false;
        }

        if ($this->compare_at_until && $this->compare_at_until->endOfDay()->lt(now())) {
            return false;
        }

        return true;
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
