<?php

namespace Modules\Restaurant\Http\ViewComposers;

use App\Models\Tenant\Promotion;
use App\Models\Tenant\ConfigurationEcommerce;


class PromotionsViewComposer
{
    public function compose($view)
    {
        // Promociones normales (sin spots)
        $view->items = Promotion::where('apply_restaurant', 1)
            ->where(function($query) {
                $query->whereNull('type')
                      ->orWhere('type', '!=', 'spots');
            })
            ->get();
        
        // Spots publicitarios
        $view->spots = Promotion::where('apply_restaurant', 1)
            ->where('type', 'spots')
            ->get();
            
        $config = ConfigurationEcommerce::first();
        $preferences = $config && $config->preferences ? $config->preferences : [];
        $view->full_width_banner = $preferences['full_width_banner'] ?? 0;
    }
}