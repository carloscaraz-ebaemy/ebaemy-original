{{--
    Miniatura del video dentro de la galería de la ficha (2026-11-25).

    Es un .mp-gallery-thumb más, con ▶ encima: al elegirla el reproductor se
    crea EN el visor principal (show.blade, playVideo). Sustituye a la fila
    aparte «Míralo en video» (partials/product-video queda para el bottom
    sheet). Nada del proveedor se descarga hasta elegirla.

    Vars: $video (PromoVideo), $src, $title, $posterFallback.
--}}
@php $gvPoster = $video->posterUrl($posterFallback ?? null); @endphp
<button type="button" class="mp-gallery-thumb mp-gallery-thumb--video"
        data-mp-video="{{ $src }}"
        data-mp-video-kind="{{ $video->isLink() ? 'embed' : 'file' }}"
        data-mp-video-provider="{{ $video->isLink() ? $video->provider : 'file' }}"
        data-mp-video-orientation="{{ $video->isVertical() ? 'vertical' : 'horizontal' }}"
        data-mp-video-title="{{ $title }}"
        aria-label="Ver el video de {{ $title }}"
        title="{{ $video->providerLabel() ? 'Video en ' . $video->providerLabel() : 'Video del producto' }}">
    @if($gvPoster)
        <img src="{{ $gvPoster }}" alt="" loading="lazy" decoding="async" fetchpriority="low" onerror="this.remove()">
    @endif
    <span class="mp-vplay mp-vplay--sm" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
    </span>
</button>
