{{-- Estilos del shell del marketplace (header/mega-menú, footer, sticky, mini-cart, etc.) movidos aquí desde el body del layout para eliminar FOUC. Se incluye en el <head>. --}}
<style>
    /* ───────────── Botón trigger del mega menú ───────────── */
    .mp-mega-toggle {
        display: inline-flex; align-items: center; gap: 6px;
        cursor: pointer; user-select: none;
        white-space: nowrap;
    }
    .mp-mega-toggle__chev { transition: transform .2s; flex-shrink: 0; }
    .mp-mega-toggle[aria-expanded="true"] .mp-mega-toggle__chev { transform: rotate(180deg); }

    /* Mobile (<=640px): el boton "Categorias" come mucho ancho del
       search bar (texto + chevron + padding ~110px). Lo compactamos
       a solo el icono hamburguesa, manteniendo el tap target 44px
       accesible. Asi el input de busqueda recupera ~80px. */
    @media (max-width: 640px) {
        .mp-mega-toggle {
            padding: 0;
            min-width: 44px; width: 44px;
            justify-content: center;
        }
        .mp-mega-toggle__label,
        .mp-mega-toggle__chev { display: none; }
    }

    /* ───────────── Panel desktop (mega menú) ───────────── */
    /* ═══════════════════════ DRAWER 2-pane ═══════════════════════ */
    .mp-cat-drawer {
        position: fixed; inset: 0;
        z-index: 1100;
        pointer-events: none;
        visibility: hidden;
    }
    .mp-cat-drawer.is-open { pointer-events: auto; visibility: visible; }
    .mp-cat-drawer__backdrop {
        position: absolute; inset: 0;
        background: rgba(15, 23, 42, .45);
        opacity: 0;
        transition: opacity .22s ease;
    }
    .mp-cat-drawer.is-open .mp-cat-drawer__backdrop { opacity: 1; }
    .mp-cat-drawer__panel {
        position: absolute;
        top: 0; left: 0; bottom: 0;
        width: min(720px, 92vw);
        max-width: 100vw;
        background: #fff;
        box-shadow: 8px 0 28px rgba(15,23,42,.18);
        display: flex; flex-direction: column;
        /* Critico: el container de panes en mobile usa
           grid-template-columns:100% 100% (200% de ancho)
           para el slide horizontal. Sin overflow:hidden el
           segundo pane se desborda fuera del panel y se ve
           "cortado" a la derecha del viewport. */
        overflow: hidden;
        transform: translateX(-105%);
        transition: transform .28s cubic-bezier(.16,1,.3,1);
    }
    /* Wrapper full-viewport (forzado con dvw/dvh para iOS Safari
       donde 100vh incluye barras del navegador). Garantiza que
       el drawer cubra todo aun si position:fixed esta roto por
       algun containing block raro en un ancestor. */
    .mp-cat-drawer.is-open {
        width: 100dvw; height: 100dvh;
        top: 0; left: 0;
    }
    .mp-cat-drawer.is-open .mp-cat-drawer__panel { transform: translateX(0); }

    /* Header */
    .mp-cat-drawer__head {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 18px;
        border-bottom: 1px solid #e5e7eb;
        flex-shrink: 0;
    }
    .mp-cat-drawer__title {
        margin: 0; flex: 1;
        font-size: 17px; font-weight: 700; color: #111827;
    }
    .mp-cat-drawer__back,
    .mp-cat-drawer__close {
        width: 38px; height: 38px;
        background: transparent; border: 0; cursor: pointer;
        border-radius: 999px;
        color: #6b7280;
        display: inline-flex; align-items: center; justify-content: center;
        transition: background .12s;
    }
    .mp-cat-drawer__back:hover,
    .mp-cat-drawer__close:hover { background: #f3f4f6; color: #111827; }

    /* Panes container: 2 columnas en desktop, 1 + slide en mobile */
    .mp-cat-drawer__panes {
        flex: 1; min-height: 0;
        display: grid;
        grid-template-columns: 260px 1fr;
    }

    /* Lista de roots (izquierda) */
    .mp-cat-drawer__roots {
        list-style: none; margin: 0; padding: 8px 0;
        overflow-y: auto;
        background: #f9fafb;
        border-right: 1px solid #e5e7eb;
    }
    .mp-cat-drawer__root {
        display: flex; align-items: center; gap: 10px;
        width: 100%; padding: 12px 16px;
        background: transparent; border: 0; cursor: pointer;
        font-size: 14px; font-weight: 500; color: #374151;
        text-align: left;
        transition: background .12s, color .12s;
        min-height: 44px;
    }
    .mp-cat-drawer__root:hover {
        background: #f3f4f6; color: #111827;
    }
    .mp-cat-drawer__root.is-active {
        background: #fff;
        color: var(--mp-primary-dark, #0c6b65);
        font-weight: 700;
        box-shadow: inset 3px 0 0 var(--mp-primary, #0f8a82);
    }
    .mp-cat-drawer__root-icon { font-size: 18px; line-height: 1; flex-shrink: 0; }
    .mp-cat-drawer__root-name { flex: 1; }
    .mp-cat-drawer__root-chev { color: #9ca3af; flex-shrink: 0; }
    .mp-cat-drawer__root.is-active .mp-cat-drawer__root-chev { color: var(--mp-primary, #0f8a82); }

    /* Panel de children (derecha) */
    .mp-cat-drawer__children {
        overflow-y: auto;
        padding: 16px 20px 24px;
    }
    .mp-cat-drawer__child-pane { display: none; }
    .mp-cat-drawer__child-pane.is-active { display: block; }

    .mp-cat-drawer__child-all {
        display: flex; align-items: center; gap: 10px;
        padding: 11px 14px;
        background: var(--mp-primary-soft, #e6f7f5);
        color: var(--mp-primary-dark, #0c6b65);
        border-radius: 10px;
        font-size: 13.5px; font-weight: 600;
        text-decoration: none;
        margin-bottom: 14px;
        min-height: 44px;
    }
    .mp-cat-drawer__child-all:hover { background: #d1fae5; }

    .mp-cat-drawer__child-list {
        list-style: none; margin: 0; padding: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 12px;
    }
    .mp-cat-drawer__child {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 10px;
        font-size: 13.5px; color: #374151;
        text-decoration: none;
        border-radius: 8px;
        transition: background .12s, color .12s;
        min-height: 40px;
    }
    .mp-cat-drawer__child:hover {
        background: #f0fdfa; color: var(--mp-primary-dark);
    }
    .mp-cat-drawer__child-icon { font-size: 14px; flex-shrink: 0; }

    /* Mobile (<700px): un solo panel a la vez, slide entre roots y children */
    @media (max-width: 700px) {
        /* Full-screen: 100dvw/100dvh para evitar el bug clasico
           de iOS Safari donde 100vh incluye URL bar. !important
           por si alguna regla anterior gano la cascada. */
        .mp-cat-drawer__panel {
            width: 100dvw !important;
            max-width: 100dvw !important;
            height: 100dvh;
            box-shadow: none;
        }
        /* Backdrop no aporta nada cuando el panel cubre todo. */
        .mp-cat-drawer__backdrop { display: none; }
        .mp-cat-drawer__panes {
            grid-template-columns: 100% 100%;
            transform: translateX(0);
            transition: transform .25s ease;
        }
        .mp-cat-drawer__panes.is-children-view {
            transform: translateX(-100%);
        }
        .mp-cat-drawer__roots {
            border-right: 0;
        }
        .mp-cat-drawer__root.is-active {
            box-shadow: none;
            background: #f3f4f6;
        }
        .mp-cat-drawer__child-list {
            grid-template-columns: 1fr;
        }
        .mp-cat-drawer__child { font-size: 15px; padding: 12px 10px; }
    }
</style>

<style>
    .mp-trust-sticky--footer {
        position: static !important;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        border-bottom: 0;
        padding: 18px 16px;
    }
    .mp-trust-sticky--footer .mp-trust-sticky-inner {
        max-width: 1180px; margin: 0 auto;
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 18px 28px;
    }
    @media (max-width: 768px) {
        .mp-trust-sticky--footer { padding: 14px 12px; }
        .mp-trust-sticky--footer .mp-trust-sticky-inner { gap: 10px 18px; font-size: 12.5px; }
    }
</style>

<style>
.mp-footer-newsletter {
    background: linear-gradient(135deg, #0a6f68 0%, #1fb1a6 100%);
    padding: 22px 16px;
}
.mp-footer-newsletter__inner {
    max-width: 1180px; margin: 0 auto;
    display: flex; flex-wrap: wrap; gap: 16px;
    align-items: center; justify-content: space-between;
}
.mp-footer-newsletter__head {
    display: flex; align-items: center; gap: 12px;
    color: #fff; flex: 1; min-width: 260px;
}
.mp-footer-newsletter__icon { font-size: 28px; line-height: 1; }
.mp-footer-newsletter__head strong { display:block; font-size: 15px; font-weight: 700; }
.mp-footer-newsletter__head small { display:block; font-size: 12px; opacity: .85; margin-top: 2px; }
.mp-footer-newsletter__form { display: flex; gap: 6px; flex: 1; min-width: 280px; max-width: 450px; }
.mp-footer-newsletter__form input {
    flex: 1; padding: 10px 14px;
    border: 0; border-radius: 8px 0 0 8px;
    font-size: 14px; outline: 0;
    background: #fff;
}
.mp-footer-newsletter__form button {
    padding: 10px 20px;
    background: #fbbf24; color: #1f2937;
    border: 0; border-radius: 0 8px 8px 0;
    font-weight: 700; font-size: 13.5px;
    cursor: pointer; transition: background .12s;
}
.mp-footer-newsletter__form button:hover { background: #f59e0b; }
.mp-footer-newsletter__msg {
    flex-basis: 100%; font-size: 13px; color: #fff;
    min-height: 18px; margin-top: 2px;
}
.mp-footer-newsletter__msg.is-ok    { color: #d1fae5; font-weight: 600; }
.mp-footer-newsletter__msg.is-error { color: #fee2e2; font-weight: 600; }

.mp-footer-trust { background: #1f2937; padding: 18px 16px; border-top: 1px solid #374151; }
.mp-footer-trust__inner {
    max-width: 1180px; margin: 0 auto;
    display: flex; flex-wrap: wrap; gap: 30px;
    align-items: center; justify-content: space-between;
}
.mp-footer-trust__label {
    font-size: 11px; font-weight: 700;
    color: #9ca3af; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 6px;
}
.mp-footer-trust__items { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.mp-footer-trust__pay {
    display: inline-flex; align-items: center; justify-content: center;
    height: 28px; padding: 0 4px;
    background: #fff; border-radius: 5px;
}
.mp-footer-trust__badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 10px;
    background: #064e3b; color: #d1fae5;
    border: 1px solid #047857; border-radius: 6px;
    font-size: 11.5px;
}
.mp-footer-trust__badge strong { color: #fff; font-weight: 700; }
@media (max-width: 720px) {
    .mp-footer-newsletter__inner { flex-direction: column; }
    .mp-footer-trust__inner { flex-direction: column; gap: 14px; align-items: flex-start; }
}
</style>

<style>
    .mp-toast {
        position: fixed;
        bottom: 78px;
        right: 16px;
        z-index: 1100;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        max-width: 360px;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border: 1.5px solid #fbbf24;
        border-radius: 14px;
        box-shadow: 0 12px 32px -8px rgba(245, 158, 11, .35);
        font-size: 13.5px;
        color: #78350f;
        line-height: 1.4;
        animation: mpToastSlideIn .4s cubic-bezier(.32,.72,0,1);
    }
    .mp-toast.is-hiding { animation: mpToastSlideOut .35s ease forwards; }
    @keyframes mpToastSlideIn {
        from { transform: translateY(20px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }
    @keyframes mpToastSlideOut {
        to { transform: translateY(20px); opacity: 0; }
    }
    .mp-toast__icon { font-size: 28px; flex-shrink: 0; line-height: 1; }
    .mp-toast__body { flex: 1; }
    .mp-toast__body strong { color: #92400e; font-weight: 800; }
    .mp-toast__body small { color: #b45309; font-size: 11.5px; opacity: .85; }
    .mp-toast__cta {
        flex-shrink: 0;
        background: #f59e0b;
        color: #fff;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12.5px;
        text-decoration: none;
        transition: background .15s;
    }
    .mp-toast__cta:hover { background: #d97706; color: #fff; }
    .mp-toast__close {
        background: transparent;
        border: 0;
        font-size: 22px;
        line-height: 1;
        color: #92400e;
        opacity: .5;
        cursor: pointer;
        padding: 0 4px;
    }
    .mp-toast__close:hover { opacity: 1; }
    @media (max-width: 640px) {
        .mp-toast {
            right: 8px;
            left: 8px;
            bottom: 78px;
            max-width: none;
        }
    }
    </style>

<style>
.mp-mini-cart {
    position: fixed; inset: 0;
    z-index: 1000;
    pointer-events: none;
    visibility: hidden;
}
.mp-mini-cart.is-open { pointer-events: auto; visibility: visible; }
.mp-mini-cart__backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, .45);
    opacity: 0;
    transition: opacity .25s ease;
    /* Z-INDEX EXPLICITO: el backdrop esta declarado DESPUES del panel
       en el DOM, lo que (sin z-index) lo renderia encima tapando los
       botones (cerrar, eliminar). Lo mandamos atras. */
    z-index: 1;
}
.mp-mini-cart.is-open .mp-mini-cart__backdrop { opacity: 1; }
.mp-mini-cart__panel {
    position: absolute;
    background: #fff;
    display: flex; flex-direction: column;
    box-shadow: -8px 0 24px rgba(15, 23, 42, .15);
    /* Desktop: slide-in desde la derecha */
    top: 0; right: 0; bottom: 0;
    width: min(420px, 92vw);
    transform: translateX(105%);
    transition: transform .28s cubic-bezier(.16,1,.3,1);
    /* Por encima del backdrop para que close/eliminar reciban clicks. */
    z-index: 2;
}
.mp-mini-cart.is-open .mp-mini-cart__panel { transform: translateX(0); }

.mp-mini-cart__head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid var(--mp-line, #e5e7eb);
    flex-shrink: 0;
}
.mp-mini-cart__title {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 16px; font-weight: 700; color: var(--mp-ink, #111827);
}
.mp-mini-cart__count {
    font-size: 11.5px; font-weight: 700;
    background: var(--mp-primary, #0f8a82); color: #fff;
    padding: 2px 8px; border-radius: 999px;
    min-width: 24px; text-align: center;
}
.mp-mini-cart__close {
    width: 36px; height: 36px; border: 0; background: transparent;
    border-radius: 999px; cursor: pointer; color: #6b7280;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.mp-mini-cart__close:hover { background: #f3f4f6; color: #111827; }

.mp-mini-cart__body {
    flex: 1; overflow-y: auto;
    padding: 8px 18px 12px;
}
.mp-mini-cart__coupon-hint {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 14px 14px 0;
    padding: 12px 14px;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1.5px solid #fbbf24;
    border-radius: 12px;
    text-decoration: none;
    color: #78350f;
    font-size: 13px;
    line-height: 1.35;
    transition: all .15s ease;
    box-shadow: 0 2px 8px -2px rgba(245, 158, 11, .25);
}
.mp-mini-cart__coupon-hint:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(245, 158, 11, .35);
    border-color: #f59e0b;
}
.mp-mini-cart__coupon-hint-icon { font-size: 22px; line-height: 1; flex-shrink: 0; }
.mp-mini-cart__coupon-hint-text { flex: 1; }
.mp-mini-cart__coupon-hint-text strong { color: #92400e; font-weight: 800; }
.mp-mini-cart__coupon-hint svg { flex-shrink: 0; opacity: .6; }

.mp-mini-cart__loading {
    text-align: center; padding: 40px 16px; color: #9ca3af; font-size: 14px;
}
.mp-mini-cart__empty {
    text-align: center; padding: 48px 16px;
}
.mp-mini-cart__empty-icon {
    font-size: 48px; opacity: .35;
}
.mp-mini-cart__empty h4 {
    margin: 14px 0 6px; font-size: 16px; color: #111827;
}
.mp-mini-cart__empty p {
    margin: 0 0 16px; font-size: 13px; color: #6b7280;
}
.mp-mini-cart__store {
    margin-top: 16px;
    border: 1px solid var(--mp-line, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
}
.mp-mini-cart__store-head {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px;
    background: #f9fafb;
    border-bottom: 1px solid var(--mp-line, #e5e7eb);
    font-size: 12.5px;
    font-weight: 700;
    color: #4b5563;
}
.mp-mini-cart__store-logo {
    width: 22px; height: 22px;
    border-radius: 6px; object-fit: cover;
    border: 1px solid var(--mp-line, #e5e7eb);
    background: #fff;
}
.mp-mini-cart__store-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mp-mini-cart__line {
    display: flex; gap: 10px;
    padding: 10px 12px;
    border-top: 1px solid #f3f4f6;
}
.mp-mini-cart__line:first-child { border-top: 0; }
.mp-mini-cart__line-img {
    width: 50px; height: 50px;
    border-radius: 8px; flex-shrink: 0;
    background: #f3f4f6;
    object-fit: cover;
}
.mp-mini-cart__line-info { flex: 1; min-width: 0; font-size: 12.5px; }
.mp-mini-cart__line-title {
    color: #111827; font-weight: 500;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    line-height: 1.35;
}
.mp-mini-cart__line-meta {
    color: #6b7280; margin-top: 3px;
}
.mp-mini-cart__line-total {
    font-weight: 700; color: var(--mp-primary-dark, #0c6b65);
    font-size: 13px;
    white-space: nowrap;
}
.mp-mini-cart__line-actions {
    display: flex; flex-direction: column; align-items: flex-end;
    gap: 6px; flex-shrink: 0;
}
.mp-mini-cart__line-remove {
    width: 28px; height: 28px;
    background: transparent; border: 0; cursor: pointer;
    border-radius: 6px;
    color: #94a3b8;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    padding: 0;
}
.mp-mini-cart__line-remove:hover {
    background: #fef2f2; color: #dc2626;
}
.mp-mini-cart__line-remove:disabled {
    opacity: .4; cursor: wait;
}
/* Fadeout/slide al remover: NO refrescamos todo el panel, solo
   animamos la linea y la quitamos del DOM. UX suave. */
.mp-mini-cart__line {
    transition: opacity .18s ease, max-height .2s ease, padding .2s ease, margin .2s ease;
    overflow: hidden;
    max-height: 200px;
}
.mp-mini-cart__line.is-removing {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
    margin-bottom: 0;
    pointer-events: none;
}

.mp-mini-cart__foot {
    border-top: 1px solid var(--mp-line, #e5e7eb);
    padding: 14px 18px calc(14px + env(safe-area-inset-bottom));
    flex-shrink: 0;
    background: #fff;
}
.mp-mini-cart__total {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
    font-size: 14px; color: #6b7280;
}
.mp-mini-cart__total strong {
    font-size: 19px; color: var(--mp-ink, #111827); font-weight: 800;
}
.mp-mini-cart__actions {
    display: grid; grid-template-columns: 1fr 1.4fr; gap: 8px;
}
.mp-mini-cart__btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 12px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
    text-decoration: none;
    transition: background .15s, border-color .15s;
    text-align: center;
    min-height: 44px;
}
.mp-mini-cart__btn--ghost {
    background: #fff; color: var(--mp-ink, #111827);
    border: 1.5px solid var(--mp-line, #e5e7eb);
}
.mp-mini-cart__btn--ghost:hover { border-color: var(--mp-primary, #0f8a82); color: var(--mp-primary-dark); }
.mp-mini-cart__btn--primary {
    background: var(--mp-primary, #0f8a82); color: #fff;
    border: 1.5px solid var(--mp-primary, #0f8a82);
}
.mp-mini-cart__btn--primary:hover { background: var(--mp-primary-dark, #0c6b65); }

/* Mobile: drawer slide-up desde abajo, no desde la derecha */
@media (max-width: 600px) {
    .mp-mini-cart__panel {
        top: auto;
        left: 0; right: 0; bottom: 0;
        width: 100%;
        max-height: 88vh;
        border-radius: 16px 16px 0 0;
        transform: translateY(105%);
    }
    .mp-mini-cart.is-open .mp-mini-cart__panel { transform: translateY(0); }
    .mp-mini-cart__head {
        padding: 14px 16px;
        position: relative;
    }
    /* Pull-handle visual indicator */
    .mp-mini-cart__head::before {
        content: '';
        position: absolute;
        top: 6px; left: 50%;
        transform: translateX(-50%);
        width: 40px; height: 4px;
        background: #d1d5db;
        border-radius: 999px;
    }
}
</style>

<style>
.mp-acc-menu { position: relative; }
.mp-acc-menu__btn {
    background: transparent; border: 0; cursor: pointer;
    padding: 6px 10px 6px 6px;
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--mp-ink, #111827); font-weight: 600; font-size: 14px;
    border-radius: 999px;
    transition: background .15s;
    -webkit-tap-highlight-color: transparent;
}
.mp-acc-menu__btn:hover { background: var(--mp-line-soft, #f1f5f9); }
.mp-acc-menu__chev { color: #94a3b8; transition: transform .15s; flex-shrink: 0; }
.mp-acc-menu.is-open .mp-acc-menu__chev { transform: rotate(180deg); }
.mp-acc-avatar {
    width: 30px; height: 30px;
    border-radius: 999px;
    background: linear-gradient(135deg, #0f8a82, #0a6f68);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
    flex-shrink: 0;
}
.mp-acc-avatar--lg { width: 42px; height: 42px; font-size: 16px; }

.mp-acc-menu__panel {
    position: absolute; top: calc(100% + 8px); right: 0;
    min-width: 240px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 12px 32px -8px rgba(15,23,42,.18);
    padding: 6px;
    z-index: 1050;
    opacity: 0; visibility: hidden;
    transform: translateY(-4px);
    transition: opacity .15s, transform .15s, visibility .15s;
}
.mp-acc-menu.is-open .mp-acc-menu__panel {
    opacity: 1; visibility: visible; transform: translateY(0);
}
.mp-acc-menu__head {
    display: flex; gap: 12px; align-items: center;
    padding: 12px 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 6px;
}
.mp-acc-menu__head-info { display: flex; flex-direction: column; min-width: 0; }
.mp-acc-menu__head-info strong { font-size: 14px; color: #0f172a; }
.mp-acc-menu__head-info span { font-size: 12.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.mp-acc-menu__panel a,
.mp-acc-menu__logout {
    display: block; width: 100%;
    padding: 10px 12px;
    font-size: 14px; color: #0f172a; font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    text-align: left;
    background: transparent; border: 0; cursor: pointer;
    transition: background .12s;
}
.mp-acc-menu__panel a:hover { background: #f0fdfa; color: #0c6b65; }
.mp-acc-menu__logout {
    margin-top: 4px;
    border-top: 1px solid #f1f5f9;
    color: #b91c1c; font-weight: 600;
}
.mp-acc-menu__logout:hover { background: #fef2f2; }

/* Mobile: dropdown se vuelve bottom-sheet con backdrop */
@media (max-width: 640px) {
    .mp-acc-menu.is-open::before {
        content: '';
        position: fixed; inset: 0;
        background: rgba(15,23,42,.45);
        z-index: 1049;
    }
    .mp-acc-menu__panel {
        position: fixed;
        top: auto; right: 0; left: 0; bottom: 0;
        min-width: 0;
        border-radius: 16px 16px 0 0;
        padding: 8px 12px calc(16px + env(safe-area-inset-bottom));
        transform: translateY(100%);
        transition: transform .25s ease, visibility .25s, opacity .25s;
    }
    .mp-acc-menu.is-open .mp-acc-menu__panel { transform: translateY(0); }
    .mp-acc-menu__head { padding: 14px 8px 16px; }
    .mp-acc-menu__panel a,
    .mp-acc-menu__logout { padding: 14px 12px; font-size: 15px; }
}
</style>
