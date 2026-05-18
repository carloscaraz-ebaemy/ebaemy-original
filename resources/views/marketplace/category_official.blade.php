@extends('marketplace.layout')

@php
    // Vista de categoría oficial (Fase D). Filtra por FK + descendencia.
    $categoryUrl = url('/marketplace/c/' . $category->full_slug);
    $indexUrl    = route('marketplace.index');

    $bcItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => $indexUrl],
    ];
    $i = 2;
    foreach ($breadcrumb as $node) {
        $bcItems[] = [
            '@type'    => 'ListItem',
            'position' => $i++,
            'name'     => $node->name,
            'item'     => url('/marketplace/c/' . $node->full_slug),
        ];
    }
    $bc = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $bcItems];

    $collectionPage = [
        '@context'      => 'https://schema.org',
        '@type'         => 'CollectionPage',
        'name'          => $category->name . ' — Marketplace ebaemy',
        'url'           => $categoryUrl,
        'numberOfItems' => (int) $total,
    ];

    $baseQs = array_filter([
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
    ], fn($v) => $v !== null && $v !== '');

    $hasFilters = $priceMin !== null || $priceMax !== null;
    $catDesc = $category->description ?: ('Explora productos de la categoría ' . $category->name . ' en ebaemy.');

    // Tema visual determinstico por categora: del slug derivamos un hue
    // HSL nico para que cada categora tenga su propia "personalidad
    // visual" sin necesidad de columnas BD ni assets por categora.
    // Misma categora siempre genera el mismo color.
    $catHue       = abs(crc32($category->slug)) % 360;
    $catHueAlt    = ($catHue + 38) % 360;
    $catGradient  = "linear-gradient(135deg, hsl({$catHue}, 62%, 38%) 0%, hsl({$catHue}, 55%, 28%) 45%, hsl({$catHueAlt}, 50%, 32%) 100%)";
    // Pattern decorativo: crculos sutiles posicionados, color en HSL con
    // baja opacidad. Refuerza el tema sin necesidad de imgenes.
    $catPattern   = "radial-gradient(circle at 85% 20%, hsla({$catHueAlt}, 85%, 75%, .22) 0%, transparent 40%), "
                  . "radial-gradient(circle at 15% 85%, hsla({$catHue}, 85%, 65%, .18) 0%, transparent 45%)";
@endphp

@section('title', $category->name . ' — Marketplace ebaemy')
@section('description', \Illuminate\Support\Str::limit($catDesc, 160))
@section('keywords', $category->name . ', marketplace, tiendas, ebaemy, Perú')
@section('og_title', $category->name . ' en Marketplace ebaemy')
@section('og_description', $total . ' productos de ' . $category->name . ' en ebaemy.com.')
@section('canonical', $categoryUrl)

