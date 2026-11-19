{{-- ═══════════════════════ OFERTAS DEL DÍA (carrusel + modal de bienvenida) ═══════════════════════
     Partial compartido entre la home (/marketplace) y la página de tienda
     (/marketplace/tienda/{sub}). El modal NO se abre solo (2026-11-19): se abre
     con el botón «🔥 Ofertas». El bloque inline (carrusel) queda visible en escritorio.

     Variables esperadas:
       $offers       Collection de ofertas (el includer ya garantiza count >= 4)
       $seeAllUrl    string  URL del botón "Ver todas las ofertas"
       $modalSeenKey string  clave sessionStorage (única por contexto: home vs cada tienda)
       $offersTitle  string  (opcional) título; default "🔥 Ofertas del día"
       $offersSub    string  (opcional) subtítulo bajo el título
       $offersDeferred bool  (opcional) fragmento diferido del inicio
                             (MarketplaceController::showcaseSection): sin
                             botón flotante (ya está en la página, ver
                             partials/showcase-deferred) ni estilos (ya
                             están en el <head>).
--}}
@php
    $offersTitle = $offersTitle ?? '🔥 Ofertas del día';
    $offersSub   = $offersSub ?? '';
@endphp

{{-- Bajo la vitrina del inicio ($offersEager = 0) queda bajo el pliegue:
     content-visibility:auto (.mp-cv-block) le ahorra el layout al cargar. --}}
<section class="mp-section mp-offers-block {{ ($offersEager ?? 3) === 0 ? 'mp-cv-block' : '' }}" id="mpOffersBlock" aria-label="Ofertas del día">
    <div class="mp-offers-head">
        <div class="mp-offers-head__title-wrap">
            <h2 class="mp-offers-title">{{ $offersTitle }}</h2>
            @if($offersSub !== '')
                <p class="mp-offers-sub">{{ $offersSub }}</p>
            @endif
        </div>
        <div class="mp-offers-head__actions">
            <a href="{{ $seeAllUrl }}" class="mp-offers-cta">Ver todas →</a>
            {{-- Flechas de carrusel (desktop) --}}
            <button type="button" class="mp-offers-nav-btn" data-offers-prev aria-label="Anterior">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button type="button" class="mp-offers-nav-btn" data-offers-next aria-label="Siguiente">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            {{-- Toggle expandir/colapsar la seccion (usuario decide) --}}
            <button type="button" class="mp-offers-collapse-btn" id="mpOffersCollapse" aria-expanded="true" aria-controls="mpOffersBody" title="Colapsar / expandir">
                <svg class="mp-offers-collapse-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        </div>
    </div>
    <div class="mp-offers-body" id="mpOffersBody">
        <div class="mp-offers-rail" id="mpOffersRail">
            @foreach($offers as $offer)
                {{-- data-offer-id: por el reordena el navegador segun la
                     afinidad del visitante (partials/personalization). --}}
                <a href="{{ route('marketplace.item', $offer->slug) }}" class="mp-offer-card"
                   data-offer-id="{{ $offer->id }}">
                    <div class="mp-offer-card__img">
                        @if($offer->image_url)
                            {{-- Miniatura de 512px si existe; el carrusel pinta
                                 ~180px y la variante `_mp` de 1080 es 4 veces
                                 mas pesada para el mismo resultado. --}}
                            {{-- Las tres primeras no van diferidas: este
                                 carrusel es lo primero que se pinta y con
                                 loading="lazy" el navegador no lo pedia hasta
                                 cerrar el layout, retrasando el primer
                                 renderizado.
                                 Sin fetchpriority, eso si: el LCP no es una
                                 tarjeta de aqui (200 px fijos) sino una de la
                                 rejilla (~48vw en movil), que es la que lleva
                                 la prioridad y el preload. Pedir prioridad
                                 alta para las dos es no pedirla para
                                 ninguna. --}}
                            @php $offerEager = $loop->index < ($offersEager ?? 3); @endphp
                            <img src="{{ $offer->thumb_image_url ?? $offer->image_url }}"
                                 alt="{{ $offer->title }}"
                                 loading="{{ $offerEager ? 'eager' : 'lazy' }}"
                                 decoding="{{ $offerEager ? 'sync' : 'async' }}"
                                 width="512" height="512">
                        @else
                            <div class="mp-offer-card__noimg">Sin imagen</div>
                        @endif
                        @if(!empty($offer->discount_pct))
                            <span class="mp-offer-card__pct">-{{ $offer->discount_pct }}%</span>
                        @endif
                        @if(!empty($offer->offer_ends_at))
                            <span class="mp-offer-card__timer" data-ends-at="{{ \Carbon\Carbon::parse($offer->offer_ends_at)->toIso8601String() }}">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span class="mp-offer-card__timer-txt">…</span>
                            </span>
                        @endif
                    </div>
                    <div class="mp-offer-card__body">
                        <h3 class="mp-offer-card__title">{{ $offer->title }}</h3>
                        <div class="mp-offer-card__price-row">
                            <span class="mp-offer-card__price">S/ {{ number_format($offer->display_price, 2) }}</span>
                            @if(!empty($offer->original_price) && $offer->original_price > $offer->display_price)
                                <span class="mp-offer-card__old">S/ {{ number_format($offer->original_price, 2) }}</span>
                        @endif
                    </div>
                    <div class="mp-offer-card__shop">{{ $offer->seller_display }}</div>
                </div>
            </a>
        @endforeach
        </div> {{-- /.mp-offers-rail --}}
    </div> {{-- /.mp-offers-body --}}
