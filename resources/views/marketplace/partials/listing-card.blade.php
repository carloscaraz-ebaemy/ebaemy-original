{{--
    Card del marketplace — partial compartido por:
      - marketplace.index            (home + listado general)
      - marketplace.category_official (taxonomía oficial /marketplace/c/{slug})
      - marketplace.category         (legacy categorías por nombre)
      - marketplace.tenant           (página por tienda /marketplace/tienda/{X})

    Vars esperadas:
      $listing       MarketplaceListing — con primary_image_url, color_dots,
                                          variant_thumbs, active_color_hex
                                          (los pone el controller correspondiente)

    Vars opcionales:
      $showTopBadge  bool   — primer producto de la home (badge "⭐ Top")
      $showBestBadge bool   — segundo producto de la home (badge "🔥 Más vendido")
      $showNewBadge  bool   — producto reciente (≤14d) sin top/best (badge "NUEVO")
--}}
@php
    $showTopBadge  = $showTopBadge  ?? false;
    $showBestBadge = $showBestBadge ?? false;
    $showNewBadge  = $showNewBadge  ?? false;

    // Imagen principal: variante is_primary si existe, fallback a la del padre.
    $cardPrimaryImg = $listing->primary_image_url ?? $listing->image_url;
@endphp
<a href="{{ route('marketplace.item', $listing->slug) }}" class="mp-card"
   @if(!empty($listing->gallery_image_urls) && count($listing->gallery_image_urls) >= 2)
       data-gallery="{{ json_encode($listing->gallery_image_urls) }}"
   @endif>
    <div class="mp-card-img" data-has-secondary="{{ $listing->secondary_image_url ? '1' : '0' }}">
        @if($cardPrimaryImg)
            <img class="mp-card-img-primary" src="{{ $cardPrimaryImg }}" alt="{{ $listing->title }}" loading="lazy">
            @if($listing->secondary_image_url)
                <img class="mp-card-img-secondary" src="{{ $listing->secondary_image_url }}" alt="" loading="lazy" aria-hidden="true">
            @endif
        @else
            <div class="mp-card-img-empty">Sin imagen</div>
        @endif

        <div class="mp-card-badges">
            @if(!empty($listing->is_featured) && (empty($listing->featured_until) || \Carbon\Carbon::parse($listing->featured_until)->isFuture()))
                <span class="mp-badge mp-badge--top" title="Producto destacado" style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff">⭐ Destacado</span>
            @elseif($showTopBadge)
                <span class="mp-badge mp-badge--top" title="Destacado">⭐ Top</span>
            @elseif($showBestBadge)
                <span class="mp-badge mp-badge--best" title="Más vendido">🔥 Más vendido</span>
            @elseif($showNewBadge)
                <span class="mp-badge mp-badge--new" title="Nuevo">NUEVO</span>
            @endif
            @if($listing->tenant_verified)
                <span class="mp-badge mp-badge--verified" title="Tienda verificada">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.39 5.42L20 8.27l-4 4.15.94 5.58L12 15.77l-4.94 2.23L8 12.42 4 8.27l5.61-.85L12 2z"/></svg>
                    Verificado
                </span>
            @endif
            @if(!empty($listing->is_on_offer) && !empty($listing->discount_pct))
                @if(($listing->discount_source ?? null) === 'flash_sale')
                    <span class="mp-badge mp-badge--flash" title="Oferta por tiempo limitado">⚡ Flash -{{ $listing->discount_pct }}%</span>
                @else
                    <span class="mp-badge mp-badge--offer" title="En oferta">-{{ $listing->discount_pct }}%</span>
                @endif
            @endif
            @if(!empty($listing->is_pack))
                @php
                    $packCount = is_array($listing->pack_contents) ? count($listing->pack_contents) : 0;
                @endphp
                <span class="mp-badge mp-badge--pack"
                      title="Combo de {{ $packCount }} producto{{ $packCount === 1 ? '' : 's' }}"
                      style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
                    📦 Pack{{ $packCount > 0 ? ' ×' . $packCount : '' }}
                </span>
            @endif
        </div>
    </div>

    <div class="mp-card-body">
        <h3 class="mp-card-title">{{ $listing->title }}</h3>

        @if($listing->rating_count > 0)
            <div class="mp-card-rating">
                <span class="mp-card-rating-stars">
                    @for($i=1;$i<=5;$i++){{ $i <= round($listing->avg_rating) ? '★' : '☆' }}@endfor
                </span>
                <span class="mp-card-rating-count">({{ $listing->rating_count }})</span>
            </div>
        @endif

        <div class="mp-card-price-row">
            @if($listing->display_price > 0)
                @if(!empty($listing->has_variants))
                    <span class="mp-card-price-prefix">Desde</span>
                    <span class="mp-card-price">S/ {{ number_format($listing->min_price ?? $listing->display_price, 2) }}</span>
                @else
                    <span class="mp-card-price">S/ {{ number_format($listing->display_price, 2) }}</span>
                    @if(!empty($listing->is_on_offer) && !empty($listing->original_price) && $listing->original_price > $listing->display_price)
                        <span class="mp-card-price-old">S/ {{ number_format($listing->original_price, 2) }}</span>
                    @endif
                @endif
            @else
                <span style="color:#6b7280;font-size:13px;font-weight:500">Consultar precio</span>
            @endif
        </div>

        <div class="mp-card-shop">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18"/><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/></svg>
            @if($listing->subdomain)
                <span class="mp-card-shop-name mp-card-shop-link js-shop-link"
                      data-href="{{ route('marketplace.tenant', ['subdomain' => $listing->subdomain]) }}"
                      title="Ver tienda {{ $listing->seller_display }}">{{ $listing->seller_display }}</span>
            @else
                <span class="mp-card-shop-name" title="Vendido por {{ $listing->seller_display }}">{{ $listing->seller_display }}</span>
            @endif
        </div>

        {{-- Dots de color: solo cuando el value tiene color_hex Y al menos una
             variante con stock > 0 lo usa (filtrado en el controller). --}}
        @if(!empty($listing->color_dots) && $listing->color_dots->count())
            <div class="mp-card-colors" aria-label="Colores disponibles">
                @foreach($listing->color_dots as $cd)
                    @php
                        $isActiveDot = !empty($listing->active_color_hex)
                            && strcasecmp($cd->color_hex, $listing->active_color_hex) === 0;
                    @endphp
                    <span class="mp-card-color-dot mp-card-color-dot--hex {{ $isActiveDot ? 'is-active' : '' }}"
                          @if(!empty($cd->image_url)) data-img="{{ $cd->image_url }}" @endif
                          title="{{ $cd->value }}"
                          style="background:{{ $cd->color_hex }}"></span>
                @endforeach
            </div>
        @endif

        {{-- Thumbs de variantes con imagen propia (cuando NO hay opción "color"
             pero sí imágenes por variante). --}}
        @if(empty($listing->color_dots) && !empty($listing->variant_thumbs) && $listing->variant_thumbs->count())
            <div class="mp-card-variants" aria-label="Variantes disponibles">
                @foreach($listing->variant_thumbs as $vt)
                    <span class="mp-card-variant-dot"
                          data-img="{{ $vt->image_url }}"
                          title="{{ $vt->display_name }}">
                        <img src="{{ $vt->image_url }}" alt="{{ $vt->display_name }}" loading="lazy">
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</a>
