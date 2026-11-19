{{--
    Carruseles de productos por categoría, estilo reel (fase 2 del rediseño).
    Una fila por cada categoría raíz con más productos publicados (hasta 5),
    con su título, «Ver todo →» a la página de categoría oficial y las
    primeras HomeShowcase::REEL_RENDER tarjetas del partial listing-card de
    siempre; la última casilla es otro enlace «Ver todo» (el resto de la
    categoría vive allí: 12 por fila eran >3.300 nodos en el inicio).

    La selección la hace HomeShowcase (cache de 10 min): con foto real y
    stock, ofertas y destacados delante, luego lo más visto, y en RONDA POR
    TIENDAS para que ninguna fila sea el escaparate de una sola tienda.

    Deslizable con el dedo; en escritorio, flechas. La tarjeta siguiente
    asoma por el borde para indicar que hay más. El scroll-snap se enciende
    DESPUÉS de `load` (.is-snappy), como en el carril de ofertas: activo de
    entrada, su reajuste cuenta como scroll y Chrome deja de medir el LCP.

    LCP (medido 2026-11-19): con portada, el elemento LCP es la PRIMERA
    tarjeta del PRIMER carrusel en móvil (y ≈ 593 px a 390 de ancho), no la
    foto de la portada (84 px); en escritorio salió la 4.ª de esa fila (se ven
    5,3 y todas miden igual). Por eso TODA la primera fila va sin diferir y la
    primera lleva preload + fetchpriority alto, con el mismo src/srcset que
    pinta la tarjeta (partials/listing-preload). Las filas 2-5 quedan bajo
    el pliegue: lazy y con content-visibility:auto (.mp-cv-reel).

    Diferidos (2026-11-19): el HTML inicial del inicio solo lleva el PRIMER
    carrusel; los demás llegan como fragmento de
    MarketplaceController::showcaseSection ($reelsDeferred = true): sin
    preload, todo lazy, y el script se vuelve a ejecutar al insertarlo
    (partials/showcase-deferred), por eso cada fila se marca al iniciarse y
    no se engancha dos veces.

    Vars: $reels Collection de object{root, items}
          $reelsDeferred bool (opcional) — fragmento cargado aparte
--}}
@if(!empty($reels) && $reels->count() > 0)
    @php $reelsDeferred = !empty($reelsDeferred); @endphp
    @unless($reelsDeferred)
        @include('marketplace.partials.listing-preload', [
            'items'      => $reels->first()->items->take(6),
            'withSrcset' => true,
        ])
    @endunless
    @foreach($reels as $reel)
        @php
            $reelId    = 'mpReel' . $reel->root->id;
            $reelFirst = $loop->first && !$reelsDeferred;
            $reelUrl   = route('marketplace.category_official', ['fullSlug' => $reel->root->full_slug]);
        @endphp
        <section class="mp-reel {{ ($reelFirst || $reelsDeferred) ? '' : 'mp-cv-reel' }}" aria-labelledby="{{ $reelId }}Title" data-reel>
            <div class="mp-section-head">
                <h2 class="mp-section-title" id="{{ $reelId }}Title">
                    <a href="{{ $reelUrl }}">{{ $reel->root->name }}</a>
                </h2>
                <div class="mp-section-head__actions">
                    <a href="{{ $reelUrl }}" class="mp-section-more">
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
                            'listing'           => $listing,
                            'cardEagerCount'    => $reelFirst ? \App\Services\Marketplace\HomeShowcase::REEL_RENDER : 0,
                            'cardPriorityCount' => 1,
                            'showTopBadge'      => false,
                            'showBestBadge'     => false,
                            'showNewBadge'      => false,
                        ])
                    </div>
                @endforeach
                <div class="mp-reel__item mp-reel__item--more">
                    <a href="{{ $reelUrl }}" class="mp-reel__more">
                        <span class="mp-reel__more-icon" aria-hidden="true">→</span>
                        <span>Ver todo en <strong>{{ $reel->root->name }}</strong></span>
                        @if(!empty($reel->root->count))
                            <span class="mp-reel__more-count">{{ $reel->root->count }} producto{{ $reel->root->count === 1 ? '' : 's' }}</span>
                        @endif
                    </a>
                </div>
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
            // Este script corre otra vez con cada carrusel diferido que llega:
            // las filas ya iniciadas no se vuelven a enganchar.
            if (reel.getAttribute('data-reel-ready')) return;
            reel.setAttribute('data-reel-ready', '1');
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
                // Fila aun sin pintar (content-visibility): no se sabe su
                // ancho; se recalcula cuando entra en pantalla.
                if (!rail.clientWidth) return;
                prev.disabled = rail.scrollLeft <= 4;
                next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
            }
            prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
            next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
            rail.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
            reel.addEventListener('contentvisibilityautostatechange', sync);
            window.addEventListener('resize', sync);
            sync();
        });
    })();
    </script>
@endif
