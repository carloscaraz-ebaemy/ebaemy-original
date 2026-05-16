@php
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
    <meta name="robots"      content="index, follow">
    <meta name="theme-color" content="#0f8a82">

    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph (administrable desde /admin/marketplace/seo) --}}
    <meta property="og:site_name"   content="ebaemy Marketplace">
    <meta property="og:locale"      content="es_PE">
    <meta property="og:type"        content="@yield('og_type', 'website')">
    <meta property="og:title"       content="@yield('og_title', $mpOgTitle)">
    <meta property="og:description" content="@yield('og_description', $mpOgDesc)">
    <meta property="og:image"       content="@yield('og_image', $mpOgImage)">
    <meta property="og:image:secure_url" content="@yield('og_image', $mpOgImage)">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url"         content="@yield('canonical', url()->current())">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', $mpOgTitle)">
    <meta name="twitter:description" content="@yield('og_description', $mpOgDesc)">
    <meta name="twitter:image"       content="@yield('og_image', $mpOgImage)">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">

    {{-- Estilos de cards/grid/paginador compartidos por las 4 vistas que
         renderizan listings (home, categoría oficial, categoría legacy,
         tienda). Antes vivían inline solo en index.blade.php y las otras
         mostraban cards sin estilo. --}}
    @include('marketplace.partials.listing-card-styles')

    @stack('styles')
