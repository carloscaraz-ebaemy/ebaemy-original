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
        $base   = $domain . '/ecommerce';

        $entries = collect();

        // ── Página principal ───────────────────────────────────────────────
        $entries->push([
            'loc'        => $base,
            'lastmod'    => now()->toDateString(),
            'changefreq' => 'daily',
            'priority'   => '1.0',
            'image'      => null,
        ]);

        // ── Categorías ─────────────────────────────────────────────────────
        try {
            foreach (Category::all() as $category) {
                $entries->push([
                    'loc'        => $base . '/' . Str::slug($category->name),
                    'lastmod'    => $category->updated_at
                                   ? $category->updated_at->format('Y-m-d')
                                   : now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                    'image'      => null,
                ]);
            }
        } catch (\Exception $e) {}

        // ── Productos (una sola entrada por URL, con imagen si existe) ──────
        try {
            $products = Item::where('apply_store', 1)
                ->whereNotNull('internal_id')
                ->select(['id', 'slug', 'description', 'image', 'updated_at', 'stock'])
                ->get();

            foreach ($products as $product) {
                $slug     = $product->slug ?: $product->id;
                $imageUrl = ($product->image && $product->image !== 'imagen-no-disponible.jpg')
                            ? asset('storage/uploads/items/' . $product->image)
                            : null;

                $entries->push([
                    'loc'        => $base . '/item/' . $slug,
                    'lastmod'    => $product->updated_at
                                   ? $product->updated_at->format('Y-m-d')
                                   : now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority'   => $product->stock > 0 ? '0.7' : '0.4',
                    'image'      => $imageUrl ? [
                        'loc'   => $imageUrl,
                        'title' => $product->description,
                    ] : null,
                ]);
            }
        } catch (\Exception $e) {}

        // ── Páginas legales ────────────────────────────────────────────────
        $legalPages = [
            '/terminos-condiciones',
            '/politica-privacidad',
            '/cambios-devolucion',
            '/politica-envio',
        ];

        foreach ($legalPages as $path) {
            $entries->push([
                'loc'        => $base . $path,
                'lastmod'    => now()->toDateString(),
                'changefreq' => 'monthly',
                'priority'   => '0.3',
                'image'      => null,
            ]);
        }

        // ── Construir XML ──────────────────────────────────────────────────
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($entries as $entry) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>'        . htmlspecialchars($entry['loc'])        . '</loc>'        . "\n";
            $xml .= '    <lastmod>'    . $entry['lastmod']                      . '</lastmod>'    . "\n";
            $xml .= '    <changefreq>' . $entry['changefreq']                   . '</changefreq>' . "\n";
            $xml .= '    <priority>'   . $entry['priority']                     . '</priority>'   . "\n";

            if (!empty($entry['image'])) {
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>'   . htmlspecialchars($entry['image']['loc'])   . '</image:loc>'   . "\n";
                $xml .= '      <image:title>' . htmlspecialchars($entry['image']['title']) . '</image:title>' . "\n";
                $xml .= '    </image:image>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
