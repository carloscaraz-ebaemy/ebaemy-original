{{--
    Paso 2 y 3 del formulario: elegir el ORIGEN de los participantes y
    configurar sus filtros.

    Todo se pinta desde el esquema que declara cada ParticipantSource
    (`filters()`), así que sumar un módulo nuevo al sorteo NO requiere tocar
    esta vista: aparece solo con sus propios filtros.

    Variables: $sources (Collection<ParticipantSource>), $raffle, $filterOptions.
--}}
@php
    $currentSource = old('source', $raffle->source ?: 'orders_management');
    $stored        = $raffle->source_filters ?? [];
@endphp

<div class="rf-section">
    <div class="rf-section__label">Origen de los participantes</div>
    <div class="rf-note mb-2">
        Elige de qué módulo salen los clientes. Cada origen trae sus propios filtros.
    </div>

    <div class="rf-sources">
        @foreach($sources as $source)
            @php $disabled = !$source->available(); @endphp
            <label class="rf-source {{ $disabled ? 'is-disabled' : '' }}"
                   title="{{ $disabled ? $source->unavailableReason() : $source->description() }}">
                <input type="radio" name="source" value="{{ $source->key() }}"
                       @checked($currentSource === $source->key()) @disabled($disabled)>
                <span class="rf-source__icon">{{ $source->icon() }}</span>
                <span class="rf-source__body">
                    <span class="rf-source__name">{{ $source->label() }}</span>
                    <span class="rf-source__desc">
                        {{ $disabled ? $source->unavailableReason() : $source->description() }}
                    </span>
                </span>
            </label>
        @endforeach
    </div>
</div>

{{-- Un panel de filtros por origen; el JS muestra solo el del origen elegido. --}}
@foreach($sources as $source)
    @php $isCurrent = $currentSource === $source->key(); @endphp
    <div class="rf-section rf-filters" data-rf-filters="{{ $source->key() }}" @style(['display:none' => !$isCurrent])>
        <div class="rf-section__label">Filtros · {{ $source->label() }}</div>

        @foreach($source->filters() as $filter)
            @php
                // Solo el origen activo hidrata sus valores guardados: los
                // paneles ocultos arrancan vacíos para no ensuciar el POST.
                $value   = $isCurrent ? ($stored[$filter['key']] ?? ($filter['default'] ?? null)) : ($filter['default'] ?? null);
                $old     = old("filters.{$filter['key']}");
                $value   = $old !== null ? $old : $value;
                $name    = "filters[{$filter['key']}]";
                $id      = "f_{$source->key()}_{$filter['key']}";
                $catalog = is_string($filter['options'] ?? null)
                            ? ($filterOptions[$filter['options']] ?? [])
                            : ($filter['options'] ?? []);
            @endphp

            <div class="rf-field">
                @if($filter['type'] === 'boolean')
                    <label class="rf-check" for="{{ $id }}">
                        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
                               @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN)) @disabled(!$isCurrent)>
                        <span>{{ $filter['label'] }}</span>
                    </label>

                @elseif($filter['type'] === 'multiselect')
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <select id="{{ $id }}" name="{{ $name }}[]" class="rf-input" multiple size="5" @disabled(!$isCurrent)>
                        @foreach($catalog as $optValue => $optLabel)
                            <option value="{{ $optValue }}" @selected(in_array((string) $optValue, array_map('strval', (array) $value), true))>
                                {{ $optLabel }}
                            </option>
                        @endforeach
                    </select>

                @elseif($filter['type'] === 'select')
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <select id="{{ $id }}" name="{{ $name }}" class="rf-input" @disabled(!$isCurrent)>
                        <option value="">Todos</option>
                        @foreach($catalog as $optValue => $optLabel)
                            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                        @endforeach
                    </select>

                @elseif($filter['type'] === 'textarea')
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <textarea id="{{ $id }}" name="{{ $name }}" class="rf-input" rows="{{ $filter['rows'] ?? 5 }}"
                              placeholder="{{ $filter['placeholder'] ?? '' }}" @disabled(!$isCurrent)>{{ $value }}</textarea>

                @elseif($filter['type'] === 'items')
                    <label for="{{ $id }}_search">{{ $filter['label'] }}</label>
                    <input id="{{ $id }}_search" class="rf-input js-rf-item-search" autocomplete="off"
                           placeholder="Escribe para buscar un producto…" @disabled(!$isCurrent)>
                    <div class="rf-note js-rf-item-results"></div>
                    <div class="rf-thumbs js-rf-item-chips" data-name="{{ $name }}[]"></div>

                @elseif($filter['type'] === 'date')
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <input id="{{ $id }}" type="date" name="{{ $name }}" class="rf-input"
                           value="{{ $value }}" @disabled(!$isCurrent)>

                @elseif($filter['type'] === 'number')
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <input id="{{ $id }}" type="number" step="0.01" min="0" name="{{ $name }}" class="rf-input"
                           value="{{ $value }}" @disabled(!$isCurrent)>

                @else
                    <label for="{{ $id }}">{{ $filter['label'] }}</label>
                    <input id="{{ $id }}" type="text" name="{{ $name }}" class="rf-input"
                           value="{{ $value }}" @disabled(!$isCurrent)>
                @endif

                @if(!empty($filter['help']))
                    <div class="rf-note">{{ $filter['help'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

{{-- Regla común a todos los orígenes: se aplica sobre el acumulado del cliente. --}}
<div class="rf-section">
    <div class="rf-section__label">Regla general</div>
    <div class="rf-field">
        <label for="rf_min_amount">Monto acumulado mínimo por cliente</label>
        <input id="rf_min_amount" type="number" step="0.01" min="0" name="min_amount" class="rf-input"
               value="{{ old('min_amount', $raffle->min_amount) }}" placeholder="Opcional">
        <div class="rf-note">
            Se evalúa sobre el total del cliente en el origen elegido, no por pedido:
            quien compró dos veces S/ 100 alcanza un mínimo de S/ 200.
        </div>
    </div>
</div>
