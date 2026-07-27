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
        <div class="section-title">{{ $title ?? 'Contenido del paquete' }}</div>

        @if(count($pkgLines) === 1)
            {{-- Un solo ítem: no merece viñeta. --}}
            <div class="med-text pkg-single">{{ $pkgLines[0] }}</div>
        @else
            <ul class="pkg-list med-text">
                @foreach($pkgLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
