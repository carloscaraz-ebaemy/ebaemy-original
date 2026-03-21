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

@push('scripts')
<script>
// Toggle activo/inactivo sin recargar — actualiza el hidden input y hace submit
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
</script>
@endpush
