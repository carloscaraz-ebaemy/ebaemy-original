@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Reviews — Marketplace</h2>
        <a href="{{ route('system.marketplace.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-warning-subtle">
                <div class="card-body py-3">
                    <strong>{{ $stats['pending'] }}</strong> pendientes
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success-subtle">
                <div class="card-body py-3">
                    <strong>{{ $stats['approved'] }}</strong> aprobadas
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger-subtle">
                <div class="card-body py-3">
                    <strong>{{ $stats['rejected'] }}</strong> rechazadas
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="pending" {{ request('status')==='pending' ? 'selected' : '' }}>Pendientes</option>
                <option value="approved" {{ request('status')==='approved' ? 'selected' : '' }}>Aprobadas</option>
                <option value="rejected" {{ request('status')==='rejected' ? 'selected' : '' }}>Rechazadas</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="tenant" class="form-control form-control-sm" placeholder="Filtrar por tienda (fqdn)" value="{{ request('tenant') }}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100">Filtrar</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:140px">Fecha</th>
                        <th>Producto / Tienda</th>
                        <th style="width:180px">Cliente</th>
                        <th class="text-center" style="width:100px">Rating</th>
                        <th>Comentario</th>
                        <th class="text-center" style="width:100px">Estado</th>
                        <th class="text-end" style="width:170px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $r)
                        <tr>
                            <td>
                                <small>{{ $r->created_at->format('Y-m-d H:i') }}</small>
                            </td>
                            <td>
                                @if($r->listing)
                                    <a href="{{ $r->listing->public_url ?? '#' }}" target="_blank"><strong>{{ $r->listing->title }}</strong></a><br>
                                    <small class="text-muted">{{ $r->listing->tenant_fqdn }}</small>
                                @else
                                    <em class="text-muted">Listing removido</em>
                                @endif
                            </td>
                            <td>
                                <div>{{ $r->customer_name }}</div>
                                @if($r->customer_email)<small class="text-muted">{{ $r->customer_email }}</small>@endif
                            </td>
                            <td class="text-center">
                                <span style="color:#f59e0b;font-size:15px">
                                    @for($i=1;$i<=5;$i++)
                                        {{ $i <= $r->rating ? '★' : '☆' }}
                                    @endfor
                                </span>
                                <div><small>{{ $r->rating }}/5</small></div>
                            </td>
                            <td>
                                @if($r->comment)
                                    <small>{{ \Illuminate\Support\Str::limit($r->comment, 160) }}</small>
                                @else
                                    <em class="text-muted">Sin comentario</em>
                                @endif
                                @if($r->rejection_reason)
                                    <br><small class="text-danger">Rechazada: {{ $r->rejection_reason }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $badge = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$r->status] ?? 'light';
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $r->status }}</span>
                            </td>
                            <td class="text-end">
                                @if($r->status !== 'approved')
                                    <form method="POST" action="{{ route('system.marketplace.reviews.approve', $r->id) }}" style="display:inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success" title="Aprobar">✓</button>
                                    </form>
                                @endif
                                @if($r->status !== 'rejected')
                                    <form method="POST" action="{{ route('system.marketplace.reviews.reject', $r->id) }}" style="display:inline"
                                          onsubmit="this.querySelector('[name=rejection_reason]').value = prompt('Motivo del rechazo (opcional):') || '';">
                                        @csrf
                                        <input type="hidden" name="rejection_reason" value="">
                                        <button class="btn btn-sm btn-danger" title="Rechazar">✖</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No hay reviews</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $reviews->links() }}
    </div>
</div>
@endsection
