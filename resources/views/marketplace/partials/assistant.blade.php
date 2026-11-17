{{-- ════════════════════════ ASISTENTE DE COMPRAS ════════════════════════
     Burbuja de chat con Claude (ver App\Services\Marketplace\ShoppingAssistant).
     Sale oculta y solo aparece si /marketplace/asistente/estado dice que está
     encendido: la página se cachea igual para todos, así que no se decide aquí.

     La conversación vive en sessionStorage de este navegador y se manda en
     cada mensaje. El texto del bot se pinta con textContent (nunca innerHTML)
     y los enlaces solo se convierten en <a> si apuntan a este mismo sitio. --}}
<div class="mp-ai" id="mpAi" hidden>
    <button type="button" class="mp-ai__fab" id="mpAiFab" aria-expanded="false" aria-controls="mpAiPanel">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span class="mp-ai__fab-label">¿Te ayudo a buscar?</span>
    </button>
    <section class="mp-ai__panel" id="mpAiPanel" role="dialog" aria-label="Asistente de compras" hidden>
        <header class="mp-ai__head">
            <div>
                <strong>Asistente de compras</strong>
                <span>Busca en todas las tiendas de ebaemy</span>
            </div>
            <button type="button" class="mp-ai__icon" id="mpAiReset" title="Nueva conversación" aria-label="Nueva conversación">↺</button>
            <button type="button" class="mp-ai__icon" id="mpAiClose" aria-label="Cerrar">✕</button>
        </header>
        <div class="mp-ai__log" id="mpAiLog" aria-live="polite"></div>
        <div class="mp-ai__chips" id="mpAiChips">
            <button type="button" data-q="Busco un regalo para mi mamá, menos de 80 soles">Regalo para mamá</button>
            <button type="button" data-q="¿Cómo funcionan los envíos?">Envíos</button>
            <button type="button" data-q="¿Qué hago si mi producto llega con falla?">Devoluciones</button>
        </div>
        <form class="mp-ai__form" id="mpAiForm">
            <input type="text" id="mpAiInput" maxlength="500" autocomplete="off"
                   placeholder="Escribe qué buscas…" aria-label="Escribe tu mensaje">
            <button type="submit" id="mpAiSend" aria-label="Enviar">➤</button>
        </form>
        <p class="mp-ai__note">Responde con IA a partir del catálogo publicado. Revisa precio y stock en la ficha antes de comprar.</p>
    </section>
</div>

