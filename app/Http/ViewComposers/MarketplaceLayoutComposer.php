<?php

namespace App\Http\ViewComposers;

use App\Models\System\MarketplaceCategory;
use Illuminate\Support\Facades\Cache;

class MarketplaceLayoutComposer
{
    public function compose($view)
    {
        // Si el controller ya proveyó un nav scoped (p.ej. la página de
        // tienda con sus propias categorías), respetarlo y no sobreescribir.
        $data = $view->getData();
        if (array_key_exists('marketplaceNavCategories', $data)) {
            return;
        }

        $view->marketplaceNavCategories = self::navCategories();
    }

    /**
     * Raíces visibles con sus hijas, para el drawer «Categorías». Cacheado
     * 30 min. Lo usan el layout (para saber si pinta el botón) y
     * MarketplaceController::categoryMenu (el drawer en sí, pedido aparte).
     */
    public static function navCategories()
    {
        return Cache::remember(
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
