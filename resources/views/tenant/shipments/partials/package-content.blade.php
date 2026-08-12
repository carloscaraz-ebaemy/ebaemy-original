{{-- ══════════════════════════════════════════════════════════════════════
     CONTENIDO DEL PAQUETE (rótulo)
     Un solo lugar que decide cómo se ve el detalle de producto, para que el
     rótulo individual y el del lote no se separen nunca.

     El cliente escribe listas a mano ("- planta cebra grande + soporte") y
     esas líneas son más largas que el ancho del rótulo, así que envuelven.
     Se pintan como lista real con sangría francesa: la continuación queda
     alineada bajo el TEXTO, no bajo la viñeta.

     Espera: $shipment y, opcional, $title.
     ══════════════════════════════════════════════════════════════════════ --}}
@php
    $pkgLines = $shipment->contentLines();
@endphp

@if(count($pkgLines))
    <div class="section">
        <div class="section-title">
            {{ $title ?? 'Contenido del paquete' }}
            @if(count($pkgLines) > 1)
                <span class="pkg-count">{{ count($pkgLines) }} ítems</span>
            @endif
        </div>

        @if(count($pkgLines) === 1)
            {{-- Un solo ítem: no merece viñeta ni numeración. --}}
            <div class="med-text pkg-single">{{ $pkgLines[0] }}</div>
        @else
            {{-- NUMERADA: quien embala va contando, y un número se sigue mejor
                 que una viñeta cuando hay que verificar que no falte nada. --}}
            @php
                // Umbrales sobre datos reales: el envío más cargado tiene 9
                // ítems y el promedio es 3. Con 6 se aprieta el interlineado y
                // con 10 se pasa a dos columnas, antes de que el JS tenga que
                // achicar la letra de todo el rótulo.
                $n = count($pkgLines);
                $pkgClass = 'pkg-list med-text'
                    . ($n >= 6  ? ' pkg-list--dense' : '')
                    . ($n >= 10 ? ' pkg-list--cols'  : '');
            @endphp
            <ol class="{{ $pkgClass }}">
                @foreach($pkgLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        @endif
    </div>
@endif
