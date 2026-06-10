@extends('system.layouts.app')

@section('content')
@php
    use Illuminate\Support\Str;

    $today = now()->toDateString();
    $presets = [
        '7'  => ['label' => '7 días',  'from' => now()->subDays(6)->toDateString()],
        '30' => ['label' => '30 días', 'from' => now()->subDays(29)->toDateString()],
        '90' => ['label' => '90 días', 'from' => now()->subDays(89)->toDateString()],
    ];
    $statusLabels = [
        'active' => 'Activos', 'paused' => 'Pausados',
        'rejected' => 'Rechazados', 'pending_review' => 'En revisión',
    ];
    $statusColors = [
        'active' => '#10b981', 'paused' => '#f59e0b',
        'rejected' => '#ef4444', 'pending_review' => '#3b82f6',
    ];
    $sortLabels = [
        'views' => 'Más vistas', 'clicks' => 'Más clicks',
        'ctr' => 'Mejor CTR', 'leads' => 'Más leads',
    ];

    // ── Datos para los gráficos (json_encode evita el trap de @json) ──
    $chartData = [
        'trend' => [
            'labels' => $dailySeries->map(fn($d) => \Carbon\Carbon::parse($d->day)->format('d/m'))->values(),
            'views'  => $dailySeries->pluck('views')->values(),
            'clicks' => $dailySeries->pluck('clicks')->values(),
        ],
        'top' => [
            'labels' => $topByViews->map(fn($l) => Str::limit($l->title, 24))->values(),
            'views'  => $topByViews->pluck('views')->values(),
            'clicks' => $topByViews->pluck('clicks')->values(),
        ],
        'cat' => [
            'labels' => $byCategory->pluck('label')->values(),
            'views'  => $byCategory->pluck('views')->values(),
        ],
        'tenant' => [
            'labels' => $byTenant->pluck('label')->values(),
            'views'  => $byTenant->pluck('views')->values(),
            'clicks' => $byTenant->pluck('clicks')->values(),
        ],
        'funnel' => [
            'labels' => collect($funnel)->pluck('stage')->values(),
            'values' => collect($funnel)->pluck('value')->values(),
            'rates'  => collect($funnel)->pluck('rate')->values(),
        ],
        'status' => [
            'labels' => $listingsByStatus->map(fn($s) => $statusLabels[$s->status] ?? $s->status)->values(),
            'values' => $listingsByStatus->pluck('cnt')->values(),
            'colors' => $listingsByStatus->map(fn($s) => $statusColors[$s->status] ?? '#6b7280')->values(),
        ],
        'revenue' => [
            'labels' => $revenueByTenant->pluck('tenant_fqdn')->values(),
            'values' => $revenueByTenant->map(fn($r) => round((float) $r->revenue, 2))->values(),
        ],
        'showTrend' => !$useFallback && $spanDays <= 92 && $dailySeries->isNotEmpty(),
    ];
