<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ConfigurationEcommerce;

class RobotsController extends Controller
{
    public function index()
    {
        $seo = ConfigurationEcommerce::first();
        $indexable = $seo ? $seo->indexable : false;
        $domain = request()->getScheme() . '://' . request()->getHost();

        if ($indexable) {
            $content = "User-agent: *\n";
            $content .= "Allow: /ecommerce/\n";
            $content .= "Disallow: /admin/\n";
            $content .= "Disallow: /login\n";
            $content .= "Disallow: /register\n";
            $content .= "Disallow: /ecommerce/detail_cart\n";
            $content .= "Disallow: /ecommerce/pay_cart\n";
            $content .= "Disallow: /ecommerce/login\n";
            $content .= "\n";
            $content .= "Sitemap: {$domain}/sitemap.xml\n";
        } else {
            $content = "User-agent: *\n";
            $content .= "Disallow: /\n";
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}