@extends('system.layouts.app')

@section('content')
@php
    use Illuminate\Support\Str;
    // Presets de rango rápido (links que reescriben from/to manteniendo el resto).
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
    $sortLabels = [
        'views' => 'Más vistas', 'clicks' => 'Más clicks',
        'ctr' => 'Mejor CTR', 'leads' => 'Más leads',
    ];
@endphp

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">📈 Analítica de productos</h3>
        <div>
            <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
            <a href="{{ route('system.marketplace.listings') }}" class="btn btn-outline-secondary btn-sm">Productos publicados</a>
        </div>
    </div>

    {{-- ════════════ FILTROS ════════════ --}}
    <form method="GET" action="{{ route('system.marketplace.products_analytics') }}" class="card p-3 mb-3 mpa-filters">
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
                <a href="{{ route('system.marketplace.products_analytics') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-center gap-2 justify-content-md-end">
                <span class="small text-muted">Rápido:</span>
                @foreach($presets as $p)
                    <a class="btn btn-outline-secondary btn-sm py-0 px-2"
                       href="{{ route('system.marketplace.products_analytics', array_merge(request()->except(['from','to','page']), ['from' => $p['from'], 'to' => $today])) }}">{{ $p['label'] }}</a>
                @endforeach
            </div>
        </div>
    </form>

    {{-- Aviso de rango activo + fallback histórico --}}
    @if($useFallback)
        <div class="alert alert-warning py-2 px-3 small mb-3">
            ⚠️ El rango <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
            no tiene desglose diario por producto, así que se muestran los
            <strong>totales históricos acumulados</strong> de cada producto.
            @if($trackingStart)
                El tracking diario por fecha empezó el <strong>{{ \Carbon\Carbon::parse($trackingStart)->format('d/m/Y') }}</strong>: elegí un rango desde esa fecha en adelante para ver la evolución día a día.
            @else
                El tracking diario por fecha empieza a registrar desde el primer pageview tras este despliegue.
            @endif
        </div>
    @else
        <div class="small text-muted mb-3">
            Mostrando <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
            ({{ $spanDays }} {{ $spanDays === 1 ? 'día' : 'días' }}) · orden: {{ $sortLabels[$filters['sort']] ?? $filters['sort'] }}
        </div>
    @endif

    {{-- ════════════ PRODUCTO ESTRELLA ════════════ --}}
    @if($champion && $champion->views > 0)
        <div class="card p-3 mb-4 mpa-champion">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="mpa-champ-thumb">
                    @if($champion->image_url)
                        <img src="{{ $champion->image_url }}" alt="" loading="lazy">
                    @else
                        <span>📦</span>
                    @endif
                </div>
                <div class="flex-fill" style="min-width:200px">
                    <div class="small text-uppercase fw-bold" style="letter-spacing:1px;color:#8b5cf6">🏆 Producto con más vistas</div>
                    <div class="h5 mb-1">{{ Str::limit($champion->title, 70) }}</div>
                    <div class="small text-muted">{{ $champion->tenant_fqdn }}</div>
                </div>
                <div class="d-flex gap-4 text-center flex-wrap">
                    <div><div class="h4 mb-0">{{ number_format($champion->views) }}</div><small class="text-muted">vistas</small></div>
                    <div><div class="h4 mb-0">{{ number_format($champion->clicks) }}</div><small class="text-muted">clicks</small></div>
                    <div><div class="h4 mb-0">{{ $champion->ctr }}%</div><small class="text-muted">CTR</small></div>
                    <div><div class="h4 mb-0">{{ number_format($champion->leads) }}</div><small class="text-muted">leads</small></div>
                </div>
            </div>
        </div>
    @endif

    {{-- ════════════ KPIs DEL RANGO ════════════ --}}
    <div class="mpd-section-label">📋 Totales del rango</div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card p-3 border-secondary">
                <small class="text-muted">📦 Productos</small>
                <h3 class="mb-0">{{ number_format($kpis['products']) }}</h3>
                <small class="text-muted">con datos en el filtro</small>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 border-info">
                <small class="text-info">👁️ Vistas</small>
                <h3 class="mb-0">{{ number_format($kpis['views']) }}</h3>
                <small class="text-muted">en el rango</small>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3" style="border-color:#8b5cf6">
                <small style="color:#8b5cf6">🖱️ Clicks</small>
                <h3 class="mb-0">{{ number_format($kpis['clicks']) }}</h3>
                <small class="text-muted">al storefront</small>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 border-primary">
                <small class="text-primary">🎯 CTR</small>
                <h3 class="mb-0">{{ $kpis['ctr'] }}%</h3>
                <small class="text-muted">clicks / vistas</small>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card p-3 border-warning">
                <small class="text-warning">📞 Leads</small>
                <h3 class="mb-0">{{ number_format($kpis['leads']) }}</h3>
                <small class="text-muted">consultas generadas</small>
            </div>
        </div>
    </div>

    {{-- ════════════ TENDENCIA DIARIA ════════════ --}}
    @unless($useFallback)
        <div class="mpd-section-label">📈 Evolución día a día</div>
        <div class="card mb-4 p-3">
            @php
                $isEmpty = $dailySeries->isEmpty() || $dailySeries->every(fn($d) => $d->views === 0 && $d->clicks === 0);
                $maxBar  = max(1, $dailySeries->max(fn($d) => $d->views));
            @endphp
            @if($spanDays > 92)
                <div class="text-center text-muted py-4">El rango es muy amplio para el gráfico diario (máx. 92 días). Acotá las fechas para ver la evolución.</div>
            @elseif($isEmpty)
                <div class="text-center text-muted py-4">No hubo vistas ni clicks en este rango con los filtros aplicados.</div>
            @else
                <div style="display:flex;align-items:end;gap:2px;height:150px">
                    @foreach($dailySeries as $d)
                        @php
                            $viewH = max(2, round($d->views * 100 / $maxBar));
                            $clickH = $d->views > 0 ? min(100, round($d->clicks * 100 / max(1,$d->views))) : 0;
                        @endphp
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;height:100%;justify-content:flex-end"
                             title="{{ \Carbon\Carbon::parse($d->day)->format('d/m/Y') }}: {{ $d->views }} vistas · {{ $d->clicks }} clicks">
                            <div style="width:100%;height:{{ $viewH }}%;position:relative;background:#c4b5fd;border-radius:4px 4px 0 0;overflow:hidden;display:flex;align-items:end">
                                <div style="width:100%;height:{{ $clickH }}%;background:#6d28d9"></div>
                            </div>
                            @if($spanDays <= 31)
                                <small style="font-size:9px;color:#9ca3af">{{ \Carbon\Carbon::parse($d->day)->format('d/m') }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 small d-flex gap-3">
                    <span><span style="display:inline-block;width:10px;height:10px;background:#c4b5fd;border-radius:2px"></span> Vistas</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#6d28d9;border-radius:2px"></span> Clicks (dentro de la barra de vistas)</span>
                </div>
            @endif
        </div>
    @endunless

    {{-- ════════════ TOP 10 — BARRAS ════════════ --}}
    <div class="mpd-section-label">🔥 Ranking de productos</div>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3">Top 10 — por vistas 👁️</h5>
                @if($topByViews->isEmpty() || $topByViews->first()->views == 0)
                    <div class="text-center text-muted py-4">Sin vistas en este rango</div>
                @else
                    @php $vMax = max(1, $topByViews->max('views')); @endphp
                    <div class="mp-status-bars">
                        @foreach($topByViews as $l)
                            @php $pct = max(2, round($l->views / $vMax * 100, 1)); @endphp
                            <div class="mp-status-row">
                                <div class="mp-status-row__head">
                                    <span title="{{ $l->title }}">{{ Str::limit($l->title, 42) }}</span>
                                    <span class="text-muted small">{{ number_format($l->views) }}</span>
                                </div>
                                <div class="mp-status-bar"><div class="mp-status-fill" style="width:{{ $pct }}%;background:linear-gradient(90deg,#818cf8,#6366f1)"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3">Top 10 — por clicks 🖱️</h5>
                @if($topByClicks->isEmpty() || $topByClicks->first()->clicks == 0)
                    <div class="text-center text-muted py-4">Sin clicks en este rango</div>
                @else
                    @php $cMax = max(1, $topByClicks->max('clicks')); @endphp
                    <div class="mp-status-bars">
                        @foreach($topByClicks as $l)
                            @php $pct = max(2, round($l->clicks / $cMax * 100, 1)); @endphp
                            <div class="mp-status-row">
                                <div class="mp-status-row__head">
                                    <span title="{{ $l->title }}">{{ Str::limit($l->title, 42) }}</span>
                                    <span class="text-muted small">{{ number_format($l->clicks) }}</span>
                                </div>
                                <div class="mp-status-bar"><div class="mp-status-fill" style="width:{{ $pct }}%;background:linear-gradient(90deg,#a78bfa,#7c3aed)"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ════════════ TABLA DETALLE ════════════ --}}
    <div class="mpd-section-label">📋 Detalle por producto ({{ number_format($rows->count()) }})</div>
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
                            <td>
                                <a href="{{ url('/marketplace/item/' . $l->slug) }}" target="_blank" rel="noopener">{{ Str::limit($l->title, 60) }}</a>
                            </td>
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

