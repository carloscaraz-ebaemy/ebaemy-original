@extends('tenant.layouts.app')

@section('content')
    {{-- `shipping` decide si se ofrece el boton de configuracion: las dos
         pantallas viven en el modulo de Envios y en un tenant sin el modulo
         serian un 404. Se resuelve aqui y no en el Vue porque es una pregunta
         de esquema, no de pantalla. --}}
    <tenant-orders-index
        :user="{{ json_encode(auth()->user()) }}"
        :shipping="{{ \App\Models\Tenant\ShippingRequest::moduleInstalled() ? 'true' : 'false' }}"
    ></tenant-orders-index>
@endsection
