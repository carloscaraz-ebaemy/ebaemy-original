{{--
    THEME ALIMENTOS — Grid de productos
    Cards redondeadas, colores cálidos, badges de envío, estilo delivery
--}}
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
    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $symbol = $item->currency_type['symbol'] ?? 'S/';
    $price = number_format($item->sale_unit_price, 2);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item">
    <article class="food-card{{ $outOfStock ? ' food-card--oos' : '' }}">
        <div class="food-card__media">
            <a href="{{ $productUrl }}">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="food-card__img ec-img-lazy">
                @else
                <img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="food-card__img">
                @endif
            </a>
            @if($isNew && !$outOfStock)<span class="food-badge food-badge--new">Nuevo</span>@endif
            @if($outOfStock)<span class="food-badge food-badge--oos">No disponible</span>@endif
        </div>
        <div class="food-card__body">
            @if($item->category)<span class="food-card__cat">{{ $item->category->name }}</span>@endif
            <h2 class="food-card__title"><a href="{{ $productUrl }}">{{ $item->description }}</a></h2>
            <div class="food-card__bottom">
                <span class="food-card__price">{{ $symbol }} {{ $price }}</span>
                @if(!$outOfStock)
                    @if($item->has_variants)
                    <a href="{{ $productUrl }}" class="food-card__add" title="Ver opciones">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    </a>
                    @else
                    <button type="button" class="food-card__add ec-btn-cart" data-ec-cart="{{ json_encode($item) }}" title="Agregar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    </button>
                    @endif
                @endif
            </div>
        </div>
    </article>
</div>
@endforeach

@once
<style>
.food-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); transition: transform .2s, box-shadow .2s; }
.food-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.food-card__media { position: relative; aspect-ratio: 1; background: #fef7ed; overflow: hidden; }
.food-card__img { width: 100%; height: 100%; object-fit: cover; }
.food-badge { position: absolute; top: 10px; left: 10px; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 12px; z-index: 2; }
.food-badge--new { background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff; }
.food-badge--oos { background: rgba(0,0,0,.6); color: #fff; }
.food-card__body { padding: .75rem; }
.food-card__cat { font-size: 10px; color: #9ca3af; font-weight: 600; text-transform: uppercase; }
.food-card__title { font-size: 14px; font-weight: 600; margin: 4px 0 8px; line-height: 1.3; }
.food-card__title a { color: #1f2937; text-decoration: none; }
.food-card__bottom { display: flex; align-items: center; justify-content: space-between; }
.food-card__price { font-size: 16px; font-weight: 700; color: #1f2937; }
.food-card__add { width: 36px; height: 36px; border-radius: 50%; background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: transform .15s; flex-shrink: 0; }
.food-card__add:hover { transform: scale(1.1); color: #fff; }
.food-card--oos .food-card__media { opacity: .5; }
.food-card--oos .food-card__price { color: #9ca3af; }
</style>
@endonce
