{{-- Paginador del tablero de envíos. Espera $shipments (LengthAwarePaginator).
     Los enlaces caen dentro de #shPanel, así que el AJAX los intercepta solo. --}}
@if($shipments->total() > 0)
    @php
        $cur  = $shipments->currentPage();
        $last = $shipments->lastPage();
        // Ventana: primera, última y ±1 alrededor de la actual.
        $pages = collect(range(1, $last))->filter(
            fn ($p) => $p === 1 || $p === $last || abs($p - $cur) <= 1
        )->values();
    @endphp
    <div class="sh-pager">
        <div class="sh-pager__info">
            Mostrando <strong>{{ $shipments->firstItem() }}</strong>–<strong>{{ $shipments->lastItem() }}</strong>
            de <strong>{{ number_format($shipments->total()) }}</strong> envíos
        </div>

        @if($shipments->hasPages())
            <nav class="sh-pager__nav" aria-label="Paginación">
                @if($shipments->onFirstPage())
                    <span class="sh-page is-off" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $shipments->previousPageUrl() }}" class="sh-page" rel="prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></a>
                @endif

                @php $prev = 0; @endphp
                @foreach($pages as $p)
                    @if($prev && $p - $prev > 1)
                        <span class="sh-gap">…</span>
                    @endif
                    @if($p === $cur)
                        <span class="sh-page is-current" aria-current="page">{{ $p }}</span>
                    @else
                        <a href="{{ $shipments->url($p) }}" class="sh-page">{{ $p }}</a>
                    @endif
                    @php $prev = $p; @endphp
                @endforeach

                @if($shipments->hasMorePages())
                    <a href="{{ $shipments->nextPageUrl() }}" class="sh-page" rel="next" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="sh-page is-off" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>
                @endif
            </nav>
        @endif
    </div>
@endif
