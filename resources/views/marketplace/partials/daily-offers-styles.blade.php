{{-- Estilos del carril y el modal de Ofertas del día. Aparte de
     partials/daily-offers para que el inicio los cargue en el <head> aunque el
     carril llegue después como fragmento (partials/showcase-deferred): el botón
     «🔥 Ofertas» se ve desde el principio. --}}
<style>
    .mp-offers-block { padding: 16px 0 8px; }
    .mp-offers-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:12px; flex-wrap: wrap; }
    .mp-offers-head__title-wrap { min-width: 0; flex: 1; }
    .mp-offers-head__actions { display: inline-flex; gap: 8px; align-items: center; flex-shrink: 0; }
    .mp-offers-title { margin:0; font-size:18px; font-weight:800; color:#0a0e1a; }
    .mp-offers-sub { margin:2px 0 0; font-size:12.5px; color:#555c69; }   /* #6b7280 sobre #f4f6f9 daba 4,46 */
    .mp-offers-cta { font-size:13px; font-weight:700; color:#bf1b1b; text-decoration:none; white-space:nowrap; }   /* #dc2626 sobre #f4f6f9 daba 4,46 */
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
        overflow-x:auto; scroll-behavior:smooth;
        /* El snap se activa DESPUES de `load`, con la clase .is-snappy.
           Un contenedor con scroll-snap-type:x mandatory se re-encaja solo
           cuando su contenido termina de asentarse, y eso cuenta como un
           scroll del usuario: Chrome deja de registrar candidatos a LCP en
           el primer scroll. Medido el 2026-10-08 en escritorio, el
           NotifyScroll caia en 1362 ms, treinta milisegundos ANTES del
           primer pintado de imagen, y la pagina acababa sin LCP — con la
           metrica sin medir, PageSpeed devuelve la puntuacion entera nula.
           Las paginas sin este carril median 97. */
        padding:4px 2px 14px;
        scrollbar-width:none;            /* Firefox */
        -ms-overflow-style:none;         /* IE/Edge antiguo */
    }
    .mp-offers-rail.is-snappy { scroll-snap-type:x mandatory; }
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
        font-size:12px; font-weight:600;
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
    .mp-offer-card__old { font-size:12px; color:#6b7280; text-decoration:line-through; }   /* #9ca3af sobre blanco daba 2,54 */
    .mp-offer-card__shop { margin-top:4px; font-size:12px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
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
            border: none; border-radius: 999px; padding: 0 16px; min-height: 44px;
            font-size: 13px; font-weight: 800;
            box-shadow: 0 8px 20px -6px rgba(220,38,38,.55); cursor: pointer;
        }
        .mp-offers-fab:active { transform: scale(.96); }

        .mp-offers-modal__panel {
            left: 0; right: 0; top: auto; bottom: 0; transform: none;
            width: auto; max-height: 84vh; max-height: min(84vh, calc(100dvh - 140px)); /* deja a la vista el header */
            border-radius: 18px 18px 0 0;
            padding: 14px 14px calc(14px + env(safe-area-inset-bottom));
            box-shadow: none;
            animation: mpOffersUp .28s ease;
        }
        @keyframes mpOffersUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    }
</style>
