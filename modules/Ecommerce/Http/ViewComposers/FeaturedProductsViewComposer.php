<?php

namespace Modules\Ecommerce\Http\ViewComposers;

use App\Models\Tenant\Item;
use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\Catalogs\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Tenant\Api\ServiceController;

class FeaturedProductsViewComposer
{
    public function compose($view)
    {
        $exchange_rate_sale = $this->getExchangeRateSale();

        // Buscar el tag "Destacado" — clave prefijada por tenant para aislar datos en SaaS
        $tenantUuid  = app(\Hyn\Tenancy\Environment::class)->tenant()?->uuid ?? 'default';
        $featuredTag = Cache::remember('ec_' . $tenantUuid . '_featured_tag', 3600, function () {
            return Tag::whereRaw('LOWER(name) = ?', ['destacado'])->first();
        });

        $query = Item::with(['currency_type', 'warehouses', 'tags', 'tags.tag'])
            ->where('apply_store', 1)
            ->where('internal_id', '!=', null);

        // Si existe el tag "Destacado" y tiene productos asignados → filtrar por él
        if ($featuredTag) {
            $taggedItems = (clone $query)
                ->whereHas('tags', fn($q) => $q->where('tag_id', $featuredTag->id))
                ->latest()
                ->get()
                ->filter(fn($row) => $row->warehouses->sum('stock') > 0)
                ->values();

            // Si hay productos con el tag → usarlos; si no → fallback a recientes
            $items = $taggedItems->count() > 0 ? $taggedItems : $this->fallbackItems($query);
        } else {
            $items = $this->fallbackItems($query);
        }

        $view->items = $items->transform(function ($row) use ($exchange_rate_sale) {
            $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price * 1.18;

            return (object) [
                'id'                           => $row->id,
                'slug'                         => $row->slug ?: $row->id,
                'internal_id'                  => $row->internal_id,
                'unit_type_id'                 => $row->unit_type_id,
                'description'                  => $row->description,
                'sale_unit_price'              => ($row->currency_type_id === 'PEN') ? $sale_unit_price : $sale_unit_price * $exchange_rate_sale,
                'sale_unit'                    => $sale_unit_price,
                'currency_type_id'             => $row->currency_type_id,
                'has_igv'                      => (bool) $row->has_igv,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'currency_type_symbol'         => $row->currency_type->symbol,
                'image'                        => $row->image,
                'image_medium'                 => $row->image_medium,
                'image_small'                  => $row->image_small,
                'stock'                        => $row->warehouses->sum('stock'),
                'tags'                         => $row->tags->pluck('tag_id')->toArray(),
                'is_featured'                  => true,
            ];
        });

        $view->featuredTagExists = (bool) $featuredTag;
    }

    private function fallbackItems($query)
    {
        return $query->latest()->get()
            ->filter(fn($row) => $row->warehouses->sum('stock') > 0)
            ->values();
    }

    private function getExchangeRateSale(): float
    {
        $today = date('Y-m-d');
        $cacheKey = 'exchange_rate_sale_' . $today;

        return Cache::remember($cacheKey, 3600, function () use ($today) {
            $stored = ExchangeRate::where('date', $today)->first();
            if ($stored) {
                return (float) $stored->sale;
            }

            try {
                $exchange_rate = app(ServiceController::class)->exchangeRateTest($today);
                return (array_key_exists('sale', $exchange_rate) && $exchange_rate['sale'] > 0)
                    ? (float) $exchange_rate['sale']
                    : 1.0;
            } catch (\Exception $e) {
                Log::warning('FeaturedProductsViewComposer: exchange rate unavailable - ' . $e->getMessage());
                $last = ExchangeRate::orderBy('date', 'desc')->first();
                return $last ? (float) $last->sale : 1.0;
            }
        });
    }
}
