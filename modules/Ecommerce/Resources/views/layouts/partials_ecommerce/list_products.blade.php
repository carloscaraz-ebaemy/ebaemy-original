@php
    $configuration = \App\Models\Tenant\Configuration::first();

    // Guardas contra "Cannot redeclare": una pagina puede incluir este listado
    // mas de una vez (grilla + rail de relacionados) y la segunda declaracion
    // aborta el request con un fatal. Cada funcion se guarda por separado:
    // los themes de nicho declaran stock() por su cuenta, asi que stockCount()
    // puede seguir haciendo falta aunque stock() ya exista.
    if (!function_exists('stock')) {
        function stock($item, $config) {
            if (!$config) return false;
            return stockCount($item) <= 0;
        }
    }
    if (!function_exists('stockCount')) {
        function stockCount($item) {
            $total = 0;
            foreach ($item->warehouses as $wh) {
                $total += (float) $wh->stock;
            }
            return $total;
        }
    }

    // Ratings de TODA la página en una sola query. Preguntar por producto
    // seria un N+1 de 24 consultas; el listado solo necesita promedio y conteo.
    // Solo se calcula si la tarjeta muestra rating.
    if (!isset($__ratingsLoaded)) {
        $__ratingsLoaded = true;
        $__ratings = [];
        if (\App\Services\EcommerceCardOptions::enabled('rating')) {
            try {
                $__ratings = \App\Models\Tenant\ProductReview::approved()
                    ->whereIn('item_id', collect($dataPaginate->items())->pluck('id'))
                    ->groupBy('item_id')
                    ->selectRaw('item_id, AVG(rating) as avg_rating, COUNT(*) as total')
                    ->get()
                    ->keyBy('item_id')
                    ->all();
            } catch (\Exception $e) {}
        }
    }

@endphp

@php
    // Precarga de la primera foto de la rejilla: es la candidata a LCP y el
    // navegador tardaba 9,4 s en pedirla porque estaba enterrada detras de
    // las hojas de estilo y los scripts del <head>. Con el preload empieza a
    // bajar mientras el resto todavia se descarga.
    //
    // Solo en la primera pagina: de la 2 en adelante no hay primer pliegue que
    // adelantar. Y solo con foto REAL: adelantar un marcador seria pagar una
    // peticion por una imagen que ni siquiera deberia existir.
    $primeraFoto = null;
    if ((int) request('page', 1) === 1) {
        foreach ($dataPaginate as $__p) {
            if ($__p->image && $__p->image !== 'imagen-no-disponible.jpg') {
                $primeraFoto = asset('storage/uploads/items/' . $__p->image);
                break;
            }
        }
    }
@endphp
@if($primeraFoto)
    @push('head_extra')
        <link rel="preload" as="image" fetchpriority="high" href="{{ $primeraFoto }}">
    @endpush
@endif

