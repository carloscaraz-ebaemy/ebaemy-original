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
    .sh-head__ic { display:flex; align-items:center; justify-content:center; width:40px; height:40px;
        border-radius:12px; font-size:1.05rem; color:#4f46e5;
        background:linear-gradient(145deg,#eef2ff,#e0e7ff); box-shadow:inset 0 0 0 1px #e0e7ff; }
    .sh-head__t { margin:0; font-size:1.06rem; font-weight:700; color:#0f172a; letter-spacing:-.015em; }

    /* ── Avatar de cliente ── */
    .sh-cli { display:flex; align-items:center; gap:9px; }
    .sh-avt { display:flex; align-items:center; justify-content:center; flex:0 0 auto;
        width:30px; height:30px; border-radius:9px; font-size:.7rem; font-weight:700; letter-spacing:.01em; }

    /* ── Pestañas de flujo (una sola dimensión de filtro) ── */
    .sh-tabs { display:flex; gap:4px; align-items:center; margin-bottom:12px; padding:4px;
        background:#f4f5f8; border-radius:11px; overflow-x:auto; scrollbar-width:none; }
    .sh-tabs::-webkit-scrollbar { display:none; }
    .sh-tab { display:inline-flex; align-items:center; gap:7px; white-space:nowrap; text-decoration:none;
        padding:.46rem .8rem; font-size:.815rem; font-weight:600; color:#6b7280; border-radius:8px;
        transition:background .16s ease-out, color .16s ease-out, box-shadow .16s ease-out; }
    .sh-tab:hover { color:#111827; background:rgba(255,255,255,.7); }
    .sh-tab.is-on { color:#312e81; background:#fff;
        box-shadow:0 1px 2px rgba(16,24,40,.06), 0 4px 10px -6px rgba(49,46,129,.35); }
    .sh-tab__n { font-size:.69rem; font-weight:700; padding:.1rem .38rem; border-radius:5px;
        background:#f1f3f5; color:#6b7280; font-variant-numeric:tabular-nums; }
    .sh-tab.is-on .sh-tab__n { background:#4f46e5; color:#fff; }

    /* ── Barra de herramientas ── */
    .sh-tools { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
    .sh-search { position:relative; display:flex; align-items:center; flex:1 1 240px; max-width:380px; margin:0; }
    .sh-search__ic { position:absolute; left:10px; font-size:.74rem; color:#9aa2af; pointer-events:none; }
    .sh-search input { width:100%; padding:.42rem 1.7rem .42rem 1.95rem; font-size:.8rem; color:#111827;
        background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 1px 2px rgba(16,24,40,.04);
        transition:border-color .15s ease-out, box-shadow .15s ease-out; }
    .sh-search input:focus { outline:none; border-color:#a5b4fc; box-shadow:0 0 0 3px rgba(79,70,229,.1); }
    .sh-search button { position:absolute; right:6px; border:0; background:none; color:#9aa2af;
        font-size:.78rem; cursor:pointer; padding:2px 4px; line-height:1; }
    .sh-search button:hover { color:#4b5563; }

    /* ── Chips de filtro ── */
    .sh-chip { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; cursor:pointer; text-decoration:none;
        padding:.42rem .68rem; font-size:.78rem; font-weight:600; color:#4b5563;
        background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 1px 2px rgba(16,24,40,.04);
        transition:background .15s ease-out, border-color .15s ease-out, color .15s ease-out; }
    .sh-chip:hover { background:#f9fafb; border-color:#d1d5db; color:#111827; }
    .sh-chip b { font-size:.68rem; font-weight:700; padding:.06rem .34rem; border-radius:5px;
        background:#f1f3f5; color:#6b7280; font-variant-numeric:tabular-nums; }
    .sh-chip.is-on { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
    .sh-chip.is-on b { background:#4f46e5; color:#fff; }

    /* ── Popover de filtros avanzados ── */
    .sh-filters { min-width:300px; padding:12px; border:1px solid #e8eaee; border-radius:12px; }
    .sh-filters__g { padding:6px 0; }
    .sh-filters__g + .sh-filters__g { border-top:1px solid #f1f3f5; margin-top:4px; }
    .sh-filters__t { display:block; font-size:.65rem; font-weight:600; letter-spacing:.06em;
        text-transform:uppercase; color:#9aa2af; margin-bottom:6px; }
    .sh-filters__r { display:flex; flex-wrap:wrap; gap:5px; }
    .sh-mini { display:inline-flex; align-items:center; gap:5px; padding:.3rem .55rem; font-size:.75rem;
        font-weight:600; color:#4b5563; text-decoration:none; background:#f8fafc;
        border:1px solid #eceff3; border-radius:7px; }
    .sh-mini:hover { background:#f1f5f9; color:#111827; }
    .sh-mini.is-on { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
    .sh-mini b { font-size:.66rem; color:#9aa2af; font-variant-numeric:tabular-nums; }
    .sh-mini.is-on b { color:#4f46e5; }
    .sh-mini.is-clear { background:none; border-color:transparent; color:#9aa2af; }
    .sh-filters__d { display:flex; align-items:center; gap:6px; }
    .sh-filters__d input { flex:1; min-width:0; padding:.32rem .5rem; font-size:.76rem;
        border:1px solid #e5e7eb; border-radius:7px; color:#374151; }
    .sh-filters__d span { color:#9aa2af; font-size:.75rem; }
    .sh-filters__clear { display:block; margin-top:10px; padding-top:9px; border-top:1px solid #f1f3f5;
        font-size:.75rem; font-weight:600; color:#dc2626; text-decoration:none; text-align:center; }
    .sh-filters__clear:hover { color:#b91c1c; }
    @media (max-width:768px) { .sh-search { max-width:none; flex:1 1 100%; } }

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
    .sh-act--primary { color:#3730a3; background:#eef2ff; border-color:#c7d2fe; }
    .sh-act--primary:hover { background:#e0e7ff; color:#312e81; }
    .sh-act--ok { color:#166534; background:#f0fdf4; border-color:#bbf7d0; }
    .sh-act--ok:hover { background:#dcfce7; color:#14532d; }
    .sh-act--ghost { color:#9ca3af; background:transparent; border-color:#e5e7eb; padding:.45rem .58rem; }
    .sh-act--ghost:hover { background:#f3f4f6; color:#4b5563; }
    .sh-act[disabled], .sh-act.is-off { opacity:.4; pointer-events:none; }
    /* ── Tabla: acabado de producto, no Bootstrap por defecto ── */
    #shipmentsApp .card { border:1px solid #e9ebef; border-radius:14px; overflow:hidden;
        box-shadow:0 1px 2px rgba(16,24,40,.04), 0 6px 14px -10px rgba(16,24,40,.14),
                   0 26px 46px -32px rgba(16,24,40,.28); }
    #shipmentsApp .table { margin:0; }
    #shipmentsApp .table > thead > tr > th { background:#fbfcfd; color:#8b93a1;
        font-size:.655rem; font-weight:600; letter-spacing:.07em; text-transform:uppercase;
        padding:.72rem .75rem; border-bottom:1px solid #eceff3; white-space:nowrap; }
    #shipmentsApp .table > tbody > tr > td { padding:.82rem .75rem; border-top:1px solid #f4f6f8;
        vertical-align:middle; font-size:.815rem; color:#5b6472; }
    #shipmentsApp .table > tbody > tr:first-child > td { border-top:0; }
    #shipmentsApp .table > tbody > tr { transition:background .12s ease-out; }
    #shipmentsApp .table > tbody > tr:hover > td { background:#f8fafd; }
    /* Jerarquía dentro de la fila */
    .sh-code { display:block; font-weight:650; color:#111827; font-size:.775rem;
        letter-spacing:-.015em; font-variant-numeric:tabular-nums; }
    .sh-client { display:block; font-weight:600; color:#111827; font-size:.815rem; line-height:1.3; }
    .sh-phone { display:block; margin-top:1px; font-size:.71rem; color:#9aa2af; font-variant-numeric:tabular-nums; }
    .sh-date { font-weight:600; color:#374151; font-size:.775rem; font-variant-numeric:tabular-nums; }
    .sh-time { display:block; font-size:.7rem; color:#9aa2af; font-variant-numeric:tabular-nums; }
    /* Etiquetas (reemplazan los badges genéricos) */
    .sh-tag { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:600;
        padding:.24rem .52rem; border-radius:6px; letter-spacing:.005em; text-decoration:none; white-space:nowrap; }
    .sh-tag--danger { background:#fef2f2; color:#b42318; }
    .sh-tag--ok { background:#ecfdf3; color:#067647; }
    .sh-tag--ok:hover { background:#d1fadf; color:#05603a; }

    /* ── Pie de tabla (paginación tipo ERP) ── */
    .sh-foot { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;
        padding:11px 16px; border-top:1px solid #eef0f4; background:linear-gradient(180deg,#fcfcfd,#f8fafc);
        border-radius:0 0 .5rem .5rem; font-size:.8rem; }
    .sh-foot__info { color:#6b7280; display:inline-flex; align-items:baseline; gap:5px; }
    .sh-foot__range, .sh-foot__total { color:#111827; font-weight:600; font-variant-numeric:tabular-nums; }
    .sh-foot__of, .sh-foot__label { color:#9ca3af; }
    .sh-foot__size { display:inline-flex; align-items:center; gap:8px; color:#6b7280; }
    .sh-foot__size label { margin:0; font-weight:500; }
    .sh-select { position:relative; display:inline-flex; align-items:center; }
    .sh-select select { appearance:none; -webkit-appearance:none; padding:.34rem 1.7rem .34rem .6rem;
        font-size:.78rem; font-weight:600; color:#374151; background:#fff; border:1px solid #e5e7eb;
        border-radius:8px; box-shadow:0 1px 2px rgba(16,24,40,.04); cursor:pointer;
        transition:border-color .15s ease-out, box-shadow .15s ease-out; }
    .sh-select select:hover { border-color:#d1d5db; }
    .sh-select select:focus { outline:none; border-color:#a5b4fc; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    .sh-select i { position:absolute; right:.6rem; font-size:.6rem; color:#9ca3af; pointer-events:none; }
    .sh-foot__nav { display:inline-flex; align-items:center; gap:4px; }
    .sh-pg-nums { display:inline-flex; align-items:center; gap:4px; }
    .sh-pg { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px;
        border-radius:8px; font-size:.78rem; font-weight:600; font-variant-numeric:tabular-nums; color:#4b5563;
        text-decoration:none; background:#fff; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(16,24,40,.04);
        transition:background .15s ease-out, border-color .15s ease-out, color .15s ease-out, transform .1s ease-out; }
    .sh-pg:hover { background:#f9fafb; border-color:#d1d5db; color:#111827; }
    .sh-pg:active { transform:translateY(1px); }
    .sh-pg.is-current { background:#4f46e5; border-color:#4f46e5; color:#fff;
        box-shadow:0 1px 2px rgba(79,70,229,.45), 0 2px 6px -2px rgba(79,70,229,.4); }
    .sh-pg.is-off { opacity:.38; pointer-events:none; box-shadow:none; }
    .sh-pg-gap { padding:0 2px; color:#d1d5db; letter-spacing:1px; font-size:.7rem; }
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
            'from'   => $from ?? null,
            'to'     => $to ?? null,
            'sort'   => (($sort ?? 'recent') !== 'recent') ? $sort : null,
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
              + (int) (!empty($from) || !empty($to))
              + (int) ($activeGroup === 'cancelados');
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
        <form method="GET" action="{{ route('shipments.index') }}" id="shSearchForm" class="sh-search">
            <input type="hidden" name="filter" value="{{ $filter }}">
            @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            @if($group)<input type="hidden" name="group" value="{{ $group }}">@endif
            <input type="hidden" name="sort" value="{{ $sort ?? 'recent' }}">
            <i class="fas fa-search sh-search__ic"></i>
            <input type="text" name="q" id="shSearchInput" value="{{ $q }}" placeholder="Buscar cliente, código, guía…" autocomplete="off">
            <button type="button" id="shClearSearch" title="Limpiar" style="{{ $q ? '' : 'display:none;' }}">✕</button>
        </form>

        <a href="{{ $mk(['filter' => $filter === 'sin-guia' ? null : 'sin-guia']) }}"
           class="sh-chip {{ $filter === 'sin-guia' ? 'is-on' : '' }}">
            Sin guía <b>{{ $counts['sin-guia'] ?? 0 }}</b>
        </a>

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
                <div class="sh-filters__g">
                    <span class="sh-filters__t">Fecha de registro</span>
                    <div class="sh-filters__d">
                        <input type="date" name="from" id="shFrom" form="shSearchForm" value="{{ $from }}" title="Desde">
                        <span>→</span>
                        <input type="date" name="to" id="shTo" form="shSearchForm" value="{{ $to }}" title="Hasta">
                    </div>
                    @php $hoy = now()->format('Y-m-d'); @endphp
                    <div class="sh-filters__r" style="margin-top:6px;">
                        <a href="{{ $mk(['from' => $hoy, 'to' => $hoy]) }}" class="sh-mini">Hoy</a>
                        <a href="{{ route('shipments.index', ['filter' => 'pendientes', 'from' => $hoy, 'to' => $hoy]) }}" class="sh-mini">Por alistar hoy</a>
                        @if($from || $to)<a href="{{ $mk(['from' => null, 'to' => null]) }}" class="sh-mini is-clear">Quitar fechas</a>@endif
                    </div>
                </div>
                @if($advN || $q || $filter !== 'todos')
                    <a href="{{ route('shipments.index') }}" class="sh-filters__clear">Limpiar todos los filtros</a>
                @endif
            </div>
        </div>
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
                    @endphp
                    <tr class="{{ $s->is_cancelled ? 'text-muted' : '' }}" style="{{ $s->is_cancelled ? 'opacity:.7' : '' }}">
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
                            @else
                                {{ $s->shipping_agency ?: '—' }}
                            @endif
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
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="sh-actions">
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
                                    <li>
                                        <a class="dropdown-item {{ $bloqueado ? 'disabled' : '' }}"
                                           href="{{ $bloqueado ? '#' : route('shipments.print', $s->id) }}"
                                           @if(!$bloqueado) target="_blank" @endif
                                           @if($bloqueado) tabindex="-1" aria-disabled="true" @endif>
                                            <i class="fas fa-print fa-fw me-2"></i> Imprimir rótulo
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item js-edit-shipment"
                                                data-bs-toggle="modal" data-bs-target="#modalEditar"
                                                data-id="{{ $s->id }}"
                                                data-full_name="{{ $s->full_name }}"
                                                data-dni="{{ $s->dni }}"
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
                                    @if(!$s->is_cancelled)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('shipments.cancel', $s->id) }}"
                                              onsubmit="return confirm('¿Anular el envío {{ $s->shipment_code }}? Podrás reactivarlo cambiando su estado.');">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fas fa-ban fa-fw me-2"></i> Anular
                                            </button>
                                        </form>
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
            <div class="col-md-5"><label class="form-label">DNI / RUC</label>
              <input type="text" name="dni" id="ed_dni" class="form-control js-doc-lookup"
                     data-target-name="ed_full_name" data-target-address="ed_shipping_destination" data-ubigeo-group="ed"
                     inputmode="numeric" maxlength="11" autocomplete="off">
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
        ['full_name','dni','phone','shipping_destination','reference','shipping_agency','package_content','package_count','weight','notes',
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

    /** Refresca todo el panel (para clics en filtros: actualiza estados y contadores). */
    function swap(url, opts) {
        opts = opts || {};
        busy(true);
        fetchDoc(url).then(function (doc) {
            var fresh = doc.getElementById('shPanel');
            var cur = document.getElementById('shPanel');
            if (fresh && cur) cur.innerHTML = fresh.innerHTML;
            after(url, !opts.noPush);
            busy(false);
        }).catch(function (e) {
            if (!e || e.name !== 'AbortError') { busy(false); window.location = url; }
        });
    }

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

    // Rango de fechas: al elegir una fecha, recargar la lista.
    document.addEventListener('change', function (ev) {
        if (ev.target.id === 'shFrom' || ev.target.id === 'shTo') {
            var u = searchUrl(); if (u) swap(u);
        }
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
        fetch('{{ route("shipments.payment_bulk") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function () { swap(location.href, { noPush: true }); })
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
        sel.disabled = true;
        busy(true);
        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r && !r.ok) { sel.disabled = false; busy(false); return; }
                swap(location.href, { noPush: true }); // al terminar hace busy(false)
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
@endpush
