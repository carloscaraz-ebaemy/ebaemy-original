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
        // Los <el-upload> de Element UI suben con su propio XHR, que NO pasa
        // por axios y por tanto no lleva este header. Sin el, el POST llega a
        // Laravel con pinta de navegacion normal: RedirectModule lo evalua
        // como si fuera un click en el menu y, si el usuario no tiene el
        // modulo de esa ruta, responde 302 al dashboard. El componente
        // recibe el HTML del dashboard en vez del JSON y dice «error de
        // imagen» sin mas. Lo declaramos aqui porque este objeto es el que
        // comparten los 60-y-pico <el-upload> del panel.
        'X-Requested-With': 'XMLHttpRequest',
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
            window.headers_token = {
                'X-CSRF-TOKEN': data.token,
                'X-Requested-With': 'XMLHttpRequest',
            };
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

/**
 * Sesion caida: llevar al login en vez de dejar la pantalla muerta.
 *
 * El servidor ya hacia lo correcto —navegando responde 302 a /login, y por
 * XHR responde 401 con {success:false,message:'No se encuentra autenticado'}—
 * pero NADIE atendia ese 401. El panel es Vue: sus peticiones son XHR, asi que
 * al caducar la sesion el usuario se quedaba en una pantalla que ya no
 * funcionaba, viendo un error sin explicacion y sin que nada le llevara a
 * entrar de nuevo.
 *
 * Dos codigos y dos tratos distintos:
 *   401 — no hay sesion. Al login.
 *   419 — el token caduco pero la sesion puede seguir viva (caso tipico:
 *         iPhone pausa la pestana al abrir la camara). Se refresca el token y
 *         se reintenta UNA vez; solo si eso falla se manda al login. Asi no se
 *         expulsa a nadie por un token viejo cuando su sesion esta bien.
 */
(function () {
    let yaEnMarcha = false;

    function estamosEnLogin() {
        return /(^|\/)login(\/|$)/.test(window.location.pathname);
    }

    function avisarEIrAlLogin() {
        if (yaEnMarcha || estamosEnLogin()) return;
        yaEnMarcha = true;

        // Se avisa antes de mover la pagina: un salto sin explicacion se lee
        // como un fallo mas. Sin dependencias (esto corre antes de ElementUI).
        try {
            const aviso = document.createElement('div');
            aviso.setAttribute('role', 'status');
            aviso.textContent = 'Tu sesion ha caducado. Te llevamos a iniciar sesion…';
            aviso.style.cssText = [
                'position:fixed', 'inset:0 0 auto 0', 'z-index:2147483647',
                'background:#b91c1c', 'color:#fff', 'padding:14px 16px',
                'font:600 14px/1.4 system-ui,-apple-system,sans-serif',
                'text-align:center', 'box-shadow:0 2px 12px rgba(0,0,0,.25)',
            ].join(';');
            document.body.appendChild(aviso);
        } catch (e) { /* si no se puede avisar, al menos se redirige */ }

        // Se RECARGA en vez de ir directo a /login: una navegacion normal hace
        // que el servidor responda 302 y guarde a donde volver (redirect
        // ->guest), asi que tras entrar el usuario aterriza donde estaba.
        setTimeout(function () { window.location.reload(); }, 1200);
    }

    axios.interceptors.response.use(undefined, async function (error) {
        const status = error && error.response ? error.response.status : null;

        if (status === 419 && error.config && !error.config.__csrfReintentado) {
            // refreshCsrfToken usa fetch, no axios: no puede reentrar aqui.
            const ok = await window.refreshCsrfToken();
            if (ok) {
                error.config.__csrfReintentado = true;
                error.config.headers = error.config.headers || {};
                error.config.headers['X-CSRF-TOKEN'] = axios.defaults.headers.common['X-CSRF-TOKEN'];

                return axios.request(error.config);
            }
        }

        if (status === 401 || status === 419) avisarEIrAlLogin();

        return Promise.reject(error);
    });
})();

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
