@extends('tenant.layouts.app')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3" id="shipmentsApp">

    {{-- Cabecera --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold">📦 Registro y Control de Envíos</h4>
            <small class="text-muted">Tablero de despacho — sube la guía cuando el paquete llegue a la agencia.</small>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoEnvio">
            <i class="fas fa-plus me-1"></i> Registrar envío
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filtros rápidos --}}
    @php
        $pills = [
            'todos'        => ['Todos', 'secondary'],
            'sin-guia'     => ['Sin guía de envío', 'danger'],
            'con-guia'     => ['Con guía de envío', 'success'],
            'pendientes'   => ['Pendientes', 'warning'],
            'enviados-hoy' => ['Enviados hoy', 'info'],
        ];
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($pills as $key => [$label, $color])
            <a href="{{ route('shipments.index', ['filter' => $key]) }}"
               class="btn btn-sm {{ $filter === $key ? "btn-$color" : "btn-outline-$color" }} d-flex align-items-center gap-1">
                @if($key === 'sin-guia')<i class="fas fa-triangle-exclamation"></i>@endif
                {{ $label }}
                <span class="badge rounded-pill bg-{{ $filter === $key ? 'light text-dark' : $color }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    {{-- Aviso del filtro crítico --}}
    @if($filter === 'sin-guia' && $counts['sin-guia'] > 0)
        <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Estos paquetes <strong>aún no tienen guía cargada</strong>. Súbela apenas los entregues a la agencia.</span>
        </div>
    @endif

    {{-- Buscador --}}
    <form method="GET" action="{{ route('shipments.index') }}" class="mb-3">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="input-group input-group-sm" style="max-width:420px;">
            <input type="text" name="q" value="{{ $q }}" class="form-control"
                   placeholder="Buscar por cliente, código, guía, ciudad o agencia…">
            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            @if($q)<a href="{{ route('shipments.index', ['filter' => $filter]) }}" class="btn btn-outline-secondary">✕</a>@endif
        </div>
    </form>

    {{-- Barra de selección (impresión por lote) --}}
    <div id="shBulkBar" class="alert alert-primary d-none align-items-center justify-content-between py-2 mb-2">
        <span><strong id="shSelCount">0</strong> seleccionados</span>
        <button type="button" class="btn btn-sm btn-primary" id="shPrintSel">
            <i class="fas fa-print me-1"></i> Imprimir rótulos seleccionados
        </button>
    </div>

    {{-- Tabla (scroll horizontal en móvil) --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width:860px;">
                <thead class="table-light">
                    <tr>
                        <th style="width:34px;"><input type="checkbox" class="form-check-input" id="shCheckAll" title="Seleccionar todos"></th>
                        <th>Envío</th>
                        <th>Cliente</th>
                        <th>Ciudad</th>
                        <th>Agencia</th>
                        <th>Guía</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($shipments as $s)
                    @php
                        $badge = [
                            'recibido'   => 'secondary',
                            'confirmado' => 'info',
                            'preparando' => 'warning',
                            'embalando'  => 'warning',
                            'despachado' => 'primary',
                            'en_agencia' => 'success',
                            'en_ruta'    => 'success',
                            'entregado'  => 'primary',
                            'anulado'    => 'dark',
                            'pendiente'  => 'secondary', 'listo' => 'warning', 'enviado' => 'success',
                        ][$s->status] ?? 'secondary';
                    @endphp
                    <tr class="{{ $s->is_cancelled ? 'text-muted' : '' }}" style="{{ $s->is_cancelled ? 'opacity:.7' : '' }}">
                        <td><input type="checkbox" class="form-check-input sh-check" value="{{ $s->id }}"></td>
                        <td><span class="fw-semibold">{{ $s->shipment_code }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $s->full_name }}</div>
                            <small class="text-muted">{{ $s->phone }}</small>
                        </td>
                        <td>{{ $s->destination_city ?: '—' }}</td>
                        <td>{{ $s->shipping_agency ?: '—' }}</td>
                        <td>
                            @if($s->has_guide)
                                <a href="{{ route('shipments.guide', $s->id) }}" target="_blank" class="badge bg-success text-decoration-none">
                                    <i class="fas fa-paperclip"></i> Adjunta
                                </a>
                                @if($s->tracking_number)<div><small class="text-muted">{{ $s->tracking_number }}</small></div>@endif
                            @else
                                <span class="badge bg-danger-subtle text-danger">Sin guía</span>
                            @endif
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-{{ $badge }} dropdown-toggle py-0" type="button" data-bs-toggle="dropdown" {{ $s->is_cancelled ? 'disabled' : '' }}>
                                    {{ $s->status_label }}
                                </button>
                                <ul class="dropdown-menu shadow-sm">
                                    @foreach($statuses as $val => $lbl)
                                        @if($val !== 'anulado')
                                        <li>
                                            <form method="POST" action="{{ route('shipments.status', $s->id) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $val }}">
                                                <button class="dropdown-item {{ $s->status === $val ? 'active' : '' }}" type="submit">{{ $lbl }}</button>
                                            </form>
                                        </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group">
                                @if($s->has_guide)
                                    <a href="{{ route('shipments.guide', $s->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-eye me-1"></i> Ver guía
                                    </a>
                                @elseif(!$s->is_cancelled)
                                    <button type="button" class="btn btn-sm btn-primary js-upload-guide"
                                            data-bs-toggle="modal" data-bs-target="#modalSubirGuia"
                                            data-id="{{ $s->id }}" data-cliente="{{ $s->full_name }}"
                                            data-agencia="{{ $s->shipping_agency }}" data-ciudad="{{ $s->destination_city }}">
                                        <i class="fas fa-upload me-1"></i> Subir guía
                                    </button>
                                @endif
                                <a href="{{ route('shipments.print', $s->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-print me-1"></i> Imprimir
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Más acciones"></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <button type="button" class="dropdown-item js-edit-shipment"
                                                data-bs-toggle="modal" data-bs-target="#modalEditar"
                                                data-id="{{ $s->id }}"
                                                data-full_name="{{ $s->full_name }}"
                                                data-dni="{{ $s->dni }}"
                                                data-phone="{{ $s->phone }}"
                                                data-shipping_destination="{{ $s->shipping_destination }}"
                                                data-reference="{{ $s->reference }}"
                                                data-destination_city="{{ $s->destination_city }}"
                                                data-shipping_agency="{{ $s->shipping_agency }}"
                                                data-package_content="{{ $s->package_content }}"
                                                data-package_count="{{ $s->package_count }}"
                                                data-weight="{{ $s->weight }}"
                                                data-notes="{{ $s->notes }}"
                                                data-department_id="{{ $s->department_id }}"
                                                data-province_id="{{ $s->province_id }}"
                                                data-district_id="{{ $s->district_id }}"
                                                data-shipment_code="{{ $s->shipment_code }}">
                                            <i class="fas fa-pen fa-fw me-2"></i> Editar
                                        </button>
                                    </li>
                                    @if(!$s->is_cancelled)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('shipments.cancel', $s->id) }}"
                                              onsubmit="return confirm('¿Anular el envío {{ $s->shipment_code }}? Podrás reactivarlo cambiando su estado.');">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fas fa-ban fa-fw me-2"></i> Anular
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                            No hay envíos {{ $filter !== 'todos' ? 'con este filtro' : 'registrados todavía' }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $shipments->links() }}</div>

