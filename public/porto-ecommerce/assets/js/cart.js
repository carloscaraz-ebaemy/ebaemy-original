
function cart_add(data) {

    try {

        let array = localStorage.getItem('products_cart');
        array = array ? JSON.parse(array) : [];
        if (!Array.isArray(array)) array = [];

        let item = JSON.parse(data);

        // Validar stock — usar stock de variante si existe, sino del producto
        var stock = item.variant_stock ? parseFloat(item.variant_stock) : (parseFloat(item.stock) || 0);
        // Si tiene variante seleccionada, confiar en su stock
        if (item.variant_id && stock <= 0 && item.variant_stock === undefined) {
            stock = 9999; // variante sin stock info — dejar pasar, el server validará
        }
        if (stock <= 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({icon: 'warning', title: 'Sin stock', text: 'Este producto no tiene stock disponible.', timer: 3000});
            } else {
                alert('Este producto no tiene stock disponible.');
            }
            return;
        }

        // Diferenciar por variant_id si existe (mismo producto, distinta variante = línea separada)
        let found = item.variant_id
            ? array.find(x => x.id == item.id && x.variant_id == item.variant_id)
            : array.find(x => x.id == item.id && !x.variant_id);
        const isUpdate = !!found;

        // Cantidad seleccionada por el usuario (reemplaza, no suma)
        var newQty = parseInt(item.quantity) || 1;
        if (newQty > stock) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({icon: 'warning', title: 'Stock insuficiente', text: 'Solo hay ' + stock + ' unidades disponibles.', timer: 3000});
            } else {
                alert('Solo hay ' + stock + ' unidades disponibles.');
            }
            return;
        }

        if (!found) {
            item.quantity = newQty;
            array.push(item);
        } else {
            found.quantity = newQty;
            if (item.sale_unit_price) found.sale_unit_price = item.sale_unit_price;
        }

        localStorage.setItem('products_cart', JSON.stringify(array));
        productsCartDropDown();
        persistCartToServer();

        // ── Tracking: AddToCart ─────────────────────────
        if (typeof EcommerceTracker !== 'undefined') {
            EcommerceTracker.addToCart({
                id:       item.id,
                name:     item.description,
                price:    parseFloat(item.sale_unit_price) || 0,
                currency: item.currency_type_id || 'PEN',
                quantity: parseInt(item.quantity) || 1
            });
        }

        calculateTotalCart();
        showCartToast(item, isUpdate, array.length);

    } catch (e) {
        console.error('cart_add error:', e);
    }

}

function showCartToast(item, isUpdate, totalItems) {
    var DURATION = 5000; // ms before auto-close

    var imagePath = (item.image_medium && item.image_medium !== 'imagen-no-disponible.jpg')
        ? '/storage/uploads/items/' + item.image_medium
        : '/logo/imagen-no-disponible.jpg';

    var symbol   = item.currency_type_symbol || 'S/';
    var price    = parseFloat(item.sale_unit_price || item.sale_unit) || 0;
    var itemName = item.variant_display_name
        ? item.description + ' — ' + item.variant_display_name
        : item.description;
    var qty      = parseInt(item.quantity) || 1;
    var cartUrl  = '/ecommerce/detail_cart';

    var headerText = isUpdate ? 'Cantidad actualizada' : 'Agregado al carrito';
    var metaText   = symbol + ' ' + price.toFixed(2) + '  ·  ' + totalItems + (totalItems === 1 ? ' producto en carrito' : ' productos en carrito');

    var toastId = 'ec-toast-' + Date.now();

    var html = '<div id="' + toastId + '" class="ec-cart-toast' + (isUpdate ? ' ec-toast--updated' : '') + '" role="alert" aria-live="assertive">'
        + '<div class="ec-toast__header">'
        +   '<span class="ec-toast__header-icon"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>'
        +   '<span class="ec-toast__header-text">' + headerText + '</span>'
        +   '<button type="button" class="ec-toast__close" aria-label="Cerrar">'
        +     '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
        +   '</button>'
        + '</div>'
        + '<div class="ec-toast__body">'
        +   '<div class="ec-toast__img"><img src="' + imagePath + '" alt="' + item.description + '" onerror="this.src=\'/logo/imagen-no-disponible.jpg\'"></div>'
        +   '<div class="ec-toast__info">'
        +     '<div class="ec-toast__name">' + itemName + '</div>'
        +     '<div class="ec-toast__meta">' + metaText + '</div>'
        +   '</div>'
        + '</div>'
        + '<div class="ec-toast__actions">'
        +   '<a href="' + cartUrl + '" class="ec-toast__btn-cart">Ver carrito →</a>'
        +   '<button type="button" class="ec-toast__btn-continue">Seguir comprando</button>'
        + '</div>'
        + '<div class="ec-toast__progress"><div class="ec-toast__progress-bar" style="animation-duration:' + DURATION + 'ms"></div></div>'
        + '</div>';

    var container = document.getElementById('ec-cart-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ec-cart-toast-container';
        document.body.appendChild(container);
    }

    container.insertAdjacentHTML('beforeend', html);
    var toastEl = document.getElementById(toastId);

    // trigger slide-in
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            toastEl.classList.add('ec-toast--visible');
        });
    });

    var timer = setTimeout(function() { dismissToast(toastEl); }, DURATION);

    // close button
    toastEl.querySelector('.ec-toast__close').addEventListener('click', function() {
        clearTimeout(timer);
        dismissToast(toastEl);
    });
    // seguir comprando
    toastEl.querySelector('.ec-toast__btn-continue').addEventListener('click', function() {
        clearTimeout(timer);
        dismissToast(toastEl);
    });
    // pause progress on hover
    toastEl.addEventListener('mouseenter', function() {
        clearTimeout(timer);
        var bar = toastEl.querySelector('.ec-toast__progress-bar');
        if (bar) bar.style.animationPlayState = 'paused';
    });
    toastEl.addEventListener('mouseleave', function() {
        var bar = toastEl.querySelector('.ec-toast__progress-bar');
        if (bar) bar.style.animationPlayState = 'running';
        timer = setTimeout(function() { dismissToast(toastEl); }, 2000);
    });
}

