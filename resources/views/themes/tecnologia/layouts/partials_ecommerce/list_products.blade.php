{{--
    THEME TECNOLOGÍA — Grid de productos
    Cards con specs visibles, aspecto cuadrado, badges técnicos
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
    $delay = min($loop->iteration * 40, 400);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item" style="animation-delay:{{ $delay }}ms">
    <article class="tech-card{{ $outOfStock ? ' tech-card--oos' : '' }}">
        <div class="tech-card__media">
            <a href="{{ $productUrl }}">
                @if($hasRealImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="tech-card__img ec-img-lazy">
                @else
                <img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="tech-card__img">
                @endif
            </a>
            @if($outOfStock)<span class="tech-badge tech-badge--oos">Agotado</span>
            @elseif($isNew)<span class="tech-badge tech-badge--new">Nuevo</span>@endif
            @if($item->has_variants)<span class="tech-badge tech-badge--var">{{ $item->has_variants ? 'Opciones' : '' }}</span>@endif
            <button type="button" class="tech-card__wish ec-btn-wishlist" data-wishlist-id="{{ $item->id }}" aria-pressed="false" title="Favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </div>
        <div class="tech-card__body">
            @if($item->category)<span class="tech-card__cat">{{ $item->category->name }}</span>@endif
            <h2 class="tech-card__title"><a href="{{ $productUrl }}">{{ $item->description }}</a></h2>
            <div class="tech-card__price">
                <span class="tech-card__price-now">{{ $symbol }} {{ $price }}</span>
            </div>
            @if(!$outOfStock)
                @if($item->has_variants)
                <a href="{{ $productUrl }}" class="tech-card__cta">Ver opciones</a>
                @else
                <button type="button" class="tech-card__cta ec-btn-cart" data-ec-cart="{{ json_encode($item) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                    Agregar
                </button>
                @endif
            @endif
        </div>
    </article>
</div>
@endforeach

@once
<style>
.tech-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; transition: box-shadow .2s; }
.tech-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.tech-card__media { position: relative; aspect-ratio: 1; background: #f9fafb; overflow: hidden; }
.tech-card__img { width: 100%; height: 100%; object-fit: contain; padding: 12px; }
.tech-badge { position: absolute; top: 8px; left: 8px; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; z-index: 2; }
.tech-badge--new { background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff; }
.tech-badge--oos { background: #fee2e2; color: #dc2626; }
.tech-badge--var { background: #dbeafe; color: #2563eb; top: auto; bottom: 8px; }
.tech-card__wish { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,.9); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #9ca3af; opacity: 0; transition: opacity .2s; }
.tech-card:hover .tech-card__wish { opacity: 1; }
.tech-card__wish:hover { color: #ef4444; }
.tech-card__body { padding: .75rem; }
.tech-card__cat { font-size: 10px; color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.tech-card__title { font-size: 13px; font-weight: 500; line-height: 1.3; margin: 4px 0; height: 2.6em; overflow: hidden; }
.tech-card__title a { color: #111827; text-decoration: none; }
.tech-card__title a:hover { color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }
.tech-card__price-now { font-size: 16px; font-weight: 700; color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }
.tech-card__cta { display: flex; align-items: center; justify-content: center; gap: .3rem; width: 100%; padding: 8px; margin-top: 8px; background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; transition: filter .18s; }
.tech-card__cta:hover { filter: brightness(.9); color: #fff; text-decoration: none; }
.tech-card--oos .tech-card__media { opacity: .5; }
</style>
@endonce
