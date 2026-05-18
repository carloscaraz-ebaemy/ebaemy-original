@extends('marketplace.layout')

@section('title', ($q ? 'Buscar "'.$q.'" — ' : '') . 'Marketplace ebaemy')

@section('content')

@php
    $baseQs = array_filter([
        'q'         => $q,
        'sort'      => $sort,
        'price_min' => $priceMin,
        'price_max' => $priceMax,
        'shop'      => $shopSubdomain ?? null,
        'on_offer'  => !empty($onOfferOnly) ? 1 : null,
        'verified'  => !empty($verifiedOnly) ? 1 : null,
        'in_stock'  => !empty($inStockOnly) ? 1 : null,
        'packs'     => !empty($packsOnly) ? 1 : null,
    ], fn($v) => $v !== null && $v !== '');

    $hasBooleanFilter = !empty($onOfferOnly) || !empty($verifiedOnly) || !empty($inStockOnly) || !empty($packsOnly);
    $hasFilters = !empty($q) || !empty($category) || $priceMin !== null || $priceMax !== null || !empty($shopSubdomain) || $hasBooleanFilter;
    $isHome = empty($q) && empty($category) && $priceMin === null && $priceMax === null && empty($shopSubdomain) && !$hasBooleanFilter;

    // Helper para alternar un filtro booleano en el query string actual.
    // Si el filtro está activo lo quita, si no lo agrega.
    $toggleQs = function(string $key) use ($baseQs) {
        $qs = $baseQs;
        if (isset($qs[$key])) unset($qs[$key]);
        else $qs[$key] = 1;
        return $qs;
    };

    // Tiendas únicas derivadas del listado actual (para la sección "Tiendas destacadas").
    // Usamos la data que el listing ya carga — cero cambios al controller.
    $featuredShops = collect();
    if ($isHome && !$listings->isEmpty()) {
        $featuredShops = collect($listings->items())
            ->filter(fn ($l) => !empty($l->tenant_fqdn))
            ->groupBy('tenant_fqdn')
            ->map(fn ($items) => $items->first())
            ->take(6)
            ->values();
    }
@endphp

{{-- Hero removido: el cliente ve trust bar + categorías + productos sin
     interferencia. Cero scroll antes del primer producto en móvil. --}}

{{-- Trust bar movida al footer (layout.blade.php) — antes ocupaba espacio
     arriba del fold sin necesidad. Ahora aparece como reaseguro al pie. --}}

{{-- Carrusel de categorías removido del home: la navegación de categorías
     vive en el mega menú del header (botón "Categorías" de la search bar)
     y en la barra de chips superior. Evita duplicación visual. --}}

{{-- Tiendas destacadas se muestran DESPUÉS del listado de productos para no
     desplazar la fila de productos por debajo del fold (UX 2026: productos primero). --}}

{{-- ═══════════════════════ BANNER DE URGENCIA (al filtrar por ofertas) ═══════════════════════
     Cuando el cliente esta viendo /marketplace?on_offer=1, mostramos un
     banner sticky arriba del grid con countdown a la oferta mas pronta.
     Aumenta urgencia y conversion. --}}
@php
    $soonestOfferEnd = null;
    if (!empty($onOfferOnly) && !$listings->isEmpty()) {
        $soonestOfferEnd = collect($listings->items())
            ->filter(fn ($l) => !empty($l->offer_ends_at))
            ->map(fn ($l) => \Carbon\Carbon::parse($l->offer_ends_at))
            ->filter(fn ($d) => $d->isFuture())
            ->sort()
            ->first();
    }
