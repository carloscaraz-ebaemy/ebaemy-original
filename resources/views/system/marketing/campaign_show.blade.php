@extends('system.layouts.app')

@section('content')
<div class="container py-3" style="max-width:880px">
    <a href="{{ route('system.marketing.campaigns.index') }}" class="text-decoration-none small text-muted">← Campañas</a>
    <h3 class="mb-3 mt-1">{{ $campaign->name }}</h3>

    @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

    <div class="row g-2 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="text-muted small">Canal</div><div class="h5 mb-0">{{ $campaign->channel }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="text-muted small">Status</div><div class="h5 mb-0">{{ $campaign->status }}</div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="text-muted small">Targets</div><div class="h5 mb-0">{{ $campaign->target_count }}</div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="text-muted small">Enviados</div><div class="h5 mb-0 text-success">{{ $campaign->sent_count }}</div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="text-muted small">Fallidos</div><div class="h5 mb-0 text-danger">{{ $campaign->failed_count }}</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light"><strong>Mensaje</strong> @if($campaign->subject) — Asunto: <em>{{ $campaign->subject }}</em>@endif</div>
        <div class="card-body" style="white-space: pre-wrap; font-family: ui-monospace, monospace; font-size: 13px">{{ $campaign->message }}</div>
    </div>

    @if($campaign->segment)
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Segmento</strong></div>
            <div class="card-body small">
                @foreach($campaign->segment as $k => $v)
                    <div><strong>{{ $k }}:</strong> {{ is_array($v) ? implode(', ', $v) : $v }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="d-flex gap-2 mb-4">
        @if($campaign->status === 'draft' || $campaign->status === 'scheduled')
            <form method="POST" action="{{ route('system.marketing.campaigns.build', $campaign->id) }}">
                @csrf
                <button class="btn btn-outline-primary">↻ Materializar targets</button>
            </form>
            <form method="POST" action="{{ route('system.marketing.campaigns.dispatch', $campaign->id) }}" class="d-flex gap-2 align-items-center">
                @csrf
                <input type="number" name="batch" value="100" min="10" max="500" class="form-control form-control-sm" style="width:90px">
                <button class="btn btn-warning" onclick="return confirm('¿Despachar lote ahora (síncrono)? Esto envía mensajes reales a contactos con consentimiento.')">📤 Lote sincrónico</button>
            </form>
            <form method="POST" action="{{ route('system.marketing.campaigns.dispatch_async', $campaign->id) }}" class="d-flex gap-2 align-items-center">
                @csrf
                <input type="number" name="batch" value="100" min="10" max="500" class="form-control form-control-sm" style="width:90px">
                <button class="btn btn-success" onclick="return confirm('¿Encolar envío en background? Requiere queue:work corriendo.')">⚡ Encolar background</button>
            </form>
        @endif
    </div>

    <h5 class="mb-2">Últimos targets procesados</h5>
    <div class="card">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Contacto</th>
                    <th>Canal</th>
                    <th>Status</th>
                    <th>Enviado</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sample as $t)
                    <tr>
                        <td>
                            <div>{{ $t->contact?->name ?: '—' }}</div>
                            <small class="text-muted">{{ $t->contact?->phone ?: $t->contact?->email }}</small>
                        </td>
                        <td>{{ $campaign->channel }}</td>
                        <td><span class="badge bg-secondary">{{ $t->status }}</span></td>
                        <td><small>{{ $t->sent_at?->format('d/m H:i') ?: '—' }}</small></td>
                        <td><small class="text-muted">{{ $t->skip_reason ?: $t->error ?: '' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin targets aún. Materialízalos arriba.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
