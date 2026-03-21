@extends('tenant.layouts.app')

@section('title', 'Dashboard Logístico')

@push('css')
<style>
/* ─── Variables de color ─────────────────────────────────────── */
:root {
    --logi-green:  #1cc88a;
    --logi-blue:   #4e73df;
    --logi-cyan:   #36b9cc;
    --logi-yellow: #f6c23e;
    --logi-red:    #e74a3b;
    --logi-gray:   #858796;
}

/* ─── KPI Cards ──────────────────────────────────────────────── */
.kpi-card {
    border: none;
    border-radius: .5rem;
    transition: transform .15s, box-shadow .15s;
    overflow: hidden;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12) !important; }
.kpi-card .kpi-body { padding: 1.1rem 1.25rem; }
.kpi-card .kpi-icon {
    font-size: 2.6rem;
    opacity: .15;
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
}
.kpi-card .kpi-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .25rem; }
.kpi-card .kpi-value { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: .15rem; }
.kpi-card .kpi-sub   { font-size: .75rem; opacity: .75; }
.kpi-card .kpi-bar   { height: 4px; border-radius: 0; }

/* Colores temáticos */
.kpi-green  { background: linear-gradient(135deg, #1cc88a 0%, #17a673 100%); color:#fff; }
.kpi-blue   { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color:#fff; }
.kpi-yellow { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); color:#fff; }
.kpi-red    { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); color:#fff; }
.kpi-cyan   { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); color:#fff; }
.kpi-gray   { background: linear-gradient(135deg, #858796 0%, #60616f 100%); color:#fff; }
.kpi-orange { background: linear-gradient(135deg, #fd7e14 0%, #d9600f 100%); color:#fff; }

/* ─── Sección títulos ─────────────────────────────────────────── */
.section-title {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--logi-gray);
    margin-bottom: .75rem;
    padding-bottom: .4rem;
    border-bottom: 2px solid #f0f0f0;
}

/* ─── Gráfico ────────────────────────────────────────────────── */
.chart-area { position: relative; height: 200px; }

/* ─── Pipeline ────────────────────────────────────────────────── */
.pipeline-step {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .55rem .75rem;
    border-radius: .4rem;
    margin-bottom: .4rem;
    background: #f8f9fc;
    border-left: 4px solid #dee2e6;
    transition: background .1s;
}
.pipeline-step:hover { background: #eef0f8; }
.pipeline-step .pipe-count {
    font-size: 1.3rem;
    font-weight: 800;
    min-width: 40px;
    text-align: right;
}
.pipeline-step .pipe-label { font-size: .8rem; font-weight: 600; }
.pipeline-step .pipe-sub   { font-size: .7rem; color: var(--logi-gray); }
.pipe-pending  { border-left-color: #858796; }
.pipe-prep     { border-left-color: #36b9cc; }
.pipe-ready    { border-left-color: #4e73df; }
.pipe-sent     { border-left-color: #1cc88a; }
.pipe-pickup   { border-left-color: #f6c23e; }
.pipe-instant  { border-left-color: #f6c23e; }

/* ─── Stock table ─────────────────────────────────────────────── */
.stock-item-name {
    max-width: 190px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.stock-zero  { color: #e74a3b; font-weight: 700; }
.stock-low   { color: #f6c23e; font-weight: 700; }

/* ─── Movimientos ─────────────────────────────────────────────── */
.mov-plus  { color: #1cc88a; font-weight: 700; }
.mov-minus { color: #e74a3b; font-weight: 700; }

/* ─── Alertas flotantes (banners) ─────────────────────────────── */
.alert-banner {
    border-radius: .4rem;
    padding: .6rem 1rem;
    font-size: .82rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: .5rem;
}

/* ─── Ritmo general ───────────────────────────────────────────── */
.card { border: none; border-radius: .5rem; }
.card-header { background: #fff; border-bottom: 1px solid #f0f1f4; font-size: .85rem; padding: .6rem 1rem; }
.table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--logi-gray); border-top: none; }
.table td { font-size: .82rem; vertical-align: middle; }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- ══ Header ══════════════════════════════════════════════════════════════ --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="fas fa-tachometer-alt me-2" style="color:var(--logi-blue);"></i>
                Dashboard Logístico
            </h4>
            <small class="text-muted">
                <i class="far fa-clock me-1"></i>Actualizado {{ now()->format('d/m/Y H:i') }}
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('logistic.warehouse_queue') }}" class="btn btn-sm btn-warning shadow-sm">
                <i class="fas fa-boxes me-1"></i> Cola Despacho
            </a>
            <a href="{{ route('logistic.sale_notes.history') }}" class="btn btn-sm btn-outline-success shadow-sm">
                <i class="fas fa-history me-1"></i> Historial
            </a>
            <a href="{{ route('logistic.returns.index') }}" class="btn btn-sm btn-outline-danger shadow-sm">
                <i class="fas fa-undo-alt me-1"></i> Devoluciones
            </a>
            <a href="{{ route('logistic.couriers.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                <i class="fas fa-truck me-1"></i> Couriers
            </a>
        </div>
    </div>

    {{-- ══ Alertas activas ══════════════════════════════════════════════════════ --}}
    @if($en_cola > 0 || $returnStats['recibido'] > 0 || $stockCritico->count() > 0)
    <div class="mb-3">
        @if($en_cola > 0)
        <div class="alert-banner text-white shadow-sm" style="background:var(--logi-yellow);">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <strong>{{ $en_cola }} pedido{{ $en_cola > 1 ? 's' : '' }}</strong> en cola esperando ser despachado{{ $en_cola > 1 ? 's' : '' }}.
            <a href="{{ route('logistic.warehouse_queue') }}" class="ms-auto text-white fw-bold" style="white-space:nowrap;">Ir a la cola →</a>
        </div>
        @endif
        @if($returnStats['recibido'] > 0)
        <div class="alert-banner bg-danger text-white shadow-sm">
            <i class="fas fa-undo fa-lg"></i>
            <strong>{{ $returnStats['recibido'] }} devolución{{ $returnStats['recibido'] > 1 ? 'es' : '' }}</strong> recibida{{ $returnStats['recibido'] > 1 ? 's' : '' }} pendiente{{ $returnStats['recibido'] > 1 ? 's' : '' }} de procesar.
            <a href="{{ route('logistic.returns.index') }}?status=RECIBIDO" class="ms-auto text-white fw-bold" style="white-space:nowrap;">Procesar →</a>
        </div>
        @endif
        @if($stockCritico->count() > 0)
        <div class="alert-banner text-white shadow-sm" style="background:var(--logi-red);">
            <i class="fas fa-boxes fa-lg"></i>
            <strong>{{ $stockCritico->count() }} producto{{ $stockCritico->count() > 1 ? 's' : '' }}</strong> sin stock disponible.
            <span class="ms-auto" style="white-space:nowrap;">Revisa la tabla abajo ↓</span>
        </div>
        @endif
    </div>
    @endif

    {{-- ══ KPI Cards ═══════════════════════════════════════════════════════════ --}}
    <div class="row mb-4">

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card kpi-card kpi-yellow shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">En Cola</div>
                    <div class="kpi-value">{{ $en_cola }}</div>
                    <div class="kpi-sub">Pendiente + Preparando</div>
                    <i class="fas fa-clock kpi-icon"></i>
                </div>
                <div class="kpi-bar bg-white" style="opacity:.3;"></div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card kpi-card kpi-green shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">Despachados Hoy</div>
                    <div class="kpi-value">{{ $despachados_hoy }}</div>
                    <div class="kpi-sub">{{ $despachados_mes }} este mes</div>
                    <i class="fas fa-truck kpi-icon"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            @php $totalDispatch = $dispatchStats->sum(); @endphp
            <div class="card kpi-card kpi-blue shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">Total Pedidos</div>
                    <div class="kpi-value">{{ $totalDispatch }}</div>
                    <div class="kpi-sub">en el sistema logístico</div>
                    <i class="fas fa-file-alt kpi-icon"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card kpi-card kpi-red shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">Devoluciones</div>
                    <div class="kpi-value">{{ $returnStats['recibido'] }}</div>
                    <div class="kpi-sub">x procesar · {{ $returnStats['mes'] }} este mes</div>
                    <i class="fas fa-undo-alt kpi-icon"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card kpi-card kpi-orange shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">Stock Agotado</div>
                    <div class="kpi-value">{{ $stockCritico->count() }}</div>
                    <div class="kpi-sub">{{ $stockBajo->count() }} con stock bajo</div>
                    <i class="fas fa-exclamation-triangle kpi-icon"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card kpi-card kpi-cyan shadow-sm h-100">
                <div class="kpi-body position-relative">
                    <div class="kpi-label">Dev. Procesadas</div>
                    <div class="kpi-value">{{ $returnStats['procesado'] }}</div>
                    <div class="kpi-sub">Stock reingresado OK</div>
                    <i class="fas fa-check-double kpi-icon"></i>
                </div>
            </div>
        </div>

    </div>

    {{-- ══ Gráfico + Pipeline ═══════════════════════════════════════════════════ --}}
    <div class="row mb-4">

        {{-- Gráfico despachos --}}
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-chart-bar me-1" style="color:var(--logi-green);"></i> Despachos — últimos 14 días</strong>
                    <span class="badge bg-light text-dark">
                        Total: <strong>{{ array_sum($diasData) }}</strong>
                    </span>
                </div>
                <div class="card-body pb-2">
                    <div class="chart-area">
                        <canvas id="chartDespachos"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pipeline --}}
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="fas fa-stream me-1" style="color:var(--logi-blue);"></i> Estado de Pedidos</strong>
                </div>
                <div class="card-body">
                    @php
                        $pipelineMap = [
                            'PENDIENTE'         => ['label'=>'Pendiente',          'sub'=>'Sin procesar',         'css'=>'pipe-pending', 'icon'=>'fa-clock',            'color'=>'#858796'],
                            'PREPARANDO'        => ['label'=>'Preparando',         'sub'=>'En almacén',           'css'=>'pipe-prep',    'icon'=>'fa-people-carry',     'color'=>'#36b9cc'],
                            'LISTO_DESPACHO'    => ['label'=>'Listo p/ Despacho',  'sub'=>'Esperando courier',    'css'=>'pipe-ready',   'icon'=>'fa-box',              'color'=>'#4e73df'],
                            'DESPACHADO'        => ['label'=>'Despachado',         'sub'=>'En camino',            'css'=>'pipe-sent',    'icon'=>'fa-truck',            'color'=>'#1cc88a'],
                            'RECOGIDO'          => ['label'=>'Recogido',           'sub'=>'Retirado por cliente', 'css'=>'pipe-pickup',  'icon'=>'fa-hand-holding-box', 'color'=>'#1cc88a'],
                            'ENTREGA_INMEDIATA' => ['label'=>'Entrega Inmediata',  'sub'=>'Flujo tienda',         'css'=>'pipe-instant', 'icon'=>'fa-store',            'color'=>'#f6c23e'],
                        ];
                    @endphp
                    @foreach($pipelineMap as $key => $p)
                    @php $cnt = $dispatchStats[$key] ?? 0; @endphp
                    <div class="pipeline-step {{ $p['css'] }}">
                        <i class="fas {{ $p['icon'] }} fa-fw" style="color:{{ $p['color'] }};font-size:1.1rem;"></i>
                        <div class="flex-grow-1">
                            <div class="pipe-label">{{ $p['label'] }}</div>
                            <div class="pipe-sub">{{ $p['sub'] }}</div>
                        </div>
                        <div class="pipe-count" style="color:{{ $p['color'] }};">{{ $cnt }}</div>
                    </div>
                    @endforeach

                    <div class="d-flex justify-content-between mt-3 pt-2 border-top">
                        <div class="text-center">
                            <div class="h5 mb-0 text-danger fw-bold">{{ $returnStats['recibido'] }}</div>
                            <small class="text-muted">Dev. x procesar</small>
                        </div>
                        <div class="text-center">
                            <div class="h5 mb-0 text-warning fw-bold">{{ $returnStats['pendiente'] }}</div>
                            <small class="text-muted">Dev. pendientes</small>
                        </div>
                        <div class="text-center">
                            <div class="h5 mb-0 text-success fw-bold">{{ $returnStats['procesado'] }}</div>
                            <small class="text-muted">Dev. procesadas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Stock crítico + Top devueltos ═══════════════════════════════════════ --}}
    <div class="row mb-4">

        {{-- Stock crítico --}}
        <div class="col-lg-7 mb-3">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-exclamation-triangle me-1 text-danger"></i> Stock Crítico y Bajo</strong>
                    <div>
                        <span class="badge bg-danger me-1">{{ $stockCritico->count() }} agotados</span>
                        <span class="badge bg-warning text-dark">{{ $stockBajo->count() }} bajos</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Almacén</th>
                                <th class="text-center">Físico</th>
                                <th class="text-center">Comprometido</th>
                                <th class="text-center">Disponible</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockCritico as $iw)
                            <tr class="table-danger">
                                <td><span class="stock-item-name d-block" title="{{ optional($iw->item)->description }}">{{ optional($iw->item)->description ?? '—' }}</span></td>
                                <td><small class="text-muted">{{ optional($iw->warehouse)->description ?? '—' }}</small></td>
                                <td class="text-center">{{ $iw->stock_physical ?? 0 }}</td>
                                <td class="text-center text-warning">{{ $iw->stock_committed ?? 0 }}</td>
                                <td class="text-center stock-zero">{{ $iw->stock_available ?? 0 }}</td>
                                <td class="text-center"><span class="badge bg-danger">Agotado</span></td>
                            </tr>
                            @endforelse
                            @forelse($stockBajo as $iw)
                            <tr class="table-warning">
                                <td><span class="stock-item-name d-block" title="{{ optional($iw->item)->description }}">{{ optional($iw->item)->description ?? '—' }}</span></td>
                                <td><small class="text-muted">{{ optional($iw->warehouse)->description ?? '—' }}</small></td>
                                <td class="text-center">{{ $iw->stock_physical ?? 0 }}</td>
                                <td class="text-center text-warning">{{ $iw->stock_committed ?? 0 }}</td>
                                <td class="text-center stock-low">{{ $iw->stock_available ?? 0 }}</td>
                                <td class="text-center"><span class="badge bg-warning text-dark">Stock bajo</span></td>
                            </tr>
                            @empty
                                @if($stockCritico->isEmpty())
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                        Todos los productos tienen stock suficiente
                                    </td>
                                </tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top devueltos --}}
        <div class="col-lg-5 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <strong><i class="fas fa-chart-pie me-1 text-warning"></i> Top 5 — Productos más Devueltos</strong>
                </div>
                <div class="card-body">
                    @if($topDevueltos->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-smile fa-2x mb-2 d-block text-success"></i>
                            Sin devoluciones registradas
                        </div>
                    @else
                    @php
                        $maxDev = $topDevueltos->max('total_devuelto') ?: 1;
                        $colors = ['danger','warning','info','primary','secondary'];
                    @endphp
                    <canvas id="chartDevoluciones" style="max-height:180px;" class="mb-3"></canvas>
                    @foreach($topDevueltos as $i => $td)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-truncate me-2" style="max-width:200px;" title="{{ $td->description }}">
                                <span class="badge bg-{{ $colors[$i] ?? 'secondary' }} me-1">{{ $i+1 }}</span>
                                {{ $td->description }}
                            </small>
                            <small class="fw-bold text-danger">{{ $td->total_devuelto }}</small>
                        </div>
                        <div class="progress" style="height:5px;">
                            <div class="progress-bar bg-{{ $colors[$i] ?? 'secondary' }}"
                                 style="width:{{ round($td->total_devuelto / $maxDev * 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Últimos movimientos de stock ════════════════════════════════════════ --}}
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-history me-1" style="color:var(--logi-gray);"></i> Últimos Movimientos de Stock</strong>
                    <small class="text-muted">Últimos 12 registros</small>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="150">Tipo</th>
                                <th>Producto</th>
                                <th class="text-center" width="90">Δ Físico</th>
                                <th class="text-center" width="90">Δ Comprometido</th>
                                <th class="text-end" width="100">Stock Post</th>
                                <th>Referencia / Notas</th>
                                <th width="110">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movimientos as $mov)
                            @php
                                $dPhy = $mov->qty_physical ?? 0;
                                $dCom = $mov->qty_committed ?? 0;
                                $phyColor = $dPhy > 0 ? 'success' : ($dPhy < 0 ? 'danger' : 'secondary');
                                $comColor = $dCom > 0 ? 'warning' : ($dCom < 0 ? 'info' : 'secondary');
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $phyColor }} px-2">
                                        {{ $mov->type?->label() ?? $mov->type }}
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-item-name d-block"
                                          title="{{ optional($mov->item)->description }}">
                                        {{ optional($mov->item)->description ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-center mov-{{ $dPhy >= 0 ? 'plus' : 'minus' }}">
                                    {{ $dPhy != 0 ? ($dPhy > 0 ? '+' : '') . $dPhy : '—' }}
                                </td>
                                <td class="text-center" style="color:{{ $dCom > 0 ? '#f6c23e' : ($dCom < 0 ? '#36b9cc' : '#858796') }}; font-weight:600;">
                                    {{ $dCom != 0 ? ($dCom > 0 ? '+' : '') . $dCom : '—' }}
                                </td>
                                <td class="text-end fw-bold">
                                    {{ $mov->stock_physical_after ?? '—' }}
                                </td>
                                <td>
                                    <small class="text-muted text-truncate d-block" style="max-width:220px;">
                                        {{ $mov->notes ?? '—' }}
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $mov->created_at?->format('d/m H:i') }}</small>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    Sin movimientos registrados
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    // ─── Gráfico de barras: Despachos diarios ───────────────────────────────
    const labels  = @json($diasLabels);
    const data    = @json($diasData);
    const maxVal  = Math.max(...data, 1);

    const ctxBar  = document.getElementById('chartDespachos').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Despachados',
                data,
                backgroundColor: data.map(v =>
                    v === maxVal ? 'rgba(28,200,138,.85)' : 'rgba(28,200,138,.45)'
                ),
                borderColor: '#1cc88a',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} despacho${ctx.parsed.y !== 1 ? 's' : ''}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { size: 11 } },
                    grid: { color: 'rgba(0,0,0,.04)' }
                },
                x: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });

    // ─── Gráfico de dona: Devoluciones ─────────────────────────────────────
    const ctxDev = document.getElementById('chartDevoluciones');
    if (ctxDev) {
        const devLabels = @json($topDevueltos->pluck('description'));
        const devData   = @json($topDevueltos->pluck('total_devuelto'));
        new Chart(ctxDev.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: devLabels,
                datasets: [{
                    data: devData,
                    backgroundColor: ['#e74a3b','#f6c23e','#36b9cc','#4e73df','#858796'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} devueltos`
                        }
                    }
                },
                cutout: '62%',
            }
        });
    }
})();
</script>
@endpush
