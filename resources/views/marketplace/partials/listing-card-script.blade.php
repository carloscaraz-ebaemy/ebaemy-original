<script>
// Hover (desktop) o tap (mobile) sobre dots de color/variante → cambia la
// imagen principal de la card y mueve "is-active" al dot seleccionado.
// Sticky: al salir/cerrar, la imagen y el dot activo quedan donde estaban.
//
// Mobile: la card es un <a>; un tap en un dot dentro del <a> en touch
// se interpreta como tap en el <a> y dispara navegacion antes de que
// stopPropagation actue. Solucion: event delegation desde la card en
// CAPTURE PHASE — interceptamos el evento ANTES de que llegue al <a>
// y lo cancelamos. Ademas escuchamos pointerdown/touchstart para que
// el preventDefault llegue lo mas temprano posible en mobile.
document.querySelectorAll('.mp-card').forEach(function (card) {
    var dots = card.querySelectorAll('.mp-card-variant-dot, .mp-card-color-dot[data-img]');
    if (!dots.length) return;
    var primary = card.querySelector('.mp-card-img-primary');
    if (!primary) return;
    var allDots = card.querySelectorAll('.mp-card-color-dot');

    function activate(dot) {
        var url = dot.getAttribute('data-img');
        if (url) primary.src = url;
        if (dot.classList.contains('mp-card-color-dot')) {
            allDots.forEach(function (d) { d.classList.remove('is-active'); });
            dot.classList.add('is-active');
        }
    }
    function dotFromEvent(e) {
        var t = e.target;
        if (!t || !t.closest) return null;
        return t.closest('.mp-card-color-dot[data-img], .mp-card-variant-dot');
    }
    // Captura cualquier tap/click dentro de la card y, si toca un dot,
    // bloquea la navegacion del <a> y aplica el cambio de imagen. true
    // = capture phase: el handler corre antes que cualquier listener
    // en bubble (incluido el del propio <a>).
    function handle(e) {
        var dot = dotFromEvent(e);
        if (!dot) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        activate(dot);
    }
    card.addEventListener('click', handle, true);
    // pointerdown cubre touch y mouse en navegadores modernos.
    // touchstart como fallback para iOS Safari antiguos.
    card.addEventListener('pointerdown', function (e) {
        if (dotFromEvent(e)) e.preventDefault();
    }, true);
    card.addEventListener('touchstart', function (e) {
        if (dotFromEvent(e)) e.preventDefault();
    }, { capture: true, passive: false });

    // Desktop hover: sigue funcionando como antes para preview instantaneo.
    dots.forEach(function (dot) {
        dot.addEventListener('mouseenter', function () { activate(dot); });
    });
});

// Galería rotativa al hover sobre la card (estilo AliExpress / TikTok Shop).
// Si la card tiene data-gallery con un array JSON de URLs, al hover ciclamos
// cada 1.2s. Al salir, volvemos a la imagen original. NO interfiere con
// el hover-image específico de los dots (data-img individual prevalece).
document.querySelectorAll('.mp-card[data-gallery]').forEach(function (card) {
    var primary = card.querySelector('.mp-card-img-primary');
    if (!primary) return;
    var gallery;
    try { gallery = JSON.parse(card.getAttribute('data-gallery') || '[]'); } catch (_) { return; }
    if (!Array.isArray(gallery) || gallery.length < 2) return;

    var originalSrc = primary.getAttribute('src');
    var timer = null;
    var idx = 0;
    var hoveringDot = false;  // si está hovering un dot, dejamos que el dot mande

    card.addEventListener('mouseenter', function () {
        if (timer || hoveringDot) return;
        idx = 0;
        timer = setInterval(function () {
            if (hoveringDot) return;
            idx = (idx + 1) % gallery.length;
            primary.src = gallery[idx];
        }, 1200);
    });
    card.addEventListener('mouseleave', function () {
        if (timer) { clearInterval(timer); timer = null; }
        primary.src = originalSrc;
    });

    // Bloquear el slideshow mientras el cursor esté sobre un dot con data-img
    // (la imagen del dot prevalece sobre el slideshow).
    card.querySelectorAll('.mp-card-color-dot[data-img], .mp-card-variant-dot').forEach(function (d) {
        d.addEventListener('mouseenter', function () { hoveringDot = true; });
        d.addEventListener('mouseleave', function () { hoveringDot = false; });
    });
});