</section>

{{-- Botón flotante para abrir el modal de ofertas (solo móvil). En el
     fragmento diferido no va: el inicio ya lo pinta desde el principio. --}}
@unless(!empty($offersDeferred))
<button type="button" id="mpOffersFab" class="mp-offers-fab" aria-label="Ver ofertas del día">
    🔥 Ofertas
</button>
@endunless

{{-- Modal de ofertas (móvil: bottom-sheet · escritorio: diálogo centrado).
     El carrusel se CLONA aquí por JS para que el bloque inline siga visible
     en escritorio sin duplicar datos en el HTML servido. --}}
<div id="mpOffersModal" class="mp-offers-modal" aria-hidden="true">
    <div class="mp-offers-modal__backdrop" data-offers-close></div>
    <div class="mp-offers-modal__panel" role="dialog" aria-modal="true" aria-label="Ofertas del día">
        <div class="mp-offers-modal__head">
            <h2 class="mp-offers-modal__title">{{ $offersTitle }}</h2>
            <button type="button" class="mp-offers-modal__close" data-offers-close aria-label="Cerrar">✕</button>
        </div>
        <div class="mp-offers-modal__body" id="mpOffersModalBody"></div>
        <a href="{{ $seeAllUrl }}" class="mp-offers-modal__all">Ver todas las ofertas →</a>
    </div>
</div>

@unless(!empty($offersDeferred))
@push('styles')
@include('marketplace.partials.daily-offers-styles')
@endpush
@endunless

<script>
// Clon del carrusel para el modal. Clonamos (no movemos) para que el bloque
// inline siga visible en escritorio, y el clon pierde el id para no
// duplicarlo en el DOM.
//
// Se construye la PRIMERA VEZ QUE SE ABRE, no al cargar. Antes se clonaba de
// entrada y eso metia otra vez en el documento las ~17 fotos del carrusel,
// invisibles, antes de que nadie hubiera pedido ver el modal. Desde que las
// tres primeras del carril dejaron de ir diferidas, el clon se traia tambien
// ese `loading="eager"` copiado tal cual y duplicaba las descargas que mas
// prisa tenian. Por si acaso, al clonar se le devuelve el diferido: lo que
// esta dentro de un modal cerrado no corre ninguna prisa.
// El snap horizontal se enciende cuando la pagina ya pinto (ver el comentario
// en .mp-offers-rail). Se aplica tambien al clon del modal, que para entonces
// puede existir o no.
(function () {
    function snap() {
        document.querySelectorAll('.mp-offers-rail').forEach(function (r) {
            r.classList.add('is-snappy');
        });
    }
    if (document.readyState === 'complete') setTimeout(snap, 200);
    else window.addEventListener('load', function () { setTimeout(snap, 200); }, { once: true });
})();

window.mpOffersEnsureClone = function () {
    var rail  = document.getElementById('mpOffersRail');
    var mBody = document.getElementById('mpOffersModalBody');
    if (!rail || !mBody || mBody.children.length) return;

    var clone = rail.cloneNode(true);
    clone.removeAttribute('id');
    clone.classList.add('is-snappy');
    clone.querySelectorAll('img').forEach(function (img) {
        img.setAttribute('loading', 'lazy');
        img.setAttribute('decoding', 'async');
        img.removeAttribute('fetchpriority');
    });
    mBody.appendChild(clone);
};

