{{--
    Medición de publicidad del marketplace — píxeles de Meta, TikTok y GA4.

    UN SOLO sitio en todo el marketplace que carga píxeles, incluido desde
    `layout.blade.php`. No se copia a ninguna vista: hay 6 vistas públicas
    (index, category, category_official, tenant, show, favorites) y duplicar
    esto significaría medir dos veces en unas y cero en otras.

    Nada se emite si:
      · el interruptor maestro está apagado en /admin/marketplace/seo,
      · no hay ningún ID de píxel configurado, o
      · el comprador no ha aceptado la medición.

    Las vistas no hablan con fbq/ttq nunca. Llaman a `window.mpTrack(payload)`
    con lo que devuelve AdsTracking::payload(), y este partial decide. Así el
    día que cambie una plataforma se cambia aquí y no en seis archivos.

    OJO con Blade: compila @push/@if incluso dentro de comentarios // de un
    <script>. Dentro del script se comenta con /* */ y nunca con //.
--}}

@php
    use App\Services\Marketplace\AdsTracking;

    $adsCfg = AdsTracking::config();

    // En modo embed (?embed=1, el bottom sheet dentro de un iframe) no se
    // carga nada: la página contenedora ya emitió el PageView y el iframe lo
    // duplicaría, además de meter el banner de cookies dentro del sheet.
    $adsOn       = AdsTracking::browserEnabled() && !request('embed');
    $adsConsent  = AdsTracking::hasConsent();
    $adsConsentUrl = route('marketplace.ads.consent');

    // Lo que el JS necesita saber. Los tokens de servidor NO salen de aquí:
    // sólo viajan los IDs de píxel, que son públicos por naturaleza.
    $adsBootstrap = [
        'meta'   => $adsCfg['meta_pixel'],
        'tiktok' => $adsCfg['tiktok_pixel'],
        'ga4'    => $adsCfg['ga4'],
    ];
@endphp

@if($adsOn)
<script>
(function () {
    'use strict';

    /* Los datos entran con json_encode y eco sin escapar, nunca con la
       directiva corta de Blade: esa parsea sus argumentos con un explode por
       comas y genera PHP invalido en cuanto el array lleva comas anidadas.
       Y ojo: Blade compila sus directivas y sus llaves tambien aqui dentro,
       en un comentario de un <script>. Por eso este texto no escribe ninguna
       de las dos formas. */
    var CFG     = {!! json_encode($adsBootstrap, JSON_UNESCAPED_SLASHES) !!};
    var CONSENT = {!! $adsConsent ? 'true' : 'false' !!};
    var loaded  = false;
    var queue   = [];

    /* ───────── Carga de los píxeles ───────── */

    function loadScript(src) {
        var s = document.createElement('script');
        s.async = true;
        s.src = src;
        document.head.appendChild(s);
    }

    function loadMeta(id) {
        if (window.fbq) return;
        var n = window.fbq = function () {
            n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
        };
        if (!window._fbq) window._fbq = n;
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        loadScript('https://connect.facebook.net/en_US/fbevents.js');
        window.fbq('init', id);
        window.fbq('track', 'PageView');
    }

    function loadTikTok(id) {
        if (window.ttq) return;
        var w = window, t = 'ttq';
        var ttq = w[t] = w[t] || [];
        ttq.methods = ['page','track','identify','instances','debug','on','off',
                       'once','ready','alias','group','enableCookie','disableCookie'];
        ttq.setAndDefer = function (obj, method) {
            obj[method] = function () {
                obj.push([method].concat(Array.prototype.slice.call(arguments, 0)));
            };
        };
        for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
        ttq.load = function (id) {
            ttq._i = ttq._i || {}; ttq._i[id] = []; ttq._t = ttq._t || {}; ttq._t[id] = +new Date();
            ttq._o = ttq._o || {}; ttq._o[id] = {};
            loadScript('https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + id + '&lib=ttq');
        };
        ttq.load(id);
        ttq.page();
    }

    function loadGa4(id) {
        if (window.gtag) return;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        loadScript('https://www.googletagmanager.com/gtag/js?id=' + id);
        window.gtag('js', new Date());
        window.gtag('config', id);
    }

    function loadAll() {
        if (loaded) return;
        loaded = true;
        if (CFG.meta)   loadMeta(CFG.meta);
        if (CFG.tiktok) loadTikTok(CFG.tiktok);
        if (CFG.ga4)    loadGa4(CFG.ga4);
        /* Lo que ocurrió antes de aceptar se emite ahora, en orden. */
        while (queue.length) emit(queue.shift());
    }

    /* ───────── Emisión ───────── */

    /* Nombre canónico → nombre en cada plataforma. El servidor usa el mismo
       mapa en AdsConversionApi: si cambia uno, cambia el otro. */
    var MAP = {
        view_content:      { meta: 'ViewContent',      tiktok: 'ViewContent',      ga4: 'view_item' },
        add_to_cart:       { meta: 'AddToCart',        tiktok: 'AddToCart',        ga4: 'add_to_cart' },
        initiate_checkout: { meta: 'InitiateCheckout', tiktok: 'InitiateCheckout', ga4: 'begin_checkout' },
        purchase:          { meta: 'Purchase',         tiktok: 'CompletePayment',  ga4: 'purchase' },
        lead:              { meta: 'Lead',             tiktok: 'SubmitForm',       ga4: 'generate_lead' }
    };

    function emit(p) {
        var names = MAP[p.event];
        if (!names) return;

        var items    = p.items || [];
        var ids      = items.map(function (i) { return i.content_id; });
        var value    = typeof p.value === 'number' ? p.value : 0;
        var currency = p.currency || 'PEN';

        if (window.fbq && names.meta) {
            window.fbq('track', names.meta, {
                content_type: 'product',
                content_ids: ids,
                contents: items.map(function (i) {
                    return { id: i.content_id, quantity: i.quantity, item_price: i.price };
                }),
                value: value,
                currency: currency
            }, p.event_id ? { eventID: p.event_id } : undefined);
        }

        if (window.ttq && names.tiktok) {
            window.ttq.track(names.tiktok, {
                content_type: 'product',
                contents: items.map(function (i) {
                    return {
                        content_id: i.content_id,
                        content_name: i.content_name,
                        quantity: i.quantity,
                        price: i.price
                    };
                }),
                value: value,
                currency: currency
            }, p.event_id ? { event_id: p.event_id } : undefined);
        }

        if (window.gtag && names.ga4) {
            window.gtag('event', names.ga4, {
                currency: currency,
                value: value,
                items: items.map(function (i) {
                    return {
                        item_id: i.content_id,
                        item_name: i.content_name,
                        quantity: i.quantity,
                        price: i.price
                    };
                })
            });
        }
    }

    /* La API pública para las vistas. Si todavía no hay consentimiento el
       evento se guarda, no se pierde: en cuanto el comprador acepta se emite
       lo que pasó mientras decidía. */
    window.mpTrack = function (payload) {
        if (!payload || !payload.event) return;
        if (!loaded) { queue.push(payload); return; }
        emit(payload);
    };

    window.mpTrackConsent = function (granted) {
        window.mpCsrfHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        })
        .then(function (h) {
            return fetch({!! json_encode($adsConsentUrl) !!}, {
                method: 'POST',
                headers: h,
                credentials: 'same-origin',
                body: JSON.stringify({ consent: granted ? 'granted' : 'denied' })
            });
        }).catch(function () { /* la decisión ya se aplicó en pantalla */ });

        var bar = document.getElementById('mpConsentBar');
        if (bar) bar.remove();

        if (granted) {
            loadAll();
        } else {
            /* Rechazado: se tira lo acumulado, no se guarda para después. */
            queue.length = 0;
        }
    };

    if (CONSENT) loadAll();
})();
</script>

