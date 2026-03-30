{{-- THEME LUJO — Grid estilo Gucci: elegante, espacio amplio, tipografía serif, tonos dorados --}}
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
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath = $defaultImage === 'imagen-no-disponible.jpg' ? asset('logo/imagen-no-disponible.jpg') : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath = $hasRealImage ? asset('storage/uploads/items/' . $item->image) : $defaultPath;
    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $symbol = $item->currency_type['symbol'] ?? 'S/';
    $price = number_format($item->sale_unit_price, 2);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-5 product-col-item">
    <article class="lux-card{{ $outOfStock ? ' lux-card--oos' : '' }}">
        <div class="lux-card__media">
            <a href="{{ $productUrl }}">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="lux-card__img ec-img-lazy">
                @else
                <img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="lux-card__img">
                @endif
            </a>
            @if($outOfStock)
            <span class="lux-badge lux-badge--oos">Agotado</span>
            @endif
            <button type="button" class="lux-card__wish ec-btn-wishlist" data-wishlist-id="{{ $item->id }}" aria-pressed="false" title="Favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
            @if(!$outOfStock)
            <div class="lux-card__hover">
                @if($item->has_variants)
                <a href="{{ $productUrl }}" class="lux-card__hover-btn">Descubrir</a>
                @else
                <button type="button" class="lux-card__hover-btn ec-btn-cart" data-ec-cart="{{ json_encode($item) }}">Añadir a la bolsa</button>
                @endif
            </div>
            @endif
        </div>
        <div class="lux-card__body">
            <h2 class="lux-card__title"><a href="{{ $productUrl }}">{{ $item->description }}</a></h2>
            <div class="lux-card__price">{{ $symbol }} {{ $price }}</div>
        </div>
    </article>
</div>
@endforeach

@once
<style>
.lux-card { text-align:center; }
.lux-card__media { position:relative; aspect-ratio:3/4; background:#f5f0eb; overflow:hidden; }
.lux-card__img { width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
.lux-card:hover .lux-card__img { transform:scale(1.04); }
.lux-badge { position:absolute; top:12px; left:12px; font-family:'Playfair Display',serif; font-size:11px; font-weight:500; letter-spacing:.08em; padding:4px 12px; z-index:2; }
.lux-badge--oos { background:rgba(12,10,9,.7); color:#a18248; }
.lux-card__wish { position:absolute; top:12px; right:12px; background:rgba(255,255,255,.85); border:none; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#a18248; opacity:0; transition:opacity .25s; }
.lux-card:hover .lux-card__wish { opacity:1; }
.lux-card__wish:hover { color:#0c0a09; }
.lux-card__hover { position:absolute; bottom:0; left:0; right:0; padding:12px; transform:translateY(100%); transition:transform .3s ease; z-index:2; }
.lux-card:hover .lux-card__hover { transform:translateY(0); }
.lux-card__hover-btn { display:block; width:100%; padding:12px; background:#0c0a09; color:#a18248; border:1px solid #a18248; text-align:center; font-family:'Playfair Display',serif; font-size:12px; font-weight:500; letter-spacing:.15em; text-transform:uppercase; text-decoration:none; cursor:pointer; transition:all .2s; }
.lux-card__hover-btn:hover { background:#a18248; color:#0c0a09; text-decoration:none; }
.lux-card__body { padding:1rem .5rem; }
.lux-card__title { font-family:'Playfair Display',Georgia,serif; font-size:14px; font-weight:500; letter-spacing:.03em; margin:0 0 .35rem; line-height:1.4; }
.lux-card__title a { color:#292524; text-decoration:none; }
.lux-card__title a:hover { color:#a18248; }
.lux-card__price { font-size:14px; color:#78716c; letter-spacing:.03em; }
.lux-card--oos .lux-card__media { opacity:.5; }
.lux-card--oos .lux-card__title a { color:#a8a29e; }
.lux-card--oos .lux-card__price { color:#d6d3d1; }
@media(max-width:575px) { .lux-card__title{font-size:12px;} .lux-card__price{font-size:13px;} }
</style>
@endonce
