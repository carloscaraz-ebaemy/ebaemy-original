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
        'active' => 'Activo', 'paused' => 'Pausado',
        'rejected' => 'Rechazado', 'pending_review' => 'En revisión',
    ];
    $sortLabels = [
        'views' => 'Más vistas', 'clicks' => 'Más clicks',
        'ctr' => 'Mejor CTR', 'leads' => 'Más leads',
    ];

    // Helpers de orden: encabezados clicables que preservan los filtros.
    $sortUrl = fn($col) => route('system.marketplace.dashboard', array_merge(request()->except('page'), ['sort' => $col]));
    $sortMark = fn($col) => $filters['sort'] === $col ? '<span class="mp-sort-on">↓</span>' : '';

    // Escalas para las mini-barras de la tabla.
    $maxViews  = max(1, $rows->max('views') ?: 1);
    $maxClicks = max(1, $rows->max('clicks') ?: 1);

    // Tasas de conversión (encodean el funnel sin un gráfico aparte).
    $rateClicks = $kpis['views'] > 0 ? round($kpis['clicks'] / $kpis['views'] * 100, 1) : 0;
    $rateLeads  = $kpis['views'] > 0 ? round($kpis['leads']  / $kpis['views'] * 100, 1) : 0;
    $rateOrders = $kpis['views'] > 0 ? round($kpis['orders'] / $kpis['views'] * 100, 1) : 0;

    $chartData = [
        'trend' => [
            'labels' => $dailySeries->map(fn($d) => \Carbon\Carbon::parse($d->day)->format('d/m'))->values(),
            'views'  => $dailySeries->pluck('views')->values(),
            'clicks' => $dailySeries->pluck('clicks')->values(),
        ],
        'top' => [
            'labels' => $topByViews->map(fn($l) => Str::limit($l->title, 28))->values(),
            'views'  => $topByViews->pluck('views')->values(),
            'clicks' => $topByViews->pluck('clicks')->values(),
        ],
        'tenant' => [
            'labels' => $byTenant->pluck('label')->values(),
            'views'  => $byTenant->pluck('views')->values(),
            'clicks' => $byTenant->pluck('clicks')->values(),
        ],
        'cat' => [
            'labels' => $byCategory->pluck('label')->values(),
            'views'  => $byCategory->pluck('views')->values(),
        ],
        'funnel' => [
            'labels' => collect($funnel)->pluck('stage')->values(),
            'values' => collect($funnel)->pluck('value')->values(),
            'rates'  => collect($funnel)->pluck('rate')->values(),
        ],
        'showTrend' => $dailySeries->isNotEmpty(),
    ];
@endphp

