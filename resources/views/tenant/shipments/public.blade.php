<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de envío · {{ $storeName }}</title>
    @php
        // Color de la tienda ya ajustado para contraste (StoreBrandColor).
        $accent = $brand['accent'] ?? '#b4472c';
    @endphp
    <meta name="theme-color" content="{{ $accent }}">
    <style>
        /* Diseño: comprador en un Android de gama media que abre el enlace
           desde WhatsApp, muchas veces en la calle. Tema claro, contraste
           alto, objetivos de 44px. Neutros cálidos; el color de la TIENDA
           solo en acciones y selección. Ver PRODUCT.md. */
        :root {
            --accent: {{ $accent }};
            --accent-soft: #f6efe9;
            --accent-line: #e3cfc4;
            --paper: #faf8f5;
            --surface: #fffefc;
            --ink: #26211d;
            --ink-2: #5a524b;
            --muted: #6b635b;
            --line: #e7e1d9;
            --line-2: #d7cfc5;
            --ok: #1f7a46; --ok-soft: #eaf6ee;
            --warn: #7a4a00; --warn-soft: #fff5e0; --warn-line: #f2d9a6;
            --bad: #b42318; --bad-soft: #fdf0ee;
            --brand: var(--accent); --brand-d: var(--accent); --bg: var(--paper); --moto: var(--accent);
            --r-sm: 10px; --r: 14px; --r-lg: 18px;
            --ease: cubic-bezier(.22,1,.36,1);
        }
        @supports (background: color-mix(in srgb, red 10%, white)) {
            :root {
                --accent-soft: color-mix(in srgb, var(--accent) 8%, var(--surface));
                --accent-line: color-mix(in srgb, var(--accent) 38%, var(--surface));
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif; background:var(--paper); color:var(--ink); -webkit-font-smoothing:antialiased; line-height:1.45; }
        button, input, select, textarea { font: inherit; color: inherit; }
        :focus-visible { outline: 3px solid var(--accent-line); outline-offset: 2px; }
        .wrap { max-width: 540px; margin: 0 auto; padding: 0 16px 40px; }

        /* En el teléfono el formulario ES la página: sin tarjeta flotante. */
        .card { padding: 4px 0 0; }
        @media (min-width: 600px) {
            .wrap { padding-top: 28px; }
            .card { background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:26px 28px; box-shadow:0 1px 2px rgba(38,33,29,.04), 0 18px 48px -28px rgba(38,33,29,.28); }
        }

        /* ── Cabecera: la tienda primero ── */
        .head { display:flex; align-items:center; gap:12px; padding:16px 0 14px; border-bottom:1px solid var(--line); margin-bottom:16px; }
        .head__logo { flex:0 0 auto; width:52px; height:52px; border-radius:14px; background:var(--surface); border:1px solid var(--line); display:grid; place-items:center; overflow:hidden; }
        .head__logo img { max-width:44px; max-height:44px; object-fit:contain; }
        .head__logo b { font-size:22px; color:var(--accent); }
        .head__tx { min-width:0; }
        .head__store { font-size:16px; font-weight:750; letter-spacing:-.01em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .head__sub { font-size:13px; color:var(--muted); display:flex; align-items:center; gap:5px; margin-top:1px; }
        .head__sub svg { flex:0 0 auto; color:var(--ok); }

        /* ── Pasos: barra segmentada ── */
        .stepper { display:flex; gap:6px; margin:0 0 18px; }
        .st { flex:1; display:flex; flex-direction:column; gap:6px; }
        .st .dot { height:4px; border-radius:99px; background:var(--line); font-size:0; color:transparent; transition:background .25s var(--ease); }
        .st .t { font-size:12px; font-weight:600; color:var(--muted); }
        .st.active .dot { background:var(--accent); }
        .st.active .t { color:var(--ink); font-weight:700; }
        .st.done .dot { background:var(--accent); opacity:.45; }
        .st.done .t { color:var(--ink-2); }
        .st-line { display:none; }

        /* ── Encabezado de cada paso ── */
        .step-head { margin:4px 0 16px; }
        .step-h { font-size:22px; line-height:1.2; font-weight:800; margin:0 0 4px; letter-spacing:-.02em; }
        .step-sub { font-size:14px; color:var(--muted); margin:0; }
        .sec-h { display:flex; align-items:center; gap:10px; font-size:16px; font-weight:750; margin:26px 0 2px; }
        .sec-h i { font-style:normal; flex:0 0 auto; width:26px; height:26px; border-radius:99px; background:var(--accent-soft); color:var(--accent); display:grid; place-items:center; font-size:13px; font-weight:800; }

        /* ── Campos ── */
        label { display:block; font-size:14px; font-weight:650; margin:16px 0 6px; }
        .step > label:first-of-type { margin-top:0; }
        .req::after { content:" *"; color:var(--bad); }
        input[type=text], input[type=tel], select, textarea {
            width:100%; min-height:48px; padding:12px 14px; border:1.5px solid var(--line-2); border-radius:var(--r); font-size:16px; background:var(--surface);
            transition:border-color .15s, box-shadow .15s;
        }
        input::placeholder { color:#9a9189; }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 4px var(--accent-soft); }
        .hint { font-size:13px; color:var(--muted); margin-top:5px; display:block; }
        .doc-types { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
        .doc-opt { position:relative; margin:0; }
        .doc-opt input { position:absolute; opacity:0; width:0; height:0; }
        .doc-opt span { display:inline-flex; align-items:center; min-height:40px; padding:8px 14px; border:1.5px solid var(--line-2); border-radius:99px; font-size:14px; font-weight:600; color:var(--ink-2); background:var(--surface); cursor:pointer; transition:.15s; }
        .doc-opt input:checked + span { border-color:var(--accent); background:var(--accent-soft); color:var(--accent); }
        .doc-opt input:focus-visible + span { box-shadow:0 0 0 4px var(--accent-soft); }
        input.is-auto { background:#f3efe9; font-weight:650; cursor:not-allowed; }
        input.is-auto:focus { border-color:var(--line-2); box-shadow:none; }
        .js-name-manual { background:none; border:0; padding:0 0 0 4px; color:var(--accent); font-size:13px; font-weight:600; text-decoration:underline; cursor:pointer; }
        .hint.is-warn, .hint.is-warn .js-name-manual { color:var(--warn); }

        /* ── Botones ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:52px; padding:14px; border:none; border-radius:var(--r); background:var(--accent); color:#fffefc; font-size:16px; font-weight:750; cursor:pointer; transition:transform .12s var(--ease), filter .15s; }
        .btn:hover { filter:brightness(.94); } .btn:active { transform:scale(.985); }
        .btn[disabled] { opacity:.6; cursor:progress; }
        .btn-ghost { background:transparent; color:var(--ink-2); border:1.5px solid var(--line-2); }
        .btn-ghost:hover { filter:none; background:#f3efe9; }
        .row-btns { display:flex; gap:10px; margin-top:24px; }
        .row-btns:empty { display:none; }
        .row-btns .btn { flex:1; }
        .row-btns .btn-ghost { flex:0 0 34%; }

        /* ── Paso 1: cómo lo recibe ── */
        .dtype { display:flex; flex-direction:column; gap:10px; }
        .dcard { position:relative; display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:4px 14px; text-align:left; width:100%; border:1.5px solid var(--line); background:var(--surface); border-radius:var(--r-lg); padding:16px; cursor:pointer; transition:border-color .18s, box-shadow .18s, transform .12s var(--ease); }
        .dcard:hover { border-color:var(--accent-line); box-shadow:0 10px 26px -20px rgba(38,33,29,.45); }
        .dcard:active { transform:scale(.99); }
        .dcard .tx { grid-column:2; grid-row:1; }
        .dcard .ic { grid-column:1; grid-row:1 / span 2; align-self:start; width:48px; height:48px; border-radius:14px; background:var(--accent-soft); color:var(--accent); display:grid; place-items:center; }
        .dcard .tx b { display:block; font-size:16.5px; font-weight:750; letter-spacing:-.01em; }
        .dcard .tx span { display:block; font-size:13.5px; color:var(--muted); margin-top:2px; }
        .dcard .chev { grid-column:3; grid-row:1 / span 2; color:var(--ink-2); }
        .dcard .meta { grid-column:2; grid-row:2; display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .chip { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; font-weight:650; color:var(--ink-2); background:#f3efe9; border-radius:99px; padding:4px 10px; }
        .chip svg { flex:0 0 auto; }
        .chip.is-ok { background:var(--ok-soft); color:var(--ok); }
        .dcard .go { display:none; }
        .dcard--previa { border-color:var(--accent); }
        .dcard--previa::after { content:'Continuar donde lo dejaste'; position:absolute; top:-10px; left:14px; font-size:11px; font-weight:750; color:#fffefc; background:var(--accent); border-radius:99px; padding:2px 10px; }

        .tag { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; padding:6px 12px; border-radius:99px; margin-bottom:10px; background:var(--accent-soft); color:var(--accent); }

        /* ── Tu pedido ── */
        .order { border:1px solid var(--line); background:var(--surface); border-radius:var(--r-lg); margin-bottom:18px; }
        .order summary { list-style:none; display:flex; align-items:center; gap:12px; padding:12px 14px; cursor:pointer; min-height:56px; }
        .order summary::-webkit-details-marker { display:none; }
        .order__thumbs { display:flex; flex:0 0 auto; }
        .order__thumbs span { width:40px; height:40px; border-radius:10px; border:2px solid var(--surface); background:#f3efe9 center/cover no-repeat; margin-left:-12px; display:grid; place-items:center; font-size:12px; font-weight:750; color:var(--ink-2); }
        .order__thumbs span:first-child { margin-left:0; }
        .order__tx { flex:1; min-width:0; }
        .order__tx b { display:block; font-size:15px; }
        .order__tx small { color:var(--muted); font-size:13px; }
        .order__total { font-weight:800; font-size:15px; white-space:nowrap; }
        .order__chev { color:var(--muted); transition:transform .2s var(--ease); }
        .order[open] .order__chev { transform:rotate(90deg); }
        .order__lines { border-top:1px solid var(--line); padding:6px 14px 10px; }
        .order__line { display:flex; gap:10px; align-items:center; padding:7px 0; font-size:14px; }
        .order__line + .order__line { border-top:1px dashed var(--line); }
        .order__line .q { color:var(--muted); font-weight:650; flex:0 0 auto; }
        .order__line .n { flex:1; min-width:0; }
        .order__line .v { font-weight:650; white-space:nowrap; }

        /* ── Fechas estimadas ── */
        .eta { border:1px solid var(--line); background:var(--surface); border-radius:var(--r-lg); padding:14px 16px; margin:18px 0 0; }
        .eta__t { font-size:15px; font-weight:750; margin-bottom:12px; }
        .tl { list-style:none; margin:0; padding:0; }
        .tl li { position:relative; padding:0 0 14px 26px; font-size:14px; }
        .tl li:last-child { padding-bottom:0; }
        .tl li::before { content:''; position:absolute; left:3px; top:4px; width:10px; height:10px; border-radius:99px; background:var(--surface); border:2px solid var(--accent); }
        .tl li::after { content:''; position:absolute; left:8px; top:18px; bottom:-2px; width:2px; background:var(--line); }
        .tl li:last-child::after { display:none; }
        .tl li.is-now::before { background:var(--accent); }
        .tl b { display:block; font-weight:750; }
        .tl small { color:var(--muted); font-size:13px; }
        .eta__s { font-size:13px; color:var(--muted); margin-top:12px; }

        /* ── Recojo en tienda ── */
        .pickup-box { background:var(--accent-soft); border-radius:var(--r); padding:14px 16px; margin-bottom:14px; }
        .pickup-box__t { font-size:15px; font-weight:750; }
        .pickup-box__addr { font-size:14px; margin-top:4px; font-weight:600; }
        .pickup-box__s { font-size:13.5px; color:var(--ink-2); margin-top:6px; }

        /* ── Cliente encontrado ── */
        .found { background:var(--accent-soft); border-radius:var(--r); padding:12px 14px; margin-top:10px; }
        .found .t { font-size:12px; color:var(--accent); font-weight:750; }
        .found .n { font-size:16px; font-weight:750; margin:2px 0 8px; }
        .found .acts { display:flex; gap:8px; }
        .found .acts button { flex:1; min-height:44px; border-radius:var(--r-sm); border:none; font-weight:700; font-size:14px; cursor:pointer; }
        .found .use { background:var(--accent); color:#fffefc; }
        .found .new { background:var(--surface); color:var(--ink-2); box-shadow:inset 0 0 0 1.5px var(--line-2); }

        /* ── Google Maps ── */
        .geo-btn { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:48px; margin-top:4px;
                   border:none; border-radius:var(--r); background:var(--accent); color:#fffefc; font-weight:700; font-size:15px; cursor:pointer; }
        .geo-btn[disabled] { opacity:.7; cursor:wait; }
        .geo-msg { margin-top:8px; padding:10px 12px; border-radius:var(--r-sm); font-size:13.5px; background:var(--warn-soft); border:1px solid var(--warn-line); color:var(--warn); }
        .geo-or { text-align:center; font-size:13px; color:var(--muted); margin:10px 0 6px; }
        .map-search { position:relative; }
        #shipMap { width:100%; height:260px; border-radius:var(--r); border:1.5px solid var(--line); margin-top:10px; background:#ece6de; }
        .map-picked { margin-top:8px; padding:9px 12px; background:var(--ok-soft); border-radius:var(--r-sm); font-size:13.5px; display:none; }
        .map-picked.show { display:block; }
        .map-picked .c { color:var(--ok); font-weight:700; }
        .map-note { font-size:13px; color:var(--muted); margin-top:6px; }
        .map-off { background:var(--warn-soft); border:1px solid var(--warn-line); border-radius:var(--r); padding:12px 14px; font-size:14px; color:var(--warn); margin-top:8px; }

        /* ── Confirmación ── */
        .conf { border:1px solid var(--line); background:var(--surface); border-radius:var(--r-lg); overflow:hidden; }
        .conf .h { display:none; }
        .conf .rows { padding:2px 16px 8px; }
        .conf .r { display:flex; justify-content:space-between; gap:14px; padding:9px 0; border-bottom:1px solid #f0ebe4; font-size:14.5px; }
        .conf .r:last-child { border-bottom:none; }
        .conf .r .k { color:var(--muted); flex:0 0 38%; }
        .conf .r .v { text-align:right; font-weight:650; word-break:break-word; }
        .conf .r[hidden] { display:none; }
        .conf-sec + .conf-sec { border-top:1px solid var(--line); }
        .conf-sec__h { display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:15px; font-weight:750; padding:12px 16px 0; margin:0; }
        .conf-edit { min-height:36px; background:none; border:0; padding:4px 0; font-size:14px; font-weight:700; color:var(--accent); cursor:pointer; }
        .conf-edit:hover { text-decoration:underline; }
        .conf-warn { display:flex; gap:9px; align-items:flex-start; margin:0 16px 14px; padding:10px 12px; border-radius:var(--r-sm); background:var(--warn-soft); color:var(--warn); font-size:13.5px; }

        /* ── Sorteo ── */
        .rfz { border:1px solid var(--line); background:var(--surface); border-radius:var(--r-lg); padding:14px; margin-top:16px; }
        .rfz__head { display:flex; gap:12px; align-items:center; }
        .rfz__img { width:60px; height:60px; object-fit:cover; border-radius:12px; flex-shrink:0; }
        .rfz__tx { flex:1; min-width:0; }
        .rfz__tag { font-size:12px; font-weight:750; color:var(--accent); }
        .rfz__name { font-size:15.5px; font-weight:750; line-height:1.25; margin-top:1px; }
        .rfz__prize { font-size:13px; color:var(--muted); margin-top:2px; }
        .rfz__desc { font-size:13.5px; color:var(--ink-2); margin-top:10px; }
        .rfz__dates { font-size:13px; color:var(--ink-2); background:#f3efe9; border-radius:var(--r-sm); padding:7px 10px; margin-top:10px; }
        .rfz__terms { margin-top:10px; font-size:13px; }
        .rfz__terms summary { cursor:pointer; color:var(--accent); font-weight:700; }
        .rfz__terms div { margin-top:6px; max-height:170px; overflow-y:auto; border:1px solid var(--line); border-radius:var(--r-sm); padding:10px; white-space:pre-line; color:var(--ink-2); }
        .rfz__chk { margin-top:12px; }
        .rfz__cond { font-size:12.5px; color:var(--muted); margin-top:8px; text-align:center; }

        /* ── Casillas ── */
        .terms-box { margin-top:18px; }
        .chk { display:flex; align-items:flex-start; gap:12px; background:var(--surface); border:1.5px solid var(--line-2); border-radius:var(--r); padding:13px 14px; font-size:14.5px; font-weight:600; cursor:pointer; margin:0; }
        .chk input { flex:0 0 auto; margin:1px 0 0; width:20px; height:20px; accent-color:var(--accent); }
        .chk:has(input:checked) { border-color:var(--accent); background:var(--accent-soft); }
        .chk-inline { margin-top:16px; align-items:center; }
        .chk-inline input { margin-top:0; }
        .cond { font-size:13px; color:var(--muted); margin-top:10px; }
        .cond ul { margin:6px 0 0; padding-left:18px; }
        .cond summary { cursor:pointer; font-weight:650; color:var(--ink-2); min-height:32px; display:flex; align-items:center; }

        /* ── Costos ── */
        .cost-box { margin:18px 0 4px; border:1px solid var(--line); border-radius:var(--r-lg); background:var(--surface); overflow:hidden; }
        .cost-box__t { padding:12px 16px 4px; font-size:15px; font-weight:750; }
        .cost-row { display:flex; align-items:flex-start; gap:12px; padding:10px 16px; }
        .cost-row + .cost-row { border-top:1px solid #f0ebe4; }
        .cost-row__l { flex:1; min-width:0; font-size:14px; }
        .cost-row__l small { display:block; margin-top:2px; font-size:13px; color:var(--muted); font-weight:400; }
        .cost-row__v { flex:0 0 auto; font-size:14.5px; font-weight:750; white-space:nowrap; }
        .cost-row__v.is-free { color:var(--ok); }
        .cost-row__v.is-soft { font-size:13px; font-weight:650; color:var(--muted); }
        .cost-row__v.is-warn { font-size:13px; color:var(--warn); }
        .cost-row--extra { background:var(--warn-soft); }

        /* ── Éxito ── */
        .ok-wrap { text-align:center; padding:10px 0 4px; }
        .ok-check { width:72px; height:72px; border-radius:99px; background:var(--ok); color:#fffefc; display:grid; place-items:center; margin:6px auto 14px; animation:pop .4s var(--ease); }
        @keyframes pop { from { transform:scale(.7); opacity:0; } to { transform:scale(1); opacity:1; } }
        .ok-wrap h2 { font-size:22px; margin:0 0 6px; letter-spacing:-.02em; }
        .ok-wrap > p { color:var(--ink-2); font-size:15px; margin:0 auto; max-width:40ch; }
        .ok-code { font-size:24px; font-weight:800; letter-spacing:.5px; background:var(--surface); border:1.5px dashed var(--line-2); border-radius:var(--r-lg); padding:14px; margin:18px 0 0; }
        .ok-code-sub { display:block; margin-top:4px; font-size:13px; font-weight:600; color:var(--muted); letter-spacing:0; }
        .ok-wrap .eta { text-align:left; }
        .ok-btns { display:flex; flex-direction:column; gap:10px; margin-top:18px; }
        .ok-btns a { text-decoration:none; display:block; }
        .btn-wa { background:#1f8f4e; }

        /* ── Confianza ── */
        .trust { list-style:none; margin:26px 0 0; padding:18px 0 0; border-top:1px solid var(--line); display:grid; gap:12px; }
        .trust li { display:flex; gap:10px; align-items:flex-start; font-size:14px; color:var(--ink-2); }
        .trust svg { flex:0 0 auto; color:var(--accent); margin-top:1px; }
        .trust a { color:var(--accent); font-weight:700; }
        .foot { text-align:center; color:var(--muted); font-size:12px; margin-top:20px; }
        .foot a { color:inherit; }
        .step[hidden], [hidden] { display:none !important; }

        /* ── Progreso ── */
        .prog { margin:-8px 0 16px; }
        .prog__bar { display:none; }
        .prog__t { font-size:13px; color:var(--muted); font-weight:600; }
        .prog.is-done .prog__t { color:var(--ok); }

        /* ── Errores ── */
        .err-sum { border:1px solid #f3c7c1; background:var(--bad-soft); border-radius:var(--r); padding:12px 14px; margin:0 0 14px; }
        .err-sum__t { font-weight:750; font-size:14.5px; color:var(--bad); margin-bottom:6px; }
        .err-sum__l { margin:0; padding-left:18px; }
        .err-sum__l li { font-size:14px; color:#7a1a12; margin:3px 0; }
        .err-sum__l button { background:none; border:0; padding:0; color:var(--bad); text-decoration:underline; cursor:pointer; text-align:left; }
        .is-bad { border-color:var(--bad) !important; background:var(--bad-soft) !important; }
        .fld-err { display:block; font-size:13px; color:var(--bad); margin-top:4px; font-weight:600; }
        .alert-err { background:var(--bad-soft); color:var(--bad); border:1px solid #f3c7c1; padding:12px 14px; border-radius:var(--r); font-size:14px; margin-bottom:14px; }

        .fade-in { animation:fade .25s var(--ease); }
        @keyframes fade { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation:none !important; transition:none !important; } }

        /* El cascader de ubigeo trae su propio CSS: aquí se alinea al resto. */
        .ubigeo-display { min-height:48px; border:1.5px solid var(--line-2) !important; border-radius:var(--r) !important; padding:12px 14px !important; font-size:16px !important; background:var(--surface) !important; display:flex; align-items:center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        {{-- La tienda primero: el cliente le compró a ELLA, no a ebaemy. --}}
        <header class="head">
            <div class="head__logo">
                @if(!empty($company->logo))
                    <img src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="">
                @else
                    <b>{{ mb_strtoupper(mb_substr($storeName, 0, 1)) }}</b>
                @endif
            </div>
            <div class="head__tx">
                <h1 class="head__store">{{ $storeName }}</h1>
                <div class="head__sub">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/><path d="M9 12l2 2l4 -4"/></svg>
                    Datos de envío de tu pedido
                </div>
            </div>
        </header>

        {{-- ── Barra de progreso ── --}}
        <div class="stepper" id="stepper" aria-label="Pasos">
            <div class="st {{ $sent ? 'done' : 'active' }}" data-n="1"><div class="dot">1</div><div class="t">Entrega</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="2"><div class="dot">2</div><div class="t">Tus datos</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="3"><div class="dot">3</div><div class="t">Confirmar</div></div>
        </div>

        {{-- Cuanto le falta al cliente. El porcentaje sale de los campos que de
             verdad exige el servidor para SU modalidad, no de un total fijo:
             en recojo en tienda no hay direccion que pedir y contarla dejaria
             el progreso clavado en 70% para siempre. --}}
        @if(!$sent)
            <div class="prog" id="prog" hidden>
                <div class="prog__bar"><div class="prog__fill" id="progFill"></div></div>
                <div class="prog__t" id="progText">Tu información está 0% completa</div>
            </div>
        @endif

        @if($sent)
            {{-- ══════════ PASO 4: Éxito ══════════ --}}
            @php
                $isMoto   = ($sentType === \App\Models\Tenant\ShippingRequest::DELIVERY_DOMICILIO);
                $isPickup = ($sentType === \App\Models\Tenant\ShippingRequest::DELIVERY_TIENDA);
            @endphp
            <div class="ok-wrap fade-in">
                <div class="ok-check"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg></div>
                <h2>¡Listo! Ya tenemos tus datos</h2>
                <p style="color:var(--muted);font-size:14px;margin:2px 0;">
                    @if($isPickup)
                        Preparamos tu pedido y te avisamos por WhatsApp <b>apenas esté listo para recoger</b>.
                    @elseif($isMoto)
                        Nuestro motorizado lo lleva hasta tu dirección. Te escribimos por WhatsApp antes de salir.
                    @else
                        Lo enviamos por agencia. Cuando salga de la tienda te mandamos por WhatsApp tu número de guía.
                    @endif
                </p>
                {{-- La referencia que se le da al cliente es la de su PEDIDO,
                     la misma que ve el operador en el panel y la que sale en
                     grande en el rotulo. El codigo ENV- sigue debajo porque es
                     el que resuelve el seguimiento, pero en letra chica: antes
                     era lo UNICO que se le ensenaba y no coincidia con ningun
                     numero de los que la tienda maneja. --}}
                @php $okRef = $sentShipment?->orderRef(); @endphp
                <div class="ok-code">
                    {{ $okRef ? 'Pedido #' . $okRef : $sent }}
                    @if($okRef)<span class="ok-code-sub">Seguimiento: {{ $sent }}</span>@endif
                </div>

                {{-- Confirmación de la solicitud de participación. La inscripción
                     se hace efectiva cuando la tienda valida el pago. --}}
                @include('tenant.shipments.partials.eta-box', ['etaType' => $sentType])

                @if(session('joined_raffle'))
                    <div class="rfz" style="text-align:left;margin-top:14px;">
                        <div class="rfz__tag">🎁 Solicitud registrada</div>
                        <div class="rfz__name">{{ session('joined_raffle') }}</div>
                        <div class="rfz__desc">
                            Anotamos que quieres participar con este pedido. Tu inscripción
                            <strong>queda confirmada cuando validemos el pago</strong>. Si sales sorteado,
                            te contactaremos al celular que registraste.
                        </div>
                    </div>
                @endif
                <div class="ok-btns">
                    <a href="{{ route('shipments.public.tracking', ['code' => $sent]) }}"><button type="button" class="btn">Ver el estado de mi envío</button></a>
                    @php
                        // OJO: este armado es un duplicado del de
                        // ShipmentController::waSummary(). Si tocas uno, toca el otro
                        // (o unificalos): ya se desincronizaron una vez con el precio.
                        $tiendaNombre = $company->title_web ?: ($company->trade_name ?: ($company->name ?: 'la tienda'));
                        $s = $sentShipment ?? null;
                        $L = [];
                        $L[] = "📦 *PEDIDO REGISTRADO* — {$tiendaNombre}";
                        // El cliente pega este mensaje en el chat de la tienda: el
                        // numero que la tienda puede buscar es el de PEDIDO.
                        $L[] = $okRef ? "Pedido: *{$okRef}*" : "Código: *{$sent}*";
                        if ($okRef) $L[] = "Seguimiento: {$sent}";
                        if ($s) {
                            $L[] = "";
                            $L[] = "👤 Cliente: {$s->full_name}";
                            if ($s->dni)   $L[] = "🪪 {$s->document_label}: {$s->dni}";
                            if ($s->phone) $L[] = "📱 Celular: {$s->phone}";
                            $L[] = "";
                            if ($s->is_pickup) {
                                $L[] = "🏬 *Recojo en tienda*";
                                if ($storeAddress) $L[] = "📍 Dirección de la tienda: {$storeAddress}";
                                if ($s->reference) $L[] = "🕒 Piensa pasar: {$s->reference}";
                            } elseif ($s->is_domicilio) {
                                $L[] = "🏍️ *Entrega a domicilio*";
                                if ($s->formatted_address || $s->shipping_destination) $L[] = "📍 Dirección: " . ($s->formatted_address ?: $s->shipping_destination);
                                if ($s->reference)      $L[] = "📌 Referencia: {$s->reference}";
                                if ($s->maps_link)      $L[] = "🗺️ Ubicación: {$s->maps_link}";
                                if ($p = $s->priceLabel('GRATIS')) $L[] = "💵 Costo aprox. de envío: {$p}";
                            } else {
                                $L[] = "📦 *Envío por agencia*";
                                if ($s->destination_city)     $L[] = "🏙️ Destino: {$s->destination_city}";
                                if ($s->shipping_agency)      $L[] = "🏢 Agencia: {$s->shipping_agency}";
                                if ($s->shipping_destination) $L[] = "📍 Dirección: {$s->shipping_destination}";
                                if ($s->reference)            $L[] = "📌 Referencia: {$s->reference}";
                                if ($p = $s->priceLabel('🎁 GRATIS')) $L[] = "💵 Servicio tienda→agencia: {$p}";
                            }
                            if ($s->package_content) $L[] = "📦 Contenido: {$s->package_content}";
                            if ($s->notes)           $L[] = "📝 Nota: {$s->notes}";
                        }
                        $L[] = "";
                        $L[] = "🔎 Seguimiento: " . route('shipments.public.tracking', ['code' => $sent]);
                        $waText = rawurlencode(implode("\n", $L));
                        $waHref = !empty($ordersWa) ? "https://wa.me/{$ordersWa}?text={$waText}" : "https://wa.me/?text={$waText}";
                    @endphp
                    <a href="{{ $waHref }}" target="_blank"><button type="button" class="btn btn-wa">Enviar mis datos a la tienda por WhatsApp</button></a>
                    @if(empty($order))
                        <a href="{{ route('shipments.public.form') }}"><button type="button" class="btn btn-ghost">Registrar otro envío</button></a>
                    @endif
                </div>
            </div>
        @else
            @if($errors->any())
                <div class="alert-err"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            {{-- Cuando el formulario se abre desde el enlace de un pedido, el
                 cliente tiene que ver DE QUE compra estamos hablando: sin esto
                 el enlace parece un registro suelto mas y no lo asocia. --}}
            @if(!empty($orderSummary))
                @php $os = $orderSummary; $n = count($os['lines']) + $os['more']; @endphp
                <details class="order">
                    <summary>
                        @if($os['lines'])
                            <span class="order__thumbs" aria-hidden="true">
                                @foreach(array_slice($os['lines'], 0, 3) as $ln)
                                    @if($ln['image'])<span style="background-image:url('{{ $ln['image'] }}')"></span>@else<span>{{ mb_strtoupper(mb_substr($ln['name'], 0, 1)) }}</span>@endif
                                @endforeach
                            </span>
                        @endif
                        <span class="order__tx">
                            <b>Pedido #{{ $os['number'] }}</b>
                            <small>{{ $n }} {{ $n === 1 ? 'producto' : 'productos' }} · ver detalle</small>
                        </span>
                        @if($os['total'] > 0)<span class="order__total">S/ {{ number_format($os['total'], 2) }}</span>@endif
                        <svg class="order__chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6l-6 6"/></svg>
                    </summary>
                    <div class="order__lines">
                        @foreach($os['lines'] as $ln)
                            <div class="order__line">
                                <span class="q">{{ $ln['qty'] }}×</span>
                                <span class="n">{{ $ln['name'] }}</span>
                                @if($ln['total'] > 0)<span class="v">S/ {{ number_format($ln['total'], 2) }}</span>@endif
                            </div>
                        @endforeach
                        @if($os['more'])<div class="order__line"><span class="n" style="color:var(--muted)">y {{ $os['more'] }} más</span></div>@endif
                    </div>
                </details>
            @elseif(!empty($order))
                <div class="order"><div style="padding:12px 14px"><b>Pedido #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</b></div></div>
            @endif

            <form method="POST" action="{{ $formAction }}" id="shipForm">
            @include('tenant.shipments.partials.shipment-form-body', ['p' => 'pub_', 'context' => 'public'])
            </form>

        @endif

        {{-- Confianza: le compró a una cuenta de Instagram. Decirle qué se hace
             con sus datos, cómo se entera de su envío y a quién escribir. --}}
        <ul class="trust">
            <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/></svg>
                <span>Tus datos solo se usan para entregarte este pedido.</span>
            </li>
            <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9"/></svg>
                <span>
                    Te avisamos por WhatsApp cuando tu pedido salga.
                    @if(!empty($ordersWa))<a href="https://wa.me/{{ $ordersWa }}" target="_blank" rel="noopener">¿Dudas? Escríbenos</a>@endif
                </span>
            </li>
            @if(!$sent)
            <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>
                <span>¿Ya te registraste? <a href="{{ route('shipments.public.tracking') }}">Mira el estado de tu envío</a></span>
            </li>
            @endif
        </ul>

        <div class="foot">Envíos gestionados con <a href="https://ebaemy.com" target="_blank" rel="noopener">ebaemy</a></div>
    </div>

    {{-- Venta cruzada: los productos de la tienda en el marketplace. Va FUERA
         de la tarjeta para que se vea igual mientras se llena el formulario y
         en la pantalla de éxito. --}}
    @include('tenant.shipments.partials.marketplace-reel')
</div>

@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')

@include('tenant.shipments.partials.shipment-form-js', [
    'p' => 'pub_',
    // El borrador se guarda por PEDIDO: dos enlaces distintos abiertos en el
    // mismo telefono no pueden pisarse los datos.
    'draftKey' => !empty($order) ? 'o' . $order->id : 'new',
])

@if(!empty($mapsKey))
<script>
    // ── Google Maps: Autocomplete + marcador arrastrable + geocoding ──
    (function () {
        var LIMA = { lat: -12.0464, lng: -77.0428 };
        @if(!empty($storeLat) && !empty($storeLng))
        var STORE = { lat: {{ $storeLat }}, lng: {{ $storeLng }} };
        @else
        var STORE = null;
        @endif
        @if(!empty($pricePerKm))
        var PRICE = { perKm: {{ $pricePerKm }}, base: {{ $basePrice ?? 0 }}, min: {{ $minPrice ?? 0 }} };
        @else
        var PRICE = null;
        @endif
        var map, marker, geocoder, ac, ready = false, pending = false, distSvc = null;
        // Última dirección que devolvió Google: sirve para saber si el cliente
        // editó el campo a mano y no pisarle lo que escribió.
        var lastFormatted = '';

        // Cotiza el precio del envío según los km (base + km × tarifa, con mínimo).
        function quotePrice(km) {
            if (!PRICE) return;
            var p = PRICE.base + km * PRICE.perKm;
            if (p < PRICE.min) p = PRICE.min;
            p = Math.round(p * 100) / 100;
            document.getElementById('pub_delivery_price').value = p.toFixed(2);
            var box = document.getElementById('priceBox');
            if (box) { box.style.display = 'block'; document.getElementById('priceText').textContent = p.toFixed(2); }
        }

        // Un punto nuevo invalida la cotización anterior: sin esto, si la nueva
        // consulta falla, el cliente se queda viendo el precio de otro lugar.
        function clearQuote() {
            ['pub_dist_km', 'pub_dist_text', 'pub_dur_text', 'pub_delivery_price'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            var box = document.getElementById('priceBox');
            if (box) box.style.display = 'none';
        }

        // Línea recta, la misma fórmula que ShippingRequest::haversineKm del
        // servidor: si Google no da la ruta, el precio no desaparece.
        function haversineKm(a, b) {
            var rad = Math.PI / 180, dLat = (b.lat - a.lat) * rad, dLng = (b.lng - a.lng) * rad;
            var h = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.pow(Math.sin(dLng / 2), 2);
            return Math.round(6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)) * 100) / 100;
        }
        function fallbackDistance(lat, lng) {
            var km = haversineKm(STORE, { lat: lat, lng: lng });
            document.getElementById('pub_dist_km').value = km.toFixed(2);
            document.getElementById('pub_dist_text').value = km + ' km aprox.';
            quotePrice(km);
        }

        // Distancia de manejo tienda → cliente (Google Distance Matrix).
        var distSeq = 0;
        function computeDistance(lat, lng) {
            clearQuote();
            if (!STORE) return;
            // Solo cuenta la respuesta del ÚLTIMO punto: al arrastrar el pin
            // rápido, una respuesta lenta no puede pisar a la más reciente.
            var seq = ++distSeq;
            try {
                if (!distSvc) distSvc = new google.maps.DistanceMatrixService();
                distSvc.getDistanceMatrix({
                    origins: [STORE], destinations: [{ lat: lat, lng: lng }],
                    travelMode: 'DRIVING', unitSystem: google.maps.UnitSystem.METRIC
                }, function (res, status) {
                    if (seq !== distSeq) return;
                    var el = status === 'OK' && res.rows[0] && res.rows[0].elements[0];
                    if (!el || el.status !== 'OK') { fallbackDistance(lat, lng); return; }
                    var km = (el.distance.value / 1000);
                    // La distancia se guarda (la ven motorizado y panel, no el cliente).
                    document.getElementById('pub_dist_km').value = km.toFixed(2);
                    document.getElementById('pub_dist_text').value = el.distance.text;
                    document.getElementById('pub_dur_text').value = el.duration.text;
                    // El precio SÍ se le muestra al cliente.
                    quotePrice(km);
                });
            } catch (e) { fallbackDistance(lat, lng); }
        }

        function pickComponent(components, type) {
            for (var i = 0; i < components.length; i++) {
                if (components[i].types.indexOf(type) !== -1) return components[i].long_name;
            }
            return '';
        }
        function applyPlace(lat, lng, formatted, components, placeId) {
            document.getElementById('pub_lat').value = lat;
            document.getElementById('pub_lng').value = lng;
            document.getElementById('pub_place_id').value = placeId || '';
            document.getElementById('pub_formatted').value = formatted || '';
            document.getElementById('pub_maps_url').value = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
            var city = '';
            if (components) {
                city = pickComponent(components, 'locality') || pickComponent(components, 'sublocality') ||
                       pickComponent(components, 'administrative_area_level_2') || pickComponent(components, 'administrative_area_level_1');
            }
            document.getElementById('pub_city_domicilio').value = city;
            // El input de dirección es el mismo que el buscador: al mover el pin
            // manda el pin, salvo que el cliente ya haya escrito algo distinto
            // de la última dirección que devolvió Google (su número/dpto a mano).
            var addrInput = document.getElementById('pub_addr_domicilio');
            if (formatted && (!addrInput.value.trim() || addrInput.value.trim() === lastFormatted)) {
                addrInput.value = formatted;
            }
            lastFormatted = formatted || '';
            addrInput.style.borderColor = '';
            var box = document.getElementById('mapPicked');
            if (box) {
                box.classList.add('show');
                document.getElementById('mp_city').textContent = '📍 Ubicación fijada' + (city ? ' · ' + city : '');
            }
            computeDistance(lat, lng);
        }
        function reverse(latlng) {
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    applyPlace(latlng.lat(), latlng.lng(), results[0].formatted_address, results[0].address_components, results[0].place_id);
                } else {
                    applyPlace(latlng.lat(), latlng.lng(), '', null, '');
                }
            });
        }
        function placeMarker(latlng) {
            marker.setPosition(latlng); map.panTo(latlng); if (map.getZoom() < 16) map.setZoom(16);
        }

        window.initShipMap = function () {
            geocoder = new google.maps.Geocoder();
            map = new google.maps.Map(document.getElementById('shipMap'), { center: LIMA, zoom: 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
            marker = new google.maps.Marker({ map: map, position: LIMA, draggable: true });
            marker.addListener('dragend', function () { reverse(marker.getPosition()); });
            map.addListener('click', function (e) { placeMarker(e.latLng); reverse(e.latLng); });

            var input = document.getElementById('pub_addr_domicilio');
            // Enter dentro del autocompletado no debe enviar el formulario.
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
            ac = new google.maps.places.Autocomplete(input, { componentRestrictions: { country: 'pe' }, fields: ['geometry', 'formatted_address', 'address_components', 'place_id'] });
            ac.bindTo('bounds', map);
            ac.addListener('place_changed', function () {
                var p = ac.getPlace();
                if (!p.geometry) return;
                var loc = p.geometry.location;
                placeMarker(loc);
                applyPlace(loc.lat(), loc.lng(), p.formatted_address, p.address_components, p.place_id);
            });
            // GPS del teléfono: fija el pin donde está el cliente y desde ahí
            // salen la dirección (geocoding inverso) y el costo.
            var geoBtn = document.getElementById('geoBtn'), geoMsg = document.getElementById('geoMsg');
            if (geoBtn) {
                var geoLabel = geoBtn.querySelector('span');
                function geoSay(text) { geoMsg.textContent = text; geoMsg.hidden = !text; }
                geoBtn.addEventListener('click', function () {
                    if (!navigator.geolocation) { geoSay('Tu navegador no permite compartir la ubicación. Escribe tu dirección abajo.'); return; }
                    geoSay('');
                    geoBtn.disabled = true; geoLabel.textContent = 'Buscando tu ubicación…';
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        geoBtn.disabled = false; geoLabel.textContent = 'Usar mi ubicación actual';
                        var latlng = new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                        // Al venir del GPS manda el punto: la dirección escrita antes se reemplaza.
                        document.getElementById('pub_addr_domicilio').value = '';
                        placeMarker(latlng);
                        reverse(latlng);
                        if (pos.coords.accuracy > 150) {
                            geoSay('Tu ubicación es aproximada. Revisa el marcador en el mapa y arrástralo a tu puerta si hace falta.');
                        }
                    }, function (err) {
                        geoBtn.disabled = false; geoLabel.textContent = 'Usar mi ubicación actual';
                        geoSay(err.code === 1
                            ? 'No diste permiso para ver tu ubicación. Actívalo en el candado de la barra de direcciones o escribe tu dirección abajo.'
                            : 'No pudimos obtener tu ubicación. Revisa que el GPS esté encendido o escribe tu dirección abajo.');
                    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
                });
            }

            ready = true;
            if (pending) { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }
        };
        // El mapa está oculto hasta elegir "domicilio": forzar resize al mostrarlo.
        window.__initShipMapIfReady = function () {
            if (ready && map) { setTimeout(function () { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }, 120); }
            else { pending = true; }
        };
    })();
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initShipMap&language=es&region=PE"></script>
@endif

{{-- Prellenado desde el pedido. json_encode y NO @json: la directiva se rompe
     con las comas anidadas de los valores. --}}
@if(!empty($orderPrefill))
    @php $shipPrefillJson = json_encode($orderPrefill, JSON_UNESCAPED_UNICODE); @endphp
    <script>
        (function () {
            var prefill = {!! $shipPrefillJson !!} || {};

            function set(id, value) {
                if (value === null || value === undefined || value === '') return null;
                var el = document.getElementById(id);
                // Nunca pisar lo que el cliente ya escribio, ni el old() que
                // vuelve tras un error de validacion.
                if (!el || el.value) return null;
                el.value = value;
                return el;
            }

            document.addEventListener('DOMContentLoaded', function () {
                set('pub_phone', prefill.phone);

                // La direccion del checkout sirve a las dos ramas (Lima y
                // agencia) y cada una tiene su propio campo con el MISMO
                // name: hay que fijar los dos por id, o el dato acaba en la
                // rama que el cliente no eligio.
                set('pub_addr_domicilio', prefill.shipping_destination);
                set('pub_addr_agencia',   prefill.shipping_destination);

                // El NOMBRE no se prellena: cuando el documento es DNI/RUC el
                // formulario lo pone en solo lectura y lo trae de
                // RENIEC/SUNAT, que es la fuente valida. Escribirlo aqui
                // metia en un campo bloqueado un nombre sin verificar.
                //
                // En su lugar se rellena el documento y se AVISA al
                // formulario con un evento 'input', que es lo que dispara su
                // consulta: asi el nombre, la direccion y el ubigeo los
                // completa el como si el cliente lo hubiera tecleado. Sin el
                // evento, el documento quedaba puesto pero el nombre vacio y
                // bloqueado, y el cliente no podia avanzar.
                var doc = set('pub_dni', prefill.dni);
                if (doc) {
                    doc.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        })();
    </script>
@endif
</body>
</html>
