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
</div>
@endsection
