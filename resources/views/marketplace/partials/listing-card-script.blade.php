<script>
// La imagen principal de la card lleva srcset (512w / 1080w). Cambiar solo
// `src` NO se ve: con srcset presente el navegador sigue eligiendo su
// candidato e ignora el src. Todo lo que cambie la foto de una card (dots de
// color/variante, galeria al hover) tiene que pasar por estos dos helpers.
function mpSetCardImage(img, url) {
    if (!img || !url) return;
    if (img.__mpSrcset === undefined) {
        img.__mpSrcset = img.getAttribute('srcset') || '';
        img.__mpSizes  = img.getAttribute('sizes')  || '';
    }
    img.removeAttribute('srcset');
    img.removeAttribute('sizes');
    img.setAttribute('src', url);
}
function mpRestoreCardImage(img, url) {
    if (!img) return;
    img.setAttribute('src', url);
    if (img.__mpSrcset) {
        img.setAttribute('srcset', img.__mpSrcset);
        if (img.__mpSizes) img.setAttribute('sizes', img.__mpSizes);
    }
}

// La segunda foto (la del hover) se descarga al primer acercamiento, no al
// pintar la pagina: son 24 originales a tamano completo que la mayoria de
// visitantes no llega a ver nunca. Va DELEGADO en document para que las cards
// que mete el scroll infinito funcionen sin re-bindear (ver
// feedback_vue_mainwrapper_rerender: aqui el motivo es el mismo).
(function () {
    function hydrate(e) {
        var card = e.target.closest && e.target.closest('.mp-card');
        if (!card) return;
        var img = card.querySelector('.mp-card-img-secondary[data-src]');
        if (!img) return;
        var box = card.querySelector('.mp-card-img');
        // El intercambio de fotos del hover espera a que la segunda este
        // descargada (data-secondary-ready): si no, el primer hover apaga la
        // principal contra un hueco en blanco mientras baja la otra.
        img.addEventListener('load', function () {
            if (box) box.setAttribute('data-secondary-ready', '1');
        }, { once: true });
        img.setAttribute('src', img.getAttribute('data-src'));
        img.removeAttribute('data-src');
    }
    document.addEventListener('mouseover',  hydrate, { passive: true });
    document.addEventListener('touchstart', hydrate, { passive: true });
    document.addEventListener('focusin',    hydrate);
})();

// La card ahora es <div> (no <a>): los dots pueden recibir click sin
// competir con un link padre. Un selector ampliado define que es
// "elemento interactivo" — todo lo que NO lo sea, navega al detalle.
//
// NOTA: todos los handlers de click son DELEGADOS en document para que las
// cards añadidas dinámicamente por el scroll infinito funcionen sin
// re-bindear. Lo no-delegable (galería hover, estado inicial de favoritos,
// badges de cupón) se re-aplica al recibir el evento 'mp:cards-appended'.
(function () {
    var INTERACTIVE_SEL = 'a, button, input, select, textarea, [role="link"], [role="button"], .js-shop-link, .js-alsoin-link';

    // Click en cualquier card → navegar al detalle, EXCEPTO si el click
    // se origina en un elemento interactivo interno (dots, fav, quickadd,
    // pills "tambien en N tiendas", nombre de tienda, etc).
    document.addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('.mp-card');
        if (!card || !card.dataset.href) return;
        // Si el target esta dentro de un elemento interactivo dentro
        // de la card (pero NO el seo-link invisible), no navegamos.
        var inner = e.target.closest(INTERACTIVE_SEL);
        if (inner && inner !== card && !inner.classList.contains('mp-card__seo-link')) {
            return;
        }
        window.location.href = card.dataset.href;
    });
    // Accesibilidad: Enter/Space en la card focuseada navega.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var card = e.target.classList && e.target.classList.contains('mp-card') ? e.target : null;
        if (!card || !card.dataset.href) return;
        e.preventDefault();
        window.location.href = card.dataset.href;
    });

    // Cambio de imagen al click/hover sobre dots de variante. Ahora
    // que la card es <div>, el patron simple del detalle del producto
    // (que SI funciona en mobile) basta: addEventListener('click')
    // sin capture phase ni gimnasia adicional.
    function activate(dot) {
        var card = dot.closest('.mp-card');
        if (!card) return;
        var primary = card.querySelector('.mp-card-img-primary');
        if (!primary) return;
        var url = dot.getAttribute('data-img');
        if (url && primary.getAttribute('src') !== url) {
            mpSetCardImage(primary, url);
        }
        if (dot.classList.contains('mp-card-color-dot')) {
            card.querySelectorAll('.mp-card-color-dot').forEach(function (d) {
                d.classList.remove('is-active');
            });
            dot.classList.add('is-active');
        }
    }
    document.addEventListener('click', function (e) {
        var dot = e.target.closest && e.target.closest(
            '.mp-card-color-dot[data-img], .mp-card-variant-dot'
        );
        if (dot) activate(dot);
    });
    document.addEventListener('mouseover', function (e) {
        var dot = e.target.closest && e.target.closest(
            '.mp-card-color-dot[data-img], .mp-card-variant-dot'
        );
        if (dot) activate(dot);
    });
})();

