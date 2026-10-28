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
        body { background: oklch(0.925 0.006 250); padding: 0 0 20px; margin: 0; }
        .batch, .manifest-wrap { margin-left: 10px; margin-right: 10px; }
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

{{-- Barra de impresión: el mismo lenguaje que la pantalla de Shalom
     (partials/shalom-print-ui). No se imprime. --}}
@include('tenant.shipments.partials.print-bar-styles')
@include('tenant.shipments.partials.print-embed-js')

<div class="pb no-print">
    <div class="pb-bar">
        <div class="pb-t">
            <b>{{ isset($batch) && $batch ? 'Lote ' . $batch->code : 'Rótulos' }}</b>
            <span>{{ count($items) }} {{ count($items) === 1 ? 'rótulo' : 'rótulos' }} de ebaemy{{ collect($shalom ?? [])->count() ? ' · ' . collect($shalom)->count() . ' por Shalom aparte' : '' }}</span>
        </div>
        <div class="pb-sp"></div>
        @php
            $conManifiesto = collect($items)->merge(collect($shalom ?? [])->map(fn ($s) => ['shipment' => $s]))
                ->filter(fn ($it) => $it['shipment']->entersManifest())->count();
        @endphp
        @if(count($items))
            <div class="pb-seg" role="group" aria-label="Papel">
                @foreach(['a4' => 'A4', 'a5' => 'A5'] as $fk => $fl)
                    <button type="button" class="js-fmt" data-fmt="{{ $fk }}" aria-pressed="{{ $format === $fk ? 'true' : 'false' }}">{{ $fl }}</button>
                @endforeach
            </div>
            <label class="pb-chk"><input type="checkbox" id="mfToggle" checked onchange="document.body.classList.toggle('no-manifest', !this.checked)"> Hoja de manifiesto</label>
            <button type="button" class="pb-btn pb-btn--primary" onclick="window.print()">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7V2h10v5M5 15H3V8h14v7h-2"/><rect x="5" y="12" width="10" height="6"/></svg>
                Imprimir {{ count($items) }}{{ $conManifiesto ? ' + manifiesto' : '' }}
            </button>
        @elseif($conManifiesto)
            {{-- Lote todo de Shalom: sus rótulos salen del PDF de Shalom, pero el
                 cargo de despacho (manifiesto) es del lote y se imprime aquí. --}}
            <button type="button" class="pb-btn pb-btn--primary" onclick="window.print()">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7V2h10v5M5 15H3V8h14v7h-2"/><rect x="5" y="12" width="10" height="6"/></svg>
                Imprimir manifiesto ({{ $conManifiesto }})
            </button>
        @endif
        <button type="button" class="pb-btn" onclick="ebImpresion.cerrar()">Cerrar</button>
    </div>
</div>

@if(!empty($skipped) && count($skipped))
    @php
        // Motivo por envio: pago pendiente, anulado, ya despachado o recojo en tienda.
        $reasonMap = isset($reasons) && is_array($reasons) ? $reasons : [];
    @endphp
    <div class="pb no-print"><div class="pb-box pb-warn">
        <b>No entran {{ count($skipped) }}</b> (los anulados y los que ya salieron se excluyen siempre de la impresión masiva):
        {{ collect($skipped)->map(fn ($s) => ($s->shipment_code ?: ('#' . $s->id)) . ' (' . ($reasonMap[$s->id] ?? 'No elegible') . ')')->implode(', ') }}.
    </div></div>
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
    <div class="pb no-print"><div class="pb-box" id="shBox">
        <h2><span class="pb-dot" aria-hidden="true"></span>Shalom · {{ $shalom->count() }} {{ $shalom->count() === 1 ? 'envío' : 'envíos' }} con su rótulo oficial</h2>
        <p>
            Van por Shalom con tu cuenta: llevan el rótulo de Shalom, no el de ebaemy, y primero necesitan su guía.
            {{ $shConGuia->count() }} con guía · {{ $shSinGuia->count() }} sin guía.
        </p>
        <div class="pb-acts">
            @if($shSinGuia->count())
                <button type="button" class="pb-btn pb-btn--primary" id="shCrear" data-ids="{{ $shSinGuia->pluck('id')->implode(',') }}">
                    Crear {{ $shSinGuia->count() }} {{ $shSinGuia->count() === 1 ? 'guía' : 'guías' }} que faltan
                </button>
            @endif
            <a id="shRotulos" class="pb-btn {{ $shSinGuia->count() ? '' : 'pb-btn--primary' }}" target="_blank"
               href="{{ url('registro-envio/shalom-lote') }}?ids={{ $shalom->pluck('id')->implode(',') }}"
               aria-disabled="{{ $shConGuia->count() ? 'false' : 'true' }}">
                Rótulos de Shalom ({{ $shConGuia->sum(fn ($s) => max(1, (int) ($s->package_count ?: 1))) }})
            </a>
            <a class="pb-btn js-sh-link" target="_blank"
               href="{{ url('registro-envio/shalom-lote') }}?doc=ticket&ids={{ $shalom->pluck('id')->implode(',') }}"
               aria-disabled="{{ $shConGuia->count() ? 'false' : 'true' }}">
                Tickets con QR ({{ $shConGuia->count() }})
            </a>
        </div>
        <div class="pb-res" id="shRes" aria-live="polite"></div>
    </div></div>
    @if(!count($items))
        <p class="pb no-print pb-empty">Este lote no tiene rótulos de ebaemy: todos sus envíos van por Shalom.</p>
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
                    var r = await fetch({!! json_encode(url('registro-envio/shalom-guias-lote')) !!}, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!} },
                        body: JSON.stringify({ ids: ids.slice(i, i + 10) }),
                    });
                    var d = await r.json();
                    (d.results || []).forEach(function (x) { if (x.ok) ok++; else mal.push(x); });
                } catch (e) { mal.push({ code: 'Tanda ' + (i / 10 + 1), message: 'sin respuesta; revisa en pro.shalom.pe antes de reintentar' }); }
            }
            res.innerHTML = '<b style="color:var(--ok)">' + ok + (ok === 1 ? ' guía creada.' : ' guías creadas.') + '</b>' + (mal.length
                ? '<ul>' + mal.map(function (x) { var li = document.createElement('li'); li.textContent = x.code + ': ' + x.message; return li.outerHTML; }).join('') + '</ul>'
                : '');
            // Sin recargar: en un lote ya impreso, volver a abrirlo pide motivo de
            // reimpresión. La hoja de Shalom solo toma los que ya tienen guía.
            if (ok) {
                btn.textContent = 'Guías creadas';
                document.querySelectorAll('#shBox a').forEach(function (a) { a.setAttribute('aria-disabled', 'false'); });
                var rot = document.getElementById('shRotulos');
                rot.textContent = 'Rótulos de Shalom'; rot.classList.add('pb-btn--primary');
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
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    });
})();
</script>

@include('tenant.shipments.partials.label-fit-js')

</body>
</html>
