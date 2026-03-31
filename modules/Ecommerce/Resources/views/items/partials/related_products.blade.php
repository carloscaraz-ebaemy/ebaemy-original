@php
    $configuration = \App\Models\Tenant\Configuration::first();

    function relatedStock($item, $config) {
        if (!$config) return false;
        return $item->warehouses->sum('stock') <= 0;
    }
@endphp

@if(isset($relatedProducts) && $relatedProducts->count() > 0)
<section class="ec-related-section" aria-label="Productos relacionados">
    <div class="ec-section-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
        <h2 class="ec-section-title" style="margin:0">También te puede interesar</h2>
        <div style="display:flex;gap:8px">
            <div class="ec-related-prev ec-slider-btn" role="button" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </div>
            <div class="ec-related-next ec-slider-btn" role="button" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
        </div>
    </div>

    <div class="swiper ec-related-swiper">
        <div class="swiper-wrapper">
            @foreach($relatedProducts as $item)
            @php
                $outOfStock   = relatedStock($item, $configuration);
                $isNew        = $item->created_at && $item->created_at->diffInDays(now()) <= 30;
                $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
                $defaultPath  = $defaultImage === 'imagen-no-disponible.jpg'
                    ? asset('logo/imagen-no-disponible.jpg')
                    : asset('storage/defaults/' . $defaultImage);
                $imagePath    = ($item->image && $item->image !== 'imagen-no-disponible.jpg')
                    ? asset('storage/uploads/items/' . $item->image)
                    : $defaultPath;
                $productUrl   = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
            @endphp

            <div class="swiper-slide">
                <article class="ec-product-card{{ $outOfStock ? ' ec-product-card--oos' : '' }}">

                    <div class="ec-badges">
                        @if($isNew && !$outOfStock)
                            <span class="ec-badge ec-badge--new">Nuevo</span>
                        @endif
                        @if($outOfStock)
                            <span class="ec-badge ec-badge--oos">Agotado</span>
                        @endif
                    </div>

                    <button type="button"
                            class="ec-btn-wishlist"
                            data-wishlist-id="{{ $item->id }}"
                            aria-pressed="false"
                            title="Guardar en favoritos">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>

                    <a href="{{ $productUrl }}" class="ec-product-card__img-wrap" tabindex="-1">
                        <img src="{{ $imagePath }}"
                             alt="{{ $item->description }}"
                             width="300" height="300"
                             loading="lazy"
                             class="ec-product-card__img"
                             onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                    </a>

                    <div class="ec-product-card__body">
                        <h3 class="ec-product-card__title">
                            <a href="{{ $productUrl }}">{{ $item->description }}</a>
                        </h3>
                        <div class="ec-product-card__footer">
                            <div class="ec-product-card__price">
                                <span class="ec-price-current">
                                    {{ $item->currency_type['symbol'] ?? 'S/' }}
                                    {{ number_format($item->sale_unit_price, 2) }}
                                </span>
                            </div>
                            @if(!$outOfStock)
                                <button type="button"
                                        class="ec-btn-cart"
                                        data-ec-cart="{{ json_encode(['id' => $item->id, 'description' => $item->description, 'sale_unit_price' => $item->sale_unit_price, 'currency_type_id' => $item->currency_type_id, 'currency_type_symbol' => $item->currency_type['symbol'] ?? 'S/', 'image' => $item->image, 'image_medium' => $item->image_medium, 'image_small' => $item->image_small, 'stock' => $item->warehouses->sum('stock'), 'slug' => $item->slug]) }}"
                                        aria-label="Agregar {{ $item->description }} al carrito"
                                        title="Agregar al carrito">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2.5"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    <span class="ec-btn-cart__text">Agregar</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>
        <div class="swiper-pagination ec-related-pagination" style="margin-top:16px;position:relative;bottom:auto"></div>
    </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swiper !== 'undefined') {
        new Swiper('.ec-related-swiper', {
            slidesPerView: 2,
            spaceBetween: 16,
            grabCursor: true,
            navigation: { nextEl: '.ec-related-next', prevEl: '.ec-related-prev' },
            pagination: { el: '.ec-related-pagination', clickable: true },
            breakpoints: {
                576: { slidesPerView: 3 },
                768: { slidesPerView: 4 },
                1024: { slidesPerView: 5 },
            }
        });
    }
});
</script>
@endif
