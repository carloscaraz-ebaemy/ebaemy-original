/**
 * Productos vistos recientemente
 * Almacena hasta 10 IDs en localStorage.
 * API pública: window.RecentlyViewed.push(id), .getAll()
 *
 * Renderiza un Swiper en #ec-recently-viewed (ficha de producto).
 */
(function () {
    'use strict';

    var KEY = 'ec_recently_viewed';
    var MAX = 10;

    function load() {
        try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
    }
    function save(ids) {
        try { localStorage.setItem(KEY, JSON.stringify(ids)); } catch (e) {}
    }

    var RecentlyViewed = {
        push: function (id) {
            var sid = String(id);
            var ids = load().filter(function (i) { return i !== sid; });
            ids.unshift(sid);
            if (ids.length > MAX) ids = ids.slice(0, MAX);
            save(ids);
        },
        getAll: function () { return load(); }
    };

    // ── Renderizar sección con Swiper ───────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('ec-recently-viewed');
        if (!container) return;

        var currentId = container.getAttribute('data-current-id');
        var itemsBar  = container.getAttribute('data-items-bar') || '/ecommerce/items_bar';

        var ids = RecentlyViewed.getAll().filter(function (i) { return i !== String(currentId); });
        if (!ids.length) { container.style.display = 'none'; return; }

        fetch(itemsBar)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var catalog = data.data || [];
                var ordered = [];
                ids.forEach(function (id) {
                    var found = catalog.find(function (p) { return String(p.id) === id; });
                    if (found) ordered.push(found);
                });

                if (!ordered.length) { container.style.display = 'none'; return; }

                // Construir estructura Swiper dentro del contenedor
                container.innerHTML = [
                    '<div class="ec-section-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">',
                    '  <h2 class="ec-section-title" style="margin:0">Vistos recientemente</h2>',
                    '  <div style="display:flex;gap:8px">',
                    '    <div class="ec-rv-prev ec-slider-btn" role="button" aria-label="Anterior">',
                    '      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',
                    '    </div>',
                    '    <div class="ec-rv-next ec-slider-btn" role="button" aria-label="Siguiente">',
                    '      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
                    '    </div>',
                    '  </div>',
                    '</div>',
                    '<div class="swiper ec-rv-swiper">',
                    '  <div class="swiper-wrapper ec-rv-list"></div>',
                    '  <div class="swiper-pagination ec-rv-pagination" style="margin-top:16px;position:relative;bottom:auto"></div>',
                    '</div>'
                ].join('');

                var list = container.querySelector('.ec-rv-list');
                ordered.slice(0, 10).forEach(function (p) {
                    list.appendChild(buildCard(p));
                });

                container.style.display = '';

                if (window.EcLazyLoad) window.EcLazyLoad.scan();

                if (typeof Swiper !== 'undefined') {
                    new Swiper('.ec-rv-swiper', {
                        slidesPerView: 2,
                        spaceBetween: 16,
                        grabCursor: true,
                        navigation: { nextEl: '.ec-rv-next', prevEl: '.ec-rv-prev' },
                        pagination: { el: '.ec-rv-pagination', clickable: true },
                        breakpoints: {
                            576: { slidesPerView: 3 },
                            768: { slidesPerView: 4 },
                            1024: { slidesPerView: 5 },
                        }
                    });
                }
            })
            .catch(function () { container.style.display = 'none'; });
    });

    function buildCard(p) {
        var placeholder = '/porto-ecommerce/assets/images/placeholder.svg';
        var imgSrc = (p.image_url_small && !p.image_url_small.includes('imagen-no-disponible'))
                     ? p.image_url_small : placeholder;
        // amount_sale_unit_price es numérico; sale_unit_price viene formateado "S/ X.XX"
        var rawPrice = parseFloat(p.amount_sale_unit_price || p.sale_unit_price) || 0;
        var price    = rawPrice > 0 ? 'S/ ' + rawPrice.toFixed(2) : (p.sale_unit_price || '');
        var href     = p.slug ? '/ecommerce/item/' + p.slug : '/ecommerce/item/' + p.id;
        var ecCart   = JSON.stringify({
            id: p.id, description: p.description,
            sale_unit_price: p.amount_sale_unit_price || 0,
            currency_type_id: p.currency_type_id,
            currency_type_symbol: p.currency_type_symbol || 'S/',
            image: null, image_medium: null, image_small: null,
            stock: p.stock || 0, slug: p.slug
        }).replace(/'/g, "&#39;");

        var col = document.createElement('div');
        col.className = 'swiper-slide';
        col.innerHTML = [
            '<article class="ec-product-card">',
            '  <button type="button" class="ec-btn-wishlist" data-wishlist-id="' + p.id + '" aria-pressed="false" title="Guardar en favoritos">',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            '  </button>',
            '  <a href="' + href + '" class="ec-product-card__img-wrap" tabindex="-1">',
            '    <img src="' + placeholder + '" data-src="' + imgSrc + '"',
            '         alt="' + (p.description || '') + '"',
            '         class="ec-product-card__img ec-img-lazy"',
            '         width="300" height="300"',
            '         onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">',
            '  </a>',
            '  <div class="ec-product-card__body">',
            '    <h3 class="ec-product-card__title"><a href="' + href + '">' + (p.description || '') + '</a></h3>',
            '    <div class="ec-product-card__footer">',
            '      <div class="ec-product-card__price"><span class="ec-price-current">' + price + '</span></div>',
            '      <button type="button" class="ec-btn-cart paction add-cart"',
            '              data-ec-cart=\'' + ecCart + '\'',
            '              title="Agregar al carrito">',
            '        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
            '        <span class="ec-btn-cart__text">Agregar</span>',
            '      </button>',
            '    </div>',
            '  </div>',
            '</article>'
        ].join('');
        return col;
    }

    window.RecentlyViewed = RecentlyViewed;

}());
