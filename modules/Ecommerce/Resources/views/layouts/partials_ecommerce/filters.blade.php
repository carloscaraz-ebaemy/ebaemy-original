@php
    $currentSort  = $sortBy    ?? 'newest';
    $currentAvail = $onlyAvail ?? 0;
    $currentMin   = $minPrice  ?? '';
    $currentMax   = $maxPrice  ?? '';
    $baseUrl      = url()->current();
    $prMin        = $priceRange['min'] ?? 0;
    $prMax        = $priceRange['max'] ?? 9999;
    $sliderMin    = is_numeric($currentMin) ? (int)$currentMin : $prMin;
    $sliderMax    = is_numeric($currentMax) && (int)$currentMax > 0 ? (int)$currentMax : $prMax;
    $searchQ      = request('q');
@endphp

@if($searchQ)
<div class="ec-search-active-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
    </svg>
    Resultados para: <strong>"{{ $searchQ }}"</strong>
    <a href="{{ $baseUrl }}" class="ec-search-active-clear" title="Quitar búsqueda">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </a>
</div>
@endif

{{-- ── Mobile toggle button (visible sólo en móvil) ──────────────── --}}
<button type="button" class="ec-filter-mob-toggle" id="ec-filter-mob-toggle"
        aria-expanded="false" aria-controls="ec-filter-form-wrap">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/>
    </svg>
    <span>Filtros</span>
    <span class="ec-filter-count-badge" id="ec-filter-badge" style="display:none">0</span>
    <svg class="ec-filter-mob-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"/>
    </svg>
</button>

{{-- ── Filter form (colapsable en móvil) ─────────────────────────── --}}
<div id="ec-filter-form-wrap" class="ec-filter-form-wrap">
    <form method="GET" action="{{ $baseUrl }}" id="ec-filter-form" class="ec-filters"
          data-ajax-url="{{ $baseUrl }}"
          data-price-min="{{ $prMin }}"
          data-price-max="{{ $prMax }}">

        {{-- Ordenar por --}}
        <div class="ec-filter-group">
            <label for="ec-sort" class="ec-filter-label">Ordenar</label>
            <div class="ec-filter-select-wrap">
                <select id="ec-sort" name="sort" class="ec-filter-select">
                    <option value="newest"     {{ $currentSort === 'newest'     ? 'selected' : '' }}>Más recientes</option>
                    <option value="price_asc"  {{ $currentSort === 'price_asc'  ? 'selected' : '' }}>Menor precio</option>
                    <option value="price_desc" {{ $currentSort === 'price_desc' ? 'selected' : '' }}>Mayor precio</option>
                    <option value="name_asc"   {{ $currentSort === 'name_asc'   ? 'selected' : '' }}>A → Z</option>
                </select>
            </div>
        </div>

        {{-- Rango de precio con slider --}}
        <div class="ec-filter-group ec-filter-group--price">
            <label class="ec-filter-label">
                Precio:
                <span id="ec-price-display">
                    S/ {{ $sliderMin }} – S/ {{ $sliderMax }}
                </span>
            </label>
            <div class="ec-range-slider" id="ec-range-slider"
                 data-min="{{ $prMin }}" data-max="{{ $prMax }}"
                 data-val-min="{{ $sliderMin }}" data-val-max="{{ $sliderMax }}">
                <div class="ec-range-track">
                    <div class="ec-range-fill" id="ec-range-fill"></div>
                </div>
                <input type="range" class="ec-range-input ec-range-input--min"
                       id="ec-range-min" name="min_price"
                       min="{{ $prMin }}" max="{{ $prMax }}"
                       value="{{ $sliderMin }}" step="1">
                <input type="range" class="ec-range-input ec-range-input--max"
                       id="ec-range-max" name="max_price"
                       min="{{ $prMin }}" max="{{ $prMax }}"
                       value="{{ $sliderMax }}" step="1">
            </div>
        </div>

        {{-- Solo disponibles --}}
        <div class="ec-filter-group ec-filter-group--toggle">
            <label class="ec-filter-toggle" for="ec-only-avail">
                <input type="checkbox"
                       id="ec-only-avail"
                       name="available"
                       value="1"
                       {{ $currentAvail ? 'checked' : '' }}>
                <span class="ec-filter-toggle__track"></span>
                <span class="ec-filter-label">Solo disponibles</span>
            </label>
        </div>

        {{-- Hidden category_id for pill filter --}}
        <input type="hidden" name="category_id" id="ec-filter-category" value="">

        {{-- Limpiar filtros --}}
        <div class="ec-filter-group" id="ec-clear-wrap"
             style="{{ ($currentSort !== 'newest' || $currentAvail || $currentMin !== '' || $currentMax !== '') ? '' : 'display:none' }}">
            <button type="button" class="ec-filter-clear" id="ec-filter-clear">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Limpiar
            </button>
        </div>

    </form>
</div>

{{-- ── Active filter chips (JS-rendered) ─────────────────────────── --}}
<div class="ec-active-chips" id="ec-active-chips" style="display:none"></div>
