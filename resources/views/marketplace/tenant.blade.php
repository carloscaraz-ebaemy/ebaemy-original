@extends('marketplace.layout')

@php
    $tenantUrl = route('marketplace.tenant', ['subdomain' => $store->subdomain]);
    $indexUrl  = route('marketplace.index');

    $bcItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => $indexUrl],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $store->name,  'item' => $tenantUrl],
    ];
    $bc = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $bcItems];

    // Schema.org Store: identifica la tienda como entidad para Google Knowledge Graph.
    $storeSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Store',
        'name'     => $store->name,
        'url'      => $tenantUrl,
        'image'    => $store->logo_url ?: asset('logo/logo.png'),
        'parentOrganization' => [
            '@type' => 'Organization',
            'name'  => 'ebaemy',
            'url'   => url('/'),
        ],
    ];
    if ($store->ruc) {
        $storeSchema['identifier'] = ['@type' => 'PropertyValue', 'name' => 'RUC', 'value' => $store->ruc];
    }
    if (!empty($store->fqdn)) {
        $storeSchema['sameAs'] = ['https://' . $store->fqdn];
    }

    $baseQs = array_filter([
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
        'q'         => $q,
        'category'  => $activeCategoryFullSlug ?? null,
    ], fn($v) => $v !== null && $v !== '');

    $hasFilters = $priceMin !== null || $priceMax !== null || !empty($activeCategoryFullSlug ?? null);
    $description = 'Catálogo oficial de ' . $store->name . ' en el marketplace de ebaemy. ' . $total . ' producto' . ($total === 1 ? '' : 's') . ' disponibles.';
@endphp

@section('title', $store->name . ' — Tienda en Marketplace ebaemy')
@section('description', \Illuminate\Support\Str::limit($description, 160))
@section('keywords', $store->name . ', tienda online, marketplace, ebaemy, Perú')
@section('og_title', $store->name . ' en ebaemy')
@section('og_description', $description)
@if($store->logo_url)
    @section('og_image', $store->logo_url)
@endif
@section('canonical', $tenantUrl)

