@extends('tenant.layouts.app')

@section('content')
@php
    $econfig = \App\Models\Tenant\ConfigurationEcommerce::first();
@endphp

<div class="page-header pr-0">
    <h2><i class="fas fa-bell"></i></h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Tienda Virtual</a></li>
        <li class="active"><span>Notificaciones</span></li>
    </ol>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fab fa-whatsapp mr-2" style="color:#25d366"></i>
                    Notificaciones WhatsApp
                </h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4" style="font-size:13px">
                    Configura qué notificaciones deseas recibir por WhatsApp y cada cuánto tiempo.
                </p>

                <form id="notification-form">
                    {{-- Intervalo de recordatorio --}}
                    <div class="form-group mb-4">
                        <label class="font-weight-bold">
                            <i class="fas fa-clock mr-1"></i>
                            Recordatorio de pedidos pendientes cada:
                        </label>
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <select id="notification_interval" class="form-control" style="max-width:200px">
                                <option value="1" {{ ($econfig->notification_interval ?? 5) == 1 ? 'selected' : '' }}>1 minuto</option>
                                <option value="5" {{ ($econfig->notification_interval ?? 5) == 5 ? 'selected' : '' }}>5 minutos</option>
                                <option value="10" {{ ($econfig->notification_interval ?? 5) == 10 ? 'selected' : '' }}>10 minutos</option>
                                <option value="15" {{ ($econfig->notification_interval ?? 5) == 15 ? 'selected' : '' }}>15 minutos</option>
                                <option value="30" {{ ($econfig->notification_interval ?? 5) == 30 ? 'selected' : '' }}>30 minutos</option>
                                <option value="60" {{ ($econfig->notification_interval ?? 5) == 60 ? 'selected' : '' }}>1 hora</option>
                                <option value="120" {{ ($econfig->notification_interval ?? 5) == 120 ? 'selected' : '' }}>2 horas</option>
                                <option value="360" {{ ($econfig->notification_interval ?? 5) == 360 ? 'selected' : '' }}>6 horas</option>
                                <option value="720" {{ ($econfig->notification_interval ?? 5) == 720 ? 'selected' : '' }}>12 horas</option>
                                <option value="1440" {{ ($econfig->notification_interval ?? 5) == 1440 ? 'selected' : '' }}>24 horas</option>
                            </select>
                            <span class="text-muted" style="font-size:13px">Si hay pedidos sin confirmar después de este tiempo, te avisamos.</span>
                        </div>
                    </div>

                    <hr>

                    {{-- Tipos de notificación --}}
                    <label class="font-weight-bold mb-3">
                        <i class="fas fa-toggle-on mr-1"></i>
                        Tipos de notificación:
                    </label>

                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notify_new_order"
                                   {{ ($econfig->notify_new_order ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_new_order">
                                <strong>Nuevo pedido</strong>
                                <br><small class="text-muted">Te avisa al WhatsApp cuando un cliente hace un pedido nuevo</small>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notify_pending_reminder"
                                   {{ ($econfig->notify_pending_reminder ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_pending_reminder">
                                <strong>Recordatorio de pendientes</strong>
                                <br><small class="text-muted">Recuerda periódicamente si hay pedidos sin confirmar</small>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notify_order_confirmed"
                                   {{ ($econfig->notify_order_confirmed ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_order_confirmed">
                                <strong>Pedido confirmado</strong>
                                <br><small class="text-muted">Avisa cuando confirmas un pedido (para verificación)</small>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notify_customer_order"
                                   {{ ($econfig->notify_customer_order ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_customer_order">
                                <strong>Notificar al cliente</strong>
                                <br><small class="text-muted">Envía WhatsApp al cliente cuando hace un pedido y cuando se despacha</small>
                            </label>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-primary" id="btn-save-notifications">
                            <i class="fas fa-save mr-1"></i> Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btn-test-whatsapp">
                            <i class="fab fa-whatsapp mr-1"></i> Enviar mensaje de prueba
                        </button>
                    </div>

                    <p id="save-msg" class="mt-3" style="display:none;font-size:13px"></p>
                </form>
            </div>
        </div>
    </div>

    {{-- Panel derecho: estado de conexión --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0"><i class="fas fa-signal mr-2"></i> Estado</h4>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>WhatsApp Admin:</strong><br>
                    <span class="text-muted">{{ $econfig->phone_whatsapp ?? 'No configurado' }}</span>
                </div>
                <div class="mb-3">
                    <strong>Servicio QR Api:</strong><br>
                    @php $config = \App\Models\Tenant\Configuration::first(); @endphp
                    @if($config->qr_api_enable && $config->qr_api_url)
                    <span class="text-success"><i class="fas fa-check-circle"></i> Conectado</span>
                    <br><small class="text-muted">{{ $config->qr_api_url }}</small>
                    @else
                    <span class="text-danger"><i class="fas fa-times-circle"></i> No configurado</span>
                    <br><small><a href="/companies/create">Configurar en Empresa</a></small>
                    @endif
                </div>
                <div>
                    <strong>Intervalo actual:</strong><br>
                    <span class="badge badge-primary" style="font-size:14px">{{ $econfig->notification_interval ?? 5 }} min</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};
    var msg = document.getElementById('save-msg');

    document.getElementById('btn-save-notifications').addEventListener('click', function(){
        var body = JSON.stringify({
            notification_interval: parseInt(document.getElementById('notification_interval').value),
            notify_new_order: document.getElementById('notify_new_order').checked ? 1 : 0,
            notify_pending_reminder: document.getElementById('notify_pending_reminder').checked ? 1 : 0,
            notify_order_confirmed: document.getElementById('notify_order_confirmed').checked ? 1 : 0,
            notify_customer_order: document.getElementById('notify_customer_order').checked ? 1 : 0,
        });
        fetch('/ecommerce/configuration_notifications', {method:'POST', headers:headers, body:body})
        .then(function(r){return r.json()})
        .then(function(data){
            msg.style.display = 'block';
            msg.className = 'mt-3 text-success';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || 'Guardado');
            setTimeout(function(){ msg.style.display = 'none'; }, 5000);
        })
        .catch(function(e){
            msg.style.display = 'block';
            msg.className = 'mt-3 text-danger';
            msg.textContent = 'Error: ' + e.message;
        });
    });

    document.getElementById('btn-test-whatsapp').addEventListener('click', function(){
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        var btn = this;
        fetch('/ecommerce/test_whatsapp', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp mr-1"></i> Enviar mensaje de prueba';
            alert(data.success ? '✅ Mensaje enviado correctamente' : '❌ ' + (data.message || 'Error al enviar'));
        });
    });
});
</script>
@endsection
