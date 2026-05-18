{{-- ════════════════ Bottom sheet del detalle del producto (mobile) ════════════════
     En vez de navegar a /marketplace/item/{slug}, en mobile interceptamos
     el click en cada .mp-card y abrimos el detalle como bottom sheet
     deslizable. Carga el HTML del show.blade va AJAX con ?embed=1,
     extrae el <main> con DOMParser y lo inyecta en el panel. Re-ejecuta
     los <script> embebidos para que galera, variantes y carrito
     funcionen dentro del sheet. En desktop el click navega normal.

     Patrn: Instagram Shopping / TikTok Shop / Shopee.

     Renderizado UNA vez en marketplace/layout.blade.php  funciona en
     todas las vistas del marketplace (home, categora, ofertas, tienda,
     favoritos, etc.) sin duplicar markup.
--}}
<div id="mpProductSheet" class="mp-sheet" aria-hidden="true" role="dialog">
    <div class="mp-sheet__overlay" data-mpsheet-close></div>
    <div class="mp-sheet__panel" role="document">
        <div class="mp-sheet__grabber" data-mpsheet-close aria-label="Cerrar"></div>
        <div class="mp-sheet__topbar">
            <button type="button" class="mp-sheet__close" data-mpsheet-close aria-label="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="mp-sheet__title" id="mpSheetTitle">Producto</div>
            <a href="#" class="mp-sheet__expand" id="mpSheetExpand" target="_blank" rel="noopener" aria-label="Abrir en pgina completa">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>
        </div>
        <div class="mp-sheet__body">
            <div class="mp-sheet__loader" id="mpSheetLoader">
                <div class="mp-sheet__spinner"></div>
                <div class="mp-sheet__loader-text">Cargando producto</div>
            </div>
            <div id="mpSheetContent" class="mp-sheet__content"></div>
        </div>
    </div>
</div>

<style>
/* Solo activo en mobile (<=768px). En desktop nunca se muestra porque
   el JS de intercept solo dispara cuando matchMedia es mobile. */
