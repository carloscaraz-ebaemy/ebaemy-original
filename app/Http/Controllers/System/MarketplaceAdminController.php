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

    /**
     * Dashboard ÚNICO del marketplace. Fusiona la analítica por producto
     * (vistas/clicks/CTR/leads con filtro por fecha y gráficos) con los KPIs
     * de negocio (pedidos, revenue, tiendas, funnel). Todo respeta el rango
     * de fechas elegido.
     *
     * FUENTE: vistas/clicks por día → marketplace_listing_stats_daily (tracking
     * going-forward). Si el rango empieza antes del inicio del tracking, se usa
     * el acumulado histórico de marketplace_listings (ver $useFallback).
     */
    public function dashboard(Request $request)
    {
        $conn = \DB::connection('system');

        // ── Filtros (rango por defecto: últimos 30 días, incluye hoy) ──────────
        $to   = $request->filled('to')
            ? \Carbon\Carbon::parse($request->input('to'))->toDateString()
            : now()->toDateString();
        $from = $request->filled('from')
            ? \Carbon\Carbon::parse($request->input('from'))->toDateString()
            : now()->subDays(29)->toDateString();
        if ($from > $to) { [$from, $to] = [$to, $from]; }

        $sort       = in_array($request->input('sort'), ['views', 'clicks', 'ctr', 'leads'], true)
                      ? $request->input('sort') : 'views';
        $tenant     = trim((string) $request->input('tenant', ''));
        $categoryId = $request->input('category');
        $status     = $request->input('status');
        $q          = trim((string) $request->input('q', ''));
        $minViews   = max(0, (int) $request->input('min_views', 0));

        // Desglose diario SOLO si todo el rango cae dentro del período trackeado.
        $trackingStart = $conn->table('marketplace_listing_stats_daily')->min('stat_date');
        $useFallback   = !$trackingStart || $from < $trackingStart;

        // ── Agregado por producto en el rango ──────────────────────────────────
        if ($useFallback) {
            $base = MarketplaceListing::query()
                ->selectRaw('marketplace_listings.id,
                    marketplace_listings.view_count  as views,
                    marketplace_listings.click_count as clicks,
                    marketplace_listings.lead_count  as leads');
        } else {
            $base = MarketplaceListing::query()
                ->leftJoinSub(
                    $conn->table('marketplace_listing_stats_daily')
                        ->selectRaw('listing_id, SUM(views) as views, SUM(clicks) as clicks')
                        ->whereBetween('stat_date', [$from, $to])->groupBy('listing_id'),
                    'd', 'd.listing_id', '=', 'marketplace_listings.id'
                )
                ->leftJoinSub(
                    $conn->table('marketplace_leads')
                        ->selectRaw('listing_id, COUNT(*) as leads')
                        ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->groupBy('listing_id'),
                    'lq', 'lq.listing_id', '=', 'marketplace_listings.id'
                )
                ->selectRaw('marketplace_listings.id,
                    COALESCE(d.views, 0)  as views,
                    COALESCE(d.clicks, 0) as clicks,
                    COALESCE(lq.leads, 0) as leads');
        }
        $base->addSelect(
            'marketplace_listings.title', 'marketplace_listings.slug',
            'marketplace_listings.tenant_fqdn', 'marketplace_listings.status',
            'marketplace_listings.image_url', 'marketplace_listings.marketplace_category_id'
        );
        if ($tenant !== '') $base->where('marketplace_listings.tenant_fqdn', 'like', "%{$tenant}%");
        if ($categoryId)    $base->where('marketplace_listings.marketplace_category_id', $categoryId);
        if ($status)        $base->where('marketplace_listings.status', $status);
        if ($q !== '')      $base->where('marketplace_listings.title', 'like', "%{$q}%");

        $rows = $base->get()->map(function ($r) {
            $r->views  = (int) $r->views;
            $r->clicks = (int) $r->clicks;
            $r->leads  = (int) $r->leads;
            $r->ctr    = $r->views > 0 ? round($r->clicks / $r->views * 100, 1) : 0.0;
            return $r;
        });
        if ($minViews > 0) $rows = $rows->where('views', '>=', $minViews)->values();
        $rows = $rows->sortByDesc($sort === 'ctr' ? 'ctr' : $sort)->values();

        // ── Pedidos + revenue del rango (respetan el filtro de fecha) ──────────
        $ordersAgg = $conn->table('tenant_marketplace_orders')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount_amount),0) as discount')
            ->first();
        $ordersRange  = (int) ($ordersAgg->total ?? 0);
        $revenueRange = round((float) (($ordersAgg->gross ?? 0) - ($ordersAgg->discount ?? 0)), 2);

        // ── KPIs ───────────────────────────────────────────────────────────────
        $kpis = [
            'products'          => $rows->count(),
            'views'             => (int) $rows->sum('views'),
            'clicks'            => (int) $rows->sum('clicks'),
            'leads'             => (int) $rows->sum('leads'),
            'orders'            => $ordersRange,
            'revenue'           => $revenueRange,
            // Estado actual (no depende del rango de fechas).
            'tenants_active'    => (int) MarketplaceListing::where('is_active', true)->distinct()->count('hostname_id'),
            'listings_total'    => MarketplaceListing::count(),
            'listings_active'   => MarketplaceListing::where('status', 'active')->count(),
            'listings_paused'   => MarketplaceListing::where('status', 'paused')->count(),
            'listings_rejected' => MarketplaceListing::where('status', 'rejected')->count(),
        ];
        $kpis['ctr'] = $kpis['views'] > 0 ? round($kpis['clicks'] / $kpis['views'] * 100, 2) : 0;

        $topByViews  = $rows->sortByDesc('views')->take(10)->values();
        $topByClicks = $rows->sortByDesc('clicks')->take(10)->values();
        $champion    = $topByViews->first();

        // "Rezagado": producto con tráfico real pero la PEOR conversión — se ve
        // mucho y nadie hace click. Es el más accionable ("desperdicia vistas").
        // Lo buscamos entre los 20 más vistos para que no sea un producto con
        // 1 sola vista; de esos, el de menor CTR.
        $laggard = $rows->sortByDesc('views')->take(20)
            ->filter(fn($r) => $r->views > 0)
            ->sortBy('ctr')->first();

        // ── Línea de tiempo de vistas/clicks ───────────────────────────────────
        // A diferencia de los KPIs (que pueden caer al acumulado histórico), la
        // línea de tiempo SIEMPRE muestra el tracking diario dentro del rango,
        // agrupado según la granularidad elegida (día / semana / mes).
        $granularity = in_array($request->input('granularity'), ['day', 'week', 'month'], true)
            ? $request->input('granularity') : 'day';

        $spanDays  = \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
        $trendFrom = $trackingStart ? max($from, $trackingStart) : $from;

        // Expresión SQL del "bucket" según granularidad. WEEKDAY()=0 es lunes,
        // así que restarlo lleva la fecha al lunes de su semana (ISO).
        $bucketExpr = match ($granularity) {
            'week'  => 'DATE(DATE_SUB(stat_date, INTERVAL WEEKDAY(stat_date) DAY))',
            'month' => "DATE_FORMAT(stat_date, '%Y-%m-01')",
            default => 'DATE(stat_date)',
        };

        $dailyView = !$trackingStart ? collect() : $conn->table('marketplace_listing_stats_daily')
            ->selectRaw("$bucketExpr as day, SUM(views) as views, SUM(clicks) as clicks")
            ->whereBetween('stat_date', [$trendFrom, $to])
            ->when($tenant !== '' || $categoryId || $status || $q !== '', function ($qb) use ($tenant, $categoryId, $status, $q) {
                $ids = MarketplaceListing::query()
                    ->when($tenant !== '', fn($x) => $x->where('tenant_fqdn', 'like', "%{$tenant}%"))
                    ->when($categoryId, fn($x) => $x->where('marketplace_category_id', $categoryId))
                    ->when($status, fn($x) => $x->where('status', $status))
                    ->when($q !== '', fn($x) => $x->where('title', 'like', "%{$q}%"))
                    ->pluck('id');
                $qb->whereIn('listing_id', $ids);
            })
            ->groupByRaw($bucketExpr)->orderBy('day')->get()->keyBy('day');

        // Serie continua (sin huecos) iterando bucket a bucket.
        $dailySeries = collect();
        if ($trackingStart) {
            $end    = \Carbon\Carbon::parse($to);
            $cursor = \Carbon\Carbon::parse($trendFrom);
            if ($granularity === 'week')  $cursor->startOfWeek();   // lunes
            if ($granularity === 'month') $cursor->startOfMonth();
            // Cap de puntos para no saturar (día: últimos 92).
            if ($granularity === 'day' && $cursor->diffInDays($end) + 1 > 92) {
                $cursor = (clone $end)->subDays(91);
            }
            $guard = 0;
            while ($cursor->lte($end) && $guard < 400) {
                $key = $cursor->toDateString();
                $dailySeries->push((object) [
                    'day'    => $key,
                    'views'  => (int) ($dailyView[$key]->views  ?? 0),
                    'clicks' => (int) ($dailyView[$key]->clicks ?? 0),
                ]);
                $granularity === 'week' ? $cursor->addWeek()
                    : ($granularity === 'month' ? $cursor->addMonth() : $cursor->addDay());
                $guard++;
            }
        }

        // Stats de la línea de tiempo (para los chips del panel).
        $trendStats = [
            'total_views' => (int) $dailySeries->sum('views'),
            'avg_views'   => $dailySeries->count() ? round($dailySeries->avg('views'), 1) : 0,
            'peak_views'  => (int) ($dailySeries->max('views') ?? 0),
            'peak_day'    => optional($dailySeries->sortByDesc('views')->first())->day,
            'days'        => $dailySeries->count(),
        ];

        // ── Agregados para los gráficos ────────────────────────────────────────
        $categories = $conn->table('marketplace_categories')->orderBy('name')->get(['id', 'name']);
        $catName = $categories->pluck('name', 'id');

        $byCategory = $rows->groupBy('marketplace_category_id')
            ->map(fn($g, $cid) => (object) [
                'label' => $cid ? ($catName[$cid] ?? 'Categoría #' . $cid) : 'Sin categoría',
                'views' => (int) $g->sum('views'),
            ])
            ->filter(fn($c) => $c->views > 0)->sortByDesc('views')->values()->take(7);

        $byTenant = $rows->groupBy('tenant_fqdn')
            ->map(fn($g, $fqdn) => (object) [
                'label'  => $fqdn,
                'views'  => (int) $g->sum('views'),
                'clicks' => (int) $g->sum('clicks'),
            ])
            ->filter(fn($t) => $t->views > 0)->sortByDesc('views')->values()->take(8);

        // Revenue por tienda en el rango (top 6) → donut.
        $revenueByTenant = $conn->table('tenant_marketplace_orders as tmo')
            ->join('hostnames as h', 'h.id', '=', 'tmo.hostname_id')
            ->whereBetween('tmo.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('h.fqdn as tenant_fqdn, COALESCE(SUM(tmo.subtotal - tmo.discount_amount),0) as revenue')
            ->groupBy('h.fqdn')->orderByDesc('revenue')->limit(6)->get();

        // Distribución actual de listings por estado.
        $listingsByStatus = MarketplaceListing::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')->orderByDesc('cnt')->get();

        // Funnel global del rango.
        $funnel = [
            ['stage' => 'Vistas',  'value' => $kpis['views'],  'rate' => 100],
            ['stage' => 'Clicks',  'value' => $kpis['clicks'], 'rate' => $kpis['views'] > 0 ? round($kpis['clicks'] / $kpis['views'] * 100, 2) : 0],
            ['stage' => 'Leads',   'value' => $kpis['leads'],  'rate' => $kpis['views'] > 0 ? round($kpis['leads']  / $kpis['views'] * 100, 2) : 0],
            ['stage' => 'Pedidos', 'value' => $kpis['orders'], 'rate' => $kpis['views'] > 0 ? round($kpis['orders'] / $kpis['views'] * 100, 2) : 0],
        ];

        $filters = compact('from', 'to', 'sort', 'tenant', 'status', 'q', 'minViews', 'granularity') + ['category' => $categoryId];

        return view('system.marketplace.dashboard', compact(
            'rows', 'kpis', 'topByViews', 'topByClicks', 'champion', 'laggard', 'dailySeries',
            'categories', 'filters', 'useFallback', 'trackingStart', 'spanDays', 'trendStats',
            'byCategory', 'byTenant', 'revenueByTenant', 'listingsByStatus', 'funnel'
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
            'marketplace_facebook_url'   => 'nullable|url|max:500',
            'marketplace_instagram_url'  => 'nullable|url|max:500',
            'marketplace_whatsapp_url'   => 'nullable|url|max:500',
            'marketplace_tiktok_url'     => 'nullable|url|max:500',
        ]);

        $config = \App\Models\System\Configuration::firstOrCreate(['id' => 1]);
        $config->marketplace_og_title       = $request->input('marketplace_og_title');
        $config->marketplace_og_description = $request->input('marketplace_og_description');
        $config->marketplace_meta_keywords  = $request->input('marketplace_meta_keywords');
        $config->marketplace_facebook_url   = $request->input('marketplace_facebook_url');
        $config->marketplace_instagram_url  = $request->input('marketplace_instagram_url');
        $config->marketplace_whatsapp_url   = $request->input('marketplace_whatsapp_url');
        $config->marketplace_tiktok_url     = $request->input('marketplace_tiktok_url');

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

        // ── Invalidar caché de redes sociales automáticamente ─────────────
        // FB Graph: scrape=true fuerza a Facebook a re-fetchear la URL y
        // recachear los meta tags. WhatsApp usa la misma infra, así que
        // refresca también. Sin esto el SuperAdmin tendría que ir al
        // debugger de FB manualmente — feedback del usuario fue claro:
        // "no quiero hacer el procedimiento de ir a facebook".
        $invalidated = $this->invalidateSocialCache(url('/marketplace'));

        $msg = '✓ Configuración SEO actualizada.';
        if ($invalidated) {
            $msg .= ' Caché de WhatsApp/Facebook refrescado automáticamente. Probá compartir el link ahora.';
        } else {
            $msg .= ' (No pudimos refrescar el caché de FB automáticamente — usa el botón de abajo si la preview sigue saliendo vieja.)';
        }

        return redirect()->route('system.marketplace.seo')->with('mp_seo_message', $msg);
    }

    /**
     * Llama al Graph API de Facebook con scrape=true para forzar re-cacheo
     * del og:image y meta tags. No requiere autenticación (endpoint público).
     * WhatsApp usa la misma infraestructura — al refrescar FB se refresca
     * la preview de WhatsApp también.
     *
     * Si falla (timeout, network, FB cambió el endpoint), devolvemos false
     * para que el caller muestre el fallback manual. No tiramos exception.
     */
    private function invalidateSocialCache(string $url): bool
    {
        try {
            $token = config('services.facebook.app_token') ?: '';
            $endpoint = 'https://graph.facebook.com/';
            $params = ['id' => $url, 'scrape' => 'true'];
            if ($token) $params['access_token'] = $token;

            $response = \Illuminate\Support\Facades\Http::timeout(8)
                ->asForm()
                ->post($endpoint, $params);

            return $response->successful();
        } catch (\Throwable $e) {
            \Log::warning('[MarketplaceAdmin] FB scrape failed', [
                'url' => $url, 'error' => $e->getMessage(),
            ]);
            return false;
        }
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
