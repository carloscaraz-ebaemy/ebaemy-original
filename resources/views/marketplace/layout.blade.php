<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Marketplace ebaemy — productos de todas nuestras tiendas')</title>
    <meta name="description" content="@yield('description', 'Descubre productos publicados por miles de tiendas que usan ebaemy. Un solo lugar para comprar, contactar o solicitar envío.')">
    <meta name="keywords"    content="@yield('keywords', 'marketplace peru, ebaemy, tiendas online, compra, productos, catalogo')">
    <meta name="robots"      content="index, follow">
    <meta name="theme-color" content="#0f8a82">

    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph --}}
    <meta property="og:site_name"   content="ebaemy Marketplace">
    <meta property="og:locale"      content="es_PE">
    <meta property="og:type"        content="@yield('og_type', 'website')">
    <meta property="og:title"       content="@yield('og_title', 'Marketplace ebaemy')">
    <meta property="og:description" content="@yield('og_description', 'Productos de todas las tiendas ebaemy en un solo lugar.')">
    <meta property="og:image"       content="@yield('og_image', asset('logo/logo.png'))">
    <meta property="og:image:secure_url" content="@yield('og_image', asset('logo/logo.png'))">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url"         content="@yield('canonical', url()->current())">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('og_title', 'Marketplace ebaemy')">
    <meta name="twitter:description" content="@yield('og_description', 'Productos de todas las tiendas ebaemy en un solo lugar.')">
    <meta name="twitter:image"       content="@yield('og_image', asset('logo/logo.png'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
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
            <a href="{{ route('pricing') }}">Planes y precios</a>
            <a href="{{ route('seller.landing') }}">¿Quieres vender?</a>
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
            <button type="button"
                    id="mpMegaToggle"
                    class="mp-search-category mp-mega-toggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="mpMegaPanel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <span class="mp-mega-toggle__label">Categorías</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mp-mega-toggle__chev"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <input type="search"
                   name="q"
                   value="{{ $q ?? '' }}"
                   class="mp-search-input"
                   placeholder="Busca productos, tiendas o categorías…"
                   aria-label="Buscar">
            <button type="submit" class="mp-search-btn" aria-label="Buscar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </form>

        {{-- ═══════════════════════ MEGA MENÚ DE CATEGORÍAS ═══════════════════════ --}}
        @isset($marketplaceNavCategories)
            <div id="mpMegaPanel" class="mp-mega-panel" role="dialog" aria-label="Categorías" aria-hidden="true">
                <div class="mp-mega-panel__head">
                    <h3>Categorías</h3>
                    <button type="button" class="mp-mega-panel__close" aria-label="Cerrar" data-mega-close>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="mp-mega-panel__grid">
                    @foreach($marketplaceNavCategories as $root)
                        @php $rootHref = route('marketplace.category_official', ['fullSlug' => $root->full_slug]); @endphp
                        <details class="mp-mega-col" {{ $loop->first ? 'open' : '' }}>
                            <summary class="mp-mega-col__head">
                                <span class="mp-mega-col__icon">{{ $root->icon ?: '📦' }}</span>
                                <span class="mp-mega-col__name">{{ $root->name }}</span>
                                @if(!empty($root->listings_count_cache))
                                    <span class="mp-mega-col__count">{{ $root->listings_count_cache }}</span>
                                @endif
                                <svg class="mp-mega-col__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <div class="mp-mega-col__body">
                                @if($root->children && $root->children->count())
                                    <ul class="mp-mega-sublist">
                                        @foreach($root->children->take(8) as $child)
                                            <li>
                                                <a href="{{ route('marketplace.category_official', ['fullSlug' => $child->full_slug]) }}" class="mp-mega-sublink">
                                                    @if($child->icon)<span class="mp-mega-sublink__icon">{{ $child->icon }}</span>@endif
                                                    <span class="mp-mega-sublink__name">{{ $child->name }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($root->children->count() > 8)
                                        <a href="{{ $rootHref }}" class="mp-mega-seeall">Ver todas ({{ $root->children->count() }}) →</a>
                                    @else
                                        <a href="{{ $rootHref }}" class="mp-mega-seeall">Ver todo en {{ $root->name }} →</a>
                                    @endif
                                @else
                                    <a href="{{ $rootHref }}" class="mp-mega-seeall">Ver productos →</a>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
            <div id="mpMegaBackdrop" class="mp-mega-backdrop" aria-hidden="true" data-mega-close></div>
        @endisset

        <style>
            /* ───────────── Botón trigger del mega menú ───────────── */
            .mp-mega-toggle {
                display: inline-flex; align-items: center; gap: 6px;
                cursor: pointer; user-select: none;
                white-space: nowrap;
            }
            .mp-mega-toggle__chev { transition: transform .2s; flex-shrink: 0; }
            .mp-mega-toggle[aria-expanded="true"] .mp-mega-toggle__chev { transform: rotate(180deg); }

            /* ───────────── Panel desktop (mega menú) ───────────── */
            .mp-mega-panel {
                position: absolute;
                top: calc(100% + 6px);
                left: 0; right: 0;
                max-width: 1180px;
                margin: 0 auto;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                box-shadow: 0 18px 40px -16px rgba(15,23,42,.18), 0 6px 16px -8px rgba(0,0,0,.08);
                padding: 18px 22px 20px;
                z-index: 50;
                opacity: 0;
                transform: translateY(-8px);
                pointer-events: none;
                transition: opacity .18s ease, transform .18s ease;
            }
            .mp-mega-panel.is-open { opacity: 1; transform: translateY(0); pointer-events: auto; }
            .mp-mega-panel__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
            .mp-mega-panel__head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #111827; }
            .mp-mega-panel__close {
                display: none; /* solo móvil */
                background: transparent; border: 0; cursor: pointer;
                width: 36px; height: 36px; border-radius: 999px; color: #6b7280;
            }
            .mp-mega-panel__close:hover { background: #f3f4f6; color: #111827; }
            .mp-mega-panel__grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 18px 26px;
            }
            .mp-mega-col { border: 0; }
            .mp-mega-col__head {
                display: flex; align-items: center; gap: 8px;
                padding: 6px 0 8px;
                cursor: pointer;
                list-style: none;
                border-bottom: 1px solid #f1f5f9;
            }
            .mp-mega-col__head::-webkit-details-marker { display: none; }
            .mp-mega-col__icon { font-size: 16px; line-height: 1; }
            .mp-mega-col__name { font-size: 14px; font-weight: 700; color: #0f172a; flex: 1; }
            .mp-mega-col__count {
                font-size: 11px; font-weight: 700;
                background: #f3f4f6; color: #6b7280;
                padding: 1px 7px; border-radius: 999px;
            }
            .mp-mega-col__chev { color: #94a3b8; transition: transform .2s; display: none; /* solo móvil */ }
            .mp-mega-col__body { padding-top: 6px; }
            .mp-mega-sublist {
                list-style: none; margin: 0; padding: 0;
                display: flex; flex-direction: column; gap: 1px;
            }
            .mp-mega-sublink {
                display: flex; align-items: center; gap: 8px;
                padding: 6px 6px;
                font-size: 13px; color: #374151;
                text-decoration: none;
                border-radius: 6px;
                transition: background .12s, color .12s;
            }
            .mp-mega-sublink:hover { background: #f0fdfa; color: var(--mp-primary, #0f8a82); }
            .mp-mega-sublink__icon { font-size: 13px; }
            .mp-mega-seeall {
                display: inline-block;
                margin-top: 6px; padding: 4px 6px;
                font-size: 12px; font-weight: 600;
                color: var(--mp-primary, #0f8a82);
                text-decoration: none;
            }
            .mp-mega-seeall:hover { text-decoration: underline; }

            /* ───────────── Backdrop ───────────── */
            .mp-mega-backdrop {
                display: none;
                position: fixed; inset: 0;
                background: rgba(15, 23, 42, .35);
                z-index: 49;
                opacity: 0;
                transition: opacity .18s ease;
            }
            .mp-mega-backdrop.is-open { display: block; opacity: 1; }

            /* ───────────── Mobile: drawer slide desde la izquierda ───────────── */
            @media (max-width: 900px) {
                .mp-mega-toggle__label { display: none; } /* solo icon en móvil */
                .mp-mega-panel {
                    position: fixed;
                    top: 0; left: 0; right: auto; bottom: 0;
                    width: min(86vw, 360px);
                    max-width: 360px;
                    margin: 0;
                    border-radius: 0 18px 18px 0;
                    transform: translateX(-100%);
                    transition: transform .25s ease, opacity .2s ease;
                    overflow-y: auto;
                    padding: 16px 14px calc(20px + env(safe-area-inset-bottom));
                    opacity: 1;
                }
                .mp-mega-panel.is-open { transform: translateX(0); }
                .mp-mega-panel__close { display: inline-flex; align-items: center; justify-content: center; }
                .mp-mega-panel__grid { grid-template-columns: 1fr; gap: 4px; }
                .mp-mega-col__head { padding: 12px 4px; border-bottom: 1px solid #f1f5f9; }
                .mp-mega-col__chev { display: inline-block; }
                .mp-mega-col[open] .mp-mega-col__chev { transform: rotate(180deg); color: var(--mp-primary, #0f8a82); }
                .mp-mega-sublink { padding: 10px 8px; font-size: 14px; } /* tap targets más grandes */
                .mp-mega-col__body { padding-left: 8px; }
            }
            @media (min-width: 901px) {
                /* En desktop todas las columnas siempre abiertas, no acordeón */
                .mp-mega-col { /* details actúa como div en desktop */ }
                .mp-mega-col__head { cursor: default; }
            }
            @media (max-width: 600px) {
                .mp-mega-panel__grid { grid-template-columns: 1fr; }
            }
            @media (min-width: 901px) and (max-width: 1100px) {
                .mp-mega-panel__grid { grid-template-columns: repeat(3, 1fr); }
            }
        </style>

        <script>
            (function () {
                var btn = document.getElementById('mpMegaToggle');
                var panel = document.getElementById('mpMegaPanel');
                var backdrop = document.getElementById('mpMegaBackdrop');
                if (!btn || !panel) return;

                function open() {
                    panel.classList.add('is-open');
                    panel.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-expanded', 'true');
                    if (backdrop) backdrop.classList.add('is-open');
                    if (window.matchMedia('(max-width: 900px)').matches) {
                        document.body.style.overflow = 'hidden';
                    }
                }
                function close() {
                    panel.classList.remove('is-open');
                    panel.setAttribute('aria-hidden', 'true');
                    btn.setAttribute('aria-expanded', 'false');
                    if (backdrop) backdrop.classList.remove('is-open');
                    document.body.style.overflow = '';
                }
                function toggle() {
                    if (panel.classList.contains('is-open')) close(); else open();
                }

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggle();
                });

                // Cerrar al clickear fuera (desktop) o en backdrop (mobile)
                document.addEventListener('click', function (e) {
                    if (!panel.classList.contains('is-open')) return;
                    if (e.target.closest('[data-mega-close]')) { close(); return; }
                    if (panel.contains(e.target) || btn.contains(e.target)) return;
                    close();
                });

                // Escape cierra
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && panel.classList.contains('is-open')) close();
                });

                // En desktop, todas las columnas siempre abiertas (overrideamos el details)
                if (window.matchMedia('(min-width: 901px)').matches) {
                    panel.querySelectorAll('details.mp-mega-col').forEach(function (d) {
                        d.setAttribute('open', '');
                        // Bloquear toggle al click en summary en desktop
                        d.querySelector('summary').addEventListener('click', function (e) { e.preventDefault(); });
                    });
                }
            })();
        </script>

        <div class="mp-nav-actions">
            <a href="{{ route('marketplace.cart') }}" class="mp-nav-link" id="mpCartNavLink"
               title="Mi carrito" style="position:relative">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="mp-nav-link-text">Carrito</span>
                <span id="mpCartBadge"
                      style="display:none;position:absolute;top:-2px;right:-6px;background:#dc2626;color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:18px;height:18px;padding:0 5px;line-height:18px;text-align:center"></span>
            </a>
            <a href="{{ route('seller.landing') }}" class="mp-btn-sell">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.4 7h12.8"/><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/></svg>
                <span class="mp-btn-sell-text">Vender en ebaemy</span>
            </a>
        </div>
    </div>

    @isset($marketplaceNavCategories)
        @if($marketplaceNavCategories->count() > 0)
            <nav class="mp-cats-bar" aria-label="Categorías">
                <div class="mp-cats-inner">
                    <a href="{{ route('marketplace.index') }}"
                       class="mp-cat-chip {{ empty($activeCategoryFullSlug ?? null) ? 'is-active' : '' }}">
                        📦 Todas
                    </a>
                    @foreach($marketplaceNavCategories as $root)
                        <a href="{{ route('marketplace.category_official', ['fullSlug' => $root->full_slug]) }}"
                           class="mp-cat-chip {{ ($activeCategoryFullSlug ?? null) === $root->full_slug ? 'is-active' : '' }}">
                            @if($root->icon){{ $root->icon }} @endif{{ $root->name }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    @endisset
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
    <div class="mp-footer-grid">
        <div class="mp-footer-brand">
            <h3>
                <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#1fb1a6,#0a6f68);font-weight:800;">e</span>
                ebaemy
            </h3>
            <p>El marketplace peruano que conecta compradores con tiendas verificadas. Todas las empresas tienen RUC validado y facturación electrónica.</p>
            <div class="mp-footer-socials">
                <a href="#" class="mp-footer-social" aria-label="Facebook">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" class="mp-footer-social" aria-label="Instagram">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                </a>
                <a href="#" class="mp-footer-social" aria-label="WhatsApp">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.173.198-.297.298-.495.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
                </a>
            </div>
        </div>

        <div class="mp-footer-col">
            <h4>Comprar</h4>
            <ul>
                <li><a href="{{ route('marketplace.index') }}">Explorar marketplace</a></li>
                <li><a href="{{ route('marketplace.index', ['sort' => 'newest']) }}">Novedades</a></li>
                <li><a href="{{ route('marketplace.index', ['sort' => 'price_asc']) }}">Ofertas</a></li>
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
                <li><a href="#">Preguntas frecuentes</a></li>
                <li><a href="#">Términos y condiciones</a></li>
                <li><a href="#">Política de privacidad</a></li>
            </ul>
        </div>
    </div>

    <div class="mp-footer-bottom">
        <div>© {{ date('Y') }} ebaemy — Todas las tiendas del Perú, un solo lugar.</div>
        <div>Hecho en 🇵🇪 con Laravel + ebaemy SaaS</div>
    </div>
</footer>

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
</script>

{{-- JS común a todas las vistas que renderizan cards de listings:
     hover en color dots + click en nombre de tienda. --}}
@include('marketplace.partials.listing-card-script')

</body>
</html>
