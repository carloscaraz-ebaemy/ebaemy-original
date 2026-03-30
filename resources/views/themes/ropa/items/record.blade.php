{{--
    ╔══════════════════════════════════════════════════════════════╗
    ║  THEME: ROPA — Vista de Producto                           ║
    ║  Diseño tipo Zara / Falabella                              ║
    ║  - Galería con thumbnails verticales                       ║
    ║  - Variantes: color con imagen, talla con botón circular   ║
    ║  - Tipografía editorial, diseño limpio                     ║
    ╚══════════════════════════════════════════════════════════════╝
--}}
@extends('ecommerce::layouts.layout_ecommerce_item.record')

@php
    $configurationModel     = \App\Models\Tenant\Configuration::first();
    $ecommerceConfiguration = \App\Models\Tenant\ConfigurationEcommerce::first();
    $company                = \App\Models\Tenant\Company::first();
    $phoneWhatsapp          = $ecommerceConfiguration->phone_whatsapp ?? $configurationModel->phone_whatsapp ?? null;
    $defaultImage           = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath       = $defaultImage === 'imagen-no-disponible.jpg'
                              ? asset('logo/imagen-no-disponible.jpg')
                              : asset('storage/defaults/' . $defaultImage);
    $mainImagePath          = ($record->image && $record->image !== 'imagen-no-disponible.jpg')
                              ? asset('storage/uploads/items/'.$record->image)
                              : $defaultImagePath;
    $productUrl             = route('tenant.ecommerce.item', ['slug' => $record->slug ?: $record->id]);
    $shortDesc              = \Illuminate\Support\Str::limit(strip_tags($record->name ?: $record->description), 155);
@endphp

{{-- SEO --}}
@section('page_title', $record->description . ' | ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', $shortDesc)
@section('og_type', 'product')
@section('og_title', $record->description)
@section('og_description', $shortDesc)
@section('og_image', $mainImagePath)
@section('canonical_url', $productUrl)

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

@section('schema_product')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ addslashes($record->description) }}",
    "sku": "{{ $record->internal_id ?? $record->id }}",
    "image": ["{{ $mainImagePath }}"@foreach($record->images as $img)@if($img->image),"{{ asset('storage/uploads/items/'.$img->image) }}"@endif @endforeach],
    "offers": {
        "@type": "Offer",
        "url": "{{ $productUrl }}",
        "priceCurrency": "{{ $record->currency_type_id ?? 'PEN' }}",
        "price": "{{ number_format($record->sale_unit_price, 2, '.', '') }}",
        "availability": "{{ $record->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}"
    }
}
</script>
@endsection

@section('content')
@php
    // Stock efectivo
    $totalStock = $record->stock;
    if ($record->has_variants && !empty($record->item_variants)) {
        $totalStock = collect($record->item_variants)->where('is_active', true)->sum('stock');
    }
    $isAvailable = $totalStock > 0;

    // Mapear imágenes de variantes a valores de opción de color
    $variantImageMap = [];
    if ($record->has_variants && !empty($record->item_variants)) {
        foreach ($record->item_variants as $v) {
            if (!empty($v['image'])) {
                foreach ($v['option_value_ids'] as $ovId) {
                    if (!isset($variantImageMap[$ovId])) {
                        $variantImageMap[$ovId] = asset('storage/uploads/items/' . $v['image']);
                    }
                }
            }
        }
    }

    // WhatsApp
    $showWhatsapp = ($configurationModel->enable_whatsapp ?? false) && !empty($phoneWhatsapp) && $isAvailable;
    $waPhoneRaw   = $showWhatsapp ? preg_replace('/\D+/', '', $phoneWhatsapp) : '';
    $waPhone      = ($showWhatsapp && strlen($waPhoneRaw) == 9 && str_starts_with($waPhoneRaw, '9')) ? '51' . $waPhoneRaw : $waPhoneRaw;
    $waMsgLines   = ["Hola, me interesa:", "", "*{$record->description}*", "Código: " . ($record->internal_id ?? 'S/N'), "Precio: {$record->currency_type['symbol']} " . number_format($record->sale_unit_price, 2), "", $productUrl];
    $waText       = rawurlencode(implode("\n", $waMsgLines));
    $waLink       = $showWhatsapp ? "https://wa.me/{$waPhone}?text={$waText}" : '#';
