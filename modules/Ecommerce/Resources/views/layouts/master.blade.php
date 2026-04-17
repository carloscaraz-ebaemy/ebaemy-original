<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Anti-flash: aplicar tema ANTES de que cargue el CSS --}}
    <script>(function(){var t=localStorage.getItem('ec_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}());</script>
    <script src="{{ asset('porto-ecommerce/assets/js/jquery.min.js') }}"></script>

@php
    $seo = \App\Models\Tenant\ConfigurationEcommerce::first() ?? new \App\Models\Tenant\ConfigurationEcommerce();
    $information = $seo;
    $company = \App\Models\Tenant\Company::first();
    $social_scripts = \App\Models\Tenant\ConfigurationScript::where('active', true)->get();
    $path_logos = asset('storage/uploads/logos/');
    $logo_default = asset('porto-ecommerce/assets/images/logo-black.png');
    $v = $company->updated_at ? $company->updated_at->timestamp : time();
    
    if ($company && $company->favicon) {
        $favicon_url = str_contains($company->favicon, 'storage/') 
                       ? asset($company->favicon) 
                       : asset('storage/uploads/favicons/' . $company->favicon);
    } else {
        $favicon_url = asset('porto-ecommerce/assets/images/icons/favicon.ico');
    }
    
    if ($seo->og_image) {
        $share_image = str_contains($seo->og_image, 'storage/') 
                       ? asset($seo->og_image) 
                       : asset('storage/uploads/logos/' . $seo->og_image);
    } else {
        $share_image = $favicon_url;
    }
@endphp

    {{-- Performance: Preconnect/DNS-Prefetch --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://connect.facebook.net">

    {{-- SEO: Título dinámico por página --}}
    <title>@yield('page_title', $seo->seo_title ?? $company->name ?? 'Tienda Online')</title>
    <meta name="description" content="@yield('meta_description', $seo->seo_description ?? 'Bienvenido a nuestra tienda.')">
    <meta name="keywords" content="@yield('meta_keywords', $seo->seo_keywords ?? 'ecommerce, tienda, decoración, hogar')">
    <meta name="author" content="{{ $seo->seo_author ?? $company->name }}">

    {{-- SEO: Robots --}}
    @if(($seo->indexable ?? true))
        <meta name="robots" content="{{ $seo->seo_robots ?? 'index, follow' }}">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- SEO: Canonical dinámico por página --}}
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    {{-- SEO: Paginación rel prev/next --}}
    @hasSection('prev_page')
        <link rel="prev" href="@yield('prev_page')">
    @endif
    @hasSection('next_page')
        <link rel="next" href="@yield('next_page')">
    @endif

    {{-- SEO: Open Graph --}}
    <meta property="og:locale" content="es_PE">
    <meta property="og:title" content="@yield('og_title', $seo->og_title ?? $seo->seo_title ?? $company->name)">
    <meta property="og:description" content="@yield('og_description', $seo->og_description ?? $seo->seo_description)">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:image" content="@yield('og_image', $share_image . '?v=' . $v)">
    <meta property="og:image:secure_url" content="@yield('og_image', $share_image . '?v=' . $v)">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="{{ $company->name ?? 'Tienda Online' }}">

    {{-- SEO: Google Search Console Verification --}}
    @if(!empty($seo->google_site_verification))
        <meta name="google-site-verification" content="{{ $seo->google_site_verification }}">
    @endif

    {{-- SEO: Twitter Cards --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $seo->twitter_title ?? $seo->seo_title ?? $company->name)">
    <meta name="twitter:description" content="@yield('og_description', $seo->twitter_description ?? $seo->seo_description)">
    <meta name="twitter:image" content="@yield('og_image', $share_image . '?v=' . $v)">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="apple-touch-icon" href="{{ $favicon_url }}?v={{ $v }}">
    <link rel="shortcut icon" href="{{ $favicon_url }}?v={{ $v }}">

    {{-- PWA --}}
    <link rel="manifest" href="{{ route('ecommerce.manifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $company->trade_name ?? $company->name ?? 'Tienda' }}">
    <meta name="theme-color" content="{{ $seo->color_ecommerce ?? '#ff8000' }}" id="ec-theme-color">

    {{-- SEO: Schema.org JSON-LD para el sitio --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Store",
        "name": "{{ $company->name ?? 'Tienda Online' }}",
        "description": "{{ $seo->seo_description ?? 'Tienda de decoración y hogar' }}",
        "url": "{{ url('/ecommerce') }}",
        "logo": "{{ $share_image }}",
        "image": "{{ $share_image }}",
        @if($company->telephone ?? false)
        "telephone": "{{ $company->telephone }}",
        @endif
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "PE"
            @if($company->address ?? false)
            ,"streetAddress": "{{ $company->address }}"
            @endif
        }
        @if($company->trade_name ?? false)
        ,"alternateName": "{{ $company->trade_name }}"
        @endif
    }
    </script>

    {{-- SEO: Organization Schema --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "{{ $company->name ?? config('app.name') }}",
        "url": "{{ url('/ecommerce') }}",
        "logo": "{{ $share_image }}",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "{{ $company->telephone ?? '' }}",
            "contactType": "customer service"
        }
    }
    </script>

    {{-- SEO: BreadcrumbList --}}
    @hasSection('breadcrumb_json')
        @yield('breadcrumb_json')
    @endif

    {{-- Scripts personalizados (head) --}}
    @foreach($social_scripts->where('position', 'head') as $item)
        {!! $item->script !!}
    @endforeach

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/css/rating.css') }}">
    {{-- NO cargar @vite app.js: el ecommerce usa su propia instancia Vue (CDN) --}}
    <link rel="stylesheet" href="{{ asset('porto-ecommerce/assets/font-awesome/css/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="{{ asset(file_exists(public_path('porto-light/css/styles_ecommerce.min.css')) ? 'porto-light/css/styles_ecommerce.min.css' : 'porto-light/css/styles_ecommerce.css') }}">
    <link rel="stylesheet" href="{{ asset('porto-light/css/ecommerce-theme-override.css') }}">
    {{-- Plugins CSS — carga directa sin directiva para compatibilidad --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/drift-basic.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css">
    {{-- Theme por rubro (dinámico según configuración del tenant) --}}
    @php
        $__themeConf = \App\Models\Tenant\ConfigurationEcommerce::first();
        $__theme = $__themeConf->theme_template ?? 'generic';
        $__themeFile = "porto-light/css/themes/{$__theme}.css";
    @endphp
    @if($__theme !== 'generic' && file_exists(public_path($__themeFile)))
        <link rel="stylesheet" href="{{ asset($__themeFile) }}">
    @endif

    {{-- ── Color primario del cliente: inyectado server-side para evitar flash ── --}}
    @php
        $__hex = $seo->color_ecommerce ?? '#ff8000';
        $__hex = ltrim($__hex, '#');
        if (strlen($__hex) === 3) {
            $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2];
        }
        $__r = hexdec(substr($__hex,0,2))/255;
        $__g = hexdec(substr($__hex,2,2))/255;
        $__b = hexdec(substr($__hex,4,2))/255;
        $__max = max($__r,$__g,$__b); $__min = min($__r,$__g,$__b);
        $__l = ($__max+$__min)/2;
        if ($__max == $__min) { $__h = $__s = 0; } else {
            $__d = $__max-$__min;
            $__s = $__l > 0.5 ? $__d/(2-$__max-$__min) : $__d/($__max+$__min);
            if ($__max==$__r)      $__h = ($__g-$__b)/$__d + ($__g<$__b?6:0);
            elseif ($__max==$__g)  $__h = ($__b-$__r)/$__d + 2;
            else                   $__h = ($__r-$__g)/$__d + 4;
            $__h /= 6;
        }
        $__pH = round($__h*360);
        $__pS = round($__s*100).'%';
        $__pL = round($__l*100).'%';
    @endphp
    <style>:root{--primary-h:{{ $__pH }};--primary-s:{{ $__pS }};--primary-l:{{ $__pL }};}</style>
    <!-- Vue debe cargarse ANTES del header (que usa new Vue) -->
    <script src="{{ asset('porto-ecommerce/assets/js/vue.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/axios.min.js') }}"></script>

    {{-- Ocultar comparador de productos en móvil --}}
    <style>
    @media (max-width: 767px) {
        .ec-btn-compare,
        .ec-btn-compare--detail,
        .pcard__compare-mini,
        .ec-compare-bar,
        .ec-compare-modal,
        .ec-compare-toast,
        [data-compare-id] { display: none !important; }
    }

    /* E-commerce: en todas las variantes de cards, cubrir completamente el bloque de imagen */
    .pcard__img,
    .pcard__img--hover,
    .tech-card__img,
    .urb-card__img,
    .eleg-card__img,
    .food-card__img,
    .sport-card__img,
    .lux-card__img,
    .ropa-card__img {
        width: 100%;
        height: 100%;
        object-fit: cover !important;
        object-position: center;
        padding: 0 !important;
    }

    @media (max-width: 767px) {
        .pcard__media,
        .tech-card__media,
        .urb-card__media,
        .eleg-card__media,
        .food-card__media,
        .sport-card__media,
        .lux-card__media,
        .ropa-card__media {
            aspect-ratio: 1 / 1;
            overflow: hidden;
        }
    }
    </style>
</head>

<body>
    @if($social_scripts->where('position', 'body')->isNotEmpty())
        @foreach($social_scripts->where('position', 'body') as $item)
            @if(!empty($item->script))
                {!! $item->script !!}
            @endif
        @endforeach
    @endif

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

    <div class="page-wrapper">
        @include('ecommerce::layouts.partials_ecommerce.header')
        <main class="main" role="main">
            {{-- SEO: Breadcrumbs visibles --}}
            @hasSection('breadcrumbs')
                <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                    <div class="container">
                        @yield('breadcrumbs')
                    </div>
                </nav>
            @endif

            @yield('content')
        </main>

        <footer class="footer" role="contentinfo">
            @include('ecommerce::layouts.partials_ecommerce.footer')
        </footer>
    </div>

    <div class="mobile-menu-overlay"></div>
    <div class="mobile-menu-container">
        @include('ecommerce::layouts.partials_ecommerce.mobile_menu')
    </div>

    <a id="scroll-top" href="#top" title="Volver arriba" role="button"><i class="icon-angle-up"></i></a>

    {{-- ── Newsletter Pop-up ─────────────────────────────────────────── --}}
    @php $nlConfig = \App\Models\Tenant\ConfigurationEcommerce::first(); @endphp
    @if(!empty($nlConfig->newsletter_popup_enabled))
    <div id="ec-nl-overlay" class="ec-nl-overlay" role="dialog" aria-modal="true"
         aria-label="Oferta de bienvenida" style="display:none">
        <div class="ec-nl-modal">
            <button class="ec-nl-close" id="ec-nl-close" aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="ec-nl-body">
                <div class="ec-nl-left">
                    @if(!empty($nlConfig->newsletter_popup_image))
                        <img src="{{ asset('storage/uploads/logos/' . $nlConfig->newsletter_popup_image) }}"
                             alt="Oferta" class="ec-nl-img">
                    @else
                        <div class="ec-nl-icon-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                            </svg>
                        </div>
                    @endif
                </div>
                <div class="ec-nl-right">
                    <p class="ec-nl-tag">Oferta exclusiva</p>
                    <h2 class="ec-nl-title">
                        {{ $nlConfig->newsletter_popup_title ?? '¡Obtén un descuento de bienvenida!' }}
                    </h2>
                    <p class="ec-nl-desc">
                        {{ $nlConfig->newsletter_popup_desc ?? 'Suscríbete y recibe tu código de descuento en tu correo.' }}
                    </p>

                    <div id="ec-nl-form-wrap">
                        <form class="ec-nl-form" id="ec-nl-form" novalidate>
                            <input type="email" id="ec-nl-email" class="ec-nl-input"
                                   placeholder="tu@correo.com" required autocomplete="email">
                            <button type="submit" class="ec-nl-btn">
                                Obtener descuento
                            </button>
                        </form>
                        <p class="ec-nl-privacy">Sin spam. Puedes darte de baja en cualquier momento.</p>
                    </div>

                    <div id="ec-nl-success" class="ec-nl-success" style="display:none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="16 8 10 14 7 11"/>
                        </svg>
                        <p>¡Listo! Revisa tu correo para ver tu código.</p>
                        @if(!empty($nlConfig->newsletter_discount_code))
                        <div class="ec-nl-coupon">
                            <span class="ec-nl-coupon-code">{{ $nlConfig->newsletter_discount_code }}</span>
                            <button type="button" class="ec-nl-copy-btn" id="ec-nl-copy">Copiar</button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Quick View Modal ──────────────────────────────────────────── --}}
    <div id="ec-qv-overlay" class="ec-qv-overlay" role="dialog" aria-modal="true" aria-label="Vista rápida" style="display:none">
        <div class="ec-qv-modal">
            <button class="ec-qv-close" id="ec-qv-close" aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            {{-- Loading state --}}
            <div id="ec-qv-loading" class="ec-qv-loading">
                <div class="ec-qv-spinner"></div>
            </div>

            {{-- Content --}}
            <div id="ec-qv-content" class="ec-qv-content" style="display:none">
                <div class="ec-qv-gallery">
                    <div class="ec-qv-main-wrap">
                        <img id="ec-qv-main-img" src="" alt="" class="ec-qv-main-img">
                    </div>
                    <div id="ec-qv-thumbs" class="ec-qv-thumbs"></div>
                </div>
                <div class="ec-qv-info">
                    <p id="ec-qv-category" class="ec-qv-category"></p>
                    <h2 id="ec-qv-title" class="ec-qv-title"></h2>
                    <div class="ec-qv-price-row">
                        <span id="ec-qv-price" class="ec-qv-price"></span>
                    </div>
                    <p id="ec-qv-stock" class="ec-qv-stock"></p>
                    <p id="ec-qv-desc" class="ec-qv-desc"></p>

                    <div class="ec-qv-qty-row">
                        <div class="ec-qv-qty">
                            <button class="ec-qv-qty-btn" id="ec-qv-qty-minus" aria-label="Menos">−</button>
                            <input id="ec-qv-qty-input" class="ec-qv-qty-input" type="number" value="1" min="1" max="99">
                            <button class="ec-qv-qty-btn" id="ec-qv-qty-plus" aria-label="Más">+</button>
                        </div>
                        <button id="ec-qv-add-cart" class="ec-qv-btn-cart">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            Agregar al carrito
                        </button>
                        <button id="ec-qv-wishlist" class="ec-qv-btn-wish" aria-label="Guardar en favoritos" title="Guardar en favoritos">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>
                    </div>

                    <a id="ec-qv-full-link" href="#" class="ec-qv-full-link">
                        Ver página completa del producto →
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- JS --}}
    <script src="{{ asset('porto-ecommerce/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/plugins.min.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/tracker.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/wishlist.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/cart.js') }}?v=20260401"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/main.js') }}"></script>
    <!-- Vue ya cargado en <head> -->
    <script src="{{ asset('porto-ecommerce/assets/js/lazy-load.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/stock-notify.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/recently-viewed.js?v=20260404') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/compare.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/filter-ajax.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/quick-view.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/image-zoom.js') }}"></script>
    <script src="{{ asset('porto-ecommerce/assets/js/newsletter-popup.js') }}"></script>
    {{-- Plugins JS — carga directa sin directiva para compatibilidad --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/drift-zoom@1/dist/Drift.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js"></script>
    <script src="https://instant.page/5.2.0" type="module"></script>

    {{-- Inicialización de plugins en páginas de producto --}}
    @if(request()->is('ecommerce/item/*'))
        @include('themes._partials.plugins.product-enhancements')
    @endif

    {{-- Inicialización global de plugins --}}
    @include('themes._partials.plugins.init')

    @stack('scripts')

    {{-- PWA: Service Worker registration --}}
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/ecommerce' })
                .then(function (reg) {
                    // Check for updates every 60s
                    setInterval(function () { reg.update(); }, 60000);
                    // Notify user when new version available
                    reg.addEventListener('updatefound', function () {
                        var newWorker = reg.installing;
                        if (!newWorker) return;
                        newWorker.addEventListener('statechange', function () {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                showPwaUpdateToast();
                            }
                        });
                    });
                })
                .catch(function (err) { console.warn('[PWA] SW registration failed:', err); });
        });
    }

    function showPwaUpdateToast() {
        var t = document.createElement('div');
        t.className = 'ec-pwa-update-toast';
        t.innerHTML = '<span>Nueva versión disponible</span><button onclick="window.location.reload()">Actualizar</button>';
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('ec-pwa-update-toast--in'); });
    }
    </script>

    @include('ecommerce::layouts.partials_ecommerce.auth_modal')

    {{-- Social Proof Toast Notifications --}}
    <div id="ec-social-proof" style="position:fixed;bottom:20px;left:20px;z-index:9998;pointer-events:none;"></div>
    <style>
    .ec-sp-toast{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.15);padding:14px 18px;display:flex;align-items:center;gap:12px;max-width:340px;opacity:0;transform:translateY(20px);transition:all .4s ease;pointer-events:auto;margin-top:8px}
    .ec-sp-toast--visible{opacity:1;transform:translateY(0)}
    .ec-sp-toast--hiding{opacity:0;transform:translateY(-10px)}
    .ec-sp-icon{font-size:20px;flex-shrink:0}
    .ec-sp-text{font-size:13px;color:#374151;line-height:1.4}
    .ec-sp-name{font-weight:600;color:#1f2937}
    .ec-sp-time{font-size:11px;color:#9ca3af;margin-top:2px}
    </style>
    <script>
    (function(){
        var container = document.getElementById('ec-social-proof');
        if (!container) return;

        fetch('/ecommerce/social-proof')
            .then(function(r){ return r.json(); })
            .then(function(purchases){
                if (!purchases || !purchases.length) return;
                var idx = 0;
                function showNext() {
                    if (idx >= purchases.length) idx = 0;
                    var p = purchases[idx++];
                    var toast = document.createElement('div');
                    toast.className = 'ec-sp-toast';
                    toast.innerHTML = '<span class="ec-sp-icon">🛒</span><div class="ec-sp-text"><span class="ec-sp-name">' +
                        p.name + (p.city ? ' de ' + p.city : '') +
                        '</span> compró <strong>' + p.product + '</strong><div class="ec-sp-time">' + p.time_ago + '</div></div>';
                    container.appendChild(toast);
                    setTimeout(function(){ toast.classList.add('ec-sp-toast--visible'); }, 50);
                    setTimeout(function(){
                        toast.classList.remove('ec-sp-toast--visible');
                        toast.classList.add('ec-sp-toast--hiding');
                        setTimeout(function(){ if(toast.parentNode) toast.parentNode.removeChild(toast); }, 500);
                    }, 5000);
                }
                // Show first after 8s, then every 25s
                setTimeout(showNext, 8000);
                setInterval(showNext, 25000);
            })
            .catch(function(){});
    })();
    </script>

</body>

</html>
