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

                <label>DNI / RUC</label>
                <input type="text" name="dni" id="pub_dni" value="{{ old('dni') }}" class="js-doc-lookup"
                       data-target-name="pub_full_name" data-target-address="pub_shipping_destination" data-ubigeo-group="pub"
                       maxlength="11" inputmode="numeric" autocomplete="off" placeholder="8 dígitos (DNI) u 11 (RUC)">
                <small class="js-doc-status" style="display:block;font-size:12px;margin-top:2px;"></small>

                <label class="req">Nombre completo</label>
                <input type="text" name="full_name" id="pub_full_name" value="{{ old('full_name') }}" required maxlength="160">

                <label class="req">Teléfono</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" inputmode="tel">

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
                <input type="text" name="shipping_destination" id="pub_shipping_destination" value="{{ old('shipping_destination') }}" maxlength="255">

                <label>Agencia de envío (si la conoces)</label>
                <input type="text" name="shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Shalom, Olva…">

                <label>Información adicional</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255" placeholder="Referencia, indicaciones…">

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

    // Consulta DNI (RENIEC) / RUC (SUNAT) — igual que el panel del encargado.
    (function () {
        var LOOKUP = '{{ url("envio/consulta") }}';
        var inp = document.getElementById('pub_dni');
        if (!inp) return;
        var status = document.querySelector('.js-doc-status');
        var t = null;
        inp.addEventListener('input', function () {
            var num = (inp.value || '').replace(/\D+/g, '');
            clearTimeout(t);
            if (num.length !== 8 && num.length !== 11) { if (status) status.textContent = ''; return; }
            var kind = num.length === 8 ? 'dni' : 'ruc';
            if (status) { status.style.color = '#6b7280'; status.textContent = 'Consultando ' + kind.toUpperCase() + '…'; }
            t = setTimeout(function () {
                fetch(LOOKUP + '/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || res.success === false || !res.data) { if (status) { status.style.color = '#dc2626'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; } return; }
                        var d = res.data;
                        var full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                        var nameEl = document.getElementById('pub_full_name'); if (nameEl && full) nameEl.value = full;
                        if (d.address) { var a = document.getElementById('pub_shipping_destination'); if (a && !a.value) a.value = d.address; }
                        var loc = d.location_id;
                        var dep = (loc && loc[0]) || d.department_id || '', prov = (loc && loc[1]) || d.province_id || '', dist = (loc && loc[2]) || d.district_id || '';
                        if ((dep || dist) && window.__ubPreset) window.__ubPreset('pub', dep, prov, dist);
                        if (status) { status.style.color = '#16a34a'; status.textContent = '✓ ' + (full || 'encontrado'); }
                    })
                    .catch(function () { if (status) { status.style.color = '#dc2626'; status.textContent = 'No se pudo consultar.'; } });
            }, 450);
        });
    })();
</script>
@include('tenant.shipments.partials.ubigeo-cascader-js')
</body>
</html>
