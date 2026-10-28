{{-- Pantalla de impresión de los documentos de Shalom (rótulo suelto y lote).

     Es un diálogo de impresión: a la izquierda las hojas tal como van a salir,
     a la derecha qué documento, en qué papel y cuántos. La vista previa se
     dibuja con pdf.js sobre el MISMO PDF que se imprime (partials/shalom-pdf-js),
     así lo que se ve es lo que sale.

     Recibe: $titulo, $subtitulo, $doc ('rotulo'|'ticket'), $docUrls, $fuentes
     [{url, copies, nombre}], $resumen [[etiqueta, valor]], $incluidos (lista
     opcional), $fuera [[código, motivo]], $registrar [{id, reimp}], $original
     (PDF tal cual, solo en el suelto). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <style>
        :root {
            --desk: oklch(0.925 0.006 250);
            --panel: oklch(0.992 0.002 250);
            --panel-2: oklch(0.972 0.004 250);
            --ink: oklch(0.24 0.02 258);
            --ink-2: oklch(0.42 0.018 258);
            --muted: oklch(0.56 0.015 258);
            --line: oklch(0.9 0.006 250);
            --line-2: oklch(0.84 0.008 250);
            --accent: oklch(0.47 0.13 262);
            --accent-ink: oklch(0.99 0.003 262);
            --accent-soft: oklch(0.955 0.025 262);
            --ok: oklch(0.48 0.11 150);
            --warn: oklch(0.52 0.11 70);
            --warn-soft: oklch(0.97 0.035 85);
            --r: 10px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { font: 14px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, Roboto, sans-serif; color: var(--ink); background: var(--desk); }
        .app { display: grid; grid-template-columns: 1fr 340px; height: 100vh; }
        @media (max-width: 860px) { .app { grid-template-columns: 1fr; height: auto; } .side { order: -1; border-left: 0; border-bottom: 1px solid var(--line); } }

        /* ── Vista previa ── */
        .stage { overflow: auto; padding: 28px 24px 40px; display: flex; flex-direction: column; align-items: center; gap: 18px; }
        .sheet { background: var(--panel); box-shadow: 0 1px 2px oklch(0.3 0.02 258 / 0.12), 0 6px 18px -6px oklch(0.3 0.02 258 / 0.18); border-radius: 2px; }
        .sheet canvas { display: block; width: 100%; height: auto; }
        .sheet-n { font-size: 12px; color: var(--muted); margin-top: -10px; font-variant-numeric: tabular-nums; }
        .stage-msg { margin: auto; max-width: 420px; text-align: center; color: var(--ink-2); padding: 40px 20px; }
        .stage-msg b { display: block; font-size: 15px; color: var(--ink); margin-bottom: 4px; }
        .skeleton { width: min(560px, 100%); aspect-ratio: 210 / 297; background: linear-gradient(100deg, var(--panel) 40%, var(--panel-2) 50%, var(--panel) 60%) 0 0 / 200% 100%; animation: shimmer 1.4s linear infinite; border-radius: 2px; }
        @keyframes shimmer { to { background-position: -200% 0; } }

        /* ── Panel de opciones ── */
        .side { background: var(--panel); border-left: 1px solid var(--line); display: flex; flex-direction: column; min-height: 0; }
        .side-h { padding: 18px 20px 14px; border-bottom: 1px solid var(--line); }
        .side-h h1 { font-size: 16px; font-weight: 650; margin: 0; letter-spacing: -0.005em; }
        .side-h p { margin: 3px 0 0; color: var(--muted); font-size: 13px; }
        .side-b { padding: 16px 20px; overflow: auto; flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .lbl { font-size: 12px; font-weight: 600; color: var(--ink-2); margin: 0 0 8px; }

        .seg { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; background: var(--panel-2); border: 1px solid var(--line); border-radius: var(--r); padding: 3px; }
        .seg a { text-align: center; padding: 7px 8px; border-radius: 7px; color: var(--ink-2); text-decoration: none; font-weight: 600; font-size: 13px; }
        .seg a:hover { color: var(--ink); }
        .seg a[aria-current="true"] { background: var(--panel); color: var(--ink); box-shadow: 0 1px 2px oklch(0.3 0.02 258 / 0.14); }

        .papers { display: grid; gap: 6px; }
        .paper { display: grid; grid-template-columns: 26px 1fr; gap: 10px; align-items: center; width: 100%; text-align: left; font: inherit; color: inherit; background: var(--panel); border: 1px solid var(--line); border-radius: var(--r); padding: 9px 12px; cursor: pointer; transition: border-color .15s ease-out, background-color .15s ease-out; }
        .paper:hover { border-color: var(--line-2); }
        .paper:focus-visible, .btn:focus-visible, .seg a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        .paper[aria-checked="true"] { border-color: var(--accent); background: var(--accent-soft); }
        .paper svg { width: 22px; height: 26px; color: var(--muted); }
        .paper[aria-checked="true"] svg { color: var(--accent); }
        .paper b { display: block; font-size: 13.5px; font-weight: 600; }
        .paper span { display: block; font-size: 12px; color: var(--muted); }

        .facts { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; margin: 0; font-size: 13px; }
        .facts dt { color: var(--muted); }
        .facts dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }

        .list { margin: 0; padding: 0; list-style: none; border: 1px solid var(--line); border-radius: var(--r); max-height: 168px; overflow: auto; }
        .list li { display: flex; justify-content: space-between; gap: 10px; padding: 7px 12px; font-size: 12.5px; border-top: 1px solid var(--line); }
        .list li:first-child { border-top: 0; }
        .list li span { color: var(--muted); text-align: right; }
        .note { background: var(--warn-soft); color: var(--warn); border-radius: var(--r); padding: 10px 12px; font-size: 12.5px; }
        .note b { color: oklch(0.42 0.1 65); }

        .side-f { padding: 14px 20px 18px; border-top: 1px solid var(--line); display: grid; gap: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 42px; border-radius: var(--r); font: 600 14px/1 inherit; text-decoration: none; cursor: pointer; border: 1px solid var(--line-2); background: var(--panel); color: var(--ink); transition: background-color .15s ease-out, opacity .15s ease-out; }
        .btn:hover { background: var(--panel-2); }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); height: 46px; font-size: 15px; }
        .btn-primary:hover { background: oklch(0.42 0.13 262); }
        .btn[disabled], .btn[aria-disabled="true"] { opacity: .5; cursor: default; pointer-events: none; }
        .btn svg { width: 17px; height: 17px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .done { font-size: 12.5px; color: var(--ok); text-align: center; min-height: 1em; }
    </style>
</head>
<body>
@include('tenant.shipments.partials.print-embed-js')
@php
    $iconos = [
        'a4-2'   => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="20" height="24" rx="1.5"/><path d="M4 13h14"/><rect x="4" y="4" width="14" height="6.5" rx=".5"/><rect x="4" y="15.5" width="14" height="6.5" rx=".5"/></svg>',
        'a4-1'   => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="20" height="24" rx="1.5"/><rect x="4" y="7" width="14" height="10" rx=".5"/></svg>',
        'a5'     => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="6" width="20" height="14" rx="1.5"/><rect x="4" y="9" width="14" height="8" rx=".5"/></svg>',
        'e10x15' => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="1" width="12" height="24" rx="1.5"/><path d="M8 5v16M11 5v16"/></svg>',
        't80'    => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 1h12v22l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5z"/><rect x="8" y="5" width="6" height="6" rx=".5"/><path d="M8 14h6M8 17h4"/></svg>',
        't58'    => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 1h8v22l-2-1.5-2 1.5-2-1.5-2 1.5z"/><rect x="9" y="5" width="4" height="4" rx=".5"/><path d="M9 12h4M9 15h3"/></svg>',
        'a4'     => '<svg viewBox="0 0 22 26" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="20" height="24" rx="1.5"/><rect x="8" y="4" width="6" height="9" rx=".5"/></svg>',
    ];
    $papeles = $doc === 'ticket' ? ['t80', 't58', 'a4'] : ['a4-2', 'a4-1', 'a5', 'e10x15'];
    $total = collect($fuentes)->sum('copies');
    $nombreDoc = $doc === 'ticket' ? ($total === 1 ? 'ticket' : 'tickets') : ($total === 1 ? 'rótulo' : 'rótulos');
@endphp
<div class="app">
    <main class="stage" id="stage" aria-label="Vista previa">
        @if(!count($fuentes))
            <div class="stage-msg"><b>No hay nada que imprimir todavía</b>{{ $vacio ?? 'Crea las guías de Shalom que faltan desde el lote o desde cada pedido.' }}</div>
        @else
            <div class="skeleton" id="skeleton" aria-hidden="true"></div>
        @endif
    </main>

    <aside class="side">
        <header class="side-h">
            <h1>{{ $titulo }}</h1>
            <p>{{ $subtitulo }}</p>
        </header>

        <div class="side-b">
            <section>
                <p class="lbl">Documento</p>
                <nav class="seg" aria-label="Documento">
                    <a href="{{ $docUrls['rotulo'] }}" aria-current="{{ $doc === 'rotulo' ? 'true' : 'false' }}">Rótulo</a>
                    <a href="{{ $docUrls['ticket'] }}" aria-current="{{ $doc === 'ticket' ? 'true' : 'false' }}">Ticket con QR</a>
                </nav>
            </section>

            <section>
                <p class="lbl" id="lblPapel">Papel</p>
                <div class="papers" role="radiogroup" aria-labelledby="lblPapel" id="papers">
                    @foreach($papeles as $k)
                        <button type="button" class="paper" role="radio" data-fmt="{{ $k }}" aria-checked="false">
                            {!! $iconos[$k] !!}
                            <div><b class="js-l"></b><span class="js-d"></span></div>
                        </button>
                    @endforeach
                </div>
            </section>

            <section>
                <p class="lbl">Resumen</p>
                <dl class="facts">
                    @foreach($resumen as [$k, $v])<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach
                    <dt>Hojas</dt><dd id="hojas">…</dd>
                </dl>
            </section>

            @if(count($fuera))
                <section>
                    <p class="lbl">No entran ({{ count($fuera) }})</p>
                    <ul class="list">@foreach($fuera as [$code, $why])<li><b>{{ $code }}</b><span>{{ $why }}</span></li>@endforeach</ul>
                </section>
            @endif
            @if(!empty($incluidos) && count($incluidos) > 1)
                <section>
                    <p class="lbl">Incluye</p>
                    <ul class="list">@foreach($incluidos as [$a, $b])<li><b>{{ $a }}</b><span>{{ $b }}</span></li>@endforeach</ul>
                </section>
            @endif


            <p class="note" id="aviso" hidden></p>
        </div>

        <footer class="side-f">
            <button type="button" class="btn btn-primary" id="imprimir" disabled>
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7V2h10v5M5 15H3V8h14v7h-2"/><rect x="5" y="12" width="10" height="6"/></svg>
                Imprimir {{ $total }} {{ $nombreDoc }}
            </button>
            <div class="row2">
                <a class="btn" id="descargar" aria-disabled="true" download="{{ $doc === 'ticket' ? 'tickets' : 'rotulos' }}-shalom.pdf">Descargar PDF</a>
                <button type="button" class="btn" onclick="ebImpresion.cerrar()">Cerrar</button>
            </div>
            <p class="done" id="hecho" aria-live="polite"></p>
        </footer>
    </aside>
</div>
<iframe id="printFrame" title="Impresión" style="position:absolute;width:0;height:0;border:0;visibility:hidden"></iframe>

@if(count($fuentes))
@include('tenant.shipments.partials.shalom-pdf-js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    var DOC = {!! json_encode($doc) !!};
    var FUENTES = {!! json_encode(array_values($fuentes), JSON_UNESCAPED_UNICODE) !!};
    var REGISTRAR = {!! json_encode(array_values($registrar ?? []), JSON_UNESCAPED_UNICODE) !!};
    var ORIGINAL = {!! json_encode($original ?? null) !!};
    var TOTAL = {{ (int) $total }};
    var stage = document.getElementById('stage'), btn = document.getElementById('imprimir'),
        dl = document.getElementById('descargar'), aviso = document.getElementById('aviso'),
        hojas = document.getElementById('hojas'), hecho = document.getElementById('hecho'),
        frame = document.getElementById('printFrame');
    var actual = null, seq = 0;
    if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var botones = Array.prototype.slice.call(document.querySelectorAll('.paper'));
    botones.forEach(function (b) {
        var f = ShalomPdf.FORMATOS[b.getAttribute('data-fmt')];
        b.querySelector('.js-l').textContent = f.label;
        b.querySelector('.js-d').textContent = f.desc || '';
        b.addEventListener('click', function () { elegir(b.getAttribute('data-fmt'), true); });
    });

    function decir(texto) { aviso.hidden = !texto; aviso.innerHTML = texto || ''; }
    function listo(on) { btn.disabled = !on; dl.setAttribute('aria-disabled', on ? 'false' : 'true'); }

    function elegir(fmt, recordar) {
        botones.forEach(function (b) { b.setAttribute('aria-checked', b.getAttribute('data-fmt') === fmt ? 'true' : 'false'); });
        if (recordar) ShalomPdf.recordar(DOC, fmt);
        armar(fmt);
    }

    async function dibujar(url, n) {
        stage.innerHTML = '';
        var pdf = await pdfjsLib.getDocument(url).promise;
        if (n !== seq) return;
        hojas.textContent = pdf.numPages;
        var ancho = Math.min(stage.clientWidth - 48, 620);
        var max = Math.min(pdf.numPages, 24);
        for (var i = 1; i <= max; i++) {
            var pg = await pdf.getPage(i);
            if (n !== seq) return;
            var base = pg.getViewport({ scale: 1 });
            var k = Math.min(ancho / base.width, 1.6);
            var vp = pg.getViewport({ scale: k * (window.devicePixelRatio || 1) });
            var hoja = document.createElement('div'); hoja.className = 'sheet'; hoja.style.width = (base.width * k) + 'px';
            var c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
            hoja.appendChild(c); stage.appendChild(hoja);
            var num = document.createElement('div'); num.className = 'sheet-n'; num.textContent = 'Hoja ' + i + ' de ' + pdf.numPages;
            stage.appendChild(num);
            await pg.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
        }
        if (pdf.numPages > max) {
            var mas = document.createElement('div'); mas.className = 'sheet-n';
            mas.textContent = 'y ' + (pdf.numPages - max) + ' hojas más en el PDF';
            stage.appendChild(mas);
        }
    }

    function armar(fmt) {
        var n = ++seq;
        listo(false); decir(''); hojas.textContent = '…';
        stage.innerHTML = '<div class="skeleton" aria-hidden="true"></div>';
        ShalomPdf.build(FUENTES, fmt, function (i, t) { if (t > 1) hecho.textContent = 'Bajando de Shalom ' + (i + 1) + ' de ' + t + '…'; })
            .then(function (url) {
                if (n !== seq) return;
                hecho.textContent = '';
                if (actual) URL.revokeObjectURL(actual);
                actual = url; dl.href = url; frame.src = url;
                listo(true);
                return dibujar(url, n).catch(function () {
                    stage.innerHTML = '<div class="stage-msg"><b>Listo para imprimir</b>La vista previa no se pudo dibujar en este navegador, pero el documento está armado.</div>';
                });
            })
            .catch(function (e) {
                if (n !== seq) return;
                hecho.textContent = '';
                if (ORIGINAL) {
                    // Sin armar: el PDF tal cual de Shalom, avisando las copias.
                    actual = null; dl.href = ORIGINAL; frame.src = ORIGINAL; listo(true);
                    stage.innerHTML = '<div class="stage-msg"><b>Se imprimirá el PDF original de Shalom</b>No se pudo acomodar al papel elegido.</div>';
                    if (TOTAL > 1) decir('Imprime <b>' + TOTAL + ' copias</b>, una por caja.');
                } else {
                    stage.innerHTML = '<div class="stage-msg"><b>No se pudo preparar el documento</b>' + ((e && e.message) || '') + ' Imprime ese envío desde su pedido («Guía de Shalom») y vuelve a intentar.</div>';
                }
            });
    }

    btn.addEventListener('click', function () {
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (e) { window.open(frame.src, '_blank'); }
        if (!REGISTRAR.length || btn.dataset.avisado) return;
        btn.dataset.avisado = '1';
        // Solo el rótulo cuenta como «impreso»: el ticket es para la agencia.
        var pend = REGISTRAR.map(function (s) {
            return fetch({!! json_encode(url('registro-envio')) !!} + '/' + s.id + '/impreso', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': {!! json_encode(csrf_token()) !!} },
                body: JSON.stringify({ format: 'sticker', motivo: s.reimp ? 'Reimpresión del rótulo de Shalom' : null }),
            }).catch(function () {});
        });
        Promise.all(pend).then(function () {
            hecho.textContent = REGISTRAR.length === 1 ? 'Marcado como rótulo impreso.' : REGISTRAR.length + ' envíos marcados como rótulo impreso.';
            REGISTRAR.forEach(function (s) { ebImpresion.avisar({ tipo: 'rotulo-impreso', envio: s.id }); });
        });
    });

    elegir(ShalomPdf.recordado(DOC), false);
})();
</script>
@endif
</body>
</html>
