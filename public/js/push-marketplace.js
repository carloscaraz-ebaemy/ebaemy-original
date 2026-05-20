/**
 * Web Push — suscripción del comprador del marketplace.
 *
 * NO pide permiso al cargar (mala UX). Expone window.ebaemyEnablePush()
 * para llamarse desde un botón ("Activar notificaciones") o tras una
 * acción del usuario (ej. después de hacer un pedido).
 *
 * Requiere que el SW /sw-marketplace.js ya esté registrado (lo hace el layout).
 */
(function () {
    'use strict';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    // ¿El navegador soporta push? (iOS solo en PWA instalada iOS 16.4+)
    window.ebaemyPushSupported = function () {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    };

    // Estado actual del permiso: 'default' | 'granted' | 'denied'
    window.ebaemyPushPermission = function () {
        return window.ebaemyPushSupported() ? Notification.permission : 'unsupported';
    };

    window.ebaemyEnablePush = function () {
        if (!window.ebaemyPushSupported()) {
            return Promise.reject(new Error('Push no soportado en este navegador'));
        }

        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                throw new Error('Permiso denegado');
            }
            return navigator.serviceWorker.ready;
        }).then(function (reg) {
            // Traer la VAPID public key del backend
            return fetch('/marketplace/push/public-key')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.key) throw new Error('VAPID public key no configurada en el servidor');
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(data.key),
                    });
                });
        }).then(function (subscription) {
            // Enviar la suscripción al backend
            return fetch('/marketplace/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
        }).then(function (r) { return r.json(); });
    };

    window.ebaemyDisablePush = function () {
        if (!window.ebaemyPushSupported()) return Promise.resolve();
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (!sub) return;
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () {
                return fetch('/marketplace/push/unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ endpoint: endpoint }),
                });
            });
        });
    };
})();
