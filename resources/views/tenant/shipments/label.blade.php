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
        body { background: #f5f5f5; display: flex; justify-content: center; padding: 64px 20px 20px; }
        @media print { body { background: #fff; padding: 0; } }
    </style>
    @include('tenant.shipments.partials.label-styles')
</head>
<body class="fmt-{{ $format }}">

<div class="no-print" style="position:fixed;top:12px;left:0;right:0;z-index:999;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
    <div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:5px;display:inline-flex;gap:4px;align-items:center;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">
        <span style="font-size:12px;color:#666;padding:0 6px;">Formato:</span>
        @foreach(['sticker'=>'Sticker','a5'=>'A5','a4'=>'A4'] as $fk => $fl)
            <button type="button" class="js-fmt" data-fmt="{{ $fk }}"
                    style="border:none;cursor:pointer;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;{{ $format === $fk ? 'background:#4f46e5;color:#fff;' : 'background:#f1f3f5;color:#333;' }}">{{ $fl }}</button>
        @endforeach
    </div>
    <button onclick="window.print()" style="padding:9px 18px;background:#198754;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">🖨️ Imprimir</button>
    <button onclick="window.close()" style="padding:9px 14px;background:#6c757d;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">✕</button>
</div>

@include('tenant.shipments.partials.label-body')

<script>
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
            b.style.background = on ? '#4f46e5' : '#f1f3f5';
            b.style.color      = on ? '#fff' : '#333';
        });

        document.title = 'Rótulo ' + fmt.toUpperCase() + ' — {{ $shipment->shipment_code }}';
    });
})();
</script>

@include('tenant.shipments.partials.label-fit-js')

</body>
</html>
