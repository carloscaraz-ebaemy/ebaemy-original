<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ConfigurationEcommerce;
use Modules\Item\Models\Category;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    public function index()
    {
        $seo = ConfigurationEcommerce::first();

        if ($seo && !$seo->indexable) {
            abort(404);
        }

        $domain = request()->getScheme() . '://' . request()->getHost();
        $base = $domain . '/ecommerce';

        $urls = collect();

        // Página principal
        $urls->push([
            'loc'        => $base,
            'lastmod'    => now()->toDateString(),
            'changefreq' => 'daily',
            'priority'   => '1.0',
        ]);

        // Categorías
        try {
            $categories = Category::all();
            foreach ($categories as $category) {
                $name = Str::slug($category->name);
                $urls->push([
                    'loc'        => $base . '/' . $name,
                    'lastmod'    => $category->updated_at
                        ? $category->updated_at->format('Y-m-d')
                        : now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ]);
            }
        } catch (\Exception $e) {}

        // Productos
        try {
            $products = Item::where('apply_store', 1)
                ->whereNotNull('internal_id')
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->get();

            foreach ($products as $product) {
                $urls->push([
                    'loc'        => $base . '/item/' . $product->slug,
                    'lastmod'    => $product->updated_at
                        ? $product->updated_at->format('Y-m-d')
                        : now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.6',
                ]);
            }
        } catch (\Exception $e) {}

        // Páginas legales
        $legalPages = [
            ['loc' => $base . '/terminos-condiciones',  'priority' => '0.3'],
            ['loc' => $base . '/politica-privacidad',   'priority' => '0.3'],
            ['loc' => $base . '/cambios-devolucion',    'priority' => '0.3'],
            ['loc' => $base . '/politica-envio',        'priority' => '0.3'],
        ];

        foreach ($legalPages as $page) {
            $urls->push([
                'loc'        => $page['loc'],
                'lastmod'    => now()->toDateString(),
                'changefreq' => 'monthly',
                'priority'   => $page['priority'],
            ]);
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>'        . htmlspecialchars($url['loc']) . '</loc>'        . "\n";
            $xml .= '    <lastmod>'    . $url['lastmod']               . '</lastmod>'    . "\n";
            $xml .= '    <changefreq>' . $url['changefreq']            . '</changefreq>' . "\n";
            $xml .= '    <priority>'   . $url['priority']              . '</priority>'   . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}