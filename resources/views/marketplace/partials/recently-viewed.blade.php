{{--
    Carousel horizontal de "Vistos recientemente" — productos que el
    visitante ya abrió antes en esta sesión. Reutilizable:
      @include('marketplace.partials.recently-viewed', ['recentlyViewed' => $recentlyViewed])

    Si la colección está vacía, no renderiza nada (caller no se preocupa).

    Mobile-first: scroll-x con snap. Desktop: grid de 4-5 cols visibles.
--}}
@if(isset($recentlyViewed) && $recentlyViewed->count() > 0)
<section class="mp-recent" aria-label="Vistos recientemente">
    <div class="mp-recent-head">
        <h2 class="mp-recent-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Vistos recientemente
        </h2>
        <span class="mp-recent-count">{{ $recentlyViewed->count() }}</span>
    </div>

    <div class="mp-recent-scroll" data-mp-recent-scroll>
        @foreach($recentlyViewed as $listing)
            <div class="mp-recent-item">
                @include('marketplace.partials.listing-card', ['listing' => $listing])
            </div>
        @endforeach
    </div>
</section>

@once
<style>
.mp-recent {
    margin: 32px 0;
    padding: 0;
}
.mp-recent-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 clamp(12px, 3vw, 4px);
    margin-bottom: 14px;
}
.mp-recent-title {
    font-size: clamp(16px, 3vw, 19px);
    font-weight: 700;
    margin: 0;
    color: var(--mp-ink, #111827);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.mp-recent-title svg { color: var(--mp-primary, #0f8a82); }
.mp-recent-count {
    font-size: 11px;
    font-weight: 700;
    background: var(--mp-primary-soft, #e6f7f5);
    color: var(--mp-primary-dark, #0c6b65);
    padding: 2px 9px;
    border-radius: 999px;
}
.mp-recent-scroll {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(180px, 1fr);
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    -webkit-mask-image: linear-gradient(to right, #000 94%, transparent);
            mask-image: linear-gradient(to right, #000 94%, transparent);
    padding: 4px 4px 12px;
}
.mp-recent-scroll::-webkit-scrollbar { height: 4px; }
.mp-recent-scroll::-webkit-scrollbar-thumb { background: var(--mp-line, #e5e7eb); border-radius: 999px; }
.mp-recent-item {
    scroll-snap-align: start;
    min-width: 0;
}
/* Desktop: grid normal (sin scroll), hasta 5 columnas */
@media (min-width: 900px) {
    .mp-recent-scroll {
        grid-auto-flow: row;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        overflow-x: visible;
        -webkit-mask-image: none;
                mask-image: none;
        scroll-snap-type: none;
    }
}
/* Las cards dentro del scroll: igualar altura para no romper el grid */
.mp-recent-scroll .mp-card { height: 100%; }
</style>
@endonce
@endif
