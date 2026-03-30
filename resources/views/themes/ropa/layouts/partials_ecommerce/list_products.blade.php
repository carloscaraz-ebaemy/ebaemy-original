{{--
    THEME ROPA — Grid de productos estilo Zara/Falabella
    Cards limpias, sin bordes, imagen alta (3:4), tipografía editorial
--}}
@php
    $configuration = \App\Models\Tenant\Configuration::first();

    if (!function_exists('stock')) {
        function stock($item, $config) {
            if (!$config) return false;
            $total = 0;
            foreach ($item->warehouses as $wh) { $total += $wh->stock; }
            return $total <= 0;
        }
    }
    if (!function_exists('stockCount')) {
        function stockCount($item) {
            $total = 0;
            foreach ($item->warehouses as $wh) { $total += $wh->stock; }
            return $total;
        }
    }
@endphp

@foreach ($dataPaginate as $item)
@php
    $outOfStock   = stock($item, $configuration);
    $totalStock   = stockCount($item);
    if ($item->has_variants && $item->relationLoaded('variants')) {
        $variantStock = $item->variants->sum('stock');
        $outOfStock   = $variantStock <= 0;
        $totalStock   = $variantStock;
    }
    $isNew        = $item->created_at && $item->created_at->diffInDays(now()) <= 30;
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath  = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath    = $hasRealImage
        ? asset('storage/uploads/items/' . $item->image)
        : $defaultPath;
    $hoverImage = null;
    if ($item->relationLoaded('images') && $item->images->isNotEmpty()) {
        $firstGallery = $item->images->first();
        if ($firstGallery && $firstGallery->image && $firstGallery->image !== 'imagen-no-disponible.jpg') {
            $hoverImage = asset('storage/uploads/items/' . $firstGallery->image);
        }
    }
    $productUrl   = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $altText      = $item->description;
    $symbol       = $item->currency_type['symbol'] ?? 'S/';
    $price        = number_format($item->sale_unit_price, 2);
    $delay        = min($loop->iteration * 40, 400);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item"
     style="animation-delay: {{ $delay }}ms">

    <article class="ropa-card{{ $outOfStock ? ' ropa-card--oos' : '' }}">

        {{-- ── IMAGEN ── --}}
        <div class="ropa-card__media">
            <a href="{{ $productUrl }}" class="ropa-card__img-link">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}"
                     data-src="{{ $imagePath }}"
                     alt="{{ $altText }}"
                     loading="lazy" decoding="async"
                     class="ropa-card__img ropa-card__img--primary ec-img-lazy">
                @else
                <img src="{{ $defaultPath }}"
                     alt="{{ $altText }}"
                     class="ropa-card__img ropa-card__img--primary">
                @endif

                @if($hoverImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}"
                     data-src="{{ $hoverImage }}"
                     alt="{{ $altText }}"
                     class="ropa-card__img ropa-card__img--hover ec-img-lazy"
                     aria-hidden="true">
                @endif
            </a>

            {{-- Badges minimalistas --}}
            @if($outOfStock)
            <span class="ropa-badge ropa-badge--oos">Agotado</span>
            @elseif($isNew)
            <span class="ropa-badge ropa-badge--new">New</span>
            @endif

            {{-- Wishlist --}}
            <button type="button"
                    class="ropa-card__wish ec-btn-wishlist"
                    data-wishlist-id="{{ $item->id }}"
                    aria-pressed="false" title="Favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>

            {{-- Quick add (solo hover) --}}
            @if(!$outOfStock)
            <div class="ropa-card__quick">
                @if($item->has_variants)
                <a href="{{ $productUrl }}" class="ropa-card__quick-btn">Elegir opción</a>
                @else
                <button type="button" class="ropa-card__quick-btn ec-btn-cart"
                        data-ec-cart="{{ json_encode($item) }}">
                    Agregar al carro
                </button>
                @endif
            </div>
            @endif
        </div>

        {{-- ── INFO ── --}}
        <div class="ropa-card__info">
            @if($item->category)
            <span class="ropa-card__cat">{{ strtoupper($item->category->name) }}</span>
            @endif

            <h2 class="ropa-card__title">
                <a href="{{ $productUrl }}">{{ $item->description }}</a>
            </h2>

            <div class="ropa-card__price">
                <span class="ropa-card__price-current">{{ $symbol }} {{ $price }}</span>
            </div>

            @if($item->has_variants && !$outOfStock)
            <span class="ropa-card__variants-hint">Más opciones</span>
            @endif
        </div>

    </article>
