@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h3 class="mb-0">📣 Marketplace — Solicitudes de publicidad</h3>
        <a href="{{ route('system.marketplace.seo') }}" class="btn btn-outline-secondary btn-sm">Medición y píxeles</a>
    </div>
    <p class="text-muted small mb-3">
        Las tiendas las piden desde su panel (Tienda virtual → <b>Publicidad</b>). Confirma presupuesto y pago por WhatsApp,
        crea la campaña en Meta con el <b>nombre</b> y los <b>parámetros de URL</b> de cada solicitud, y ve cambiando el estado:
        la tienda lo ve en su panel. Los pedidos y consultas se cuentan solos por el nombre de campaña.
    </p>

    @if(session('ok'))    <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(isset($errors) && $errors->any())   <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="mb-3 d-flex flex-wrap gap-1">
        <a href="{{ route('system.marketplace.ads') }}" class="btn btn-sm {{ $status ? 'btn-outline-secondary' : 'btn-secondary' }}">Todas</a>
        @foreach(\App\Models\System\MarketplaceAdRequest::STATUSES as $key => $label)
            <a href="{{ route('system.marketplace.ads', ['status' => $key]) }}"
               class="btn btn-sm {{ $status === $key ? 'btn-secondary' : 'btn-outline-secondary' }}">
                {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @forelse($requests as $r)
        @php($res = in_array($r->status, ['activa', 'terminada'], true) ? $r->results() : null)
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">#{{ $r->id }} · {{ $r->store_name }}
                            <span class="badge bg-{{ ['solicitada'=>'warning','pagada'=>'info','activa'=>'success','terminada'=>'secondary','rechazada'=>'danger'][$r->status] ?? 'light' }}">{{ $r->statusLabel() }}</span>
                        </h5>
                        <div class="small text-muted">
                            {{ $r->created_at->format('d/m/Y H:i') }} · pidió {{ $r->requested_by ?: '—' }} ·
                            WhatsApp <a href="https://wa.me/51{{ preg_replace('/\D/', '', $r->contact_phone) }}" target="_blank" rel="noopener">{{ $r->contact_phone }}</a>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold">S/ {{ number_format($r->budget, 2) }}</div>
                        <div class="small text-muted">{{ $r->days }} días · {{ \App\Models\System\MarketplaceAdRequest::ZONES[$r->zone] ?? $r->zone }}</div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-lg-5">
                        <div class="small"><b>Qué anunciar:</b> {{ \App\Models\System\MarketplaceAdRequest::SCOPES[$r->scope] ?? $r->scope }}
                            @if($r->scope_detail)<br><span class="text-muted">{{ $r->scope_detail }}</span>@endif
                        </div>
                        @if($r->notes)<div class="small mt-1"><b>Nota de la tienda:</b> {{ $r->notes }}</div>@endif
                        @if($r->starts_at || $r->ends_at)
                            <div class="small mt-1 text-muted">
                                @if($r->starts_at) Empezó {{ $r->starts_at->format('d/m/Y') }} @endif
                                @if($r->ends_at) · terminó {{ $r->ends_at->format('d/m/Y') }} @endif
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-4 small">
                        <div><b>Nombre de la campaña en Meta</b></div>
                        <code class="user-select-all">{{ $r->utm_campaign }}</code>
                        <div class="mt-1"><b>URL del anuncio</b></div>
                        <code class="user-select-all">{{ $r->shortUrl() }}</code>
                        <div class="mt-1"><b>Parámetros de URL</b></div>
                        <code class="user-select-all" style="word-break:break-all">{{ $r->urlParams() }}</code>
                        <div class="mt-1"><b>Conjunto de productos</b>: Etiqueta personalizada 2 = <code>tienda_{{ $r->subdomain }}</code></div>
                    </div>
                    <div class="col-lg-3 small">
                        @if($res)
                            <div><b>Resultados</b></div>
                            <div>Pedidos: <b>{{ $res['orders'] }}</b> · S/ {{ number_format($res['revenue'], 2) }}</div>
                            <div>Consultas: <b>{{ $res['leads'] }}</b></div>
                            @if($r->spent)<div>Gastado en Meta: S/ {{ number_format($r->spent, 2) }}</div>@endif
                        @else
                            <div class="text-muted">Los resultados aparecen cuando la campaña está activa.</div>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('system.marketplace.ads.update', $r->id) }}" class="row g-2 align-items-end mt-2 border-top pt-2">
                    @csrf
                    <div class="col-sm-3">
                        <label class="form-label small mb-0">Estado</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach(\App\Models\System\MarketplaceAdRequest::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small mb-0">Gastado (S/)</label>
                        <input type="number" step="0.01" min="0" name="spent" value="{{ $r->spent }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label small mb-0">Nota interna</label>
                        <input type="text" name="admin_notes" maxlength="500" value="{{ $r->admin_notes }}" class="form-control form-control-sm" placeholder="Ej. pagó por Yape el 27/10">
                    </div>
                    <div class="col-sm-2">
                        <button class="btn btn-primary btn-sm w-100">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">Todavía ninguna tienda ha pedido publicidad.</div>
    @endforelse

    {{ $requests->links() }}
</div>
@endsection
