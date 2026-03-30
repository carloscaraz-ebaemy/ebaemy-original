@extends('tenant.layouts.app')

@section('title', 'Agencias de Transporte / Couriers')

@section('content')
<div class="container-fluid" style="max-width:700px">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-truck text-primary me-2"></i>
                Agencias de Transporte / Couriers
            </h4>
            <small class="text-muted">Aparecen como opciones al registrar un despacho</small>
        </div>
        <a href="{{ route('logistic.sale_notes.queue') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Cola de Despacho
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Formulario agregar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white py-2">
            <strong><i class="fas fa-plus me-2"></i>Agregar Courier</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('logistic.couriers.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col">
                    <input type="text"
                           name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="Nombre del courier o agencia…"
                           value="{{ old('name') }}"
                           maxlength="120"
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Lista --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($couriers->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-truck fa-3x mb-3 d-block"></i>
                    <p>No hay couriers configurados.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">API</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($couriers as $courier)
                            <tr>
                                <td>
                                    <form method="POST"
                                          action="{{ route('logistic.couriers.update', $courier) }}"
                                          class="d-flex align-items-center gap-2"
                                          id="form-{{ $courier->id }}">
                                        @csrf @method('PUT')
                                        <input type="text"
                                               name="name"
                                               class="form-control form-control-sm"
                                               value="{{ $courier->name }}"
                                               maxlength="120"
                                               required>
                                        <input type="hidden" name="is_active" value="{{ $courier->is_active ? 1 : 0 }}">
                                        {{-- API hidden fields (populated by modal) --}}
                                        <input type="hidden" name="api_driver"   id="hf-driver-{{ $courier->id }}"   value="{{ $courier->api_driver ?? 'manual' }}">
                                        <input type="hidden" name="api_key"      id="hf-key-{{ $courier->id }}"      value="">
                                        <input type="hidden" name="api_secret"   id="hf-secret-{{ $courier->id }}"   value="">
                                        <input type="hidden" name="api_endpoint" id="hf-endpoint-{{ $courier->id }}" value="{{ $courier->api_endpoint ?? '' }}">
                                        <input type="hidden" name="api_sandbox"  id="hf-sandbox-{{ $courier->id }}"  value="{{ $courier->api_sandbox ? 1 : 0 }}">
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-flex align-items-center gap-2">
                                        <input class="form-check-input toggle-active"
                                               type="checkbox"
                                               data-id="{{ $courier->id }}"
                                               data-name="{{ $courier->name }}"
                                               {{ $courier->is_active ? 'checked' : '' }}>
                                        <span class="badge {{ $courier->is_active ? 'bg-success' : 'bg-secondary' }} toggle-label"
                                              style="font-size:.7rem">
                                            {{ $courier->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if(($courier->api_driver ?? 'manual') !== 'manual')
                                        <span class="badge bg-primary" style="font-size:.7rem">
                                            {{ strtoupper($courier->api_driver) }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                    <button type="button"
                                            class="btn btn-xs btn-outline-secondary ms-1 btn-api-config"
                                            data-id="{{ $courier->id }}"
                                            data-driver="{{ $courier->api_driver ?? 'manual' }}"
                                            data-endpoint="{{ $courier->api_endpoint ?? '' }}"
                                            data-sandbox="{{ $courier->api_sandbox ? '1' : '0' }}"
                                            title="Configurar API">
                                        <i class="fas fa-plug"></i>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="submit"
                                                form="form-{{ $courier->id }}"
                                                class="btn btn-xs btn-outline-primary"
                                                title="Guardar cambios">
                                            <i class="fas fa-save me-1"></i> Guardar
                                        </button>
                                        <form method="POST"
                                              action="{{ route('logistic.couriers.destroy', $courier) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('¿Eliminar {{ $courier->name }}?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>
@endsection

{{-- Modal configuración API --}}
<div class="modal fade" id="apiConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plug me-2"></i>Integración API — Courier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Driver / Proveedor</label>
                    <select class="form-select" id="modal-driver">
                        <option value="manual">Manual (sin integración)</option>
                        <option value="chazki">Chazki</option>
                        <option value="nueveminutos">99Minutos</option>
                    </select>
                </div>
                <div id="api-fields" style="display:none">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Endpoint base</label>
                        <input type="url" class="form-control" id="modal-endpoint"
                               placeholder="https://api.chazki.com/v1">
                        <div class="form-text">URL base de la API del carrier (sin barra final)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">API Key</label>
                        <input type="password" class="form-control" id="modal-key"
                               placeholder="Dejar vacío para no cambiar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">API Secret</label>
                        <input type="password" class="form-control" id="modal-secret"
                               placeholder="Dejar vacío para no cambiar">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="modal-sandbox">
                        <label class="form-check-label" for="modal-sandbox">Modo sandbox (pruebas)</label>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <strong>API Key / Secret</strong> se almacenan cifrados. Déjalos vacíos si no quieres cambiarlos.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="modal-save">
                    <i class="fas fa-save me-1"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Toggle activo/inactivo sin recargar
document.querySelectorAll('.toggle-active').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var id    = this.dataset.id;
        var form  = document.getElementById('form-' + id);
        var label = this.closest('.form-switch').querySelector('.toggle-label');
        var active = this.checked;

        form.querySelector('input[name="is_active"]').value = active ? 1 : 0;

        if (label) {
            label.textContent = active ? 'Activo' : 'Inactivo';
            label.className   = 'badge toggle-label ' + (active ? 'bg-success' : 'bg-secondary');
        }

        form.submit();
    });
});

// API config modal
(function () {
    var currentId  = null;
    var driverEl   = document.getElementById('modal-driver');
    var apiFields  = document.getElementById('api-fields');

    driverEl.addEventListener('change', function () {
        apiFields.style.display = this.value === 'manual' ? 'none' : 'block';
    });

    document.querySelectorAll('.btn-api-config').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentId = this.dataset.id;
            driverEl.value                                   = this.dataset.driver  || 'manual';
            document.getElementById('modal-endpoint').value = this.dataset.endpoint || '';
            document.getElementById('modal-sandbox').checked = this.dataset.sandbox === '1';
            document.getElementById('modal-key').value    = '';
            document.getElementById('modal-secret').value = '';
            apiFields.style.display = driverEl.value === 'manual' ? 'none' : 'block';

            var modal = new bootstrap.Modal(document.getElementById('apiConfigModal'));
            modal.show();
        });
    });

    document.getElementById('modal-save').addEventListener('click', function () {
        if (!currentId) return;

        document.getElementById('hf-driver-'   + currentId).value = driverEl.value;
        document.getElementById('hf-endpoint-' + currentId).value = document.getElementById('modal-endpoint').value;
        document.getElementById('hf-sandbox-'  + currentId).value = document.getElementById('modal-sandbox').checked ? '1' : '0';
        document.getElementById('hf-key-'      + currentId).value = document.getElementById('modal-key').value;
        document.getElementById('hf-secret-'   + currentId).value = document.getElementById('modal-secret').value;

        bootstrap.Modal.getInstance(document.getElementById('apiConfigModal')).hide();
    });
})();
</script>
@endpush