@push('styles')
<script type="application/ld+json">
{!! json_encode($bc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($collectionPage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<style>
/* ───── Icono decorativo del hero (sale del campo category.icon) ───── */
.mp-cat-hero { position: relative; overflow: hidden; }
.mp-cat-hero__icon {
    position: absolute;
    right: -10px;
    top: 50%;
    transform: translateY(-50%) rotate(-12deg);
    font-size: clamp(160px, 26vw, 280px);
    line-height: 1;
    opacity: .14;
    pointer-events: none;
    user-select: none;
    z-index: 1;
}
@media (max-width: 640px) {
    .mp-cat-hero__icon {
        right: -30px;
        font-size: 200px;
        opacity: .18;
    }
}

/* ───── Subcategoras como carrusel horizontal (scroll-snap) ───── */
.mp-subcats-rail {
    display: flex;
    gap: 8px;
    margin: 16px 0 24px;
    padding: 4px 16px 12px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
    scroll-padding-left: 16px;
    /* Scrollbar discreta — el rail se nota como scrollable sin ser invasivo */
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    /* Margen negativo para que el rail llegue al borde de la pantalla */
    margin-left: calc(-1 * clamp(16px, 4vw, 32px));
    margin-right: calc(-1 * clamp(16px, 4vw, 32px));
}
.mp-subcats-rail::-webkit-scrollbar { height: 4px; }
.mp-subcats-rail::-webkit-scrollbar-track { background: transparent; }
.mp-subcats-rail::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

.mp-subcat-chip {
    flex: 0 0 auto;
    scroll-snap-align: start;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    background: #fff;
    border: 1.5px solid var(--mp-border, #e5e7eb);
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    color: var(--mp-ink, #111827);
    text-decoration: none;
    white-space: nowrap;
    transition: all .15s ease;
    /* Cada chip tiene una sombra de color muy sutil derivada de la
       categora padre + offset por ndice. Solo se nota al hover. */
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.mp-subcat-chip:hover,
.mp-subcat-chip:focus {
    border-color: hsl(var(--chip-hue, 173), 55%, 45%);
    color: hsl(var(--chip-hue, 173), 55%, 32%);
    background: hsl(var(--chip-hue, 173), 80%, 97%);
    box-shadow: 0 4px 12px -4px hsla(var(--chip-hue, 173), 55%, 45%, .25);
    transform: translateY(-1px);
}
.mp-subcat-chip__icon { font-size: 15px; line-height: 1; }
.mp-subcat-chip__name { font-weight: 600; }
.mp-subcat-chip__count {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 500;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 999px;
    margin-left: 2px;
}
.mp-subcat-chip:hover .mp-subcat-chip__count {
    background: hsla(var(--chip-hue, 173), 55%, 90%, 1);
    color: hsl(var(--chip-hue, 173), 55%, 32%);
}
</style>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    @foreach($breadcrumb as $node)
        <span class="sep">›</span>
        @if($loop->last)
            <span style="color:var(--mp-ink);font-weight:500">{{ $node->name }}</span>
        @else
            <a href="{{ url('/marketplace/c/' . $node->full_slug) }}">{{ $node->name }}</a>
        @endif
    @endforeach
</nav>

{{-- Hero temtico: gradient nico por categora + icono decorativo.
     El color sale del slug (hash determinstico) para que cada categora
     tenga su propia paleta sin requerir BD ni assets por categora. --}}
<section class="mp-hero mp-cat-hero" style="
    background: {{ $catPattern }}, {{ $catGradient }};
    min-height: 180px;
    padding: clamp(24px, 4vw, 48px) clamp(20px, 4vw, 56px);
    position: relative;
    overflow: hidden;
">
    @if($category->icon)
        <div class="mp-cat-hero__icon" aria-hidden="true">{{ $category->icon }}</div>
    @endif
    <div style="position:relative; z-index:2">
        <span class="mp-hero-eyebrow">{{ $category->icon ? $category->icon . ' ' : '' }}Categoría oficial</span>
        <h1 style="font-size:clamp(24px, 3.5vw, 36px)">{{ $category->name }}</h1>
        <p>{{ $total }} producto{{ $total === 1 ? '' : 's' }} disponibles — tiendas verificadas en ebaemy.</p>
    </div>
</section>

@if($subcategories->isNotEmpty())
    {{-- Subcategoras como carrusel horizontal (scroll-snap).
         En vez de un grid wrap que ocupa muchos renglones en mobile,
         scroll horizontal nativo con padding inicial y final. Cada chip
         es un punto de snap, da sensacin de "carrusel de lnea". --}}
    <div class="mp-subcats-rail" role="region" aria-label="Subcategoras">
        @foreach($subcategories as $sub)
            <a href="{{ url('/marketplace/c/' . $sub->full_slug) }}" class="mp-subcat-chip"
               style="--chip-hue:{{ ($catHue + ($loop->index * 12)) % 360 }}">
                @if($sub->icon)<span class="mp-subcat-chip__icon">{{ $sub->icon }}</span>@endif
                <span class="mp-subcat-chip__name">{{ $sub->name }}</span>
                @if($sub->listings_count_cache)<span class="mp-subcat-chip__count">{{ $sub->listings_count_cache }}</span>@endif
            </a>
        @endforeach
    </div>
@endif

<div class="mp-list-layout">

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
                <strong>{{ $total }}</strong> producto{{ $total === 1 ? '' : 's' }} en <strong>{{ $category->name }}</strong>
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
                <p>Explora las subcategorías arriba o vuelve al <a href="{{ route('marketplace.index') }}" style="color:var(--mp-primary-dark);font-weight:600">marketplace completo</a>.</p>
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
