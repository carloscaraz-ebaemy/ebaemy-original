@php
    $spotsArray = isset($spots) ? $spots->filter(fn($s) => !empty($s->image_url))->values() : collect();
@endphp

@if($spotsArray->count() > 0)
<div class="ec-offers-grid">
    @foreach($spotsArray->take(4) as $spot)
        <div class="ec-offer-item">
            @if(!empty($spot->spot_url))
                <a href="{{ $spot->spot_url }}" target="_blank" rel="noopener noreferrer" aria-label="Ver oferta">
                    <img src="{{ $spot->image_url }}"
                         alt="{{ $spot->description ?? 'Oferta especial' }}"
                         loading="lazy">
                </a>
            @else
                <img src="{{ $spot->image_url }}"
                     alt="{{ $spot->description ?? 'Oferta especial' }}"
                     loading="lazy">
            @endif
        </div>
    @endforeach
</div>
@endif
