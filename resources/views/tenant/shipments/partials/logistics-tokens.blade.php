{{--
    Tokens de la capa logística (lotes, dashboard, bitácora).
    Reutilizan la paleta `--sh-*` del panel de Envíos y añaden los colores de
    MODALIDAD acordados: azul provincia, naranja Lima, verde recojo.
    Al tocar estas pantallas, usar estos tokens y no hex sueltos.
--}}
<style>
    .lg-app {
        --sh-ink:#111827; --sh-muted:#6b7280; --sh-faint:#9ca3af;
        --sh-line:#e5e7eb; --sh-line-soft:#f3f4f6; --sh-track:#fafafa;
        --sh-hover:#f5f6fb; --sh-surface:#ffffff;
        --sh-brand:#4f46e5; --sh-brand-weak:#eef2ff; --sh-brand-line:#c7d2fe; --sh-brand-ink:#3730a3;
        /* Modalidades */
        --mod-prov:#2563eb; --mod-prov-bg:#eff6ff; --mod-prov-line:#bfdbfe;
        --mod-lima:#ea580c; --mod-lima-bg:#fff7ed; --mod-lima-line:#fed7aa;
        --mod-tienda:#16a34a; --mod-tienda-bg:#f0fdf4; --mod-tienda-line:#bbf7d0;
        color:var(--sh-ink);
    }

    .lg-head { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-start;
        justify-content:space-between; margin-bottom:1rem; }
    .lg-title { font-size:1.15rem; font-weight:700; margin:0; letter-spacing:-.01em; }
    .lg-sub { font-size:.82rem; color:var(--sh-muted); margin:.15rem 0 0; }
    .lg-actions { display:flex; flex-wrap:wrap; gap:.4rem; }

    .lg-btn { display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--sh-line);
        background:var(--sh-surface); color:var(--sh-ink); border-radius:9px; padding:.42rem .7rem;
        font-size:.82rem; font-weight:600; text-decoration:none; cursor:pointer; transition:.15s; }
    .lg-btn:hover { background:var(--sh-hover); border-color:var(--sh-brand-line); color:var(--sh-brand-ink); }
    .lg-btn--primary { background:var(--sh-brand); border-color:var(--sh-brand); color:#fff; }
    .lg-btn--primary:hover { background:var(--sh-brand-ink); border-color:var(--sh-brand-ink); color:#fff; }
    .lg-btn--danger { color:#b91c1c; border-color:#fecaca; }
    .lg-btn--danger:hover { background:#fef2f2; border-color:#fca5a5; color:#991b1b; }
    .lg-btn[disabled], .lg-btn.is-disabled { opacity:.5; pointer-events:none; }

    /* Tarjetas de indicadores */
    .lg-kpis { display:grid; gap:.6rem; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));
        margin-bottom:1rem; }
    .lg-kpi { background:var(--sh-surface); border:1px solid var(--sh-line); border-radius:13px;
        padding:.7rem .85rem; text-decoration:none; color:inherit; display:block; transition:.15s; }
    a.lg-kpi:hover { border-color:var(--sh-brand-line); background:var(--sh-hover); }
    .lg-kpi__label { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em;
        color:var(--sh-faint); font-weight:700; }
    .lg-kpi__value { font-size:1.45rem; font-weight:700; line-height:1.15; margin-top:.15rem; }
    .lg-kpi__hint { font-size:.72rem; color:var(--sh-muted); }
    .lg-kpi--prov { background:var(--mod-prov-bg); border-color:var(--mod-prov-line); }
    .lg-kpi--prov .lg-kpi__value { color:var(--mod-prov); }
    .lg-kpi--lima { background:var(--mod-lima-bg); border-color:var(--mod-lima-line); }
    .lg-kpi--lima .lg-kpi__value { color:var(--mod-lima); }
    .lg-kpi--tienda { background:var(--mod-tienda-bg); border-color:var(--mod-tienda-line); }
    .lg-kpi--tienda .lg-kpi__value { color:var(--mod-tienda); }
    .lg-kpi--alert { background:#fef2f2; border-color:#fecaca; }
    .lg-kpi--alert .lg-kpi__value { color:#b91c1c; }
    .lg-kpi--brand { background:var(--sh-brand-weak); border-color:var(--sh-brand-line); }
    .lg-kpi--brand .lg-kpi__value { color:var(--sh-brand-ink); }

    /* Tarjetas contenedoras */
    .lg-card { background:var(--sh-surface); border:1px solid var(--sh-line); border-radius:14px;
        overflow:hidden; margin-bottom:1rem; }
    .lg-card__head { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
        justify-content:space-between; padding:.7rem .9rem; border-bottom:1px solid var(--sh-line-soft); }
    .lg-card__title { font-size:.9rem; font-weight:700; margin:0; }
    .lg-card__body { padding:.9rem; }

    /* Tabla */
    .lg-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.83rem; }
    .lg-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em;
        color:var(--sh-faint); font-weight:700; padding:.55rem .7rem; background:var(--sh-track);
        border-bottom:1px solid var(--sh-line); white-space:nowrap; }
    .lg-table td { padding:.6rem .7rem; border-bottom:1px solid var(--sh-line-soft); vertical-align:middle; }
    .lg-table tbody tr:hover { background:var(--sh-hover); }
    .lg-empty { padding:2rem 1rem; text-align:center; color:var(--sh-muted); font-size:.85rem; }
    .lg-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }

    /* Chips de modalidad y estado */
    .lg-mod { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:700;
        padding:.2rem .55rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; }
    .lg-mod--agencia   { background:var(--mod-prov-bg);   color:var(--mod-prov);   border-color:var(--mod-prov-line); }
    .lg-mod--domicilio { background:var(--mod-lima-bg);   color:var(--mod-lima);   border-color:var(--mod-lima-line); }
    .lg-mod--tienda    { background:var(--mod-tienda-bg); color:var(--mod-tienda); border-color:var(--mod-tienda-line); }

    .lg-pill { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:700;
        padding:.2rem .55rem; border-radius:999px; border:1px solid var(--sh-line);
        background:var(--sh-track); color:var(--sh-muted); white-space:nowrap; }
    .lg-pill--open     { background:#fffbeb; color:#b45309; border-color:#fde68a; }
    .lg-pill--printed  { background:var(--sh-brand-weak); color:var(--sh-brand-ink); border-color:var(--sh-brand-line); }
    .lg-pill--closed   { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
    .lg-pill--cancelled{ background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }

    /* Bitácora */
    .lg-log { list-style:none; margin:0; padding:0; }
    .lg-log li { display:flex; gap:.6rem; padding:.5rem 0; border-bottom:1px solid var(--sh-line-soft);
        font-size:.8rem; }
    .lg-log li:last-child { border-bottom:0; }
    .lg-log__when { color:var(--sh-faint); white-space:nowrap; font-variant-numeric:tabular-nums; }
    .lg-log__body { flex:1; min-width:0; }
    .lg-log__who { color:var(--sh-muted); font-size:.74rem; }
    .lg-log__exc { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:999px;
        font-size:.66rem; font-weight:700; padding:.05rem .4rem; }

    .lg-note { font-size:.74rem; color:var(--sh-muted); }
    .lg-window { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;
        background:var(--sh-brand-weak); border:1px solid var(--sh-brand-line); border-radius:11px;
        padding:.55rem .75rem; font-size:.8rem; color:var(--sh-brand-ink); margin-bottom:1rem; }

    @media (max-width: 640px) {
        .lg-kpis { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .lg-kpi__value { font-size:1.2rem; }
        .lg-table { min-width:680px; }
    }
</style>