</div>
@endforeach

{{-- ── CSS del grid tipo moda ── --}}
@once
<style>
/* ═══ THEME ROPA — PRODUCT CARDS ═══ */

.ropa-card {
    position: relative;
    margin-bottom: .5rem;
}

/* Imagen ratio 3:4 (moda) */
.ropa-card__media {
    position: relative;
    overflow: hidden;
    background: #f5f5f5;
    aspect-ratio: 3 / 4;
}

.ropa-card__img-link {
    display: block;
    width: 100%;
    height: 100%;
}

.ropa-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: opacity .3s ease;
}

.ropa-card__img--hover {
    position: absolute;
    inset: 0;
    opacity: 0;
}

.ropa-card__img-link:hover .ropa-card__img--hover {
    opacity: 1;
}
.ropa-card__img-link:hover .ropa-card__img--primary {
    opacity: 0;
}

/* Badges minimalistas */
.ropa-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 3px 8px;
    z-index: 2;
}

.ropa-badge--new {
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    color: #fff;
}

.ropa-badge--oos {
    background: #f3f4f6;
    color: #6b7280;
}

/* Wishlist */
.ropa-card__wish {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    z-index: 2;
    opacity: 0;
    transition: opacity .2s, color .2s;
}
.ropa-card:hover .ropa-card__wish {
    opacity: 1;
}
.ropa-card__wish:hover {
    color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
}

/* Quick add button (aparece al hover) */
.ropa-card__quick {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 8px;
    transform: translateY(100%);
    transition: transform .25s ease;
    z-index: 2;
}
.ropa-card:hover .ropa-card__quick {
    transform: translateY(0);
}
.ropa-card__quick-btn {
    display: block;
    width: 100%;
    padding: 10px;
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    color: #fff;
    border: none;
    text-align: center;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    cursor: pointer;
    transition: background .18s;
}
.ropa-card__quick-btn:hover {
    background: hsl(var(--primary-h), var(--primary-s), calc(var(--primary-l) - 10%));
    color: #fff;
    text-decoration: none;
}

/* Info */
.ropa-card__info {
    padding: .75rem 0 0;
}

.ropa-card__cat {
    font-size: 10px;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: .08em;
    display: block;
    margin-bottom: 2px;
}

.ropa-card__title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 15px;
    font-weight: 500;
    line-height: 1.3;
    margin: 0 0 4px;
}
.ropa-card__title a {
    color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
    text-decoration: none;
}
.ropa-card__title a:hover {
    text-decoration: underline;
}

.ropa-card__price {
    margin-top: 2px;
}
.ropa-card__price-current {
    font-size: 15px;
    font-weight: 600;
    color: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
}

.ropa-card__variants-hint {
    font-size: 11px;
    color: #6b7280;
    display: block;
    margin-top: 2px;
}

/* Agotado */
.ropa-card--oos .ropa-card__media {
    opacity: .5;
}
.ropa-card--oos .ropa-card__price-current {
    color: #9ca3af;
}

/* Responsive: 2 columnas en móvil */
@media (max-width: 575px) {
    .ropa-card__title {
        font-size: 13px;
    }
    .ropa-card__price-current {
        font-size: 14px;
    }
}
</style>
@endonce
