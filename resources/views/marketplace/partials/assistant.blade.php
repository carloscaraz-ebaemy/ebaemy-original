{{-- ════════════════════════ ASISTENTE DE COMPRAS (IA) ════════════════════════
     Burbuja de chat con Claude (ver App\Services\Marketplace\ShoppingAssistant).
     Sale oculta y solo aparece si /marketplace/asistente/estado dice que está
     encendido: la página se cachea igual para todos, así que no se decide aquí.

     Identidad de asistente, no de chat de soporte: marca propia (la chispa),
     insignia «IA», tarjeta de bienvenida con lo que sabe hacer, pasos de
     trabajo visibles mientras busca («Buscando «cojín»…» ✓), hallazgos con
     rótulo y respuestas rápidas que propone él. Estilos en marketplace.css
     (bloque .mp-ai).

     La conversación vive en el navegador (sessionStorage) y se manda en cada
     mensaje; si el comprador está conectado y no tiene nada local, se recupera
     la última del servidor. Cada navegador tiene una clave de sesión (UUID en
     localStorage) con la que se registran turnos, 👍/👎 y clics.

     La respuesta llega por SSE: eventos `status` y un `done`. El texto del bot
     se pinta con textContent (nunca innerHTML) y los enlaces solo se vuelven
     <a> si apuntan a este sitio o a WhatsApp. --}}
@php
    $mpAiSpark = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5c.4 3.9 3.6 7.1 7.5 7.5-3.9.4-7.1 3.6-7.5 7.5-.4-3.9-3.6-7.1-7.5-7.5 3.9-.4 7.1-3.6 7.5-7.5z"/><path d="M19 15c.2 1.6 1.4 2.8 3 3-1.6.2-2.8 1.4-3 3-.2-1.6-1.4-2.8-3-3 1.6-.2 2.8-1.4 3-3z" opacity=".75"/></svg>';
@endphp
<div class="mp-ai" id="mpAi" hidden>
    <button type="button" class="mp-ai__fab" id="mpAiFab" aria-expanded="false" aria-controls="mpAiPanel">
        <span class="mp-ai__mark">{!! $mpAiSpark !!}</span>
        <span class="mp-ai__fab-label">Asistente IA<small>Te ayudo a encontrar</small></span>
    </button>
    <section class="mp-ai__panel" id="mpAiPanel" role="dialog" aria-label="Asistente de compras con inteligencia artificial" hidden>
        <header class="mp-ai__head">
            <span class="mp-ai__mark">{!! $mpAiSpark !!}</span>
            <div>
                <strong>Asistente de compras<span class="mp-ai__badge">IA</span></strong>
                <span class="mp-ai__status" id="mpAiStatus">Responde al instante</span>
            </div>
            <button type="button" class="mp-ai__icon" id="mpAiReset" title="Nueva conversación" aria-label="Nueva conversación">↺</button>
            <button type="button" class="mp-ai__icon" id="mpAiClose" aria-label="Cerrar">✕</button>
        </header>
        <div class="mp-ai__log" id="mpAiLog" aria-live="polite"></div>
        <div class="mp-ai__chips" id="mpAiChips">
            <button type="button" data-q="Busco un regalo para mi mamá, menos de 80 soles">Regalo para mamá</button>
            <button type="button" data-q="¿Qué ofertas hay hoy?">Ofertas de hoy</button>
            <button type="button" data-q="¿Cómo funcionan los envíos?">Envíos</button>
            <button type="button" data-q="¿Qué hago si mi producto llega con falla?">Devoluciones</button>
        </div>
        <form class="mp-ai__form" id="mpAiForm">
            <input type="text" id="mpAiInput" maxlength="500" autocomplete="off"
                   placeholder="Pregúntame lo que buscas…" aria-label="Escribe tu pregunta">
            <button type="submit" id="mpAiSend" aria-label="Enviar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
            </button>
        </form>
        <p class="mp-ai__note">Respuestas generadas con IA a partir del catálogo publicado. Confirma precio y stock en la ficha.</p>
    </section>
