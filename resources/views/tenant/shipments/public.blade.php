<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de envío — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#2563eb; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f1f5f9; color:var(--ink); }
        .wrap { max-width: 520px; margin: 0 auto; padding: 18px 14px 48px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:20px; box-shadow:0 6px 24px -12px rgba(15,23,42,.15); }
        .head { text-align:center; margin-bottom:18px; }
        .head h1 { font-size:19px; margin:6px 0 2px; }
        .head p { margin:0; color:var(--muted); font-size:13px; }
        .brand { font-size:13px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; }
        label { display:block; font-size:13px; font-weight:600; margin:12px 0 4px; }
        .req::after { content:" *"; color:#dc2626; }
        input[type=text], input[type=tel], select, textarea {
            width:100%; padding:11px 12px; border:1px solid var(--line); border-radius:10px; font-size:15px; background:#fff;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .row { display:flex; gap:10px; }
        .row > div { flex:1; }
        .terms { display:flex; align-items:flex-start; gap:8px; margin:16px 0; font-size:13px; color:#374151; }
        .terms input { margin-top:3px; }
        .btn { width:100%; padding:13px; border:none; border-radius:12px; background:var(--brand); color:#fff; font-size:16px; font-weight:700; cursor:pointer; }
        .btn:active { transform:scale(.99); }
        .alert { padding:11px 13px; border-radius:10px; font-size:13.5px; margin-bottom:14px; }
        .alert-err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .ok { text-align:center; padding:10px 0; }
        .ok .code { font-size:26px; font-weight:800; letter-spacing:2px; color:var(--brand); background:#eff6ff; border:1px dashed var(--brand); border-radius:12px; padding:12px; margin:10px 0; }
        .foot { text-align:center; color:var(--muted); font-size:11.5px; margin-top:16px; }

        /* ── Cascader de ubigeo (1 campo, popup 3 columnas) ── */
        .ubigeo-field { position:relative; }
        .ubigeo-display { border:1px solid var(--line); border-radius:10px; padding:11px 12px; cursor:pointer; background:#fff; color:var(--muted); font-size:15px; }
        .ubigeo-display.has-value { color:var(--ink); font-weight:500; }
        .ubigeo-pop { position:absolute; z-index:5000; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:0 12px 32px -8px rgba(15,23,42,.25); display:flex; overflow:hidden; }
        .ubigeo-col { flex:1; min-width:33%; max-height:240px; overflow-y:auto; border-right:1px solid #f1f5f9; }
        .ubigeo-col:last-child { border-right:none; }
        .ubigeo-item { padding:9px 10px; cursor:pointer; font-size:13px; border-bottom:1px solid #f8fafc; }
        .ubigeo-item:hover, .ubigeo-item.active { background:#eff6ff; color:var(--brand); font-weight:600; }
        .ubigeo-col:empty::before { content:'—'; display:block; text-align:center; color:#cbd5e1; padding:12px 0; font-size:12px; }
        @media (max-width:520px){ .ubigeo-pop{ overflow-x:auto; } .ubigeo-col{ min-width:130px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            <h1>📦 Registro de envío</h1>
            <p>Completa tus datos para preparar tu paquete.</p>
        </div>

        @if($sent)
            {{-- Confirmación con el código --}}
            <div class="ok">
                <div style="font-size:40px;">✅</div>
                @if(session('duplicate'))
                    <h2 style="font-size:17px;margin:6px 0;">Ya teníamos tu registro</h2>
                    <p style="color:var(--muted);font-size:13px;margin:0;">Hace un momento registraste un envío igual. Este es tu código:</p>
                @else
                    <h2 style="font-size:17px;margin:6px 0;">¡Registro recibido!</h2>
                    <p style="color:var(--muted);font-size:13px;margin:0;">Guarda tu código de envío:</p>
                @endif
                <div class="code">{{ $sent }}</div>
                <p style="color:var(--muted);font-size:12.5px;">Nuestro equipo preparará tu paquete y te enviará la guía cuando salga.</p>
                <a href="{{ route('shipments.public.form') }}" class="btn" style="display:inline-block;text-decoration:none;margin-top:6px;">Registrar otro envío</a>
            </div>
        @else
            @if($errors->any())
                <div class="alert alert-err">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('shipments.public.store') }}">
                @csrf

                <label class="req">Nombre completo</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required maxlength="160">

                <div class="row">
                    <div>
                        <label>DNI</label>
                        <input type="text" name="dni" value="{{ old('dni') }}" maxlength="15" inputmode="numeric">
                    </div>
                    <div>
                        <label class="req">Teléfono</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel">
                    </div>
                </div>

                <label class="req">Destino (ubigeo)</label>
                <div class="ubigeo-field" data-ubigeo-group="pub">
                    <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                    <input type="hidden" name="department_id" data-ub="department">
                    <input type="hidden" name="province_id"   data-ub="province">
                    <input type="hidden" name="district_id"   data-ub="district">
                    <div class="ubigeo-pop" hidden>
                        <div class="ubigeo-col" data-col="dep"></div>
                        <div class="ubigeo-col" data-col="prov"></div>
                        <div class="ubigeo-col" data-col="dist"></div>
                    </div>
                </div>

                <label>Dirección / referencia de destino</label>
                <input type="text" name="shipping_destination" value="{{ old('shipping_destination') }}" maxlength="255">

                <label>Agencia de envío (si la conoces)</label>
                <input type="text" name="shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Shalom, Olva…">

                <label>Contenido del paquete</label>
                <input type="text" name="package_content" value="{{ old('package_content') }}" maxlength="255" placeholder="Ej: 2 mantas, 1 juego de ollas">

                <div class="row">
                    <div style="max-width:120px;">
                        <label>N° de bultos</label>
                        <input type="number" name="package_count" value="{{ old('package_count', 1) }}" min="1" max="9999" inputmode="numeric">
                    </div>
                    <div>
                        <label>Información adicional</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Referencia, indicaciones…">
                    </div>
                </div>

                <label class="terms">
                    <input type="checkbox" name="accepted_terms" value="1" {{ old('accepted_terms') ? 'checked' : '' }} required>
                    <span>Acepto que mis datos se usen para gestionar y enviar mi paquete.</span>
                </label>

                <button type="submit" class="btn">Registrar mi envío</button>
            </form>
        @endif

        <div class="foot">Powered by ebaemy · Registro y Control de Envíos</div>
    </div>
</div>
<script>
    // Evitar doble envío por doble clic: deshabilitar el botón al enviar.
    var f = document.querySelector('form');
    if (f) f.addEventListener('submit', function () {
        var b = f.querySelector('button[type="submit"]');
        if (b) { b.disabled = true; b.textContent = 'Enviando…'; }
    });
</script>
@include('tenant.shipments.partials.ubigeo-cascader-js')
</body>
</html>
