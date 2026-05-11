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
                @if($listingsByStatus->isEmpty())
                    <div class="text-center text-muted py-4">Sin listings aún</div>
                @else
                    @php
                        $statusTotal = $listingsByStatus->sum('cnt') ?: 1;
                        $statusColors = [
                            'active'         => '#10b981',
                            'paused'         => '#f59e0b',
                            'rejected'       => '#ef4444',
                            'pending_review' => '#3b82f6',
                        ];
                        $statusLabels = [
                            'active'         => 'Activos',
                            'paused'         => 'Pausados',
                            'rejected'       => 'Rechazados',
                            'pending_review' => 'En revisión',
                        ];
                    @endphp
                    <div class="mp-status-bars">
                        @foreach($listingsByStatus as $s)
                            @php
                                $color = $statusColors[$s->status] ?? '#6b7280';
                                $label = $statusLabels[$s->status] ?? $s->status;
                                $pct = round(($s->cnt / $statusTotal) * 100, 1);
                            @endphp
                            <div class="mp-status-row">
                                <div class="mp-status-row__head">
                                    <span><span class="mp-status-dot" style="background:{{ $color }}"></span> {{ $label }}</span>
                                    <span class="text-muted small">{{ $s->cnt }} · {{ $pct }}%</span>
                                </div>
                                <div class="mp-status-bar"><div class="mp-status-fill" style="width:{{ $pct }}%;background:{{ $color }}"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
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
                    @php $revMax = $revenueByTenant->max('revenue') ?: 1; @endphp
                    <div class="mp-status-bars">
                        @foreach($revenueByTenant as $r)
                            @php $pct = max(2, round(($r->revenue / $revMax) * 100, 1)); @endphp
                            <div class="mp-status-row">
                                <div class="mp-status-row__head">
                                    <span>{{ $r->tenant_fqdn }}</span>
                                    <span class="text-muted small">S/ {{ number_format($r->revenue, 2) }}</span>
                                </div>
                                <div class="mp-status-bar"><div class="mp-status-fill" style="width:{{ $pct }}%;background:linear-gradient(90deg,#10b981,#0ea5e9)"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <h5 class="mb-3">🏷️ Categorías más populares</h5>
                @if($topCategories->isEmpty())
                    <div class="text-center text-muted py-4">Sin categorías oficiales asignadas aún</div>
                @else
                    @php $catMax = $topCategories->max('cnt') ?: 1; @endphp
                    <div class="mp-status-bars">
                        @foreach($topCategories as $c)
                            @php $pct = max(2, round(($c->cnt / $catMax) * 100, 1)); @endphp
                            <div class="mp-status-row">
                                <div class="mp-status-row__head">
                                    <span>{{ $c->name }}</span>
                                    <span class="text-muted small">{{ $c->cnt }} listings · {{ number_format($c->views ?? 0) }} vistas</span>
                                </div>
                                <div class="mp-status-bar"><div class="mp-status-fill" style="width:{{ $pct }}%;background:#3b82f6"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

{{-- Styles van a @stack('styles') del layout (en el <head>). Inline en
     content no se aplicaba bien — los .mp-funnel-* no estilaban
     porque algun reset del sistema layout los pisaba. --}}
@push('styles')
<style>
.mp-funnel-row { display: flex; flex-direction: column; gap: 8px; }
.mp-funnel-step { display: flex !important; align-items: center; gap: 10px; }
.mp-funnel-bar { flex: 1; height: 30px; background: #f3f4f6; border-radius: 6px; position: relative; overflow: hidden; }
.mp-funnel-fill { position: absolute; left: 0; top: 0; bottom: 0; background: linear-gradient(90deg, #3b82f6, #8b5cf6); display: flex !important; align-items: center; padding: 0 12px; color: #fff; font-size: 13px; font-weight: 700; border-radius: 6px; transition: width .6s ease; }
.mp-funnel-label { width: 80px; font-size: 13px; font-weight: 600; color: #374151; flex-shrink: 0; }
.mp-funnel-rate { width: 60px; font-size: 12px; color: #6b7280; text-align: right; font-weight: 600; flex-shrink: 0; }

/* Barras horizontales para distribución de status / revenue / categorías */
.mp-status-bars { display: flex; flex-direction: column; gap: 10px; }
.mp-status-row { display: flex; flex-direction: column; gap: 4px; }
.mp-status-row__head { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #374151; font-weight: 500; }
.mp-status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; vertical-align: middle; margin-right: 6px; }
.mp-status-bar { height: 18px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
.mp-status-fill { height: 100%; border-radius: 4px; transition: width .6s ease; }
</style>
@endpush

@push('scripts')
<script>
(function(){
    // Solo el funnel necesita JS — el resto (status, revenue, categorias)
    // se renderiza server-side con barras CSS. Sin Chart.js, sin CDN, sin
    // dependencias externas.
    const FUNNEL_DATA = @json($funnel ?? []);

    function renderFunnel() {
        const funnelEl = document.getElementById('mpFunnel');
        if (!funnelEl || !Array.isArray(FUNNEL_DATA) || FUNNEL_DATA.length === 0) return;
        const maxValue = Math.max(1, ...FUNNEL_DATA.map(s => s.value || 0));
        const html = '<div class="mp-funnel-row">' + FUNNEL_DATA.map(s => {
            const widthPct = Math.max(2, ((s.value || 0) / maxValue) * 100);
            return `
                <div class="mp-funnel-step">
                    <span class="mp-funnel-label">${s.stage}</span>
                    <div class="mp-funnel-bar">
                        <div class="mp-funnel-fill" style="width:${widthPct}%">${(s.value || 0).toLocaleString('es-PE')}</div>
                    </div>
                    <span class="mp-funnel-rate">${s.rate}%</span>
                </div>`;
        }).join('') + '</div>';
        funnelEl.innerHTML = html;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderFunnel);
    } else {
        renderFunnel();
    }
})();
</script>
@endpush
