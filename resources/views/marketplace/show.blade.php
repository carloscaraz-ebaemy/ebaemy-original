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
    $bcCategoryUrl  = $listing->category_name ? route('marketplace.category', \Illuminate\Support\Str::slug($listing->category_name)) : null;

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
    if ($listing->brand_name) $product['brand'] = ['@type' => 'Brand', 'name' => $listing->brand_name];
    if ($listing->internal_id) $product['sku'] = $listing->internal_id;
    // Rich snippet de estrellas en Google (solo si hay al menos 1 review aprobada)
    if (($listing->rating_count ?? 0) > 0) {
        $product['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $listing->avg_rating, 1),
            'reviewCount' => (int) $listing->rating_count,
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }

    $breadcrumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Marketplace', 'item' => $bcIndexUrl],
    ];
    if ($listing->category_name) {
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

@push('styles')
<style>
    .mp-detail { display:grid; grid-template-columns: 1.1fr 1fr; gap:36px; background:#fff; border-radius:16px; padding:32px; margin-top:12px; }
    .mp-detail-img { background:#f3f4f6; border-radius:12px; aspect-ratio:1/1; overflow:hidden; }
    .mp-detail-img img { width:100%; height:100%; object-fit:cover; }
    .mp-detail h1 { margin:0 0 12px; font-size:26px; line-height:1.2; }
    .mp-shop-link { color:#6366f1; font-size:14px; margin-bottom:14px; display:inline-block; }
    .mp-price-box { background:#faf5ff; border:1px solid #e9d5ff; padding:18px; border-radius:12px; margin-bottom:20px; }
    .mp-price { font-size:28px; font-weight:700; color:#6d28d9; }
    .mp-stock { font-size:13px; color:#059669; margin-top:4px; }
    .mp-stock--low { color:#d97706; }
    .mp-stock--none { color:#dc2626; }
    .mp-lead-form { display:grid; gap:12px; }
    .mp-lead-form label { font-size:13px; font-weight:500; color:#374151; }
    .mp-lead-form input, .mp-lead-form textarea { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; font-family:inherit; }
    .mp-lead-form textarea { min-height:80px; resize:vertical; }
    .mp-cta { background:#111; color:#fff; padding:14px 24px; border:none; border-radius:10px; font-weight:600; font-size:15px; cursor:pointer; width:100%; }
    .mp-cta:hover { background:#000; }
    .mp-errors { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:8px; font-size:13px; }
    .mp-cta-primary { display:block; text-align:center; background:linear-gradient(135deg,#059669 0%, #047857 100%); color:#fff !important; padding:16px 24px; border-radius:12px; font-weight:700; font-size:16px; text-decoration:none; margin-bottom:16px; transition:transform .15s ease, box-shadow .15s ease; }
    .mp-cta-primary:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(5,150,105,.3); }
    .mp-cta-divider { display:flex; align-items:center; gap:10px; color:#9ca3af; font-size:12px; margin:18px 0 14px; }
    .mp-cta-divider::before, .mp-cta-divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    .mp-meta { font-size:13px; color:#64748b; display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
    .mp-description { border-top:1px solid #e5e7eb; padding-top:20px; line-height:1.6; color:#334155; font-size:14px; }
    .mp-related { margin-top:40px; }
    .mp-related h3 { margin:0 0 16px; font-size:18px; }

    /* ══ Reviews ══════════════════════════════════════════ */
    .mp-reviews { margin-top:40px; background:#fff; border-radius:16px; padding:28px; }
    .mp-reviews-head { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:8px; margin-bottom:18px; border-bottom:1px solid #e5e7eb; padding-bottom:14px; }
    .mp-reviews-head h3 { margin:0; font-size:18px; color:#0f172a; }
    .mp-reviews-stars { display:flex; align-items:center; gap:8px; }
    .mp-stars { color:#f59e0b; font-size:18px; letter-spacing:2px; }
    .mp-stars--sm { font-size:14px; letter-spacing:1px; }
    .mp-reviews-meta { font-size:13px; color:#64748b; font-weight:500; }
    .mp-reviews-empty { font-size:13px; color:#94a3b8; }
    .mp-review-notice { background:#dcfce7; border:1px solid #86efac; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:14px; }
    .mp-reviews-list { display:grid; gap:14px; margin-bottom:18px; }
    .mp-review { background:#f9fafb; border-radius:10px; padding:14px 16px; border:1px solid #e5e7eb; }
    .mp-review-head { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap; }
    .mp-review-body { margin:4px 0 0; font-size:14px; color:#374151; line-height:1.5; }
    .mp-review-form-wrap { border-top:1px solid #e5e7eb; padding-top:16px; margin-top:6px; }
    .mp-review-form-wrap summary { cursor:pointer; font-size:14px; color:#6366f1; font-weight:500; padding:6px 0; }
    .mp-review-form-wrap summary:hover { color:#4f46e5; }
    .mp-review-form { display:grid; gap:12px; margin-top:12px; }
    .mp-review-form label { font-size:13px; font-weight:500; color:#374151; }
    .mp-review-form input, .mp-review-form textarea { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; font-family:inherit; }
    .mp-review-form textarea { min-height:80px; resize:vertical; }

    /* Star rating input — radios invertidos */
    .mp-rating-radios { display:inline-flex; flex-direction:row-reverse; gap:2px; }
    .mp-rating-radios input { display:none; }
    .mp-rating-radios label { font-size:28px; color:#d1d5db; cursor:pointer; transition:color .15s; padding:0 4px; }
    .mp-rating-radios input:checked ~ label,
    .mp-rating-radios label:hover,
    .mp-rating-radios label:hover ~ label { color:#f59e0b; }

    @media (max-width:760px){
        .mp-detail { grid-template-columns:1fr; padding:20px; }
    }
</style>
@endpush

@section('content')
    <div style="margin-bottom:12px;font-size:13px;color:#64748b">
        <a href="{{ route('marketplace.index') }}" style="color:#6366f1">← Volver al marketplace</a>
    </div>

    <section class="mp-detail">
        <div>
            <div class="mp-detail-img">
                @if($listing->image_url)
                    <img src="{{ $listing->image_url }}" alt="{{ $listing->title }}">
                @else
                    <div style="display:flex;height:100%;align-items:center;justify-content:center;color:#9ca3af">Sin imagen</div>
                @endif
            </div>
        </div>

        <div>
            <a class="mp-shop-link" href="https://{{ $listing->tenant_fqdn }}" target="_blank" rel="noopener">
                @if($listing->tenant_logo_url)
                    <img src="{{ $listing->tenant_logo_url }}" alt="{{ $listing->seller_display }}"
                         style="height:18px;width:18px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:6px">
                @endif
                🏪 Vendido por <strong>{{ $listing->seller_display }}</strong>
                @if($listing->tenant_verified)
                    <span class="mp-verified-inline" title="Tienda verificada por ebaemy">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2563eb"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                        Verificada
                    </span>
                @endif
                →
            </a>
            <h1>{{ $listing->title }}</h1>

            <div class="mp-meta">
                @if($listing->category_name)
                    <span>📂 <a href="{{ route('marketplace.category', \Illuminate\Support\Str::slug($listing->category_name)) }}"
                                style="color:#6366f1;text-decoration:none">{{ $listing->category_name }}</a></span>
                @endif
                @if($listing->brand_name)    <span>🏷️ {{ $listing->brand_name }}</span> @endif
                @if($listing->internal_id)   <span>🔖 SKU: {{ $listing->internal_id }}</span> @endif
            </div>

            <div class="mp-price-box">
                <div class="mp-price">S/ {{ number_format($listing->display_price, 2) }}</div>
                @if($listing->stock <= 0)
                    <div class="mp-stock mp-stock--none">⛔ Sin stock disponible</div>
                @elseif($listing->stock < 5)
                    <div class="mp-stock mp-stock--low">⚠ Últimas {{ $listing->stock }} unidades</div>
                @else
                    <div class="mp-stock">✓ En stock ({{ $listing->stock }} disponibles)</div>
                @endif
            </div>

            @if($errors->any())
                <div class="mp-errors">
                    @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                </div>
            @endif

            {{-- CTA directa al storefront del tenant (el tenant factura con su RUC) --}}
            @if($listing->stock > 0)
                <a href="{{ route('marketplace.go', $listing->slug) }}" rel="nofollow sponsored"
                   class="mp-cta-primary">
                    🛒 Comprar en {{ $listing->seller_display }}
                </a>
                <div class="mp-cta-divider"><span>o solicita información / envío</span></div>
            @endif

            <form method="POST" action="{{ route('marketplace.lead', $listing->slug) }}" class="mp-lead-form">
                @csrf
                {{-- Honeypot: humanos no lo ven, bots lo llenan y son rechazados. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off"
                       style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0"
                       aria-hidden="true">
                <div>
                    <label>Nombre completo *</label>
                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="180">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label>Teléfono / WhatsApp</label>
                        <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" maxlength="40" placeholder="9XX XXX XXX">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="customer_email" value="{{ old('customer_email') }}" maxlength="180">
                    </div>
                </div>
                <div>
                    <label>Cantidad</label>
                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ max(1, $listing->stock) }}">
                </div>
                <div>
                    <label>Mensaje al vendedor (opcional)</label>
                    <textarea name="message" placeholder="Preguntas, detalles de envío, etc.">{{ old('message') }}</textarea>
                </div>
                <button type="submit" class="mp-cta" @if($listing->stock <= 0) disabled style="opacity:.5;cursor:not-allowed" @endif>
                    @if($listing->stock <= 0) Sin stock @else Enviar solicitud / Pedir este producto @endif
                </button>
                <small style="color:#64748b;text-align:center">Tu solicitud se envía directamente a {{ $listing->seller_display }}</small>
            </form>

            @if($listing->description)
                <div class="mp-description">
                    {!! nl2br(e($listing->description)) !!}
                </div>
            @endif
        </div>
    </section>

    {{-- ══ REVIEWS ══════════════════════════════════════════════════ --}}
    <section class="mp-reviews">
        <div class="mp-reviews-head">
            <h3>Opiniones de clientes</h3>
            @if($listing->rating_count > 0)
                <div class="mp-reviews-stars">
                    @php $r = $listing->avg_rating; @endphp
                    <span class="mp-stars">
                        @for($i=1;$i<=5;$i++)
                            {{ $i <= round($r) ? '★' : '☆' }}
                        @endfor
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
                                <small style="color:#94a3b8;margin-left:8px">{{ $review->created_at->diffForHumans() }}</small>
                            </div>
                            <span class="mp-stars mp-stars--sm">
                                @for($i=1;$i<=5;$i++)
                                    {{ $i <= $review->rating ? '★' : '☆' }}
                                @endfor
                            </span>
                        </div>
                        @if($review->comment)
                            <p class="mp-review-body">{{ $review->comment }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Form para nueva review --}}
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
                    <label>Email (opcional, no se publica)</label>
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
                    <textarea name="comment" maxlength="1000" placeholder="Comparte tu experiencia...">{{ old('comment') }}</textarea>
                </div>
                <button type="submit" class="mp-cta">Enviar opinión</button>
            </form>
        </details>
    </section>

    @if($related->isNotEmpty())
        <section class="mp-related">
            <h3>También te puede interesar</h3>
            <div class="mp-grid">
                @foreach($related as $r)
                    <a href="{{ route('marketplace.item', $r->slug) }}" class="mp-card">
                        <div class="mp-card-img">
                            @if($r->image_url) <img src="{{ $r->image_url }}" alt="{{ $r->title }}" loading="lazy">@endif
                        </div>
                        <div class="mp-card-body">
                            <h3 class="mp-card-title">{{ $r->title }}</h3>
                            <div class="mp-card-price">S/ {{ number_format($r->display_price, 2) }}</div>
                            <div class="mp-card-shop">🏪 {{ \Illuminate\Support\Str::limit($r->seller_display, 24) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