</div>

{{-- ══════════════ Modal: Subir guía de envío ══════════════ --}}
<div class="modal fade" id="modalSubirGuia" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="#" enctype="multipart/form-data" id="formSubirGuia">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">📤 Subir guía de envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Envío de <strong id="sgCliente">—</strong> · <span id="sgDestino">—</span></p>

          <div class="mb-3">
            <label class="form-label fw-semibold">Número de guía</label>
            <input type="text" name="tracking_number" class="form-control" placeholder="Ejemplo: SH-458712" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Seleccionar archivo</label>
            <label id="sgDrop" class="d-block text-center p-4 border border-2 border-dashed rounded"
                   style="cursor:pointer;border-style:dashed !important;background:#f8f9fa;">
              <i class="fas fa-cloud-arrow-up fa-2x text-muted mb-2 d-block"></i>
              <span id="sgFileText">Arrastra la imagen aquí<br>o haz clic para seleccionar</span>
              <div class="small text-muted mt-1">JPG, PNG o PDF · máx 8&nbsp;MB</div>
              <input type="file" name="guide_file" id="sgFile" accept="image/jpeg,image/png,application/pdf" class="d-none" required>
            </label>
          </div>

          <div class="mb-1">
            <label class="form-label fw-semibold">Observación (opcional)</label>
            <input type="text" name="observation" class="form-control" placeholder="Entregado en agencia Shalom - Talara">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar guía</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ══════════════ Modal: Registrar envío (manual) ══════════════ --}}
