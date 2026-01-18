@foreach ($dataPaginate as $item)
    <div>
        <div class="product product-style {{ stock($item, $configuration) ? 'productdisabled' : '' }}">
            <div class="position-relative">
                @php
                    $configuration = \App\Models\Tenant\Configuration::first();
                    $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
                    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
                        ? asset('logo/imagen-no-disponible.jpg')
                        : asset('storage/defaults/' . $defaultImage);
                            
                    $imagePath = $item->image !== 'imagen-no-disponible.jpg'
                        ? asset('storage/uploads/items/' . $item->image)
                        : $defaultImagePath;
                @endphp
                <img src="{{ $imagePath }}" class="image-product" alt="{{ $item->description }}">
                {{-- <a href="/restaurant/item/{{ $item->id }}" class="product-image product-image-list-restaurant">
                    <img src="{{ $imagePath }}" class="image" alt="{{ $item->description }}">
                </a> --}}
                {{-- <a href="{{route('restaurant.item_partial', ['id' => $item->id])}}" class="btn-quickview">Vista Rápida</a> --}}
                {{-- <span class="product-label label-sale">-20%</span> --}}
                @if(json_encode($item->is_new) == 1)
                    <span class="product-label label-hot">Nuevo</span>
                @endif
                @if(stock($item, $configuration))
                    <span class="product-label product-danger">AGOTADO</span>
                @endif
            </div>
            <div class="product-details-restaurant">
                <div class="product-information">
                    <h2 class="product-title-restaurant">
                        <a href="{{route('restaurant.item', ['id' => $item->id])}}">{{ $item->description }}</a>
                    </h2>

                    @if(isset($preferences['show_description']) && $preferences['show_description'] == 1)
                        @if ($item->name)
                            <p class="text-muted product-description">
                                {{ $item->name }}
                            </p>
                        @else
                            <p class="text-muted product-description" style="opacity: .5">
                                Sin descripción disponible.
                            </p>
                        @endif
                    @endif                                      
                </div>

                <div class="product-price-restaurante mt-auto">
                    
                    @if(isset($preferences['show_stock']) && $preferences['show_stock'] == 1)
                        @if ($item->stock > 0)
                            <h3 class="product-stock font-weight-bold">Disponible: <span>{{ number_format($item ->stock, 0) }}</span></h3>
                        @else
                            <h3 class="product-stock text-danger font-weight-bold">Sin stock</h3>
                        @endif
                    @endif  

                    <div class="d-flex align-items-center justify-content-between">
                        <div class="price-box-restaurant">
                            <!-- <span class="old-price">S/ {{ number_format( ($item->sale_unit_price * 1.2 ) , 2 )}}</span> -->
                            <span class="product-price-restaurant">{{ $item->currency_type['symbol'] }} {{ number_format($item->sale_unit_price, 2) }}</span>
                        </div>
                        <div class="product-action">
                            <a href="#" class="paction add-cart add-cart-list" data-product="{{ json_encode( $item ) }}" title="Agregar al carrito">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z" /><path d="M9 11v-5a3 3 0 0 1 6 0v5" /></svg>
                                <span>Añadir</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endforeach

<?php

    function stock($item, $config)
    {
        if($config) {
            $stock=0;
            foreach ($item->warehouses as $key => $value) {
                $stock += $value->stock;
            }
            return ($stock > 0) ? false : true;
        }
    }
?>

<style>
    /* .product-style {
        border-style: solid;
        border-width: 1px;
        border-color: "#ddd";
        margin: 10px 1px;
    } */
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
    .productdisabled
    {
        pointer-events: none;
        /* opacity: 0.7; */
    }
    .add-cart::before{
        content: none
    }
</style>
