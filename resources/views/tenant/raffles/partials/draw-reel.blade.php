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
    $yaGanaron  = $raffle->winners()->count();
    $prizesLeft = max(0, (int) $raffle->prize_quantity - $yaGanaron);
    $puedeSortear = $raffle->status === \App\Models\Tenant\Raffle::STATUS_ACTIVE
                    && $prizesLeft > 0 && $pool > 0;

    // Ya sorteado: no es un error, es el estado normal despues de sortear.
    // Se muestra QUIEN gano y que hacer si se quiere sortear otra vez, en vez
    // de un boton apagado que no explica nada.
    $ganador = $yaGanaron > 0 ? optional($raffle->winners()->with('participant')->first())->participant : null;
@endphp

@push('styles')
<style>
    .dm-cta { display:flex; align-items:center; justify-content:center; gap:.6rem; width:100%;
        padding:1.05rem 1.2rem; border:0; border-radius:16px; cursor:pointer;
        font-size:1.05rem; font-weight:800; letter-spacing:.02em; color:#fff;
        background:linear-gradient(135deg,#4f46e5,#7c3aed 55%,#a21caf);
        box-shadow:0 12px 28px -10px rgba(79,70,229,.65); transition:transform .12s, box-shadow .2s; }
    .dm-cta:hover:not([disabled]) { transform:translateY(-1px); box-shadow:0 16px 34px -10px rgba(79,70,229,.75); }
    .dm-cta[disabled] { background:#e5e7f0; color:#9aa1b4; box-shadow:none; cursor:not-allowed; }
    .dm-cta__sub { display:block; font-size:.72rem; font-weight:600; opacity:.85; letter-spacing:0; }

    /* Estado "ya sorteado" */
    .dm-done { border:2px solid #fcd34d; background:linear-gradient(135deg,#fffbeb,#fef3c7);
        border-radius:16px; padding:1rem 1.1rem; text-align:center; }
    .dm-done__t { font-size:.78rem; font-weight:800; color:#92400e; letter-spacing:.04em;
        text-transform:uppercase; }
    .dm-done__n { font-size:1.2rem; font-weight:900; color:#0f172a; margin:.25rem 0 .35rem; line-height:1.2; }
    .dm-done__h { font-size:.76rem; color:#78350f; line-height:1.45; }

    .dm-back { position:fixed; inset:0; z-index:2000; background:rgba(15,23,42,.72);
        backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; padding:16px; }
    .dm-back.is-on { display:flex; }
    .dm-box { background:#fff; border-radius:20px; width:100%; max-width:520px; overflow:hidden;
        box-shadow:0 30px 70px -20px rgba(0,0,0,.5);
        animation:dmIn .22s cubic-bezier(.2,.9,.3,1); }
    @keyframes dmIn { from { opacity:0; transform:translateY(14px) scale(.97); } }
    .dm-box__h { padding:1rem 1.2rem; background:linear-gradient(135deg,#4f46e5,#7c3aed);
        color:#fff; font-weight:800; font-size:1rem; text-align:center;
        letter-spacing:.01em; }
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
       Ventana de TRES filas: se ven los nombres de arriba y abajo, que es lo
       que hace que parezca una tragamonedas de verdad. Antes se veia una
       sola y el giro no se leia como tal.
       La fila del medio es la que gana; las otras dos van atenuadas para que
       el ojo sepa donde mirar. */
    .dm-reel { height:186px; overflow:hidden; position:relative; border-radius:14px;
        background:#0f172a; border:2px solid #4f46e5; margin-bottom:.9rem; }
    .dm-reel__strip { position:absolute; left:0; right:0; top:0; }
    .dm-reel__i { height:62px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:.92rem; font-weight:700; text-align:center; padding:0 .8rem;
        line-height:1.2; opacity:.32; transition:opacity .2s, transform .2s;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    /* La fila central: se marca con un marco fijo encima del rollo. */
    .dm-reel__win { position:absolute; left:6px; right:6px; top:62px; height:62px; z-index:3;
        border:2px solid #facc15; border-radius:10px; pointer-events:none;
        box-shadow:0 0 0 9999px rgba(15,23,42,.35) inset; }
    .dm-reel.is-done .dm-reel__i.is-center { opacity:1; font-size:1.05rem; transform:scale(1.04); color:#fde68a; }
    /* Sombras arriba y abajo: dan sensacion de rollo girando. */
    .dm-reel::before, .dm-reel::after { content:''; position:absolute; left:0; right:0; height:30px; z-index:2; pointer-events:none; }
    .dm-reel::before { top:0; background:linear-gradient(#0f172a,transparent); }
    .dm-reel::after { bottom:0; background:linear-gradient(transparent,#0f172a); }

    .dm-win { text-align:center; }
    .dm-win__t { font-size:1.15rem; font-weight:900; color:#15803d; margin-bottom:.5rem; }
    .dm-win__n { font-size:1.5rem; font-weight:900; color:#0f172a; line-height:1.2; margin-bottom:.5rem; }
    .dm-win__d { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; text-align:left; margin-top:.7rem; }
    .dm-win__d div { background:#f8fafc; border-radius:9px; padding:.45rem .6rem; font-size:.82rem; }
    .dm-win__d span { display:block; font-size:.66rem; color:#697084; text-transform:uppercase; }

    /* ── Detalles del resultado ──────────────────────────────────────── */
    .dm-win__t { animation:dmPop .35s cubic-bezier(.2,1.5,.4,1); }
    @keyframes dmPop { from { opacity:0; transform:scale(.7); } }
    /* Cinta del premio bajo el nombre, para que el resultado se lea como
       "quien gano QUE" y no solo como un nombre suelto. */
    .dm-win__p { display:inline-block; margin-top:.15rem; padding:.25rem .7rem;
        border-radius:99px; background:#fef3c7; color:#92400e;
        font-size:.78rem; font-weight:700; }
    .dm-spin { display:flex; align-items:center; justify-content:center; gap:.45rem;
        font-size:.82rem; color:#697084; margin:0; }
    .dm-spin__d { width:6px; height:6px; border-radius:50%; background:#7c3aed;
        animation:dmBlink 1s infinite; }
    .dm-spin__d:nth-child(2) { animation-delay:.15s; }
    .dm-spin__d:nth-child(3) { animation-delay:.3s; }
    @keyframes dmBlink { 0%,100% { opacity:.25; } 50% { opacity:1; } }

    @media (max-width:576px) {
        .dm-facts, .dm-win__d { grid-template-columns:1fr; }
        .dm-win__n { font-size:1.25rem; }
        .dm-reel { height:150px; }
        .dm-reel__i { height:50px; }
        .dm-reel__win { top:50px; height:50px; }
    }
</style>
@endpush

@if($prizesLeft < 1 && $ganador)
    {{-- Estado "ya sorteado": lo que corresponde ver es el resultado, no un
         boton muerto. Y se dice como sortear otra vez si hace falta. --}}
    <div class="dm-done">
        <div class="dm-done__t">🏆 Sorteo realizado</div>
        <div class="dm-done__n">{{ $ganador->full_name }}</div>
        <div class="dm-done__h">
            {{ $yaGanaron }} de {{ $raffle->prize_quantity }} premio(s) asignado(s).
            Para sortear otra vez: sube la cantidad de premios en <a href="{{ route('raffles.edit', $raffle) }}">Editar</a>,
            o crea una campaña nueva.
        </div>
    </div>
@else
    <button type="button" class="dm-cta" id="dmOpen" @disabled(!$puedeSortear)>
        🎰 REALIZAR SORTEO
        <span class="dm-cta__sub">
            @if($puedeSortear)
                {{ number_format($pool) }} participantes · {{ $prizesLeft }} premio(s)
            @elseif($raffle->status !== \App\Models\Tenant\Raffle::STATUS_ACTIVE)
                Cambia el estado a «Activo» para poder sortear (ahora: {{ $raffle->status_label }})
            @elseif($prizesLeft < 1)
                Ya se asignaron los {{ $raffle->prize_quantity }} premio(s)
            @elseif($raffle->requiresAcceptance())
                Nadie aceptó participar todavía ({{ $raffle->participants()->count() }} invitados)
            @else
                No hay participantes elegibles con estos criterios
            @endif
        </span>
    </button>
@endif

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
                <div class="dm-reel" id="dmReel">
                    <div class="dm-reel__strip" id="dmStrip"></div>
                    {{-- Marco de la posicion ganadora, fijo sobre el rollo. --}}
                    <div class="dm-reel__win"></div>
                </div>
                <p class="dm-spin">
                    <span class="dm-spin__d"></span><span class="dm-spin__d"></span><span class="dm-spin__d"></span>
                    Eligiendo al azar…
                </p>
            </div>

            {{-- Paso 3: ganador --}}
            <div id="dmStep3" hidden>
                <div class="dm-win">
                    <div class="dm-win__t">🏆 ¡TENEMOS GANADOR!</div>
                    @if($raffle->prize_image)
                        <img class="dm-prize" src="{{ $raffle->prizeImageUrl('medium') }}" alt="Premio">
                    @endif
                    <div class="dm-win__n" id="dmWinName">—</div>
                    @if($raffle->prize_name)
                        <div class="dm-win__p">🎁 {{ $raffle->prize_name }}</div>
                    @endif
                    <div class="dm-win__d" id="dmWinData"></div>
                    <button type="button" class="dm-btn dm-btn--go mt-3" id="dmClose"
                            style="width:100%">Ver el sorteo</button>
                </div>
            </div>

        </div>
    </div>
</div>

@push('scripts')
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

    var ALTO = 62;      // alto de cada fila
    var CENTRO = 1;     // la fila del medio de las tres visibles

    /**
     * Llena el carrete. La posicion ganadora queda con vecinos a los lados
     * para que se vean "los posibles ganadores" arriba y abajo, como en una
     * tragamonedas. Con pocos participantes los nombres se repiten.
     */
    function llenarCarrete(ganador) {
        var base = NOMBRES.length ? NOMBRES.slice() : ['Participante'];
        var tira = [];
        while (tira.length < 42) {
            tira = tira.concat(base);
        }
        tira = tira.slice(0, 42);

        // Indice donde se detiene. Se deja al menos una fila despues para que
        // el vecino de abajo exista y no quede la ventana a medias.
        var iGanador = tira.length - 2;
        tira[iGanador] = ganador;

        $('dmStrip').innerHTML = tira.map(function (n, i) {
            return '<div class="dm-reel__i' + (i === iGanador ? ' is-center' : '') + '">'
                 + esc(n) + '</div>';
        }).join('');

        return iGanador;
    }

    /**
     * Gira y desacelera hasta dejar `iGanador` en la fila CENTRAL. El paso
     * entre cuadros crece progresivamente: eso es lo que se lee como frenado.
     */
    function girar(iGanador, alTerminar) {
        var strip = $('dmStrip');
        var i = 0;
        var espera = 42;

        function ubicar(idx) {
            // Se resta CENTRO para que la fila idx quede en el medio y no arriba.
            strip.style.transform = 'translateY(' + (-(idx - CENTRO) * ALTO) + 'px)';
        }

        (function paso_() {
            ubicar(i);

            if (i >= iGanador) {
                strip.style.transition = 'transform .4s cubic-bezier(.2,.9,.3,1)';
                var reel = $('dmReel');
                if (reel) reel.classList.add('is-done');
                return setTimeout(alTerminar, 520);
            }

            i++;
            // Los ultimos 14 cuadros frenan; antes va parejo y rapido.
            if (i > iGanador - 14) espera *= 1.26;
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

        if (t.closest && t.closest('#dmOpen')) {
            var reel = $('dmReel');
            if (reel) reel.classList.remove('is-done');
            var st = $('dmStrip');
            if (st) { st.style.transition = ''; st.style.transform = ''; }
            paso(1); abrir(true); return;
        }
        if (t.closest && t.closest('#dmCancel')) { abrir(false); return; }
        // Al cerrar se recarga: la ficha tiene que mostrar al ganador ya guardado.
        if (t.closest && t.closest('#dmClose')) { window.location.reload(); return; }

        if (!t.closest || !t.closest('#dmGo')) return;

        var go = $('dmGo');
        go.disabled = true;

        paso(2);
        var iGanador = llenarCarrete('…');
        var respuesta = null;
        var giroListo = false;

        // Se lanza el giro y la petición a la vez: el carrete no espera al
        // servidor, así la animación no se ve trabada si la red demora.
        girar(iGanador, function () { giroListo = true; revelar(); });

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
            var celda = $('dmStrip').querySelector('.is-center');
            if (celda) celda.textContent = w.name;
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
@endpush
