{{--
    «Míralo en video» — fila del inicio (2026-11-19), después de los
    carruseles por categoría.

    Videos aprobados por el SuperAdmin: primero los ★ destacados vigentes,
    luego los aprobados más recientes (HomeShowcase::videoRow, cache 10 min).
    Sin videos, la fila no sale.

    Tarjeta vertical 9:16 con miniatura, ▶, nombre y precio. Es un enlace a la
    ficha (sin JS, o con Ctrl/Cmd+clic, navega); con JS abre un modal que
    reproduce el video con «Ver producto». Nada se descarga del proveedor
    hasta que se toca: miniaturas lazy y el iframe se crea al abrir el modal.

    Vars: $videos Collection de object{video: PromoVideo, listing: MarketplaceListing}
--}}
@if(!empty($videos) && $videos->count() > 0)
    <section class="mp-reel mp-vreel" aria-labelledby="mpVreelTitle" data-vreel>
        <div class="mp-section-head">
            <h2 class="mp-section-title" id="mpVreelTitle">Míralo en video</h2>
            <div class="mp-section-head__actions">
                <button type="button" class="mp-reel__nav" data-vreel-prev aria-controls="mpVreelRail" aria-label="Videos anteriores" disabled>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="mp-reel__nav" data-vreel-next aria-controls="mpVreelRail" aria-label="Videos siguientes">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
        <ul class="mp-vreel__rail" id="mpVreelRail" data-vreel-rail>
            @foreach($videos as $row)
                @php
                    $vl = $row->listing;
                    $vv = $row->video;
                    $vPoster = $vv->posterUrl($vl->thumb_image_url ?: $vl->image_url);
                    $vHasDiscount = !empty($vl->is_on_offer) && !empty($vl->original_price) && $vl->original_price > $vl->display_price;
                    $vPrice = ($vl->has_variants && $vl->min_price) ? 'Desde S/ ' . number_format($vl->min_price, 2) : 'S/ ' . number_format($vl->display_price, 2);
                @endphp
                <li class="mp-vreel__item">
                    <a class="mp-vreel__card" href="{{ $vl->public_url }}"
                       data-vreel-open
                       data-src="{{ $vv->embedSrc() }}"
                       data-kind="{{ $vv->isLink() ? 'embed' : 'file' }}"
                       data-provider="{{ $vv->isLink() ? $vv->provider : 'file' }}"
                       data-orientation="{{ $vv->isVertical() ? 'vertical' : 'horizontal' }}"
                       data-title="{{ $vl->title }}"
                       data-price="{{ $vl->display_price > 0 ? $vPrice : '' }}"
                       aria-label="Ver el video de {{ $vl->title }}">
                        <span class="mp-vreel__media">
                            @if($vPoster)
                                <img src="{{ $vPoster }}" alt="" loading="lazy" decoding="async" width="270" height="480" onerror="this.remove()">
                            @endif
                            <span class="mp-vplay mp-vplay--lg" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
                            </span>
                            @if($vv->providerLabel())
                                <span class="mp-vreel__brand">{{ $vv->providerLabel() }}</span>
                            @endif
                        </span>
                        <span class="mp-vreel__name">{{ $vl->title }}</span>
                        @if($vl->display_price > 0)
                            <span class="mp-vreel__price">
                                {{ $vPrice }}
                                @if($vHasDiscount && !($vl->has_variants && $vl->min_price))
                                    <s>S/ {{ number_format($vl->original_price, 2) }}</s>
                                @endif
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Modal del video. [hidden] con display:none explícito en el CSS: un
         display de autor le ganaría al atributo. --}}
    <div class="mp-vmodal" id="mpVideoModal" role="dialog" aria-modal="true" aria-labelledby="mpVideoModalTitle" hidden>
        <div class="mp-vmodal__backdrop" data-vmodal-close></div>
        <div class="mp-vmodal__panel">
            <button type="button" class="mp-vmodal__close" data-vmodal-close aria-label="Cerrar el video">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="mp-vmodal__stage" data-vmodal-stage></div>
            <div class="mp-vmodal__info">
                <div class="mp-vmodal__text">
                    <p class="mp-vmodal__title" id="mpVideoModalTitle"></p>
                    <p class="mp-vmodal__price" data-vmodal-price></p>
                </div>
                <a class="mp-vmodal__cta" href="#" data-vmodal-cta>Ver producto</a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var section = document.querySelector('[data-vreel]');
        var modal = document.getElementById('mpVideoModal');
        if (!section || !modal) return;
        var rail = section.querySelector('[data-vreel-rail]');
        var prev = section.querySelector('[data-vreel-prev]');
        var next = section.querySelector('[data-vreel-next]');

        // Snap solo tras `load` (ver partials/category-reels: activo de
        // entrada cuenta como scroll y Chrome deja de medir el LCP).
        function snap() { rail.classList.add('is-snappy'); }
        if (document.readyState === 'complete') setTimeout(snap, 200);
        else window.addEventListener('load', function () { setTimeout(snap, 200); }, { once: true });

        function step() {
            var item = rail.querySelector('.mp-vreel__item');
            var w = item ? item.getBoundingClientRect().width : 160;
            return Math.max(w, rail.clientWidth - w);
        }
        function sync() {
            prev.disabled = rail.scrollLeft <= 4;
            next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
        }
        prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
        next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
        rail.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
        window.addEventListener('resize', sync);
        sync();

        // ── Modal ──
        var panel = modal.querySelector('.mp-vmodal__panel');
        var stage = modal.querySelector('[data-vmodal-stage]');
        var titleEl = document.getElementById('mpVideoModalTitle');
        var priceEl = modal.querySelector('[data-vmodal-price]');
        var cta = modal.querySelector('[data-vmodal-cta]');
        var closeBtn = modal.querySelector('.mp-vmodal__close');
        var opener = null;

        function open(card) {
            var player = window.mpVideoPlayer && window.mpVideoPlayer(card.getAttribute('data-src'), {
                kind: card.getAttribute('data-kind'),
                title: card.getAttribute('data-title')
            });
            if (!player) return false;
            var horizontal = card.getAttribute('data-orientation') === 'horizontal';
            stage.innerHTML = '';
            stage.className = 'mp-vmodal__stage mp-vmodal__stage--' + (horizontal ? 'horizontal' : 'vertical')
                + ' mp-vmodal__stage--' + (card.getAttribute('data-provider') || 'file');
            panel.classList.toggle('is-horizontal', horizontal);
            stage.appendChild(player);
            // textContent: el nombre viene de la tienda, nunca como HTML.
            titleEl.textContent = card.getAttribute('data-title') || '';
            priceEl.textContent = card.getAttribute('data-price') || '';
            priceEl.hidden = !priceEl.textContent;
            cta.setAttribute('href', card.getAttribute('href'));
            opener = card;
            modal.hidden = false;
            document.documentElement.classList.add('mp-vmodal-open');
            closeBtn.focus();
            return true;
        }
        function close() {
            if (modal.hidden) return;
            modal.hidden = true;
            stage.innerHTML = '';   // corta el video
            document.documentElement.classList.remove('mp-vmodal-open');
            if (opener) opener.focus();
            opener = null;
        }

        rail.addEventListener('click', function (e) {
            var card = e.target.closest('[data-vreel-open]');
            if (!card || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
            if (open(card)) e.preventDefault();
        });
        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-vmodal-close]')) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
    </script>
@endif
