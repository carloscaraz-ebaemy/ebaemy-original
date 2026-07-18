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
                <h2 style="font-size:17px;margin:6px 0;">¡Registro recibido!</h2>
                <p style="color:var(--muted);font-size:13px;margin:0;">Guarda tu código de envío:</p>
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

                <label class="req">Ciudad de destino</label>
                <input type="text" name="destination_city" value="{{ old('destination_city') }}" required maxlength="120" placeholder="Ej: Talara, Piura…">

                <label>Dirección / referencia de destino</label>
                <input type="text" name="shipping_destination" value="{{ old('shipping_destination') }}" maxlength="255">

                <label>Agencia de envío (si la conoces)</label>
                <input type="text" name="shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Shalom, Olva…">

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
</body>
</html>
