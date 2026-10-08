{{--
    Las cards de "Vistos recientemente", sin el envoltorio de la seccion.

    Esta separado para que el endpoint de personalizacion pueda devolver SOLO
    esto y el navegador lo meta dentro del contenedor que ya existe en la
    pagina. Asi la pagina sale igual para todos (y se puede cachear) y lo que
    cada visitante vio llega despues, sin duplicar el HTML de la card en JS
    — que es la trampa que habria que evitar aqui: la card tiene que seguir
    siendo la misma en las cuatro vistas que la pintan.
--}}
@foreach($recentlyViewed as $listing)
    <div class="mp-recent-item">
        @include('marketplace.partials.listing-card', ['listing' => $listing])
    </div>
@endforeach
