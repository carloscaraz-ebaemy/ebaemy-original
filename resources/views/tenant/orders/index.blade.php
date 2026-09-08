@extends('tenant.layouts.app')

@section('content')
    {{-- `shipping` decide si se ofrece el boton de configuracion: las dos
         pantallas viven en el modulo de Envios y en un tenant sin el modulo
         serian un 404. Se resuelve aqui y no en el Vue porque es una pregunta
         de esquema, no de pantalla. --}}
    @php
        // Los dos avisos que vivian en el menu lateral. Se calculan AQUI y no
        // alli porque alli corrian en cada carga de cualquier pantalla del
        // panel; solo hacen falta en Pedidos, que es donde se actua sobre ellos.
        $shipAlerts = ['sin_guia' => 0, 'lotes' => 0];
        try {
            $shipAlerts['sin_guia'] = \App\Models\Tenant\ShippingRequest::withoutGuide()->count();
            $shipAlerts['lotes']    = \App\Models\Tenant\ShippingPrintBatch::open()->count();
        } catch (\Throwable $e) {
            // Tenant sin el modulo: los avisos no aplican y no es un error.
        }
    @endphp
    <tenant-orders-index
        :user="{{ json_encode(auth()->user()) }}"
        :ship-alerts="{{ json_encode($shipAlerts) }}"
        :shipping="{{ \App\Models\Tenant\ShippingRequest::moduleInstalled() ? 'true' : 'false' }}"
        :verification="{{ \App\Services\Tenant\PaymentVerification::requerida() ? 'true' : 'false' }}"
    ></tenant-orders-index>
@endsection
