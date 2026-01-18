<?php

namespace Modules\Ecommerce\Http\ViewComposers;

use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Company;



class InformationContactViewComposer
{
    public function compose($view)
    {
        $view->information = ConfigurationEcommerce::first();
        // Agregar información de la empresa (nombre) para usarla en vistas como el footer
        $view->company = Company::first();
    }
}
