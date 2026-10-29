@php
    $format = in_array($format ?? 'a4', ['sticker','a5','a4'], true) ? ($format ?? 'a4') : 'a4';
    $pageSize   = ['sticker' => 'auto', 'a5' => 'A5', 'a4' => 'A4'][$format];
    $pageMargin = $format === 'sticker' ? '4mm' : ($format === 'a5' ? '8mm' : '12mm');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulo {{ strtoupper($format) }} — {{ $shipment->shipment_code }}</title>
    {{-- La regla @page se reescribe al cambiar de formato (ver el script del pie):
         el cambio es puramente visual y NO vuelve al servidor, así que no cuenta
         como una impresión nueva ni choca con la regla de reimpresión. --}}
    <style id="pageStyle">@page { size: {{ $pageSize }}; margin: {{ $pageMargin }}; }</style>
    <style>
        body { background: oklch(0.925 0.006 250); margin: 0; padding: 0 0 20px; }
        body > .label { margin: 0 auto; }
        @media print { body { background: #fff; padding: 0; } }
    </style>
    @include('tenant.shipments.partials.label-styles')
</head>
<body class="fmt-{{ $format }}">

@include('tenant.shipments.partials.print-bar-styles')
@include('tenant.shipments.partials.print-embed-js')
<div class="pb no-print">
    <div class="pb-bar">
        <div class="pb-t">
            <b>Rótulo {{ $shipment->shipment_code }}</b>
            <span>{{ $shipment->full_name }}{{ $shipment->package_count > 1 ? ' · ' . $shipment->package_count . ' bultos, un rótulo por caja' : '' }}</span>
        </div>
        <div class="pb-sp"></div>
        <div class="pb-seg" role="group" aria-label="Papel">
            @foreach(['a4' => 'A4', 'a5' => 'A5', 'sticker' => 'Etiqueta'] as $fk => $fl)
                <button type="button" class="js-fmt" data-fmt="{{ $fk }}" aria-pressed="{{ $format === $fk ? 'true' : 'false' }}">{{ $fl }}</button>
            @endforeach
        </div>
        <button type="button" class="pb-btn pb-btn--primary" onclick="window.print()">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7V2h10v5M5 15H3V8h14v7h-2"/><rect x="5" y="12" width="10" height="6"/></svg>
            Imprimir
        </button>
        <button type="button" class="pb-btn" onclick="ebImpresion.cerrar()">Cerrar</button>
    </div>
</div>

@php $bultoDe = max(1, (int) ($shipment->package_count ?: 1)); @endphp
@if($bultoDe > 1)
    @for($bultoN = 1; $bultoN <= $bultoDe; $bultoN++)
        @include('tenant.shipments.partials.label-body', ['bultoN' => $bultoN, 'bultoDe' => $bultoDe])
    @endfor
@else
    @include('tenant.shipments.partials.label-body')
@endif

<script>
/*
 * Registrar la impresion DE VERDAD.
 *
 * Abrir esta pagina ya no cuenta: el contador y el chip de documentos se
 * encienden cuando el navegador termina de imprimir, que es lo que dice
 * `afterprint`. Antes se registraba al abrirla, asi que mirar el rotulo para
 * comprobar una direccion lo daba por impreso.
 *
 * Se manda UNA sola vez por pagina: `afterprint` se dispara tambien al
 * cancelar el dialogo del sistema, y no hay forma de distinguirlo desde el
 * navegador. Entre contar de mas cada vez que alguien abre el dialogo y
 * contar una impresion que quiza se cancelo, esto ultimo es lo que se parece
 * a lo que hace el operador: abrir el dialogo es ir a imprimir.
 */
(function () {
    var yaAvisado = false;

    function registrarImpresion() {
        if (yaAvisado) return;
        yaAvisado = true;

        var fmt = (document.body.className || '').replace('fmt-', '') || '{{ $format }}';

        fetch('{{ url("registro-envio/" . $shipment->id . "/impreso") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ format: fmt, motivo: @json(request()->query('motivo')) }),
        })
            .then(function () {
                // El listado que abrio esta pestaña sigue mostrando el estado
                // de antes. Se le avisa para que refresque sin que el operador
                // tenga que recargar a mano.
                // Pestaña aparte o diálogo de Pedidos: el aviso llega a los dos.
                ebImpresion.avisar({ tipo: 'rotulo-impreso', envio: {{ $shipment->id }} });
            })
            .catch(function () {
                // Sin registro no se pierde el rotulo: el papel ya salio. Se
                // vera al recargar, que es como estaba antes.
            });
    }

    window.addEventListener('afterprint', registrarImpresion);
})();

/*
 * Cambio de formato SIN recargar.
 * Antes cada botón era un enlace que volvía a `…/imprimir?format=…`, así que
 * tras la primera impresión el contador ya estaba en 1 y la regla de
 * reimpresión bloqueaba el cambio: A4 simplemente no salía. Cambiar el tamaño
 * de papel es una vista distinta del MISMO rótulo, no una impresión nueva.
 */
(function () {
    var PAGE = {
        sticker: { size: 'auto', margin: '4mm' },
        a5:      { size: 'A5',   margin: '8mm' },
        a4:      { size: 'A4',   margin: '12mm' }
    };

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.js-fmt') : null;
        if (!btn) return;

        var fmt = btn.getAttribute('data-fmt');
        var cfg = PAGE[fmt];
        if (!cfg) return;

        document.body.className = 'fmt-' + fmt;

        // El alto util cambia con el formato: hay que recalcular el encogido.
        if (window.ajustarRotulos) setTimeout(window.ajustarRotulos, 0);

        var style = document.getElementById('pageStyle');
        if (style) style.textContent = '@page { size: ' + cfg.size + '; margin: ' + cfg.margin + '; }';

        document.querySelectorAll('.js-fmt').forEach(function (b) {
            var on = b === btn;
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        document.title = 'Rótulo ' + fmt.toUpperCase() + ' — {{ $shipment->shipment_code }}';
    });
})();
</script>

@include('tenant.shipments.partials.label-fit-js')

</body>
</html>