@push('styles')
<script type="application/ld+json">
{!! json_encode($bc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($storeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<style>
.mp-store-hero {
    display: flex;
    align-items: center;
    gap: clamp(16px, 3vw, 28px);
    padding: clamp(20px, 3vw, 36px);
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border: 1px solid var(--mp-border, #e5e7eb);
    border-radius: 14px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.mp-store-logo {
    width: 96px; height: 96px;
    border-radius: 14px;
    border: 2px solid var(--mp-border, #e5e7eb);
    background: #fff;
    object-fit: contain;
    padding: 6px;
    flex-shrink: 0;
}
.mp-store-logo--placeholder {
    display: flex; align-items: center; justify-content: center;
    font-size: 36px; font-weight: 700; color: var(--mp-primary-dark, #0c6b65);
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
}
.mp-store-info { flex: 1; min-width: 240px; }
.mp-store-info h1 {
    margin: 0 0 6px;
    font-size: clamp(22px, 3vw, 30px);
    color: var(--mp-ink, #111827);
}
.mp-store-meta {
    display: flex; flex-wrap: wrap; gap: 12px;
    color: #6b7280; font-size: 14px;
}
.mp-store-meta span { display: inline-flex; align-items: center; gap: 4px; }
.mp-badge--store-verified {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 999px;
    background: #dcfce7; color: #15803d; font-size: 12px; font-weight: 600;
}
.mp-store-actions {
    display: flex; gap: 10px; flex-wrap: wrap;
}
.mp-store-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 600;
    text-decoration: none; transition: all .15s;
}
.mp-store-btn--primary {
    background: var(--mp-primary, #0f8a82); color: #fff;
}
.mp-store-btn--primary:hover { background: var(--mp-primary-dark, #0c6b65); }
.mp-store-btn--ghost {
    background: #fff; color: var(--mp-ink, #111827);
    border: 1px solid var(--mp-border, #e5e7eb);
}
.mp-store-btn--ghost:hover { border-color: var(--mp-primary, #0f8a82); color: var(--mp-primary-dark, #0c6b65); }
</style>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">{{ $store->name }}</span>
</nav>

<section class="mp-store-hero">
    @if($store->logo_url)
        <img src="{{ $store->logo_url }}" alt="Logo de {{ $store->name }}" class="mp-store-logo">
    @else
        <div class="mp-store-logo mp-store-logo--placeholder">
            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->name, 0, 1)) }}
        </div>
    @endif

    <div class="mp-store-info">
        <h1>
            {{ $store->name }}
            @if($store->verified)
                <span class="mp-badge--store-verified" title="Tienda verificada por ebaemy">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                    Verificado
                </span>
            @endif
        </h1>
        <div class="mp-store-meta">
            <span>📦 {{ $total }} producto{{ $total === 1 ? '' : 's' }}</span>
            @if($store->ruc)<span>🏛️ RUC {{ $store->ruc }}</span>@endif
            <span>🛍️ Tienda en ebaemy</span>
        </div>
    </div>

    <div class="mp-store-actions">
        <a href="{{ $store->site_url }}" class="mp-store-btn mp-store-btn--primary" target="_blank" rel="noopener">
            Visitar tienda online
        </a>
        <a href="{{ route('marketplace.index') }}" class="mp-store-btn mp-store-btn--ghost">
            Ver más tiendas
        </a>
    </div>
</section>

<div class="mp-list-layout">

    <button type="button" class="mp-filters-mobile-btn" onclick="document.getElementById('mpFilters').classList.add('is-open'); document.body.style.overflow='hidden';">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        Filtros{{ $hasFilters ? ' (activos)' : '' }}
    </button>

    <aside class="mp-filters-card" id="mpFilters">
        <div class="mp-filters-header">
            <h3>Filtrar en esta tienda</h3>
            @if($hasFilters || $q)
                <a href="{{ $tenantUrl }}" class="mp-filters-clear">Limpiar</a>
            @endif
            <button type="button" class="mp-filters-close"
                    onclick="document.getElementById('mpFilters').classList.remove('is-open'); document.body.style.overflow='';"
                    style="display:none" id="mpFiltersClose">×</button>
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">🔎 Buscar</div>
            <form method="GET" action="{{ $tenantUrl }}">
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                @if(!empty($activeCategoryFullSlug)) <input type="hidden" name="category" value="{{ $activeCategoryFullSlug }}"> @endif
                <input type="text" name="q" placeholder="Buscar en {{ \Illuminate\Support\Str::limit($store->name, 18) }}…"
                       value="{{ $q }}"
                       style="width:100%;padding:8px 10px;border:1px solid var(--mp-border,#e5e7eb);border-radius:8px">
                <button type="submit" class="mp-filter-apply">Aplicar</button>
            </form>
        </div>

        @if(isset($tenantCategories) && $tenantCategories->count() > 0)
            <div class="mp-filter-group">
                <div class="mp-filter-label">📂 Categorías</div>
                <ul style="list-style:none;padding:0;margin:0">
                    @php
                        $catBaseQs = array_filter([
                            'sort'      => $sort && $sort !== 'relevance' ? $sort : null,
                            'price_min' => $priceMin,
                            'price_max' => $priceMax,
                            'q'         => $q,
                        ], fn($v) => $v !== null && $v !== '');
                    @endphp
                    <li style="margin-bottom:4px">
                        <a href="{{ $tenantUrl . (empty($catBaseQs) ? '' : '?' . http_build_query($catBaseQs)) }}"
                           class="mp-filter-item {{ empty($activeCategoryFullSlug) ? 'is-active' : '' }}">
                            Todas
                        </a>
                    </li>
                    @foreach($tenantCategories as $cat)
                        <li style="margin-bottom:4px">
                            <a href="{{ $tenantUrl . '?' . http_build_query(array_merge($catBaseQs, ['category' => $cat->full_slug])) }}"
                               class="mp-filter-item {{ ($activeCategoryFullSlug ?? null) === $cat->full_slug ? 'is-active' : '' }}">
                                @if($cat->icon){{ $cat->icon }} @endif{{ $cat->name }}
                            </a>
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
            <form method="GET" action="{{ $tenantUrl }}">
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                @if($q) <input type="hidden" name="q" value="{{ $q }}"> @endif
                @if(!empty($activeCategoryFullSlug)) <input type="hidden" name="category" value="{{ $activeCategoryFullSlug }}"> @endif
                <div class="mp-price-range">
                    <input type="number" name="price_min" min="0" step="0.01" placeholder="Desde" value="{{ $priceMin !== null ? $priceMin : '' }}">
                    <span class="mp-price-range-sep">—</span>
                    <input type="number" name="price_max" min="0" step="0.01" placeholder="Hasta" value="{{ $priceMax !== null ? $priceMax : '' }}">
                </div>
                <button type="submit" class="mp-filter-apply">Aplicar</button>
            </form>
        </div>
    </aside>

    <div class="mp-main-col">
        <div class="mp-toolbar">
            <div class="mp-toolbar-count">
                <strong>{{ $listings->total() }}</strong> producto{{ $listings->total() === 1 ? '' : 's' }} en <strong>{{ $store->name }}</strong>
            </div>
            <form method="GET" action="{{ $tenantUrl }}" style="margin:0">
                @foreach($baseQs as $k => $v)
                    @if($k !== 'sort') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
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
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
                <h3>{{ $hasFilters || $q ? 'Sin resultados con esos filtros' : 'Esta tienda aún no tiene productos publicados' }}</h3>
                <p>
                    @if($hasFilters || $q)
                        Prueba quitando filtros o vuelve al <a href="{{ $tenantUrl }}" style="color:var(--mp-primary-dark);font-weight:600">catálogo completo de {{ $store->name }}</a>.
                    @else
                        Vuelve pronto o <a href="{{ route('marketplace.index') }}" style="color:var(--mp-primary-dark);font-weight:600">explora otras tiendas</a>.
                    @endif
                </p>
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
                        </div>
                        <div class="mp-card-body">
                            <h3 class="mp-card-title">{{ $listing->title }}</h3>
                            @if(!empty($listing->rating_count) && $listing->rating_count > 0)
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

<script>
if (window.matchMedia('(max-width: 899px)').matches) {
    document.getElementById('mpFiltersClose').style.display = 'inline-block';
}
</script>
@endsection
