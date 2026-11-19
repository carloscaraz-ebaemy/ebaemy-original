{{--
    Fragmento de una sección diferida de la vitrina del inicio, tal como lo
    devuelve MarketplaceController::showcaseSection y lo inserta
    partials/showcase-deferred. Reutiliza los MISMOS partials que el inicio:
    no hay un segundo markup que mantener.

    Vars: $section  'reel' | 'videos' | 'offers'
          $reels    (reel)   Collection de object{root, items}
          $videos   (videos) Collection de object{video, listing}
          $offers   (offers) Collection de ofertas
--}}
@if($section === 'reel')
    @include('marketplace.partials.category-reels', ['reels' => $reels, 'reelsDeferred' => true])
@elseif($section === 'videos')
    @include('marketplace.partials.video-reels', ['videos' => $videos])
@elseif($section === 'offers')
    @php
        $offerTenantsCount = collect($offers)->pluck('hostname_id')->unique()->count();
    @endphp
    @include('marketplace.partials.daily-offers', [
        'offers'         => $offers,
        'seeAllUrl'      => route('marketplace.index', ['on_offer' => 1]),
        'modalSeenKey'   => 'mp_offers_modal_seen',
        'offersSub'      => 'Descuentos vigentes de ' . $offerTenantsCount . ' tienda' . ($offerTenantsCount === 1 ? '' : 's') . ' verificada' . ($offerTenantsCount === 1 ? '' : 's') . '. Aprovecha mientras duren.',
        'offersEager'    => 0,
        'offersDeferred' => true,
    ])
@endif