@endphp

<div class="ropa-product-page">
    <div class="row">
        {{-- ═══════════════════════════════════════════════════════════
             GALERÍA — Thumbnails verticales a la izquierda
             ═══════════════════════════════════════════════════════════ --}}
        <div class="col-lg-7 col-md-6">
            <div class="ropa-gallery">
                <div class="ropa-gallery__thumbs">
                    <div class="ropa-thumb ropa-thumb--active" data-index="0">
                        <img src="{{ $mainImagePath }}" alt="{{ $record->description }}" loading="lazy">
                    </div>
                    @foreach($record->images as $i => $row)
                    @php
                        $loopImg = ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                            ? asset('storage/uploads/items/'.$row->image) : $defaultImagePath;
                    @endphp
                    <div class="ropa-thumb" data-index="{{ $i + 1 }}">
                        <img src="{{ $loopImg }}" alt="{{ $record->description }}" loading="lazy">
                    </div>
                    @endforeach
                </div>
                <div class="ropa-gallery__main">
                    <img id="ropa-main-img" class="ropa-gallery__img"
                         src="{{ $mainImagePath }}" alt="{{ $record->description }}">
                    {{-- Navegación --}}
                    <button class="ropa-gallery__nav ropa-gallery__nav--prev" id="ropa-prev" aria-label="Anterior">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="ropa-gallery__nav ropa-gallery__nav--next" id="ropa-next" aria-label="Siguiente">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             INFO DEL PRODUCTO
             ═══════════════════════════════════════════════════════════ --}}
        <div class="col-lg-5 col-md-6">
            <div class="ropa-product-info">
                {{-- Marca / Categoría --}}
                @if($record->category)
                <p class="ropa-brand">
                    <a href="{{ route('tenant.ecommerce.index', \Illuminate\Support\Str::slug($record->category->name)) }}">
                        {{ strtoupper($record->category->name) }}
                    </a>
                </p>
                @endif

                {{-- Título --}}
                <h1 class="ropa-title">{{ $record->description }}</h1>

                {{-- Rating --}}
                <div class="ropa-rating" id="ec-product-rating" style="display:none">
                    <div class="ropa-rating__stars" id="ec-inline-stars"></div>
                    <a href="#product-reviews-content" class="ropa-rating__count" id="ec-inline-rating-link"
                       onclick="document.getElementById('product-tab-reviews').click()"></a>
                </div>

                {{-- ── VARIANTES ────────────────────────────────────── --}}
                @if($record->has_variants && count($record->item_options ?? []))
                <div class="ropa-variants" id="ec-variant-selectors">
                    @foreach($record->item_options as $opt)
                    @php
                        $isColor   = stripos($opt['name'], 'color') !== false;
                        $hasImages = $isColor && collect($opt['values'])->contains(fn($v) => isset($variantImageMap[$v['id']]));
                    @endphp
                    <div class="ropa-variant-group" data-option-id="{{ $opt['id'] }}">
                        <div class="ropa-variant-label">
                            {{ $opt['name'] }}:
                            <span class="ropa-variant-label__val" id="ec-optval-{{ $opt['id'] }}"></span>
                        </div>

                        @if($hasImages)
                        {{-- Color con imagen miniatura (estilo Falabella) --}}
                        <div class="ropa-variant-btns ropa-variant-btns--thumbs">
                            @foreach($opt['values'] as $val)
                            <button type="button"
                                    class="ropa-vbtn ropa-vbtn--thumb ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false">
                                <img src="{{ $variantImageMap[$val['id']] ?? $defaultImagePath }}"
                                     alt="{{ $val['value'] }}" loading="lazy">
                            </button>
                            @endforeach
                        </div>
                        @elseif($isColor)
                        {{-- Color con swatch --}}
                        <div class="ropa-variant-btns ropa-variant-btns--swatches">
                            @foreach($opt['values'] as $val)
                            <button type="button"
                                    class="ropa-vbtn ropa-vbtn--swatch ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false"
                                    style="--swatch: {{ $val['color_hex'] ?? '#ccc' }}">
                                <span class="ropa-swatch" style="background: {{ $val['color_hex'] ?? '#ccc' }}"></span>
                            </button>
                            @endforeach
                        </div>
                        @else
                        {{-- Talla u otra opción: botones circulares (estilo Falabella) --}}
                        <div class="ropa-variant-btns ropa-variant-btns--sizes">
                            @foreach($opt['values'] as $val)
                            <button type="button"
                                    class="ropa-vbtn ropa-vbtn--size ec-rv-opt"
                                    data-option-id="{{ $opt['id'] }}"
                                    data-value-id="{{ $val['id'] }}"
                                    data-val="{{ $val['value'] }}"
                                    data-label-id="ec-optval-{{ $opt['id'] }}"
                                    title="{{ $val['value'] }}"
                                    aria-pressed="false">
                                {{ $val['value'] }}
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                <div id="ec-variant-resolved" style="display:none;margin:8px 0;padding:8px 12px;background:#f0f9eb;border-radius:4px;font-size:13px;color:#16a34a;font-weight:600;"></div>
                <p class="ropa-variant-msg" id="ec-variant-required" style="display:none">
                    Selecciona talla y color para agregar al carrito
                </p>

                {{-- JS de variantes (reutiliza la misma lógica) --}}
                <script>
                (function(){
                    var variants  = @json($record->item_variants);
                    var options   = @json($record->item_options);
                    var basePrice = {{ $record->sale_unit_price }};
                    var symbol    = '{{ $record->currency_type_symbol }}';
                    var selected  = {};

                    function canSelect(optionId, valueId){
                        var test = Object.assign({}, selected);
                        test[String(optionId)] = valueId;
                        return variants.some(function(v){
                            if(!v.is_active || (v.stock||0) <= 0) return false;
                            return Object.keys(test).every(function(oid){
                                return (v.option_value_ids||[]).indexOf(test[oid]) !== -1;
                            });
                        });
                    }

                    function refreshAvailability(){
                        document.querySelectorAll('.ec-rv-opt').forEach(function(btn){
                            var optId = btn.getAttribute('data-option-id');
                            var valId = parseInt(btn.getAttribute('data-value-id'));
                            var avail = canSelect(optId, valId);
                            btn.disabled = !avail;
                            btn.classList.toggle('ropa-vbtn--oos', !avail);
                        });
                    }

                    document.querySelectorAll('.ec-rv-opt').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            if(this.disabled) return;
                            var optId   = this.getAttribute('data-option-id');
                            var valId   = parseInt(this.getAttribute('data-value-id'));
                            var val     = this.getAttribute('data-val');
                            var labelId = this.getAttribute('data-label-id');
                            document.querySelectorAll('.ec-rv-opt[data-option-id="'+optId+'"]').forEach(function(b){
                                b.setAttribute('aria-pressed','false');
                            });
                            this.setAttribute('aria-pressed','true');
                            selected[optId] = valId;
                            var lbl = document.getElementById(labelId);
                            if(lbl) lbl.textContent = val;
                            refreshAvailability();
                            resolveVariant();
                        });
                    });

                    function resolveVariant(){
                        var reqEl = document.getElementById('ec-variant-required');
                        var resEl = document.getElementById('ec-variant-resolved');
                        var allSelected = options.every(function(opt){ return selected[String(opt.id)] !== undefined; });
                        if(!allSelected){
                            setPriceDisplay(null);
                            if(resEl) resEl.style.display='none';
                            if(reqEl) reqEl.style.display='none';
                            window._ecSelectedVariant = null;
                            return;
                        }
                        var selIds = options.map(function(o){ return selected[String(o.id)]; }).sort(function(a,b){return a-b;});
                        var found = variants.find(function(v){
                            var vIds=(v.option_value_ids||[]).slice().sort(function(a,b){return a-b;});
                            return JSON.stringify(vIds)===JSON.stringify(selIds);
                        });
                        if(found && found.is_active && (found.stock||0) > 0){
                            window._ecSelectedVariant = found;
                            setPriceDisplay(found.sale_unit_price != null ? parseFloat(found.sale_unit_price) : null);
                            updateStockUI(found.stock);
                            if(resEl){resEl.style.display='block';resEl.textContent='✓ '+found.display_name;}
                            if(reqEl) reqEl.style.display='none';
                            if(found.image) swapMainImage('/storage/uploads/items/'+found.image);
                            // Mostrar botones
                            var qw=document.getElementById('ec-qty-selector');
                            if(qw&&qw.closest('.ropa-qty-wrap')) qw.closest('.ropa-qty-wrap').style.display='';
                            var ab=document.getElementById('btn-add-to-cart');
                            if(ab) ab.style.display='';
                        } else {
                            window._ecSelectedVariant = null;
                            if(resEl) resEl.style.display='none';
                            if(reqEl){reqEl.style.display='block';reqEl.textContent=(found?'Esta combinación no tiene stock.':'Combinación no disponible.');reqEl.style.color='#dc2626';}
                            var qw2=document.getElementById('ec-qty-selector');
                            if(qw2&&qw2.closest('.ropa-qty-wrap')) qw2.closest('.ropa-qty-wrap').style.display='none';
                            var ab2=document.getElementById('btn-add-to-cart');
                            if(ab2) ab2.style.display='none';
                        }
                    }

                    function setPriceDisplay(price){
                        var el=document.querySelector('.ropa-price__current');
                        if(!el)return;
                        var p=(price!=null)?price:basePrice;
                        el.textContent=symbol+' '+p.toFixed(2);
                    }

                    function updateStockUI(stock){
                        var s=Math.floor(stock||0);
                        var numEl=document.getElementById('ec-stock-num');
                        if(numEl) numEl.textContent=s;
                        var hint=document.getElementById('ec-stock-hint');
                        if(hint) hint.style.display='none';
                        var badge=document.getElementById('ec-stock-badge');
                        if(badge){badge.className=s>0?'ropa-stock-badge ropa-stock-badge--in':'ropa-stock-badge ropa-stock-badge--out';badge.textContent=s>0?'En stock':'Sin stock';}
                        var lowEl=document.getElementById('ec-low-stock');
                        var lowTxt=document.getElementById('ec-low-stock-txt');
                        if(lowEl){if(s>0&&s<=5){if(lowTxt)lowTxt.textContent='¡Solo quedan '+s+' unidades!';lowEl.style.display='flex';}else{lowEl.style.display='none';}}
                        var inp=document.getElementById('ec-qty-input');
                        if(inp){inp.max=s;if(parseInt(inp.value)>s)inp.value=Math.max(1,s);}
                    }

                    function swapMainImage(url){
                        var img=document.getElementById('ropa-main-img');
                        if(!img||img.src.endsWith(url))return;
                        img.style.opacity='0';
                        setTimeout(function(){img.src=url;img.style.opacity='1';},150);
                    }

                    refreshAvailability();
                    // Estado inicial: ocultar qty y add-to-cart
                    var qwI=document.getElementById('ec-qty-selector');
                    if(qwI&&qwI.closest('.ropa-qty-wrap')) qwI.closest('.ropa-qty-wrap').style.display='none';
                    var abI=document.getElementById('btn-add-to-cart');
                    if(abI) abI.style.display='none';
                    var reqI=document.getElementById('ec-variant-required');
                    if(reqI){reqI.style.display='block';reqI.style.color='#6b7280';}
                })();
                </script>
                @endif

                {{-- ── PRECIO ──────────────────────────────────── --}}
                <div class="ropa-price">
                    <span class="ropa-price__current" itemprop="price">
                        {{ $record->currency_type['symbol'] }} {{ number_format($record->sale_unit_price, 2) }}
                    </span>
                </div>

                {{-- ── STOCK ───────────────────────────────────── --}}
                <div class="ropa-stock">
                    <span id="ec-stock-badge" class="{{ $totalStock > 0 ? 'ropa-stock-badge ropa-stock-badge--in' : 'ropa-stock-badge ropa-stock-badge--out' }}">
                        {{ $totalStock > 0 ? 'En stock' : 'Sin stock' }}
                    </span>
                    <span class="ropa-stock__num">(<span id="ec-stock-num">{{ number_format($totalStock, 0) }}</span> disponibles)</span>
                    @if($record->has_variants)
                    <span id="ec-stock-hint" class="ropa-stock__hint">Selecciona una opción para ver disponibilidad</span>
                    @endif
                </div>
                <div id="ec-low-stock" class="ropa-low-stock" style="display:none">
                    <span id="ec-low-stock-txt"></span>
                </div>

                {{-- ── CANTIDAD + AGREGAR AL CARRITO ───────────── --}}
                @if($isAvailable)
                <div class="ropa-qty-wrap">
                    <div class="ropa-qty" id="ec-qty-selector">
                        <button type="button" class="ropa-qty__btn" onclick="ecQtyChange(-1)" aria-label="Reducir">−</button>
                        <input type="number" class="ropa-qty__input" id="ec-qty-input"
                               value="1" min="1" max="{{ $totalStock }}">
                        <button type="button" class="ropa-qty__btn" onclick="ecQtyChange(1)" aria-label="Aumentar">+</button>
                    </div>
                </div>

                <div class="ropa-actions">
                    <a href="#" class="ropa-btn-cart" id="btn-add-to-cart"
                       data-product="{{ json_encode($record) }}"
                       data-ec-cart="{{ json_encode($record) }}">
                        Agregar al Carro
                    </a>

                    {{-- Wishlist --}}
                    <button type="button" class="ropa-btn-wish ec-wishlist-btn"
                            data-item-id="{{ $record->id }}" title="Agregar a favoritos" aria-label="Favoritos">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                </div>

                @if($record->has_variants)
                <script>
                (function(){
                    document.getElementById('btn-add-to-cart').addEventListener('click', function(e){
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if(!window._ecSelectedVariant){
                            document.getElementById('ec-variant-required').style.display='block';
                            document.getElementById('ec-variant-required').textContent='Por favor selecciona todas las opciones.';
                            document.getElementById('ec-variant-required').style.color='#dc2626';
                            return;
                        }
                        var v=window._ecSelectedVariant;
                        var base=JSON.parse(this.getAttribute('data-ec-cart'));
                        base.variant_id=v.id;
                        base.variant_display_name=v.display_name;
                        base.variant_stock=v.stock||0;
                        base.stock=v.stock||0;
                        if(v.sale_unit_price!==null&&v.sale_unit_price!==undefined){
                            base.sale_unit_price=parseFloat(v.sale_unit_price);
                        }
                        var qtyEl=document.getElementById('ec-qty-input');
                        base.quantity=qtyEl?(parseInt(qtyEl.value)||1):1;
                        cart_add(JSON.stringify(base));
                    });
                })();
                </script>
                @endif
                @else
                <button type="button" class="ropa-btn-notify"
                        data-item-id="{{ $record->id }}" data-item-name="{{ $record->description }}">
                    Avisarme cuando haya stock
                </button>
                @endif

                {{-- ── CÓDIGO DE PRODUCTO ──────────────────────── --}}
                <div class="ropa-product-codes">
                    @if($record->internal_id)
                    <span class="ropa-code">Código: {{ $record->internal_id }}</span>
                    @endif
                </div>

                {{-- ── INFO DE ENTREGA ─────────────────────────── --}}
                @if($isAvailable)
                <div class="ropa-delivery">
                    <div class="ropa-delivery__row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span>Stock en tienda</span>
                    </div>
                    <div class="ropa-delivery__row">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        <span>Despacho a domicilio</span>
                    </div>
                </div>
                @endif

                {{-- ── WHATSAPP ─────────────────────────────────── --}}
                @if($showWhatsapp)
                <a href="{{ $waLink }}" class="ropa-btn-whatsapp" target="_blank" rel="noopener noreferrer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/></svg>
                    Consultar por WhatsApp
                </a>
                @endif

                {{-- ── COMPARTIR ────────────────────────────────── --}}
                <div class="ropa-share">
                    <span class="ropa-share__label">Compartir:</span>
                    <a href="https://wa.me/?text={{ rawurlencode($record->description . ' ' . $productUrl) }}"
                       target="_blank" rel="noopener" class="ropa-share__link" title="WhatsApp">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/></svg>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($productUrl) }}"
                       target="_blank" rel="noopener" class="ropa-share__link" title="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- ── TABS (descripción, reviews, specs) ──────────── --}}
