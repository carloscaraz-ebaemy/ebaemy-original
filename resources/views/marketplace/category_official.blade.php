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

{{-- Hero compacto de categoría oficial --}}
<section class="mp-hero" style="min-height:180px;padding:clamp(24px, 4vw, 48px) clamp(20px, 4vw, 56px)">
    <div>
        <span class="mp-hero-eyebrow">{{ $category->icon ? $category->icon . ' ' : '' }}Categoría oficial</span>
        <h1 style="font-size:clamp(24px, 3.5vw, 36px)">{{ $category->name }}</h1>
        <p>{{ $total }} producto{{ $total === 1 ? '' : 's' }} disponibles — tiendas verificadas en ebaemy.</p>
    </div>
</section>

@if($subcategories->isNotEmpty())
    <div class="mp-subcats" style="display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 24px">
        @foreach($subcategories as $sub)
            <a href="{{ url('/marketplace/c/' . $sub->full_slug) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#fff;border:1px solid var(--mp-border, #e5e7eb);border-radius:999px;font-size:13px;color:var(--mp-ink, #111827);text-decoration:none;transition:all .15s"
               onmouseover="this.style.borderColor='var(--mp-primary, #0f8a82)';this.style.color='var(--mp-primary-dark, #0c6b65)'"
               onmouseout="this.style.borderColor='var(--mp-border, #e5e7eb)';this.style.color='var(--mp-ink, #111827)'">
                @if($sub->icon)<span>{{ $sub->icon }}</span>@endif
                <span>{{ $sub->name }}</span>
                @if($sub->listings_count_cache)<span style="color:#9ca3af;font-size:11px">({{ $sub->listings_count_cache }})</span>@endif
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
                    <a href="{{ route('marketplace.item', $listing->slug) }}" class="mp-card">
                        <div class="mp-card-img">
                            @if($listing->image_url)
                                <img src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy">
                            @else
                                <div class="mp-card-img-empty">Sin imagen</div>
                            @endif
                            <div class="mp-card-badges">
                                @if(!empty($listing->tenant_verified))
                                    <span class="mp-badge mp-badge--verified">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                                        Verificado
                                    </span>
                                @endif
                            </div>
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
                                <span class="mp-card-price">S/ {{ number_format($listing->display_price, 2) }}</span>
                            </div>
                            <div class="mp-card-shop">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                                <span class="mp-card-shop-name">{{ \Illuminate\Support\Str::limit($listing->seller_display, 24) }}</span>
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
