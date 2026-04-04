@extends('ecommerce::layouts.layout_ecommerce_item.record')

@php
    $configurationModel    = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = \App\Models\Tenant\ConfigurationEcommerce::first();
    $company               = \App\Models\Tenant\Company::first();
    $phoneWhatsapp         = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $defaultImage          = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath      = $defaultImage === 'imagen-no-disponible.jpg'
                             ? asset('logo/imagen-no-disponible.jpg')
                             : asset('storage/defaults/' . $defaultImage);
    $mainImagePath         = ($record->image && $record->image !== 'imagen-no-disponible.jpg')
                             ? asset('storage/uploads/items/'.$record->image)
                             : $defaultImagePath;
    $productUrl            = route('tenant.ecommerce.item', ['slug' => $record->slug ?: $record->id]);
    $shortDesc             = \Illuminate\Support\Str::limit(strip_tags($record->name ?: $record->description), 155);
@endphp

{{-- ── SEO META DINÁMICOS POR PRODUCTO ─────────────── --}}
@section('page_title', $record->description . ' | ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', $shortDesc)
@section('meta_keywords', $record->description . ($record->category ? ', ' . $record->category->name : '') . ', ' . ($company->name ?? ''))
@section('og_type', 'product')
@section('og_title', $record->description)
@section('og_description', $shortDesc)
@section('og_image', $mainImagePath)
@section('canonical_url', $productUrl)

{{-- ── BREADCRUMB SCHEMA + VISIBLE ─────────────────── --}}
@section('breadcrumb_item')
    @if($record->category)
    <li class="breadcrumb-item">
        <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($record->category->name)) }}">
            {{ $record->category->name }}
        </a>
    </li>
    @endif
    <li class="breadcrumb-item active" aria-current="page">{{ $record->description }}</li>
@endsection

{{-- ── SCHEMA.ORG PRODUCT JSON-LD ──────────────────── --}}
@section('schema_product')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ addslashes($record->description) }}",
    "description": "{{ addslashes(strip_tags($record->name ?: $record->description)) }}",
    "sku": "{{ $record->internal_id ?? $record->id }}",
    "image": [
        "{{ $mainImagePath }}"
        @foreach($record->images as $img)
        @if($img->image && $img->image !== 'imagen-no-disponible.jpg')
        ,"{{ asset('storage/uploads/items/' . $img->image) }}"
        @endif
        @endforeach
    ],
    "brand": {
        "@type": "Brand",
        "name": "{{ addslashes($company->trade_name ?? $company->name ?? 'Tienda') }}"
    },
    @if($record->category)
    "category": "{{ addslashes($record->category->name) }}",
    @endif
    "offers": {
        "@type": "Offer",
        "url": "{{ $productUrl }}",
        "priceCurrency": "{{ $record->currency_type_id ?? 'PEN' }}",
        "price": "{{ number_format($record->sale_unit_price, 2, '.', '') }}",
        "availability": "{{ $record->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
        "itemCondition": "https://schema.org/NewCondition",
        "seller": {
            "@type": "Organization",
            "name": "{{ addslashes($company->name ?? 'Tienda Online') }}"
        }
    }
}
</script>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Inicio",
            "item": "{{ url('/ecommerce') }}"
        }
        @if($record->category)
        ,{
            "@type": "ListItem",
            "position": 2,
            "name": "{{ addslashes($record->category->name) }}",
            "item": "{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($record->category->name)) }}"
        }
        ,{
            "@type": "ListItem",
            "position": 3,
            "name": "{{ addslashes($record->description) }}",
            "item": "{{ $productUrl }}"
        }
        @else
        ,{
            "@type": "ListItem",
            "position": 2,
            "name": "{{ addslashes($record->description) }}",
            "item": "{{ $productUrl }}"
        }
        @endif
    ]
}
</script>
@endsection

@section('content')

