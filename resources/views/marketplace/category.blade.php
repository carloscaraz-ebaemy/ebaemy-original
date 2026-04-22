@extends('marketplace.layout')

@section('title', $category . ' — Marketplace ebaemy')
@section('description', 'Explora productos de la categoría ' . $category . ' de tiendas peruanas en ebaemy. ' . $total . ' producto(s) disponibles.')
@section('keywords', $category . ', marketplace, tiendas, ebaemy, Perú')
@section('og_title', $category . ' en Marketplace ebaemy')
@section('og_description', $total . ' productos de la categoría ' . $category . ' en ebaemy.com.')
@section('canonical', route('marketplace.category', $categorySlug))

@push('styles')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Marketplace", "item": @json(route('marketplace.index')) },
        { "@type": "ListItem", "position": 2, "name": @json($category) }
    ]
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": @json($category . ' — Marketplace ebaemy'),
    "url": @json(route('marketplace.category', $categorySlug)),
    "numberOfItems": {{ (int) $total }}
}
</script>
@endpush

@section('content')
    <nav style="font-size:13px;color:#64748b;margin-bottom:10px">
        <a href="{{ route('marketplace.index') }}" style="color:#6366f1;text-decoration:none">Marketplace</a>
        <span style="margin:0 6px;color:#9ca3af">›</span>
        <span>{{ $category }}</span>
    </nav>

    <section class="mp-hero">
        <h1>{{ $category }}</h1>
        <p>{{ $total }} producto(s) en esta categoría — vendidos por tiendas peruanas en ebaemy.</p>
    </section>

    <form method="GET" action="{{ route('marketplace.category', $categorySlug) }}" class="mp-price-form"
          style="display:flex;gap:8px;align-items:center;margin:10px 0 18px;flex-wrap:wrap">
        @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
        <span style="font-size:13px;color:#64748b">Precio:</span>
        <input type="number" name="price_min" min="0" step="0.01" placeholder="Desde S/"
               value="{{ $priceMin !== null ? $priceMin : '' }}"
               style="width:120px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
        <input type="number" name="price_max" min="0" step="0.01" placeholder="Hasta S/"
               value="{{ $priceMax !== null ? $priceMax : '' }}"
               style="width:120px;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
        <button type="submit"
                style="background:#111;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            Aplicar
        </button>

        <select name="sort" onchange="this.form.submit()"
                style="margin-left:auto;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevancia</option>
            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Precio: menor a mayor</option>
            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Más recientes</option>
        </select>
    </form>

    @if($listings->isEmpty())
        <div class="mp-empty">
            <h3>Sin productos en esta categoría</h3>
            <p>Ajusta los filtros o vuelve al <a href="{{ route('marketplace.index') }}">marketplace completo</a>.</p>
        </div>
    @else
        <div class="mp-grid">
            @foreach($listings as $listing)
                <a href="{{ route('marketplace.item', $listing->slug) }}" class="mp-card">
                    <div class="mp-card-img">
                        @if($listing->image_url)
                            <img src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy">
                        @else
                            <div style="display:flex;height:100%;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">Sin imagen</div>
                        @endif
                    </div>
                    <div class="mp-card-body">
                        <h3 class="mp-card-title">{{ $listing->title }}</h3>
                        <div class="mp-card-price">S/ {{ number_format($listing->display_price, 2) }}</div>
                        <div class="mp-card-shop">
                            <span title="Vendido por {{ $listing->seller_display }}">
                                🏪 {{ \Illuminate\Support\Str::limit($listing->seller_display, 24) }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mp-pag">
            {{ $listings->links() }}
        </div>
    @endif
@endsection
