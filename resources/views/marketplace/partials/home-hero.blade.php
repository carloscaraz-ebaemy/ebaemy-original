{{--
    Portada de temporada del inicio del marketplace (fase 2 del rediseño).
    Se configura en /admin/marketplace/portada (configurations.marketplace_hero)
    y la resuelve App\Services\Marketplace\HomeShowcase::hero(): si esta
    apagada, fuera de fechas o sin destino, $hero llega null y no se pinta
    nada — no deja hueco.

    Va lo primero del inicio, asi que su foto es la candidata a LCP: la
    primera lleva preload + fetchpriority alto, y las dos width/height para
    que el hueco este reservado antes de que llegue la imagen. El titulo es
    <h2>: el <h1> de la portada es el oculto de index.blade.php (uno solo).

    Vars: $hero object{title, subtitle, cta_text, cta_url, photos} | null
--}}
@if(!empty($hero))
    @php $heroPhotos = $hero->photos->take(2); @endphp
    @if($heroPhotos->isNotEmpty())
        @include('marketplace.partials.listing-preload', [
            'items'      => $heroPhotos->take(1)->map(fn ($p) => (object) ['image_url' => $p->image]),
            'withSrcset' => false,
        ])
    @endif
    <section class="mp-season {{ $heroPhotos->isEmpty() ? 'mp-season--text' : '' }}" aria-label="{{ $hero->title }}">
        <div class="mp-season__text">
            <h2 class="mp-season__title">{{ $hero->title }}</h2>
            @if($hero->subtitle)
                <p class="mp-season__sub">{{ $hero->subtitle }}</p>
            @endif
            <a href="{{ $hero->cta_url }}" class="mp-season__cta">
                {{ $hero->cta_text }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
            </a>
        </div>
        @if($heroPhotos->isNotEmpty())
            <div class="mp-season__photos mp-season__photos--{{ $heroPhotos->count() }}">
                @foreach($heroPhotos as $hp)
                    <a href="{{ route('marketplace.item', $hp->slug) }}" class="mp-season__photo" tabindex="-1" aria-hidden="true">
                        <img src="{{ $hp->image }}" alt="" width="512" height="512"
                             loading="eager" decoding="async"
                             @if($loop->first) fetchpriority="high" @endif>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endif