<div class="container-fluid py-3 mpd">
    {{-- ── Encabezado ── --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mpd-h1 mb-0">Marketplace</h3>
            <div class="mpd-breadcrumb">Rendimiento de productos y tiendas</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('system.marketplace.seo') }}" class="btn btn-outline-secondary btn-sm">SEO / Compartir</a>
            <a href="{{ route('system.marketplace.listings') }}" class="btn btn-outline-secondary btn-sm">Listings</a>
            <a href="{{ route('system.marketplace.leads') }}" class="btn btn-outline-secondary btn-sm">Leads</a>
        </div>
    </div>

    {{-- ── Filtros ── --}}
    <form method="GET" action="{{ route('system.marketplace.dashboard') }}" class="mpd-filters">
        <div class="mpd-filters__dates">
            <div class="mpd-field">
                <label>Desde</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ $today }}">
            </div>
            <div class="mpd-field">
                <label>Hasta</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" max="{{ $today }}">
            </div>
            <div class="mpd-presets">
                @foreach($presets as $p)
                    <a href="{{ route('system.marketplace.dashboard', array_merge(request()->except(['from','to','page']), ['from' => $p['from'], 'to' => $today])) }}"
                       class="mpd-chip {{ $filters['from'] === $p['from'] && $filters['to'] === $today ? 'is-active' : '' }}">{{ $p['label'] }}</a>
                @endforeach
            </div>
        </div>
        <div class="mpd-filters__row">
            <div class="mpd-field mpd-field--grow">
                <label>Buscar producto</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nombre del producto…">
            </div>
            <div class="mpd-field">
                <label>Tienda</label>
                <input type="text" name="tenant" value="{{ $filters['tenant'] }}" placeholder="tienda.ebaemy.com">
            </div>
            <div class="mpd-field">
                <label>Categoría</label>
                <select name="category">
                    <option value="">Todas</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string)$filters['category'] === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mpd-field">
                <label>Estado</label>
                <select name="status">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mpd-field mpd-field--narrow">
                <label>Vistas mín.</label>
                <input type="number" name="min_views" min="0" value="{{ $filters['minViews'] ?: '' }}" placeholder="0">
            </div>
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <div class="mpd-actions">
                <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-link btn-sm text-muted">Limpiar</a>
            </div>
        </div>
    </form>

    {{-- ── Aviso de rango / fallback ── --}}
    <div class="mpd-rangebar">
        <span class="mpd-rangebar__period">{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</span>
        @if($useFallback)
            <span class="mpd-rangebar__note">
                Mostrando <strong>totales históricos acumulados</strong> de vistas/clicks.
                @if($trackingStart)
                    El desglose diario está disponible desde el {{ \Carbon\Carbon::parse($trackingStart)->format('d/m/Y') }}.
                @else
                    El desglose diario empezará a registrarse con el tráfico nuevo.
                @endif
            </span>
        @else
            <span class="mpd-rangebar__note">{{ $spanDays }} {{ $spanDays === 1 ? 'día' : 'días' }} · desglose diario</span>
        @endif
    </div>

    {{-- ── KPIs (metric strip sobrio, jerarquía por peso) ── --}}
    <div class="mpd-metrics">
        <div class="mpd-metric mpd-metric--primary">
            <span class="mpd-metric__label">Vistas</span>
            <span class="mpd-metric__value">{{ number_format($kpis['views']) }}</span>
        </div>
        <div class="mpd-metric">
            <span class="mpd-metric__label">Clicks</span>
            <span class="mpd-metric__value">{{ number_format($kpis['clicks']) }}</span>
            <span class="mpd-metric__rate">{{ $rateClicks }}% de vistas</span>
        </div>
        <div class="mpd-metric">
            <span class="mpd-metric__label">Leads</span>
            <span class="mpd-metric__value">{{ number_format($kpis['leads']) }}</span>
            <span class="mpd-metric__rate">{{ $rateLeads }}% de vistas</span>
        </div>
        <div class="mpd-metric">
            <span class="mpd-metric__label">Pedidos</span>
            <span class="mpd-metric__value">{{ number_format($kpis['orders']) }}</span>
            <span class="mpd-metric__rate">{{ $rateOrders }}% de vistas</span>
        </div>
        <div class="mpd-metric">
            <span class="mpd-metric__label">Revenue</span>
            <span class="mpd-metric__value">S/ {{ number_format($kpis['revenue'], 0) }}</span>
        </div>
        <div class="mpd-metric__context">
            <span><strong>{{ $kpis['tenants_active'] }}</strong> tiendas activas</span>
            <span><strong>{{ $kpis['listings_active'] }}</strong> de {{ $kpis['listings_total'] }} listings activos</span>
        </div>
    </div>

    {{-- ── Top / Rezagado: respuesta directa a "qué rinde y qué no" ── --}}
    <div class="mpd-highlights">
        @if($champion && $champion->views > 0)
            <a href="{{ url('/marketplace/item/' . $champion->slug) }}" target="_blank" rel="noopener" class="mpd-card mpd-card--good">
                <div class="mpd-card__head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4zM5 9a2 2 0 0 1-2-2V5h4M19 9a2 2 0 0 0 2-2V5h-4"/></svg>
                    <span>Mejor rendimiento</span>
                </div>
                <div class="mpd-card__title">{{ Str::limit($champion->title, 52) }}</div>
                <div class="mpd-card__tenant">{{ $champion->tenant_fqdn }}</div>
                <div class="mpd-card__stats">
                    <span><strong>{{ number_format($champion->views) }}</strong> vistas</span>
                    <span><strong>{{ number_format($champion->clicks) }}</strong> clicks</span>
                    <span class="mpd-stat-good"><strong>{{ $champion->ctr }}%</strong> CTR</span>
                </div>
            </a>
        @endif

        @if($laggard && $laggard->views > 0 && (!$champion || $laggard->id !== $champion->id))
            <a href="{{ url('/marketplace/item/' . $laggard->slug) }}" target="_blank" rel="noopener" class="mpd-card mpd-card--warn">
                <div class="mpd-card__head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                    <span>Desperdicia tráfico</span>
                </div>
                <div class="mpd-card__title">{{ Str::limit($laggard->title, 52) }}</div>
                <div class="mpd-card__tenant">{{ $laggard->tenant_fqdn }}</div>
                <div class="mpd-card__stats">
                    <span><strong>{{ number_format($laggard->views) }}</strong> vistas</span>
                    <span><strong>{{ number_format($laggard->clicks) }}</strong> clicks</span>
                    <span class="mpd-stat-warn"><strong>{{ $laggard->ctr }}%</strong> CTR</span>
                </div>
                <div class="mpd-card__hint">Se ve mucho pero casi nadie hace click: revisá precio, foto o título.</div>
            </a>
        @endif
    </div>

    {{-- ════════════ GRÁFICOS ════════════ --}}
    {{-- Fila 1: línea de tiempo de vistas (protagonista visual) --}}
    <div class="mpd-panel">
        <div class="mpd-panel__head">
            <h5 class="mpd-panel__title">Vistas en el tiempo</h5>
            @if($chartData['showTrend'])
                <div class="mpd-trendstats">
                    <span><strong>{{ number_format($trendStats['total_views']) }}</strong> vistas en {{ $trendStats['days'] }} {{ $trendStats['days'] === 1 ? 'día' : 'días' }}</span>
                    <span><strong>{{ number_format($trendStats['avg_views'], 1) }}</strong> promedio/día</span>
                    @if($trendStats['peak_day'])
                        <span>Pico: <strong>{{ number_format($trendStats['peak_views']) }}</strong> el {{ \Carbon\Carbon::parse($trendStats['peak_day'])->format('d/m') }}</span>
                    @endif
                </div>
            @endif
        </div>
        @if($chartData['showTrend'])
            <div id="chTrend" class="mpd-canvas"></div>
            @if($useFallback && $trackingStart)
                <div class="mpd-panel__foot">La línea de tiempo arranca el {{ \Carbon\Carbon::parse($trackingStart)->format('d/m/Y') }} (inicio del tracking diario). Los KPIs de arriba muestran el histórico acumulado completo.</div>
            @endif
        @else
            <div class="mpd-canvas-empty">
                Todavía no hay vistas registradas día a día. La línea de tiempo se empieza a dibujar con el primer tráfico que entre tras activar el tracking.
            </div>
        @endif
    </div>

    {{-- Fila 2: top productos + funnel --}}
    <div class="mpd-charts">
        <div class="mpd-panel">
            <div class="mpd-panel__head"><h5 class="mpd-panel__title">Top 10 productos por vistas</h5></div>
            <div id="chTop" class="mpd-canvas"></div>
            <div class="mpd-canvas-empty" id="chTopEmpty" hidden>Sin vistas en este rango.</div>
        </div>
        <div class="mpd-panel">
            <div class="mpd-panel__head">
                <h5 class="mpd-panel__title">Embudo de conversión</h5>
                <span class="mpd-panel__count">vistas → clicks → leads → pedidos</span>
            </div>
            <div id="chFunnel" class="mpd-canvas"></div>
        </div>
    </div>

    {{-- Fila 3: por tienda + por categoría --}}
    <div class="mpd-charts">
        <div class="mpd-panel">
            <div class="mpd-panel__head"><h5 class="mpd-panel__title">Rendimiento por tienda</h5></div>
            <div id="chTenant" class="mpd-canvas"></div>
            <div class="mpd-canvas-empty" id="chTenantEmpty" hidden>Sin tiendas con vistas en este rango.</div>
        </div>
        <div class="mpd-panel">
            <div class="mpd-panel__head"><h5 class="mpd-panel__title">Vistas por categoría</h5></div>
            <div id="chCat" class="mpd-canvas"></div>
            <div class="mpd-canvas-empty" id="chCatEmpty" hidden>Sin categorías con vistas.</div>
        </div>
    </div>

    {{-- ════════════ PROTAGONISTA: tabla de rendimiento ════════════ --}}
    <div class="mpd-panel mpd-panel--table">
        <div class="mpd-panel__head">
            <h5 class="mpd-panel__title">Rendimiento por producto</h5>
            <span class="mpd-panel__count">{{ number_format($rows->count()) }} productos</span>
        </div>
        <div class="table-responsive">
            <table class="mpd-table">
                <thead>
                    <tr>
                        <th class="mpd-th-num">#</th>
                        <th>Producto</th>
                        <th>Tienda</th>
                        <th class="mpd-th-c">Estado</th>
                        <th class="mpd-th-r"><a href="{{ $sortUrl('views') }}">Vistas {!! $sortMark('views') !!}</a></th>
                        <th class="mpd-th-r"><a href="{{ $sortUrl('clicks') }}">Clicks {!! $sortMark('clicks') !!}</a></th>
                        <th class="mpd-th-r"><a href="{{ $sortUrl('ctr') }}">CTR {!! $sortMark('ctr') !!}</a></th>
                        <th class="mpd-th-r"><a href="{{ $sortUrl('leads') }}">Leads {!! $sortMark('leads') !!}</a></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows->take(200) as $i => $l)
                        <tr>
                            <td class="mpd-td-num">{{ $i + 1 }}</td>
                            <td>
                                <a href="{{ url('/marketplace/item/' . $l->slug) }}" target="_blank" rel="noopener" class="mpd-prod">{{ Str::limit($l->title, 58) }}</a>
                            </td>
                            <td class="mpd-td-tenant">{{ $l->tenant_fqdn }}</td>
                            <td class="mpd-th-c">
                                <span class="mpd-badge mpd-badge--{{ $l->status }}">{{ $statusLabels[$l->status] ?? $l->status }}</span>
                            </td>
                            <td class="mpd-th-r">
                                <div class="mpd-barcell">
                                    <span class="mpd-barcell__num">{{ number_format($l->views) }}</span>
                                    <span class="mpd-barcell__track"><span class="mpd-barcell__fill mpd-fill-views" style="width:{{ max(2, round($l->views / $maxViews * 100)) }}%"></span></span>
                                </div>
                            </td>
                            <td class="mpd-th-r">
                                <div class="mpd-barcell">
                                    <span class="mpd-barcell__num">{{ number_format($l->clicks) }}</span>
                                    <span class="mpd-barcell__track"><span class="mpd-barcell__fill mpd-fill-clicks" style="width:{{ max(2, round($l->clicks / $maxClicks * 100)) }}%"></span></span>
                                </div>
                            </td>
                            <td class="mpd-th-r">
                                <span class="mpd-ctr {{ $l->ctr >= 8 ? 'is-good' : ($l->ctr > 0 && $l->ctr < 2 ? 'is-warn' : '') }}">{{ $l->ctr }}%</span>
                            </td>
                            <td class="mpd-th-r mpd-td-leads">{{ number_format($l->leads) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="mpd-empty">
                            No hay productos que coincidan con los filtros. Probá ampliar el rango de fechas o limpiar la búsqueda.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->count() > 200)
            <div class="mpd-panel__foot">Mostrando los primeros 200 de {{ number_format($rows->count()) }}. Acotá los filtros para ver el resto.</div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
:root {
    --mp-ink: #1e2330; --mp-muted: #6b7280; --mp-faint: #9aa1ad;
    --mp-line: #e7e9ef; --mp-line2: #eef0f4; --mp-surface: #ffffff; --mp-subtle: #f8fafc;
    --mp-accent: #4f46e5; --mp-accent-soft: #eef2ff;
    --mp-good: #15803d; --mp-good-soft: #ecfdf5;
    --mp-warn: #b45309; --mp-warn-soft: #fffbeb;
}
.mpd { color: var(--mp-ink); }
.mpd-h1 { font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
.mpd-breadcrumb { font-size: 12.5px; color: var(--mp-muted); margin-top: 2px; }

/* Filtros */
.mpd-filters { background: var(--mp-surface); border: 1px solid var(--mp-line); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; }
.mpd-filters__dates { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--mp-line2); }
.mpd-filters__row { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
.mpd-field { display: flex; flex-direction: column; gap: 5px; min-width: 130px; }
.mpd-field--grow { flex: 1 1 220px; }
.mpd-field--narrow { min-width: 90px; max-width: 110px; }
.mpd-field label { font-size: 11px; font-weight: 600; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .03em; }
.mpd-field input, .mpd-field select { height: 36px; border: 1px solid var(--mp-line); border-radius: 8px; padding: 0 10px; font-size: 13.5px; color: var(--mp-ink); background: var(--mp-surface); transition: border-color .15s, box-shadow .15s; }
.mpd-field input:focus, .mpd-field select:focus { outline: none; border-color: var(--mp-accent); box-shadow: 0 0 0 3px var(--mp-accent-soft); }
.mpd-presets { display: flex; gap: 6px; margin-left: auto; }
.mpd-chip { font-size: 12.5px; padding: 7px 12px; border-radius: 999px; border: 1px solid var(--mp-line); color: var(--mp-muted); text-decoration: none; transition: all .15s; }
.mpd-chip:hover { border-color: var(--mp-accent); color: var(--mp-accent); }
.mpd-chip.is-active { background: var(--mp-accent); border-color: var(--mp-accent); color: #fff; }
.mpd-actions { display: flex; align-items: center; gap: 4px; margin-left: auto; }

/* Range bar */
.mpd-rangebar { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; font-size: 13px; }
.mpd-rangebar__period { font-weight: 700; color: var(--mp-ink); }
.mpd-rangebar__note { color: var(--mp-muted); }

/* Metric strip */
.mpd-metrics { display: flex; align-items: stretch; gap: 0; background: var(--mp-surface); border: 1px solid var(--mp-line); border-radius: 12px; padding: 4px 0; margin-bottom: 16px; flex-wrap: wrap; }
.mpd-metric { display: flex; flex-direction: column; gap: 2px; padding: 14px 22px; border-right: 1px solid var(--mp-line2); }
.mpd-metric__label { font-size: 11.5px; font-weight: 600; color: var(--mp-muted); text-transform: uppercase; letter-spacing: .03em; }
.mpd-metric__value { font-size: 22px; font-weight: 700; letter-spacing: -.02em; line-height: 1.1; }
.mpd-metric__rate { font-size: 11.5px; color: var(--mp-faint); }
.mpd-metric--primary { padding-left: 24px; }
.mpd-metric--primary .mpd-metric__value { font-size: 30px; color: var(--mp-accent); }
.mpd-metric__context { display: flex; flex-direction: column; justify-content: center; gap: 4px; padding: 14px 22px; margin-left: auto; font-size: 12.5px; color: var(--mp-muted); }
.mpd-metric__context strong { color: var(--mp-ink); }

/* Highlights Top / Rezagado */
.mpd-highlights { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
.mpd-card { display: block; background: var(--mp-surface); border: 1px solid var(--mp-line); border-radius: 12px; padding: 16px 18px; text-decoration: none; color: var(--mp-ink); transition: box-shadow .15s, transform .15s; }
.mpd-card:hover { box-shadow: 0 6px 18px rgba(30,35,48,.08); transform: translateY(-1px); color: var(--mp-ink); }
.mpd-card__head { display: flex; align-items: center; gap: 7px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
.mpd-card--good .mpd-card__head { color: var(--mp-good); }
.mpd-card--warn .mpd-card__head { color: var(--mp-warn); }
.mpd-card--good { border-color: #c7eed4; background: #fbfffc; }
.mpd-card--warn { border-color: #f3e3c2; background: #fffdf6; }
.mpd-card__title { font-size: 15px; font-weight: 650; line-height: 1.25; }
.mpd-card__tenant { font-size: 12.5px; color: var(--mp-muted); margin-top: 2px; }
.mpd-card__stats { display: flex; gap: 16px; margin-top: 10px; font-size: 13px; color: var(--mp-muted); }
.mpd-card__stats strong { color: var(--mp-ink); font-size: 14px; }
.mpd-stat-good strong { color: var(--mp-good); }
.mpd-stat-warn strong { color: var(--mp-warn); }
.mpd-card__hint { margin-top: 10px; font-size: 12px; color: var(--mp-warn); background: var(--mp-warn-soft); border-radius: 7px; padding: 6px 10px; }

/* Panels */
.mpd-panel { background: var(--mp-surface); border: 1px solid var(--mp-line); border-radius: 12px; margin-bottom: 16px; }
.mpd-panel__head { display: flex; align-items: baseline; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--mp-line2); }
.mpd-panel__title { font-size: 14.5px; font-weight: 650; margin: 0; }
.mpd-panel__count { font-size: 12.5px; color: var(--mp-muted); }
.mpd-trendstats { display: flex; gap: 16px; flex-wrap: wrap; font-size: 12.5px; color: var(--mp-muted); }
.mpd-trendstats strong { color: var(--mp-ink); font-weight: 700; }
.mpd-panel__foot { padding: 10px 18px; font-size: 12px; color: var(--mp-muted); text-align: center; border-top: 1px solid var(--mp-line2); }

/* Tabla protagonista */
.mpd-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.mpd-table thead th { position: sticky; top: 0; background: var(--mp-subtle); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--mp-muted); padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--mp-line); white-space: nowrap; }
.mpd-table thead th a { color: var(--mp-muted); text-decoration: none; }
.mpd-table thead th a:hover { color: var(--mp-accent); }
.mp-sort-on { color: var(--mp-accent); font-weight: 700; }
.mpd-th-num { width: 44px; text-align: center !important; }
.mpd-th-c { text-align: center !important; }
.mpd-th-r { text-align: right !important; }
.mpd-table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--mp-line2); vertical-align: middle; }
.mpd-table tbody tr:hover { background: var(--mp-subtle); }
.mpd-table tbody tr:last-child td { border-bottom: none; }
.mpd-td-num { text-align: center; color: var(--mp-faint); font-variant-numeric: tabular-nums; }
.mpd-prod { color: var(--mp-ink); font-weight: 550; text-decoration: none; }
.mpd-prod:hover { color: var(--mp-accent); text-decoration: underline; }
.mpd-td-tenant { color: var(--mp-muted); font-size: 12.5px; }
.mpd-td-leads { font-variant-numeric: tabular-nums; font-weight: 600; }

.mpd-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 999px; }
.mpd-badge--active { background: var(--mp-good-soft); color: var(--mp-good); }
.mpd-badge--paused { background: var(--mp-warn-soft); color: var(--mp-warn); }
.mpd-badge--rejected { background: #fef2f2; color: #b91c1c; }
.mpd-badge--pending_review { background: var(--mp-accent-soft); color: var(--mp-accent); }

/* Mini-barras en celda */
.mpd-barcell { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; min-width: 84px; margin-left: auto; }
.mpd-barcell__num { font-variant-numeric: tabular-nums; font-weight: 600; }
.mpd-barcell__track { width: 100%; height: 4px; background: var(--mp-line2); border-radius: 3px; overflow: hidden; }
.mpd-barcell__fill { display: block; height: 100%; border-radius: 3px; }
.mpd-fill-views { background: var(--mp-accent); }
.mpd-fill-clicks { background: #a5b4fc; }
.mpd-ctr { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--mp-muted); }
.mpd-ctr.is-good { color: var(--mp-good); }
.mpd-ctr.is-warn { color: var(--mp-warn); }
.mpd-empty { text-align: center; color: var(--mp-muted); padding: 40px 16px !important; }

/* Charts */
.mpd-charts { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.mpd-canvas { min-height: 300px; padding: 8px 10px 12px; }
.mpd-canvas-empty { min-height: 200px; display: flex; align-items: center; justify-content: center; text-align: center; color: var(--mp-muted); font-size: 13px; padding: 20px; }

@media (max-width: 991px) {
    .mpd-highlights, .mpd-charts { grid-template-columns: 1fr; }
    .mpd-metric--primary .mpd-metric__value { font-size: 26px; }
    .mpd-metric { padding: 12px 16px; }
    .mpd-presets, .mpd-actions { margin-left: 0; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    var DATA = {!! json_encode($chartData) !!};
    function ready(fn){ document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn(); }
    ready(function () {
        if (typeof ApexCharts === 'undefined') return;
        var base = {
            chart: { background: 'transparent', toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#6b7280' },
            theme: { mode: 'light' },
            grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
            dataLabels: { enabled: false },
            tooltip: { theme: 'light' },
            legend: { labels: { colors: '#6b7280' }, markers: { radius: 3 } },
            noData: { text: 'Sin datos', style: { color: '#9aa1ad' } },
        };
        function deepMerge(a, b){ var o = Object.assign({}, a); for (var k in b){ o[k] = (b[k] && typeof b[k]==='object' && !Array.isArray(b[k])) ? deepMerge(a[k]||{}, b[k]) : b[k]; } return o; }
        function render(id, opts){ var el = document.querySelector(id); if (el) new ApexCharts(el, deepMerge(base, opts)).render(); }
        var sum = function (a){ return (a || []).reduce(function (s, n){ return s + (+n || 0); }, 0); };

        function emptyState(id){ var c = document.querySelector(id); if (c) c.style.display='none'; var e = document.querySelector(id+'Empty'); if (e) e.hidden=false; }

        // Tendencia diaria (área)
        if (DATA.showTrend) {
            render('#chTrend', {
                chart: { type: 'area', height: 300 },
                series: [
                    { name: 'Vistas', data: DATA.trend.views },
                    { name: 'Clicks', data: DATA.trend.clicks },
                ],
                colors: ['#4f46e5', '#94a3b8'],
                xaxis: { categories: DATA.trend.labels, tickAmount: 8, axisBorder: { show: false }, axisTicks: { show: false } },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 95] } },
            });
        }

        // Top 10 productos por vistas (barras horizontales)
        if (sum(DATA.top.views) > 0) {
            render('#chTop', {
                chart: { type: 'bar', height: 340 },
                series: [
                    { name: 'Vistas', data: DATA.top.views },
                    { name: 'Clicks', data: DATA.top.clicks },
                ],
                colors: ['#4f46e5', '#a5b4fc'],
                plotOptions: { bar: { horizontal: true, barHeight: '64%', borderRadius: 3 } },
                xaxis: { categories: DATA.top.labels, axisBorder: { show: false } },
            });
        } else { emptyState('#chTop'); }

        // Embudo de conversión (barras horizontales con %)
        if (sum(DATA.funnel.values) > 0) {
            render('#chFunnel', {
                chart: { type: 'bar', height: 340 },
                series: [{ name: 'Cantidad', data: DATA.funnel.values }],
                colors: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc'],
                plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '58%', borderRadius: 3 } },
                dataLabels: { enabled: true, style: { colors: ['#fff'], fontSize: '11px' }, formatter: function(v){ return Number(v).toLocaleString('es-PE'); } },
                xaxis: { categories: DATA.funnel.labels, axisBorder: { show: false } },
                legend: { show: false },
                tooltip: { y: { formatter: function(v, o){ var r = DATA.funnel.rates[o.dataPointIndex]; return Number(v).toLocaleString('es-PE') + ' (' + r + '% de vistas)'; } } },
            });
        }

        // Rendimiento por tienda (barras horizontales)
        if (sum(DATA.tenant.views) > 0) {
            render('#chTenant', {
                chart: { type: 'bar', height: 300 },
                series: [
                    { name: 'Vistas', data: DATA.tenant.views },
                    { name: 'Clicks', data: DATA.tenant.clicks },
                ],
                colors: ['#4f46e5', '#a5b4fc'],
                plotOptions: { bar: { horizontal: true, barHeight: '62%', borderRadius: 3 } },
                xaxis: { categories: DATA.tenant.labels, axisBorder: { show: false } },
            });
        } else { emptyState('#chTenant'); }

        // Vistas por categoría (donut, paleta índigo secuencial)
        if (sum(DATA.cat.views) > 0) {
            render('#chCat', {
                chart: { type: 'donut', height: 300 },
                series: DATA.cat.views,
                labels: DATA.cat.labels,
                colors: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#ddd6fe', '#e0e7ff'],
                legend: { position: 'bottom', labels: { colors: '#6b7280' } },
                plotOptions: { pie: { donut: { size: '64%', labels: { show: true, total: { show: true, label: 'Vistas', color: '#6b7280' } } } } },
                stroke: { colors: ['#ffffff'], width: 2 },
            });
        } else { emptyState('#chCat'); }
    });
})();
</script>
@endpush
