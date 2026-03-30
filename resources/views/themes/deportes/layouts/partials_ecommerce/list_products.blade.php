{{-- THEME DEPORTES — Grid estilo Nike: cards con imagen grande, CTA bold, badges energéticos --}}
@php
    $configuration = \App\Models\Tenant\Configuration::first();
    if (!function_exists('stock')) {
        function stock($item, $config) { if (!$config) return false; $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total <= 0; }
    }
    if (!function_exists('stockCount')) {
        function stockCount($item) { $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total; }
    }
@endphp

@foreach ($dataPaginate as $item)
@php
    $outOfStock = stock($item, $configuration);
    $totalStock = stockCount($item);
    if ($item->has_variants && $item->relationLoaded('variants')) { $variantStock = $item->variants->sum('stock'); $outOfStock = $variantStock <= 0; $totalStock = $variantStock; }
    $isNew = $item->created_at && $item->created_at->diffInDays(now()) <= 30;
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath = $defaultImage === 'imagen-no-disponible.jpg' ? asset('logo/imagen-no-disponible.jpg') : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath = $hasRealImage ? asset('storage/uploads/items/' . $item->image) : $defaultPath;
    $hoverImage = null;
    if ($item->relationLoaded('images') && $item->images->isNotEmpty()) {
        $first = $item->images->first();
        if ($first && $first->image && $first->image !== 'imagen-no-disponible.jpg') $hoverImage = asset('storage/uploads/items/' . $first->image);
    }
    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $symbol = $item->currency_type['symbol'] ?? 'S/';
    $price = number_format($item->sale_unit_price, 2);
    $delay = min($loop->iteration * 40, 400);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item" style="animation-delay:{{ $delay }}ms">
    <article class="sport-card{{ $outOfStock ? ' sport-card--oos' : '' }}">
        <div class="sport-card__media">
            <a href="{{ $productUrl }}" class="sport-card__img-link">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="sport-card__img sport-card__img--primary ec-img-lazy">
                @else
                <img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="sport-card__img sport-card__img--primary">
                @endif
                @if($hoverImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $hoverImage }}" alt="{{ $item->description }}" class="sport-card__img sport-card__img--hover ec-img-lazy" aria-hidden="true">
                @endif
            </a>
            @if($outOfStock)<span class="sport-badge sport-badge--oos">Agotado</span>
            @elseif($isNew)<span class="sport-badge sport-badge--new">Nuevo</span>@endif
            @if($item->has_variants && !$outOfStock)<span class="sport-badge sport-badge--opt">+ Opciones</span>@endif
            <button type="button" class="sport-card__wish ec-btn-wishlist" data-wishlist-id="{{ $item->id }}" aria-pressed="false" title="Favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </div>
        <div class="sport-card__body">
            @if($item->category)<span class="sport-card__cat">{{ $item->category->name }}</span>@endif
            <h2 class="sport-card__title"><a href="{{ $productUrl }}">{{ $item->description }}</a></h2>
            <div class="sport-card__price">{{ $symbol }} {{ $price }}</div>
            @if(!$outOfStock)
                @if($item->has_variants)
                <a href="{{ $productUrl }}" class="sport-card__cta">Elegir opciones</a>
                @else
                <button type="button" class="sport-card__cta ec-btn-cart" data-ec-cart="{{ json_encode($item) }}">Agregar al carrito</button>
                @endif
            @endif
        </div>
    </article>
</div>
@endforeach

@once
<style>
.sport-card { background:#fff; border-radius:0; overflow:hidden; transition:transform .2s; }
.sport-card:hover { transform:translateY(-4px); }
.sport-card__media { position:relative; aspect-ratio:1; background:#f5f5f5; overflow:hidden; }
.sport-card__img-link { display:block; width:100%; height:100%; }
.sport-card__img { width:100%; height:100%; object-fit:cover; transition:opacity .3s; }
.sport-card__img--hover { position:absolute; inset:0; opacity:0; }
.sport-card__img-link:hover .sport-card__img--hover { opacity:1; }
.sport-card__img-link:hover .sport-card__img--primary { opacity:0; }
.sport-badge { position:absolute; z-index:2; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; padding:4px 10px; }
.sport-badge--new { top:10px; left:10px; background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; }
.sport-badge--oos { top:10px; left:10px; background:#000; color:#fff; }
.sport-badge--opt { bottom:10px; left:10px; background:rgba(0,0,0,.75); color:#fff; }
.sport-card__wish { position:absolute; top:10px; right:10px; background:rgba(255,255,255,.9); border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#999; opacity:0; transition:opacity .2s; }
.sport-card:hover .sport-card__wish { opacity:1; }
.sport-card__wish:hover { color:#ef4444; }
.sport-card__body { padding:.75rem .5rem; }
.sport-card__cat { font-size:10px; font-weight:700; color:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); text-transform:uppercase; letter-spacing:.04em; }
.sport-card__title { font-size:14px; font-weight:700; margin:4px 0; line-height:1.3; }
.sport-card__title a { color:#111; text-decoration:none; }
.sport-card__title a:hover { text-decoration:underline; }
.sport-card__price { font-size:16px; font-weight:800; color:#111; margin-bottom:6px; }
.sport-card__cta { display:block; width:100%; padding:10px; background:#111; color:#fff; border:none; text-align:center; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.1em; text-decoration:none; cursor:pointer; transition:background .15s; }
.sport-card__cta:hover { background:hsl(var(--primary-h),var(--primary-s),var(--primary-l)); color:#fff; text-decoration:none; }
.sport-card--oos .sport-card__media { opacity:.4; }
.sport-card--oos .sport-card__price { color:#999; }
@media(max-width:575px) { .sport-card__title{font-size:12px;} .sport-card__price{font-size:14px;} }
</style>
@endonce
