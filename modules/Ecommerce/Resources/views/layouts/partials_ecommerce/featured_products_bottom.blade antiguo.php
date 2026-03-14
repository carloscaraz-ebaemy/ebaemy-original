@php

$path = explode('/', request()->path());
$slug_or_id = (isset($path[2])) ? $path[2] : 0;

// Buscar el item actual por slug o ID para obtener el ID real
$currentItem = \App\Models\Tenant\Item::where('slug', $slug_or_id)->first() 
    ?? \App\Models\Tenant\Item::find($slug_or_id);
$item_id = $currentItem ? $currentItem->id : 0;

@endphp
<div class="container">
    <h2 class="carousel-title">Productos Relacionados</h2>

    <div class="featured-products owl-carousel owl-theme owl-dots-top">

        @foreach ($items as $item)
            @if($item && $item->tags)
                @inject('intersectTag', 'App\Services\TagsIntersect')

                @if( $intersectTag->intersect($item->tags, $item_id) )
                    <div class="product">
                        <figure class="product-image-container">
                            <a href="/ecommerce/item/{{ $item->slug ?? $item->id }}" class="product-image">
                                <img src="{{ asset('storage/uploads/items/'.$item->image) }}" alt="{{ $item->description }}">
                            </a>
                            <a href="{{route('item_partial', ['id' => $item->id])}}" class="btn-quickview">Vista Rápida</a>
                        </figure>
                        <div class="product-details">
                            <div class="ratings-container">
                                <div class="product-ratings">
                                    <span class="ratings" style="width:80%"></span>
                                </div>
                            </div>
                            <h2 class="product-title">
                                <a href="/ecommerce/item/{{ $item->slug ?? $item->id }}">{{$item->description}}</a>
                            </h2>
                            <div class="price-box">
                                <span class="product-price">{{ $item->currency_type_symbol }} {{ number_format($item->sale_unit, 2) }}</span>
                            </div>

                            <div class="product-action">
                                <a href="#" class="paction add-cart" data-product="{{ json_encode( $item ) }}" title="Add to Cart">
                                    <span>Agregar a Carrito</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        @endforeach

    </div>
</div>