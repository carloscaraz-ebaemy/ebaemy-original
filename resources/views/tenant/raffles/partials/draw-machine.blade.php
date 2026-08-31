{{-- ══════════════════════════════════════════════════════════════════════
     SORTEO: despachador de animación.

     El azar NO ocurre en ninguna de las dos animaciones. El backend elige
     dentro de una transacción (RaffleController@draw) y devuelve al ganador
     ya guardado; la animación solo lo dibuja. Por eso puede cambiarse el
     estilo sin tocar una línea de la lógica del sorteo.

     Cada parcial es autónoma: trae sus propios estilos, su propio markup y
     su propio JS, y consume el mismo endpoint con el mismo contrato.

     Espera: $raffle, $metrics, $reelNames.
     ══════════════════════════════════════════════════════════════════════ --}}
@if(($raffle->draw_animation ?? \App\Models\Tenant\Raffle::ANIMATION_WHEEL) === \App\Models\Tenant\Raffle::ANIMATION_REEL)
    @include('tenant.raffles.partials.draw-reel')
@else
    @include('tenant.raffles.partials.draw-wheel')
@endif
