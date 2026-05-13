import Vue from 'vue';
import lodash from 'lodash';
import moment from 'moment';
import * as Popper from '@popperjs/core';
import jquery from 'jquery';

window._ = lodash;
window.moment = moment;
window.Popper = Popper;
// No sobrescribir un jQuery global existente (por ejemplo, el de porto-ecommerce)
// porque algunos plugins (OwlCarousel) quedan registrados en esa instancia.
if (!window.jQuery && !window.$) {
    window.$ = window.jQuery = jquery;
}

// try {
//     window.$ = window.jQuery = require('jquery');
//     require('bootstrap');
// } catch (e) {}

import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
    window.headers_token = {
        'X-CSRF-TOKEN': token.content,
    }
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

/**
 * Auto-refresh del CSRF token para evitar 419 "Page Expired" cuando el
 * usuario tarda mucho con el formulario abierto.
 *
 * Caso típico iPhone: el-upload abre la cámara → Safari pausa la pestaña
 * → la sesión rota mientras tanto → al volver, el token capturado en
 * window.headers_token quedó obsoleto y el POST a /upload da 419.
 *
 * Mutamos las propiedades del MISMO objeto window.headers_token (no
 * reasignamos) para que las vistas que ya lo importaron como
 * `:headers="headers"` vean el valor nuevo sin necesidad de reactividad.
 */
window.refreshCsrfToken = async function () {
    try {
        const r = await fetch('/csrf-refresh', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!r.ok) return false;
        const data = await r.json();
        if (!data || !data.token) return false;

        const metaEl = document.head.querySelector('meta[name="csrf-token"]');
        if (metaEl) metaEl.setAttribute('content', data.token);
        axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;
        if (window.headers_token) {
            window.headers_token['X-CSRF-TOKEN'] = data.token;
        } else {
            window.headers_token = { 'X-CSRF-TOKEN': data.token };
        }
        return true;
    } catch (e) {
        return false;
    }
};

// Refrescar al volver a la pestaña — atrapa el caso iPhone Safari pausa.
// Se ejecuta cuando el usuario regresa después de la cámara/galería.
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            window.refreshCsrfToken();
        }
    });
    // Heartbeat: refresca cada 60s para no llegar nunca a expiración.
    // 60s es barato (1 GET/min) y mantiene la sesión efectivamente viva.
    setInterval(function () {
        if (document.visibilityState === 'visible') {
            window.refreshCsrfToken();
        }
    }, 60 * 1000);
}

Vue.prototype.$http = axios;

Vue.prototype.$setStorage =   function(name,obj){
    localStorage.setItem(name, JSON.stringify(obj));
};
Vue.prototype.$getStorage = function(name){
    return JSON.parse(localStorage.getItem(name));
};

import './vendor/perfect-scrollbar.jquery.min';
import './vendor/sidebarmenu';
import './vendor/waves';
import './vendor/custom';

$(function () {
    const listElements = document.getElementsByClassName('nav-active');
    if (listElements.length > 0) {
        listElements[0].scrollIntoView();
    }
});


const mercadopago = window.Mercadopago;

if(mercadopago)
{
    mercadopago.setPublishableKey(window.token_mercado_pago);
    mercadopago.getIdentificationTypes();
}
