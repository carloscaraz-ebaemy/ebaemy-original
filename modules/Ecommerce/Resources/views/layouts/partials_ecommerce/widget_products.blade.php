@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);

    $featuredItems = isset($items) ? $items->take(8) : collect();
    $isByTag = isset($featuredTagExists) && $featuredTagExists;
@endphp

@if($featuredItems->count() > 0)
<div class="ec-wp-widget">

    {{-- Header --}}
    <div class="ec-wp-header">
        <div class="ec-wp-header__title">
            @if($isByTag)
            <span class="ec-wp-star">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </span>
            @endif
            <span>Productos Destacados</span>
        </div>
        <div class="ec-wp-nav">
            <button class="ec-wp-prev ec-wp-navbtn" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button class="ec-wp-next ec-wp-navbtn" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </div>

    {{-- Swiper --}}
    <div class="swiper ec-wp-swiper">
        <div class="swiper-wrapper">
            @foreach($featuredItems as $index => $item)
            @php
                $itemImagePath = ($item->image && $item->image !== 'imagen-no-disponible.jpg')
                    ? asset('storage/uploads/items/' . $item->image)
                    : $defaultImagePath;
                $itemUrl = '/ecommerce/item/' . ($item->slug ?? $item->id);
                $isNew   = isset($item->is_new) ? $item->is_new : 0;
                $ecCart  = htmlspecialchars(json_encode([
                    'id'                  => $item->id,
                    'description'         => $item->description,
                    'sale_unit_price'     => $item->sale_unit,
                    'currency_type_id'    => $item->currency_type_id,
                    'currency_type_symbol'=> $item->currency_type_symbol,
                    'image'               => $item->image,
                    'image_medium'        => $item->image_medium,
                    'image_small'         => $item->image_small,
                    'stock'               => $item->stock,
                    'slug'                => $item->slug ?? $item->id,
                ]), ENT_QUOTES);
            @endphp
            <div class="swiper-slide">
                <div class="ec-wp-card">
                    {{-- Imagen --}}
                    <a href="{{ $itemUrl }}" class="ec-wp-card__img-wrap">
                        <img src="{{ $itemImagePath }}"
                             alt="{{ $item->description }}"
                             onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                        @if($isByTag)
                        <span class="ec-wp-card__badge">Destacado</span>
                        @endif
                    </a>

                    {{-- Info --}}
                    <div class="ec-wp-card__body">
                        <a href="{{ $itemUrl }}" class="ec-wp-card__name">
                            {{ \Illuminate\Support\Str::limit($item->description, 38) }}
                        </a>
                        <div class="ec-wp-card__footer">
                            <span class="ec-wp-card__price">
                                {{ $item->currency_type_symbol }} {{ number_format($item->sale_unit, 2) }}
                            </span>
                            <button type="button"
                                    class="ec-wp-card__btn paction add-cart"
                                    data-ec-cart="{{ $ecCart }}"
                                    title="Agregar al carrito">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                <span>Agregar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swiper !== 'undefined') {
        new Swiper('.ec-wp-swiper', {
            direction: 'vertical',
            slidesPerView: 3,
            spaceBetween: 0,
            grabCursor: true,
            navigation: { nextEl: '.ec-wp-next', prevEl: '.ec-wp-prev' },
        });
    }
});
</script>
@endif
