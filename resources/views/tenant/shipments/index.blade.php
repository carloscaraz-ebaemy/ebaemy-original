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

    {{-- Tabla (scroll horizontal en móvil) --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width:820px;">
                <thead class="table-light">
                    <tr>
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
                            'pendiente'  => 'secondary',
                            'preparando' => 'warning',
                            'listo'      => 'info',
                            'enviado'    => 'success',
                            'entregado'  => 'primary',
                        ][$s->status] ?? 'secondary';
                    @endphp
                    <tr>
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
                                <button class="btn btn-sm btn-{{ $badge }} dropdown-toggle py-0" data-bs-toggle="dropdown">
                                    {{ $s->status_label }}
                                </button>
                                <ul class="dropdown-menu">
                                    @foreach($statuses as $val => $lbl)
                                        <li>
                                            <form method="POST" action="{{ route('shipments.status', $s->id) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $val }}">
                                                <button class="dropdown-item {{ $s->status === $val ? 'active' : '' }}" type="submit">{{ $lbl }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            @if(!$s->has_guide)
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal" data-bs-target="#modalSubirGuia"
                                        data-id="{{ $s->id }}" data-cliente="{{ $s->full_name }}"
                                        data-agencia="{{ $s->shipping_agency }}" data-ciudad="{{ $s->destination_city }}">
                                    <i class="fas fa-upload me-1"></i> Subir guía
                                </button>
                            @else
                                <a href="{{ route('shipments.guide', $s->id) }}" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-eye me-1"></i> Ver guía
                                </a>
                            @endif
                            <a href="{{ route('shipments.print', $s->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print me-1"></i> Imprimir
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="{{ route('shipments.store') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Registrar envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12"><label class="form-label small mb-0">Nombre completo *</label>
              <input type="text" name="full_name" class="form-control" required></div>
            <div class="col-6"><label class="form-label small mb-0">DNI</label>
              <input type="text" name="dni" class="form-control"></div>
            <div class="col-6"><label class="form-label small mb-0">Teléfono *</label>
              <input type="text" name="phone" class="form-control" required></div>
            <div class="col-12"><label class="form-label small mb-0">Destino (dirección)</label>
              <input type="text" name="shipping_destination" class="form-control"></div>
            <div class="col-6"><label class="form-label small mb-0">Ciudad *</label>
              <input type="text" name="destination_city" class="form-control" required></div>
            <div class="col-6"><label class="form-label small mb-0">Agencia</label>
              <input type="text" name="shipping_agency" class="form-control" placeholder="Shalom, Olva…"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Modal subir guía: fijar action y datos del envío al abrir.
    var modal = document.getElementById('modalSubirGuia');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) return;
            var id = btn.getAttribute('data-id');
            document.getElementById('formSubirGuia').setAttribute('action', '{{ url("registro-envio") }}/' + id + '/subir-guia');
            document.getElementById('sgCliente').textContent = btn.getAttribute('data-cliente') || '—';
            var ciudad = btn.getAttribute('data-ciudad') || '';
            var ag = btn.getAttribute('data-agencia') || '';
            document.getElementById('sgDestino').textContent = [ag, ciudad].filter(Boolean).join(' · ') || '—';
        });
    }

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
@endpush
