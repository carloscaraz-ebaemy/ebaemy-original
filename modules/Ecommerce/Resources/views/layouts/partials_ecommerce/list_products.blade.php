@php
    $configuration = \App\Models\Tenant\Configuration::first();

    function stock($item, $config) {
        if (!$config) return false;
        $total = 0;
        foreach ($item->warehouses as $wh) { $total += $wh->stock; }
        return $total <= 0;
    }
    function stockCount($item) {
        $total = 0;
        foreach ($item->warehouses as $wh) { $total += $wh->stock; }
        return $total;
    }
@endphp

@foreach ($dataPaginate as $item)
@php
    $outOfStock   = stock($item, $configuration);
    $totalStock   = stockCount($item);
    $isNew        = $item->created_at && $item->created_at->diffInDays(now()) <= 30;
    $isLowStock   = !$outOfStock && $totalStock > 0 && $totalStock <= 5;
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath  = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath    = $hasRealImage
        ? asset('storage/uploads/items/' . $item->image)
        : $defaultPath;
    $productUrl   = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $altText      = $item->description . ($item->category ? ' — ' . $item->category->name : '');
    $symbol       = $item->currency_type['symbol'] ?? 'S/';
    $price        = number_format($item->sale_unit_price, 2);
    // Stagger delay (1-based position in the page)
    $loop_i       = $loop->iteration;
    $delay        = min($loop_i * 40, 400);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item"
     style="animation-delay: {{ $delay }}ms">

    <article class="pcard{{ $outOfStock ? ' pcard--oos' : '' }}"
             itemscope itemtype="https://schema.org/Product">

        {{-- ── IMAGE SECTION ───────────────────────────────── --}}
        <div class="pcard__media">

            {{-- Badges top-left --}}
            <div class="pcard__badges">
                @if($outOfStock)
                    <span class="pbadge pbadge--oos">Agotado</span>
                @elseif($isLowStock)
                    <span class="pbadge pbadge--hot">
                        <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                        Últimas {{ $totalStock }}
                    </span>
                @elseif($isNew)
                    <span class="pbadge pbadge--new">Nuevo</span>
                @endif
            </div>

            {{-- Wishlist (top-right, glassmorphism) --}}
            <button type="button"
                    class="pcard__wish ec-btn-wishlist"
                    data-wishlist-id="{{ $item->id }}"
                    aria-pressed="false"
                    title="Guardar en favoritos"
                    aria-label="Guardar {{ $item->description }} en favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>

            {{-- Product image --}}
            <a href="{{ $productUrl }}" class="pcard__img-link" tabindex="-1" aria-label="{{ $altText }}">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}"
                     data-src="{{ $imagePath }}"
                     alt="{{ $altText }}"
                     width="400" height="400"
                     class="pcard__img ec-img-lazy"
                     onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'"
                     itemprop="image">
                @else
                <img src="{{ $defaultPath }}"
                     alt="{{ $altText }}"
                     width="400" height="400"
                     class="pcard__img"
                     onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'"
                     itemprop="image">
                @endif
            </a>

            {{-- Hover overlay with quick view --}}
            @if(!$outOfStock)
            <div class="pcard__overlay">
                <button type="button"
                        class="pcard__quickview ec-btn-quickview"
                        data-item-id="{{ $item->id }}"
                        aria-label="Vista rápida de {{ $item->description }}"
                        title="Vista rápida">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Vista rápida
                </button>
                <button type="button"
                        class="pcard__compare-mini ec-btn-compare"
                        data-compare-id="{{ $item->id }}"
                        data-product="{{ json_encode($item) }}"
                        aria-pressed="false"
                        title="Comparar"
                        aria-label="Comparar {{ $item->description }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                        <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                    </svg>
                </button>
            </div>
            @endif

        </div>{{-- /pcard__media --}}

        {{-- ── BODY ────────────────────────────────────────── --}}
        <div class="pcard__body">

            {{-- Category label --}}
            @if($item->category)
            <span class="pcard__cat" itemprop="category">{{ $item->category->name }}</span>
            @endif

            {{-- Title --}}
            <h2 class="pcard__title" itemprop="name">
                <a href="{{ $productUrl }}">{{ $item->description }}</a>
            </h2>

            {{-- Short desc --}}
            @if(isset($preferences['show_description']) && $preferences['show_description'] == 1 && $item->name)
            <p class="pcard__desc">{{ \Illuminate\Support\Str::limit($item->name, 55) }}</p>
            @endif

            {{-- Low stock bar --}}
            @if($isLowStock)
            <div class="pcard__low-stock" aria-label="Stock bajo">
                <span>¡Solo {{ $totalStock }} disponibles!</span>
                <div class="pcard__stock-bar" role="progressbar" aria-valuenow="{{ $totalStock }}" aria-valuemin="0" aria-valuemax="5">
                    <div class="pcard__stock-fill" style="width:{{ min(100, ($totalStock / 5) * 100) }}%"></div>
                </div>
            </div>
            @elseif(isset($preferences['show_stock']) && $preferences['show_stock'] == 1 && !$outOfStock)
            <p class="pcard__stock-pill">
                <span class="pcard__stock-dot"></span>
                En stock
            </p>
            @endif

            {{-- Price --}}
            <div class="pcard__price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <meta itemprop="priceCurrency" content="{{ $item->currency_type_id ?? 'PEN' }}">
                <meta itemprop="price"         content="{{ $item->sale_unit_price }}">
                <link  itemprop="url"           href="{{ $productUrl }}">
                @if($outOfStock)
                    <link itemprop="availability" href="https://schema.org/OutOfStock">
                @else
                    <link itemprop="availability" href="https://schema.org/InStock">
                @endif
                <span class="pcard__price-current">{{ $symbol }} {{ $price }}</span>
            </div>

            {{-- CTA --}}
            @if(!$outOfStock)
                <button type="button"
                        class="pcard__cta ec-btn-cart"
                        data-ec-cart="{{ json_encode($item) }}"
                        aria-label="Agregar {{ $item->description }} al carrito"
                        title="Agregar al carrito">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span class="pcard__cta-text">Agregar al carrito</span>
                </button>
            @else
                <button type="button"
                        class="pcard__notify ec-btn-notify"
                        data-item-id="{{ $item->id }}"
                        data-item-name="{{ $item->description }}"
                        title="Notificarme cuando esté disponible">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Avisar cuando haya stock
                </button>
            @endif

        </div>{{-- /pcard__body --}}
    </article>
</div>
@endforeach