function dismissToast(el) {
    if (!el) return;
    el.classList.remove('ec-toast--visible');
    el.classList.add('ec-toast--hiding');
    setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 400);
}

function productsCartDropDown() {

    // Vanilla JS — sin dependencia de jQuery
    var cartContainer = document.querySelector('.dropdown-cart-products');
    if (cartContainer) cartContainer.innerHTML = '';
    document.querySelectorAll('.cart-count').forEach(function(el) { el.textContent = ''; });
    document.querySelectorAll('.cart-count-label').forEach(function(el) { el.textContent = ''; });

    var array = localStorage.getItem('products_cart');
    array = array ? JSON.parse(array) : [];
    if (!Array.isArray(array)) array = [];

    var count = array.length;

    array.forEach(function(element) {
        var imagePath = (element.image_small && element.image_small !== 'imagen-no-disponible.jpg')
            ? '/storage/uploads/items/' + element.image_small
            : '/logo/imagen-no-disponible.jpg';
        var qty      = element.quantity || 1;
        var price    = parseFloat(element.sale_unit_price) || 0;
        var subtotal = (price * qty).toFixed(2);
        var symbol   = element.currency_type_symbol || element.currency_type_id || 'S/';
        var slug     = element.slug ? '/ecommerce/item/' + element.slug : '#';

        if (cartContainer) {
            cartContainer.insertAdjacentHTML('beforeend',
                '<div class="ec-minicart-item">' +
                    '<a href="' + slug + '" class="ec-minicart-img">' +
                        '<img src="' + imagePath + '" alt="' + (element.description || '') + '" onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">' +
                    '</a>' +
                    '<div class="ec-minicart-info">' +
                        '<a href="' + slug + '" class="ec-minicart-name">' + (element.description || '') + '</a>' +
                        '<span class="ec-minicart-meta">' + qty + ' × ' + symbol + ' ' + price.toFixed(2) + '</span>' +
                        '<span class="ec-minicart-sub">' + symbol + ' ' + subtotal + '</span>' +
                    '</div>' +
                    '<button type="button" class="ec-minicart-remove" data-item-id="' + element.id + '" data-variant-id="' + (element.variant_id || '') + '" title="Eliminar" aria-label="Eliminar">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                    '</button>' +
                '</div>'
            );
        }
    });

    document.querySelectorAll('.cart-count').forEach(function(el) { el.textContent = count; });
    document.querySelectorAll('.cart-count-label').forEach(function(el) { el.textContent = count; });

    // mostrar/ocultar estado vacío
    var emptyEl = document.querySelector('.ec-minicart-empty');
    var footerEl = document.querySelector('.ec-minicart-footer');
    if (count === 0) {
        if (emptyEl) emptyEl.style.display = '';
        if (footerEl) footerEl.style.display = 'none';
    } else {
        if (emptyEl) emptyEl.style.display = 'none';
        if (footerEl) footerEl.style.display = '';
    }
}


