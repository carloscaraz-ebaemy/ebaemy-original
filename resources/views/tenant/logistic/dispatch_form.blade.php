@extends('tenant.layouts.app')

@section('title', 'Despacho — ' . ($saleNote->series . '-' . str_pad($saleNote->number, 8, '0', STR_PAD_LEFT)))

@section('content')
<div class="container-fluid">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('logistic.sale_notes.queue') }}" class="text-muted text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i> Cola de Despacho
            </a>
            <h4 class="mb-0 mt-1 d-flex align-items-center gap-2">
                Pedido:
                <span class="text-primary">
                    {{ $saleNote->series }}-{{ str_pad($saleNote->number, 8, '0', STR_PAD_LEFT) }}
                </span>
                @if($saleNote->is_urgent)
                    <span class="badge bg-danger"><i class="fas fa-bolt me-1"></i>URGENTE</span>
                @endif
                @if($saleNote->delivery_type)
                    <span class="badge bg-{{ $saleNote->delivery_type->badgeColor() }}">
                        <i class="{{ $saleNote->delivery_type->icon() }} me-1"></i>
                        {{ $saleNote->delivery_type->label() }}
                    </span>
                @endif
            </h4>
        </div>
        @if($saleNote->logistic_status)
            <span class="badge bg-{{ $saleNote->logistic_status->badgeColor() }} fs-6 px-3 py-2">
                {{ $saleNote->logistic_status->label() }}
            </span>
        @endif
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">

        {{-- Columna izquierda: datos del pedido + ítems --}}
        <div class="col-lg-7">

            {{-- Info del pedido --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light py-2">
                    <strong><i class="fas fa-receipt me-2"></i>Datos del Pedido</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Cliente</small>
                            <strong>{{ $saleNote->customer->name ?? '—' }}</strong>
                            <div class="text-muted small">{{ $saleNote->customer->number ?? '' }}</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted d-block">Fecha</small>
                            {{ \Carbon\Carbon::parse($saleNote->date_of_issue)->format('d/m/Y') }}
                        </div>
                        <div class="col-3 text-end">
                            <small class="text-muted d-block">Total</small>
                            <strong class="text-success">
                                {{ $saleNote->currency_type_id }} {{ number_format($saleNote->total, 2) }}
                            </strong>
                        </div>
                        @if($saleNote->observation)
                            <div class="col-12">
                                <small class="text-muted d-block">Observaciones</small>
                                {{ $saleNote->observation }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Datos de envío registrados por el vendedor --}}
            @if($saleNote->shipping_address || $saleNote->shipping_recipient)
                <div class="card border-primary shadow-sm mb-3">
                    <div class="card-header bg-primary text-white py-2">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Datos de Envío (registrado por el vendedor)</strong>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-1">
                            @if($saleNote->shipping_recipient)
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Destinatario</small>
                                    <strong>{{ $saleNote->shipping_recipient }}</strong>
                                </div>
                            @endif
                            @if($saleNote->shipping_phone)
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Teléfono</small>
                                    {{ $saleNote->shipping_phone }}
                                </div>
                            @endif
                            @if($saleNote->shipping_address)
                                <div class="col-md-8">
                                    <small class="text-muted d-block">Dirección</small>
                                    {{ $saleNote->shipping_address }}
                                    @if($saleNote->shipping_city)
                                        — <span class="text-muted">{{ $saleNote->shipping_city }}</span>
                                    @endif
                                </div>
                            @endif
                            @if($saleNote->preferred_courier)
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Courier preferido</small>
                                    <span class="badge bg-primary">{{ $saleNote->preferred_courier }}</span>
                                </div>
                            @endif
                            @if($saleNote->shipping_notes)
                                <div class="col-12">
                                    <small class="text-muted d-block">Instrucciones</small>
                                    <em class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>{{ $saleNote->shipping_notes }}</em>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabla de ítems con selector multi-almacén --}}
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-list me-2"></i>Productos del Pedido</strong>
                    <small class="text-muted">Selecciona el almacén de origen si es necesario</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th>Almacén Origen</th>
                                    <th class="text-center">Stock Disponible</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($saleNote->items as $item)
                                    <tr class="align-middle" id="row-item-{{ $item->item_id }}">
                                        <td>
                                            <div class="fw-semibold">{{ $item->relation_item->description ?? $item->description }}</div>
                                            <small class="text-muted">{{ $item->relation_item->internal_id ?? '' }}</small>
                                        </td>
                                        <td class="text-center fw-semibold">
                                            {{ number_format($item->quantity, 2) }}
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm warehouse-selector"
                                                    name="warehouse_overrides[{{ $loop->index }}][warehouse_id]"
                                                    data-item-id="{{ $item->item_id }}"
                                                    data-qty="{{ $item->quantity }}"
                                                    onchange="checkStock(this)"
                                                    form="form-action">
                                                <option value="">— Almacén por defecto —</option>
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}">{{ $wh->description }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden"
                                                   name="warehouse_overrides[{{ $loop->index }}][item_id]"
                                                   value="{{ $item->item_id }}"
                                                   form="form-action">
                                        </td>
                                        <td class="text-center" id="stock-{{ $item->item_id }}">
                                            <span class="text-muted small">—</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna derecha: acciones según estado --}}
        <div class="col-lg-5">

            @php $status = $saleNote->logistic_status; @endphp
            @php $delivery = $saleNote->delivery_type; @endphp
            @php $isPickup = $delivery === \App\Enums\DeliveryTypeEnum::PICKUP; @endphp

            {{-- PENDIENTE → Iniciar preparación --}}
            @if($status === \App\Enums\LogisticStatusEnum::PENDIENTE)
                <div class="card border-warning shadow-sm">
                    <div class="card-header bg-warning bg-opacity-25 border-bottom border-warning">
                        <strong class="text-dark"><i class="fas fa-clock me-2 text-warning"></i>Pedido Pendiente</strong>
                    </div>
                    <div class="card-body">
                        @if($isPickup)
                            <p class="text-muted">El cliente <strong>vendrá a recoger</strong> este pedido. Haz clic en <strong>Iniciar Preparación</strong> para preparar los productos.</p>
                        @else
                            <p class="text-muted">El pedido está en espera de despacho por courier. Haz clic en <strong>Iniciar Preparación</strong> para comenzar.</p>
                        @endif
                        <form method="POST" action="{{ route('logistic.sale_notes.process', $saleNote) }}">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark w-100"
                                    onclick="return confirm('¿Iniciar preparación de este pedido?')">
                                <i class="fas fa-box-open me-2"></i> Iniciar Preparación (Picking)
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- PREPARANDO → Marcar listo --}}
            @if($status === \App\Enums\LogisticStatusEnum::PREPARANDO)
                <div class="card border-primary shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <strong><i class="fas fa-box-open me-2"></i>En Preparación</strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Selecciona el almacén de origen de cada producto si difiere del almacén principal, luego marca el pedido como listo.</p>
                        <form method="POST" action="{{ route('logistic.sale_notes.ready', $saleNote) }}" id="form-ready">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-sticky-note me-1 text-muted"></i> Observaciones internas
                                </label>
                                <textarea name="warehouse_notes"
                                          class="form-control"
                                          rows="2"
                                          placeholder="Ej: faltó 1 unidad de producto X, se reemplazó por Y…"
                                          maxlength="500">{{ old('warehouse_notes', $saleNote->warehouse_notes) }}</textarea>
                                <small class="text-muted">Solo visible al equipo de almacén.</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-check me-2"></i>
                                @if($isPickup)
                                    Marcar Listo para Recojo
                                @else
                                    Marcar Listo para Despacho
                                @endif
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- LISTO_DESPACHO → Formulario según tipo --}}
            @if($status === \App\Enums\LogisticStatusEnum::LISTO_DESPACHO)

                @if($isPickup)
                    {{-- RECOJO EN TIENDA --}}
                    <div class="card border-info shadow-sm">
                        <div class="card-header bg-info text-white">
                            <strong><i class="fas fa-hand-holding-box me-2"></i>Confirmar Entrega al Cliente</strong>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info border small mb-3">
                                <i class="fas fa-user me-1"></i>
                                El cliente viene a recoger su pedido. Verifica su comprobante y entrega los productos.
                            </div>
                            <form method="POST"
                                  action="{{ route('logistic.sale_notes.pickup', $saleNote) }}"
                                  id="form-action">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Recibido por (opcional)
                                    </label>
                                    <input type="text"
                                           name="pickup_person"
                                           class="form-control"
                                           placeholder="Nombre de quien recoge"
                                           value="{{ old('pickup_person', $saleNote->customer->name ?? '') }}">
                                    <small class="text-muted">Puede ser el cliente u otra persona autorizada.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Fecha y Hora de Entrega <span class="text-danger">*</span>
                                    </label>
                                    <input type="datetime-local"
                                           name="dispatch_date"
                                           class="form-control"
                                           value="{{ old('dispatch_date', now()->format('Y-m-d\TH:i')) }}"
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-sticky-note me-1 text-muted"></i> Observaciones internas
                                    </label>
                                    <textarea name="warehouse_notes"
                                              class="form-control"
                                              rows="2"
                                              placeholder="Ej: cliente vino con DNI, se entregó al vecino autorizado…"
                                              maxlength="500">{{ old('warehouse_notes', $saleNote->warehouse_notes) }}</textarea>
                                    <small class="text-muted">No aparece en el comprobante del cliente.</small>
                                </div>

                                <button type="submit" class="btn btn-info text-white w-100 mt-1"
                                        onclick="return confirm('¿Confirmar que el cliente recogió su pedido?')">
                                    <i class="fas fa-check-circle me-2"></i> Confirmar Entrega al Cliente
                                </button>
                            </form>
                        </div>
                    </div>

                @else
                    {{-- COURIER / DESPACHO A PROVINCIA --}}
                    <div class="card border-success shadow-sm">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fas fa-truck me-2"></i>Registrar Despacho</strong>
                        </div>
                        <div class="card-body">
                            <form method="POST"
                                  action="{{ route('logistic.sale_notes.dispatch', $saleNote) }}"
                                  id="form-action">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Courier / Transportista <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           name="courier_name"
                                           list="courier-list"
                                           class="form-control @error('courier_name') is-invalid @enderror"
                                           placeholder="Selecciona o escribe el courier…"
                                           value="{{ old('courier_name', $saleNote->preferred_courier) }}"
                                           autocomplete="off"
                                           required>
                                    <datalist id="courier-list">
                                        @foreach($couriers as $courier)
                                            <option value="{{ $courier }}">
                                        @endforeach
                                    </datalist>
                                    @error('courier_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">
                                        Puedes escribir uno nuevo si no aparece en la lista.
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Número de Guía / Tracking</label>
                                    <input type="text"
                                           name="tracking_number"
                                           class="form-control"
                                           placeholder="Ej: OLV-12345678"
                                           value="{{ old('tracking_number') }}">
                                </div>

                                {{-- ── Envío — campos del almacén ────────────────────── --}}
                                <div class="card border-secondary mb-3">
                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                        <strong class="text-secondary">
                                            <i class="fas fa-box me-1"></i> Datos de Envío
                                        </strong>
                                        @if($saleNote->shipping_cost_customer > 0)
                                        <span class="badge bg-info text-white">
                                            Cobrado al cliente: S/ {{ number_format($saleNote->shipping_cost_customer, 2) }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="card-body py-2">

                                        {{-- Fila 1: Bultos + Costo agencia --}}
                                        <div class="row g-2 mb-2">
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1">N° Bultos reales</label>
                                                <input type="number" name="shipping_packages" id="inp_packages"
                                                       class="form-control form-control-sm" min="1" step="1"
                                                       value="{{ old('shipping_packages', $saleNote->shipping_packages ?? 1) }}"
                                                       oninput="calcEnvioTotal()">
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1">
                                                    Costo agencia (S/)
                                                    <small class="text-muted fw-normal">Olva, Shalom…</small>
                                                </label>
                                                <input type="number" name="shipping_cost_agency" id="inp_agency"
                                                       class="form-control form-control-sm" min="0" step="0.50"
                                                       value="{{ old('shipping_cost_agency', $saleNote->shipping_cost_agency ?? 0) }}"
                                                       oninput="calcEnvioTotal()">
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1">¿Quién pagó a la agencia?</label>
                                                <select name="shipping_paid_by" class="form-select form-select-sm">
                                                    <option value="empresa"   {{ old('shipping_paid_by', $saleNote->shipping_paid_by) == 'empresa'  ? 'selected' : '' }}>La empresa</option>
                                                    <option value="tercero"   {{ old('shipping_paid_by', $saleNote->shipping_paid_by) == 'tercero'  ? 'selected' : '' }}>Un tercero</option>
                                                    <option value="cliente"   {{ old('shipping_paid_by', $saleNote->shipping_paid_by) == 'cliente'  ? 'selected' : '' }}>El cliente</option>
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Fila 2: Quién lleva + costo extra --}}
                                        <div class="row g-2 mb-2">
                                            <div class="col-sm-4">
                                                <label class="form-label small fw-semibold mb-1">¿Quién lleva a la agencia?</label>
                                                <select name="shipping_carrier_type" id="sel_carrier_type"
                                                        class="form-select form-select-sm"
                                                        onchange="toggleCarrierCost()">
                                                    <option value="propio"  {{ old('shipping_carrier_type', $saleNote->shipping_carrier_type) == 'propio'  ? 'selected' : '' }}>Empleado propio</option>
                                                    <option value="tercero" {{ old('shipping_carrier_type', $saleNote->shipping_carrier_type) == 'tercero' ? 'selected' : '' }}>Motorizado / Tercero</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-4" id="row_carrier_cost"
                                                 style="{{ old('shipping_carrier_type', $saleNote->shipping_carrier_type) == 'tercero' ? '' : 'display:none;' }}">
                                                <label class="form-label small fw-semibold mb-1">Costo motorizado (S/)</label>
                                                <input type="number" name="shipping_carrier_cost" id="inp_carrier"
                                                       class="form-control form-control-sm" min="0" step="0.50"
                                                       value="{{ old('shipping_carrier_cost', $saleNote->shipping_carrier_cost ?? 0) }}"
                                                       oninput="calcEnvioTotal()">
                                            </div>
                                            <div class="col-sm-4 d-flex flex-column justify-content-end">
                                                <label class="form-label small text-muted mb-1">Costo total empresa</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">S/</span>
                                                    <input type="text" id="lbl_total_cost" class="form-control fw-bold text-danger"
                                                           readonly value="{{ number_format(($saleNote->shipping_cost_agency ?? 0) + ($saleNote->shipping_carrier_cost ?? 0), 2) }}">
                                                </div>
                                                <input type="hidden" name="shipping_cost" id="inp_shipping_cost"
                                                       value="{{ ($saleNote->shipping_cost_agency ?? 0) + ($saleNote->shipping_carrier_cost ?? 0) }}">
                                            </div>
                                        </div>

                                        {{-- Resumen margen --}}
                                        @if($saleNote->shipping_cost_customer > 0)
                                        <div class="alert alert-light border py-1 px-2 mb-0 small" id="div_margen">
                                            <span class="text-muted">Cobrado al cliente:</span>
                                            <strong>S/ {{ number_format($saleNote->shipping_cost_customer, 2) }}</strong>
                                            &nbsp;—&nbsp;
                                            <span class="text-muted">Costo empresa:</span>
                                            <strong id="lbl_costo_empresa">S/ {{ number_format(($saleNote->shipping_cost_agency ?? 0) + ($saleNote->shipping_carrier_cost ?? 0), 2) }}</strong>
                                            &nbsp;=&nbsp;
                                            <span class="text-muted">Margen:</span>
                                            <strong id="lbl_margen" class="text-success">
                                                S/ {{ number_format($saleNote->shipping_cost_customer - ($saleNote->shipping_cost_agency ?? 0) - ($saleNote->shipping_carrier_cost ?? 0), 2) }}
                                            </strong>
                                        </div>
                                        @endif

                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Fecha de Despacho <span class="text-danger">*</span>
                                    </label>
                                    <input type="datetime-local"
                                           name="dispatch_date"
                                           class="form-control @error('dispatch_date') is-invalid @enderror"
                                           value="{{ old('dispatch_date', now()->format('Y-m-d\TH:i')) }}"
                                           required>
                                    @error('dispatch_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-sticky-note me-1 text-muted"></i> Observaciones internas
                                    </label>
                                    <textarea name="warehouse_notes"
                                              class="form-control"
                                              rows="2"
                                              placeholder="Ej: paquete frágil, se envolvió en burbuja, falta ítem X pendiente…"
                                              maxlength="500">{{ old('warehouse_notes', $saleNote->warehouse_notes) }}</textarea>
                                    <small class="text-muted">No aparece en el comprobante del cliente.</small>
                                </div>

                                <div class="alert alert-light border small">
                                    <i class="fas fa-info-circle text-info me-1"></i>
                                    Si seleccionaste almacenes alternativos en la tabla de ítems,
                                    se registrará el descuento de stock correspondiente.
                                </div>

                                <button type="submit" class="btn btn-success w-100 mt-1"
                                        onclick="return confirm('¿Confirmar despacho del pedido?')">
                                    <i class="fas fa-truck me-2"></i> Confirmar Despacho
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Botón guía de remisión --}}
                    <div class="mt-2">
                        <a href="/dispatches/generate/{{ $saleNote->id }}"
                           class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-file-alt me-1"></i> Generar Guía de Remisión
                        </a>
                    </div>
                @endif
            @endif

            {{-- DESPACHADO — vista de solo lectura --}}
            @if($status === \App\Enums\LogisticStatusEnum::DESPACHADO)
                <div class="card border-success shadow-sm">
                    <div class="card-header bg-success text-white">
                        <strong><i class="fas fa-check-circle me-2"></i>Pedido Despachado</strong>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-5">Courier</dt>
                            <dd class="col-7">{{ $saleNote->courier_name }}</dd>

                            <dt class="col-5">Guía / Tracking</dt>
                            <dd class="col-7">{{ $saleNote->tracking_number ?? '—' }}</dd>

                            <dt class="col-5">Fecha Despacho</dt>
                            <dd class="col-7">
                                {{ $saleNote->dispatch_date?->format('d/m/Y H:i') ?? '—' }}
                            </dd>

                            @if($saleNote->warehouse_notes)
                                <dt class="col-5">Observaciones</dt>
                                <dd class="col-7">
                                    <span class="text-muted fst-italic">{{ $saleNote->warehouse_notes }}</span>
                                </dd>
                            @endif
                        </dl>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <a href="{{ route('logistic.sale_notes.label', $saleNote) }}"
                               target="_blank"
                               class="btn btn-outline-dark flex-fill">
                                <i class="fas fa-print me-2"></i> Imprimir Etiqueta
                            </a>
                            <a href="/dispatches/generate/{{ $saleNote->id }}"
                               class="btn btn-outline-success flex-fill">
                                <i class="fas fa-file-alt me-2"></i> Guía de Remisión
                            </a>
                            @if($saleNote->customer->telephone ?? $saleNote->customer->cell ?? false)
                                @php
                                    $phone = preg_replace('/\D/', '', $saleNote->customer->telephone ?? $saleNote->customer->cell ?? '');
                                    $msg = urlencode(
                                        "Hola " . ($saleNote->customer->name ?? '') . ", tu pedido " .
                                        $saleNote->number_full . " fue despachado" .
                                        ($saleNote->courier_name ? " vía " . $saleNote->courier_name : "") .
                                        ($saleNote->tracking_number ? ". Guía: " . $saleNote->tracking_number : "") .
                                        ". Gracias por tu compra."
                                    );
                                @endphp
                                <a href="https://wa.me/51{{ $phone }}?text={{ $msg }}"
                                   target="_blank"
                                   class="btn btn-success flex-fill">
                                    <i class="fab fa-whatsapp me-2"></i> WhatsApp
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- RECOGIDO — vista de solo lectura --}}
            @if($status === \App\Enums\LogisticStatusEnum::RECOGIDO)
                <div class="card border-success shadow-sm">
                    <div class="card-header bg-success text-white">
                        <strong><i class="fas fa-check-circle me-2"></i>Pedido Entregado al Cliente</strong>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-5">Recibido por</dt>
                            <dd class="col-7">{{ $saleNote->pickup_person ?? $saleNote->customer->name ?? '—' }}</dd>

                            <dt class="col-5">Fecha Entrega</dt>
                            <dd class="col-7">
                                {{ $saleNote->dispatch_date?->format('d/m/Y H:i') ?? '—' }}
                            </dd>

                            @if($saleNote->warehouse_notes)
                                <dt class="col-5">Observaciones</dt>
                                <dd class="col-7">
                                    <span class="text-muted fst-italic">{{ $saleNote->warehouse_notes }}</span>
                                </dd>
                            @endif
                        </dl>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('logistic.sale_notes.label', $saleNote) }}"
                               target="_blank"
                               class="btn btn-outline-dark flex-fill">
                                <i class="fas fa-print me-2"></i> Imprimir Etiqueta
                            </a>
                            @if($saleNote->customer->telephone ?? $saleNote->customer->cell ?? false)
                                @php
                                    $phone = preg_replace('/\D/', '', $saleNote->customer->telephone ?? $saleNote->customer->cell ?? '');
                                    $msg = urlencode(
                                        "Hola " . ($saleNote->customer->name ?? '') . ", tu pedido " .
                                        $saleNote->number_full . " fue entregado. Gracias por tu compra."
                                    );
                                @endphp
                                <a href="https://wa.me/51{{ $phone }}?text={{ $msg }}"
                                   target="_blank"
                                   class="btn btn-success flex-fill">
                                    <i class="fab fa-whatsapp me-2"></i> WhatsApp
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.warehouse-selector').forEach(function(sel) {
        if (!sel.value && sel.options.length > 1) {
            sel.selectedIndex = 1;
        }
        if (sel.value) checkStock(sel);
    });
});