.mp-sheet {
    position: fixed;
    inset: 0;
    z-index: 200;
    display: none;
    pointer-events: none;
}
.mp-sheet.is-open {
    display: block;
    pointer-events: auto;
}
.mp-sheet__overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    opacity: 0;
    transition: opacity .25s ease;
}
.mp-sheet.is-open .mp-sheet__overlay { opacity: 1; }
.mp-sheet__panel {
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 92dvh;
    background: #fff;
    border-radius: 18px 18px 0 0;
    box-shadow: 0 -10px 40px -10px rgba(0,0,0,.35);
    transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.mp-sheet.is-open .mp-sheet__panel { transform: translateY(0); }
.mp-sheet__grabber {
    align-self: center;
    width: 42px;
    height: 5px;
    background: #cbd5e1;
    border-radius: 999px;
    margin: 8px 0 4px;
    cursor: pointer;
    flex-shrink: 0;
}
.mp-sheet__topbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 10px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.mp-sheet__close,
.mp-sheet__expand {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #f1f5f9;
    border: 0;
    color: #475569;
    cursor: pointer;
    text-decoration: none;
    flex-shrink: 0;
}
.mp-sheet__close:active,
.mp-sheet__expand:active { background: #e2e8f0; }
.mp-sheet__title {
    flex: 1;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mp-sheet__body {
    flex: 1;
    position: relative;
    overflow: hidden;
}
.mp-sheet__content {
    width: 100%;
    height: 100%;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 0 14px 24px;
}
/* show.blade tiene su propio mp-container con padding/max-width.
   Dentro del sheet queremos full width sin extras  resetear. */
.mp-sheet__content .mp-container,
.mp-sheet__content > main { padding: 0 !important; max-width: none !important; margin: 0 !important; }
.mp-sheet__content .mp-breadcrumb { font-size: 12px; margin-bottom: 8px; }
.mp-sheet__loader {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: #fff;
    color: #64748b;
    z-index: 2;
}
.mp-sheet__loader.is-hidden { display: none; }
.mp-sheet__spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e2e8f0;
    border-top-color: #0f8a82;
    border-radius: 50%;
    animation: mpSheetSpin 0.8s linear infinite;
}
.mp-sheet__loader-text { font-size: 13px; font-weight: 500; }
@keyframes mpSheetSpin { to { transform: rotate(360deg); } }
body.mp-sheet-open { overflow: hidden; }
@media (min-width: 769px) {
    .mp-sheet { display: none !important; }
}
</style>

<script>
// Bottom sheet: click en .mp-card en mobile  fetch del HTML del detalle,
// parseamos con DOMParser, inyectamos el <main> en el sheet y
// re-ejecutamos los <script> embebidos para que galera, selector
// de variantes, agregar al carrito, etc. funcionen.
//
// Mismo patrn que usa mpMiniCart pero con HTML server-rendered en vez
// de JSON renderizado en JS. No usamos iframe  todo es nativo del DOM
// padre, sin doble carga de CSS ni overhead.
(function () {
    var sheet  = document.getElementById('mpProductSheet');
    if (!sheet) return;

    var content = document.getElementById('mpSheetContent');
    var loader  = document.getElementById('mpSheetLoader');
    var title   = document.getElementById('mpSheetTitle');
    var expand  = document.getElementById('mpSheetExpand');
    var mqMobile = window.matchMedia('(max-width: 768px)');

    function executeScripts(container) {
        var scripts = container.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            var newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function (attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    function showLoader() {
        loader.classList.remove('is-hidden');
        content.innerHTML = '';
    }
    function hideLoader() {
        loader.classList.add('is-hidden');
    }

    async function loadProduct(url) {
        showLoader();
        try {
            var sep = url.indexOf('?') >= 0 ? '&' : '?';
            var resp = await fetch(url + sep + 'embed=1', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            var html = await resp.text();
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var main = doc.querySelector('main.mp-container') || doc.querySelector('main') || doc.body;
            if (!main) throw new Error('No se encontr el contenido');
            content.innerHTML = main.innerHTML;
            executeScripts(content);
            hideLoader();
            content.scrollTop = 0;
        } catch (err) {
            content.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#64748b">' +
                '<div style="font-size:32px;margin-bottom:10px"></div>' +
                'No se pudo cargar el producto.<br>' +
                '<a href="' + url + '" style="color:#0f8a82;text-decoration:underline">Abrir en pgina completa</a></div>';
            hideLoader();
        }
    }

    function openSheet(url, productName) {
        if (!url) return;
        title.textContent = productName || 'Producto';
        expand.href = url;
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mp-sheet-open');
        loadProduct(url);
        try { history.pushState({ mpSheet: true }, '', url); } catch (e) {}
    }

    function closeSheet(skipHistory) {
        if (!sheet.classList.contains('is-open')) return;
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mp-sheet-open');
        setTimeout(function () { content.innerHTML = ''; }, 320);
        if (!skipHistory && history.state && history.state.mpSheet) {
            try { history.back(); } catch (e) {}
        }
    }

    sheet.addEventListener('click', function (e) {
        if (e.target.closest('[data-mpsheet-close]')) closeSheet();
    });

    window.addEventListener('popstate', function () { closeSheet(true); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSheet();
    });

    // Interceptar click en las cards de productos. Solo en mobile.
    // La card es <div class="mp-card" data-href="..."> (no <a>) y el
    // navigate lo hace listing-card-script via window.location.href en
    // bubble phase. Para interceptar antes:
    //   - capture: true  corremos en capture (antes de bubble)
    //   - stopImmediatePropagation  evitamos que el handler bubble del
    //     listing-card-script ejecute window.location.href
    document.addEventListener('click', function (e) {
        if (!mqMobile.matches) return;
        var card = e.target.closest('.mp-card[data-href]');
        if (!card) return;
        if (e.target.closest('.js-shop-link, .js-alsoin-link, .mp-card-dot, .mp-card-thumb, .mp-card-fav, button, input, select')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var href = card.dataset.href;
        var name = (card.querySelector('.mp-card-title') || {}).textContent || '';
        openSheet(href, name.trim());
    }, true);
})();
</script>
