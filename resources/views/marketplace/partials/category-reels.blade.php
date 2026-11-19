{{--
    Carruseles de productos por categoría, estilo reel (fase 2 del rediseño).
    Una fila por cada categoría raíz con más productos publicados (hasta 5),
    con su título, «Ver todo →» a la página de categoría oficial y 10-12
    tarjetas del partial listing-card de siempre (nombre de tienda incluido).

    La selección la hace HomeShowcase (cache de 10 min): con foto real y
    stock, ofertas y destacados delante, luego lo más visto, y en RONDA POR
    TIENDAS para que ninguna fila sea el escaparate de una sola tienda.

    Deslizable con el dedo; en escritorio, flechas. La tarjeta siguiente
    asoma por el borde para indicar que hay más. El scroll-snap se enciende
    DESPUÉS de `load` (.is-snappy), como en el carril de ofertas: activo de
    entrada, su reajuste cuenta como scroll y Chrome deja de medir el LCP.
    Todo va lazy: los carruseles quedan bajo el primer pliegue.

    Vars: $reels Collection de object{root, items}
--}}
@if(!empty($reels) && $reels->count() > 0)
    @foreach($reels as $reel)
        @php $reelId = 'mpReel' . $reel->root->id; @endphp
        <section class="mp-reel" aria-labelledby="{{ $reelId }}Title" data-reel>
            <div class="mp-section-head">
                <h2 class="mp-section-title" id="{{ $reelId }}Title">
                    <a href="{{ route('marketplace.category_official', ['fullSlug' => $reel->root->full_slug]) }}">{{ $reel->root->name }}</a>
                </h2>
                <div class="mp-section-head__actions">
                    <a href="{{ route('marketplace.category_official', ['fullSlug' => $reel->root->full_slug]) }}" class="mp-section-more">
                        Ver todo <span aria-hidden="true">→</span><span class="mp-sr-only"> en {{ $reel->root->name }}</span>
                    </a>
                    <button type="button" class="mp-reel__nav" data-reel-prev aria-controls="{{ $reelId }}" aria-label="Anteriores de {{ $reel->root->name }}" disabled>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button type="button" class="mp-reel__nav" data-reel-next aria-controls="{{ $reelId }}" aria-label="Siguientes de {{ $reel->root->name }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
            <div class="mp-reel__rail" id="{{ $reelId }}" data-reel-rail>
                @foreach($reel->items as $listing)
                    <div class="mp-reel__item">
                        @include('marketplace.partials.listing-card', [
                            'listing'        => $listing,
                            'cardEagerCount' => 0,
                            'showTopBadge'   => false,
                            'showBestBadge'  => false,
                            'showNewBadge'   => false,
                        ])
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    <script>
    (function () {
        var reels = document.querySelectorAll('[data-reel]');
        if (!reels.length) return;
        function snap() { reels.forEach(function (r) { r.querySelector('[data-reel-rail]').classList.add('is-snappy'); }); }
        if (document.readyState === 'complete') setTimeout(snap, 200);
        else window.addEventListener('load', function () { setTimeout(snap, 200); }, { once: true });

        reels.forEach(function (reel) {
            var rail = reel.querySelector('[data-reel-rail]');
            var prev = reel.querySelector('[data-reel-prev]');
            var next = reel.querySelector('[data-reel-next]');
            function step() {
                var item = rail.querySelector('.mp-reel__item');
                // Avanza lo que se ve menos una tarjeta: la ultima visible
                // pasa a ser la primera, y no se pierde el hilo.
                var w = item ? item.getBoundingClientRect().width : 220;
                return Math.max(w, rail.clientWidth - w);
            }
            function sync() {
                prev.disabled = rail.scrollLeft <= 4;
                next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
            }
            prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
            next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
            rail.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
            window.addEventListener('resize', sync);
            sync();
        });
    })();
    </script>
@endif
