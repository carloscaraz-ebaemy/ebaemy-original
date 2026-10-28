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
    @if(count($items))
    <button onclick="window.print()" style="padding:10px 20px;background:#198754;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">🖨️ Imprimir {{ count($items) }} rótulos</button>
    @endif
    <button onclick="window.close()" style="padding:10px 14px;background:#6c757d;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">✕ Cerrar</button>
</div>

@if(!empty($skipped) && count($skipped))
    <div class="no-print" style="max-width:820px;margin:0 auto 14px;background:#fff8e1;border:1px solid #f6d365;border-radius:10px;padding:12px 16px;font-size:13px;color:#8a5a00;">
        @php
            // Motivo por envio: pago pendiente, anulado, ya despachado o recojo en tienda.
            $reasonMap = isset($reasons) && is_array($reasons) ? $reasons : [];
            $skipList = collect($skipped)->map(function ($s) use ($reasonMap) {
                $code = $s->shipment_code ?: ('#' . $s->id);
                $why  = $reasonMap[$s->id] ?? 'No elegible';
                return $code . ' (' . $why . ')';
            })->implode(', ');
        @endphp
        ⚠️ Se omitieron <b>{{ count($skipped) }}</b> envío(s) que no son elegibles para rotular:
        {{ $skipList }}.
        Los anulados y los que ya salieron de la tienda se excluyen siempre de la impresión masiva.
    </div>
@endif


{{-- Shalom con la cuenta de la tienda: otro procedimiento dentro del mismo
     lote. Su rótulo es el PDF oficial y sale de la guía, así que no se mezcla
     con los de ebaemy: primero se crean las guías que faltan y después se
     imprimen juntos (una copia por bulto, en el papel que elija la tienda). --}}
@php
    $shalom = collect($shalom ?? []);
    $shConGuia = $shalom->filter(fn ($s) => \App\Services\Tenant\ShalomTracking::hasGuide($s))->values();
    $shSinGuia = $shalom->reject(fn ($s) => \App\Services\Tenant\ShalomTracking::hasGuide($s))->values();
@endphp
@if($shalom->count())
    <div class="no-print" id="shBox" style="max-width:820px;margin:0 auto 14px;background:#fff;border:1px solid #fecaca;border-left:4px solid #e11d2e;border-radius:10px;padding:14px 16px;font-size:13.5px;color:#1f2937;">
        <div style="font-weight:700;font-size:15px;margin-bottom:4px;">Shalom · {{ $shalom->count() }} envío(s) con su rótulo oficial</div>
        <div style="color:#4b5563;margin-bottom:10px;">
            Van por Shalom con tu cuenta: llevan el rótulo de Shalom (no el de ebaemy) y primero necesitan su guía.
            {{ $shConGuia->count() }} con guía · {{ $shSinGuia->count() }} sin guía.
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($shSinGuia->count())
                <button type="button" id="shCrear" data-ids="{{ $shSinGuia->pluck('id')->implode(',') }}"
                        style="padding:9px 16px;background:#e11d2e;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;">
                    Crear {{ $shSinGuia->count() }} guía(s) que faltan
                </button>
            @endif
            <a id="shRotulos" href="{{ url('registro-envio/shalom-lote') }}?ids={{ $shalom->pluck('id')->implode(',') }}" target="_blank"
               style="padding:9px 16px;background:#0f172a;color:#fff;border-radius:8px;font-size:13.5px;font-weight:600;text-decoration:none;{{ $shConGuia->count() ? '' : 'opacity:.45;pointer-events:none;' }}">
                🖨 Rótulos de Shalom ({{ $shConGuia->sum(fn ($s) => max(1, (int) ($s->package_count ?: 1))) }})
            </a>
            <a href="{{ url('registro-envio/shalom-lote') }}?doc=ticket&ids={{ $shalom->pluck('id')->implode(',') }}" target="_blank"
               style="padding:9px 16px;background:#fff;color:#0f172a;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;font-weight:600;text-decoration:none;{{ $shConGuia->count() ? '' : 'opacity:.45;pointer-events:none;' }}">
                Tickets con QR ({{ $shConGuia->count() }})
            </a>
        </div>
        <div id="shRes" style="margin-top:10px;font-size:13px;"></div>
    </div>
    @if(!count($items))
        <div class="no-print" style="max-width:820px;margin:0 auto 14px;text-align:center;color:#6b7280;font-size:13px;">
            Este lote no tiene rótulos de ebaemy: todos sus envíos van por Shalom.
        </div>
    @endif
    <script>
    (function () {
        var btn = document.getElementById('shCrear');
        if (!btn) return;
        btn.addEventListener('click', async function () {
            var ids = btn.getAttribute('data-ids').split(',').filter(Boolean), res = document.getElementById('shRes'), ok = 0, mal = [];
            btn.disabled = true;
            // De a 10: cada guía tarda unos segundos en Shalom.
            for (var i = 0; i < ids.length; i += 10) {
                btn.textContent = 'Creando guías… ' + Math.min(i + 10, ids.length) + ' de ' + ids.length;
                try {
                    var r = await fetch(@json(url('registro-envio/shalom-guias-lote')), {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ ids: ids.slice(i, i + 10) }),
                    });
                    var d = await r.json();
                    (d.results || []).forEach(function (x) { if (x.ok) ok++; else mal.push(x); });
                } catch (e) { mal.push({ code: 'Tanda ' + (i / 10 + 1), message: 'sin respuesta; revisa en pro.shalom.pe antes de reintentar' }); }
            }
            res.innerHTML = '<b style="color:#15803d">' + ok + ' guía(s) creada(s).</b>' + (mal.length
                ? '<div style="color:#b45309;margin-top:4px">No se crearon ' + mal.length + ':<ul style="margin:4px 0 0;padding-left:18px">'
                  + mal.map(function (x) { var li = document.createElement('li'); li.textContent = x.code + ': ' + x.message; return li.outerHTML; }).join('') + '</ul></div>'
                : '');
            // Sin recargar: en un lote ya impreso, volver a abrirlo pide motivo de
            // reimpresión. La hoja de Shalom solo toma los que ya tienen guía.
            if (ok) {
                btn.textContent = '✓ Guías creadas';
                document.querySelectorAll('#shBox a').forEach(function (a) { a.style.opacity = ''; a.style.pointerEvents = ''; });
                document.getElementById('shRotulos').textContent = '🖨 Rótulos de Shalom';
            } else {
                btn.disabled = false; btn.textContent = 'Reintentar';
            }
        });
    })();
    </script>
@endif

<div class="batch">
    @foreach($items as $it)
        @include('tenant.shipments.partials.label-body', ['shipment' => $it['shipment'], 'ubigeo' => $it['ubigeo'], 'qr' => $it['qr'], 'barcode' => $it['barcode']])
    @endforeach
</div>

{{-- Hoja de manifiesto (cargo de despacho): resumen de toda la tanda.
     El recojo en tienda NO entra: no forma parte del despacho. --}}
@php
    $manifestItems = collect($items)
        ->merge(collect($shalom ?? [])->map(fn ($s) => ['shipment' => $s, 'ubigeo' => null]))
        ->filter(fn ($it) => $it['shipment']->entersManifest())->values()->all();
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
