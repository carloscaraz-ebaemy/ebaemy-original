{{-- Fechas estimadas de UNA modalidad (ShippingEta::forForm()).

     La llegada se cuenta desde que el paquete SALE de la tienda: por eso en
     agencia se muestran los dos hitos, y no un "de 3 a 7 días" que el
     cliente no sabe desde cuándo contar.

     Parámetros: $etaType ('agencia' | 'domicilio' | 'tienda'), $eta,
                 $etaId (opcional, para que el JS la muestre u oculte). --}}
@php $e = $eta[$etaType] ?? null; @endphp
@if($e)
<div class="eta" @if(!empty($etaId)) id="{{ $etaId }}" hidden @endif>
    <div class="eta__t">¿Cuándo llega?</div>
    <ol class="tl">
        <li class="is-now"><b>Hoy</b><small>Registras tus datos de envío</small></li>
        @if($etaType === 'agencia')
            {{-- El viaje lo define la agencia, no la tienda: solo se da una
                 fecha de llegada cuando Shalom la cotiza para la ruta (oficina
                 de la tienda → oficina del cliente). Si no, no se inventa. --}}
            @php $arr = $sentArrival ?? null; @endphp
            <li><b>Sale de la tienda {{ $e['leave_rel'] }}</b><small>{{ ucfirst($e['leave']) }}. Te enviamos tu número de guía por WhatsApp.</small></li>
            <li class="js-eta-arrive">
                @if($arr)
                    <b>Llega a tu oficina aprox. el {{ $arr['date'] }}</b>
                    <small>Shalom tarda unas {{ $arr['hours'] }} horas en esta ruta. Recoges con tu documento de identidad.</small>
                @else
                    <b>Viaja con la agencia hasta tu ciudad</b>
                    <small>El tiempo de viaje lo define la agencia. Te avisamos cuando llegue para que lo recojas con tu documento.</small>
                @endif
            </li>
        @elseif($etaType === 'domicilio')
            <li><b>Te lo entregamos {{ $e['range'] }}</b><small>El motorizado te escribe antes de llegar.</small></li>
        @else
            <li><b>Listo para recoger {{ $e['ready'] === '' ? '' : 'desde el ' . $e['ready'] }}</b><small>Te avisamos por WhatsApp apenas esté listo.</small></li>
        @endif
    </ol>
    <div class="eta__s">
        Fechas estimadas en días hábiles (sin domingos ni feriados){{ $etaType === 'agencia' ? '; el viaje depende de la agencia' : '' }}.
        @if(!empty($eta['cutoff']))Lo registrado después de las {{ $eta['cutoff'] }} se prepara al día siguiente.@endif
    </div>
</div>
@endif