<div class="product-single-container product-single-default">
    <div class="row">
        <div class="col-lg-7 col-md-6 product-single-gallery">
            <div class="product-slider-container product-item">
                <div class="product-single-carousel owl-carousel owl-theme">
                    <div class="product-item">
                        <img class="product-single-image" src="{{ $mainImagePath }}"
                            data-zoom-image="{{ $mainImagePath }}" />
                            
                    </div>
                    @foreach($record->images as $row)

                        <div class="product-item">
                            @php
                                $loopImagePath = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                                    ? asset('storage/uploads/items/'.$row->image)
                                    : $defaultImagePath;
                            @endphp
                            <img class="product-single-image" src="{{ $loopImagePath }}"
                                 data-zoom-image="{{ $loopImagePath }}" alt="{{ $record->description }}" />
                        </div>

                    @endforeach
                    <!--<div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-2.jpg"
                            data-zoom-image="assets/images/products/zoom/product-2-big.jpg" />
                    </div>
                    <div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-3.jpg"
                            data-zoom-image="assets/images/products/zoom/product-3-big.jpg" />
                    </div>
                    <div class="product-item">
                        <img class="product-single-image" src="assets/images/products/zoom/product-4.jpg"
                            data-zoom-image="assets/images/products/zoom/product-4-big.jpg" />
                    </div>-->
                </div>
                <!-- End .product-single-carousel -->
                <span class="prod-full-screen">
                    <i class="icon-plus"></i>
                </span>
            </div>
            <div class="prod-thumbnail row owl-dots" id='carousel-custom-dots'>
                <div class="col-3 owl-dot">
                    <img src="{{ $mainImagePath }}" alt="{{ $record->description }}" />
                </div>
                @foreach($record->images as $row)
                    <div class="col-3 owl-dot">
                        @php
                            $thumbImagePath = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/'.$row->image)
                                : $defaultImagePath;
                        @endphp
                        <img src="{{ $thumbImagePath }}" alt="{{ $record->description }}" />
                    </div>
                @endforeach
                <!--<div class="col-3 owl-dot">
                    <img src="assets/images/products/zoom/product-2.jpg" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="assets/images/products/zoom/product-3.jpg" />
                </div>
                <div class="col-3 owl-dot">
                    <img src="assets/images/products/zoom/product-4.jpg" />
                </div> -->
            </div>
        </div><!-- End .col-lg-7 -->

        <div class="col-lg-5 col-md-6">
            <div class="product-single-details">

                {{-- Categoría --}}
                @if($record->category)
                <p class="product-category mb-1">
                    <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($record->category->name)) }}"
                       style="color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">
                        {{ $record->category->name }}
                    </a>
                </p>
                @endif

                <h1 class="product-title" itemprop="name">{{ $record->description }}</h1>

                {{-- Rating y reviews (estilo Falabella) --}}
                <div class="ec-product-rating" id="ec-product-rating">
                    <div class="ec-product-rating__stars" id="ec-inline-stars"></div>
                    <a href="#product-reviews-content" class="ec-product-rating__count" id="ec-inline-rating-link"
                       onclick="document.getElementById('product-tab-reviews').click()"></a>
                </div>

                {{-- Precio — muestra descuento si hay Flash Sale o Pack --}}
                @php
                    $hasDiscount = !empty($record->original_price);
                    $discountPct = $hasDiscount ? round((1 - $record->sale_unit_price / $record->original_price) * 100) : 0;
                @endphp
                <div class="price-box" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                    <meta itemprop="priceCurrency" content="{{ $record->currency_type_id ?? 'PEN' }}">
                    <meta itemprop="price" content="{{ $record->sale_unit_price }}">

                    @if($hasDiscount)
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
                        <span style="text-decoration:line-through;color:#9ca3af;font-size:18px;font-weight:400">
                            {{ $record->currency_type['symbol'] }} {{ number_format($record->original_price, 2) }}
                        </span>
                        <span style="background:#e53e3e;color:#fff;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px">
                            -{{ $discountPct }}%
                        </span>
                        @if($record->is_set)
                        <span style="background:#7c3aed;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">PACK</span>
                        @endif
                    </div>
                    @endif

                    <span class="product-price" style="font-size:32px;font-weight:800;{{ $hasDiscount ? 'color:#e53e3e' : 'color:#1a1a1a' }}">
                        {{ $record->currency_type['symbol'] }} {{ number_format($record->sale_unit_price, 2) }}
                    </span>

                    @if($hasDiscount)
                    <p style="color:#16a34a;font-size:13px;font-weight:600;margin:4px 0 0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:3px">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Ahorras {{ $record->currency_type['symbol'] }} {{ number_format($record->original_price - $record->sale_unit_price, 2) }}
                    </p>
                    @endif

                    @if(!$hasDiscount && $record->is_set)
                    <span style="display:inline-block;background:#7c3aed;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin-top:6px">PACK ESPECIAL</span>
                    @endif
                </div>

                {{-- Countdown Flash Sale --}}
                @if($record->flash_ends_at)
                <div id="ec-bl-countdown" data-ends="{{ $record->flash_ends_at->timestamp * 1000 }}"
                     style="margin:12px 0;padding:10px 16px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1px solid #fed7aa;border-radius:10px;display:inline-flex;align-items:center;gap:10px">
                    <div style="background:#ea580c;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <div>
                        <span style="font-size:12px;color:#9a3412;font-weight:600;display:block">OFERTA FLASH</span>
                        <div style="display:flex;align-items:center;gap:4px;margin-top:2px">
                            <span style="background:#ea580c;color:#fff;font-size:14px;font-weight:700;padding:2px 7px;border-radius:5px;min-width:28px;text-align:center" id="ec-cd-h">00</span>
                            <span style="color:#ea580c;font-weight:700">:</span>
                            <span style="background:#ea580c;color:#fff;font-size:14px;font-weight:700;padding:2px 7px;border-radius:5px;min-width:28px;text-align:center" id="ec-cd-m">00</span>
                            <span style="color:#ea580c;font-weight:700">:</span>
                            <span style="background:#ea580c;color:#fff;font-size:14px;font-weight:700;padding:2px 7px;border-radius:5px;min-width:28px;text-align:center" id="ec-cd-s">00</span>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Stock --}}
                @php
                    $totalStock = $record->stock ?? 0;
                    if (!empty($record->has_variants) && !empty($record->item_variants)) {
                        $totalStock = collect($record->item_variants)->where('is_active', true)->sum('stock');
                    }
                @endphp
                <div class="product-desc">
                    <p class="product-stock">
                        Disponible: <span id="ec-stock-num">{{ number_format($totalStock, 0) }}</span>
                        @if($totalStock > 0)
                            <span class="alert-stock" id="ec-stock-badge" role="alert">En stock</span>
                        @else
                            <span class="alert-sin-stock" id="ec-stock-badge" role="alert">Sin stock</span>
                        @endif
                        @if($record->has_variants)
                            <span id="ec-stock-hint" style="font-size:12px;color:#9ca3af;display:block;margin-top:2px">Selecciona una opción para ver disponibilidad</span>
                        @endif
                    </p>
                    @if($record->name)
                    <p>{{ $record->name }}</p>
                    @endif
                </div>
                {{-- Indicador de pocas unidades --}}
                <div id="ec-low-stock" style="display:none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                    <span id="ec-low-stock-txt"></span>
                </div>

                {{-- ── Variantes reales del sistema (estilo Falabella) ──────── --}}
                @if($record->has_variants && count($record->item_options ?? []))
                @php
                    // Mapear imágenes de variantes a cada valor de opción de color
                    $variantImageMap = [];
                    foreach ($record->item_variants as $v) {
                        if (!empty($v['image'])) {
                            foreach ($v['option_value_ids'] as $ovId) {
                                if (!isset($variantImageMap[$ovId])) {
                                    $variantImageMap[$ovId] = asset('storage/uploads/items/' . $v['image']);
                                }
                            }
                        }
                    }
                @endphp
                <div class="ec-variant-selectors ec-variant-selectors--falabella" id="ec-variant-selectors">
                    @foreach($record->item_options as $opt)
                    @php
                        $isColor = stripos($opt['name'], 'color') !== false || stripos($opt['name'], 'colour') !== false;
                        $hasImages = $isColor && collect($opt['values'])->contains(fn($v) => isset($variantImageMap[$v['id']]));
                    @endphp
                    <div class="ec-variant-group" data-option-id="{{ $opt['id'] }}">
                        <div class="ec-variant-label">
                            <span class="ec-variant-label__name">{{ $opt['name'] }}:</span>
                            <span class="ec-variant-label__val" id="ec-optval-{{ $opt['id'] }}"></span>
                        </div>
                        <div class="ec-variant-options {{ $hasImages ? 'ec-variant-options--thumbs' : ($isColor ? 'ec-variant-options--colors' : 'ec-variant-options--sizes') }}">
                            @foreach($opt['values'] as $val)
                            @php
                                $cssColor = $val['color_hex'] ?? null;
                                $thumbUrl = $variantImageMap[$val['id']] ?? null;
                                $lightColors = ['#f9fafb','#fef3c7','#f0e6c8','#d6c3a8','#fef9c3'];
                                $isLight = $cssColor && in_array($cssColor, $lightColors);
                            @endphp
                            @if($hasImages)
                            {{-- Color con miniatura de imagen (estilo Falabella) --}}
                            <button type="button"
                                    class="ec-variant-opt ec-variant-opt--thumb ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false">
                                <img src="{{ $thumbUrl ?? $defaultImagePath }}"
                                     alt="{{ $val['value'] }}"
                                     loading="lazy">
                            </button>
                            @elseif($isColor && $cssColor)
                            {{-- Color con swatch circular (fallback si no hay imágenes) --}}
                            <button type="button"
                                    class="ec-variant-opt ec-variant-opt--color ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    @if($cssColor) style="--swatch:{{ $cssColor }}" @endif
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false">
                                <span class="ec-swatch {{ $isLight ? 'ec-swatch--light' : '' }}"
                                      style="background:{{ $cssColor }}"></span>
                            </button>
                            @else
                            {{-- Talla / otro: botón rectangular (estilo Falabella) --}}
                            <button type="button"
                                    class="ec-variant-opt ec-variant-opt--size ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false">
                                {{ $val['value'] }}
                            </button>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                <div id="ec-variant-resolved" style="display:none;margin:8px 0;padding:8px 12px;background:#f0f9eb;border-radius:8px;font-size:13px;color:#67C23A;font-weight:600;"></div>
                <p class="ec-variant-required" id="ec-variant-required" style="display:none">
                    Por favor selecciona todas las opciones antes de agregar al carrito.
                </p>
                <script>
                (function(){
                    var variants  = @json($record->item_variants);
                    var options   = @json($record->item_options);
                    var basePrice = {{ $record->sale_unit_price }};
                    var symbol    = '{{ $record->currency_type_symbol }}';
                    var selected  = {}; // optionId -> valueId

                    // ── Matriz de disponibilidad ─────────────────────────────────────
                    // Dado el estado actual de selección, ¿puede un valor ser seleccionado?
                    function canSelect(optionId, valueId){
                        var test = Object.assign({}, selected);
                        test[String(optionId)] = valueId;
                        return variants.some(function(v){
                            if(!v.is_active || (v.stock || 0) <= 0) return false;
                            return Object.keys(test).every(function(oid){
                                return (v.option_value_ids || []).indexOf(test[oid]) !== -1;
                            });
                        });
                    }

                    function refreshAvailability(){
                        document.querySelectorAll('.ec-rv-opt').forEach(function(btn){
                            var optId = btn.getAttribute('data-option-id');
                            var valId = parseInt(btn.getAttribute('data-value-id'));
                            var avail = canSelect(optId, valId);
                            btn.disabled = !avail;
                            btn.classList.toggle('ec-variant-opt--oos', !avail);
                        });
                    }

                    // ── Click handler ────────────────────────────────────────────────
                    document.querySelectorAll('.ec-rv-opt').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            if(this.disabled) return;
                            var optId   = this.getAttribute('data-option-id');
                            var valId   = parseInt(this.getAttribute('data-value-id'));
                            var val     = this.getAttribute('data-val');
                            var labelId = this.getAttribute('data-label-id');

                            // Deselect siblings — el CSS maneja el estilo via aria-pressed
                            document.querySelectorAll('.ec-rv-opt[data-option-id="'+optId+'"]').forEach(function(b){
                                b.setAttribute('aria-pressed', 'false');
                            });
                            this.setAttribute('aria-pressed', 'true');

                            selected[optId] = valId;
                            var lbl = document.getElementById(labelId);
                            if(lbl) lbl.textContent = '— ' + val;

                            refreshAvailability();
                            resolveVariant();
                        });
                    });

                    // ── Resolver combinación actual ──────────────────────────────────
                    function resolveVariant(){
                        var reqEl = document.getElementById('ec-variant-required');
                        var resEl = document.getElementById('ec-variant-resolved');
                        var allSelected = options.every(function(opt){
                            return selected[String(opt.id)] !== undefined;
                        });
                        if(!allSelected){
                            setPriceDisplay(null);
                            if(resEl) resEl.style.display = 'none';
                            if(reqEl) reqEl.style.display = 'none';
                            window._ecSelectedVariant = null;
                            return;
                        }
                        var selIds = options.map(function(o){ return selected[String(o.id)]; })
                                           .sort(function(a,b){ return a-b; });
                        var found = variants.find(function(v){
                            var vIds = (v.option_value_ids||[]).slice().sort(function(a,b){return a-b;});
                            return JSON.stringify(vIds) === JSON.stringify(selIds);
                        });
                        if(found && found.is_active && (found.stock||0) > 0){
                            window._ecSelectedVariant = found;
                            setPriceDisplay(found.sale_unit_price != null ? parseFloat(found.sale_unit_price) : null);
                            updateStockUI(found.stock);
                            if(resEl){ resEl.style.display='block'; resEl.textContent='✓ '+found.display_name; }
                            if(reqEl) reqEl.style.display='none';
                            if(found.image) swapMainImage('/storage/uploads/items/'+found.image);
                        } else {
                            window._ecSelectedVariant = null;
                            if(resEl) resEl.style.display='none';
                            if(reqEl){
                                reqEl.style.display='block';
                                reqEl.textContent='⚠ '+(found ? 'Esta combinación no tiene stock.' : 'Combinación no disponible.');
                            }
                        }
                    }

                    // ── Precio con animación flash ───────────────────────────────────
                    function setPriceDisplay(price){
                        var el = document.querySelector('.product-price');
                        if(!el) return;
                        var p = (price != null) ? price : basePrice;
                        el.classList.add('ec-price-flash');
                        setTimeout(function(){
                            el.textContent = symbol + ' ' + p.toFixed(2);
                            el.classList.remove('ec-price-flash');
                        }, 130);
                    }

                    // ── Stock UI ─────────────────────────────────────────────────────
                    function updateStockUI(stock){
                        var s = Math.floor(stock || 0);
                        // Número
                        var numEl = document.getElementById('ec-stock-num');
                        if(numEl) numEl.textContent = s;
                        // Ocultar hint de "selecciona una opción"
                        var hint = document.getElementById('ec-stock-hint');
                        if(hint) hint.style.display = 'none';
                        // Badge En stock / Sin stock
                        var badge = document.getElementById('ec-stock-badge');
                        if(badge){
                            badge.className = s > 0 ? 'alert-stock' : 'alert-sin-stock';
                            badge.textContent = s > 0 ? 'En stock' : 'Sin stock';
                        }
                        // Indicador pocas unidades
                        var lowEl  = document.getElementById('ec-low-stock');
                        var lowTxt = document.getElementById('ec-low-stock-txt');
                        if(lowEl){
                            if(s > 0 && s <= 5){
                                if(lowTxt) lowTxt.textContent = '¡Solo quedan '+s+' unidades!';
                                lowEl.style.display = 'flex';
                            } else {
                                lowEl.style.display = 'none';
                            }
                        }
                        // Max del input de cantidad
                        var inp = document.getElementById('ec-qty-input');
                        if(inp){ inp.max = s; if(parseInt(inp.value) > s) inp.value = Math.max(1, s); }
                        // Mostrar/ocultar selector cantidad y botón carrito
                        var qtyWrap = document.getElementById('ec-qty-selector');
                        if(qtyWrap && qtyWrap.closest('.mb-3')) qtyWrap.closest('.mb-3').style.display = s > 0 ? '' : 'none';
                        var addBtn = document.getElementById('btn-add-to-cart');
                        if(addBtn) addBtn.style.display = s > 0 ? '' : 'none';
                        // Mostrar botón "Avisarme" si agotado
                        var notifyBtn = document.querySelector('.ec-btn-notify');
                        if(notifyBtn) notifyBtn.style.display = s > 0 ? 'none' : '';
                    }

                    // ── Swap imagen principal ────────────────────────────────────────
                    function swapMainImage(url){
                        var img = document.querySelector('.product-single-carousel .product-single-image');
                        if(!img || img.src.endsWith(url)) return;
                        img.style.transition = 'opacity .15s ease';
                        img.style.opacity = '0';
                        setTimeout(function(){
                            img.src = url;
                            if(img.dataset.zoomImage !== undefined) img.dataset.zoomImage = url;
                            img.style.opacity = '1';
                        }, 150);
                        // Actualizar thumbnail activo
                        var thumb = document.querySelector('#carousel-custom-dots .owl-dot.active img');
                        if(thumb) thumb.src = url;
                    }

                    // ── NO auto-seleccionar (estilo Shopify) ──────────────────────
                    // El usuario debe elegir. Mostramos stock total y deshabilitamos
                    // opciones sin stock desde el inicio.
                    refreshAvailability();

                    // Estado inicial: ocultar qty selector y mostrar hint
                    var qtyWrapInit = document.getElementById('ec-qty-selector');
                    if(qtyWrapInit && qtyWrapInit.closest('.mb-3')) qtyWrapInit.closest('.mb-3').style.display = 'none';
                    var addBtnInit = document.getElementById('btn-add-to-cart');
                    if(addBtnInit) addBtnInit.style.display = 'none';
                    // Mostrar mensaje de selección requerida
                    var reqInit = document.getElementById('ec-variant-required');
                    if(reqInit){ reqInit.style.display = 'block'; reqInit.textContent = 'Selecciona talla y color para agregar al carrito'; reqInit.style.color = '#6b7280'; }
                })();
                </script>

                {{-- Atributos del producto — selector interactivo --}}
                @elseif($record->attributes && count($record->attributes))
                @php
                    // Types that render as selectable chips/swatches
                    $selectableTypes = ['color','talla','tamaño','size','presentacion','presentación','modelo','sabor','fragancia','capacidad','material'];
                    $colorMap = [
                        'rojo'=>'#e11d48','red'=>'#e11d48','rosa'=>'#ec4899','pink'=>'#ec4899',
                        'azul'=>'#2563eb','blue'=>'#2563eb','celeste'=>'#0ea5e9','turquesa'=>'#14b8a6',
                        'verde'=>'#16a34a','green'=>'#16a34a','lima'=>'#84cc16',
                        'amarillo'=>'#f59e0b','yellow'=>'#f59e0b','dorado'=>'#d97706','gold'=>'#d97706',
                        'naranja'=>'#f97316','orange'=>'#f97316','coral'=>'#fb7185',
                        'morado'=>'#9333ea','purple'=>'#9333ea','violeta'=>'#7c3aed','lila'=>'#a78bfa',
                        'negro'=>'#1f2937','black'=>'#1f2937',
                        'blanco'=>'#f9fafb','white'=>'#f9fafb',
                        'gris'=>'#6b7280','grey'=>'#6b7280','gray'=>'#6b7280','plateado'=>'#9ca3af','silver'=>'#9ca3af',
                        'cafe'=>'#92400e','marrón'=>'#92400e','marron'=>'#92400e','brown'=>'#92400e','beige'=>'#d6c3a8',
                        'crema'=>'#fef3c7','champagne'=>'#f0e6c8','navy'=>'#1e3a5f','marino'=>'#1e3a5f',
                    ];
                @endphp
                <div class="ec-variant-selectors" id="ec-variant-selectors">
                    @foreach($record->attributes as $at)
                    @php
                        $typeKey    = strtolower(trim($at->description ?? ''));
                        $isSelect   = in_array($typeKey, $selectableTypes);
                        $isColor    = strpos($typeKey, 'color') !== false || strpos($typeKey, 'colour') !== false;
                        $rawValues  = array_map('trim', explode(',', $at->value ?? ''));
                        $multiVal   = count($rawValues) > 1;
                    @endphp

                    @if($isSelect && $multiVal)
                    {{-- Selectable attribute --}}
                    <div class="ec-variant-group" data-attr="{{ $at->description }}">
                        <div class="ec-variant-label">
                            <span class="ec-variant-label__name">{{ $at->description }}</span>
                            <span class="ec-variant-label__val" id="ec-val-{{ $loop->index }}">— Elige una opción</span>
                        </div>
                        <div class="ec-variant-options {{ $isColor ? 'ec-variant-options--colors' : '' }}">
                            @foreach($rawValues as $val)
                            @php
                                $valKey   = strtolower(trim($val));
                                $cssColor = $isColor ? ($colorMap[$valKey] ?? null) : null;
                                $isLight  = $cssColor && in_array($valKey, ['blanco','white','beige','crema','champagne','amarillo','yellow','lima']);
                            @endphp
                            <button type="button"
                                    class="ec-variant-opt {{ $isColor ? 'ec-variant-opt--color' : 'ec-variant-opt--chip' }}"
                                    data-attr="{{ $at->description }}"
                                    data-val="{{ trim($val) }}"
                                    data-label-id="ec-val-{{ $loop->parent->index }}"
                                    @if($cssColor) style="--swatch:{{ $cssColor }}" @endif
                                    title="{{ trim($val) }}"
                                    aria-label="Seleccionar {{ $at->description }}: {{ trim($val) }}"
                                    aria-pressed="false">
                                @if($isColor)
                                    <span class="ec-swatch {{ $isLight ? 'ec-swatch--light' : '' }}"
                                          style="background:{{ $cssColor ?? '#ccc' }}"></span>
                                @else
                                    {{ trim($val) }}
                                @endif
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @else
                    {{-- Informational badge --}}
                    <div class="ec-variant-group ec-variant-group--info">
                        <span class="product-attr-badge">
                            <strong>{{ $at->description }}:</strong> {{ $at->value }}
                        </span>
                    </div>
                    @endif
                    @endforeach
                </div>
                {{-- Required selection notice (shown if selectable attrs exist) --}}
                <p class="ec-variant-required" id="ec-variant-required" style="display:none">
                    ⚠ Por favor selecciona todas las opciones antes de agregar al carrito.
                </p>
                @endif

                {{-- Selector de cantidad --}}
                @if($record->stock > 0)
                <div class="mb-3">
                    <label style="font-size:12px; font-weight:700; color:var(--subtitle-color); display:block; margin-bottom:6px;">
                        CANTIDAD
                    </label>
                    <div class="ec-qty-selector" id="ec-qty-selector">
                        <button type="button" class="ec-qty-btn" onclick="ecQtyChange(-1)" aria-label="Reducir cantidad">−</button>
                        <input  type="number" class="ec-qty-input" id="ec-qty-input"
                                value="1" min="1" max="{{ $record->stock }}"
                                aria-label="Cantidad">
                        <button type="button" class="ec-qty-btn" onclick="ecQtyChange(1)" aria-label="Aumentar cantidad">+</button>
                    </div>
                </div>
                @endif

                {{-- Acciones --}}
                @php
                    // ── Stock efectivo (considera variantes) ───────────────────────────
                    $effectiveStock = $record->stock;
                    if ($record->has_variants && !empty($record->item_variants)) {
                        $effectiveStock = collect($record->item_variants)->sum('stock');
                    }
                    $isAvailable = $effectiveStock > 0;

                    // ── WhatsApp ────────────────────────────────────────────────────────
                    $showWhatsapp = ($configurationModel->enable_whatsapp ?? false)
                                    && !empty($phoneWhatsapp)
                                    && $isAvailable;           // ← ocultar si agotado
                    $waPhoneRaw   = $showWhatsapp ? preg_replace('/\D+/', '', $phoneWhatsapp) : '';
                    $waPhone      = ($showWhatsapp && strlen($waPhoneRaw) == 9 && str_starts_with($waPhoneRaw, '9'))
                                    ? '51' . $waPhoneRaw : $waPhoneRaw;
                    $waMsgLines   = [
                        "Hola, me interesa este producto:",
                        "",
                        "*{$record->description}*",
                        "Código: " . ($record->internal_id ?? 'S/N'),
                        "Precio: {$record->currency_type['symbol']} " . number_format($record->sale_unit_price, 2),
                        "",
                        "🔗 {$productUrl}",
                    ];
                    $waText       = rawurlencode(implode("\n", $waMsgLines));
                    $waLink       = $showWhatsapp ? "https://wa.me/{$waPhone}?text={$waText}" : '#';
                @endphp

                <div class="product-action product-all-icons d-none d-md-block" id="product-actions">
                    @if($isAvailable)
                    <a href="#" class="paction add-cart"
                       id="btn-add-to-cart"
                       data-product="{{ json_encode($record) }}"
                       data-ec-cart="{{ json_encode($record) }}"
                       title="Agregar al carrito">
                        <span>Agregar al Carrito</span>
                    </a>
                    @if($record->has_variants)
                    <script>
                    (function(){
                        document.getElementById('btn-add-to-cart').addEventListener('click', function(e){
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            if(!window._ecSelectedVariant){
                                document.getElementById('ec-variant-required').style.display='block';
                                document.getElementById('ec-variant-required').textContent='⚠ Por favor selecciona todas las opciones antes de agregar al carrito.';
                                this.closest('.product-single-details').querySelector('.ec-variant-selectors').scrollIntoView({behavior:'smooth',block:'nearest'});
                                return;
                            }
                            var v = window._ecSelectedVariant;
                            var base = JSON.parse(this.getAttribute('data-ec-cart'));
                            base.variant_id = v.id;
                            base.variant_display_name = v.display_name;
                            base.variant_stock = v.stock || 0;
                            base.stock = v.stock || 0; // override parent stock with variant stock
                            if(v.sale_unit_price !== null && v.sale_unit_price !== undefined){
                                base.sale_unit_price = parseFloat(v.sale_unit_price);
                                base.sale_unit = parseFloat(v.sale_unit_price);
                            }
                            var qtyEl = document.getElementById('ec-qty-input');
                            base.quantity = qtyEl ? (parseInt(qtyEl.value)||1) : 1;
                            cart_add(JSON.stringify(base));
                        });
                        // Mobile bar btn
                        var mobBtn = document.querySelector('.ec-mob-cart-btn');
                        if(mobBtn){
                            mobBtn.addEventListener('click', function(e){
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                document.getElementById('btn-add-to-cart').click();
                            });
                        }
                    })();
                    </script>
                    @endif
                    @else
                    <button type="button" class="ec-btn-notify ec-btn-notify--full"
                            data-item-id="{{ $record->id }}"
                            data-item-name="{{ $record->description }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                             <path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Avisarme cuando haya stock
                    </button>
                    @endif

                    @if($showWhatsapp)
                    <a href="{{ $waLink }}" class="btn-whatsapp mt-2 d-none d-md-inline-flex" target="_blank" rel="noopener noreferrer"
                       title="Consultar por WhatsApp">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/>
                            <path d="M9 10a.5.5 0 0 0 1 0v-1a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>
                        </svg>
                        <span>Consultar por WhatsApp</span>
                    </a>
                    @endif
                </div>

                {{-- Código de producto (estilo Falabella) --}}
                <div class="ec-product-codes">
                    @if($record->internal_id)
                    <span class="ec-product-code">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Código: {{ $record->internal_id }}
                    </span>
                    @endif
                </div>

                {{-- Info de envío / disponibilidad (estilo Falabella) --}}
                @if($isAvailable)
                <div class="ec-delivery-info">
                    <div class="ec-delivery-row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span>Stock disponible</span>
                    </div>
                    <div class="ec-delivery-row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        <span>Despacho a domicilio</span>
                    </div>
                </div>
                @endif

                {{-- Comparar --}}
                <div class="mt-2">
                    <button type="button"
                            class="ec-btn-compare ec-btn-compare--detail"
                            data-compare-id="{{ $record->id }}"
                            data-product="{{ json_encode($record) }}"
                            aria-pressed="false"
                            title="Agregar a comparación">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                            <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                        </svg>
                        Agregar a comparación
                    </button>
                </div>

                {{-- Compartir --}}
                <div class="product-single-share mt-3">
                    <div class="ec-share-bar">
                        <span class="ec-share-bar__label">Compartir:</span>
                        <button type="button" class="ec-share-btn" id="ec-native-share"
                                data-title="{{ $record->description }}"
                                data-url="{{ $productUrl }}"
                                title="Compartir este producto"
                                aria-label="Compartir">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/>
                                <circle cx="18" cy="19" r="3"/>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                            </svg>
                            Compartir
                        </button>
                        {{-- Fallback: enlaces directos --}}
                        <div class="ec-share-links" id="ec-share-links" style="display:none">
                            <a href="https://wa.me/?text={{ rawurlencode($record->description . ' ' . $productUrl) }}"
                               target="_blank" rel="noopener" class="ec-share-link ec-share-link--wa" title="WhatsApp">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/>
                                </svg> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($productUrl) }}"
                               target="_blank" rel="noopener" class="ec-share-link ec-share-link--fb" title="Facebook">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                </svg> Facebook
                            </a>
                            <button type="button" class="ec-share-link ec-share-link--copy" id="ec-copy-link"
                                    data-url="{{ $productUrl }}" title="Copiar enlace">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg> Copiar
                            </button>
                        </div>
                    </div>
                </div>
            </div><!-- End .product-single-details -->

            {{-- ── MOBILE STICKY BAR (solo visible en móvil ≤ 767px) ── --}}
            @if($isAvailable)
            <div class="ec-mobile-action-bar d-md-none">
                <style>@media(max-width:767px){a.ws-flotante{display:none!important}}</style>
                @if($showWhatsapp)
                <a href="{{ $waLink }}" class="btn-whatsapp ec-mob-wa-btn" target="_blank" rel="noopener noreferrer"
                   title="Consultar por WhatsApp" aria-label="WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/>
                        <path d="M9 10a.5.5 0 0 0 1 0v-1a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>
                    </svg>
                </a>
                @endif
                <a href="#" class="paction add-cart ec-mob-cart-btn"
                   data-product="{{ json_encode($record) }}"
                   data-ec-cart="{{ json_encode($record) }}"
                   style="background:#2d6b4a !important;color:#fff !important">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="#fff" stroke-width="2.5" aria-hidden="true">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Agregar al Carrito
                </a>
            </div>
            @endif

        </div><!-- End .col-lg-5 -->
    </div><!-- End .row -->