// Galería rotativa al hover sobre la card (estilo AliExpress / TikTok Shop).
// Si la card tiene data-gallery con un array JSON de URLs, al hover ciclamos
// cada 1.2s. Al salir, volvemos a la imagen original. NO interfiere con
// el hover-image específico de los dots (data-img individual prevalece).
// Se enlaza por-card (mouseenter/leave no se delegan), con guard para no
// duplicar bindeo cuando el scroll infinito añade cards.
function mpBindGallery(scope) {
    (scope || document).querySelectorAll('.mp-card[data-gallery]').forEach(function (card) {
        if (card.__mpGalleryBound) return;
        card.__mpGalleryBound = true;
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
                mpSetCardImage(primary, gallery[idx]);
            }, 1200);
        });
        card.addEventListener('mouseleave', function () {
            if (timer) { clearInterval(timer); timer = null; }
            mpRestoreCardImage(primary, originalSrc);
        });

        // Bloquear el slideshow mientras el cursor esté sobre un dot con data-img
        // (la imagen del dot prevalece sobre el slideshow).
        card.querySelectorAll('.mp-card-color-dot[data-img], .mp-card-variant-dot').forEach(function (d) {
            d.addEventListener('mouseenter', function () { hoveringDot = true; });
            d.addEventListener('mouseleave', function () { hoveringDot = false; });
        });
    });
}
mpBindGallery(document);

// Sub-links dentro de la card: nombre de tienda + pill "Tambien en N
// tiendas". El tap en el span propaga a la card. Solucion: delegacion en
// document en capture phase + touchstart con preventDefault para abortar la
// navegacion de la card. Al ser delegado, funciona con cards dinamicas.
(function () {
    var SUB_SEL = '.js-shop-link, .js-alsoin-link';
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest(SUB_SEL);
        if (!link) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        var href = link.getAttribute('data-href');
        if (href) window.location.href = href;
    }, true);
    document.addEventListener('touchstart', function (e) {
        if (e.target.closest && e.target.closest(SUB_SEL)) e.preventDefault();
    }, { capture: true, passive: false });
    // Accesibilidad: Enter/Space en sub-links activa la navegacion.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var link = e.target.closest && e.target.closest(SUB_SEL);
        if (!link) return;
        e.preventDefault();
        var href = link.getAttribute('data-href');
        if (href) window.location.href = href;
    });
})();

// Wishlist: hidratar cards con estado de favoritos del visitante y
// manejar el toggle del corazón. Session-based; no requiere login.
// Toggle delegado en document; hidratacion re-aplicable a cards nuevas.
(function () {
    var toggleUrl = @json(route('marketplace.favorites.toggle'));
    var jsonUrl   = @json(route('marketplace.favorites.json'));

    var favSet = new Set();

    // Marca las cards (existentes y nuevas) segun favSet. Idempotente.
    function hydrateFavs() {
        document.querySelectorAll('.mp-card-fav').forEach(function (btn) {
            var id = parseInt(btn.getAttribute('data-listing-id'), 10);
            if (favSet.has(id)) btn.setAttribute('aria-pressed', 'true');
        });
    }
    window.addEventListener('mp:cards-appended', hydrateFavs);

    // 1) Sync inicial: pedir los IDs ya guardados y marcar las cards.
    fetch(jsonUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            (data.ids || []).forEach(function (id) { favSet.add(id); });
            hydrateFavs();
            if (window.mpFavBadgeUpdate) window.mpFavBadgeUpdate(data.count || 0);
        })
        .catch(function () { /* silent */ });

    // 2) Toggle delegado: funciona para cualquier .mp-card-fav, incluidas
    //    las que añade el scroll infinito.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.mp-card-fav');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var id = parseInt(btn.getAttribute('data-listing-id'), 10);
        if (!id) return;

        btn.classList.add('is-pulsing');
        setTimeout(function () { btn.classList.remove('is-pulsing'); }, 350);

        window.mpCsrfHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        })
        .then(function (h) {
            return fetch(toggleUrl, {
                method: 'POST',
                headers: h,
                credentials: 'same-origin',
                body: JSON.stringify({ listing_id: id })
            });
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            if (data.is_favorited) favSet.add(id); else favSet.delete(id);
            btn.setAttribute('aria-pressed', data.is_favorited ? 'true' : 'false');
            if (window.mpFavBadgeUpdate) window.mpFavBadgeUpdate(data.count || 0);
        })
        .catch(function () { /* silent */ });
    });
})();

