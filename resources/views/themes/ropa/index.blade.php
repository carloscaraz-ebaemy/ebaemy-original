{{--
    THEME: ROPA — Home estilo Shein
    Filtros laterales izquierda + grid de productos derecha
--}}
@extends('ecommerce::layouts.master')

@php
    $tagid             = Request::segment(3);
    $hasCategoryFilter = isset($currentCategory) && $currentCategory;
    $categoryName      = $hasCategoryFilter ? $currentCategory->name : null;
    $categoryUrl       = $hasCategoryFilter ? url('/ecommerce/' . $currentCategory->name) : null;
    $homeUrl           = route('tenant.ecommerce.index');
@endphp

@if($hasCategoryFilter)
    @section('page_title', $categoryName . ' — Tienda Online')
    @section('meta_description', 'Explora todos los productos de ' . $categoryName)
@endif

@if($hasCategoryFilter)
@section('breadcrumbs')
<ol class="ec-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="{{ $homeUrl }}" itemprop="item"><span itemprop="name">Inicio</span></a>
        <meta itemprop="position" content="1">
    </li>
    <li class="ec-breadcrumb__sep" aria-hidden="true">/</li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span itemprop="name" aria-current="page">{{ $categoryName }}</span>
        <meta itemprop="position" content="2">
    </li>
</ol>
@endsection
@endif

@section('content')
<div class="container-fluid" style="max-width:1400px">

    {{-- ═══ BANNER (solo home sin filtro) ═══ --}}
    @if(!$tagid && !$hasCategoryFilter)
        @include('ecommerce::layouts.partials_ecommerce.home_slider')
    @endif

    <div class="ropa-shop-layout">

        {{-- ═══════════════════════════════════════════════
             SIDEBAR FILTROS (estilo Shein)
             ═══════════════════════════════════════════════ --}}
        <aside class="ropa-sidebar" id="ropa-sidebar">
            <button class="ropa-sidebar__close d-lg-none" id="ropa-sidebar-close" aria-label="Cerrar filtros">&times;</button>

            {{-- Categorías --}}
            @if(isset($categories) && $categories->count())
            <div class="ropa-filter-group">
                <h3 class="ropa-filter-title">Categoría</h3>
                <ul class="ropa-filter-list">
                    <li>
                        <a href="{{ $homeUrl }}" class="ropa-filter-link {{ !$hasCategoryFilter ? 'ropa-filter-link--active' : '' }}">
                            Todos
                        </a>
                    </li>
                    @foreach($categories as $cat)
                    <li>
                        <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($cat->name)) }}"
                           class="ropa-filter-link {{ ($hasCategoryFilter && $categoryName === $cat->name) ? 'ropa-filter-link--active' : '' }}">
                            {{ $cat->name }}
                            <span class="ropa-filter-count">{{ $cat->items_count ?? '' }}</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Rango de precio --}}
            @if(isset($priceRange) && $priceRange)
            <div class="ropa-filter-group">
                <h3 class="ropa-filter-title">Precio</h3>
                <div class="ropa-price-filter">
                    <div class="ropa-price-inputs">
                        <input type="number" id="ropa-min-price" class="ropa-price-input"
                               placeholder="Min" value="{{ request('min_price') }}">
                        <span class="ropa-price-sep">—</span>
                        <input type="number" id="ropa-max-price" class="ropa-price-input"
                               placeholder="Max" value="{{ request('max_price') }}">
                    </div>
                    <button type="button" class="ropa-price-apply" id="ropa-price-apply">Aplicar</button>
                </div>
            </div>
            @endif

            {{-- Ordenar --}}
            <div class="ropa-filter-group">
                <h3 class="ropa-filter-title">Ordenar por</h3>
                <ul class="ropa-filter-list">
                    <li><a href="?sort=newest" class="ropa-filter-link {{ (request('sort','newest')==='newest') ? 'ropa-filter-link--active' : '' }}">Más nuevos</a></li>
                    <li><a href="?sort=price_asc" class="ropa-filter-link {{ request('sort')==='price_asc' ? 'ropa-filter-link--active' : '' }}">Precio: menor a mayor</a></li>
                    <li><a href="?sort=price_desc" class="ropa-filter-link {{ request('sort')==='price_desc' ? 'ropa-filter-link--active' : '' }}">Precio: mayor a menor</a></li>
                    <li><a href="?sort=name_asc" class="ropa-filter-link {{ request('sort')==='name_asc' ? 'ropa-filter-link--active' : '' }}">Nombre A-Z</a></li>
                </ul>
            </div>
        </aside>

        {{-- ═══════════════════════════════════════════════
             CONTENIDO PRINCIPAL (productos)
             ═══════════════════════════════════════════════ --}}
        <main class="ropa-main">

            {{-- Barra superior: título + botón filtros mobile --}}
            <div class="ropa-main__header">
                <h1 class="ropa-main__title">
                    {{ $hasCategoryFilter ? $categoryName : 'Todos los productos' }}
                </h1>
                <button class="ropa-btn-filters d-lg-none" id="ropa-open-filters">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
                    Filtros
                </button>
            </div>

            {{-- Category pills (desktop) --}}
            @if(!$hasCategoryFilter && isset($categories) && $categories->count())
            <div class="ec-category-pills ropa-pills" id="ec-category-pills">
                <button class="ec-cat-pill ec-cat-pill--active ropa-pill" data-category-id="">Todos</button>
                @foreach($categories as $cat)
                <button class="ec-cat-pill ropa-pill" data-category-id="{{ $cat->id }}" data-category-name="{{ $cat->name }}">
                    {{ $cat->name }}
                </button>
                @endforeach
            </div>
            @endif

            {{-- Grid de productos --}}
            <div id="ec-filter-results" class="ec-filter-results">
                @include('ecommerce::layouts.partials_ecommerce.products_grid')
            </div>
        </main>
    </div>
