<?php

namespace App\Http\ViewComposers\Tenant\Ecommerce;

use App\Models\Tenant\Promotion;


class PromotionsViewComposer
{
    public function compose($view)
    {
        $view->items = Promotion::where('status', 1)->get();
    }
}