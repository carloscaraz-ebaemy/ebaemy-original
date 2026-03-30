<?php
namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    public function getActiveItems(int $warehouseId = null, int $page = 1, int $perPage = 50): array
    {
        $key = "catalog_items_{$warehouseId}_{$page}_{$perPage}";
        return Cache::remember($key, 900, function () use ($warehouseId, $page, $perPage) {
            return Item::where('internal_id', '!=', null)
                ->when($warehouseId, fn($q) => $q->whereHas('warehouses', fn($w) => $w->where('warehouse_id', $warehouseId)))
                ->with(['currency_type:id,symbol', 'category:id,name'])
                ->select('id', 'internal_id', 'description', 'sale_unit_price', 'currency_type_id', 'stock', 'image_small', 'category_id', 'barcode')
                ->orderBy('description')
                ->forPage($page, $perPage)
                ->get()
                ->toArray();
        });
    }

    public function getCategories(): array
    {
        return Cache::remember('catalog_categories', 3600, function () {
            return \App\Models\Tenant\Category::orderBy('name')->get(['id', 'name'])->toArray();
        });
    }

    public function getExchangeRate(): ?float
    {
        return Cache::remember('exchange_rate_usd', 3600, function () {
            return \App\Models\Tenant\ExchangeRate::where('currency_type_id', 'USD')
                ->whereDate('date', today())
                ->value('sale') ?? \App\Models\Tenant\ExchangeRate::where('currency_type_id', 'USD')
                ->latest('date')
                ->value('sale');
        });
    }

    public function invalidateItem(int $itemId): void
    {
        // Clear all catalog pages (simple approach)
        Cache::forget("catalog_items_null_1_50");
        Cache::forget("social_proof_item_{$itemId}");
    }

    public function invalidateAll(): void
    {
        Cache::forget('catalog_categories');
        Cache::forget('exchange_rate_usd');
    }
}
