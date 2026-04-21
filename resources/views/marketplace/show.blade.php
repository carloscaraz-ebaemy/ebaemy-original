@extends('marketplace.layout')

@section('title', $listing->title . ' — ' . $listing->tenant_fqdn . ' | Marketplace ebaemy')
@section('description', \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 155))
@section('keywords', $listing->title . ', ' . ($listing->category_name ?? '') . ', ' . $listing->tenant_fqdn . ', marketplace ebaemy')
@section('og_title', $listing->title)
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 155))
@section('og_image', $listing->image_url ?: asset('logo/logo.png'))
@section('og_type', 'product')
@section('canonical', route('marketplace.item', $listing->slug))

@push('styles')
<script type="application/ld+json">
{
    "@context": "https://schema.org/",
    "@type": "Product",
    "name": @json($listing->title),
    "image": @json($listing->image_url ?: asset('logo/logo.png')),
    "description": @json(\Illuminate\Support\Str::limit(strip_tags($listing->description ?? $listing->title), 500)),
    @if($listing->brand_name) "brand": { "@type": "Brand", "name": @json($listing->brand_name) }, @endif
    @if($listing->internal_id) "sku": @json($listing->internal_id), @endif
    "offers": {
        "@type": "Offer",
        "url": @json(route('marketplace.item', $listing->slug)),
        "priceCurrency": "PEN",
        "price": @json(number_format($listing->display_price, 2, '.', '')),
        "availability": @json($listing->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'),
        "seller": { "@type": "Organization", "name": @json($listing->tenant_fqdn) }
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Marketplace", "item": @json(route('marketplace.index')) },
        @if($listing->category_name)
        { "@type": "ListItem", "position": 2, "name": @json($listing->category_name), "item": @json(route('marketplace.index', ['category' => $listing->category_name])) },
        { "@type": "ListItem", "position": 3, "name": @json($listing->title) }
        @else
        { "@type": "ListItem", "position": 2, "name": @json($listing->title) }
        @endif
    ]
}
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
                Vendido por {{ $listing->tenant_fqdn }} →
            </a>
            <h1>{{ $listing->title }}</h1>

            <div class="mp-meta">
                @if($listing->category_name) <span>📂 {{ $listing->category_name }}</span> @endif
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
                    🛒 Comprar ahora en {{ $listing->tenant_fqdn }}
                </a>
                <div class="mp-cta-divider"><span>o solicita información / envío</span></div>
            @endif

            <form method="POST" action="{{ route('marketplace.lead', $listing->slug) }}" class="mp-lead-form">
                @csrf
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
                <small style="color:#64748b;text-align:center">Tu solicitud se envía directamente a {{ $listing->tenant_fqdn }}</small>
            </form>

            @if($listing->description)
                <div class="mp-description">
                    {!! nl2br(e($listing->description)) !!}
                </div>
            @endif
        </div>
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
                            <div class="mp-card-shop">{{ $r->tenant_fqdn }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