<div class="product-single-tabs">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#ropa-desc">Descripción</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ropa-reviews" onclick="getRating('{{ $record->id }}')">Reviews</a></li>
        @if($record->technical_specifications)
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#ropa-specs">Especificaciones</a></li>
        @endif
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="ropa-desc">
            <p>{{ $record->description }}</p>
            @if($record->name)<p>{{ $record->name }}</p>@endif
        </div>
        <div class="tab-pane fade" id="ropa-reviews">
            <div class="ec-reviews-wrap" id="ec-reviews-wrap" data-item-id="{{ $record->id }}">
                <div class="ec-reviews-summary" id="ec-reviews-summary">
                    <div class="ec-reviews-avg">
                        <span class="ec-reviews-avg__num" id="ec-avg-num">—</span>
                        <div class="ec-reviews-avg__stars" id="ec-avg-stars"></div>
                        <span class="ec-reviews-avg__total" id="ec-avg-total">0 reseñas</span>
                    </div>
                </div>
                <div class="ec-reviews-list" id="ec-reviews-list">
                    <p class="ec-reviews-loading">Cargando reseñas...</p>
                </div>
            </div>
        </div>
        @if($record->technical_specifications)
        <div class="tab-pane fade" id="ropa-specs">
            @php
                $allowedTags = '<p><br><b><strong><i><em><ul><ol><li><table><tr><th><td>';
                $safeSpecs = strip_tags($record->technical_specifications, $allowedTags);
                $safeSpecs = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $safeSpecs);
            @endphp
            <div class="specs-content">{!! $safeSpecs !!}</div>
        </div>
        @endif
    </div>
