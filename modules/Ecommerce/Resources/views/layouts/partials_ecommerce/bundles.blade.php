@php
    // Excluir bundles que ya están en Flash Sale activa para no duplicar
    $flashItemIds = (isset($flashSale) && $flashSale && $flashSale->items)
        ? $flashSale->items->pluck('id')->toArray()
        : [];
    $filteredBundles = isset($bundles) ? $bundles->filter(fn($b) => !in_array($b->id, $flashItemIds)) : collect();
@endphp
@if($filteredBundles->count() > 0)
<section class="ec-home-section ec-bundles-section" aria-label="Paquetes y combos">
    <div class="ec-section-header">
        <h2 class="ec-section-title">Paquetes y combos</h2>
        <span class="ec-section-badge">¡Ahorra más!</span>
    </div>

    <div class="row">
        @foreach($filteredBundles as $bundle)
        @php
            // Stock del pack = mínimo de (stock_componente / qty_en_pack)
            $stock = 0;
            if ($bundle->sets && $bundle->sets->count() > 0) {
                $componentStocks = [];
                foreach ($bundle->sets as $setItem) {
                    if ($setItem->individual_item && $setItem->quantity > 0) {
                        $compStock = $setItem->individual_item->warehouses
                            ? $setItem->individual_item->warehouses->sum('stock')
                            : 0;
                        $componentStocks[] = floor($compStock / $setItem->quantity);
                    }
                }
                $stock = count($componentStocks) > 0 ? min($componentStocks) : 0;
            } else {
                $stock = $bundle->warehouses->sum('stock');
            }
            $outOfStock  = $stock <= 0;
            $imagePath   = ($bundle->image && $bundle->image !== 'imagen-no-disponible.jpg')
                ? asset('storage/uploads/items/' . $bundle->image)
                : asset('logo/imagen-no-disponible.jpg');
            $productUrl  = route('tenant.ecommerce.bundle', ['slug' => $bundle->slug ?: $bundle->id]);
            $priceSet    = $bundle->sale_unit_price_set ?: $bundle->sale_unit_price;
            $priceNormal = $bundle->sale_unit_price;
            $hasDiscount = $priceSet && $priceSet < $priceNormal;
            $symbol      = $bundle->currency_type['symbol'] ?? 'S/';
        @endphp

        <div class="col-12 col-sm-6 col-lg-4 mb-4">
            <article class="ec-bundle-card{{ $outOfStock ? ' ec-bundle-card--oos' : '' }}">

                {{-- Imagen --}}
                <a href="{{ $productUrl }}" class="ec-bundle-card__img-wrap">
                    <img src="{{ $imagePath }}"
                         alt="{{ $bundle->description }}"
                         loading="lazy"
                         class="ec-bundle-card__img"
                         onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                    @if($hasDiscount)
                        @php $pct = round((1 - $priceSet / $priceNormal) * 100); @endphp
                        <span class="ec-bundle-discount-badge">-{{ $pct }}%</span>
                    @endif
                </a>

                {{-- Cuerpo --}}
                <div class="ec-bundle-card__body">
                    <h3 class="ec-bundle-card__title">
                        <a href="{{ $productUrl }}">{{ $bundle->description }}</a>
                    </h3>

                    {{-- Componentes del bundle --}}
                    @if($bundle->sets && $bundle->sets->count() > 0)
                    <ul class="ec-bundle-items" aria-label="Incluye">
                        @foreach($bundle->sets->take(4) as $setItem)
                            @if($setItem->individual_item)
                            <li class="ec-bundle-items__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24"
                                     fill="currentColor" aria-hidden="true" style="color:var(--primary-color,#333);flex-shrink:0">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                                </svg>
                                <span>{{ number_format($setItem->quantity, 0) }}x {{ $setItem->individual_item->description }}</span>
                            </li>
                            @endif
                        @endforeach
                        @if($bundle->sets->count() > 4)
                            <li class="ec-bundle-items__more">+{{ $bundle->sets->count() - 4 }} más...</li>
                        @endif
                    </ul>
                    @endif

                    {{-- Precio --}}
                    <div class="ec-bundle-card__footer">
                        <div class="ec-bundle-card__price">
                            @if($hasDiscount)
                                <span class="ec-bundle-price-old">{{ $symbol }} {{ number_format($priceNormal, 2) }}</span>
                            @endif
                            <span class="ec-bundle-price-current">{{ $symbol }} {{ number_format($priceSet, 2) }}</span>
                        </div>

                        @if(!$outOfStock)
                            <a href="{{ $productUrl }}"
                               class="ec-btn-cart ec-bundle-btn"
                               aria-label="Ver pack {{ $bundle->description }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                </svg>
                                Ver pack
                            </a>
                        @else
                            <span class="ec-bundle-oos">Agotado</span>
                        @endif
                    </div>
                </div>
            </article>
        </div>
        @endforeach
    </div>
</section>
@endif
