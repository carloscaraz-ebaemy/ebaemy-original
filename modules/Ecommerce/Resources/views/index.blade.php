@extends('ecommerce::layouts.master')

@php
    $tagid           = Request::segment(3);
    $hasCategoryFilter = isset($currentCategory) && $currentCategory;
    $categoryName    = $hasCategoryFilter ? $currentCategory->name : null;
    $categoryUrl     = $hasCategoryFilter ? url('/ecommerce/' . $currentCategory->name) : null;
    $homeUrl         = route('tenant.ecommerce.index');
@endphp

{{-- ── SEO: título y meta para páginas de categoría ─────────────── --}}
@if($hasCategoryFilter)
    @section('page_title', $categoryName . ' — Tienda Online')
    @section('meta_description', 'Explora todos los productos de ' . $categoryName . ' en nuestra tienda.')
    @section('canonical_url', $categoryUrl)
@endif

{{-- ── Schema.org BreadcrumbList ────────────────────────────────── --}}
@if($hasCategoryFilter)
@section('breadcrumb_json')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Inicio",
            "item": "{{ $homeUrl }}"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "{{ $categoryName }}",
            "item": "{{ $categoryUrl }}"
        }
    ]
}
</script>
@endsection

{{-- ── Breadcrumbs visibles ──────────────────────────────────────── --}}
@section('breadcrumbs')
<ol class="ec-breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="{{ $homeUrl }}" itemprop="item">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span itemprop="name">Inicio</span>
        </a>
        <meta itemprop="position" content="1">
    </li>
    <li class="ec-breadcrumb__sep" aria-hidden="true">/</li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span itemprop="name" aria-current="page">{{ $categoryName }}</span>
        <meta itemprop="position" content="2">
        <meta itemprop="item" content="{{ $categoryUrl }}">
    </li>
</ol>
@endsection
@endif

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12 ecommerce-view" style="padding-top: 0">

            {{-- ── SLIDER / BANNER ──────────────────────────────────── --}}
            @if(!$tagid && !$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.home_slider')
            @endif

            {{-- Categorías: se muestran como pills de filtro dentro de la sección de productos --}}

            {{-- ── PAQUETES / BUNDLES ───────────────────────────────── --}}
            @if(!$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.bundles')
            @endif

            {{-- ── FLASH SALE ───────────────────────────────────────── --}}
            @if(!$hasCategoryFilter)
                @include('ecommerce::layouts.partials_ecommerce.flash_sale')
            @endif

            {{-- ── PRODUCTOS ────────────────────────────────────────── --}}
            <section class="ec-home-section" aria-label="{{ $hasCategoryFilter ? 'Productos de ' . $categoryName : 'Catálogo de productos' }}">
                <div class="ec-section-header">
                    <h2 class="ec-section-title">
                        @if($hasCategoryFilter)
                            {{ $categoryName }}
                        @elseif($tagid)
                            Productos de la categoría
                        @else
                            Explora nuestros productos
                        @endif
                    </h2>
                    @if($hasCategoryFilter)
                        <a href="{{ $homeUrl }}" class="ec-section-link">← Ver todos</a>
                    @else
                        <a href="{{ $homeUrl }}" class="ec-section-link">Ver todos</a>
                    @endif
                </div>

                {{-- ── Sticky zone: filtros + pills ───────────── --}}
                <div class="ec-filter-sticky-zone">
                    {{-- Filtros y ordenación --}}
                    @include('ecommerce::layouts.partials_ecommerce.filters')

                    {{-- Category pills --}}
                    @if(!$hasCategoryFilter && isset($categories) && $categories->count())
                    <div class="ec-category-pills" id="ec-category-pills">
                        <button class="ec-cat-pill ec-cat-pill--active" data-category-id="">Todos</button>
                        @foreach($categories as $cat)
                        <button class="ec-cat-pill" data-category-id="{{ $cat->id }}" data-category-name="{{ $cat->name }}">
                            {{ $cat->name }}
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>{{-- /ec-filter-sticky-zone --}}

                {{-- AJAX products wrapper --}}
                <div id="ec-filter-results" class="ec-filter-results">
                    @include('ecommerce::layouts.partials_ecommerce.products_grid')
                </div>
            </section>

            {{-- ── Tracking: ViewCategory / Search ──────────────────── --}}
            @if($hasCategoryFilter)
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.EcommerceTracker) {
                    EcommerceTracker.viewCategory({
                        id:       '{{ $currentCategory->id }}',
                        category: '{{ addslashes($currentCategory->name) }}'
                    });
                }
            });
            </script>
            @endif

            @if(request('q'))
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.EcommerceTracker) {
                    EcommerceTracker.search({ query: '{{ addslashes(request("q")) }}' });
                }
            });
            </script>
            @endif

            {{-- ── OFERTAS / SPOTS ──────────────────────────────────── --}}
            @if(!$hasCategoryFilter)
            @php
                $spotsHasImages = isset($spots) && $spots->whereNotNull('image_url')->where('image_url', '!=', '')->count() > 0;
            @endphp
            @if($spotsHasImages)
            <section class="ec-home-section ec-home-section--offers" aria-label="Ofertas y promociones">
                <div class="ec-section-header">
                    <h2 class="ec-section-title">Ofertas especiales</h2>
                </div>
                @include('ecommerce::layouts.partials_ecommerce.offers')
            </section>
            @endif
            @endif

        </div>
    </div>
</div>
@endsection
