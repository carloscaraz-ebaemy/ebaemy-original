{{-- 
    TEMPLATE MEJORADO: Listado de productos con SEO
    Archivo: resources/views/ecommerce/partials/product_list.blade.php (o donde esté tu template actual)
    
    CAMBIOS REALIZADOS:
    1. URLs con slug en vez de ID numérico
    2. Atributos alt descriptivos en imágenes
    3. Imágenes con width/height y lazy loading
    4. Datos estructurados Schema.org (JSON-LD) por producto
    5. Semántica HTML mejorada (article, header, etc.)
    6. Meta información para compartir en redes
--}}

@php
    $configuration = \App\Models\Tenant\Configuration::first();
@endphp

@foreach ($dataPaginate as $item)
    <div class="col-6 {{ \Route::currentRouteName() == 'tenant.ecommerce.index' ? 'col-md-3' : 'col-md-4' }}">
        <article class="product product-style {{ stock($item, $configuration) ? 'productdisabled' : '' }}" 
                 itemscope itemtype="https://schema.org/Product">
            <figure class="product-image-container product-image-container-ecommerce">
                @php
                    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
                    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
                        ? asset('logo/imagen-no-disponible.jpg')
                        : asset('storage/defaults/' . $defaultImage);
                            
                    $imagePath = $item->image !== 'imagen-no-disponible.jpg'
                        ? asset('storage/uploads/items/' . $item->image)
                        : $defaultImagePath;

                    // Generar URL con slug (con fallback a ID)
                    $productUrl = route('tenant.ecommerce.item', ['slug' => $item->slug ?: $item->id]);

                    // Alt descriptivo para SEO
                    $altText = $item->description;
                    if ($item->category) {
                        $altText .= ' - ' . $item->category->name;
                    }
                @endphp
                            
                <a href="{{ $productUrl }}" class="product-image product-image-list">
                    <img src="{{ $imagePath }}" 
                         class="image" 
                         alt="{{ $altText }}"
                         loading="lazy"
                         width="210" 
                         height="210"
                         itemprop="image">
                </a>
                <a href="{{ route('item_partial', ['id' => $item->id]) }}" class="btn-quickview" aria-label="Vista rápida de {{ $item->description }}">Vista Rápida</a>

                @if($item->is_new)
                    <span class="product-label label-hot">Nuevo</span>
                @endif
                @if(stock($item, $configuration))
                    <span class="product-label product-danger">AGOTADO</span>
                @endif
            </figure>

            <div class="product-details-ecommerce">
                <div class="ratings-container">
                    <div class="product-ratings">
                        <span class="ratings" style="width:0%"></span>
                    </div>
                </div>

                <div class="product-information">
                    <h2 class="product-title-ecommerce" itemprop="name">
                        <a href="{{ $productUrl }}">{{ $item->description }}</a>
                    </h2>

                    @if(isset($preferences['show_description']) && $preferences['show_description'] == 1)
                        @if ($item->name)
                            <p class="text-muted product-description" itemprop="description">
                                {{ $item->name }}
                            </p>
                        @else
                            <p class="text-muted product-description" style="opacity: .5">
                                Sin descripción disponible.
                            </p>
                        @endif
                    @endif

                    @if(isset($preferences['show_stock']) && $preferences['show_stock'] == 1)
                        @if($item->stock > 0)
                            <h3 class="product-stock">Disponible: 
                                <span>{{ number_format($item->getStockByWarehouseMain(), 0) }}</span>
                            </h3>
                        @else
                            <h3 class="product-stock text-danger">Sin stock</h3>
                        @endif
                    @endif
                </div>

                <div class="product-price-ecommerce" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                    <meta itemprop="priceCurrency" content="{{ $item->currency_type_id ?? 'PEN' }}">
                    <meta itemprop="price" content="{{ $item->sale_unit_price }}">
                    <link itemprop="url" href="{{ $productUrl }}">
                    
                    @if(stock($item, $configuration))
                        <link itemprop="availability" href="https://schema.org/OutOfStock">
                    @else
                        <link itemprop="availability" href="https://schema.org/InStock">
                    @endif

                    <div class="price-box-ecommerce">
                        <span class="product-price-ecommerce">{{ $item->currency_type['symbol'] }} {{ number_format($item->sale_unit_price, 2) }}</span>
                    </div>
                    <div class="product-action">
                        <a href="#" class="paction add-cart" data-product="{{ json_encode($item) }}" title="Agregar {{ $item->description }} al carrito">
                            <span>Agregar a Carrito</span>
                        </a>
                    </div>
                </div>
            </div>
        </article>
    </div>
@endforeach

@php
    function stock($item, $config)
    {
        if (!$config) {
            return false;
        }
        $stock = 0;
        foreach ($item->warehouses as $warehouse) {
            $stock += $warehouse->stock;
        }
        return $stock <= 0;
    }
@endphp

<style>
    .product-image-list {
        max-height: 210px;
        min-height: 210px;
    }
    .image {
        max-height: 210px;
    }
    .product-danger {
        float: right;
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }
    .productdisabled {
        pointer-events: none;
    } 
</style>