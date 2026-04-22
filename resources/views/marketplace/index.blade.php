@extends('marketplace.layout')

@section('title', ($q ? 'Buscar "'.$q.'" — ' : '') . 'Marketplace ebaemy')

@section('content')
    <section class="mp-hero">
        <h1>@if($q) Resultados para “{{ $q }}” @else Todas las tiendas, un solo lugar @endif</h1>
        <p>
            @if($q)
                {{ $listings->total() }} producto(s) encontrados.
            @else
                Explora productos de tiendas que venden con ebaemy. Compra, consulta o solicita envío directo.
            @endif
        </p>
    </section>

    @php
        // Query base conservada entre chips de categoría
        $baseQs = array_filter([
            'q'         => $q,
            'sort'      => $sort,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
        ], fn($v) => $v !== null && $v !== '');
    @endphp

    <div class="mp-filters">
        <a href="{{ route('marketplace.index', $baseQs) }}"
           class="mp-chip {{ !$category ? 'mp-chip--active' : '' }}">Todas</a>

        @foreach($categories as $cat)
            <a href="{{ route('marketplace.index', array_merge($baseQs, ['category' => $cat])) }}"
               class="mp-chip {{ $category === $cat ? 'mp-chip--active' : '' }}">{{ $cat }}</a>
        @endforeach

        <form method="GET" action="{{ route('marketplace.index') }}" class="mp-sort-form" style="margin-left:auto">
            @if($q) <input type="hidden" name="q" value="{{ $q }}"> @endif
            @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
            @if($priceMin !== null) <input type="hidden" name="price_min" value="{{ $priceMin }}"> @endif
            @if($priceMax !== null) <input type="hidden" name="price_max" value="{{ $priceMax }}"> @endif
            <select name="sort" class="mp-sort" onchange="this.form.submit()">
                <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevancia</option>
                <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Precio: menor a mayor</option>
                <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Más recientes</option>
            </select>
        </form>
    </div>

    <form method="GET" action="{{ route('marketplace.index') }}" class="mp-price-form"
          style="display:flex;gap:8px;align-items:center;margin:10px 0 18px;flex-wrap:wrap">
        @if($q) <input type="hidden" name="q" value="{{ $q }}"> @endif
        @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
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
        @if($priceMin !== null || $priceMax !== null)
            <a href="{{ route('marketplace.index', array_filter(['q' => $q, 'category' => $category, 'sort' => $sort])) }}"
               style="font-size:13px;color:#6366f1;text-decoration:none">Limpiar</a>
        @endif
    </form>

    @if($listings->isEmpty())
        <div class="mp-empty">
            <h3>No hay productos publicados todavía</h3>
            <p>Cuando las tiendas ebaemy publiquen sus productos, aparecerán aquí.</p>
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
                            @if($listing->tenant_verified)
                                <span class="mp-verified-badge" title="Tienda verificada por ebaemy">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                                    Verificada
                                </span>
                            @endif
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
