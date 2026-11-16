{{-- Enlace mínimo: el cliente solo elige su oficina de Shalom (envío que llegó sin ella). --}}
@php
    $accent = $brand['accent'] ?? '#b4472c';
    $sel = old('agency_office_id', $shipment->agency_office_id);
    $scopeText = [
        'district'   => 'Oficinas de Shalom en tu distrito. Si no ves la tuya, usa el buscador.',
        'province'   => 'Tu distrito no tiene oficina de Shalom: estas son las de tu provincia.',
        'department' => 'Tu provincia no tiene oficina de Shalom: estas son las de tu departamento. Elige la más cercana.',
    ][$scope] ?? '';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Elige tu oficina de Shalom · {{ $storeName }}</title>
    <style>
        :root { --ac: {{ $accent }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f6f7f9; color: #1f2937; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; margin-bottom: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 14px; }
        .sum { font-size: 14px; line-height: 1.5; }
        .sum b { color: #111827; }
        .find { width: 100%; font-size: 16px; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; margin: 8px 0; }
        .near { width: 100%; padding: 10px; border: 1px dashed var(--ac); color: var(--ac); background: #fff; border-radius: 10px; font-size: 15px; cursor: pointer; }
        .opt { display: block; width: 100%; text-align: left; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; margin: 8px 0; cursor: pointer; font: inherit; color: inherit; }
        .opt b { display: block; font-size: 15px; }
        .opt span { display: block; font-size: 13px; color: #4b5563; margin-top: 2px; }
        .opt i { display: inline-block; font-style: normal; font-size: 12px; color: #6b7280; margin: 4px 8px 0 0; }
        .opt[aria-checked="true"] { border: 2px solid var(--ac); background: #fffaf7; }
        .opt .best { color: #047857; font-weight: 600; }
        .btn { width: 100%; padding: 14px; border: 0; border-radius: 12px; background: var(--ac); color: #fff; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn[disabled] { opacity: .5; cursor: not-allowed; }
        .bar { position: sticky; bottom: 0; background: #f6f7f9; padding: 12px 0 4px; }
        .ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .empty { text-align: center; color: #6b7280; padding: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>¿En qué oficina de Shalom recoges tu paquete?</h1>
        <div class="muted">{{ $storeName }}</div>
    </div>

    @if($saved)
        <div class="card ok">
            <b>¡Listo! Guardamos tu oficina: {{ $saved }}.</b><br>
            Te avisaremos cuando tu paquete salga. Si te equivocaste, puedes elegir otra abajo.
        </div>
    @endif

    @if($errors->any())
        <div class="card err">{{ $errors->first() }}</div>
    @endif

    <div class="card sum">
        Envío <b>{{ $shipment->shipment_code }}</b><br>
        Para <b>{{ $shipment->full_name }}</b> · destino <b>{{ $shipment->destination_city ?: '—' }}</b>
    </div>

    @if(count($offices) === 0)
        <div class="card">
            <p class="empty">No pudimos cargar las oficinas de Shalom ahora mismo. Inténtalo de nuevo en unos minutos o escríbenos.</p>
        </div>
    @else
        <form method="POST" action="{{ route('shipments.public.office_store', ['external_id' => $order->external_id]) }}" class="card" id="f">
            @csrf
            <div class="muted">{{ $scopeText }}</div>
            <input type="search" class="find" id="q" placeholder="Busca tu oficina: nombre, zona o calle" aria-label="Buscar oficina">
            <button type="button" class="near" id="near" hidden>📍 Usar mi ubicación para ver la más cercana</button>
            <input type="hidden" name="agency_office_id" id="sel" value="{{ $sel }}">
            <div id="list" role="radiogroup" aria-label="Oficinas de Shalom">
                @foreach($offices as $o)
                    <button type="button" class="opt" role="radio" data-id="{{ $o['id'] }}"
                            data-lat="{{ $o['lat'] }}" data-lng="{{ $o['lng'] }}"
                            data-text="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($o['name'] . ' ' . ($o['district'] ?? '') . ' ' . ($o['address'] ?? ''))) }}"
                            aria-checked="{{ (string) $sel === (string) $o['id'] ? 'true' : 'false' }}">
                        <b>{{ $o['name'] }}</b>
                        @if(!empty($o['address']))<span>{{ $o['address'] }}</span>@endif
                        @if(!empty($o['hours']))<span>🕒 {{ $o['hours'] }}</span>@endif
                        @if(!empty($o['district']))<i>{{ $o['district'] }}</i>@endif
                        <i class="km"></i>
                    </button>
                @endforeach
            </div>
            <p class="empty" id="none" hidden>Ninguna oficina coincide con tu búsqueda.</p>
            <div class="bar">
                <button type="submit" class="btn" id="go" {{ $sel ? '' : 'disabled' }}>Guardar mi oficina</button>
            </div>
        </form>
    @endif
</div>

<script>
(function () {
    var list = document.getElementById('list');
    if (!list) return;
    var sel = document.getElementById('sel'), go = document.getElementById('go');
    var q = document.getElementById('q'), none = document.getElementById('none'), near = document.getElementById('near');
    var opts = Array.prototype.slice.call(list.querySelectorAll('.opt'));

    list.addEventListener('click', function (e) {
        var b = e.target.closest('.opt');
        if (!b) return;
        opts.forEach(function (o) { o.setAttribute('aria-checked', o === b ? 'true' : 'false'); });
        sel.value = b.getAttribute('data-id');
        go.disabled = false;
    });

    function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim(); }
    q.addEventListener('input', function () {
        var t = norm(q.value), n = 0;
        opts.forEach(function (o) {
            var ok = !t || o.getAttribute('data-text').indexOf(t) !== -1;
            o.hidden = !ok; if (ok) n++;
        });
        none.hidden = n > 0;
    });

    // Ordenar por cercanía con el GPS del cliente.
    if (navigator.geolocation && opts.length > 1) near.hidden = false;
    near.addEventListener('click', function () {
        near.textContent = 'Buscando tu ubicación…';
        navigator.geolocation.getCurrentPosition(function (p) {
            var a = { lat: p.coords.latitude, lng: p.coords.longitude }, r = Math.PI / 180;
            var km = function (o) {
                var lat = parseFloat(o.getAttribute('data-lat')), lng = parseFloat(o.getAttribute('data-lng'));
                if (isNaN(lat) || isNaN(lng)) return 1e9;
                var dLat = (lat - a.lat) * r, dLng = (lng - a.lng) * r;
                var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a.lat * r) * Math.cos(lat * r) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                return 2 * 6371 * Math.asin(Math.sqrt(h));
            };
            opts.sort(function (x, y) { return km(x) - km(y); });
            opts.forEach(function (o, i) {
                var d = km(o), el = o.querySelector('.km');
                el.textContent = d < 1e9 ? 'a ' + (d < 10 ? d.toFixed(1) : Math.round(d)) + ' km' : '';
                el.className = 'km' + (i === 0 && d < 1e9 ? ' best' : '');
                if (i === 0 && d < 1e9) el.textContent = 'Más cercana · ' + el.textContent;
                list.appendChild(o);
            });
            near.hidden = true;
        }, function () {
            near.textContent = 'No pudimos ver tu ubicación. Busca tu oficina por nombre.';
        }, { timeout: 10000 });
    });
})();
</script>
</body>
</html>
