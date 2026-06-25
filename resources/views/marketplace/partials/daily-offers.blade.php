{{-- ═══════════════════════ OFERTAS DEL DÍA (carrusel + modal de bienvenida) ═══════════════════════
     Partial compartido entre la home (/marketplace) y la página de tienda
     (/marketplace/tienda/{sub}). El modal se auto-abre 1× por sesión en móvil
     Y escritorio. El bloque inline (carrusel) queda visible en escritorio.

     Variables esperadas:
       $offers       Collection de ofertas (el includer ya garantiza count >= 4)
       $seeAllUrl    string  URL del botón "Ver todas las ofertas"
       $modalSeenKey string  clave sessionStorage (única por contexto: home vs cada tienda)
       $offersTitle  string  (opcional) título; default "🔥 Ofertas del día"
       $offersSub    string  (opcional) subtítulo bajo el título
--}}
@php
    $offersTitle = $offersTitle ?? '🔥 Ofertas del día';
    $offersSub   = $offersSub ?? '';
@endphp

<section class="mp-section mp-offers-block" id="mpOffersBlock" aria-label="Ofertas del día">
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
                <a href="{{ route('marketplace.item', $offer->slug) }}" class="mp-offer-card">
                    <div class="mp-offer-card__img">
                        @if($offer->image_url)
                            <img src="{{ $offer->image_url }}" alt="{{ $offer->title }}" loading="lazy">
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

{{-- Botón flotante para abrir el modal de ofertas (solo móvil) --}}
<button type="button" id="mpOffersFab" class="mp-offers-fab" aria-label="Ver ofertas del día">
    🔥 Ofertas
</button>

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

