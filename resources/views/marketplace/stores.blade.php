@extends('marketplace.layout')

@php
    // Directorio de tiendas (MarketplaceController@stores). Responde a la
    // busqueda «ebaemy tiendas», que antes no tenia ninguna pagina propia.
    $storesUrl    = route('marketplace.stores');
    $totalProducts = $stores->sum('products');

    $bc = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => route('marketplace.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tiendas'],
        ],
    ];
    $itemList = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Tiendas de ebaemy',
        'url'             => $storesUrl,
        'numberOfItems'   => $stores->count(),
        'itemListElement' => $stores->values()->map(fn ($s, $i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'item'     => array_filter([
                '@type' => 'Store',
                'name'  => $s->name,
                'url'   => route('marketplace.tenant', $s->subdomain),
                'image' => $s->logo ?: ($s->photos[0]['src'] ?? null),
                'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'PE'],
            ]),
        ])->all(),
    ];
    $metaDesc = 'Conoce las ' . $stores->count() . ' tiendas peruanas que venden en ebaemy: ' . $totalProducts
              . ' productos de empresas con RUC validado, boleta o factura y envíos a todo el Perú.';
@endphp

@section('title', 'Tiendas de ebaemy — tiendas peruanas verificadas | Marketplace ebaemy')
@section('description', $metaDesc)
@section('keywords', 'ebaemy tiendas, tiendas ebaemy, tiendas online peru, tiendas verificadas, marketplace peru, ebaemy')
@section('og_title', 'Tiendas de ebaemy')
@section('og_description', $metaDesc)
@section('canonical', $storesUrl)

