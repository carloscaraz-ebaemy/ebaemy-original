<style>
    /* ───────────── Paginador profesional (override de bootstrap-4 view) ───────────── */
    .mp-pag {
        display: flex; justify-content: center;
        margin: 24px 0 16px;
    }
    .mp-pag nav { width: 100%; }
    .mp-pag .pagination {
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 6px;
        list-style: none;
        margin: 0; padding: 0;
    }
    .mp-pag .page-item { margin: 0; }
    .mp-pag .page-item .page-link,
    .mp-pag .page-item span.page-link {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 36px; height: 36px;
        padding: 0 12px;
        font-size: 13.5px; font-weight: 600;
        color: #374151;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        text-decoration: none;
        transition: background .15s, color .15s, border-color .15s, transform .15s;
    }
    .mp-pag .page-item .page-link:hover {
        background: #f0fdfa;
        border-color: var(--mp-primary, #0f8a82);
        color: var(--mp-primary-dark, #0a6f68);
        transform: translateY(-1px);
    }
    .mp-pag .page-item.active .page-link,
    .mp-pag .page-item.active span.page-link {
        background: linear-gradient(135deg, #0f8a82, #0a6f68);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 10px -4px rgba(15,138,130,.45);
    }
    .mp-pag .page-item.disabled .page-link,
    .mp-pag .page-item.disabled span.page-link {
        background: #f9fafb;
        color: #cbd5e1;
        border-color: #f1f5f9;
        cursor: not-allowed;
    }
    .mp-pag .page-link svg { width: 14px; height: 14px; }
    .mp-pag p { display: none; }

    @media (max-width: 480px) {
        .mp-pag .pagination { gap: 4px; }
        .mp-pag .page-item .page-link { min-width: 32px; height: 32px; padding: 0 9px; font-size: 12.5px; }
    }

    /* ───────────── Cards de producto modernas (SaaS 2026) ───────────── */
    .mp-card {
        position: relative;
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        overflow: hidden;
        text-decoration: none;
        display: flex; flex-direction: column;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .mp-card:hover {
        transform: translateY(-3px);
        border-color: rgba(15,138,130,.35);
        box-shadow: 0 10px 24px -12px rgba(15,138,130,.22), 0 4px 10px -6px rgba(0,0,0,.06);
    }
    .mp-card-img {
        position: relative;
        aspect-ratio: 1 / 1;
        background: #f7f9fb;
        overflow: hidden;
    }
    .mp-card-img img { transition: transform .35s ease; }
    .mp-card:hover .mp-card-img img { transform: scale(1.04); }

    /* Hover-image: muestra la 2da foto al pasar el cursor */
    .mp-card-img-primary,
    .mp-card-img-secondary {
        position: absolute; top: 0; left: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        transition: opacity .25s ease, transform .35s ease;
    }
    .mp-card-img-secondary { opacity: 0; }
    .mp-card-img[data-has-secondary="1"]:hover .mp-card-img-primary { opacity: 0; }
    .mp-card-img[data-has-secondary="1"]:hover .mp-card-img-secondary { opacity: 1; }

    /* Shop name clickable */
    .mp-card-shop-link {
        cursor: pointer;
        text-decoration: none;
        transition: color .12s;
    }
    .mp-card-shop-link:hover {
        color: var(--mp-primary, #10b981);
        text-decoration: underline;
    }

    /* Pill "También en N tiendas" — feature de comparación. Click va a
       /marketplace?q=<titulo> para que el comprador vea todas las opciones. */
    .mp-card-alsoin {
        display: inline-flex; align-items: center; gap: 5px;
        margin-top: 8px;
        padding: 4px 9px 4px 7px;
        font-size: 11.5px; line-height: 1.25; font-weight: 500;
        color: #475569;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        text-decoration: none;
        max-width: 100%;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        transition: background .15s, border-color .15s, color .15s;
    }
    .mp-card-alsoin svg {
        width: 12px; height: 12px;
        color: #94a3b8;
        flex-shrink: 0;
    }
    .mp-card-alsoin strong {
        color: #0f172a;
        font-weight: 700;
    }
    .mp-card-alsoin:hover {
        background: #ecfeff;
        border-color: #67e8f9;
        color: #0e7490;
    }
    .mp-card-alsoin:hover svg { color: #0891b2; }
    /* Si en otra tienda está más barato, lo marcamos en verde para
       generar una micro-señal de "puedes ahorrar". */
    .mp-card-alsoin.is-cheaper {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }
    .mp-card-alsoin.is-cheaper svg { color: #10b981; }
    .mp-card-alsoin.is-cheaper strong { color: #064e3b; }
    .mp-card-alsoin.is-cheaper:hover {
        background: #d1fae5;
        border-color: #6ee7b7;
    }

    /* Sidebar: contador junto al nombre de la tienda */
    .mp-filter-item .mp-filter-count {
        float: right;
        font-size: 11px;
        color: var(--mp-muted, #9ca3af);
        background: rgba(0,0,0,.04);
        padding: 1px 7px;
        border-radius: 999px;
        line-height: 1.4;
    }
    .mp-filter-item.is-active .mp-filter-count {
        background: rgba(255,255,255,.25);
        color: #fff;
    }

    /* Color dots en cards (estilo Falabella) */
    .mp-card-colors {
        display: flex; gap: 5px;
        margin-top: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .mp-card-color-dot {
        width: 16px; height: 16px;
        border-radius: 999px;
        border: 1.5px solid #e5e7eb;
        cursor: pointer;
        transition: transform .12s, border-color .15s, box-shadow .15s;
        flex-shrink: 0;
        display: inline-block;
        position: relative;
        overflow: hidden;
    }
    .mp-card-color-dot:hover {
        border-color: #0a0e1a;
        transform: scale(1.18);
        box-shadow: 0 2px 6px -2px rgba(0,0,0,.18);
    }
    .mp-card-color-dot.is-active {
        border-color: #10b981;
        box-shadow: 0 0 0 2px rgba(16,185,129,.32);
        transform: scale(1.12);
    }
    .mp-card-color-dot--img img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        border-radius: 999px;
    }
    @media (max-width: 480px) {
        .mp-card-color-dot { width: 14px; height: 14px; }
    }

    /* Variant dots/thumbs en cards (legacy fallback) */
    .mp-card-variants {
        display: flex; gap: 4px;
        margin-top: 6px;
        flex-wrap: wrap;
    }
    .mp-card-variant-dot {
        width: 22px; height: 22px;
        border-radius: 6px;
        overflow: hidden;
        border: 1.5px solid #e5e7eb;
        background: #f9fafb;
        cursor: pointer;
        transition: border-color .15s, transform .12s;
        flex-shrink: 0;
    }
    .mp-card-variant-dot img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .15s;
    }
    .mp-card-variant-dot:hover {
        border-color: var(--mp-primary, #0f8a82);
        transform: scale(1.12);
    }
    @media (max-width: 640px) {
        .mp-card-variant-dot { width: 20px; height: 20px; }
    }

    .mp-card-body { padding: 12px 12px 14px; }
    .mp-card-price { font-size: 17px; font-weight: 800; color: #0a0e1a; }
    .mp-card-price-prefix {
        font-size: 11px; font-weight: 600;
        color: #6b7280; text-transform: uppercase;
        letter-spacing: .3px; margin-right: 4px;
    }
    .mp-card-price-old {
        font-size: 12.5px; font-weight: 500;
        color: #9ca3af; text-decoration: line-through;
        margin-left: 6px;
    }
    .mp-badge--offer {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        font-weight: 800;
        letter-spacing: .3px;
    }
    .mp-badge--flash {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: #fff;
        font-weight: 800;
        letter-spacing: .3px;
        animation: mp-flash-pulse 1.6s ease-in-out infinite;
    }
    @keyframes mp-flash-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, .55); }
        50%      { box-shadow: 0 0 0 5px rgba(249, 115, 22, 0); }
    }

    /* Grid 2 cols en móvil, 4-5 cols en desktop */
    .mp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    @media (max-width: 640px) {
        .mp-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .mp-card-body { padding: 10px 10px 12px; }
        .mp-card-title { font-size: 13px; }
        .mp-card-price { font-size: 15px; }
    }
</style>
