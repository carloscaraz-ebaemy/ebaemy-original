{{--
    Portada de temporada del inicio del marketplace (fase 2 del rediseño).
    Se configura en /admin/marketplace/portada (configurations.marketplace_hero)
    y la resuelve App\Services\Marketplace\HomeShowcase::hero(): si esta
    apagada, fuera de fechas o sin destino, $hero llega null y no se pinta
    nada — no deja hueco.

    Las fotos de la portada se pintan a 84-150 px: NO son el LCP (lo es la
    primera tarjeta del primer carrusel, medido 2026-11-19; ver
    partials/category-reels). Por eso van sin diferir pero SIN preload ni
    fetchpriority alto —antes se llevaban la prioridad 50 KB a 512 px para
    pintarse a 84— y con la versión de 256 px (`image_small`) cuando existe.
    width/height reservan el hueco antes de que llegue la imagen. El titulo
    es <h2>: el <h1> de la portada es el oculto de index.blade.php (uno solo).

    Vars: $hero object{title, subtitle, cta_text, cta_url, photos} | null
--}}
@if(!empty($hero))
    @php $heroPhotos = $hero->photos->take(2); @endphp
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
                    @php $hpSmall = $hp->image_small ?? null; @endphp
                    <a href="{{ route('marketplace.item', $hp->slug) }}" class="mp-season__photo" tabindex="-1" aria-hidden="true">
                        <img src="{{ $hpSmall ?: $hp->image }}" alt="" width="256" height="256"
                             @if($hpSmall && $hpSmall !== $hp->image)
                                 srcset="{{ $hpSmall }} 256w, {{ $hp->image }} 512w"
                                 sizes="(max-width: 600px) 84px, 150px"
                             @endif
                             loading="eager" decoding="async">
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endif