@push('styles')
<script type="application/ld+json">
{!! json_encode($bc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<style>
.mp-stores-head { margin: 4px 0 22px; }
.mp-stores-head h1 { font-size: clamp(24px, 3.2vw, 32px); font-weight: 800; letter-spacing: -.02em; margin: 0 0 6px; color: var(--mp-ink, #0f172a); }
.mp-stores-head p { margin: 0; color: var(--mp-muted, #64748b); font-size: 15px; max-width: 720px; }
.mp-stores-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.mp-stores-stat { display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid var(--mp-line, #e5e7eb); border-radius: 999px; padding: 6px 12px; font-size: 13px; color: var(--mp-ink, #0f172a); }
.mp-stores-stat b { font-weight: 800; }
.mp-stores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 16px; margin-bottom: 32px; }
.mp-store { background: #fff; border: 1px solid var(--mp-line, #e5e7eb); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s, border-color .15s; }
.mp-store:hover { box-shadow: 0 10px 28px rgba(15,23,42,.10); transform: translateY(-2px); border-color: #cbd5e1; text-decoration: none; color: inherit; }
.mp-store:focus-visible { outline: 3px solid var(--mp-primary, #0f8a82); outline-offset: 2px; }
/* Cuatro cuadrados iguales: la foto entera (contain, nunca recortada) sobre blanco. */
.mp-store__photos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px; background: #f1f5f9; }
.mp-store__photos > * { aspect-ratio: 1 / 1; width: 100%; height: auto; min-width: 0; background: #fff; display: block; }
.mp-store__photos img { object-fit: contain; }
.mp-store__body { padding: 14px 16px 16px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
.mp-store__who { display: flex; align-items: center; gap: 12px; min-width: 0; }
.mp-store__logo { width: 48px; height: 48px; border-radius: 12px; border: 1px solid var(--mp-line, #e5e7eb); background: #fff; object-fit: contain; flex: none; }
.mp-store__logo--txt { display: grid; place-items: center; font-weight: 800; font-size: 20px; color: #fff; background: linear-gradient(135deg, var(--mp-primary, #0f8a82), #22c3b6); border: 0; }
.mp-store__name { font-size: 16px; font-weight: 700; margin: 0; line-height: 1.25; color: var(--mp-ink, #0f172a); overflow-wrap: anywhere; }
.mp-store__meta { font-size: 12.5px; color: var(--mp-muted, #64748b); margin-top: 2px; display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: center; }
.mp-store__ok { color: #047857; font-weight: 600; }
.mp-store__cats { display: flex; flex-wrap: wrap; gap: 6px; }
.mp-store__cat { font-size: 12px; background: #f1f5f9; color: #334155; border-radius: 999px; padding: 3px 10px; }
.mp-store__go { margin-top: auto; font-size: 13.5px; font-weight: 700; color: var(--mp-primary, #0f8a82); }
.mp-stores-sell { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; background: linear-gradient(135deg, #0a6f68, #1fb1a6); color: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 40px; }
.mp-stores-sell h2 { margin: 0 0 4px; font-size: 18px; color: #fff; }
.mp-stores-sell p { margin: 0; font-size: 14px; opacity: .9; }
.mp-stores-sell a { background: #fbbf24; color: #1f2937; font-weight: 700; border-radius: 10px; padding: 11px 18px; text-decoration: none; white-space: nowrap; }
.mp-stores-sell a:hover { background: #f59e0b; color: #1f2937; text-decoration: none; }
.mp-stores-empty { background: #fff; border: 1px dashed #cbd5e1; border-radius: 16px; padding: 36px 20px; text-align: center; color: var(--mp-muted, #64748b); margin-bottom: 32px; }
</style>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">Tiendas</span>
</nav>

<header class="mp-stores-head">
    <h1>Tiendas de ebaemy</h1>
    <p>Empresas peruanas que venden en ebaemy. Todas tienen RUC validado ante SUNAT y te entregan boleta o factura. Entra a cada tienda para ver todos sus productos.</p>
    <div class="mp-stores-stats">
        <span class="mp-stores-stat">🏪 <b>{{ $stores->count() }}</b> {{ $stores->count() === 1 ? 'tienda' : 'tiendas' }}</span>
        <span class="mp-stores-stat">🛍️ <b>{{ number_format($totalProducts) }}</b> productos</span>
        <span class="mp-stores-stat">🚚 Envíos a todo el Perú</span>
        <span class="mp-stores-stat">↩️ <a href="{{ route('marketplace.shipping_returns') }}#garantia-7-dias">Garantía de 7 días</a></span>
    </div>
</header>

@if($stores->isEmpty())
    <div class="mp-stores-empty">Pronto verás aquí las tiendas que venden en ebaemy.</div>
@else
    <div class="mp-stores-grid">
        @foreach($stores as $s)
            <a class="mp-store" href="{{ route('marketplace.tenant', $s->subdomain) }}">
                @if(count($s->photos))
                    <div class="mp-store__photos" aria-hidden="true">
                        @foreach($s->photos as $ph)
                            <img src="{{ $ph['src'] }}" alt="" loading="{{ $loop->parent->index < 2 ? 'eager' : 'lazy' }}" width="120" height="120">
                        @endforeach
                        @for($i = count($s->photos); $i < 4; $i++)<span></span>@endfor
                    </div>
                @endif
                <div class="mp-store__body">
                    <div class="mp-store__who">
                        @if($s->logo)
                            <img class="mp-store__logo" src="{{ $s->logo }}" alt="Logo de {{ $s->name }}" loading="lazy" width="48" height="48">
                        @else
                            <span class="mp-store__logo mp-store__logo--txt" aria-hidden="true">{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}</span>
                        @endif
                        <div style="min-width:0">
                            <h2 class="mp-store__name">{{ $s->name }}</h2>
                            <div class="mp-store__meta">
                                <span>{{ $s->products }} {{ $s->products === 1 ? 'producto' : 'productos' }}</span>
                                @if($s->verified)<span class="mp-store__ok">✓ RUC verificado</span>@endif
                            </div>
                        </div>
                    </div>
                    @if(count($s->categories))
                        <div class="mp-store__cats">
                            @foreach($s->categories as $c)
                                <span class="mp-store__cat">{{ $c['name'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    <span class="mp-store__go">Ver tienda →</span>
                </div>
            </a>
        @endforeach
    </div>
@endif

<section class="mp-stores-sell">
    <div>
        <h2>¿Tienes una tienda?</h2>
        <p>Publica tus productos en ebaemy gratis y llega a compradores de todo el Perú.</p>
    </div>
    <a href="{{ route('seller.landing') }}">Vender en ebaemy</a>
</section>

@endsection
