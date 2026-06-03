{{--
    Fila de confianza bajo el grid de productos (marketplace ofertas/home).
    Partial reutilizable: incluir con @include('marketplace.partials.grid-trust-row')
    debajo del grid en cualquier vista de listado que lo necesite (hoy: index).
    Self-contained: trae su markup + estilo. No es una card → no va en
    listing-card-styles.
--}}
<div class="mp-grid-trust" role="list" aria-label="Garantías de compra en el marketplace">
    <div class="mp-grid-trust__item" role="listitem">
        <span class="mp-grid-trust__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 4v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </span>
        <span class="mp-grid-trust__txt">
            <strong>Envíos a todo el Perú</strong>
            <small>Rápido y seguro</small>
        </span>
    </div>
    <div class="mp-grid-trust__item" role="listitem">
        <span class="mp-grid-trust__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </span>
        <span class="mp-grid-trust__txt">
            <strong>Compra 100% segura</strong>
            <small>Protegemos tus datos</small>
        </span>
    </div>
    <div class="mp-grid-trust__item" role="listitem">
        <span class="mp-grid-trust__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
        </span>
        <span class="mp-grid-trust__txt">
            <strong>Soporte 24/7</strong>
            <small>Te ayudamos siempre</small>
        </span>
    </div>
    <div class="mp-grid-trust__item" role="listitem">
        <span class="mp-grid-trust__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 11 12 14 16 9"/></svg>
        </span>
        <span class="mp-grid-trust__txt">
            <strong>Garantía de satisfacción</strong>
            <small>O te devolvemos tu dinero</small>
        </span>
    </div>
</div>

<style>
    .mp-grid-trust {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-top: 22px;
        padding: 18px 20px;
        background: #fff;
        border: 1px solid var(--mp-line-soft, #eef0f3);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(10, 14, 26, .04);
    }
    .mp-grid-trust__item { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .mp-grid-trust__icon {
        flex-shrink: 0;
        width: 42px; height: 42px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--mp-primary-soft, #ecfdf5);
        color: var(--mp-primary, #0f8a82);
    }
    .mp-grid-trust__icon svg { width: 20px; height: 20px; }
    .mp-grid-trust__txt { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
    .mp-grid-trust__txt strong { font-size: 13.5px; font-weight: 700; color: #1f2937; }
    .mp-grid-trust__txt small { font-size: 12px; color: #6b7280; }

    @media (max-width: 860px) {
        .mp-grid-trust { grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px; }
    }
    @media (max-width: 460px) {
        .mp-grid-trust__icon { width: 36px; height: 36px; }
        .mp-grid-trust__icon svg { width: 17px; height: 17px; }
        .mp-grid-trust__txt strong { font-size: 12.5px; }
        .mp-grid-trust__txt small { font-size: 11px; }
    }
</style>
