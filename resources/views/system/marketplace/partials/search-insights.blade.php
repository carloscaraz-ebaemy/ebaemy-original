{{--
    Qué buscan los compradores del marketplace.

    El panel pone primero lo que NO se encuentra, no el top de búsquedas: el
    top confirma lo que ya sabes, y la lista de vacías dice qué catálogo falta
    y con qué argumento salir a captar tiendas.

    En su propio partial para poder renderizarlo aislado en un test; el
    dashboard completo no se puede renderizar fuera de un request real.

    Recibe `searchStats`, `topSearches` y `zeroSearches` del controlador.
--}}
<div class="mpd-panel mpd-panel--table">
    <div class="mpd-panel__head">
        <h5 class="mpd-panel__title">Qué buscan tus clientes</h5>
        <span class="mpd-panel__count">
            {{ number_format($searchStats['searches']) }}
            {{ $searchStats['searches'] === 1 ? 'búsqueda' : 'búsquedas' }} ·
            {{ number_format($searchStats['terms']) }}
            {{ $searchStats['terms'] === 1 ? 'término' : 'términos' }}
        </span>
    </div>

    @if($searchStats['searches'] === 0)
        <div class="mpd-empty">
            Todavía no hay búsquedas registradas en este rango.
            <br>
            <small class="text-muted">
                Se empiezan a guardar desde que esto está publicado: si acabas de
                desplegar, dale unos días antes de sacar conclusiones.
            </small>
        </div>
    @else
        {{-- Cabecera: el porcentaje de búsquedas vacías es el número que
             resume la salud del catálogo frente a lo que la gente pide. --}}
        <div class="mpd-cov">
            <div class="mpd-cov__bar">
                <span class="mpd-cov__fill mpd-cov__fill--warn"
                      style="width:{{ max(1, (float) $searchStats['zero_rate']) }}%"></span>
            </div>
            <div class="mpd-cov__txt">
                <strong>{{ number_format($searchStats['zero']) }}</strong>
                de {{ number_format($searchStats['searches']) }} búsquedas no encontraron nada
                ({{ $searchStats['zero_rate'] }}%)
            </div>
        </div>

        <div class="mpd-charts">
            {{-- Lo accionable va primero y a la izquierda. --}}
            <div class="mpd-si">
                <h6 class="mpd-si__title">Buscado sin encontrar nada</h6>

                @forelse($zeroSearches as $t)
                    <div class="mpd-si__row">
                        <span class="mpd-si__term" title="{{ $t->term_norm }}">{{ $t->sample ?: $t->term_norm }}</span>
                        <span class="mpd-si__badge mpd-si__badge--warn">{{ number_format($t->zero) }} {{ $t->zero == 1 ? 'vez' : 'veces' }}</span>
                    </div>
                @empty
                    <p class="mpd-si__empty">
                        Ninguna búsqueda del rango se quedó sin resultados. Es buena señal:
                        lo que piden, lo tienes.
                    </p>
                @endforelse

                @if($zeroSearches->isNotEmpty())
                    <p class="mpd-si__hint">
                        Cada término de aquí es un comprador que se fue con las manos
                        vacías. Son dos cosas distintas y conviene separarlas: lo que
                        <strong>sí vendes pero está mal escrito o mal etiquetado</strong>
                        (se arregla con sinónimos o completando categoría y marca), y lo
                        que <strong>no vende nadie en el marketplace</strong>, que es la
                        lista con la que salir a captar tiendas.
                    </p>
                @endif
            </div>

            <div class="mpd-si">
                <h6 class="mpd-si__title">Lo más buscado</h6>

                @forelse($topSearches as $t)
                    <div class="mpd-si__row">
                        <span class="mpd-si__term" title="{{ $t->term_norm }}">{{ $t->sample ?: $t->term_norm }}</span>
                        <span class="mpd-si__badge {{ $t->last_results == 0 ? 'mpd-si__badge--warn' : '' }}">{{ number_format($t->searches) }} · {{ $t->last_results == 0 ? 'sin resultados' : number_format($t->last_results) . ' result.' }}</span>
                    </div>
                @empty
                    <p class="mpd-si__empty">Sin datos en este rango.</p>
                @endforelse
            </div>
        </div>
    @endif

    <div class="mpd-panel__foot">
        Sólo se registra la búsqueda de la página, no cada tecla del autocompletado.
        Se guarda el término y cuántas veces se buscó: ni IP, ni usuario, ni sesión.
    </div>
</div>
