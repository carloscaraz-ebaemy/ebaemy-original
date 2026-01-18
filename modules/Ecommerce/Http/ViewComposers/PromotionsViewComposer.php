<?php

namespace Modules\Ecommerce\Http\ViewComposers;

use App\Models\Tenant\Promotion;
use App\Models\Tenant\ConfigurationEcommerce;


class PromotionsViewComposer
{
    public function compose($view)
    {
        $view->items = Promotion::where('apply_restaurant', 0)->get();
        
        $config = ConfigurationEcommerce::first();
        $preferences = $config && $config->preferences ? $config->preferences : [];
        $view->full_width_banner = $preferences['full_width_banner'] ?? 0;
    }
}