/**
 * Service Worker — Marketplace ebaemy PWA (comprador)
 *
 * Scope: /marketplace (no interfiere con el SW del ecommerce del tenant /sw.js)
 *
 * Estrategia:
 *   - Assets estáticos (CSS, JS, imágenes, fuentes) → Cache-first
 *   - Llamadas API / AJAX (cart, coupon, search-suggest) → Network-only (nunca cachear)
 *   - Páginas HTML → Network-first con fallback offline
 *
 * Bump CACHE_NAME (mp-v2, mp-v3...) en cada deploy que cambie assets precacheados
 * para forzar el refresh del cache en los clientes.
 */

var CACHE_NAME  = 'mp-v1';
var OFFLINE_URL = '/marketplace/offline';

var PRECACHE = [
    '/marketplace',
    '/css/design-tokens.css',
    '/css/marketplace.css',
    '/images/icon-192.png',
    '/images/icon-512.png',
];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE.map(function (url) {
                return new Request(url, { cache: 'reload' });
            })).catch(function (err) {
                // No bloquear install si algún asset 404
                console.warn('[SW-MP] Precache error:', err);
            });
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) { return k !== CACHE_NAME && k.indexOf('mp-') === 0; })
                    .map(function (k) { return caches.delete(k); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;

    var url = new URL(req.url);
    if (url.protocol === 'chrome-extension:') return;

    // Solo manejar requests dentro del scope /marketplace (+ assets globales)
    var inScope = url.pathname.indexOf('/marketplace') === 0 || isStaticAsset(url);
    if (!inScope) return;

    if (isStaticAsset(url)) {
        e.respondWith(cacheFirst(req));
        return;
    }

    // API / AJAX del marketplace → network-only (datos siempre frescos)
    if (isApiCall(url)) {
        return;
    }

    if (req.headers.get('Accept') && req.headers.get('Accept').includes('text/html')) {
        e.respondWith(networkFirstWithFallback(req));
        return;
    }
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function isStaticAsset(url) {
    return /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)(\?.*)?$/.test(url.pathname);
}

function isApiCall(url) {
    return url.pathname.indexOf('/marketplace/api/') === 0 ||
           url.pathname.indexOf('/marketplace/cart') === 0 ||
           url.pathname.indexOf('/marketplace/checkout') === 0 ||
           url.pathname.indexOf('/api/') === 0;
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
    return fetch(req).then(function (res) {
        if (res && res.status === 200) {
            var clone = res.clone();
            caches.open(CACHE_NAME).then(function (c) { c.put(req, clone); });
        }
        return res;
    }).catch(function () {
        return caches.match(req).then(function (cached) {
            return cached || caches.match(OFFLINE_URL) || new Response(
                '<html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Sin conexión</h1><p>Verifica tu conexión a internet e intenta de nuevo.</p></body></html>',
                { headers: { 'Content-Type': 'text/html' } }
            );
        });
    });
}
