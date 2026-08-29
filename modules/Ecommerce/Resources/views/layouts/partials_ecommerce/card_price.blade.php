{{--
    Precio de la tarjeta, con microdatos schema.org.

    Lo usan las tarjetas de los themes de nicho, que son forks del markup y
    antes mostraban `sale_unit_price` a secas: sin flash sale, sin ofertas,
    sin precio de pack y sin datos estructurados para Google.

    El cálculo vive en App\Services\EcommerceItemPricing — acá solo se pinta.

    Parámetros:
      $item       producto
      $pricing    EcommerceItemPricing::for($item, $flash)
      $productUrl URL de la ficha
      $outOfStock bool
      $priceClass clase CSS del precio actual, propia de cada theme
--}}
@php
    $priceClass = $priceClass ?? 'pcard__price-current';
@endphp
<div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
    <meta itemprop="priceCurrency" content="{{ $item->currency_type_id ?? 'PEN' }}">
    <meta itemprop="price" content="{{ $pricing->display }}">
    <link itemprop="url" href="{{ $productUrl }}">
    <link itemprop="availability" href="https://schema.org/{{ $outOfStock ? 'OutOfStock' : 'InStock' }}">

    @if($pricing->hasDiscount)
        @cardOption('old_price')
        <span class="ec-card-old-price">{{ $pricing->symbol }} {{ $pricing->formattedOriginal() }}</span>
        @endcardOption
    @endif
    <span class="{{ $priceClass }}{{ $pricing->hasDiscount ? ' ec-card-price--sale' : '' }}">
        {{ $pricing->symbol }} {{ $pricing->formatted() }}
    </span>
</div>