@push('styles')
<style>
    .mp-offers-block { padding: 16px 0 8px; }
    .mp-offers-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:12px; flex-wrap: wrap; }
    .mp-offers-head__title-wrap { min-width: 0; flex: 1; }
    .mp-offers-head__actions { display: inline-flex; gap: 8px; align-items: center; flex-shrink: 0; }
    .mp-offers-title { margin:0; font-size:18px; font-weight:800; color:#0a0e1a; }
    .mp-offers-sub { margin:2px 0 0; font-size:12.5px; color:#6b7280; }
    .mp-offers-cta { font-size:13px; font-weight:700; color:#dc2626; text-decoration:none; white-space:nowrap; }
    .mp-offers-cta:hover { text-decoration:underline; }

    /* Flechas nav del carrusel (desktop). Mobile: ocultas, swipe nativo. */
    .mp-offers-nav-btn {
        width: 32px; height: 32px;
        border-radius: 999px;
        border: 1.5px solid var(--mp-line, #e5e7eb);
        background: #fff;
        color: #0a0e1a;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: border-color .15s, color .15s, background .15s, transform .12s;
    }
    .mp-offers-nav-btn:hover { border-color: #dc2626; color: #dc2626; }
    .mp-offers-nav-btn:active { transform: scale(.94); }
    .mp-offers-nav-btn:disabled { opacity: .35; cursor: not-allowed; }
    @media (max-width: 700px) {
        .mp-offers-nav-btn { display: none; }
    }

    /* Boton collapse/expand */
    .mp-offers-collapse-btn {
        width: 32px; height: 32px;
        border-radius: 999px;
        border: 1.5px solid var(--mp-line, #e5e7eb);
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: border-color .15s, color .15s, background .15s;
    }
    .mp-offers-collapse-btn:hover { border-color: #6b7280; color: #0a0e1a; }
    .mp-offers-collapse-icon { transition: transform .25s; }
    .mp-offers-collapse-btn[aria-expanded="false"] .mp-offers-collapse-icon {
        transform: rotate(-90deg);
    }

    /* Body collapsable con animacion */
    .mp-offers-body {
        transition: max-height .35s ease, opacity .25s ease;
        max-height: 480px;
        overflow: hidden;
    }
    .mp-offers-body.is-collapsed {
        max-height: 0;
        opacity: 0;
        margin-top: 0;
    }
    .mp-offers-rail-wrap { position: relative; }
    .mp-offers-rail {
        display:flex; gap:14px;
        overflow-x:auto; scroll-snap-type:x mandatory; scroll-behavior:smooth;
        padding:4px 2px 14px;
        scrollbar-width:none;            /* Firefox */
        -ms-overflow-style:none;         /* IE/Edge antiguo */
    }
    .mp-offers-rail::-webkit-scrollbar { width:0; height:0; display:none; }  /* Chrome/Safari */
    .mp-offer-card {
        flex:0 0 auto; scroll-snap-align:start;
        width:200px;
        background:#fff; border:1px solid #f1f5f9; border-radius:14px;
        text-decoration:none; color:inherit;
        overflow:hidden;
        transition: transform .18s, box-shadow .18s, border-color .18s;
    }
    .mp-offer-card:hover {
        transform: translateY(-3px);
        border-color: rgba(220,38,38,.35);
        box-shadow: 0 10px 22px -12px rgba(220,38,38,.22);
    }
    .mp-offer-card__img { position:relative; aspect-ratio:1/1; background:#f7f9fb; overflow:hidden; }
    .mp-offer-card__img img { width:100%; height:100%; object-fit:cover; transition:transform .35s; }
    .mp-offer-card:hover .mp-offer-card__img img { transform:scale(1.04); }
    .mp-offer-card__noimg { display:flex; align-items:center; justify-content:center; height:100%; color:#9ca3af; font-size:12px; }
    .mp-offer-card__pct {
        position:absolute; top:8px; left:8px;
        background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
        font-size:12px; font-weight:800; letter-spacing:.3px;
        padding:3px 8px; border-radius:999px;
        box-shadow: 0 4px 8px -4px rgba(220,38,38,.45);
    }
    .mp-offer-card__timer {
        position:absolute; bottom:8px; right:8px;
        display:inline-flex; align-items:center;
        background:rgba(15,23,42,.85); color:#fff;
        font-size:11px; font-weight:600;
        padding:3px 8px; border-radius:999px;
        backdrop-filter: blur(4px);
    }
    .mp-offer-card__body { padding:10px 12px 12px; }
    .mp-offer-card__title {
        margin:0 0 6px; font-size:13px; font-weight:600; color:#1f2937;
        line-height:1.3;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
        overflow:hidden;
        min-height: 34px;
    }
    .mp-offer-card__price-row { display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; }
    .mp-offer-card__price { font-size:16px; font-weight:800; color:#dc2626; }
    .mp-offer-card__old { font-size:12px; color:#9ca3af; text-decoration:line-through; }
    .mp-offer-card__shop { margin-top:4px; font-size:11.5px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    @media (max-width: 640px) {
        .mp-offer-card { width: 160px; }
        .mp-offers-title { font-size:16px; }
        .mp-offers-sub { display: none; }
    }

    /* ── Modal de ofertas ──
       Escritorio: diálogo centrado, auto-abierto 1×/sesión (el bloque inline
       sigue visible debajo). Móvil: bottom-sheet + FAB, bloque inline oculto. */
    .mp-offers-fab { display: none; }
    .mp-offers-modal { display: none; }
    .mp-offers-modal.is-open { display: block; }
    .mp-offers-modal__backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 1300; }
    .mp-offers-modal__panel {
        position: fixed; z-index: 1310;
        left: 50%; top: 50%; transform: translate(-50%, -50%);
        width: min(680px, 92vw); max-height: 80vh;
        background: #fff; border-radius: 18px; padding: 18px;
        display: flex; flex-direction: column;
        box-shadow: 0 24px 60px -12px rgba(15,23,42,.4);
        animation: mpOffersPop .22s ease;
    }
    @keyframes mpOffersPop {
        from { opacity: 0; transform: translate(-50%, -48%) scale(.97); }
        to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    }
    .mp-offers-modal__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .mp-offers-modal__title { margin: 0; font-size: 18px; font-weight: 800; color: #0a0e1a; }
    .mp-offers-modal__close { border: none; background: #f3f4f6; width: 32px; height: 32px; border-radius: 999px; font-size: 15px; cursor: pointer; color: #374151; }
    .mp-offers-modal__body { overflow-y: auto; }
    .mp-offers-modal__body .mp-offers-rail { padding-bottom: 6px; }
    .mp-offers-modal__all { display: block; text-align: center; margin-top: 10px; padding-top: 8px; font-size: 13px; font-weight: 700; color: #dc2626; text-decoration: none; border-top: 1px solid #f1f5f9; }

    @media (max-width: 700px) {
        /* La fila inline se oculta en móvil; el modal vive como bottom-sheet. */
        .mp-offers-block { display: none; }

        .mp-offers-fab {
            display: inline-flex; align-items: center; gap: 6px;
            position: fixed; left: 14px; bottom: 80px; z-index: 1200;
            background: linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
            border: none; border-radius: 999px; padding: 10px 16px;
            font-size: 13px; font-weight: 800;
            box-shadow: 0 8px 20px -6px rgba(220,38,38,.55); cursor: pointer;
        }
        .mp-offers-fab:active { transform: scale(.96); }

        .mp-offers-modal__panel {
            left: 0; right: 0; top: auto; bottom: 0; transform: none;
            width: auto; max-height: 84vh;
            border-radius: 18px 18px 0 0;
            padding: 14px 14px calc(14px + env(safe-area-inset-bottom));
            box-shadow: none;
            animation: mpOffersUp .28s ease;
        }
        @keyframes mpOffersUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    }
</style>
@endpush

<script>
// Clonar el carrusel dentro del modal. Clonamos (no movemos) para que el
// bloque inline siga visible en escritorio. El clon pierde el id para no
// duplicarlo en el DOM.
(function () {
    var rail  = document.getElementById('mpOffersRail');
    var mBody = document.getElementById('mpOffersModalBody');
    if (!rail || !mBody || mBody.children.length) return;
    var clone = rail.cloneNode(true);
    clone.removeAttribute('id');
    mBody.appendChild(clone);
})();

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

    function open()  { modal.classList.add('is-open');  modal.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
    function close() { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true');  document.body.style.overflow = ''; }

    if (fab) fab.addEventListener('click', open);
    modal.querySelectorAll('[data-offers-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Auto-abrir una sola vez por sesión (no molesta en cada navegación).
    // La clave es única por contexto (home vs cada tienda).
    try {
        var KEY = @json($modalSeenKey);
        if (!sessionStorage.getItem(KEY)) {
            sessionStorage.setItem(KEY, '1');
            setTimeout(open, 700);
        }
    } catch (e) {}
})();
</script>