</div><!-- End .product-single-container -->

<div class="product-single-tabs">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active"  id="product-tab-desc" data-toggle="tab" href="#product-desc-content" role="tab"
                aria-controls="product-desc-content" aria-selected="true">Descripcion</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" onclick="getRating('{{ $record->id}}')" id="product-tab-reviews" data-toggle="tab" href="#product-reviews-content" role="tab"
                aria-controls="product-reviews-content" aria-selected="false">Reviews</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="product-tab-especTecn" data-toggle="tab" href="#product-especTecn-content" role="tab" aria-controls="product-especTecn-content" aria-selected="true">Especificaciones Técnicas</a>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="product-desc-content" role="tabpanel"
            aria-labelledby="product-tab-desc">
            <div class="product-desc-content">
                <p> {{ $record->description}} </p>
                <p> {{ $record->name}} </p>
            </div><!-- End .product-desc-content -->
        </div><!-- End .tab-pane -->

        <div class="tab-pane fade" id="product-reviews-content" role="tabpanel" aria-labelledby="product-tab-reviews">
            <div class="ec-reviews-wrap" id="ec-reviews-wrap" data-item-id="{{ $record->id }}">

                {{-- ── Resumen + distribución ───────────────────────── --}}
                <div class="ec-reviews-summary" id="ec-reviews-summary">
                    <div class="ec-reviews-avg">
                        <span class="ec-reviews-avg__num" id="ec-avg-num">—</span>
                        <div class="ec-reviews-avg__stars" id="ec-avg-stars"></div>
                        <span class="ec-reviews-avg__total" id="ec-avg-total">0 reseñas</span>
                    </div>
                    <div class="ec-reviews-dist" id="ec-reviews-dist">
                        @foreach([5,4,3,2,1] as $s)
                        <div class="ec-dist-row">
                            <span class="ec-dist-label">{{ $s }} ★</span>
                            <div class="ec-dist-bar">
                                <div class="ec-dist-fill" id="ec-dist-{{ $s }}" style="width:0%"></div>
                            </div>
                            <span class="ec-dist-count" id="ec-dist-count-{{ $s }}">0</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── Filtro por estrellas ─────────────────────────── --}}
                <div class="ec-reviews-filters" id="ec-reviews-filters" style="display:none">
                    <span class="ec-reviews-filter-label">Filtrar:</span>
                    <button class="ec-filter-star-btn ec-filter-star-btn--active" data-filter="0">Todas</button>
                    @foreach([5,4,3,2,1] as $s)
                    <button class="ec-filter-star-btn" data-filter="{{ $s }}">{{ $s }} ★</button>
                    @endforeach
                </div>

                {{-- ── Lista de reviews ─────────────────────────────── --}}
                <div class="ec-reviews-list" id="ec-reviews-list">
                    <p class="ec-reviews-loading">Cargando reseñas...</p>
                </div>

                {{-- ── Paginación ───────────────────────────────────── --}}
                <div class="ec-reviews-pagination" id="ec-reviews-pagination" style="display:none">
                    <button class="ec-reviews-page-btn" id="ec-reviews-load-more">Ver más reseñas</button>
                </div>

                {{-- ── Formulario ───────────────────────────────────── --}}
                @auth('ecommerce')
                <div class="ec-review-form-wrap">
                    <h3 class="ec-review-form-title">Deja tu opinión</h3>
                    <div class="ec-review-stars-input" id="ec-star-picker">
                        @foreach([1,2,3,4,5] as $s)
                        <button type="button" class="ec-star-pick" data-val="{{ $s }}" aria-label="{{ $s }} estrella(s)">★</button>
                        @endforeach
                    </div>
                    <input type="hidden" id="ec-rating-val" value="0">
                    <input type="text" id="ec-reviewer-name"
                           class="ec-review-input"
                           placeholder="Tu nombre"
                           value="{{ auth('ecommerce')->user()->name ?? '' }}">
                    <textarea id="ec-review-comment" class="ec-review-textarea"
                              placeholder="Escribe tu opinión (opcional)" rows="3"></textarea>
                    <button type="button" class="ec-review-submit-btn" id="ec-review-submit">
                        Publicar reseña
                    </button>
                    <p class="ec-review-msg" id="ec-review-msg"></p>
                </div>
                @else
                <p class="ec-review-login-msg">
                    <a href="{{ route('tenant_ecommerce_login') }}">Inicia sesión</a> para dejar tu reseña.
                </p>
                @endauth

            </div>
        </div>

        <div class="tab-pane fade" id="product-especTecn-content" role="tabpanel" aria-labelledby="product-tab-especTecn">
            <div class="product-especTecn-content">
                @php
                    // Sanitizar HTML: permitir solo etiquetas seguras y eliminar atributos de eventos
                    $allowedTags = '<p><br><b><strong><i><em><ul><ol><li><table><thead><tbody><tr><th><td><h1><h2><h3><h4><h5><h6><span><div>';
                    $safeSpecs = strip_tags($record->technical_specifications ?? '', $allowedTags);
                    // Remover atributos on* (onclick, onmouseover, etc.) y javascript: en href/src
                    $safeSpecs = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $safeSpecs);
                    $safeSpecs = preg_replace('/\s+(?:href|src|action)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $safeSpecs);
                @endphp
                <div class="specs-content">{!! $safeSpecs !!}</div>
            </div>
        </div><!-- End .tab-pane -->
    </div>
</div>

{{-- ── Rating inline (carga al abrir la página) ──────── --}}
<script>
(function(){
    fetch('/ecommerce/reviews/{{ $record->id }}')
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(!data || !data.total) return;
            var starsEl = document.getElementById('ec-inline-stars');
            var linkEl  = document.getElementById('ec-inline-rating-link');
            if(!starsEl || !linkEl) return;
            var avg = data.avg || 0;
            var html = '';
            for(var i = 1; i <= 5; i++){
                if(i <= Math.floor(avg)) html += '<span class="ec-star ec-star--full">★</span>';
                else if(i - avg < 1) html += '<span class="ec-star ec-star--half">★</span>';
                else html += '<span class="ec-star ec-star--empty">★</span>';
            }
            starsEl.innerHTML = html;
            linkEl.textContent = avg.toFixed(1) + ' (' + data.total + ')';
            document.getElementById('ec-product-rating').style.display = 'flex';
        }).catch(function(){});
})();
</script>

