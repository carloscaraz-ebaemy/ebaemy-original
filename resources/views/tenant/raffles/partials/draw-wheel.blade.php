{{-- ══════════════════════════════════════════════════════════════════════
     SORTEO: ruleta giratoria (estilo Temu).

     El azar NO ocurre acá. El backend elige dentro de una transacción y
     devuelve al ganador; la ruleta solo lo dibuja. Por eso la animación
     puede ser puramente decorativa sin abrir la puerta a que alguien la
     manipule desde el navegador.

     La rueda tiene 12 gajos y el sorteo puede tener cientos de
     participantes: los nombres de los gajos son una MUESTRA, no el universo.
     Por eso el rótulo de abajo dice entre cuántos se está sorteando. No lo
     quites: sin él la rueda miente.

     Espera: $raffle, $metrics, $reelNames.
     ══════════════════════════════════════════════════════════════════════ --}}
@php
    $pool = $raffle->requiresAcceptance() ? ($metrics['accepted'] ?? 0) : ($metrics['invited'] ?? 0);
    $yaGanaron  = $raffle->winners()->count();
    $prizesLeft = max(0, (int) $raffle->prize_quantity - $yaGanaron);
    $puedeSortear = $raffle->status === \App\Models\Tenant\Raffle::STATUS_ACTIVE
                    && $prizesLeft > 0 && $pool > 0;

    $ganador = $yaGanaron > 0 ? optional($raffle->winners()->with('participant')->first())->participant : null;

    // Blade @json() se rompe con comas anidadas: se precalcula la variable y
    // se vuelca con json_encode (ver feedback_blade_json_parser_trap).
    $wheelNames = collect($reelNames ?: [])->filter()->values()->all();
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

    .dm-done { border:2px solid #fcd34d; background:linear-gradient(135deg,#fffbeb,#fef3c7);
        border-radius:16px; padding:1rem 1.1rem; text-align:center; }
    .dm-done__t { font-size:.78rem; font-weight:800; color:#92400e; letter-spacing:.04em;
        text-transform:uppercase; }
    .dm-done__n { font-size:1.2rem; font-weight:900; color:#0f172a; margin:.25rem 0 .35rem; line-height:1.2; }
    .dm-done__h { font-size:.76rem; color:#78350f; line-height:1.45; }

    .dm-back { position:fixed; inset:0; z-index:2000; background:rgba(15,23,42,.72);
        backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; padding:16px; }
    .dm-back.is-on { display:flex; }
    .dm-box { background:#fff; border-radius:20px; width:100%; max-width:520px;
        max-height:96vh; overflow-y:auto;
        box-shadow:0 30px 70px -20px rgba(0,0,0,.5);
        animation:dmIn .22s cubic-bezier(.2,.9,.3,1); }
    @keyframes dmIn { from { opacity:0; transform:translateY(14px) scale(.97); } }
    .dm-box__h { padding:1rem 1.2rem; background:linear-gradient(135deg,#4f46e5,#7c3aed);
        color:#fff; font-weight:800; font-size:1rem; text-align:center; letter-spacing:.01em; }
    .dm-box__b { padding:1.1rem 1.2rem; }
    .dm-prize { display:block; max-width:100%; max-height:150px; margin:0 auto .8rem;
        border-radius:12px; object-fit:contain; }
    .dm-facts { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.8rem; }
    .dm-fact { background:#f8fafc; border:1px solid #eef0f7; border-radius:11px; padding:.55rem .7rem; }
    .dm-fact b { display:block; font-size:1.15rem; color:#1f2430; line-height:1.2; }
    .dm-fact span { font-size:.7rem; color:#697084; text-transform:uppercase; letter-spacing:.04em; }
    .dm-crit { list-style:none; margin:0 0 .9rem; padding:0; font-size:.8rem; color:#334155; }
    .dm-crit li { padding:.16rem 0; }
    .dm-crit li::before { content:'\2713'; color:#15803d; font-weight:800; margin-right:.4rem; }
    .dm-acts { display:flex; gap:.5rem; }
    .dm-btn { flex:1; padding:.7rem; border-radius:11px; font-weight:700; font-size:.86rem;
        border:1px solid #e5e7f0; background:#fff; color:#1f2430; cursor:pointer; }
    .dm-btn--go { border:0; color:#fff; background:linear-gradient(135deg,#4f46e5,#7c3aed); }
    .dm-btn[disabled] { opacity:.55; cursor:not-allowed; }

    /* ── Ruleta ──────────────────────────────────────────────────────────
       La rueda gira; el puntero queda fijo arriba (12 en punto). El SVG se
       arma en JS porque el nombre del gajo ganador se reescribe justo antes
       de que la rueda frene. */
    .dw-wrap { position:relative; width:min(86vw,380px); margin:0 auto .75rem; aspect-ratio:1;
        display:flex; align-items:center; justify-content:center; }
    .dw-svg { width:100%; height:100%; display:block;
        filter:drop-shadow(0 10px 24px rgba(15,23,42,.28)); }
    /* Giro libre mientras se espera al servidor. La rotacion final NO usa
       esta animacion: se empalma con un transform inline (ver el JS). */
    .dw-svg.is-spinning { animation:dwSpin 1.1s linear infinite; }
    @keyframes dwSpin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }

    .dw-pin { position:absolute; top:-4px; left:50%; transform:translateX(-50%); z-index:4;
        width:0; height:0; border-left:15px solid transparent; border-right:15px solid transparent;
        border-top:27px solid #f43f5e; filter:drop-shadow(0 3px 5px rgba(0,0,0,.45)); }
    .dw-hub { position:absolute; width:23%; aspect-ratio:1; border-radius:50%; z-index:3;
        background:linear-gradient(135deg,#fff,#eef0ff); border:3px solid #4f46e5;
        display:flex; align-items:center; justify-content:center; text-align:center;
        font-size:.68rem; font-weight:900; color:#4f46e5; letter-spacing:.03em;
        box-shadow:0 4px 14px rgba(15,23,42,.25); }

    .dw-pool { text-align:center; font-size:.84rem; color:#334155; margin:0 0 .25rem; font-weight:700; }
    .dw-note { text-align:center; font-size:.7rem; color:#94a3b8; margin:0; line-height:1.45; }
    .dw-step { text-align:center; font-size:.72rem; font-weight:800; color:#7c3aed;
        text-transform:uppercase; letter-spacing:.05em; margin:0 0 .5rem; }

    .dw-conf { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; z-index:5; }

    .dm-win { text-align:center; }
    .dm-win__t { font-size:1.15rem; font-weight:900; color:#15803d; margin-bottom:.5rem;
        animation:dmPop .35s cubic-bezier(.2,1.5,.4,1); }
    @keyframes dmPop { from { opacity:0; transform:scale(.7); } }
    .dm-win__n { font-size:1.5rem; font-weight:900; color:#0f172a; line-height:1.2; margin-bottom:.5rem; }
    .dm-win__p { display:inline-block; margin-top:.15rem; padding:.25rem .7rem; border-radius:99px;
        background:#fef3c7; color:#92400e; font-size:.78rem; font-weight:700; }
    .dm-win__d { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; text-align:left; margin-top:.7rem; }
    .dm-win__d div { background:#f8fafc; border-radius:9px; padding:.45rem .6rem; font-size:.82rem; }
    .dm-win__d span { display:block; font-size:.66rem; color:#697084; text-transform:uppercase; }

    @media (max-width:576px) {
        .dm-facts, .dm-win__d { grid-template-columns:1fr; }
        .dm-win__n { font-size:1.25rem; }
        .dw-wrap { width:min(90vw,315px); }
    }

    /* Quien pidio menos movimiento no recibe el giro: se revela y ya. */
    @media (prefers-reduced-motion: reduce) {
        .dw-svg.is-spinning { animation:none; }
        .dw-svg { transition:none !important; }
        .dm-box { animation:none; }
        .dm-win__t { animation:none; }
    }
</style>
@endpush

@if($prizesLeft < 1 && $ganador)
    {{-- Ya sorteado: lo que corresponde ver es el resultado, no un boton
         muerto. Y se dice como sortear otra vez si hace falta. --}}
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
        🎡 REALIZAR SORTEO
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
                    <button type="button" class="dm-btn dm-btn--go" id="dmGo">🎡 GIRAR LA RULETA</button>
                </div>
            </div>

            {{-- Paso 2: girando --}}
            <div id="dmStep2" hidden>
                @if((int) $raffle->prize_quantity > 1)
                    <p class="dw-step">Premio {{ $yaGanaron + 1 }} de {{ $raffle->prize_quantity }}</p>
                @endif

                <div class="dw-wrap">
                    <div class="dw-pin"></div>
                    <svg class="dw-svg" id="dwWheel" viewBox="0 0 200 200" aria-hidden="true"></svg>
                    <div class="dw-hub">GIRA</div>
                    <canvas class="dw-conf" id="dwConf" hidden></canvas>
                </div>

                {{-- Honestidad obligatoria: 12 gajos, cientos de participantes. --}}
                <p class="dw-pool">Girando entre {{ number_format($pool) }} participantes</p>
                <p class="dw-note">
                    La rueda muestra solo una muestra de los nombres.<br>
                    El ganador se elige al azar entre los {{ number_format($pool) }}.
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
 * Ruleta del sorteo.
 *
 * El ganador YA viene decidido del servidor: la rueda arranca a girar, se
 * pide el sorteo, y cuando llega la respuesta se escribe el nombre en el
 * gajo destino y la rueda desacelera hasta dejarlo bajo el puntero. Si se
 * decidiera aqui, cualquiera podria amanarlo desde la consola.
 *
 * Delegacion en `document` (ver feedback_vue_mainwrapper_rerender): Vue
 * monta en #main-wrapper y re-renderiza el DOM, asi que no se pueden
 * guardar nodos ni colgar listeners directos.
 */
(function () {
    var NOMBRES    = {!! json_encode($wheelNames, JSON_UNESCAPED_UNICODE) !!};
    var URL_SORTEO = '{{ route('raffles.draw', $raffle) }}';
    var PREMIOS_RESTANTES = {{ (int) $prizesLeft }};

    var GAJOS  = 12;
    var SEG    = 360 / GAJOS;
    var VUELTAS = 6;

    var COLOR_A = '#4f46e5';
    var COLOR_B = '#312e81';
    var COLOR_WIN = '#f59e0b';

    // Angulo acumulado de la rueda. NUNCA se resetea a 0: si se resetea, el
    // siguiente giro va hacia atras.
    var anguloAcumulado = 0;
    var gajoGanador = 0;

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

    function menosMovimiento() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function corto(s) {
        s = String(s == null ? '' : s).trim();
        return s.length > 14 ? s.slice(0, 13) + '…' : s;
    }

    /* ── Dibujo del SVG ───────────────────────────────────────────────── */

    // Punto del borde para el angulo dado (grados, 0 = derecha, -90 = arriba).
    function borde(grados, radio) {
        var r = (grados * Math.PI) / 180;
        return [100 + radio * Math.cos(r), 100 + radio * Math.sin(r)];
    }

    /**
     * Arma los 12 gajos. El gajo 0 arranca en las 12 en punto y se avanza en
     * sentido horario, que es como se mide luego el angulo de frenado.
     */
    function dibujar(nombres) {
        var partes = [];

        for (var k = 0; k < GAJOS; k++) {
            var ini = k * SEG - 90;
            var fin = ini + SEG;
            var p1 = borde(ini, 96);
            var p2 = borde(fin, 96);

            partes.push(
                '<path d="M100,100 L' + p1[0].toFixed(2) + ',' + p1[1].toFixed(2) +
                ' A96,96 0 0,1 ' + p2[0].toFixed(2) + ',' + p2[1].toFixed(2) + ' Z" ' +
                'fill="' + (k % 2 ? COLOR_B : COLOR_A) + '" ' +
                'stroke="rgba(255,255,255,.35)" stroke-width="1" ' +
                'id="dwSeg' + k + '"/>'
            );

            // Texto radial: se lee del centro hacia afuera. En la mitad
            // izquierda se voltea 180 grados para que no quede de cabeza.
            var bisec = ini + SEG / 2;
            var norm  = ((bisec % 360) + 360) % 360;
            var flip  = norm > 90 && norm < 270;

            partes.push(
                '<text id="dwTxt' + k + '" ' +
                'transform="rotate(' + (bisec + (flip ? 180 : 0)).toFixed(2) + ' 100 100)" ' +
                'x="' + (flip ? 32 : 168) + '" y="103" ' +
                'text-anchor="' + (flip ? 'start' : 'end') + '" ' +
                'fill="#fff" font-size="9.5" font-weight="700" ' +
                'style="pointer-events:none">' +
                esc(corto(nombres[k])) + '</text>'
            );
        }

        partes.push('<circle cx="100" cy="100" r="97" fill="none" stroke="#fbbf24" stroke-width="4"/>');

        $('dwWheel').innerHTML = partes.join('');
    }

    /** 12 nombres de muestra. Con pocos participantes se repiten. */
    function nombresDeMuestra() {
        var base = NOMBRES.length ? NOMBRES.slice() : ['Participante'];
        var out = [];
        while (out.length < GAJOS) { out = out.concat(base); }
        return out.slice(0, GAJOS);
    }

    /** Lee la rotacion actual del SVG en grados, incluida la del keyframe. */
    function anguloActual(el) {
        var t = window.getComputedStyle(el).transform;
        if (!t || t === 'none') { return 0; }
        var m = t.match(/matrix\(([^)]+)\)/);
        if (!m) { return 0; }
        var v = m[1].split(',').map(parseFloat);
        return (Math.atan2(v[1], v[0]) * 180) / Math.PI;
    }

    /**
     * Empalma el giro libre con la desaceleracion final. Sin este empalme la
     * rueda pega un salto visible al cambiar de la animacion CSS al
     * transform inline: hay que fijar el angulo actual, forzar reflow y
     * recien ahi pedir la transicion.
     */
    function frenarEn(k, alTerminar) {
        var w = $('dwWheel');
        if (!w) { return alTerminar(); }

        // Angulo canonico para dejar el gajo k bajo el puntero.
        var jitter  = (Math.random() - 0.5) * SEG * 0.7;
        var destino = -(k * SEG + SEG / 2) + jitter;

        if (menosMovimiento()) {
            w.classList.remove('is-spinning');
            w.style.transition = 'none';
            w.style.transform = 'rotate(' + destino + 'deg)';
            anguloAcumulado = destino;
            return alTerminar();
        }

        var actual = anguloActual(w);

        w.classList.remove('is-spinning');
        w.style.transition = 'none';
        w.style.transform = 'rotate(' + actual + 'deg)';
        void w.offsetWidth;                       // reflow: fija el punto de partida

        var final = actual + VUELTAS * 360;
        final -= (((final - destino) % 360) + 360) % 360;   // deja final ≡ destino (mod 360)

        w.style.transition = 'transform 4.6s cubic-bezier(.15,.9,.25,1)';
        w.style.transform  = 'rotate(' + final + 'deg)';
        anguloAcumulado = final;

        var listo = false;
        function fin() {
            if (listo) { return; }
            listo = true;
            w.removeEventListener('transitionend', fin);
            alTerminar();
        }
        w.addEventListener('transitionend', fin);
        setTimeout(fin, 5200);                    // red de seguridad
    }

    /* ── Confeti (propio, sin librerias) ──────────────────────────────── */
    function confeti() {
        if (menosMovimiento()) { return; }
        var c = $('dwConf');
        if (!c || !c.getContext) { return; }

        var caja = c.parentNode.getBoundingClientRect();
        c.width = caja.width;
        c.height = caja.height;
        c.hidden = false;

        var ctx = c.getContext('2d');
        var colores = ['#f59e0b', '#4f46e5', '#ec4899', '#22c55e', '#38bdf8'];
        var trozos = [];

        for (var i = 0; i < 70; i++) {
            trozos.push({
                x: c.width / 2, y: c.height / 2,
                vx: (Math.random() - 0.5) * 9,
                vy: Math.random() * -9 - 2,
                g: 0.22 + Math.random() * 0.12,
                w: 4 + Math.random() * 5,
                h: 6 + Math.random() * 6,
                rot: Math.random() * Math.PI,
                vr: (Math.random() - 0.5) * 0.3,
                col: colores[i % colores.length]
            });
        }

        var cuadros = 0;
        (function pintar() {
            ctx.clearRect(0, 0, c.width, c.height);
            trozos.forEach(function (p) {
                p.x += p.vx; p.y += p.vy; p.vy += p.g; p.rot += p.vr;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.col;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            if (++cuadros < 130) { requestAnimationFrame(pintar); }
            else { ctx.clearRect(0, 0, c.width, c.height); c.hidden = true; }
        })();
    }

    /* ── Resultado ────────────────────────────────────────────────────── */
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

        var cerrar = $('dmClose');
        if (cerrar) {
            cerrar.textContent = PREMIOS_RESTANTES > 1
                ? 'Girar de nuevo (quedan ' + (PREMIOS_RESTANTES - 1) + ')'
                : 'Ver el sorteo';
        }

        $('dmTitle').textContent = '🏆 Resultado del sorteo';
        paso(3);
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) { return; }

        if (t.closest('#dmOpen')) {
            var w = $('dwWheel');
            if (w) {
                w.classList.remove('is-spinning');
                w.style.transition = '';
                w.style.transform = 'rotate(' + anguloAcumulado + 'deg)';
            }
            paso(1); abrir(true); return;
        }
        if (t.closest('#dmCancel')) { abrir(false); return; }
        // Al cerrar se recarga: la ficha tiene que mostrar al ganador ya
        // guardado, y los contadores del servidor quedaron viejos.
        if (t.closest('#dmClose')) { window.location.reload(); return; }

        if (!t.closest('#dmGo')) { return; }

        var go = $('dmGo');
        go.disabled = true;
        paso(2);

        // El gajo ganador se decide ANTES de conocer al ganador: es solo la
        // posicion donde se va a escribir el nombre que devuelva el servidor.
        gajoGanador = Math.floor(Math.random() * GAJOS);
        dibujar(nombresDeMuestra());

        var rueda = $('dwWheel');
        if (rueda && !menosMovimiento()) {
            rueda.style.transition = 'none';
            rueda.style.transform = '';
            rueda.classList.add('is-spinning');
        }

        var respuesta = null;
        var pedido = false;

        function resolver() {
            if (!respuesta || pedido) { return; }
            pedido = true;

            if (respuesta.error) {
                if (rueda) { rueda.classList.remove('is-spinning'); }
                abrir(false);
                window.alert(respuesta.error);
                go.disabled = false;
                return;
            }

            var w = (respuesta.winners || [])[0];
            if (!w) { window.location.reload(); return; }

            // El nombre real entra en el gajo destino ANTES de frenar.
            var txt = $('dwTxt' + gajoGanador);
            if (txt) { txt.textContent = corto(w.name); }
            var seg = $('dwSeg' + gajoGanador);
            if (seg) { seg.setAttribute('fill', COLOR_WIN); }

            if (respuesta.pool) {
                var rot = document.querySelector('.dw-pool');
                if (rot) {
                    rot.textContent = 'Girando entre ' + respuesta.pool + ' participantes';
                }
            }

            frenarEn(gajoGanador, function () {
                confeti();
                setTimeout(function () { mostrarGanador(w); }, 700);
            });
        }

        fetch(URL_SORTEO, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: 'quantity=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { respuesta = d; resolver(); })
        .catch(function () {
            respuesta = { error: 'No se pudo realizar el sorteo. Revisa tu conexión.' };
            resolver();
        });
    });
})();
</script>
@endpush
