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

{{-- ═══════════════════════ HERO COMERCIAL ═══════════════════════ --}}
@if($isHome)
    <section class="mp-hero">
        <div>
            <span class="mp-hero-urgency">🔥 Ofertas por tiempo limitado</span>
            <h1>Descubre miles de productos<br>de tiendas verificadas 🇵🇪</h1>
            <p>Compra con confianza, paga seguro y recibe en todo el Perú. Empresas reales con RUC validado.</p>
            <div class="mp-hero-actions">
                <a href="#productos" class="mp-btn mp-btn-primary">
                    Comprar ahora
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="{{ route('marketplace.index', ['sort' => 'price_asc']) }}" class="mp-btn mp-btn-outline">
                    Ver ofertas
                </a>
            </div>
        </div>

        {{-- Cards decorativas flotantes comerciales --}}
        <div class="mp-hero-visual" aria-hidden="true">
            <div class="mp-hero-card-stack" style="position:relative;height:280px">
                <div class="mp-hero-float mp-hero-float--1">
                    <div class="mp-hero-float-icon offer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V2H4v10l8 10 8-10z"/></svg>
                    </div>
                    <div>
                        <div class="mp-hero-float-label">Descuentos</div>
                        <div class="mp-hero-float-value">Hasta -40%</div>
                    </div>
                </div>
                <div class="mp-hero-float mp-hero-float--2">
                    <div class="mp-hero-float-icon popular">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v18"/><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div>
                        <div class="mp-hero-float-label">Más populares</div>
                        <div class="mp-hero-float-value">Top ventas</div>
                    </div>
                </div>
                <div class="mp-hero-float mp-hero-float--3">
                    <div class="mp-hero-float-icon free">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="13" height="10" rx="2"/><path d="M15 9h5l2 4v4h-7V9z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                    </div>
                    <div>
                        <div class="mp-hero-float-label">Envío</div>
                        <div class="mp-hero-float-value">A todo Perú</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- ═══════════════════════ TRUST BAR STICKY ═══════════════════════ --}}
<section class="mp-trust-sticky">
    <div class="mp-trust-sticky-inner">
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            <span><strong>Compra segura</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="13" height="10" rx="2"/><path d="M15 9h5l2 4v4h-7V9z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
            <span>Envío <strong>a todo Perú</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
            <span>Tiendas <strong>verificadas</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <span>Pago <strong>contra entrega</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
            <span><strong>Factura</strong> electrónica</span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            <span>Soporte <strong>directo</strong></span>
        </span>
    </div>
</section>

{{-- ═══════════════════════ CATEGORÍAS OFICIALES (Fase D) ═══════════════════════ --}}
@if(!empty($officialRoots) && $officialRoots->count())
    <section class="mp-section">
        <div class="mp-section-head">
            <div>
                <h2 class="mp-section-title">
                    <span class="mp-section-title-emoji">🗂️</span>
                    Explora por categoría
                </h2>
                <p class="mp-section-subtitle">Navega el catálogo oficial del marketplace.</p>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:12px">
            @foreach($officialRoots as $root)
                <a href="{{ url('/marketplace/c/' . $root->full_slug) }}"
                   style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:#111827;transition:all .15s;min-height:110px;text-align:center"
                   onmouseover="this.style.borderColor='var(--mp-primary, #0f8a82)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px -8px rgba(15,138,130,.25)'"
                   onmouseout="this.style.borderColor='#e5e7eb';this.style.transform='';this.style.boxShadow=''">
                    <div style="font-size:32px;line-height:1;margin-bottom:6px">{{ $root->icon ?: '📦' }}</div>
                    <div style="font-size:13px;font-weight:600">{{ $root->name }}</div>
                    @if($root->listings_count_cache)
                        <div style="font-size:11px;color:#9ca3af;margin-top:2px">{{ $root->listings_count_cache }} prod.</div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- ═══════════════════════ TIENDAS DESTACADAS (solo home) ═══════════════════════ --}}
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
                            <img src="{{ $shop->tenant_logo_url }}" alt="{{ $shop->seller_display }}">
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
                        <div class="mp-card-img">
                            @if($listing->image_url)
                                <img src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy">
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
                                <span class="mp-card-price">@if($listing->display_price > 0)S/ {{ number_format($listing->display_price, 2) }}@else<span style="color:#6b7280;font-size:13px;font-weight:500">Consultar precio</span>@endif</span>
                            </div>

                            <div class="mp-card-shop">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                                <span class="mp-card-shop-name" title="Vendido por {{ $listing->seller_display }}">{{ $listing->seller_display }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mp-pag">
                {{ $listings->links() }}
            </div>
        @endif
    </div>
</div>

<script>
if (window.matchMedia('(max-width: 899px)').matches) {
    document.getElementById('mpFiltersClose').style.display = 'inline-block';
}
</script>
@endsection
