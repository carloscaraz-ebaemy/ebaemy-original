@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📣 Campañas de marketing</h3>
        <a href="{{ route('system.marketing.campaigns.create') }}" class="btn btn-primary btn-sm">+ Nueva campaña</a>
    </div>

    @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Contactos en BD</div>
                <div class="h4 mb-0">{{ $stats['contacts'] }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Aceptaron promos (alcanzables)</div>
                <div class="h4 mb-0 text-success">{{ $stats['consented'] }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Cancelaron suscripción</div>
                <div class="h4 mb-0 text-danger">{{ $stats['opted_out'] }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Canal</th>
                    <th>Status</th>
                    <th class="text-center">Targets</th>
                    <th class="text-center">Enviados</th>
                    <th class="text-center">Fallidos</th>
                    <th>Programado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $c)
                    <tr>
                        <td><strong>{{ $c->name }}</strong></td>
                        <td>{{ $c->channel }}</td>
                        <td><span class="badge bg-secondary">{{ $c->status }}</span></td>
                        <td class="text-center">{{ $c->target_count }}</td>
                        <td class="text-center text-success">{{ $c->sent_count }}</td>
                        <td class="text-center text-danger">{{ $c->failed_count }}</td>
                        <td><small>{{ $c->scheduled_at?->format('d/m H:i') ?: '—' }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('system.marketing.campaigns.show', $c->id) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Aún no hay campañas. Crea la primera arriba a la derecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $campaigns->links() }}</div>
</div>
@endsection
