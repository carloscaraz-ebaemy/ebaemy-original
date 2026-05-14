@extends('marketplace.layout')

@section('title', ($q ? 'Buscar "'.$q.'" — ' : '') . 'Marketplace ebaemy')

@section('content')

@php
    $baseQs = array_filter([
        'q'         => $q,
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
        'shop'      => $shopSubdomain ?? null,
        'on_offer'  => !empty($onOfferOnly) ? 1 : null,
        'verified'  => !empty($verifiedOnly) ? 1 : null,
        'in_stock'  => !empty($inStockOnly) ? 1 : null,
        'packs'     => !empty($packsOnly) ? 1 : null,
    ], fn($v) => $v !== null && $v !== '');

    $hasBooleanFilter = !empty($onOfferOnly) || !empty($verifiedOnly) || !empty($inStockOnly) || !empty($packsOnly);
    $hasFilters = !empty($q) || !empty($category) || $priceMin !== null || $priceMax !== null || !empty($shopSubdomain) || $hasBooleanFilter;
    $isHome = empty($q) && empty($category) && $priceMin === null && $priceMax === null && empty($shopSubdomain) && !$hasBooleanFilter;

    // Helper para alternar un filtro booleano en el query string actual.
    // Si el filtro está activo lo quita, si no lo agrega.
    $toggleQs = function(string $key) use ($baseQs) {
        $qs = $baseQs;
        if (isset($qs[$key])) unset($qs[$key]);
        else $qs[$key] = 1;
        return $qs;
    };

    // Tiendas únicas derivadas del listado actual (para la sección "Tiendas destacadas").
    // Usamos la data que el listing ya carga — cero cambios al controller.
    $featuredShops = collect();
    if ($isHome && !$listings->isEmpty()) {
        $featuredShops = collect($listings->items())
            ->filter(fn ($l) => !empty($l->tenant_fqdn))
            ->groupBy('tenant_fqdn')
            ->map(fn ($items) => $items->first())
            ->take(6)
            ->values();
    }
@endphp

{{-- Hero removido: el cliente ve trust bar + categorías + productos sin
     interferencia. Cero scroll antes del primer producto en móvil. --}}

{{-- Trust bar movida al footer (layout.blade.php) — antes ocupaba espacio
     arriba del fold sin necesidad. Ahora aparece como reaseguro al pie. --}}

{{-- Carrusel de categorías removido del home: la navegación de categorías
     vive en el mega menú del header (botón "Categorías" de la search bar)
     y en la barra de chips superior. Evita duplicación visual. --}}

{{-- Tiendas destacadas se muestran DESPUÉS del listado de productos para no
     desplazar la fila de productos por debajo del fold (UX 2026: productos primero). --}}

{{-- ═══════════════════════ OFERTAS DEL DÍA (solo home, ≥4 ofertas) ═══════════════════════
     Ocultar cuando el visitante ya esta filtrando por ofertas (?on_offer=1) —
     el listado de abajo ya muestra los mismos productos, evitar duplicado. --}}
