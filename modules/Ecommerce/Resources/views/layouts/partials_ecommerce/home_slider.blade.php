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
                    $bannerHref = !empty($item->item_id)
                        ? url('/ecommerce/item/'.$item->item_id.'/'.$item->id)
                        : null;
                @endphp

                @if($bannerHref)
                    <a href="{{ $bannerHref }}" class="banner-slide-link" aria-label="Ver producto">
                @endif

                <div class="owl-lazy slide-bg" data-src="{{ asset('storage/uploads/promotions/'.$item->image) }}"></div>
                <div class="home-slide-content text-white">
                    {{-- <h1>{{ $item->name }}</h1>
                    <p>{{ $item->description }}</p>
                    <a href="/ecommerce/item/{{ $item->item_id }}/{{ $item->id }}" class="btn btn-dark">
                        Comprar Ahora!
                    </a> --}}
                </div>

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
