@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();

        // Siempre mostramos la 1 y la última. La ventana solo cubre páginas intermedias.
        $middleFirst = 2;
        $middleLast = max(2, $lastPage - 1);

        // Ventana (tamaño 3) alrededor de la actual: (current-1, current, current+1)
        $windowStart = max($middleFirst, $currentPage - 1);
        $windowEnd = min($middleLast, $currentPage + 1);

        // Ajuste para mantener hasta 3 páginas visibles en la ventana (si existen)
        $windowSize = 3;
        $currentWindowSize = $windowEnd - $windowStart + 1;
        if ($currentWindowSize < $windowSize) {
            $missing = $windowSize - $currentWindowSize;
            $windowStart = max($middleFirst, $windowStart - $missing);
            $windowEnd = min($middleLast, $windowEnd + ($windowSize - ($windowEnd - $windowStart + 1)));
        }
    @endphp

    <nav aria-label="Paginación">
        <ul class="pagination mb-0">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
                </li>
            @endif

            {{-- Pages --}}
            @if ($lastPage <= 5)
                @for ($page = 1; $page <= $lastPage; $page++)
                    @if ($page === $currentPage)
                        <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
                    @endif
                @endfor
            @else
                {{-- 1 siempre --}}
                @if (1 === $currentPage)
                    <li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
                @endif

                {{-- Hueco entre 1 y ventana --}}
                @if ($windowStart > 2)
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>
                @endif

                {{-- Ventana de páginas intermedias (2..last-1) --}}
                @for ($page = $windowStart; $page <= $windowEnd; $page++)
                    @if ($page === 1 || $page === $lastPage)
                        @continue
                    @endif

                    @if ($page === $currentPage)
                        <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
                    @endif
                @endfor

                {{-- Hueco entre ventana y última --}}
                @if ($windowEnd < $lastPage - 1)
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>
                @endif

                {{-- Última siempre --}}
                @if ($currentPage === $lastPage)
                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $lastPage }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a></li>
                @endif
            @endif

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