{{-- ── FRECUENTEMENTE COMPRADOS JUNTOS ──────────────── --}}
<div class="container mt-5" id="ec-fbt-section" style="display:none">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:16px">Frecuentemente comprados juntos</h3>
    <div class="row" id="ec-fbt-grid"></div>
</div>
<script>
(function(){
    fetch('/recommendations/fbt/{{ $record->id }}')
        .then(function(r){ return r.json(); })
        .then(function(items){
            if (!items || !items.length) return;
            var grid = document.getElementById('ec-fbt-grid');
            var section = document.getElementById('ec-fbt-section');
            if (!grid || !section) return;
            items.forEach(function(item){
                var img = item.image_small ? '/storage/uploads/items/' + item.image_small : '/logo/imagen-no-disponible.jpg';
                grid.insertAdjacentHTML('beforeend',
                    '<div class="col-6 col-md-3 mb-3">' +
                    '<div style="background:#fff;border-radius:12px;padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);text-align:center">' +
                    '<img src="' + img + '" loading="lazy" style="width:100%;max-height:160px;object-fit:contain;border-radius:8px" alt="' + (item.description||'') + '" onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">' +
                    '<p style="font-size:13px;font-weight:600;margin:8px 0 4px;color:#1f2937;line-height:1.3">' + (item.description||'').substring(0,50) + '</p>' +
                    '<span style="font-size:15px;font-weight:700;color:#7c3aed">S/ ' + parseFloat(item.sale_unit_price||0).toFixed(2) + '</span>' +
                    '</div></div>'
                );
            });
            section.style.display = '';
        })
        .catch(function(){});
})();
</script>

