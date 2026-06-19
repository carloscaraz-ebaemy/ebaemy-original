@php
    // Modo embed: cuando una URL viene con ?embed=1, se asume que se est
    // mostrando dentro de un iframe (bottom sheet mobile). Oculta
    // header/footer/trust bar va CSS para que solo se vea el contenido.
    $isEmbed = (bool) request('embed');

    // Config administrable desde /admin/marketplace/seo. Cacheado 10min.
    // Fallbacks profesionales por si los campos están vacíos.
    $mpCfg = \App\Models\System\Configuration::firstCached();
    $mpOgTitle = $mpCfg->marketplace_og_title
                ?? 'Marketplace ebaemy — Compra de tiendas verificadas';
    $mpOgDesc  = $mpCfg->marketplace_og_description
                ?? 'Descubre productos de tiendas peruanas verificadas en un solo lugar. Envío a todo Perú, contacto directo con el vendedor.';
    $mpOgImage = $mpCfg ? $mpCfg->marketplace_og_image_url : asset('logo/logo.jpg');
    $mpKeywords= $mpCfg->marketplace_meta_keywords
                ?? 'marketplace peru, ebaemy, tiendas online verificadas, compra segura, productos peruanos';

    // sameAs: perfiles oficiales para que Google/Bing reconozcan "ebaemy"
    // como una entidad/marca propia (clave para separar la marca de "eBay"
    // en los resultados de búsqueda y habilitar el panel de conocimiento).
    $mpSameAs = array_values(array_filter([
        $mpCfg->marketplace_facebook_url  ?? null,
        $mpCfg->marketplace_instagram_url ?? null,
        $mpCfg->marketplace_tiktok_url    ?? null,
    ]));

    // JSON-LD Organization + WebSite. El WebSite.potentialAction habilita el
    // "sitelinks searchbox" (cuadro de búsqueda dentro del resultado de Google).
    $mpHome = route('marketplace.index');
    $mpJsonLd = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'        => 'Organization',
                '@id'          => url('/') . '#organization',
                'name'         => 'ebaemy',
                'alternateName'=> 'ebaemy Marketplace',
                'url'          => url('/'),
                'logo'         => asset('logo/logo.jpg'),
                'description'  => $mpOgDesc,
                'email'        => 'soporte@ebaemy.com',
                'areaServed'   => ['@type' => 'Country', 'name' => 'Perú'],
                'sameAs'       => $mpSameAs,
            ],
            [
                '@type'           => 'WebSite',
                '@id'             => url('/') . '#website',
                'name'            => 'ebaemy',
                'alternateName'   => 'Marketplace ebaemy',
                'url'             => url('/'),
                'inLanguage'      => 'es-PE',
                'publisher'       => ['@id' => url('/') . '#organization'],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $mpHome . '?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- CSRF para fetch() del newsletter, cart, coupon, etc. Sin este meta
         las llamadas POST/PATCH/DELETE devuelven 419 'CSRF token mismatch'. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $mpOgTitle)</title>
    <meta name="description" content="@yield('description', $mpOgDesc)">
    <meta name="keywords"    content="@yield('keywords', $mpKeywords)">
    <meta name="robots"      content="@yield('robots', 'index, follow')">
    <meta name="theme-color" content="#0f8a82">

    {{-- PWA — instalable como app del marketplace (scope /marketplace).
         Usa manifest e íconos propios, NO colisiona con la PWA del
         ecommerce del tenant (/manifest.json + /sw.js). --}}
    @unless($isEmbed)
    <link rel="manifest" href="{{ asset('manifest-marketplace.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ebaemy">
    <meta name="mobile-web-app-capable" content="yes">
    @endunless

    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph (administrable desde /admin/marketplace/seo) --}}
    <meta property="og:site_name"   content="ebaemy Marketplace">
    <meta property="og:locale"      content="es_PE">
    <meta property="og:type"        content="@yield('og_type', 'website')">
    <meta property="og:title"       content="@yield('og_title', $mpOgTitle)">
    <meta property="og:description" content="@yield('og_description', $mpOgDesc)">
    <meta property="og:image"       content="@yield('og_image', $mpOgImage)">
    <meta property="og:image:secure_url" content="@yield('og_image', $mpOgImage)">
    {{-- Las dimensiones DEBEN coincidir con la imagen real o WhatsApp/Facebook
         descartan el preview. El detalle de producto las sobreescribe a 1080x1080
         (variante _mp cuadrada); el resto usa el banner 1200x630 por defecto. --}}
    <meta property="og:image:width"  content="@yield('og_image_width', '1200')">
    <meta property="og:image:height" content="@yield('og_image_height', '630')">
    <meta property="og:url"         content="@yield('canonical', url()->current())">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', $mpOgTitle)">
    <meta name="twitter:description" content="@yield('og_description', $mpOgDesc)">
    <meta name="twitter:image"       content="@yield('og_image', $mpOgImage)">

    {{-- JSON-LD global de marca (Organization + WebSite). Solo en páginas
         reales, no en el iframe embed. Identifica "ebaemy" como entidad
         propia ante Google/Bing y habilita el cuadro de búsqueda de sitelinks. --}}
    @unless($isEmbed)
    <script type="application/ld+json">{!! json_encode($mpJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endunless

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @php
        // Cache-busting por filemtime: el navegador recarga el CSS solo cuando
        // el archivo cambia (evita "deployé pero no veo el cambio" por caché).
        $mpCssVer = @filemtime(public_path('css/marketplace.css')) ?: null;
    @endphp
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}{{ $mpCssVer ? '?v=' . $mpCssVer : '' }}">

    {{-- Estilos de cards/grid/paginador compartidos por las 4 vistas que
         renderizan listings (home, categoría oficial, categoría legacy,
         tienda). Antes vivían inline solo en index.blade.php y las otras
         mostraban cards sin estilo. --}}
    @include('marketplace.partials.listing-card-styles')

    @include('marketplace.partials.shell-styles')

    @stack('styles')

    @if($isEmbed)
        {{-- Modo iframe: ocultar header, footer, trust bar y elementos
             secundarios. Solo se ve <main class="mp-container"> con el
             contenido del producto. --}}
        <style>
            body.is-embed > header,
            body.is-embed > footer,
            body.is-embed .mp-trust-sticky,
            body.is-embed .mp-mobile-actionbar,
            body.is-embed .mp-back-to-top { display: none !important; }
            body.is-embed { padding-bottom: 0 !important; }
            body.is-embed main.mp-container { padding-top: 12px; }
        </style>
    @endif
</head>
<body class="{{ $isEmbed ? 'is-embed' : '' }}">

{{-- ═══════════════════════ TOP BAR (desktop) ═══════════════════════ --}}
<div class="mp-topbar">
    <div class="mp-topbar-inner">
        <div class="mp-topbar-left">
            <span>🚚 Envío a todo el Perú</span>
            <span>✓ Compra 100% segura</span>
            <span>⭐ {{ \App\Models\System\Client::query()->where('is_verified', true)->count() }}+ tiendas verificadas</span>
        </div>
        <div class="mp-topbar-right">
            {{-- 'Planes y precios' y '¿Quieres vender?' removidos del topbar
                 (ocupaban espacio y duplicaban accesos disponibles en el footer
                 y el boton lateral 'Vender en ebaemy'). 2026-05-14. --}}
            <a href="mailto:soporte@ebaemy.com">Ayuda</a>
        </div>
    </div>
</div>

{{-- ═══════════════════════ NAV PRINCIPAL ═══════════════════════ --}}
<header class="mp-nav">
    <div class="mp-nav-main">
        <a href="{{ route('marketplace.index') }}" class="mp-logo" aria-label="ebaemy marketplace">
            <span class="mp-logo-mark">e</span>
            <span>
                ebaemy
                <span class="mp-logo-badge">Marketplace</span>
            </span>
        </a>

        <form id="mpSearchForm" action="{{ route('marketplace.index') }}" method="GET" class="mp-search" role="search">
            @if(isset($marketplaceNavCategories) && $marketplaceNavCategories->count() > 0)
            <button type="button"
                    id="mpMegaToggle"
                    class="mp-search-category mp-mega-toggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="mpMegaPanel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <span class="mp-mega-toggle__label">{{ !empty($navScopedToSubdomain ?? null) ? 'Categorías de la tienda' : 'Categorías' }}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mp-mega-toggle__chev"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            @endif
            <div class="mp-search-input-wrap" style="position:relative;flex:1;display:flex">
                <input type="search"
                       name="q"
                       id="mpSearchInput"
                       value="{{ $q ?? '' }}"
                       class="mp-search-input"
                       placeholder="Busca productos, tiendas o categorías…"
                       autocomplete="off"
                       aria-label="Buscar"
                       aria-autocomplete="list"
                       aria-controls="mpSearchSuggest">
                <div id="mpSearchSuggest" class="mp-search-suggest" role="listbox" aria-hidden="true"></div>
            </div>
            <button type="submit" class="mp-search-btn" aria-label="Buscar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </form>

        {{-- ═══════════════════════ DRAWER DE CATEGORÍAS (2-pane) ═══════════════════════
             Click en "Categorías" abre un drawer desde la izquierda con la
             lista de roots. Click en un root revela sus children en el panel
             derecho (desktop) o desliza hacia ellos (mobile). Estilo Amazon
             Mobile — limpio, tap-friendly, no se desborda nunca. --}}
        @if(isset($marketplaceNavCategories) && $marketplaceNavCategories->count() > 0)
            @php
                $isScoped = !empty($navScopedToSubdomain ?? null);
                $scopedBase = $isScoped ? route('marketplace.tenant', ['subdomain' => $navScopedToSubdomain]) : null;
            @endphp
            <div id="mpMegaPanel" class="mp-cat-drawer" role="dialog" aria-label="Categorías" aria-hidden="true" hidden>
                <div class="mp-cat-drawer__panel">
                    {{-- Header del drawer --}}
                    <div class="mp-cat-drawer__head">
                        {{-- Mobile: boton volver cuando estamos en vista de children --}}
                        <button type="button" class="mp-cat-drawer__back" id="mpCatBack" aria-label="Volver" style="display:none">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <h3 class="mp-cat-drawer__title" id="mpCatTitle">Categorías</h3>
                        <button type="button" class="mp-cat-drawer__close" aria-label="Cerrar" data-mega-close>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    {{-- 2 paneles: roots (izquierda) y children (derecha).
                         En mobile se ve uno a la vez, en desktop ambos. --}}
                    <div class="mp-cat-drawer__panes" id="mpCatPanes">
                        <ul class="mp-cat-drawer__roots" id="mpCatRoots">
                            @foreach($marketplaceNavCategories as $root)
                                @php
                                    $rootHref = $isScoped
                                        ? $scopedBase . '?category=' . urlencode($root->full_slug)
                                        : route('marketplace.category_official', ['fullSlug' => $root->full_slug]);
                                @endphp
                                <li>
                                    <button type="button"
                                            class="mp-cat-drawer__root {{ $loop->first ? 'is-active' : '' }}"
                                            data-cat-id="{{ $root->id }}"
                                            data-cat-href="{{ $rootHref }}"
                                            data-cat-name="{{ $root->name }}">
                                        <span class="mp-cat-drawer__root-icon">{{ $root->icon ?: '📦' }}</span>
                                        <span class="mp-cat-drawer__root-name">{{ $root->name }}</span>
                                        <svg class="mp-cat-drawer__root-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mp-cat-drawer__children" id="mpCatChildren">
                            @foreach($marketplaceNavCategories as $root)
                                @php
                                    $rootHref = $isScoped
                                        ? $scopedBase . '?category=' . urlencode($root->full_slug)
                                        : route('marketplace.category_official', ['fullSlug' => $root->full_slug]);
                                @endphp
                                <div class="mp-cat-drawer__child-pane {{ $loop->first ? 'is-active' : '' }}"
                                     data-cat-id="{{ $root->id }}">
                                    <a href="{{ $rootHref }}" class="mp-cat-drawer__child-all">
                                        <span style="font-size:16px">{{ $root->icon ?: '📦' }}</span>
                                        Ver todo en <strong>{{ $root->name }}</strong>
                                    </a>
                                    @if($root->children && $root->children->count())
                                        <ul class="mp-cat-drawer__child-list">
                                            @foreach($root->children as $child)
                                                @php
                                                    $childHref = $isScoped
                                                        ? $scopedBase . '?category=' . urlencode($child->full_slug)
                                                        : route('marketplace.category_official', ['fullSlug' => $child->full_slug]);
                                                @endphp
                                                <li>
                                                    <a href="{{ $childHref }}" class="mp-cat-drawer__child">
                                                        @if($child->icon)<span class="mp-cat-drawer__child-icon">{{ $child->icon }}</span>@endif
                                                        <span>{{ $child->name }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mp-cat-drawer__backdrop" data-mega-close></div>
            </div>
        @endif

        <script>
            (function () {
                var btn      = document.getElementById('mpMegaToggle');
                var drawer   = document.getElementById('mpMegaPanel');
                if (!btn || !drawer) return;

                // Teleport al <body>: el drawer queda renderizado dentro del
                // <header> sticky, lo que puede romper position:fixed si algun
                // ancestor (sticky, transform, filter, contain) crea un
                // containing block. Moverlo al body raiz garantiza que
                // position:fixed siempre se calcule contra el viewport.
                if (drawer.parentNode !== document.body) {
                    document.body.appendChild(drawer);
                }

                var panes      = document.getElementById('mpCatPanes');
                var backBtn    = document.getElementById('mpCatBack');
                var titleEl    = document.getElementById('mpCatTitle');
                var rootBtns   = drawer.querySelectorAll('.mp-cat-drawer__root');
                var childPanes = drawer.querySelectorAll('.mp-cat-drawer__child-pane');

                function isMobile() { return window.matchMedia('(max-width: 700px)').matches; }

                function open() {
                    // Quitar 'hidden' nativo PRIMERO (presente al cargar para
                    // evitar FOUC con la lista de categorias visible un
                    // instante antes del CSS). El doble RAF asegura que el
                    // navegador procesa el cambio de display antes del
                    // transition del slide-in.
                    drawer.hidden = false;
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            drawer.classList.add('is-open');
                            drawer.setAttribute('aria-hidden', 'false');
                            btn.setAttribute('aria-expanded', 'true');
                            document.body.style.overflow = 'hidden';
                            if (isMobile()) {
                                panes.classList.remove('is-children-view');
                                backBtn.style.display = 'none';
                                titleEl.textContent = 'Categorías';
                            }
                        });
                    });
                }
                function close() {
                    drawer.classList.remove('is-open');
                    drawer.setAttribute('aria-hidden', 'true');
                    btn.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                    // Re-aplicar hidden DESPUES de la animacion de salida
                    // (280ms del slide-out) para evitar que el contenido
                    // reaparezca visualmente al cerrar.
                    setTimeout(function () {
                        if (!drawer.classList.contains('is-open')) {
                            drawer.hidden = true;
                        }
                    }, 320);
                }
                function selectRoot(id, name) {
                    rootBtns.forEach(function (b) {
                        b.classList.toggle('is-active', String(b.dataset.catId) === String(id));
                    });
                    childPanes.forEach(function (p) {
                        p.classList.toggle('is-active', String(p.dataset.catId) === String(id));
                    });
                    if (isMobile()) {
                        panes.classList.add('is-children-view');
                        backBtn.style.display = 'inline-flex';
                        titleEl.textContent = name;
                    }
                }

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    drawer.classList.contains('is-open') ? close() : open();
                });

                rootBtns.forEach(function (b) {
                    b.addEventListener('click', function (e) {
                        e.preventDefault();
                        var id   = b.dataset.catId;
                        var name = b.dataset.catName;
                        // Mobile: solo cambiar vista. Desktop: el click revela children
                        // pero si volvemos a clickear el mismo root activo, navegamos a
                        // la categoria.
                        if (!isMobile() && b.classList.contains('is-active')) {
                            window.location.href = b.dataset.catHref;
                            return;
                        }
                        selectRoot(id, name);
                    });
                });

                backBtn.addEventListener('click', function () {
                    panes.classList.remove('is-children-view');
                    backBtn.style.display = 'none';
                    titleEl.textContent = 'Categorías';
                });

                // Backdrop + boton X cierran
                drawer.addEventListener('click', function (e) {
                    if (e.target.closest('[data-mega-close]')) close();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
                });
            })();
        </script>

        <div class="mp-nav-actions">
            <a href="{{ route('marketplace.favorites') }}" class="mp-nav-link" id="mpFavNavLink"
               title="Mis favoritos" style="position:relative">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="mp-nav-link-text">Favoritos</span>
                <span id="mpFavBadge"
                      style="display:none;position:absolute;top:-2px;right:-6px;background:#dc2626;color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:18px;height:18px;padding:0 5px;line-height:18px;text-align:center"></span>
            </a>
            {{-- Cupones: solo se muestra para usuarios logueados (los annimos
                 no tienen cupones asignados). Badge con conteo de disponibles
                 (no usados, no vencidos). --}}
            @php $mktUserForCoupon = auth('marketplace')->user(); @endphp
            @if($mktUserForCoupon)
                <a href="{{ route('marketplace.account.coupons') }}" class="mp-nav-link" id="mpCouponNavLink"
                   title="Mis cupones" style="position:relative">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"/>
                        <line x1="9" y1="9" x2="9" y2="15"/>
                    </svg>
                    <span class="mp-nav-link-text">Cupones</span>
                    <span id="mpCouponBadge"
                          style="display:none;position:absolute;top:-2px;right:-6px;background:#f59e0b;color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:18px;height:18px;padding:0 5px;line-height:18px;text-align:center"></span>
                </a>
            @endif
            <a href="{{ route('marketplace.cart') }}" class="mp-nav-link" id="mpCartNavLink"
               title="Mi carrito" style="position:relative">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="mp-nav-link-text">Carrito</span>
                <span id="mpCartBadge"
                      style="display:none;position:absolute;top:-2px;right:-6px;background:#dc2626;color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:18px;height:18px;padding:0 5px;line-height:18px;text-align:center"></span>
            </a>
            {{-- Auth comprador.
                 - Anonimo: link directo a /login.
                 - Logueado: avatar boton que abre dropdown con accesos
                   rapidos (mi cuenta, favoritos, pedidos, cupones, ajustes,
                   cerrar sesion). En mobile el dropdown queda como modal
                   overlay (CSS @media). --}}
            @php $mktUser = auth('marketplace')->user(); @endphp
            @if(!$mktUser)
                <a href="{{ route('marketplace.login') }}" class="mp-nav-link" id="mpAccountNavLink" title="Entrar / Crear cuenta">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                    <span class="mp-nav-link-text">Entrar</span>
                </a>
            @else
                @php
                    $firstName = \Illuminate\Support\Str::limit(explode(' ', $mktUser->name)[0], 12);
                    $initial = mb_strtoupper(mb_substr($mktUser->name, 0, 1));
                @endphp
                <div class="mp-acc-menu" id="mpAccountMenu">
                    <button type="button" class="mp-nav-link mp-acc-menu__btn" id="mpAccountMenuBtn"
                            aria-haspopup="true" aria-expanded="false"
                            title="Mi cuenta — {{ $mktUser->name }}">
                        <span class="mp-acc-avatar" aria-hidden="true">{{ $initial }}</span>
                        <span class="mp-nav-link-text">{{ $firstName }}</span>
                        <svg class="mp-acc-menu__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="mp-acc-menu__panel" role="menu" aria-hidden="true">
                        <div class="mp-acc-menu__head">
                            <div class="mp-acc-avatar mp-acc-avatar--lg" aria-hidden="true">{{ $initial }}</div>
                            <div class="mp-acc-menu__head-info">
                                <strong>{{ \Illuminate\Support\Str::limit($mktUser->name, 22) }}</strong>
                                <span>{{ \Illuminate\Support\Str::limit($mktUser->email, 28) }}</span>
                            </div>
                        </div>
                        <a href="{{ route('marketplace.account') }}" role="menuitem">Mi cuenta</a>
                        <a href="{{ route('marketplace.favorites') }}" role="menuitem">Favoritos</a>
                        <a href="{{ route('marketplace.account.orders') }}" role="menuitem">Mis pedidos</a>
                        <a href="{{ route('marketplace.account.coupons') }}" role="menuitem">Mis cupones</a>
                        <a href="{{ route('marketplace.account.settings') }}" role="menuitem">Ajustes</a>
                        <form method="POST" action="{{ route('marketplace.auth.logout') }}" style="margin:0">
                            @csrf
                            <button type="submit" class="mp-acc-menu__logout" role="menuitem">Cerrar sesion</button>
                        </form>
                    </div>
                </div>
            @endif
            {{-- 'Vender en ebaemy' removido del navbar a pedido del usuario.
                 Acceso sigue disponible desde el footer (columna Vender). --}}
        </div>
    </div>

    {{-- Chip bar de categorías root OCULTA a pedido del usuario (2026-06-16).
         El CSS (.mp-cats-bar) ya quedó como una sola línea deslizable; para
         reactivarla, descomentar el bloque de abajo (quitar el envoltorio de
         comentario Blade). La navegación de categorías sigue disponible via el
         botón "Categorías" del search bar (megamenú). --}}
    {{-- @isset($marketplaceNavCategories)
        @if($marketplaceNavCategories->count() > 0)
            @php
                $catsBarScoped = !empty($navScopedToSubdomain ?? null);
                $catsBarHome   = $catsBarScoped
                    ? route('marketplace.tenant', ['subdomain' => $navScopedToSubdomain])
                    : route('marketplace.index');
            @endphp
            <nav class="mp-cats-bar" aria-label="Categorías">
                <div class="mp-cats-inner">
                    <a href="{{ $catsBarHome }}"
                       class="mp-cat-chip {{ empty($activeCategoryFullSlug ?? null) ? 'is-active' : '' }}">
                        📦 Todas
                    </a>
                    @foreach($marketplaceNavCategories as $root)
                        @php
                            $chipHref = $catsBarScoped
                                ? $catsBarHome . '?category=' . urlencode($root->full_slug)
                                : route('marketplace.category_official', ['fullSlug' => $root->full_slug]);
                        @endphp
                        <a href="{{ $chipHref }}"
                           class="mp-cat-chip {{ ($activeCategoryFullSlug ?? null) === $root->full_slug ? 'is-active' : '' }}">
                            @if($root->icon){{ $root->icon }} @endif{{ $root->name }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    @endisset --}}
</header>

{{-- ═══════════════════════ CONTENIDO ═══════════════════════ --}}
<main class="mp-container">
    @yield('content')
</main>

{{-- ═══════════════════════ TRUST BAR (movida al footer) ═══════════════════════ --}}
<section class="mp-trust-sticky mp-trust-sticky--footer">
    <div class="mp-trust-sticky-inner">
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            <span><strong>Compra segura</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="13" height="10" rx="2"/><path d="M15 9h5l2 4v4h-7V9z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
            <span>Envío <strong>a todo Perú</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
            <span>Tiendas <strong>verificadas</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <span>Pago <strong>contra entrega</strong></span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
            <span><strong>Factura</strong> electrónica</span>
        </span>
        <span class="mp-trust-sticky-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            <span>Soporte <strong>directo</strong></span>
        </span>
    </div>
</section>

{{-- ═══════════════════════ FOOTER ═══════════════════════ --}}
<footer class="mp-footer">

    {{-- ── Newsletter signup (captura leads opt-in) ── --}}
    <div class="mp-footer-newsletter">
        <div class="mp-footer-newsletter__inner">
            <div class="mp-footer-newsletter__head">
                <span class="mp-footer-newsletter__icon">📩</span>
                <div>
                    <strong>Recibe ofertas y nuevas tiendas en tu email</strong>
                    <small>Te avisamos máximo una vez por semana · cancela cuando quieras</small>
                </div>
            </div>
            <form id="mpNewsletterForm" class="mp-footer-newsletter__form">
                <input type="email" name="email" required maxlength="180"
                       placeholder="tu@email.com" autocomplete="email">
                <button type="submit">Suscribirme</button>
            </form>
            <div id="mpNewsletterMsg" class="mp-footer-newsletter__msg"></div>
        </div>
    </div>

    {{-- ── Métodos de pago + sellos de confianza ── --}}
    <div class="mp-footer-trust">
        <div class="mp-footer-trust__inner">
            <div class="mp-footer-trust__block">
                <div class="mp-footer-trust__label">💳 Métodos de pago</div>
                <div class="mp-footer-trust__items">
                    <span class="mp-footer-trust__pay" title="MercadoPago">
                        <svg width="40" height="20" viewBox="0 0 100 50" fill="none">
                            <rect width="100" height="50" rx="6" fill="#009ee3"/>
                            <text x="50" y="32" text-anchor="middle" fill="#fff" font-family="Arial" font-weight="bold" font-size="14">MP</text>
                        </svg>
                    </span>
                    <span class="mp-footer-trust__pay" title="Yape">
                        <svg width="36" height="20" viewBox="0 0 100 50" fill="none">
                            <rect width="100" height="50" rx="6" fill="#7c3aed"/>
                            <text x="50" y="32" text-anchor="middle" fill="#fff" font-family="Arial" font-weight="bold" font-size="13">Yape</text>
                        </svg>
                    </span>
                    <span class="mp-footer-trust__pay" title="Plin">
                        <svg width="36" height="20" viewBox="0 0 100 50" fill="none">
                            <rect width="100" height="50" rx="6" fill="#0ea5e9"/>
                            <text x="50" y="32" text-anchor="middle" fill="#fff" font-family="Arial" font-weight="bold" font-size="13">Plin</text>
                        </svg>
                    </span>
                    <span class="mp-footer-trust__pay" title="Visa">
                        <svg width="36" height="20" viewBox="0 0 100 50" fill="none">
                            <rect width="100" height="50" rx="6" fill="#1a1f71"/>
                            <text x="50" y="32" text-anchor="middle" fill="#fff" font-family="Arial" font-weight="bold" font-size="14">VISA</text>
                        </svg>
                    </span>
                    <span class="mp-footer-trust__pay" title="Mastercard">
                        <svg width="36" height="20" viewBox="0 0 100 50" fill="none">
                            <rect width="100" height="50" rx="6" fill="#ffffff" stroke="#ddd"/>
                            <circle cx="42" cy="25" r="13" fill="#eb001b"/>
                            <circle cx="58" cy="25" r="13" fill="#f79e1b" fill-opacity=".9"/>
                        </svg>
                    </span>
                    <span class="mp-footer-trust__pay" title="Transferencia bancaria">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round"><rect x="2" y="6" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </span>
                </div>
            </div>

            <div class="mp-footer-trust__block">
                <div class="mp-footer-trust__label">🛡️ Garantía y seguridad</div>
                <div class="mp-footer-trust__items">
                    <span class="mp-footer-trust__badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"><path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6l9-4z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span><strong>RUC validado</strong> · SUNAT</span>
                    </span>
                    <span class="mp-footer-trust__badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span><strong>Compra protegida</strong> · SSL</span>
                    </span>
                    <span class="mp-footer-trust__badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        <span><strong>Factura</strong> electrónica</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="mp-footer-grid">
        <div class="mp-footer-brand">
            <h3>
                <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#1fb1a6,#0a6f68);font-weight:800;">e</span>
                ebaemy
            </h3>
            <p>El marketplace peruano que conecta compradores con tiendas verificadas. Todas las empresas tienen RUC validado y facturación electrónica.</p>
            <div style="font-size:12.5px;color:#9ca3af;margin-top:10px">
                ✉️ <a href="mailto:soporte@ebaemy.com" style="color:#bbb;text-decoration:underline">soporte@ebaemy.com</a>
            </div>
            @php
                // Solo renderizar el icono si la URL está configurada en
                // /admin/marketplace/seo. Evita links a "#" en producción.
                $socialFb = $mpCfg->marketplace_facebook_url  ?? null;
                $socialIg = $mpCfg->marketplace_instagram_url ?? null;
                $socialWa = $mpCfg->marketplace_whatsapp_url  ?? null;
                $socialTk = $mpCfg->marketplace_tiktok_url    ?? null;
                $hasAnySocial = $socialFb || $socialIg || $socialWa || $socialTk;
            @endphp
            @if($hasAnySocial)
            <div class="mp-footer-socials">
                @if($socialFb)
                <a href="{{ $socialFb }}" class="mp-footer-social" aria-label="Facebook" target="_blank" rel="noopener nofollow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                @endif
                @if($socialIg)
                <a href="{{ $socialIg }}" class="mp-footer-social" aria-label="Instagram" target="_blank" rel="noopener nofollow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                </a>
                @endif
                @if($socialWa)
                <a href="{{ $socialWa }}" class="mp-footer-social" aria-label="WhatsApp" target="_blank" rel="noopener nofollow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.173.198-.297.298-.495.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
                </a>
                @endif
                @if($socialTk)
                <a href="{{ $socialTk }}" class="mp-footer-social" aria-label="TikTok" target="_blank" rel="noopener nofollow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.93a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.84-.36z"/></svg>
                </a>
                @endif
            </div>
            @endif
        </div>

        <div class="mp-footer-col">
            <h4>Comprar</h4>
            <ul>
                <li><a href="{{ route('marketplace.index') }}">Explorar marketplace</a></li>
                <li><a href="{{ route('marketplace.index', ['sort' => 'newest']) }}">Novedades</a></li>
                <li><a href="{{ route('marketplace.index', ['on_offer' => 1]) }}">Ofertas</a></li>
            </ul>
        </div>

        <div class="mp-footer-col">
            <h4>Vender</h4>
            <ul>
                <li><a href="{{ route('seller.landing') }}">Vender en ebaemy</a></li>
                <li><a href="{{ route('seller.register') }}">Crear solicitud</a></li>
                <li><a href="{{ url('/guest-register') }}">Registro rápido</a></li>
            </ul>
        </div>

        <div class="mp-footer-col">
            <h4>Soporte</h4>
            <ul>
                <li><a href="mailto:soporte@ebaemy.com">Contacto</a></li>
                <li><a href="{{ route('marketplace.faq') }}">Preguntas frecuentes</a></li>
                <li><a href="{{ route('marketplace.terms') }}">Términos y condiciones</a></li>
                <li><a href="{{ route('marketplace.privacy') }}">Política de privacidad</a></li>
            </ul>
        </div>
    </div>

    <div class="mp-footer-bottom">
        <div>© {{ date('Y') }} ebaemy — Todas las tiendas del Perú, un solo lugar.</div>
        <div>🇵🇪 Hecho en Perú · <a href="mailto:soporte@ebaemy.com" style="color:#9ca3af">Atención: soporte@ebaemy.com</a></div>
    </div>
</footer>

<script>
(function(){
    const form = document.getElementById('mpNewsletterForm');
    const msg  = document.getElementById('mpNewsletterMsg');
    if (!form || !msg) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = form.querySelector('input[name=email]');
        const btn   = form.querySelector('button');
        const email = (input.value || '').trim();
        if (!email) {
            msg.textContent = 'Ingresa un email válido.';
            msg.className = 'mp-footer-newsletter__msg is-error';
            return;
        }
        btn.disabled = true;
        const originalBtn = btn.textContent;
        btn.textContent = '…';
        msg.textContent = '';
        try {
            const res = await fetch(@json(route('marketplace.newsletter')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ email }),
            });
            const data = await res.json();
            msg.textContent = data.message || (res.ok ? '✓ Listo' : '✕ Error');
            msg.className = 'mp-footer-newsletter__msg ' + (res.ok && data.success ? 'is-ok' : 'is-error');
            if (res.ok && data.success) {
                input.value = '';
                btn.textContent = '✓';
            } else {
                btn.disabled = false;
                btn.textContent = originalBtn;
            }
        } catch (err) {
            msg.textContent = 'No se pudo conectar. Intenta de nuevo.';
            msg.className = 'mp-footer-newsletter__msg is-error';
            btn.disabled = false;
            btn.textContent = originalBtn;
        }
    });
})();
</script>

