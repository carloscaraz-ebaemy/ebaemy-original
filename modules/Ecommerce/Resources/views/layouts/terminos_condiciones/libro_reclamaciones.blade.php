@extends('ecommerce::layouts.master')


@section('content')
<div class="container" style="padding-top: 120px; padding-bottom: 50px;">
@if(session('success'))
    <div class="alert alert-success" id="successMessage" style="position: top; top: 10%; left: 50%; transform: translateX(-50%); z-index: 9999; padding: 20px; text-align: center; background-color: #28a745; color: white; border-radius: 5px; font-weight: bold; font-size: 18px; opacity: 1 !important; display: block !important;">
        {{ session('success') }}
    </div>
@endif
    <div class="row justify-content-center">
        <div class="col-md-10">
            <h2 class="mb-4 text-center text-primary">Libro de Reclamaciones</h2>
            <p class="text-center mb-2">
                <strong>"{{ $name_company }}" RUC: {{ $number_company }}</strong><br>
            </p>

            <form method="POST" enctype="multipart/form-data">
                @csrf

                {{-- 1. Identificación del Consumidor Reclamante --}}
                <h4 class="mt-4">1. Identificación del Consumidor Reclamante</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombres" class="form-label">Nombres *</label>
                        <input type="text" name="nombres" id="nombres" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="apellidos" class="form-label">Apellidos *</label>
                        <input type="text" name="apellidos" id="apellidos" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fecha_reclamo" class="form-label">Fecha del Reclamo *</label>
                        <input type="date" name="fecha_reclamo" id="fecha_reclamo" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="correo" class="form-label">Correo electrónico</label>
                        <input type="email" name="correo" id="correo" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tipo_documento" class="form-label">Tipo de Documento *</label>
                        <select name="tipo_documento" id="tipo_documento" class="form-control" required>
                            <option value="DNI">DNI</option>
                            <option value="CE">Carné de Extranjería</option>
                            <option value="PAS">Pasaporte</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="numero_documento" class="form-label">N° Documento *</label>
                        <input type="text" name="numero_documento" id="numero_documento" class="form-control" required>
                    </div>
                </div>

                {{-- 2. Identificación del Bien Contratado --}}
                <h4 class="mt-4">2. Identificación del Bien Contratado</h4>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label d-block">Tipo *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_bien" id="bien" value="Bien" checked>
                            <label class="form-check-label" for="bien">Bien</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_bien" id="servicio" value="Servicio">
                            <label class="form-check-label" for="servicio">Servicio</label>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="moneda" class="form-label">Seleccione Moneda *</label>
                        <select name="moneda" id="moneda" class="form-control" required>
                            <option value="S/">S/</option>
                            <option value="$">$</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="monto" class="form-label">Monto Reclamado *</label>
                        <input type="number" name="monto" id="monto" class="form-control" min="0" step="0.01" required>
                    </div>

                    <div class="col-12 mb-3">
                        <label for="descripcion" class="form-label">
                            Descripción (Máx 2000 caracteres) *
                        </label>
                        <textarea name="descripcion" id="descripcion" rows="3" maxlength="2000"
                            class="form-control" style="resize: vertical; min-height: 100px;"></textarea>
                        <div class="text-end">
                            <small id="charCount">0 / 2000</small>
                        </div>
                    </div>
                </div>

                {{-- 3. Detalle de la Reclamación y Pedido del Consumidor --}}
                <h4 class="mt-4">3. Detalle de la Reclamación y Pedido del Consumidor</h4>
                    {{-- Tipo de reclamo --}}
                    <div class="mb-3">
                        <label class="form-label d-block">Tipo *</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_reclamo" id="reclamo" value="Reclamo" required>
                            <label class="form-check-label" for="reclamo">Reclamo</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_reclamo" id="queja" value="Queja">
                            <label class="form-check-label" for="queja">Queja</label>
                        </div>
                    </div>

                    {{-- Detalle --}}
                    <div class="mb-3">
                        <label for="detalle_reclamo" class="form-label">Detalles (Máx 1000 caracteres) *</label>
                        <textarea name="detalle_reclamo" id="detalle_reclamo" maxlength="1000" rows="3" class="form-control" required></textarea>
                        <div class="text-end">
                            <small id="detalleCount">0 / 1000</small>
                        </div>
                    </div>

                    {{-- Pedido --}}
                    <div class="mb-3">
                        <label for="pedido_consumidor" class="form-label">Pedidos (Máx 1000 caracteres) *</label>
                        <textarea name="pedido_consumidor" id="pedido_consumidor" maxlength="1000" rows="3" class="form-control" required></textarea>
                        <div class="text-end">
                            <small id="pedidoCount">0 / 1000</small>
                        </div>
                    </div>

                    {{-- Adjuntos --}}
                    <div class="mb-3">
                        <label class="form-label">Subir evidencia relacionada con el Reclamo o Queja (jpg, jpeg, png o PDF)</label>
                        <input type="file" name="archivos[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    {{-- Leyenda explicativa --}}
                    <p class="text-muted">
                        <strong>Reclamo:</strong> Disconformidad relacionada a los productos o servicios. 
                        <strong>Queja:</strong> Disconformidad no relacionada a los productos o servicios; o malestar o descontento respecto a la atención al público.
                    </p>

                    {{-- Aceptación --}}
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="autorizacion_datos" id="autorizacion_datos" required>
                        <label class="form-check-label" for="autorizacion_datos">
                            Autorizo a <strong>{{ $name_company }}</strong> a registrar, almacenar y utilizar mis datos personales para los fines de este formulario.
                        </label>
                    </div>

                    {{-- Enlace de consentimiento --}}
                    <div class="mb-4">
                         <a href="{{ route('tenant.politica_privacidad') }}" target="_blank" class="text-primary" style="text-decoration: underline;">
                            (*) Ver Consentimiento de uso de datos personales
                        </a> 
                    </div>
                
                    {{-- Botón Enviar --}}
                    <div class="text-start">
                        <form action="mailto:{{$information_contact_email}}" method="POST" enctype="multipart/form-data">
                        @csrf
                            <input type="hidden" name="subject" value="Libro de Reclamaciones - {{ $information_contact_email }}">
                            <input type="hidden" name="body" value="Detalles del Reclamo: {{ old('detalle_reclamo') }}. Peticiones: {{ old('pedido_consumidor') }}. Moneda: {{ old('moneda') }}. Monto: {{ old('monto') }}.">
                            <button type="submit" class="btn btn-primary " style=" border: none;">
                                    ENVIAR
                            </button>
                        </form>
                    </div>

            </form>
        </div>
    </div>
