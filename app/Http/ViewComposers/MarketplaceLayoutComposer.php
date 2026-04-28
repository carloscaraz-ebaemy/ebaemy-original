<?php

namespace App\Http\ViewComposers;

use App\Models\System\MarketplaceCategory;
use Illuminate\Support\Facades\Cache;

class MarketplaceLayoutComposer
{
    public function compose($view)
    {
        $view->marketplaceNavCategories = Cache::remember(
            'marketplace_nav_roots_with_children_v1',
            1800,
            function () {
                return MarketplaceCategory::query()
                    ->active()
                    ->visible()
                    ->roots()
                    ->orderBy('sort_order')
                    ->with(['children' => function ($q) {
                        $q->where('is_active', true)
                          ->where('is_visible_in_marketplace', true)
                          ->orderBy('sort_order')
                          ->select(['id', 'parent_id', 'name', 'full_slug']);
                    }])
                    ->get(['id', 'name', 'slug', 'full_slug', 'icon']);
            }
        );
    }
}