<script>
(function(){
    const badge = document.getElementById('mpCartBadge');
    if (!badge) return;
    function paint(summary) {
        const count = (summary && summary.count) || 0;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }

        // Barra inferior móvil "Ver carrito" (solo existe en el home/listado):
        // se muestra SOLO cuando hay productos, con cantidad + total. Antes
        // estaba siempre visible y se veía poco profesional.
        const bar = document.querySelector('.mp-mobile-actionbar');
        if (bar) {
            if (count > 0) {
                bar.classList.add('is-visible');
                bar.setAttribute('aria-hidden', 'false');
                document.body.classList.add('mp-cartbar-on');
                const c = document.getElementById('mpMabCount');
                if (c) c.textContent = '(' + count + ')';
                const t = document.getElementById('mpMabTotal');
                if (t && summary && summary.subtotal != null) t.textContent = 'S/ ' + Number(summary.subtotal).toFixed(2);
            } else {
                bar.classList.remove('is-visible');
                bar.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('mp-cartbar-on');
            }
        }
    }
    window.mpCartBadgeUpdate = paint;
    fetch(@json(route('marketplace.cart.json')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json()).then(paint).catch(function(){ /* silent */ });
})();

// Badge del navbar para cupones (solo usuarios logueados). Carga el
// conteo al inicio + expone mpCouponBadgeUpdate(n) para refrescar
// despus de aplicar un cupn en checkout o asignar uno nuevo. Adems
// trackea el conteo en window.mpCouponCount + window.mpCouponTenantIds
// para que otros touchpoints (mini-cart hint, badge en cards, banner
// home) los lean sin re-fetchear.
window.mpCouponCount = 0;
window.mpCouponTenantIds = []; // hostname_ids donde el user tiene cupn
(function(){
    const cbadge = document.getElementById('mpCouponBadge');
    const hint   = document.getElementById('mpMiniCartCouponHint');
    const hintN  = document.getElementById('mpMiniCartCouponHintCount');
    function paint(n) {
        const count = parseInt(n, 10) || 0;
        window.mpCouponCount = count;
        // Badge navbar
        if (cbadge) {
            if (count > 0) {
                cbadge.textContent = count > 99 ? '99+' : String(count);
                cbadge.style.display = 'inline-block';
            } else {
                cbadge.style.display = 'none';
            }
        }
        // Hint en mini-cart drawer
        if (hint && hintN) {
            if (count > 0) {
                hintN.textContent = count;
                hint.style.display = 'flex';
            } else {
                hint.style.display = 'none';
            }
        }
    }
    window.mpCouponBadgeUpdate = paint;
    // Solo fetcheamos si hay endpoint disponible (user logueado tiene navbar
    // con icono; anonimous no, evitamos pegada innecesaria).
    if (cbadge) {
        fetch(@json(route('marketplace.account.coupons.count')), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(d => {
            window.mpCouponTenantIds = Array.isArray(d.tenant_ids) ? d.tenant_ids : [];
            paint(d.count);
            // Disparar evento para que las cards ya renderizadas pinten su badge
            window.dispatchEvent(new CustomEvent('mp:coupons-loaded', {
                detail: { count: d.count, tenant_ids: window.mpCouponTenantIds }
            }));
        })
        .catch(function(){ /* silent */ });
    }
})();

// Badge del navbar para favoritos. El conteo inicial lo carga el script
// de listing-card; aquí solo exponemos la función paint.
(function(){
    const fbadge = document.getElementById('mpFavBadge');
    if (!fbadge) return;
    window.mpFavBadgeUpdate = function (count) {
        const n = parseInt(count, 10) || 0;
        if (n > 0) {
            fbadge.textContent = n > 99 ? '99+' : String(n);
            fbadge.style.display = 'inline-block';
        } else {
            fbadge.style.display = 'none';
        }
    };
})();
</script>

{{-- JS común a todas las vistas que renderizan cards de listings:
     hover en color dots + click en nombre de tienda. --}}
@include('marketplace.partials.listing-card-script')

{{-- Bottom sheet del detalle de producto (solo mobile). Renderizado
     una sola vez en layout  funciona en TODAS las vistas que muestran
     cards (home, categora, categora oficial, tienda, favoritos, etc.).
     NO duplicar este markup en vistas individuales. --}}
@include('marketplace.partials.product-sheet')

{{-- ════════ Toast post-login: cupones disponibles (item 5 roadmap) ════════
     Cuando el user se loggea y tiene cupones disponibles, MarketplaceAuthController
     flashea mkt_coupons_toast con el count. Lo mostramos como toast flotante
     auto-hide a los 8s, clickeable a /account/coupons. --}}
@if(session('mkt_coupons_toast'))
    <div id="mpCouponsToast" class="mp-toast mp-toast--coupons" role="status" aria-live="polite">
        <span class="mp-toast__icon">🎟️</span>
        <span class="mp-toast__body">
            Tienes <strong>{{ session('mkt_coupons_toast') }}</strong>
            {{ session('mkt_coupons_toast') == 1 ? 'cupn disponible' : 'cupones disponibles' }}
            <br><small>Aplicalos en tu prximo pedido</small>
        </span>
        <a href="{{ route('marketplace.account.coupons') }}" class="mp-toast__cta">Ver</a>
        <button type="button" class="mp-toast__close" aria-label="Cerrar">×</button>
    </div>
    <script>
    (function () {
        var t = document.getElementById('mpCouponsToast');
        if (!t) return;
        var hide = function () {
            t.classList.add('is-hiding');
            setTimeout(function () { t.remove(); }, 400);
        };
        t.querySelector('.mp-toast__close').addEventListener('click', hide);
        setTimeout(hide, 8000); // auto-hide a los 8s
    })();
    </script>
@endif

{{-- ═══════════════════════ SEARCH AUTOCOMPLETE ═══════════════════════
     Debounce 250ms; pega al endpoint searchSuggest (cache 60s server-side).
     ↑/↓ navegan, Enter abre la suggestion activa o submitea el form. --}}
<script>
(function(){
    const input   = document.getElementById('mpSearchInput');
    const dropdown= document.getElementById('mpSearchSuggest');
    const form    = document.getElementById('mpSearchForm');
    if (!input || !dropdown || !form) return;

    const SUGGEST_URL = @json(route('marketplace.search.suggest'));
    const SEARCH_BASE = @json(route('marketplace.index'));
    let timer = null;
    let lastQ = '';
    let activeIdx = -1;
    let items = [];

    const esc = s => String(s).replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));

    function close() {
        dropdown.classList.remove('is-open');
        dropdown.setAttribute('aria-hidden','true');
        activeIdx = -1;
    }
    function open() {
        dropdown.classList.add('is-open');
        dropdown.setAttribute('aria-hidden','false');
    }

    function productItemHtml(s, i) {
        const url = SEARCH_BASE.replace(/\/$/,'') + '/item/' + s.slug;
        const badges = [];
        if (s.is_pack) badges.push('<span class="mp-search-suggest__badge mp-search-suggest__badge--pack">📦 Pack</span>');
        if (s.is_on_offer && s.discount_pct) badges.push(`<span class="mp-search-suggest__badge mp-search-suggest__badge--offer">-${s.discount_pct}%</span>`);
        if (s.out_of_stock) badges.push('<span class="mp-search-suggest__badge mp-search-suggest__badge--out">Agotado</span>');
        return `
            <a class="mp-search-suggest__item" data-idx="${i}" href="${url}">
                ${s.image_url ? `<img class="mp-search-suggest__thumb" src="${esc(s.image_url)}" alt="" loading="lazy">` : '<div class="mp-search-suggest__thumb"></div>'}
                <div class="mp-search-suggest__info">
                    <span class="mp-search-suggest__title">${esc(s.title)}</span>
                    <span class="mp-search-suggest__meta">
                        ${badges.join('')}
                        ${s.tenant_name ? '<span>· ' + esc(s.tenant_name) + '</span>' : ''}
                    </span>
                </div>
                <span class="mp-search-suggest__price">S/ ${(s.price || 0).toFixed(2)}</span>
            </a>`;
    }

    // Bloques de populares + categorías (foco vacío y "sin resultados").
    function popularBlocks(data) {
        let html = '';
        const popular = data.popular || [];
        const cats = data.categories || [];
        if (popular.length) {
            html += '<div class="mp-search-suggest__section">';
            html += '<div class="mp-search-suggest__header">Productos populares</div>';
            popular.forEach((s, i) => { html += productItemHtml(s, i); });
            html += '</div>';
        }
        if (cats.length) {
            html += '<div class="mp-search-suggest__section">';
            html += '<div class="mp-search-suggest__header">Categorías</div>';
            html += '<div class="mp-search-suggest__cats">';
            cats.forEach(c => {
                const url = `${SEARCH_BASE}?q=${encodeURIComponent(c)}`;
                html += `<a class="mp-search-suggest__cat" href="${url}">${esc(c)}</a>`;
            });
            html += '</div></div>';
        }
        return html;
    }

    function render(data, q) {
        const sug = data.suggestions || [];
        const shops = data.shops || [];
        let html = '';

        if (sug.length || shops.length) {
            if (sug.length) {
                html += '<div class="mp-search-suggest__section">';
                html += '<div class="mp-search-suggest__header">Productos</div>';
                sug.forEach((s, i) => { html += productItemHtml(s, i); });
                html += '</div>';
            }
            if (shops.length) {
                html += '<div class="mp-search-suggest__section">';
                html += '<div class="mp-search-suggest__header">Tiendas</div>';
                shops.forEach(sh => {
                    const url = SEARCH_BASE + '/tienda/' + encodeURIComponent(sh.subdomain || '');
                    html += `
                        <a class="mp-search-suggest__item" href="${url}">
                            <div class="mp-search-suggest__thumb" style="display:flex;align-items:center;justify-content:center;font-size:18px">🏪</div>
                            <div class="mp-search-suggest__info">
                                <span class="mp-search-suggest__title">${esc(sh.name)}</span>
                                <span class="mp-search-suggest__meta">${sh.products_count} productos</span>
                            </div>
                        </a>`;
                });
                html += '</div>';
            }
            html += `<a class="mp-search-suggest__seemore" href="${SEARCH_BASE}?q=${encodeURIComponent(q)}">Ver todos los resultados →</a>`;
        } else {
            // Sin resultados (q presente) o foco vacío (q vacío) → populares + categorías.
            if (q) {
                html += `<div class="mp-search-suggest__empty">Sin resultados para "${esc(q)}". Quizás te interese:</div>`;
            }
            const blocks = popularBlocks(data);
            html += blocks;
            if (!blocks && q) {
                html = `<div class="mp-search-suggest__empty">Sin resultados para "${esc(q)}"</div>`;
            }
            if (!html) return; // nada que mostrar
        }

        dropdown.innerHTML = html;
        items = Array.from(dropdown.querySelectorAll('.mp-search-suggest__item'));
        activeIdx = -1;
        open();
    }

    async function fetchSuggest(q) {
        try {
            const res = await fetch(`${SUGGEST_URL}?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (q !== lastQ) return; // descartar respuestas viejas
            render(data, q);
        } catch (e) { /* silencio: red caída no debe romper UI */ }
    }

    input.addEventListener('input', () => {
        const q = input.value.trim();
        lastQ = q;
        clearTimeout(timer);
        if (q.length < 2) { close(); return; }
        timer = setTimeout(() => fetchSuggest(q), 250);
    });

    input.addEventListener('keydown', (e) => {
        if (!dropdown.classList.contains('is-open')) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length === 0) return;
            activeIdx = (activeIdx + 1) % items.length;
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
            items[activeIdx]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length === 0) return;
            activeIdx = activeIdx <= 0 ? items.length - 1 : activeIdx - 1;
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
            items[activeIdx]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (activeIdx >= 0 && items[activeIdx]) {
                e.preventDefault();
                window.location.href = items[activeIdx].href;
            }
        } else if (e.key === 'Escape') {
            close();
        }
    });

    // Cerrar al click fuera
    document.addEventListener('click', (e) => {
        if (!form.contains(e.target)) close();
    });
    input.addEventListener('focus', () => {
        const q = input.value.trim();
        if (q.length >= 2 && dropdown.innerHTML) { open(); return; }
        // Foco con input vacío → sugerencias populares + categorías.
        if (q.length < 2) { lastQ = ''; fetchSuggest(''); }
    });
})();
</script>

{{-- ════════════════════════ MINI-CART DRAWER ════════════════════════
     Panel deslizable que se abre al hacer click en el icono del carrito.
     Resume items por tienda + total + CTA al checkout. Mobile: slide-up
     desde abajo. Desktop: slide-in desde la derecha. Renderiza siempre
     en la layout para que cualquier ruta del marketplace lo tenga.  --}}
<div id="mpMiniCart" class="mp-mini-cart" aria-hidden="true" role="dialog" aria-label="Mini carrito">
    <div class="mp-mini-cart__panel">
        <div class="mp-mini-cart__head">
            <div class="mp-mini-cart__title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Mi carrito</span>
                <span id="mpMiniCartCount" class="mp-mini-cart__count"></span>
            </div>
            <button type="button" class="mp-mini-cart__close" id="mpMiniCartClose" aria-label="Cerrar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="6" y1="18" x2="18" y2="6"/></svg>
            </button>
        </div>

        {{-- Hint de cupones disponibles. Visible solo si el user logueado
             tiene cupones (mpCouponCount > 0). Lo poblamos desde JS al
             abrir el drawer para no acoplar al render del cart. --}}
        <a href="{{ route('marketplace.account.coupons') }}"
           id="mpMiniCartCouponHint"
           class="mp-mini-cart__coupon-hint"
           style="display:none">
            <span class="mp-mini-cart__coupon-hint-icon">🎟️</span>
            <span class="mp-mini-cart__coupon-hint-text">
                Tienes <strong id="mpMiniCartCouponHintCount">0</strong> cupones disponibles para aplicar en el checkout
            </span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>

        <div class="mp-mini-cart__body" id="mpMiniCartBody">
            <div class="mp-mini-cart__loading">Cargando…</div>
        </div>

        <div class="mp-mini-cart__foot" id="mpMiniCartFoot" style="display:none">
            <div class="mp-mini-cart__total">
                <span>Total</span>
                <strong id="mpMiniCartTotal">S/ 0.00</strong>
            </div>
            <div class="mp-mini-cart__actions">
                <a href="{{ route('marketplace.cart') }}" class="mp-mini-cart__btn mp-mini-cart__btn--ghost">Ver carrito</a>
                <a href="{{ route('marketplace.cart') }}" class="mp-mini-cart__btn mp-mini-cart__btn--primary">Ir al checkout →</a>
            </div>
        </div>
    </div>
    <div class="mp-mini-cart__backdrop" id="mpMiniCartBackdrop"></div>
</div>

<script>
(function () {
    const drawer   = document.getElementById('mpMiniCart');
    const navLink  = document.getElementById('mpCartNavLink');
    const closeBtn = document.getElementById('mpMiniCartClose');
    const backdrop = document.getElementById('mpMiniCartBackdrop');
    const body     = document.getElementById('mpMiniCartBody');
    const foot     = document.getElementById('mpMiniCartFoot');
    const totalEl  = document.getElementById('mpMiniCartTotal');
    const countEl  = document.getElementById('mpMiniCartCount');

    if (!drawer || !navLink) return;

    const miniUrl = @json(route('marketplace.cart.mini'));
    const cartUrl = @json(route('marketplace.cart'));
    const idxUrl  = @json(route('marketplace.index'));

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderEmpty() {
        body.innerHTML = `
            <div class="mp-mini-cart__empty">
                <div class="mp-mini-cart__empty-icon">🛒</div>
                <h4>Tu carrito está vacío</h4>
                <p>Agrega productos del marketplace para verlos aquí.</p>
                <a href="${idxUrl}" class="mp-mini-cart__btn mp-mini-cart__btn--primary" style="display:inline-block">Explorar productos</a>
            </div>`;
        foot.style.display = 'none';
        countEl.style.display = 'none';
    }

    function renderData(data) {
        const stores = data.stores || [];
        const summary = data.summary || { count: 0, subtotal: 0 };

        if (!stores.length || summary.count === 0) {
            renderEmpty();
            return;
        }

        countEl.textContent = summary.count;
        countEl.style.display = '';

        let html = '';
        stores.forEach(store => {
            html += `<div class="mp-mini-cart__store">
                <div class="mp-mini-cart__store-head">`;
            if (store.tenant_logo) {
                html += `<img class="mp-mini-cart__store-logo" src="${escapeHtml(store.tenant_logo)}" alt="">`;
            } else {
                html += `<span class="mp-mini-cart__store-logo" style="display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">🏪</span>`;
            }
            html += `<span class="mp-mini-cart__store-name">${escapeHtml(store.tenant_name || store.tenant_fqdn || 'Tienda')}</span>
                <span style="font-weight:700;color:#0c6b65">S/ ${Number(store.subtotal || 0).toFixed(2)}</span>
            </div>`;

            (store.items || []).forEach(line => {
                html += `<div class="mp-mini-cart__line" data-line-id="${line.listing_id ?? ''}">`;
                if (line.image) {
                    html += `<img class="mp-mini-cart__line-img" src="${escapeHtml(line.image)}" alt="" loading="lazy">`;
                } else {
                    html += `<div class="mp-mini-cart__line-img"></div>`;
                }
                html += `<div class="mp-mini-cart__line-info">
                    <div class="mp-mini-cart__line-title">${escapeHtml(line.title)}</div>
                    <div class="mp-mini-cart__line-meta">${line.quantity} × S/ ${Number(line.price).toFixed(2)}</div>
                </div>
                <div class="mp-mini-cart__line-actions">
                    <div class="mp-mini-cart__line-total">S/ ${Number(line.line_total).toFixed(2)}</div>
                    ${line.listing_id ? `
                        <button type="button" class="mp-mini-cart__line-remove js-mini-cart-remove"
                                data-listing-id="${line.listing_id}"
                                aria-label="Eliminar del carrito" title="Eliminar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                    ` : ''}
                </div>
                </div>`;
            });
            html += `</div>`;
        });

        body.innerHTML = html;
        totalEl.textContent = 'S/ ' + Number(summary.subtotal || 0).toFixed(2);
        foot.style.display = '';
    }

    function open() {
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        body.innerHTML = '<div class="mp-mini-cart__loading">Cargando…</div>';
        foot.style.display = 'none';

        fetch(miniUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(renderData)
            .catch(() => {
                body.innerHTML = '<div class="mp-mini-cart__loading" style="color:#dc2626">No se pudo cargar el carrito</div>';
            });
    }

    function close() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    navLink.addEventListener('click', function (e) {
        e.preventDefault();
        open();
    });
    closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });

    // Si otro componente añadió al carrito (quick-add de cards), abrir
    // el drawer para feedback inmediato. Lo controlamos via un custom event.
    window.addEventListener('mpCartChanged', function () {
        if (drawer.classList.contains('is-open')) open(); // refresh
    });

    // Eliminar item del mini-cart con remocion local animada — SIN
    // re-fetch ni spinner ("Cargando..."). El endpoint devuelve summary
    // actualizado; con eso ajustamos total general y badge.
    const csrf = @json(csrf_token());
    const updateBase = @json(url('/marketplace/cart')); // PATCH /marketplace/cart/{listing}
    body.addEventListener('click', function (e) {
        const btn = e.target.closest && e.target.closest('.js-mini-cart-remove');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const listingId = parseInt(btn.dataset.listingId, 10);
        if (!listingId || btn.disabled) return;
        const lineEl  = btn.closest('.mp-mini-cart__line');
        const storeEl = btn.closest('.mp-mini-cart__store');
        btn.disabled = true;
        if (lineEl) lineEl.classList.add('is-removing');

        fetch(`${updateBase}/${listingId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ quantity: 0 }),
        })
        .then(r => r.json())
        .then(j => {
            if (!j.success) {
                btn.disabled = false;
                if (lineEl) lineEl.classList.remove('is-removing');
                return;
            }
            // Espera el fadeout del CSS (180ms) y elimina la linea.
            setTimeout(() => {
                if (lineEl) lineEl.remove();
                // ¿Quedo el store sin lineas? Ocultarlo.
                if (storeEl && !storeEl.querySelector('.mp-mini-cart__line')) {
                    storeEl.remove();
                }
                // ¿Carrito vacio? Render empty.
                const sum = j.summary || { count: 0, subtotal: 0 };
                if ((sum.count || 0) === 0) {
                    renderEmpty();
                } else {
                    // Actualiza total y count sin re-renderear todo.
                    totalEl.textContent = 'S/ ' + Number(sum.subtotal || 0).toFixed(2);
                    countEl.textContent = sum.count;
                    // Recalcular subtotal del store (sumar lineas restantes).
                    if (storeEl && storeEl.isConnected) {
                        let storeSubtotal = 0;
                        storeEl.querySelectorAll('.mp-mini-cart__line-total').forEach(el => {
                            const v = parseFloat(el.textContent.replace(/[^\d.]/g, '')) || 0;
                            storeSubtotal += v;
                        });
                        const head = storeEl.querySelector('.mp-mini-cart__store-head span:last-child');
                        if (head) head.textContent = 'S/ ' + storeSubtotal.toFixed(2);
                    }
                }
                // Badge global del navbar.
                if (window.mpCartBadgeUpdate) window.mpCartBadgeUpdate(sum);
            }, 180);
        })
        .catch(() => {
            btn.disabled = false;
            if (lineEl) lineEl.classList.remove('is-removing');
        });
    });
})();
</script>

