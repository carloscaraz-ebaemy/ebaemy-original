<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;

class RobotsController extends Controller
{
    public function index()
    {
        $seo = ConfigurationEcommerce::first();
        // Por defecto permitimos indexación si no existe configuración SEO explícita.
        $indexable = $seo ? (bool) ($seo->indexable ?? true) : true;
        $domain = request()->getScheme() . '://' . request()->getHost();

        if ($indexable) {
            $content = "User-agent: *\n";
            $content .= "Allow: /ecommerce/\n";
            $content .= "Allow: /ecommerce/item/\n";
            $content .= "\n";
            $content .= "Disallow: /admin/\n";
            $content .= "Disallow: /api/\n";
            $content .= "Disallow: /dashboard/\n";
            $content .= "Disallow: /login\n";
            $content .= "Disallow: /register\n";
            $content .= "Disallow: /ecommerce/detail_cart\n";
            $content .= "Disallow: /ecommerce/pay_cart\n";
            $content .= "Disallow: /ecommerce/checkout\n";
            $content .= "Disallow: /ecommerce/cart/\n";
            $content .= "Disallow: /ecommerce/login\n";
            $content .= "Disallow: /ecommerce/stock-check\n";
            $content .= "Disallow: /ecommerce/order/\n";
            $content .= "Disallow: /ecommerce/configuration\n";
            $content .= "Disallow: /ecommerce/feed/\n";
            $content .= "Disallow: /storage/\n";
            $content .= "Allow: /storage/uploads/items/\n";
            $content .= "Allow: /storage/uploads/logos/\n";
            $content .= "Allow: /storage/uploads/promotions/\n";
            $content .= "Allow: /storage/uploads/favicons/\n";
            $content .= "\n";
            $content .= "Sitemap: {$domain}/ecommerce/sitemap.xml\n";
            $content .= "Sitemap: {$domain}/sitemap.xml\n";
        } else {
            $content = "User-agent: *\n";
            $content .= "Disallow: /\n";
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
