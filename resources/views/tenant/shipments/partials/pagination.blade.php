{{-- Pie de tabla del tablero de envíos.
     Espera $shipments (LengthAwarePaginator) y $perPage.
     Los enlaces caen dentro de #shPanel → el AJAX los intercepta solo. --}}
@if($shipments->total() > 0)
    @php
        $cur   = $shipments->currentPage();
        $last  = $shipments->lastPage();
        // Ventana de páginas: primera, última y ±2 alrededor de la actual.
        $pages = collect(range(1, $last))->filter(
            fn ($p) => $p === 1 || $p === $last || abs($p - $cur) <= 2
        )->values();
    @endphp

    <div class="sh-foot">
        {{-- Izquierda: rango + total --}}
        <div class="sh-foot__info">
            <span class="sh-foot__range">{{ $shipments->firstItem() }}–{{ $shipments->lastItem() }}</span>
            <span class="sh-foot__of">de</span>
            <span class="sh-foot__total">{{ number_format($shipments->total()) }}</span>
            <span class="sh-foot__label">envíos</span>
        </div>

        {{-- Centro: filas por página --}}
        <div class="sh-foot__size">
            <label for="shPerPage">Filas</label>
            <div class="sh-select">
                <select id="shPerPage" aria-label="Filas por página">
                    @foreach([10, 20, 50, 100] as $n)
                        <option value="{{ request()->fullUrlWithQuery(['per_page' => $n, 'page' => 1]) }}"
                                {{ (int) ($perPage ?? 20) === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>

        {{-- Derecha: navegación --}}
        <nav class="sh-foot__nav" aria-label="Paginación">
            @if($cur > 1)
                <a href="{{ $shipments->url(1) }}" class="sh-pg" aria-label="Primera página" title="Primera"><i class="fas fa-angle-double-left"></i></a>
                <a href="{{ $shipments->previousPageUrl() }}" class="sh-pg" rel="prev" aria-label="Anterior" title="Anterior"><i class="fas fa-chevron-left"></i></a>
            @else
                <span class="sh-pg is-off" aria-disabled="true"><i class="fas fa-angle-double-left"></i></span>
                <span class="sh-pg is-off" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>
            @endif

            <div class="sh-pg-nums">
                @php $prev = 0; @endphp
                @foreach($pages as $p)
                    @if($prev && $p - $prev > 1)<span class="sh-pg-gap">···</span>@endif
                    @if($p === $cur)
                        <span class="sh-pg is-current" aria-current="page">{{ $p }}</span>
                    @else
                        <a href="{{ $shipments->url($p) }}" class="sh-pg">{{ $p }}</a>
                    @endif
                    @php $prev = $p; @endphp
                @endforeach
            </div>

            @if($cur < $last)
                <a href="{{ $shipments->nextPageUrl() }}" class="sh-pg" rel="next" aria-label="Siguiente" title="Siguiente"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ $shipments->url($last) }}" class="sh-pg" aria-label="Última página" title="Última"><i class="fas fa-angle-double-right"></i></a>
            @else
                <span class="sh-pg is-off" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>
                <span class="sh-pg is-off" aria-disabled="true"><i class="fas fa-angle-double-right"></i></span>
            @endif
        </nav>
    </div>
@endif
