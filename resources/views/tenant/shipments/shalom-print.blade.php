<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc === 'ticket' ? 'Ticket' : 'Rótulo' }} de Shalom · {{ $shipment->tracking_number ?: $shipment->shipment_code }}</title>
    @include('tenant.shipments.partials.shalom-print-styles')
</head>
<body>
    {{-- El PDF es el oficial de Shalom. Esta página existe para que imprimirlo
         cuente como «rótulo impreso» (contador, chip y bitácora), igual que el
         rótulo de ebaemy, y para acomodarlo al papel de la tienda con una
         copia por bulto (ver partials/shalom-pdf-js). --}}
    @php $bultos = $doc === 'rotulo' ? max(1, (int) ($shipment->package_count ?: 1)) : 1; @endphp
    <div class="bar">
        <a href="?doc=rotulo" class="{{ $doc === 'rotulo' ? 'on' : '' }}">Rótulo</a>
        <a href="?doc=ticket" class="{{ $doc === 'ticket' ? 'on' : '' }}">Ticket con QR</a>
        <label class="fmt">Papel <select id="fmt"></select></label>
        <button type="button" class="go" id="imprimir" disabled>🖨 Imprimir</button>
        <small id="nota">
            Guía {{ $shipment->tracking_number ?: '—' }}{{ $shipment->tracking_code ? ' · código ' . $shipment->tracking_code : '' }}
            {{ !empty($shipment->shalom_air) ? '· ✈ aérea' : '' }}
            · {{ $doc === 'rotulo'
                ? ($bultos > 1 ? $bultos . ' bultos: sale un rótulo para cada caja.' : 'Pégalo en la caja.')
                : 'La agencia escanea este QR para recibir el paquete.' }}
        </small>
    </div>
    <div id="estado" class="estado">Preparando el documento de Shalom…</div>
    <iframe id="pdf" title="Documento de Shalom" hidden></iframe>

@include('tenant.shipments.partials.shalom-pdf-js')
<script>
(function () {
    var DOC = @json($doc), ORIGINAL = @json($pdfUrl), COPIAS = {{ $bultos }};
    var sel = document.getElementById('fmt'), f = document.getElementById('pdf'),
        est = document.getElementById('estado'), btn = document.getElementById('imprimir');
    ShalomPdf.POR_DOC[DOC].forEach(function (k) {
        var o = document.createElement('option'); o.value = k; o.textContent = ShalomPdf.FORMATOS[k].label; sel.appendChild(o);
    });
    sel.value = ShalomPdf.recordado(DOC);

    function mostrar(url) { f.src = url; f.hidden = false; est.hidden = true; btn.disabled = false; }
    function armar() {
        btn.disabled = true; est.hidden = false; est.textContent = 'Preparando el documento de Shalom…';
        ShalomPdf.build([{ url: ORIGINAL, copies: COPIAS }], sel.value)
            .then(mostrar)
            .catch(function () {
                // Sin armar: el PDF tal cual llega de Shalom, avisando las copias.
                mostrar(ORIGINAL);
                if (COPIAS > 1) {
                    est.hidden = false; est.className = 'estado warn';
                    est.textContent = 'Imprime ' + COPIAS + ' copias (una por caja): no se pudo armar el PDF con todas.';
                }
            });
    }
    sel.addEventListener('change', function () { ShalomPdf.recordar(DOC, sel.value); armar(); });
    armar();

    var avisado = false;
    function registrar() {
        if (avisado || DOC !== 'rotulo') return;
        avisado = true;
        fetch('{{ url("registro-envio/" . $shipment->id . "/impreso") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ format: 'sticker', motivo: {!! json_encode($shipment->print_count > 0 ? 'Reimpresión del rótulo de Shalom' : null) !!} }),
        }).then(function () {
            try { if (window.opener && !window.opener.closed) window.opener.postMessage({ tipo: 'rotulo-impreso', envio: {{ $shipment->id }} }, '*'); } catch (e) {}
        }).catch(function () {});
    }
    btn.addEventListener('click', function () {
        try { f.contentWindow.focus(); f.contentWindow.print(); }
        catch (e) { window.open(f.src, '_blank'); }
        registrar();
    });
})();
</script>
</body>
</html>