</div>

{{-- Rating inline --}}
<script>
(function(){
    fetch('/ecommerce/reviews/{{ $record->id }}').then(function(r){return r.json();}).then(function(data){
        if(!data||!data.total)return;
        var starsEl=document.getElementById('ec-inline-stars');var linkEl=document.getElementById('ec-inline-rating-link');
        if(!starsEl||!linkEl)return;
        var avg=data.avg||0;var html='';
        for(var i=1;i<=5;i++){html+='<span class="ec-star '+(i<=Math.floor(avg)?'ec-star--full':(i-avg<1?'ec-star--half':'ec-star--empty'))+'">★</span>';}
        starsEl.innerHTML=html;linkEl.textContent=avg.toFixed(1)+' ('+data.total+')';
        document.getElementById('ec-product-rating').style.display='flex';
    }).catch(function(){});
})();
</script>

{{-- Galería interactiva --}}
<script>
(function(){
    var imgs=[];var current=0;
    document.querySelectorAll('.ropa-thumb img').forEach(function(t){imgs.push(t.src);});
    function show(i){
        current=i;
        var main=document.getElementById('ropa-main-img');
        if(main){main.style.opacity='0';setTimeout(function(){main.src=imgs[i];main.style.opacity='1';},120);}
        document.querySelectorAll('.ropa-thumb').forEach(function(t,idx){
            t.classList.toggle('ropa-thumb--active',idx===i);
        });
    }
    document.querySelectorAll('.ropa-thumb').forEach(function(t){
        t.addEventListener('click',function(){show(parseInt(this.dataset.index));});
    });
    var prev=document.getElementById('ropa-prev');var next=document.getElementById('ropa-next');
    if(prev)prev.addEventListener('click',function(){show((current-1+imgs.length)%imgs.length);});
    if(next)next.addEventListener('click',function(){show((current+1)%imgs.length);});
})();
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════
     CSS INLINE — THEME ROPA: PRODUCT PAGE
     Estilo Zara / Falabella
     ══════════════════════════════════════════════════════════════ --}}
