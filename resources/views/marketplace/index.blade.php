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
@endphp

{{-- ═══════════════════════ HERO ═══════════════════════ --}}
@if(empty($q) && empty($category))
    <section class="mp-hero">
        <div>
            <span class="mp-hero-eyebrow">🇵🇪 Marketplace peruano</span>
            <h1>Descubre miles de productos<br>en un solo lugar</h1>
            <p>Compra con confianza a tiendas verificadas con RUC validado. Pago seguro y entrega en todo el Perú.</p>
            <div class="mp-hero-actions">
                <a href="#productos" class="mp-btn mp-btn-primary">
                    Explorar productos
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="{{ route('seller.landing') }}" class="mp-btn mp-btn-outline">Vender en ebaemy</a>
            </div>
        </div>
        <div class="mp-hero-visual" aria-hidden="true">
            <div class="mp-hero-card-stack">
                <div class="mp-hero-card mp-hero-card--1">
                    <div style="font-size:11px;opacity:.85;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Tiendas</div>
                    <div style="font-size:32px;font-weight:800;margin:4px 0 0;letter-spacing:-.02em;">+500</div>
                    <div style="font-size:12px;opacity:.85;">verificadas con RUC</div>
                </div>
                <div class="mp-hero-card mp-hero-card--2">
                    <div style="font-size:11px;opacity:.85;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Productos</div>
                    <div style="font-size:32px;font-weight:800;margin:4px 0 0;letter-spacing:-.02em;">+10k</div>
                    <div style="font-size:12px;opacity:.85;">en todo el Perú</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="mp-trust-strip">
        <div class="mp-trust-item">
            <span class="mp-trust-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <div>
                <div class="mp-trust-label">Compra segura</div>
                <div class="mp-trust-sub">Protegida por ebaemy</div>
            </div>
        </div>
        <div class="mp-trust-item">
            <span class="mp-trust-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="13" height="10" rx="2"/><path d="M15 9h5l2 4v4h-7V9z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
            </span>
            <div>
                <div class="mp-trust-label">Envío a todo el Perú</div>
                <div class="mp-trust-sub">Coordinado por cada vendedor</div>
            </div>
        </div>
        <div class="mp-trust-item">
            <span class="mp-trust-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
            </span>
            <div>
                <div class="mp-trust-label">Tiendas verificadas</div>
                <div class="mp-trust-sub">Empresas reales con RUC</div>
            </div>
        </div>
        <div class="mp-trust-item">
            <span class="mp-trust-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
            </span>
            <div>
                <div class="mp-trust-label">Factura electrónica</div>
                <div class="mp-trust-sub">Emitida por el vendedor</div>
            </div>
        </div>
    </section>
@endif

{{-- ═══════════════════════ BREADCRUMB + RESULTADOS ═══════════════════════ --}}
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

    {{-- Sidebar filtros --}}
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

        {{-- Categorías --}}
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

        {{-- Precio --}}
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

        {{-- Ofertas (placeholder UI) --}}
        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 12V2H4v10l8 10 8-10z"/></svg>
                Ofertas
            </div>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Con descuento
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próximamente</span>
            </label>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Envío gratis
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próximamente</span>
            </label>
        </div>

        {{-- Tiendas verificadas --}}
        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                Confianza
            </div>
            <label class="mp-filter-checkbox">
                <input type="checkbox" disabled> Solo tiendas verificadas
                <span style="font-size:10px;color:var(--mp-muted);margin-left:auto">Próx.</span>
            </label>
        </div>
    </aside>

    {{-- Columna main --}}
    <div class="mp-main-col">
        <div class="mp-toolbar">
            <div class="mp-toolbar-count">
                <strong>{{ $listings->total() }}</strong>
                producto{{ $listings->total() === 1 ? '' : 's' }}
                @if($q) para "{{ $q }}"@endif
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
                @foreach($listings as $listing)
                    <a href="{{ route('marketplace.item', $listing->slug) }}" class="mp-card">
                        <div class="mp-card-img">
                            @if($listing->image_url)
                                <img src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy">
                            @else
                                <div class="mp-card-img-empty">Sin imagen</div>
                            @endif
                            <div class="mp-card-badges">
                                @if($listing->tenant_verified)
                                    <span class="mp-badge mp-badge--verified" title="Tienda verificada">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                                        Verificado
                                    </span>
                                @endif
                            </div>
                            <button type="button" class="mp-card-fav" onclick="event.preventDefault();" aria-label="Favoritos">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                            </button>
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
                                <span class="mp-card-price">S/ {{ number_format($listing->display_price, 2) }}</span>
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
// Mostrar botón cerrar del sidebar filter en mobile
if (window.matchMedia('(max-width: 899px)').matches) {
    document.getElementById('mpFiltersClose').style.display = 'inline-block';
}
</script>
@endsection
