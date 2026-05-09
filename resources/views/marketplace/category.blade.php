@extends('marketplace.layout')

@php
    // Pre-calculamos expresiones para evitar el parser bug de @json() con
    // argumentos que tienen comas anidadas (ver show.blade.php).
    $categoryUrl = route('marketplace.category', $categorySlug);
    $indexUrl    = route('marketplace.index');
    $breadcrumb = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => $indexUrl],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category],
        ],
    ];
    $collectionPage = [
        '@context'      => 'https://schema.org',
        '@type'         => 'CollectionPage',
        'name'          => $category . ' — Marketplace ebaemy',
        'url'           => $categoryUrl,
        'numberOfItems' => (int) $total,
    ];

    $baseQs = array_filter([
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
    ], fn($v) => $v !== null && $v !== '');

    $hasFilters = $priceMin !== null || $priceMax !== null;
@endphp

@section('title', $category . ' — Marketplace ebaemy')
@section('description', 'Explora productos de la categoría ' . $category . ' de tiendas peruanas en ebaemy. ' . $total . ' producto(s) disponibles.')
@section('keywords', $category . ', marketplace, tiendas, ebaemy, Perú')
@section('og_title', $category . ' en Marketplace ebaemy')
@section('og_description', $total . ' productos de la categoría ' . $category . ' en ebaemy.com.')
@section('canonical', $categoryUrl)

@push('styles')
<script type="application/ld+json">
{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($collectionPage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">{{ $category }}</span>
</nav>

{{-- Hero compacto de categoría --}}
<section class="mp-hero" style="min-height:180px;padding:clamp(24px, 4vw, 48px) clamp(20px, 4vw, 56px)">
    <div>
        <span class="mp-hero-eyebrow">Categoría</span>
        <h1 style="font-size:clamp(24px, 3.5vw, 36px)">{{ $category }}</h1>
        <p>{{ $total }} producto{{ $total === 1 ? '' : 's' }} en esta categoría — vendidos por tiendas verificadas en ebaemy.</p>
    </div>
</section>

<div class="mp-list-layout">

    {{-- Sidebar --}}
    <button type="button" class="mp-filters-mobile-btn" onclick="document.getElementById('mpFilters').classList.add('is-open'); document.body.style.overflow='hidden';">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
        Filtros{{ $hasFilters ? ' (activos)' : '' }}
    </button>

    <aside class="mp-filters-card" id="mpFilters">
        <div class="mp-filters-header">
            <h3>Filtrar</h3>
            @if($hasFilters)
                <a href="{{ $categoryUrl }}" class="mp-filters-clear">Limpiar</a>
            @endif
            <button type="button" class="mp-filters-close"
                    onclick="document.getElementById('mpFilters').classList.remove('is-open'); document.body.style.overflow='';"
                    style="display:none" id="mpFiltersClose">×</button>
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Precio (S/)
            </div>
            <form method="GET" action="{{ $categoryUrl }}">
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
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
                <strong>{{ $total }}</strong> producto{{ $total === 1 ? '' : 's' }} en <strong>{{ $category }}</strong>
            </div>
            <form method="GET" action="{{ $categoryUrl }}" style="margin:0">
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
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </div>
                <h3>Sin productos en esta categoría</h3>
                <p>Ajusta los filtros o vuelve al <a href="{{ route('marketplace.index') }}" style="color:var(--mp-primary-dark);font-weight:600">marketplace completo</a>.</p>
            </div>
        @else
            <div class="mp-grid">
                @foreach($listings as $listing)
                    @include('marketplace.partials.listing-card', ['listing' => $listing])
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
