<?php

namespace Modules\Ecommerce\Http\ViewComposers;

use App\Models\Tenant\Promotion;
use App\Models\Tenant\ConfigurationEcommerce;


class PromotionsViewComposer
{
    public function compose($view)
    {
        // visibleNow() respeta starts_at/ends_at; un banner sin fechas sigue
        // siendo visible siempre. sort_order define el orden del carrusel —
        // antes salían en el orden que devolvía la base.
        $view->items = Promotion::where('apply_restaurant', 0)
            ->visibleNow()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        
        $config = ConfigurationEcommerce::firstCached();
        $preferences = $config && $config->preferences ? $config->preferences : [];
        $view->full_width_banner = $preferences['full_width_banner'] ?? 0;
    }
}