</div>

<script>
(function () {
    var root = document.getElementById('mpAi');
    if (!root || !window.fetch) return;

    var STORE = 'mp_ai_chat_v2';
    var SID_KEY = 'mp_ai_sid';
    var SPARK = @json($mpAiSpark);
    var URLS = {
        message:  @json(route('marketplace.assistant.message')),
        status:   @json(route('marketplace.assistant.status')),
        feedback: @json(route('marketplace.assistant.feedback')),
        event:    @json(route('marketplace.assistant.event')),
        history:  @json(route('marketplace.assistant.history')),
        cart:     @json(route('marketplace.cart')),
    };
    var fab = document.getElementById('mpAiFab');
    var panel = document.getElementById('mpAiPanel');
    var log = document.getElementById('mpAiLog');
    var form = document.getElementById('mpAiForm');
    var input = document.getElementById('mpAiInput');
    var send = document.getElementById('mpAiSend');
    var chips = document.getElementById('mpAiChips');
    var statusEl = document.getElementById('mpAiStatus');
    var historial = [];
    var ocupado = false;
    var listo = false;
    var pendiente = null; // pregunta que llegó antes de saber si el bot está encendido

    // ── Identidad del navegador ───────────────────────────────────────
    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
    function sid() {
        var s = null;
        try { s = localStorage.getItem(SID_KEY); } catch (e) {}
        if (!s || !/^[A-Za-z0-9-]{8,64}$/.test(s)) {
            s = uuid();
            try { localStorage.setItem(SID_KEY, s); } catch (e) {}
        }
        return s;
    }
    function leer() {
        try { return JSON.parse(sessionStorage.getItem(STORE) || '[]') || []; } catch (e) { return []; }
    }
    function guardar() {
        try { sessionStorage.setItem(STORE, JSON.stringify(historial.slice(-20))); } catch (e) { /* sin almacenamiento */ }
    }

    // ── Dónde está el comprador ───────────────────────────────────────
    function contexto() {
        var m = location.pathname.match(/\/marketplace\/item\/([a-z0-9\-]+)/i);
        return { url: location.href.slice(0, 500), slug: m ? m[1] : null };
    }

    // ── Registro silencioso (no bloquea nada) ─────────────────────────
    function post(url, body) {
        return window.mpCsrfHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' })
            .then(function (h) {
                return fetch(url, { method: 'POST', credentials: 'same-origin', headers: h, keepalive: true, body: JSON.stringify(body) });
            });
    }
    function evento(type, slug, messageId) {
        post(URLS.event, { session: sid(), type: type, slug: slug || null, message_id: messageId || null }).catch(function () {});
    }

    // ── Piezas ────────────────────────────────────────────────────────
    function marca(sm) {
        var s = document.createElement('span');
        s.className = 'mp-ai__mark' + (sm ? ' mp-ai__mark--sm' : '');
        s.innerHTML = SPARK; // SVG fijo del servidor, no contenido del usuario
        s.setAttribute('aria-hidden', 'true');
        return s;
    }
    function setBusy(on, texto) {
        root.classList.toggle('is-busy', !!on);
        statusEl.textContent = on ? (texto || 'Pensando…') : 'Responde al instante';
    }

    function pintarTexto(el, texto) {
        var partes = String(texto).split(/(https?:\/\/[^\s)]+)/g);
        partes.forEach(function (p, i) {
            if (i % 2 === 1) {
                var url;
                try { url = new URL(p); } catch (e) { url = null; }
                var propio = url && (url.origin === location.origin || /(^|\.)ebaemy\.com$/.test(url.hostname));
                var wa = url && /^(wa\.me|api\.whatsapp\.com)$/.test(url.hostname);
                if (propio || wa) {
                    var a = document.createElement('a');
                    a.href = url.href;
                    if (wa) { a.target = '_blank'; a.rel = 'noopener'; a.textContent = 'Escribir por WhatsApp'; a.addEventListener('click', function () { evento('handoff'); }); }
                    else a.textContent = url.pathname.length > 1 ? url.pathname.replace(/^\/marketplace\//, '') : url.hostname;
                    el.appendChild(a);
                    return;
                }
            }
            el.appendChild(document.createTextNode(p));
        });
    }

    function tarjetas(productos, messageId) {
        var wrap = document.createElement('div');
        var h = document.createElement('div');
        h.className = 'mp-ai__found';
        h.textContent = productos.length === 1 ? 'Encontré esto' : 'Encontré ' + Math.min(productos.length, 6) + ' opciones';
        wrap.appendChild(h);
        var cont = document.createElement('div');
        cont.className = 'mp-ai__cards';
        productos.slice(0, 6).forEach(function (p) {
            var a = document.createElement('a');
            a.className = 'mp-ai__card';
            a.href = p.url;
            a.addEventListener('click', function () { evento('click', p.slug, messageId); });
            if (p.image_url) {
                var img = document.createElement('img');
                img.src = p.image_url; img.alt = ''; img.loading = 'lazy'; img.width = 56; img.height = 56;
                a.appendChild(img);
            }
            var info = document.createElement('span');
            var t = document.createElement('span'); t.className = 'mp-ai__card-title'; t.textContent = p.title;
            var pr = document.createElement('span'); pr.className = 'mp-ai__card-price';
            pr.textContent = 'S/ ' + Number(p.price || 0).toFixed(2);
            if (p.tenant_name || p.in_stock === false) {
                var sm = document.createElement('small');
                sm.textContent = ' · ' + [p.tenant_name, p.in_stock === false ? 'Agotado' : null].filter(Boolean).join(' · ');
                pr.appendChild(sm);
            }
            info.appendChild(t); info.appendChild(pr);
            a.appendChild(info);
            cont.appendChild(a);
        });
        wrap.appendChild(cont);
        return wrap;
    }

    function sugerencias(lista) {
        var cont = document.createElement('div');
        cont.className = 'mp-ai__suggest';
        lista.slice(0, 3).forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.innerHTML = SPARK;
            b.appendChild(document.createTextNode(s));
            b.addEventListener('click', function () { cont.remove(); enviar(s); });
            cont.appendChild(b);
        });
        return cont;
    }

    function opinion(messageId) {
        var cont = document.createElement('div');
        cont.className = 'mp-ai__rate';
        var label = document.createElement('span'); label.textContent = '¿Te sirvió?';
        cont.appendChild(label);
        [[1, '👍', 'Sí, me sirvió'], [-1, '👎', 'No me sirvió']].forEach(function (o) {
            var b = document.createElement('button');
            b.type = 'button'; b.textContent = o[1]; b.setAttribute('aria-label', o[2]); b.title = o[2];
            b.addEventListener('click', function () {
                cont.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-on'); });
                b.classList.add('is-on');
                label.textContent = o[0] === 1 ? '¡Gracias!' : 'Gracias, lo revisaremos';
                post(URLS.feedback, { session: sid(), message_id: messageId, value: o[0] }).catch(function () {});
            });
            cont.appendChild(b);
        });
        return cont;
    }

    function burbuja(rol, texto, productos, extra) {
        extra = extra || {};
        var b = document.createElement('div');
        b.className = 'mp-ai__msg mp-ai__msg--' + rol;
        if (rol === 'assistant') b.appendChild(marca(true));
        var body = document.createElement('div');
        body.className = 'mp-ai__body';
        var p = document.createElement('p');
        if (rol === 'assistant') pintarTexto(p, texto); else p.textContent = texto;
        body.appendChild(p);
        if (productos && productos.length) body.appendChild(tarjetas(productos, extra.id));
        if (extra.cart) {
            var ir = document.createElement('a');
            ir.className = 'mp-ai__cartlink'; ir.href = URLS.cart;
            ir.textContent = 'Ver carrito' + (extra.cart.count ? ' (' + extra.cart.count + ')' : '');
            body.appendChild(ir);
        }
        if (rol === 'assistant' && extra.id) body.appendChild(opinion(extra.id));
        if (extra.suggestions && extra.suggestions.length && extra.ultimo) body.appendChild(sugerencias(extra.suggestions));
        b.appendChild(body);
        log.appendChild(b);
        log.scrollTop = log.scrollHeight;
        return b;
    }

    // Pasos de trabajo: una lista que crece con cada herramienta.
    function pasos() {
        var b = document.createElement('div');
        b.className = 'mp-ai__msg mp-ai__msg--assistant';
        b.appendChild(marca(true));
        var ul = document.createElement('ul');
        ul.className = 'mp-ai__steps';
        b.appendChild(ul);
        log.appendChild(b);
        log.scrollTop = log.scrollHeight;
        return {
            el: b,
            paso: function (texto) {
                ul.querySelectorAll('li.is-now').forEach(function (li) { li.classList.remove('is-now'); li.classList.add('is-done'); });
                var li = document.createElement('li');
                li.className = 'is-now';
                li.textContent = texto;
                ul.appendChild(li);
                setBusy(true, texto);
                log.scrollTop = log.scrollHeight;
            }
        };
    }

    function bienvenida() {
        log.textContent = '';
        var ctx = contexto();
        var card = document.createElement('div');
        card.className = 'mp-ai__welcome';
        var h = document.createElement('h3');
        h.textContent = ctx.slug ? 'Veo que estás mirando este producto' : 'Hola, soy el asistente de ebaemy';
        var p = document.createElement('p');
        p.textContent = ctx.slug
            ? 'Pregúntame por tallas, colores o el envío a tu ciudad. Si lo quieres, dime «lo quiero» y lo agrego al carrito.'
            : 'Dime con tus palabras qué buscas y lo encuentro entre todas las tiendas. Por ejemplo «un cojín gris para la sala, menos de 50 soles».';
        card.appendChild(h); card.appendChild(p);
        var ul = document.createElement('ul');
        ul.className = 'mp-ai__skills';
        [
            ['M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z', 'Busco en todas las tiendas a la vez'],
            ['M3 7h18M3 12h12M3 17h8', 'Comparo precios, tallas y envío a tu ciudad'],
            ['M6 6h15l-1.5 9h-12zM6 6 5 3H2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm9 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z', 'Agrego al carrito y te paso con la tienda'],
        ].forEach(function (s) {
            var li = document.createElement('li');
            li.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + s[0] + '"/></svg>';
            li.appendChild(document.createTextNode(s[1]));
            ul.appendChild(li);
        });
        card.appendChild(ul);
        log.appendChild(card);
        chips.hidden = historial.length > 0;
    }

    function pintarHistorial() {
        bienvenida();
        historial.forEach(function (m, i) {
            burbuja(m.role, m.text, m.products, { id: m.id, suggestions: m.suggestions, ultimo: i === historial.length - 1 });
        });
    }

    function abrir() {
        panel.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        input.focus();
        if (!abrir.visto) { abrir.visto = true; evento('open'); }
    }
    function cerrar() {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
    }

    // ── Lectura del stream SSE ────────────────────────────────────────
    async function leerSSE(res, onEvent) {
        var reader = res.body.getReader();
        var dec = new TextDecoder();
        var buf = '';
        for (;;) {
            var r = await reader.read();
            if (r.done) break;
            buf += dec.decode(r.value, { stream: true });
            var idx;
            while ((idx = buf.indexOf('\n\n')) >= 0) {
                var bloque = buf.slice(0, idx); buf = buf.slice(idx + 2);
                var ev = 'message', data = '';
                bloque.split('\n').forEach(function (l) {
                    if (l.indexOf('event:') === 0) ev = l.slice(6).trim();
                    else if (l.indexOf('data:') === 0) data += l.slice(5).trim();
                });
                var json = null;
                try { json = JSON.parse(data); } catch (e) {}
                if (json) onEvent(ev, json);
            }
        }
    }

    async function enviar(texto) {
        texto = (texto || '').trim();
        if (!texto || ocupado) return;
        if (!listo) { pendiente = texto; abrir(); return; }
        ocupado = true; send.disabled = true; chips.hidden = true;
        input.value = '';
        log.querySelectorAll('.mp-ai__suggest').forEach(function (s) { s.remove(); });
        burbuja('user', texto);
        var trabajo = pasos();
        trabajo.paso('Pensando…');

        // El servidor valida 2000 caracteres por turno; una respuesta larga no debe tumbar la petición.
        var previos = historial.map(function (m) { return { role: m.role, text: String(m.text || '').slice(0, 2000) }; });
        var body = { message: texto, history: previos.slice(-10), session: sid(), context: contexto() };
        try {
            var res = await fetch(URLS.message, {
                method: 'POST',
                credentials: 'same-origin',
                headers: await window.mpCsrfHeaders({ 'Content-Type': 'application/json', 'Accept': 'text/event-stream' }),
                body: JSON.stringify(body),
            });
            var data = null;
            if (res.status === 429) {
                data = { text: 'Vas muy rápido. Espera un momento y vuelve a escribir.', products: [] };
            } else if (res.ok && /text\/event-stream/.test(res.headers.get('Content-Type') || '') && res.body) {
                await leerSSE(res, function (ev, json) {
                    if (ev === 'status' && json.text && json.text !== 'Pensando…') trabajo.paso(json.text);
                    if (ev === 'done') data = json;
                });
                if (!data) data = { text: 'No pude responder ahora. Puedes usar el buscador de arriba mientras tanto.', products: [] };
            } else {
                data = await res.json();
            }
            trabajo.el.remove();
            var respuesta = data.text || data.error || 'No pude responder ahora.';
            var extra = { id: data.message_id || null, suggestions: data.suggestions || [], cart: data.cart || null, ultimo: true };
            burbuja('assistant', respuesta, data.products || [], extra);
            if (data.cart) {
                evento('cart', data.cart.slug, data.message_id);
                document.dispatchEvent(new CustomEvent('mp:cart-updated', { detail: data.cart }));
            }
            if (res.ok) {
                historial.push({ role: 'user', text: texto });
                historial.push({ role: 'assistant', text: respuesta, products: data.products || [], id: extra.id, suggestions: extra.suggestions });
                guardar();
            }
        } catch (e) {
            trabajo.el.remove();
            burbuja('assistant', 'No pude conectarme. Revisa tu conexión e inténtalo de nuevo.');
        } finally {
            ocupado = false; send.disabled = false; setBusy(false);
        }
    }

    // Lo usan otras partes de la página («Pregúntale al asistente» en 0 resultados).
    window.mpAiAsk = function (texto) {
        abrir();
        if (texto) { evento('search_rescue'); enviar(texto); }
    };

    fab.addEventListener('click', function () { panel.hidden ? abrir() : cerrar(); });
    document.getElementById('mpAiClose').addEventListener('click', cerrar);
    document.getElementById('mpAiReset').addEventListener('click', function () {
        historial = []; guardar();
        try { localStorage.removeItem(SID_KEY); } catch (e) {}
        bienvenida();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) cerrar(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); enviar(input.value); });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-q]');
        if (b) enviar(b.getAttribute('data-q'));
    });

    fetch(URLS.status, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            if (!d || !d.enabled) return;
            historial = leer();
            root.hidden = false;
            if (historial.length) { listo = true; pintarHistorial(); return afterReady(); }
            // Sin nada local: si está conectado, recuperar su última conversación.
            return fetch(URLS.history, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (h) {
                    if (h && h.session_key && h.history && h.history.length) {
                        try { localStorage.setItem(SID_KEY, h.session_key); } catch (e) {}
                        historial = h.history; guardar();
                    }
                })
                .catch(function () {})
                .then(function () { listo = true; pintarHistorial(); afterReady(); });
        })
        .catch(function () { /* sin asistente: la burbuja no aparece */ });

    function afterReady() {
        if (pendiente) { var q = pendiente; pendiente = null; enviar(q); }
    }
})();
</script>