@foreach ($dataPaginate as $item)
@php
    $outOfStock   = stock($item, $configuration);
    $totalStock   = stockCount($item);
    // Para productos con variantes: stock total = suma de stock de variantes activas
    if ($item->has_variants && $item->relationLoaded('variants')) {
        $variantStock = $item->variants->sum('stock');
        $outOfStock   = $variantStock <= 0;
        $totalStock   = $variantStock;
    }
    $isNew        = $item->created_at && $item->created_at->diffInDays(now()) <= 30;
    $isLowStock   = !$outOfStock && $totalStock > 0 && $totalStock <= 5;
    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultPath  = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $hasRealImage = $item->image && $item->image !== 'imagen-no-disponible.jpg';
    $imagePath    = $hasRealImage
        ? asset('storage/uploads/items/' . $item->image)
        : $defaultPath;
    // Segunda imagen para hover swap
    $hoverImage = null;
    if ($item->relationLoaded('images') && $item->images->isNotEmpty()) {
        $firstGallery = $item->images->first();
        if ($firstGallery && $firstGallery->image && $firstGallery->image !== 'imagen-no-disponible.jpg') {
            $hoverImage = asset('storage/uploads/items/' . $firstGallery->image);
        }
    }
    // Las primeras tarjetas NO van diferidas: son las del primer pliegue y de
    // ahi sale el elemento que Google mide como LCP. Con todas en `lazy` el
    // navegador no pedia esa imagen hasta cerrar el layout, y el desglose del
    // LCP lo cantaba: 9,4 s de «Load Delay» sobre 15,2 s totales, el 62 %.
    // Cuatro cubre dos filas de movil y una de escritorio.
    //
    // Se cuentan las tarjetas CON FOTO, no las posiciones. Si las primeras del
    // listado son productos sin imagen —en alasitas hay 9 asi— contar
    // posiciones gastaria el cupo en marcadores que ni siquiera hacen una
    // peticion, y la foto de verdad, que es la que mide Google, se quedaria
    // diferida. Es el fallo que me saco la prueba en local.
    $conFoto      = ($conFoto ?? 0) + ($hasRealImage ? 1 : 0);
    $cardEager    = $hasRealImage && $conFoto <= 4;
    $cardPriority = $hasRealImage && $conFoto === 1;

    $productUrl   = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);
    $altText      = $item->description . ($item->category ? ' — ' . $item->category->name : '');
    $symbol       = $item->currency_type['symbol'] ?? 'S/';
    // Precio efectivo: flash sale, pack u oferta vigente. Una sola
    // definicion, compartida con las tarjetas de los themes de nicho.
    // Ambos mapas (flash sales y rangos de variantes) se resuelven una sola vez
    // por request y se memoizan dentro del servicio, así que llamarlos en cada
    // iteración del listado no repite consultas.
    //
    // OJO: comentario PHP y no Blade. Dentro de @php el contenido es PHP crudo y
    // un {{-- --}} se compila tal cual → parse error. Ver la guía del proyecto.
    $pricing       = \App\Services\EcommerceItemPricing::for(
        $item,
        \App\Services\EcommerceItemPricing::flashPrices(),
        \App\Services\EcommerceItemPricing::variantRanges()
    );
    $displayPrice  = $pricing->display;
    $originalPrice = $pricing->original;
    $hasDiscount   = $pricing->hasDiscount;
    $discountPct   = $pricing->discountPct;
    $price         = $pricing->formatted();
    $rating        = $__ratings[$item->id] ?? null;
    // Stagger delay (1-based position in the page)
    $loop_i       = $loop->iteration;
    $delay        = min($loop_i * 40, 400);
@endphp

