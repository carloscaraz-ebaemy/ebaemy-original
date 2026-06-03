{{--
    Carrusel "Recomendado para ti" — personalización on-site basada en el perfil
    de intereses por categoría del comprador logueado (marketplace_user_interests,
    calculado por RecalculateMarketplaceUserInterests). Reutiliza el estilo de
    "Vistos recientemente". Si la colección está vacía no renderiza nada.

      @include('marketplace.partials.recommended', ['recommendedForYou' => $recommendedForYou])
--}}
@if(isset($recommendedForYou) && $recommendedForYou->count() > 0)
<section class="mp-recent mp-reco" aria-label="Recomendado para ti">
    <div class="mp-recent-head">
        <h2 class="mp-recent-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2l2.39 4.84 5.34.78-3.87 3.77.91 5.32L12 14.98 7.23 17.5l.91-5.32L4.27 8.4l5.34-.78L12 2z"/>
            </svg>
            Recomendado para ti
        </h2>
        <span class="mp-recent-count">{{ $recommendedForYou->count() }}</span>
    </div>

    <div class="mp-recent-scroll" data-mp-reco-scroll>
        @foreach($recommendedForYou as $listing)
            <div class="mp-recent-item">
                @include('marketplace.partials.listing-card', ['listing' => $listing])
            </div>
        @endforeach
    </div>
</section>
@endif
