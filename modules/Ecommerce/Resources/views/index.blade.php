@extends('ecommerce::layouts.master')

@php
    $tagid           = Request::segment(3);
    $hasCategoryFilter = isset($currentCategory) && $currentCategory;
    $categoryName    = $hasCategoryFilter ? $currentCategory->name : null;
    $categoryUrl     = $hasCategoryFilter ? route('tenant.ecommerce.category', ['category' => $currentCategory->id]) : null;
    $homeUrl         = route('tenant.ecommerce.index');

    // ── Canonical de las paginas paginadas ───────────────────────────────
    // A partir de la pagina 2, la canonical apunta a ESA pagina, no a la
    // primera. Antes todas las paginas de la serie se canonicalizaban a la
    // portada mientras a la vez emitian rel=prev/next: dos senales que se
    // contradicen —«esto es una serie» y «todas son la misma pagina»—. Y la
    // consecuencia practica es que los productos que solo aparecen a partir
    // de la pagina 2 pierden la unica ruta de descubrimiento que tienen desde
    // el listado.
    //
    // Los demas parametros (orden, filtros, busqueda) SI siguen consolidando
    // a la URL limpia: ahi no hay contenido nuevo que descubrir, solo el mismo
    // catalogo barajado, y multiplicar URLs no aporta nada.
    // ── Politica de indexacion de busquedas y filtros ────────────────────
    // La busqueda interna y las combinaciones de filtros no se indexan: son
    // el mismo catalogo barajado, se multiplican sin limite y no hay ninguna
    // consulta de Google que deba aterrizar ahi. Las categorias SI siguen
    // siendo indexables: esas si tienen contenido y utilidad propios.
    //
    // Se hace con `noindex` en la pagina y NO con robots.txt: si se bloquea el
    // rastreo, el buscador nunca llega a leer la directiva y la URL puede
    // acabar indexada igual, sin titulo ni descripcion.
    //
    // `follow` a proposito: que no se indexe la pagina de resultados no quita
    // que sus enlaces lleven a fichas que si interesan.
    $parametrosNoIndexables = ['q', 'sort', 'available', 'min_price', 'max_price', 'mp_category'];
    $esBusquedaOFiltro = collect($parametrosNoIndexables)
        ->contains(fn ($p) => filled(request($p)));

    $paginaActual = (int) request('page', 1);
    $urlBase      = $categoryUrl ?: $homeUrl;
    $canonicalUrl = $paginaActual > 1
        ? $urlBase . '?page=' . $paginaActual
        : $urlBase;

    // og:image dinámica por categoría: usamos la imagen del primer producto
    // visible. Fallback al $share_image global del layout si no hay productos
    // o no tienen imagen.
    $categoryOgImage = null;
    if ($hasCategoryFilter && isset($dataPaginate) && $dataPaginate->isNotEmpty()) {
        foreach ($dataPaginate as $_first) {
            if (!empty($_first->image)) {
                $categoryOgImage = asset('storage/uploads/items/' . $_first->image);
                break;
            }
        }
    }

    // rel prev/next: el layout master expone @yield('prev_page')/'next_page'
    // y el SEO de paginación se activa con @hasSection. Sólo seteamos cuando
    // hay página previa/siguiente real.
    $prevPageUrl = (isset($dataPaginate) && method_exists($dataPaginate, 'previousPageUrl'))
        ? $dataPaginate->previousPageUrl() : null;

    // Desde la pagina 2, el `prev` apunta a la URL limpia y no a `?page=1`:
    // son la misma pagina con dos direcciones, y la limpia es la canonica.
    if ($prevPageUrl && $paginaActual === 2) {
        $prevPageUrl = $urlBase;
    }
    $nextPageUrl = (isset($dataPaginate) && method_exists($dataPaginate, 'nextPageUrl'))
        ? $dataPaginate->nextPageUrl() : null;
@endphp

{{-- ── SEO: título y meta para páginas de categoría ─────────────── --}}
@if($hasCategoryFilter)
    @section('page_title', $categoryName . ' — Tienda Online')
    @section('meta_description', 'Explora todos los productos de ' . $categoryName . ' en nuestra tienda.')
    @section('meta_keywords', $categoryName . ', tienda online, comprar ' . $categoryName . ', ' . ($company->name ?? ''))
    @section('canonical_url', $canonicalUrl)
    @if($categoryOgImage)
        @section('og_image', $categoryOgImage)
    @endif