// Countdown ligero para los timers de "Termina en" (incluye los del clon).
// Re-consulta el DOM en cada tick para cubrir las cards clonadas al modal.
(function () {
    function fmt(ms) {
        if (ms <= 0) return 'Expirada';
        var h = Math.floor(ms / 3600000);
        var m = Math.floor((ms % 3600000) / 60000);
        if (h >= 24) {
            var d = Math.floor(h / 24);
            return d + 'd ' + (h % 24) + 'h';
        }
        return h + 'h ' + m + 'm';
    }
    function tick() {
        var timers = document.querySelectorAll('.mp-offer-card__timer[data-ends-at]');
        if (!timers.length) return;
        var now = Date.now();
        timers.forEach(function (t) {
            var ends = new Date(t.getAttribute('data-ends-at')).getTime();
            var span = t.querySelector('.mp-offer-card__timer-txt');
            if (span) span.textContent = fmt(ends - now);
        });
    }
    tick();
    setInterval(tick, 60000);
})();

// Carrusel inline: flechas prev/next + collapse/expand del bloque.
(function () {
    var rail  = document.getElementById('mpOffersRail');
    var block = document.getElementById('mpOffersBlock');
    var body  = document.getElementById('mpOffersBody');
    if (!rail || !block) return;

    var prevBtn = block.querySelector('[data-offers-prev]');
    var nextBtn = block.querySelector('[data-offers-next]');
    function scrollBy(dir) {
        var card = rail.querySelector('.mp-offer-card');
        var step = card ? card.offsetWidth + 14 : 220;
        rail.scrollBy({ left: dir * step * 2, behavior: 'smooth' });
    }
    function syncNav() {
        if (!prevBtn || !nextBtn) return;
        prevBtn.disabled = rail.scrollLeft <= 4;
        nextBtn.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
    }
    if (prevBtn) prevBtn.addEventListener('click', function () { scrollBy(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { scrollBy(1); });
    rail.addEventListener('scroll', syncNav, { passive: true });
    window.addEventListener('resize', syncNav);
    syncNav();

    var collapseBtn = document.getElementById('mpOffersCollapse');
    if (collapseBtn && body) {
        var key = 'mp_offers_collapsed';
        try {
            if (localStorage.getItem(key) === '1') {
                body.classList.add('is-collapsed');
                collapseBtn.setAttribute('aria-expanded', 'false');
            }
        } catch (e) {}
        collapseBtn.addEventListener('click', function () {
            var collapsed = body.classList.toggle('is-collapsed');
            collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (e) {}
        });
    }
})();

// Modal: abrir/cerrar + FAB + auto-abrir 1× por sesión (móvil y escritorio).
(function () {
    var modal = document.getElementById('mpOffersModal');
    var fab   = document.getElementById('mpOffersFab');
    if (!modal) return;

    function open()  {
        if (window.mpOffersEnsureClone) window.mpOffersEnsureClone();
        modal.classList.add('is-open');  modal.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
    }
    function close() { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true');  document.body.style.overflow = ''; }
    // El drawer de Categorías lo cierra al abrirse: si no, su fondo oscuro
    // quedaba encima y no se podía elegir ninguna categoría.
    window.mpOffersClose = function () { if (modal.classList.contains('is-open')) close(); };
    // El botón «🔥 Ofertas» del inicio existe antes que este carril
    // (llega diferido): si se toca antes, carga el fragmento y llama a esto.
    window.mpOffersOpen = open;

    if (fab) fab.addEventListener('click', open);
    modal.querySelectorAll('[data-offers-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // NO se abre solo, en ningún ancho (2026-11-19). En escritorio se abría
    // 1,5 s después de `load` encima de la portada recién pintada: tapaba la
    // vitrina, era un intersticial justo cuando la persona empieza a mirar y
    // empeoraba el LCP. En móvil ya no se abría desde 2026-11. Queda el botón
    // «🔥 Ofertas» (y el carril inline en escritorio) para abrirlo a petición.
    // `modalSeenKey` ya no se usa: se conserva en la firma del partial para
    // no tocar a quien lo incluye.
})();
</script>
