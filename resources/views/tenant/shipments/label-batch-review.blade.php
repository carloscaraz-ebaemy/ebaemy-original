<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar lote</title>
    <style>
        body { margin: 0; background: oklch(0.965 0.004 250); }
        .rv { max-width: 980px; margin: 0 auto; padding: 0 16px 40px; }
        .rv-intro { margin: 0 0 18px; color: var(--ink-2); font-size: 13.5px; max-width: 70ch; }
        .rv-g { margin: 0 0 22px; }
        .rv-gh { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin: 0 0 8px; }
        .rv-gh h2 { margin: 0; font-size: 15px; font-weight: 650; display: flex; align-items: center; gap: 8px; }
        .rv-gh .n { font-size: 12.5px; color: var(--muted); font-weight: 500; }
        .rv-gh p { flex-basis: 100%; margin: 0; font-size: 12.5px; color: var(--muted); }
        .rv-gh .rv-all { margin-left: auto; font-size: 12.5px; color: var(--ink-2); display: inline-flex; align-items: center; gap: 6px; }
        .rv-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--line-2); }
        .rv-g[data-k="shalom"] .rv-dot { background: var(--brand-sh); }
        .rv-g[data-k="moto"] .rv-dot { background: oklch(0.55 0.15 300); }
        .rv-g[data-k^="ag:"] .rv-dot { background: var(--accent); }
        table.rv-t { width: 100%; table-layout: fixed; border-collapse: collapse; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .rv-t th { text-align: left; font-size: 11.5px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em;
                   padding: 8px 12px; background: var(--panel-2); border-bottom: 1px solid var(--line); }
        .rv-t th:nth-child(1) { width: 28%; } .rv-t th:nth-child(3) { width: 140px; } .rv-t th:nth-child(4) { width: 190px; }
        .rv-t td { overflow-wrap: anywhere; }
        .rv-t td { padding: 9px 12px; border-top: 1px solid var(--line); font-size: 13.5px; vertical-align: middle; }
        .rv-t tr:first-child td { border-top: 0; }
        .rv-ref { font-weight: 650; font-variant-numeric: tabular-nums; }
        .rv-sub { display: block; font-size: 12px; color: var(--muted); }
        .rv-num { display: inline-flex; align-items: center; border: 1px solid var(--line-2); border-radius: 8px; overflow: hidden; background: var(--panel); }
        .rv-num button { width: 32px; height: 32px; border: 0; background: var(--panel-2); font: 600 16px/1 inherit; color: var(--ink-2); cursor: pointer; }
        .rv-num button:hover { background: var(--line); }
        .rv-num input { width: 40px; height: 32px; border: 0; text-align: center; font: 650 14px/1 inherit; color: var(--ink); -moz-appearance: textfield; }
        .rv-num input::-webkit-inner-spin-button { -webkit-appearance: none; }
        .rv-sel, .rv-kg { height: 34px; border: 1px solid var(--line-2); border-radius: 8px; background: var(--panel); font: 500 13.5px/1 inherit; color: var(--ink); padding: 0 8px; }
        .rv-kg { width: 76px; text-align: right; }
        .rv-unit { font-size: 12.5px; color: var(--muted); margin-left: 4px; }
        .rv-off td { color: var(--muted); }
        .rv-why { font-size: 12.5px; color: var(--warn); }
        .rv-lock { font-size: 12.5px; color: var(--ink-2); }
        .rv-res { max-width: 980px; margin: 0 auto 14px; padding: 0 16px; font-size: 13.5px; }
        .rv-res ul { margin: 6px 0 0; padding-left: 18px; color: var(--warn); }
        .rv-res .ok { color: var(--ok); font-weight: 600; }
        @media (max-width: 720px) {
            .rv-t thead { display: none; }
            .rv-t, .rv-t tbody, .rv-t tr, .rv-t td { display: block; width: 100%; box-sizing: border-box; }
            .rv-t tr { padding: 10px 12px; border-top: 1px solid var(--line); }
            .rv-t tr:first-child { border-top: 0; }
            .rv-t td { border: 0; padding: 3px 0; }
            .rv-t td[data-l]::before { content: attr(data-l); display: inline-block; min-width: 72px; font-size: 12px; color: var(--muted); }
        }
    </style>
</head>
<body>
@include('tenant.shipments.partials.print-bar-styles')
@include('tenant.shipments.partials.print-embed-js')
@php
    $filas = collect($grupos)->flatMap(fn ($g) => collect($g['filas'])->map(fn ($f) => $f + ['k' => $g['clave']]));
    $imprimibles = $filas->filter(fn ($f) => $f['k'] !== 'tienda' && !$f['motivo']);
@endphp
<div class="pb">
    <div class="pb-bar">
        <div class="pb-t">
            <b>{{ $batch ? 'Lote ' . $batch->code : 'Revisar antes de imprimir' }}</b>
            <span>{{ $filas->count() }} {{ $filas->count() === 1 ? 'pedido' : 'pedidos' }} · <span id="rvTotal">{{ $imprimibles->sum('bultos') }}</span> rótulos</span>
        </div>
        <div class="pb-sp"></div>
        <button type="button" class="pb-btn" onclick="ebImpresion.cerrar()">Cancelar</button>
        <button type="button" class="pb-btn pb-btn--primary" id="rvGo" {{ $imprimibles->isEmpty() ? 'disabled' : '' }}>
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 7V2h10v5M5 15H3V8h14v7h-2"/><rect x="5" y="12" width="10" height="6"/></svg>
            Continuar e imprimir
        </button>
    </div>

    <div class="rv-res" id="rvRes" aria-live="polite"></div>

    <div class="rv">
        <p class="rv-intro">Cada pedido sale con un rótulo por bulto. Revisa cuántas cajas lleva cada uno y su tamaño; ya vienen llenos, solo cambia los que sean distintos.</p>

        @foreach($grupos as $g)
            @php $esShalom = $g['clave'] === 'shalom'; $esTienda = $g['clave'] === 'tienda'; @endphp
            <section class="rv-g" data-k="{{ $g['clave'] }}">
                <div class="rv-gh">
                    <h2><span class="rv-dot" aria-hidden="true"></span>{{ $g['titulo'] }}</h2>
                    <span class="n">{{ count($g['filas']) }} {{ count($g['filas']) === 1 ? 'pedido' : 'pedidos' }}</span>
                    @if($esShalom && collect($g['filas'])->contains(fn ($f) => !$f['bloqueado'] && !$f['motivo']))
                        <label class="rv-all">Todos en
                            <select class="rv-sel js-all-size">
                                <option value="">—</option>
                                @foreach($tamanos as $k => $l)<option value="{{ $k }}">{{ $k === 'SOBRE' ? 'Sobre' : 'Caja ' . $l }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                    <p>{{ $g['nota'] }}</p>
                </div>
                <table class="rv-t">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Destino</th>
                            @unless($esTienda)
                                <th>Bultos</th>
                                <th>{{ $esShalom ? 'Tamaño' : 'Peso' }}</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($g['filas'] as $f)
                        @php $edita = !$esTienda && !$f['motivo'] && !$f['bloqueado']; @endphp
                        <tr class="{{ $f['motivo'] ? 'rv-off' : '' }} {{ $edita ? 'js-row' : '' }} {{ !$esTienda && !$f['motivo'] ? 'js-print' : '' }}"
                            data-id="{{ $f['id'] }}" data-bultos="{{ $f['bultos'] }}" data-shalom="{{ $esShalom && $edita ? 1 : 0 }}">
                            <td><span class="rv-ref">{{ $f['ref'] }}</span><span class="rv-sub">{{ $f['cliente'] }}</span></td>
                            <td data-l="Destino">{{ $f['destino'] ?: '—' }}</td>
                            @unless($esTienda)
                                @if($f['motivo'])
                                    <td colspan="2"><span class="rv-why">No entra: {{ $f['motivo'] }}</span></td>
                                @elseif($f['bloqueado'])
                                    <td data-l="Bultos"><b>{{ $f['bultos'] }}</b></td>
                                    <td><span class="rv-lock">Guía {{ $f['guia'] ?: 'creada' }}. Para cambiarla, ábrela en su panel «Guía de Shalom».</span></td>
                                @else
                                    <td data-l="Bultos">
                                        <span class="rv-num">
                                            <button type="button" class="js-dec" aria-label="Un bulto menos">−</button>
                                            <input type="number" class="js-bultos" min="1" max="99" value="{{ $f['bultos'] }}" aria-label="Bultos de {{ $f['ref'] }}">
                                            <button type="button" class="js-inc" aria-label="Un bulto más">+</button>
                                        </span>
                                    </td>
                                    <td data-l="{{ $esShalom ? 'Tamaño' : 'Peso' }}">
                                        @if($esShalom)
                                            <select class="rv-sel js-size" aria-label="Tamaño de {{ $f['ref'] }}">
                                                @unless($tipico)<option value="">Elige…</option>@endunless
                                                @foreach($tamanos as $k => $l)
                                                    <option value="{{ $k }}" {{ $tipico === $k ? 'selected' : '' }}>{{ $k === 'SOBRE' ? 'Sobre' : 'Caja ' . $l }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="number" class="rv-kg js-peso" min="0" max="9999" step="0.1" inputmode="decimal"
                                                   value="{{ $f['peso'] !== null ? rtrim(rtrim(number_format($f['peso'], 2, '.', ''), '0'), '.') : '' }}"
                                                   placeholder="opcional" aria-label="Peso de {{ $f['ref'] }} en kg"><span class="rv-unit">kg</span>
                                        @endif
                                    </td>
                                @endif
                            @endunless
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
</div>

<script>
(function () {
    var NEXT = {!! json_encode($next) !!}, CSRF = {!! json_encode(csrf_token()) !!};
    var SAVE = {!! json_encode(route('shipments.print_review.save')) !!};
    var GUIAS = {!! json_encode(url('registro-envio/shalom-guias-lote')) !!};
    var total = document.getElementById('rvTotal'), go = document.getElementById('rvGo'), res = document.getElementById('rvRes');

    function bultosDe(tr) {
        var i = tr.querySelector('.js-bultos');
        return i ? Math.min(99, Math.max(1, parseInt(i.value, 10) || 1)) : parseInt(tr.getAttribute('data-bultos'), 10) || 1;
    }
    function recontar() {
        var n = 0;
        document.querySelectorAll('tr.js-print').forEach(function (tr) { n += bultosDe(tr); });
        total.textContent = n;
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-inc, .js-dec');
        if (!b) return;
        var i = b.parentNode.querySelector('.js-bultos');
        i.value = Math.min(99, Math.max(1, (parseInt(i.value, 10) || 1) + (b.classList.contains('js-inc') ? 1 : -1)));
        recontar();
    });
    document.addEventListener('input', function (e) { if (e.target.classList.contains('js-bultos')) recontar(); });
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-bultos')) { e.target.value = Math.min(99, Math.max(1, parseInt(e.target.value, 10) || 1)); recontar(); }
        if (e.target.classList.contains('js-all-size') && e.target.value) {
            e.target.closest('.rv-g').querySelectorAll('.js-size').forEach(function (s) { s.value = e.target.value; });
        }
    });

    function post(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        }).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'No se pudo guardar.'); return d; }); });
    }
    function lista(items) {
        return '<ul>' + items.map(function (x) { var li = document.createElement('li'); li.textContent = x; return li.outerHTML; }).join('') + '</ul>';
    }

    var soloImprimir = false;
    go.addEventListener('click', async function () {
        if (soloImprimir) { location.href = NEXT; return; }
        var filas = Array.prototype.slice.call(document.querySelectorAll('tr.js-row'));
        var faltaTam = filas.filter(function (tr) { var s = tr.querySelector('.js-size'); return s && !s.value; });
        if (faltaTam.length) {
            res.innerHTML = '<span class="rv-why">Elige el tamaño de ' + faltaTam.length + (faltaTam.length === 1 ? ' pedido' : ' pedidos') + ' de Shalom.</span>';
            faltaTam[0].querySelector('.js-size').focus();
            return;
        }
        go.disabled = true;
        res.textContent = '';
        try {
            if (filas.length) {
                go.lastChild.textContent = ' Guardando…';
                await post(SAVE, { rows: filas.map(function (tr) {
                    var p = tr.querySelector('.js-peso');
                    return { id: +tr.getAttribute('data-id'), bultos: bultosDe(tr), peso: p && p.value !== '' ? p.value : null };
                }) });
            }

            // Guías de Shalom que faltan, cada una con sus bultos y su tamaño. De a 10: cada guía tarda.
            var sh = filas.filter(function (tr) { return tr.getAttribute('data-shalom') === '1'; });
            var ok = 0, mal = [];
            for (var i = 0; i < sh.length; i += 10) {
                go.lastChild.textContent = ' Creando guías de Shalom… ' + Math.min(i + 10, sh.length) + ' de ' + sh.length;
                var tanda = sh.slice(i, i + 10), sizes = {};
                tanda.forEach(function (tr) { sizes[tr.getAttribute('data-id')] = tr.querySelector('.js-size').value; });
                try {
                    var d = await post(GUIAS, { ids: tanda.map(function (tr) { return +tr.getAttribute('data-id'); }), sizes: sizes });
                    (d.results || []).forEach(function (x) { if (x.ok) ok++; else mal.push(x.code + ': ' + x.message); });
                } catch (e) { mal.push('Tanda ' + (i / 10 + 1) + ': sin respuesta. Revisa en pro.shalom.pe antes de reintentar.'); }
            }
            if (mal.length) {
                // Se detiene para que se lea: los demás ya quedaron listos.
                res.innerHTML = (ok ? '<span class="ok">' + ok + (ok === 1 ? ' guía creada.' : ' guías creadas.') + '</span> ' : '')
                    + 'Estas guías de Shalom no se crearon:' + lista(mal);
                go.disabled = false;
                go.lastChild.textContent = ' Imprimir igual';
                soloImprimir = true;
                return;
            }
            go.lastChild.textContent = ' Abriendo impresión…';
            location.href = NEXT;
        } catch (e) {
            res.innerHTML = '<span class="rv-why">' + (e.message || 'No se pudo guardar.') + '</span>';
            go.disabled = false;
            go.lastChild.textContent = ' Continuar e imprimir';
        }
    });
})();
</script>
</body>
</html>