{{-- ── PRODUCTOS RELACIONADOS ───────────────────────── --}}
<div class="container mt-5">
    @include('ecommerce::items.partials.related_products')
</div>

{{-- ── VISTOS RECIENTEMENTE ─────────────────────────── --}}
<div class="container mt-5"
     id="ec-recently-viewed"
     data-current-id="{{ $record->id }}"
     data-items-bar="/ecommerce/items_bar"
     style="display:none">
    {{-- El JS de recently-viewed.js inyecta el Swiper aquí --}}
</div>

@endsection

@push('scripts')
<script>
// ── Variant selector ────────────────────────────────
(function () {
    var selectedAttrs = {};   // { "Color": "Rojo", "Talla": "M" }
    var selectableGroups = document.querySelectorAll('.ec-variant-group:not(.ec-variant-group--info)');
    var requiredCount = selectableGroups.length;

    function isComplete() {
        return Object.keys(selectedAttrs).length >= requiredCount;
    }

    function buildAttrString() {
        return Object.entries(selectedAttrs).map(function (e) { return e[0] + ': ' + e[1]; }).join(' | ');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ec-variant-opt');
        if (!btn) return;

        var attr    = btn.getAttribute('data-attr');
        var val     = btn.getAttribute('data-val');
        var labelId = btn.getAttribute('data-label-id');

        // Deactivate siblings
        btn.closest('.ec-variant-options').querySelectorAll('.ec-variant-opt').forEach(function (b) {
            b.classList.remove('ec-variant-opt--selected');
            b.setAttribute('aria-pressed', 'false');
        });
        // Activate this
        btn.classList.add('ec-variant-opt--selected');
        btn.setAttribute('aria-pressed', 'true');

        // Update label
        if (labelId) {
            var lbl = document.getElementById(labelId);
            if (lbl) lbl.textContent = val;
        }

        selectedAttrs[attr] = val;

        // Update cart button text
        var cartBtn = document.getElementById('btn-add-to-cart');
        if (cartBtn) {
            var notice = document.getElementById('ec-variant-required');
            if (notice) notice.style.display = 'none';

            if (isComplete()) {
                cartBtn.querySelector('span').textContent = 'Agregar al Carrito';
                cartBtn.dataset.variantReady = 'true';
            }
        }
    });

    // Intercept add-to-cart to inject selected attributes
    document.addEventListener('DOMContentLoaded', function () {
        var cartBtn = document.getElementById('btn-add-to-cart');
        if (!cartBtn || requiredCount === 0) return;

        cartBtn.addEventListener('click', function (e) {
            if (!isComplete()) {
                e.stopImmediatePropagation();
                var notice = document.getElementById('ec-variant-required');
                if (notice) notice.style.display = 'block';
                // Shake the selectors
                document.querySelectorAll('.ec-variant-group').forEach(function (g) {
                    if (!g.classList.contains('ec-variant-group--info')) {
                        var attr = g.querySelector('.ec-variant-opt.ec-variant-opt--selected');
                        if (!attr) g.classList.add('ec-variant-group--shake');
                        setTimeout(function () { g.classList.remove('ec-variant-group--shake'); }, 500);
                    }
                });
                return;
            }

            // Inject selected_attributes into product data
            try {
                var product = JSON.parse(cartBtn.getAttribute('data-product') || '{}');
                product.selected_attributes = selectedAttrs;
                product.variant_label = buildAttrString();
                cartBtn.setAttribute('data-product', JSON.stringify(product));
            } catch(err) {}
        }, true); // capture phase = before the cart.js handler
    });
}());

