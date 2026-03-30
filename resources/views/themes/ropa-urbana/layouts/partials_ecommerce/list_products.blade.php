{{-- THEME MODA URBANA — Grid estilo Shein: cards con descuento, hover zoom, badge colores vivos --}}
@php
    $configuration = \App\Models\Tenant\Configuration::first();
    if (!function_exists('stock')) { function stock($item, $config) { if (!$config) return false; $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total <= 0; } }
    if (!function_exists('stockCount')) { function stockCount($item) { $total = 0; foreach ($item->warehouses as $wh) { $total += $wh->stock; } return $total; } }
@endphp
@foreach ($dataPaginate as $item)
@php
    $outOfStock = stock($item, $configuration); $totalStock = stockCount($item);
    if ($item->has_variants && $item->relationLoaded('variants')) { $variantStock = $item->variants->sum('stock'); $outOfStock = $variantStock <= 0; $totalStock = $variantStock; }
    $isNew = $item->created_at && $item->created_at->diffInDays(now()) <= 14;
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath = $defaultImage === 'imagen-no-disponible.jpg' ? asset('logo/imagen-no-disponible.jpg') : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath = $hasRealImage ? asset('storage/uploads/items/' . $item->image) : $defaultPath;
    $hoverImage = null;
    if ($item->relationLoaded('images') && $item->images->isNotEmpty()) { $f = $item->images->first(); if ($f && $f->image && $f->image !== 'imagen-no-disponible.jpg') $hoverImage = asset('storage/uploads/items/' . $f->image); }
    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $symbol = $item->currency_type['symbol'] ?? 'S/'; $price = number_format($item->sale_unit_price, 2);
@endphp
<div class="col-6 col-md-4 col-lg-3 mb-3 product-col-item">
    <article class="urb-card{{ $outOfStock ? ' urb-card--oos' : '' }}">
        <div class="urb-card__media">
            <a href="{{ $productUrl }}" class="urb-card__link">
                @if($hasRealImage)<img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $imagePath }}" alt="{{ $item->description }}" loading="lazy" class="urb-card__img urb-card__img--main ec-img-lazy">
                @else<img src="{{ $defaultPath }}" alt="{{ $item->description }}" class="urb-card__img urb-card__img--main">@endif
                @if($hoverImage)<img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}" data-src="{{ $hoverImage }}" alt="{{ $item->description }}" class="urb-card__img urb-card__img--hover ec-img-lazy" aria-hidden="true">@endif
            </a>
            @if($isNew && !$outOfStock)<span class="urb-badge urb-badge--new">NEW</span>@endif
            @if($outOfStock)<span class="urb-badge urb-badge--oos">Agotado</span>@endif
            @if($item->has_variants)<span class="urb-badge urb-badge--colors">+Colores</span>@endif
            <button type="button" class="urb-card__wish ec-btn-wishlist" data-wishlist-id="{{ $item->id }}" aria-pressed="false"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>
            @if(!$outOfStock)
            <div class="urb-card__actions">
                @if($item->has_variants)<a href="{{ $productUrl }}" class="urb-card__btn">Ver opciones</a>
                @else<button type="button" class="urb-card__btn ec-btn-cart" data-ec-cart="{{ json_encode($item) }}"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg> Agregar</button>@endif
            </div>
            @endif
        </div>
        <div class="urb-card__info">
            @if($item->category)<span class="urb-card__cat">{{ $item->category->name }}</span>@endif
            <h2 class="urb-card__title"><a href="{{ $productUrl }}">{{ \Illuminate\Support\Str::limit($item->description, 45) }}</a></h2>
            <div class="urb-card__price"><span class="urb-card__now">{{ $symbol }} {{ $price }}</span></div>
        </div>
    </article>
</div>
@endforeach
@once
<style>
.urb-card{border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:transform .2s,box-shadow .2s}
.urb-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.1)}
.urb-card__media{position:relative;aspect-ratio:3/4;overflow:hidden;background:#fafafa}
.urb-card__link{display:block;width:100%;height:100%}
.urb-card__img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease,opacity .3s}
.urb-card__img--hover{position:absolute;inset:0;opacity:0}
.urb-card__link:hover .urb-card__img--hover{opacity:1}
.urb-card__link:hover .urb-card__img--main{transform:scale(1.08)}
.urb-badge{position:absolute;z-index:2;font-size:10px;font-weight:800;padding:3px 10px;border-radius:4px;text-transform:uppercase;letter-spacing:.04em}
.urb-badge--new{top:8px;left:8px;background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff}
.urb-badge--oos{top:8px;left:8px;background:#1f2937;color:#fff}
.urb-badge--colors{bottom:8px;left:8px;background:rgba(255,255,255,.95);color:#374151;border:1px solid #e5e7eb}
.urb-card__wish{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.9);border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#d1d5db;opacity:0;transition:opacity .2s,color .15s,transform .15s}
.urb-card:hover .urb-card__wish{opacity:1}
.urb-card__wish:hover{color:#ef4444;transform:scale(1.15)}
.urb-card__actions{position:absolute;bottom:0;left:0;right:0;padding:8px;transform:translateY(100%);transition:transform .25s ease}
.urb-card:hover .urb-card__actions{transform:translateY(0)}
.urb-card__btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;background:hsl(var(--primary-h),var(--primary-s),var(--primary-l));color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;text-transform:uppercase;letter-spacing:.04em;transition:filter .15s}
.urb-card__btn:hover{filter:brightness(.9);color:#fff;text-decoration:none}
.urb-card__info{padding:10px 12px}
.urb-card__cat{font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em}
.urb-card__title{font-size:13px;font-weight:600;margin:3px 0;line-height:1.35;height:2.7em;overflow:hidden}
.urb-card__title a{color:#1f2937;text-decoration:none}.urb-card__title a:hover{color:hsl(var(--primary-h),var(--primary-s),var(--primary-l))}
.urb-card__now{font-size:16px;font-weight:800;color:#1f2937}
.urb-card--oos .urb-card__media{opacity:.45}
.urb-card--oos .urb-card__now{color:#9ca3af}
@media(max-width:575px){.urb-card__title{font-size:12px}.urb-card__now{font-size:14px}}

/* GSAP animations initial state */
.urb-card{opacity:0;transform:translateY(30px)}
.urb-card.gsap-ready{opacity:1;transform:none}
</style>

{{-- GSAP: Animación de entrada escalonada --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
    if(typeof gsap==='undefined'||typeof ScrollTrigger==='undefined') {
        // Sin GSAP, mostrar todo inmediatamente
        document.querySelectorAll('.urb-card').forEach(function(c){c.classList.add('gsap-ready')});
        return;
    }
    gsap.registerPlugin(ScrollTrigger);

    // Animar cards al entrar en viewport
    var cards = document.querySelectorAll('.urb-card');
    if(cards.length){
        gsap.to(cards, {
            opacity: 1,
            y: 0,
            duration: 0.6,
            stagger: 0.08,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: cards[0].closest('.row, .ec-filter-results'),
                start: 'top 90%',
            },
            onComplete: function(){ cards.forEach(function(c){c.classList.add('gsap-ready')}); }
        });
    }
});
</script>
@endonce