<script>
(function () {
    var root = document.getElementById('mpAi');
    if (!root || !window.fetch) return;

    var STORE = 'mp_ai_chat_v1';
    var fab = document.getElementById('mpAiFab');
    var panel = document.getElementById('mpAiPanel');
    var log = document.getElementById('mpAiLog');
    var form = document.getElementById('mpAiForm');
    var input = document.getElementById('mpAiInput');
    var send = document.getElementById('mpAiSend');
    var chips = document.getElementById('mpAiChips');
    var historial = [];
    var ocupado = false;

    function leer() {
        try { return JSON.parse(sessionStorage.getItem(STORE) || '[]') || []; } catch (e) { return []; }
    }
    function guardar() {
        try { sessionStorage.setItem(STORE, JSON.stringify(historial.slice(-20))); } catch (e) { /* sin almacenamiento */ }
    }

    // Texto del bot: los saltos de línea se respetan por CSS; los enlaces
    // a este mismo sitio (o ebaemy.com) se vuelven <a>, el resto queda texto.
    function pintarTexto(el, texto) {
        var partes = String(texto).split(/(https?:\/\/[^\s)]+)/g);
        partes.forEach(function (p, i) {
            if (i % 2 === 1) {
                var url;
                try { url = new URL(p); } catch (e) { url = null; }
                if (url && (url.origin === location.origin || /(^|\.)ebaemy\.com$/.test(url.hostname))) {
                    var a = document.createElement('a');
                    a.href = url.href;
                    a.textContent = url.pathname.length > 1 ? url.pathname : url.hostname;
                    el.appendChild(a);
                    return;
                }
            }
            el.appendChild(document.createTextNode(p));
        });
    }

    function tarjetas(productos) {
        var cont = document.createElement('div');
        cont.className = 'mp-ai__cards';
        productos.slice(0, 6).forEach(function (p) {
            var a = document.createElement('a');
            a.className = 'mp-ai__card';
            a.href = p.url;
            if (p.image_url) {
                var img = document.createElement('img');
                img.src = p.image_url; img.alt = ''; img.loading = 'lazy'; img.width = 64; img.height = 64;
                a.appendChild(img);
            }
            var info = document.createElement('span');
            var t = document.createElement('span'); t.className = 'mp-ai__card-title'; t.textContent = p.title;
            var pr = document.createElement('span'); pr.className = 'mp-ai__card-price';
            pr.textContent = 'S/ ' + Number(p.price || 0).toFixed(2) + (p.tenant_name ? ' · ' + p.tenant_name : '');
            info.appendChild(t); info.appendChild(pr);
            a.appendChild(info);
            cont.appendChild(a);
        });
        return cont;
    }

    function burbuja(rol, texto, productos) {
        var b = document.createElement('div');
        b.className = 'mp-ai__msg mp-ai__msg--' + rol;
        var p = document.createElement('p');
        if (rol === 'assistant') pintarTexto(p, texto); else p.textContent = texto;
        b.appendChild(p);
        if (productos && productos.length) b.appendChild(tarjetas(productos));
        log.appendChild(b);
        log.scrollTop = log.scrollHeight;
        return b;
    }

    function bienvenida() {
        log.textContent = '';
        burbuja('assistant', 'Hola, ¿qué estás buscando? Puedes decírmelo con tus palabras, por ejemplo «un cojín gris para la sala, menos de 50 soles».');
        chips.hidden = historial.length > 0;
    }

    function pintarHistorial() {
        bienvenida();
        historial.forEach(function (m) { burbuja(m.role, m.text, m.products); });
    }

    function abrir() {
        panel.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        input.focus();
    }
    function cerrar() {
        panel.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
    }

    async function enviar(texto) {
        texto = (texto || '').trim();
        if (!texto || ocupado) return;
        ocupado = true; send.disabled = true; chips.hidden = true;
        input.value = '';
        burbuja('user', texto);
        var espera = burbuja('assistant', 'Buscando…');
        espera.classList.add('is-loading');

        var previos = historial.map(function (m) { return { role: m.role, text: m.text }; });
        try {
            var res = await fetch(@json(route('marketplace.assistant.message')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: await window.mpCsrfHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
                body: JSON.stringify({ message: texto, history: previos.slice(-10) }),
            });
            var data = res.status === 429
                ? { text: 'Vas muy rápido. Espera un momento y vuelve a escribir.', products: [] }
                : await res.json();
            espera.remove();
            var respuesta = data.text || data.error || 'No pude responder ahora.';
            burbuja('assistant', respuesta, data.products || []);
            if (res.ok) {
                historial.push({ role: 'user', text: texto });
                historial.push({ role: 'assistant', text: respuesta, products: data.products || [] });
                guardar();
            }
        } catch (e) {
            espera.remove();
            burbuja('assistant', 'No pude conectarme. Revisa tu conexión e inténtalo de nuevo.');
        } finally {
            ocupado = false; send.disabled = false;
        }
    }

    fab.addEventListener('click', function () { panel.hidden ? abrir() : cerrar(); });
    document.getElementById('mpAiClose').addEventListener('click', cerrar);
    document.getElementById('mpAiReset').addEventListener('click', function () {
        historial = []; guardar(); bienvenida();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) cerrar(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); enviar(input.value); });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-q]');
        if (b) enviar(b.getAttribute('data-q'));
    });

    fetch(@json(route('marketplace.assistant.status')), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            if (!d || !d.enabled) return;
            historial = leer();
            pintarHistorial();
            root.hidden = false;
        })
        .catch(function () { /* sin asistente: la burbuja no aparece */ });
})();
</script>
