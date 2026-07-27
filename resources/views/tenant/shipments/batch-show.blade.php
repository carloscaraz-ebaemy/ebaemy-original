@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.shipments.partials.logistics-tokens')
@endpush

@section('content')
<div class="lg-app container-fluid py-3">

    <div class="lg-head">
        <div>
            <h1 class="lg-title">
                📦 {{ $batch->code }}
                <span class="lg-pill lg-pill--{{ $batch->status }}">{{ $batch->status_label }}</span>
            </h1>
            <p class="lg-sub">
                Creado {{ optional($batch->created_at)->format('d/m/Y H:i') }}
                @if($batch->created_by_name) por {{ $batch->created_by_name }} @endif
                @if($batch->manifest_code) · Manifiesto <strong>{{ $batch->manifest_code }}</strong> @endif
            </p>
        </div>
        <div class="lg-actions">
            <a href="{{ route('shipments.batches') }}" class="lg-btn">← Lotes</a>

            @if(!$batch->isPrinted())
                <a href="{{ route('shipments.batches.print', $batch) }}" target="_blank"
                   class="lg-btn lg-btn--primary {{ $batch->shipments->isEmpty() ? 'is-disabled' : '' }}">
                    🖨️ Imprimir lote
                </a>
                <form method="POST" action="{{ route('shipments.batches.discard', $batch) }}"
                      onsubmit="return confirm('¿Descartar el lote? Sus envíos volverán a la cola de impresión.');">
                    @csrf
                    <button class="lg-btn lg-btn--danger" type="submit">Descartar</button>
                </form>
            @else
                <button type="button" class="lg-btn" data-bs-toggle="modal" data-bs-target="#modalReprint">
                    🖨️ Reimprimir
                </button>
                @if($batch->status !== 'closed')
                    <form method="POST" action="{{ route('shipments.batches.close', $batch) }}">
                        @csrf
                        <button class="lg-btn lg-btn--primary" type="submit">Cerrar lote</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($batch->isPrinted())
        <div class="lg-window">
            🔒 Este lote ya fue impreso: sus envíos están <strong>bloqueados</strong>.
            Para cambiar la modalidad de alguno hay que generar un lote nuevo.
        </div>
    @endif

    {{-- ── Datos del lote ────────────────────────────────────── --}}
    <div class="lg-kpis">
        <div class="lg-kpi lg-kpi--brand">
            <div class="lg-kpi__label">Etiquetas</div>
            <div class="lg-kpi__value">{{ $batch->label_count }}</div>
        </div>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Hojas</div>
            <div class="lg-kpi__value">{{ $batch->sheet_count ?: '—' }}</div>
            <div class="lg-kpi__hint">formato {{ strtoupper($batch->format ?: 'A5') }}</div>
        </div>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Paquetes</div>
            <div class="lg-kpi__value">{{ $batch->package_count }}</div>
        </div>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Reimpresiones</div>
            <div class="lg-kpi__value">{{ $batch->reprintCount() }}</div>
        </div>
        <div class="lg-kpi">
            <div class="lg-kpi__label">Impreso</div>
            <div class="lg-kpi__value" style="font-size:.95rem">
                {{ $batch->printed_at ? $batch->printed_at->format('d/m/Y H:i') : 'Pendiente' }}
            </div>
            <div class="lg-kpi__hint">{{ $batch->printed_by_name ?: '—' }}</div>
        </div>
    </div>

    @if($batch->label_range)
        <div class="lg-card">
            <div class="lg-card__body lg-note">
                <strong>Rango de etiquetas:</strong> {{ $batch->label_range }}
                @if(!$batch->generatesManifest())
                    · Este lote no genera manifiesto (recojo en tienda no se despacha).
                @endif
            </div>
        </div>
    @endif

    <div class="row g-3">
        {{-- ── Envíos del lote ───────────────────────────────── --}}
        <div class="col-lg-7">
            <div class="lg-card">
                <div class="lg-card__head">
                    <h2 class="lg-card__title">Envíos del lote ({{ $batch->shipments->count() }})</h2>
                </div>
                <div class="lg-scroll">
                    <table class="lg-table">
                        <thead>
                            <tr><th>Código</th><th>Cliente</th><th>Modalidad</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse($batch->shipments as $s)
                            <tr>
                                <td><strong>{{ $s->shipment_code }}</strong></td>
                                <td>
                                    {{ $s->full_name }}
                                    <div class="lg-note">{{ $s->destination_city ?: $s->shipping_destination }}</div>
                                </td>
                                <td><span class="lg-mod lg-mod--{{ $s->delivery_type }}">{{ $s->delivery_short }}</span></td>
                                <td>{{ $s->status_label }}</td>
                                <td class="text-end">
                                    @if(!$batch->isPrinted())
                                        <form method="POST" action="{{ route('shipments.batch_remove', $s) }}">
                                            @csrf
                                            <button class="lg-btn lg-btn--danger" type="submit">Quitar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="lg-empty">El lote quedó sin envíos.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Impresiones y bitácora ────────────────────────── --}}
        <div class="col-lg-5">
            <div class="lg-card">
                <div class="lg-card__head"><h2 class="lg-card__title">🖨️ Historial de impresiones</h2></div>
                <div class="lg-card__body">
                    <ul class="lg-log">
                        @forelse($events as $event)
                            <li>
                                <span class="lg-log__when">{{ optional($event->created_at)->format('d/m H:i') }}</span>
                                <span class="lg-log__body">
                                    <strong>#{{ $event->sequence }}</strong>
                                    {{ $event->is_reprint ? 'Reimpresión' : 'Impresión original' }}
                                    · {{ $event->label_count }} etiqueta(s)
                                    <div class="lg-log__who">
                                        {{ $event->user_name ?: '—' }}
                                        @if($event->reason) · Motivo: {{ $event->reason }} @endif
                                    </div>
                                </span>
                            </li>
                        @empty
                            <li><div class="lg-empty">El lote todavía no se imprimió.</div></li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="lg-card">
                <div class="lg-card__head"><h2 class="lg-card__title">🧾 Bitácora del lote</h2></div>
                <div class="lg-card__body">
                    <ul class="lg-log">
                        @forelse($logs as $log)
                            <li>
                                <span class="lg-log__when">{{ optional($log->created_at)->format('d/m H:i') }}</span>
                                <span class="lg-log__body">
                                    {{ $log->summary }}
                                    @if($log->is_exception)<span class="lg-log__exc">excepción</span>@endif
                                    <div class="lg-log__who">
                                        {{ $log->user_name }}@if($log->notes) · {{ $log->notes }}@endif
                                    </div>
                                </span>
                            </li>
                        @empty
                            <li><div class="lg-empty">Sin movimientos.</div></li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal de reimpresión: el motivo es obligatorio y queda en el historial. --}}
@if($batch->isPrinted())
<div class="modal fade" id="modalReprint" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="GET" action="{{ route('shipments.batches.print', $batch) }}" target="_blank">
            <div class="modal-header">
                <h5 class="modal-title">Reimprimir {{ $batch->code }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="lg-note">
                    Cada reimpresión queda registrada con usuario, fecha y motivo.
                    El historial anterior no se modifica.
                </p>
                <div class="mb-2">
                    <label class="form-label" for="reprintReason">Motivo de la reimpresión *</label>
                    <input id="reprintReason" name="motivo" class="form-control" required maxlength="255"
                           placeholder="Ej. la impresora cortó mal las etiquetas">
                </div>
                <div>
                    <label class="form-label" for="reprintFormat">Formato</label>
                    <select id="reprintFormat" name="format" class="form-select">
                        <option value="a5" @selected($batch->format === 'a5')>A5</option>
                        <option value="a4" @selected($batch->format === 'a4')>A4</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="lg-btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="lg-btn lg-btn--primary">Reimprimir</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
