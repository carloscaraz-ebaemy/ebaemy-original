<div class="row g-3">

    <div class="col-md-6">
        <h6 class="text-muted border-bottom pb-1">Documento</h6>
        <div class="fw-semibold">{{ $saleNote->number_full ?? ($saleNote->series . '-' . str_pad($saleNote->number, 8, '0', STR_PAD_LEFT)) }}</div>
        <div class="text-muted small">{{ \Carbon\Carbon::parse($saleNote->date_of_issue)->format('d/m/Y') }}</div>
    </div>

    <div class="col-md-6">
        <h6 class="text-muted border-bottom pb-1">Estado</h6>
        @if($saleNote->logistic_status)
            <span class="badge bg-{{ $saleNote->logistic_status->badgeColor() }} fs-6">
                {{ $saleNote->logistic_status->label() }}
            </span>
        @endif
    </div>

    <div class="col-md-6">
        <h6 class="text-muted border-bottom pb-1">Cliente</h6>
        <div class="fw-semibold">{{ $saleNote->customer->name ?? '—' }}</div>
        <div class="text-muted small">{{ $saleNote->customer->number ?? '' }}</div>
    </div>

    <div class="col-md-6">
        <h6 class="text-muted border-bottom pb-1">Vendedor</h6>
        <div>{{ $saleNote->user->name ?? '—' }}</div>
    </div>

    <div class="col-12">
        <h6 class="text-muted border-bottom pb-1">Productos</h6>
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">Precio Unit.</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saleNote->items as $item)
                <tr>
                    <td>{{ $item->relation_item->description ?? $item->description }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->unit_price ?? 0, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($item->total ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Total</td>
                    <td class="text-end fw-bold text-success">
                        {{ $saleNote->currency_type_id }} {{ number_format($saleNote->total, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($saleNote->logistic_status === \App\Enums\LogisticStatusEnum::DESPACHADO)
    <div class="col-12">
        <div class="alert alert-success mb-0">
            <h6 class="alert-heading"><i class="fas fa-truck me-1"></i> Datos de Envío</h6>
            <strong>Courier:</strong> {{ $saleNote->courier_name ?? '—' }}<br>
            <strong>Tracking:</strong> {{ $saleNote->tracking_number ?? '—' }}<br>
            <strong>Fecha despacho:</strong> {{ $saleNote->dispatch_date ? \Carbon\Carbon::parse($saleNote->dispatch_date)->format('d/m/Y H:i') : '—' }}
        </div>
    </div>
    @endif

    @if($saleNote->observations)
    <div class="col-12">
        <h6 class="text-muted border-bottom pb-1">Observaciones</h6>
        <p class="text-muted small mb-0">{{ $saleNote->observations }}</p>
    </div>
    @endif

</div>
