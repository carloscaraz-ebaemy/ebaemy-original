@extends('tenant.layouts.app')

@section('title', 'Devoluciones')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-undo-alt mr-2 text-warning"></i> Devoluciones</h4>
            <small class="text-muted">Gestiona las devoluciones de pedidos despachados</small>
        </div>
        <a href="{{ route('logistic.returns.create') }}" class="btn btn-warning">
            <i class="fas fa-plus mr-1"></i> Nueva Devolución
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Contadores --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-warning shadow-sm">
                <div class="card-body py-2 d-flex justify-content-between align-items-center">
                    <div><div class="text-xs text-warning font-weight-bold">PENDIENTES</div>
                    <div class="h4 mb-0">{{ $counters['pendiente'] }}</div></div>
                    <i class="fas fa-clock fa-2x text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-info shadow-sm">
                <div class="card-body py-2 d-flex justify-content-between align-items-center">
                    <div><div class="text-xs text-info font-weight-bold">RECIBIDOS</div>
                    <div class="h4 mb-0">{{ $counters['recibido'] }}</div></div>
                    <i class="fas fa-box-open fa-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success shadow-sm">
                <div class="card-body py-2 d-flex justify-content-between align-items-center">
                    <div><div class="text-xs text-success font-weight-bold">PROCESADOS</div>
                    <div class="h4 mb-0">{{ $counters['procesado'] }}</div></div>
                    <i class="fas fa-check-double fa-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="form-inline">
                <input type="text" name="search" value="{{ request('search') }}"
                    class="form-control form-control-sm mr-2" placeholder="N° pedido o tracking...">
                <select name="status" class="form-control form-control-sm mr-2">
                    <option value="">Todos los estados</option>
                    <option value="PENDIENTE" {{ request('status') == 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                    <option value="RECIBIDO"  {{ request('status') == 'RECIBIDO'  ? 'selected' : '' }}>Recibido</option>
                    <option value="PROCESADO" {{ request('status') == 'PROCESADO' ? 'selected' : '' }}>Procesado</option>
                </select>
                <button class="btn btn-sm btn-primary mr-1"><i class="fas fa-search"></i> Filtrar</button>
                <a href="{{ route('logistic.returns.index') }}" class="btn btn-sm btn-light">Limpiar</a>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Pedido</th>
                        <th>Motivo</th>
                        <th>Courier</th>
                        <th>Almacén</th>
                        <th>Ítems</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $return)
                        <tr>
                            <td>{{ $return->id }}</td>
                            <td>
                                @if($return->saleNote)
                                    <strong>{{ $return->saleNote->number_full }}</strong>
                                @else
                                    <span class="text-muted">Sin pedido</span>
                                @endif
                            </td>
                            <td>{{ $return->reasonLabel() }}</td>
                            <td>{{ $return->courier_name ?? '—' }}</td>
                            <td>{{ optional($return->warehouse)->description }}</td>
                            <td><span class="badge badge-secondary">{{ $return->items->count() }}</span></td>
                            <td>
                                <span class="badge badge-{{ $return->statusColor() }}">
                                    {{ $return->statusLabel() }}
                                </span>
                            </td>
                            <td>{{ $return->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('logistic.returns.show', $return) }}"
                                   class="btn btn-xs btn-outline-primary">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                No hay devoluciones registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($returns->hasPages())
            <div class="card-footer">{{ $returns->links() }}</div>
        @endif
    </div>

</div>
@endsection
