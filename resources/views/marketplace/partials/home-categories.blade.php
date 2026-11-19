{{--
    «Compra por categoría» (fase 2 del rediseño): una tarjeta por categoría
    raíz con productos publicados, con la foto REAL de uno de ellos (oferta o
    el más visto), el nombre y el número de productos. Ordenadas por número de
    productos; los datos los da HomeShowcase::roots() (cache de 10 min).

    Una sola fila: 6 a la vista en escritorio, 3 en móvil; el resto se
    desliza en horizontal, con el borde derecho desvanecido y la barra fina
    visible como pista de que hay más (feedback_mobile_first). SIN
    scroll-snap a proposito: el reajuste del snap al cargar cuenta como un
    scroll y Chrome deja de medir el LCP (project_pagespeed_lcp_marketplace).

    Las fotos van lazy salvo las $eagerTiles primeras (3 cuando no hay
    portada: entonces esta fila es el primer pliegue en movil).

    Vars: $tiles Collection de HomeShowcase::roots() con imagen
          $eagerTiles int
--}}
@if(!empty($tiles) && $tiles->count() >= 3)
    @php $eagerTiles = $eagerTiles ?? 0; @endphp
    <section class="mp-shopcats" aria-labelledby="mpShopcatsTitle">
        <div class="mp-section-head">
            <h2 class="mp-section-title" id="mpShopcatsTitle">Compra por categoría</h2>
        </div>
        <ul class="mp-shopcats__rail">
            @foreach($tiles as $tile)
                @php
                    $tileEager = $loop->index < $eagerTiles;
                    // ~110 px en móvil y ~180 en escritorio: con la de 256 px
                    // basta salvo pantallas densas (HomeShowcase::roots()).
                    $tileSmall = $tile->image_small ?? null;
                @endphp
                <li class="mp-shopcats__item">
                    <a href="{{ route('marketplace.category_official', ['fullSlug' => $tile->full_slug]) }}" class="mp-shopcat">
                        <span class="mp-shopcat__img">
                            <img src="{{ $tileSmall ?: $tile->image }}" alt="" width="256" height="256"
                                 @if($tileSmall && $tileSmall !== $tile->image)
                                     srcset="{{ $tileSmall }} 256w, {{ $tile->image }} 512w"
                                     sizes="(max-width: 640px) 31vw, (max-width: 900px) 23vw, 180px"
                                 @endif
                                 loading="{{ $tileEager ? 'eager' : 'lazy' }}" decoding="async">
                        </span>
                        <span class="mp-shopcat__name">{{ $tile->name }}</span>
                        <span class="mp-shopcat__count">{{ $tile->count }} producto{{ $tile->count === 1 ? '' : 's' }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
