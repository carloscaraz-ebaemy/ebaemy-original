{{-- 
    - vista slider promociones
    - var items definida en Modules\Ecommerce\Http\ViewComposers\PromotionsViewComposer
--}}
@php
    $banners = $items->filter(fn($i) => $i->type !== 'spots');
@endphp

@if($banners->isNotEmpty())
<div class="banner-slider-wrapper" style="position: relative;">
    <div class="home-slider ecommerce owl-carousel owl-carousel-lazy owl-theme owl-theme-light {{ $full_width_banner ? 'full-width-banner' : '' }}">
        @foreach ($banners as $item)
            <div class="home-slide">
                @php
                    // El destino lo resuelve el modelo (link_type explícito o
                    // deducido de banner_url/item_id, como hacía este blade).
                    $bannerHref = $item->link_href;

                    // Imagen mobile: si el tenant no subió una, se usa la de
                    // desktop — el comportamiento de siempre.
                    $bannerDesktop = asset('storage/uploads/promotions/'.$item->image);
                    $bannerMobile  = $item->image_mobile_url ?: $bannerDesktop;
                    $hasCopy = filled($item->title) || filled($item->subtitle) || filled($item->button_text);
                @endphp

                @if($bannerHref)
                    <a href="{{ $bannerHref }}" class="banner-slide-link" aria-label="{{ $item->title ?: ($item->name ?: 'Ver banner') }}">
                @endif

                {{-- Dos capas con la misma clase owl-lazy: owl carga la que
                     esté visible según el breakpoint. Se usa data-src en las
                     dos para no romper el lazy load del carrusel. --}}
                <div class="owl-lazy slide-bg slide-bg--desktop" data-src="{{ $bannerDesktop }}"></div>
                <div class="owl-lazy slide-bg slide-bg--mobile" data-src="{{ $bannerMobile }}"></div>
                <noscript><img src="{{ $bannerDesktop }}" alt="{{ $item->title ?: ($item->name ?? 'Banner promocional') }}" width="1200" height="400" style="width:100%;height:auto"></noscript>

                @if($hasCopy)
                <div class="home-slide-content ec-slide-copy">
                    @if(filled($item->title))
                        <h2 class="ec-slide-copy__title">{{ $item->title }}</h2>
                    @endif
                    @if(filled($item->subtitle))
                        <p class="ec-slide-copy__subtitle">{{ $item->subtitle }}</p>
                    @endif
                    @if(filled($item->button_text) && $bannerHref)
                        <span class="ec-slide-copy__btn">{{ $item->button_text }}</span>
                    @endif
                </div>
                @endif

                @if($bannerHref)
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    @if($banners->count() > 1)
    <button type="button" class="banner-nav-btn banner-nav-prev" onclick="navigateEcommerceBanner('prev')">
        <span>
            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 6l-6 6l6 6" /></svg>
        </span>
    </button>
    <button type="button" class="banner-nav-btn banner-nav-next" onclick="navigateEcommerceBanner('next')">
        <span>
            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6l6 6l-6 6" /></svg>
        </span>
    </button>
    @endif
</div>

<script>
function navigateEcommerceBanner(direction) {
    var owl = $('.home-slider.ecommerce');
    if (direction === 'next') {
        owl.trigger('next.owl.carousel');
    } else {
        owl.trigger('prev.owl.carousel');
    }
}

$(document).ready(function() {
    $('.banner-nav-btn').hover(
        function() {
            $(this).css('background', 'rgba(0,0,0,0.8)');
        },
        function() {
            $(this).css('background', 'rgba(0,0,0,0.5)');
        }
    );

    var $owl = $('.home-slider.ecommerce');

    function numberOwlDots() {
        var $dots = $owl.find('.owl-dots .owl-dot span');
        if (!$dots.length) return;

        $owl.attr('data-dots-numbered', '1');
        $dots.each(function(index) {
            $(this).text(index + 1);
        });
    }

    $owl.on('initialized.owl.carousel refreshed.owl.carousel', function() {
        numberOwlDots();
    });
    setTimeout(numberOwlDots, 0);
});
</script>

<style>
.banner-slider-wrapper {
    position: relative;
}

.banner-slide-link {
    display: block;
    width: 100%;
    height: 100%;
    color: inherit;
    text-decoration: none;
}
</style>
@endif

<style>
/* ── Banner: versión mobile ───────────────────────────────────────────
   El slide tenía una sola capa de fondo. Ahora hay dos y se muestra la
   que corresponde al ancho; si el tenant no subió imagen vertical, las
   dos apuntan al mismo archivo y no se nota diferencia. */
.slide-bg--mobile { display: none; }
@media (max-width: 767px) {
    .slide-bg--desktop { display: none; }
    .slide-bg--mobile  { display: block; }
}

/* ── Texto sobre el banner ────────────────────────────────────────────
   Solo se renderiza si el banner tiene título, subtítulo o botón, así que
   los banners que son pura imagen quedan exactamente como estaban. */
.ec-slide-copy {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; justify-content: center;
    gap: 10px; padding: 0 8%;
    color: #fff;
    text-shadow: 0 1px 12px rgba(0,0,0,.45);
    pointer-events: none;
}
.ec-slide-copy__title {
    font-size: clamp(22px, 3.4vw, 44px);
    font-weight: 800; line-height: 1.1; margin: 0;
    max-width: 16ch; text-wrap: balance;
}
.ec-slide-copy__subtitle {
    font-size: clamp(13px, 1.5vw, 18px);
    margin: 0; max-width: 42ch; line-height: 1.45;
}
.ec-slide-copy__btn {
    align-self: flex-start;
    background: var(--theme-primary, hsl(var(--primary-h), var(--primary-s), var(--primary-l)));
    color: var(--theme-primary-contrast, #fff);
    font-weight: 700; font-size: 14px;
    padding: 10px 24px; border-radius: 8px;
    text-shadow: none; margin-top: 4px;
}
@media (max-width: 767px) {
    .ec-slide-copy { padding: 0 7%; gap: 7px; }
    .ec-slide-copy__btn { padding: 8px 18px; font-size: 13px; }
}
</style>
