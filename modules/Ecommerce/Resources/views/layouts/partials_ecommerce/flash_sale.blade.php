@if(isset($flashSale) && $flashSale && $flashSale->items->count())
@php
    $endsAt = $flashSale->ends_at->toIso8601String();
@endphp

<section class="ec-flash-sale" aria-label="Oferta relámpago">
    <div class="ec-flash-sale__head">
        <div class="ec-flash-sale__title-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                 fill="currentColor" aria-hidden="true">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
            <div>
                <h2 class="ec-flash-sale__title">{{ $flashSale->title }}</h2>
                @if($flashSale->subtitle)
                    <p class="ec-flash-sale__subtitle">{{ $flashSale->subtitle }}</p>
                @endif
            </div>
        </div>

        {{-- Countdown --}}
        <div class="ec-countdown" id="ec-countdown" data-ends="{{ $endsAt }}" aria-live="polite">
            <div class="ec-countdown__block">
                <span class="ec-countdown__num" id="ec-cd-h">00</span>
                <span class="ec-countdown__label">Horas</span>
            </div>
            <span class="ec-countdown__sep">:</span>
            <div class="ec-countdown__block">
                <span class="ec-countdown__num" id="ec-cd-m">00</span>
                <span class="ec-countdown__label">Min</span>
            </div>
            <span class="ec-countdown__sep">:</span>
            <div class="ec-countdown__block">
                <span class="ec-countdown__num" id="ec-cd-s">00</span>
                <span class="ec-countdown__label">Seg</span>
            </div>
        </div>
    </div>

    @php $flashItemCount = $flashSale->items->count(); @endphp
    <div style="position:relative">
        @if($flashItemCount > 2)
        <button type="button" class="ec-flash-nav ec-flash-nav--prev" aria-label="Anterior"
                style="position:absolute;left:-6px;top:50%;transform:translateY(-50%);z-index:5;background:rgba(255,255,255,.9);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button type="button" class="ec-flash-nav ec-flash-nav--next" aria-label="Siguiente"
                style="position:absolute;right:-6px;top:50%;transform:translateY(-50%);z-index:5;background:rgba(255,255,255,.9);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.15)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        @endif
    <div class="ec-flash-sale__products{{ $flashItemCount <= 2 ? ' ec-flash--few' : '' }}" id="ec-flash-scroll">
        @foreach($flashSale->items as $item)
        @php
            $flashPrice   = $item->pivot->flash_price;
            $regularPrice = $item->sale_unit_price;
            $discount     = $regularPrice > 0
                ? round((($regularPrice - $flashPrice) / $regularPrice) * 100)
                : 0;
            $imgUrl = ($item->image && $item->image !== 'imagen-no-disponible.jpg')
                ? asset('storage/uploads/items/'. $item->image)
                : asset('porto-ecommerce/assets/images/no-image.png');
            $itemUrl = route('tenant.ecommerce.item', ['slug' => $item->slug]);
        @endphp
        <a href="{{ $itemUrl }}" class="ec-flash-card">
            @if($discount > 0)
            <span class="ec-flash-card__badge">-{{ $discount }}%</span>
            @endif
            <div class="ec-flash-card__img-wrap">
                <img src="{{ $imgUrl }}" alt="{{ $item->description }}"
                     loading="lazy" class="ec-flash-card__img">
            </div>
            <div class="ec-flash-card__body">
                <p class="ec-flash-card__name">{{ \Illuminate\Support\Str::limit($item->description, 50) }}</p>
                <div class="ec-flash-card__prices">
                    <span class="ec-flash-card__price-new">S/ {{ number_format($flashPrice, 2) }}</span>
                    @if($regularPrice > $flashPrice)
                    <span class="ec-flash-card__price-old">S/ {{ number_format($regularPrice, 2) }}</span>
                    @endif
                </div>
            </div>
        </a>
        @endforeach
    </div>
    </div>
</section>

<script>
(function(){
    var scroll = document.getElementById('ec-flash-scroll');
    if (!scroll) return;
    var prev = document.querySelector('.ec-flash-nav--prev');
    var next = document.querySelector('.ec-flash-nav--next');
    var step = function(){ return scroll.querySelector('.ec-flash-card')?.offsetWidth + 16 || 200; };
    if (prev) prev.addEventListener('click', function(){ scroll.scrollBy({left: -step(), behavior:'smooth'}); });
    if (next) next.addEventListener('click', function(){ scroll.scrollBy({left: step(), behavior:'smooth'}); });
}());
</script>

@push('scripts')
<script>
(function () {
    var el = document.getElementById('ec-countdown');
    if (!el) return;

    var endsAt  = new Date(el.getAttribute('data-ends')).getTime();
    var elH = document.getElementById('ec-cd-h');
    var elM = document.getElementById('ec-cd-m');
    var elS = document.getElementById('ec-cd-s');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function tick() {
        var now  = Date.now();
        var diff = Math.max(0, Math.floor((endsAt - now) / 1000));

        if (diff <= 0) {
            el.innerHTML = '<span class="ec-countdown__ended">¡Oferta terminada!</span>';
            return;
        }

        var days = Math.floor(diff / 86400);
        var h    = Math.floor((diff % 86400) / 3600);
        var m    = Math.floor((diff % 3600) / 60);
        var s    = diff % 60;

        // If more than 24h, show days in hours
        elH.textContent = pad(days * 24 + h);
        elM.textContent = pad(m);
        elS.textContent = pad(s);
    }

    tick();
    setInterval(tick, 1000);
}());
</script>
@endpush
@endif
