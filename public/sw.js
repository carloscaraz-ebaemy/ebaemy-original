/**
 * Service Worker — Ecommerce PWA
 * Strategy:
 *   - Static assets (CSS, JS, images, fonts) → Cache-first
 *   - API / HTML pages → Network-first with cache fallback
 *   - Offline fallback → /ecommerce/offline
 */

var CACHE_NAME   = 'ec-v1';
var OFFLINE_URL  = '/ecommerce/offline';

var PRECACHE = [
    '/ecommerce',
    '/porto-light/css/styles_ecommerce.css',
    '/porto-ecommerce/assets/js/bootstrap.bundle.min.js',
    '/porto-ecommerce/assets/js/plugins.min.js',
    '/porto-ecommerce/assets/js/lazy-load.js',
    '/porto-ecommerce/assets/js/wishlist.js',
    '/porto-ecommerce/assets/js/cart.js',
    '/porto-ecommerce/assets/js/compare.js',
    '/porto-ecommerce/assets/images/placeholder.svg',
    '/logo/imagen-no-disponible.jpg',
];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', function (e) {
    e.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE.map(function (url) {
                return new Request(url, { cache: 'reload' });
            })).catch(function (err) {
                // Non-critical: don't block install if some assets 404
                console.warn('[SW] Precache error:', err);
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
                keys.filter(function (k) { return k !== CACHE_NAME; })
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

    // Only handle GET, same-origin + CDN fonts
    if (req.method !== 'GET') return;

    var url = new URL(req.url);

    // Skip Chrome extensions
    if (url.protocol === 'chrome-extension:') return;

    // Static assets → cache-first
    if (isStaticAsset(url)) {
        e.respondWith(cacheFirst(req));
        return;
    }

    // API / AJAX calls → network-only (never cache)
    if (isApiCall(url)) {
        return; // Let the browser handle normally
    }

    // HTML pages → network-first with offline fallback
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
    return url.pathname.includes('/ecommerce/items_bar') ||
           url.pathname.includes('/ecommerce/reviews/') ||
           url.pathname.includes('/ecommerce/apply-coupon') ||
           url.pathname.includes('/exchange_rate/') ||
           url.pathname.includes('/api/');
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
                '<html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Sin conexión</h1><p>Verifica tu conexión a internet.</p></body></html>',
                { headers: { 'Content-Type': 'text/html' } }
            );
        });
    });
}