.mpa-champion { border:1px solid #ede9fe; background:linear-gradient(90deg,#faf5ff,#ffffff); }
.mpa-champ-thumb { width:72px; height:72px; border-radius:10px; overflow:hidden; background:#f3f4f6;
    display:flex; align-items:center; justify-content:center; font-size:28px; flex-shrink:0; }
.mpa-champ-thumb img { width:100%; height:100%; object-fit:cover; }

.mpa-table th { font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; white-space:nowrap; }
.mpa-table td { font-size:13px; }

/* Barras horizontales y labels — mismas clases que el dashboard para consistencia */
.mp-status-bars { display:flex; flex-direction:column; gap:10px; }
.mp-status-row { display:flex; flex-direction:column; gap:4px; }
.mp-status-row__head { display:flex; justify-content:space-between; align-items:center; font-size:13px; color:#374151; font-weight:500; gap:8px; }
.mp-status-row__head span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mp-status-bar { height:18px; background:#f3f4f6; border-radius:4px; overflow:hidden; }
.mp-status-fill { height:100%; border-radius:4px; transition:width .6s ease; }

.mpd-section-label { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
    color:#6b7280; padding:4px 0 10px; margin-top:6px; border-bottom:1px solid #e5e7eb; margin-bottom:12px; }
.mpd-section-label:first-of-type { margin-top:0; }
</style>
@endpush
