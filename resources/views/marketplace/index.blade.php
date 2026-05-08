@extends('marketplace.layout')

@section('title', ($q ? 'Buscar "'.$q.'" — ' : '') . 'Marketplace ebaemy')

@section('content')

@php
    $baseQs = array_filter([
        'q'         => $q,
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
    ], fn($v) => $v !== null && $v !== '');

    $hasFilters = !empty($q) || !empty($category) || $priceMin !== null || $priceMax !== null;
    $isHome = empty($q) && empty($category) && $priceMin === null && $priceMax === null;

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

{{-- ═══════════════════════ OFERTAS DEL DÍA (solo home, ≥4 ofertas) ═══════════════════════ --}}
@if(isset($dailyOffers) && $dailyOffers->count() >= 4)
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

        @if(!empty($categories) && count($categories) > 0)
            <div class="mp-filter-group">
                <div class="mp-filter-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Categorías
                </div>
                <ul class="mp-filter-list">
                    <li>
                        <a href="{{ route('marketplace.index', $baseQs) }}"
                           class="mp-filter-item {{ empty($category) ? 'is-active' : '' }}">Todas</a>
                    </li>
                    @foreach($categories as $cat)
                        <li>
                            <a href="{{ route('marketplace.index', array_merge($baseQs, ['category' => $cat])) }}"
                               class="mp-filter-item {{ $category === $cat ? 'is-active' : '' }}">{{ $cat }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Precio (S/)
            </div>
            <form method="GET" action="{{ route('marketplace.index') }}">
                @if($q)        <input type="hidden" name="q"        value="{{ $q }}">        @endif
                @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
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
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Con descuento
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próx.</span>
            </label>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Envío gratis
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próx.</span>
            </label>
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                Confianza
            </div>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Solo tiendas verificadas
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próx.</span>
            </label>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Con stock disponible
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próx.</span>
            </label>
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
                <select name="sort" class="mp-sort-dropdown" onchange="this.form.submit()" aria-label="Ordenar">
                    <option value="relevance" {{ $sort === 'relevance'  ? 'selected' : '' }}>Relevancia</option>
                    <option value="price_asc" {{ $sort === 'price_asc'  ? 'selected' : '' }}>Precio: menor a mayor</option>
                    <option value="price_desc"{{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                    <option value="newest"    {{ $sort === 'newest'     ? 'selected' : '' }}>Más recientes</option>
                </select>
            </form>
        </div>

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
                        // Badges derivados del loop: primer producto de cada página
                        // recibe "Top", segundo "Más vendido" cuando está en home.
                        $showTopBadge  = $isHome && $idx === 0 && $listings->currentPage() === 1;
                        $showBestBadge = $isHome && $idx === 1 && $listings->currentPage() === 1;
                        $showNewBadge  = !$showTopBadge && !$showBestBadge
                                         && isset($listing->created_at)
                                         && \Carbon\Carbon::parse($listing->created_at)->gt(now()->subDays(14));
                    @endphp
                    <a href="{{ route('marketplace.item', $listing->slug) }}" class="mp-card">
                        <div class="mp-card-img" data-has-secondary="{{ $listing->secondary_image_url ? '1' : '0' }}">
                            @if($listing->image_url)
                                <img class="mp-card-img-primary" src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy">
                                @if($listing->secondary_image_url)
                                    <img class="mp-card-img-secondary" src="{{ $listing->secondary_image_url }}" alt="" loading="lazy" aria-hidden="true">
                                @endif
                            @else
                                <div class="mp-card-img-empty">Sin imagen</div>
                            @endif

                            <div class="mp-card-badges">
                                @if(!empty($listing->is_featured) && (empty($listing->featured_until) || \Carbon\Carbon::parse($listing->featured_until)->isFuture()))
                                    <span class="mp-badge mp-badge--top" title="Producto destacado" style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff">⭐ Destacado</span>
                                @elseif($showTopBadge)
                                    <span class="mp-badge mp-badge--top" title="Destacado">⭐ Top</span>
                                @elseif($showBestBadge)
                                    <span class="mp-badge mp-badge--best" title="Más vendido">🔥 Más vendido</span>
                                @elseif($showNewBadge)
                                    <span class="mp-badge mp-badge--new" title="Nuevo">NUEVO</span>
                                @endif
                                @if($listing->tenant_verified)
                                    <span class="mp-badge mp-badge--verified" title="Tienda verificada">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                                        Verificado
                                    </span>
                                @endif
                                @if(!empty($listing->is_on_offer) && !empty($listing->discount_pct))
                                    <span class="mp-badge mp-badge--offer" title="En oferta">-{{ $listing->discount_pct }}%</span>
                                @endif
                            </div>

                        </div>
                        <div class="mp-card-body">
                            <h3 class="mp-card-title">{{ $listing->title }}</h3>

                            @if($listing->rating_count > 0)
                                <div class="mp-card-rating">
                                    <span class="mp-card-rating-stars">
                                        @for($i=1;$i<=5;$i++){{ $i <= round($listing->avg_rating) ? '★' : '☆' }}@endfor
                                    </span>
                                    <span class="mp-card-rating-count">({{ $listing->rating_count }})</span>
                                </div>
                            @endif

                            <div class="mp-card-price-row">
                                @if($listing->display_price > 0)
                                    @if(!empty($listing->has_variants))
                                        <span class="mp-card-price-prefix">Desde</span>
                                        <span class="mp-card-price">S/ {{ number_format($listing->min_price ?? $listing->display_price, 2) }}</span>
                                    @else
                                        <span class="mp-card-price">S/ {{ number_format($listing->display_price, 2) }}</span>
                                        @if(!empty($listing->is_on_offer) && !empty($listing->original_price) && $listing->original_price > $listing->display_price)
                                            <span class="mp-card-price-old">S/ {{ number_format($listing->original_price, 2) }}</span>
                                        @endif
                                    @endif
                                @else
                                    <span style="color:#6b7280;font-size:13px;font-weight:500">Consultar precio</span>
                                @endif
                            </div>

                            <div class="mp-card-shop">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                                <span class="mp-card-shop-name" title="Vendido por {{ $listing->seller_display }}">{{ $listing->seller_display }}</span>
                            </div>

                            {{-- Dots de color disponibles. Solo se muestran los valores
                                 que tienen color_hex Y al menos una variante con stock > 0
                                 (filtrado en el controller). --}}
                            @if(!empty($listing->color_dots) && $listing->color_dots->count())
                                <div class="mp-card-colors" aria-label="Colores disponibles">
                                    @foreach($listing->color_dots as $cd)
                                        <span class="mp-card-color-dot mp-card-color-dot--hex"
                                              title="{{ $cd->value }}"
                                              style="background:{{ $cd->color_hex }}"></span>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Thumbs de variantes con imagen propia (legacy: sigue activo
                                 para items que NO tienen opción tipo "color" pero sí imágenes
                                 por variante). Hover cambia la imagen principal de la card. --}}
                            @if(empty($listing->color_dots) && !empty($listing->variant_thumbs) && $listing->variant_thumbs->count())
                                <div class="mp-card-variants" aria-label="Variantes disponibles">
                                    @foreach($listing->variant_thumbs as $vt)
                                        <span class="mp-card-variant-dot"
                                              data-img="{{ $vt->image_url }}"
                                              title="{{ $vt->display_name }}">
                                            <img src="{{ $vt->image_url }}" alt="{{ $vt->display_name }}" loading="lazy">
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </a>
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

<style>
    /* ───────────── Paginador profesional (override de bootstrap-4 view) ───────────── */
    .mp-pag {
        display: flex; justify-content: center;
        margin: 24px 0 16px;
    }
    .mp-pag nav { width: 100%; }
    .mp-pag .pagination {
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 6px;
        list-style: none;
        margin: 0; padding: 0;
    }
    .mp-pag .page-item { margin: 0; }
    .mp-pag .page-item .page-link,
    .mp-pag .page-item span.page-link {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 36px; height: 36px;
        padding: 0 12px;
        font-size: 13.5px; font-weight: 600;
        color: #374151;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        text-decoration: none;
        transition: background .15s, color .15s, border-color .15s, transform .15s;
    }
    .mp-pag .page-item .page-link:hover {
        background: #f0fdfa;
        border-color: var(--mp-primary, #0f8a82);
        color: var(--mp-primary-dark, #0a6f68);
        transform: translateY(-1px);
    }
    .mp-pag .page-item.active .page-link,
    .mp-pag .page-item.active span.page-link {
        background: linear-gradient(135deg, #0f8a82, #0a6f68);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 10px -4px rgba(15,138,130,.45);
    }
    .mp-pag .page-item.disabled .page-link,
    .mp-pag .page-item.disabled span.page-link {
        background: #f9fafb;
        color: #cbd5e1;
        border-color: #f1f5f9;
        cursor: not-allowed;
    }
    /* SVGs internos que vienen del view bootstrap-4 (chevrons) */
    .mp-pag .page-link svg { width: 14px; height: 14px; }
    /* Oculta texto "Showing X to Y of Z results" generado arriba del nav (es feo) */
    .mp-pag p { display: none; }

    @media (max-width: 480px) {
        .mp-pag .pagination { gap: 4px; }
        .mp-pag .page-item .page-link { min-width: 32px; height: 32px; padding: 0 9px; font-size: 12.5px; }
    }

    /* ───────────── Cards de producto modernas (SaaS 2026) ───────────── */
    .mp-card {
        position: relative;
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        overflow: hidden;
        text-decoration: none;
        display: flex; flex-direction: column;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .mp-card:hover {
        transform: translateY(-3px);
        border-color: rgba(15,138,130,.35);
        box-shadow: 0 10px 24px -12px rgba(15,138,130,.22), 0 4px 10px -6px rgba(0,0,0,.06);
    }
    .mp-card-img {
        position: relative;
        aspect-ratio: 1 / 1;
        background: #f7f9fb;
        overflow: hidden;
    }
    .mp-card-img img { transition: transform .35s ease; }
    .mp-card:hover .mp-card-img img { transform: scale(1.04); }

    /* ───────── Hover-image: muestra la 2da foto al pasar el cursor ───────── */
    .mp-card-img-primary,
    .mp-card-img-secondary {
        position: absolute; top: 0; left: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        transition: opacity .25s ease, transform .35s ease;
    }
    .mp-card-img-secondary { opacity: 0; }
    .mp-card-img[data-has-secondary="1"]:hover .mp-card-img-primary { opacity: 0; }
    .mp-card-img[data-has-secondary="1"]:hover .mp-card-img-secondary { opacity: 1; }

    /* ───────── Color dots en cards (estilo Falabella) ───────── */
    .mp-card-colors {
        display: flex; gap: 5px;
        margin-top: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .mp-card-color-dot {
        width: 16px; height: 16px;
        border-radius: 999px;
        border: 1.5px solid #e5e7eb;
        cursor: pointer;
        transition: transform .12s, border-color .15s, box-shadow .15s;
        flex-shrink: 0;
        display: inline-block;
        position: relative;
        overflow: hidden;
    }
    .mp-card-color-dot:hover {
        border-color: #0a0e1a;
        transform: scale(1.18);
        box-shadow: 0 2px 6px -2px rgba(0,0,0,.18);
    }
    .mp-card-color-dot--img img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        border-radius: 999px;
    }
    @media (max-width: 480px) {
        .mp-card-color-dot { width: 14px; height: 14px; }
    }

    /* ───────── Variant dots/thumbs en cards (legacy fallback) ───────── */
    .mp-card-variants {
        display: flex; gap: 4px;
        margin-top: 6px;
        flex-wrap: wrap;
    }
    .mp-card-variant-dot {
        width: 22px; height: 22px;
        border-radius: 6px;
        overflow: hidden;
        border: 1.5px solid #e5e7eb;
        background: #f9fafb;
        cursor: pointer;
        transition: border-color .15s, transform .12s;
        flex-shrink: 0;
    }
    .mp-card-variant-dot img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .15s;
    }
    .mp-card-variant-dot:hover {
        border-color: var(--mp-primary, #0f8a82);
        transform: scale(1.12);
    }
    @media (max-width: 640px) {
        .mp-card-variant-dot { width: 20px; height: 20px; }
    }

    /* CTA "Comprar" overlay que aparece al hover (desktop). En móvil queda fijo abajo */
    .mp-card-cta {
        position: absolute;
        left: 50%; bottom: 10px;
        transform: translateX(-50%) translateY(8px);
        opacity: 0;
        background: #0f8a82;
        color: #fff;
        font-size: 12.5px; font-weight: 700;
        padding: 8px 14px;
        border-radius: 999px;
        display: inline-flex; align-items: center; gap: 6px;
        box-shadow: 0 8px 20px -8px rgba(15,138,130,.55);
        transition: opacity .18s ease, transform .18s ease;
        pointer-events: none;
    }
    .mp-card:hover .mp-card-cta {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .mp-card-body { padding: 12px 12px 14px; }
    .mp-card-price { font-size: 17px; font-weight: 800; color: #0a0e1a; }
    .mp-card-price-prefix {
        font-size: 11px; font-weight: 600;
        color: #6b7280; text-transform: uppercase;
        letter-spacing: .3px; margin-right: 4px;
    }
    .mp-card-price-old {
        font-size: 12.5px; font-weight: 500;
        color: #9ca3af; text-decoration: line-through;
        margin-left: 6px;
    }
    .mp-badge--offer {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        font-weight: 800;
        letter-spacing: .3px;
    }

    /* ───────────── Grid 2 cols en móvil, 4-5 cols en desktop ───────────── */
    .mp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    @media (max-width: 640px) {
        .mp-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .mp-card-body { padding: 10px 10px 12px; }
        .mp-card-title { font-size: 13px; }
        .mp-card-price { font-size: 15px; }
        .mp-card-cta {
            position: static; opacity: 1; pointer-events: auto;
            transform: none;
            display: flex; justify-content: center;
            margin: 8px 10px 10px;
            box-shadow: none;
            font-size: 12px;
            padding: 9px 0;
        }
    }

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

// Hover sobre dots (color o variante) → cambia la imagen principal de la
// card. Solo los dots que tienen data-img cargan algo (los color hex sin
// imagen no hacen nada al hover). Al salir del card, restaura la original.
document.querySelectorAll('.mp-card').forEach(function (card) {
    var dots = card.querySelectorAll('.mp-card-variant-dot, .mp-card-color-dot[data-img]');
    if (!dots.length) return;
    var primary = card.querySelector('.mp-card-img-primary');
    if (!primary) return;
    var origSrc = primary.getAttribute('src');
    dots.forEach(function (dot) {
        dot.addEventListener('mouseenter', function (e) {
            e.preventDefault();
            var url = dot.getAttribute('data-img');
            if (url) primary.src = url;
        });
        // Evita que al click en el dot navegue al detalle (es solo hover preview)
        dot.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    card.addEventListener('mouseleave', function () {
        primary.src = origSrc;
    });
});

// Inyectar CTA "Comprar" en cada card de producto sin duplicar markup en el blade
document.querySelectorAll('.mp-card .mp-card-img').forEach(function (img) {
    if (img.querySelector('.mp-card-cta')) return;
    var cta = document.createElement('span');
    cta.className = 'mp-card-cta';
    cta.innerHTML = 'Comprar <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
    img.appendChild(cta);
});
</script>
@endsection
