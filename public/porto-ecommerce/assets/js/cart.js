
function cart_add(data) {

    try {

        let array = localStorage.getItem('products_cart');
        array = array ? JSON.parse(array) : [];
        if (!Array.isArray(array)) array = [];

        let item = JSON.parse(data);
        let found = array.find(x => x.id == item.id);
        const isUpdate = !!found;

        if (!found) {
            array.push(item);
        } else {
            found.quantity = (parseInt(found.quantity) || 1) + (parseInt(item.quantity) || 1);
        }

        localStorage.setItem('products_cart', JSON.stringify(array));
        productsCartDropDown();

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
        +     '<div class="ec-toast__name">' + item.description + '</div>'
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

    jQuery(".dropdown-cart-products").empty();
    jQuery(".cart-count").empty();
    jQuery(".cart-count-label").empty();

    let array = localStorage.getItem('products_cart');
    array = array ? JSON.parse(array) : [];
    if (!Array.isArray(array)) array = [];

    let count = array.length;

    array.forEach(element => {
        const imagePath = (element.image_small && element.image_small !== 'imagen-no-disponible.jpg')
            ? `/storage/uploads/items/${element.image_small}`
            : `/logo/imagen-no-disponible.jpg`;
        const qty      = element.quantity || 1;
        const price    = parseFloat(element.sale_unit_price) || 0;
        const subtotal = (price * qty).toFixed(2);
        const symbol   = element.currency_type_symbol || element.currency_type_id || 'S/';
        const slug     = element.slug ? `/ecommerce/item/${element.slug}` : '#';

        jQuery(".dropdown-cart-products").append(`
            <div class="ec-minicart-item">
                <a href="${slug}" class="ec-minicart-img">
                    <img src="${imagePath}" alt="${element.description}" onerror="this.src='/logo/imagen-no-disponible.jpg'">
                </a>
                <div class="ec-minicart-info">
                    <a href="${slug}" class="ec-minicart-name">${element.description}</a>
                    <span class="ec-minicart-meta">${qty} × ${symbol} ${price.toFixed(2)}</span>
                    <span class="ec-minicart-sub">${symbol} ${subtotal}</span>
                </div>
                <button type="button" onclick="cartRemove(${element.id})" class="ec-minicart-remove" title="Eliminar" aria-label="Eliminar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>`
        );
    });

    jQuery(".cart-count").append(count);
    jQuery(".cart-count-label").append(count);

    // mostrar/ocultar estado vacío
    if (count === 0) {
        jQuery(".ec-minicart-empty").show();
        jQuery(".ec-minicart-footer").hide();
    } else {
        jQuery(".ec-minicart-empty").hide();
        jQuery(".ec-minicart-footer").show();
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

    $(".cart-total-price").empty();
    $(".cart-total-price").append(total.toFixed(2));
}

function cartRemove(id) {
    let array = localStorage.getItem('products_cart');
    array = array ? JSON.parse(array) : [];
    if (!Array.isArray(array)) array = [];

    array = array.filter(x => x.id != id);
    localStorage.setItem('products_cart', JSON.stringify(array));
    productsCartDropDown();
    calculateTotalCart();
}

function logout() {
    $.ajax({
        url: "/ecommerce/logout",
        method: 'get',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
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

// ── Inicializar localStorage al cargar ────────────────────────────────────
(function () {
    try {
        var cart = localStorage.getItem('products_cart');
        if (!cart || !Array.isArray(JSON.parse(cart))) {
            localStorage.setItem('products_cart', '[]');
        }
    } catch (e) {}
    productsCartDropDown();
    calculateTotalCart();
})();
