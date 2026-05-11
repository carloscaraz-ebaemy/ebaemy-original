<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceReview;
use App\Services\System\MarketplaceOrderDispatcher;
use Illuminate\Http\Request;

/**
 * Panel de moderación del marketplace central. Accesible solo para admins del
 * landlord. Permite revisar listings publicados por tenants, pausar/activar/
 * rechazar y ver los leads (solicitudes) generados desde el storefront.
 */
class MarketplaceAdminController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        // Fix: distinct('col') + count('col') no funcionaba — Laravel ignora
        // el argumento de distinct. Forma correcta: distinct()->count('col')
        // que emite COUNT(DISTINCT col).
        $stats = [
            'listings_total'   => MarketplaceListing::count(),
            'listings_active'  => MarketplaceListing::where('status', 'active')->count(),
            'listings_paused'  => MarketplaceListing::where('status', 'paused')->count(),
            'listings_rejected' => MarketplaceListing::where('status', 'rejected')->count(),
            'tenants_active'   => MarketplaceListing::where('is_active', true)
                                    ->distinct()
                                    ->count('hostname_id'),
            'views_total'      => (int) MarketplaceListing::sum('view_count'),
            'clicks_total'     => (int) MarketplaceListing::sum('click_count'),
            'leads_total'      => MarketplaceLead::count(),
            'leads_converted'  => MarketplaceLead::where('status', 'converted')->count(),
            'leads_failed'     => MarketplaceLead::where('status', 'failed')->count(),
            'leads_30d'        => MarketplaceLead::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Tasa de conversión global click → lead
        $stats['conversion_rate'] = $stats['clicks_total'] > 0
            ? round(($stats['leads_total'] / $stats['clicks_total']) * 100, 2)
            : 0;

        // Pedidos marketplace (tenant_marketplace_orders) — multi-tienda real.
        // Estos son los pedidos del checkout central, distintos de los leads
        // (que pueden o no convertirse).
        $ordersAgg = \DB::connection('system')->table('tenant_marketplace_orders')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_30d,
                COALESCE(SUM(subtotal), 0) as gross,
                COALESCE(SUM(discount_amount), 0) as discount,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN subtotal - discount_amount ELSE 0 END), 0) as revenue_30d
            ', [now()->subDays(30), now()->subDays(30)])
            ->first();

        $stats['orders_total']    = (int) ($ordersAgg->total ?? 0);
        $stats['orders_30d']      = (int) ($ordersAgg->last_30d ?? 0);
        $stats['revenue_total']   = round((float) (($ordersAgg->gross ?? 0) - ($ordersAgg->discount ?? 0)), 2);
        $stats['revenue_30d']     = round((float) ($ordersAgg->revenue_30d ?? 0), 2);

        // Top 10 tiendas por leads
        $topTenants = MarketplaceListing::selectRaw('tenant_fqdn, SUM(view_count) as v, SUM(click_count) as c, SUM(lead_count) as l, COUNT(*) as listings')
            ->groupBy('tenant_fqdn')
            ->orderByDesc('l')
            ->limit(10)
            ->get();

        // Top 10 productos por clicks
        $topListings = MarketplaceListing::orderByDesc('click_count')
            ->limit(10)
            ->get();

        // Serie diaria 30d: leads + orders (combinada para chart)
        $leadsByDay  = MarketplaceLead::selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')->orderBy('day')->pluck('count', 'day');
        $ordersByDay = \DB::connection('system')->table('tenant_marketplace_orders')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')->orderBy('day')->pluck('count', 'day');

        // Serie continua para que el chart no tenga huecos
        $dailySeries = collect();
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $dailySeries->push((object) [
                'day'    => $d,
                'leads'  => (int) ($leadsByDay[$d] ?? 0),
                'orders' => (int) ($ordersByDay[$d] ?? 0),
            ]);
        }

        // Revenue por tenant (top 5) — para doughnut chart
        $revenueByTenant = \DB::connection('system')->table('tenant_marketplace_orders as tmo')
            ->join('hostnames as h', 'h.id', '=', 'tmo.hostname_id')
            ->selectRaw('h.fqdn as tenant_fqdn, COALESCE(SUM(tmo.subtotal - tmo.discount_amount), 0) as revenue')
            ->groupBy('h.fqdn')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // Distribución de listings por estado
        $listingsByStatus = MarketplaceListing::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        // Top categorías oficiales — qué taxonomía tiene más productos
        $topCategories = \DB::connection('system')->table('marketplace_listings as ml')
            ->join('marketplace_categories as mc', 'mc.id', '=', 'ml.marketplace_category_id')
            ->where('ml.is_active', true)
            ->selectRaw('mc.name, COUNT(*) as cnt, SUM(ml.view_count) as views, SUM(ml.click_count) as clicks')
            ->groupBy('mc.id', 'mc.name')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        // Funnel global de conversión
        $funnel = [
            ['stage' => 'Vistas',  'value' => $stats['views_total'],  'rate' => 100],
            ['stage' => 'Clicks',  'value' => $stats['clicks_total'], 'rate' => $stats['views_total']  > 0 ? round($stats['clicks_total'] / $stats['views_total'] * 100, 2) : 0],
            ['stage' => 'Leads',   'value' => $stats['leads_total'],  'rate' => $stats['views_total']  > 0 ? round($stats['leads_total']  / $stats['views_total'] * 100, 2) : 0],
            ['stage' => 'Pedidos', 'value' => $stats['orders_total'], 'rate' => $stats['views_total']  > 0 ? round($stats['orders_total'] / $stats['views_total'] * 100, 2) : 0],
        ];

        return view('system.marketplace.dashboard', compact(
            'stats', 'topTenants', 'topListings', 'leadsByDay', 'dailySeries',
            'revenueByTenant', 'listingsByStatus', 'topCategories', 'funnel'
        ));
    }

    // ── SEO / Open Graph del marketplace ──────────────────────────────────────
    //
    // Permite al SuperAdmin editar el título, descripción e imagen que aparece
    // cuando alguien comparte ebaemy.com/marketplace por WhatsApp, Facebook,
    // Twitter, etc. La imagen debe ser 1200×630 para ratio óptimo.

    public function seo()
    {
        $config = \App\Models\System\Configuration::firstCached();
        return view('system.marketplace.seo', compact('config'));
    }

    public function seoUpdate(Request $request)
    {
        $request->validate([
            'marketplace_og_title'       => 'nullable|string|max:120',
            'marketplace_og_description' => 'nullable|string|max:250',
            'marketplace_meta_keywords'  => 'nullable|string|max:500',
            'og_image'                   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:min_width=600,min_height=300',
        ]);

        $config = \App\Models\System\Configuration::firstOrCreate(['id' => 1]);
        $config->marketplace_og_title       = $request->input('marketplace_og_title');
        $config->marketplace_og_description = $request->input('marketplace_og_description');
        $config->marketplace_meta_keywords  = $request->input('marketplace_meta_keywords');

        if ($request->hasFile('og_image')) {
            $file = $request->file('og_image');
            $filename = 'mp-og-' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/uploads/system', $filename);
            // Borramos el anterior para no dejar basura
            if ($config->marketplace_og_image) {
                @\Storage::delete('public/uploads/system/' . $config->marketplace_og_image);
            }
            $config->marketplace_og_image = $filename;
        }
        $config->save();

        \Cache::forget('system_config');

        return redirect()->route('system.marketplace.seo')
            ->with('mp_seo_message', 'Configuración SEO actualizada. La nueva preview puede tardar minutos en propagarse a WhatsApp/Facebook (limpian su caché).');
    }

    // ── Listings ──────────────────────────────────────────────────────────────

    public function listings(Request $request)
    {
        $query = MarketplaceListing::query()
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant')) {
            $query->where('tenant_fqdn', 'like', '%' . $request->tenant . '%');
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $listings = $query->paginate(20)->withQueryString();
        $stats = [
            'total'    => MarketplaceListing::count(),
            'active'   => MarketplaceListing::where('status', 'active')->count(),
            'pending'  => MarketplaceListing::where('status', 'pending_review')->count(),
            'rejected' => MarketplaceListing::where('status', 'rejected')->count(),
            'leads'    => MarketplaceLead::count(),
        ];

        return view('system.marketplace.listings', compact('listings', 'stats'));
    }

    public function updateListingStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,paused,rejected,pending_review',
            'rejection_reason' => 'nullable|string|max:250',
        ]);

        $listing = MarketplaceListing::findOrFail($id);
        $listing->status = $request->status;
        $listing->is_active = $request->status === 'active';
        if ($request->status === 'rejected') {
            $listing->rejection_reason = $request->rejection_reason;
        } else {
            $listing->rejection_reason = null;
        }
        $listing->save();

        return back()->with('ok', "Listing actualizado: {$request->status}");
    }

    /**
     * Activa o desactiva el flag de "destacado" para un listing. Permite
     * configurar duración (en días) y score de orden entre destacados.
     *
     * Sin pasarela de pago todavía: el SuperAdmin decide a qué listings dar
     * realce. Cuando exista billing, esto será un upgrade pagado por el seller.
     */
    public function toggleListingFeatured(Request $request, $id)
    {
        $request->validate([
            'is_featured'   => 'required|boolean',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'score'         => 'nullable|integer|min:0|max:1000',
        ]);

        $listing = MarketplaceListing::findOrFail($id);

        if ($request->boolean('is_featured')) {
            $listing->is_featured     = true;
            $listing->featured_score  = (int) ($request->input('score') ?? 100);
            $listing->featured_until  = $request->filled('duration_days')
                ? now()->addDays((int) $request->input('duration_days'))
                : null;
        } else {
            $listing->is_featured     = false;
            $listing->featured_until  = null;
            $listing->featured_score  = 0;
        }

        $listing->save();

        return [
            'success'        => true,
            'message'        => $request->boolean('is_featured')
                ? 'Listing destacado en el marketplace.'
                : 'Listing retirado de destacados.',
            'is_featured'    => $listing->is_featured,
            'featured_until' => $listing->featured_until?->toIso8601String(),
            'featured_score' => $listing->featured_score,
        ];
    }

    /**
     * Toggle de tenant verificado — muestra badge "Tienda verificada" en la
     * vitrina pública del marketplace central. Actualiza el cache denormalizado
     * en todos los listings del cliente afectado.
     */
    public function toggleTenantVerified(Request $request, $clientId)
    {
        $request->validate([
            'is_verified' => 'required|boolean',
            'note'        => 'nullable|string|max:180',
        ]);

        $client = \App\Models\System\Client::findOrFail($clientId);
        $client->is_verified = (bool) $request->is_verified;
        $client->verified_at = $client->is_verified ? now() : null;
        $client->verified_note = $request->note;
        $client->save();

        // Propagar cache denormalizado a todos los listings del tenant
        MarketplaceListing::where('client_id', $client->id)
            ->update(['tenant_verified' => $client->is_verified]);

        return back()->with('ok', $client->is_verified
            ? "Tienda verificada: {$client->name}"
            : "Verificación removida: {$client->name}");
    }

    // ── Leads ─────────────────────────────────────────────────────────────────

    public function leads(Request $request)
    {
        $query = MarketplaceLead::with('listing')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant')) {
            $query->where('tenant_fqdn', 'like', '%' . $request->tenant . '%');
        }

        $leads = $query->paginate(20)->withQueryString();

        return view('system.marketplace.leads', compact('leads'));
    }

    public function retryLead($id, MarketplaceOrderDispatcher $dispatcher)
    {
        $lead = MarketplaceLead::findOrFail($id);
        if (!in_array($lead->status, ['failed', 'new'])) {
            return back()->with('error', 'Solo se reintentan leads fallidos o nuevos');
        }

        $ok = $dispatcher->dispatchLead($lead);
        return back()->with($ok ? 'ok' : 'error', $ok ? 'Lead reenviado al tenant' : 'No se pudo reenviar: ' . $lead->sync_error);
    }

    public function archiveLead($id)
    {
        $lead = MarketplaceLead::findOrFail($id);
        $lead->update(['status' => 'archived']);
        return back()->with('ok', 'Lead archivado');
    }

    // ── Reviews ───────────────────────────────────────────────────────────────

    public function reviews(Request $request)
    {
        $query = MarketplaceReview::with('listing:id,title,slug,tenant_fqdn')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tenant')) {
            $tenantFqdn = $request->tenant;
            $query->whereHas('listing', fn($q) => $q->where('tenant_fqdn', 'like', '%'.$tenantFqdn.'%'));
        }

        $reviews = $query->paginate(20)->withQueryString();
        $stats = [
            'pending'  => MarketplaceReview::where('status', 'pending')->count(),
            'approved' => MarketplaceReview::where('status', 'approved')->count(),
            'rejected' => MarketplaceReview::where('status', 'rejected')->count(),
        ];

        return view('system.marketplace.reviews', compact('reviews', 'stats'));
    }

    public function approveReview($id)
    {
        $review = MarketplaceReview::findOrFail($id);
        $review->update(['status' => 'approved', 'approved_at' => now(), 'rejection_reason' => null]);
        MarketplaceReview::recalculateListingStats($review->listing_id);
        return back()->with('ok', 'Review aprobada');
    }

    public function rejectReview(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:200']);
        $review = MarketplaceReview::findOrFail($id);
        $review->update([
            'status' => 'rejected',
            'approved_at' => null,
            'rejection_reason' => $request->rejection_reason,
        ]);
        MarketplaceReview::recalculateListingStats($review->listing_id);
        return back()->with('ok', 'Review rechazada');
    }

    /**
     * Export de leads a CSV respetando filtros activos. Stream para no cargar
     * todos los leads en memoria si crece la tabla.
     */
    public function exportLeads(Request $request)
    {
        $query = MarketplaceLead::query()->orderByDesc('created_at');
        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('tenant'))  $query->where('tenant_fqdn', 'like', '%' . $request->tenant . '%');

        $filename = 'marketplace-leads-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel abra UTF-8 correctamente
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Fecha', 'Tienda', 'Producto', 'SKU', 'Cliente',
                'Telefono', 'Email', 'Cantidad', 'Precio snapshot', 'Total',
                'Mensaje', 'Estado', 'Order externo', 'Error',
            ]);

            $query->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $l) {
                    fputcsv($out, [
                        $l->created_at?->format('Y-m-d H:i'),
                        $l->tenant_fqdn,
                        $l->snapshot_title,
                        $l->remote_item_id,
                        $l->customer_name,
                        $l->customer_phone,
                        $l->customer_email,
                        $l->quantity,
                        $l->snapshot_price,
                        number_format($l->snapshot_price * $l->quantity, 2, '.', ''),
                        $l->message,
                        $l->status,
                        $l->tenant_order_external_id,
                        $l->sync_error,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
