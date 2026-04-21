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
}
