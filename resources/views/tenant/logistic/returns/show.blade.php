@extends('tenant.layouts.app')

@section('title', 'Devolución #' . $return->id)

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-undo-alt mr-2 text-warning"></i>
                Devolución <strong>#{{ $return->id }}</strong>
                <span class="badge badge-{{ $return->statusColor() }} ml-2">{{ $return->statusLabel() }}</span>
            </h4>
            <small class="text-muted">Registrada el {{ $return->created_at->format('d/m/Y H:i') }}</small>
        </div>
        <a href="{{ route('logistic.returns.index') }}" class="btn btn-light">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">

        {{-- Columna izquierda: detalle --}}
        <div class="col-md-8">

            {{-- Info del pedido original --}}
            @if($return->saleNote)
            <div class="card shadow-sm mb-3">
                <div class="card-header"><strong><i class="fas fa-file-alt mr-1"></i> Pedido Original</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <small class="text-muted d-block">N° Documento</small>
                            <strong>{{ $return->saleNote->number_full }}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Cliente</small>
                            <strong>{{ optional($return->saleNote->person)->name ?? '—' }}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Estado despacho</small>
                            <span class="badge badge-{{ optional($return->saleNote->logistic_status)?->badgeColor() ?? 'secondary' }}">
                                {{ optional($return->saleNote->logistic_status)?->label() ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Productos --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header"><strong><i class="fas fa-boxes mr-1"></i> Productos Devueltos</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Devuelto</th>
                                <th class="text-center">Reingresado</th>
                                <th class="text-center">Condición</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($return->items as $item)
                            <tr>
                                <td>{{ optional($item->item)->description ?? '—' }}</td>
                                <td class="text-center">{{ $item->quantity_returned }}</td>
                                <td class="text-center">
                                    @if($item->quantity_restocked > 0)
                                        <span class="text-success font-weight-bold">{{ $item->quantity_restocked }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-{{ $item->conditionColor() }}">
                                        {{ $item->conditionLabel() }}
                                    </span>
                                </td>
                                <td class="text-right">S/ {{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="4" class="text-right font-weight-bold">Total devuelto:</td>
                                <td class="text-right font-weight-bold">
                                    S/ {{ number_format($return->items->sum('subtotal'), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        {{-- Columna derecha: info + acciones --}}
        <div class="col-md-4">

            <div class="card shadow-sm mb-3">
                <div class="card-header"><strong>Datos de la Devolución</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Motivo</dt>
                        <dd class="col-7">{{ $return->reasonLabel() }}</dd>

                        <dt class="col-5 text-muted">Almacén</dt>
                        <dd class="col-7">{{ optional($return->warehouse)->description }}</dd>

                        <dt class="col-5 text-muted">Recibido por</dt>
                        <dd class="col-7">{{ optional($return->user)->name }}</dd>

                        @if($return->courier_name)
                        <dt class="col-5 text-muted">Courier</dt>
                        <dd class="col-7">{{ $return->courier_name }}</dd>
                        @endif

                        @if($return->tracking_number)
                        <dt class="col-5 text-muted">Guía retorno</dt>
                        <dd class="col-7">{{ $return->tracking_number }}</dd>
                        @endif

                        @if($return->received_at)
                        <dt class="col-5 text-muted">Recibido</dt>
                        <dd class="col-7">{{ $return->received_at->format('d/m/Y H:i') }}</dd>
                        @endif

                        @if($return->processed_at)
                        <dt class="col-5 text-muted">Procesado</dt>
                        <dd class="col-7">{{ $return->processed_at->format('d/m/Y H:i') }}</dd>
                        @endif

                        @if($return->notes)
                        <dt class="col-5 text-muted">Notas</dt>
                        <dd class="col-7">{{ $return->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Acción: procesar --}}
            @if(!$return->isProcesado())
            <div class="card shadow-sm border-warning">
                <div class="card-body text-center">
                    <p class="mb-2 text-muted">
                        Al procesar, los productos en <strong>buen estado</strong>
                        se reingresarán al stock del almacén.
                    </p>
                    <form method="POST" action="{{ route('logistic.returns.process', $return) }}"
                          onsubmit="return confirm('¿Confirmas el procesamiento? Se actualizará el stock.')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-check-double mr-1"></i> Procesar y Reingresar Stock
                        </button>
                    </form>
                </div>
            </div>
            @else
            <div class="alert alert-success text-center">
                <i class="fas fa-check-circle mr-1"></i>
                Devolución procesada. Stock actualizado.
            </div>
            @endif

        </div>
    </div>

</div>
@endsection
