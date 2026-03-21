@extends('tenant.layouts.app')

@section('title', 'Historial de Despachos')

@section('content')
<div class="container-fluid">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-history text-success me-2"></i>
                Historial de Despachos
            </h4>
            <small class="text-muted">Pedidos ya entregados al cliente o enviados por courier</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-dark btn-sm" id="btnPrintAll" title="Imprimir todas las etiquetas de la página">
                <i class="fas fa-print me-1"></i> Imprimir Etiquetas
            </button>
            <a href="{{ route('logistic.sale_notes.queue') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-stream me-1"></i> Cola de Despacho
            </a>
        </div>
    </div>

    {{-- Contadores --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3 border-top border-3 border-primary">
                <div class="fw-bold fs-3 text-primary">{{ $totals['DESPACHADO'] }}</div>
                <small class="text-muted"><i class="fas fa-truck me-1"></i> Enviados por Courier</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3 border-top border-3 border-success">
                <div class="fw-bold fs-3 text-success">{{ $totals['RECOGIDO'] }}</div>
                <small class="text-muted"><i class="fas fa-store me-1"></i> Recogidos en Tienda</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center py-3 border-top border-3 border-danger">
                <div class="fw-bold fs-3 text-danger">{{ $totals['ANULADO'] }}</div>
                <small class="text-muted"><i class="fas fa-ban me-1"></i> Anulados</small>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('logistic.sale_notes.history') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Buscar</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Cliente, NV, courier, guía…"
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Tipo</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="DESPACHADO" {{ request('status') === 'DESPACHADO' ? 'selected' : '' }}>Courier</option>
                        <option value="RECOGIDO"   {{ request('status') === 'RECOGIDO'   ? 'selected' : '' }}>Recojo en Tienda</option>
                        <option value="ANULADO"    {{ request('status') === 'ANULADO'    ? 'selected' : '' }}>Anulados</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="{{ route('logistic.sale_notes.history') }}" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($saleNotes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    <p>No hay pedidos finalizados con los filtros actuales.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Documento</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Tipo entrega</th>
                                <th class="text-center">Estado</th>
                                <th>Courier / Persona</th>
                                <th>N° Guía</th>
                                <th class="text-center">Fecha Despacho</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($saleNotes as $sn)
                                <tr class="{{ $sn->logistic_status === \App\Enums\LogisticStatusEnum::ANULADO ? 'table-light' : '' }}" style="{{ $sn->logistic_status === \App\Enums\LogisticStatusEnum::ANULADO ? 'opacity:.65;' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            @if($sn->is_urgent)
                                                <span class="badge bg-danger"><i class="fas fa-bolt"></i> URGENTE</span>
                                            @endif
                                            <span class="fw-semibold">
                                                {{ $sn->number_full ?? $sn->series . '-' . str_pad($sn->number, 8, '0', STR_PAD_LEFT) }}
                                            </span>
                                        </div>
                                        @if($sn->delivery_type)
                                            <small>
                                                <span class="badge bg-{{ $sn->delivery_type->badgeColor() }} bg-opacity-75">
                                                    <i class="{{ $sn->delivery_type->icon() }} me-1"></i>{{ $sn->delivery_type->label() }}
                                                </span>
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $sn->customer->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $sn->customer->number ?? '' }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $sn->user?->name ?? '—' }}</small>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ $sn->currency_type_id }} {{ number_format($sn->total, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::RECOGIDO)
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-store me-1"></i>Tienda
                                            </span>
                                        @else
                                            <span class="badge bg-primary">
                                                <i class="fas fa-truck me-1"></i>Courier
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($sn->logistic_status)
                                            <span class="badge bg-{{ $sn->logistic_status->badgeColor() }}">
                                                {{ $sn->logistic_status->label() }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::RECOGIDO)
                                            {{ $sn->pickup_person ?? '—' }}
                                        @else
                                            {{ $sn->courier_name ?? '—' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($sn->tracking_number)
                                            <code>{{ $sn->tracking_number }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($sn->dispatch_date)
                                            {{ \Carbon\Carbon::parse($sn->dispatch_date)->format('d/m/Y H:i') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1 flex-wrap">
                                            {{-- Reimprimir etiqueta --}}
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-label"
                                                    title="Reimprimir etiqueta de despacho"
                                                    data-label-url="{{ route('logistic.sale_notes.label_html', $sn) }}"
                                                    data-label-id="{{ $sn->id }}">
                                                <i class="fas fa-redo me-1"></i> Reimprimir
                                            </button>

                                            {{-- Anular despacho (solo DESPACHADO) --}}
                                            @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::DESPACHADO)
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-danger"
                                                        title="Anular despacho y revertir stock"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalAnnul"
                                                        data-id="{{ $sn->id }}"
                                                        data-number="{{ $sn->number_full ?? $sn->series.'-'.str_pad($sn->number,8,'0',STR_PAD_LEFT) }}"
                                                        data-url="{{ route('logistic.sale_notes.annul', $sn) }}">
                                                    <i class="fas fa-ban me-1"></i> Anular
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="d-flex justify-content-center py-3">
                    {{ $saleNotes->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Modal Anulación --}}
<div class="modal fade" id="modalAnnul" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Anular Despacho</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAnnul" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Esta acción revertirá el stock</strong> físico y comprometido de todos los ítems
                        del pedido <strong id="annulNumber"></strong>. No se puede deshacer.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo de anulación <span class="text-danger">*</span></label>
                        <textarea name="annul_reason" class="form-control" rows="3"
                                  placeholder="Ej: Cliente canceló el pedido, dirección incorrecta…"
                                  required maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban me-1"></i> Confirmar Anulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<style>
@media print {
    /* Ocultar TODO excepto el área de impresión */
    body > *                { display: none !important; }
    #_logisticPrintArea     { display: block !important; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Área de impresión oculta en la misma página ───────────────────────────
    var printArea = document.createElement('div');
    printArea.id = '_logisticPrintArea';
    printArea.style.display = 'none';
    document.body.appendChild(printArea);

    // ── Imprimir HTML parcial via AJAX (sin abrir nueva pantalla) ────────────
    function printPartial(url, btn, origHtml) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.text();
            })
            .then(function(html) {
                printArea.innerHTML = html;
                window.print();
                printArea.innerHTML = '';
                btn.disabled = false;
                btn.innerHTML = origHtml;
            })
            .catch(function(e) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                console.error(e);
            });
    }

    // ── Reimprimir etiqueta individual ───────────────────────────────────────
    document.querySelectorAll('.btn-label').forEach(function (btn) {
        var origHtml = btn.innerHTML;
        btn.addEventListener('click', function () {
            printPartial(this.dataset.labelUrl, this, origHtml);
        });
    });

    // ── Imprimir todas las etiquetas de la página ─────────────────────────────
    var btnAll     = document.getElementById('btnPrintAll');
    var btnAllOrig = btnAll.innerHTML;
    btnAll.addEventListener('click', function () {
        var ids = Array.from(document.querySelectorAll('.btn-label'))
            .map(function (b) { return b.dataset.labelId; })
            .filter(Boolean);
        if (ids.length === 0) { alert('No hay etiquetas para imprimir.'); return; }
        printPartial('{{ route("logistic.sale_notes.labels_batch") }}?ids=' + ids.join(',') + '&partial=1',
            btnAll, btnAllOrig);
    });

    // ── Modal anulación ───────────────────────────────────────────────────────
    document.getElementById('modalAnnul').addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        document.getElementById('annulNumber').textContent = btn.dataset.number;
        document.getElementById('formAnnul').action        = btn.dataset.url;
        document.querySelector('#formAnnul textarea[name="annul_reason"]').value = '';
    });

});
</script>
@endpush