@unless($adsConsent)
{{-- Banner de consentimiento. Hasta que el comprador decida, no se ha cargado
     ningún píxel: el marketplace es público y de cara a consumidores peruanos
     (Ley 29733). Mobile-first: el aviso es una barra inferior que no tapa el
     contenido y con botones de 44px de alto mínimo. --}}
<div id="mpConsentBar" class="mp-consent" role="dialog" aria-live="polite"
     aria-label="Aviso de cookies de medición">
    <p class="mp-consent__text">
        Usamos cookies para medir qué productos interesan y mejorar la tienda.
        <a href="{{ route('marketplace.privacy') }}" rel="nofollow">Más información</a>.
    </p>
    <div class="mp-consent__actions">
        <button type="button" class="mp-consent__btn mp-consent__btn--ghost"
                onclick="window.mpTrackConsent(false)">Rechazar</button>
        <button type="button" class="mp-consent__btn mp-consent__btn--primary"
                onclick="window.mpTrackConsent(true)">Aceptar</button>
    </div>
</div>
<style>
.mp-consent{
    position:fixed; left:0; right:0; bottom:0; z-index:1080;
    display:flex; flex-direction:column; gap:10px;
    padding:14px 16px calc(14px + env(safe-area-inset-bottom));
    background:#11211f; color:#eef6f5;
    box-shadow:0 -6px 24px rgba(0,0,0,.28);
    font-size:13.5px; line-height:1.45;
}
.mp-consent__text{ margin:0; }
.mp-consent__text a{ color:#6fd3c9; text-decoration:underline; }
.mp-consent__actions{ display:flex; gap:10px; }
.mp-consent__btn{
    flex:1 1 0; min-width:0; min-height:44px;
    border-radius:10px; border:0; cursor:pointer;
    font-size:14.5px; font-weight:600;
}
.mp-consent__btn--ghost{ background:transparent; color:#cfe3e1; border:1px solid #3c5b57; }
.mp-consent__btn--primary{ background:#0f8a82; color:#fff; }
@media (min-width:600px){
    .mp-consent{ flex-direction:row; align-items:center; justify-content:space-between; gap:18px; }
    .mp-consent__actions{ flex:0 0 auto; }
    .mp-consent__btn{ flex:0 0 auto; padding:0 20px; }
}
</style>
@endunless
@endif