@push('styles')
<style>
/* ═══ ROPA THEME: PRODUCT PAGE ═══ */

.ropa-product-page { padding: 1.5rem 0; }

/* ── Galería vertical ── */
.ropa-gallery { display: flex; gap: 12px; }
.ropa-gallery__thumbs {
    display: flex; flex-direction: column; gap: 8px;
    width: 72px; flex-shrink: 0;
}
.ropa-thumb {
    width: 72px; height: 90px; cursor: pointer;
    border: 2px solid transparent; overflow: hidden;
    transition: border-color .18s;
}
.ropa-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ropa-thumb--active { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }
.ropa-thumb:hover { border-color: #9ca3af; }
.ropa-gallery__main {
    flex: 1; position: relative; overflow: hidden;
    background: #f9fafb;
}
.ropa-gallery__img {
    width: 100%; height: auto; display: block;
    transition: opacity .15s ease;
}
.ropa-gallery__nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,.9); border: none; width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; opacity: 0; transition: opacity .2s;
}
.ropa-gallery:hover .ropa-gallery__nav { opacity: 1; }
.ropa-gallery__nav--prev { left: 8px; }
.ropa-gallery__nav--next { right: 8px; }

/* ── Info del producto ── */
.ropa-product-info { padding-left: 1.5rem; }
.ropa-brand {
    margin-bottom: .25rem;
}
.ropa-brand a {
    font-size: 12px; color: #6b7280; text-decoration: none;
    letter-spacing: .1em; font-weight: 600;
}
.ropa-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 26px; font-weight: 500; color: hsl(var(--primary-h), var(--primary-s), 15%);
    margin-bottom: .5rem; line-height: 1.3;
}
.ropa-rating {
    display: flex; align-items: center; gap: .5rem;
    margin-bottom: .75rem;
}
.ropa-rating__stars { display: flex; gap: 1px; }
.ropa-rating__count {
    font-size: .82rem; color: #6b7280; text-decoration: none;
}

