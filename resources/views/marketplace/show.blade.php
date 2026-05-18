@extends('marketplace.layout')

@php
    // Pre-calculamos todas las expresiones complejas. Blade's @json() parsea
    // argumentos con explode(',') ingenuamente y se rompe cuando hay comas
    // dentro de route(), Str::limit(), ternarios, etc. — genera PHP inválido
    // con ParseError. Usamos variables planas y json_encode() directo.
    $seoTitle       = $listing->title . ' — ' . $listing->seller_display . ' | Marketplace ebaemy';
    $seoDescription = \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 155);
    $seoKeywords    = $listing->title . ', ' . ($listing->category_name ?? '') . ', ' . $listing->seller_display . ', marketplace ebaemy';
    $seoImage       = $listing->image_url ?: asset('logo/logo.png');
    $canonical      = route('marketplace.item', $listing->slug);
    $ldDescription  = \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 500);
    $ldUrl          = route('marketplace.item', $listing->slug);
    $ldPrice        = number_format($listing->display_price, 2, '.', '');
    $ldAvailability = $listing->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $ldSellerUrl    = 'https://' . $listing->tenant_fqdn;
    $bcIndexUrl     = route('marketplace.index');
    // Prefiere URL oficial (Fase D). Fallback a slug legacy por category_name.
    $bcCategoryUrl  = $officialCategoryUrl
                    ?? ($listing->category_name ? route('marketplace.category', \Illuminate\Support\Str::slug($listing->category_name)) : null);

    $product = [
        '@context'    => 'https://schema.org/',
        '@type'       => 'Product',
        'name'        => $listing->title,
        'image'       => $seoImage,
        'description' => $ldDescription,
        'offers'      => [
            '@type'         => 'Offer',
            'url'           => $ldUrl,
            'priceCurrency' => 'PEN',
            'price'         => $ldPrice,
            'availability'  => $ldAvailability,
            'seller'        => [
                '@type' => 'Organization',
                'name'  => $listing->seller_display,
                'url'   => $ldSellerUrl,
            ],
        ],
    ];
    if ($listing->brand_name)   $product['brand'] = ['@type' => 'Brand', 'name' => $listing->brand_name];
    if ($listing->internal_id)  $product['sku']   = $listing->internal_id;
    if (($listing->rating_count ?? 0) > 0) {
        $product['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format((float) $listing->avg_rating, 1),
            'reviewCount' => (int) $listing->rating_count,
            'bestRating'  => '5',
            'worstRating' => '1',
        ];
    }
    // Emit individual Review items (hasta 10) — Google los usa para enriquecer
    // el snippet con citas textuales y estrellas individuales en SERP.
    if (isset($reviews) && $reviews->isNotEmpty()) {
        $product['review'] = $reviews->take(10)->map(function ($r) use ($listing) {
            $rev = [
                '@type'         => 'Review',
                'reviewRating'  => [
                    '@type'       => 'Rating',
                    'ratingValue' => (string) $r->rating,
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ],
                'author'        => [
                    '@type' => 'Person',
                    'name'  => $r->customer_name ?: 'Cliente verificado',
                ],
                'datePublished' => optional($r->created_at)->toIso8601String(),
            ];
            if ($r->comment) $rev['reviewBody'] = mb_substr(strip_tags($r->comment), 0, 500);
            return $rev;
        })->values()->all();
    }

    $breadcrumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => $bcIndexUrl],
    ];
    if (!empty($officialBreadcrumb) && $officialBreadcrumb->count()) {
        $pos = 2;
        foreach ($officialBreadcrumb as $node) {
            $breadcrumbItems[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $node->name,
                'item'     => url('/marketplace/c/' . $node->full_slug),
            ];
        }
        $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $listing->title];
    } elseif ($listing->category_name) {
        $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $listing->category_name, 'item' => $bcCategoryUrl];
        $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $listing->title];
    } else {
        $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $listing->title];
    }
    $breadcrumb = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbItems];
@endphp

@section('title', $seoTitle)
@section('description', $seoDescription)
@section('keywords', $seoKeywords)
@section('og_title', $listing->title)
@section('og_description', $seoDescription)
@section('og_image', $seoImage)
@section('og_type', 'product')
@section('canonical', $canonical)

