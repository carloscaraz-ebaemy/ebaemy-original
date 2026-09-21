{{-- Partial returned on AJAX filter requests --}}
@php
    $total = $dataPaginate->total();
    $from  = $dataPaginate->firstItem() ?? 0;
    $to    = $dataPaginate->lastItem()  ?? 0;
@endphp

{{-- El total manda y el rango lo acompaña: «361 productos / Mostrando
     1–24». El <strong> con el total se queda dentro de #ec-results-count
     porque el boton «Ver N productos» del drawer movil lo lee de ahi
     (ver filter-ajax.js, syncApplyLabel). --}}
<div class="ec-results-bar">
    <p class="ec-results-count" id="ec-results-count">
        @if($total > 0)
            <strong>{{ number_format($total) }}</strong> producto{{ $total !== 1 ? 's' : '' }}
            <span class="ec-results-range">· Mostrando {{ $from }}–{{ $to }}</span>
        @else
            <strong>0</strong> productos encontrados
        @endif
    </p>
    <div class="ec-view-toggle" id="ec-view-toggle" role="group" aria-label="Cambiar vista">
        <button type="button" class="ec-view-btn ec-view-btn--active"
                data-view="grid" title="Vista cuadrícula" aria-pressed="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
            </svg>
        </button>
        <button type="button" class="ec-view-btn"
                data-view="list" title="Vista lista" aria-pressed="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                <line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/>
                <line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
        </button>
    </div>
</div>

<div class="row row-sm" id="ec-products-grid">
    @if($dataPaginate->count())
        @include('ecommerce::layouts.partials_ecommerce.list_products')
    @else
        <div class="col-12 ec-no-results">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                 fill="none" stroke="#ccc" stroke-width="1.2" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                <line x1="8" y1="11" x2="14" y2="11"/>
            </svg>
            <p>No se encontraron productos con los filtros seleccionados.</p>
            <a href="{{ url()->current() }}" class="ec-no-results__clear">Limpiar filtros</a>
        </div>
    @endif
</div>

<div class="row page-pagination mt-2" id="ec-pagination">
    <div class="col-md-12 col-lg-12 d-flex justify-content-end mb-4">
        {{ $dataPaginate->onEachSide(1)->links('restaurant::layouts.partials.pagination') }}
    </div>
</div>
