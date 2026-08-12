<!DOCTYPE html>
<html lang="es">
@php
    $format = in_array($format ?? 'a4', ['a5','a4'], true) ? ($format ?? 'a4') : 'a4';
    $pageSize   = ['a5' => 'A5', 'a4' => 'A4'][$format];
    $pageMargin = $format === 'a5' ? '8mm' : '12mm';
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulos ({{ count($items) }}) — {{ strtoupper($format) }}</title>
    {{-- La regla @page se reescribe al cambiar de formato (script del pie), sin
         volver al servidor: así el cambio de tamaño no cuenta como reimpresión
         del lote ni ensucia el historial con motivos falsos. --}}
    <style id="pageStyle">@page { size: {{ $pageSize }}; margin: {{ $pageMargin }}; }</style>
    <style>
        body { background: #e5e7eb; padding: 64px 10px 20px; }
        /* Un rótulo por hoja: salto de página después de cada uno. */
        .batch .label { margin: 0 auto 14px; page-break-after: always; break-after: page; }
        .batch .label:last-child { page-break-after: auto; break-after: auto; margin-bottom: 0; }
        @media print { body { background: #fff; padding: 0; } .batch .label { margin: 0 auto; } }
        /* Manifiesto (hoja final) desactivable desde la barra. */
        .manifest-wrap { margin: 24px auto 0; max-width: 100%; background: #fff; padding: 18px; }
        body.no-manifest .manifest-wrap { display: none; }
        @media print { .manifest-wrap { margin: 0; padding: 0; } }
    </style>
    @include('tenant.shipments.partials.label-styles')
</head>
<body class="fmt-{{ $format }}">

<div class="no-print" style="position:fixed;top:12px;left:0;right:0;z-index:999;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
    <div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:5px;display:inline-flex;gap:4px;align-items:center;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">
        <span style="font-size:12px;color:#666;padding:0 6px;">Formato:</span>
        @foreach(['a5'=>'A5','a4'=>'A4'] as $fk => $fl)
            <button type="button" class="js-fmt" data-fmt="{{ $fk }}"
                    style="border:none;cursor:pointer;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;{{ $format === $fk ? 'background:#4f46e5;color:#fff;' : 'background:#f1f3f5;color:#333;' }}">{{ $fl }}</button>
        @endforeach
    </div>
    <label style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:0 12px;display:inline-flex;gap:6px;align-items:center;font-size:13px;color:#333;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);cursor:pointer;">
        <input type="checkbox" id="mfToggle" checked onchange="document.body.classList.toggle('no-manifest', !this.checked)"> Hoja de manifiesto
    </label>
    <button onclick="window.print()" style="padding:10px 20px;background:#198754;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">🖨️ Imprimir {{ count($items) }} rótulos</button>
    <button onclick="window.close()" style="padding:10px 14px;background:#6c757d;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">✕ Cerrar</button>
</div>

@if(!empty($skipped) && count($skipped))
    <div class="no-print" style="max-width:820px;margin:0 auto 14px;background:#fff8e1;border:1px solid #f6d365;border-radius:10px;padding:12px 16px;font-size:13px;color:#8a5a00;">
        ⚠️ Se omitieron <b>{{ count($skipped) }}</b> envío(s) por <b>pago sin confirmar</b>:
        {{ collect($skipped)->map(fn($s) => $s->shipment_code ?: ('#'.$s->id))->implode(', ') }}.
        Confirma su pago para incluirlos en el rótulo.
    </div>
@endif

<div class="batch">
    @foreach($items as $it)
        @include('tenant.shipments.partials.label-body', ['shipment' => $it['shipment'], 'ubigeo' => $it['ubigeo'], 'qr' => $it['qr'], 'barcode' => $it['barcode']])
    @endforeach
</div>

{{-- Hoja de manifiesto (cargo de despacho): resumen de toda la tanda.
     El recojo en tienda NO entra: no forma parte del despacho. --}}
@php
    $manifestItems = collect($items)->filter(fn ($it) => $it['shipment']->entersManifest())->values()->all();
@endphp
@if(count($manifestItems))
    <div class="manifest-wrap">
        @include('tenant.shipments.partials.label-manifest', [
            'items'     => $manifestItems,
            'company'   => $company,
            'printedAt' => now(),
            'batch'     => $batch ?? null,
        ])
    </div>
@endif

<script>
/* Cambio de formato del lote sin recargar: mismo criterio que el rótulo
   individual. Antes recargaba el endpoint y, en un lote ya impreso, cada
   cambio de tamaño quedaba en el historial como una "reimpresión". */
(function () {
    var PAGE = { a5: { size: 'A5', margin: '8mm' }, a4: { size: 'A4', margin: '12mm' } };

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.js-fmt') : null;
        if (!btn) return;

        var fmt = btn.getAttribute('data-fmt');
        var cfg = PAGE[fmt];
        if (!cfg) return;

        // Se conserva `no-manifest` (el toggle de la hoja de manifiesto).
        var noManifest = document.body.classList.contains('no-manifest');
        document.body.className = 'fmt-' + fmt + (noManifest ? ' no-manifest' : '');

        // El alto util cambia con el formato: hay que recalcular el encogido.
        if (window.ajustarRotulos) setTimeout(window.ajustarRotulos, 0);

        var style = document.getElementById('pageStyle');
        if (style) style.textContent = '@page { size: ' + cfg.size + '; margin: ' + cfg.margin + '; }';

        document.querySelectorAll('.js-fmt').forEach(function (b) {
            var on = b === btn;
            b.style.background = on ? '#4f46e5' : '#f1f3f5';
            b.style.color      = on ? '#fff' : '#333';
        });
    });
})();
</script>

@include('tenant.shipments.partials.label-fit-js')

</body>
</html>
