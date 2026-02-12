@extends('ecommerce::layouts.master')

@section('content')
<div class="container py-5">
    {{-- Navegación de miga de pan --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="/ecommerce" class="text-info">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Políticas de Privacidad</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-9">
            {{-- Encabezado con icono de seguridad/privacidad --}}
            <div class="text-center mb-5">
                <i class="el-icon-lock text-info mb-3" style="font-size: 3.5rem;"></i>
                <h1 class="display-4 font-weight-bold text-dark" style="font-size: 2.8rem;">Políticas de Privacidad</h1>
                <p class="lead text-muted">Tu privacidad es importante para nosotros. Conoce cómo protegemos tus datos.</p>
                <hr class="w-25 border-info" style="border-width: 3px; opacity: 0.6;">
            </div>

            {{-- Contenedor principal con fuente optimizada --}}
            <div class="card shadow-sm border-0" style="border-radius: 20px;">
                <div class="card-body p-4 p-md-5">
                    <div class="policy-readable">
                        {{-- Muestra el contenido de la columna 'politica_privacy' --}}
                        {!! $terms !!}
                    </div>
                </div>
            </div>

            {{-- Botón de acción --}}
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
        color: #333333; /* Color oscuro para mayor nitidez */
        line-height: 1.85; /* Espaciado amplio para facilitar la lectura */
        font-size: 1.2rem; /* Tamaño de fuente incrementado para evitar texto pequeño */
        word-wrap: break-word;
    }

    .policy-readable p {
        margin-bottom: 1.8rem;
    }

    /* Resalte para subtítulos importantes */
    .policy-readable strong, 
    .policy-readable b {
        font-size: 1.3rem; 
        color: #000;
        display: inline-block;
        margin-top: 15px;
        text-transform: uppercase;
    }

    /* Estilos para encabezados H1-H3 dentro del CKEditor */
    .policy-readable h1, 
    .policy-readable h2, 
    .policy-readable h3 {
        color: #17a2b8;
        font-weight: 700;
        margin-top: 2.5rem;
        margin-bottom: 1.2rem;
    }

    /* Listas ordenadas y desordenadas */
    .policy-readable ul, 
    .policy-readable ol {
        margin-bottom: 2rem;
        padding-left: 2.5rem;
    }

    .policy-readable li {
        margin-bottom: 1rem;
    }

    /* Sombra de la tarjeta similar a la estética del sitio */
    .card.shadow-sm {
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
@endsection