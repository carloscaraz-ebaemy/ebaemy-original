@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.shipments.partials.logistics-tokens')
@endpush

@php
    $mk = fn ($params) => route('shipments.index', $params);
@endphp

@section('content')
<div class="lg-app container-fluid py-3">

    <div class="lg-head">
        <div>
            <h1 class="lg-title">📊 Tablero logístico</h1>
            <p class="lg-sub">Estado de la operación: revisión, impresión, lotes y modalidades de entrega.</p>
        </div>
        <div class="lg-actions">
            <a href="{{ route('shipments.index') }}" class="lg-btn">Registro de Envíos</a>
            <a href="{{ route('shipments.batches') }}" class="lg-btn lg-btn--primary">Lotes de impresión</a>
        </div>
    </div>

    {{-- Ventana de corte operativo --}}
    <div class="lg-window">
        @if($window['cutoff'])
            🕒 <strong>Corte operativo {{ $window['cutoff'] }}</strong> ·
            El lote vigente toma lo registrado desde
            {{ $window['from']->format('d/m H:i') }} hasta {{ $window['to']->format('d/m H:i') }}.
            @if($metrics['waiting_next'] > 0)
                <span>· <strong>{{ $metrics['waiting_next'] }}</strong> pedido(s) quedaron para el lote siguiente.</span>
            @endif
        @else
            🕒 Sin hora de corte configurada: el lote toma todo el día calendario.
            <a href="{{ route('shipments.settings') }}">Configurar corte</a>
        @endif
    </div>

    {{-- ── Operación ─────────────────────────────────────────── --}}
    <div class="lg-kpis">
        <a class="lg-kpi lg-kpi--brand" href="{{ $mk(['filter' => 'pendientes']) }}">
            <div class="lg-kpi__label">Pendientes de revisión</div>
            <div class="lg-kpi__value">{{ $metrics['pending_review'] }}</div>
            <div class="lg-kpi__hint">esperan validación</div>
        </a>
        <a class="lg-kpi" href="{{ $mk(['filter' => 'listos-imprimir']) }}">
            <div class="lg-kpi__label">Pendientes de impresión</div>
            <div class="lg-kpi__value">{{ $metrics['pending_print'] }}</div>
            <div class="lg-kpi__hint">dentro del corte vigente</div>
        </a>
        <a class="lg-kpi" href="{{ route('shipments.batches', ['estado' => 'open']) }}">
            <div class="lg-kpi__label">Lotes pendientes</div>
            <div class="lg-kpi__value">{{ $metrics['batches_open'] }}</div>
            <div class="lg-kpi__hint">abiertos, sin imprimir</div>
        </a>
        <a class="lg-kpi" href="{{ route('shipments.batches') }}">
            <div class="lg-kpi__label">Lotes impresos hoy</div>
            <div class="lg-kpi__value">{{ $metrics['batches_today'] }}</div>
        </a>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Reimpresiones</div>
            <div class="lg-kpi__value">{{ $metrics['reprints'] }}</div>
            <div class="lg-kpi__hint">histórico acumulado</div>
        </div>
        <a class="lg-kpi lg-kpi--alert" href="{{ $mk(['pri' => 'vencidos']) }}">
            <div class="lg-kpi__label">Pedidos atrasados</div>
            <div class="lg-kpi__value">{{ $metrics['overdue'] }}</div>
            <div class="lg-kpi__hint">fuera del plazo</div>
        </a>
    </div>

    {{-- ── Por modalidad ─────────────────────────────────────── --}}
    <div class="lg-kpis">
        <a class="lg-kpi lg-kpi--lima" href="{{ $mk(['filter' => 'lima']) }}">
            <div class="lg-kpi__label">🟠 Lima / Callao</div>
            <div class="lg-kpi__value">{{ $metrics['lima'] }}</div>
            <div class="lg-kpi__hint">Prioridad 1 · mismo día</div>
        </a>
        <a class="lg-kpi lg-kpi--tienda" href="{{ $mk(['filter' => 'recojo']) }}">
            <div class="lg-kpi__label">🟢 Recojo en tienda</div>
            <div class="lg-kpi__value">{{ $metrics['tienda'] }}</div>
            <div class="lg-kpi__hint">Prioridad 2 · avisar al cliente</div>
        </a>
        <a class="lg-kpi lg-kpi--prov" href="{{ $mk(['filter' => 'provincias']) }}">
            <div class="lg-kpi__label">🔵 Provincias</div>
            <div class="lg-kpi__value">{{ $metrics['provincia'] }}</div>
            <div class="lg-kpi__hint">Prioridad 3 · plazo operativo</div>
        </a>
        <a class="lg-kpi" href="{{ $mk(['filter' => 'anulados']) }}">
            <div class="lg-kpi__label">Anulados</div>
            <div class="lg-kpi__value">{{ $metrics['cancelled'] }}</div>
            <div class="lg-kpi__hint">se conservan en el historial</div>
        </a>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Restaurados</div>
            <div class="lg-kpi__value">{{ $metrics['restored'] }}</div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ── Últimos lotes ─────────────────────────────────── --}}
        <div class="col-lg-6">
            <div class="lg-card">
                <div class="lg-card__head">
                    <h2 class="lg-card__title">📦 Últimos lotes</h2>
                    <a href="{{ route('shipments.batches') }}" class="lg-btn">Ver todos</a>
                </div>
                <div class="lg-scroll">
                    <table class="lg-table">
                        <thead>
                            <tr>
                                <th>Lote</th><th>Estado</th><th class="text-end">Etiquetas</th>
                                <th>Impreso</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($recentBatches as $batch)
                            <tr>
                                <td>
                                    <strong>{{ $batch->code }}</strong>
                                    @if($batch->manifest_code)
                                        <div class="lg-note">{{ $batch->manifest_code }}</div>
                                    @endif
                                </td>
                                <td><span class="lg-pill lg-pill--{{ $batch->status }}">{{ $batch->status_label }}</span></td>
                                <td class="text-end">{{ $batch->label_count }}</td>
                                <td>{{ $batch->printed_at ? $batch->printed_at->format('d/m H:i') : '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('shipments.batches.show', $batch) }}" class="lg-btn">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="lg-empty">Todavía no se generó ningún lote.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Bitácora ──────────────────────────────────────── --}}
        <div class="col-lg-6">
            @if($exceptions->count())
                <div class="lg-card">
                    <div class="lg-card__head"><h2 class="lg-card__title">⚠️ Excepciones registradas</h2></div>
                    <div class="lg-card__body">
                        <ul class="lg-log">
                            @foreach($exceptions as $log)
                                <li>
                                    <span class="lg-log__when">{{ optional($log->created_at)->format('d/m H:i') }}</span>
                                    <span class="lg-log__body">
                                        {{ $log->summary }}
                                        <span class="lg-log__exc">excepción</span>
                                        <div class="lg-log__who">{{ $log->user_name }}@if($log->notes) · {{ $log->notes }}@endif</div>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="lg-card">
                <div class="lg-card__head"><h2 class="lg-card__title">🧾 Últimos movimientos</h2></div>
                <div class="lg-card__body">
                    <ul class="lg-log">
                        @forelse($recentLogs as $log)
                            <li>
                                <span class="lg-log__when">{{ optional($log->created_at)->format('d/m H:i') }}</span>
                                <span class="lg-log__body">
                                    {{ $log->summary }}
                                    @if($log->is_exception)<span class="lg-log__exc">excepción</span>@endif
                                    <div class="lg-log__who">
                                        {{ $log->user_name }}
                                        @if($log->notes) · {{ $log->notes }} @endif
                                    </div>
                                </span>
                            </li>
                        @empty
                            <li><div class="lg-empty">Sin movimientos registrados todavía.</div></li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
