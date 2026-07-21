@extends('tenant.layouts.app')

@push('styles')
<style>
    /* Compactar la lista de productos: los nombres largos ("Planta Artificial |
       OREJA DE ELEFANTE | x18 Hojas | Base y Soporte de Metal") wrapeaban a ~6
       líneas y solo entraban 2 productos por pantalla. Se recortan a 1 línea con
       "…" y se baja el alto de fila → entran muchos más productos por pantalla.
       El nombre completo sigue disponible en el modal Editar. */
    .items_ecommerce table td,
    .items_ecommerce table th { padding-top:6px !important; padding-bottom:6px !important; vertical-align:middle; }
    .items_ecommerce table td:nth-child(4) {
        max-width:360px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    @media (max-width:768px) {
        .items_ecommerce table td:nth-child(4) { max-width:200px; }
    }
</style>
@endpush

@section('content')
    <tenant-items-ecommerce-index></tenant-items-ecommerce-index>

@endsection