@else
    @section('canonical_url', $canonicalUrl)
@endif

{{-- ── SEO: busquedas y filtros fuera del indice ───────────────── --}}
@if($esBusquedaOFiltro)
    @section('meta_robots', 'noindex, follow')
@endif

{{-- ── SEO: rel prev/next para paginación ──────────────────────── --}}
@if($prevPageUrl)
    @section('prev_page', $prevPageUrl)
@endif
@if($nextPageUrl)
    @section('next_page', $nextPageUrl)
@endif

{{-- ── Schema.org CollectionPage para listados de categoría ────── --}}
@if($hasCategoryFilter && isset($dataPaginate) && $dataPaginate->isNotEmpty())
@push('head_extra')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": "{{ $categoryName }}",
    "url": "{{ $categoryUrl }}",
    "isPartOf": { "@type": "WebSite", "url": "{{ $homeUrl }}" },
    "mainEntity": {
        "@type": "ItemList",
        "numberOfItems": {{ $dataPaginate->total() }},
        "itemListElement": [
            @foreach($dataPaginate->take(20) as $i => $_p)
            {
                "@type": "ListItem",
                "position": {{ $i + 1 }},
                "url": "{{ route('tenant.ecommerce.item', ['slug' => $_p->slug ?? $_p->id]) }}",
                "name": @json($_p->description ?? $_p->name ?? '')
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
}
</script>
@endpush
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

@php
    // Se resuelven una vez por render; el foreach de abajo solo incluye.
    $homeSectionsMain = \App\Services\EcommerceHomeSections::forZone(
        \App\Services\EcommerceHomeSections::ZONE_MAIN, (bool) $hasCategoryFilter, (bool) $tagid
    );
    $homeSectionsWide = \App\Services\EcommerceHomeSections::forZone(
        \App\Services\EcommerceHomeSections::ZONE_WIDE, (bool) $hasCategoryFilter, (bool) $tagid
    );
@endphp

@section('content')
{{-- `ec-shop` ensancha el contenedor del catálogo a 1400px en pantallas
     grandes. Con el .container de Porto (1140px) una pantalla de 1920
     desperdiciaba 780px de ancho y seguía mostrando 4 productos por fila.
     Solo afecta a esta vista; el resto del ecommerce sigue con su ancho. --}}
<div class="container ec-shop">
    <div class="row">
        <div class="col-lg-12 ecommerce-view" style="padding-top: 0">

            {{-- ── H1 SEO (oculto visualmente si hay slider, visible si no) ── --}}
            @php
                $storeName = $company->trade_name ?? $company->name ?? 'Tienda Online';
                $storeDesc = $seo->seo_description ?? 'Descubre nuestros productos al mejor precio.';
            @endphp
            @if(!$tagid && !$hasCategoryFilter)
                <h1 class="ec-seo-h1">{{ $storeName }}</h1>
                <p class="ec-seo-intro">{{ $storeDesc }}</p>
            @elseif($hasCategoryFilter)
                <h1 class="ec-seo-h1">{{ $categoryName }} — {{ $storeName }}</h1>
            @endif

            {{-- ── SECCIONES DEL HOME ────────────────────────────────
                 El orden y el encendido salen de
                 App\Services\EcommerceHomeSections (preferences.home_sections).
                 Sin configuración guardada el orden es el histórico, así que
                 un tenant que no toque nada ve exactamente el mismo home.

                 Las categorías no tienen sección propia: se muestran como
                 pills de filtro dentro del catálogo. --}}
            @foreach($homeSectionsMain as $__section)
                @include($__section['partial'])
            @endforeach
        </div>

        @foreach($homeSectionsWide as $__section)
            @include($__section['partial'])
        @endforeach
    </div>
</div>

{{-- El H1 y la bajada ya no llevan estilos aquí: vivían en un <style>
     inline, que por ir después de las hojas ganaba siempre y dejaba la
     jerarquía de la portada fuera del alcance del CSS del tema. Ahora
     están en styles_ecommerce.css, bloque «LISTADO DE TIENDA v4». --}}

@endsection