// Sub-links dentro de la card (que es <a>): nombre de tienda + pill
// "Tambien en N tiendas". Mismo bug mobile que los dots — el tap en el
// span propaga al <a> padre antes de que stopPropagation actue.
// Solucion: event delegation desde la card, capture phase, y
// touchstart con preventDefault para abortar la navegacion nativa.
(function () {
    function findSubLink(e) {
        var t = e.target;
        if (!t || !t.closest) return null;
        return t.closest('.js-shop-link, .js-alsoin-link');
    }
    document.querySelectorAll('.mp-card').forEach(function (card) {
        if (!card.querySelector('.js-shop-link, .js-alsoin-link')) return;
        card.addEventListener('click', function (e) {
            var link = findSubLink(e);
            if (!link) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            var href = link.getAttribute('data-href');
            if (href) window.location.href = href;
        }, true);
        card.addEventListener('touchstart', function (e) {
            if (findSubLink(e)) e.preventDefault();
        }, { capture: true, passive: false });
    });
    // Accesibilidad: Enter/Space en sub-links activa la navegacion.
    document.querySelectorAll('.js-shop-link, .js-alsoin-link').forEach(function (el) {
        el.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            var href = el.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
})();

// Wishlist: hidratar cards con estado de favoritos del visitante y
// manejar el toggle del corazón. Session-based; no requiere login.
(function () {
    var toggleUrl = @json(route('marketplace.favorites.toggle'));
    var jsonUrl   = @json(route('marketplace.favorites.json'));
    var csrf      = @json(csrf_token());

    // 1) Sync inicial: pedir los IDs ya guardados y marcar las cards.
    var favSet = new Set();
    fetch(jsonUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            (data.ids || []).forEach(function (id) { favSet.add(id); });
            document.querySelectorAll('.mp-card-fav').forEach(function (btn) {
                var id = parseInt(btn.getAttribute('data-listing-id'), 10);
                if (favSet.has(id)) btn.setAttribute('aria-pressed', 'true');
            });
            if (window.mpFavBadgeUpdate) window.mpFavBadgeUpdate(data.count || 0);
        })
        .catch(function () { /* silent */ });

    // 2) Click handler: toggle vía POST. Si la card está dentro de un <a>,
    //    detenemos propagación para no navegar al detalle.
    document.querySelectorAll('.mp-card-fav').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var id = parseInt(btn.getAttribute('data-listing-id'), 10);
            if (!id) return;

            btn.classList.add('is-pulsing');
            setTimeout(function () { btn.classList.remove('is-pulsing'); }, 350);

            fetch(toggleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ listing_id: id })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                btn.setAttribute('aria-pressed', data.is_favorited ? 'true' : 'false');
                if (window.mpFavBadgeUpdate) window.mpFavBadgeUpdate(data.count || 0);
            })
            .catch(function () { /* silent */ });
        });
    });
})();

// Botón quick-add del card: añade 1 unidad al carrito sin entrar al
// detalle. Si el listing tiene variantes/sin precio, navega al detalle
// (donde el comprador elige opciones).
(function () {
    var addUrl  = @json(route('marketplace.cart.add'));
    var csrf    = @json(csrf_token());
    var detailBase = @json(route('marketplace.index')) + '/item/'; // marketplace.item route

    document.querySelectorAll('.mp-card-quickadd').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Si no se puede quick-add (variantes/sin stock) → ir a detalle.
            if (btn.classList.contains('is-detail')) {
                var slug = btn.getAttribute('data-listing-slug');
                if (slug) window.location.href = detailBase + slug;
                return;
            }

            if (btn.classList.contains('is-loading') || btn.classList.contains('is-added')) return;

            var listingId = parseInt(btn.getAttribute('data-listing-id'), 10);
            if (!listingId) return;

            btn.classList.add('is-loading');

            fetch(addUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ listing_id: listingId, quantity: 1 })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.classList.remove('is-loading');
                if (!data.success) {
                    // Si falla (p.ej. stock cambió), llevamos al detalle para
                    // que el usuario vea el motivo y reintente con contexto.
                    var slug = btn.getAttribute('data-listing-slug');
                    if (slug) window.location.href = detailBase + slug;
                    return;
                }
                btn.classList.add('is-added');
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                if (window.mpCartBadgeUpdate) window.mpCartBadgeUpdate(data.summary);
                setTimeout(function () {
                    btn.classList.remove('is-added');
                    btn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                }, 1500);
            })
            .catch(function () {
                btn.classList.remove('is-loading');
            });
        });
    });
})();
</script>