// ── Selector de cantidad ────────────────────────────
function ecQtyChange(delta) {
    var input = document.getElementById('ec-qty-input');
    if (!input) return;
    var val = parseInt(input.value) || 1;
    var max = parseInt(input.getAttribute('max')) || 9999;
    val = Math.min(max, Math.max(1, val + delta));
    input.value = val;
}

// ── Registrar producto visto ────────────────────────
if (window.RecentlyViewed) {
    RecentlyViewed.push({{ $record->id }});
} else {
    document.addEventListener('DOMContentLoaded', function () {
        if (window.RecentlyViewed) RecentlyViewed.push({{ $record->id }});
    });
}

// ── Web Share API ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var shareBtn  = document.getElementById('ec-native-share');
    var shareLinks = document.getElementById('ec-share-links');
    var copyBtn   = document.getElementById('ec-copy-link');

    if (shareBtn) {
        if (navigator.share) {
            shareBtn.addEventListener('click', function () {
                navigator.share({
                    title: shareBtn.getAttribute('data-title'),
                    url:   shareBtn.getAttribute('data-url')
                }).catch(function () {});
            });
        } else {
            // Mostrar fallback
            shareBtn.addEventListener('click', function () {
                if (shareLinks) shareLinks.style.display = shareLinks.style.display === 'none' ? 'flex' : 'none';
            });
        }
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var url = copyBtn.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(function () {
                copyBtn.textContent = '¡Copiado!';
                setTimeout(function () { copyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copiar'; }, 2000);
            }).catch(function () {
                var tmp = document.createElement('input');
                tmp.value = url; document.body.appendChild(tmp); tmp.select();
                document.execCommand('copy'); document.body.removeChild(tmp);
                copyBtn.textContent = '¡Copiado!';
                setTimeout(function () { copyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copiar'; }, 2000);
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    // Inyectar quantity en data-product al hacer click
    var btn = document.getElementById('btn-add-to-cart');
    var qtyInput = document.getElementById('ec-qty-input');
    if (btn && qtyInput) {
        btn.addEventListener('click', function () {
            try {
                var product = JSON.parse(this.getAttribute('data-product') || '{}');
                product.quantity = parseInt(qtyInput.value) || 1;
                this.setAttribute('data-product', JSON.stringify(product));
            } catch(e) {}
        });
    }

    // Tracking ViewContent
    var productData = {!! \Illuminate\Support\Js::from([
        'id'       => $record->id,
        'name'     => $record->description,
        'price'    => $record->sale_unit_price,
        'currency' => $record->currency_type_id ?? 'PEN',
    ]) !!};
    if (window.EcommerceTracker) {
        window.EcommerceTracker.viewContent(productData);
    }

    // Flash Sale Countdown
    var cdEl = document.getElementById('ec-bl-countdown');
    if (cdEl) {
        var ends = parseInt(cdEl.getAttribute('data-ends'));
        var hEl = document.getElementById('ec-cd-h');
        var mEl = document.getElementById('ec-cd-m');
        var sEl = document.getElementById('ec-cd-s');
        setInterval(function() {
            var diff = Math.max(0, ends - Date.now());
            var h = Math.floor(diff / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            if (hEl) hEl.textContent = h < 10 ? '0' + h : h;
            if (mEl) mEl.textContent = m < 10 ? '0' + m : m;
            if (sEl) sEl.textContent = s < 10 ? '0' + s : s;
        }, 1000);
    }
});
</script>

<style>
.product-attributes { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
.product-attr-badge {
    display:inline-block; padding:4px 10px;
    background:hsl(var(--primary-h),var(--primary-s),95%);
    border:1px solid hsl(var(--primary-h),var(--primary-s),88%);
    border-radius:20px; font-size:12px; color:var(--title-color);
}
.product-attr-badge strong {
    font-weight:700;
    color:hsl(var(--primary-h),var(--primary-s),var(--primary-l));
}
/* Ocultar precio tachado si no hay descuento real */
.price-box .old-price { display:none; }
</style>

{{-- ── REVIEWS JS ─────────────────────────────────────────────────────────── --}}
<script>
(function () {
    var ITEM_ID   = {{ $record->id }};
    var PER_PAGE  = 5;
    var allReviews  = [];
    var filtered    = [];
    var shownCount  = 0;
    var activeFilter = 0;
    var loaded      = false;

    // ── Utilidades ──────────────────────────────────────────────────────────
    function starHtml(val) {
        var s = '';
        for (var i = 1; i <= 5; i++) {
            s += '<span class="' + (i <= val ? 'ec-star ec-star--on' : 'ec-star') + '">★</span>';
        }
        return s;
    }

    function timeAgo(dateStr) {
        var d   = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 1000);
        if (diff < 60)   return 'hace un momento';
        if (diff < 3600) return 'hace ' + Math.floor(diff / 60) + ' min';
        if (diff < 86400) return 'hace ' + Math.floor(diff / 3600) + ' h';
        var days = Math.floor(diff / 86400);
        if (days < 30)   return 'hace ' + days + ' día' + (days > 1 ? 's' : '');
        var months = Math.floor(days / 30);
        if (months < 12) return 'hace ' + months + ' mes' + (months > 1 ? 'es' : '');
        return 'hace ' + Math.floor(months / 12) + ' año' + (Math.floor(months / 12) > 1 ? 's' : '');
    }

    function renderCard(r) {
        return '<div class="ec-review-card">' +
            '<div class="ec-review-card__head">' +
                '<span class="ec-review-card__name">' + (r.reviewer_name || 'Anónimo') + '</span>' +
                '<span class="ec-review-card__stars">' + starHtml(r.value) + '</span>' +
                '<span class="ec-review-card__date">' + timeAgo(r.created_at) + '</span>' +
            '</div>' +
            (r.comment ? '<p class="ec-review-card__comment">' + r.comment.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>' : '') +
        '</div>';
    }

    function updateSummary(data) {
        var avgEl   = document.getElementById('ec-avg-num');
        var starsEl = document.getElementById('ec-avg-stars');
        var totalEl = document.getElementById('ec-avg-total');
        if (avgEl) avgEl.textContent = data.avg > 0 ? data.avg : '—';
        if (starsEl) starsEl.innerHTML = data.avg > 0 ? starHtml(Math.round(data.avg)) : '';
        if (totalEl) totalEl.textContent = data.total + ' reseña' + (data.total !== 1 ? 's' : '');
        for (var i = 1; i <= 5; i++) {
            var fill  = document.getElementById('ec-dist-' + i);
            var count = document.getElementById('ec-dist-count-' + i);
            if (fill && data.dist && data.dist[i]) {
                fill.style.width = data.dist[i].pct + '%';
            }
            if (count && data.dist && data.dist[i]) {
                count.textContent = data.dist[i].count;
            }
        }
    }

    function renderList() {
        var list = document.getElementById('ec-reviews-list');
        var pagBtn = document.getElementById('ec-reviews-pagination');
        if (!list) return;

        var start = shownCount;
        var end   = Math.min(shownCount + PER_PAGE, filtered.length);
        var html  = '';

        if (filtered.length === 0) {
            list.innerHTML = '<p class="ec-reviews-empty">Aún no hay reseñas' + (activeFilter > 0 ? ' con ' + activeFilter + ' estrella' + (activeFilter > 1 ? 's' : '') : '') + '. ¡Sé el primero!</p>';
            if (pagBtn) pagBtn.style.display = 'none';
            return;
        }

        if (shownCount === 0) list.innerHTML = '';

        for (var i = start; i < end; i++) {
            html += renderCard(filtered[i]);
        }
        list.insertAdjacentHTML('beforeend', html);
        shownCount = end;

        if (pagBtn) {
            pagBtn.style.display = shownCount < filtered.length ? 'flex' : 'none';
        }
    }

    function applyFilter(star) {
        activeFilter = star;
        filtered     = star === 0 ? allReviews.slice() : allReviews.filter(function (r) { return r.value === star; });
        shownCount   = 0;
        document.getElementById('ec-reviews-list').innerHTML = '';
        renderList();

        // Active button state
        document.querySelectorAll('.ec-filter-star-btn').forEach(function (btn) {
            btn.classList.toggle('ec-filter-star-btn--active', parseInt(btn.getAttribute('data-filter')) === star);
        });
    }

    function loadReviews() {
        if (loaded) return;
        loaded = true;
        var list = document.getElementById('ec-reviews-list');
        if (list) list.innerHTML = '<p class="ec-reviews-loading">Cargando reseñas...</p>';

        fetch('/ecommerce/reviews/' + ITEM_ID, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                allReviews = data.reviews || [];
                updateSummary(data);

                var filtersEl = document.getElementById('ec-reviews-filters');
                if (filtersEl && allReviews.length > 0) filtersEl.style.display = 'flex';

                applyFilter(0);
            })
            .catch(function () {
                var list = document.getElementById('ec-reviews-list');
                if (list) list.innerHTML = '<p class="ec-reviews-empty">No se pudieron cargar las reseñas.</p>';
            });
    }

    // ── Bootstrap tab click ──────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var tabLink = document.getElementById('product-tab-reviews');
        if (tabLink) {
            tabLink.addEventListener('click', loadReviews);
            // Si la URL tiene #reviews activo, cargar de inmediato
            if (window.location.hash === '#product-reviews-content') loadReviews();
        }

        // ── Filter buttons ───────────────────────────────────────────────────
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.ec-filter-star-btn');
            if (!btn) return;
            applyFilter(parseInt(btn.getAttribute('data-filter')));
        });

        // ── Load more ────────────────────────────────────────────────────────
        var loadMore = document.getElementById('ec-reviews-load-more');
        if (loadMore) {
            loadMore.addEventListener('click', renderList);
        }

        // ── Star picker ──────────────────────────────────────────────────────
        var picker  = document.getElementById('ec-star-picker');
        var ratingVal = document.getElementById('ec-rating-val');
        if (picker) {
            var stars = picker.querySelectorAll('.ec-star-pick');
            function highlightStars(upTo) {
                stars.forEach(function (s, idx) {
                    s.classList.toggle('ec-star-pick--on', idx < upTo);
                });
            }
            stars.forEach(function (s, idx) {
                s.addEventListener('mouseenter', function () { highlightStars(idx + 1); });
                s.addEventListener('mouseleave', function () {
                    highlightStars(ratingVal ? parseInt(ratingVal.value) : 0);
                });
                s.addEventListener('click', function () {
                    var val = idx + 1;
                    if (ratingVal) ratingVal.value = val;
                    highlightStars(val);
                });
            });
        }

        // ── Form submit ──────────────────────────────────────────────────────
        var submitBtn = document.getElementById('ec-review-submit');
        var msgEl     = document.getElementById('ec-review-msg');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var val  = ratingVal ? parseInt(ratingVal.value) : 0;
                var name = (document.getElementById('ec-reviewer-name') || {}).value || '';
                var txt  = (document.getElementById('ec-review-comment') || {}).value || '';

                if (val < 1) {
                    if (msgEl) { msgEl.textContent = 'Por favor selecciona una puntuación.'; msgEl.style.color = '#e55'; }
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando...';

                var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var body = new URLSearchParams();
                body.append('_token',        csrfToken);
                body.append('item_id',       ITEM_ID);
                body.append('value',         val);
                body.append('reviewer_name', name);
                body.append('comment',       txt);

                fetch('/ecommerce/rating_item', {
                    method:  'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString()
                })
                .then(function (res) { return res.json(); })
                .then(function () {
                    if (msgEl) { msgEl.textContent = '¡Gracias por tu reseña!'; msgEl.style.color = '#22a'; }
                    submitBtn.textContent = 'Publicar reseña';
                    submitBtn.disabled = false;
                    // Reset form
                    if (ratingVal) ratingVal.value = 0;
                    if (picker) picker.querySelectorAll('.ec-star-pick').forEach(function (s) { s.classList.remove('ec-star-pick--on'); });
                    var nameInput = document.getElementById('ec-reviewer-name');
                    var cmtInput  = document.getElementById('ec-review-comment');
                    if (cmtInput) cmtInput.value = '';
                    // Reload reviews
                    loaded = false;
                    loadReviews();
                })
                .catch(function () {
                    if (msgEl) { msgEl.textContent = 'Ocurrió un error. Intenta de nuevo.'; msgEl.style.color = '#e55'; }
                    submitBtn.textContent = 'Publicar reseña';
                    submitBtn.disabled = false;
                });
            });
        }
    });

    // ── Compatibilidad con onclick="getRating(id)" legado ───────────────────
    window.getRating = function () { loadReviews(); };
}());
</script>

