{{--
    Pixeles de medicion: Meta, TikTok y GA4.
    Cada uno se pinta solo si su identificador esta configurado en el panel.

    ESTO VIVIA DENTRO DE `master.blade.php`, Y ESE ERA EL PROBLEMA
    -------------------------------------------------------------
    El escaparate no tiene un unico layout: la ficha de producto usa
    `layout_ecommerce_item/record` y los seis views del carrito y el checkout
    usan `layout_ecommerce_cart/index`. Ninguno de los dos extiende `master`,
    asi que los pixeles cargaban en la portada y en las categorias y NO en la
    ficha ni en el carrito.

    Medido en alasitas el 2026-10-12: Meta y TikTok presentes en portada y
    categoria, ausentes en ficha y carrito. O sea que se media el trafico y no
    se media ni una conversion —ni `view_item`, ni `add_to_cart`, ni la
    compra—, y los datos que si llegaban enganaban porque parecian completos.

    Al sacarlo a un parcial, anadir una medicion nueva o un layout nuevo deja
    de depender de que alguien se acuerde de copiarlo.
--}}
@php
    // Dos de los layouts que lo incluyen no definen `$seo`, asi que se
    // resuelve aqui si hace falta. `firstCached()` evita repetir la consulta.
    //
    // Dentro de try/catch porque esto lee la conexion `tenant`, y si no hay
    // tenant resuelto esa conexion no existe y la consulta lanza. Un bloque de
    // medicion no puede tumbar la pagina: sin `$seo` no se pinta ningun pixel
    // y la tienda se sirve igual, que es exactamente lo que debe pasar.
    try {
        $seo = $seo ?? \App\Models\Tenant\ConfigurationEcommerce::firstCached();
    } catch (\Throwable $e) {
        $seo = null;
    }
@endphp
    {{-- ── Meta (Facebook / Instagram) Pixel ────────────────────────────── --}}
    @if(!empty($seo->facebook_pixel_id))
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $seo->facebook_pixel_id }}');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
      src="https://www.facebook.com/tr?id={{ $seo->facebook_pixel_id }}&ev=PageView&noscript=1"/></noscript>
    @endif

    {{-- ── TikTok Pixel ────────────────────────────────────────────────── --}}
    @if(!empty($seo->tiktok_pixel_id))
    <script>
    !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
    ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];
    ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
    for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
    ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};
    ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";
    ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;
    ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript";
    o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];
    a.parentNode.insertBefore(o,a)};
    ttq.load('{{ $seo->tiktok_pixel_id }}');
    ttq.page();}(window, document, 'ttq');
    </script>
    @endif

    {{-- ── Google Analytics 4 ─────────────────────────────────────────── --}}
    @if(!empty($seo->ga4_measurement_id))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seo->ga4_measurement_id }}"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $seo->ga4_measurement_id }}', { send_page_view: true });
    </script>
    @endif
