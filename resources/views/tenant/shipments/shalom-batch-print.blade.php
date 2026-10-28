<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc === 'ticket' ? 'Tickets' : 'Rótulos' }} de Shalom · {{ $listos->count() }} envíos</title>
    @include('tenant.shipments.partials.shalom-print-styles')
    <style>
        .fuera { max-width: 720px; margin: 12px auto 0; padding: 10px 14px; border-radius: 10px; background: #fff7e6; color: #8a5300; font-size: 13px; }
        .fuera ul { margin: 6px 0 0; padding-left: 18px; }
    </style>
</head>
<body>
    {{-- Rótulos (o tickets) OFICIALES de Shalom de toda una tanda, en un solo
         PDF: una copia por bulto y en el papel elegido. Imprimir registra la
         impresión de cada envío igual que el rótulo suelto. --}}
    @php
        $q = ['ids' => request('ids')];
        $total = $listos->sum(fn ($s) => $doc === 'rotulo' ? max(1, (int) ($s->package_count ?: 1)) : 1);
        // json_encode y no @json: la directiva se rompe con las comas anidadas.
        $fuentes = $listos->map(fn ($s) => [
            'id'     => $s->id,
            'nombre' => $s->shipment_code,
            'url'    => url('orders/envio/' . $s->id . ($doc === 'ticket' ? '/shalom-ticket' : '/shalom-rotulo')),
            'copies' => $doc === 'rotulo' ? max(1, (int) ($s->package_count ?: 1)) : 1,
            'reimp'  => (int) $s->print_count > 0,
        ])->values();
    @endphp
    <div class="bar">
        <a href="?{{ http_build_query($q + ['doc' => 'rotulo']) }}" class="{{ $doc === 'rotulo' ? 'on' : '' }}">Rótulos</a>
        <a href="?{{ http_build_query($q + ['doc' => 'ticket']) }}" class="{{ $doc === 'ticket' ? 'on' : '' }}">Tickets con QR</a>
        <label class="fmt">Papel <select id="fmt"></select></label>
        <button type="button" class="go" id="imprimir" disabled>🖨 Imprimir {{ $total }}</button>
        <small>
            {{ $listos->count() }} envíos por Shalom ·
            {{ $doc === 'rotulo' ? $total . ' rótulos (uno por caja)' : $total . ' tickets: la agencia escanea el QR para recibir cada paquete' }}
        </small>
    </div>

    @if(count($fuera))
        <div class="fuera">
            No entran {{ count($fuera) }}:
            <ul>@foreach($fuera as [$code, $why])<li><b>{{ $code }}</b>: {{ $why }}</li>@endforeach</ul>
        </div>
    @endif

    @if($listos->isEmpty())
        <div class="estado warn">No hay envíos de Shalom con guía para imprimir. Crea las guías que faltan desde el lote o desde cada pedido.</div>
    @else
        <div id="estado" class="estado">Preparando los documentos de Shalom…</div>
        <iframe id="pdf" title="Documentos de Shalom" hidden></iframe>

        @include('tenant.shipments.partials.shalom-pdf-js')
        <script>
        (function () {
            var DOC = @json($doc);
            var FUENTES = {!! json_encode($fuentes, JSON_UNESCAPED_UNICODE) !!};
            var sel = document.getElementById('fmt'), f = document.getElementById('pdf'),
                est = document.getElementById('estado'), btn = document.getElementById('imprimir');
            ShalomPdf.POR_DOC[DOC].forEach(function (k) {
                var o = document.createElement('option'); o.value = k; o.textContent = ShalomPdf.FORMATOS[k].label; sel.appendChild(o);
            });
            sel.value = ShalomPdf.recordado(DOC);

            function armar() {
                btn.disabled = true; f.hidden = true; est.hidden = false; est.className = 'estado';
                ShalomPdf.build(FUENTES, sel.value, function (i, n) { est.textContent = 'Bajando de Shalom ' + (i + 1) + ' de ' + n + '…'; })
                    .then(function (url) { f.src = url; f.hidden = false; est.hidden = true; btn.disabled = false; })
                    .catch(function (e) {
                        est.className = 'estado warn';
                        est.textContent = (e && e.message ? e.message + ' ' : '') + 'Imprime ese envío desde su pedido («Guía de Shalom») y vuelve a intentar con el resto.';
                    });
            }
            sel.addEventListener('change', function () { ShalomPdf.recordar(DOC, sel.value); armar(); });
            armar();

            var avisado = false;
            btn.addEventListener('click', function () {
                try { f.contentWindow.focus(); f.contentWindow.print(); }
                catch (e) { window.open(f.src, '_blank'); }
                // Solo el rótulo cuenta como «impreso»: el ticket es para la agencia.
                if (avisado || DOC !== 'rotulo') return;
                avisado = true;
                FUENTES.forEach(function (s) {
                    fetch('{{ url("registro-envio") }}/' + s.id + '/impreso', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ format: 'sticker', motivo: s.reimp ? 'Reimpresión del rótulo de Shalom (lote)' : null }),
                    }).catch(function () {});
                });
            });
        })();
        </script>
    @endif
</body>
</html>