@if($record->stock > 0)
<script>
// ── Sticky add-to-cart en desktop ───────────────────────────────────────────
(function () {
    var bar = document.createElement('div');
    bar.id  = 'ec-sticky-desktop';
    bar.className = 'ec-sticky-desktop';
    bar.innerHTML = [
        '<div class="ec-sticky-desktop__inner">',
        '  <p class="ec-sticky-desktop__name">{{ addslashes($record->description) }}</p>',
        '  <span class="ec-sticky-desktop__price">',
        '    {{ $record->currency_type_symbol }} {{ number_format($record->sale_unit_price, 2) }}',
        '  </span>',
        '  <a href="#" class="paction add-cart ec-sticky-desktop__btn"',
        '     data-product=\'{{ addslashes(json_encode($record)) }}\'',
        '     data-ec-cart=\'{{ addslashes(json_encode($record)) }}\'',
        '     title="Agregar al carrito">',
        '    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"',
        '         fill="none" stroke="currentColor" stroke-width="2.5">',
        '      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>',
        '      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        '    </svg>',
        '    Agregar al carrito',
        '  </a>',
        '</div>'
    ].join('');
    document.body.appendChild(bar);

    var trigger = document.getElementById('product-actions');
    if (!trigger || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        var hidden = !entries[0].isIntersecting;
        bar.classList.toggle('ec-sticky-desktop--visible', hidden);
    }, { threshold: 0 });

    observer.observe(trigger);
}());
</script>
@endif

@endpush
