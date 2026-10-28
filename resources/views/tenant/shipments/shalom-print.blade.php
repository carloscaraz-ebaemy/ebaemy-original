<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc === 'ticket' ? 'Ticket' : 'Rótulo' }} de Shalom · {{ $shipment->tracking_number ?: $shipment->shipment_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #e5e7eb; color: #0f172a; }
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center;
               padding: 10px 12px; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .bar a, .bar button { border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 9px; padding: 8px 14px;
               font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .bar a.on { background: #0f172a; border-color: #0f172a; color: #fff; }
        .bar .go { background: #16a34a; border-color: #16a34a; color: #fff; }
        .bar small { color: #64748b; font-size: 12.5px; width: 100%; text-align: center; }
        iframe { display: block; width: 100%; height: calc(100vh - 92px); border: 0; background: #fff; }
    </style>
</head>
<body>
    {{-- El PDF es el oficial de Shalom. Esta página existe para que imprimirlo
         cuente como «rótulo impreso» (contador, chip y bitácora), igual que el
         rótulo de ebaemy: abrir el PDF suelto no dejaba rastro. --}}
    <div class="bar">
        <a href="?doc=rotulo" class="{{ $doc === 'rotulo' ? 'on' : '' }}">Rótulo</a>
        <a href="?doc=ticket" class="{{ $doc === 'ticket' ? 'on' : '' }}">Ticket con QR</a>
        <button type="button" class="go" id="imprimir">🖨 Imprimir</button>
        <small>
            Guía {{ $shipment->tracking_number ?: '—' }}{{ $shipment->tracking_code ? ' · código ' . $shipment->tracking_code : '' }}
            {{ !empty($shipment->shalom_air) ? '· ✈ aérea' : '' }}
            · El ticket trae el QR que escanea la agencia.
        </small>
    </div>
    <iframe id="pdf" src="{{ $pdfUrl }}" title="Documento de Shalom"></iframe>

<script>
(function () {
    var avisado = false;
    function registrar() {
        if (avisado) return;
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
    document.getElementById('imprimir').addEventListener('click', function () {
        var f = document.getElementById('pdf');
        try { f.contentWindow.focus(); f.contentWindow.print(); }
        catch (e) { window.open(f.src, '_blank'); }
        registrar();
    });
})();
</script>
</body>
</html>
