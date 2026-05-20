/**
 * Service Worker — Panel Vendedor ebaemy PWA (seller / tenant admin)
 *
 * Scope: / (subdominio del tenant) PERO ignora /ecommerce y /marketplace
 * para no interferir con la tienda del comprador.
 *
 * Estrategia (panel de gestión → datos siempre frescos):
 *   - Assets estáticos → Cache-first
 *   - Todo lo demás (HTML del panel, API) → Network-first con fallback offline
 *   - NUNCA cachea respuestas de mutación (POST/PUT/DELETE las ignora el SW)
 *
 * Bump CACHE_NAME (sl-v2...) en cada deploy que cambie assets precacheados.
 */

var CACHE_NAME  = 'sl-v1';
var OFFLINE_URL = '/dashboard';

var PRECACHE = [
    '/images/icon-192.png',
    '/images/icon-512.png',
];

self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE.map(function (url) {
                return new Request(url, { cache: 'reload' });
            })).catch(function (err) {
                console.warn('[SW-Seller] Precache error:', err);
            });
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) { return k !== CACHE_NAME && k.indexOf('sl-') === 0; })
                    .map(function (k) { return caches.delete(k); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;

    var url = new URL(req.url);
    if (url.protocol === 'chrome-extension:') return;

    // No interferir con la tienda del comprador ni el marketplace
    if (url.pathname.indexOf('/ecommerce') === 0 || url.pathname.indexOf('/marketplace') === 0) {
        return;
    }

    if (isStaticAsset(url)) {
        e.respondWith(cacheFirst(req));
        return;
    }

    // API del panel → network-only (gestión necesita datos al instante)
    if (url.pathname.indexOf('/api/') === 0) {
        return;
    }

    if (req.headers.get('Accept') && req.headers.get('Accept').includes('text/html')) {
        e.respondWith(networkFirstWithFallback(req));
        return;
    }
});

function isStaticAsset(url) {
    return /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?.*)?$/.test(url.pathname);
}

function cacheFirst(req) {
    return caches.match(req).then(function (cached) {
        if (cached) return cached;
        return fetch(req).then(function (res) {
            if (res && res.status === 200) {
                var clone = res.clone();
                caches.open(CACHE_NAME).then(function (c) { c.put(req, clone); });
            }
            return res;
        });
    });
}

function networkFirstWithFallback(req) {
    return fetch(req).catch(function () {
        return caches.match(req).then(function (cached) {
            return cached || new Response(
                '<html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Sin conexión</h1><p>El panel de gestión necesita internet. Verifica tu conexión.</p></body></html>',
                { headers: { 'Content-Type': 'text/html' } }
            );
        });
    });
}
