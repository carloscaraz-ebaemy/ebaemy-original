@extends('tenant.layouts.app')

@section('title', 'Nueva Devolución')

@section('content')
<div class="container-fluid" id="app">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-undo-alt mr-2 text-warning"></i> Nueva Devolución</h4>
            <small class="text-muted">Registra la devolución de un pedido despachado</small>
        </div>
        <a href="{{ route('logistic.returns.index') }}" class="btn btn-light">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
    </div>

    <form method="POST" action="{{ route('logistic.returns.store') }}">
        @csrf
        <div class="row">

            {{-- Columna izquierda --}}
            <div class="col-md-8">

                {{-- Buscar pedido --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><strong>1. Buscar pedido original</strong></div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" id="search_order" class="form-control"
                                placeholder="Ingresa N° de documento (NV-00001) o tracking del courier...">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-info" onclick="searchOrder()">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                        <div id="order_info" class="mt-2" style="display:none;">
                            <div class="alert alert-info py-2 mb-0">
                                <strong id="order_number"></strong> —
                                <span id="order_customer"></span> —
                                Estado: <span id="order_status"></span>
                            </div>
                        </div>
                        <input type="hidden" name="sale_note_id" id="sale_note_id">
                    </div>
                </div>

                {{-- Ítems a devolver --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <strong>2. Productos a devolver</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addManualItem()">
                            <i class="fas fa-plus"></i> Agregar manualmente
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0" id="items_table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Producto</th>
                                    <th width="90">Cantidad</th>
                                    <th width="100">Condición</th>
                                    <th width="90">Precio</th>
                                    <th width="40"></th>
                                </tr>
                            </thead>
                            <tbody id="items_body">
                                <tr id="empty_row">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        Busca un pedido para cargar los productos automáticamente.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- Columna derecha --}}
            <div class="col-md-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><strong>3. Datos de la devolución</strong></div>
                    <div class="card-body">

                        <div class="form-group">
                            <label>Almacén de recepción <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-control form-control-sm" required>
                                <option value="">Seleccionar...</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Motivo <span class="text-danger">*</span></label>
                            <select name="reason" class="form-control form-control-sm" required>
                                <option value="">Seleccionar...</option>
                                @foreach($reasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Courier que devuelve</label>
                            <input type="text" name="courier_name" class="form-control form-control-sm"
                                placeholder="Olva, Shalom, etc.">
                        </div>

                        <div class="form-group">
                            <label>N° de guía de retorno</label>
                            <input type="text" name="tracking_number" class="form-control form-control-sm">
                        </div>

                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="3"
                                placeholder="Estado del empaque, detalles del problema..."></textarea>
                        </div>

                    </div>
                </div>

                <button type="submit" class="btn btn-warning btn-block">
                    <i class="fas fa-save mr-1"></i> Registrar Devolución
                </button>
            </div>
        </div>
    </form>

</div>

<script>
let itemIndex = 0;

function searchOrder() {
    const q = document.getElementById('search_order').value.trim();
    if (!q) return;

    fetch(`{{ route('logistic.returns.search') }}?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            document.getElementById('sale_note_id').value = data.id;
            document.getElementById('order_number').textContent   = data.number_full;
            document.getElementById('order_customer').textContent = data.customer;
            document.getElementById('order_status').textContent   = data.status;
            document.getElementById('order_info').style.display   = 'block';

            // Cargar items
            const tbody = document.getElementById('items_body');
            tbody.innerHTML = '';
            data.items.forEach(item => addItemRow(item));
        })
        .catch(() => alert('Error al buscar el pedido.'));
}

function addItemRow(item = {}) {
    const tbody = document.getElementById('items_body');
    const i     = itemIndex++;
    const row   = document.createElement('tr');
    row.innerHTML = `
        <td>
            <input type="hidden" name="items[${i}][item_id]" value="${item.item_id || ''}">
            <input type="text" class="form-control form-control-sm"
                value="${item.description || ''}" placeholder="Producto..."
                onchange="this.previousElementSibling.value=''" readonly style="background:#f8f9fa;">
        </td>
        <td>
            <input type="number" name="items[${i}][quantity]" value="${item.quantity || 1}"
                class="form-control form-control-sm" step="0.01" min="0.01" required>
        </td>
        <td>
            <select name="items[${i}][condition]" class="form-control form-control-sm" required>
                <option value="BUENO">Buen estado</option>
                <option value="PARCIAL">Parcial</option>
                <option value="DANADO">Dañado</option>
            </select>
        </td>
        <td>
            <input type="number" name="items[${i}][unit_price]" value="${item.unit_price || 0}"
                class="form-control form-control-sm" step="0.01" min="0" required>
        </td>
        <td>
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('tr').remove()">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
}

function addManualItem() {
    document.getElementById('empty_row') && document.getElementById('empty_row').remove();
    addItemRow();
}
</script>
@endsection
