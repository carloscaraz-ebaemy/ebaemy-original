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

        {{-- Thumbnails (futuro: galería múltiple). Por ahora solo la principal. --}}
        @if($listing->image_url)
            <div class="mp-gallery-thumbs">
                <button type="button" class="mp-gallery-thumb is-active" aria-label="Vista principal">
                    <img src="{{ $listing->image_url }}" alt="">
                </button>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════ INFO + COMPRA ═══════════════════════ --}}
    <div class="mp-detail-info">

        <a class="mp-shop-link" href="https://{{ $listing->tenant_fqdn }}" target="_blank" rel="noopener">
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

        <h1>{{ $listing->title }}</h1>

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
            <div class="mp-price">S/ {{ number_format($listing->display_price, 2) }}</div>

            @if($listing->stock <= 0)
                <div class="mp-stock mp-stock--none">
                    <span class="mp-stock-dot"></span>
                    Sin stock disponible
                </div>
            @elseif($listing->stock < 5)
                <div class="mp-stock mp-stock--low">
                    <span class="mp-stock-dot"></span>
                    ¡Últimas {{ $listing->stock }} unidades!
                </div>
            @else
                <div class="mp-stock">
                    <span class="mp-stock-dot"></span>
                    En stock ({{ $listing->stock }} disponibles)
                </div>
            @endif
        </div>

        @if($errors->any())
            <div class="mp-errors">
                @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
            </div>
        @endif

        {{-- CTA principal: ir al storefront del tenant --}}
        @if($listing->stock > 0)
            <a href="{{ route('marketplace.go', $listing->slug) }}" rel="nofollow sponsored" class="mp-cta-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                Comprar en {{ $listing->seller_display }}
            </a>
            <div class="mp-cta-divider"><span>o solicita información / envío</span></div>
        @endif

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

        @if($listing->description)
            <div class="mp-description">
                <h3 style="font-size:15px;font-weight:700;color:var(--mp-ink);margin:0 0 10px;letter-spacing:-0.01em">Descripción del producto</h3>
                {!! nl2br(e($listing->description)) !!}
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
    <section class="mp-related">
        <h3>También te puede interesar</h3>
        <div class="mp-grid">
            @foreach($related as $r)
                <a href="{{ route('marketplace.item', $r->slug) }}" class="mp-card">
                    <div class="mp-card-img">
                        @if($r->image_url)
                            <img src="{{ $r->image_url }}" alt="{{ $r->title }}" loading="lazy">
                        @else
                            <div class="mp-card-img-empty">Sin imagen</div>
                        @endif
                    </div>
                    <div class="mp-card-body">
                        <h3 class="mp-card-title">{{ $r->title }}</h3>
                        <div class="mp-card-price-row">
                            <span class="mp-card-price">S/ {{ number_format($r->display_price, 2) }}</span>
                        </div>
                        <div class="mp-card-shop">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
                            <span class="mp-card-shop-name">{{ \Illuminate\Support\Str::limit($r->seller_display, 24) }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif

@endsection
