@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📊 Marketplace — Dashboard</h3>
        <div>
            <a href="{{ route('system.marketplace.listings') }}" class="btn btn-outline-secondary btn-sm">Listings</a>
            <a href="{{ route('system.marketplace.leads') }}" class="btn btn-outline-primary btn-sm">Leads</a>
        </div>
    </div>

    {{-- KPI cards (6 columnas: tiendas, listings, pedidos, revenue, leads, tráfico) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card p-3 border-primary">
                <small class="text-muted">Tiendas activas</small>
                <h3 class="mb-0">{{ $stats['tenants_active'] }}</h3>
                <small class="text-muted">{{ $stats['listings_total'] }} productos totales</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 border-success">
                <small class="text-success">Listings activos</small>
                <h3 class="mb-0">{{ $stats['listings_active'] }}</h3>
                <small class="text-muted">{{ $stats['listings_paused'] }} pausados · {{ $stats['listings_rejected'] }} rechazados</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3" style="border-color:#10b981">
                <small style="color:#10b981">🛒 Pedidos 30d</small>
                <h3 class="mb-0">{{ number_format($stats['orders_30d']) }}</h3>
                <small class="text-muted">{{ number_format($stats['orders_total']) }} histórico</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3" style="border-color:#0ea5e9">
                <small style="color:#0ea5e9">💰 Revenue 30d</small>
                <h3 class="mb-0">S/ {{ number_format($stats['revenue_30d'], 0) }}</h3>
                <small class="text-muted">S/ {{ number_format($stats['revenue_total'], 0) }} histórico</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 border-warning">
                <small class="text-warning">Leads totales</small>
                <h3 class="mb-0">{{ $stats['leads_total'] }}</h3>
                <small class="text-muted">{{ $stats['leads_30d'] }} en 30d · {{ $stats['leads_converted'] }} conv.</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card p-3 border-info">
                <small class="text-info">Tráfico</small>
                <h3 class="mb-0">{{ number_format($stats['views_total']) }}</h3>
                <small class="text-muted">{{ number_format($stats['clicks_total']) }} clicks · {{ $stats['conversion_rate'] }}% conv.</small>
            </div>
        </div>
    </div>

    {{-- Chart combinado leads + pedidos últimos 30d --}}
    <div class="card mb-4 p-3">
        <h5 class="mb-3">Actividad — últimos 30 días</h5>
        @php
            $isEmpty = $dailySeries->every(fn($d) => $d->leads === 0 && $d->orders === 0);
            $maxBar  = $dailySeries->reduce(fn($c, $d) => max($c, $d->leads + $d->orders), 1);
        @endphp
        @if($isEmpty)
            <div class="text-center text-muted py-4">No hay actividad aún</div>
        @else
            <div style="display:flex;align-items:end;gap:3px;height:130px">
                @foreach($dailySeries as $d)
                    @php
                        $totalH = max(2, round(($d->leads + $d->orders) * 100 / $maxBar));
                        $ordH   = ($d->leads + $d->orders) > 0 ? round($d->orders * 100 / ($d->leads + $d->orders)) : 0;
                        $leadH  = 100 - $ordH;
                    @endphp
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;height:100%;justify-content:flex-end"
                         title="{{ $d->day }}: {{ $d->orders }} pedidos · {{ $d->leads }} leads">
                        <div style="width:100%;height:{{ $totalH }}%;display:flex;flex-direction:column-reverse;border-radius:4px 4px 0 0;overflow:hidden">
                            <div style="background:#10b981;height:{{ $ordH }}%"></div>
                            <div style="background:#8b5cf6;height:{{ $leadH }}%"></div>
                        </div>
                        <small style="font-size:9px;color:#9ca3af">{{ \Carbon\Carbon::parse($d->day)->format('d/m') }}</small>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 small d-flex gap-3">
                <span><span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px"></span> Pedidos</span>
                <span><span style="display:inline-block;width:10px;height:10px;background:#8b5cf6;border-radius:2px"></span> Leads</span>
            </div>
        @endif
    </div>

    <div class="row g-3">
        {{-- Top tiendas --}}
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">🏪 Top tiendas</h5>
                <table class="table table-sm">
                    <thead>
                        <tr><th>Tienda</th><th class="text-center">Listings</th><th class="text-center">Clicks</th><th class="text-center">Leads</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topTenants as $t)
                            <tr>
                                <td><small>{{ $t->tenant_fqdn }}</small></td>
                                <td class="text-center">{{ $t->listings }}</td>
                                <td class="text-center">{{ $t->c }}</td>
                                <td class="text-center"><span class="badge bg-primary">{{ $t->l }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Top productos --}}
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">🔥 Productos con más clicks</h5>
                <table class="table table-sm">
                    <thead><tr><th>Producto</th><th class="text-center">Clicks</th><th class="text-center">Leads</th></tr></thead>
                    <tbody>
                        @forelse($topListings as $l)
                            <tr>
                                <td>
                                    <a href="{{ $l->public_url }}" target="_blank"><small>{{ \Illuminate\Support\Str::limit($l->title, 50) }}</small></a>
                                    <br><small class="text-muted">{{ $l->tenant_fqdn }}</small>
                                </td>
                                <td class="text-center">{{ $l->click_count }}</td>
                                <td class="text-center"><span class="badge bg-info">{{ $l->lead_count }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════ MÁS GRÁFICOS ═══════════════════════ --}}
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">🎯 Funnel global de conversión</h5>
                <div id="mpFunnel"></div>
                <small class="text-muted d-block mt-3">
                    Vistas → Clicks → Leads → Pedidos. Cada paso muestra el % del tráfico total que llegó hasta ahí.
                </small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">📊 Estado de listings</h5>
                <canvas id="mpStatusChart" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">💰 Revenue por tienda (top 5)</h5>
                @if($revenueByTenant->isEmpty() || $revenueByTenant->sum('revenue') == 0)
                    <div class="text-center text-muted py-4">Sin pedidos aún</div>
                @else
                    <canvas id="mpRevenueChart" height="180"></canvas>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">🏷️ Categorías más populares</h5>
                @if($topCategories->isEmpty())
                    <div class="text-center text-muted py-4">Sin categorías oficiales asignadas aún</div>
                @else
                    <canvas id="mpCategoriesChart" height="220"></canvas>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.mp-funnel-row { display: flex; flex-direction: column; gap: 8px; }