@endphp

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">📊 Marketplace — Dashboard</h3>
        <div>
            <a href="{{ route('system.marketplace.seo') }}" class="btn btn-outline-info btn-sm">🔗 SEO / Compartir</a>
            <a href="{{ route('system.marketplace.listings') }}" class="btn btn-outline-secondary btn-sm">Listings</a>
            <a href="{{ route('system.marketplace.leads') }}" class="btn btn-outline-primary btn-sm">Leads</a>
        </div>
    </div>

    {{-- ════════════ FILTROS ════════════ --}}
    <form method="GET" action="{{ route('system.marketplace.dashboard') }}" class="card p-3 mb-3 mpa-filters">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ $today }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" max="{{ $today }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small mb-1">Tienda</label>
                <input type="text" name="tenant" value="{{ $filters['tenant'] }}" placeholder="ej. tienda.ebaemy.com" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Categoría</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string)$filters['category'] === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Ordenar por</label>
                <select name="sort" class="form-select form-select-sm">
                    @foreach($sortLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected($filters['sort'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1">Buscar producto</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nombre del producto…" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Vistas mínimas</label>
                <input type="number" name="min_views" min="0" value="{{ $filters['minViews'] ?: '' }}" placeholder="0" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">Aplicar filtros</button>
                <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-center gap-2 justify-content-md-end">
                <span class="small text-muted">Rápido:</span>
                @foreach($presets as $p)
                    <a class="btn btn-outline-secondary btn-sm py-0 px-2"
                       href="{{ route('system.marketplace.dashboard', array_merge(request()->except(['from','to','page']), ['from' => $p['from'], 'to' => $today])) }}">{{ $p['label'] }}</a>
                @endforeach
            </div>
        </div>
    </form>

    {{-- Aviso de rango / fallback --}}
    @if($useFallback)
        <div class="alert alert-warning py-2 px-3 small mb-3">
            ⚠️ El rango <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
            no tiene desglose diario de vistas/clicks, así que esas métricas muestran los <strong>totales históricos acumulados</strong> por producto.
            @if($trackingStart)
                El tracking diario empezó el <strong>{{ \Carbon\Carbon::parse($trackingStart)->format('d/m/Y') }}</strong>: elegí un rango desde esa fecha para ver la evolución día a día.
            @else
                El tracking diario empieza a registrar desde el primer pageview tras el despliegue.
            @endif
        </div>
    @endif

    {{-- ════════════ DASHBOARD OSCURO ════════════ --}}
    <div class="mpa-dash">
        <div class="mpa-dash__head">
            <div>
                <div class="mpa-dash__title">Rendimiento del marketplace</div>
                <div class="mpa-dash__sub">
                    {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
                    · {{ $spanDays }} {{ $spanDays === 1 ? 'día' : 'días' }}
                </div>
            </div>
            @if($champion && $champion->views > 0)
                <div class="mpa-champ">
                    <div class="mpa-champ__thumb">
                        @if($champion->image_url)<img src="{{ $champion->image_url }}" alt="" loading="lazy">@else<span>📦</span>@endif
                    </div>
                    <div>
                        <div class="mpa-champ__tag">🏆 Producto con más vistas</div>
                        <div class="mpa-champ__name">{{ Str::limit($champion->title, 46) }}</div>
                        <div class="mpa-champ__meta">{{ number_format($champion->views) }} vistas · {{ number_format($champion->clicks) }} clicks · {{ $champion->ctr }}% CTR</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- KPIs: negocio + tráfico --}}
        <div class="mpa-kpis">
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#60a5fa">🏪</span><div><div class="mpa-kpi__num">{{ number_format($kpis['tenants_active']) }}</div><div class="mpa-kpi__lbl">Tiendas activas</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#34d399">📦</span><div><div class="mpa-kpi__num">{{ number_format($kpis['listings_active']) }}</div><div class="mpa-kpi__lbl">Listings activos</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#38bdf8">👁️</span><div><div class="mpa-kpi__num">{{ number_format($kpis['views']) }}</div><div class="mpa-kpi__lbl">Vistas</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#a78bfa">🖱️</span><div><div class="mpa-kpi__num">{{ number_format($kpis['clicks']) }}</div><div class="mpa-kpi__lbl">Clicks</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#22d3ee">🎯</span><div><div class="mpa-kpi__num">{{ $kpis['ctr'] }}%</div><div class="mpa-kpi__lbl">CTR</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#fbbf24">📞</span><div><div class="mpa-kpi__num">{{ number_format($kpis['leads']) }}</div><div class="mpa-kpi__lbl">Leads</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#4ade80">🛒</span><div><div class="mpa-kpi__num">{{ number_format($kpis['orders']) }}</div><div class="mpa-kpi__lbl">Pedidos</div></div></div>
            <div class="mpa-kpi"><span class="mpa-kpi__ico" style="color:#0ea5e9">💰</span><div><div class="mpa-kpi__num">S/ {{ number_format($kpis['revenue'], 0) }}</div><div class="mpa-kpi__lbl">Revenue</div></div></div>
        </div>

        {{-- Fila: funnel + estado de listings --}}
        <div class="mpa-grid mpa-grid--2">
            <div class="mpa-chart">
                <div class="mpa-chart__title">Funnel de conversión <span class="mpa-chart__hint">vistas → clicks → leads → pedidos</span></div>
                <div id="chFunnel" class="mpa-chart__canvas"></div>
            </div>
            <div class="mpa-chart">
                <div class="mpa-chart__title">Estado de listings</div>
                <div id="chStatus" class="mpa-chart__canvas"></div>
                <div class="mpa-chart__empty" id="chStatusEmpty" hidden>Sin listings.</div>
            </div>
        </div>

        {{-- Fila: tendencia (área) --}}
        <div class="mpa-grid">
            <div class="mpa-chart mpa-chart--wide">
                <div class="mpa-chart__title">Vistas y clicks por día</div>
                @if($chartData['showTrend'])
                    <div id="chTrend" class="mpa-chart__canvas"></div>
                @else
                    <div class="mpa-chart__empty">
                        @if($useFallback)
                            Elegí un rango dentro del período con tracking diario para ver la evolución.
                        @else
                            Sin actividad diaria en este rango.
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Fila: top productos + categoría --}}
        <div class="mpa-grid mpa-grid--2">
            <div class="mpa-chart">
                <div class="mpa-chart__title">Top 10 productos — vistas vs clicks</div>
                <div id="chTop" class="mpa-chart__canvas"></div>
                <div class="mpa-chart__empty" id="chTopEmpty" hidden>Sin vistas en este rango.</div>
            </div>
            <div class="mpa-chart">
                <div class="mpa-chart__title">Vistas por categoría</div>
                <div id="chCat" class="mpa-chart__canvas"></div>
                <div class="mpa-chart__empty" id="chCatEmpty" hidden>Sin categorías con vistas.</div>
            </div>
        </div>

        {{-- Fila: tienda (vistas/clicks) + revenue por tienda --}}
        <div class="mpa-grid mpa-grid--2">
            <div class="mpa-chart">
                <div class="mpa-chart__title">Vistas y clicks por tienda</div>
                <div id="chTenant" class="mpa-chart__canvas"></div>
                <div class="mpa-chart__empty" id="chTenantEmpty" hidden>Sin tiendas con vistas.</div>
            </div>
            <div class="mpa-chart">
                <div class="mpa-chart__title">Revenue por tienda <span class="mpa-chart__hint">en el rango</span></div>
                <div id="chRevenue" class="mpa-chart__canvas"></div>
                <div class="mpa-chart__empty" id="chRevenueEmpty" hidden>Sin pedidos en este rango.</div>
            </div>
        </div>
    </div>

    {{-- ════════════ TABLA DETALLE ════════════ --}}
    <div class="mpd-section-label mt-4">📋 Detalle por producto ({{ number_format($rows->count()) }})</div>
    <div class="card p-0 mb-4">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 mpa-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Producto</th>
                        <th>Tienda</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end">Vistas</th>
                        <th class="text-end">Clicks</th>
                        <th class="text-end">CTR</th>
                        <th class="text-end">Leads</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows->take(200) as $i => $l)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td><a href="{{ url('/marketplace/item/' . $l->slug) }}" target="_blank" rel="noopener">{{ Str::limit($l->title, 60) }}</a></td>
                            <td><small class="text-muted">{{ $l->tenant_fqdn }}</small></td>
                            <td class="text-center"><span class="badge bg-light text-dark">{{ $statusLabels[$l->status] ?? $l->status }}</span></td>
                            <td class="text-end">{{ number_format($l->views) }}</td>
                            <td class="text-end">{{ number_format($l->clicks) }}</td>
                            <td class="text-end">{{ $l->ctr }}%</td>
                            <td class="text-end"><span class="badge bg-info">{{ number_format($l->leads) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay productos que coincidan con los filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->count() > 200)
            <div class="p-2 small text-muted text-center">Mostrando los primeros 200 de {{ number_format($rows->count()) }}. Acotá los filtros para ver el resto.</div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.mpa-filters .form-label { color:#6b7280; font-weight:600; }

.mpa-dash { background:#0f1729; border-radius:16px; padding:20px; color:#e2e8f0; box-shadow:0 10px 30px rgba(15,23,42,.25); }
.mpa-dash__head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
.mpa-dash__title { font-size:18px; font-weight:700; color:#fff; }
.mpa-dash__sub { font-size:12.5px; color:#94a3b8; }

.mpa-champ { display:flex; align-items:center; gap:12px; background:#1b2440; border:1px solid #2a3656; border-radius:12px; padding:10px 14px; max-width:420px; }
.mpa-champ__thumb { width:52px; height:52px; border-radius:9px; overflow:hidden; background:#0f1729; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
.mpa-champ__thumb img { width:100%; height:100%; object-fit:cover; }
.mpa-champ__tag { font-size:10.5px; font-weight:700; letter-spacing:.5px; color:#c4b5fd; text-transform:uppercase; }
.mpa-champ__name { font-size:14px; font-weight:600; color:#fff; line-height:1.2; }
.mpa-champ__meta { font-size:11.5px; color:#94a3b8; }

.mpa-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.mpa-kpi { display:flex; align-items:center; gap:10px; background:#1b2440; border:1px solid #2a3656; border-radius:12px; padding:12px 14px; }
.mpa-kpi__ico { font-size:20px; }
.mpa-kpi__num { font-size:19px; font-weight:700; color:#fff; line-height:1.1; }
.mpa-kpi__lbl { font-size:10.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.4px; }

.mpa-grid { display:grid; grid-template-columns:1fr; gap:14px; margin-bottom:14px; }
.mpa-grid--2 { grid-template-columns:1fr 1fr; }
.mpa-chart { background:#131c33; border:1px solid #2a3656; border-radius:14px; padding:16px; }
.mpa-chart__title { font-size:13.5px; font-weight:700; color:#e2e8f0; margin-bottom:6px; }
.mpa-chart__hint { font-size:11px; font-weight:400; color:#64748b; }
.mpa-chart__canvas { min-height:300px; }
.mpa-chart--wide .mpa-chart__canvas { min-height:320px; }
.mpa-chart__empty { color:#64748b; text-align:center; padding:60px 10px; font-size:13px; }

@media (max-width: 991px){
    .mpa-kpis { grid-template-columns:repeat(2,1fr); }
    .mpa-grid--2 { grid-template-columns:1fr; }
}

.mpa-table th { font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; white-space:nowrap; }
.mpa-table td { font-size:13px; }
.mpd-section-label { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6b7280; padding:4px 0 10px; border-bottom:1px solid #e5e7eb; margin-bottom:12px; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    var DATA = {!! json_encode($chartData) !!};
    var PALETTE = ['#3b82f6', '#22d3ee', '#ef4444', '#f59e0b', '#10b981', '#a78bfa', '#ec4899'];

    function ready(fn){ document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn(); }
    ready(function () {
        if (typeof ApexCharts === 'undefined') return;

        var base = {
            chart: { background: 'transparent', toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#94a3b8' },
            theme: { mode: 'dark' },
            grid: { borderColor: '#243049', strokeDashArray: 4 },
            tooltip: { theme: 'dark' },
            dataLabels: { enabled: false },
            legend: { labels: { colors: '#cbd5e1' } },
            noData: { text: 'Sin datos', style: { color: '#64748b' } },
        };
        var sum = function (a){ return (a || []).reduce(function (s, n){ return s + (+n || 0); }, 0); };
        function deepMerge(a, b){ var o = Object.assign({}, a); for (var k in b){ o[k] = (b[k] && typeof b[k]==='object' && !Array.isArray(b[k])) ? deepMerge(a[k]||{}, b[k]) : b[k]; } return o; }
        function render(id, opts){ var el = document.querySelector(id); if (el) new ApexCharts(el, deepMerge(base, opts)).render(); }
        function emptyState(id){ var c = document.querySelector(id); if (c) c.style.display='none'; var e = document.querySelector(id+'Empty'); if (e) e.hidden=false; }

        // Funnel (barras horizontales)
        if (sum(DATA.funnel.values) > 0) {
            render('#chFunnel', {
                chart: { type: 'bar', height: 300 },
                series: [{ name: 'Cantidad', data: DATA.funnel.values }],
                colors: ['#6366f1'],
                plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%', borderRadius: 4 } },
                colors: ['#3b82f6', '#22d3ee', '#f59e0b', '#10b981'],
                dataLabels: { enabled: true, style: { colors: ['#fff'] } },
                xaxis: { categories: DATA.funnel.labels },
                legend: { show: false },
                tooltip: { y: { formatter: function(v, o){ var r = DATA.funnel.rates[o.dataPointIndex]; return v.toLocaleString('es-PE') + ' (' + r + '%)'; } } },
            });
        }

        // Estado de listings (donut)
        if (sum(DATA.status.values) > 0) {
            render('#chStatus', {
                chart: { type: 'donut', height: 300 },
                series: DATA.status.values,
                labels: DATA.status.labels,
                colors: DATA.status.colors,
                legend: { position: 'bottom', labels: { colors: '#cbd5e1' } },
                plotOptions: { pie: { donut: { size: '62%' } } },
                stroke: { colors: ['#131c33'] },
            });
        } else { emptyState('#chStatus'); }

        // Tendencia diaria (área)
        if (DATA.showTrend) {
            render('#chTrend', {
                chart: { type: 'area', height: 320 },
                series: [
                    { name: 'Vistas', data: DATA.trend.views },
                    { name: 'Clicks', data: DATA.trend.clicks },
                ],
                colors: ['#22d3ee', '#a78bfa'],
                xaxis: { categories: DATA.trend.labels, tickAmount: 10 },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95] } },
            });
        }

        // Top 10 productos (barras agrupadas)
        if (sum(DATA.top.views) > 0) {
            render('#chTop', {
                chart: { type: 'bar', height: 360 },
                series: [
                    { name: 'Vistas', data: DATA.top.views },
                    { name: 'Clicks', data: DATA.top.clicks },
                ],
                colors: ['#3b82f6', '#ef4444'],
                plotOptions: { bar: { columnWidth: '62%', borderRadius: 4 } },
                xaxis: { categories: DATA.top.labels, labels: { rotate: -40, trim: true, style: { fontSize: '10px' } } },
            });
        } else { emptyState('#chTop'); }

        // Vistas por categoría (donut)
        if (sum(DATA.cat.views) > 0) {
            render('#chCat', {
                chart: { type: 'donut', height: 360 },
                series: DATA.cat.views,
                labels: DATA.cat.labels,
                colors: PALETTE,
                legend: { position: 'bottom', labels: { colors: '#cbd5e1' } },
                plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Vistas', color: '#cbd5e1' } } } } },
                stroke: { colors: ['#131c33'] },
            });
        } else { emptyState('#chCat'); }

        // Vistas y clicks por tienda (barras horizontales)
        if (sum(DATA.tenant.views) > 0) {
            render('#chTenant', {
                chart: { type: 'bar', height: 340 },
                series: [
                    { name: 'Vistas', data: DATA.tenant.views },
                    { name: 'Clicks', data: DATA.tenant.clicks },
                ],
                colors: ['#10b981', '#f59e0b'],
                plotOptions: { bar: { horizontal: true, barHeight: '64%', borderRadius: 4 } },
                xaxis: { categories: DATA.tenant.labels },
            });
        } else { emptyState('#chTenant'); }

        // Revenue por tienda (donut)
        if (sum(DATA.revenue.values) > 0) {
            render('#chRevenue', {
                chart: { type: 'donut', height: 340 },
                series: DATA.revenue.values,
                labels: DATA.revenue.labels,
                colors: PALETTE,
                legend: { position: 'bottom', labels: { colors: '#cbd5e1' } },
                plotOptions: { pie: { donut: { size: '62%' } } },
                stroke: { colors: ['#131c33'] },
                tooltip: { y: { formatter: function(v){ return 'S/ ' + (+v).toLocaleString('es-PE'); } } },
            });
        } else { emptyState('#chRevenue'); }
    });
})();
</script>
@endpush