// Botón quick-add del card: añade 1 unidad al carrito sin entrar al
// detalle. Si el listing tiene variantes/sin precio, navega al detalle
// (donde el comprador elige opciones). Delegado en document.
(function () {
    var addUrl  = @json(route('marketplace.cart.add'));
    var detailBase = @json(route('marketplace.index')) + '/item/'; // marketplace.item route

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.mp-card-quickadd');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        // Si no se puede quick-add (variantes/sin stock) → ir a detalle.
        if (btn.classList.contains('is-detail')) {
            var slugD = btn.getAttribute('data-listing-slug');
            if (slugD) window.location.href = detailBase + slugD;
            return;
        }

        if (btn.classList.contains('is-loading') || btn.classList.contains('is-added')) return;

        var slug = btn.getAttribute('data-listing-slug');
        if (!slug) return;

        btn.classList.add('is-loading');

        // El endpoint valida { slug, quantity } — no listing_id.
        window.mpCsrfHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        })
        .then(function (h) {
            return fetch(addUrl, {
                method: 'POST',
                headers: h,
                credentials: 'same-origin',
                body: JSON.stringify({ slug: slug, quantity: 1 })
            });
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.classList.remove('is-loading');
            if (!data.success) {
                // Si falla (p.ej. stock cambió), llevamos al detalle para
                // que el usuario vea el motivo y reintente con contexto.
                if (slug) window.location.href = detailBase + slug;
                return;
            }
            btn.classList.add('is-added');
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            if (window.mpCartBadgeUpdate) window.mpCartBadgeUpdate(data.summary);

            /* AddToCart del quick-add. El payload sale de data.line, que es la
               linea que acaba de confirmar el servidor: asi el precio medido es
               el que se cobro y no el que la card tenia pintado. El content_id
               es mp_{listing_id}, el mismo <g:id> del feed. */
            if (window.mpTrack && data.line) {
                window.mpTrack({
                    event: 'add_to_cart',
                    currency: 'PEN',
                    value: Math.round((data.line.price || 0) * (data.line.quantity || 1) * 100) / 100,
                    items: [{
                        content_id: 'mp_' + data.line.listing_id,
                        content_name: data.line.title || '',
                        quantity: data.line.quantity || 1,
                        price: data.line.price || 0
                    }]
                });
            }
            setTimeout(function () {
                btn.classList.remove('is-added');
                btn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
            }, 1500);
        })
        .catch(function () {
            btn.classList.remove('is-loading');
        });
    });
})();

// ════════ Badge "Cupn disponible" en cards ════════
// Cuando layout.blade.php termina de fetchear /account/coupons/count,
// dispara mp:coupons-loaded con la lista de hostname_ids donde el user
// tiene cupones aplicables. Recorremos las cards y pintamos un badge
// dorado a las que pertenecen a esos tenants. Tambien re-pintamos cuando
// el scroll infinito añade cards.
(function () {
    function paintBadges(tenantIds) {
        if (!Array.isArray(tenantIds) || !tenantIds.length) return;
        var set = new Set(tenantIds.map(function (id) { return String(id); }));
        document.querySelectorAll('.mp-card[data-hostname-id]').forEach(function (card) {
            var hostId = card.getAttribute('data-hostname-id');
            if (!set.has(hostId)) return;
            if (card.querySelector('.mp-card-coupon-badge')) return; // ya pintado
            var badge = document.createElement('div');
            badge.className = 'mp-card-coupon-badge';
            badge.setAttribute('aria-label', 'Tienes cupn disponible para esta tienda');
            badge.innerHTML = '🎟️ <span>Cupn disponible</span>';
            // Lo insertamos al inicio de .mp-card-img para que quede sobre la imagen
            var imgEl = card.querySelector('.mp-card-img');
            if (imgEl) imgEl.appendChild(badge);
        });
    }
    window.addEventListener('mp:coupons-loaded', function (e) {
        window.mpCouponTenantIds = (e.detail && e.detail.tenant_ids) || window.mpCouponTenantIds;
        paintBadges(window.mpCouponTenantIds);
    });
    // Re-pintar cuando el scroll infinito añade cards.
    window.addEventListener('mp:cards-appended', function () {
        paintBadges(window.mpCouponTenantIds);
    });
    // Si el evento ya pas (race: el layout fetche antes que este script
    // se evale), revisar la variable global.
    if (Array.isArray(window.mpCouponTenantIds) && window.mpCouponTenantIds.length) {
        paintBadges(window.mpCouponTenantIds);
    }
})();
</script>