.mp-funnel-step { display: flex; align-items: center; gap: 10px; }
.mp-funnel-bar { flex: 1; height: 30px; background: #f3f4f6; border-radius: 6px; position: relative; overflow: hidden; }
.mp-funnel-fill { position: absolute; left: 0; top: 0; bottom: 0; background: linear-gradient(90deg, #3b82f6, #8b5cf6); display: flex; align-items: center; padding: 0 12px; color: #fff; font-size: 13px; font-weight: 700; border-radius: 6px; transition: width .6s ease; }
.mp-funnel-label { width: 80px; font-size: 13px; font-weight: 600; color: #374151; }
.mp-funnel-rate { width: 60px; font-size: 12px; color: #6b7280; text-align: right; font-weight: 600; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function(){
    // ── Funnel (rendered como HTML stacked bars, sin Chart.js) ─────────────
    const funnelData = @json($funnel);
    const maxValue = Math.max(1, ...funnelData.map(s => s.value));
    const funnelHtml = '<div class="mp-funnel-row">' + funnelData.map(s => {
        const widthPct = Math.max(2, (s.value / maxValue) * 100);
        return `
            <div class="mp-funnel-step">
                <span class="mp-funnel-label">${s.stage}</span>
                <div class="mp-funnel-bar">
                    <div class="mp-funnel-fill" style="width:${widthPct}%">${(s.value || 0).toLocaleString('es-PE')}</div>
                </div>
                <span class="mp-funnel-rate">${s.rate}%</span>
            </div>`;
    }).join('') + '</div>';
    document.getElementById('mpFunnel').innerHTML = funnelHtml;

    // ── Status doughnut ────────────────────────────────────────────────────
    const statusData = @json($listingsByStatus);
    if (statusData.length > 0) {
        new Chart(document.getElementById('mpStatusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.status),
                datasets: [{
                    data: statusData.map(s => s.cnt),
                    backgroundColor: ['#10b981','#f59e0b','#ef4444','#6b7280','#3b82f6','#8b5cf6'],
                    borderWidth: 0,
                }],
            },
            options: { plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false },
        });
    }

    // ── Revenue por tenant doughnut ───────────────────────────────────────
    const revData = @json($revenueByTenant);
    if (revData.length > 0 && revData.some(r => r.revenue > 0)) {
        new Chart(document.getElementById('mpRevenueChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: revData.map(r => r.tenant_fqdn || '—'),
                datasets: [{
                    data: revData.map(r => Number(r.revenue)),
                    backgroundColor: ['#10b981','#3b82f6','#f59e0b','#8b5cf6','#ec4899'],
                    borderWidth: 0,
                }],
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': S/ ' + Number(ctx.parsed).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2}) } },
                },
                responsive: true, maintainAspectRatio: false,
            },
        });
    }

    // ── Top categorías (horizontal bars) ───────────────────────────────────
    const catData = @json($topCategories);
    if (catData.length > 0) {
        new Chart(document.getElementById('mpCategoriesChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: catData.map(c => c.name),
                datasets: [{
                    label: 'Listings', data: catData.map(c => Number(c.cnt)),
                    backgroundColor: '#3b82f6', borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                responsive: true, maintainAspectRatio: false,
            },
        });
    }
})();
</script>
@endsection
