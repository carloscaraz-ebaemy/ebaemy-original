{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index.

     Se autooculta si el tenant no eligió marcas — la mayoría de las tiendas
     no trabaja con marcas y no tiene por qué ver una franja vacía. --}}
@php
    $brands = \App\Services\EcommerceHomeContent::brands();
@endphp

@if(count($brands))
<section class="ec-home-section ec-brands" aria-label="Marcas">
    <div class="ec-section-header">
        <h2 class="ec-section-title">Marcas que trabajamos</h2>
    </div>
    {{-- Tira con scroll horizontal: sin librería de carrusel, con inercia
         nativa en móvil y navegable con el teclado en escritorio. --}}
    <div class="ec-brands__track" tabindex="0" role="list">
        @foreach($brands as $brand)
        <div class="ec-brand" role="listitem" title="{{ $brand['name'] }}">
            @if($brand['logo'])
                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" loading="lazy" height="40">
            @else
                <span class="ec-brand__name">{{ $brand['name'] }}</span>
            @endif
        </div>
        @endforeach
    </div>
</section>
@endif

<style>
/* ═══ MARCAS ═══ */
.ec-brands { margin-top: 1.5rem; }
.ec-brands__track {
    display: flex; align-items: center; gap: 1.5rem;
    overflow-x: auto; padding: 4px 2px 12px;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}
.ec-brands__track::-webkit-scrollbar { height: 4px; }
.ec-brands__track::-webkit-scrollbar-thumb {
    background: var(--theme-border, #e5e7eb); border-radius: 2px;
}
.ec-brand {
    flex: 0 0 auto; scroll-snap-align: start;
    min-width: 108px; height: 62px;
    display: flex; align-items: center; justify-content: center;
    padding: 10px 16px; border-radius: 10px;
    background: var(--theme-surface, #f8fafc);
    border: 1px solid var(--theme-border, #e5e7eb);
}
/* Los logos llegan en colores y tamaños distintos: se normalizan en escala
   de grises y recuperan color al pasar el mouse, para que la franja se lea
   como un conjunto y no como un collage. */
.ec-brand img {
    max-height: 40px; max-width: 100%; width: auto; object-fit: contain;
    filter: grayscale(1); opacity: .72; transition: filter .2s, opacity .2s;
}
.ec-brand:hover img { filter: grayscale(0); opacity: 1; }
.ec-brand__name {
    font-size: 13px; font-weight: 700; letter-spacing: .02em;
    color: var(--theme-text-secondary, #64748b); text-align: center;
}
@media (max-width: 575px) {
    .ec-brands__track { gap: 1rem; }
    .ec-brand { min-width: 92px; height: 54px; padding: 8px 12px; }
    .ec-brand img { max-height: 32px; }
}
</style>
