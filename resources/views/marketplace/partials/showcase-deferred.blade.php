{{--
    Secciones de la vitrina del inicio que van BAJO EL PLIEGUE: carruseles
    2-5, «Míralo en video» y «Ofertas del día» (2026-11-19).

    Por qué: con todo dentro, el HTML inicial del inicio pesaba ~580 KB y el
    móvil gastaba ~1,8 s en Style & Layout antes de pintar (PageSpeed 42-60).
    Ahora el HTML lleva solo portada, «Compra por categoría» y el primer
    carrusel; cada sección de abajo es un hueco con ALTO RESERVADO (sin
    salto de maquetado) que se rellena al acercarse con el scroll
    (IntersectionObserver, 800 px de margen) con el fragmento de
    MarketplaceController::showcaseSection.

    Sin JS, o si la petición falla, el hueco se queda con su título y un
    enlace rastreable («Ver más en Hogar», «Ver todas las ofertas»). Los
    enlaces a las categorías siguen además en «Compra por categoría».

    Al insertar: se re-ejecutan los <script> del fragmento (flechas, snap,
    modal de video y de ofertas; cada uno se protege de engancharse dos
    veces), se enganchan la galería al hover y los favoritos
    (mp:cards-appended, igual que el scroll infinito) y se aplica el orden de
    ofertas por afinidad del visitante (partials/personalization).

    Vars: $sections array de object{key, kind: reel|videos|offers, title, url}
--}}
@if(!empty($sections))
    @foreach($sections as $sec)
        <section class="mp-defer mp-defer--{{ $sec->kind }}"
                 data-showcase-defer="{{ $sec->key }}"
                 data-src="{{ route('marketplace.showcase_section', ['seccion' => $sec->key]) }}"
                 aria-label="{{ $sec->title }}" aria-busy="true">
            <div class="mp-section-head">
                <h2 class="mp-section-title">
                    @if($sec->url)<a href="{{ $sec->url }}">{{ $sec->title }}</a>@else{{ $sec->title }}@endif
                </h2>
                @if($sec->url)
                    <div class="mp-section-head__actions">
                        <a href="{{ $sec->url }}" class="mp-section-more">
                            {{ $sec->kind === 'offers' ? 'Ver todas las ofertas' : 'Ver más en ' . $sec->title }} <span aria-hidden="true">→</span>
                        </a>
                    </div>
                @endif
            </div>
            <div class="mp-defer__body" aria-hidden="true"></div>
        </section>

        {{-- El botón flotante «🔥 Ofertas» (móvil) va desde el principio:
             si se toca antes de que llegue el carril, lo trae y abre el modal. --}}
        @if($sec->kind === 'offers')
            @push('styles')
                @include('marketplace.partials.daily-offers-styles')
            @endpush
            <button type="button" id="mpOffersFab" class="mp-offers-fab" aria-label="Ver ofertas del día" data-offers-lazy-fab>
                🔥 Ofertas
            </button>
        @endif
    @endforeach

    <script>
    (function () {
        var holes = Array.prototype.slice.call(document.querySelectorAll('[data-showcase-defer]'));
        if (!holes.length || !window.fetch) return;

        function insert(hole, html) {
            var tpl = document.createElement('template');
            tpl.innerHTML = html;
            var nodes = Array.prototype.slice.call(tpl.content.childNodes);
            hole.replaceWith(tpl.content);
            nodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                // Un <script> metido con innerHTML no se ejecuta: se recrea.
                if (n.tagName === 'SCRIPT') {
                    var s = document.createElement('script');
                    s.text = n.textContent;
                    n.replaceWith(s);
                    return;
                }
                if (window.mpBindGallery) window.mpBindGallery(n);
            });
            window.dispatchEvent(new CustomEvent('mp:cards-appended', { detail: { grid: null } }));
            if (hole.getAttribute('data-showcase-defer') === 'ofertas' && window.mpApplyOffersOrder) {
                window.mpApplyOffersOrder();
            }
        }

        function load(hole) {
            if (hole._mpLoad) return hole._mpLoad;
            hole._mpLoad = fetch(hole.getAttribute('data-src'), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                if (r.status === 204) return '';
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            }).then(function (html) {
                if (html.trim() === '') { hole.remove(); return; }
                insert(hole, html);
            }).catch(function () {
                // Se queda el título con su enlace: la página sigue siendo usable.
                hole.removeAttribute('aria-busy');
                hole.classList.add('is-failed');
            });
            return hole._mpLoad;
        }

        window.mpShowcaseLoad = function (key) {
            var hole = document.querySelector('[data-showcase-defer="' + key + '"]');
            return hole ? load(hole) : Promise.resolve();
        };

        // Se empieza a vigilar DESPUÉS de `load`: los huecos que caen a menos
        // de 800 px del primer pliegue se pedirían si no en plena carga,
        // compitiendo con la foto del LCP por la red y el hilo principal.
        function watch() {
            if (!('IntersectionObserver' in window)) { holes.forEach(load); return; }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    io.unobserve(e.target);
                    load(e.target);
                });
            }, { rootMargin: '800px 0px' });
            holes.forEach(function (h) { io.observe(h); });
        }
        if (document.readyState === 'complete') watch();
        else window.addEventListener('load', watch, { once: true });

        // «🔥 Ofertas» antes de que llegue el carril: lo trae y abre el modal.
        var fab = document.querySelector('[data-offers-lazy-fab]');
        if (fab) {
            fab.addEventListener('click', function () {
                if (window.mpOffersOpen) return;   // ya cargado: lo abre su propio script
                window.mpShowcaseLoad('ofertas').then(function () {
                    if (window.mpOffersOpen) window.mpOffersOpen();
                });
            });
        }
    })();
    </script>
@endif
