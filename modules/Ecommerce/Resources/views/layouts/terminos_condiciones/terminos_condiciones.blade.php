@extends('ecommerce::layouts.master')

@section('content')
<div class="container py-5">
    {{-- Navegación de miga de pan --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="/ecommerce" class="text-info">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Términos y Condiciones</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-9">
            {{-- Encabezado con icono de documento verificado --}}
            <div class="text-center mb-5">
                <i class="el-icon-document-checked text-info mb-3" style="font-size: 3.5rem;"></i>
                <h1 class="display-4 font-weight-bold text-dark" style="font-size: 2.8rem;">Términos y Condiciones</h1>
                <p class="lead text-muted">Reglas, deberes y derechos para el uso de nuestra plataforma.</p>
                <hr class="w-25 border-info" style="border-width: 3px; opacity: 0.6;">
            </div>

            {{-- Contenedor principal con fuente optimizada --}}
            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-body p-4 p-md-5">
                    <div class="policy-readable">
                        {{-- Muestra el contenido de la columna 'termino_conditions' --}}
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
    /* AJUSTES PARA TEXTO LEGIBLE Y PROFESIONAL */
    .policy-readable {
        color: #333333;
        line-height: 1.85; /* Espaciado amplio para evitar el efecto de "bloque de texto" */
        font-size: 1.2rem; /* Tamaño de fuente incrementado para mayor comodidad */
        word-wrap: break-word;
    }

    .policy-readable p {
        margin-bottom: 1.8rem;
    }

    /* Resalte para subtítulos creados en CKEditor */
    .policy-readable strong, 
    .policy-readable b {
        font-size: 1.3rem; 
        color: #000;
        display: inline-block;
        margin-top: 15px;
        text-transform: uppercase;
    }

    /* Estilos para jerarquía de títulos H1-H3 */
    .policy-readable h1, 
    .policy-readable h2, 
    .policy-readable h3 {
        color: #17a2b8;
        font-weight: 700;
        margin-top: 2.5rem;
        margin-bottom: 1.2rem;
    }

    /* Listas con buen margen */
    .policy-readable ul, 
    .policy-readable ol {
        margin-bottom: 2rem;
        padding-left: 2.5rem;
    }

    .policy-readable li {
        margin-bottom: 1rem;
    }

    /* Estilo de tarjeta moderna */
    .card.shadow-sm {
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
@endsection