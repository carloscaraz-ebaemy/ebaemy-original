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

    <div class="mp-filters">
        <a href="{{ route('marketplace.index', array_filter(['q' => $q, 'sort' => $sort])) }}"
           class="mp-chip {{ !$category ? 'mp-chip--active' : '' }}">Todas</a>

        @foreach($categories as $cat)
            <a href="{{ route('marketplace.index', array_filter(['q' => $q, 'category' => $cat, 'sort' => $sort])) }}"
               class="mp-chip {{ $category === $cat ? 'mp-chip--active' : '' }}">{{ $cat }}</a>
        @endforeach

        <form method="GET" action="{{ route('marketplace.index') }}" class="mp-sort-form" style="margin-left:auto">
            @if($q) <input type="hidden" name="q" value="{{ $q }}"> @endif
            @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
            <select name="sort" class="mp-sort" onchange="this.form.submit()">
                <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevancia</option>
                <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Precio: menor a mayor</option>
                <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Más recientes</option>
            </select>
        </form>
    </div>

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
                        <div class="mp-card-shop">{{ $listing->tenant_fqdn }}</div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mp-pag">
            {{ $listings->links() }}
        </div>
    @endif
@endsection
