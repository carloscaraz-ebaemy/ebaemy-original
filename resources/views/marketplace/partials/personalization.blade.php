{{--
    Aplica en el navegador lo que es de cada visitante, para que el HTML que
    sale del servidor pueda ser identico para todos (y por tanto cacheable).

    Lo pide todo en UNA peticion a /marketplace/personalizacion y hace dos
    cosas con la respuesta:

      1. Reordena el carrusel de ofertas del dia. Los nodos ya estan en la
         pagina: se MUEVEN con appendChild, no se vuelven a crear. Asi
         sobreviven los listeners ya enganchados y los temporizadores de
         «Termina en» siguen contando.
      2. Mete las cards de «Vistos recientemente» en la seccion que el
         servidor dejo vacia y oculta.

    Si la peticion falla o no hay JS, la pagina se queda tal cual salio del
    servidor: ofertas en orden neutro y sin bloque de vistos. Se degrada, no
    se rompe.

    Var opcional:
      $excluirListingId  — en la ficha de producto, para que el propio
                           producto no salga en sus «vistos recientemente».
--}}
<script>
(function () {
    var rail   = document.getElementById('mpOffersRail');
    var recent = document.querySelector('[data-mp-recent]');
    if (!rail && !recent) return;

    var url = '{{ route('marketplace.personalization') }}'
        @isset($excluirListingId) + '?excluir={{ (int) $excluirListingId }}' @endisset;

    /**
     * Reordena las cards de un carril segun una lista de ids.
     *
     * Con `order` de CSS, NO moviendo los nodos. La primera version usaba
     * appendChild y eso le costaba el LCP a la pagina entera: Lighthouse
     * descarta como candidato a LCP cualquier elemento que salga del DOM, y
     * mover un nodo es sacarlo y volver a meterlo. El 2026-10-08, en cuanto
     * la primera foto del carrusel dejo de ir diferida y paso a ser la
     * candidata, PageSpeed empezo a responder `NO_LCP` y con el varias
     * auditorias en Error.
     *
     * El carril es `display:flex`, asi que `order` da exactamente el mismo
     * resultado visual. Y de paso se conserva lo que ya conservaba el
     * appendChild: listeners enganchados y temporizadores en marcha.
     */
    function reordenar(carril, ids) {
        if (!carril || !ids.length) return;
        var porId = {};
        carril.querySelectorAll('[data-offer-id]').forEach(function (el) {
            porId[el.getAttribute('data-offer-id')] = el;
        });
        // Lo que el orden menciona va delante, en ese orden; lo que no
        // aparezca se queda detras tal y como venia.
        var pos = 0;
        ids.forEach(function (id) {
            var el = porId[String(id)];
            if (el) el.style.order = ++pos;
        });
    }

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            if (!d) return;

            var ids = Array.isArray(d.offers_order) ? d.offers_order : [];
            reordenar(rail, ids);
            // El modal clona el carril al cargar la pagina, asi que su copia
            // ya existe y hay que reordenarla tambien: si no, carrusel y modal
            // ensenarian ordenes distintos.
            var modal = document.getElementById('mpOffersModalBody');
            if (modal) reordenar(modal.querySelector('.mp-offers-rail'), ids);

            if (recent && d.recently_viewed) {
                var scroll = recent.querySelector('[data-mp-recent-scroll]');
                if (!scroll) return;
                scroll.innerHTML = d.recently_viewed;
                var cuenta = recent.querySelector('[data-mp-recent-count]');
                if (cuenta) cuenta.textContent = d.recently_count || '';
                recent.removeAttribute('hidden');
                // Las cards recien metidas necesitan lo que no esta delegado
                // en document: la galeria al hover se engancha por card, y los
                // favoritos y los badges de cupon se re-aplican con el evento
                // que ya usa el scroll infinito.
                if (window.mpBindGallery) window.mpBindGallery(scroll);
                window.dispatchEvent(new Event('mp:cards-appended'));
            }
        })
        .catch(function () { /* la pagina ya es usable sin esto */ });
})();
</script>