@endphp
@if($soonestOfferEnd)
    <div class="mp-offers-urgency-banner" data-ends-at="{{ $soonestOfferEnd->toIso8601String() }}">
        <span class="mp-offers-urgency-banner__icon">⏰</span>
        <span class="mp-offers-urgency-banner__text">
            La oferta más próxima termina en
            <strong class="mp-offers-urgency-banner__countdown" data-countdown>—</strong>
            — ¡aprovecha antes que se acabe!
        </span>
    </div>
    <style>
    .mp-offers-urgency-banner {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px;
        background: linear-gradient(90deg, #fef2f2 0%, #fff7ed 100%);
        border: 1px solid #fecaca;
        border-radius: 12px;
        margin-bottom: 14px;
        font-size: 14px;
        color: #7f1d1d;
        line-height: 1.4;
    }
    .mp-offers-urgency-banner__icon { font-size: 20px; flex-shrink: 0; }
    .mp-offers-urgency-banner__text { flex: 1; }
    .mp-offers-urgency-banner__countdown {
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        font-weight: 700; color: #dc2626;
        padding: 1px 8px;
        background: rgba(220,38,38,.1);
        border-radius: 6px;
        white-space: nowrap;
    }
    @media (max-width: 600px) {
        .mp-offers-urgency-banner { font-size: 12.5px; padding: 10px 12px; gap: 8px; }
        .mp-offers-urgency-banner__icon { font-size: 18px; }
    }
    </style>
    <script>
    (function () {
        var banner = document.querySelector('.mp-offers-urgency-banner');
        if (!banner) return;
        var ends = new Date(banner.getAttribute('data-ends-at')).getTime();
        var span = banner.querySelector('[data-countdown]');
        function fmt(ms) {
            if (ms <= 0) return 'Expirada';
            var d = Math.floor(ms / 86400000);
            var h = Math.floor((ms % 86400000) / 3600000);
            var m = Math.floor((ms % 3600000) / 60000);
            var s = Math.floor((ms % 60000) / 1000);
            if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
            if (h > 0) return h + 'h ' + m + 'm ' + s + 's';
            return m + 'm ' + s + 's';
        }
        function tick() {
            span.textContent = fmt(ends - Date.now());
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
@endif

{{-- ═══════════════════════ MODO SELECCION MULTIPLE (solo en ofertas) ═══════════════════════
     Al filtrar por ofertas, ofrecemos un toggle que activa checkboxes en
     las cards. Sticky bar abajo muestra contador + ahorro total + boton
     'Agregar al carrito'. Bulk add en un solo POST. --}}
@if(!empty($onOfferOnly) && !$listings->isEmpty())
    <div class="mp-bulk-toggle-row">
        <button type="button" id="mpBulkToggle" class="mp-bulk-toggle" aria-pressed="false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span data-bulk-label>Comprar varias a la vez</span>
        </button>
        <span class="mp-bulk-toggle-hint">Selecciona varias ofertas y agrégalas al carrito de un click.</span>
    </div>

    {{-- Sticky bar abajo cuando hay items seleccionados --}}
    <div class="mp-bulk-bar" id="mpBulkBar" hidden role="region" aria-label="Resumen seleccion">
        <div class="mp-bulk-bar__info">
            <strong id="mpBulkCount">0</strong> seleccionados
            <span class="mp-bulk-bar__savings">· Ahorras <strong id="mpBulkSavings">S/ 0.00</strong></span>
        </div>
        <div class="mp-bulk-bar__actions">
            <button type="button" id="mpBulkClear" class="mp-bulk-bar__clear">Limpiar</button>
            <button type="button" id="mpBulkAdd" class="mp-bulk-bar__add">
                Agregar al carrito
                <span id="mpBulkAddTotal" class="mp-bulk-bar__add-total"></span>
            </button>
        </div>
    </div>

    <style>
    .mp-bulk-toggle-row {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 12px; flex-wrap: wrap;
    }
    .mp-bulk-toggle {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 16px;
        background: #fff;
        border: 1.5px solid var(--mp-primary, #0f8a82);
        color: var(--mp-primary-dark, #0c6b65);
        border-radius: 999px;
        font-size: 13.5px; font-weight: 700;
        cursor: pointer;
        transition: background .15s, color .15s;
    }
    .mp-bulk-toggle:hover { background: var(--mp-primary-soft, #e6f7f5); }
    .mp-bulk-toggle[aria-pressed="true"] {
        background: var(--mp-primary, #0f8a82); color: #fff;
    }
    .mp-bulk-toggle-hint { font-size: 12.5px; color: #6b7280; }
    @media (max-width: 600px) { .mp-bulk-toggle-hint { display: none; } }

    /* Cards en modo seleccion: checkbox visible top-left, fondo highlight si elegido */
    .mp-bulk-mode .mp-card { position: relative; }
    .mp-bulk-mode .mp-card::before {
        content: '';
        position: absolute; top: 10px; left: 10px;
        width: 26px; height: 26px;
        background: #fff;
        border: 2px solid var(--mp-line, #e5e7eb);
        border-radius: 6px;
        z-index: 5;
        transition: background .15s, border-color .15s;
    }
    .mp-bulk-mode .mp-card.is-bulk-selected::before {
        background: var(--mp-primary, #0f8a82);
        border-color: var(--mp-primary, #0f8a82);
    }
    .mp-bulk-mode .mp-card.is-bulk-selected::after {
        content: '';
        position: absolute; top: 13px; left: 16px;
        width: 6px; height: 12px;
        border: solid #fff;
        border-width: 0 2.5px 2.5px 0;
        transform: rotate(45deg);
        z-index: 6;
    }
    .mp-bulk-mode .mp-card.is-bulk-selected {
        border-color: var(--mp-primary, #0f8a82);
        box-shadow: 0 0 0 2px var(--mp-primary-soft, #e6f7f5);
    }
    /* En modo seleccion, click en el card NO navega — selecciona/deselecciona */
    .mp-bulk-mode .mp-card { cursor: pointer; }
    /* Boton + del card oculto en modo seleccion (evita confusion) */
    .mp-bulk-mode .mp-card-quickadd { display: none !important; }

    /* Sticky bar */
    .mp-bulk-bar {
        position: fixed;
        left: 0; right: 0; bottom: 0;
        background: #fff;
        border-top: 1px solid var(--mp-line, #e5e7eb);
        box-shadow: 0 -8px 24px rgba(15, 23, 42, .12);
        padding: 14px 18px calc(14px + env(safe-area-inset-bottom));
        z-index: 200;
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }
    .mp-bulk-bar__info { font-size: 14px; color: var(--mp-ink, #111827); }
    .mp-bulk-bar__info strong { font-weight: 700; color: var(--mp-primary-dark, #0c6b65); }
    .mp-bulk-bar__savings { color: #16a34a; margin-left: 6px; }
    .mp-bulk-bar__savings strong { color: #16a34a; }
    .mp-bulk-bar__actions { display: inline-flex; gap: 10px; align-items: center; }
    .mp-bulk-bar__clear {
        background: transparent; border: 0; color: #6b7280;
        font-size: 13px; cursor: pointer;
        padding: 8px 10px;
    }
    .mp-bulk-bar__clear:hover { color: var(--mp-ink); }
    .mp-bulk-bar__add {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 12px 22px;
        background: var(--mp-primary, #0f8a82); color: #fff;
        border: 0; border-radius: 10px;
        font-weight: 700; font-size: 14px;
        cursor: pointer;
        transition: background .15s;
        min-height: 44px;
    }
    .mp-bulk-bar__add:hover { background: var(--mp-primary-dark, #0c6b65); }
    .mp-bulk-bar__add-total { opacity: .85; font-weight: 600; }
    @media (max-width: 600px) {
        .mp-bulk-bar { padding: 12px 14px calc(12px + env(safe-area-inset-bottom)); gap: 10px; }
        .mp-bulk-bar__info { font-size: 13px; flex: 1 1 100%; }
        .mp-bulk-bar__actions { flex: 1; justify-content: flex-end; }
        .mp-bulk-bar__add { padding: 12px 16px; }
    }
    </style>

    <script>
    (function () {
        var toggle  = document.getElementById('mpBulkToggle');
        var bar     = document.getElementById('mpBulkBar');
        if (!toggle || !bar) return;
        var countEl   = document.getElementById('mpBulkCount');
        var savingsEl = document.getElementById('mpBulkSavings');
        var totalEl   = document.getElementById('mpBulkAddTotal');
        var clearBtn  = document.getElementById('mpBulkClear');
        var addBtn    = document.getElementById('mpBulkAdd');
        var label     = toggle.querySelector('[data-bulk-label]');

        var selected = new Map(); // id -> {price, originalPrice, title}
        var mode = false;

        function applyMode(on) {
            mode = on;
            document.body.classList.toggle('mp-bulk-mode', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            label.textContent = on ? 'Salir del modo selección' : 'Comprar varias a la vez';
            if (!on) {
                clear();
            }
        }

        function refreshBar() {
            var n = selected.size;
            countEl.textContent = n;
            var total = 0, savings = 0;
            selected.forEach(function (v) {
                total   += v.price;
                savings += Math.max(0, (v.originalPrice || 0) - v.price);
            });
            savingsEl.textContent = 'S/ ' + savings.toFixed(2);
            totalEl.textContent   = '(S/ ' + total.toFixed(2) + ')';
            bar.hidden = n === 0;
        }

        function clear() {
            selected.clear();
            document.querySelectorAll('.mp-card.is-bulk-selected').forEach(function (c) {
                c.classList.remove('is-bulk-selected');
            });
            refreshBar();
        }

        toggle.addEventListener('click', function () { applyMode(!mode); });
        clearBtn.addEventListener('click', clear);

        // Delegate clicks: en modo seleccion, click en card → toggle. Sin
        // modo seleccion, navegacion normal del <a class="mp-card">.
        document.addEventListener('click', function (e) {
            if (!mode) return;
            var card = e.target.closest('.mp-card');
            if (!card) return;
            e.preventDefault();
            // Solo agregamos a la seleccion los productos que se pueden
            // bulk-add (sin variantes/pack). El backend tambien valida.
            var quickAdd = card.querySelector('.mp-card-quickadd');
            var isDetailOnly = quickAdd && quickAdd.classList.contains('is-detail');
            if (isDetailOnly) {
                // tiene variantes — no se puede agregar masivo, navegar al detalle
                window.location.href = card.getAttribute('href');
                return;
            }
            var id = quickAdd ? parseInt(quickAdd.getAttribute('data-listing-id'), 10) : null;
            if (!id) return;
            // Capturar precios desde el DOM
            var priceEl = card.querySelector('.mp-card-price');
            var oldEl   = card.querySelector('.mp-card-price-old');
            var price   = priceEl ? parseFloat(priceEl.textContent.replace(/[^0-9.]/g, '')) || 0 : 0;
            var oldP    = oldEl   ? parseFloat(oldEl.textContent.replace(/[^0-9.]/g, '')) || 0 : 0;

            if (selected.has(id)) {
                selected.delete(id);
                card.classList.remove('is-bulk-selected');
            } else {
                selected.set(id, { price: price, originalPrice: oldP, title: '' });
                card.classList.add('is-bulk-selected');
            }
            refreshBar();
        });

        addBtn.addEventListener('click', function () {
            if (selected.size === 0) return;
            addBtn.disabled = true;
            var oldHtml = addBtn.innerHTML;
            addBtn.innerHTML = 'Agregando…';

            fetch(@json(route('marketplace.cart.bulk_add')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ listing_ids: Array.from(selected.keys()) })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error('failed');
                if (window.mpCartBadgeUpdate) window.mpCartBadgeUpdate(data.summary);
                var addedN  = data.added_count || 0;
                var skipped = (data.skipped || []).length;
                var msg = '✓ ' + addedN + ' agregado(s) al carrito';
                if (skipped > 0) msg += ' (' + skipped + ' requieren elegir opciones)';
                addBtn.innerHTML = msg;
                setTimeout(function () {
                    clear();
                    applyMode(false);
                    addBtn.innerHTML = oldHtml;
                    addBtn.disabled = false;
                }, 1800);
            })
            .catch(function () {
                addBtn.innerHTML = oldHtml;
                addBtn.disabled = false;
                alert('No se pudo agregar — recarga e intenta otra vez.');
            });
        });
    })();
    </script>
@endif

{{-- ═══════════════════════ OFERTAS DEL DÍA (solo home, ≥4 ofertas) ═══════════════════════
     Ocultar cuando el visitante ya esta filtrando por ofertas (?on_offer=1) —
     el listado de abajo ya muestra los mismos productos, evitar duplicado. --}}
@if(isset($dailyOffers) && $dailyOffers->count() >= 4 && empty($onOfferOnly))
    <section class="mp-section mp-offers-block" id="mpOffersBlock" aria-label="Ofertas del día">
        <div class="mp-offers-head">
            <div class="mp-offers-head__title-wrap">
                <h2 class="mp-offers-title">🔥 Ofertas del día</h2>
                @php
                    // Conteo defensivo: $dailyOffers podria venir del cache con
                    // tipo Collection generico (no Eloquent) tras la serializacion.
                    // collect() lo normaliza para garantizar el pluck.
                    $offerTenantsCount = collect($dailyOffers)->pluck('hostname_id')->unique()->count();
                @endphp
                <p class="mp-offers-sub">Descuentos vigentes de {{ $offerTenantsCount }} tienda{{ $offerTenantsCount === 1 ? '' : 's' }} verificada{{ $offerTenantsCount === 1 ? '' : 's' }}. Aprovecha mientras duren.</p>
            </div>
            <div class="mp-offers-head__actions">
                <a href="{{ route('marketplace.index', ['on_offer' => 1]) }}" class="mp-offers-cta">Ver todas →</a>
                {{-- Flechas de carrusel (desktop) --}}
                <button type="button" class="mp-offers-nav-btn" data-offers-prev aria-label="Anterior">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="mp-offers-nav-btn" data-offers-next aria-label="Siguiente">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                {{-- Toggle expandir/colapsar la seccion (usuario decide) --}}
                <button type="button" class="mp-offers-collapse-btn" id="mpOffersCollapse" aria-expanded="true" aria-controls="mpOffersBody" title="Colapsar / expandir">
                    <svg class="mp-offers-collapse-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>
        <div class="mp-offers-body" id="mpOffersBody">
            <div class="mp-offers-rail" id="mpOffersRail">
                @foreach($dailyOffers as $offer)
                    <a href="{{ route('marketplace.item', $offer->slug) }}" class="mp-offer-card">
                        <div class="mp-offer-card__img">
                            @if($offer->image_url)
                                <img src="{{ $offer->image_url }}" alt="{{ $offer->title }}" loading="lazy">
                            @else
                                <div class="mp-offer-card__noimg">Sin imagen</div>
                            @endif
                            @if(!empty($offer->discount_pct))
                                <span class="mp-offer-card__pct">-{{ $offer->discount_pct }}%</span>
                            @endif
                            @if(!empty($offer->offer_ends_at))
                                <span class="mp-offer-card__timer" data-ends-at="{{ \Carbon\Carbon::parse($offer->offer_ends_at)->toIso8601String() }}">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span class="mp-offer-card__timer-txt">…</span>
                                </span>
                            @endif
                        </div>
                        <div class="mp-offer-card__body">
                            <h3 class="mp-offer-card__title">{{ $offer->title }}</h3>
                            <div class="mp-offer-card__price-row">
                                <span class="mp-offer-card__price">S/ {{ number_format($offer->display_price, 2) }}</span>
                                @if(!empty($offer->original_price) && $offer->original_price > $offer->display_price)
                                    <span class="mp-offer-card__old">S/ {{ number_format($offer->original_price, 2) }}</span>
                            @endif
                        </div>
                        <div class="mp-offer-card__shop">{{ $offer->seller_display }}</div>
                    </div>
                </a>
            @endforeach
            </div> {{-- /.mp-offers-rail --}}
        </div> {{-- /.mp-offers-body --}}
    </section>

    <style>
        .mp-offers-block { padding: 16px 0 8px; }
        .mp-offers-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:12px; flex-wrap: wrap; }
        .mp-offers-head__title-wrap { min-width: 0; flex: 1; }
        .mp-offers-head__actions { display: inline-flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .mp-offers-title { margin:0; font-size:18px; font-weight:800; color:#0a0e1a; }
        .mp-offers-sub { margin:2px 0 0; font-size:12.5px; color:#6b7280; }
        .mp-offers-cta { font-size:13px; font-weight:700; color:#dc2626; text-decoration:none; white-space:nowrap; }
        .mp-offers-cta:hover { text-decoration:underline; }

        /* Flechas nav del carrusel (desktop). Mobile: ocultas, swipe nativo. */
        .mp-offers-nav-btn {
            width: 32px; height: 32px;
            border-radius: 999px;
            border: 1.5px solid var(--mp-line, #e5e7eb);
            background: #fff;
            color: #0a0e1a;
            cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: border-color .15s, color .15s, background .15s, transform .12s;
        }
        .mp-offers-nav-btn:hover { border-color: #dc2626; color: #dc2626; }
        .mp-offers-nav-btn:active { transform: scale(.94); }
        .mp-offers-nav-btn:disabled { opacity: .35; cursor: not-allowed; }
        @media (max-width: 700px) {
            .mp-offers-nav-btn { display: none; }
        }

        /* Boton collapse/expand */
        .mp-offers-collapse-btn {
            width: 32px; height: 32px;
            border-radius: 999px;
            border: 1.5px solid var(--mp-line, #e5e7eb);
            background: #fff;
            color: #6b7280;
            cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            transition: border-color .15s, color .15s, background .15s;
        }
        .mp-offers-collapse-btn:hover { border-color: #6b7280; color: #0a0e1a; }
        .mp-offers-collapse-icon { transition: transform .25s; }
        .mp-offers-collapse-btn[aria-expanded="false"] .mp-offers-collapse-icon {
            transform: rotate(-90deg);
        }

        /* Body collapsable con animacion */
        .mp-offers-body {
            transition: max-height .35s ease, opacity .25s ease;
            max-height: 480px;
            overflow: hidden;
        }
        .mp-offers-body.is-collapsed {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
        }
        /* Fade gradient en el borde derecho indicando 'mas contenido' */
        .mp-offers-rail-wrap {
            position: relative;
        }
        .mp-offers-rail {
            display:flex; gap:14px;
            overflow-x:auto; scroll-snap-type:x mandatory; scroll-behavior:smooth;
            padding:4px 2px 14px;
            scrollbar-width:thin;
        }
        .mp-offers-rail::-webkit-scrollbar { height:6px; }
        .mp-offers-rail::-webkit-scrollbar-thumb { background:rgba(0,0,0,.1); border-radius:999px; }
        .mp-offer-card {
            flex:0 0 auto; scroll-snap-align:start;
            width:200px;
            background:#fff; border:1px solid #f1f5f9; border-radius:14px;
            text-decoration:none; color:inherit;
            overflow:hidden;
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .mp-offer-card:hover {
            transform: translateY(-3px);
            border-color: rgba(220,38,38,.35);
            box-shadow: 0 10px 22px -12px rgba(220,38,38,.22);
        }
        .mp-offer-card__img { position:relative; aspect-ratio:1/1; background:#f7f9fb; overflow:hidden; }
        .mp-offer-card__img img { width:100%; height:100%; object-fit:cover; transition:transform .35s; }
        .mp-offer-card:hover .mp-offer-card__img img { transform:scale(1.04); }
        .mp-offer-card__noimg { display:flex; align-items:center; justify-content:center; height:100%; color:#9ca3af; font-size:12px; }
        .mp-offer-card__pct {
            position:absolute; top:8px; left:8px;
            background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
            font-size:12px; font-weight:800; letter-spacing:.3px;
            padding:3px 8px; border-radius:999px;
            box-shadow: 0 4px 8px -4px rgba(220,38,38,.45);
        }
        .mp-offer-card__timer {
            position:absolute; bottom:8px; right:8px;
            display:inline-flex; align-items:center;
            background:rgba(15,23,42,.85); color:#fff;
            font-size:11px; font-weight:600;
            padding:3px 8px; border-radius:999px;
            backdrop-filter: blur(4px);
        }
        .mp-offer-card__body { padding:10px 12px 12px; }
        .mp-offer-card__title {
            margin:0 0 6px; font-size:13px; font-weight:600; color:#1f2937;
            line-height:1.3;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
            overflow:hidden;
            min-height: 34px;
        }
        .mp-offer-card__price-row { display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; }
        .mp-offer-card__price { font-size:16px; font-weight:800; color:#dc2626; }
        .mp-offer-card__old { font-size:12px; color:#9ca3af; text-decoration:line-through; }
        .mp-offer-card__shop { margin-top:4px; font-size:11.5px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        @media (max-width: 640px) {
            .mp-offer-card { width: 160px; }
            .mp-offers-title { font-size:16px; }
            /* "Descuentos vigentes de N tiendas... Aprovecha mientras duren"
               es flavor + numero que ya esta implicito en las cards. Recupera
               una linea vertical. */
            .mp-offers-sub { display: none; }
        }
    </style>

    <script>
    // Countdown ligero para los timers de "Termina en". Update cada minuto;
    // si la oferta expira mientras el cliente está en la página, el timer
    // muestra "Expirada" sin recargar. Sin overhead notable: una sola
    // pasada por todos los timers cada 60s.
    (function () {
        var timers = document.querySelectorAll('.mp-offer-card__timer[data-ends-at]');
        if (!timers.length) return;
        function fmt(ms) {
            if (ms <= 0) return 'Expirada';
            var h = Math.floor(ms / 3600000);
            var m = Math.floor((ms % 3600000) / 60000);
            if (h >= 24) {
                var d = Math.floor(h / 24);
                return d + 'd ' + (h % 24) + 'h';
            }
            return h + 'h ' + m + 'm';
        }
        function tick() {
            var now = Date.now();
            timers.forEach(function (t) {
                var ends = new Date(t.getAttribute('data-ends-at')).getTime();
                var span = t.querySelector('.mp-offer-card__timer-txt');
                if (span) span.textContent = fmt(ends - now);
            });
        }
        tick();
        setInterval(tick, 60000);
    })();

    // Carrusel: flechas prev/next + collapse/expand del bloque.
    (function () {
        var rail  = document.getElementById('mpOffersRail');
        var block = document.getElementById('mpOffersBlock');
        var body  = document.getElementById('mpOffersBody');
        if (!rail) return;

        // Flechas de navegacion
        var prevBtn = block.querySelector('[data-offers-prev]');
        var nextBtn = block.querySelector('[data-offers-next]');
        function scrollBy(dir) {
            var card = rail.querySelector('.mp-offer-card');
            var step = card ? card.offsetWidth + 14 : 220;
            rail.scrollBy({ left: dir * step * 2, behavior: 'smooth' });
        }
        function syncNav() {
            if (!prevBtn || !nextBtn) return;
            prevBtn.disabled = rail.scrollLeft <= 4;
            nextBtn.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
        }
        if (prevBtn) prevBtn.addEventListener('click', function () { scrollBy(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { scrollBy(1); });
        rail.addEventListener('scroll', syncNav, { passive: true });
        window.addEventListener('resize', syncNav);
        syncNav();

        // Collapse/expand — persiste en localStorage para que el usuario
        // no tenga que cerrar la seccion en cada visita.
        var collapseBtn = document.getElementById('mpOffersCollapse');
        if (collapseBtn && body) {
            var key = 'mp_offers_collapsed';
            try {
                if (localStorage.getItem(key) === '1') {
                    body.classList.add('is-collapsed');
                    collapseBtn.setAttribute('aria-expanded', 'false');
                }
            } catch (e) {}
            collapseBtn.addEventListener('click', function () {
                var collapsed = body.classList.toggle('is-collapsed');
                collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (e) {}
            });
        }
    })();
    </script>
@endif

{{-- ═══════════════════════ BREADCRUMB (resultados filtrados) ═══════════════════════ --}}
@if($q || $category)
    <nav class="mp-breadcrumb">
        <a href="{{ route('marketplace.index') }}">Marketplace</a>
        @if($category)
            <span class="sep">›</span>
            <span>{{ $category }}</span>
        @endif
        @if($q)
            <span class="sep">›</span>
            <span>Resultados para "{{ $q }}"</span>
        @endif
    </nav>
@endif

{{-- ═══════════════════════ LAYOUT LISTADO ═══════════════════════ --}}
<div class="mp-list-layout" id="productos">

    {{-- Barra horizontal mobile con [Ordenar] [Filtros] juntos.
         Solo visible en mobile (CSS abajo en mp-mobile-topbar). En desktop
         el ordenar vive dentro de .mp-toolbar y los filtros estn en el sidebar.

         El select de ordenar usa los mismos hidden inputs que la versin
         desktop pero hace submit nativo (el browser muestra el picker
         nativo en mobile, mejor UX que un custom dropdown). --}}
    <div class="mp-mobile-topbar">
        <form method="GET" action="{{ route('marketplace.index') }}" class="mp-mobile-topbar__sort">
            @if($q)        <input type="hidden" name="q"        value="{{ $q }}">        @endif
            @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
            @if($priceMin !== null) <input type="hidden" name="price_min" value="{{ $priceMin }}"> @endif
            @if($priceMax !== null) <input type="hidden" name="price_max" value="{{ $priceMax }}"> @endif
            @if($shopSubdomain)        <input type="hidden" name="shop"     value="{{ $shopSubdomain }}"> @endif
            @if(!empty($onOfferOnly))  <input type="hidden" name="on_offer" value="1"> @endif
            @if(!empty($verifiedOnly)) <input type="hidden" name="verified" value="1"> @endif
            @if(!empty($inStockOnly))  <input type="hidden" name="in_stock" value="1"> @endif
            @if(!empty($packsOnly))    <input type="hidden" name="packs"    value="1"> @endif
            <label class="mp-mobile-topbar__btn mp-mobile-topbar__btn--sort">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 6h18M6 12h12M10 18h4"/>
                </svg>
                <select name="sort" onchange="this.form.submit()" aria-label="Ordenar">
                    <option value="relevance" {{ $sort === 'relevance'  ? 'selected' : '' }}>Relevancia</option>
                    <option value="price_asc" {{ $sort === 'price_asc'  ? 'selected' : '' }}>Precio: menor a mayor</option>
                    <option value="price_desc"{{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                    <option value="newest"    {{ $sort === 'newest'     ? 'selected' : '' }}>Ms recientes</option>
                </select>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </label>
        </form>
        <button type="button" class="mp-mobile-topbar__btn mp-mobile-topbar__btn--filters"
                onclick="document.getElementById('mpFilters').classList.add('is-open'); document.body.style.overflow='hidden';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
            </svg>
            Filtros{{ $hasFilters ? ' ' : '' }}
        </button>
    </div>

    <aside class="mp-filters-card" id="mpFilters">
        <div class="mp-filters-header">
            <h3>Filtrar productos</h3>
            @if($hasFilters)
                <a href="{{ route('marketplace.index') }}" class="mp-filters-clear">Limpiar</a>
            @endif
            <button type="button" class="mp-filters-close"
                    onclick="document.getElementById('mpFilters').classList.remove('is-open'); document.body.style.overflow='';"
                    style="display:none" id="mpFiltersClose">×</button>
        </div>

        {{-- Bloque "Categorías" del sidebar removido: mostraba las categorías
             internas del tenant (Macetas, OFERTA/SALE, etc.) que se mezclaban
             con la taxonomía oficial del marketplace de los chips de arriba.
             La taxonomía oficial sigue activa vía /marketplace/c/{slug}. --}}

        @if(!empty($shops) && $shops->count() > 0)
            <div class="mp-filter-group">
                <div class="mp-filter-label mp-filter-label--with-action">
                    <span class="d-flex align-items-center gap-1">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                        Tiendas <small class="text-muted">({{ $shops->count() }})</small>
                    </span>
                </div>
                @php
                    // Para los links del sidebar "Tiendas" descartamos el query
                    // de búsqueda (q): el seller suele teclear el nombre de la
                    // tienda en el buscador y luego filtrar — si preservamos q
                    // queda doble filtro y muestra 0 resultados aunque la tienda
                    // tenga productos. También quitamos 'shop' para que los
                    // links nuevos sobrescriban el actual sin acumular.
                    $shopBaseQs = array_diff_key($baseQs, ['shop' => null, 'q' => null]);
                @endphp

                {{-- Buscador inline (filtra client-side las tiendas visibles).
                     Solo aparece si hay >5 tiendas para no saturar. --}}
                @if($shops->count() > 5)
                    <input type="search"
                           id="mpShopFilterInput"
                           class="mp-shop-search"
                           placeholder="🔍 Buscar tienda…"
                           autocomplete="off">
                @endif

                <ul class="mp-filter-list mp-shop-list">
                    <li>
                        <a href="{{ route('marketplace.index', $shopBaseQs) }}"
                           class="mp-filter-item mp-shop-item {{ empty($shopSubdomain) ? 'is-active' : '' }}">
                            <span class="mp-shop-logo mp-shop-logo--all">🏬</span>
                            <span class="mp-shop-name">Todas las tiendas</span>
                            <span class="mp-filter-count">{{ $shops->sum('products_count') }}</span>
                        </a>
                    </li>
                    @foreach($shops as $shop)
                        <li data-shop-name="{{ mb_strtolower($shop->name) }}">
                            <a href="{{ route('marketplace.index', array_merge($shopBaseQs, ['shop' => $shop->subdomain])) }}"
                               class="mp-filter-item mp-shop-item {{ $shopSubdomain === $shop->subdomain ? 'is-active' : '' }}"
                               title="Ver productos de {{ $shop->name }}">
                                @if($shop->logo_url)
                                    <img class="mp-shop-logo" src="{{ $shop->logo_url }}" alt="" loading="lazy">
                                @else
                                    <span class="mp-shop-logo mp-shop-logo--initial">{{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}</span>
                                @endif
                                <span class="mp-shop-name">
                                    {{ $shop->name }}
                                    @if($shop->verified)
                                        <span class="mp-shop-verified" title="Tienda verificada por ebaemy">✓</span>
                                    @endif
                                </span>
                                <span class="mp-filter-count">{{ $shop->products_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('marketplace.index', array_merge($shopBaseQs, ['view' => 'shops'])) }}"
                   class="mp-shop-see-all">Ver todas las tiendas →</a>
            </div>
        @endif

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Precio (S/)
            </div>
            <form method="GET" action="{{ route('marketplace.index') }}">
                @if($q)             <input type="hidden" name="q"        value="{{ $q }}">             @endif
                @if($category)      <input type="hidden" name="category" value="{{ $category }}">      @endif
                @if($sort && $sort !== 'relevance') <input type="hidden" name="sort" value="{{ $sort }}"> @endif
                @if($shopSubdomain) <input type="hidden" name="shop"     value="{{ $shopSubdomain }}"> @endif
                {{-- Preservar filtros booleanos al aplicar precio --}}
                @if(!empty($onOfferOnly))  <input type="hidden" name="on_offer" value="1"> @endif
                @if(!empty($verifiedOnly)) <input type="hidden" name="verified" value="1"> @endif
                @if(!empty($inStockOnly))  <input type="hidden" name="in_stock" value="1"> @endif
                @if(!empty($packsOnly))    <input type="hidden" name="packs"    value="1"> @endif
                <div class="mp-price-range">
                    <input type="number" name="price_min" min="0" step="0.01" placeholder="Desde" value="{{ $priceMin !== null ? $priceMin : '' }}">
                    <span class="mp-price-range-sep">—</span>
                    <input type="number" name="price_max" min="0" step="0.01" placeholder="Hasta" value="{{ $priceMax !== null ? $priceMax : '' }}">
                </div>
                <button type="submit" class="mp-filter-apply">Aplicar</button>
            </form>
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 12V2H4v10l8 10 8-10z"/></svg>
                Ofertas
            </div>
            <a href="{{ route('marketplace.index', $toggleQs('on_offer')) }}"
               class="mp-filter-checkbox {{ !empty($onOfferOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($onOfferOnly) ? 'checked' : '' }} onclick="return false">
                Con descuento
            </a>
            <a href="{{ route('marketplace.index', $toggleQs('packs')) }}"
               class="mp-filter-checkbox {{ !empty($packsOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($packsOnly) ? 'checked' : '' }} onclick="return false">
                📦 Solo packs / combos
            </a>
            {{-- 'Envío gratis' removido hasta que la feature este implementada.
                 Lo dejamos comentado para reactivar cuando este lista. --}}
        </div>

        <div class="mp-filter-group">
            <div class="mp-filter-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                Confianza
            </div>
            <a href="{{ route('marketplace.index', $toggleQs('verified')) }}"
               class="mp-filter-checkbox {{ !empty($verifiedOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($verifiedOnly) ? 'checked' : '' }} onclick="return false">
                Solo tiendas verificadas
            </a>
            <a href="{{ route('marketplace.index', $toggleQs('in_stock')) }}"
               class="mp-filter-checkbox {{ !empty($inStockOnly) ? 'is-active' : '' }}">
                <input type="checkbox" {{ !empty($inStockOnly) ? 'checked' : '' }} onclick="return false">
                Con stock disponible
            </a>
        </div>
    </aside>

    <div class="mp-main-col">
        <div class="mp-toolbar">
            <div>
                <div class="mp-toolbar-count {{ $isHome ? 'mp-toolbar-count--home' : '' }}">
                    <strong>{{ $listings->total() }}</strong>
                    producto{{ $listings->total() === 1 ? '' : 's' }}
                    @if($q) para "{{ $q }}"@endif
                </div>
                @if($isHome && $listings->total() > 0)
                    <div class="mp-toolbar-subtitle">🔥 Productos destacados de nuestro marketplace</div>
                @endif
            </div>
            <form method="GET" action="{{ route('marketplace.index') }}" style="margin:0">
                @if($q)        <input type="hidden" name="q"        value="{{ $q }}">        @endif
                @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
                @if($priceMin !== null) <input type="hidden" name="price_min" value="{{ $priceMin }}"> @endif
                @if($priceMax !== null) <input type="hidden" name="price_max" value="{{ $priceMax }}"> @endif
                @if($shopSubdomain)        <input type="hidden" name="shop"     value="{{ $shopSubdomain }}"> @endif
                @if(!empty($onOfferOnly))  <input type="hidden" name="on_offer" value="1"> @endif
                @if(!empty($verifiedOnly)) <input type="hidden" name="verified" value="1"> @endif
                @if(!empty($inStockOnly))  <input type="hidden" name="in_stock" value="1"> @endif
                @if(!empty($packsOnly))    <input type="hidden" name="packs"    value="1"> @endif
                <select name="sort" class="mp-sort-dropdown" onchange="this.form.submit()" aria-label="Ordenar">
                    <option value="relevance" {{ $sort === 'relevance'  ? 'selected' : '' }}>Relevancia</option>
                    <option value="price_asc" {{ $sort === 'price_asc'  ? 'selected' : '' }}>Precio: menor a mayor</option>
                    <option value="price_desc"{{ $sort === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                    <option value="newest"    {{ $sort === 'newest'     ? 'selected' : '' }}>Más recientes</option>
                </select>
            </form>
        </div>

        {{-- ── Pills de filtros activos ────────────────────────────────────
             Muestra arriba del grid TODO lo aplicado. Cada pill tiene su X
             que quita SOLO ese filtro; "Limpiar todo" reset completo.
             Mobile-first: scroll horizontal con fade gradient hint. --}}
        @php
            $activeFilters = [];
            $currentParams = array_filter([
                'q'                    => $q,
                'category'             => $category,
                'official_category_id' => $officialCatId,
                'price_min'            => $priceMin,
                'price_max'            => $priceMax,
                'shop'                 => $shopSubdomain,
                'on_offer'             => $onOfferOnly ? 1 : null,
                'verified'             => $verifiedOnly ? 1 : null,
                'in_stock'             => $inStockOnly ? 1 : null,
                'packs'                => $packsOnly ? 1 : null,
                'sort'                 => ($sort && $sort !== 'relevance') ? $sort : null,
            ], fn($v) => $v !== null && $v !== '' && $v !== false);

            $removeUrl = function (string ...$keys) use ($currentParams) {
                $kept = $currentParams;
                foreach ($keys as $k) { unset($kept[$k]); }
                return route('marketplace.index', $kept);
            };

            if ($q) {
                $activeFilters[] = ['label' => '🔍 "' . $q . '"', 'url' => $removeUrl('q')];
            }
            if ($shopSubdomain) {
                $shopName = optional($shops->firstWhere('subdomain', $shopSubdomain))->name ?? $shopSubdomain;
                $activeFilters[] = ['label' => '🏪 ' . $shopName, 'url' => $removeUrl('shop')];
            }
            if ($officialCatId) {
                $catName = optional($officialRoots->firstWhere('id', $officialCatId))->name ?? 'Categoría';
                $activeFilters[] = ['label' => '🏷️ ' . $catName, 'url' => $removeUrl('official_category_id')];
            }
            if ($category) {
                $activeFilters[] = ['label' => '🏷️ ' . $category, 'url' => $removeUrl('category')];
            }
            if ($priceMin !== null || $priceMax !== null) {
                $priceLabel = '💰 ';
                if ($priceMin !== null && $priceMax !== null) {
                    $priceLabel .= 'S/ ' . (int)$priceMin . ' - S/ ' . (int)$priceMax;
                } elseif ($priceMin !== null) {
                    $priceLabel .= 'Desde S/ ' . (int)$priceMin;
                } else {
                    $priceLabel .= 'Hasta S/ ' . (int)$priceMax;
                }
                $activeFilters[] = ['label' => $priceLabel, 'url' => $removeUrl('price_min', 'price_max')];
            }
            if ($onOfferOnly)  $activeFilters[] = ['label' => '⚡ Oferta',    'url' => $removeUrl('on_offer')];
            if ($verifiedOnly) $activeFilters[] = ['label' => '✓ Verificada', 'url' => $removeUrl('verified')];
            if ($inStockOnly)  $activeFilters[] = ['label' => '📦 Con stock', 'url' => $removeUrl('in_stock')];
            if ($packsOnly)    $activeFilters[] = ['label' => '🎁 Pack',      'url' => $removeUrl('packs')];
            if ($sort && $sort !== 'relevance') {
                $sortLabels = ['price_asc' => 'Precio ↑', 'price_desc' => 'Precio ↓', 'newest' => 'Más recientes'];
                $activeFilters[] = ['label' => '↕ ' . ($sortLabels[$sort] ?? $sort), 'url' => $removeUrl('sort')];
            }
        @endphp

        @if(!empty($activeFilters))
            <div class="mp-active-filters" role="region" aria-label="Filtros activos">
                <span class="mp-active-filters__label">Filtros:</span>
                <div class="mp-active-filters__pills">
                    @foreach($activeFilters as $f)
                        <a href="{{ $f['url'] }}" class="mp-pill" title="Quitar este filtro">
                            <span class="mp-pill__text">{{ $f['label'] }}</span>
                            <svg class="mp-pill__x" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                <line x1="6" y1="6" x2="18" y2="18"/>
                                <line x1="6" y1="18" x2="18" y2="6"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
                @if(count($activeFilters) >= 2)
                    <a href="{{ route('marketplace.index') }}" class="mp-active-filters__clear">Limpiar todo</a>
                @endif
            </div>
        @endif

        @if($listings->isEmpty())
            <div class="mp-empty">
                <div class="mp-empty-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </div>
                <h3>
                    No encontramos resultados
                    @if($q) para "{{ $q }}" @endif
                </h3>
                <p>
                    Sugerencias:
                </p>
                <ul style="margin:8px 0 16px;padding-left:20px;font-size:13.5px;color:#4b5563;line-height:1.7;text-align:left;display:inline-block">
                    <li>Verifica que las palabras estén bien escritas</li>
                    <li>Usa términos más generales (ej. "planta" en lugar de "ficus lyrata 200cm")</li>
                    <li>Prueba sin filtros: <a href="{{ route('marketplace.index', ['q' => $q]) }}" style="color:var(--mp-primary-dark);font-weight:600">buscar "{{ $q ?: 'sin filtros' }}" sin filtros</a></li>
                </ul>
                <p style="margin-top:8px">
                    O explora <a href="{{ route('marketplace.index') }}" style="color:var(--mp-primary-dark);font-weight:600">todo el marketplace</a>
                    @if(isset($officialRoots) && $officialRoots->count() > 0)
                        — categorías populares:
                        @foreach($officialRoots->take(4) as $root)
                            <a href="{{ route('marketplace.category_official', ['fullSlug' => $root->full_slug]) }}"
                               style="display:inline-block;margin:4px 4px 0 0;padding:4px 10px;background:#f3f4f6;border-radius:999px;font-size:12px;color:#374151;text-decoration:none">{{ $root->icon ?: '' }} {{ $root->name }}</a>
                        @endforeach
                    @endif
                </p>
            </div>
        @else
            <div class="mp-grid">
                @foreach($listings as $idx => $listing)
                    @php
                        // Badges del loop — solo en home en la primera página.
                        $showTopBadge  = $isHome && $idx === 0 && $listings->currentPage() === 1;
                        $showBestBadge = $isHome && $idx === 1 && $listings->currentPage() === 1;
                        $showNewBadge  = !$showTopBadge && !$showBestBadge
                                         && isset($listing->created_at)
                                         && \Carbon\Carbon::parse($listing->created_at)->gt(now()->subDays(14));
                    @endphp
                    @include('marketplace.partials.listing-card', [
                        'listing'       => $listing,
                        'showTopBadge'  => $showTopBadge,
                        'showBestBadge' => $showBestBadge,
                        'showNewBadge'  => $showNewBadge,
                    ])
                @endforeach
            </div>

            <div class="mp-pag">
                {{ $listings->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════ TIENDAS DESTACADAS (después de productos) ═══════════════════════ --}}
@if($featuredShops->count() >= 3)
    <section class="mp-section">
        <div class="mp-section-head">
            <div>
                <h2 class="mp-section-title">
                    <span class="mp-section-title-emoji">🏪</span>
                    Tiendas destacadas
                </h2>
                <p class="mp-section-subtitle">Empresas peruanas reales con RUC verificado vendiendo en ebaemy.</p>
            </div>
        </div>
        <div class="mp-shops-row">
            @foreach($featuredShops as $shop)
                <a href="https://{{ $shop->tenant_fqdn }}" target="_blank" rel="noopener" class="mp-shop-card">
                    <div class="mp-shop-card-logo">
                        @if(!empty($shop->tenant_logo_url))
                            <img src="{{ $shop->tenant_logo_url }}" alt="{{ $shop->seller_display }}" loading="lazy">
                        @else
                            <span class="mp-shop-card-logo-fallback">{{ mb_strtoupper(mb_substr($shop->seller_display, 0, 2)) }}</span>
                        @endif
                    </div>
                    <h3 class="mp-shop-card-name">{{ \Illuminate\Support\Str::limit($shop->seller_display, 34) }}</h3>
                    @if(!empty($shop->tenant_verified))
                        <span class="mp-shop-card-meta">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                            Verificada
                        </span>
                    @endif
                    <span class="mp-shop-card-cta">
                        Ver tienda
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- Estilos de cards/grid/paginador del marketplace viven en
     marketplace.partials.listing-card-styles, incluido por el layout
     para que las 4 vistas (home, categoría oficial, categoría legacy,
     tienda) compartan exactamente el mismo CSS. --}}

<style>
    /* ───────────── Barra superior mobile [Ordenar][Filtros] ─────────────
       Solo visible en mobile (<=768px). En desktop el ordenar vive en
       .mp-toolbar (lado derecho) y los filtros estn en el sidebar.
    */
    .mp-mobile-topbar { display: none; }
    @media (max-width: 768px) {
        .mp-mobile-topbar {
            display: flex;
            gap: 8px;
            margin: 0 0 12px;
            padding: 0;
        }
        .mp-mobile-topbar__btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 42px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #1f2937;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease;
        }
        .mp-mobile-topbar__btn:hover,
        .mp-mobile-topbar__btn:focus {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .mp-mobile-topbar__btn--sort {
            position: relative;
            padding: 0 10px 0 14px;
        }
        /* El <select> nativo se superpone invisible sobre el label para
           que en mobile abra el picker nativo (mejor UX que custom dropdown). */
        .mp-mobile-topbar__btn--sort select {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            background: transparent;
            color: transparent;
            font-size: 13.5px;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }
        .mp-mobile-topbar__btn--sort select option { color: #1f2937; }
        .mp-mobile-topbar__sort { flex: 1; margin: 0; }
        /* En mobile, ocultar el sort de .mp-toolbar (duplicara con el de arriba). */
        .mp-toolbar > form { display: none !important; }
    }

    /* ───────────── Sticky bottom bar móvil con carrito + filtros ───────────── */
    .mp-mobile-actionbar {
        display: none;
        position: fixed; left: 0; right: 0; bottom: 0;
        z-index: 60;
        background: rgba(255,255,255,.97);
        backdrop-filter: blur(8px);
        border-top: 1px solid #e5e7eb;
        padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
        gap: 8px;
        box-shadow: 0 -6px 20px -10px rgba(0,0,0,.12);
    }
    .mp-mobile-actionbar .mp-mab-btn {
        flex: 1;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 11px 12px;
        border-radius: 999px;
        font-size: 13px; font-weight: 700;
        text-decoration: none;
        border: 1px solid #e5e7eb;
        background: #fff; color: #1f2937;
    }
    .mp-mobile-actionbar .mp-mab-btn--primary {
        background: linear-gradient(135deg, #0f8a82, #0a6f68);
        color: #fff; border-color: transparent;
    }
    /* Botn full-width cuando es el nico del sticky (Carrito solo) */
    .mp-mobile-actionbar .mp-mab-btn--full { flex: 1 1 100%; }

    /* ───────────── Bottom sheet del detalle del producto (mobile) ─────────────
       Solo activo en mobile (<=768px). En desktop nunca se muestra porque
       el JS de intercept solo dispara cuando matchMedia es mobile. */
    .mp-sheet {
        position: fixed;
        inset: 0;
        z-index: 200;
        display: none;
        pointer-events: none;
    }
    .mp-sheet.is-open {
        display: block;
        pointer-events: auto;
    }
    .mp-sheet__overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        opacity: 0;
        transition: opacity .25s ease;
    }
    .mp-sheet.is-open .mp-sheet__overlay { opacity: 1; }
    .mp-sheet__panel {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 92dvh;
        background: #fff;
        border-radius: 18px 18px 0 0;
        box-shadow: 0 -10px 40px -10px rgba(0,0,0,.35);
        transform: translateY(100%);
        transition: transform .3s cubic-bezier(.32,.72,0,1);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .mp-sheet.is-open .mp-sheet__panel { transform: translateY(0); }
    .mp-sheet__grabber {
        align-self: center;
        width: 42px;
        height: 5px;
        background: #cbd5e1;
        border-radius: 999px;
        margin: 8px 0 4px;
        cursor: pointer;
        flex-shrink: 0;
    }
    .mp-sheet__topbar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 12px 10px;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
    }
    .mp-sheet__close,
    .mp-sheet__expand {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #f1f5f9;
        border: 0;
        color: #475569;
        cursor: pointer;
        text-decoration: none;
        flex-shrink: 0;
    }
    .mp-sheet__close:active,
    .mp-sheet__expand:active { background: #e2e8f0; }
    .mp-sheet__title {
        flex: 1;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mp-sheet__body {
        flex: 1;
        position: relative;
        overflow: hidden;
    }
    .mp-sheet__frame {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
    }
    .mp-sheet__loader {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        background: #fff;
        color: #64748b;
        z-index: 2;
    }
    .mp-sheet__loader.is-hidden { display: none; }
    .mp-sheet__spinner {
        width: 32px;
        height: 32px;
        border: 3px solid #e2e8f0;
        border-top-color: #0f8a82;
        border-radius: 50%;
        animation: mpSheetSpin 0.8s linear infinite;
    }
    .mp-sheet__loader-text { font-size: 13px; font-weight: 500; }
    @keyframes mpSheetSpin { to { transform: rotate(360deg); } }
    /* Body lock cuando el sheet est abierto (evita scroll de fondo) */
    body.mp-sheet-open { overflow: hidden; }
    /* En desktop nunca se abre el sheet, pero por si acaso lo escondemos */
    @media (min-width: 769px) {
        .mp-sheet { display: none !important; }
    }
    @media (max-width: 768px) {
        .mp-mobile-actionbar { display: flex; }
        body { padding-bottom: 64px; } /* deja espacio para que el bar no tape contenido */
    }

    /* ───────────── Filtros como modal en móvil (overlay) ───────────── */
    @media (max-width: 768px) {
        .mp-filters-card.is-open {
            position: fixed !important;
            inset: 0;
            z-index: 70;
            background: #fff;
            overflow-y: auto;
            border-radius: 0;
            padding: 16px;
            margin: 0;
        }
        .mp-filters-card { display: none; }
        .mp-filters-card.is-open { display: block; }
    }
</style>

{{-- Sticky bottom mobile: solo Carrito.
     El botn Filtrar y el dropdown Ordenar viven ahora en
     mp-mobile-topbar arriba, lo que evita redundancia y libera espacio
     visual. --}}
<div class="mp-mobile-actionbar" aria-hidden="false">
    <a href="{{ route('marketplace.cart') }}" class="mp-mab-btn mp-mab-btn--primary mp-mab-btn--full">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Ver carrito
    </a>
</div>

{{-- ═══════════ Bottom sheet del detalle del producto (solo mobile) ═══════════
     En vez de navegar a /marketplace/item/{slug}, en mobile interceptamos
     el click en cada .mp-card y abrimos el detalle como bottom sheet
     deslizable. Se carga la misma URL del detalle dentro de un iframe
     con ?embed=1, lo que oculta header/footer del detalle dejando solo
     el contenido. En desktop el click sigue navegando como antes.

     Patrn: Instagram Shopping / TikTok Shop / Shopee.
--}}
<div id="mpProductSheet" class="mp-sheet" aria-hidden="true" role="dialog">
    <div class="mp-sheet__overlay" data-mpsheet-close></div>
    <div class="mp-sheet__panel" role="document">
        <div class="mp-sheet__grabber" data-mpsheet-close aria-label="Cerrar"></div>
        <div class="mp-sheet__topbar">
            <button type="button" class="mp-sheet__close" data-mpsheet-close aria-label="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="mp-sheet__title" id="mpSheetTitle">Producto</div>
            <a href="#" class="mp-sheet__expand" id="mpSheetExpand" target="_blank" rel="noopener" aria-label="Abrir en pgina completa">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>
        </div>
        <div class="mp-sheet__body">
            {{-- Loader mientras carga el iframe --}}
            <div class="mp-sheet__loader" id="mpSheetLoader">
                <div class="mp-sheet__spinner"></div>
                <div class="mp-sheet__loader-text">Cargando producto</div>
            </div>
            <iframe id="mpSheetFrame" class="mp-sheet__frame" src="about:blank" title="Detalle del producto" loading="lazy"></iframe>
        </div>
    </div>
</div>

<script>
if (window.matchMedia('(max-width: 899px)').matches) {
    var btn = document.getElementById('mpFiltersClose');
    if (btn) btn.style.display = 'inline-block';
}

{{-- El JS de hover de dots y click en shop-link ahora vive en
     marketplace.partials.listing-card-script (incluido por layout.blade.php),
     compartido con todas las vistas que renderizan cards. --}}

// ════════════ Bottom sheet del detalle del producto (mobile) ════════════
// Patrn: click en una .mp-card en mobile  abre el detalle como sheet
// deslizable con iframe en lugar de navegar. En desktop, el <a> navega
// normal (no interceptamos). Permite seguir browseando el listado.
(function () {
    var sheet  = document.getElementById('mpProductSheet');
    if (!sheet) return;

    var frame  = document.getElementById('mpSheetFrame');
    var loader = document.getElementById('mpSheetLoader');
    var title  = document.getElementById('mpSheetTitle');
    var expand = document.getElementById('mpSheetExpand');
    var mqMobile = window.matchMedia('(max-width: 768px)');

    function openSheet(url, productName) {
        if (!url) return;
        title.textContent = productName || 'Producto';
        expand.href = url; // botn "abrir en pgina completa" usa la URL real
        loader.classList.remove('is-hidden');
        // Cargar el detalle con ?embed=1 para que el layout oculte header/footer
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        frame.src = url + sep + 'embed=1';
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mp-sheet-open');
        // Push state para que el botn "atrs" del browser cierre el sheet
        try { history.pushState({ mpSheet: true }, '', url); } catch (e) {}
    }

    function closeSheet(skipHistory) {
        if (!sheet.classList.contains('is-open')) return;
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mp-sheet-open');
        // Limpiar el iframe despus de la transicin (300ms) para liberar memoria
        setTimeout(function () { frame.src = 'about:blank'; }, 320);
        if (!skipHistory && history.state && history.state.mpSheet) {
            try { history.back(); } catch (e) {}
        }
    }

    // Botones de cierre (X, grabber, overlay) — cualquier elemento con data-mpsheet-close
    sheet.addEventListener('click', function (e) {
        if (e.target.closest('[data-mpsheet-close]')) closeSheet();
    });

    // Botn back del browser cierra el sheet
    window.addEventListener('popstate', function () { closeSheet(true); });

    // ESC cierra el sheet (en mobile no tiene teclado, pero por si acaso)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSheet();
    });

    // Loader: cuando el iframe termina de cargar, ocultarlo
    frame.addEventListener('load', function () {
        if (frame.src && frame.src !== 'about:blank') {
            loader.classList.add('is-hidden');
        }
    });

    // Interceptar click en las cards de productos. Las cards son <a class="mp-card">
    // dentro de .mp-grid. Solo en mobile.
    document.addEventListener('click', function (e) {
        if (!mqMobile.matches) return; // desktop: navegar normal
        var card = e.target.closest('a.mp-card');
        if (!card) return;
        // No interceptar si el click fue sobre un dot/thumb/link interno
        // que tiene su propio handler (color dots, shop link, etc).
        if (e.target.closest('.js-shop-link, .mp-card-dot, .mp-card-thumb, button')) return;
        e.preventDefault();
        var href = card.getAttribute('href');
        var name = (card.querySelector('.mp-card-title') || {}).textContent || '';
        openSheet(href, name.trim());
    }, true); // capture: para que se ejecute antes que el navigate del <a>
})();
</script>
@push('styles')
<style>
/* ── Filtro Tiendas mejorado (sidebar marketplace) ── */
.mp-filter-label--with-action { display:flex; justify-content:space-between; align-items:center; }
.mp-shop-search {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--mp-line);
    border-radius: 6px;
    font-size: 12.5px;
    margin-bottom: 8px;
    outline: 0;
}
.mp-shop-search:focus { border-color: var(--mp-primary); }
.mp-shop-list { max-height: 320px; overflow-y: auto; }
.mp-shop-list::-webkit-scrollbar { width: 4px; }
.mp-shop-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
.mp-shop-item {
    display: flex !important;
    align-items: center;
    gap: 8px;
    padding: 5px 6px !important;
    border-radius: 6px;
    transition: background .12s;
}
.mp-shop-item:hover { background: rgba(15,138,130,.05); }
.mp-shop-item.is-active { background: rgba(15,138,130,.10); font-weight: 600; }
.mp-shop-logo {
    width: 24px; height: 24px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
    color: var(--mp-muted);
    overflow: hidden;
}
.mp-shop-logo--all { background: linear-gradient(135deg, #0f8a82, #0a6f68); color: #fff; font-size: 12px; }
.mp-shop-logo--initial { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; }
.mp-shop-name {
    flex: 1; font-size: 12.5px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mp-shop-verified {
    display: inline-block;
    margin-left: 4px;
    width: 14px; height: 14px;
    background: #16a34a;
    color: #fff;
    border-radius: 50%;
    font-size: 9px; font-weight: 700;
    text-align: center; line-height: 14px;
    vertical-align: middle;
}
.mp-shop-see-all {
    display: block;
    margin-top: 6px;
    padding: 6px;
    text-align: center;
    font-size: 11.5px;
    color: var(--mp-primary-dark);
    font-weight: 600;
    text-decoration: none;
    border-top: 1px dashed var(--mp-line-soft);
}
.mp-shop-see-all:hover { background: rgba(15,138,130,.04); }
.mp-shop-empty { padding: 8px; font-size: 11.5px; color: var(--mp-muted); text-align: center; }

/* ═════════════════════ Pills de filtros activos ═════════════════════
   Aparecen sobre el grid cuando hay al menos 1 filtro aplicado. Click
   en la X de un pill quita SOLO ese filtro. "Limpiar todo" reset.
   Mobile: scroll horizontal con fade gradient hint. */
.mp-active-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #f0fdfa 0%, #fff 100%);
    border: 1px solid #d1fae5;
    border-radius: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.mp-active-filters__label {
    font-size: 12.5px;
    font-weight: 700;
    color: #0c6b65;
    text-transform: uppercase;
    letter-spacing: .03em;
    flex-shrink: 0;
}
.mp-active-filters__pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
}
.mp-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    background: #fff;
    border: 1.5px solid var(--mp-primary, #0f8a82);
    color: var(--mp-primary-dark, #0c6b65);
    border-radius: 999px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.2;
    transition: background .12s, border-color .12s, color .12s;
    white-space: nowrap;
    max-width: 100%;
}
.mp-pill:hover {
    background: #fee2e2;
    border-color: #dc2626;
    color: #b91c1c;
}
.mp-pill__text {
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}
.mp-pill__x {
    flex-shrink: 0;
    opacity: .7;
}
.mp-pill:hover .mp-pill__x { opacity: 1; }
.mp-active-filters__clear {
    margin-left: auto;
    font-size: 13px;
    color: #dc2626;
    text-decoration: none;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    flex-shrink: 0;
}
.mp-active-filters__clear:hover {
    background: #fee2e2;
    color: #b91c1c;
}

@media (max-width: 768px) {
    .mp-active-filters {
        padding: 8px 12px;
        gap: 8px;
    }
    .mp-active-filters__label {
        width: 100%;
        margin-bottom: 2px;
    }
    .mp-active-filters__pills {
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: thin;
        -webkit-mask-image: linear-gradient(to right, #000 92%, transparent);
                mask-image: linear-gradient(to right, #000 92%, transparent);
        padding-bottom: 4px;
        margin-right: -12px;
        padding-right: 12px;
    }
    .mp-active-filters__pills::-webkit-scrollbar { height: 3px; }
    .mp-active-filters__pills::-webkit-scrollbar-thumb {
        background: var(--mp-line, #e5e7eb);
        border-radius: 999px;
    }
    .mp-pill {
        padding: 7px 12px;
        font-size: 12.5px;
        flex-shrink: 0;
    }
    .mp-pill__text { max-width: 140px; }
    .mp-active-filters__clear {
        margin-left: 0;
        width: 100%;
        text-align: center;
        padding: 6px;
        background: #fff;
        border: 1.5px dashed #dc2626;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function(){
    const input = document.getElementById('mpShopFilterInput');
    if (!input) return;
    const list = input.parentElement.querySelector('.mp-shop-list');
    if (!list) return;
    const items = list.querySelectorAll('li[data-shop-name]');

    input.addEventListener('input', () => {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;
        items.forEach(li => {
            const name = li.dataset.shopName || '';
            const match = !q || name.includes(q);
            li.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        // Mensaje "sin resultados" si no quedó ninguno (solo "Todas" no cuenta)
        const empty = list.querySelector('.mp-shop-empty');
        if (visible === 0 && q) {
            if (!empty) {
                const el = document.createElement('li');
                el.className = 'mp-shop-empty';
                el.textContent = 'Sin coincidencias';
                list.appendChild(el);
            }
        } else if (empty) {
            empty.remove();
        }
    });
})();
</script>
@endpush

@include('marketplace.partials.recently-viewed', ['recentlyViewed' => $recentlyViewed ?? collect()])

@endsection