</head>
<body>

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

        <style>
            /* ───────────── Botón trigger del mega menú ───────────── */
            .mp-mega-toggle {
                display: inline-flex; align-items: center; gap: 6px;
                cursor: pointer; user-select: none;
                white-space: nowrap;
            }
            .mp-mega-toggle__chev { transition: transform .2s; flex-shrink: 0; }
            .mp-mega-toggle[aria-expanded="true"] .mp-mega-toggle__chev { transform: rotate(180deg); }

            /* Mobile (<=640px): el boton "Categorias" come mucho ancho del
               search bar (texto + chevron + padding ~110px). Lo compactamos
               a solo el icono hamburguesa, manteniendo el tap target 44px
               accesible. Asi el input de busqueda recupera ~80px. */
            @media (max-width: 640px) {
                .mp-mega-toggle {
                    padding: 0;
                    min-width: 44px; width: 44px;
                    justify-content: center;
                }
                .mp-mega-toggle__label,
                .mp-mega-toggle__chev { display: none; }
            }

            /* ───────────── Panel desktop (mega menú) ───────────── */
            /* ═══════════════════════ DRAWER 2-pane ═══════════════════════ */
            .mp-cat-drawer {
                position: fixed; inset: 0;
                z-index: 1100;
                pointer-events: none;
                visibility: hidden;
            }
            .mp-cat-drawer.is-open { pointer-events: auto; visibility: visible; }
            .mp-cat-drawer__backdrop {
                position: absolute; inset: 0;
                background: rgba(15, 23, 42, .45);
                opacity: 0;
                transition: opacity .22s ease;
            }
            .mp-cat-drawer.is-open .mp-cat-drawer__backdrop { opacity: 1; }
            .mp-cat-drawer__panel {
                position: absolute;
                top: 0; left: 0; bottom: 0;
                width: min(720px, 92vw);
                max-width: 100vw;
                background: #fff;
                box-shadow: 8px 0 28px rgba(15,23,42,.18);
                display: flex; flex-direction: column;
                /* Critico: el container de panes en mobile usa
                   grid-template-columns:100% 100% (200% de ancho)
                   para el slide horizontal. Sin overflow:hidden el
                   segundo pane se desborda fuera del panel y se ve
                   "cortado" a la derecha del viewport. */
                overflow: hidden;
                transform: translateX(-105%);
                transition: transform .28s cubic-bezier(.16,1,.3,1);
            }
            /* Wrapper full-viewport (forzado con dvw/dvh para iOS Safari
               donde 100vh incluye barras del navegador). Garantiza que
               el drawer cubra todo aun si position:fixed esta roto por
               algun containing block raro en un ancestor. */
            .mp-cat-drawer.is-open {
                width: 100dvw; height: 100dvh;
                top: 0; left: 0;
            }
            .mp-cat-drawer.is-open .mp-cat-drawer__panel { transform: translateX(0); }

            /* Header */
            .mp-cat-drawer__head {
                display: flex; align-items: center; gap: 10px;
                padding: 14px 18px;
                border-bottom: 1px solid #e5e7eb;
                flex-shrink: 0;
            }
            .mp-cat-drawer__title {
                margin: 0; flex: 1;
                font-size: 17px; font-weight: 700; color: #111827;
            }
            .mp-cat-drawer__back,
            .mp-cat-drawer__close {
                width: 38px; height: 38px;
                background: transparent; border: 0; cursor: pointer;
                border-radius: 999px;
                color: #6b7280;
                display: inline-flex; align-items: center; justify-content: center;
                transition: background .12s;
            }
            .mp-cat-drawer__back:hover,
            .mp-cat-drawer__close:hover { background: #f3f4f6; color: #111827; }

            /* Panes container: 2 columnas en desktop, 1 + slide en mobile */
            .mp-cat-drawer__panes {
                flex: 1; min-height: 0;
                display: grid;
                grid-template-columns: 260px 1fr;
            }

            /* Lista de roots (izquierda) */
            .mp-cat-drawer__roots {
                list-style: none; margin: 0; padding: 8px 0;
                overflow-y: auto;
                background: #f9fafb;
                border-right: 1px solid #e5e7eb;
            }
            .mp-cat-drawer__root {
                display: flex; align-items: center; gap: 10px;
                width: 100%; padding: 12px 16px;
                background: transparent; border: 0; cursor: pointer;
                font-size: 14px; font-weight: 500; color: #374151;
                text-align: left;
                transition: background .12s, color .12s;
                min-height: 44px;
            }
            .mp-cat-drawer__root:hover {
                background: #f3f4f6; color: #111827;
            }
            .mp-cat-drawer__root.is-active {
                background: #fff;
                color: var(--mp-primary-dark, #0c6b65);
                font-weight: 700;
                box-shadow: inset 3px 0 0 var(--mp-primary, #0f8a82);
            }
            .mp-cat-drawer__root-icon { font-size: 18px; line-height: 1; flex-shrink: 0; }
            .mp-cat-drawer__root-name { flex: 1; }
            .mp-cat-drawer__root-chev { color: #9ca3af; flex-shrink: 0; }
            .mp-cat-drawer__root.is-active .mp-cat-drawer__root-chev { color: var(--mp-primary, #0f8a82); }

            /* Panel de children (derecha) */
            .mp-cat-drawer__children {
                overflow-y: auto;
                padding: 16px 20px 24px;
            }
            .mp-cat-drawer__child-pane { display: none; }
            .mp-cat-drawer__child-pane.is-active { display: block; }

            .mp-cat-drawer__child-all {
                display: flex; align-items: center; gap: 10px;
                padding: 11px 14px;
                background: var(--mp-primary-soft, #e6f7f5);
                color: var(--mp-primary-dark, #0c6b65);
                border-radius: 10px;
                font-size: 13.5px; font-weight: 600;
                text-decoration: none;
                margin-bottom: 14px;
                min-height: 44px;
            }
            .mp-cat-drawer__child-all:hover { background: #d1fae5; }

            .mp-cat-drawer__child-list {
                list-style: none; margin: 0; padding: 0;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 4px 12px;
            }
            .mp-cat-drawer__child {
                display: flex; align-items: center; gap: 8px;
                padding: 10px 10px;
                font-size: 13.5px; color: #374151;
                text-decoration: none;
                border-radius: 8px;
                transition: background .12s, color .12s;
                min-height: 40px;
            }
            .mp-cat-drawer__child:hover {
                background: #f0fdfa; color: var(--mp-primary-dark);
            }
            .mp-cat-drawer__child-icon { font-size: 14px; flex-shrink: 0; }

            /* Mobile (<700px): un solo panel a la vez, slide entre roots y children */
            @media (max-width: 700px) {
                /* Full-screen: 100dvw/100dvh para evitar el bug clasico
                   de iOS Safari donde 100vh incluye URL bar. !important
                   por si alguna regla anterior gano la cascada. */
                .mp-cat-drawer__panel {
                    width: 100dvw !important;
                    max-width: 100dvw !important;
                    height: 100dvh;
                    box-shadow: none;
                }
                /* Backdrop no aporta nada cuando el panel cubre todo. */
                .mp-cat-drawer__backdrop { display: none; }
                .mp-cat-drawer__panes {
                    grid-template-columns: 100% 100%;
                    transform: translateX(0);
                    transition: transform .25s ease;
                }
                .mp-cat-drawer__panes.is-children-view {
                    transform: translateX(-100%);
                }
                .mp-cat-drawer__roots {
                    border-right: 0;
                }
                .mp-cat-drawer__root.is-active {
                    box-shadow: none;
                    background: #f3f4f6;
                }
                .mp-cat-drawer__child-list {
                    grid-template-columns: 1fr;
                }
                .mp-cat-drawer__child { font-size: 15px; padding: 12px 10px; }
            }
        </style>

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

    {{-- Chip bar de categorias root oculta a pedido del usuario (2026-05-14):
         con 15 root categories ocupaba 2 filas y saturaba el header. La
         navegacion sigue disponible via el boton 'Categorias' del search
         bar (megamenu). Para reactivar quitar el comentario externo. --}}
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
<style>
    .mp-trust-sticky--footer {
        position: static !important;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        border-bottom: 0;
        padding: 18px 16px;
    }
    .mp-trust-sticky--footer .mp-trust-sticky-inner {
        max-width: 1180px; margin: 0 auto;
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 18px 28px;
    }
    @media (max-width: 768px) {
        .mp-trust-sticky--footer { padding: 14px 12px; }
        .mp-trust-sticky--footer .mp-trust-sticky-inner { gap: 10px 18px; font-size: 12.5px; }
    }
</style>

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

{{-- Estilos del newsletter + trust footer --}}
<style>
.mp-footer-newsletter {
    background: linear-gradient(135deg, #0a6f68 0%, #1fb1a6 100%);
    padding: 22px 16px;
}
.mp-footer-newsletter__inner {
    max-width: 1180px; margin: 0 auto;
    display: flex; flex-wrap: wrap; gap: 16px;
    align-items: center; justify-content: space-between;
}
.mp-footer-newsletter__head {
    display: flex; align-items: center; gap: 12px;
    color: #fff; flex: 1; min-width: 260px;
}
.mp-footer-newsletter__icon { font-size: 28px; line-height: 1; }
.mp-footer-newsletter__head strong { display:block; font-size: 15px; font-weight: 700; }
.mp-footer-newsletter__head small { display:block; font-size: 12px; opacity: .85; margin-top: 2px; }
.mp-footer-newsletter__form { display: flex; gap: 6px; flex: 1; min-width: 280px; max-width: 450px; }
.mp-footer-newsletter__form input {
    flex: 1; padding: 10px 14px;
    border: 0; border-radius: 8px 0 0 8px;
    font-size: 14px; outline: 0;
    background: #fff;
}
.mp-footer-newsletter__form button {
    padding: 10px 20px;
    background: #fbbf24; color: #1f2937;
    border: 0; border-radius: 0 8px 8px 0;
    font-weight: 700; font-size: 13.5px;
    cursor: pointer; transition: background .12s;
}
.mp-footer-newsletter__form button:hover { background: #f59e0b; }
.mp-footer-newsletter__msg {
    flex-basis: 100%; font-size: 13px; color: #fff;
    min-height: 18px; margin-top: 2px;
}
.mp-footer-newsletter__msg.is-ok    { color: #d1fae5; font-weight: 600; }
.mp-footer-newsletter__msg.is-error { color: #fee2e2; font-weight: 600; }

.mp-footer-trust { background: #1f2937; padding: 18px 16px; border-top: 1px solid #374151; }
.mp-footer-trust__inner {
    max-width: 1180px; margin: 0 auto;
    display: flex; flex-wrap: wrap; gap: 30px;
    align-items: center; justify-content: space-between;
}
.mp-footer-trust__label {
    font-size: 11px; font-weight: 700;
    color: #9ca3af; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 6px;
}
.mp-footer-trust__items { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.mp-footer-trust__pay {
    display: inline-flex; align-items: center; justify-content: center;
    height: 28px; padding: 0 4px;
    background: #fff; border-radius: 5px;
}
.mp-footer-trust__badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 10px;
    background: #064e3b; color: #d1fae5;
    border: 1px solid #047857; border-radius: 6px;
    font-size: 11.5px;
}
.mp-footer-trust__badge strong { color: #fff; font-weight: 700; }
@media (max-width: 720px) {
    .mp-footer-newsletter__inner { flex-direction: column; }
    .mp-footer-trust__inner { flex-direction: column; gap: 14px; align-items: flex-start; }
}
</style>

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
    }
    window.mpCartBadgeUpdate = paint;
    fetch(@json(route('marketplace.cart.json')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json()).then(paint).catch(function(){ /* silent */ });
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

    function render(data, q) {
        const sug = data.suggestions || [];
        const shops = data.shops || [];
        if (!sug.length && !shops.length) {
            dropdown.innerHTML = `<div class="mp-search-suggest__empty">Sin resultados para "${esc(q)}"</div>`;
            open();
            return;
        }
        let html = '';
        if (sug.length) {
            html += '<div class="mp-search-suggest__section">';
            html += '<div class="mp-search-suggest__header">Productos</div>';
            sug.forEach((s, i) => {
                const url = SEARCH_BASE.replace(/\/$/,'') + '/item/' + s.slug;
                const badges = [];
                if (s.is_pack) badges.push('<span class="mp-search-suggest__badge mp-search-suggest__badge--pack">📦 Pack</span>');
                if (s.is_on_offer && s.discount_pct) badges.push(`<span class="mp-search-suggest__badge mp-search-suggest__badge--offer">-${s.discount_pct}%</span>`);
                if (s.out_of_stock) badges.push('<span class="mp-search-suggest__badge mp-search-suggest__badge--out">Agotado</span>');
                html += `
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
            });
            html += '</div>';
        }
        if (shops.length) {
            html += '<div class="mp-search-suggest__section">';
            html += '<div class="mp-search-suggest__header">Tiendas</div>';
            shops.forEach(sh => {
                // Página dedicada de tienda — tiene OG con logo del seller
                // (preview correcto al compartir en WhatsApp / FB).
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
        if (input.value.trim().length >= 2 && dropdown.innerHTML) open();
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

<style>
.mp-mini-cart {
    position: fixed; inset: 0;
    z-index: 1000;
    pointer-events: none;
    visibility: hidden;
}
.mp-mini-cart.is-open { pointer-events: auto; visibility: visible; }
.mp-mini-cart__backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, .45);
    opacity: 0;
    transition: opacity .25s ease;
    /* Z-INDEX EXPLICITO: el backdrop esta declarado DESPUES del panel
       en el DOM, lo que (sin z-index) lo renderia encima tapando los
       botones (cerrar, eliminar). Lo mandamos atras. */
    z-index: 1;
}
.mp-mini-cart.is-open .mp-mini-cart__backdrop { opacity: 1; }
.mp-mini-cart__panel {
    position: absolute;
    background: #fff;
    display: flex; flex-direction: column;
    box-shadow: -8px 0 24px rgba(15, 23, 42, .15);
    /* Desktop: slide-in desde la derecha */
    top: 0; right: 0; bottom: 0;
    width: min(420px, 92vw);
    transform: translateX(105%);
    transition: transform .28s cubic-bezier(.16,1,.3,1);
    /* Por encima del backdrop para que close/eliminar reciban clicks. */
    z-index: 2;
}
.mp-mini-cart.is-open .mp-mini-cart__panel { transform: translateX(0); }

.mp-mini-cart__head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid var(--mp-line, #e5e7eb);
    flex-shrink: 0;
}
.mp-mini-cart__title {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 16px; font-weight: 700; color: var(--mp-ink, #111827);
}
.mp-mini-cart__count {
    font-size: 11.5px; font-weight: 700;
    background: var(--mp-primary, #0f8a82); color: #fff;
    padding: 2px 8px; border-radius: 999px;
    min-width: 24px; text-align: center;
}
.mp-mini-cart__close {
    width: 36px; height: 36px; border: 0; background: transparent;
    border-radius: 999px; cursor: pointer; color: #6b7280;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.mp-mini-cart__close:hover { background: #f3f4f6; color: #111827; }

.mp-mini-cart__body {
    flex: 1; overflow-y: auto;
    padding: 8px 18px 12px;
}
.mp-mini-cart__loading {
    text-align: center; padding: 40px 16px; color: #9ca3af; font-size: 14px;
}
.mp-mini-cart__empty {
    text-align: center; padding: 48px 16px;
}
.mp-mini-cart__empty-icon {
    font-size: 48px; opacity: .35;
}
.mp-mini-cart__empty h4 {
    margin: 14px 0 6px; font-size: 16px; color: #111827;
}
.mp-mini-cart__empty p {
    margin: 0 0 16px; font-size: 13px; color: #6b7280;
}
.mp-mini-cart__store {
    margin-top: 16px;
    border: 1px solid var(--mp-line, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
}
.mp-mini-cart__store-head {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px;
    background: #f9fafb;
    border-bottom: 1px solid var(--mp-line, #e5e7eb);
    font-size: 12.5px;
    font-weight: 700;
    color: #4b5563;
}
.mp-mini-cart__store-logo {
    width: 22px; height: 22px;
    border-radius: 6px; object-fit: cover;
    border: 1px solid var(--mp-line, #e5e7eb);
    background: #fff;
}
.mp-mini-cart__store-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mp-mini-cart__line {
    display: flex; gap: 10px;
    padding: 10px 12px;
    border-top: 1px solid #f3f4f6;
}
.mp-mini-cart__line:first-child { border-top: 0; }
.mp-mini-cart__line-img {
    width: 50px; height: 50px;
    border-radius: 8px; flex-shrink: 0;
    background: #f3f4f6;
    object-fit: cover;
}
.mp-mini-cart__line-info { flex: 1; min-width: 0; font-size: 12.5px; }
.mp-mini-cart__line-title {
    color: #111827; font-weight: 500;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    line-height: 1.35;
}
.mp-mini-cart__line-meta {
    color: #6b7280; margin-top: 3px;
}
.mp-mini-cart__line-total {
    font-weight: 700; color: var(--mp-primary-dark, #0c6b65);
    font-size: 13px;
    white-space: nowrap;
}
.mp-mini-cart__line-actions {
    display: flex; flex-direction: column; align-items: flex-end;
    gap: 6px; flex-shrink: 0;
}
.mp-mini-cart__line-remove {
    width: 28px; height: 28px;
    background: transparent; border: 0; cursor: pointer;
    border-radius: 6px;
    color: #94a3b8;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s, color .15s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    padding: 0;
}
.mp-mini-cart__line-remove:hover {
    background: #fef2f2; color: #dc2626;
}
.mp-mini-cart__line-remove:disabled {
    opacity: .4; cursor: wait;
}
/* Fadeout/slide al remover: NO refrescamos todo el panel, solo
   animamos la linea y la quitamos del DOM. UX suave. */
.mp-mini-cart__line {
    transition: opacity .18s ease, max-height .2s ease, padding .2s ease, margin .2s ease;
    overflow: hidden;
    max-height: 200px;
}
.mp-mini-cart__line.is-removing {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
    margin-bottom: 0;
    pointer-events: none;
}

.mp-mini-cart__foot {
    border-top: 1px solid var(--mp-line, #e5e7eb);
    padding: 14px 18px calc(14px + env(safe-area-inset-bottom));
    flex-shrink: 0;
    background: #fff;
}
.mp-mini-cart__total {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
    font-size: 14px; color: #6b7280;
}
.mp-mini-cart__total strong {
    font-size: 19px; color: var(--mp-ink, #111827); font-weight: 800;
}
.mp-mini-cart__actions {
    display: grid; grid-template-columns: 1fr 1.4fr; gap: 8px;
}
.mp-mini-cart__btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 12px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
    text-decoration: none;
    transition: background .15s, border-color .15s;
    text-align: center;
    min-height: 44px;
}
.mp-mini-cart__btn--ghost {
    background: #fff; color: var(--mp-ink, #111827);
    border: 1.5px solid var(--mp-line, #e5e7eb);
}
.mp-mini-cart__btn--ghost:hover { border-color: var(--mp-primary, #0f8a82); color: var(--mp-primary-dark); }
.mp-mini-cart__btn--primary {
    background: var(--mp-primary, #0f8a82); color: #fff;
    border: 1.5px solid var(--mp-primary, #0f8a82);
}
.mp-mini-cart__btn--primary:hover { background: var(--mp-primary-dark, #0c6b65); }

/* Mobile: drawer slide-up desde abajo, no desde la derecha */
@media (max-width: 600px) {
    .mp-mini-cart__panel {
        top: auto;
        left: 0; right: 0; bottom: 0;
        width: 100%;
        max-height: 88vh;
        border-radius: 16px 16px 0 0;
        transform: translateY(105%);
    }
    .mp-mini-cart.is-open .mp-mini-cart__panel { transform: translateY(0); }
    .mp-mini-cart__head {
        padding: 14px 16px;
        position: relative;
    }
    /* Pull-handle visual indicator */
    .mp-mini-cart__head::before {
        content: '';
        position: absolute;
        top: 6px; left: 50%;
        transform: translateX(-50%);
        width: 40px; height: 4px;
        background: #d1d5db;
        border-radius: 999px;
    }
}
</style>

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
<style>
.mp-acc-menu { position: relative; }
.mp-acc-menu__btn {
    background: transparent; border: 0; cursor: pointer;
    padding: 6px 10px 6px 6px;
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--mp-ink, #111827); font-weight: 600; font-size: 14px;
    border-radius: 999px;
    transition: background .15s;
    -webkit-tap-highlight-color: transparent;
}
.mp-acc-menu__btn:hover { background: var(--mp-line-soft, #f1f5f9); }
.mp-acc-menu__chev { color: #94a3b8; transition: transform .15s; flex-shrink: 0; }
.mp-acc-menu.is-open .mp-acc-menu__chev { transform: rotate(180deg); }
.mp-acc-avatar {
    width: 30px; height: 30px;
    border-radius: 999px;
    background: linear-gradient(135deg, #0f8a82, #0a6f68);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
    flex-shrink: 0;
}
.mp-acc-avatar--lg { width: 42px; height: 42px; font-size: 16px; }

.mp-acc-menu__panel {
    position: absolute; top: calc(100% + 8px); right: 0;
    min-width: 240px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 12px 32px -8px rgba(15,23,42,.18);
    padding: 6px;
    z-index: 1050;
    opacity: 0; visibility: hidden;
    transform: translateY(-4px);
    transition: opacity .15s, transform .15s, visibility .15s;
}
.mp-acc-menu.is-open .mp-acc-menu__panel {
    opacity: 1; visibility: visible; transform: translateY(0);
}
.mp-acc-menu__head {
    display: flex; gap: 12px; align-items: center;
    padding: 12px 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 6px;
}
.mp-acc-menu__head-info { display: flex; flex-direction: column; min-width: 0; }
.mp-acc-menu__head-info strong { font-size: 14px; color: #0f172a; }
.mp-acc-menu__head-info span { font-size: 12.5px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.mp-acc-menu__panel a,
.mp-acc-menu__logout {
    display: block; width: 100%;
    padding: 10px 12px;
    font-size: 14px; color: #0f172a; font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    text-align: left;
    background: transparent; border: 0; cursor: pointer;
    transition: background .12s;
}
.mp-acc-menu__panel a:hover { background: #f0fdfa; color: #0c6b65; }
.mp-acc-menu__logout {
    margin-top: 4px;
    border-top: 1px solid #f1f5f9;
    color: #b91c1c; font-weight: 600;
}
.mp-acc-menu__logout:hover { background: #fef2f2; }

/* Mobile: dropdown se vuelve bottom-sheet con backdrop */
@media (max-width: 640px) {
    .mp-acc-menu.is-open::before {
        content: '';
        position: fixed; inset: 0;
        background: rgba(15,23,42,.45);
        z-index: 1049;
    }
    .mp-acc-menu__panel {
        position: fixed;
        top: auto; right: 0; left: 0; bottom: 0;
        min-width: 0;
        border-radius: 16px 16px 0 0;
        padding: 8px 12px calc(16px + env(safe-area-inset-bottom));
        transform: translateY(100%);
        transition: transform .25s ease, visibility .25s, opacity .25s;
    }
    .mp-acc-menu.is-open .mp-acc-menu__panel { transform: translateY(0); }
    .mp-acc-menu__head { padding: 14px 8px 16px; }
    .mp-acc-menu__panel a,
    .mp-acc-menu__logout { padding: 14px 12px; font-size: 15px; }
}
</style>
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

</body>
</html>
