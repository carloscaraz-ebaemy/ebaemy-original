@extends('ecommerce::layouts.master')

@section('content')
<div class="container py-5">
    {{-- Navegación de miga de pan --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="/ecommerce" class="text-info">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Políticas de Envío</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-9">
            {{-- Encabezado con icono de transporte --}}
            <div class="text-center mb-5">
                <i class="el-icon-truck text-info mb-3" style="font-size: 3.5rem;"></i>
                <h1 class="display-4 font-weight-bold text-dark" style="font-size: 2.8rem;">Políticas de Envío</h1>
                <p class="lead text-muted ">Conoce nuestros tiempos de entrega, costos y coberturas.</p>
                <hr class="w-25 border-info" style="border-width: 3px; opacity: 0.6;">
            </div>
            {{-- Contenedor de contenido con legibilidad aumentada --}}
            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-body p-4 p-md-5">
                    <div class="policy-readable">
                        {{-- Renderiza el contenido de la columna 'politica_envio' --}}
                        {!! $terms !!}
                    </div>
                </div>
            </div>

            <div class="text-center mt-5">
                <a href="/ecommerce" class="btn btn-secondary btn-lg px-5 shadow-sm text-white">
                    <i class="el-icon-back"></i> Volver a la tienda
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* AJUSTES PARA TEXTO GRANDE Y LEGIBLE */
    .policy-readable {
        color: #333333;
        line-height: 1.85; /* Mayor espacio entre líneas para evitar fatiga visual */
        font-size: 1.2rem; /* Tamaño de fuente incrementado */
        word-wrap: break-word;
    }

    .policy-readable p {
        margin-bottom: 1.8rem;
    }

    /* Resalte para los títulos internos pegados desde CKEditor */
    .policy-readable strong, 
    .policy-readable b {
        font-size: 1.3rem; 
        color: #000;
        display: inline-block;
        margin-top: 15px;
        text-transform: uppercase;
    }


    .policy-readable ul, 
    .policy-readable ol {
        margin-bottom: 2rem;
        padding-left: 2.5rem;
    }

    .policy-readable li {
        margin-bottom: 1rem;
    }

    /* Sombra suave para la tarjeta */
    .card.shadow-sm {
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
@endsection