@stack('scripts')

{{-- ════════════════════════ AVATAR DROPDOWN (account menu) ════════════════════════
     Dropdown del avatar del comprador en el navbar. En mobile se
     convierte en bottom-sheet (overlay full width al pie). --}}
<script>
(function () {
    const menu = document.getElementById('mpAccountMenu');
    if (!menu) return;
    const btn = document.getElementById('mpAccountMenuBtn');
    const panel = menu.querySelector('.mp-acc-menu__panel');
    function open()  { menu.classList.add('is-open');    btn.setAttribute('aria-expanded', 'true');  panel.setAttribute('aria-hidden', 'false'); }
    function close() { menu.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); panel.setAttribute('aria-hidden', 'true');  }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.contains('is-open') ? close() : open();
    });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
</script>

@unless($isEmbed)
<script>
// PWA — registrar service worker del marketplace. Scope acotado a
// /marketplace para no pisar el SW del ecommerce del tenant (/sw.js).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('{{ asset('sw-marketplace.js') }}', { scope: '/marketplace' })
            .catch(function (err) { console.warn('[PWA] SW registro fallo:', err); });
    });
}
</script>
{{-- Web Push: expone window.ebaemyEnablePush()/DisablePush() para botones.
     No pide permiso automáticamente (mala UX) — se llama desde un control. --}}
<script src="{{ asset('js/push-marketplace.js') }}" defer></script>
@endunless

</body>
</html>
