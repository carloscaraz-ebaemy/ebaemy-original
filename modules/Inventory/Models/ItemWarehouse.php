<?php

namespace Modules\Inventory\Models;

use App\Models\Tenant\ItemWarehouse as BaseItemWarehouse;
use Illuminate\Database\Eloquent\Builder;

/**
 * \Modules\Inventory\Models\ItemWarehouse
 *
 * Extends the canonical App\Models\Tenant\ItemWarehouse.
 * Only inventory-module-specific scopes live here; all shared
 * fillable, casts, relationships and stock logic are inherited.
 */
class ItemWarehouse extends BaseItemWarehouse
{
    /**
     * @param Builder $query
     * @param         $warehouse_id
     *
     * @return Builder
     */
    public function scopeWhereWarehouse(Builder $query, $warehouse_id)
    {
        if (!is_null($warehouse_id) && $warehouse_id !== 'all') {
            return $query->where('warehouse_id', $warehouse_id);
        }
        return $query;
    }

    /**
     * Filtrar por categoria del item - reporte inventario
     *
     * @param Builder $query
     * @param int $category_id
     *
     * @return Builder
     */
    public function scopeWhereItemCategory(Builder $query, $category_id)
    {
        return $query->whereHas('item', function ($q) use ($category_id) {
            $q->where('category_id', $category_id);
        });
    }

    /**
     * Filtrar por marca del item - reporte inventario
     *
     * @param Builder $query
     * @param int $brand_id
     *
     * @return Builder
     */
    public function scopeWhereItemBrand(Builder $query, $brand_id)
    {
        return $query->whereHas('item', function ($q) use ($brand_id) {
            $q->where('brand_id', $brand_id);
        });
    }

    /**
     * Obtener stock del producto por almacen
     *
     * @param  Builder $query
     * @param  int $item_id
     * @param  int $warehouse_id
     * @return Builder
     */
    public function scopeGetItemStockData($query, $item_id, $warehouse_id)
    {
        return $query->where([
            ['item_id', $item_id],
            ['warehouse_id', $warehouse_id],
        ]);
    }
}
