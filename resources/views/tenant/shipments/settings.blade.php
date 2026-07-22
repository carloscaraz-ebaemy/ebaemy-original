@extends('tenant.layouts.app')

@push('styles')
<style>
    .cfg-wrap { max-width:640px; }
    #storeMap { width:100%; height:340px; border-radius:14px; border:1px solid #e5e7eb; background:#e5e7eb; margin-top:10px; }
    .cfg-picked { margin-top:10px; padding:12px 14px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:12px; font-size:14px; }
    .cfg-picked b { color:#3730a3; }
    .cfg-off { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:12px 14px; color:#92400e; font-size:13.5px; }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3 py-3 cfg-wrap">

    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-bold">📍 Ubicación de la tienda</h4>
            <small class="text-muted">Fija de dónde salen tus motorizados. Se usa para calcular la distancia a cada cliente.</small>
        </div>
        <a href="{{ route('shipments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Volver</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if(empty($mapsKey))
                <div class="cfg-off"><i class="fas fa-triangle-exclamation me-1"></i> El mapa no está disponible (falta la API key de Google Maps). Puedes ingresar las coordenadas manualmente abajo.</div>
            @endif

            <form method="POST" action="{{ route('shipments.settings.save') }}" id="storeForm">
                @csrf
                <label class="form-label fw-semibold mt-2">Buscar mi tienda</label>
                <input type="text" id="storeSearch" class="form-control" placeholder="Escribe la dirección de tu tienda…" autocomplete="off">

                @if(!empty($mapsKey))
                    <div id="storeMap"></div>
                    <small class="text-muted d-block mt-1">Arrastra el marcador para ajustar la ubicación exacta de la tienda.</small>
                @endif

                <div class="cfg-picked mt-2" id="storePicked" style="{{ $store->has_origin ? '' : 'display:none;' }}">
                    <b id="sp_addr">{{ $store->store_address ?: '—' }}</b>
                    <div class="small text-muted" id="sp_coords">
                        @if($store->has_origin){{ $store->store_latitude }}, {{ $store->store_longitude }}@endif
                    </div>
                </div>

                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Latitud</label>
                        <input type="text" name="store_latitude" id="store_lat" class="form-control form-control-sm" value="{{ old('store_latitude', $store->store_latitude) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Longitud</label>
                        <input type="text" name="store_longitude" id="store_lng" class="form-control form-control-sm" value="{{ old('store_longitude', $store->store_longitude) }}" required>
                    </div>
                </div>
                <input type="hidden" name="store_address" id="store_address" value="{{ old('store_address', $store->store_address) }}">

                <hr class="my-4">
                <h6 class="fw-bold mb-1">🛵 Tarifa del motorizado (cobro por km)</h6>
                <p class="text-muted small mb-3">El cliente verá un <b>precio aproximado</b> al elegir su ubicación. Fórmula: <b>base + (km × tarifa)</b>, nunca menor al mínimo.</p>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">Cobro base (S/)</label>
                        <input type="number" step="0.10" min="0" name="base_price" class="form-control form-control-sm" value="{{ old('base_price', $store->base_price) }}" placeholder="0.00">
                    </div>
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">Por km (S/)</label>
                        <input type="number" step="0.10" min="0" name="price_per_km" class="form-control form-control-sm" value="{{ old('price_per_km', $store->price_per_km) }}" placeholder="Ej. 0.80">
                    </div>
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">Mínimo (S/)</label>
                        <input type="number" step="0.10" min="0" name="min_price" class="form-control form-control-sm" value="{{ old('min_price', $store->min_price) }}" placeholder="Ej. 5.00">
                    </div>
                </div>
                <div class="small text-muted mt-2">Ejemplo: base <b>S/3</b> + <b>S/0.80</b>/km, mínimo <b>S/5</b> → un cliente a 4 km paga <b>S/6.20</b>. Deja la tarifa por km en blanco/0 si no quieres mostrar precio.</div>

                <hr class="my-4">
                <h6 class="fw-bold mb-1">📦 Envío por agencia (provincia)</h6>
                <p class="text-muted small mb-2">Costo de llevar el paquete <b>desde tu tienda hasta la agencia</b>, por paquete (se multiplica por el N° de bultos). Ej. <b>S/ 20</b>.</p>
                <div class="input-group input-group-sm" style="max-width:220px;">
                    <span class="input-group-text">S/</span>
                    <input type="number" step="0.10" min="0" name="agency_fee" class="form-control" value="{{ $store->agency_fee }}" placeholder="Ej. 20.00">
                    <span class="input-group-text">por paquete</span>
                </div>
                <div class="small text-muted mt-1">Déjalo vacío/0 si no cobras este servicio.</div>

                <hr class="my-4">
                <h6 class="fw-bold mb-1">💬 WhatsApp para recibir pedidos</h6>
                <p class="text-muted small mb-2">Cuando el cliente termina de registrar, verá un botón <b>“Enviar mi pedido por WhatsApp”</b> con <b>todos sus datos</b>, que va <b>directo a este número</b>.</p>
                <div class="input-group input-group-sm" style="max-width:280px;">
                    <span class="input-group-text">🇵🇪 +51</span>
                    <input type="text" name="orders_whatsapp" class="form-control" value="{{ $store->orders_whatsapp }}" placeholder="9 dígitos (ej. 958183558)" maxlength="20">
                </div>
                <div class="small text-muted mt-1">Si lo dejas vacío, el cliente elegirá el contacto manualmente al compartir.</div>

                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Guardar configuración</button>
            </form>
        </div>
    </div>
</div>
@endsection

@if(!empty($mapsKey))
@push('scripts')
<script>
(function () {
    var START = { lat: {{ $store->store_latitude ?: -12.0464 }}, lng: {{ $store->store_longitude ?: -77.0428 }} };
    var HAS = {{ $store->has_origin ? 'true' : 'false' }};
    var map, marker, geocoder, ac;

    function setFields(lat, lng, addr) {
        document.getElementById('store_lat').value = lat;
        document.getElementById('store_lng').value = lng;
        if (addr !== null && addr !== undefined) document.getElementById('store_address').value = addr;
        var box = document.getElementById('storePicked'); box.style.display = 'block';
        if (addr) document.getElementById('sp_addr').textContent = addr;
        document.getElementById('sp_coords').textContent = (+lat).toFixed(6) + ', ' + (+lng).toFixed(6);
    }
    function reverse(latlng) {
        geocoder.geocode({ location: latlng }, function (res, status) {
            setFields(latlng.lat(), latlng.lng(), (status === 'OK' && res[0]) ? res[0].formatted_address : null);
        });
    }
    window.initStoreMap = function () {
        geocoder = new google.maps.Geocoder();
        map = new google.maps.Map(document.getElementById('storeMap'), { center: START, zoom: HAS ? 16 : 12, mapTypeControl:false, streetViewControl:false, fullscreenControl:false });
        marker = new google.maps.Marker({ map: map, position: START, draggable: true });
        marker.addListener('dragend', function () { reverse(marker.getPosition()); });
        map.addListener('click', function (e) { marker.setPosition(e.latLng); reverse(e.latLng); });
        var input = document.getElementById('storeSearch');
        ac = new google.maps.places.Autocomplete(input, { componentRestrictions:{country:'pe'}, fields:['geometry','formatted_address'] });
        ac.bindTo('bounds', map);
        ac.addListener('place_changed', function () {
            var p = ac.getPlace(); if (!p.geometry) return;
            var loc = p.geometry.location; map.panTo(loc); map.setZoom(16); marker.setPosition(loc);
            setFields(loc.lat(), loc.lng(), p.formatted_address);
        });
    };
})();
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initStoreMap&language=es&region=PE"></script>
@endpush
@endif
