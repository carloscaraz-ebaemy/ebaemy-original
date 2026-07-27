@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.shipments.partials.logistics-tokens')
@endpush

@section('content')
<div class="lg-app container-fluid py-3">

    <div class="lg-head">
        <div>
            <h1 class="lg-title">📦 Lotes de impresión</h1>
            <p class="lg-sub">
                Cada impresión genera un lote. Una vez impreso, sus envíos quedan bloqueados
                y no se mezclan con pedidos nuevos.
            </p>
        </div>
        <div class="lg-actions">
            <a href="{{ route('shipments.dashboard') }}" class="lg-btn">Tablero</a>
            <a href="{{ route('shipments.index') }}" class="lg-btn">Registro de Envíos</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="lg-window">
        @if($window['cutoff'])
            🕒 <strong>Corte {{ $window['cutoff'] }}</strong> ·
            ventana vigente {{ $window['from']->format('d/m H:i') }} → {{ $window['to']->format('d/m H:i') }}
        @else
            🕒 Sin hora de corte: la ventana es el día calendario.
            <a href="{{ route('shipments.settings') }}">Configurar</a>
        @endif
    </div>

    {{-- ── Cola lista para el lote del día ───────────────────── --}}
    <div class="lg-card">
        <div class="lg-card__head">
            <h2 class="lg-card__title">Listos para imprimir ({{ $ready->count() }})</h2>
            @if($ready->count())
                <form method="POST" action="{{ route('shipments.batches.store') }}" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="hidden" name="ids" value="{{ $ready->pluck('id')->implode(',') }}">
                    <select name="format" class="form-select form-select-sm" style="width:auto">
                        <option value="a5">A5</option>
                        <option value="a4">A4</option>
                    </select>
                    <button class="lg-btn lg-btn--primary" type="submit">
                        Generar lote con {{ $ready->count() }} envío(s)
                    </button>
                </form>
            @endif
        </div>

        <div class="lg-scroll">
            <table class="lg-table">
                <thead>
                    <tr><th>Código</th><th>Cliente</th><th>Modalidad</th><th>Prioridad</th><th>Registrado</th></tr>
                </thead>
                <tbody>
                @forelse($ready as $s)
                    <tr>
                        <td><strong>{{ $s->shipment_code }}</strong></td>
                        <td>{{ $s->full_name }}</td>
                        <td><span class="lg-mod lg-mod--{{ $s->delivery_type }}">{{ $s->delivery_short }}</span></td>
                        <td>{{ $s->priority_label }}</td>
                        <td>{{ optional($s->created_at)->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="lg-empty">No hay envíos esperando rótulo en esta ventana.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($waiting->count())
        <div class="lg-card">
            <div class="lg-card__head">
                <h2 class="lg-card__title">Después del corte — van al lote siguiente ({{ $waiting->count() }})</h2>
            </div>
            @php
                // Precalculado: un @if inline dentro de la misma línea que un
                // {{ }} rompe el compilador de Blade (ver feedback_blade_compile_validation).
                $waitCodes = $waiting->take(12)->pluck('shipment_code')->implode(', ');
                $waitMore  = $waiting->count() > 12 ? ' y ' . ($waiting->count() - 12) . ' más' : '';
            @endphp
            <div class="lg-card__body lg-note">
                Se registraron pasado el corte de las {{ $window['cutoff'] }}, así que quedan
                pendientes para el próximo lote aunque sea el mismo día:
                {{ $waitCodes }}{{ $waitMore }}.
            </div>
        </div>
    @endif

    {{-- ── Historial de lotes ────────────────────────────────── --}}
    <div class="lg-card">
        <div class="lg-card__head">
            <h2 class="lg-card__title">Historial de lotes</h2>
            <form method="GET" action="{{ route('shipments.batches') }}">
                <select name="estado" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    @foreach(\App\Models\Tenant\ShippingPrintBatch::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="lg-scroll">
            <table class="lg-table">
                <thead>
                    <tr>
                        <th>Lote</th><th>Manifiesto</th><th>Estado</th><th>Modalidad</th>
                        <th class="text-end">Etiquetas</th><th class="text-end">Hojas</th>
                        <th>Impreso</th><th>Usuario</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>
                            <strong>{{ $batch->code }}</strong>
                            <div class="lg-note">{{ optional($batch->created_at)->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>{{ $batch->manifest_code ?: '—' }}</td>
                        <td><span class="lg-pill lg-pill--{{ $batch->status }}">{{ $batch->status_label }}</span></td>
                        <td>
                            @if($batch->delivery_type)
                                <span class="lg-mod lg-mod--{{ $batch->delivery_type }}">
                                    {{ \App\Models\Tenant\ShippingRequest::DELIVERY_SHORT[$batch->delivery_type] ?? $batch->delivery_type }}
                                </span>
                            @else
                                <span class="lg-pill">Mixto</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $batch->shipments_count }}</td>
                        <td class="text-end">{{ $batch->sheet_count ?: '—' }}</td>
                        <td>{{ $batch->printed_at ? $batch->printed_at->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ $batch->printed_by_name ?: $batch->created_by_name ?: '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('shipments.batches.show', $batch) }}" class="lg-btn">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="lg-empty">
                                Aún no hay lotes. Genera el primero desde la cola de arriba.
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $batches->links() }}
</div>
@endsection
