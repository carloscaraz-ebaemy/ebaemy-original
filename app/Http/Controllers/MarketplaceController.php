<?php

namespace App\Http\Controllers;

use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use App\Services\System\MarketplaceOrderDispatcher;
use Illuminate\Http\Request;

/**
 * Vista pública del marketplace central (ebaemy.com/marketplace).
 * Consume el índice marketplace_listings, no toca BDs de tenants.
 * La compra se transforma en un Lead que un Dispatcher convierte en Order dentro
 * del tenant dueño del producto.
 */
class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $q        = $request->input('q');
        $category = $request->input('category');
        $sort     = $request->input('sort', 'relevance');

        $query = MarketplaceListing::published()
            ->search($q)
            ->category($category);

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('mp_price')->orderBy('price');
                break;
            case 'price_desc':
                $query->orderByDesc('mp_price')->orderByDesc('price');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            default:
                $query->orderByDesc('sort_score')->orderByDesc('view_count');
        }

        $listings   = $query->paginate(24)->withQueryString();
        $categories = MarketplaceListing::published()
            ->whereNotNull('category_name')
            ->select('category_name')
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->limit(40)
            ->pluck('category_name');

        return view('marketplace.index', compact('listings', 'categories', 'q', 'category', 'sort'));
    }

    public function show(string $slug)
    {
        $listing = MarketplaceListing::where('slug', $slug)->firstOrFail();

        // Pageview — se incrementa asíncronamente para no ralentizar render
        MarketplaceListing::where('id', $listing->id)->increment('view_count');

        $related = MarketplaceListing::published()
            ->where('id', '!=', $listing->id)
            ->where('category_name', $listing->category_name)
            ->limit(6)
            ->get();

        return view('marketplace.show', compact('listing', 'related'));
    }

    /**
     * Recibe el formulario "Solicitar/Comprar" y crea un lead. El
     * MarketplaceOrderDispatcher lo convierte en Order dentro del tenant.
     */
    public function lead(Request $request, string $slug, MarketplaceOrderDispatcher $dispatcher)
    {
        $listing = MarketplaceListing::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'customer_name'  => 'required|string|max:180',
            'customer_phone' => 'nullable|string|max:40',
            'customer_email' => 'nullable|email|max:180',
            'quantity'       => 'nullable|integer|min:1|max:999',
            'message'        => 'nullable|string|max:1000',
        ]);

        $lead = MarketplaceLead::create([
            'listing_id'     => $listing->id,
            'hostname_id'    => $listing->hostname_id,
            'tenant_fqdn'    => $listing->tenant_fqdn,
            'remote_item_id' => $listing->remote_item_id,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'quantity'       => $data['quantity'] ?? 1,
            'message'        => $data['message'] ?? null,
            'snapshot_title' => $listing->title,
            'snapshot_price' => $listing->display_price,
            'status'         => 'new',
            'source_ip'      => $request->ip(),
            'source_ua'      => substr((string) $request->header('User-Agent'), 0, 250),
        ]);

        // Intento sincrónico de despacho al tenant (si falla, queda en estado failed
        // para que un job o admin lo reintente más tarde).
        $dispatcher->dispatchLead($lead);

        return redirect()
            ->route('marketplace.thanks', ['slug' => $slug])
            ->with('lead_id', $lead->id);
    }

    public function thanks(string $slug)
    {
        $listing = MarketplaceListing::where('slug', $slug)->firstOrFail();
        return view('marketplace.thanks', compact('listing'));
    }

    /**
     * sitemap-marketplace.xml — expone todas las fichas públicas del marketplace
     * para que Google / Bing indexen los productos. Incluye la home y el detalle
     * de cada listing activo. Respuesta cacheada por 1 hora para reducir carga.
     */
    public function sitemap()
    {
        $listings = MarketplaceListing::published()
            ->orderByDesc('updated_at')
            ->limit(40000) // límite seguro, sitemap máx 50k
            ->get(['slug', 'updated_at', 'image_url', 'title']);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
              . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        $xml .= $this->sitemapUrl(url('/marketplace'), now(), '1.0', 'daily');

        foreach ($listings as $l) {
            $loc     = url('/marketplace/item/' . $l->slug);
            $lastmod = optional($l->updated_at)->toIso8601String() ?: now()->toIso8601String();
            $xml .= "<url>\n";
            $xml .= "  <loc>{$loc}</loc>\n";
            $xml .= "  <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "  <changefreq>weekly</changefreq>\n";
            $xml .= "  <priority>0.8</priority>\n";
            if ($l->image_url) {
                $xml .= "  <image:image>\n";
                $xml .= '    <image:loc>' . htmlspecialchars($l->image_url, ENT_XML1) . "</image:loc>\n";
                $xml .= '    <image:title>' . htmlspecialchars($l->title, ENT_XML1) . "</image:title>\n";
                $xml .= "  </image:image>\n";
            }
            $xml .= "</url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Allow: /marketplace',
            'Disallow: /admin',
            'Disallow: /login',
            '',
            'Sitemap: ' . url('/sitemap-marketplace.xml'),
        ];
        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    private function sitemapUrl(string $loc, $lastmod, string $priority, string $changefreq): string
    {
        $lastmod = $lastmod instanceof \DateTimeInterface ? $lastmod->toIso8601String() : (string) $lastmod;
        return "<url>\n"
             . "  <loc>{$loc}</loc>\n"
             . "  <lastmod>{$lastmod}</lastmod>\n"
             . "  <changefreq>{$changefreq}</changefreq>\n"
             . "  <priority>{$priority}</priority>\n"
             . "</url>\n";
    }
}