<div class="col-6 col-md-4 col-lg-3 mb-4 product-col-item"
     style="animation-delay: {{ $delay }}ms">

    <article class="pcard{{ $outOfStock ? ' pcard--oos' : '' }}"
             itemscope itemtype="https://schema.org/Product">

        {{-- ── IMAGE SECTION ───────────────────────────────── --}}
        <div class="pcard__media">

            {{-- Badges top-left --}}
            <div class="pcard__badges">
                {{-- "Agotado" no es configurable: ocultarlo haria creer al
                     comprador que puede comprar algo que no hay. --}}
                @if($outOfStock)
                    <span class="pbadge pbadge--oos">Agotado</span>
                @elseif($isLowStock && !$item->has_variants && \App\Services\EcommerceCardOptions::enabled('badge_stock'))
                    <span class="pbadge pbadge--hot">
                        <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                        Últimas {{ $totalStock }}
                    </span>
                @elseif($isNew && \App\Services\EcommerceCardOptions::enabled('badge_new'))
                    <span class="pbadge pbadge--new">Nuevo</span>
                @endif
                @if($hasDiscount && $discountPct > 0)
                    @cardOption('discount_pct')
                    <span class="pbadge pbadge--discount">-{{ $discountPct }}%</span>
                    @endcardOption
                @endif
                @if($item->has_variants)
                    @cardOption('badge_variants')
                    <span class="pbadge pbadge--variants">Variantes</span>
                    @endcardOption
                @endif
            </div>

            {{-- Wishlist (top-right, glassmorphism) --}}
            @cardOption('wishlist')
            <button type="button"
                    class="pcard__wish ec-btn-wishlist"
                    data-wishlist-id="{{ $item->id }}"
                    aria-pressed="false"
                    title="Guardar en favoritos"
                    aria-label="Guardar {{ $item->description }} en favoritos">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>
            @endcardOption

            {{-- Product image --}}
            <a href="{{ $productUrl }}"
               class="pcard__img-link{{ $hoverImage ? ' pcard__img-link--has-hover' : '' }}"
               tabindex="-1" aria-label="{{ $altText }}">
                @if($hasRealImage)
                <img src="{{ $imagePath }}"
                     alt="{{ $altText }}"
                     loading="{{ $cardEager ? 'eager' : 'lazy' }}"
                     decoding="{{ $cardEager ? 'sync' : 'async' }}"
                     @if($cardPriority) fetchpriority="high" @endif
                     width="400" height="400"
                     class="pcard__img pcard__img--primary"
                     onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'"
                     itemprop="image">
                @else
                    {{-- Producto sin foto. Antes se servia
                         `logo/imagen-no-disponible.jpg`, un JPG de 20 KB que
                         ademas llevaba el `alt` del producto —le contaba a
                         Google y a un lector de pantalla que ESA imagen era el
                         producto— y que en la portada de alasitas acabo siendo
                         el propio elemento LCP. Un marcador no deberia ser
                         nunca lo mas importante que pinta la pagina.
                         Ahora es un dibujo en el HTML: cero peticiones, cero
                         bytes y `aria-hidden`, porque no aporta nada que leer.
                         Tampoco lleva `itemprop="image"`: declarar una imagen
                         de producto que no es el producto es mentirle a
                         Google. --}}
                    <div class="pcard__img pcard__img--primary pcard__img--vacia"
                         role="img" aria-label="Producto sin imagen"
                         style="display:flex;align-items:center;justify-content:center;background:#f3f4f6">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none"
                             stroke="#c3c9d2" stroke-width="1.3" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="m21 15-5-5L5 21"/>
                        </svg>
                    </div>
                @endif

                {{-- Segunda imagen: aparece al hacer hover --}}
                @if($hoverImage)
                <img src="{{ asset('porto-ecommerce/assets/images/placeholder.svg') }}"
                     data-src="{{ $hoverImage }}"
                     alt="{{ $altText }}"
                     width="400" height="400"
                     class="pcard__img pcard__img--hover ec-img-lazy"
                     loading="lazy"
                     aria-hidden="true">
                @endif
            </a>

            {{-- Hover overlay: si el tenant apaga las dos acciones no se
                 renderiza, para no dejar una capa vacia que igual intercepta
                 el hover sobre la imagen. --}}
            @php
                $showQuickView = \App\Services\EcommerceCardOptions::enabled('quickview');
                $showCompare   = \App\Services\EcommerceCardOptions::enabled('compare');
            @endphp
            @if(!$outOfStock && ($showQuickView || $showCompare))
            <div class="pcard__overlay">
                @if($showQuickView)
                <button type="button"
                        class="pcard__quickview ec-btn-quickview"
                        data-item-id="{{ $item->id }}"
                        aria-label="Vista rápida de {{ $item->description }}"
                        title="Vista rápida">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Vista rápida
                </button>
                @endif
                @if($showCompare)
                <button type="button"
                        class="pcard__compare-mini ec-btn-compare"
                        data-compare-id="{{ $item->id }}"
                        data-product="{{ json_encode($item) }}"
                        aria-pressed="false"
                        title="Comparar"
                        aria-label="Comparar {{ $item->description }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                        <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                    </svg>
                </button>
                @endif
            </div>
            @endif

        </div>{{-- /pcard__media --}}

        {{-- ── BODY ────────────────────────────────────────── --}}
        <div class="pcard__body">

            {{-- Marca: la pide el rubro tecnologia (Samsung, Logitech...).
                 Apagada por defecto porque no todos los tenants la cargan. --}}
            @cardOption('brand')
                @if($item->relationLoaded('brand') && $item->brand)
                <span class="pcard__brand" itemprop="brand">{{ $item->brand->name }}</span>
                @endif
            @endcardOption

            {{-- Category label --}}
            @cardOption('category')
                @if($item->category)
                <span class="pcard__cat" itemprop="category">{{ $item->category->name }}</span>
                @endif
            @endcardOption

            {{-- Title --}}
            <h2 class="pcard__title" itemprop="name">
                <a href="{{ $productUrl }}">{{ $item->description }}</a>
            </h2>

            {{-- Rating: solo si el producto tiene resenas aprobadas. Sin
                 resenas no se muestran 5 estrellas vacias, que se leen como
                 producto mal calificado. --}}
            @if($rating)
            <div class="pcard__rating" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                <meta itemprop="ratingValue" content="{{ round($rating->avg_rating, 1) }}">
                <meta itemprop="reviewCount" content="{{ $rating->total }}">
                <span class="pcard__stars" aria-label="{{ round($rating->avg_rating, 1) }} de 5 estrellas">
                    @for($i = 1; $i <= 5; $i++)
                    <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"
                         fill="{{ $i <= round($rating->avg_rating) ? 'currentColor' : 'none' }}"
                         stroke="currentColor" stroke-width="1.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    @endfor
                </span>
                <span class="pcard__rating-count">({{ $rating->total }})</span>
            </div>
            @endif

            {{-- Short desc --}}
            @if(isset($preferences['show_description']) && $preferences['show_description'] == 1 && $item->name)
            <p class="pcard__desc">{{ \Illuminate\Support\Str::limit($item->name, 55) }}</p>
            @endif

            {{-- Low stock bar --}}
            @if($isLowStock)
            <div class="pcard__low-stock" aria-label="Stock bajo">
                <span>¡Solo {{ $totalStock }} disponibles!</span>
                <div class="pcard__stock-bar" role="progressbar" aria-valuenow="{{ $totalStock }}" aria-valuemin="0" aria-valuemax="5">
                    <div class="pcard__stock-fill" style="width:{{ min(100, ($totalStock / 5) * 100) }}%"></div>
                </div>
            </div>
            @elseif(isset($preferences['show_stock']) && $preferences['show_stock'] == 1 && !$outOfStock)
            <p class="pcard__stock-pill">
                <span class="pcard__stock-dot"></span>
                En stock
            </p>
            @endif

            {{-- Price --}}
            <div class="pcard__price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <meta itemprop="priceCurrency" content="{{ $item->currency_type_id ?? 'PEN' }}">
                <meta itemprop="price"         content="{{ $item->sale_unit_price }}">
                <link  itemprop="url"           href="{{ $productUrl }}">
                @if($outOfStock)
                    <link itemprop="availability" href="https://schema.org/OutOfStock">
                @else
                    <link itemprop="availability" href="https://schema.org/InStock">
                @endif
                @if($hasDiscount)
                    @cardOption('old_price')
                    <span style="text-decoration:line-through;color:#9ca3af;font-size:12px;margin-right:4px">{{ $symbol }} {{ number_format($originalPrice, 2) }}</span>
                    @endcardOption
                @endif
                <span class="pcard__price-current" style="{{ $hasDiscount ? 'color:#e53e3e' : '' }}">{{ $symbol }} {{ $price }}</span>
            </div>

            {{-- CTA --}}
            @if($item->has_variants && !$outOfStock)
                <a href="{{ $productUrl }}"
                   class="pcard__cta pcard__cta--variants"
                   aria-label="Ver opciones de {{ $item->description }}"
                   title="Elegir variante">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 8 16 12 12 16"/><line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    <span class="pcard__cta-text">Elegir opciones</span>
                </a>
            @elseif(!$outOfStock && !\App\Services\EcommerceCardOptions::enabled('add_to_cart'))
                {{-- Sin carrito rapido: la tarjeta lleva a la ficha, donde el
                     comprador elige cantidad y ve el detalle. --}}
                <a href="{{ $productUrl }}" class="pcard__cta pcard__cta--variants"
                   aria-label="Ver {{ $item->description }}">
                    <span class="pcard__cta-text">Ver producto</span>
                </a>
            @elseif(!$outOfStock)
                <button type="button"
                        class="pcard__cta ec-btn-cart"
                        data-ec-cart="{{ json_encode(array_merge($item->toArray(), ['sale_unit_price' => $displayPrice, 'original_price' => ($originalPrice > 0 && $originalPrice > $displayPrice) ? $originalPrice : null])) }}"
                        aria-label="Agregar {{ $item->description }} al carrito"
                        title="Agregar al carrito">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span class="pcard__cta-text">Agregar al carrito</span>
                </button>
            @else
                <button type="button"
                        class="pcard__notify ec-btn-notify"
                        data-item-id="{{ $item->id }}"
                        data-item-name="{{ $item->description }}"
                        title="Notificarme cuando esté disponible">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Avisar cuando haya stock
                </button>
            @endif

        </div>{{-- /pcard__body --}}
    </article>
</div>
@endforeach
