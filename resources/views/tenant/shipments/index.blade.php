@extends('tenant.layouts.app')

@push('styles')
<style>
    .sh-metrics { display:grid; grid-template-columns:repeat(auto-fit, minmax(94px,1fr)); gap:7px; margin-bottom:9px; }
    .sh-metric { position:relative; display:flex; align-items:center; gap:8px; text-decoration:none; background:#fff; border:1px solid #e9ecef; border-left:3px solid var(--mc); border-radius:10px; padding:7px 10px; color:#212529; transition:transform .16s, box-shadow .16s, background .16s; box-shadow:0 1px 3px rgba(0,0,0,.05); overflow:hidden; }
    .sh-metric:hover { transform:translateY(-2px); box-shadow:0 8px 16px -8px rgba(0,0,0,.28); border-left-width:5px; }
    .sh-metric:active { transform:scale(.98); }
    /* Estado activo: la tarjeta se rellena con su color (más dinámico) */
    .sh-metric.is-active { background:var(--mc); border-color:var(--mc); box-shadow:0 6px 16px -6px var(--mc); }
    .sh-metric.is-active .m-ic, .sh-metric.is-active .m-v, .sh-metric.is-active .m-l { color:#fff; }
    .sh-metric .m-ic { color:var(--mc); font-size:14px; }
    .sh-metric .m-txt { display:flex; flex-direction:column; }
    .sh-metric .m-v { font-size:19px; font-weight:800; line-height:1; }
    .sh-metric .m-l { font-size:10.5px; color:#6c757d; font-weight:600; line-height:1.15; }
    /* Filtros: más compactos y con feedback dinámico */
    #shipmentsApp .btn-sm { padding-top:.22rem; padding-bottom:.22rem; transition:transform .12s ease, box-shadow .12s ease, background .12s ease; }
    #shipmentsApp .btn-sm:hover { transform:translateY(-1px); box-shadow:0 4px 10px -5px rgba(0,0,0,.3); }
    #shipmentsApp .btn-sm:active { transform:scale(.96); }
    #shipmentsApp .mb-3 { margin-bottom:.55rem !important; }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3 py-3" id="shipmentsApp">

    {{-- Cabecera --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold">📦 Registro y Control de Envíos</h4>
            <small class="text-muted">Tablero de despacho — sube la guía cuando el paquete llegue a la agencia.</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('shipments.settings') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-location-dot me-1"></i> Ubicación tienda
            </a>
            <a href="{{ route('shipments.couriers') }}" class="btn btn-sm text-white" style="background:#7c3aed;">
                <i class="fas fa-motorcycle me-1"></i> Motorizado
                @if(($metrics['courier_active'] ?? 0) > 0)<span class="badge rounded-pill bg-light text-dark ms-1">{{ $metrics['courier_active'] }}</span>@endif
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoEnvio">
                <i class="fas fa-plus me-1"></i> Registrar envío
            </button>
        </div>
    </div>

    {{-- ── Panel de métricas por estado ── --}}
    @php
        $cards = [
            ['k'=>null,         'l'=>'Total',         'c'=>'#4f46e5', 'i'=>'fa-boxes-stacked',  'v'=>$metrics['total']],
            ['k'=>'confirmar',  'l'=>'Por confirmar', 'c'=>'#6b7280', 'i'=>'fa-clipboard-list', 'v'=>$metrics['confirmar']],
            ['k'=>'embalaje',   'l'=>'Por embalar',   'c'=>'#f59e0b', 'i'=>'fa-box-open',       'v'=>$metrics['embalaje']],
            ['k'=>'despacho',   'l'=>'Por despachar', 'c'=>'#0ea5e9', 'i'=>'fa-dolly',          'v'=>$metrics['despacho']],
            ['k'=>'transito',   'l'=>'En tránsito',   'c'=>'#8b5cf6', 'i'=>'fa-truck-fast',     'v'=>$metrics['transito']],
            ['k'=>'entregados', 'l'=>'Entregados',    'c'=>'#16a34a', 'i'=>'fa-circle-check',   'v'=>$metrics['entregados']],
            ['k'=>'cancelados', 'l'=>'Cancelados',    'c'=>'#dc2626', 'i'=>'fa-ban',            'v'=>$metrics['cancelados']],
        ];
        $activeGroup = $group ?? null;
    @endphp
    <div class="sh-metrics">
        @foreach($cards as $c)
            <a href="{{ route('shipments.index', $c['k'] ? ['group'=>$c['k']] : []) }}"
               class="sh-metric {{ ($activeGroup === $c['k']) ? 'is-active' : '' }}" style="--mc:{{ $c['c'] }};">
                <div class="m-ic"><i class="fas {{ $c['i'] }}"></i></div>
                <div class="m-txt">
                    <div class="m-v">{{ $c['v'] }}</div>
                    <div class="m-l">{{ $c['l'] }}</div>
                </div>
            </a>
        @endforeach
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
    {{-- Filtros de guía (los de estado ya están en las tarjetas de arriba; aquí solo
         lo que las tarjetas NO cubren: guía y enviados hoy). "Todos"/"Pendientes" se
         quitaron por ser redundantes con las tarjetas "Total" y las de "por hacer". --}}
    @php
        $pills = [
            'sin-guia'     => ['Sin guía de envío', 'danger'],
            'con-guia'     => ['Con guía de envío', 'success'],
            'enviados-hoy' => ['Enviados hoy', 'info'],
        ];
    @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        {{-- Filtros de guía --}}
        @foreach($pills as $key => [$label, $color])
            <a href="{{ route('shipments.index', ['filter' => $key]) }}"
               class="btn btn-sm {{ $filter === $key ? "btn-$color" : "btn-outline-$color" }} d-flex align-items-center gap-1">
                @if($key === 'sin-guia')<i class="fas fa-triangle-exclamation"></i>@endif
                {{ $label }}
                <span class="badge rounded-pill bg-{{ $filter === $key ? 'light text-dark' : $color }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach

        <span class="vr mx-1 d-none d-md-inline"></span>

        {{-- Filtro por tipo de entrega --}}
        <a href="{{ route('shipments.index', array_filter(['filter'=>$filter,'group'=>$group])) }}"
           class="btn btn-sm {{ empty($type) ? 'btn-dark' : 'btn-outline-dark' }}">Todos los tipos</a>
        <a href="{{ route('shipments.index', array_filter(['filter'=>$filter,'group'=>$group,'type'=>'domicilio'])) }}"
           class="btn btn-sm {{ ($type ?? '')==='domicilio' ? 'text-white' : '' }}" style="{{ ($type ?? '')==='domicilio' ? 'background:#7c3aed;' : 'border:1px solid #7c3aed;color:#7c3aed;' }}">
            🏍️ A domicilio <span class="badge rounded-pill bg-light text-dark">{{ $metrics['domicilio'] ?? 0 }}</span>
        </a>
        <a href="{{ route('shipments.index', array_filter(['filter'=>$filter,'group'=>$group,'type'=>'agencia'])) }}"
           class="btn btn-sm {{ ($type ?? '')==='agencia' ? 'btn-primary' : 'btn-outline-primary' }}">
            📦 Por agencia <span class="badge rounded-pill bg-light text-dark">{{ $metrics['agencia'] ?? 0 }}</span>
        </a>

        @if($filter !== 'todos' || $type || $group)
            <a href="{{ route('shipments.index') }}" class="btn btn-sm btn-link text-muted text-decoration-none p-1">✕ Quitar</a>
        @endif

        {{-- Buscador: empujado a la derecha para llenar el espacio en blanco --}}
        <form method="GET" action="{{ route('shipments.index') }}" class="ms-auto mb-0" style="flex:1 1 220px;max-width:340px;">
            <input type="hidden" name="filter" value="{{ $filter }}">
            @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            @if($group)<input type="hidden" name="group" value="{{ $group }}">@endif
            <div class="input-group input-group-sm">
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar cliente, código, guía…">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                @if($q)<a href="{{ route('shipments.index', ['filter' => $filter]) }}" class="btn btn-outline-secondary">✕</a>@endif
            </div>
        </form>
    </div>

    {{-- Aviso del filtro crítico --}}
    @if($filter === 'sin-guia' && $counts['sin-guia'] > 0)
        <div class="alert alert-warning py-2 d-flex align-items-center gap-2">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Estos paquetes <strong>aún no tienen guía cargada</strong>. Súbela apenas los entregues a la agencia.</span>
        </div>
    @endif

    {{-- Barra de selección (impresión por lote) — fija arriba y bien visible --}}
    <div id="shBulkBar" class="align-items-center justify-content-between py-2 px-3 mb-2"
         style="display:none; position:sticky; top:8px; z-index:30; background:#4f46e5; color:#fff; border-radius:12px; box-shadow:0 10px 24px -8px rgba(79,70,229,.6);">
        <span style="font-weight:600;">✅ <strong id="shSelCount">0</strong> envío(s) seleccionado(s)</span>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light text-primary fw-bold" id="shClearSel">Quitar selección</button>
            <button type="button" class="btn btn-sm btn-light fw-bold" id="shPrintSel" style="color:#4f46e5;">
                <i class="fas fa-print me-1"></i> Imprimir los seleccionados
            </button>
        </div>
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
                            'recibido'            => 'secondary',
                            'confirmado'          => 'info',
                            'preparando'          => 'warning',
                            'asignado_motorizado' => 'info',
                            'en_camino'           => 'primary',
                            'embalando'           => 'warning',
                            'despachado'          => 'primary',
                            'en_agencia'          => 'success',
                            'en_ruta'             => 'success',
                            'entregado'           => 'primary',
                            'anulado'             => 'dark',
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
                        <td>
                            @if($s->is_domicilio)
                                <span class="badge" style="background:#f3e8ff;color:#7c3aed;">🏍️ Domicilio</span>
                                @if($s->distance_km)<div><small class="fw-bold" style="color:#3730a3;">🛵 {{ $s->distance_text ?: ($s->distance_km.' km') }}@if($s->duration_text) · ~{{ $s->duration_text }}@endif</small></div>@endif
                                <div>
                                    <button type="button" class="btn btn-link btn-sm p-0 fw-bold text-success js-edit-price" style="text-decoration:none;font-size:.8rem;"
                                            data-bs-toggle="modal" data-bs-target="#modalPrecio"
                                            data-id="{{ $s->id }}" data-code="{{ $s->shipment_code }}" data-price="{{ $s->delivery_price }}">
                                        💵 {{ $s->delivery_price ? 'S/ '.number_format($s->delivery_price, 2) : 'Poner precio' }}
                                        <i class="fas fa-pen ms-1" style="font-size:.65rem;opacity:.6;"></i>
                                    </button>
                                </div>
                                @if($s->maps_link)
                                    <div class="mt-1"><a href="{{ $s->maps_link }}" target="_blank" class="small text-decoration-none"><i class="fas fa-map-marker-alt me-1"></i>Ver ubicación</a></div>
                                @endif
                                @if($s->courier_name)<div><small class="text-muted"><i class="fas fa-motorcycle me-1"></i>{{ $s->courier_name }}</small></div>@endif
                            @else
                                {{ $s->shipping_agency ?: '—' }}
                            @endif
                        </td>
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
                                    @foreach($s->selectableStatuses() as $val)
                                        <li>
                                            <form method="POST" action="{{ route('shipments.status', $s->id) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $val }}">
                                                <button class="dropdown-item {{ $s->status === $val ? 'active' : '' }}" type="submit">{{ $statuses[$val] ?? $val }}</button>
                                            </form>
                                        </li>
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
                                                data-delivery_type="{{ $s->delivery_type }}"
                                                data-latitude="{{ $s->latitude }}"
                                                data-longitude="{{ $s->longitude }}"
                                                data-formatted_address="{{ $s->formatted_address }}"
                                                data-google_place_id="{{ $s->google_place_id }}"
                                                data-google_maps_url="{{ $s->google_maps_url }}"
                                                data-distance_km="{{ $s->distance_km }}"
                                                data-distance_text="{{ $s->distance_text }}"
                                                data-duration_text="{{ $s->duration_text }}"
                                                data-delivery_price="{{ $s->delivery_price }}"
                                                data-maps_link="{{ $s->maps_link }}"
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

          <input type="hidden" name="delivery_type" id="ed_delivery_type" value="agencia">

          <div class="sh-section"><i class="fas fa-map-marker-alt fa-fw me-1"></i> Destino</div>

          {{-- ── Rama AGENCIA ── --}}
          <div class="row g-3 ed-agencia">
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

          {{-- ── Rama DOMICILIO (motorizado) ── --}}
          <div class="row g-3 ed-domicilio" style="display:none;">
            <div class="col-12">
              <div class="alert alert-light border small mb-0 py-2">
                🏍️ <b>Entrega a domicilio</b> — ubicación fijada por el cliente:
                <div class="fw-semibold" id="ed_coords_display">—</div>
                <a href="#" id="ed_maps_link" target="_blank" class="small text-decoration-none">📍 Ver en Google Maps</a>
                <span id="ed_dist_display" class="text-muted ms-2"></span>
              </div>
            </div>
            <div class="col-md-6"><label class="form-label small mb-1">Costo de envío (S/)</label>
              <input type="number" step="0.10" min="0" name="delivery_price" id="ed_delivery_price" class="form-control" placeholder="0.00"></div>
            {{-- Ocultos preservados (no se pierden al editar) --}}
            <input type="hidden" name="latitude" id="ed_latitude">
            <input type="hidden" name="longitude" id="ed_longitude">
            <input type="hidden" name="formatted_address" id="ed_formatted_address">
            <input type="hidden" name="google_place_id" id="ed_google_place_id">
            <input type="hidden" name="google_maps_url" id="ed_google_maps_url">
            <input type="hidden" name="destination_city" id="ed_destination_city">
            <input type="hidden" name="distance_km" id="ed_distance_km">
            <input type="hidden" name="distance_text" id="ed_distance_text">
            <input type="hidden" name="duration_text" id="ed_duration_text">
          </div>

          {{-- Dirección + Referencia (común a ambos tipos) --}}
          <div class="row g-3 mt-0">
            <div class="col-md-6"><label class="form-label small mb-1">Dirección</label>
              <input type="text" name="shipping_destination" id="ed_shipping_destination" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Referencia</label>
              <input type="text" name="reference" id="ed_reference" class="form-control"></div>
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

{{-- Modal: editar precio del envío a domicilio --}}
<div class="modal fade" id="modalPrecio" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="formPrecio">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">💵 Precio de envío</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-muted small mb-2">Envío <span id="pr_code" class="fw-semibold"></span></div>
          <label class="form-label small text-muted mb-1">Precio a cobrar (S/)</label>
          <div class="input-group">
            <span class="input-group-text">S/</span>
            <input type="number" step="0.10" min="0" name="delivery_price" id="pr_price" class="form-control" placeholder="0.00" autofocus>
          </div>
          <div class="form-text">Déjalo vacío para quitar el precio.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar</button>
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
    try { initDropdowns(); } catch (e) { /* no romper el resto del script si Popper/Bootstrap falla */ }

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
        var get = function (k) { return btn.getAttribute('data-' + k) || ''; };
        ['full_name','dni','phone','shipping_destination','reference','shipping_agency','package_content','package_count','weight','notes',
         'delivery_price','latitude','longitude','formatted_address','google_place_id','google_maps_url','destination_city','distance_km','distance_text','duration_text'].forEach(function (f) {
            var el = document.getElementById('ed_' + f);
            if (el) el.value = get(f);
        });
        var code = document.getElementById('edCode');
        if (code) code.textContent = get('shipment_code');

        // Tipo de entrega: alternar la rama agencia/domicilio del modal.
        var type = get('delivery_type') === 'domicilio' ? 'domicilio' : 'agencia';
        var isDom = type === 'domicilio';
        document.getElementById('ed_delivery_type').value = type;
        var bAg = document.querySelector('#modalEditar .ed-agencia');
        var bDom = document.querySelector('#modalEditar .ed-domicilio');
        if (bAg) bAg.style.display = isDom ? 'none' : '';
        if (bDom) bDom.style.display = isDom ? '' : 'none';
        // Desactivar los inputs de la rama oculta para que NO se envíen.
        if (bAg) bAg.querySelectorAll('input,select').forEach(function (el) { el.disabled = isDom; });
        if (bDom) bDom.querySelectorAll('input').forEach(function (el) { el.disabled = !isDom; });

        if (isDom) {
            var lat = get('latitude'), lng = get('longitude');
            var cd = document.getElementById('ed_coords_display');
            if (cd) cd.textContent = get('formatted_address') || ((lat && lng) ? (parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)) : '—');
            var ml = document.getElementById('ed_maps_link');
            if (ml) { var link = get('maps_link'); if (link) { ml.href = link; ml.style.display = ''; } else ml.style.display = 'none'; }
            var dd = document.getElementById('ed_dist_display');
            if (dd) dd.textContent = get('distance_text') ? ('🛵 ' + get('distance_text')) : '';
        } else {
            // Precargar el ubigeo (dep → prov → dist) del envío.
            if (window.__ubPreset) window.__ubPreset('ed', get('department_id'), get('province_id'), get('district_id'));
            if (window.__syncAgency) window.__syncAgency();
        }
    });

    // Modal precio: precargar el precio actual y apuntar el form al envío.
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-edit-price');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var form = document.getElementById('formPrecio');
        if (form) form.setAttribute('action', '{{ url("registro-envio") }}/' + id + '/precio');
        var pr = document.getElementById('pr_price');
        if (pr) pr.value = btn.getAttribute('data-price') || '';
        var code = document.getElementById('pr_code');
        if (code) code.textContent = btn.getAttribute('data-code') || '';
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

{{-- Impresión por lote. IMPORTANTE: el ERP monta Vue en #main-wrapper (envuelve
     este panel) y re-renderiza el DOM, así que NO se pueden capturar referencias
     a nodos ni enlazar listeners directos (se pierden). Se usa SOLO delegación en
     `document` + re-consulta fresca de los elementos en cada evento. --}}
<script>
(function () {
    function $checks() { return Array.prototype.slice.call(document.querySelectorAll('.sh-check')); }
    function $checked() { return $checks().filter(function (c) { return c.checked; }); }
    function refresh() {
        var n = $checked().length;
        var countEl = document.getElementById('shSelCount');
        var bar = document.getElementById('shBulkBar');
        if (countEl) countEl.textContent = n;
        if (bar) bar.style.display = n > 0 ? 'flex' : 'none';
    }

    // Cambios en los checkboxes (delegado — sobrevive al re-render de Vue).
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t) return;
        if (t.id === 'shCheckAll') {
            var on = t.checked;
            $checks().forEach(function (c) { c.checked = on; });
            refresh();
        } else if (t.classList && t.classList.contains('sh-check')) {
            refresh();
        }
    });

    // Clicks en los botones de la barra (delegado).
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest ? ev.target : null;
        var print = ev.target.closest && ev.target.closest('#shPrintSel');
        var clear = ev.target.closest && ev.target.closest('#shClearSel');
        if (print) {
            ev.preventDefault();
            var ids = $checked().map(function (c) { return c.value; });
            if (ids.length) window.open('{{ route("shipments.print_batch") }}?ids=' + ids.join(','), '_blank');
        } else if (clear) {
            ev.preventDefault();
            $checks().forEach(function (c) { c.checked = false; });
            var ca = document.getElementById('shCheckAll'); if (ca) ca.checked = false;
            refresh();
        }
    });

    // Estado inicial + reintento tras el montaje de Vue (que re-renderiza el DOM).
    refresh();
    setTimeout(refresh, 400);
    setTimeout(refresh, 1200);
})();
</script>
@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')
@endpush
