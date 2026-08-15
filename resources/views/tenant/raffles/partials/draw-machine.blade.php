{{-- ══════════════════════════════════════════════════════════════════════
     SORTEO: confirmación → animación → ganador

     El azar NO ocurre acá. El backend elige dentro de una transacción y
     devuelve al ganador; el carrete solo lo dibuja. Por eso la animación
     puede ser puramente decorativa sin abrir la puerta a que alguien la
     manipule desde el navegador.

     Espera: $raffle, $metrics, $reelNames.
     ══════════════════════════════════════════════════════════════════════ --}}
@php
    $pool = $raffle->requiresAcceptance() ? ($metrics['accepted'] ?? 0) : ($metrics['invited'] ?? 0);
    $prizesLeft = max(0, (int) $raffle->prize_quantity - $raffle->winners()->count());
    $puedeSortear = $raffle->status === \App\Models\Tenant\Raffle::STATUS_ACTIVE
                    && $prizesLeft > 0 && $pool > 0;
@endphp

<style>
    .dm-cta { display:flex; align-items:center; justify-content:center; gap:.6rem; width:100%;
        padding:1.05rem 1.2rem; border:0; border-radius:16px; cursor:pointer;
        font-size:1.05rem; font-weight:800; letter-spacing:.02em; color:#fff;
        background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#a21caf);
        box-shadow:0 12px 28px -10px rgba(79,70,229,.65); transition:transform .12s, box-shadow .2s; }
    .dm-cta:hover:not([disabled]) { transform:translateY(-1px); box-shadow:0 16px 34px -10px rgba(79,70,229,.75); }
    .dm-cta[disabled] { background:#e5e7f0; color:#9aa1b4; box-shadow:none; cursor:not-allowed; }
    .dm-cta__sub { display:block; font-size:.72rem; font-weight:600; opacity:.85; letter-spacing:0; }

    .dm-back { position:fixed; inset:0; z-index:2000; background:rgba(15,23,42,.72);
        backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; padding:16px; }
    .dm-back.is-on { display:flex; }
    .dm-box { background:#fff; border-radius:20px; width:100%; max-width:520px; overflow:hidden;
        box-shadow:0 30px 70px -20px rgba(0,0,0,.5); }
    .dm-box__h { padding:1rem 1.2rem; background:linear-gradient(135deg,#4f46e5,#7c3aed);
        color:#fff; font-weight:800; font-size:1rem; text-align:center; }
    .dm-box__b { padding:1.1rem 1.2rem; }
    .dm-prize { display:block; max-width:100%; max-height:150px; margin:0 auto .8rem;
        border-radius:12px; object-fit:contain; }
    .dm-facts { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.8rem; }
    .dm-fact { background:#f8fafc; border:1px solid #eef0f7; border-radius:11px; padding:.55rem .7rem; }
    .dm-fact b { display:block; font-size:1.15rem; color:#1f2430; line-height:1.2; }
    .dm-fact span { font-size:.7rem; color:#697084; text-transform:uppercase; letter-spacing:.04em; }
    .dm-crit { list-style:none; margin:0 0 .9rem; padding:0; font-size:.8rem; color:#334155; }
    .dm-crit li { padding:.16rem 0; }
    .dm-crit li::before { content:'✓'; color:#15803d; font-weight:800; margin-right:.4rem; }
    .dm-acts { display:flex; gap:.5rem; }
    .dm-btn { flex:1; padding:.7rem; border-radius:11px; font-weight:700; font-size:.86rem;
        border:1px solid #e5e7f0; background:#fff; color:#1f2430; cursor:pointer; }
    .dm-btn--go { border:0; color:#fff; background:linear-gradient(135deg,#4f46e5,#7c3aed); }
    .dm-btn[disabled] { opacity:.55; cursor:not-allowed; }

    /* ── Carrete ─────────────────────────────────────────────────────────
       Se mueve una tira con translateY y `overflow:hidden` deja ver una
       sola fila. La desaceleracion la hace el JS variando el paso. */
    .dm-reel { height:82px; overflow:hidden; position:relative; border-radius:14px;
        background:#0f172a; border:2px solid #4f46e5; margin-bottom:.9rem; }
    .dm-reel__strip { position:absolute; left:0; right:0; top:0; }
    .dm-reel__i { height:82px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:1rem; font-weight:700; text-align:center; padding:0 .8rem;
        line-height:1.25; }
    /* Sombras arriba y abajo: dan sensacion de rollo girando. */
    .dm-reel::before, .dm-reel::after { content:''; position:absolute; left:0; right:0; height:26px; z-index:2; pointer-events:none; }
    .dm-reel::before { top:0; background:linear-gradient(#0f172a,transparent); }
    .dm-reel::after { bottom:0; background:linear-gradient(transparent,#0f172a); }

    .dm-win { text-align:center; }
    .dm-win__t { font-size:1.15rem; font-weight:900; color:#15803d; margin-bottom:.5rem; }
    .dm-win__n { font-size:1.5rem; font-weight:900; color:#0f172a; line-height:1.2; margin-bottom:.5rem; }
    .dm-win__d { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; text-align:left; margin-top:.7rem; }
    .dm-win__d div { background:#f8fafc; border-radius:9px; padding:.45rem .6rem; font-size:.82rem; }
    .dm-win__d span { display:block; font-size:.66rem; color:#697084; text-transform:uppercase; }
</style>

<button type="button" class="dm-cta" id="dmOpen" @disabled(!$puedeSortear)>
    🎰 REALIZAR SORTEO
    <span class="dm-cta__sub">
        @if($puedeSortear)
            {{ number_format($pool) }} participantes · {{ $prizesLeft }} premio(s)
        @elseif($raffle->status !== \App\Models\Tenant\Raffle::STATUS_ACTIVE)
            La campaña debe estar Activa
        @elseif($prizesLeft < 1)
            Ya se asignaron todos los premios
        @else
            No hay participantes elegibles
        @endif
    </span>
</button>

<div class="dm-back" id="dmBack">
    <div class="dm-box">
        <div class="dm-box__h" id="dmTitle">🎉 {{ $raffle->name }}</div>
        <div class="dm-box__b">

            {{-- Paso 1: confirmar --}}
            <div id="dmStep1">
                @if($raffle->prize_image)
                    <img class="dm-prize" src="{{ $raffle->prizeImageUrl('medium') }}" alt="Premio">
                @endif

                <div class="dm-facts">
                    <div class="dm-fact"><b>{{ number_format($pool) }}</b><span>Participantes</span></div>
                    <div class="dm-fact"><b>{{ $prizesLeft }}</b><span>Premio(s) por sortear</span></div>
                </div>

                <ul class="dm-crit">
                    @foreach($raffle->criteriaSummary() as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>

                <div class="dm-acts">
                    <button type="button" class="dm-btn" id="dmCancel">Cancelar</button>
                    <button type="button" class="dm-btn dm-btn--go" id="dmGo">🎰 INICIAR SORTEO</button>
                </div>
            </div>

            {{-- Paso 2: girando --}}
            <div id="dmStep2" hidden>
                <div class="dm-reel"><div class="dm-reel__strip" id="dmStrip"></div></div>
                <p class="text-center text-muted" style="font-size:.82rem;margin:0;">Eligiendo al azar…</p>
            </div>

            {{-- Paso 3: ganador --}}
            <div id="dmStep3" hidden>
                <div class="dm-win">
                    <div class="dm-win__t">🏆 ¡TENEMOS GANADOR!</div>
                    @if($raffle->prize_image)
                        <img class="dm-prize" src="{{ $raffle->prizeImageUrl('medium') }}" alt="Premio">
                    @endif
                    <div class="dm-win__n" id="dmWinName">—</div>
                    <div class="dm-win__d" id="dmWinData"></div>
                    <button type="button" class="dm-btn dm-btn--go mt-3" id="dmClose"
                            style="width:100%">Ver el sorteo</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
/*
 * Máquina del sorteo.
 *
 * El ganador YA viene decidido del servidor: la animación arranca, se pide
 * el sorteo, y el carrete desacelera hasta el nombre que devolvió el
 * backend. Si se decidiera aquí, cualquiera podría amañarlo desde la
 * consola del navegador.
 *
 * Delegación en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var NOMBRES = {!! json_encode($reelNames ?: [], JSON_UNESCAPED_UNICODE) !!};
    var URL_SORTEO = '{{ route('raffles.draw', $raffle) }}';
    var token = document.querySelector('meta[name="csrf-token"]');

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function abrir(on) {
        var b = $('dmBack');
        if (b) b.classList.toggle('is-on', on);
    }

    function paso(n) {
        [1, 2, 3].forEach(function (i) {
            var el = $('dmStep' + i);
            if (el) el.hidden = (i !== n);
        });
    }

    /** Llena el carrete. Con pocos participantes se repiten para que gire. */
    function llenarCarrete(ganador) {
        var base = NOMBRES.length ? NOMBRES.slice() : ['Participante'];
        var tira = [];
        while (tira.length < 40) {
            tira = tira.concat(base);
        }
        tira = tira.slice(0, 40);
        tira.push(ganador);   // el ultimo es donde se detiene

        $('dmStrip').innerHTML = tira.map(function (n) {
            return '<div class="dm-reel__i">' + esc(n) + '</div>';
        }).join('');

        return tira.length;
    }

    /**
     * Gira y desacelera. El paso entre cuadros crece progresivamente, que es
     * lo que da la sensación de frenado de una tragamonedas.
     */
    function girar(total, alTerminar) {
        var strip = $('dmStrip');
        var alto = 82;
        var i = 0;
        var espera = 45;

        (function paso_() {
            strip.style.transform = 'translateY(' + (-i * alto) + 'px)';

            if (i >= total - 1) {
                strip.style.transition = 'transform .45s cubic-bezier(.2,.9,.3,1)';
                return setTimeout(alTerminar, 550);
            }

            i++;
            // Los ultimos 12 cuadros frenan; antes va parejo y rapido.
            if (i > total - 12) espera *= 1.28;
            setTimeout(paso_, espera);
        })();
    }

    function mostrarGanador(w) {
        $('dmWinName').textContent = w.name || '—';

        var campos = [
            ['Documento', w.document],
            ['Teléfono', w.phone],
            ['Compras', w.orders],
            ['Última compra', w.last_at]
        ].filter(function (f) { return f[1] !== null && f[1] !== '' && f[1] !== undefined; });

        $('dmWinData').innerHTML = campos.map(function (f) {
            return '<div><span>' + esc(f[0]) + '</span>' + esc(f[1]) + '</div>';
        }).join('');

        $('dmTitle').textContent = '🏆 Resultado del sorteo';
        paso(3);
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;

        if (t.closest && t.closest('#dmOpen')) { paso(1); abrir(true); return; }
        if (t.closest && t.closest('#dmCancel')) { abrir(false); return; }
        // Al cerrar se recarga: la ficha tiene que mostrar al ganador ya guardado.
        if (t.closest && t.closest('#dmClose')) { window.location.reload(); return; }

        if (!t.closest || !t.closest('#dmGo')) return;

        var go = $('dmGo');
        go.disabled = true;

        paso(2);
        var total = llenarCarrete('…');
        var respuesta = null;
        var giroListo = false;

        // Se lanza el giro y la petición a la vez: el carrete no espera al
        // servidor, así la animación no se ve trabada si la red demora.
        girar(total, function () { giroListo = true; revelar(); });

        function revelar() {
            if (!giroListo || !respuesta) return;
            if (respuesta.error) {
                abrir(false);
                window.alert(respuesta.error);
                go.disabled = false;
                return;
            }
            var w = (respuesta.winners || [])[0];
            if (!w) { window.location.reload(); return; }
            $('dmStrip').lastElementChild.textContent = w.name;
            mostrarGanador(w);
        }

        fetch(URL_SORTEO, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: 'quantity=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { respuesta = d; revelar(); })
        .catch(function () {
            respuesta = { error: 'No se pudo realizar el sorteo. Revisa tu conexión.' };
            revelar();
        });
    });
})();
</script>
