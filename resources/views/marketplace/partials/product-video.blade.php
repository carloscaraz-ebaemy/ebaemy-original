{{--
    Video del producto bajo la galería de la ficha (2026-11-19).

    Fila compacta: miniatura pequeña con ▶ + texto. Pequeña a propósito: la
    foto principal tiene que seguir siendo el LCP, y una miniatura vertical
    grande bajo la galería podía ganarle en el primer pliegue del móvil. El
    iframe (TikTok / YouTube / Instagram) o el <video> se crean SOLO al tocar
    (partials/video-embed-script).

    Vars: $video (App\Models\System\PromoVideo), $title (nombre del producto),
          $posterFallback (foto del producto, si el video no trae miniatura).
--}}
@php
    $pvSrc      = $video->isLink() ? $video->embedSrc() : $video->mp4_url;
    $pvPoster   = $video->posterUrl($posterFallback ?? null);
    $pvVertical = $video->isVertical();
    $pvLabel    = $video->providerLabel();
@endphp
@if($pvSrc)
    <div class="mp-pvideo">
        <button type="button" class="mp-pvideo__lite {{ $pvVertical ? 'is-vertical' : 'is-horizontal' }}"
                data-mp-video="{{ $pvSrc }}"
                data-mp-video-kind="{{ $video->isLink() ? 'embed' : 'file' }}"
                data-mp-video-provider="{{ $video->isLink() ? $video->provider : 'file' }}"
                data-mp-video-orientation="{{ $pvVertical ? 'vertical' : 'horizontal' }}"
                data-mp-video-title="{{ $title }}"
                aria-label="Reproducir el video de {{ $title }}">
            <span class="mp-pvideo__thumb">
                @if($pvPoster)
                    <img src="{{ $pvPoster }}" alt="" loading="lazy" decoding="async" fetchpriority="low"
                         width="{{ $pvVertical ? 90 : 160 }}" height="{{ $pvVertical ? 160 : 90 }}" onerror="this.remove()">
                @endif
                <span class="mp-vplay" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
                </span>
            </span>
            <span class="mp-pvideo__text">
                <strong>Míralo en video</strong>
                <span>{{ $pvLabel ? 'Video de la tienda en ' . $pvLabel : 'Video de la tienda' }} · toca para reproducir</span>
            </span>
        </button>
    </div>
@endif