@push('styles')
<script type="application/ld+json">
{!! json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')

{{-- Breadcrumb --}}
<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    @if(!empty($officialBreadcrumb) && $officialBreadcrumb->count())
        @foreach($officialBreadcrumb as $node)
            <span class="sep">›</span>
            <a href="{{ url('/marketplace/c/' . $node->full_slug) }}">{{ $node->name }}</a>
        @endforeach
    @elseif($listing->category_name)
        <span class="sep">›</span>
        <a href="{{ route('marketplace.category', \Illuminate\Support\Str::slug($listing->category_name)) }}">{{ $listing->category_name }}</a>
    @endif
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">{{ \Illuminate\Support\Str::limit($listing->title, 60) }}</span>
</nav>

<article class="mp-detail">

    {{-- ═══════════════════════ GALERÍA ═══════════════════════ --}}
    <div class="mp-gallery">
        <div class="mp-gallery-main">
            @if($listing->image_url)
                <img id="mpGalleryMain" src="{{ $listing->image_url }}" alt="{{ $listing->title }}">
            @else
                <div style="display:flex;height:100%;align-items:center;justify-content:center;color:var(--mp-muted);font-size:14px;font-weight:500">Sin imagen disponible</div>
            @endif
        </div>

        {{-- Galería múltiple: usa $listing->gallery_image_urls cuando el item
             tiene imágenes adicionales sincronizadas desde item_images. Si no
             hay galería, muestra solo la principal. El JS de líneas ~636
             enlaza click en thumb → cambia mpGalleryMain. --}}
        @php
            $galleryUrls = is_array($listing->gallery_image_urls) ? $listing->gallery_image_urls : [];
            // Si no hay galería sincronizada o solo trae la principal, fallback al thumb único
            $hasGallery = count($galleryUrls) > 1;
        @endphp
        @if($hasGallery)
            <div class="mp-gallery-thumbs">
                @foreach($galleryUrls as $idx => $thumbUrl)
                    <button type="button"
                            class="mp-gallery-thumb {{ $idx === 0 ? 'is-active' : '' }}"
                            aria-label="Vista {{ $idx + 1 }}">
                        <img src="{{ $thumbUrl }}" alt="" data-full-image="{{ $thumbUrl }}">
                    </button>
                @endforeach
            </div>
        @elseif($listing->image_url)
            <div class="mp-gallery-thumbs">
                <button type="button" class="mp-gallery-thumb is-active" aria-label="Vista principal">
                    <img src="{{ $listing->image_url }}" alt="">
                </button>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════ INFO + COMPRA ═══════════════════════ --}}
    <div class="mp-detail-info">

        @php $sellerStorePageUrl = $listing->store_url ?: ('https://' . $listing->tenant_fqdn); @endphp
        <a class="mp-shop-link" href="{{ $sellerStorePageUrl }}">
            @if($listing->tenant_logo_url)
                <img src="{{ $listing->tenant_logo_url }}" alt="{{ $listing->seller_display }}">
            @else
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
            @endif
            Vendido por <strong>{{ $listing->seller_display }}</strong>
            @if($listing->tenant_verified)
                <span class="mp-verified-inline" title="Tienda verificada por ebaemy">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                    Verificada
                </span>
            @endif
        </a>

        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:8px">
            <h1 style="flex:1;margin:0">{{ $listing->title }}</h1>
            <button type="button" id="mpDetailFavBtn" data-listing="{{ $listing->id }}"
                    style="flex-shrink:0;width:42px;height:42px;border-radius:50%;border:1.5px solid #e5e7eb;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;transition:all .15s"
                    title="Guardar en favoritos"
                    aria-label="Favoritos">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('mpDetailFavBtn');
            if (!btn) return;
            var lid = btn.dataset.listing;
            function readFavs(){
                try { return JSON.parse(localStorage.getItem('mp_favs') || '[]'); } catch(e){ return []; }
            }
            function paint(isFav){
                if (isFav) {
                    btn.style.background = '#fee2e2'; btn.style.borderColor = '#ef4444'; btn.style.color = '#ef4444';
                    btn.querySelector('svg').setAttribute('fill', 'currentColor');
                } else {
                    btn.style.background = '#fff'; btn.style.borderColor = '#e5e7eb'; btn.style.color = '#6b7280';
                    btn.querySelector('svg').setAttribute('fill', 'none');
                }
            }
            paint(readFavs().includes(lid));
            btn.addEventListener('click', function(){
                var favs = readFavs();
                var idx = favs.indexOf(lid);
                if (idx >= 0) favs.splice(idx, 1); else favs.push(lid);
                localStorage.setItem('mp_favs', JSON.stringify(favs));
                paint(idx < 0);
            });
        })();
        </script>

        @if($listing->rating_count > 0)
            <div class="mp-rating-summary">
                <span class="mp-rating-stars-xl">
                    @for($i=1;$i<=5;$i++){{ $i <= round($listing->avg_rating) ? '★' : '☆' }}@endfor
                </span>
                <strong>{{ number_format($listing->avg_rating, 1) }}</strong>
                <a href="#reviews" style="color:var(--mp-primary-dark);font-weight:500">{{ $listing->rating_count }} {{ $listing->rating_count === 1 ? 'opinión' : 'opiniones' }}</a>
            </div>
        @endif

        <div class="mp-meta">
            @if(!empty($officialCategoryUrl))
                <span class="mp-meta-chip">
                    <a href="{{ $officialCategoryUrl }}">📂 {{ $officialBreadcrumb->last()->name ?? $listing->category_name }}</a>
                </span>
            @elseif($listing->category_name)
                <span class="mp-meta-chip">
                    <a href="{{ route('marketplace.category', \Illuminate\Support\Str::slug($listing->category_name)) }}">📂 {{ $listing->category_name }}</a>
                </span>
            @endif
            @if($listing->brand_name)
                <span class="mp-meta-chip">🏷️ {{ $listing->brand_name }}</span>
            @endif
            @if($listing->internal_id)
                <span class="mp-meta-chip">🔖 SKU: {{ $listing->internal_id }}</span>
            @endif
        </div>

        <div class="mp-price-box">
            <div class="mp-price-box-label">Precio en {{ $listing->seller_display }}</div>
            <div class="mp-price" id="mpDisplayPrice">
                @if($listing->display_price > 0)
                    S/ {{ number_format(($variants->isNotEmpty() ? $variants->first()->price : $listing->display_price), 2) }}
                @else
                    <span style="font-size:18px;color:#6b7280">Precio a consultar</span>
                @endif
            </div>
            @if(($variants->isNotEmpty() && $variants->first()->is_on_offer) || (!$variants->isNotEmpty() && $listing->is_on_offer))
                <div class="mp-price-old" id="mpOldPrice">
                    <span style="text-decoration:line-through;color:#9ca3af;font-size:14px">
                        S/ {{ number_format(($variants->isNotEmpty() ? ($variants->first()->original_price ?? 0) : ($listing->original_price ?? 0)), 2) }}
                    </span>
                </div>
            @endif

            @php
                $initialStock = $variants->isNotEmpty() ? $variants->first()->stock : $listing->stock;
            @endphp
            <div id="mpStockBox">
                @if($initialStock <= 0)
                    <div class="mp-stock mp-stock--none">
                        <span class="mp-stock-dot"></span>
                        Sin stock disponible
                    </div>
                @elseif($initialStock < 5)
                    <div class="mp-stock mp-stock--low">
                        <span class="mp-stock-dot"></span>
                        ¡Últimas {{ $initialStock }} unidades!
                    </div>
                @else
                    <div class="mp-stock">
                        <span class="mp-stock-dot"></span>
                        En stock ({{ $initialStock }} disponibles)
                    </div>
                @endif
            </div>

            {{-- Cupones disponibles del comprador para esta tienda
                 (item 6 roadmap visibilidad cupones). Solo se renderiza si
                 el user logueado tiene cupones aplicables. Indicativo  el
                 cupn se aplica recin en el checkout. --}}
            @if(isset($availableCoupons) && $availableCoupons->isNotEmpty())
                @php $best = $availableCoupons->sortByDesc('discount')->first(); @endphp
                <a href="{{ route('marketplace.cart') }}" class="mp-detail-coupons-hint">
                    <span class="mp-detail-coupons-hint__icon">🎟️</span>
                    <span class="mp-detail-coupons-hint__body">
                        <strong>Tienes {{ $availableCoupons->count() }} {{ $availableCoupons->count() === 1 ? 'cupn aplicable' : 'cupones aplicables' }}</strong>
                        @if($best && $best['discount'] > 0)
                            <br>Mejor descuento: <em>{{ $best['coupon']->code }}</em>  ahorras hasta <strong>S/ {{ number_format($best['discount'], 2) }}</strong>
                        @else
                            <br>Aplicalos en el checkout
                        @endif
                    </span>
                    <span class="mp-detail-coupons-hint__arrow"></span>
                </a>
                <style>
                .mp-detail-coupons-hint {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-top: 12px;
                    padding: 12px 14px;
                    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
                    border: 1.5px solid #fbbf24;
                    border-radius: 12px;
                    text-decoration: none;
                    color: #78350f;
                    font-size: 13px;
                    line-height: 1.4;
                    transition: all .15s ease;
                    box-shadow: 0 2px 8px -2px rgba(245, 158, 11, .25);
                }
                .mp-detail-coupons-hint:hover {
                    border-color: #f59e0b;
                    transform: translateY(-1px);
                    color: #78350f;
                    box-shadow: 0 6px 14px -2px rgba(245, 158, 11, .4);
                }
                .mp-detail-coupons-hint__icon { font-size: 28px; line-height: 1; flex-shrink: 0; }
                .mp-detail-coupons-hint__body { flex: 1; }
                .mp-detail-coupons-hint__body strong { color: #92400e; font-weight: 800; }
                .mp-detail-coupons-hint__body em {
                    font-style: normal;
                    font-family: 'SF Mono', Menlo, Consolas, monospace;
                    background: #fff;
                    padding: 1px 6px;
                    border-radius: 4px;
                    border: 1px solid #fcd34d;
                    font-size: 11.5px;
                    letter-spacing: .04em;
                }
                .mp-detail-coupons-hint__arrow { font-size: 20px; flex-shrink: 0; color: #b45309; }
                </style>
            @endif
        </div>

        {{-- ═══════════ VARIANTES AGRUPADAS POR OPCIÓN (Fase 0.C) ═══════════ --}}
        @if(!empty($options) && $options->count() > 0)
            @php
                // Detectar si una opción es "color" (case-insensitive). Las
                // opciones color se renderizan como thumbnails-imagen; el
                // resto como pills de texto. Heurística simple por nombre.
                $isColorOpt = fn($name) => stripos((string) $name, 'color') !== false;
            @endphp
            <div class="mp-options" id="mpOptions" data-listing-id="{{ $listing->id }}">
                @foreach($options as $opt)
                    @php
                        $colorMode = $isColorOpt($opt->name);
                        // Value inicial seleccionado: el del primary (si lo hay)
                        // o fallback al primero. Determina qué está activo al
                        // cargar la página y qué texto sale en "Color: X".
                        $initialValue = !empty($primaryValueIds)
                            ? $opt->values->first(fn($v) => in_array($v->id, $primaryValueIds))
                            : null;
                        if (!$initialValue) $initialValue = $opt->values->first();
                        $initialId = $initialValue ? $initialValue->id : null;
                    @endphp
                    <div class="mp-option-group" data-option-id="{{ $opt->id }}">
                        <div class="mp-option-group__head">
                            <span class="mp-option-group__name">{{ $opt->name }}:</span>
                            <span class="mp-option-group__current" data-current-for="{{ $opt->id }}">
                                {{ $initialValue->value ?? '—' }}
                            </span>
                        </div>
                        <div class="mp-option-group__values {{ $colorMode ? 'is-color' : 'is-pill' }}">
                            @foreach($opt->values as $vIdx => $val)
                                <button type="button"
                                    class="mp-opt-value {{ $val->id === $initialId ? 'is-selected' : '' }} {{ $colorMode ? 'mp-opt-value--color' : 'mp-opt-value--pill' }}"
                                    data-option-id="{{ $opt->id }}"
                                    data-value-id="{{ $val->id }}"
                                    data-value-label="{{ $val->value }}"
                                    @if(!$colorMode) title="{{ $val->value }}" @endif>
                                    @if($colorMode && $val->image_url)
                                        <img src="{{ $val->image_url }}" alt="{{ $val->value }}" loading="lazy">
                                    @elseif($colorMode && $val->color_hex)
                                        <span class="mp-opt-value__hex" style="background:{{ $val->color_hex }}"></span>
                                    @else
                                        <span class="mp-opt-value__text">{{ $val->value }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Datos para JS resolver la combinación elegida --}}
            <script type="application/json" id="mpVariantMapData">{!! json_encode($variantMap) !!}</script>

            <style>
                .mp-options { margin: 14px 0 4px; display: flex; flex-direction: column; gap: 14px; }
                .mp-option-group__head {
                    display: flex; align-items: baseline; gap: 6px;
                    margin-bottom: 8px;
                }
                .mp-option-group__name {
                    font-size: 13px; font-weight: 700; color: #1f2937;
                }
                .mp-option-group__current {
                    font-size: 13px; color: #6b7280;
                }
                .mp-option-group__values { display: flex; flex-wrap: wrap; gap: 8px; }

                /* ─── Colores: thumbnails-imagen (estilo Falabella) ─── */
                .mp-option-group__values.is-color .mp-opt-value {
                    width: 56px; height: 56px;
                    padding: 2px;
                    background: #fff;
                    border: 2px solid #e5e7eb;
                    border-radius: 8px;
                    cursor: pointer;
                    overflow: hidden;
                    transition: border-color .15s, transform .12s, box-shadow .15s;
                }
                .mp-opt-value--color img,
                .mp-opt-value--color .mp-opt-value__hex {
                    width: 100%; height: 100%;
                    border-radius: 6px;
                    object-fit: cover;
                    display: block;
                }
                .mp-opt-value--color .mp-opt-value__hex { border: 1px solid rgba(0,0,0,.06); }
                .mp-opt-value--color:hover {
                    border-color: var(--mp-primary, #0f8a82);
                    transform: translateY(-1px);
                }
                .mp-opt-value--color.is-selected {
                    border-color: #0a0e1a;
                    box-shadow: 0 0 0 2px rgba(10,14,26,.08);
                }
                @media (max-width: 480px) {
                    .mp-option-group__values.is-color .mp-opt-value { width: 50px; height: 50px; }
                }

                /* ─── Pills: tallas, materiales, etc. ─── */
                .mp-opt-value--pill {
                    min-width: 48px;
                    padding: 10px 16px;
                    background: #fff;
                    border: 1.5px solid #d1d5db;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 13.5px; font-weight: 600; color: #1f2937;
                    transition: border-color .15s, background .15s, color .15s;
                }
                .mp-opt-value--pill:hover {
                    border-color: var(--mp-primary, #0f8a82);
                }
                .mp-opt-value--pill.is-selected {
                    background: #0a0e1a;
                    border-color: #0a0e1a;
                    color: #fff;
                }
                .mp-opt-value--pill.is-out {
                    opacity: .5;
                    text-decoration: line-through;
                    cursor: not-allowed;
                }
                .mp-opt-value--color.is-out {
                    opacity: .35;
                    cursor: not-allowed;
                }
            </style>

            <script>
            (function () {
                var dataNode = document.getElementById('mpVariantMapData');
                var optsBox  = document.getElementById('mpOptions');
                if (!dataNode || !optsBox) return;

                var variantMap = {};
                try { variantMap = JSON.parse(dataNode.textContent || '{}'); } catch (_) {}

                // Estado: option_id (string) → value_id (number) seleccionado
                var selected = {};
                optsBox.querySelectorAll('.mp-opt-value.is-selected').forEach(function (b) {
                    selected[b.dataset.optionId] = parseInt(b.dataset.valueId, 10);
                });

                function valueIdsArr() {
                    return Object.keys(selected)
                        .map(function (k) { return selected[k]; })
                        .filter(function (v) { return !!v; })
                        .sort(function (a, b) { return a - b; });
                }
                function variantForCombo(combo) {
                    if (!combo.length) return null;
                    return variantMap[combo.join('-')] || null;
                }
                function findCurrentVariant() {
                    var ids = valueIdsArr();
                    return ids.length === Object.keys(selected).length
                        ? variantForCombo(ids)
                        : null;
                }

                // Marcar como out-of-stock las opciones cuya combinación final
                // resulta en stock=0. Iteramos cada value de cada option y
                // probamos qué pasaría si el cliente lo eligiera.
                function refreshAvailability() {
                    optsBox.querySelectorAll('.mp-option-group').forEach(function (group) {
                        var optId = group.dataset.optionId;
                        group.querySelectorAll('.mp-opt-value').forEach(function (btn) {
                            var hypothetical = Object.assign({}, selected);
                            hypothetical[optId] = parseInt(btn.dataset.valueId, 10);
                            var ids = Object.keys(hypothetical)
                                .map(function (k) { return hypothetical[k]; })
                                .sort(function (a, b) { return a - b; });
                            // Solo marcamos out si TODAS las opciones tienen valor
                            // (sino no podemos saber si la combinación existe)
                            if (ids.length === Object.keys(selected).length) {
                                var v = variantForCombo(ids);
                                if (v && v.stock <= 0) btn.classList.add('is-out');
                                else btn.classList.remove('is-out');
                            } else {
                                btn.classList.remove('is-out');
                            }
                        });
                    });
                }

                function applyVariant(v) {
                    if (!v) return;
                    // Precio
                    var priceEl = document.getElementById('mpDisplayPrice');
                    if (priceEl) priceEl.textContent = 'S/ ' + Number(v.price).toFixed(2);
                    var oldEl = document.getElementById('mpOldPrice');
                    if (oldEl) {
                        if (v.is_on_offer && v.original_price && v.original_price > v.price) {
                            oldEl.innerHTML = '<span style="text-decoration:line-through;color:#9ca3af;font-size:14px">S/ ' + Number(v.original_price).toFixed(2) + '</span>';
                            oldEl.style.display = '';
                        } else {
                            oldEl.style.display = 'none';
                        }
                    }
                    // Stock
                    var stockBox = document.getElementById('mpStockBox');
                    if (stockBox) {
                        var s = v.stock;
                        if (s <= 0) {
                            stockBox.innerHTML = '<div class="mp-stock mp-stock--none"><span class="mp-stock-dot"></span>Sin stock disponible</div>';
                        } else if (s < 5) {
                            stockBox.innerHTML = '<div class="mp-stock mp-stock--low"><span class="mp-stock-dot"></span>¡Últimas ' + s + ' unidades!</div>';
                        } else {
                            stockBox.innerHTML = '<div class="mp-stock"><span class="mp-stock-dot"></span>En stock (' + s + ' disponibles)</div>';
                        }
                    }
                    // Imagen principal: cambia TODAS las imágenes grandes del
                    // producto en la página (Falabella-style — varias copias
                    // sincronizadas pueden existir). Mismo enfoque que el
                    // ecommerce tenant.
                    if (v.image_url) {
                        var imgs = Array.from(document.querySelectorAll('img')).filter(function (i) {
                            return i.src && i.src.indexOf('/uploads/items/') !== -1
                                && i.offsetWidth >= 200;
                        });
                        if (!imgs.length) {
                            var fallback = document.querySelector('#mpGalleryMain, .mp-gallery img');
                            if (fallback) imgs = [fallback];
                        }
                        imgs.forEach(function (img) {
                            img.style.transition = 'opacity .15s ease, transform .15s ease';
                            img.style.opacity = '0';
                            // Reset del zoom hover (si el cursor sigue sobre la imagen
                            // al cambiar variante, mouseleave no se dispara — la
                            // imagen nueva quedaría con scale 1.8). Volvemos a scale 1
                            // y origin centro para que la imagen entre completa en
                            // su contenedor.
                            img.style.transform = 'scale(1)';
                            img.style.transformOrigin = 'center center';
                            setTimeout(function () {
                                img.src = v.image_url;
                                if (img.dataset.zoomImage !== undefined) img.dataset.zoomImage = v.image_url;
                                img.style.opacity = '1';
                            }, 120);
                        });
                    }
                    // Botón Comprar — habilitar/deshabilitar por stock
                    var cartBtn = document.getElementById('mpAddToCartBtn');
                    if (cartBtn) {
                        cartBtn.disabled = v.stock <= 0;
                        cartBtn.dataset.variantId = v.tenant_variant_id;
                        cartBtn.style.opacity = v.stock <= 0 ? '.55' : '';
                    }
                    // Mobile sticky CTA: sincronizar precio + estado al cambiar variante.
                    var stickyPriceEl = document.getElementById('mpStickyPrice');
                    if (stickyPriceEl) stickyPriceEl.textContent = 'S/ ' + Number(v.price).toFixed(2);
                    var stickyOldEl = document.getElementById('mpStickyOld');
                    if (stickyOldEl) {
                        if (v.is_on_offer && v.original_price && v.original_price > v.price) {
                            stickyOldEl.textContent = 'S/ ' + Number(v.original_price).toFixed(2);
                            stickyOldEl.style.display = '';
                        } else {
                            stickyOldEl.style.display = 'none';
                        }
                    }
                    var stickyBtn = document.getElementById('mpMobileStickyBtn');
                    if (stickyBtn) {
                        stickyBtn.disabled = v.stock <= 0;
                        stickyBtn.style.opacity = v.stock <= 0 ? '.55' : '';
                    }
                }

                optsBox.addEventListener('click', function (e) {
                    var btn = e.target.closest('.mp-opt-value');
                    if (!btn) return;
                    if (btn.classList.contains('is-out')) return;
                    var optId = btn.dataset.optionId;
                    // Limpiar selected del mismo grupo
                    btn.parentElement.querySelectorAll('.mp-opt-value.is-selected').forEach(function (b) {
                        b.classList.remove('is-selected');
                    });
                    btn.classList.add('is-selected');
                    selected[optId] = parseInt(btn.dataset.valueId, 10);

                    // Actualizar texto "Color: Rojo" arriba del grupo
                    var currentTxt = btn.closest('.mp-option-group').querySelector('.mp-option-group__current');
                    if (currentTxt) currentTxt.textContent = btn.dataset.valueLabel || '';

                    refreshAvailability();
                    var v = findCurrentVariant();
                    applyVariant(v);
                });

                // Init: aplicar la combinación inicial (primer valor de cada opción)
                refreshAvailability();
                var initial = findCurrentVariant();
                applyVariant(initial);

                // ── Thumbs clickeables ──────────────────────────────────────
                // Cualquier <img> dentro de .mp-gallery-thumb se vuelve clickeable
                // y al click cambia la imagen principal. Útil cuando el producto
                // tiene galería múltiple (item_images).
                document.querySelectorAll('.mp-gallery-thumb img, [class*="thumb"] img').forEach(function (t) {
                    if (t.id === 'mpGalleryMain' || t.dataset.mpBound) return;
                    t.dataset.mpBound = '1';
                    t.style.cursor = 'pointer';
                    t.addEventListener('click', function (e) {
                        e.preventDefault();
                        var url = t.dataset.fullImage || t.src;
                        var imgs = Array.from(document.querySelectorAll('img')).filter(function (i) {
                            return i.src && i.src.indexOf('/uploads/items/') !== -1 && i.offsetWidth >= 200;
                        });
                        imgs.forEach(function (img) { img.src = url; });
                        // Marcar thumb activa
                        document.querySelectorAll('.mp-gallery-thumb').forEach(function (b) {
                            b.classList.remove('is-active');
                        });
                        var btn = t.closest('.mp-gallery-thumb');
                        if (btn) btn.classList.add('is-active');
                    });
                });

                // ── Zoom hover sobre imagen principal ───────────────────────
                // Solo aplica si NO hay zoom nativo (heurística por texto).
                var bodyText = (document.body.innerText || '').toLowerCase();
                var hasNativeZoom = bodyText.indexOf('pasa el mouse para hacer zoom') !== -1
                    || bodyText.indexOf('ampliar imagen') !== -1;
                if (!hasNativeZoom) {
                    var mainImg = document.getElementById('mpGalleryMain');
                    var container = mainImg && mainImg.parentElement;
                    if (mainImg && container && !container.classList.contains('mp-zoom-wrap')) {
                        container.classList.add('mp-zoom-wrap');
                        container.style.overflow = 'hidden';
                        container.addEventListener('mousemove', function (e) {
                            var rect = container.getBoundingClientRect();
                            var x = ((e.clientX - rect.left) / rect.width) * 100;
                            var y = ((e.clientY - rect.top) / rect.height) * 100;
                            mainImg.style.transformOrigin = x + '% ' + y + '%';
                        });
                        container.addEventListener('mouseenter', function () {
                            mainImg.style.transition = 'transform .2s ease';
                            mainImg.style.transform = 'scale(1.8)';
                            mainImg.style.cursor = 'zoom-in';
                        });
                        container.addEventListener('mouseleave', function () {
                            mainImg.style.transform = 'scale(1)';
                        });
                    }
                }

                // Exponer al script existente del botón Comprar (que ya leía
                // `variant_id` desde un input radio). Mantenemos compatibilidad:
                // creamos un input radio oculto si no existe, con el valor actual.
                var hidden = document.getElementById('mpHiddenVariantId');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.id = 'mpHiddenVariantId';
                    hidden.name = 'variant_id';
                    document.body.appendChild(hidden);
                }
                function syncHidden() {
                    var v = findCurrentVariant();
                    hidden.value = v ? v.tenant_variant_id : '';
                }
                optsBox.addEventListener('click', syncHidden);
                syncHidden();
            })();
            </script>
        @endif

        @if($errors->any())
            <div class="mp-errors">
                @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
            </div>
        @endif

        {{-- CTA principal: añadir al carrito multi-tienda --}}
        @if($listing->stock > 0)
            <button type="button" class="mp-cta-primary" id="mpAddToCartBtn"
                    data-slug="{{ $listing->slug }}"
                    style="width:100%;cursor:pointer;border:none">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                Añadir al carrito
            </button>
            @if(!empty($listing->seller_whatsapp))
                @php
                    $waMessage = '¡Hola! Te escribo desde ebaemy.com/marketplace por este producto:'
                                 . "\n\n" . $listing->title
                                 . "\n" . url('/marketplace/p/' . $listing->slug)
                                 . "\n\nQuisiera hacerte una consulta antes de comprar.";
                    $waUrl = 'https://wa.me/' . $listing->seller_whatsapp
                           . '?text=' . rawurlencode($waMessage);
                @endphp
                <a href="{{ $waUrl }}" target="_blank" rel="noopener nofollow"
                   class="mp-cta-secondary"
                   style="margin-top:8px">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                    </svg>
                    Contactar al vendedor
                </a>
            @endif

            {{-- Boton "Compartir por WhatsApp": viraliza la oferta. Especialmente
                 util en productos con descuento — el receptor abre el link
                 directo al producto en el marketplace. --}}
            @php
                $shareText = '🛍️ Mira este producto en ebaemy Marketplace:'
                           . "\n\n" . $listing->title
                           . (!empty($listing->is_on_offer) && !empty($listing->discount_pct)
                                ? "\n⚡ ¡Oferta -" . $listing->discount_pct . '%! Solo S/ ' . number_format($listing->display_price, 2)
                                : "\nDesde S/ " . number_format($listing->display_price, 2))
                           . "\n\n" . url('/marketplace/item/' . $listing->slug);
                $shareUrl = 'https://wa.me/?text=' . rawurlencode($shareText);
            @endphp
            <a href="{{ $shareUrl }}" target="_blank" rel="noopener nofollow"
               class="mp-cta-share"
               style="margin-top:8px"
               onclick="if(window.gtag){gtag('event','share',{method:'whatsapp',item_id:'{{ $listing->slug }}'});}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                </svg>
                Compartir por WhatsApp
            </a>

            <div class="mp-cta-divider"><span>o solicita información / envío al vendedor</span></div>

            {{-- Sticky bottom bar mobile: aparece cuando el CTA principal sale
                 del viewport (IntersectionObserver). Solo visible <768px.
                 Click → dispara el mismo flujo que el botón de arriba. --}}
            <div class="mp-mobile-sticky-cta" id="mpMobileStickyCta" aria-hidden="true">
                <div class="mp-mobile-sticky-cta__info">
                    @php
                        $stickyPrice = $listing->display_price ?? 0;
                        if (!empty($listing->has_variants)) {
                            $stickyPrice = $listing->min_price ?? $listing->display_price ?? 0;
                        }
                    @endphp
                    @if(!empty($listing->has_variants))
                        <span class="mp-mobile-sticky-cta__prefix">Desde</span>
                    @endif
                    <span class="mp-mobile-sticky-cta__price" id="mpStickyPrice">S/ {{ number_format($stickyPrice, 2) }}</span>
                    @if(!empty($listing->is_on_offer) && !empty($listing->original_price) && $listing->original_price > $listing->display_price)
                        <span class="mp-mobile-sticky-cta__old" id="mpStickyOld">S/ {{ number_format($listing->original_price, 2) }}</span>
                    @endif
                </div>
                <button type="button" class="mp-mobile-sticky-cta__btn" id="mpMobileStickyBtn" aria-label="Añadir al carrito">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Añadir
                </button>
            </div>
        @endif

        <script>
        (function(){
            // ── Selector de variantes (Fase 0.B) ──
            // Cuando el usuario cambia de variante: actualiza precio,
            // precio tachado, stock y la imagen principal si la variante
            // tiene una propia. El variant_id seleccionado se envía al
            // carrito desde el botón de abajo.
            // El selector legacy mp-variants fue reemplazado por mp-options
            // (Fase 0.C). El nuevo flow usa un hidden input #mpHiddenVariantId
            // que se actualiza desde el script de selección compuesta.
            const hiddenVariantInput = document.getElementById('mpHiddenVariantId');

            // ── Add to cart (con soporte de variant_id) ──
            const btn = document.getElementById('mpAddToCartBtn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                const slug = btn.dataset.slug;
                if (btn.disabled) return;
                btn.disabled = true;
                const original = btn.innerHTML;
                btn.innerHTML = 'Añadiendo…';

                const body = { slug, quantity: 1 };
                // Prioridad: hidden input poblado por el selector de opciones,
                // o el data-variant-id que el otro script setea en el botón.
                const vid = (hiddenVariantInput && hiddenVariantInput.value)
                    ? hiddenVariantInput.value
                    : (btn.dataset.variantId || '');
                if (vid) {
                    body.variant_id = parseInt(vid, 10);
                }

                fetch(@json(route('marketplace.cart.add')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body)
                })
                .then(r => r.json())
                .then(function (data) {
                    if (!data.success) {
                        btn.innerHTML = original; btn.disabled = false;
                        alert(data.message || 'No se pudo añadir el producto');
                        return;
                    }
                    btn.innerHTML = '✓ Añadido — ir al carrito';
                    btn.onclick = function () { window.location = @json(route('marketplace.cart')); };
                    btn.disabled = false;
                    if (window.mpCartBadgeUpdate) window.mpCartBadgeUpdate(data.summary);
                })
                .catch(function () {
                    btn.innerHTML = original; btn.disabled = false;
                    alert('Error de red');
                });
            });

            // ── Sticky CTA mobile ──
            // Aparece cuando el CTA principal sale del viewport. Click → proxy
            // al botón principal (reusa todo el flujo de cart.add + estado).
            var stickyBar = document.getElementById('mpMobileStickyCta');
            var stickyBtnEl = document.getElementById('mpMobileStickyBtn');
            if (stickyBar && stickyBtnEl && 'IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    var e = entries[0];
                    // Mostrar la barra cuando el botón principal NO está visible.
                    stickyBar.classList.toggle('is-visible', !e.isIntersecting);
                    stickyBar.setAttribute('aria-hidden', e.isIntersecting ? 'true' : 'false');
                }, { rootMargin: '0px 0px -40px 0px', threshold: 0.01 });
                observer.observe(btn);

                stickyBtnEl.addEventListener('click', function () {
                    // Si el botón principal está deshabilitado (sin stock o ya
                    // añadido), reflejamos el estado y no hacemos nada.
                    if (btn.disabled) return;
                    // Scroll suave al botón principal para que el usuario vea
                    // el cambio de estado ('✓ Añadido — ir al carrito'), y
                    // dispara el flow completo del botón principal.
                    btn.click();
                    btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
        })();
        </script>

        {{-- Form de lead (datos del formulario intactos) --}}
        <form method="POST" action="{{ route('marketplace.lead', $listing->slug) }}" class="mp-lead-form">
            @csrf
            {{-- Honeypot --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0"
                   aria-hidden="true">

            <div>
                <label>Nombre completo *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="180">
            </div>

            <div class="row-2">
                <div>
                    <label>Teléfono / WhatsApp</label>
                    <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" maxlength="40" placeholder="9XX XXX XXX">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="180">
                </div>
            </div>

            <div class="row-2">
                <div>
                    <label>Cantidad</label>
                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ max(1, $listing->stock) }}">
                </div>
                <div>
                    <label>&nbsp;</label>
                </div>
            </div>

            <div>
                <label>Mensaje al vendedor <span style="color:var(--mp-muted);font-weight:400">(opcional)</span></label>
                <textarea name="message" placeholder="Preguntas, detalles de envío, etc.">{{ old('message') }}</textarea>
            </div>

            <button type="submit" class="mp-cta" @if($listing->stock <= 0) disabled @endif>
                @if($listing->stock <= 0)
                    Sin stock
                @else
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px"><path d="m22 2-7 20-4-9-9-4 20-7z"/><path d="m22 2-11 11"/></svg>
                    Solicitar este producto
                @endif
            </button>
            <small style="color:var(--mp-muted);text-align:center;font-size:12px;display:block;margin-top:-4px">Tu solicitud se envía directamente a <strong>{{ $listing->seller_display }}</strong></small>
        </form>

        {{-- Badges de confianza mini (dentro de la columna de compra) --}}
        <div class="mp-trust-mini">
            <div class="mp-trust-mini-item">
                <span class="mp-trust-mini-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <span>Pago seguro a vendedor verificado</span>
            </div>
            <div class="mp-trust-mini-item">
                <span class="mp-trust-mini-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="13" height="10" rx="2"/><path d="M15 9h5l2 4v4h-7V9z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                </span>
                <span>Envío coordinado por el vendedor</span>
            </div>
            <div class="mp-trust-mini-item">
                <span class="mp-trust-mini-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                </span>
                <span>Factura electrónica SUNAT</span>
            </div>
            <div class="mp-trust-mini-item">
                <span class="mp-trust-mini-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <span>Comunicación directa con el vendedor</span>
            </div>
        </div>

        {{-- Bloque "Qué incluye este pack" — solo cuando es bundle.
             Renderiza la lista de componentes desde pack_contents JSON. --}}
        @if(!empty($listing->is_pack) && is_array($listing->pack_contents) && count($listing->pack_contents))
            <div class="mp-pack-block">
                <div class="mp-pack-block__head">
                    <span class="mp-pack-block__icon">📦</span>
                    <div>
                        <h3 class="mp-pack-block__title">¿Qué incluye este pack?</h3>
                        <p class="mp-pack-block__hint">{{ count($listing->pack_contents) }} producto{{ count($listing->pack_contents) === 1 ? '' : 's' }} en este combo</p>
                    </div>
                </div>
                <ul class="mp-pack-list">
                    @foreach($listing->pack_contents as $comp)
                        <li class="mp-pack-list__item">
                            @if(!empty($comp['image_url']))
                                <img class="mp-pack-list__thumb" src="{{ $comp['image_url'] }}" alt="{{ $comp['name'] ?? '' }}" loading="lazy">
                            @else
                                <div class="mp-pack-list__thumb mp-pack-list__thumb--empty">📦</div>
                            @endif
                            <div class="mp-pack-list__info">
                                <span class="mp-pack-list__name">{{ $comp['name'] ?? '—' }}</span>
                                <span class="mp-pack-list__qty">×{{ $comp['quantity'] ?? 1 }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if(($listing->pack_stock ?? null) !== null && $listing->pack_stock <= 5 && $listing->pack_stock > 0)
                    <div class="mp-pack-block__alert">
                        ⚠️ Solo quedan <strong>{{ $listing->pack_stock }}</strong> pack{{ $listing->pack_stock === 1 ? '' : 's' }} disponibles.
                    </div>
                @endif
            </div>
        @endif

        @if($listing->description)
            @php
                // Sanitización mínima: permite tags de formato (negrita, listas,
                // enlaces, etc.) que produce el editor del form, y descarta
                // todo lo demás. Después limpia event handlers y javascript:
                // urls para mitigar XSS si el seller pega HTML manipulado.
                $allowedTags = '<p><br><strong><em><b><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span>';
                $cleanDesc = strip_tags($listing->description, $allowedTags);
                $cleanDesc = preg_replace('/\sjavascript\s*:/i', ':', $cleanDesc);
                $cleanDesc = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $cleanDesc);
                $cleanDesc = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $cleanDesc);
                // Si el contenido NO trae ningún tag (texto plano con saltos
                // de línea), aplicamos nl2br + escape para preservar el
                // comportamiento legacy de descripciones viejas.
                $hasHtml = $cleanDesc !== strip_tags($cleanDesc);
                $renderDesc = $hasHtml ? $cleanDesc : nl2br(e($cleanDesc));
            @endphp
            <div class="mp-description">
                <h3 style="font-size:15px;font-weight:700;color:var(--mp-ink);margin:0 0 10px;letter-spacing:-0.01em">Descripción del producto</h3>
                {!! $renderDesc !!}
            </div>
        @endif
    </div>