/* ── Variantes ── */
.ropa-variants { margin: 1rem 0; }
.ropa-variant-group { margin-bottom: 1rem; }
.ropa-variant-label {
    font-size: 13px; font-weight: 600; color: #374151;
    margin-bottom: .5rem;
}
.ropa-variant-label__val { font-weight: 400; color: #6b7280; }
.ropa-variant-btns { display: flex; flex-wrap: wrap; gap: .5rem; }

/* Thumbnails de imagen (color) */
.ropa-vbtn--thumb {
    width: 60px; height: 60px; padding: 2px;
    border: 2px solid #e5e7eb; border-radius: 4px;
    background: #fff; cursor: pointer; overflow: hidden;
    transition: border-color .18s;
}
.ropa-vbtn--thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 2px; }
.ropa-vbtn--thumb:hover { border-color: #9ca3af; }
.ropa-vbtn--thumb[aria-pressed="true"] { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); box-shadow: 0 0 0 1px hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }

/* Swatches de color */
.ropa-vbtn--swatch {
    width: 36px; height: 36px; border-radius: 50%;
    border: 2px solid transparent; padding: 0;
    cursor: pointer; position: relative;
    transition: border-color .18s, transform .18s;
}
.ropa-swatch { display: block; width: 100%; height: 100%; border-radius: 50%; }
.ropa-vbtn--swatch:hover { transform: scale(1.12); }
.ropa-vbtn--swatch[aria-pressed="true"] { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }

