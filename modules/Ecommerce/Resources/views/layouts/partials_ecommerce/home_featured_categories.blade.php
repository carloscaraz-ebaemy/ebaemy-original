{{-- Sección del home. La incluye el renderizador de secciones:
     ver App\Services\EcommerceHomeSections y ecommerce::index.

     Se autooculta si el tenant no eligió categorías: una sección vacía en la
     portada se lee como tienda a medio armar. --}}
@php
    $featured  = \App\Services\EcommerceHomeContent::featuredCategories();
    $showCount = \App\Services\EcommerceHomeContent::featuredCategoriesShowCount();
@endphp

@if(count($featured))
<section class="ec-home-section ec-featured-cats" aria-label="Explora por categoría">
    <div class="ec-section-header">
        <h2 class="ec-section-title">Explora por categoría</h2>
    </div>
    <div class="ec-featured-cats__grid">
        @foreach($featured as $cat)
        <a href="{{ $cat['url'] }}" class="ec-featured-cat">
            <span class="ec-featured-cat__media">
                @if($cat['image'])
                    <img src="{{ $cat['image'] }}" alt="{{ $cat['name'] }}" loading="lazy" width="200" height="200">
                @else
                    {{-- Sin imagen: inicial de la categoría sobre el color de
                         la tienda. Mejor que un cuadro gris vacío. --}}
                    <span class="ec-featured-cat__initial">{{ mb_strtoupper(mb_substr($cat['name'], 0, 1)) }}</span>
                @endif
            </span>
            <span class="ec-featured-cat__name">{{ $cat['name'] }}</span>
            @if($showCount)
            <span class="ec-featured-cat__count">{{ $cat['count'] }} producto{{ $cat['count'] === 1 ? '' : 's' }}</span>
            @endif
        </a>
        @endforeach
    </div>
</section>
@endif

<style>
/* ═══ CATEGORÍAS DESTACADAS ═══ */
.ec-featured-cats { margin-top: 1.5rem; }
/* auto-fit: con pocas categorías no quedan huecos, y en móvil entran dos
   sin necesidad de media queries por cantidad. */
.ec-featured-cats__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 1rem;
}
.ec-featured-cat {
    display: flex; flex-direction: column; align-items: center; gap: 7px;
    text-decoration: none; color: inherit; text-align: center;
}
.ec-featured-cat__media {
    width: 100%; aspect-ratio: 1; border-radius: 14px; overflow: hidden;
    background: var(--theme-surface, #f8fafc);
    border: 1px solid var(--theme-border, #e5e7eb);
    display: flex; align-items: center; justify-content: center;
    transition: transform .2s, box-shadow .2s;
}
.ec-featured-cat:hover .ec-featured-cat__media {
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0,0,0,.09);
}
.ec-featured-cat__media img { width: 100%; height: 100%; object-fit: cover; }
.ec-featured-cat__initial {
    font-size: 30px; font-weight: 700;
    color: var(--theme-primary, hsl(var(--primary-h), var(--primary-s), var(--primary-l)));
}
.ec-featured-cat__name {
    font-size: 13px; font-weight: 600;
    color: var(--theme-text-primary, #1e293b);
    line-height: 1.25;
}
.ec-featured-cat__count { font-size: 11px; color: var(--theme-text-secondary, #94a3b8); }
@media (max-width: 575px) {
    .ec-featured-cats__grid { grid-template-columns: repeat(auto-fit, minmax(88px, 1fr)); gap: .7rem; }
    .ec-featured-cat__name { font-size: 12px; }
}
</style>
