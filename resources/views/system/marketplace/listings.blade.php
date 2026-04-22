@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🌐 Marketplace — Listings</h3>
        <a href="{{ route('system.marketplace.leads') }}" class="btn btn-outline-primary btn-sm">
            Ver leads ({{ $stats['leads'] }})
        </a>
    </div>

    @if(session('ok'))    <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card p-3"><small class="text-muted">Total</small><h4>{{ $stats['total'] }}</h4></div></div>
        <div class="col-md-3"><div class="card p-3 border-success"><small class="text-success">Activos</small><h4>{{ $stats['active'] }}</h4></div></div>
        <div class="col-md-3"><div class="card p-3 border-warning"><small class="text-warning">Pendientes</small><h4>{{ $stats['pending'] }}</h4></div></div>
        <div class="col-md-3"><div class="card p-3 border-danger"><small class="text-danger">Rechazados</small><h4>{{ $stats['rejected'] }}</h4></div></div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3"><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Buscar producto"></div>
        <div class="col-md-3"><input type="text" name="tenant" value="{{ request('tenant') }}" class="form-control form-control-sm" placeholder="Tienda (fqdn)"></div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="active"        {{ request('status')==='active' ? 'selected':'' }}>Activos</option>
                <option value="paused"        {{ request('status')==='paused' ? 'selected':'' }}>Pausados</option>
                <option value="pending_review" {{ request('status')==='pending_review' ? 'selected':'' }}>Pendiente revisión</option>
                <option value="rejected"      {{ request('status')==='rejected' ? 'selected':'' }}>Rechazados</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary btn-sm">Filtrar</button></div>
    </form>

    <div class="card">
        <table class="table mb-0 table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">Img</th>
                    <th>Producto</th>
                    <th>Tienda</th>
                    <th class="text-end">Precio</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center" title="Clicks / Leads / Conversión">
                        Tráfico
                        <br><small class="text-muted fw-normal" style="font-size:10px">clicks · leads · conv.</small>
                    </th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($listings as $l)
                    <tr>
                        <td>
                            @if($l->image_url)
                                <img src="{{ $l->image_url }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                            @endif
                        </td>
                        <td>
                            <a href="{{ $l->public_url }}" target="_blank" class="text-decoration-none">
                                <strong>{{ $l->title }}</strong>
                            </a>
                            <br><small class="text-muted">SKU {{ $l->internal_id ?? '—' }} · {{ $l->category_name ?? 'Sin categoría' }}</small>
                        </td>
                        <td>
                            <small>{{ $l->tenant_fqdn }}</small>
                            @if($l->tenant_verified)
                                <br><span class="badge bg-primary" style="font-size:10px;font-weight:600" title="Tienda verificada por ebaemy">✓ Verificada</span>
                            @endif
                        </td>
                        <td class="text-end">S/ {{ number_format($l->display_price, 2) }}</td>
                        <td class="text-center">{{ $l->stock }}</td>
                        <td class="text-center">
                            <div style="font-size:13px">
                                🖱 <strong>{{ $l->click_count }}</strong>
                                · 📩 <strong class="text-info">{{ $l->lead_count }}</strong>
                                @if($l->click_count > 0)
                                    · <span class="text-success">{{ $l->conversion_rate }}%</span>
                                @endif
                            </div>
                            <small class="text-muted">{{ $l->view_count }} vistas</small>
                        </td>
                        <td class="text-center">
                            @php
                                $badge = ['active'=>'success','paused'=>'secondary','pending_review'=>'warning','rejected'=>'danger','draft'=>'dark'][$l->status] ?? 'light';
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $l->status }}</span>
                            @if($l->rejection_reason) <br><small class="text-danger">{{ $l->rejection_reason }}</small> @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Acción</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form method="POST" action="{{ route('system.marketplace.listings.status', $l->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <button class="dropdown-item text-success" @if($l->status==='active') disabled @endif>✓ Activar</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('system.marketplace.listings.status', $l->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="paused">
                                            <button class="dropdown-item text-warning" @if($l->status==='paused') disabled @endif>⏸ Pausar</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('system.marketplace.listings.status', $l->id) }}"
                                              onsubmit="this.querySelector('[name=rejection_reason]').value = prompt('Motivo de rechazo:') || ''; if(!this.querySelector('[name=rejection_reason]').value) return false;">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button class="dropdown-item text-danger" @if($l->status==='rejected') disabled @endif>✖ Rechazar</button>
                                        </form>
                                    </li>
                                    @if($l->client_id)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('system.marketplace.tenant.verify', $l->client_id) }}"
                                              onsubmit="var n = prompt('Nota interna (opcional):') || ''; this.querySelector('[name=note]').value = n;">
                                            @csrf
                                            <input type="hidden" name="is_verified" value="{{ $l->tenant_verified ? 0 : 1 }}">
                                            <input type="hidden" name="note" value="">
                                            <button class="dropdown-item text-primary">
                                                {{ $l->tenant_verified ? '✖ Remover verificación' : '✓ Verificar tienda' }}
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No hay listings todavía</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $listings->links() }}
    </div>
</div>
@endsection
