{{-- THEME MODA ELEGANTE — Grid ultra minimalista: sin bordes, mucho espacio, tipografía delgada --}}
@php
    $configuration = \App\Models\Tenant\Configuration::first();
    if (!function_exists('stock')) { function stock($item, $config) { if (!$config) return false; $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total <= 0; } }
    if (!function_exists('stockCount')) { function stockCount($item) { $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total; } }
@endphp
@foreach ($dataPaginate as $item)
@php
    $outOfStock = stock($item, $configuration); $totalStock = stockCount($item);
    if ($item->has_variants && $item->relationLoaded('variants')) { $variantStock = $item->variants->sum('stock'); $outOfStock = $variantStock <= 0; $totalStock = $variantStock; }
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath = $defaultImage === 'imagen-no-disponible.jpg' ? asset('logo/imagen-no-disponible.jpg') : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath = $hasRealImage ? asset('storage/uploads/items/' . $item->image) : $defaultPath;
    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $symbol = $item->currency_type['symbol'] ?? 'S/'; $price = number_format($item->sale_unit_price, 2);
@endphp
<div class="col-6 col-md-4 col-lg-3 mb-5 product-col-item">
    <article class="eleg-card{{ $outOfStock ? ' eleg-card--oos' : '' }}">
        <div class="eleg-card__media">
            <a href="{{ $productUrl }}">
                @if($hasRealImage)<img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="eleg-card__img ec-img-lazy">
                @else<img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="eleg-card__img">@endif
            </a>
            @if(!$outOfStock)
            <a href="{{ $productUrl }}" class="eleg-card__overlay">
                <span>{{ $item->has_variants ? 'Ver opciones' : 'Ver producto' }}</span>
            </a>
            @endif
        </div>
        <div class="eleg-card__info">
            <h2 class="eleg-card__title"><a href="{{ $productUrl }}">{{ $item->description }}</a></h2>
            <span class="eleg-card__price">{{ $symbol }} {{ $price }}</span>
        </div>
    </article>
</div>
@endforeach
@once
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&display=swap');
.eleg-card{text-align:center;font-family:'Inter',sans-serif}
.eleg-card__media{position:relative;aspect-ratio:2/3;background:#f7f7f7;overflow:hidden}
.eleg-card__img{width:100%;height:100%;object-fit:cover;transition:transform .8s cubic-bezier(.25,.46,.45,.94)}
.eleg-card:hover .eleg-card__img{transform:scale(1.03)}
.eleg-card__overlay{position:absolute;bottom:0;left:0;right:0;padding:16px;display:flex;justify-content:center;opacity:0;transition:opacity .3s}
.eleg-card:hover .eleg-card__overlay{opacity:1}
.eleg-card__overlay span{padding:8px 24px;background:#fff;color:#111;font-size:11px;font-weight:400;letter-spacing:.15em;text-transform:uppercase;text-decoration:none;border:1px solid #111;transition:all .2s}
.eleg-card__overlay span:hover{background:#111;color:#fff}
.eleg-card__info{padding:1rem .5rem}
.eleg-card__title{font-size:13px;font-weight:300;letter-spacing:.03em;line-height:1.5;margin:0 0 6px}
.eleg-card__title a{color:#333;text-decoration:none}.eleg-card__title a:hover{color:#111}
.eleg-card__price{font-size:13px;font-weight:400;color:#666;letter-spacing:.02em}
.eleg-card--oos .eleg-card__media{opacity:.4}
.eleg-card--oos .eleg-card__price{color:#bbb}
@media(max-width:575px){.eleg-card__title{font-size:12px}.eleg-card__price{font-size:12px}}
</style>
@endonce