</div>

{{-- Overlay para sidebar mobile --}}
<div class="ropa-sidebar-overlay" id="ropa-sidebar-overlay"></div>

<script>
(function(){
    var sidebar = document.getElementById('ropa-sidebar');
    var overlay = document.getElementById('ropa-sidebar-overlay');
    var open    = document.getElementById('ropa-open-filters');
    var close   = document.getElementById('ropa-sidebar-close');

    function toggle(show){
        if(show){
            sidebar.classList.add('ropa-sidebar--open');
            overlay.classList.add('ropa-sidebar-overlay--visible');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.remove('ropa-sidebar--open');
            overlay.classList.remove('ropa-sidebar-overlay--visible');
            document.body.style.overflow = '';
        }
    }
    if(open) open.addEventListener('click', function(){ toggle(true); });
    if(close) close.addEventListener('click', function(){ toggle(false); });
    if(overlay) overlay.addEventListener('click', function(){ toggle(false); });

    // Filtro de precio
    var applyBtn = document.getElementById('ropa-price-apply');
    if(applyBtn){
        applyBtn.addEventListener('click', function(){
            var min = document.getElementById('ropa-min-price').value;
            var max = document.getElementById('ropa-max-price').value;
            var url = new URL(window.location.href);
            if(min) url.searchParams.set('min_price', min); else url.searchParams.delete('min_price');
            if(max) url.searchParams.set('max_price', max); else url.searchParams.delete('max_price');
            window.location.href = url.toString();
        });
    }
})();
</script>

<style>
/* ═══ THEME ROPA — LAYOUT SHEIN ═══ */

.ropa-shop-layout {
    display: flex;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* ── SIDEBAR ── */
.ropa-sidebar {
    width: 240px;
    flex-shrink: 0;
    position: sticky;
    top: 80px;
    height: fit-content;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}

.ropa-sidebar__close {
    display: none;
    position: absolute;
    top: 12px;
    right: 12px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #374151;
}

.ropa-filter-group {
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #f3f4f6;
}

.ropa-filter-title {
    font-size: 13px;
    font-weight: 700;
    color: hsl(var(--primary-h), var(--primary-s), 15%);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ropa-filter-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ropa-filter-list li {
    margin-bottom: 2px;
}

.ropa-filter-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 8px;
    font-size: 13px;
    color: #4b5563;
    text-decoration: none;
    border-radius: 4px;
    transition: background .15s, color .15s;
}

.ropa-filter-link:hover {
    background: #f9fafb;
    color: hsl(var(--primary-h), var(--primary-s), 15%);
    text-decoration: none;
}

.ropa-filter-link--active {
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    color: #fff !important;
    font-weight: 600;
}

.ropa-filter-count {
    font-size: 11px;
    color: #9ca3af;
}

.ropa-filter-link--active .ropa-filter-count {
    color: rgba(255,255,255,.7);
}

/* Precio */
.ropa-price-inputs {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-bottom: .5rem;
}
.ropa-price-input {
    width: 80px;
    padding: 5px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 13px;
    text-align: center;
}
.ropa-price-sep { color: #9ca3af; }
.ropa-price-apply {
    width: 100%;
    padding: 6px;
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.ropa-price-apply:hover { background: hsl(var(--primary-h), var(--primary-s), calc(var(--primary-l) - 10%)); }

/* ── MAIN ── */
.ropa-main {
    flex: 1;
    min-width: 0;
}

.ropa-main__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.ropa-main__title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 24px;
    font-weight: 500;
    color: hsl(var(--primary-h), var(--primary-s), 15%);
    margin: 0;
}

.ropa-btn-filters {
    display: none;
    align-items: center;
    gap: .4rem;
    padding: 8px 14px;
    border: 1px solid #d1d5db;
    background: #fff;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: #374151;
}

/* Pills */
.ropa-pill {
    border-radius: 0 !important;
    text-transform: uppercase !important;
    letter-spacing: .06em !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    border-color: #e5e7eb !important;
}

/* ── OVERLAY MOBILE ── */
.ropa-sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 999;
}
.ropa-sidebar-overlay--visible {
    display: block;
}

/* ── RESPONSIVE ── */
@media (max-width: 991px) {
    .ropa-sidebar {
        position: fixed;
        top: 0;
        left: -280px;
        width: 270px;
        height: 100vh;
        max-height: 100vh;
        background: #fff;
        z-index: 1000;
        padding: 3rem 1.25rem 1.5rem;
        transition: left .25s ease;
        box-shadow: 2px 0 12px rgba(0,0,0,.1);
    }
    .ropa-sidebar--open {
        left: 0;
    }
    .ropa-sidebar__close {
        display: block;
    }
    .ropa-btn-filters {
        display: flex;
    }
    .ropa-shop-layout {
        flex-direction: column;
    }
}
</style>
@endsection