async function checkStock(selectEl) {
    const itemId      = selectEl.dataset.itemId;
    const warehouseId = selectEl.value;
    const qty         = parseFloat(selectEl.dataset.qty || 0);
    const cell        = document.getElementById('stock-' + itemId);

    if (!warehouseId) {
        cell.innerHTML = '<span class="text-muted small">—</span>';
        return;
    }

    cell.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary"></span>';

    try {
        const res  = await fetch(`/logistic/sale-notes/stock-by-item/${itemId}`);
        const data = await res.json();

        const wh = data.stocks?.find(s => s.warehouse_id == warehouseId);
        if (!wh) {
            cell.innerHTML = '<span class="text-danger small">Sin datos</span>';
            return;
        }

        const available = wh.stock_available;
        const ok        = available >= qty;
        cell.innerHTML  = `
            <span class="badge bg-${ok ? 'success' : 'danger'}"
                  title="Físico: ${wh.stock_physical} | Comprometido: ${wh.stock_committed}">
                ${available.toFixed(2)} <i class="fas fa-${ok ? 'check' : 'exclamation-triangle'}"></i>
            </span>`;
    } catch (e) {
        cell.innerHTML = '<span class="text-danger small">Error</span>';
        console.error(e);
    }
}

function calcEnvioTotal() {
    const agency  = parseFloat(document.getElementById('inp_agency')?.value)  || 0;
    const carrier = parseFloat(document.getElementById('inp_carrier')?.value) || 0;
    const total   = (agency + carrier).toFixed(2);

    const lbl = document.getElementById('lbl_total_cost');
    const inp = document.getElementById('inp_shipping_cost');
    if (lbl) lbl.value = total;
    if (inp) inp.value = total;

    // Actualizar margen
    const lblCosto  = document.getElementById('lbl_costo_empresa');
    const lblMargen = document.getElementById('lbl_margen');
    if (lblCosto)  lblCosto.textContent  = 'S/ ' + total;
    if (lblMargen) {
        const cliente = parseFloat('{{ $saleNote->shipping_cost_customer ?? 0 }}') || 0;
        const margen  = (cliente - parseFloat(total)).toFixed(2);
        lblMargen.textContent = 'S/ ' + margen;
        lblMargen.className   = parseFloat(margen) >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
    }
}

function toggleCarrierCost() {
    const sel = document.getElementById('sel_carrier_type');
    const row = document.getElementById('row_carrier_cost');
    if (!sel || !row) return;
    row.style.display = sel.value === 'tercero' ? '' : 'none';
    if (sel.value !== 'tercero') {
        const inp = document.getElementById('inp_carrier');
        if (inp) { inp.value = 0; calcEnvioTotal(); }
    }
}
</script>
@endpush
