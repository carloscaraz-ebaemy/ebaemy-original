/**
 * Quick View — modal de vista rápida de producto
 * Se dispara con cualquier .ec-btn-quickview[href] o [data-item-id]
 */
(function () {
    'use strict';

    var overlay  = document.getElementById('ec-qv-overlay');
    var loading  = document.getElementById('ec-qv-loading');
    var content  = document.getElementById('ec-qv-content');
    var mainImg  = document.getElementById('ec-qv-main-img');
    var thumbs   = document.getElementById('ec-qv-thumbs');
    var category = document.getElementById('ec-qv-category');
    var title    = document.getElementById('ec-qv-title');
    var price    = document.getElementById('ec-qv-price');
    var stockEl  = document.getElementById('ec-qv-stock');
    var descEl   = document.getElementById('ec-qv-desc');
    var qtyInput = document.getElementById('ec-qv-qty-input');
    var addBtn   = document.getElementById('ec-qv-add-cart');
    var wishBtn  = document.getElementById('ec-qv-wishlist');
    var fullLink = document.getElementById('ec-qv-full-link');

    var currentItem = null;

    if (!overlay) return; // guard

    // ── Abrir modal ──────────────────────────────────────────────────────────
    function open(itemId) {
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        loading.style.display = 'flex';
        content.style.display = 'none';
        qtyInput.value = 1;

        fetch('/ecommerce/item_quick/' + itemId)
            .then(function (r) {
                if (!r.ok) throw new Error('Error ' + r.status);
                return r.json();
            })
            .then(function (data) {
                render(data);
            })
            .catch(function () {
                close();
            });
    }

    // ── Renderizar datos ─────────────────────────────────────────────────────
    function render(data) {
        currentItem = data;

        // Galería
        var imgs = data.images && data.images.length ? data.images : ['/logo/imagen-no-disponible.jpg'];
        mainImg.src = imgs[0];
        mainImg.alt = data.description;

        thumbs.innerHTML = imgs.length > 1
            ? imgs.map(function (src, i) {
                return '<button class="ec-qv-thumb' + (i === 0 ? ' ec-qv-thumb--active' : '') + '" ' +
                       'data-src="' + src + '" aria-label="Imagen ' + (i + 1) + '">' +
                       '<img src="' + src + '" alt="" loading="lazy">' +
                       '</button>';
              }).join('')
            : '';

        // Info
        category.textContent = data.category || '';
        category.style.display = data.category ? '' : 'none';
        title.textContent = data.description;
        price.textContent = (data.symbol || 'S/') + ' ' + parseFloat(data.price).toFixed(2);

        var inStock = data.stock > 0;
        stockEl.className = 'ec-qv-stock ' + (inStock ? 'ec-qv-stock--in' : 'ec-qv-stock--out');
        stockEl.textContent = inStock ? '✓ En stock (' + data.stock + ' disponibles)' : '✗ Agotado';

        descEl.textContent = data.name || '';
        descEl.style.display = data.name ? '' : 'none';

        // Botón carrito
        addBtn.disabled = !inStock;
        addBtn.style.opacity = inStock ? '1' : '.5';

        // Wishlist
        updateWishBtn(data.id);

        // Enlace completo
        fullLink.href = '/ecommerce/item/' + (data.slug || data.id);

        loading.style.display = 'none';
        content.style.display = '';
    }

    // ── Cerrar ───────────────────────────────────────────────────────────────
    function close() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        currentItem = null;
    }

    // ── Wishlist button state ────────────────────────────────────────────────
    function updateWishBtn(id) {
        if (!wishBtn) return;
        var active = window.Wishlist && window.Wishlist.has(id);
        var svg = wishBtn.querySelector('svg');
        if (svg) {
            svg.setAttribute('fill', active ? '#e53e3e' : 'none');
            svg.setAttribute('stroke', active ? '#e53e3e' : 'currentColor');
        }
        wishBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
        wishBtn.title = active ? 'Quitar de favoritos' : 'Guardar en favoritos';
    }

    // ── Event listeners ──────────────────────────────────────────────────────

    // Click en botón quickview (delegado)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ec-btn-quickview');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        // Obtener ID: primero data-item-id, si no parsear del href
        var itemId = btn.getAttribute('data-item-id');
        if (!itemId) {
            var href = btn.getAttribute('href') || '';
            var match = href.match(/item_partial\/(\d+)/);
            if (match) itemId = match[1];
        }
        if (itemId) open(itemId);
    });

    // Cerrar con X
    document.getElementById('ec-qv-close').addEventListener('click', close);

    // Cerrar al hacer click en el overlay (fuera del modal)
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    // Thumbnails
    thumbs.addEventListener('click', function (e) {
        var btn = e.target.closest('.ec-qv-thumb');
        if (!btn) return;
        mainImg.src = btn.getAttribute('data-src');
        thumbs.querySelectorAll('.ec-qv-thumb').forEach(function (t) {
            t.classList.toggle('ec-qv-thumb--active', t === btn);
        });
    });

    // Cantidad
    document.getElementById('ec-qv-qty-minus').addEventListener('click', function () {
        var v = parseInt(qtyInput.value, 10) || 1;
        if (v > 1) qtyInput.value = v - 1;
    });
    document.getElementById('ec-qv-qty-plus').addEventListener('click', function () {
        var v = parseInt(qtyInput.value, 10) || 1;
        qtyInput.value = v + 1;
    });

    // Agregar al carrito
    addBtn.addEventListener('click', function () {
        if (!currentItem || !currentItem.item_data) return;
        var qty = parseInt(qtyInput.value, 10) || 1;
        var item = Object.assign({}, currentItem.item_data, { quantity: qty });

        // Dispara el mismo evento que usa add-cart
        if (typeof cart_add === 'function') {
            cart_add(JSON.stringify(item));
        } else {
            // Fallback: simular click en botón add-cart de la grilla
            var fakeBtn = document.createElement('button');
            fakeBtn.className = 'paction add-cart';
            fakeBtn.setAttribute('data-product', JSON.stringify(item));
            fakeBtn.style.display = 'none';
            document.body.appendChild(fakeBtn);
            fakeBtn.click();
            setTimeout(function () { fakeBtn.remove(); }, 200);
        }

        // Feedback visual
        var orig = addBtn.innerHTML;
        addBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ¡Agregado!';
        addBtn.style.background = '#16a34a';
        setTimeout(function () {
            addBtn.innerHTML = orig;
            addBtn.style.background = '';
        }, 1800);
    });

    // Wishlist
    wishBtn.addEventListener('click', function () {
        if (!currentItem || !window.Wishlist) return;
        window.Wishlist.toggle(currentItem.id);
        updateWishBtn(currentItem.id);
    });

    // Sincronizar wishlist btn si cambia desde otro lugar
    document.addEventListener('wishlist:changed', function () {
        if (currentItem) updateWishBtn(currentItem.id);
    });

}());
