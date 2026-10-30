<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulo · {{ $shipment->shipment_code }}</title>
    @include('tenant.shipments.partials.print-bar-styles')
    <style>
        body { margin: 0; background: oklch(0.925 0.006 250); }
        .ch { max-width: 600px; margin: 40px auto; padding: 0 16px; }
        .ch-card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 22px 24px; }
        .ch-card h1 { font-size: 17px; font-weight: 650; margin: 0 0 4px; }
        .ch-card > p { margin: 0; color: var(--ink-2); font-size: 13.5px; }
        .ch-ped { margin: 14px 0 0; padding: 10px 14px; background: var(--panel-2); border-radius: 9px; font-size: 13px; color: var(--ink-2); }
        .ch-ped b { color: var(--ink); }
        .ch-op { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line); }
        .ch-op h2 { font-size: 14.5px; font-weight: 650; margin: 0 0 2px; display: flex; align-items: center; gap: 8px; }
        .ch-op h2 .n { width: 22px; height: 22px; border-radius: 50%; display: inline-grid; place-items: center; font-size: 12px; background: var(--accent-soft); color: var(--accent); }
        .ch-op p { margin: 0 0 12px 30px; color: var(--ink-2); font-size: 13px; }
        .ch-form { margin-left: 30px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
        .ch-f label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        .ch-f select, .ch-f input { height: 38px; border: 1px solid var(--line-2); border-radius: 8px; background: var(--panel); font: 500 14px/1 inherit; color: var(--ink); padding: 0 10px; }
        .ch-f input { width: 72px; text-align: center; }
        .ch-msg { margin: 10px 0 0 30px; font-size: 13px; }
        .ch-msg.err { color: var(--warn); }
        .ch-msg.ok { color: var(--ok); font-weight: 600; }
        .ch-why { margin: 0 0 0 30px; padding: 9px 12px; border-radius: 8px; background: var(--warn-soft); color: oklch(0.42 0.1 65); font-size: 13px; }
        .ch-op.destacar { background: var(--accent-soft); margin-left: -24px; margin-right: -24px; padding-left: 24px; padding-right: 24px; }
        .ch-acts { display: flex; justify-content: flex-end; margin-top: 18px; }
        @media (max-width: 560px) { .ch-op p, .ch-form, .ch-msg, .ch-why { margin-left: 0; } }
    </style>
</head>
<body>
@include('tenant.shipments.partials.print-embed-js')
<div class="pb ch">
    <div class="ch-card">
        <h1>Este paquete va por Shalom y aún no tiene guía</h1>
        <p>Elige cómo sale. Puedes crear la guía ahora o imprimir el rótulo de la tienda y sacar la guía en el mostrador, como antes.</p>

        <div class="ch-ped">
            <b>{{ $shipment->publicRef() ?: $shipment->shipment_code }}</b> · {{ $shipment->full_name }}
            @if($shipment->agency_office_name) · Oficina {{ $shipment->agency_office_name }}@endif
            @if($shipment->destination_city) · {{ $shipment->destination_city }}@endif
        </div>

        <div class="ch-op" id="opGuia">
            <h2><span class="n">1</span>Crear la guía de Shalom ahora</h2>
            <p>Se crea en tu cuenta de Shalom y sale su rótulo oficial, uno por caja.</p>
            @if($bloqueo || !$crearUrl)
                <div class="ch-why">No se puede crear desde aquí: {{ $bloqueo ?: 'el envío no está unido a un pedido.' }}</div>
            @else
                <form class="ch-form" id="fGuia">
                    <div class="ch-f">
                        <label for="gBultos">Bultos</label>
                        <input id="gBultos" type="number" min="1" max="50" value="{{ max(1, (int) ($shipment->package_count ?: 1)) }}" required>
                    </div>
                    <div class="ch-f">
                        <label for="gTam">Tamaño</label>
                        <select id="gTam" required>
                            @unless($tipico)<option value="">Elige…</option>@endunless
                            @foreach($tamanos as $k => $l)
                                <option value="{{ $k }}" {{ $tipico === $k ? 'selected' : '' }}>{{ $k === 'SOBRE' ? 'Sobre' : 'Caja ' . $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="pb-btn pb-btn--primary" id="gBtn">Crear guía e imprimir</button>
                </form>
                <div class="ch-msg" id="gMsg" aria-live="polite"></div>
            @endif
        </div>

        <div class="ch-op {{ $bloqueo ? 'destacar' : '' }}" id="opTienda">
            <h2><span class="n">2</span>Imprimir el rótulo de la tienda</h2>
            <p>Para pedidos de antes de conectar Shalom, o si Shalom no deja crear la guía. Llevas el paquete al mostrador y ahí te dan la guía; luego la subes con «Subir guía de la agencia».</p>
            <form class="ch-form" method="POST" action="{{ $tiendaUrl }}">
                @csrf
                <button type="submit" class="pb-btn {{ $bloqueo ? 'pb-btn--primary' : '' }}">Imprimir rótulo de la tienda</button>
            </form>
        </div>

        <div class="ch-acts">
            <button type="button" class="pb-btn" onclick="ebImpresion.cerrar()">Cancelar</button>
        </div>
    </div>
</div>
@if(!$bloqueo && $crearUrl)
<script>
(function () {
    var f = document.getElementById('fGuia'), btn = document.getElementById('gBtn'), msg = document.getElementById('gMsg');
    f.addEventListener('submit', async function (e) {
        e.preventDefault();
        var tam = document.getElementById('gTam').value;
        if (!tam) { msg.className = 'ch-msg err'; msg.textContent = 'Elige el tamaño del paquete.'; return; }
        btn.disabled = true; btn.textContent = 'Creando guía en Shalom…';
        msg.className = 'ch-msg'; msg.textContent = 'Puede tardar unos segundos.';
        var d = {};
        try {
            var r = await fetch({!! json_encode($crearUrl) !!}, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!} },
                body: JSON.stringify({ content: tam, bultos: +document.getElementById('gBultos').value || 1 }),
            });
            try { d = await r.json(); } catch (x) { d = { message: r.status === 429 ? 'Demasiados intentos seguidos: espera un minuto.' : 'Shalom no respondió (error ' + r.status + ').' }; }
            if (r.status === 422 && d.errors) d.message = Object.values(d.errors).flat().join(' ');
        } catch (x) {
            d = { message: 'Sin conexión. Antes de reintentar, revisa en pro.shalom.pe que la guía no se haya creado.' };
        }
        if (d.success) {
            msg.className = 'ch-msg ok'; msg.textContent = (d.message || 'Guía creada.') + ' Abriendo el rótulo…';
            location.href = {!! json_encode($imprimirShalom) !!};
            return;
        }
        // Falló: el motivo exacto, y la otra salida a la vista.
        msg.className = 'ch-msg err';
        msg.textContent = (d.message || 'Shalom no creó la guía.') + ' Corrígelo y vuelve a intentar, o imprime el rótulo de la tienda (opción 2).';
        document.getElementById('opTienda').classList.add('destacar');
        btn.disabled = false; btn.textContent = 'Reintentar';
    });
})();
</script>
@endif
</body>
</html>
