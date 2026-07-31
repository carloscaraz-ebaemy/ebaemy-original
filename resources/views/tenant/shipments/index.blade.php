@extends('tenant.layouts.app')

@push('styles')
<style>
    /* ── Modales del módulo: secciones en tarjeta + footer fijo ── */
    #modalEditar .modal-content, #modalNuevoEnvio .modal-content { border:0; border-radius:16px; overflow:hidden;
        box-shadow:0 24px 60px -22px rgba(16,24,40,.4); }
    #modalEditar .modal-header, #modalNuevoEnvio .modal-header { background:#fff; border-bottom:1px solid #eef0f4;
        padding:.95rem 1.15rem; }
    #modalEditar .modal-title, #modalNuevoEnvio .modal-title { font-size:.98rem; font-weight:700; color:#0f172a; }
    #modalEditar .modal-body, #modalNuevoEnvio .modal-body { background:#f7f9fb; padding:.9rem 1.15rem;
        max-height:calc(100vh - 230px); overflow-y:auto; }
    #modalEditar .modal-footer, #modalNuevoEnvio .modal-footer { position:sticky; bottom:0; z-index:2;
        background:#fff; border-top:1px solid #eef0f4; padding:.8rem 1.15rem; }
    /* Sección en tarjeta */
    .sh-fs { background:#fff; border:1px solid #eef0f4; border-radius:12px; padding:.85rem .95rem;
        margin-bottom:.65rem; box-shadow:0 1px 2px rgba(16,24,40,.03); }
    .sh-fs__h { display:flex; align-items:center; gap:7px; font-size:.66rem; font-weight:700; letter-spacing:.07em;
        text-transform:uppercase; color:#8b93a1; margin-bottom:.7rem; }
    .sh-fs__h i { color:#4f46e5; font-size:.8rem; }
    /* Controles compactos */
    #modalEditar .form-label, #modalNuevoEnvio .form-label { font-size:.72rem; font-weight:600; color:#6b7280; margin-bottom:.25rem; }
    #modalEditar .form-control, #modalEditar .form-select, #modalEditar .ubigeo-display,
    #modalNuevoEnvio .form-control, #modalNuevoEnvio .form-select, #modalNuevoEnvio .ubigeo-display {
        font-size:.83rem; padding:.45rem .62rem; border-radius:9px; border-color:#e5e7eb; }
    #modalEditar .form-control:focus, #modalEditar .form-select:focus,
    #modalNuevoEnvio .form-control:focus, #modalNuevoEnvio .form-select:focus {
        border-color:#a5b4fc; box-shadow:0 0 0 3px rgba(79,70,229,.1); }
    .sh-fs__int { margin-left:auto; font-size:.6rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
        color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:5px; padding:.1rem .38rem; cursor:help; }
    .sh-hint { margin-top:.35rem; font-size:.7rem; color:#9aa2af; }
    #modalEditar textarea.form-control, #modalNuevoEnvio textarea.form-control { resize:vertical; min-height:74px; line-height:1.45; }

    /* Desplegable de opcionales */
    .sh-more { display:flex; align-items:center; gap:8px; width:100%; padding:.6rem .9rem; background:#fff;
        border:1px dashed #dfe3e8; border-radius:12px; font-size:.78rem; font-weight:600; color:#6b7280;
        cursor:pointer; transition:border-color .15s ease-out, color .15s ease-out, background .15s ease-out; }
    .sh-more:hover { border-color:#c7d2fe; color:#4f46e5; background:#fafbff; }
    .sh-more .chev { margin-left:auto; font-size:.7rem; transition:transform .2s ease-out; }
    .sh-more[aria-expanded="true"] .chev { transform:rotate(180deg); }

    /* ── Cabecera con presencia ── */
    .sh-head { display:flex; align-items:center; gap:12px; }
    .sh-head__ic { display:flex; align-items:center; justify-content:center; width:42px; height:42px;
        border-radius:13px; font-size:1.05rem; color:var(--sh-brand, #4f46e5);
        background:linear-gradient(145deg,#eef1fe,#dfe4fb); box-shadow:inset 0 0 0 1px #dfe4fb; }
    .sh-head__t { margin:0; font-size:1.08rem; font-weight:700; color:var(--sh-ink, #1f2430); letter-spacing:-.015em; }

    /* ── Avatar de cliente ── */
    .sh-cli { display:flex; align-items:center; gap:9px; }
    .sh-avt { display:flex; align-items:center; justify-content:center; flex:0 0 auto;
        width:30px; height:30px; border-radius:9px; font-size:.7rem; font-weight:700; letter-spacing:.01em; }

    /* ── Tokens del panel (neutros tintados hacia el índigo de marca) ── */
    #shipmentsApp {
        --sh-brand:#4f46e5; --sh-brand-ink:#3730a3; --sh-brand-weak:#eef1fe; --sh-brand-line:#cdd4f8;
        --sh-ink:#1f2430; --sh-muted:#697084; --sh-faint:#9aa1b4;
        --sh-line:#e5e7f0; --sh-line-soft:#eef0f7; --sh-surface:#fff;
        --sh-track:#f1f2f8; --sh-hover:#f5f6fc; --sh-count:#ebedf6;
    }

    /* ── Pestañas de flujo (control segmentado) ── */
    .sh-tabs { display:flex; gap:3px; align-items:center; margin-bottom:14px; padding:4px;
        background:var(--sh-track); border:1px solid var(--sh-line-soft); border-radius:12px;
        overflow-x:auto; scrollbar-width:none; }
    .sh-tabs::-webkit-scrollbar { display:none; }
    .sh-tab { display:inline-flex; align-items:center; gap:8px; white-space:nowrap; text-decoration:none;
        padding:.5rem .85rem; font-size:.82rem; font-weight:600; color:var(--sh-muted); border-radius:9px;
        transition:background .18s cubic-bezier(.2,.8,.2,1), color .18s ease-out, box-shadow .18s ease-out; }
    .sh-tab:hover { color:var(--sh-ink); background:rgba(255,255,255,.6); }
    .sh-tab.is-on { color:var(--sh-brand-ink); background:var(--sh-surface);
        box-shadow:0 1px 1px rgba(31,36,64,.05), 0 6px 16px -9px rgba(79,70,229,.55); }
    .sh-tab__n { font-size:.68rem; font-weight:700; min-width:1.15rem; text-align:center; padding:.11rem .4rem; border-radius:6px;
        background:var(--sh-count); color:var(--sh-muted); font-variant-numeric:tabular-nums; }
    .sh-tab.is-on .sh-tab__n { background:var(--sh-brand); color:#fff; }

    /* ── Prioridad por antigüedad (semáforo) ── */
    .sh-pri { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0 0 14px; }
    .sh-pri__lbl { display:inline-flex; align-items:center; gap:6px; font-size:.7rem; font-weight:700;
        color:var(--sh-muted); text-transform:uppercase; letter-spacing:.05em; }
    .sh-pri__lbl i { color:var(--sh-faint); }
    .sh-pri__chip { display:inline-flex; align-items:center; gap:7px; white-space:nowrap; text-decoration:none;
        padding:.42rem .7rem; font-size:.77rem; font-weight:600; color:var(--sh-muted);
        background:var(--sh-surface); border:1px solid var(--sh-line); border-radius:9px;
        transition:background .16s ease-out, border-color .16s ease-out, color .16s ease-out, box-shadow .16s ease-out; }
    .sh-pri__chip:hover { background:var(--sh-hover); border-color:#d5d9e6; color:var(--sh-ink); }
    /* Filtros rápidos por modalidad: azul provincia, naranja Lima, verde recojo. */
    .sh-quick { flex-wrap:wrap; }
    .sh-quick__dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .sh-quick__dot--agencia   { background:#2563eb; }
    .sh-quick__dot--domicilio { background:#ea580c; }
    .sh-quick__dot--tienda    { background:#16a34a; }
    .sh-quick--agencia.is-on   { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
    .sh-quick--domicilio.is-on { background:#fff7ed; border-color:#fed7aa; color:#c2410c; }
    .sh-quick--tienda.is-on    { background:#f0fdf4; border-color:#bbf7d0; color:#15803d; }
    /* Chip de modalidad dentro de la tabla. */
    .sh-mod { display:inline-flex; align-items:center; gap:.28rem; font-size:.7rem; font-weight:700;
        padding:.14rem .5rem; border-radius:999px; border:1px solid transparent; white-space:nowrap; }
    .sh-mod--agencia   { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
    .sh-mod--domicilio { background:#fff7ed; color:#ea580c; border-color:#fed7aa; }
    .sh-mod--tienda    { background:#f0fdf4; color:#16a34a; border-color:#bbf7d0; }
    /* Fila recién afectada: destello breve para reubicar la vista sin leer. */
    #shipmentsApp .sh-row--just > td { animation: shJust 1.8s ease-out; }
    @keyframes shJust {
        0%   { background:var(--sh-brand-weak); box-shadow:inset 3px 0 0 var(--sh-brand); }
        70%  { background:var(--sh-brand-weak); box-shadow:inset 3px 0 0 var(--sh-brand); }
        100% { background:transparent; box-shadow:none; }
    }
    .sh-batch { font-size:.68rem; color:var(--sh-faint); font-weight:600; }
    .sh-batch--on { color:var(--sh-brand-ink); }
    .sh-pri__chip b { font-size:.67rem; font-weight:800; padding:.06rem .4rem; border-radius:999px;
        background:var(--sh-count); color:var(--sh-muted); font-variant-numeric:tabular-nums; }
    .sh-pri__dot { width:9px; height:9px; border-radius:50%; flex:0 0 auto; }
    .sh-pri__dot--warn { background:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,.16); }
    .sh-pri__dot--danger { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.16); }
    .sh-pri__chip.is-on { background:var(--sh-brand-weak); border-color:var(--sh-brand-line); color:var(--sh-brand-ink); }
    .sh-pri__chip--warn.is-on { background:#fff3e8; border-color:#fed3a8; color:#c2410c; }
    .sh-pri__chip--warn.is-on b { background:#ea580c; color:#fff; }
    .sh-pri__chip--danger.is-on { background:#fdecec; border-color:#fac6c6; color:#b91c1c; }
    .sh-pri__chip--danger.is-on b { background:#dc2626; color:#fff; }
    .sh-pri__hint { font-size:.72rem; color:var(--sh-faint); margin-left:auto; }
    .sh-pri__hint b { color:var(--sh-muted); font-variant-numeric:tabular-nums; }

    /* ── Aviso de vencidos (banner proactivo) ── */
    .sh-overdue { display:flex; align-items:center; gap:12px; margin:0 0 14px; padding:11px 14px;
        border:1px solid #f7cccc; background:#fdf0f0; border-radius:12px; text-decoration:none;
        transition:background .15s, border-color .15s; }
    .sh-overdue:hover { background:#fce4e4; border-color:#f2b8b8; }
    .sh-overdue__ic { flex:0 0 auto; width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center;
        background:#f8d4d4; color:#b91c1c; font-size:.9rem; }
    .sh-overdue__tx { flex:1; font-size:.82rem; color:#7f1d1d; line-height:1.35; }
    .sh-overdue__tx b { color:#b91c1c; }
    .sh-overdue__cta { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
        font-size:.78rem; font-weight:700; color:#fff; background:#dc2626; padding:.4rem .7rem; border-radius:8px; }
    .sh-overdue:hover .sh-overdue__cta { background:#b91c1c; }
    @media (max-width:640px) { .sh-overdue__cta span, .sh-overdue { } }

    /* ── Semáforo de antigüedad en la fila ── */
    .sh-age { display:inline-flex; align-items:center; gap:5px; margin-top:4px; padding:.12rem .44rem;
        font-size:.7rem; font-weight:700; border-radius:999px; white-space:nowrap; line-height:1.5;
        font-variant-numeric:tabular-nums; }
    .sh-age__dot { width:7px; height:7px; border-radius:50%; background:currentColor; flex:0 0 auto; }
    /* El resalte de fila por antigüedad vive en la sección de tabla (tinte
       sutil, sin franja lateral). El badge de la columna Fecha lleva el detalle. */

    /* ── Barra de herramientas ── */
    .sh-tools { display:flex; align-items:center; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
    .sh-search { position:relative; display:flex; align-items:center; flex:1 1 240px; max-width:380px; margin:0; }
    .sh-search__ic { position:absolute; left:11px; font-size:.76rem; color:var(--sh-faint); pointer-events:none; }
    .sh-search input { width:100%; padding:.48rem 1.7rem .48rem 2rem; font-size:.8rem; color:var(--sh-ink);
        background:var(--sh-surface); border:1px solid var(--sh-line); border-radius:9px;
        transition:border-color .15s ease-out, box-shadow .15s ease-out; }
    .sh-search input::placeholder { color:var(--sh-faint); }
    .sh-search input:focus { outline:none; border-color:var(--sh-brand-line); box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    .sh-search button { position:absolute; right:7px; border:0; background:none; color:var(--sh-faint);
        font-size:.78rem; cursor:pointer; padding:2px 4px; line-height:1; }
    .sh-search button:hover { color:var(--sh-muted); }

    /* ── Chips (Sin guía · Fecha · Filtros) ── */
    .sh-chip { display:inline-flex; align-items:center; gap:7px; white-space:nowrap; cursor:pointer; text-decoration:none;
        padding:.48rem .74rem; font-size:.78rem; font-weight:600; color:var(--sh-muted);
        background:var(--sh-surface); border:1px solid var(--sh-line); border-radius:9px;
        transition:background .15s ease-out, border-color .15s ease-out, color .15s ease-out; }
    .sh-chip i { color:var(--sh-faint); transition:color .15s ease-out; }
    .sh-chip:hover { background:var(--sh-hover); border-color:#d5d9e6; color:var(--sh-ink); }
    .sh-chip b { font-size:.68rem; font-weight:800; padding:.06rem .38rem; border-radius:999px;
        background:var(--sh-count); color:var(--sh-muted); font-variant-numeric:tabular-nums; }
    .sh-chip.is-on { background:var(--sh-brand-weak); border-color:var(--sh-brand-line); color:var(--sh-brand-ink); }
    .sh-chip.is-on i { color:var(--sh-brand); }
    .sh-chip.is-on b { background:var(--sh-brand); color:#fff; }

    /* ── Popover de filtros avanzados ── */
    .sh-filters { min-width:300px; padding:14px; border:1px solid var(--sh-line); border-radius:14px; }
    .sh-filters__g { padding:6px 0; }
    .sh-filters__g + .sh-filters__g { border-top:1px solid var(--sh-line-soft); margin-top:4px; }
    .sh-filters__t { display:block; font-size:.64rem; font-weight:700; letter-spacing:.07em;
        text-transform:uppercase; color:var(--sh-faint); margin-bottom:7px; }
    .sh-filters__r { display:flex; flex-wrap:wrap; gap:5px; }
    .sh-mini { display:inline-flex; align-items:center; gap:5px; padding:.34rem .58rem; font-size:.75rem;
        font-weight:600; color:var(--sh-muted); text-decoration:none; background:var(--sh-track);
        border:1px solid transparent; border-radius:8px; transition:background .14s, color .14s, border-color .14s; }
    .sh-mini:hover { background:var(--sh-hover); color:var(--sh-ink); }
    .sh-mini.is-on { background:var(--sh-brand-weak); border-color:var(--sh-brand-line); color:var(--sh-brand-ink); }
    .sh-mini b { font-size:.66rem; color:var(--sh-faint); font-variant-numeric:tabular-nums; }
    .sh-mini.is-on b { color:var(--sh-brand); }
    .sh-mini.is-clear { background:none; border-color:transparent; color:var(--sh-faint); }

    /* ── Calendario de rango (un solo campo: clic inicio → clic fin) ── */
    .sh-cal-wrap { min-width:288px; padding:14px; border-radius:16px; }
    .sh-cal { display:flex; flex-direction:column; gap:11px; user-select:none; }
    .sh-cal__head { display:flex; align-items:center; justify-content:space-between; }
    .sh-cal__title { font-size:.88rem; font-weight:700; color:var(--sh-ink); text-transform:capitalize; }
    .sh-cal__nav { width:30px; height:30px; border:1px solid var(--sh-line); background:var(--sh-surface); border-radius:9px;
        color:var(--sh-muted); cursor:pointer; font-size:.72rem; display:flex; align-items:center; justify-content:center;
        transition:background .15s, color .15s, border-color .15s; }
    .sh-cal__nav:hover { background:var(--sh-brand-weak); color:var(--sh-brand-ink); border-color:var(--sh-brand-line); }
    .sh-cal__dow { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; }
    .sh-cal__dow span { text-align:center; font-size:.62rem; font-weight:700; color:var(--sh-faint); text-transform:uppercase; padding:2px 0; }
    .sh-cal__dow span:nth-child(6), .sh-cal__dow span:nth-child(7) { color:#c0c5d4; }
    .sh-cal__grid { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; }
    .sh-cal__d { display:flex; align-items:center; justify-content:center; height:33px; padding:0; font-size:.79rem;
        font-weight:600; color:var(--sh-ink); background:none; border:none; border-radius:9px; cursor:pointer;
        font-variant-numeric:tabular-nums; transition:background .12s ease-out, color .12s ease-out; }
    button.sh-cal__d:hover { background:var(--sh-brand-weak); color:var(--sh-brand-ink); }
    .sh-cal__d.is-empty { visibility:hidden; }
    .sh-cal__d.is-off { color:#c4c9d8; cursor:default; }
    .sh-cal__d.is-today:not(.is-start):not(.is-end):not(.is-in) { color:var(--sh-brand); font-weight:800;
        box-shadow:inset 0 0 0 1.5px var(--sh-brand-line); }
    .sh-cal__d.is-in { background:var(--sh-brand-weak); color:var(--sh-brand-ink); border-radius:0; }
    .sh-cal__d.is-start, .sh-cal__d.is-end { background:var(--sh-brand); color:#fff;
        box-shadow:0 5px 12px -5px rgba(79,70,229,.6); }
    .sh-cal__d.is-start { border-radius:9px 0 0 9px; }
    .sh-cal__d.is-end { border-radius:0 9px 9px 0; }
    .sh-cal__d.is-start.is-end { border-radius:9px; }
    button.sh-cal__d.is-start:hover, button.sh-cal__d.is-end:hover { background:var(--sh-brand-ink); color:#fff; }
    .sh-cal__foot { display:flex; align-items:center; justify-content:space-between; gap:8px;
        border-top:1px solid var(--sh-line-soft); padding-top:10px; }
    .sh-cal__hint { font-size:.73rem; color:var(--sh-muted); }
    .sh-cal__clear { font-size:.72rem; font-weight:700; color:#dc2626; text-decoration:none; white-space:nowrap; }
    .sh-cal__clear:hover { color:#b91c1c; text-decoration:underline; }
    .sh-filters__clear { display:block; margin-top:10px; padding-top:10px; border-top:1px solid var(--sh-line-soft);
        font-size:.75rem; font-weight:700; color:#dc2626; text-decoration:none; text-align:center; }
    .sh-filters__clear:hover { color:#b91c1c; }

    /* ── Modal "ojo": ficha de lo que registró el cliente ── */
    .sh-view { border:none; border-radius:18px; overflow:hidden; }
    .sh-view__head { align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid var(--sh-line-soft);
        background:linear-gradient(180deg,var(--sh-track),var(--sh-surface)); }
    .sh-view__id { display:flex; flex-direction:column; gap:1px; margin-right:auto; }
    .sh-view__code { font-size:.72rem; font-weight:700; letter-spacing:.02em; color:var(--sh-brand-ink);
        font-variant-numeric:tabular-nums; }
    .sh-view__name { font-size:1.02rem; font-weight:700; color:var(--sh-ink); }
    .sh-view__type { font-size:.68rem; font-weight:700; padding:.24rem .6rem; border-radius:999px; white-space:nowrap; }
    .sh-view__type.is-dom { background:#f3e8ff; color:#7c3aed; } .sh-view__type.is-ag { background:#dbeafe; color:#1d4ed8; }
    .sh-view__body { padding:8px 20px 16px; }
    .sh-view__sec { padding:14px 0 4px; }
    .sh-view__sec + .sh-view__sec { border-top:1px solid var(--sh-line-soft); }
    .sh-view__t { font-size:.64rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
        color:var(--sh-faint); margin-bottom:8px; }
    .sh-view__row { display:flex; gap:16px; padding:6px 0; font-size:.86rem; align-items:baseline; }
    .sh-view__row .k { flex:0 0 38%; color:var(--sh-muted); }
    .sh-view__row .v { flex:1; color:var(--sh-ink); font-weight:600; word-break:break-word; }
    .sh-view__foot { border-top:1px solid var(--sh-line-soft); gap:8px; flex-wrap:wrap; }
    .sh-view__wa { background:#25d366; border-color:#25d366; color:#fff; }
    .sh-view__wa:hover { background:#1eb257; color:#fff; }
    @media (max-width:768px) { .sh-search { max-width:none; flex:1 1 100%; }
        .sh-view__row { flex-direction:column; gap:2px; } .sh-view__row .k { flex:none; } }

    /* Filtros: más compactos y con feedback dinámico */
    #shipmentsApp .btn-sm { padding-top:.22rem; padding-bottom:.22rem; transition:transform .12s ease, box-shadow .12s ease, background .12s ease; }
    #shipmentsApp .btn-sm:hover { transform:translateY(-1px); box-shadow:0 4px 10px -5px rgba(0,0,0,.3); }
    #shipmentsApp .btn-sm:active { transform:scale(.96); }
    #shipmentsApp .mb-3 { margin-bottom:.55rem !important; }
    /* Puerta de pago: un solo control, tinte suave (el acento no debe gritar en cada fila) */
    .sh-pay-gate { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; cursor:pointer;
        font-size:.76rem; font-weight:600; line-height:1; padding:.45rem .7rem; border-radius:7px;
        color:#92400e; background:#fffbeb; border:1px solid #fde68a;
        transition:background .15s ease-out, border-color .15s ease-out; }
    .sh-pay-gate:hover { background:#fef3c7; border-color:#fcd34d; }
    .sh-pay-gate:active { transform:scale(.98); }
    .sh-paid { display:inline-flex; align-items:center; gap:4px; margin-top:3px; padding:0; border:0; background:none;
        font-size:.67rem; color:#9ca3af; cursor:pointer; }
    .sh-paid:hover { color:#6b7280; text-decoration:underline; }
    /* Acciones de fila: una principal + menú discreto (misma altura, tintes suaves) */
    .sh-actions { display:inline-flex; align-items:center; gap:6px; justify-content:flex-end; }
    .sh-act { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; cursor:pointer; text-decoration:none;
        font-size:.76rem; font-weight:600; line-height:1; padding:.45rem .7rem; border-radius:7px;
        border:1px solid transparent; transition:background .15s ease-out, border-color .15s ease-out; }
    .sh-act--primary { color:var(--sh-brand-ink); background:var(--sh-brand-weak); border-color:var(--sh-brand-line); }
    .sh-act--primary:hover { background:#e3e8fd; color:#312e81; }
    .sh-act--ok { color:#166534; background:#eafaf0; border-color:#bbf7d0; }
    .sh-act--ok:hover { background:#dcfce7; color:#14532d; }
    .sh-act--ghost { color:var(--sh-faint); background:transparent; border-color:var(--sh-line); padding:.45rem .58rem; }
    .sh-act--ghost:hover { background:var(--sh-hover); color:var(--sh-muted); }
    .sh-act[disabled], .sh-act.is-off { opacity:.4; pointer-events:none; }
    /* ── Tabla: acabado de producto, no Bootstrap por defecto ── */
    #shipmentsApp .card { border:1px solid var(--sh-line); border-radius:16px; overflow:hidden;
        box-shadow:0 1px 2px rgba(31,36,64,.04), 0 8px 18px -12px rgba(31,36,64,.16),
                   0 30px 50px -34px rgba(31,36,64,.3); }
    #shipmentsApp .table { margin:0; }
    #shipmentsApp .table > thead > tr > th { background:var(--sh-track); color:var(--sh-faint);
        font-size:.655rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
        padding:.75rem .8rem; border-bottom:1px solid var(--sh-line-soft); white-space:nowrap; }
    #shipmentsApp .table > tbody > tr > td { padding:.85rem .8rem; border-top:1px solid var(--sh-line-soft);
        vertical-align:middle; font-size:.815rem; color:var(--sh-muted); }
    #shipmentsApp .table > tbody > tr:first-child > td { border-top:0; }
    #shipmentsApp .table > tbody > tr { transition:background .12s ease-out; }
    #shipmentsApp .table > tbody > tr:hover > td { background:var(--sh-hover); }
    /* Antigüedad crítica: tinte de fila sutil (sin franja lateral). Solo naranja
       y rojo se resaltan; verde/amarillo se quedan neutros para no ser un arcoíris. */
    #shipmentsApp .table > tbody > tr.sh-row--age3 > td { background:#fdf3f3; }
    #shipmentsApp .table > tbody > tr.sh-row--age3:hover > td { background:#fbe9e9; }
    #shipmentsApp .table > tbody > tr.sh-row--age2 > td { background:#fff8f1; }
    #shipmentsApp .table > tbody > tr.sh-row--age2:hover > td { background:#fdefe0; }
    /* Jerarquía dentro de la fila */
    .sh-code { display:block; font-weight:650; color:var(--sh-ink); font-size:.775rem;
        letter-spacing:-.015em; font-variant-numeric:tabular-nums; }
    .sh-client { display:block; font-weight:600; color:var(--sh-ink); font-size:.815rem; line-height:1.3; }
    .sh-phone { display:block; margin-top:1px; font-size:.71rem; color:var(--sh-faint); font-variant-numeric:tabular-nums; }
    .sh-date { font-weight:600; color:var(--sh-ink); font-size:.775rem; font-variant-numeric:tabular-nums; }
    .sh-time { display:block; font-size:.7rem; color:var(--sh-faint); font-variant-numeric:tabular-nums; }
    /* Etiquetas (reemplazan los badges genéricos) */
    .sh-tag { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700;
        padding:.26rem .54rem; border-radius:999px; letter-spacing:.005em; text-decoration:none; white-space:nowrap; }
    .sh-tag--danger { background:#fdecec; color:#b42318; }
    .sh-tag--ok { background:#eafaf0; color:#067647; }
    .sh-tag--ok:hover { background:#d1fadf; color:#05603a; }
    /* Selector de estado: suavizar los colores Bootstrap (evitar el cian/amarillo
       chillón) y darle el mismo acabado tokenizado. El color del texto queda como
       pista sutil del estado; el borde es neutro. */
    #shipmentsApp .sh-status-select { border-radius:9px !important; font-weight:600;
        background:var(--sh-surface); box-shadow:none; padding-block:.34rem;
        border-color:var(--sh-line) !important; transition:border-color .15s, box-shadow .15s; }
    #shipmentsApp .sh-status-select:focus { border-color:var(--sh-brand-line) !important; box-shadow:0 0 0 3px rgba(79,70,229,.12) !important; }
    #shipmentsApp .sh-status-select.text-secondary { color:#647089 !important; }
    #shipmentsApp .sh-status-select.text-info      { color:#0e7490 !important; }
    #shipmentsApp .sh-status-select.text-warning   { color:#b45309 !important; }
    #shipmentsApp .sh-status-select.text-primary   { color:var(--sh-brand) !important; }
    #shipmentsApp .sh-status-select.text-success   { color:#15803d !important; }
    #shipmentsApp .sh-status-select.text-dark      { color:var(--sh-ink) !important; }

    /* ── Pie de tabla (paginación tipo ERP) ── */
    .sh-foot { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
        padding:11px 16px; border-top:1px solid var(--sh-line-soft); background:var(--sh-track);
        font-size:.8rem; }
    .sh-foot__info { color:var(--sh-muted); display:inline-flex; align-items:baseline; gap:5px; }
    .sh-foot__range, .sh-foot__total { color:var(--sh-ink); font-weight:600; font-variant-numeric:tabular-nums; }
    .sh-foot__of, .sh-foot__label { color:var(--sh-faint); }
    .sh-foot__size { display:inline-flex; align-items:center; gap:8px; color:var(--sh-muted); }
    .sh-foot__size label { margin:0; font-weight:500; }
    .sh-select { position:relative; display:inline-flex; align-items:center; }
    .sh-select select { appearance:none; -webkit-appearance:none; padding:.36rem 1.7rem .36rem .6rem;
        font-size:.78rem; font-weight:600; color:var(--sh-ink); background:var(--sh-surface); border:1px solid var(--sh-line);
        border-radius:9px; cursor:pointer;
        transition:border-color .15s ease-out, box-shadow .15s ease-out; }
    .sh-select select:hover { border-color:#d5d9e6; }
    .sh-select select:focus { outline:none; border-color:var(--sh-brand-line); box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    .sh-select i { position:absolute; right:.6rem; font-size:.6rem; color:var(--sh-faint); pointer-events:none; }
    .sh-foot__nav { display:inline-flex; align-items:center; gap:4px; }
    .sh-pg-nums { display:inline-flex; align-items:center; gap:4px; }
    .sh-pg { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px;
        border-radius:9px; font-size:.78rem; font-weight:600; font-variant-numeric:tabular-nums; color:var(--sh-muted);
        text-decoration:none; background:var(--sh-surface); border:1px solid var(--sh-line);
        transition:background .15s ease-out, border-color .15s ease-out, color .15s ease-out, transform .1s ease-out; }
    .sh-pg:hover { background:var(--sh-hover); border-color:#d5d9e6; color:var(--sh-ink); }
    .sh-pg:active { transform:translateY(1px); }
    .sh-pg.is-current { background:var(--sh-brand); border-color:var(--sh-brand); color:#fff;
        box-shadow:0 2px 8px -2px rgba(79,70,229,.5); }
    .sh-pg.is-off { opacity:.38; pointer-events:none; box-shadow:none; }
    .sh-pg-gap { padding:0 2px; color:var(--sh-faint); letter-spacing:1px; font-size:.7rem; }
    @media (max-width:768px) {
        .sh-foot { justify-content:center; text-align:center; gap:10px; }
        .sh-pg-nums .sh-pg:not(.is-current) { display:none; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3 py-3" id="shipmentsApp">

    {{-- Cabecera --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div class="sh-head">
            <div class="sh-head__ic"><i class="fas fa-box-open"></i></div>
            <div>
            <h4 class="sh-head__t">Registro y Control de Envíos</h4>
            <small class="text-muted">
                @if(($group ?? null) === 'confirmar')
                    📥 <strong>Pedidos nuevos</strong> por atender — toca «Total» para ver todos.
                @else
                    Tablero de despacho — sube la guía cuando el paquete llegue a la agencia.
                @endif
            </small>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <div class="dropdown">
                <button class="sh-act sh-act--ghost" data-bs-toggle="dropdown" aria-label="Más opciones" title="Más opciones"><i class="fas fa-ellipsis-h"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="{{ route('shipments.couriers') }}">
                        <i class="fas fa-motorcycle fa-fw me-2 text-muted"></i> Reparto motorizado
                        @if(($metrics['courier_active'] ?? 0) > 0)<span class="badge rounded-pill bg-primary ms-1">{{ $metrics['courier_active'] }}</span>@endif
                    </a></li>
                    <li><a class="dropdown-item" href="{{ route('shipments.settings') }}">
                        <i class="fas fa-cog fa-fw me-2 text-muted"></i> Configuración de la tienda
                    </a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoEnvio">
                <i class="fas fa-plus me-1"></i> Registrar envío
            </button>
        </div>
    </div>

    {{-- ── Filtros: una sola dimensión visible (pestañas) + avanzados en popover ── --}}
    @php
        $activeGroup = $group ?? null;
        $curParams = array_filter([
            'filter' => ($filter && $filter !== 'todos') ? $filter : null,
            'type'   => $type ?: null,
            'group'  => $group ?: null,
            'q'      => $q ?: null,
            // El rango manda: si está activo, no arrastramos from/to (los calcula
            // el controlador). Solo se conservan from/to sueltos si no hay rango.
            'range'  => $range ?? null,
            'from'   => ($range ?? null) ? null : ($from ?? null),
            'to'     => ($range ?? null) ? null : ($to ?? null),
            'sort'   => (($sort ?? 'recent') !== 'recent') ? $sort : null,
            'pri'    => $pri ?? null,
            'per_page' => ((int) ($perPage ?? 20) !== 20) ? $perPage : null,
        ], fn ($v) => $v !== null && $v !== '');
        $mk = function (array $override) use ($curParams) {
            $p = array_filter(array_merge($curParams, $override), fn ($v) => $v !== null && $v !== '');
            return route('shipments.index', $p);
        };
        $tabs = [
            ['k'=>'confirmar',  'l'=>'Nuevos',        'v'=>$metrics['confirmar']],
            ['k'=>'preparar',   'l'=>'Embalando',     'v'=>$metrics['preparar'] ?? 0],
            ['k'=>'transito',   'l'=>'En agencia',    'v'=>$metrics['transito']],
            ['k'=>'entregados', 'l'=>'Entregados',    'v'=>$metrics['entregados']],
            ['k'=>'todos',      'l'=>'Todos',         'v'=>$metrics['total']],
        ];
        $advN = (int) !empty($type)
              + (int) in_array($filter, ['con-guia','enviados-hoy'], true)
              + (int) ($activeGroup === 'cancelados');
        // Rangos de fecha para el selector único.
        $rangeOpts = [
            'hoy'        => 'Hoy',
            'ayer'       => 'Ayer',
            '7dias'      => 'Últimos 7 días',
            '30dias'     => 'Últimos 30 días',
            'mes'        => 'Este mes',
            'mes_pasado' => 'Mes pasado',
        ];
        $fmtD = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/y') : null;
        if (!empty($range)) {
            $rangeLabel = $rangeOpts[$range];
        } elseif ($from && $to) {
            $rangeLabel = $fmtD($from) . ' – ' . $fmtD($to);
        } elseif ($from) {
            $rangeLabel = 'Desde ' . $fmtD($from);
        } elseif ($to) {
            $rangeLabel = 'Hasta ' . $fmtD($to);
        } else {
            $rangeLabel = 'Cualquier fecha';
        }
        $rangeOn = !empty($range) || !empty($from) || !empty($to);
        $hoyStr  = now()->format('Y-m-d');
    @endphp
    {{-- #shPanel: zona que se recarga por AJAX (sin recargar toda la página). --}}
    <div id="shPanel">

    <div class="sh-tabs">
        @foreach($tabs as $t)
            <a href="{{ $mk(['group' => $t['k']]) }}" class="sh-tab {{ $activeGroup === $t['k'] ? 'is-on' : '' }}">
                {{ $t['l'] }}<span class="sh-tab__n">{{ number_format($t['v']) }}</span>
            </a>
        @endforeach
    </div>

    {{-- ── Filtros rápidos por modalidad y etapa del ciclo logístico ── --}}
    <div class="sh-pri sh-quick">
        <span class="sh-pri__lbl"><i class="fas fa-filter"></i> Rápidos</span>
        @foreach(\App\Http\Controllers\Tenant\ShipmentController::FILTER_LABELS as $fk => $fl)
            @php
                // El chip de modalidad se pinta con su color acordado.
                $mod = ['lima' => 'domicilio', 'provincias' => 'agencia', 'recojo' => 'tienda'][$fk] ?? null;
            @endphp
            <a href="{{ $mk(['filter' => $fk === 'todos' ? null : $fk]) }}"
               class="sh-pri__chip {{ $mod ? 'sh-quick--' . $mod : '' }} {{ ($filter ?? 'todos') === $fk ? 'is-on' : '' }}">
                @if($mod)<span class="sh-quick__dot sh-quick__dot--{{ $mod }}"></span>@endif
                {{ $fl }}
            </a>
        @endforeach
        <a href="{{ route('shipments.batches') }}" class="sh-pri__chip"><i class="fas fa-boxes-stacked"></i> Lotes</a>
        <a href="{{ route('shipments.dashboard') }}" class="sh-pri__chip"><i class="fas fa-chart-simple"></i> Tablero</a>
    </div>

    {{-- ── Prioridad por antigüedad (días hábiles) ── --}}
    <div class="sh-pri">
        <span class="sh-pri__lbl"><i class="fas fa-traffic-light"></i> Prioridad</span>
        <a href="{{ $mk(['sort' => ($sort ?? '') === 'priority' ? null : 'priority', 'pri' => null]) }}"
           class="sh-pri__chip {{ ($sort ?? '') === 'priority' ? 'is-on' : '' }}"
           title="Ordena mostrando primero los más antiguos / por vencer">
            <i class="fas fa-sort-amount-down-alt"></i> Más antiguos primero
        </a>
        <a href="{{ $mk(['pri' => ($pri ?? '') === 'urgentes' ? null : 'urgentes']) }}"
           class="sh-pri__chip sh-pri__chip--warn {{ ($pri ?? '') === 'urgentes' ? 'is-on' : '' }}"
           title="Envíos abiertos con ≥ {{ ($maxDays ?? 4) - 1 }} días hábiles">
            <span class="sh-pri__dot sh-pri__dot--warn"></span> Urgentes @if(($metrics['urgentes'] ?? 0) > 0)<b>{{ $metrics['urgentes'] }}</b>@endif
        </a>
        <a href="{{ $mk(['pri' => ($pri ?? '') === 'vencidos' ? null : 'vencidos']) }}"
           class="sh-pri__chip sh-pri__chip--danger {{ ($pri ?? '') === 'vencidos' ? 'is-on' : '' }}"
           title="Envíos abiertos que superaron los {{ $maxDays ?? 4 }} días hábiles">
            <span class="sh-pri__dot sh-pri__dot--danger"></span> Vencidos @if(($metrics['vencidos'] ?? 0) > 0)<b>{{ $metrics['vencidos'] }}</b>@endif
        </a>
        <span class="sh-pri__hint">Plazo: <b>{{ $maxDays ?? 4 }}</b> días hábiles</span>
    </div>

    {{-- Aviso proactivo: envíos que ya superaron el plazo (a menos que ya se esté filtrando por vencidos). --}}
    @if(($metrics['vencidos'] ?? 0) > 0 && ($pri ?? '') !== 'vencidos')
        <a href="{{ $mk(['pri' => 'vencidos']) }}" class="sh-overdue">
            <span class="sh-overdue__ic"><i class="fas fa-triangle-exclamation"></i></span>
            <span class="sh-overdue__tx">
                <b>{{ $metrics['vencidos'] }}</b> envío(s) <b>vencido(s)</b> superaron los {{ $maxDays ?? 4 }} días hábiles y necesitan atención.
            </span>
            <span class="sh-overdue__cta">Ver vencidos <i class="fas fa-arrow-right"></i></span>
        </a>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="sh-tools">
        {{-- El buscador reconstruye la URL SOLO con estos campos (searchUrl() usa
             FormData), así que todo filtro activo tiene que viajar aquí o se
             pierde al escribir. Se emite el MISMO juego que $curParams: con
             rango preestablecido va `range`; con rango manual van `from`/`to`,
             nunca los dos (el controlador le da prioridad a `range`). --}}
        <form method="GET" action="{{ route('shipments.index') }}" id="shSearchForm" class="sh-search">
            @foreach($curParams as $ck => $cv)
                @continue($ck === 'q')
                <input type="hidden" name="{{ $ck }}" value="{{ $cv }}">
            @endforeach
            <i class="fas fa-search sh-search__ic"></i>
            <input type="text" name="q" id="shSearchInput" value="{{ $q }}" placeholder="Buscar cliente, código, guía…" autocomplete="off">
            <button type="button" id="shClearSearch" title="Limpiar" style="{{ $q ? '' : 'display:none;' }}">✕</button>
        </form>

        <a href="{{ $mk(['filter' => $filter === 'sin-guia' ? null : 'sin-guia']) }}"
           class="sh-chip {{ $filter === 'sin-guia' ? 'is-on' : '' }}">
            Sin guía <b>{{ $counts['sin-guia'] ?? 0 }}</b>
        </a>

        {{-- Selector ÚNICO de fecha: un chip que despliega los rangos a elegir. --}}
        <div class="dropdown">
            <button type="button" class="sh-chip {{ $rangeOn ? 'is-on' : '' }}" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Filtrar por fecha de registro">
                <i class="far fa-calendar-alt"></i> {{ $rangeLabel }}
            </button>
            <div class="dropdown-menu shadow sh-filters sh-cal-wrap">
                {{-- Un solo campo: calendario de rango. Clic en el día de inicio y
                     clic en el día de fin (ej. del 1 al 10). Se aplica al 2º clic. --}}
                <div class="sh-cal" id="shCal"
                     data-today="{{ $hoyStr }}"
                     data-sel-start="{{ $range ? '' : $from }}"
                     data-sel-end="{{ $range ? '' : $to }}"
                     data-base="{{ $mk(['range' => null, 'from' => null, 'to' => null]) }}">
                    <div class="sh-cal__head">
                        <button type="button" class="sh-cal__nav" data-cal="prev" aria-label="Mes anterior"><i class="fas fa-chevron-left"></i></button>
                        <span class="sh-cal__title" data-cal="title">—</span>
                        <button type="button" class="sh-cal__nav" data-cal="next" aria-label="Mes siguiente"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="sh-cal__dow"><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span></div>
                    <div class="sh-cal__grid" data-cal="grid"></div>
                    <div class="sh-cal__foot">
                        <span class="sh-cal__hint" data-cal="hint">Elige el día de inicio</span>
                        @if($rangeOn)<a href="{{ $mk(['range' => null, 'from' => null, 'to' => null]) }}" class="sh-cal__clear">Quitar</a>@endif
                    </div>
                </div>
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="sh-chip {{ $advN ? 'is-on' : '' }}" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                <i class="fas fa-sliders-h"></i> Filtros @if($advN)<b>{{ $advN }}</b>@endif
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow sh-filters">
                <div class="sh-filters__g">
                    <span class="sh-filters__t">Tipo de entrega</span>
                    <div class="sh-filters__r">
                        <a href="{{ $mk(['type' => null]) }}" class="sh-mini {{ empty($type) ? 'is-on' : '' }}">Todos</a>
                        <a href="{{ $mk(['type' => 'domicilio']) }}" class="sh-mini {{ ($type ?? '') === 'domicilio' ? 'is-on' : '' }}">Domicilio <b>{{ $metrics['domicilio'] ?? 0 }}</b></a>
                        <a href="{{ $mk(['type' => 'agencia']) }}" class="sh-mini {{ ($type ?? '') === 'agencia' ? 'is-on' : '' }}">Agencia <b>{{ $metrics['agencia'] ?? 0 }}</b></a>
                    </div>
                </div>
                <div class="sh-filters__g">
                    <span class="sh-filters__t">Guía y despacho</span>
                    <div class="sh-filters__r">
                        <a href="{{ $mk(['filter' => $filter === 'con-guia' ? null : 'con-guia']) }}" class="sh-mini {{ $filter === 'con-guia' ? 'is-on' : '' }}">Con guía <b>{{ $counts['con-guia'] ?? 0 }}</b></a>
                        <a href="{{ $mk(['filter' => $filter === 'enviados-hoy' ? null : 'enviados-hoy']) }}" class="sh-mini {{ $filter === 'enviados-hoy' ? 'is-on' : '' }}">Enviados hoy <b>{{ $counts['enviados-hoy'] ?? 0 }}</b></a>
                        <a href="{{ $mk(['group' => 'cancelados']) }}" class="sh-mini {{ $activeGroup === 'cancelados' ? 'is-on' : '' }}">Cancelados <b>{{ $metrics['cancelados'] ?? 0 }}</b></a>
                    </div>
                </div>
                @if($advN || $q || $filter !== 'todos' || $rangeOn)
                    <a href="{{ route('shipments.index') }}" class="sh-filters__clear">Limpiar todos los filtros</a>
                @endif
            </div>
        </div>

        {{-- Exportar la lista filtrada a Excel/CSV. target=_blank evita que el
             interceptor AJAX del panel se lo trague (descarga directa). --}}
        <a href="{{ route('shipments.export', $curParams) }}" target="_blank" class="sh-chip" title="Descargar la lista filtrada en Excel (CSV)">
            <i class="fas fa-file-excel"></i> Exportar
        </a>
    </div>

    {{-- Aviso del filtro crítico --}}
    @if($filter === 'sin-guia' && $counts['sin-guia'] > 0)
        <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Estos paquetes <strong>aún no tienen guía cargada</strong>. Súbela apenas los entregues a la agencia.</span>
        </div>
    @endif

    {{-- Barra de selección (impresión por lote) — fija arriba y bien visible --}}
    {{-- #shResults: solo los RESULTADOS. Al escribir en el buscador se refresca
         únicamente esto, así el input nunca se reemplaza (sin parpadeo ni pérdida
         de foco). Los clics en filtros sí refrescan todo #shPanel. --}}
    <div id="shResults">

    <div id="shBulkBar" class="align-items-center justify-content-between py-2 px-3 mb-2"
         style="display:none; position:sticky; top:8px; z-index:30; background:#4f46e5; color:#fff; border-radius:12px; box-shadow:0 10px 24px -8px rgba(79,70,229,.6);">
        <span style="font-weight:600;">✅ <strong id="shSelCount">0</strong> envío(s) seleccionado(s)</span>
        <div class="d-flex align-items-center gap-2">
            @if($requirePayment ?? false)
                <button type="button" class="btn btn-sm btn-warning fw-bold" id="shPaySel"><i class="fas fa-lock-open me-1"></i> Confirmar pago</button>
            @endif
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-light fw-bold" data-bs-toggle="dropdown" style="color:#4f46e5;">
                    <i class="fas fa-exchange-alt me-1"></i> Cambiar estado
                </button>
                <ul class="dropdown-menu shadow-sm">
                    @foreach(['embalando'=>'Embalando','despachado'=>'Despachado','en_agencia'=>'Entregado a agencia','en_camino'=>'Motorizado en camino','entregado'=>'Entregado'] as $sv => $sl)
                        <li><button type="button" class="dropdown-item sh-bulk-status" data-status="{{ $sv }}"><i class="fas fa-arrow-right fa-fw me-2 text-muted"></i>{{ $sl }}</button></li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn btn-sm btn-light text-primary fw-bold" id="shClearSel">Quitar selección</button>
            <button type="button" class="btn btn-sm btn-light fw-bold" id="shPrintSel" style="color:#4f46e5;">
                <i class="fas fa-print me-1"></i> Imprimir los seleccionados
            </button>
        </div>
    </div>

    {{-- Tabla (scroll horizontal en móvil) --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width:940px;">
                <thead class="table-light">
                    <tr>
                        <th style="width:34px;"><input type="checkbox" class="form-check-input" id="shCheckAll" title="Seleccionar todos"></th>
                        <th>Envío</th>
                        <th>Cliente</th>
                        <th>Ciudad</th>
                        <th>Agencia</th>
                        <th>Guía</th>
                        <th>Estado</th>
                        <th class="text-nowrap">
                            <a href="{{ $mk(['sort' => ($sort ?? 'recent') === 'oldest' ? 'recent' : 'oldest']) }}" class="text-decoration-none text-dark">
                                Fecha
                                @if(($sort ?? 'recent') === 'oldest')<i class="fas fa-sort-amount-up ms-1"></i>@else<i class="fas fa-sort-amount-down ms-1"></i>@endif
                            </a>
                        </th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($shipments as $s)
                    @php
                        $badge = [
                            'recibido'            => 'secondary',
                            'confirmado'          => 'info',
                            'preparando'          => 'warning',
                            'asignado_motorizado' => 'info',
                            'en_camino'           => 'primary',
                            'embalando'           => 'warning',
                            'despachado'          => 'primary',
                            'en_agencia'          => 'success',
                            'en_ruta'             => 'success',
                            'entregado'           => 'primary',
                            'anulado'             => 'dark',
                            'pendiente'  => 'secondary', 'listo' => 'warning', 'enviado' => 'success',
                        ][$s->status] ?? 'secondary';
                        // Flujo bloqueado hasta confirmar el pago (si la tienda lo exige).
                        $bloqueado = ($requirePayment ?? false) && !$s->payment_confirmed && !$s->is_cancelled;
                        // Semáforo de antigüedad (solo envíos abiertos).
                        $age = $s->aging($maxDays ?? 4, $skipHolidays ?? true);
                        $ageMeta = $age['level'] !== null ? \App\Models\Tenant\ShippingRequest::AGING_META[$age['level']] : null;

                        // Todo lo que registró el cliente, agrupado para el modal "ojo".
                        $isDom = $s->is_domicilio;
                        $secEntrega = $isDom
                            ? array_filter([
                                'Dirección'   => $s->shipping_destination ?: $s->formatted_address,
                                'Referencia'  => $s->reference,
                                'Ciudad'      => $s->destination_city,
                                'Distancia'   => $s->distance_km ? ($s->distance_text ?: ($s->distance_km.' km')) : null,
                                'Motorizado'  => $s->courier_name,
                            ])
                            : array_filter([
                                'Agencia'            => $s->shipping_agency,
                                'Oficina de recojo'  => $s->reference,
                                'Dirección de reparto' => $s->shipping_destination,
                                'Ciudad / destino'   => $s->destination_city,
                            ]);
                        $secCosto = array_filter([
                            'Costo de envío' => $s->delivery_price ? ('S/ '.number_format($s->delivery_price, 2)) : null,
                            'Pago'           => ($requirePayment ?? false) ? ($s->payment_confirmed ? ('Confirmado'.($s->payment_confirmed_at ? ' · '.$s->payment_confirmed_at->format('d/m/Y H:i') : '')) : 'Pendiente') : null,
                        ]);
                        $viewPhone = preg_replace('/\D+/', '', (string) $s->phone);
                        $viewData = [
                            'code' => $s->shipment_code ?: ('#'.$s->id),
                            'name' => $s->full_name,
                            'type' => $isDom ? 'Domicilio' : 'Agencia',
                            'maps' => $isDom ? $s->maps_link : null,
                            'id'    => $s->id,
                            'phone' => strlen($viewPhone) === 9 ? ('51'.$viewPhone) : $viewPhone,
                            'print' => $bloqueado ? null : route('shipments.print', $s->id),
                            // Nº de impresiones previas: si es > 0 el rótulo exige
                            // motivo y el botón abre el modal de reimpresión.
                            'print_count' => (int) $s->print_count,
                            'code'        => $s->shipment_code,
                            'sections' => array_filter([
                                'Cliente' => array_filter([
                                    'Nombre'  => $s->full_name,
                                    $s->document_label => $s->dni,
                                    'Celular' => $s->phone,
                                ]),
                                ($isDom ? 'Entrega a domicilio' : 'Envío por agencia') => $secEntrega,
                                'Paquete' => array_filter([
                                    // Los saltos de línea se pierden al pintarlos en la ficha:
                                    // se separan los ítems para que no se lean pegados.
                                    // El textarea de edición sí conserva el texto crudo.
                                    'Contenido'     => $s->contentInline(),
                                    'Bultos'        => $s->package_count,
                                    'Peso'          => $s->weight ? ($s->weight.' kg') : null,
                                    'Observaciones' => $s->notes,
                                ]),
                                'Costo' => $secCosto,
                                'Seguimiento' => array_filter([
                                    'Estado'     => $s->status_label,
                                    'Registrado' => optional($s->created_at)->format('d/m/Y H:i'),
                                    'Código'     => $s->shipment_code,
                                ]),
                            ]),
                        ];
                    @endphp
                    <tr class="{{ $s->is_cancelled ? 'text-muted' : '' }} {{ $ageMeta ? 'sh-row--age'.$age['level'] : '' }}"
                        style="{{ $s->is_cancelled ? 'opacity:.7;' : '' }}">
                        <td><input type="checkbox" class="form-check-input sh-check" value="{{ $s->id }}"></td>
                        <td><span class="sh-code">{{ $s->shipment_code }}</span></td>
                        <td>
                            @php
                                $ini = collect(preg_split('/\s+/', trim($s->full_name)))
                                    ->filter()->take(2)
                                    ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                                $hue = crc32($s->full_name) % 360;
                            @endphp
                            <div class="sh-cli">
                                <span class="sh-avt" style="background:hsl({{ $hue }} 62% 94%);color:hsl({{ $hue }} 42% 38%);">{{ $ini ?: '?' }}</span>
                                <span>
                                    <span class="sh-client">{{ $s->full_name }}</span>
                                    <span class="sh-phone">{{ $s->phone }}</span>
                                </span>
                            </div>
                        </td>
                        <td>{{ $s->destination_city ?: '—' }}</td>
                        <td>
                            @if($s->is_domicilio)
                                <span class="badge" style="background:#f3e8ff;color:#7c3aed;">🏍️ Domicilio</span>
                                @if($s->distance_km)<div><small class="fw-bold" style="color:#3730a3;">🛵 {{ $s->distance_text ?: ($s->distance_km.' km') }}@if($s->duration_text) · ~{{ $s->duration_text }}@endif</small></div>@endif
                                <div>
                                    <button type="button" class="btn btn-link btn-sm p-0 fw-bold text-success js-edit-price" style="text-decoration:none;font-size:.8rem;"
                                            data-bs-toggle="modal" data-bs-target="#modalPrecio"
                                            data-id="{{ $s->id }}" data-code="{{ $s->shipment_code }}" data-price="{{ $s->delivery_price }}">
                                        💵 {{ $s->delivery_price ? 'S/ '.number_format($s->delivery_price, 2) : 'Poner precio' }}
                                        <i class="fas fa-pen ms-1" style="font-size:.65rem;opacity:.6;"></i>
                                    </button>
                                </div>
                                @if($s->maps_link)
                                    <div class="mt-1"><a href="{{ $s->maps_link }}" target="_blank" class="small text-decoration-none"><i class="fas fa-map-marker-alt me-1"></i>Ver ubicación</a></div>
                                @endif
                                @if($s->courier_name)<div><small class="text-muted"><i class="fas fa-motorcycle me-1"></i>{{ $s->courier_name }}</small></div>@endif
                            @elseif($s->is_pickup)
                                <a href="{{ route('shipments.pickup_receipt', $s->id) }}" target="_blank"
                                   class="small text-decoration-none">
                                    <i class="fas fa-receipt me-1"></i>Comprobante de entrega
                                </a>
                            @else
                                {{ $s->shipping_agency ?: '—' }}
                            @endif

                            {{-- Modalidad + lote: los tres campos separados que pide la operación. --}}
                            <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                <span class="sh-mod sh-mod--{{ $s->delivery_type }}">
                                    {{ $s->delivery_meta['icon'] }} {{ $s->delivery_short }}
                                </span>
                                <span class="sh-batch {{ $s->print_batch_id ? 'sh-batch--on' : '' }}">
                                    @if($s->print_batch_id)
                                        <a href="{{ route('shipments.batches.show', $s->print_batch_id) }}"
                                           class="text-decoration-none">{{ $s->batch_label }}</a>
                                        @if($s->isLockedByBatch())<i class="fas fa-lock ms-1" title="Lote impreso: bloqueado"></i>@endif
                                    @else
                                        Sin lote
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td>
                            @if($s->has_guide)
                                <a href="{{ route('shipments.guide', $s->id) }}" target="_blank" class="sh-tag sh-tag--ok">
                                    <i class="fas fa-paperclip"></i> Adjunta
                                </a>
                                @if($s->tracking_number)<div><small class="text-muted">{{ $s->tracking_number }}</small></div>@endif
                            @else
                                <span class="sh-tag sh-tag--danger">Sin guía</span>
                            @endif
                        </td>
                        <td>
                            @if($s->is_cancelled)
                                <span class="badge bg-dark">Anulado</span>
                            @else
                                @php $flow = $s->selectableStatuses(); $curInFlow = in_array($s->status, $flow, true); @endphp
                                @if($bloqueado)
                                    {{-- Divulgación progresiva: mientras no se pueda usar el estado, no se muestra. --}}
                                    <form method="POST" action="{{ route('shipments.payment', $s->id) }}" class="m-0 js-pay-form">
                                        @csrf
                                        <button type="submit" class="sh-pay-gate" title="Confirmar el pago habilita el estado y la impresión">
                                            <i class="fas fa-lock"></i> Confirmar pago
                                        </button>
                                    </form>
                                @else
                                <form method="POST" action="{{ route('shipments.status', $s->id) }}" class="d-inline m-0">
                                    @csrf
                                    <select name="status" class="form-select form-select-sm sh-status-select border-{{ $badge }} text-{{ $badge }}" style="min-width:150px;font-weight:600;" {{ $bloqueado ? 'disabled' : '' }} title="{{ $bloqueado ? 'Confirma el pago para habilitar' : '' }}">
                                        @unless($curInFlow)
                                            <option value="{{ $s->status }}" selected>{{ $s->status_label }}</option>
                                        @endunless
                                        @foreach($flow as $val)
                                            <option value="{{ $val }}" {{ $s->status === $val ? 'selected' : '' }}>{{ $statuses[$val] ?? $val }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                @if(($requirePayment ?? false) && $s->payment_confirmed)
                                    <form method="POST" action="{{ route('shipments.payment', $s->id) }}" class="m-0 js-pay-form">
                                        @csrf
                                        <button type="submit" class="sh-paid" title="Clic para revertir la confirmación de pago">
                                            <i class="fas fa-check"></i> Pagado {{ optional($s->payment_confirmed_at)->format('d/m H:i') }}
                                        </button>
                                    </form>
                                @endif
                                @endif
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if($s->created_at)
                                <div class="sh-date">{{ $s->created_at->format('d/m/Y') }}</div>
                                <span class="sh-time">{{ $s->created_at->format('H:i') }}</span>
                                @if($ageMeta)
                                    <div class="sh-age" style="color:{{ $ageMeta['color'] }};background:{{ $ageMeta['bg'] }};"
                                         title="{{ $ageMeta['label'] }} · {{ $age['days'] }} día(s) hábil(es) desde el registro (plazo {{ $maxDays ?? 4 }})">
                                        <span class="sh-age__dot"></span>
                                        {{ $age['days'] }} d háb · {{ $ageMeta['label'] }}
                                    </div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="sh-actions">
                                <button type="button" class="sh-act sh-act--ghost js-view-shipment" title="Ver todo lo que registró el cliente"
                                        data-bs-toggle="modal" data-bs-target="#modalVerEnvio"
                                        data-view="{{ json_encode($viewData, JSON_UNESCAPED_UNICODE) }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @if($s->has_guide)
                                    <a href="{{ route('shipments.guide', $s->id) }}" target="_blank" class="sh-act sh-act--ok" title="Ver la guía cargada">
                                        <i class="fas fa-eye"></i> Ver guía
                                    </a>
                                @elseif(!$s->is_cancelled)
                                    <button type="button" class="sh-act sh-act--primary js-upload-guide {{ $bloqueado ? 'is-off' : '' }}"
                                            @if($bloqueado) disabled title="Confirma el pago para habilitar" @endif
                                            data-bs-toggle="modal" data-bs-target="#modalSubirGuia"
                                            data-id="{{ $s->id }}" data-cliente="{{ $s->full_name }}"
                                            data-agencia="{{ $s->shipping_agency }}" data-ciudad="{{ $s->destination_city }}">
                                        <i class="fas fa-upload"></i> Subir guía
                                    </button>
                                @endif
                                <div class="dropdown">
                                <button type="button" class="sh-act sh-act--ghost" data-bs-toggle="dropdown" aria-label="Más acciones" title="Más acciones"><i class="fas fa-ellipsis-h"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    {{-- Primera impresión: enlace directo. Reimpresión: el servidor
                                         exige motivo, así que es un botón que ABRE EL MODAL con el
                                         data-api de Bootstrap (declarativo). Nada de abrirlo por JS:
                                         el bundle no expone `window.bootstrap`. --}}
                                    <li>
                                        @if($s->print_count > 0 && !$bloqueado)
                                            <button type="button" class="dropdown-item js-reprint"
                                                    data-bs-toggle="modal" data-bs-target="#modalReimprimir"
                                                    data-id="{{ $s->id }}"
                                                    data-url="{{ route('shipments.print', $s->id) }}"
                                                    data-count="{{ (int) $s->print_count }}"
                                                    data-code="{{ $s->shipment_code }}">
                                                <i class="fas fa-print fa-fw me-2"></i> Reimprimir rótulo
                                                <span class="badge bg-light text-muted ms-1">{{ $s->print_count }}</span>
                                            </button>
                                        @else
                                            <a class="dropdown-item {{ $bloqueado ? 'disabled' : '' }}"
                                               href="{{ $bloqueado ? '#' : route('shipments.print', $s->id) }}"
                                               @if(!$bloqueado) target="_blank" @endif
                                               @if($bloqueado) tabindex="-1" aria-disabled="true" @endif>
                                                <i class="fas fa-print fa-fw me-2"></i> Imprimir rótulo
                                            </a>
                                        @endif
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item js-edit-shipment"
                                                data-bs-toggle="modal" data-bs-target="#modalEditar"
                                                data-id="{{ $s->id }}"
                                                data-full_name="{{ $s->full_name }}"
                                                data-dni="{{ $s->dni }}"
                                                data-document_type="{{ $s->document_option }}"
                                                data-phone="{{ $s->phone }}"
                                                data-shipping_destination="{{ $s->shipping_destination }}"
                                                data-reference="{{ $s->reference }}"
                                                data-destination_city="{{ $s->destination_city }}"
                                                data-shipping_agency="{{ $s->shipping_agency }}"
                                                data-package_content="{{ $s->package_content }}"
                                                data-package_count="{{ $s->package_count }}"
                                                data-weight="{{ $s->weight }}"
                                                data-notes="{{ $s->notes }}"
                                                data-department_id="{{ $s->department_id }}"
                                                data-province_id="{{ $s->province_id }}"
                                                data-district_id="{{ $s->district_id }}"
                                                data-delivery_type="{{ $s->delivery_type }}"
                                                data-latitude="{{ $s->latitude }}"
                                                data-longitude="{{ $s->longitude }}"
                                                data-formatted_address="{{ $s->formatted_address }}"
                                                data-google_place_id="{{ $s->google_place_id }}"
                                                data-google_maps_url="{{ $s->google_maps_url }}"
                                                data-distance_km="{{ $s->distance_km }}"
                                                data-distance_text="{{ $s->distance_text }}"
                                                data-duration_text="{{ $s->duration_text }}"
                                                data-delivery_price="{{ $s->delivery_price }}"
                                                data-maps_link="{{ $s->maps_link }}"
                                                data-shipment_code="{{ $s->shipment_code }}">
                                            <i class="fas fa-pen fa-fw me-2"></i> Editar
                                        </button>
                                    </li>
                                    {{-- ── Modalidad de entrega: flujo propio con cascada y auditoría ── --}}
                                    @if(!$s->is_cancelled)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button type="button" class="dropdown-item js-change-modality"
                                                data-bs-toggle="modal" data-bs-target="#modalModalidad"
                                                data-id="{{ $s->id }}"
                                                data-code="{{ $s->shipment_code }}"
                                                data-current="{{ $s->delivery_type }}"
                                                data-locked="{{ $s->isLockedByBatch() ? '1' : '' }}"
                                                data-batch="{{ $s->batch_label }}">
                                            <i class="fas fa-shuffle fa-fw me-2"></i> Cambiar modalidad
                                        </button>
                                    </li>
                                    @if($s->print_batch_id && !$s->isLockedByBatch())
                                    <li>
                                        <form method="POST" action="{{ route('shipments.batch_remove', $s->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-box-open fa-fw me-2"></i> Quitar del lote
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                    <li>
                                        <button type="button" class="dropdown-item js-audit-trail"
                                                data-bs-toggle="modal" data-bs-target="#modalBitacora"
                                                data-url="{{ route('shipments.audit', $s->id) }}"
                                                data-code="{{ $s->shipment_code }}">
                                            <i class="fas fa-clock-rotate-left fa-fw me-2"></i> Ver bitácora
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button type="button" class="dropdown-item text-danger js-cancel-shipment"
                                                data-bs-toggle="modal" data-bs-target="#modalAnular"
                                                data-id="{{ $s->id }}" data-code="{{ $s->shipment_code }}">
                                            <i class="fas fa-ban fa-fw me-2"></i> Anular
                                        </button>
                                    </li>
                                    @else
                                    {{-- Anulado: nunca se borra, se puede restaurar --}}
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button type="button" class="dropdown-item js-audit-trail"
                                                data-bs-toggle="modal" data-bs-target="#modalBitacora"
                                                data-url="{{ route('shipments.audit', $s->id) }}"
                                                data-code="{{ $s->shipment_code }}">
                                            <i class="fas fa-clock-rotate-left fa-fw me-2"></i> Ver bitácora
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item text-success js-restore-shipment"
                                                data-bs-toggle="modal" data-bs-target="#modalRestaurar"
                                                data-id="{{ $s->id }}" data-code="{{ $s->shipment_code }}"
                                                data-reason="{{ $s->cancel_reason }}"
                                                data-by="{{ $s->cancelled_by_name }}"
                                                data-at="{{ optional($s->cancelled_at)->format('d/m/Y H:i') }}">
                                            <i class="fas fa-rotate-left fa-fw me-2"></i> Restaurar pedido
                                        </button>
                                    </li>
                                    @endif
                                </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    @php
                        $esBandeja  = (($group ?? null) === 'confirmar' && !$q && !$from && !$to && !$type && $filter === 'todos');
                        $hayFiltros = !$esBandeja && ($q || $from || $to || $type || $group || $filter !== 'todos');
                    @endphp
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            @if($esBandeja)
                                <i class="fas fa-check-circle fa-2x mb-2 d-block text-success opacity-75"></i>
                                <strong>¡Todo al día!</strong> No hay pedidos nuevos por atender.
                                <div class="small mt-1">Los nuevos registros del cliente aparecerán aquí.</div>
                                <a href="{{ route('shipments.index', ['group' => 'todos']) }}" class="btn btn-sm btn-outline-secondary mt-2">Ver todos los envíos</a>
                            @elseif($hayFiltros)
                                <i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>
                                @if($q)
                                    No se encontraron envíos para <strong>“{{ $q }}”</strong>.
                                @else
                                    No hay envíos con los filtros aplicados.
                                @endif
                                <div class="small mt-1">Prueba con otro texto o quita algún filtro.</div>
                                <a href="{{ route('shipments.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Limpiar filtros</a>
                            @else
                                <i class="fas fa-box-open fa-2x mb-2 d-block opacity-50"></i>
                                No hay envíos registrados todavía.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('tenant.shipments.partials.pagination')
    </div>

    </div>{{-- /#shResults --}}
    </div>{{-- /#shPanel --}}

</div>

{{-- ══════════════ Modal: Subir guía de envío ══════════════ --}}
<div class="modal fade" id="modalSubirGuia" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="#" enctype="multipart/form-data" id="formSubirGuia">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">📤 Subir guía de envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Envío de <strong id="sgCliente">—</strong> · <span id="sgDestino">—</span></p>

          <div class="mb-3">
            <label class="form-label fw-semibold">Número de guía</label>
            <input type="text" name="tracking_number" class="form-control" placeholder="Ejemplo: SH-458712" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Seleccionar archivo</label>
            <label id="sgDrop" class="d-block text-center p-4 border border-2 border-dashed rounded"
                   style="cursor:pointer;border-style:dashed !important;background:#f8f9fa;">
              <i class="fas fa-cloud-arrow-up fa-2x text-muted mb-2 d-block"></i>
              <span id="sgFileText">Arrastra la imagen aquí<br>o haz clic para seleccionar</span>
              <div class="small text-muted mt-1">JPG, PNG o PDF · máx 8&nbsp;MB</div>
              <input type="file" name="guide_file" id="sgFile" accept="image/jpeg,image/png,application/pdf" class="d-none" required>
            </label>
          </div>

          <div class="mb-1">
            <label class="form-label fw-semibold">Observación (opcional)</label>
            <input type="text" name="observation" class="form-control" placeholder="Entregado en agencia Shalom - Talara">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar guía</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ══════════════ Modal: Registrar envío (manual) ══════════════ --}}
<div class="modal fade" id="modalNuevoEnvio" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('shipments.store') }}">
        @csrf
        <div class="modal-header bg-light">
          <h5 class="modal-title"><i class="fas fa-dolly text-primary me-2"></i>Registrar envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="sh-section"><i class="fas fa-user fa-fw me-1"></i> Destinatario</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small mb-1">DNI / RUC <span class="text-muted">(autocompleta)</span></label>
              <input type="text" name="dni" id="nv_dni" class="form-control js-doc-lookup"
                     data-target-name="nv_full_name" data-target-address="nv_shipping_destination" data-ubigeo-group="nv"
                     inputmode="numeric" maxlength="11" autocomplete="off" placeholder="8 dígitos (DNI) u 11 (RUC)">
              <small class="js-doc-status d-block mt-1"></small>
            </div>
            <div class="col-md-6"><label class="form-label small mb-1">Teléfono (celular) *</label>
              <input type="text" name="phone" class="form-control js-phone-pe" required maxlength="9" inputmode="numeric" placeholder="999 999 999">
              <small class="js-phone-err text-danger" style="font-size:12px;"></small></div>
            <div class="col-12"><label class="form-label small mb-1">Nombre completo *</label>
              <input type="text" name="full_name" id="nv_full_name" class="form-control" required></div>
          </div>

          <div class="sh-section"><i class="fas fa-map-marker-alt fa-fw me-1"></i> Destino</div>
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Ubigeo (Departamento / Provincia / Distrito) <span class="text-danger">*</span></label>
              <div class="ubigeo-field" data-ubigeo-group="nv">
                <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                <input type="hidden" name="department_id" data-ub="department">
                <input type="hidden" name="province_id"   data-ub="province">
                <input type="hidden" name="district_id"   data-ub="district">
                <div class="ubigeo-pop" hidden>
                  <div class="ubigeo-col" data-col="dep"></div>
                  <div class="ubigeo-col" data-col="prov"></div>
                  <div class="ubigeo-col" data-col="dist"></div>
                </div>
              </div></div>
            <div class="col-md-6"><label class="form-label">Dirección</label>
              <input type="text" name="shipping_destination" id="nv_shipping_destination" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Referencia</label>
              <input type="text" name="reference" id="nv_reference" class="form-control" placeholder="Frente a…, cerca de…"></div>
            <div class="col-12"><label class="form-label">Agencia</label>
              <div class="agency-field">
                <select class="form-select agency-select">
                  <option value="">— Selecciona —</option>
                  @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                  <option value="__otra__">Otra…</option>
                </select>
                <input type="text" name="shipping_agency" class="form-control agency-input mt-2" placeholder="Nombre de la agencia" style="display:none;">
              </div></div>
          </div>

          <div class="sh-fs">
          <div class="sh-fs__h"><i class="fas fa-clipboard-list"></i> Detalle del producto
            <span class="sh-fs__int" title="Solo lo ve tu equipo. El cliente nunca ve este campo.">interno</span>
          </div>
          <textarea name="package_content" class="form-control" rows="3"
                    placeholder="Ej.&#10;1 maceta de cerámica blanca 30cm&#10;1 planta artificial BOA x18 hojas"></textarea>
          <div class="sh-hint">Lo escribe el almacén. Se imprime en el rótulo para declarar el contenido en la agencia.</div>
          </div>

          <div class="sh-section"><i class="fas fa-box fa-fw me-1"></i> Paquete</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">N° de bultos</label>
              <input type="number" name="package_count" class="form-control" value="1" min="1" max="9999"></div>
            <div class="col-md-3"><label class="form-label">Peso (kg)</label>
              <input type="number" name="weight" class="form-control" step="0.01" min="0" placeholder="0"></div>
            <div class="col-12"><label class="form-label small mb-1">Información adicional</label>
              <input type="text" name="notes" class="form-control" placeholder="Referencia, indicaciones…"></div>
          </div>

        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ══════════════ Modal: Editar envío ══════════════ --}}
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="#" id="formEditar">
        @csrf
        <div class="modal-header bg-light">
          <h5 class="modal-title"><i class="fas fa-pen text-primary me-2"></i>Editar envío <span id="edCode" class="text-muted small ms-1"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="sh-fs">
          <div class="sh-fs__h"><i class="fas fa-user"></i> Destinatario</div>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Tipo de documento</label>
              <select name="document_type" id="ed_document_type" class="form-select">
                @foreach(\App\Models\Tenant\ShippingRequest::DOC_TYPES as $dv => $dl)<option value="{{ $dv }}">{{ $dl }}</option>@endforeach
              </select></div>
            <div class="col-md-4"><label class="form-label">N° de documento</label>
              <input type="text" name="dni" id="ed_dni" class="form-control js-doc-lookup"
                     data-target-name="ed_full_name" data-target-address="ed_shipping_destination" data-ubigeo-group="ed"
                     inputmode="numeric" maxlength="20" autocomplete="off">
              <small class="js-doc-status d-block mt-1"></small></div>
            <div class="col-md-4"><label class="form-label">Teléfono <span class="text-danger">*</span></label>
              <input type="text" name="phone" id="ed_phone" class="form-control js-phone-pe" required maxlength="9" inputmode="numeric" placeholder="999 999 999">
              <small class="js-phone-err text-danger" style="font-size:11px;"></small></div>
            <div class="col-12"><label class="form-label">Nombre completo <span class="text-danger">*</span></label>
              <input type="text" name="full_name" id="ed_full_name" class="form-control" required></div>
          </div>
          </div>

          <input type="hidden" name="delivery_type" id="ed_delivery_type" value="agencia">

          <div class="sh-fs">
          <div class="sh-fs__h"><i class="fas fa-map-marker-alt"></i> Destino</div>

          {{-- ── Rama AGENCIA ── --}}
          <div class="row g-3 ed-agencia">
            <div class="col-12"><label class="form-label">Ubigeo (Departamento / Provincia / Distrito) <span class="text-danger">*</span></label>
              <div class="ubigeo-field" data-ubigeo-group="ed">
                <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                <input type="hidden" name="department_id" data-ub="department">
                <input type="hidden" name="province_id"   data-ub="province">
                <input type="hidden" name="district_id"   data-ub="district">
                <div class="ubigeo-pop" hidden>
                  <div class="ubigeo-col" data-col="dep"></div>
                  <div class="ubigeo-col" data-col="prov"></div>
                  <div class="ubigeo-col" data-col="dist"></div>
                </div>
              </div></div>
            <div class="col-12"><label class="form-label">Agencia</label>
              <div class="agency-field">
                <select class="form-select agency-select">
                  <option value="">— Selecciona —</option>
                  @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                  <option value="__otra__">Otra…</option>
                </select>
                <input type="text" name="shipping_agency" id="ed_shipping_agency" class="form-control agency-input mt-2" style="display:none;">
              </div></div>
          </div>

          {{-- ── Rama DOMICILIO (motorizado) ── --}}
          <div class="row g-3 ed-domicilio" style="display:none;">
            <div class="col-12">
              <div class="alert alert-light border small mb-0 py-2">
                🏍️ <b>Entrega a domicilio</b> — ubicación fijada por el cliente:
                <div class="fw-semibold" id="ed_coords_display">—</div>
                <a href="#" id="ed_maps_link" target="_blank" class="small text-decoration-none">📍 Ver en Google Maps</a>
                <span id="ed_dist_display" class="text-muted ms-2"></span>
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">Costo de envío (S/)</label>
              <input type="number" step="0.10" min="0" name="delivery_price" id="ed_delivery_price" class="form-control" placeholder="0.00"></div>
            {{-- Ocultos preservados (no se pierden al editar) --}}
            <input type="hidden" name="latitude" id="ed_latitude">
            <input type="hidden" name="longitude" id="ed_longitude">
            <input type="hidden" name="formatted_address" id="ed_formatted_address">
            <input type="hidden" name="google_place_id" id="ed_google_place_id">
            <input type="hidden" name="google_maps_url" id="ed_google_maps_url">
            <input type="hidden" name="destination_city" id="ed_destination_city">
            <input type="hidden" name="distance_km" id="ed_distance_km">
            <input type="hidden" name="distance_text" id="ed_distance_text">
            <input type="hidden" name="duration_text" id="ed_duration_text">
          </div>

          {{-- Dirección + Referencia (común a ambos tipos) --}}
          <div class="row g-3 mt-0">
            <div class="col-md-6"><label class="form-label">Dirección</label>
              <input type="text" name="shipping_destination" id="ed_shipping_destination" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Referencia</label>
              <input type="text" name="reference" id="ed_reference" class="form-control"></div>
          </div>
          </div>

          <div class="sh-fs">
          <div class="sh-fs__h"><i class="fas fa-clipboard-list"></i> Detalle del producto
            <span class="sh-fs__int" title="Solo lo ve tu equipo. El cliente nunca ve este campo.">interno</span>
          </div>
          <textarea name="package_content" id="ed_package_content" class="form-control" rows="3"
                    placeholder="Ej.&#10;1 maceta de cerámica blanca 30cm&#10;1 planta artificial BOA x18 hojas"></textarea>
          <div class="sh-hint">Lo escribe el almacén. Se imprime en el rótulo para declarar el contenido en la agencia.</div>
          </div>

          <button class="sh-more" type="button" data-bs-toggle="collapse" data-bs-target="#edPaquete" aria-expanded="false">
            <i class="fas fa-box"></i> Detalles del paquete <span class="fw-normal text-muted">(opcional)</span>
            <i class="fas fa-chevron-down chev"></i>
          </button>
          <div class="collapse" id="edPaquete">
          <div class="sh-fs" style="margin-top:.65rem;">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">N° de bultos</label>
              <input type="number" name="package_count" id="ed_package_count" class="form-control" min="1" max="9999"></div>
            <div class="col-md-6"><label class="form-label">Peso (kg)</label>
              <input type="number" name="weight" id="ed_weight" class="form-control" step="0.01" min="0"></div>
            <div class="col-12"><label class="form-label">Información adicional</label>
              <input type="text" name="notes" id="ed_notes" class="form-control"></div>
          </div>
          </div>
          </div>

        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal: editar precio del envío a domicilio --}}
<div class="modal fade" id="modalPrecio" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="formPrecio">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">💵 Precio de envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-muted small mb-2">Envío <span id="pr_code" class="fw-semibold"></span></div>
          <label class="form-label small text-muted mb-1">Precio a cobrar (S/)</label>
          <div class="input-group">
            <span class="input-group-text">S/</span>
            <input type="number" step="0.10" min="0" name="delivery_price" id="pr_price" class="form-control" placeholder="0.00" autofocus>
          </div>
          <div class="form-text">Déjalo vacío para quitar el precio.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Modal "ojo": todo lo que registró el cliente (solo lectura) ── --}}
<div class="modal fade" id="modalVerEnvio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content sh-view">
      <div class="modal-header sh-view__head">
        <div class="sh-view__id">
          <span class="sh-view__code" id="vwCode">—</span>
          <span class="sh-view__name" id="vwName">—</span>
        </div>
        <span class="sh-view__type" id="vwType"></span>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body sh-view__body" id="vwBody"></div>
      <div class="modal-footer sh-view__foot">
        <a href="#" id="vwWa" target="_blank" rel="noopener" class="sh-act sh-view__wa" style="display:none;">
          <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="#" id="vwCall" class="sh-act sh-act--ghost" style="display:none;border-color:var(--sh-line);">
          <i class="fas fa-phone"></i> Llamar
        </a>
        <a href="#" id="vwMaps" target="_blank" rel="noopener" class="sh-act sh-act--ghost" style="display:none;border-color:var(--sh-line);">
          <i class="fas fa-map-marker-alt"></i> Ubicación
        </a>
        <a href="#" id="vwPrint" target="_blank" class="sh-act sh-act--ghost" style="display:none;border-color:var(--sh-line);">
          <i class="fas fa-print"></i> Rótulo
        </a>
        <button type="button" id="vwEdit" class="sh-act sh-act--primary" style="display:none;">
          <i class="fas fa-pen"></i> Editar
        </button>
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal" style="border-color:var(--sh-line);margin-left:auto;">Cerrar</button>
      </div>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     MODALIDAD DE ENTREGA
     Cambiar la modalidad arrastra agencia, guía, ruta y prioridad. Si el
     envío ya está en un lote IMPRESO se bloquea; un admin puede forzarlo
     marcando la excepción, que queda registrada como tal en la bitácora.
     ══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalModalidad" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="formModalidad">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Cambiar modalidad — <span id="modCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="modLocked" class="alert alert-warning py-2 d-none" style="font-size:.85rem">
          <strong>Este pedido ya pertenece a un lote de impresión (<span id="modBatch"></span>).</strong>
          Para modificar su modalidad deberá retirarlo del lote o generar un nuevo lote.
          @if((auth()->user()->type ?? '') === 'admin')
            <label class="d-flex gap-2 align-items-start mt-2">
              <input type="checkbox" name="force" value="1" class="mt-1">
              <span>Forzar el cambio de todos modos. Quedará registrado como <strong>excepción</strong>.</span>
            </label>
          @endif
        </div>

        <div class="mb-3">
          @foreach(\App\Models\Tenant\ShippingRequest::DELIVERY_TYPES as $key => $label)
            @php $meta = \App\Models\Tenant\ShippingRequest::DELIVERY_META[$key]; @endphp
            <label class="d-flex gap-2 align-items-start p-2 mb-1 rounded js-mod-option"
                   style="border:1px solid {{ $meta['line'] }};background:{{ $meta['bg'] }};cursor:pointer;">
              <input type="radio" name="delivery_type" value="{{ $key }}" class="mt-1" required>
              <span>
                <strong style="color:{{ $meta['color'] }}">{{ $meta['icon'] }} {{ $label }}</strong>
                <div class="small text-muted">
                  @if($key === 'agencia') Requiere agencia, genera guía y entra al manifiesto de despacho.
                  @elseif($key === 'domicilio') Reparto propio con motorizado. Prioridad 1.
                  @else Sin agencia ni guía: genera comprobante interno de entrega.
                  @endif
                </div>
              </span>
            </label>
          @endforeach
        </div>

        <div>
          <label class="form-label" for="modReason">Motivo del cambio (opcional)</label>
          <input id="modReason" name="reason" class="form-control" maxlength="255"
                 placeholder="Ej. el cliente pidió recogerlo en tienda">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="sh-act sh-act--primary">Cambiar modalidad</button>
      </div>
    </form>
  </div>
</div>

{{-- ── ANULAR (con motivo; el envío nunca se borra) ── --}}
<div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="formAnular">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Anular envío — <span id="anuCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.85rem">
          El envío <strong>no se elimina</strong>: queda en el historial con la fecha, el usuario y
          el motivo, y puede restaurarse después.
        </p>
        <label class="form-label" for="anuReason">Motivo de la anulación</label>
        <input id="anuReason" name="reason" class="form-control" maxlength="255"
               placeholder="Ej. el cliente canceló la compra">
      </div>
      <div class="modal-footer">
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="sh-act sh-act--danger">Anular envío</button>
      </div>
    </form>
  </div>
</div>

{{-- ── RESTAURAR un envío anulado ── --}}
<div class="modal fade" id="modalRestaurar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="formRestaurar">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Restaurar pedido — <span id="resCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-secondary py-2" style="font-size:.82rem" id="resInfo"></div>
        <p class="text-muted" style="font-size:.85rem">
          Recupera toda su información y vuelve al estado que tenía antes de anularse.
          Si no pertenece a un lote cerrado, podrá imprimirse de nuevo.
        </p>
        <label class="form-label" for="resReason">Motivo de la restauración</label>
        <input id="resReason" name="reason" class="form-control" maxlength="255"
               placeholder="Ej. el cliente retomó la compra">
      </div>
      <div class="modal-footer">
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="sh-act sh-act--primary">Restaurar pedido</button>
      </div>
    </form>
  </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     REIMPRIMIR RÓTULO (individual)
     El servidor exige motivo cuando el rótulo ya se imprimió; aquí es donde
     se escribe. Los motivos frecuentes están como atajos para no obligar a
     teclear lo mismo cada vez, pero el campo admite texto libre.
     ══════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalReimprimir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reimprimir rótulo — <span id="rpCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:.85rem">
          Este rótulo ya se imprimió <strong id="rpCount">1</strong> vez/veces.
          Cada reimpresión queda registrada con usuario, fecha y motivo; el historial
          anterior no se modifica.
        </p>

        <label class="form-label" for="rpReason">Motivo de la reimpresión *</label>
        <input id="rpReason" class="form-control" maxlength="255" required
               placeholder="Ej. la impresora cortó mal la etiqueta">

        <div class="d-flex flex-wrap gap-1 mt-2">
          @foreach([
            'La impresora falló o cortó mal',
            'Etiqueta dañada o ilegible',
            'Se corrigieron datos del envío',
            'Se extravió el rótulo',
            'Copia para el transportista',
          ] as $motivo)
            <button type="button" class="sh-act sh-act--ghost js-rp-quick" style="font-size:.75rem;border-color:var(--sh-line);">{{ $motivo }}</button>
          @endforeach
        </div>

        <div class="mt-3">
          <label class="form-label" for="rpFormat">Formato</label>
          <select id="rpFormat" class="form-select">
            <option value="a5">A5</option>
            <option value="a4">A4</option>
            <option value="sticker">Sticker 10 cm</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal">Cancelar</button>
        {{-- Sin data-bs-dismiss: cerraría el modal aunque falte el motivo. El JS
             valida y recién entonces dispara el botón de cerrar de la cabecera
             (así no hace falta `window.bootstrap`, que el bundle no expone). --}}
        <button type="button" class="sh-act sh-act--primary" id="rpGo">
          <i class="fas fa-print"></i> Reimprimir
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ── BITÁCORA del envío ── --}}
<div class="modal fade" id="modalBitacora" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Bitácora — <span id="bitCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="bitBody" style="max-height:60vh;overflow-y:auto">
        <div class="text-muted">Cargando…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="sh-act sh-act--ghost" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Fix: los dropdowns dentro de .table-responsive se recortaban por el
    // overflow del contenedor. Con Popper strategy:'fixed' el menú se posiciona
    // respecto al viewport y flota por encima de la tabla sin recortarse.
    function initDropdowns() {
        if (!(window.bootstrap && bootstrap.Dropdown)) return;
        document.querySelectorAll('#shipmentsApp [data-bs-toggle="dropdown"]').forEach(function (el) {
            bootstrap.Dropdown.getOrCreateInstance(el, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                }
            });
        });
    }
    window.__shInitDropdowns = function () { try { initDropdowns(); } catch (e) {} };
    window.__shInitDropdowns();

    // El widget cascader de ubigeo se define en el partial incluido abajo,
    // que expone window.__ubPreset(group, dep, prov, dist).

    // Modal subir guía: precargar action + datos por delegación de clic.
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-upload-guide');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formSubirGuia');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/subir-guia');
        var cli = document.getElementById('sgCliente');
        if (cli) cli.textContent = btn.getAttribute('data-cliente') || '—';
        var ciudad = btn.getAttribute('data-ciudad') || '';
        var ag = btn.getAttribute('data-agencia') || '';
        var dest = document.getElementById('sgDestino');
        if (dest) dest.textContent = [ag, ciudad].filter(Boolean).join(' · ') || '—';
    });

    // Modal editar: precargar los campos leyendo los data-* del botón clicado.
    // Delegación de clic (robusta: no depende de relatedTarget del evento modal).
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-edit-shipment');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formEditar');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/editar');
        var get = function (k) { return btn.getAttribute('data-' + k) || ''; };
        ['full_name','dni','document_type','phone','shipping_destination','reference','shipping_agency','package_content','package_count','weight','notes',
         'delivery_price','latitude','longitude','formatted_address','google_place_id','google_maps_url','destination_city','distance_km','distance_text','duration_text'].forEach(function (f) {
            var el = document.getElementById('ed_' + f);
            if (el) el.value = get(f);
        });
        var code = document.getElementById('edCode');
        if (code) code.textContent = get('shipment_code');

        // Tipo de entrega: alternar la rama agencia/domicilio del modal.
        var type = get('delivery_type') === 'domicilio' ? 'domicilio' : 'agencia';
        var isDom = type === 'domicilio';
        document.getElementById('ed_delivery_type').value = type;
        var bAg = document.querySelector('#modalEditar .ed-agencia');
        var bDom = document.querySelector('#modalEditar .ed-domicilio');
        if (bAg) bAg.style.display = isDom ? 'none' : '';
        if (bDom) bDom.style.display = isDom ? '' : 'none';
        // Desactivar los inputs de la rama oculta para que NO se envíen.
        if (bAg) bAg.querySelectorAll('input,select').forEach(function (el) { el.disabled = isDom; });
        if (bDom) bDom.querySelectorAll('input').forEach(function (el) { el.disabled = !isDom; });

        if (isDom) {
            var lat = get('latitude'), lng = get('longitude');
            var cd = document.getElementById('ed_coords_display');
            if (cd) cd.textContent = get('formatted_address') || ((lat && lng) ? (parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)) : '—');
            var ml = document.getElementById('ed_maps_link');
            if (ml) { var link = get('maps_link'); if (link) { ml.href = link; ml.style.display = ''; } else ml.style.display = 'none'; }
            var dd = document.getElementById('ed_dist_display');
            if (dd) dd.textContent = get('distance_text') ? ('🛵 ' + get('distance_text')) : '';
        } else {
            // Precargar el ubigeo (dep → prov → dist) del envío.
            if (window.__ubPreset) window.__ubPreset('ed', get('department_id'), get('province_id'), get('district_id'));
            if (window.__syncAgency) window.__syncAgency();
        }
    });

    // Modal precio: precargar el precio actual y apuntar el form al envío.
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-edit-price');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formPrecio');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/precio');
        var pr = document.getElementById('pr_price');
        if (pr) pr.value = btn.getAttribute('data-price') || '';
        var code = document.getElementById('pr_code');
        if (code) code.textContent = btn.getAttribute('data-code') || '';
    });

    // Autocompletar por documento: 8 dígitos → DNI (RENIEC), 11 → RUC (SUNAT).
    var docTimer = null;
    document.addEventListener('input', function (ev) {
        var inp = ev.target;
        if (!inp.classList || !inp.classList.contains('js-doc-lookup')) return;

        var status = inp.parentElement.querySelector('.js-doc-status');
        var num = (inp.value || '').replace(/\D+/g, '');
        clearTimeout(docTimer);

        if (num.length !== 8 && num.length !== 11) {
            if (status) status.textContent = '';
            return;
        }

        var kind = num.length === 8 ? 'dni' : 'ruc';
        if (status) { status.className = 'text-muted js-doc-status'; status.textContent = 'Consultando ' + kind.toUpperCase() + '…'; }

        docTimer = setTimeout(function () {
            fetch('{{ url("registro-envio/consulta") }}/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false || !res.data) {
                        if (status) { status.className = 'text-danger js-doc-status'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; }
                        return;
                    }
                    var d = res.data;
                    var full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                    var nameEl = document.getElementById(inp.getAttribute('data-target-name'));
                    if (nameEl && full) nameEl.value = full;
                    // RUC: si trae dirección y el destino está vacío, precargarla.
                    var addrId = inp.getAttribute('data-target-address');
                    if (addrId && d.address) {
                        var addrEl = document.getElementById(addrId);
                        if (addrEl && !addrEl.value) addrEl.value = d.address;
                    }
                    // Ubigeo devuelto por RENIEC/SUNAT → precargar la cascada.
                    var loc = d.location_id;
                    var uDep  = (loc && loc[0]) || d.department_id || '';
                    var uProv = (loc && loc[1]) || d.province_id || '';
                    var uDist = (loc && loc[2]) || d.district_id || '';
                    var grp = inp.getAttribute('data-ubigeo-group');
                    if (grp && (uDep || uDist) && window.__ubPreset) window.__ubPreset(grp, uDep, uProv, uDist);
                    if (status) { status.className = 'text-success js-doc-status'; status.textContent = '✓ ' + (full || 'encontrado'); }
                })
                .catch(function () {
                    if (status) { status.className = 'text-danger js-doc-status'; status.textContent = 'No se pudo consultar.'; }
                });
        }, 450);
    });

    // Input de archivo: mostrar nombre + drag&drop.
    var fileInput = document.getElementById('sgFile');
    var drop = document.getElementById('sgDrop');
    var txt = document.getElementById('sgFileText');
    if (fileInput && drop) {
        fileInput.addEventListener('change', function () {
            txt.innerHTML = fileInput.files.length ? '📎 ' + fileInput.files[0].name : 'Arrastra la imagen aquí<br>o haz clic para seleccionar';
        });
        ['dragover','dragenter'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.style.background = '#e9f5ff'; });
        });
        ['dragleave','drop'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.style.background = '#f8f9fa'; });
        });
        drop.addEventListener('drop', function (ev) {
            if (ev.dataTransfer.files.length) {
                fileInput.files = ev.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }
})();
</script>

{{-- Impresión por lote. IMPORTANTE: el ERP monta Vue en #main-wrapper (envuelve
     este panel) y re-renderiza el DOM, así que NO se pueden capturar referencias
     a nodos ni enlazar listeners directos (se pierden). Se usa SOLO delegación en
     `document` + re-consulta fresca de los elementos en cada evento. --}}
<script>
(function () {
    function $checks() { return Array.prototype.slice.call(document.querySelectorAll('.sh-check')); }
    function $checked() { return $checks().filter(function (c) { return c.checked; }); }
    function refresh() {
        var n = $checked().length;
        var countEl = document.getElementById('shSelCount');
        var bar = document.getElementById('shBulkBar');
        if (countEl) countEl.textContent = n;
        if (bar) bar.style.display = n > 0 ? 'flex' : 'none';
    }

    // Cambios en los checkboxes (delegado — sobrevive al re-render de Vue).
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t) return;
        if (t.id === 'shCheckAll') {
            var on = t.checked;
            $checks().forEach(function (c) { c.checked = on; });
            refresh();
        } else if (t.classList && t.classList.contains('sh-check')) {
            refresh();
        }
    });

    // Clicks en los botones de la barra (delegado).
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest ? ev.target : null;
        var print = ev.target.closest && ev.target.closest('#shPrintSel');
        var clear = ev.target.closest && ev.target.closest('#shClearSel');
        if (print) {
            ev.preventDefault();
            var ids = $checked().map(function (c) { return c.value; });
            if (ids.length) window.open('{{ route("shipments.print_batch") }}?ids=' + ids.join(','), '_blank');
        } else if (clear) {
            ev.preventDefault();
            $checks().forEach(function (c) { c.checked = false; });
            var ca = document.getElementById('shCheckAll'); if (ca) ca.checked = false;
            refresh();
        }
    });

    // Estado inicial + reintento tras el montaje de Vue (que re-renderiza el DOM).
    window.__shBulkRefresh = refresh;
    refresh();
    setTimeout(refresh, 400);
    setTimeout(refresh, 1200);
})();
</script>

{{-- Filtrado DINÁMICO (AJAX): al tocar una tarjeta/pastilla/paginación o buscar,
     se recarga solo #shPanel sin recargar toda la página. Delegación en document
     (a prueba del re-render de Vue en #main-wrapper). --}}
<script>
(function () {
    var BASE = '{{ url("registro-envio") }}';
    var ctrl = null; // petición en vuelo (para cancelarla al seguir escribiendo)

    function busy(on) {
        var box = document.getElementById('shResults');
        if (box) box.style.opacity = on ? '0.5' : '';
        // Defensivo: limpiar cualquier atenuación previa del panel completo.
        var panel = document.getElementById('shPanel');
        if (panel && !on) panel.style.opacity = '';
        var ic = document.querySelector('#shSearchForm button[type="submit"] i');
        if (ic) ic.className = on ? 'fas fa-spinner fa-spin' : 'fas fa-search';
    }

    function fetchDoc(url) {
        if (ctrl) { try { ctrl.abort(); } catch (e) {} }
        ctrl = ('AbortController' in window) ? new AbortController() : null;
        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (r) { return r.text(); })
          .then(function (html) { return new DOMParser().parseFromString(html, 'text/html'); });
    }

    function after(url, push) {
        if (push) { try { history.pushState({ sh: 1 }, '', url); } catch (e) {} }
        else      { try { history.replaceState({ sh: 1 }, '', url); } catch (e) {} }
        if (window.__shInitDropdowns) window.__shInitDropdowns();
        if (window.__shBulkRefresh) window.__shBulkRefresh();
    }

    /** Refresca SOLO los resultados (para escribir en el buscador: sin parpadeo). */
    function swapResults(url) {
        busy(true);
        fetchDoc(url).then(function (doc) {
            var fresh = doc.getElementById('shResults');
            var cur = document.getElementById('shResults');
            if (fresh && cur) cur.innerHTML = fresh.innerHTML;
            after(url, false);
            busy(false);
        }).catch(function (e) {
            if (!e || e.name !== 'AbortError') busy(false);
        });
    }

    /* ── Conservar la posición al refrescar ──────────────────────────────
       Las acciones del panel (estado, pago, anular, editar, modalidad…)
       refrescan la lista. Sin esto la página vuelve arriba y el operador
       pierde de vista justo la fila que acaba de tocar — más molesto cuanto
       más abajo esté. Se ancla al envío afectado y, si no se puede, al
       scroll previo. */

    /** Id del envío al que apunta un control (fila que lo contiene o su URL). */
    function anchorOf(el) {
        var tr = el && el.closest ? el.closest('tr') : null;
        var chk = tr ? tr.querySelector('.sh-check') : null;
        if (chk && chk.value) return chk.value;

        // Los formularios de los modales no están en la fila: el id sale de
        // su action (/registro-envio/{id}/…).
        var action = (el && el.getAttribute && el.getAttribute('action')) || (el && el.action) || '';
        var m = String(action).match(/registro-envio\/(\d+)\//);
        return m ? m[1] : null;
    }

    /** Devuelve la vista a la fila afectada (o al scroll previo) y la resalta. */
    function restoreAnchor(id, y) {
        var chk = id ? document.querySelector('.sh-check[value="' + id + '"]') : null;
        var tr = chk && chk.closest ? chk.closest('tr') : null;

        if (tr) {
            var r = tr.getBoundingClientRect();
            var visible = r.top >= 0 && r.bottom <= (window.innerHeight || document.documentElement.clientHeight);
            if (!visible) tr.scrollIntoView({ block: 'center' });
            tr.classList.add('sh-row--just');
            setTimeout(function () { tr.classList.remove('sh-row--just'); }, 1800);
            return;
        }
        // La fila ya no está en la lista (p. ej. se anuló y el filtro la saca):
        // al menos no saltar al inicio.
        if (typeof y === 'number') window.scrollTo(0, y);
    }

    /** Refresca todo el panel (para clics en filtros: actualiza estados y contadores). */
    function swap(url, opts) {
        opts = opts || {};
        var y = (typeof opts.scrollY === 'number') ? opts.scrollY : window.scrollY;
        busy(true);
        fetchDoc(url).then(function (doc) {
            var fresh = doc.getElementById('shPanel');
            var cur = document.getElementById('shPanel');
            if (fresh && cur) cur.innerHTML = fresh.innerHTML;
            after(url, !opts.noPush);
            if (opts.keep) restoreAnchor(opts.anchor, y);
            busy(false);
        }).catch(function (e) {
            if (!e || e.name !== 'AbortError') { busy(false); window.location = url; }
        });
    }

    /**
     * Acciones del panel por AJAX.
     *
     * Antes cada formulario (pago, anular, restaurar, modalidad, editar,
     * precio, quitar del lote…) hacía un submit nativo: recarga completa y
     * vuelta al inicio de la lista. Ahora se envían por fetch y se refresca
     * solo #shPanel, volviendo a la fila donde estaba el operador.
     *
     * Quedan fuera a propósito: el buscador (tiene su propio flujo, que no
     * debe perder el foco) y los formularios con archivos (subir guía), que
     * siguen con el submit nativo.
     */
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.id === 'shSearchForm') return;
        if (form.hasAttribute('data-no-ajax')) return;
        if ((form.getAttribute('enctype') || '') === 'multipart/form-data') return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;

        // Solo el módulo de envíos. Se incluye el POST al índice, que es el
        // alta de un envío nuevo desde el panel.
        var action = form.action || '';
        if (action.indexOf(BASE) !== 0) return;

        ev.preventDefault();

        var anchor = anchorOf(form);
        var y      = window.scrollY;
        var btn    = form.querySelector('[type="submit"]');
        if (btn) btn.disabled = true;

        busy(true);

        fetch(action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function () {
            // Si la acción venía de un modal, se cierra por su propio botón
            // (el bundle no expone `window.bootstrap`).
            var modal = form.closest ? form.closest('.modal') : null;
            if (modal) {
                var closer = modal.querySelector('[data-bs-dismiss="modal"]');
                if (closer) closer.click();
            }
            swap(location.href, { noPush: true, keep: true, anchor: anchor, scrollY: y });
        })
        .catch(function () {
            // Si el AJAX falla, el submit nativo es el respaldo: la acción no
            // se puede perder en silencio.
            busy(false);
            if (btn) btn.disabled = false;
            form.submit();
        });
    });

    // Tarjetas / pastillas / paginación (links dentro de #shPanel hacia el índice).
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest ? ev.target.closest('#shPanel a[href]') : null;
        if (!a) return;
        if (a.getAttribute('target') === '_blank') return;      // guía / imprimir / ver ubicación
        var href = a.href;
        if (!href || href.indexOf(BASE) !== 0) return;          // solo el índice de envíos
        if (href.indexOf('/', BASE.length + 1) !== -1) return;  // no interceptar /registro-envio/{id}/...
        ev.preventDefault();
        swap(href);
    });

    // Buscador: submit + escritura en vivo (con debounce).
    function searchUrl() {
        var f = document.getElementById('shSearchForm');
        if (!f) return null;
        var qs = new URLSearchParams(new FormData(f)).toString();
        return f.action + (qs ? ('?' + qs) : '');
    }
    document.addEventListener('submit', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#shSearchForm')) return;
        ev.preventDefault();
        var u = searchUrl(); if (u) swapResults(u);   // solo resultados: no se pierde el foco
    });
    var st = null;
    document.addEventListener('input', function (ev) {
        if (ev.target.id !== 'shSearchInput') return;
        var x = document.getElementById('shClearSearch');
        if (x) x.style.display = ev.target.value ? '' : 'none';
        clearTimeout(st);
        st = setTimeout(function () { var u = searchUrl(); if (u) swapResults(u); }, 300);
    });
    // ── Calendario de rango (un solo campo) ──────────────────────────────
    // El estado vive en el dataset de #shCal, así sobrevive al re-render AJAX
    // del panel. Clic en el día de inicio y clic en el de fin → aplica.
    var CAL_MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio',
                      'agosto','septiembre','octubre','noviembre','diciembre'];
    function calFmt(ds) { var p = ds.split('-'); return p[2] + '/' + p[1]; }

    /**
     * "Hoy" recalculado EN CADA RENDER.
     *
     * `data-today` lo pinta el servidor al cargar la página, pero este panel
     * es de operación y suele quedarse abierto de un día para otro: pasada la
     * medianoche el atributo se queda en ayer, el calendario marca el día
     * equivocado y el día actual queda deshabilitado como "futuro".
     *
     * Se toma el MAYOR entre la fecha del navegador y la del servidor: si la
     * pestaña está vieja gana el navegador, y si el reloj del equipo está
     * atrasado no se retrocede respecto a lo que dijo el servidor.
     */
    function calToday(el) {
        var d = new Date();
        var local = d.getFullYear() + '-'
                  + String(d.getMonth() + 1).padStart(2, '0') + '-'
                  + String(d.getDate()).padStart(2, '0');
        var server = (el && el.dataset.today) || '';
        return local > server ? local : server;
    }

    function calRender(el) {
        if (!el) return;
        var today = calToday(el);
        // Si el día cambió con la pestaña abierta, se reabre el calendario en
        // el mes vigente en vez de quedarse en el mes con el que se cargó.
        if (el.dataset.today && el.dataset.today !== today) {
            el.dataset.today = today;
            if (!el.dataset.selStart) { delete el.dataset.viewYear; delete el.dataset.viewMonth; }
        }
        var start = el.dataset.selStart || '';
        var end   = el.dataset.selEnd || '';
        var vy = parseInt(el.dataset.viewYear || '', 10);
        var vm = parseInt(el.dataset.viewMonth || '', 10);
        if (isNaN(vy) || isNaN(vm)) {
            var d0 = new Date((start || today) + 'T00:00:00');
            vy = d0.getFullYear(); vm = d0.getMonth();
            el.dataset.viewYear = vy; el.dataset.viewMonth = vm;
        }
        el.querySelector('[data-cal="title"]').textContent = CAL_MONTHS[vm] + ' ' + vy;
        var startDow = (new Date(vy, vm, 1).getDay() + 6) % 7; // lunes = 0
        var dim = new Date(vy, vm + 1, 0).getDate();
        var html = '';
        for (var i = 0; i < startDow; i++) html += '<span class="sh-cal__d is-empty"></span>';
        for (var day = 1; day <= dim; day++) {
            var ds = vy + '-' + String(vm + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            var c = 'sh-cal__d';
            var future = ds > today;
            if (future) c += ' is-off';
            if (ds === today) c += ' is-today';
            if (start && end && ds > start && ds < end) c += ' is-in';
            if (ds === start) c += ' is-start';
            if (ds === end) c += ' is-end';
            html += future
                ? '<span class="' + c + '">' + day + '</span>'
                : '<button type="button" class="' + c + '" data-cal-day="' + ds + '">' + day + '</button>';
        }
        el.querySelector('[data-cal="grid"]').innerHTML = html;
        var hint = el.querySelector('[data-cal="hint"]');
        if (start && !end) hint.textContent = 'Ahora elige el día de fin';
        else if (start && end) hint.textContent = 'Del ' + calFmt(start) + ' al ' + calFmt(end);
        else hint.textContent = 'Elige el día de inicio';
    }
    // Render al abrir el desplegable (y si ya está en el DOM al cargar).
    document.addEventListener('shown.bs.dropdown', function () {
        var el = document.getElementById('shCal'); if (el) calRender(el);
    });
    (function () { var el = document.getElementById('shCal'); if (el) calRender(el); })();
    // Navegación de mes + selección de días (delegado en document).
    document.addEventListener('click', function (ev) {
        var el = document.getElementById('shCal'); if (!el) return;
        var nav = ev.target.closest && ev.target.closest('[data-cal="prev"],[data-cal="next"]');
        if (nav) {
            ev.preventDefault();
            var vy = parseInt(el.dataset.viewYear, 10), vm = parseInt(el.dataset.viewMonth, 10);
            vm += (nav.getAttribute('data-cal') === 'next' ? 1 : -1);
            if (vm < 0) { vm = 11; vy--; } if (vm > 11) { vm = 0; vy++; }
            el.dataset.viewYear = vy; el.dataset.viewMonth = vm; calRender(el);
            return;
        }
        var dayBtn = ev.target.closest && ev.target.closest('[data-cal-day]');
        if (dayBtn && el.contains(dayBtn)) {
            ev.preventDefault();
            var ds = dayBtn.getAttribute('data-cal-day');
            var s = el.dataset.selStart || '', e = el.dataset.selEnd || '';
            if (!s || (s && e)) {
                // 1er clic (o reinicio): fija inicio, limpia fin.
                el.dataset.selStart = ds; el.dataset.selEnd = ''; calRender(el);
            } else {
                // 2º clic: fija fin (ordena si es anterior) y APLICA.
                var a = s, b = ds; if (b < a) { var t = a; a = b; b = t; }
                el.dataset.selStart = a; el.dataset.selEnd = b; calRender(el);
                var base = el.dataset.base;
                var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'from=' + a + '&to=' + b;
                if (typeof swap === 'function') swap(url); else window.location.href = url;
            }
        }
    });

    // ── Modal "ojo": rellena la ficha con lo que registró el cliente ──
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('.js-view-shipment');
        if (!btn) return;
        var data;
        try { data = JSON.parse(btn.getAttribute('data-view') || '{}'); } catch (e) { return; }
        var set = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = val || '—'; };
        set('vwCode', data.code); set('vwName', data.name);
        var typeEl = document.getElementById('vwType');
        if (typeEl) { typeEl.textContent = data.type || ''; typeEl.className = 'sh-view__type ' + (data.type === 'Domicilio' ? 'is-dom' : 'is-ag'); }
        var body = document.getElementById('vwBody');
        if (body) {
            body.innerHTML = '';
            var secs = data.sections || {};
            Object.keys(secs).forEach(function (h) {
                var rows = secs[h]; if (!rows || !Object.keys(rows).length) return;
                var sec = document.createElement('div'); sec.className = 'sh-view__sec';
                var t = document.createElement('div'); t.className = 'sh-view__t'; t.textContent = h; sec.appendChild(t);
                Object.keys(rows).forEach(function (k) {
                    var r = document.createElement('div'); r.className = 'sh-view__row';
                    var ks = document.createElement('span'); ks.className = 'k'; ks.textContent = k;
                    var vs = document.createElement('span'); vs.className = 'v'; vs.textContent = rows[k];
                    r.appendChild(ks); r.appendChild(vs); sec.appendChild(r);
                });
                body.appendChild(sec);
            });
        }
        // Acciones del pie: mostrar solo las que aplican.
        var setLink = function (id, href, show) {
            var el = document.getElementById(id); if (!el) return;
            if (show && href) { el.href = href; el.style.display = ''; } else { el.style.display = 'none'; }
        };
        setLink('vwMaps', data.maps, !!data.maps);
        setLink('vwWa', data.phone ? ('https://wa.me/' + data.phone) : '', !!data.phone);
        setLink('vwCall', data.phone ? ('tel:+' + data.phone) : '', !!data.phone);
        setLink('vwPrint', data.print, !!data.print);
        // Con impresiones previas el rótulo exige motivo: el botón deja de ser
        // un enlace directo y delega en el botón de la fila (que abre el modal
        // por data-api). Sin impresiones previas imprime de una.
        var vwPrintEl = document.getElementById('vwPrint');
        if (vwPrintEl) {
            var reprint = (data.print_count || 0) > 0;
            vwPrintEl.classList.toggle('js-reprint-from-view', reprint);
            vwPrintEl.setAttribute('data-id', data.id || '');
            if (reprint) {
                vwPrintEl.removeAttribute('target');
                vwPrintEl.setAttribute('href', '#');
            } else {
                vwPrintEl.setAttribute('target', '_blank');
            }
            vwPrintEl.innerHTML = reprint
                ? '<i class="fas fa-print"></i> Reimprimir'
                : '<i class="fas fa-print"></i> Rótulo';
        }
        var modalEl = document.getElementById('modalVerEnvio');
        if (modalEl) modalEl.setAttribute('data-current-id', data.id || '');
        var editBtn = document.getElementById('vwEdit');
        if (editBtn) editBtn.style.display = data.id ? '' : 'none';
    });

    // "Editar" desde el modal-ojo: cierra esta ficha y abre el modal de edición
    // de la misma fila (reutiliza su botón, que ya trae todos los data-*).
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#vwEdit')) return;
        var modalEl = document.getElementById('modalVerEnvio');
        var id = modalEl ? modalEl.getAttribute('data-current-id') : '';
        var rowBtn = id ? document.querySelector('.js-edit-shipment[data-id="' + id + '"]') : null;
        // `bootstrap` no existe como global (Vite no lo expone), así que el
        // hide programático fallaba en silencio y la ficha quedaba abierta
        // detrás del modal de edición. Se cierra disparando su propio botón.
        var closer = modalEl ? modalEl.querySelector('[data-bs-dismiss="modal"]') : null;
        if (closer) closer.click();
        if (rowBtn) { setTimeout(function () { rowBtn.click(); }, 200); }
    });

    // ✕ limpiar búsqueda (sin recargar).
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#shClearSearch')) return;
        ev.preventDefault();
        var inp = document.getElementById('shSearchInput');
        if (inp) { inp.value = ''; inp.focus(); }
        ev.target.closest('#shClearSearch').style.display = 'none';
        var u = searchUrl(); if (u) swapResults(u);
    });
    // Filas por página: cada opción lleva su URL completa.
    document.addEventListener('change', function (ev) {
        if (ev.target.id !== 'shPerPage') return;
        if (ev.target.value) swap(ev.target.value);
    });

    // Confirmar/revertir pago (individual) por AJAX — sin recargar la página.
    document.addEventListener('submit', function (ev) {
        var f = ev.target.closest ? ev.target.closest('.js-pay-form') : null;
        if (!f) return;
        ev.preventDefault();
        busy(true);
        fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function () { swap(location.href, { noPush: true }); })
            .catch(function () { busy(false); f.submit(); });
    });

    // Cambiar el ESTADO de los seleccionados (por lote) → confirma y avisa por WhatsApp.
    document.addEventListener('click', function (ev) {
        var opt = ev.target.closest && ev.target.closest('.sh-bulk-status');
        if (!opt) return;
        ev.preventDefault();
        var ids = Array.prototype.slice.call(document.querySelectorAll('.sh-check'))
            .filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        var label = opt.textContent.trim();
        if (!window.confirm('¿Cambiar ' + ids.length + ' envío(s) a «' + label + '»? Se avisará a cada cliente por WhatsApp.')) return;
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('ids', ids.join(','));
        fd.append('status', opt.getAttribute('data-status'));
        busy(true);
        var y = window.scrollY;
        fetch('{{ route("shipments.status_bulk") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function () { swap(location.href, { noPush: true, keep: true, scrollY: y }); })
            .catch(function () { busy(false); });
    });

    // Confirmar pago de los SELECCIONADOS (por lote).
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#shPaySel')) return;
        ev.preventDefault();
        var ids = Array.prototype.slice.call(document.querySelectorAll('.sh-check'))
            .filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) return;
        var fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('ids', ids.join(','));
        busy(true);
        var y2 = window.scrollY;
        fetch('{{ route("shipments.payment_bulk") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function () { swap(location.href, { noPush: true, keep: true, scrollY: y2 }); })
            .catch(function () { busy(false); });
    });

    // Cambio de estado (select nativo, no se recorta) → POST por AJAX + refresco.
    document.addEventListener('change', function (ev) {
        var sel = ev.target;
        if (!sel.classList || !sel.classList.contains('sh-status-select')) return;
        var form = sel.closest ? sel.closest('form') : null;
        if (!form) return;
        // IMPORTANTE: serializar ANTES de deshabilitar. Los controles deshabilitados
        // NO se incluyen en FormData, y el POST se iba sin el campo `status`.
        var fd = new FormData(form);
        var anchor = anchorOf(sel), y = window.scrollY;
        sel.disabled = true;
        busy(true);
        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r && !r.ok) { sel.disabled = false; busy(false); return; }
                // Vuelve a la fila que se acaba de cambiar. (busy(false) lo hace swap.)
                swap(location.href, { noPush: true, keep: true, anchor: anchor, scrollY: y });
            })
            .catch(function () { sel.disabled = false; busy(false); form.submit(); });
    });

    // Botón atrás/adelante del navegador.
    window.addEventListener('popstate', function () { swap(location.href, { noPush: true }); });
})();
</script>
@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')
@include('tenant.shipments.partials.logistics-js')
@endpush
