@extends('tenant.layouts.app')

@push('styles')
<style>
    .mz-wrap { max-width:720px; }
    .mz-tabs { display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
    .mz-tab { text-decoration:none; font-size:13px; font-weight:600; padding:7px 13px; border-radius:999px; border:1px solid #e5e7eb; color:#374151; background:#fff; }
    .mz-tab.active { background:#7c3aed; color:#fff; border-color:#7c3aed; }
    .mz-tab .n { background:rgba(0,0,0,.08); border-radius:999px; padding:1px 7px; margin-left:5px; font-size:11px; }
    .mz-tab.active .n { background:rgba(255,255,255,.25); }
    .mz-card { background:#fff; border:1px solid #e9ecef; border-radius:16px; padding:16px; margin-bottom:14px; box-shadow:0 2px 10px -6px rgba(0,0,0,.15); }
    .mz-top { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
    .mz-code { font-weight:800; font-size:13px; color:#7c3aed; letter-spacing:.3px; }
    .mz-name { font-size:18px; font-weight:800; margin:2px 0; }
    .mz-addr { font-size:14px; color:#374151; line-height:1.4; }
    .mz-ref { font-size:13px; color:#6b7280; margin-top:2px; }
    .mz-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:14px; }
    .mz-btn { display:flex; align-items:center; justify-content:center; gap:7px; text-decoration:none; text-align:center; padding:12px; border-radius:12px; font-weight:700; font-size:14px; border:none; cursor:pointer; }
    .mz-nav { background:#7c3aed; color:#fff; grid-column:1 / -1; font-size:15px; }
    .mz-call { background:#e0e7ff; color:#3730a3; }
    .mz-wa { background:#dcfce7; color:#166534; }
    .mz-steps { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
    .mz-steps form { flex:1; min-width:120px; }
    .mz-step-btn { width:100%; padding:11px; border-radius:12px; border:1.5px solid #7c3aed; background:#fff; color:#7c3aed; font-weight:700; font-size:13.5px; cursor:pointer; }
    .mz-step-btn.done { background:#7c3aed; color:#fff; }
    .mz-badge { font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:999px; background:#f3e8ff; color:#7c3aed; }
    .mz-empty { text-align:center; color:#6b7280; padding:40px 10px; }
    .mz-noloc { font-size:12.5px; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:8px 10px; margin-top:10px; }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3 py-3 mz-wrap">

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fas fa-motorcycle text-white rounded px-2 py-1" style="background:#7c3aed;"></i> Reparto a domicilio</h4>
            <small class="text-muted">Entregas del motorizado. Toca “Abrir en Google Maps” para navegar.</small>
        </div>
        <a href="{{ route('shipments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Volver al tablero</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Tabs --}}
    @php
        $tabs = [
            'activos'    => ['Activos', $counts['activos']],
            'entregados' => ['Entregados hoy', $counts['entregados']],
            'todos'      => ['Todos', $counts['todos']],
        ];
    @endphp
    <div class="mz-tabs">
        @foreach($tabs as $k => [$lbl, $n])
            <a href="{{ route('shipments.couriers', ['view'=>$k]) }}" class="mz-tab {{ $view === $k ? 'active' : '' }}">{{ $lbl }}<span class="n">{{ $n }}</span></a>
        @endforeach
    </div>

    {{-- Buscador --}}
    <form method="GET" action="{{ route('shipments.couriers') }}" class="mb-3">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="input-group input-group-sm" style="max-width:420px;">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por nombre, código o celular…">
            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            @if($q)<a href="{{ route('shipments.couriers', ['view'=>$view]) }}" class="btn btn-outline-secondary">✕</a>@endif
        </div>
    </form>

    @forelse($shipments as $s)
        @php
            $waPhone = preg_replace('/\D+/', '', (string) $s->phone);
            if (strlen($waPhone) === 9 && $waPhone[0] === '9') $waPhone = '51' . $waPhone;
            $flow = $s->selectableStatuses();
            $curIdx = array_search($s->status, $flow);
        @endphp
        <div class="mz-card">
            <div class="mz-top">
                <span class="mz-code">{{ $s->shipment_code }}</span>
                <span class="mz-badge">{{ $s->status_label }}</span>
            </div>
            <div class="mz-name">{{ $s->full_name }}</div>
            @if($s->distance_km)
                <div style="display:inline-block;margin:4px 6px 4px 0;font-size:13px;font-weight:800;color:#3730a3;background:#e0e7ff;border-radius:999px;padding:4px 12px;">
                    🛵 {{ $s->distance_text ?: ($s->distance_km.' km') }} desde la tienda
                    @if($s->duration_text)<span style="font-weight:600;">· ~{{ $s->duration_text }}</span>@endif
                </div>
            @endif
            @if($s->delivery_price)
                <div style="display:inline-block;margin:4px 0;font-size:13px;font-weight:800;color:#166534;background:#dcfce7;border-radius:999px;padding:4px 12px;">💵 S/ {{ number_format($s->delivery_price, 2) }}</div>
            @endif
            <div class="mz-addr"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $s->formatted_address ?: $s->shipping_destination ?: '—' }}</div>
            @if($s->reference)<div class="mz-ref"><i class="fas fa-info-circle me-1"></i>{{ $s->reference }}</div>@endif
            @if($s->destination_city)<div class="mz-ref"><i class="fas fa-city me-1"></i>{{ $s->destination_city }}</div>@endif

            <div class="mz-actions">
                @if($s->courier_directions_url)
                    <a href="{{ $s->courier_directions_url }}" target="_blank" class="mz-btn mz-nav"><i class="fas fa-directions"></i> Abrir en Google Maps</a>
                @elseif($s->maps_link)
                    <a href="{{ $s->maps_link }}" target="_blank" class="mz-btn mz-nav"><i class="fas fa-map"></i> Ver ubicación</a>
                @endif
                @if($s->phone)
                    <a href="tel:{{ $s->phone }}" class="mz-btn mz-call"><i class="fas fa-phone"></i> Llamar</a>
                    <a href="https://wa.me/{{ $waPhone }}" target="_blank" class="mz-btn mz-wa"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                @endif
            </div>

            @unless($s->has_coords)
                <div class="mz-noloc"><i class="fas fa-exclamation-triangle me-1"></i>Sin coordenadas GPS: usa la dirección para ubicar al cliente.</div>
            @endunless

            {{-- Avance de estado del motorizado --}}
            <div class="mz-steps">
                @foreach($flow as $i => $st)
                    @if(in_array($st, ['asignado_motorizado','en_camino','entregado']))
                        <form method="POST" action="{{ route('shipments.status', $s->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $st }}">
                            <button type="submit" class="mz-step-btn {{ ($curIdx !== false && $i <= $curIdx) ? 'done' : '' }}">
                                {{ $statuses[$st] ?? $st }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @empty
        <div class="mz-empty">
            <i class="fas fa-motorcycle fa-2x mb-2 d-block"></i>
            No hay entregas a domicilio {{ $view === 'activos' ? 'activas' : ($view === 'entregados' ? 'entregadas hoy' : 'registradas') }}.
        </div>
    @endforelse

    <div class="d-flex justify-content-center mt-2">{{ $shipments->links() }}</div>
</div>
@endsection
