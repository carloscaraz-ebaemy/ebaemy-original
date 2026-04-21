@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📩 Marketplace — Leads / Solicitudes</h3>
        <a href="{{ route('system.marketplace.listings') }}" class="btn btn-outline-secondary btn-sm">← Listings</a>
    </div>

    @if(session('ok'))    <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3"><input type="text" name="tenant" value="{{ request('tenant') }}" class="form-control form-control-sm" placeholder="Tienda"></div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="new"            {{ request('status')==='new' ? 'selected':'' }}>Nuevos</option>
                <option value="sent_to_tenant" {{ request('status')==='sent_to_tenant' ? 'selected':'' }}>Enviados</option>
                <option value="converted"      {{ request('status')==='converted' ? 'selected':'' }}>Convertidos</option>
                <option value="failed"         {{ request('status')==='failed' ? 'selected':'' }}>Fallidos</option>
                <option value="archived"       {{ request('status')==='archived' ? 'selected':'' }}>Archivados</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm">Filtrar</button></div>
    </form>

    <div class="card">
        <table class="table mb-0 table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Tienda</th>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td><small>{{ $lead->created_at->format('d/m H:i') }}</small></td>
                        <td>
                            <strong>{{ $lead->snapshot_title ?: ($lead->listing->title ?? 'N/A') }}</strong>
                            <br><small class="text-muted">Item #{{ $lead->remote_item_id }}</small>
                        </td>
                        <td><small>{{ $lead->tenant_fqdn }}</small></td>
                        <td>{{ $lead->customer_name }}</td>
                        <td>
                            @if($lead->customer_phone) <small>📱 {{ $lead->customer_phone }}</small><br> @endif
                            @if($lead->customer_email) <small>✉️ {{ $lead->customer_email }}</small> @endif
                        </td>
                        <td class="text-center">{{ $lead->quantity }}</td>
                        <td class="text-end">S/ {{ number_format($lead->snapshot_price * $lead->quantity, 2) }}</td>
                        <td class="text-center">
                            @php
                                $badge = ['new'=>'primary','sent_to_tenant'=>'info','converted'=>'success','failed'=>'danger','archived'=>'secondary'][$lead->status] ?? 'light';
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $lead->status }}</span>
                            @if($lead->status === 'converted' && $lead->tenant_order_external_id)
                                <br><small class="text-success">#{{ substr($lead->tenant_order_external_id, 0, 8) }}</small>
                            @endif
                            @if($lead->sync_error) <br><small class="text-danger" title="{{ $lead->sync_error }}">⚠ Error</small> @endif
                        </td>
                        <td class="text-end">
                            @if(in_array($lead->status, ['failed', 'new']))
                                <form method="POST" action="{{ route('system.marketplace.leads.retry', $lead->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-warning">↻ Reintentar</button>
                                </form>
                            @endif
                            @if($lead->status !== 'archived' && $lead->status !== 'converted')
                                <form method="POST" action="{{ route('system.marketplace.leads.archive', $lead->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Archivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @if($lead->message)
                        <tr><td colspan="9" class="ps-5"><small class="text-muted">💬 {{ $lead->message }}</small></td></tr>
                    @endif
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">No hay leads todavía</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $leads->links() }}
    </div>
</div>
@endsection