</article>

{{-- ═══════════════════════ REVIEWS ═══════════════════════ --}}
<section class="mp-reviews" id="reviews">
    <div class="mp-reviews-head">
        <h3>Opiniones de clientes</h3>
        @if($listing->rating_count > 0)
            <div class="mp-reviews-stars">
                @php $r = $listing->avg_rating; @endphp
                <span class="mp-stars">
                    @for($i=1;$i<=5;$i++){{ $i <= round($r) ? '★' : '☆' }}@endfor
                </span>
                <span class="mp-reviews-meta">{{ number_format($r, 1) }} · {{ $listing->rating_count }} {{ $listing->rating_count === 1 ? 'opinión' : 'opiniones' }}</span>
            </div>
        @else
            <span class="mp-reviews-empty">Aún sin opiniones. Sé el primero.</span>
        @endif
    </div>

    @if(session('review_msg'))
        <div class="mp-review-notice">{{ session('review_msg') }}</div>
    @endif

    @if($errors->any())
        <div class="mp-errors" style="margin-bottom:14px">
            @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
        </div>
    @endif

    @if($reviews->isNotEmpty())
        <div class="mp-reviews-list">
            @foreach($reviews as $review)
                <div class="mp-review">
                    <div class="mp-review-head">
                        <div>
                            <strong>{{ $review->customer_name }}</strong>
                            @if(!empty($review->is_verified_buyer))
                                <span class="mp-review-verified" title="El autor de esta review compró este producto a través de ebaemy.com">
                                    ✓ Compra verificada
                                </span>
                            @endif
                            <small style="color:var(--mp-muted);margin-left:8px">{{ $review->created_at->diffForHumans() }}</small>
                        </div>
                        <span class="mp-stars mp-stars--sm">
                            @for($i=1;$i<=5;$i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor
                        </span>
                    </div>
                    @if($review->comment)
                        <p class="mp-review-body">{{ $review->comment }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Form nueva review --}}
    <details class="mp-review-form-wrap">
        <summary>Escribir una opinión</summary>
        <form method="POST" action="{{ route('marketplace.review', $listing->slug) }}" class="mp-review-form">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true">

            <div>
                <label>Nombre *</label>
                <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120">
            </div>
            <div>
                <label>Email <span style="color:var(--mp-muted);font-weight:400">(opcional, no se publica)</span></label>
                <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="180">
            </div>
            <div>
                <label>Calificación *</label>
                <div class="mp-rating-radios">
                    @for($i=5;$i>=1;$i--)
                        <input type="radio" name="rating" id="rating-{{ $i }}" value="{{ $i }}" {{ old('rating')==$i ? 'checked' : '' }} required>
                        <label for="rating-{{ $i }}" title="{{ $i }} estrellas">★</label>
                    @endfor
                </div>
            </div>
            <div>
                <label>Tu opinión</label>
                <textarea name="comment" maxlength="1000" placeholder="Comparte tu experiencia con este producto…">{{ old('comment') }}</textarea>
            </div>
            <button type="submit" class="mp-cta">Enviar opinión</button>
        </form>
    </details>
