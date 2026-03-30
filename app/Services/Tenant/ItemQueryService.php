<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use Illuminate\Http\Request;

/**
 * Encapsula queries de listado/búsqueda de Items.
 * Extraído de ItemController para reducir God Controller.
 */
class ItemQueryService
{
    protected array $defaultWith = [
        'item_type', 'unit_type', 'currency_type',
        'warehouses', 'item_unit_types', 'tags',
    ];

    /**
     * Build filtered query for item records.
     */
    public function getFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Item::with($this->defaultWith);

        // Column search
        if ($request->filled('column') && $request->filled('value')) {
            $column = $request->column;
            $value = $request->value;

            if ($column === 'brand') {
                $query->whereHas('brand', fn($q) => $q->where('name', 'like', "%{$value}%"));
            } elseif ($column === 'category') {
                $query->whereHas('category', fn($q) => $q->where('name', 'like', "%{$value}%"));
            } elseif ($column === 'active') {
                $query->where('active', (int) $value);
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        // Tipo de item
        if ($request->filled('item_type')) {
            $query->where('item_type_id', $request->item_type);
        }

        // Warehouse filter
        if ($request->filled('warehouse_id')) {
            $query->whereHas('warehouses', fn($q) => $q->where('warehouse_id', $request->warehouse_id));
        }

        return $query->latest('id');
    }

    /**
     * Get paginated records.
     */
    public function getPaginatedRecords(Request $request, ?int $perPage = null)
    {
        $perPage = $perPage ?? config('tenant.items_per_page', 20);
        return $this->getFilteredQuery($request)->paginate($perPage);
    }

    /**
     * Search items for documents/sale notes (lightweight).
     */
    public function searchForDocuments(string $search, ?int $warehouseId = null, int $limit = 20)
    {
        return Item::with(['item_type', 'unit_type', 'currency_type', 'warehouses', 'item_unit_types'])
            ->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('internal_id', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            })
            ->when($warehouseId, fn($q) => $q->whereHas('warehouses', fn($w) => $w->where('warehouse_id', $warehouseId)))
            ->limit($limit)
            ->get();
    }
}