<div class="modal fade" id="modalNuevoEnvio" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('shipments.store') }}">
        @csrf
        <div class="modal-header bg-light">
          <h5 class="modal-title"><i class="fas fa-dolly text-primary me-2"></i>Registrar envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="sh-section"><i class="fas fa-user fa-fw me-1"></i> Destinatario</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small mb-1">DNI / RUC <span class="text-muted">(autocompleta)</span></label>
              <input type="text" name="dni" id="nv_dni" class="form-control js-doc-lookup"
                     data-target-name="nv_full_name" data-target-address="nv_shipping_destination" data-ubigeo-group="nv"
                     inputmode="numeric" maxlength="11" autocomplete="off" placeholder="8 dígitos (DNI) u 11 (RUC)">
              <small class="js-doc-status d-block mt-1"></small>
            </div>
            <div class="col-md-6"><label class="form-label small mb-1">Teléfono (celular) *</label>
              <input type="text" name="phone" class="form-control js-phone-pe" required maxlength="9" inputmode="numeric" placeholder="999 999 999">
              <small class="js-phone-err text-danger" style="font-size:12px;"></small></div>
            <div class="col-12"><label class="form-label small mb-1">Nombre completo *</label>
              <input type="text" name="full_name" id="nv_full_name" class="form-control" required></div>
          </div>

          <div class="sh-section"><i class="fas fa-map-marker-alt fa-fw me-1"></i> Destino</div>
          <div class="row g-3">
            <div class="col-12"><label class="form-label small mb-1">Ubigeo (Departamento / Provincia / Distrito) *</label>
              <div class="ubigeo-field" data-ubigeo-group="nv">
                <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                <input type="hidden" name="department_id" data-ub="department">
                <input type="hidden" name="province_id"   data-ub="province">
                <input type="hidden" name="district_id"   data-ub="district">
                <div class="ubigeo-pop" hidden>
                  <div class="ubigeo-col" data-col="dep"></div>
                  <div class="ubigeo-col" data-col="prov"></div>
                  <div class="ubigeo-col" data-col="dist"></div>
                </div>
              </div></div>
            <div class="col-md-6"><label class="form-label small mb-1">Dirección</label>
              <input type="text" name="shipping_destination" id="nv_shipping_destination" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Referencia</label>
              <input type="text" name="reference" id="nv_reference" class="form-control" placeholder="Frente a…, cerca de…"></div>
            <div class="col-12"><label class="form-label small mb-1">Agencia</label>
              <div class="agency-field">
                <select class="form-select agency-select">
                  <option value="">— Selecciona —</option>
                  @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                  <option value="__otra__">Otra…</option>
                </select>
                <input type="text" name="shipping_agency" class="form-control agency-input mt-2" placeholder="Nombre de la agencia" style="display:none;">
              </div></div>
          </div>

          <div class="sh-section"><i class="fas fa-box fa-fw me-1"></i> Paquete</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small mb-1">Contenido del paquete</label>
              <input type="text" name="package_content" class="form-control" placeholder="Ej: 2 mantas, 1 juego de ollas"></div>
            <div class="col-md-3"><label class="form-label small mb-1">N° de bultos</label>
              <input type="number" name="package_count" class="form-control" value="1" min="1" max="9999"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Peso (kg)</label>
              <input type="number" name="weight" class="form-control" step="0.01" min="0" placeholder="0"></div>
            <div class="col-12"><label class="form-label small mb-1">Información adicional</label>
              <input type="text" name="notes" class="form-control" placeholder="Referencia, indicaciones…"></div>
          </div>

        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ══════════════ Modal: Editar envío ══════════════ --}}
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" action="#" id="formEditar">
        @csrf
        <div class="modal-header bg-light">
          <h5 class="modal-title"><i class="fas fa-pen text-primary me-2"></i>Editar envío <span id="edCode" class="text-muted small ms-1"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <div class="sh-section"><i class="fas fa-user fa-fw me-1"></i> Destinatario</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small mb-1">DNI / RUC <span class="text-muted">(autocompleta)</span></label>
              <input type="text" name="dni" id="ed_dni" class="form-control js-doc-lookup"
                     data-target-name="ed_full_name" data-target-address="ed_shipping_destination" data-ubigeo-group="ed"
                     inputmode="numeric" maxlength="11" autocomplete="off">
              <small class="js-doc-status d-block mt-1"></small></div>
            <div class="col-md-6"><label class="form-label small mb-1">Teléfono (celular) *</label>
              <input type="text" name="phone" id="ed_phone" class="form-control js-phone-pe" required maxlength="9" inputmode="numeric" placeholder="999 999 999">
              <small class="js-phone-err text-danger" style="font-size:12px;"></small></div>
            <div class="col-12"><label class="form-label small mb-1">Nombre completo *</label>
              <input type="text" name="full_name" id="ed_full_name" class="form-control" required></div>
          </div>

          <div class="sh-section"><i class="fas fa-map-marker-alt fa-fw me-1"></i> Destino</div>
          <div class="row g-3">
            <div class="col-12"><label class="form-label small mb-1">Ubigeo (Departamento / Provincia / Distrito) *</label>
              <div class="ubigeo-field" data-ubigeo-group="ed">
                <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                <input type="hidden" name="department_id" data-ub="department">
                <input type="hidden" name="province_id"   data-ub="province">
                <input type="hidden" name="district_id"   data-ub="district">
                <div class="ubigeo-pop" hidden>
                  <div class="ubigeo-col" data-col="dep"></div>
                  <div class="ubigeo-col" data-col="prov"></div>
                  <div class="ubigeo-col" data-col="dist"></div>
                </div>
              </div></div>
            <div class="col-md-6"><label class="form-label small mb-1">Dirección</label>
              <input type="text" name="shipping_destination" id="ed_shipping_destination" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Referencia</label>
              <input type="text" name="reference" id="ed_reference" class="form-control"></div>
            <div class="col-12"><label class="form-label small mb-1">Agencia</label>
              <div class="agency-field">
                <select class="form-select agency-select">
                  <option value="">— Selecciona —</option>
                  @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                  <option value="__otra__">Otra…</option>
                </select>
                <input type="text" name="shipping_agency" id="ed_shipping_agency" class="form-control agency-input mt-2" style="display:none;">
              </div></div>
          </div>

          <div class="sh-section"><i class="fas fa-box fa-fw me-1"></i> Paquete</div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label small mb-1">Contenido del paquete</label>
              <input type="text" name="package_content" id="ed_package_content" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small mb-1">N° de bultos</label>
              <input type="number" name="package_count" id="ed_package_count" class="form-control" min="1" max="9999"></div>
            <div class="col-md-3"><label class="form-label small mb-1">Peso (kg)</label>
              <input type="number" name="weight" id="ed_weight" class="form-control" step="0.01" min="0"></div>
            <div class="col-12"><label class="form-label small mb-1">Información adicional</label>
              <input type="text" name="notes" id="ed_notes" class="form-control"></div>
          </div>

        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Fix: los dropdowns dentro de .table-responsive se recortaban por el
    // overflow del contenedor. Con Popper strategy:'fixed' el menú se posiciona
    // respecto al viewport y flota por encima de la tabla sin recortarse.
    function initDropdowns() {
        if (!(window.bootstrap && bootstrap.Dropdown)) return;
        document.querySelectorAll('#shipmentsApp [data-bs-toggle="dropdown"]').forEach(function (el) {
            bootstrap.Dropdown.getOrCreateInstance(el, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                }
            });
        });
    }
    initDropdowns();

    // ── Selección para impresión por lote ──
    (function () {
        var checkAll = document.getElementById('shCheckAll');
        var bar = document.getElementById('shBulkBar');
        var countEl = document.getElementById('shSelCount');
        var printBtn = document.getElementById('shPrintSel');
        function checks() { return Array.prototype.slice.call(document.querySelectorAll('.sh-check')); }
        function refresh() {
            var sel = checks().filter(function (c) { return c.checked; });
            if (countEl) countEl.textContent = sel.length;
            if (bar) { bar.classList.toggle('d-none', sel.length === 0); bar.classList.toggle('d-flex', sel.length > 0); }
        }
        if (checkAll) checkAll.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = checkAll.checked; }); refresh();
        });
        document.addEventListener('change', function (ev) {
            if (ev.target && ev.target.classList && ev.target.classList.contains('sh-check')) refresh();
        });
        if (printBtn) printBtn.addEventListener('click', function () {
            var ids = checks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            if (ids.length) window.open('{{ route("shipments.print_batch") }}?ids=' + ids.join(','), '_blank');
        });
    })();

    // El widget cascader de ubigeo se define en el partial incluido abajo,
    // que expone window.__ubPreset(group, dep, prov, dist).

    // Modal subir guía: precargar action + datos por delegación de clic.
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-upload-guide');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formSubirGuia');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/subir-guia');
        var cli = document.getElementById('sgCliente');
        if (cli) cli.textContent = btn.getAttribute('data-cliente') || '—';
        var ciudad = btn.getAttribute('data-ciudad') || '';
        var ag = btn.getAttribute('data-agencia') || '';
        var dest = document.getElementById('sgDestino');
        if (dest) dest.textContent = [ag, ciudad].filter(Boolean).join(' · ') || '—';
    });

    // Modal editar: precargar los campos leyendo los data-* del botón clicado.
    // Delegación de clic (robusta: no depende de relatedTarget del evento modal).
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-edit-shipment');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formEditar');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/editar');
        ['full_name','dni','phone','shipping_destination','reference','shipping_agency','package_content','package_count','weight','notes'].forEach(function (f) {
            var el = document.getElementById('ed_' + f);
            if (el) el.value = btn.getAttribute('data-' + f) || '';
        });
        var code = document.getElementById('edCode');
        if (code) code.textContent = btn.getAttribute('data-shipment_code') || '';
        // Precargar el ubigeo (dep → prov → dist) del envío.
        if (window.__ubPreset) window.__ubPreset('ed', btn.getAttribute('data-department_id'), btn.getAttribute('data-province_id'), btn.getAttribute('data-district_id'));
        // Sincronizar el desplegable de agencia con el valor cargado.
        if (window.__syncAgency) window.__syncAgency();
    });

    // Autocompletar por documento: 8 dígitos → DNI (RENIEC), 11 → RUC (SUNAT).
    var docTimer = null;
    document.addEventListener('input', function (ev) {
        var inp = ev.target;
        if (!inp.classList || !inp.classList.contains('js-doc-lookup')) return;

        var status = inp.parentElement.querySelector('.js-doc-status');
        var num = (inp.value || '').replace(/\D+/g, '');
        clearTimeout(docTimer);

        if (num.length !== 8 && num.length !== 11) {
            if (status) status.textContent = '';
            return;
        }

        var kind = num.length === 8 ? 'dni' : 'ruc';
        if (status) { status.className = 'text-muted js-doc-status'; status.textContent = 'Consultando ' + kind.toUpperCase() + '…'; }

        docTimer = setTimeout(function () {
            fetch('{{ url("registro-envio/consulta") }}/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false || !res.data) {
                        if (status) { status.className = 'text-danger js-doc-status'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; }
                        return;
                    }
                    var d = res.data;
                    var full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                    var nameEl = document.getElementById(inp.getAttribute('data-target-name'));
                    if (nameEl && full) nameEl.value = full;
                    // RUC: si trae dirección y el destino está vacío, precargarla.
                    var addrId = inp.getAttribute('data-target-address');
                    if (addrId && d.address) {
                        var addrEl = document.getElementById(addrId);
                        if (addrEl && !addrEl.value) addrEl.value = d.address;
                    }
                    // Ubigeo devuelto por RENIEC/SUNAT → precargar la cascada.
                    var loc = d.location_id;
                    var uDep  = (loc && loc[0]) || d.department_id || '';
                    var uProv = (loc && loc[1]) || d.province_id || '';
                    var uDist = (loc && loc[2]) || d.district_id || '';
                    var grp = inp.getAttribute('data-ubigeo-group');
                    if (grp && (uDep || uDist) && window.__ubPreset) window.__ubPreset(grp, uDep, uProv, uDist);
                    if (status) { status.className = 'text-success js-doc-status'; status.textContent = '✓ ' + (full || 'encontrado'); }
                })
                .catch(function () {
                    if (status) { status.className = 'text-danger js-doc-status'; status.textContent = 'No se pudo consultar.'; }
                });
        }, 450);
    });

    // Input de archivo: mostrar nombre + drag&drop.
    var fileInput = document.getElementById('sgFile');
    var drop = document.getElementById('sgDrop');
    var txt = document.getElementById('sgFileText');
    if (fileInput && drop) {
        fileInput.addEventListener('change', function () {
            txt.innerHTML = fileInput.files.length ? '📎 ' + fileInput.files[0].name : 'Arrastra la imagen aquí<br>o haz clic para seleccionar';
        });
        ['dragover','dragenter'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.style.background = '#e9f5ff'; });
        });
        ['dragleave','drop'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.style.background = '#f8f9fa'; });
        });
        drop.addEventListener('drop', function (ev) {
            if (ev.dataTransfer.files.length) {
                fileInput.files = ev.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }
})();
</script>
@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')
@endpush