</div>
@endsection
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var successMessage = document.getElementById('successMessage');
            
            if (successMessage) {
                // Espera 3 segundos y luego desvanece el mensaje
                setTimeout(function() {
                    successMessage.style.transition = "opacity 1s ease-out"; // Agregar transición
                    successMessage.style.opacity = '0'; // Desvanece el mensaje
                    // Después de 1 segundo (duración de la transición), oculta el div
                    setTimeout(function() {
                        successMessage.style.display = 'none'; // Lo oculta completamente
                    }, 1000); // 1 segundo después de la transición de opacidad
                }, 3000);  // 3000 milisegundos = 3 segundos de espera
            }
        });
    </script>
@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const campos = [
            { id: 'descripcion', contador: 'charCount', max: 2000 },
            { id: 'detalle_reclamo', contador: 'detalleCount', max: 1000 },
            { id: 'pedido_consumidor', contador: 'pedidoCount', max: 1000 }
        ];

        campos.forEach(({ id, contador, max }) => {
            const campo = document.getElementById(id);
            const contadorEl = document.getElementById(contador);

            if (campo && contadorEl) {
                contadorEl.textContent = `${campo.value.length} / ${max}`;
                campo.addEventListener('input', function () {
                    contadorEl.textContent = `${this.value.length} / ${max}`;
                });
            }
        });
    });
</script>
@endpush