function calculateTotalCart() {

    let array = localStorage.getItem('products_cart');
    array = array ? JSON.parse(array) : [];
    if (!Array.isArray(array)) array = [];

    let total = 0;
    array.forEach(element => {
        const qty = element.quantity || 1;
        total += (parseFloat(element.sale_unit_price) || 0) * qty;
    });

    document.querySelectorAll('.cart-total-price').forEach(function(el) { el.textContent = total.toFixed(2); });
}

function cartRemove(id, variantId) {
    var array = localStorage.getItem('products_cart');
    array = array ? JSON.parse(array) : [];
    if (!Array.isArray(array)) array = [];

    array = variantId
        ? array.filter(function(x) { return !(x.id == id && x.variant_id == variantId); })
        : array.filter(function(x) { return !(x.id == id && !x.variant_id); });
    localStorage.setItem('products_cart', JSON.stringify(array));
    productsCartDropDown();
    calculateTotalCart();
    persistCartToServer();

    // Si el carrito quedó vacío, limpiar también en el servidor inmediatamente
    if (array.length === 0) {
        clearCartOnServer();
    }
}

/**
 * Limpia el carrito en el servidor de forma inmediata (sin debounce).
 * Se llama cuando el carrito queda vacío (último item eliminado o vaciar carrito).
 */
function clearCartOnServer() {
    try {
        var token = getCartSessionToken();
        if (!token) return;
        fetch('/ecommerce/cart/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
            },
            body: JSON.stringify({ session_token: token, items: [] }),
            keepalive: true,
        }).catch(function() {});
    } catch (e) {}
}

function logout() {
    $.ajax({
        url: "/ecommerce/logout",
        method: 'get',
        headers: {
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
        },
        success: function () {
            location.reload();
        }
    });
}

// ── Delegado para botones ec-btn-cart (data-ec-cart) ─────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ec-cart]');
    if (!btn) return;
    e.preventDefault();
    try {
        var item = JSON.parse(btn.getAttribute('data-ec-cart'));
        var qtyInput = document.getElementById('ec-qty-input');
        item.quantity = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
        cart_add(JSON.stringify(item));
    } catch(e) {
        cart_add(btn.getAttribute('data-ec-cart'));
    }
});

// ── Carrito Abandonado — persistencia en servidor ────────────────────────────
// Genera o recupera un token de sesión anónimo para identificar el carrito.
var _cartSaveTimer = null;

function getCartSessionToken() {
    var key = 'ec_cart_token';
    var token = localStorage.getItem(key);
    if (!token) {
        token = 'crt_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem(key, token);
    }
    return token;
}

/**
 * Guarda el carrito actual en el servidor (debounce 3 s para no saturar).
 * Se llama automáticamente en cart_add y cartRemove.
 */
function persistCartToServer(extraData) {
    clearTimeout(_cartSaveTimer);
    _cartSaveTimer = setTimeout(function () {
        try {
            var items = JSON.parse(localStorage.getItem('products_cart') || '[]');
            if (!Array.isArray(items)) items = [];

            var payload = Object.assign({
                session_token: getCartSessionToken(),
                items: items,
            }, extraData || {});

            fetch('/ecommerce/cart/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                body: JSON.stringify(payload),
                keepalive: true,
            }).catch(function () {});
        } catch (e) {}
    }, 3000);
}

/**
 * En el primer load, si el localStorage está vacío, intenta restaurar
 * el carrito desde el servidor usando el token guardado.
 */
function maybeRestoreCartFromServer() {
    try {
        var existing = JSON.parse(localStorage.getItem('products_cart') || '[]');
        if (Array.isArray(existing) && existing.length > 0) return; // ya tiene carrito local

        var token = localStorage.getItem('ec_cart_token');
        if (!token) return;

        fetch('/ecommerce/cart/restore?token=' + encodeURIComponent(token))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.items && data.items.length > 0) {
                    localStorage.setItem('products_cart', JSON.stringify(data.items));
                    productsCartDropDown();
                    calculateTotalCart();
                }
            })
            .catch(function () {});
    } catch (e) {}
}

// Inicializar localStorage al cargar ─────────────────────────────────────────
(function () {
    try {
        var cart = localStorage.getItem('products_cart');
        if (!cart || !Array.isArray(JSON.parse(cart))) {
            localStorage.setItem('products_cart', '[]');
        }
    } catch (e) {}
    productsCartDropDown();
    calculateTotalCart();
    maybeRestoreCartFromServer();
})();