@if(isset($dailyOffers) && $dailyOffers->count() >= 4 && empty($onOfferOnly))
    <section class="mp-section mp-offers-block" aria-label="Ofertas del día">
        <div class="mp-offers-head">
            <div>
                <h2 class="mp-offers-title">🔥 Ofertas del día</h2>
                <p class="mp-offers-sub">Descuentos vigentes de tiendas verificadas. Aprovecha mientras duren.</p>
            </div>
            <a href="{{ route('marketplace.index', ['sort' => 'price_asc']) }}" class="mp-offers-cta">Ver todas →</a>
        </div>
        <div class="mp-offers-rail" id="mpOffersRail">
            @foreach($dailyOffers as $offer)
                <a href="{{ route('marketplace.item', $offer->slug) }}" class="mp-offer-card">
                    <div class="mp-offer-card__img">
                        @if($offer->image_url)
                            <img src="{{ $offer->image_url }}" alt="{{ $offer->title }}" loading="lazy">
                        @else
                            <div class="mp-offer-card__noimg">Sin imagen</div>
                        @endif
                        @if(!empty($offer->discount_pct))
                            <span class="mp-offer-card__pct">-{{ $offer->discount_pct }}%</span>
                        @endif
                        @if(!empty($offer->offer_ends_at))
                            <span class="mp-offer-card__timer" data-ends-at="{{ \Carbon\Carbon::parse($offer->offer_ends_at)->toIso8601String() }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span class="mp-offer-card__timer-txt">…</span>
                            </span>
                        @endif
                    </div>
                    <div class="mp-offer-card__body">
                        <h3 class="mp-offer-card__title">{{ $offer->title }}</h3>
                        <div class="mp-offer-card__price-row">
                            <span class="mp-offer-card__price">S/ {{ number_format($offer->display_price, 2) }}</span>
                            @if(!empty($offer->original_price) && $offer->original_price > $offer->display_price)
                                <span class="mp-offer-card__old">S/ {{ number_format($offer->original_price, 2) }}</span>
                            @endif
                        </div>
                        <div class="mp-offer-card__shop">{{ $offer->seller_display }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <style>
        .mp-offers-block { padding: 16px 0 8px; }
        .mp-offers-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:12px; }
        .mp-offers-title { margin:0; font-size:18px; font-weight:800; color:#0a0e1a; }
        .mp-offers-sub { margin:2px 0 0; font-size:12.5px; color:#6b7280; }
        .mp-offers-cta { font-size:13px; font-weight:700; color:#dc2626; text-decoration:none; white-space:nowrap; }
        .mp-offers-cta:hover { text-decoration:underline; }
        .mp-offers-rail {
            display:flex; gap:14px;
            overflow-x:auto; scroll-snap-type:x mandatory; scroll-behavior:smooth;
            padding:4px 2px 14px;
            scrollbar-width:thin;
        }
        .mp-offers-rail::-webkit-scrollbar { height:6px; }
        .mp-offers-rail::-webkit-scrollbar-thumb { background:rgba(0,0,0,.1); border-radius:999px; }
        .mp-offer-card {
            flex:0 0 auto; scroll-snap-align:start;
            width:200px;
            background:#fff; border:1px solid #f1f5f9; border-radius:14px;
            text-decoration:none; color:inherit;
            overflow:hidden;
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .mp-offer-card:hover {
            transform: translateY(-3px);
            border-color: rgba(220,38,38,.35);
            box-shadow: 0 10px 22px -12px rgba(220,38,38,.22);
        }
        .mp-offer-card__img { position:relative; aspect-ratio:1/1; background:#f7f9fb; overflow:hidden; }
        .mp-offer-card__img img { width:100%; height:100%; object-fit:cover; transition:transform .35s; }
        .mp-offer-card:hover .mp-offer-card__img img { transform:scale(1.04); }
        .mp-offer-card__noimg { display:flex; align-items:center; justify-content:center; height:100%; color:#9ca3af; font-size:12px; }
        .mp-offer-card__pct {
            position:absolute; top:8px; left:8px;
            background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
            font-size:12px; font-weight:800; letter-spacing:.3px;
            padding:3px 8px; border-radius:999px;
            box-shadow: 0 4px 8px -4px rgba(220,38,38,.45);
        }
        .mp-offer-card__timer {
            position:absolute; bottom:8px; right:8px;
            display:inline-flex; align-items:center;
            background:rgba(15,23,42,.85); color:#fff;
            font-size:11px; font-weight:600;
            padding:3px 8px; border-radius:999px;
            backdrop-filter: blur(4px);
        }
        .mp-offer-card__body { padding:10px 12px 12px; }
        .mp-offer-card__title {
            margin:0 0 6px; font-size:13px; font-weight:600; color:#1f2937;
            line-height:1.3;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
            overflow:hidden;
            min-height: 34px;
        }
        .mp-offer-card__price-row { display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; }
        .mp-offer-card__price { font-size:16px; font-weight:800; color:#dc2626; }
        .mp-offer-card__old { font-size:12px; color:#9ca3af; text-decoration:line-through; }
        .mp-offer-card__shop { margin-top:4px; font-size:11.5px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        @media (max-width: 640px) {
            .mp-offer-card { width: 160px; }
            .mp-offers-title { font-size:16px; }
            .mp-offers-sub { font-size:12px; }
        }
    </style>

    <script>
    // Countdown ligero para los timers de "Termina en". Update cada minuto;
    // si la oferta expira mientras el cliente está en la página, el timer
    // muestra "Expirada" sin recargar. Sin overhead notable: una sola
    // pasada por todos los timers cada 60s.
    (function () {
        var timers = document.querySelectorAll('.mp-offer-card__timer[data-ends-at]');
        if (!timers.length) return;
        function fmt(ms) {
            if (ms <= 0) return 'Expirada';
            var h = Math.floor(ms / 3600000);
            var m = Math.floor((ms % 3600000) / 60000);
            if (h >= 24) {
                var d = Math.floor(h / 24);
                return d + 'd ' + (h % 24) + 'h';
            }
            return h + 'h ' + m + 'm';
        }
        function tick() {
            var now = Date.now();
            timers.forEach(function (t) {
                var ends = new Date(t.getAttribute('data-ends-at')).getTime();
                var span = t.querySelector('.mp-offer-card__timer-txt');
                if (span) span.textContent = fmt(ends - now);
            });
        }
        tick();
        setInterval(tick, 60000);
    })();
    </script>
@endif

{{-- ═══════════════════════ BREADCRUMB (resultados filtrados) ═══════════════════════ --}}
@if($q || $category)
    <nav class="mp-breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a>
        @if($category)
            <span class="sep">›</span>
            <span>{{ $category }}</span>
        @endif
        @if($q)
            <span class="sep">›</span>
            <span>Resultados para "{{ $q }}"</span>
        @endif
    </nav>
@endif

{{-- ═══════════════════════ LAYOUT LISTADO ═══════════════════════ --}}
<div class="mp-list-layout" id="productos">

    <button type="button" class="mp-filters-mobile-btn" onclick="document.getElementById('mpFilters').classList.add('is-open'); document.body.style.overflow='hidden';">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        Filtros{{ $hasFilters ? ' (activos)' : '' }}
    </button>

    <aside class="mp-filters-card" id="mpFilters">
        <div class="mp-filters-header">
            <h3>Filtrar productos</h3>
            @if($hasFilters)
                <a href="{{ route('marketplace.index') }}" class="mp-filters-clear">Limpiar</a>
            @endif
            <button type="button" class="mp-filters-close"
                    onclick="document.getElementById('mpFilters').classList.remove('is-open'); document.body.style.overflow='';"
                    style="display:none" id="mpFiltersClose">×</button>
        </div>

        {{-- Bloque "Categorías" del sidebar removido: mostraba las categorías
             internas del tenant (Macetas, OFERTA/SALE, etc.) que se mezclaban
             con la taxonomía oficial del marketplace de los chips de arriba.
             La taxonomía oficial sigue activa vía /marketplace/c/{slug}. --}}

        @if(!empty($shops) && $shops->count() > 0)
            <div class="mp-filter-group">
                <div class="mp-filter-label mp-filter-label--with-action">
                    <span class="d-flex align-items-center gap-1">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                        Tiendas <small class="text-muted">({{ $shops->count() }})</small>
                    </span>
                </div>
                @php
                    // Para los links del sidebar "Tiendas" descartamos el query
                    // de búsqueda (q): el seller suele teclear el nombre de la
                    // tienda en el buscador y luego filtrar — si preservamos q
                    // queda doble filtro y muestra 0 resultados aunque la tienda
                    // tenga productos. También quitamos 'shop' para que los
                    // links nuevos sobrescriban el actual sin acumular.
                    $shopBaseQs = array_diff_key($baseQs, ['shop' => null, 'q' => null]);
                @endphp

                {{-- Buscador inline (filtra client-side las tiendas visibles).
                     Solo aparece si hay >5 tiendas para no saturar. --}}
                @if($shops->count() > 5)
                    <input type="search"
                           id="mpShopFilterInput"
                           class="mp-shop-search"
                           placeholder="🔍 Buscar tienda…"
                           autocomplete="off">
                @endif

                <ul class="mp-filter-list mp-shop-list">
                    <li>
                        <a href="{{ route('marketplace.index', $shopBaseQs) }}"
                           class="mp-filter-item mp-shop-item {{ empty($shopSubdomain) ? 'is-active' : '' }}">
                            <span class="mp-shop-logo mp-shop-logo--all">🏬</span>
                            <span class="mp-shop-name">Todas las tiendas</span>
                            <span class="mp-filter-count">{{ $shops->sum('products_count') }}</span>
                        </a>
                    </li>
                    @foreach($shops as $shop)
                        <li data-shop-name="{{ mb_strtolower($shop->name) }}">
                            <a href="{{ route('marketplace.index', array_merge($shopBaseQs, ['shop' => $shop->subdomain])) }}"
                               class="mp-filter-item mp-shop-item {{ $shopSubdomain === $shop->subdomain ? 'is-active' : '' }}"
                               title="Ver productos de {{ $shop->name }}">
                                @if($shop->logo_url)
                                    <img class="mp-shop-logo" src="{{ $shop->logo_url }}" alt="" loading="lazy">
                                @else
                                    <span class="mp-shop-logo mp-shop-logo--initial">{{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}</span>
                                @endif
                                <span class="mp-shop-name">
                                    {{ $shop->name }}
                                    @if($shop->verified)
                                        <span class="mp-shop-verified" title="Tienda verificada por ebaemy">✓</span>
                                    @endif
                                </span>
                                <span class="mp-filter-count">{{ $shop->products_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('marketplace.index', array_merge($shopBaseQs, ['view' => 'shops'])) }}"
                   class="mp-shop-see-all">Ver todas las tiendas →</a>
            </div>
        @endif

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Precio (S/)
            </div>
            <form method="GET" action="{{ route('marketplace.index') }}">
                @if($q)             <input type="hidden" name="q"        value="{{ $q }}">             @endif
                @if($category)      <input type="hidden" name="category" value="{{ $category }}">      @endif
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                @if($shopSubdomain) <input type="hidden" name="shop"     value="{{ $shopSubdomain }}"> @endif
                {{-- Preservar filtros booleanos al aplicar precio --}}
                @if(!empty($onOfferOnly))  <input type="hidden" name="on_offer" value="1"> @endif
                @if(!empty($verifiedOnly)) <input type="hidden" name="verified" value="1"> @endif
                @if(!empty($inStockOnly))  <input type="hidden" name="in_stock" value="1"> @endif
                @if(!empty($packsOnly))    <input type="hidden" name="packs"    value="1"> @endif
                <div class="mp-price-range">
                    <input type="number" name="price_min" min="0" step="0.01" placeholder="Desde" value="{{ $priceMin !== null ? $priceMin : '' }}">
                    <span class="mp-price-range-sep">—</span>
                    <input type="number" name="price_max" min="0" step="0.01" placeholder="Hasta" value="{{ $priceMax !== null ? $priceMax : '' }}">
                </div>
                <button type="submit" class="mp-filter-apply">Aplicar</button>
            </form>
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 12V2H4v10l8 10 8-10z"/></svg>
                Ofertas
            </div>
            <a href="{{ route('marketplace.index', $toggleQs('on_offer')) }}"
               class="mp-filter-checkbox {{ !empty($onOfferOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($onOfferOnly) ? 'checked' : '' }} onclick="return false">
                Con descuento
            </a>
            <a href="{{ route('marketplace.index', $toggleQs('packs')) }}"
               class="mp-filter-checkbox {{ !empty($packsOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($packsOnly) ? 'checked' : '' }} onclick="return false">
                📦 Solo packs / combos
            </a>
            {{-- 'Envío gratis' removido hasta que la feature este implementada.
                 Lo dejamos comentado para reactivar cuando este lista. --}}
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                Confianza
            </div>
            <a href="{{ route('marketplace.index', $toggleQs('verified')) }}"
               class="mp-filter-checkbox {{ !empty($verifiedOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($verifiedOnly) ? 'checked' : '' }} onclick="return false">
                Solo tiendas verificadas
            </a>
            <a href="{{ route('marketplace.index', $toggleQs('in_stock')) }}"
               class="mp-filter-checkbox {{ !empty($inStockOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($inStockOnly) ? 'checked' : '' }} onclick="return false">
                Con stock disponible
            </a>
        </div>
    </aside>

    <div class="mp-main-col">
        <div class="mp-toolbar">
            <div>
                <div class="mp-toolbar-count">
                    <strong>{{ $listings->total() }}</strong>
                    producto{{ $listings->total() === 1 ? '' : 's' }}
                    @if($q) para "{{ $q }}"@endif
                </div>
                @if($isHome && $listings->total() > 0)
                    <div style="font-size:12px;color:var(--mp-muted);margin-top:2px">🔥 Productos destacados de nuestro marketplace</div>
                @endif
            </div>
            <form method="GET" action="{{ route('marketplace.index') }}" style="margin:0">
                @if($q)        <input type="hidden" name="q"        value="{{ $q }}">        @endif
                @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
                @if($priceMin !== null) <input type="hidden" name="price_min" value="{{ $priceMin }}"> @endif
                @if($priceMax !== null) <input type="hidden" name="price_max" value="{{ $priceMax }}"> @endif
                @if($shopSubdomain)        <input type="hidden" name="shop"     value="{{ $shopSubdomain }}"> @endif
                @if(!empty($onOfferOnly))  <input type="hidden" name="on_offer" value="1"> @endif
                @if(!empty($verifiedOnly)) <input type="hidden" name="verified" value="1"> @endif
                @if(!empty($inStockOnly))  <input type="hidden" name="in_stock" value="1"> @endif
                @if(!empty($packsOnly))    <input type="hidden" name="packs"    value="1"> @endif
                <select name="sort" class="mp-sort-dropdown" onchange="this.form.submit()" aria-label="Ordenar">
                    <option value="relevance" {{ $sort === 'relevance'  ? 'selected' : '' }}>Relevancia</option>
                    <option value="price_asc" {{ $sort === 'price_asc'  ? 'selected' : '' }}>Precio: menor a mayor</option>
                    <option value="price_desc"{{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                    <option value="newest"    {{ $sort === 'newest'     ? 'selected' : '' }}>Más recientes</option>
                </select>
            </form>
        </div>

        {{-- ── Pills de filtros activos ────────────────────────────────────
             Muestra arriba del grid TODO lo aplicado. Cada pill tiene su X
             que quita SOLO ese filtro; "Limpiar todo" reset completo.
             Mobile-first: scroll horizontal con fade gradient hint. --}}
        @php
            $activeFilters = [];
            $currentParams = array_filter([
                'q'                    => $q,
                'category'             => $category,
                'official_category_id' => $officialCatId,
                'price_min'            => $priceMin,
                'price_max'            => $priceMax,
                'shop'                 => $shopSubdomain,
                'on_offer'             => $onOfferOnly ? 1 : null,
                'verified'             => $verifiedOnly ? 1 : null,
                'in_stock'             => $inStockOnly ? 1 : null,
                'packs'                => $packsOnly ? 1 : null,
                'sort'                 => ($sort && $sort !== 'relevance') ? $sort : null,
            ], fn($v) => $v !== null && $v !== '' && $v !== false);

            $removeUrl = function (string ...$keys) use ($currentParams) {
                $kept = $currentParams;
                foreach ($keys as $k) { unset($kept[$k]); }
                return route('marketplace.index', $kept);
            };

            if ($q) {
                $activeFilters[] = ['label' => '🔍 "' . $q . '"', 'url' => $removeUrl('q')];
            }
            if ($shopSubdomain) {
                $shopName = optional($shops->firstWhere('subdomain', $shopSubdomain))->name ?? $shopSubdomain;
                $activeFilters[] = ['label' => '🏪 ' . $shopName, 'url' => $removeUrl('shop')];
            }
            if ($officialCatId) {
                $catName = optional($officialRoots->firstWhere('id', $officialCatId))->name ?? 'Categoría';
                $activeFilters[] = ['label' => '🏷️ ' . $catName, 'url' => $removeUrl('official_category_id')];
            }
            if ($category) {
                $activeFilters[] = ['label' => '🏷️ ' . $category, 'url' => $removeUrl('category')];
            }
            if ($priceMin !== null || $priceMax !== null) {
                $priceLabel = '💰 ';
                if ($priceMin !== null && $priceMax !== null) {
                    $priceLabel .= 'S/ ' . (int)$priceMin . ' - S/ ' . (int)$priceMax;
                } elseif ($priceMin !== null) {
                    $priceLabel .= 'Desde S/ ' . (int)$priceMin;
                } else {
                    $priceLabel .= 'Hasta S/ ' . (int)$priceMax;
                }
                $activeFilters[] = ['label' => $priceLabel, 'url' => $removeUrl('price_min', 'price_max')];
            }
            if ($onOfferOnly)  $activeFilters[] = ['label' => '⚡ Oferta',    'url' => $removeUrl('on_offer')];
            if ($verifiedOnly) $activeFilters[] = ['label' => '✓ Verificada', 'url' => $removeUrl('verified')];
            if ($inStockOnly)  $activeFilters[] = ['label' => '📦 Con stock', 'url' => $removeUrl('in_stock')];
            if ($packsOnly)    $activeFilters[] = ['label' => '🎁 Pack',      'url' => $removeUrl('packs')];
            if ($sort && $sort !== 'relevance') {
                $sortLabels = ['price_asc' => 'Precio ↑', 'price_desc' => 'Precio ↓', 'newest' => 'Más recientes'];
                $activeFilters[] = ['label' => '↕ ' . ($sortLabels[$sort] ?? $sort), 'url' => $removeUrl('sort')];
            }
        @endphp

        @if(!empty($activeFilters))
            <div class="mp-active-filters" role="region" aria-label="Filtros activos">
                <span class="mp-active-filters__label">Filtros:</span>
                <div class="mp-active-filters__pills">
                    @foreach($activeFilters as $f)
                        <a href="{{ $f['url'] }}" class="mp-pill" title="Quitar este filtro">
                            <span class="mp-pill__text">{{ $f['label'] }}</span>
                            <svg class="mp-pill__x" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                <line x1="6" y1="6" x2="18" y2="18"/>
                                <line x1="6" y1="18" x2="18" y2="6"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
                @if(count($activeFilters) >= 2)
                    <a href="{{ route('marketplace.index') }}" class="mp-active-filters__clear">Limpiar todo</a>
                @endif
            </div>
        @endif

        @if($listings->isEmpty())
            <div class="mp-empty">
                <div class="mp-empty-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </div>
                <h3>No encontramos productos</h3>
                <p>Prueba con otra búsqueda o explora <a href="{{ route('marketplace.index') }}" style="color:var(--mp-primary-dark);font-weight:600">todo el marketplace</a>.</p>
            </div>
        @else
            <div class="mp-grid">
                @foreach($listings as $idx => $listing)
                    @php
                        // Badges del loop — solo en home en la primera página.
                        $showTopBadge  = $isHome && $idx === 0 && $listings->currentPage() === 1;
                        $showBestBadge = $isHome && $idx === 1 && $listings->currentPage() === 1;
                        $showNewBadge  = !$showTopBadge && !$showBestBadge
                                         && isset($listing->created_at)
                                         && \Carbon\Carbon::parse($listing->created_at)->gt(now()->subDays(14));
                    @endphp
                    @include('marketplace.partials.listing-card', [
                        'listing'       => $listing,
                        'showTopBadge'  => $showTopBadge,
                        'showBestBadge' => $showBestBadge,
                        'showNewBadge'  => $showNewBadge,
                    ])
                @endforeach
            </div>

            <div class="mp-pag">
                {{ $listings->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════ TIENDAS DESTACADAS (después de productos) ═══════════════════════ --}}
@if($featuredShops->count() >= 3)
    <section class="mp-section">
        <div class="mp-section-head">
            <div>
                <h2 class="mp-section-title">
                    <span class="mp-section-title-emoji">🏪</span>
                    Tiendas destacadas
                </h2>
                <p class="mp-section-subtitle">Empresas peruanas reales con RUC verificado vendiendo en ebaemy.</p>
            </div>
        </div>
        <div class="mp-shops-row">
            @foreach($featuredShops as $shop)
                <a href="https://{{ $shop->tenant_fqdn }}" target="_blank" rel="noopener" class="mp-shop-card">
                    <div class="mp-shop-card-logo">
                        @if(!empty($shop->tenant_logo_url))
                            <img src="{{ $shop->tenant_logo_url }}" alt="{{ $shop->seller_display }}" loading="lazy">
                        @else
                            <span class="mp-shop-card-logo-fallback">{{ mb_strtoupper(mb_substr($shop->seller_display, 0, 2)) }}</span>
                        @endif
                    </div>
                    <h3 class="mp-shop-card-name">{{ \Illuminate\Support\Str::limit($shop->seller_display, 34) }}</h3>
                    @if(!empty($shop->tenant_verified))
                        <span class="mp-shop-card-meta">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                            Verificada
                        </span>
                    @endif
                    <span class="mp-shop-card-cta">
                        Ver tienda
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- Estilos de cards/grid/paginador del marketplace viven en
     marketplace.partials.listing-card-styles, incluido por el layout
     para que las 4 vistas (home, categoría oficial, categoría legacy,
     tienda) compartan exactamente el mismo CSS. --}}

<style>
    /* ───────────── Sticky bottom bar móvil con carrito + filtros ───────────── */
    .mp-mobile-actionbar {
        display: none;
        position: fixed; left: 0; right: 0; bottom: 0;
        z-index: 60;
        background: rgba(255,255,255,.97);
        backdrop-filter: blur(8px);
        border-top: 1px solid #e5e7eb;
        padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
        gap: 8px;
        box-shadow: 0 -6px 20px -10px rgba(0,0,0,.12);
    }
    .mp-mobile-actionbar .mp-mab-btn {
        flex: 1;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 11px 12px;
        border-radius: 999px;
        font-size: 13px; font-weight: 700;
        text-decoration: none;
        border: 1px solid #e5e7eb;
        background: #fff; color: #1f2937;
    }
    .mp-mobile-actionbar .mp-mab-btn--primary {
        background: linear-gradient(135deg, #0f8a82, #0a6f68);
        color: #fff; border-color: transparent;
    }
    @media (max-width: 768px) {
        .mp-mobile-actionbar { display: flex; }
        body { padding-bottom: 64px; } /* deja espacio para que el bar no tape contenido */
    }

    /* ───────────── Filtros como modal en móvil (overlay) ───────────── */
    @media (max-width: 768px) {
        .mp-filters-card.is-open {
            position: fixed !important;
            inset: 0;
            z-index: 70;
            background: #fff;
            overflow-y: auto;
            border-radius: 0;
            padding: 16px;
            margin: 0;
        }
        .mp-filters-card { display: none; }
        .mp-filters-card.is-open { display: block; }
    }
</style>

<div class="mp-mobile-actionbar" aria-hidden="false">
    <button type="button" class="mp-mab-btn"
            onclick="document.getElementById('mpFilters').classList.add('is-open');document.body.style.overflow='hidden';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        Filtrar
    </button>
    <a href="{{ route('marketplace.cart') }}" class="mp-mab-btn mp-mab-btn--primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Carrito
    </a>
</div>

<script>
if (window.matchMedia('(max-width: 899px)').matches) {
    var btn = document.getElementById('mpFiltersClose');
    if (btn) btn.style.display = 'inline-block';
}

{{-- El JS de hover de dots y click en shop-link ahora vive en
     marketplace.partials.listing-card-script (incluido por layout.blade.php),
     compartido con todas las vistas que renderizan cards. --}}
</script>
@push('styles')
<style>
/* ── Filtro Tiendas mejorado (sidebar marketplace) ── */
.mp-filter-label--with-action { display:flex; justify-content:space-between; align-items:center; }
.mp-shop-search {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--mp-line);
    border-radius: 6px;
    font-size: 12.5px;
    margin-bottom: 8px;
    outline: 0;
}
.mp-shop-search:focus { border-color: var(--mp-primary); }
.mp-shop-list { max-height: 320px; overflow-y: auto; }
.mp-shop-list::-webkit-scrollbar { width: 4px; }
.mp-shop-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
.mp-shop-item {
    display: flex !important;
    align-items: center;
    gap: 8px;
    padding: 5px 6px !important;
    border-radius: 6px;
    transition: background .12s;
}
.mp-shop-item:hover { background: rgba(15,138,130,.05); }
.mp-shop-item.is-active { background: rgba(15,138,130,.10); font-weight: 600; }
.mp-shop-logo {
    width: 24px; height: 24px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
    color: var(--mp-muted);
    overflow: hidden;
}
.mp-shop-logo--all { background: linear-gradient(135deg, #0f8a82, #0a6f68); color: #fff; font-size: 12px; }
.mp-shop-logo--initial { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.mp-shop-name {
    flex: 1; font-size: 12.5px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mp-shop-verified {
    display: inline-block;
    margin-left: 4px;
    width: 14px; height: 14px;
    background: #16a34a;
    color: #fff;
    border-radius: 50%;
    font-size: 9px; font-weight: 700;
    text-align: center; line-height: 14px;
    vertical-align: middle;
}
.mp-shop-see-all {
    display: block;
    margin-top: 6px;
    padding: 6px;
    text-align: center;
    font-size: 11.5px;
    color: var(--mp-primary-dark);
    font-weight: 600;
    text-decoration: none;
    border-top: 1px dashed var(--mp-line-soft);
}
.mp-shop-see-all:hover { background: rgba(15,138,130,.04); }
.mp-shop-empty { padding: 8px; font-size: 11.5px; color: var(--mp-muted); text-align: center; }

/* ═════════════════════ Pills de filtros activos ═════════════════════
   Aparecen sobre el grid cuando hay al menos 1 filtro aplicado. Click
   en la X de un pill quita SOLO ese filtro. "Limpiar todo" reset.
   Mobile: scroll horizontal con fade gradient hint. */
.mp-active-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #f0fdfa 0%, #fff 100%);
    border: 1px solid #d1fae5;
    border-radius: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.mp-active-filters__label {
    font-size: 12.5px;
    font-weight: 700;
    color: #0c6b65;
    text-transform: uppercase;
    letter-spacing: .03em;
    flex-shrink: 0;
}
.mp-active-filters__pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
}
.mp-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    background: #fff;
    border: 1.5px solid var(--mp-primary, #0f8a82);
    color: var(--mp-primary-dark, #0c6b65);
    border-radius: 999px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    transition: background .12s, border-color .12s, color .12s;
    white-space: nowrap;
    max-width: 100%;
}
.mp-pill:hover {
    background: #fee2e2;
    border-color: #dc2626;
    color: #b91c1c;
}
.mp-pill__text {
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}
.mp-pill__x {
    flex-shrink: 0;
    opacity: .7;
}
.mp-pill:hover .mp-pill__x { opacity: 1; }
.mp-active-filters__clear {
    margin-left: auto;
    font-size: 13px;
    color: #dc2626;
    text-decoration: none;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    flex-shrink: 0;
}
.mp-active-filters__clear:hover {
    background: #fee2e2;
    color: #b91c1c;
}

@media (max-width: 768px) {
    .mp-active-filters {
        padding: 8px 12px;
        gap: 8px;
    }
    .mp-active-filters__label {
        width: 100%;
        margin-bottom: 2px;
    }
    .mp-active-filters__pills {
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: thin;
        -webkit-mask-image: linear-gradient(to right, #000 92%, transparent);
                mask-image: linear-gradient(to right, #000 92%, transparent);
        padding-bottom: 4px;
        margin-right: -12px;
        padding-right: 12px;
    }
    .mp-active-filters__pills::-webkit-scrollbar { height: 3px; }
    .mp-active-filters__pills::-webkit-scrollbar-thumb {
        background: var(--mp-line, #e5e7eb);
        border-radius: 999px;
    }
    .mp-pill {
        padding: 7px 12px;
        font-size: 12.5px;
        flex-shrink: 0;
    }
    .mp-pill__text { max-width: 140px; }
    .mp-active-filters__clear {
        margin-left: 0;
        width: 100%;
        text-align: center;
        padding: 6px;
        background: #fff;
        border: 1.5px dashed #dc2626;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function(){
    const input = document.getElementById('mpShopFilterInput');
    if (!input) return;
    const list = input.parentElement.querySelector('.mp-shop-list');
    if (!list) return;
    const items = list.querySelectorAll('li[data-shop-name]');

    input.addEventListener('input', () => {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;
        items.forEach(li => {
            const name = li.dataset.shopName || '';
            const match = !q || name.includes(q);
            li.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        // Mensaje "sin resultados" si no quedó ninguno (solo "Todas" no cuenta)
        const empty = list.querySelector('.mp-shop-empty');
        if (visible === 0 && q) {
            if (!empty) {
                const el = document.createElement('li');
                el.className = 'mp-shop-empty';
                el.textContent = 'Sin coincidencias';
                list.appendChild(el);
            }
        } else if (empty) {
            empty.remove();
        }
    });
})();
</script>
@endpush

@include('marketplace.partials.recently-viewed', ['recentlyViewed' => $recentlyViewed ?? collect()])

@endsection
