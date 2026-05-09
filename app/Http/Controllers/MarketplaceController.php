<?php

namespace App\Http\Controllers;

use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceReview;
use App\Services\System\MarketplaceOrderDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        $q              = $request->input('q');
        $category       = $request->input('category');
        $officialCatId  = $request->filled('official_category_id') ? (int) $request->input('official_category_id') : null;
        $sort           = $request->input('sort', 'relevance');
        // Filtros de precio — usa el campo efectivo (mp_price ?? price).
        $priceMin = $request->filled('price_min') ? max(0, (float) $request->input('price_min')) : null;
        $priceMax = $request->filled('price_max') ? max(0, (float) $request->input('price_max')) : null;
        // Filtro por tienda — el sidebar lo expone como ?shop=<subdomain>.
        // Resolvemos contra hostnames.fqdn (subdomain.ebaemy.com).
        $shopSubdomain = $request->input('shop');
        $shopHostnameId = null;
        if ($shopSubdomain) {
            $shopHostnameId = \DB::connection('system')->table('hostnames')
                ->where('fqdn', 'like', strtolower($shopSubdomain) . '.%')
                ->value('id');
        }

        $query = MarketplaceListing::published()
            ->search($q)
            ->category($category)
            ->inOfficialCategory($officialCatId);

        if ($shopHostnameId) {
            $query->where('hostname_id', $shopHostnameId);
        }

        // COALESCE(mp_price, price) → precio efectivo mostrado al usuario
        if ($priceMin !== null) {
            $query->whereRaw('COALESCE(mp_price, price) >= ?', [$priceMin]);
        }
        if ($priceMax !== null) {
            $query->whereRaw('COALESCE(mp_price, price) <= ?', [$priceMax]);
        }

        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw('COALESCE(mp_price, price) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE(mp_price, price) DESC');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            default:
                // Relevance: featured (no expirados) primero, luego score, luego views
                $query->orderByRaw('CASE WHEN is_featured = 1 AND (featured_until IS NULL OR featured_until > NOW()) THEN 1 ELSE 0 END DESC')
                      ->orderByDesc('featured_score')
                      ->orderByDesc('sort_score')
                      ->orderByDesc('view_count');
        }

        $listings   = $query->paginate(24)->withQueryString();

        // Decora cada listing con los datos que la card necesita: dots de
        // color, thumbs de variantes con imagen, imagen primaria heredada
        // de la variante is_primary, y color activo. Reusable por todas
        // las acciones que renderizan grids de cards.
        $this->decorateListingsWithVariantData($listings);

        $categories = MarketplaceListing::published()
            ->whereNotNull('category_name')
            ->select('category_name')
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->limit(40)
            ->pluck('category_name');

        // Árbol oficial (sólo raíces visibles) — cacheado 30min.
        $officialRoots = $this->getOfficialRootsCached();

        // Ofertas del día — solo en home (sin filtros). Cache 30min para no
        // pegarle a la BD en cada visita. Limite 12 productos: suficiente para
        // un carrusel destacado sin saturar visualmente. Solo se muestra si
        // hay 4+ ofertas; abajo de eso queda mejor ocultar la sección.
        $isHome = empty($q) && empty($category) && !$officialCatId
                  && $priceMin === null && $priceMax === null;
        $dailyOffers = collect();
        if ($isHome) {
            $dailyOffers = Cache::remember('mp_daily_offers_v1', 1800, function () {
                return MarketplaceListing::published()
                    ->onOffer()
                    ->orderByDesc('discount_pct')
                    ->orderByDesc('view_count')
                    ->limit(12)
                    ->get();
            });
        }

        // Top tiendas con productos publicados — sidebar de filtro por tienda.
        // Cache 30 min porque el ranking cambia lento. 12 max para no inflar
        // visualmente; las tiendas restantes quedan accesibles vía buscador.
        $shops = Cache::remember('mp_shops_top_v1', 1800, function () {
            return MarketplaceListing::published()
                ->whereNotNull('tenant_fqdn')
                ->select(
                    'tenant_fqdn',
                    'tenant_name',
                    \DB::raw('COUNT(*) as products_count')
                )
                ->groupBy('tenant_fqdn', 'tenant_name')
                ->orderByDesc('products_count')
                ->limit(12)
                ->get()
                ->map(function ($s) {
                    $sub = strtolower(strtok((string) $s->tenant_fqdn, '.')) ?: null;
                    return (object) [
                        'subdomain'      => $sub,
                        'name'           => $s->tenant_name ?: $sub,
                        'products_count' => (int) $s->products_count,
                    ];
                })
                ->filter(fn($s) => $s->subdomain)
                ->values();
        });

        return view('marketplace.index', compact(
            'listings', 'categories', 'officialRoots', 'dailyOffers',
            'q', 'category', 'officialCatId',
            'sort', 'priceMin', 'priceMax',
            'shops', 'shopSubdomain'
        ));
    }

    /**
     * Decora un paginator/colección de listings con los datos que usa la card
     * del marketplace: dots de color con su imagen, thumbs de variantes con
     * imagen propia, y la "variante principal" que define la imagen y el
     * color activo por defecto. Lo invocan todas las acciones que renderizan
     * grids de cards (index, category, categoryOfficial, tenantPage) para
     * que las cards se vean igual en todas las vistas.
     *
     * Side effect: setea $listing->variant_thumbs, $listing->color_dots,
     * $listing->primary_image_url, $listing->active_color_hex/value.
     */
    private function decorateListingsWithVariantData($listings): void
    {
        $listingIds = collect($listings->items() ?? $listings)->pluck('id');
        if ($listingIds->isEmpty()) return;

        $variantImages = \App\Models\System\MarketplaceListingVariant::query()
            ->whereIn('listing_id', $listingIds)
            ->where('is_active', true)
            ->whereNotNull('image_url')
            ->orderBy('listing_id')
            ->orderBy('id')
            ->get(['id', 'listing_id', 'tenant_variant_id', 'display_name', 'image_url'])
            ->groupBy('listing_id');

        // Color values: dots circulares en cards. Reglas:
        //  1. La opción debe llamarse "color" (case-insensitive).
        //  2. value.color_hex no nulo (los que solo tienen imagen se descartan).
        //  3. Al menos una variante activa con stock > 0 usa ese value.
        $colorValuesByListing = \DB::connection('system')->table('marketplace_listing_option_values as v')
            ->join('marketplace_listing_options as o', 'o.id', '=', 'v.option_id')
            ->whereIn('o.listing_id', $listingIds)
            ->whereRaw('LOWER(o.name) LIKE ?', ['%color%'])
            ->whereNotNull('v.color_hex')
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('marketplace_listing_variant_values as vv')
                    ->join('marketplace_listing_variants as lv', 'lv.id', '=', 'vv.listing_variant_id')
                    ->whereColumn('vv.option_value_id', 'v.id')
                    ->where('lv.is_active', true)
                    ->where('lv.stock', '>', 0);
            })
            ->orderBy('o.listing_id')
            ->orderBy('v.position')
            ->select(
                'o.listing_id',
                'v.value',
                'v.color_hex',
                \DB::connection('system')->raw('(
                    SELECT lv.image_url
                    FROM marketplace_listing_variant_values vv
                    INNER JOIN marketplace_listing_variants lv
                        ON lv.id = vv.listing_variant_id
                    WHERE vv.option_value_id = v.id
                      AND lv.is_active = 1
                      AND lv.stock > 0
                      AND lv.image_url IS NOT NULL
                    ORDER BY lv.id ASC
                    LIMIT 1
                ) AS image_url')
            )
            ->get()
            ->groupBy('listing_id');

        // Variante "principal" — define imagen + color activo por defecto.
        $primaryByListing = \DB::connection('system')->table('marketplace_listing_variants as lv')
            ->whereIn('lv.listing_id', $listingIds)
            ->where('lv.is_active', true)
            ->whereNotNull('lv.image_url')
            ->leftJoin('marketplace_listing_variant_values as vv', 'vv.listing_variant_id', '=', 'lv.id')
            ->leftJoin('marketplace_listing_option_values as ov', 'ov.id', '=', 'vv.option_value_id')
            ->leftJoin('marketplace_listing_options as o', 'o.id', '=', 'ov.option_id')
            ->select(
                'lv.listing_id',
                'lv.id as variant_id',
                'lv.image_url',
                'lv.is_primary',
                'lv.stock',
                \DB::raw("MAX(CASE WHEN LOWER(o.name) LIKE '%color%' THEN ov.color_hex END) AS active_color_hex"),
                \DB::raw("MAX(CASE WHEN LOWER(o.name) LIKE '%color%' THEN ov.value     END) AS active_color_value")
            )
            ->groupBy('lv.listing_id', 'lv.id', 'lv.image_url', 'lv.is_primary', 'lv.stock')
            ->orderByDesc('lv.is_primary')
            ->orderByRaw('lv.stock > 0 DESC')
            ->orderBy('lv.id')
            ->get()
            ->groupBy('listing_id')
            ->map(fn($rows) => $rows->first());

        foreach ($listings as $l) {
            $l->variant_thumbs = $variantImages->get($l->id, collect())->take(4);
            $l->color_dots     = $colorValuesByListing->get($l->id, collect())->take(5);

            $primary = $primaryByListing->get($l->id);
            $l->primary_image_url   = $primary->image_url   ?? null;
            $l->active_color_hex    = $primary->active_color_hex ?? null;
            $l->active_color_value  = $primary->active_color_value ?? null;
        }
    }

    /**
     * Raíces publicables del árbol oficial con conteo de listings. Cacheado
     * 30 min — el SuperAdmin rara vez cambia el árbol y el TTL corto basta.
     */
    private function getOfficialRootsCached()
    {
        // v3: solo `active()` para children — `visible()` (is_visible_in_marketplace)
        // está marcado en muchas roots pero NO en sus subcategorías, así que filtrar
        // por visible aquí dejaba la mayoría de roots con children vacíos. La root
        // ya pasó por visible() arriba; los hijos solo necesitan estar activos.
        return Cache::remember('marketplace_public_roots_v3', 1800, function () {
            return MarketplaceCategory::query()
                ->active()
                ->visible()
                ->roots()
                ->orderBy('sort_order')
                ->with(['children' => function ($q) {
                    $q->active()->orderBy('sort_order')
                      ->select(['id', 'parent_id', 'name', 'slug', 'full_slug', 'icon', 'listings_count_cache']);
                }])
                ->get(['id', 'name', 'slug', 'full_slug', 'icon', 'image', 'listings_count_cache']);
        });
    }

    /**
     * Página de categoría con SEO propio: URL canónica distinta a /marketplace?category=X,
     * meta tags específicos, JSON-LD BreadcrumbList. Listado filtrado por categoría.
     * Usa el mismo scope published y sorting del index.
     */
    public function category(Request $request, string $categorySlug)
    {
        // Migración Fase D: si el slug viejo coincide con una categoría oficial
        // (por slug final, no full_slug), redirigimos 301 a la URL canónica nueva.
        // Así recuperamos el SEO de las URLs legacy sin romper backlinks.
        $officialMatch = MarketplaceCategory::query()
            ->where('slug', $categorySlug)
            ->active()
            ->visible()
            ->first();
        if ($officialMatch) {
            return redirect()->to('/marketplace/c/' . $officialMatch->full_slug, 301);
        }

        // El slug es URL-friendly; recuperamos el category_name real buscando por
        // Str::slug(category_name) === $categorySlug sobre categorías publicadas.
        $category = MarketplaceListing::published()
            ->whereNotNull('category_name')
            ->get(['category_name'])
            ->pluck('category_name')
            ->unique()
            ->first(fn($c) => \Illuminate\Support\Str::slug($c) === $categorySlug);

        if (!$category) {
            abort(404);
        }

        $sort     = $request->input('sort', 'relevance');
        $priceMin = $request->filled('price_min') ? max(0, (float) $request->input('price_min')) : null;
        $priceMax = $request->filled('price_max') ? max(0, (float) $request->input('price_max')) : null;

        $query = MarketplaceListing::published()
            ->with(['hostname:id,fqdn']) // eager load — evita N+1 al renderizar links de tienda
            ->where('category_name', $category);

        if ($priceMin !== null) $query->whereRaw('COALESCE(mp_price, price) >= ?', [$priceMin]);
        if ($priceMax !== null) $query->whereRaw('COALESCE(mp_price, price) <= ?', [$priceMax]);

        switch ($sort) {
            case 'price_asc':  $query->orderByRaw('COALESCE(mp_price, price) ASC');  break;
            case 'price_desc': $query->orderByRaw('COALESCE(mp_price, price) DESC'); break;
            case 'newest':     $query->orderByDesc('created_at');                    break;
            default:           $query->orderByDesc('sort_score')->orderByDesc('view_count');
        }

        $listings = $query->paginate(24)->withQueryString();
        $this->decorateListingsWithVariantData($listings);
        $total    = MarketplaceListing::published()->where('category_name', $category)->count();

        return view('marketplace.category', compact('listings', 'category', 'categorySlug', 'sort', 'priceMin', 'priceMax', 'total'));
    }

    /**
     * Página de categoría oficial del marketplace. URL canónica:
     *   /marketplace/c/{fullSlug}   p.ej. /marketplace/c/hogar/muebles/sillas
     *
     * Filtra por la FK `marketplace_category_id` incluyendo toda la descendencia
     * (una categoría padre muestra productos publicados en cualquier hoja interna).
     *
     * Convive con /marketplace/categoria/{categorySlug} (legacy basada en
     * category_name string). En Fase E, cuando >95% de listings tengan FK,
     * se retira la vieja.
     */
    public function categoryOfficial(Request $request, string $fullSlug)
    {
        $fullSlug = trim($fullSlug, '/');
        $category = MarketplaceCategory::query()
            ->where('full_slug', $fullSlug)
            ->active()
            ->visible()
            ->first();

        if (!$category) {
            abort(404);
        }

        $sort     = $request->input('sort', 'relevance');
        $priceMin = $request->filled('price_min') ? max(0, (float) $request->input('price_min')) : null;
        $priceMax = $request->filled('price_max') ? max(0, (float) $request->input('price_max')) : null;

        $query = MarketplaceListing::published()->inOfficialCategory($category->id);

        if ($priceMin !== null) $query->whereRaw('COALESCE(mp_price, price) >= ?', [$priceMin]);
        if ($priceMax !== null) $query->whereRaw('COALESCE(mp_price, price) <= ?', [$priceMax]);

        switch ($sort) {
            case 'price_asc':  $query->orderByRaw('COALESCE(mp_price, price) ASC');  break;
            case 'price_desc': $query->orderByRaw('COALESCE(mp_price, price) DESC'); break;
            case 'newest':     $query->orderByDesc('created_at');                    break;
            default:           $query->orderByDesc('sort_score')->orderByDesc('view_count');
        }

        $listings = $query->paginate(24)->withQueryString();
        $this->decorateListingsWithVariantData($listings);
        $total    = MarketplaceListing::published()->inOfficialCategory($category->id)->count();

        // Breadcrumb oficial: ancestros + self
        $breadcrumb = $category->ancestorsAndSelf();

        // Subcategorías inmediatas del nodo actual (si no es hoja) — para navegación lateral
        $subcategories = MarketplaceCategory::query()
            ->active()
            ->visible()
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'full_slug', 'icon', 'listings_count_cache']);

        $activeCategoryFullSlug = $category->full_slug;

        return view('marketplace.category_official', compact(
            'listings', 'category', 'breadcrumb', 'subcategories',
            'sort', 'priceMin', 'priceMax', 'total',
            'activeCategoryFullSlug'
        ));
    }

    public function show(string $slug)
    {
        // Solo listings publicados (activos, con stock). Los pausados/rechazados
        // devuelven 404 para no exponer productos retirados en búsquedas Google.
        $listing = MarketplaceListing::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Pageview — se incrementa asíncronamente para no ralentizar render
        MarketplaceListing::where('id', $listing->id)->increment('view_count');

        // Variantes (solo si el listing las tiene). Orden: la marcada como
        // is_primary primero (define qué imagen y combo aparece al cargar),
        // después por precio asc. Si nadie está marcado, fallback al precio.
        $variants    = collect();
        $options     = collect();
        $variantMap  = []; // [option_value_id, option_value_id] join → variant_id
        $primaryValueIds = []; // option_value_ids de la variante is_primary
        if ($listing->has_variants) {
            $variants = $listing->variants()
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderBy('price')
                ->get();

            // Resolver qué values del selector deben marcarse como is-selected
            // al cargar la página: los de la variante is_primary. Si nadie
            // está marcado, el blade cae al fallback "primer value de cada opción".
            $primaryVariant = $variants->firstWhere('is_primary', true);
            if ($primaryVariant) {
                $primaryValueIds = \DB::connection('system')->table('marketplace_listing_variant_values')
                    ->where('listing_variant_id', $primaryVariant->id)
                    ->pluck('option_value_id')
                    ->map(fn($i) => (int) $i)
                    ->all();
            }

            // Opciones agrupadas (Color/Talla) con sus valores, listas para
            // renderizar como thumbs-imagen o pills según corresponda.
            $options = \App\Models\System\MarketplaceListingOption::query()
                ->where('listing_id', $listing->id)
                ->with(['values' => fn($q) => $q->orderBy('position')])
                ->orderBy('position')
                ->get();

            // Mapa "combo de valores → variant data" para que el JS resuelva
            // qué variante se elige al combinar Color + Talla. La key es el
            // array de option_value_ids ordenado ASC (formato: "12-34").
            if ($options->isNotEmpty()) {
                $pivots = \DB::connection('system')->table('marketplace_listing_variant_values')
                    ->join('marketplace_listing_variants', 'marketplace_listing_variants.id', '=', 'marketplace_listing_variant_values.listing_variant_id')
                    ->where('marketplace_listing_variants.listing_id', $listing->id)
                    ->select(
                        'marketplace_listing_variant_values.listing_variant_id',
                        'marketplace_listing_variant_values.option_value_id',
                    )
                    ->get()
                    ->groupBy('listing_variant_id');

                $variantsById = $variants->keyBy('id');
                foreach ($pivots as $variantId => $rows) {
                    $v = $variantsById->get($variantId);
                    if (!$v) continue;
                    $valueIds = $rows->pluck('option_value_id')->map(fn($i) => (int) $i)->sort()->values()->all();
                    $key = implode('-', $valueIds);
                    $variantMap[$key] = [
                        'id'             => (int) $v->id,
                        'tenant_variant_id' => (int) $v->tenant_variant_id,
                        'price'          => (float) $v->price,
                        'original_price' => $v->original_price ? (float) $v->original_price : null,
                        'is_on_offer'    => (bool) $v->is_on_offer,
                        'discount_pct'   => $v->discount_pct ? (int) $v->discount_pct : null,
                        'stock'          => (int) $v->stock,
                        'image_url'      => $v->image_url,
                        'display_name'   => $v->display_name,
                    ];
                }
            }
        }

        // Relacionados: prefiere FK oficial si está disponible, sino fallback
        // a category_name legacy para listings que todavía no migraron.
        $relatedQ = MarketplaceListing::published()->where('id', '!=', $listing->id);
        if ($listing->marketplace_category_id) {
            $relatedQ->inOfficialCategory($listing->marketplace_category_id);
        } elseif ($listing->category_name) {
            $relatedQ->where('category_name', $listing->category_name);
        }
        $related = $relatedQ->limit(6)->get();

        $reviews = MarketplaceReview::where('listing_id', $listing->id)
            ->approved()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Breadcrumb oficial si el listing tiene FK
        $officialBreadcrumb = null;
        $officialCategoryUrl = null;
        if ($listing->marketplace_category_id) {
            $cat = MarketplaceCategory::query()->find($listing->marketplace_category_id);
            if ($cat) {
                $officialBreadcrumb  = $cat->ancestorsAndSelf();
                $officialCategoryUrl = url('/marketplace/c/' . $cat->full_slug);
            }
        }

        return view('marketplace.show', compact(
            'listing', 'related', 'reviews', 'officialBreadcrumb', 'officialCategoryUrl',
            'variants', 'options', 'variantMap', 'primaryValueIds'
        ));
    }

    /**
     * Página pública de una tienda dentro del marketplace central.
     * URL: /marketplace/tienda/{subdomain}
     *
     * Resuelve el tenant por la primera parte del FQDN (subdominio). Solo
     * muestra tiendas con marketplace_enabled=true. Los productos provienen
     * del índice central — no se conecta a la BD del tenant.
     */
    public function tenantPage(Request $request, string $subdomain)
    {
        $subdomain = strtolower(trim($subdomain));
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,62}$/', $subdomain)) {
            abort(404);
        }

        // Resolver el hostname por subdominio. El FQDN del tenant siempre
        // empieza con "{subdomain}." — usamos LIKE para no acoplarnos al
        // dominio raíz (ebaemy.com vs cualquier otro).
        $hostname = \Hyn\Tenancy\Models\Hostname::query()
            ->where('fqdn', 'like', $subdomain . '.%')
            ->first();

        if (!$hostname) {
            abort(404);
        }

        $client = \App\Models\System\Client::query()
            ->where('hostname_id', $hostname->id)
            ->first();

        if (!$client || !$client->marketplace_enabled) {
            abort(404);
        }

        $sort     = $request->input('sort', 'relevance');
        $priceMin = $request->filled('price_min') ? max(0, (float) $request->input('price_min')) : null;
        $priceMax = $request->filled('price_max') ? max(0, (float) $request->input('price_max')) : null;
        $q        = $request->input('q');
        $catSlug  = $request->input('category');

        // Resolver categoría oficial seleccionada (filtro por full_slug). Acepta
        // tanto nodo padre como hoja: en padres se incluyen descendientes vía
        // el scope inOfficialCategory.
        $selectedCategory = null;
        if ($catSlug) {
            $selectedCategory = MarketplaceCategory::query()
                ->where('full_slug', trim($catSlug, '/'))
                ->active()
                ->visible()
                ->first();
        }

        $query = MarketplaceListing::published()
            ->where('hostname_id', $hostname->id)
            ->search($q);

        if ($selectedCategory) {
            $query->inOfficialCategory($selectedCategory->id);
        }

        if ($priceMin !== null) {
            $query->whereRaw('COALESCE(mp_price, price) >= ?', [$priceMin]);
        }
        if ($priceMax !== null) {
            $query->whereRaw('COALESCE(mp_price, price) <= ?', [$priceMax]);
        }

        switch ($sort) {
            case 'price_asc':  $query->orderByRaw('COALESCE(mp_price, price) ASC');  break;
            case 'price_desc': $query->orderByRaw('COALESCE(mp_price, price) DESC'); break;
            case 'newest':     $query->orderByDesc('created_at');                    break;
            default:           $query->orderByDesc('sort_score')->orderByDesc('view_count');
        }

        $listings = $query->paginate(24)->withQueryString();
        $this->decorateListingsWithVariantData($listings);
        $total    = MarketplaceListing::published()
                        ->where('hostname_id', $hostname->id)
                        ->count();

        // Categorías oficiales con productos en esta tienda — para el filtro
        // lateral. Solo nodos hoja distintos para no duplicar al subir el árbol.
        $tenantCategoryIds = MarketplaceListing::published()
            ->where('hostname_id', $hostname->id)
            ->whereNotNull('marketplace_category_id')
            ->distinct()
            ->pluck('marketplace_category_id');

        $tenantCategories = $tenantCategoryIds->isEmpty()
            ? collect()
            : MarketplaceCategory::query()
                ->whereIn('id', $tenantCategoryIds)
                ->active()
                ->visible()
                ->orderBy('name')
                ->get(['id', 'name', 'full_slug', 'icon']);

        $activeCategoryFullSlug = $selectedCategory ? $selectedCategory->full_slug : null;

        // Metadata visible: priorizar la del listing más reciente que ya
        // tiene tenant_name/logo/verified denormalizados. Si no hay listings,
        // caer al Client.
        $sample = MarketplaceListing::query()
            ->where('hostname_id', $hostname->id)
            ->orderByDesc('synced_at')
            ->first(['tenant_name', 'tenant_logo_url', 'tenant_verified', 'tenant_fqdn']);

        $store = (object) [
            'subdomain'  => $subdomain,
            'name'       => $sample->tenant_name ?? $client->name ?? $hostname->fqdn,
            'logo_url'   => $sample->tenant_logo_url ?? null,
            'verified'   => (bool) ($sample->tenant_verified ?? $client->is_verified ?? false),
            'fqdn'       => $sample->tenant_fqdn ?? $hostname->fqdn,
            'ruc'        => $client->number ?? null,
            'site_url'   => 'https://' . ($sample->tenant_fqdn ?? $hostname->fqdn),
        ];

        return view('marketplace.tenant', compact(
            'store', 'listings', 'total', 'sort', 'priceMin', 'priceMax', 'q',
            'tenantCategories', 'activeCategoryFullSlug'
        ));
    }

    /**
     * Recibe el formulario "Solicitar/Comprar" y crea un lead. El
     * MarketplaceOrderDispatcher lo convierte en Order dentro del tenant.
     */
    public function lead(Request $request, string $slug, MarketplaceOrderDispatcher $dispatcher)
    {
        // Solo acepta leads sobre listings publicados — evita crear órdenes
        // a partir de productos que el admin ya retiró/pausó.
        $listing = MarketplaceListing::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Honeypot anti-bot: si el campo `website` trae contenido, es un bot.
        // Respondemos 200 falso (para no dar pistas) sin crear lead.
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('marketplace.thanks', ['slug' => $slug]);
        }

        $data = $request->validate([
            'customer_name'  => 'required|string|max:180',
            'customer_phone' => 'nullable|string|max:40',
            'customer_email' => 'nullable|email|max:180',
            'quantity'       => ['nullable', 'integer', 'min:1', 'max:' . max(1, (int) $listing->stock)],
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
            ->with('lead_id', $lead->id)
            // dispatchLead() ya persistió el status en $lead, no recargamos de BD
            ->with('lead_status', $lead->status);
    }

    public function thanks(string $slug)
    {
        // En thanks se muestra incluso el listing pausado — el visitante llegó
        // acá desde su propio POST, no vale la pena ocultar la ficha de su compra.
        $listing = MarketplaceListing::where('slug', $slug)->firstOrFail();
        $leadStatus = session('lead_status');
        return view('marketplace.thanks', compact('listing', 'leadStatus'));
    }

    /**
     * Recibe un review del público sobre un listing. Queda 'pending' hasta
     * que un admin lo aprueba. Anti-spam: honeypot + throttle ruta +
     * chequeo de duplicado por email/ip dentro de 24h.
     */
    public function review(Request $request, string $slug)
    {
        $listing = MarketplaceListing::where('slug', $slug)->firstOrFail();

        // Honeypot
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('marketplace.item', $slug)
                ->with('review_msg', '¡Gracias por tu opinión!');
        }

        $data = $request->validate([
            'customer_name'  => 'required|string|max:120',
            'customer_email' => 'nullable|email|max:180',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'nullable|string|max:1000',
        ]);

        // Evita duplicados: mismo email/IP en las últimas 24h sobre este listing
        $duplicate = MarketplaceReview::where('listing_id', $listing->id)
            ->where('created_at', '>=', now()->subDay())
            ->where(function ($q) use ($request, $data) {
                $q->where('source_ip', $request->ip());
                if (!empty($data['customer_email'])) {
                    $q->orWhere('customer_email', $data['customer_email']);
                }
            })
            ->exists();

        if ($duplicate) {
            return redirect()->route('marketplace.item', $slug)
                ->with('review_msg', 'Ya enviaste una reseña recientemente. ¡Gracias!');
        }

        MarketplaceReview::create([
            'listing_id'     => $listing->id,
            'hostname_id'    => $listing->hostname_id,
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'rating'         => $data['rating'],
            'comment'        => $data['comment'] ?? null,
            'status'         => 'pending',
            'source_ip'      => $request->ip(),
            'source_ua'      => substr((string) $request->header('User-Agent'), 0, 250),
        ]);

        return redirect()->route('marketplace.item', $slug)
            ->with('review_msg', '¡Gracias por tu opinión! Será publicada tras revisión.');
    }

    /**
     * Click-through al storefront del tenant. Incrementa click_count y
     * redirige con UTM tags para que el tenant sepa que el visitante vino
     * de ebaemy.com. El tenant factura la venta con su RUC por su cuenta.
     */
    public function go(string $slug)
    {
        // Solo redirige si el listing sigue publicado. Evita deep-links viejos
        // llevando a tenants que ya pausaron la venta en el marketplace.
        $listing = MarketplaceListing::published()
            ->where('slug', $slug)
            ->firstOrFail();

        MarketplaceListing::where('id', $listing->id)->increment('click_count');

        return redirect()->away($listing->tenant_item_url_with_utm, 302);
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

        // Categorías publicadas — cada una expone una página canónica con SEO propio
        $categories = MarketplaceListing::published()
            ->whereNotNull('category_name')
            ->select('category_name')
            ->groupBy('category_name')
            ->pluck('category_name');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
              . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        $xml .= $this->sitemapUrl(url('/marketplace'), now(), '1.0', 'daily');

        // Categorías oficiales (URL canónica nueva) — prioridad alta.
        $officialCategories = MarketplaceCategory::query()
            ->active()
            ->visible()
            ->orderBy('full_slug')
            ->get(['full_slug', 'updated_at']);
        foreach ($officialCategories as $oc) {
            $xml .= $this->sitemapUrl(
                url('/marketplace/c/' . $oc->full_slug),
                $oc->updated_at ?: now(),
                '0.8',
                'daily'
            );
        }

        // Legacy category_name — transición. Google seguirá las 301s al índice oficial.
        foreach ($categories as $cat) {
            $xml .= $this->sitemapUrl(
                url('/marketplace/categoria/' . \Illuminate\Support\Str::slug($cat)),
                now(),
                '0.5',
                'daily'
            );
        }

        // Páginas públicas por tienda — una entrada por seller activo.
        // Tomamos el subdominio del FQDN denormalizado en marketplace_listings
        // para no consultar BDs de tenants.
        $stores = MarketplaceListing::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNotNull('tenant_fqdn')
            ->select('tenant_fqdn', \DB::raw('MAX(updated_at) as updated_at'))
            ->groupBy('tenant_fqdn')
            ->get();
        foreach ($stores as $store) {
            $sub = strtolower(strtok((string) $store->tenant_fqdn, '.')) ?: null;
            if (!$sub) {
                continue;
            }
            $xml .= $this->sitemapUrl(
                url('/marketplace/tienda/' . $sub),
                $store->updated_at ?: now(),
                '0.7',
                'weekly'
            );
        }

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

    /**
     * Feed RSS-XML compatible con Meta Catalog y Google Merchant Center.
     * Lista todos los marketplace_listings publicados con stock > 0.
     *
     * URL: /feeds/meta-catalog.xml
     *
     * Compatible con:
     *   - Meta Commerce Manager → Catálogos → Subir producto via URL feed
     *   - Google Merchant Center → fuente de datos → URL programada
     *
     * Cada item incluye <g:id> = mp_{listing_id} para evitar colisiones con
     * IDs internos de tenants. <g:link> apunta a la ficha pública del marketplace.
     */
    public function metaCatalog()
    {
        $listings = MarketplaceListing::published()
            ->orderByDesc('updated_at')
            ->limit(20000) // Meta Commerce permite hasta 20k items por feed
            ->get();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>ebaemy Marketplace</title>' . "\n";
        $xml .= '  <link>' . url('/marketplace') . '</link>' . "\n";
        $xml .= '  <description>Productos de tiendas verificadas en ebaemy.com</description>' . "\n";

        foreach ($listings as $l) {
            $price = number_format($l->display_price, 2, '.', '') . ' PEN';
            $availability = $l->stock > 0 ? 'in stock' : 'out of stock';
            $description = strip_tags($l->description ?? $l->title);
            $description = mb_substr(trim($description), 0, 5000);

            $xml .= "  <item>\n";
            $xml .= '    <g:id>mp_' . $l->id . "</g:id>\n";
            $xml .= '    <g:title>' . htmlspecialchars(mb_substr($l->title, 0, 150), ENT_XML1) . "</g:title>\n";
            $xml .= '    <g:description>' . htmlspecialchars($description ?: $l->title, ENT_XML1) . "</g:description>\n";
            $xml .= '    <g:link>' . htmlspecialchars($l->public_url, ENT_XML1) . "</g:link>\n";
            if ($l->image_url) {
                $xml .= '    <g:image_link>' . htmlspecialchars($l->image_url, ENT_XML1) . "</g:image_link>\n";
            }
            $xml .= '    <g:availability>' . $availability . "</g:availability>\n";
            $xml .= '    <g:price>' . $price . "</g:price>\n";
            $xml .= '    <g:condition>new</g:condition>' . "\n";
            $xml .= '    <g:identifier_exists>no</g:identifier_exists>' . "\n";
            if ($l->brand_name) {
                $xml .= '    <g:brand>' . htmlspecialchars(mb_substr($l->brand_name, 0, 70), ENT_XML1) . "</g:brand>\n";
            }
            if ($l->category_name) {
                $xml .= '    <g:product_type>' . htmlspecialchars(mb_substr($l->category_name, 0, 250), ENT_XML1) . "</g:product_type>\n";
            }
            // Tienda dueña — Meta lo usa para mostrar atribución
            $xml .= '    <g:custom_label_0>' . htmlspecialchars(mb_substr($l->seller_display, 0, 100), ENT_XML1) . "</g:custom_label_0>\n";
            $xml .= "  </item>\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
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
