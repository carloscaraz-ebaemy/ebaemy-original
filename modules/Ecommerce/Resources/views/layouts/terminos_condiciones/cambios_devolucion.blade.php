@extends('ecommerce::layouts.master')

@section('content')
<div class="container py-5">
    {{-- Navegación de miga de pan --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="/ecommerce" class="text-info">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Cambios y Devoluciones</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-9">
            {{-- Encabezado con icono de retorno/cambio --}}
            <div class="text-center mb-5">
                <i class="el-icon-refresh text-info mb-3" style="font-size: 3.5rem;"></i>
                <h1 class="display-4 font-weight-bold text-dark" style="font-size: 2.8rem;">Cambios y Devoluciones</h1>
                <p class="lead text-muted">Consulta nuestras políticas para cambios de productos y reembolsos.</p>
                <hr class="w-25 border-info" style="border-width: 3px; opacity: 0.6;">
            </div>

            {{-- Contenedor de contenido con legibilidad mejorada --}}
            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-body p-4 p-md-5">
                    <div class="policy-readable">
                        {{-- Se renderiza el contenido de la columna 'cambios_devolucion' --}}
                        {!! $terms !!}
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="/ecommerce" class="btn btn-info btn-lg px-5 shadow-sm text-white">
                    <i class="el-icon-back"></i> Volver a la tienda
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos de legibilidad (mantenemos los del envío para consistencia) */
    .policy-readable {
        color: #333333;
        line-height: 1.85; 
        font-size: 1.15rem; 
        word-wrap: break-word;
    }

    .policy-readable p {
        margin-bottom: 1.8rem;
    }

    .policy-readable strong, 
    .policy-readable b {
        font-size: 1.25rem;
        color: #000;
        display: inline-block;
        margin-top: 10px;
        text-transform: uppercase;
    }

    .policy-readable h1, 
    .policy-readable h2, 
    .policy-readable h3 {
        color: #17a2b8;
        font-weight: 700;
        margin-top: 2.5rem;
        margin-bottom: 1.2rem;
    }

    .policy-readable ul, 
    .policy-readable ol {
        margin-bottom: 2rem;
        padding-left: 2rem;
    }

    .policy-readable li {
        margin-bottom: 0.8rem;
    }

    .card.shadow-sm {
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
@endsection