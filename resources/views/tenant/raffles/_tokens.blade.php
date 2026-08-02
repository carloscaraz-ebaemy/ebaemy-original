{{--
    Tokens y componentes CSS del módulo de Sorteos.
    Mismo criterio que los `--sh-*` del módulo de Envíos: neutros tintados
    hacia el acento del módulo (violeta) para que todo el módulo se vea como
    un sistema y no como hex sueltos. Al tocar estas vistas, usar los tokens.
--}}
<style>
    #rfApp {
        --rf-ink:#111827; --rf-muted:#6b7280; --rf-faint:#9ca3af;
        --rf-line:#e7e5f0; --rf-line-soft:#f1eff8; --rf-track:#faf9fd;
        --rf-hover:#f6f4fc; --rf-surface:#ffffff;
        --rf-brand:#7c3aed; --rf-brand-weak:#f5f1ff; --rf-brand-line:#ddd2fb; --rf-brand-ink:#5b21b6;
        --rf-gold:#b45309; --rf-gold-weak:#fef6e7;
        color:var(--rf-ink);
    }

    /* ── Encabezado ─────────────────────────────────────────────── */
    #rfApp .rf-head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-start;
        justify-content:space-between; margin-bottom:1rem; }
    #rfApp .rf-title { font-size:1.15rem; font-weight:700; margin:0; letter-spacing:-.01em; }
    #rfApp .rf-sub { font-size:.82rem; color:var(--rf-muted); margin:.15rem 0 0; }
    #rfApp .rf-actions { display:flex; flex-wrap:wrap; gap:.4rem; }

    /* ── Botones ────────────────────────────────────────────────── */
    #rfApp .rf-btn { display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--rf-line);
        background:var(--rf-surface); color:var(--rf-ink); border-radius:9px; padding:.42rem .7rem;
        font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer; transition:.15s; }
    #rfApp .rf-btn:hover { background:var(--rf-hover); border-color:var(--rf-brand-line); color:var(--rf-brand-ink); }
    #rfApp .rf-btn--primary { background:var(--rf-brand); border-color:var(--rf-brand); color:#fff; }
    #rfApp .rf-btn--primary:hover { background:var(--rf-brand-ink); border-color:var(--rf-brand-ink); color:#fff; }
    #rfApp .rf-btn--ghost { background:transparent; }
    #rfApp .rf-btn--danger { color:#b91c1c; border-color:#fecaca; }
    #rfApp .rf-btn--danger:hover { background:#fef2f2; border-color:#fca5a5; color:#991b1b; }
    #rfApp .rf-btn[disabled], #rfApp .rf-btn.is-disabled { opacity:.5; pointer-events:none; }

    /* ── Tarjetas de métricas ───────────────────────────────────── */
    #rfApp .rf-kpis { display:grid; gap:.6rem; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));
        margin-bottom:1rem; }
    #rfApp .rf-kpi { background:var(--rf-surface); border:1px solid var(--rf-line); border-radius:13px;
        padding:.7rem .85rem; }
    #rfApp .rf-kpi__label { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em;
        color:var(--rf-faint); font-weight:700; }
    #rfApp .rf-kpi__value { font-size:1.45rem; font-weight:700; line-height:1.15; margin-top:.15rem; }
    #rfApp .rf-kpi__hint { font-size:.72rem; color:var(--rf-muted); }
    #rfApp .rf-kpi--brand { background:var(--rf-brand-weak); border-color:var(--rf-brand-line); }
    #rfApp .rf-kpi--brand .rf-kpi__value { color:var(--rf-brand-ink); }
    #rfApp .rf-kpi--gold { background:var(--rf-gold-weak); border-color:#fde3b0; }
    #rfApp .rf-kpi--gold .rf-kpi__value { color:var(--rf-gold); }

    /* ── Superficie / tarjetas ──────────────────────────────────── */
    #rfApp .rf-card { background:var(--rf-surface); border:1px solid var(--rf-line); border-radius:14px;
        overflow:hidden; margin-bottom:1rem; }
    #rfApp .rf-card__head { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
        justify-content:space-between; padding:.7rem .9rem; border-bottom:1px solid var(--rf-line-soft); }
    #rfApp .rf-card__title { font-size:.9rem; font-weight:700; margin:0; }
    #rfApp .rf-card__body { padding:.9rem; }

    /* ── Tabla ──────────────────────────────────────────────────── */
    #rfApp .rf-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.83rem; }
    #rfApp .rf-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em;
        color:var(--rf-faint); font-weight:700; padding:.55rem .7rem; background:var(--rf-track);
        border-bottom:1px solid var(--rf-line); white-space:nowrap; }
    #rfApp .rf-table td { padding:.6rem .7rem; border-bottom:1px solid var(--rf-line-soft); vertical-align:middle; }
    #rfApp .rf-table tbody tr:hover { background:var(--rf-hover); }
    #rfApp .rf-table .rf-strong { font-weight:600; }
    #rfApp .rf-empty { padding:2.2rem 1rem; text-align:center; color:var(--rf-muted); font-size:.85rem; }

    /* ── Píldoras de estado ─────────────────────────────────────── */
    #rfApp .rf-pill { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:700;
        padding:.2rem .55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; }
    #rfApp .rf-pill--draft { background:#f3f4f6; color:#4b5563; border-color:#e5e7eb; }
    #rfApp .rf-pill--active { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
    #rfApp .rf-pill--finished { background:var(--rf-brand-weak); color:var(--rf-brand-ink); border-color:var(--rf-brand-line); }
    #rfApp .rf-pill--cancelled { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
    #rfApp .rf-pill--invited { background:#fffbeb; color:#b45309; border-color:#fde68a; }
    #rfApp .rf-pill--accepted { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
    #rfApp .rf-pill--declined { background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }
    #rfApp .rf-pill--soon { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
    #rfApp .rf-pill--running { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
    #rfApp .rf-pill--over { background:#f3f4f6; color:#4b5563; border-color:#e5e7eb; }

    /* ── Formulario ─────────────────────────────────────────────── */
    #rfApp .rf-section { margin-bottom:1.15rem; }
    #rfApp .rf-section__label { display:flex; align-items:center; gap:.6rem; font-size:.74rem;
        text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:var(--rf-brand-ink);
        margin-bottom:.6rem; }
    #rfApp .rf-section__label::after { content:''; flex:1; height:1px; background:var(--rf-line); }
    #rfApp .rf-field { margin-bottom:.75rem; }
    #rfApp .rf-field label { display:block; font-size:.78rem; font-weight:600; margin-bottom:.25rem; }
    #rfApp .rf-input, #rfApp select.rf-input, #rfApp textarea.rf-input {
        width:100%; border:1px solid var(--rf-line); border-radius:9px; padding:.45rem .6rem;
        font-size:.85rem; background:var(--rf-surface); color:var(--rf-ink); }
    #rfApp .rf-input:focus { outline:none; border-color:var(--rf-brand); box-shadow:0 0 0 3px var(--rf-brand-weak); }
    #rfApp .rf-note { font-size:.74rem; color:var(--rf-muted); margin-top:.2rem; }
    #rfApp .rf-check { display:flex; align-items:flex-start; gap:.45rem; font-size:.82rem; margin-bottom:.35rem; }
    #rfApp .rf-check input { margin-top:.18rem; }

    /* ── Premio ─────────────────────────────────────────────────── */
    #rfApp .rf-prize { display:flex; gap:.9rem; align-items:flex-start; flex-wrap:wrap; }
    #rfApp .rf-prize__img { width:150px; height:150px; object-fit:cover; border-radius:12px;
        border:1px solid var(--rf-line); background:var(--rf-track); }
    #rfApp .rf-thumbs { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; }
    #rfApp .rf-thumb { position:relative; }
    #rfApp .rf-thumb img { width:64px; height:64px; object-fit:cover; border-radius:9px; border:1px solid var(--rf-line); }
    #rfApp .rf-thumb label { position:absolute; inset:auto 0 0 0; background:rgba(17,24,39,.75); color:#fff;
        font-size:.62rem; text-align:center; padding:.1rem 0; border-radius:0 0 9px 9px; cursor:pointer; }

    /* ── Vista previa de imagen (antes de guardar) ──────────────── */
    #rfApp .rf-preview { margin-top:.5rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    #rfApp .rf-preview img { width:150px; height:150px; object-fit:cover; border-radius:12px;
        border:1px solid var(--rf-line); background:var(--rf-track); }
    #rfApp .rf-preview--sm img { width:74px; height:74px; border-radius:10px; }
    #rfApp .rf-preview__tag { font-size:.7rem; color:var(--rf-muted); }
    #rfApp .rf-preview__new { font-size:.7rem; font-weight:700; color:var(--rf-brand-ink);
        background:var(--rf-brand-weak); border:1px solid var(--rf-brand-line);
        border-radius:999px; padding:.1rem .5rem; }
    #rfApp .rf-preview__err { font-size:.74rem; color:#b91c1c; }

    /* ── Opciones de premio ─────────────────────────────────────── */
    #rfApp .rf-opt { display:flex; gap:.6rem; align-items:flex-start; padding:.6rem;
        border:1px solid var(--rf-line); border-radius:12px; margin-bottom:.5rem;
        background:var(--rf-surface); }
    #rfApp .rf-opt__img { flex:0 0 auto; width:120px; }
    #rfApp .rf-opt__img .rf-input { font-size:.7rem; padding:.25rem; margin-top:.3rem; }
    #rfApp .rf-opt__tx { flex:1; min-width:0; }
    #rfApp .rf-opt .js-opt-del { flex:0 0 auto; padding:.3rem .55rem; }

    /* ── Selector de origen de participantes ────────────────────── */
    #rfApp .rf-sources { display:grid; gap:.5rem; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); }
    #rfApp .rf-source { display:flex; gap:.6rem; align-items:flex-start; cursor:pointer; margin:0;
        border:1px solid var(--rf-line); border-radius:12px; padding:.65rem .75rem; background:var(--rf-surface);
        transition:.15s; }
    #rfApp .rf-source:hover { border-color:var(--rf-brand-line); background:var(--rf-hover); }
    #rfApp .rf-source input { margin-top:.2rem; flex-shrink:0; }
    #rfApp .rf-source:has(input:checked) { border-color:var(--rf-brand); background:var(--rf-brand-weak);
        box-shadow:0 0 0 3px var(--rf-brand-weak); }
    #rfApp .rf-source__icon { font-size:1.15rem; line-height:1.2; }
    #rfApp .rf-source__body { display:flex; flex-direction:column; gap:.1rem; min-width:0; }
    #rfApp .rf-source__name { font-size:.85rem; font-weight:700; }
    #rfApp .rf-source__desc { font-size:.73rem; color:var(--rf-muted); line-height:1.35; }
    #rfApp .rf-source.is-disabled { opacity:.5; cursor:not-allowed; background:var(--rf-track); }
    #rfApp .rf-source.is-disabled:hover { border-color:var(--rf-line); background:var(--rf-track); }

    /* ── Vista previa del universo ──────────────────────────────── */
    #rfApp .rf-preview { display:grid; gap:.5rem; grid-template-columns:repeat(auto-fill, minmax(135px, 1fr)); }
    #rfApp .rf-stat { border:1px solid var(--rf-line); border-radius:11px; padding:.6rem .7rem; background:var(--rf-track); }
    #rfApp .rf-stat__value { font-size:1.25rem; font-weight:700; line-height:1.15; }
    #rfApp .rf-stat__label { font-size:.7rem; color:var(--rf-muted); font-weight:600; }
    #rfApp .rf-stat--ok { background:#ecfdf5; border-color:#a7f3d0; }
    #rfApp .rf-stat--ok .rf-stat__value { color:#047857; }
    #rfApp .rf-stat--warn { background:#fffbeb; border-color:#fde68a; }
    #rfApp .rf-stat--warn .rf-stat__value { color:#b45309; }

    /* Enlace público copiable */
    #rfApp .rf-link { display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;
        background:var(--rf-brand-weak); border:1px solid var(--rf-brand-line); border-radius:11px;
        padding:.55rem .7rem; }
    #rfApp .rf-link code { font-size:.78rem; color:var(--rf-brand-ink); background:transparent;
        word-break:break-all; flex:1; min-width:180px; }

    /* ── Ganador ────────────────────────────────────────────────── */
    #rfApp .rf-winner { display:flex; gap:.85rem; align-items:center; flex-wrap:wrap;
        background:linear-gradient(135deg, var(--rf-gold-weak), #fffdf7);
        border:1px solid #fde3b0; border-radius:13px; padding:.85rem; margin-bottom:.6rem; }
    #rfApp .rf-winner__img { width:74px; height:74px; object-fit:cover; border-radius:11px; border:1px solid #fde3b0; }
    #rfApp .rf-winner__name { font-size:1rem; font-weight:700; }
    #rfApp .rf-winner__meta { font-size:.76rem; color:var(--rf-muted); }

    @media (max-width: 640px) {
        #rfApp .rf-kpis { grid-template-columns:repeat(2, minmax(0,1fr)); }
        #rfApp .rf-kpi__value { font-size:1.2rem; }
        #rfApp .rf-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        #rfApp .rf-table { min-width:640px; }
    }
</style>