/* Botones de talla (circular) */
.ropa-vbtn--size {
    width: 44px; height: 44px;
    border: 1.5px solid #d1d5db; border-radius: 50%;
    background: #fff; color: #374151;
    font-size: .82rem; font-weight: 500;
    cursor: pointer; display: flex;
    align-items: center; justify-content: center;
    transition: all .18s;
}
.ropa-vbtn--size:hover { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: hsl(var(--primary-h), var(--primary-s), 15%); }
.ropa-vbtn--size[aria-pressed="true"] {
    border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff;
}

/* Out of stock */
.ropa-vbtn--oos {
    opacity: .3; cursor: not-allowed; pointer-events: none;
}
.ropa-vbtn--size.ropa-vbtn--oos { text-decoration: line-through; }

.ropa-variant-msg {
    font-size: .8rem; margin-top: .25rem;
}

/* ── Precio ── */
.ropa-price { margin: 1rem 0 .5rem; }
.ropa-price__current {
    font-size: 28px; font-weight: 600; color: hsl(var(--primary-h), var(--primary-s), 15%);
}

/* ── Stock ── */
.ropa-stock { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; }
.ropa-stock-badge {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; padding: 3px 10px; border-radius: 3px;
}
.ropa-stock-badge--in { background: #dcfce7; color: #16a34a; }
.ropa-stock-badge--out { background: #fee2e2; color: #dc2626; }
.ropa-stock__num { font-size: .78rem; color: #6b7280; }
.ropa-stock__hint { font-size: .75rem; color: #9ca3af; display: block; width: 100%; margin-top: 2px; }
.ropa-low-stock {
    display: flex; align-items: center; gap: .3rem;
    font-size: .78rem; font-weight: 600; color: #c2410c;
    margin-bottom: .5rem;
}

/* ── Cantidad ── */
.ropa-qty-wrap { margin: 1rem 0; }
.ropa-qty {
    display: inline-flex; border: 1.5px solid #d1d5db; border-radius: 0;
}
.ropa-qty__btn {
    width: 40px; height: 40px; border: none; background: #fff;
    font-size: 18px; cursor: pointer; color: #374151;
    display: flex; align-items: center; justify-content: center;
}
.ropa-qty__btn:hover { background: #f3f4f6; }
.ropa-qty__input {
    width: 50px; height: 40px; text-align: center;
    border: none; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;
    font-size: 14px; -moz-appearance: textfield;
}
.ropa-qty__input::-webkit-inner-spin-button { -webkit-appearance: none; }

/* ── Botones de acción ── */
.ropa-actions { display: flex; gap: .75rem; align-items: stretch; margin-bottom: 1rem; }
.ropa-btn-cart {
    flex: 1; display: flex; align-items: center; justify-content: center;
    background: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: #fff; text-decoration: none;
    text-transform: uppercase; letter-spacing: .12em;
    font-weight: 600; font-size: 13px; padding: 14px 24px;
    border: none; cursor: pointer; transition: background .18s;
}
.ropa-btn-cart:hover { background: hsl(var(--primary-h), var(--primary-s), calc(var(--primary-l) - 10%)); color: #fff; text-decoration: none; }
.ropa-btn-wish {
    width: 50px; display: flex; align-items: center; justify-content: center;
    border: 1.5px solid #d1d5db; background: #fff; cursor: pointer;
    transition: border-color .18s;
}
.ropa-btn-wish:hover { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }
.ropa-btn-wish svg { color: #6b7280; }

.ropa-btn-notify {
    width: 100%; padding: 14px; background: #f3f4f6; color: #374151;
    border: 1.5px solid #d1d5db; font-weight: 600; font-size: 13px;
    text-transform: uppercase; letter-spacing: .06em; cursor: pointer;
}

/* ── Código, Entrega, WhatsApp, Compartir ── */
.ropa-product-codes {
    margin: .75rem 0; padding-top: .75rem; border-top: 1px solid #f3f4f6;
}
.ropa-code { font-size: .78rem; color: #6b7280; }
.ropa-delivery {
    margin: .75rem 0; padding: .75rem 0; border-top: 1px solid #f3f4f6;
    display: flex; flex-direction: column; gap: .5rem;
}
.ropa-delivery__row {
    display: flex; align-items: center; gap: .5rem;
    font-size: .82rem; color: #374151;
}
.ropa-btn-whatsapp {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: 10px 20px; background: #25d366; color: #fff;
    text-decoration: none; font-weight: 600; font-size: 13px;
    border-radius: 4px; margin: .5rem 0; transition: background .18s;
}
.ropa-btn-whatsapp:hover { background: #1da851; color: #fff; }
.ropa-share {
    display: flex; align-items: center; gap: .75rem;
    margin-top: 1rem; padding-top: .75rem; border-top: 1px solid #f3f4f6;
}
.ropa-share__label { font-size: .78rem; color: #6b7280; }
.ropa-share__link {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 50%;
    border: 1px solid #e5e7eb; color: #6b7280;
    text-decoration: none; transition: border-color .18s, color .18s;
}
.ropa-share__link:hover { border-color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); color: hsl(var(--primary-h), var(--primary-s), 15%); }

/* ── Responsive ── */
@media (max-width: 767px) {
    .ropa-gallery { flex-direction: column-reverse; }
    .ropa-gallery__thumbs { flex-direction: row; width: 100%; overflow-x: auto; }
    .ropa-thumb { width: 60px; height: 75px; flex-shrink: 0; }
    .ropa-product-info { padding-left: 0; margin-top: 1rem; }
    .ropa-title { font-size: 22px; }
    .ropa-price__current { font-size: 24px; }
}
</style>
@endpush