</section>

{{-- ═══════════════════════ RELACIONADOS ═══════════════════════ --}}
@if($related->isNotEmpty())
    <section class="mp-related" aria-label="También te puede interesar">
        <div class="mp-related__head">
            <h3 class="mp-related__title">También te puede interesar</h3>
            <div class="mp-related__nav" role="group" aria-label="Navegar carrusel">
                <button type="button" class="mp-related__nav-btn" data-rel-prev aria-label="Anterior">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="mp-related__nav-btn" data-rel-next aria-label="Siguiente">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>

        <div class="mp-related__rail" id="mpRelatedRail">
            @foreach($related as $r)
                <a href="{{ route('marketplace.item', $r->slug) }}" class="mp-related__card">
                    <div class="mp-related__card-img">
                        @if($r->image_url)
                            <img src="{{ $r->image_url }}" alt="{{ $r->title }}" loading="lazy">
                        @else
                            <div class="mp-related__card-noimg">Sin imagen</div>
                        @endif
                        @if(!empty($r->is_on_offer) && !empty($r->discount_pct))
                            <span class="mp-related__card-pct">-{{ $r->discount_pct }}%</span>
                        @endif
                    </div>
                    <div class="mp-related__card-body">
                        <h4 class="mp-related__card-title">{{ $r->title }}</h4>
                        <div class="mp-related__card-prices">
                            @if($r->display_price > 0)
                                <span class="mp-related__card-price">S/ {{ number_format($r->display_price, 2) }}</span>
                                @if(!empty($r->is_on_offer) && !empty($r->original_price) && $r->original_price > $r->display_price)
                                    <span class="mp-related__card-old">S/ {{ number_format($r->original_price, 2) }}</span>
                                @endif
                            @else
                                <span class="mp-related__card-consult">Consultar precio</span>
                            @endif
                        </div>
                        <div class="mp-related__card-shop">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                            <span>{{ \Illuminate\Support\Str::limit($r->seller_display, 24) }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <style>
    .mp-related { margin: 32px 0 16px; padding: 0; }
    .mp-related__head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-bottom: 14px;
        padding: 0 clamp(0px, 1vw, 4px);
    }
    .mp-related__title {
        margin: 0;
        font-size: clamp(16px, 3.4vw, 19px);
        font-weight: 700;
        color: var(--mp-ink, #111827);
        letter-spacing: -.01em;
    }
    .mp-related__nav { display: inline-flex; gap: 6px; }
    .mp-related__nav-btn {
        width: 36px; height: 36px;
        border-radius: 999px;
        border: 1.5px solid var(--mp-line, #e5e7eb);
        background: #fff;
        color: var(--mp-ink, #111827);
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        transition: border-color .15s, color .15s, background .15s, transform .12s;
    }
    .mp-related__nav-btn:hover {
        border-color: var(--mp-primary, #0f8a82);
        color: var(--mp-primary-dark, #0c6b65);
    }
    .mp-related__nav-btn:active { transform: scale(.94); }
    .mp-related__nav-btn:disabled { opacity: .35; cursor: not-allowed; }

    .mp-related__rail {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(200px, 1fr);
        gap: 14px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        -webkit-mask-image: linear-gradient(to right, #000 94%, transparent);
                mask-image: linear-gradient(to right, #000 94%, transparent);
        padding: 4px 4px 14px;
    }
    .mp-related__rail::-webkit-scrollbar { height: 4px; }
    .mp-related__rail::-webkit-scrollbar-thumb { background: var(--mp-line, #e5e7eb); border-radius: 999px; }

    .mp-related__card {
        scroll-snap-align: start;
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        text-decoration: none; color: inherit;
        overflow: hidden;
        transition: transform .18s, box-shadow .18s, border-color .18s;
        display: flex; flex-direction: column;
        min-width: 0;
    }
    .mp-related__card:hover {
        transform: translateY(-3px);
        border-color: var(--mp-primary, #0f8a82);
        box-shadow: 0 10px 22px -12px rgba(15, 138, 130, .25);
    }
    .mp-related__card-img {
        position: relative;
        aspect-ratio: 1/1;
        background: #f7f9fb;
        overflow: hidden;
    }
    .mp-related__card-img img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .35s;
    }
    .mp-related__card:hover .mp-related__card-img img { transform: scale(1.05); }
    .mp-related__card-noimg {
        display: flex; align-items: center; justify-content: center;
        height: 100%; color: #9ca3af; font-size: 12px;
    }
    .mp-related__card-pct {
        position: absolute; top: 8px; left: 8px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        font-size: 12px; font-weight: 800; letter-spacing: .3px;
        padding: 3px 8px; border-radius: 999px;
        box-shadow: 0 4px 8px -4px rgba(220,38,38,.45);
    }
    .mp-related__card-body { padding: 10px 12px 12px; }
    .mp-related__card-title {
        margin: 0 0 6px;
        font-size: 13px; font-weight: 600; color: #1f2937;
        line-height: 1.35;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 34px;
    }
    .mp-related__card-prices {
        display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
        margin-bottom: 4px;
    }
    .mp-related__card-price {
        font-size: 16px; font-weight: 800;
        color: var(--mp-ink, #111827);
        letter-spacing: -.01em;
    }
    .mp-related__card-old {
        font-size: 12px;
        color: #9ca3af;
        text-decoration: line-through;
    }
    .mp-related__card-consult { font-size: 13px; font-weight: 500; color: #6b7280; }
    .mp-related__card-shop {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 11.5px; color: #6b7280;
        margin-top: 2px;
    }

    /* Mobile: cards mas compactas, ocultar nav buttons (swipe nativo) */
    @media (max-width: 700px) {
        .mp-related__rail { grid-auto-columns: minmax(160px, 1fr); gap: 10px; }
        .mp-related__nav { display: none; }
        .mp-related__card-title { font-size: 12.5px; min-height: 32px; }
        .mp-related__card-price { font-size: 15px; }
    }
    </style>

    <script>
    (function () {
        var rail = document.getElementById('mpRelatedRail');
        if (!rail) return;
        var prev = document.querySelector('[data-rel-prev]');
        var next = document.querySelector('[data-rel-next]');
        function scrollBy(dir) {
            var card = rail.querySelector('.mp-related__card');
            var step = card ? card.offsetWidth + 14 : 220;
            rail.scrollBy({ left: dir * step * 2, behavior: 'smooth' });
        }
        if (prev) prev.addEventListener('click', function () { scrollBy(-1); });
        if (next) next.addEventListener('click', function () { scrollBy(1); });

        // Habilitar/deshabilitar botones segun posicion de scroll
        function syncNav() {
            if (!prev || !next) return;
            prev.disabled = rail.scrollLeft <= 4;
            next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
        }
        rail.addEventListener('scroll', syncNav, { passive: true });
        window.addEventListener('resize', syncNav);
        syncNav();
    })();
    </script>
@endif

{{-- ════════════ Ofertas contextuales ════════════
     2 secciones cuando ambas tienen contenido:
     (a) Más ofertas en [tienda actual] — cross-sell intra-tienda
     (b) Ofertas en otras tiendas — descubrimiento del marketplace --}}

@if(!empty($sameStoreOffers) && $sameStoreOffers->count() > 0)
    <section class="mp-show-offers" aria-label="Más ofertas en esta tienda">
        <div class="mp-show-offers__head">
            <h2 class="mp-show-offers__title">{{ $sameStoreLabel ?? '🔥 Más ofertas de esta tienda' }}</h2>
            <a href="{{ route('marketplace.tenant', ['subdomain' => $listing->subdomain]) }}?on_offer=1"
               class="mp-show-offers__see-all">Ver todas →</a>
        </div>
        <div class="mp-show-offers__scroll">
            @foreach($sameStoreOffers as $offer)
                <div class="mp-show-offers__item">
                    @include('marketplace.partials.listing-card', ['listing' => $offer])
                </div>
            @endforeach
        </div>
    </section>
@endif

@if(!empty($otherStoreOffers) && $otherStoreOffers->count() > 0)
    <section class="mp-show-offers mp-show-offers--other" aria-label="Ofertas en otras tiendas">
        <div class="mp-show-offers__head">
            <h2 class="mp-show-offers__title">{{ $otherStoreLabel ?? '🔥 Ofertas en otras tiendas' }}</h2>
            <a href="{{ route('marketplace.index', ['on_offer' => 1]) }}"
               class="mp-show-offers__see-all">Ver todas →</a>
        </div>
        <div class="mp-show-offers__scroll">
            @foreach($otherStoreOffers as $offer)
                <div class="mp-show-offers__item">
                    @include('marketplace.partials.listing-card', ['listing' => $offer, 'showShopName' => true])
                </div>
            @endforeach
        </div>
    </section>
@endif

@if((!empty($sameStoreOffers) && $sameStoreOffers->count() > 0) || (!empty($otherStoreOffers) && $otherStoreOffers->count() > 0))
    <style>
    .mp-show-offers {
        margin: 32px 0 16px;
        padding: 18px clamp(12px, 3vw, 22px);
        background: linear-gradient(135deg, #fff7ed 0%, #fff 60%);
        border: 1px solid #fed7aa;
        border-radius: 14px;
    }
    /* La 2da seccion (otras tiendas) usa otro tono para diferenciarse
       visualmente — verde-azulado del marketplace en vez del naranja
       'flash' de la tienda actual. */
    .mp-show-offers--other {
        background: linear-gradient(135deg, #f0fdfa 0%, #fff 60%);
        border: 1px solid #a7f3d0;
    }
    .mp-show-offers__head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .mp-show-offers__title {
        margin: 0;
        font-size: clamp(16px, 3.4vw, 19px);
        font-weight: 800;
        color: #9a3412;
        letter-spacing: -.01em;
    }
    .mp-show-offers--other .mp-show-offers__title { color: #0c6b65; }
    .mp-show-offers__see-all {
        font-size: 13px;
        font-weight: 700;
        color: #c2410c;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(194, 65, 12, .08);
        transition: background .12s;
    }
    .mp-show-offers--other .mp-show-offers__see-all {
        color: #0c6b65;
        background: rgba(15, 138, 130, .08);
    }
    .mp-show-offers__see-all:hover { background: rgba(194, 65, 12, .16); }
    .mp-show-offers--other .mp-show-offers__see-all:hover { background: rgba(15, 138, 130, .18); }
    .mp-show-offers__scroll {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(190px, 1fr);
        gap: 12px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: thin;
        -webkit-mask-image: linear-gradient(to right, #000 94%, transparent);
                mask-image: linear-gradient(to right, #000 94%, transparent);
        padding: 4px 4px 12px;
    }
    .mp-show-offers__scroll::-webkit-scrollbar { height: 4px; }
    .mp-show-offers__scroll::-webkit-scrollbar-thumb { background: #fdba74; border-radius: 999px; }
    .mp-show-offers--other .mp-show-offers__scroll::-webkit-scrollbar-thumb { background: #6ee7b7; }
    .mp-show-offers__item { scroll-snap-align: start; min-width: 0; }
    @media (min-width: 900px) {
        .mp-show-offers__scroll {
            grid-auto-flow: row;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            overflow-x: visible;
            -webkit-mask-image: none;
                    mask-image: none;
            scroll-snap-type: none;
        }
    }
    .mp-show-offers__scroll .mp-card { height: 100%; }
    </style>
@endif

@include('marketplace.partials.recently-viewed', ['recentlyViewed' => $recentlyViewed ?? collect()])

@endsection
