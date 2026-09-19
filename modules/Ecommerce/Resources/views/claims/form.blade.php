@extends('ecommerce::layouts.master')

@section('content')
<div class="lr-wrap">
    <div class="lr-container">

        @if(session('claim_code'))
            {{-- Confirmación: ocupa la pantalla entera. El código es lo único
                 que el consumidor necesita llevarse de aquí. --}}
            <div class="lr-done">
                <div class="lr-done__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <h1 class="lr-done__title">Tu reclamación ha sido registrada correctamente</h1>
                <p class="lr-done__code">Código de registro: <strong>{{ session('claim_code') }}</strong></p>

                @if(session('claim_mail_sent'))
                    <p class="lr-done__note">Te hemos enviado una copia a <strong>{{ session('claim_email') }}</strong>.</p>
                @else
                    <p class="lr-done__note lr-done__note--warn">
                        Tu registro quedó guardado, pero no pudimos enviarte la copia por correo en este momento.
                        Anota tu código: con él y tu correo puedes consultar el estado cuando quieras.
                    </p>
                @endif

                <p class="lr-done__note">
                    Te responderemos en un plazo máximo de <strong>15 días hábiles</strong>.
                </p>

                <div class="lr-done__actions">
                    <a href="{{ route('tenant.libro_reclamaciones.track') }}" class="lr-btn lr-btn--primary">Consultar mi registro</a>
                    <a href="{{ route('tenant.ecommerce.index') }}" class="lr-btn lr-btn--ghost">Volver a la tienda</a>
                </div>
            </div>
        @else

            <header class="lr-head">
                <span class="lr-head__eyebrow">Libro de Reclamaciones</span>
                <h1 class="lr-head__title">Registra tu reclamo o queja</h1>
                <p class="lr-head__provider">
                    <strong>{{ $provider['name'] }}</strong> · RUC {{ $provider['ruc'] }}
                    @if($provider['address'])<br>{{ $provider['address'] }}@endif
                </p>
            </header>

            {{-- Aviso oficial (Anexo II del Reglamento del Libro de Reclamaciones) --}}
            <div class="lr-notice">
                <strong>Aviso del Libro de Reclamaciones</strong>
                Conforme a lo establecido en el Código de Protección y Defensa del Consumidor,
                este establecimiento cuenta con un Libro de Reclamaciones a tu disposición.
            </div>

            @if($errors->any())
                <div class="lr-alert lr-alert--error" role="alert" tabindex="-1" id="lr-errors">
                    <strong>Revisa lo siguiente antes de enviar:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('tenant.libro_reclamaciones_enviar') }}"
                  enctype="multipart/form-data" id="lr-form" novalidate>
                @csrf
                <input type="hidden" name="submission_token" value="{{ $submission_token }}">

                {{-- ── 1. Tipo ────────────────────────────────────────────── --}}
                <section class="lr-card">
                    <h2 class="lr-card__title"><span class="lr-step">1</span> ¿Qué deseas registrar?</h2>

                    <div class="lr-choices">
                        <label class="lr-choice">
                            <input type="radio" name="type" value="reclamo" {{ old('type', 'reclamo') === 'reclamo' ? 'checked' : '' }}>
                            <span class="lr-choice__body">
                                <span class="lr-choice__name">Reclamo</span>
                                <span class="lr-choice__hint">Disconformidad relacionada a los productos o servicios.</span>
                            </span>
                        </label>
                        <label class="lr-choice">
                            <input type="radio" name="type" value="queja" {{ old('type') === 'queja' ? 'checked' : '' }}>
                            <span class="lr-choice__body">
                                <span class="lr-choice__name">Queja</span>
                                <span class="lr-choice__hint">Disconformidad no relacionada a los productos o servicios; o malestar o descontento respecto a la atención al público.</span>
                            </span>
                        </label>
                    </div>
                    @error('type')<p class="lr-error">{{ $message }}</p>@enderror
                </section>

                {{-- ── 2. Consumidor ──────────────────────────────────────── --}}
                <section class="lr-card">
                    <h2 class="lr-card__title"><span class="lr-step">2</span> Tus datos</h2>

                    <div class="lr-grid">
                        <div class="lr-field">
                            <label for="names">Nombres <span class="lr-req">*</span></label>
                            <input type="text" id="names" name="names" maxlength="100" required
                                   value="{{ old('names', $prefill['names']) }}" autocomplete="given-name">
                            @error('names')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field">
                            <label for="surnames">Apellidos <span class="lr-req">*</span></label>
                            <input type="text" id="surnames" name="surnames" maxlength="100" required
                                   value="{{ old('surnames', $prefill['surnames']) }}" autocomplete="family-name">
                            @error('surnames')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field lr-field--third">
                            <label for="document_type">Tipo de documento <span class="lr-req">*</span></label>
                            <select id="document_type" name="document_type" required>
                                @foreach(['DNI' => 'DNI', 'CE' => 'Carné de extranjería', 'PAS' => 'Pasaporte', 'RUC' => 'RUC'] as $k => $v)
                                    <option value="{{ $k }}" {{ old('document_type', 'DNI') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lr-field lr-field--twothirds">
                            <label for="document_number">N° de documento <span class="lr-req">*</span></label>
                            <input type="text" id="document_number" name="document_number" maxlength="20" required
                                   inputmode="numeric" value="{{ old('document_number') }}">
                            @error('document_number')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field">
                            <label for="email">Correo electrónico <span class="lr-req">*</span></label>
                            <input type="email" id="email" name="email" maxlength="120" required
                                   value="{{ old('email', $prefill['email']) }}" autocomplete="email">
                            <p class="lr-hint">Ahí te enviaremos la copia de tu hoja y nuestra respuesta.</p>
                            @error('email')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field">
                            <label for="phone">Teléfono</label>
                            <input type="tel" id="phone" name="phone" maxlength="30"
                                   value="{{ old('phone', $prefill['phone']) }}" autocomplete="tel">
                        </div>

                        <div class="lr-field lr-field--full">
                            <label for="address">Domicilio <span class="lr-req">*</span></label>
                            <input type="text" id="address" name="address" maxlength="255" required
                                   value="{{ old('address') }}" autocomplete="street-address">
                            @error('address')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field lr-field--third">
                            <label for="department_id">Departamento</label>
                            <select id="department_id" name="department_id"><option value="">Selecciona</option></select>
                        </div>
                        <div class="lr-field lr-field--third">
                            <label for="province_id">Provincia</label>
                            <select id="province_id" name="province_id" disabled><option value="">Selecciona</option></select>
                        </div>
                        <div class="lr-field lr-field--third">
                            <label for="district_id">Distrito</label>
                            <select id="district_id" name="district_id" disabled><option value="">Selecciona</option></select>
                        </div>

                        <div class="lr-field lr-field--full">
                            <label class="lr-check">
                                <input type="checkbox" name="is_minor" value="1" id="is_minor" {{ old('is_minor') ? 'checked' : '' }}>
                                <span>Soy menor de edad</span>
                            </label>
                        </div>

                        <div class="lr-field lr-field--full" id="lr-guardian" style="display:none">
                            <label for="guardian_name">Nombre del padre, madre o apoderado <span class="lr-req">*</span></label>
                            <input type="text" id="guardian_name" name="guardian_name" maxlength="200" value="{{ old('guardian_name') }}">
                            @error('guardian_name')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- ── 3. Bien o servicio ─────────────────────────────────── --}}
                <section class="lr-card">
                    <h2 class="lr-card__title"><span class="lr-step">3</span> Sobre tu compra</h2>

                    <div class="lr-grid">
                        <div class="lr-field lr-field--full">
                            <span class="lr-label">Se trata de <span class="lr-req">*</span></span>
                            <div class="lr-pills">
                                <label class="lr-pill">
                                    <input type="radio" name="item_type" value="bien" {{ old('item_type', 'bien') === 'bien' ? 'checked' : '' }}>
                                    <span>Un bien</span>
                                </label>
                                <label class="lr-pill">
                                    <input type="radio" name="item_type" value="servicio" {{ old('item_type') === 'servicio' ? 'checked' : '' }}>
                                    <span>Un servicio</span>
                                </label>
                            </div>
                        </div>

                        @if($orders->count())
                            <div class="lr-field lr-field--full">
                                <label for="order_id">Pedido relacionado</label>
                                <select id="order_id" name="order_id">
                                    <option value="">No corresponde a un pedido</option>
                                    @foreach($orders as $order)
                                        <option value="{{ $order['id'] }}"
                                            {{ (string) old('order_id', $selected_order) === (string) $order['id'] ? 'selected' : '' }}>
                                            N° {{ $order['reference'] }} — {{ $order['date'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="lr-hint">Sólo se muestran tus propios pedidos.</p>
                            </div>
                        @endif

                        <div class="lr-field lr-field--third">
                            <label for="purchase_date">Fecha de compra</label>
                            <input type="date" id="purchase_date" name="purchase_date" max="{{ date('Y-m-d') }}" value="{{ old('purchase_date') }}">
                            @error('purchase_date')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field lr-field--third">
                            <label for="currency">Moneda</label>
                            <select id="currency" name="currency">
                                <option value="PEN" {{ old('currency', 'PEN') === 'PEN' ? 'selected' : '' }}>S/ Soles</option>
                                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>$ Dólares</option>
                            </select>
                        </div>

                        <div class="lr-field lr-field--third">
                            <label for="amount">Monto reclamado</label>
                            <input type="number" id="amount" name="amount" min="0" step="0.01" value="{{ old('amount') }}">
                            @error('amount')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="lr-field lr-field--full">
                            <label for="product_description">Descripción del producto o servicio <span class="lr-req">*</span></label>
                            <textarea id="product_description" name="product_description" rows="3" maxlength="1000" required
                                      data-counter="c-product">{{ old('product_description') }}</textarea>
                            <p class="lr-count"><span id="c-product">0</span> / 1000</p>
                            @error('product_description')<p class="lr-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- ── 4. Detalle ─────────────────────────────────────────── --}}
                <section class="lr-card">
                    <h2 class="lr-card__title"><span class="lr-step">4</span> Cuéntanos qué pasó</h2>

                    <div class="lr-field">
                        <label for="detail">Detalle <span class="lr-req">*</span></label>
                        <textarea id="detail" name="detail" rows="6" maxlength="2000" required
                                  data-counter="c-detail"
                                  placeholder="Explícanos con tus palabras qué ocurrió.">{{ old('detail') }}</textarea>
                        <p class="lr-count"><span id="c-detail">0</span> / 2000</p>
                        @error('detail')<p class="lr-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="lr-field">
                        <label for="consumer_request">¿Qué solución esperas? <span class="lr-req">*</span></label>
                        <textarea id="consumer_request" name="consumer_request" rows="4" maxlength="1000" required
                                  data-counter="c-request"
                                  placeholder="Por ejemplo: cambio del producto, devolución del dinero, reprogramación de la entrega.">{{ old('consumer_request') }}</textarea>
                        <p class="lr-count"><span id="c-request">0</span> / 1000</p>
                        @error('consumer_request')<p class="lr-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="lr-field">
                        <label for="files">Adjuntar evidencia</label>
                        <input type="file" id="files" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        <p class="lr-hint">Hasta 5 archivos JPG, PNG o PDF de 5 MB cada uno.</p>
                        @error('files.0')<p class="lr-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                {{-- ── 5. Envío ───────────────────────────────────────────── --}}
                <section class="lr-card">
                    <label class="lr-check lr-check--terms">
                        <input type="checkbox" name="accept_terms" value="1" required {{ old('accept_terms') ? 'checked' : '' }}>
                        <span>
                            Autorizo a <strong>{{ $provider['name'] }}</strong> a registrar, almacenar y utilizar mis datos
                            personales para atender este registro, conforme a la
                            <a href="{{ route('tenant.politica_privacidad') }}" target="_blank" rel="noopener">política de privacidad</a>.
                        </span>
                    </label>
                    @error('accept_terms')<p class="lr-error">{{ $message }}</p>@enderror

                    <p class="lr-legal">
                        La formulación del reclamo no impide acudir a otras vías de solución de controversias
                        ni es requisito previo para interponer una denuncia ante el INDECOPI.
                        El proveedor debe dar respuesta en un plazo no mayor a <strong>quince (15) días hábiles improrrogables</strong>.
                    </p>

                    <button type="submit" class="lr-btn lr-btn--primary lr-btn--block" id="lr-submit">
                        Registrar mi {{ old('type', 'reclamo') === 'queja' ? 'queja' : 'reclamo' }}
                    </button>

                    <p class="lr-track">
                        ¿Ya registraste uno? <a href="{{ route('tenant.libro_reclamaciones.track') }}">Consulta su estado</a>.
                    </p>
                </section>
            </form>
        @endif
    </div>
</div>

<style>
    .lr-wrap { background:#f5f6f8; padding:96px 0 48px; }
    .lr-container { max-width:840px; margin:0 auto; padding:0 16px; }

    .lr-head { margin-bottom:18px; }
    .lr-head__eyebrow { display:inline-block; font-size:12px; letter-spacing:.09em; text-transform:uppercase; color:#5b6472; font-weight:700; }
    .lr-head__title { font-size:clamp(24px, 5vw, 32px); margin:6px 0 10px; color:#141821; font-weight:700; }
    .lr-head__provider { font-size:14px; color:#5b6472; line-height:1.6; margin:0; }

    .lr-notice { background:#fff8e6; border:1px solid #f2d68a; border-radius:10px; padding:14px 16px; font-size:13.5px; line-height:1.6; color:#5c4708; margin-bottom:18px; }
    .lr-notice strong { display:block; margin-bottom:3px; }

    .lr-alert { border-radius:10px; padding:14px 16px; font-size:14px; line-height:1.6; margin-bottom:18px; }
    .lr-alert--error { background:#fdecec; border:1px solid #f3b7b7; color:#8e1b1b; }
    .lr-alert ul { margin:8px 0 0; padding-left:18px; }

    .lr-card { background:#fff; border:1px solid #e6e8ec; border-radius:14px; padding:20px; margin-bottom:16px; }
    .lr-card__title { display:flex; align-items:center; gap:10px; font-size:17px; font-weight:700; color:#141821; margin:0 0 16px; }
    .lr-step { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:#141821; color:#fff; font-size:13px; font-weight:700; flex:none; }

    .lr-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:14px; }
    .lr-field { grid-column:span 3; display:flex; flex-direction:column; }
    .lr-field--full { grid-column:span 6; }
    .lr-field--third { grid-column:span 2; }
    .lr-field--twothirds { grid-column:span 4; }

    .lr-field label, .lr-label { font-size:13.5px; font-weight:600; color:#39414f; margin-bottom:6px; display:block; }
    .lr-req { color:#c0392b; }
    .lr-field input[type=text], .lr-field input[type=email], .lr-field input[type=tel],
    .lr-field input[type=date], .lr-field input[type=number], .lr-field select, .lr-field textarea,
    .lr-card input[type=text], .lr-card input[type=email] {
        width:100%; border:1px solid #d3d7de; border-radius:9px; padding:11px 12px; font-size:15px;
        background:#fff; color:#141821; font-family:inherit; min-height:44px;
    }
    .lr-field textarea { resize:vertical; line-height:1.55; }
    .lr-field input:focus, .lr-field select:focus, .lr-field textarea:focus {
        outline:none; border-color:#1f5eff; box-shadow:0 0 0 3px rgba(31,94,255,.14);
    }
    .lr-field input[aria-invalid=true], .lr-field textarea[aria-invalid=true] { border-color:#d94141; }

    .lr-hint { font-size:12.5px; color:#7a8393; margin:6px 0 0; }
    .lr-count { font-size:12px; color:#8b93a1; margin:5px 0 0; text-align:right; }
    .lr-error { font-size:13px; color:#c0392b; margin:6px 0 0; font-weight:500; }

    .lr-choices { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .lr-choice { display:flex; gap:10px; border:1.5px solid #dfe3e9; border-radius:12px; padding:14px; cursor:pointer; background:#fff; }
    .lr-choice input { margin-top:3px; flex:none; }
    .lr-choice__name { display:block; font-weight:700; font-size:15px; color:#141821; margin-bottom:3px; }
    .lr-choice__hint { display:block; font-size:12.5px; color:#6b7382; line-height:1.5; }
    .lr-choice:has(input:checked) { border-color:#1f5eff; background:#f5f8ff; }

    .lr-pills { display:flex; gap:10px; flex-wrap:wrap; }
    .lr-pill { display:inline-flex; align-items:center; gap:8px; border:1.5px solid #dfe3e9; border-radius:999px; padding:10px 16px; cursor:pointer; font-size:14.5px; background:#fff; min-height:44px; }
    .lr-pill:has(input:checked) { border-color:#1f5eff; background:#f5f8ff; font-weight:600; }

    .lr-check { display:flex; gap:10px; align-items:flex-start; font-size:14px; line-height:1.6; color:#39414f; cursor:pointer; }
    .lr-check input { margin-top:3px; flex:none; width:18px; height:18px; }
    .lr-check--terms { margin-bottom:14px; }

    .lr-legal { font-size:12.5px; color:#6b7382; line-height:1.6; background:#f7f8fa; border-radius:9px; padding:12px 14px; margin:0 0 16px; }

    .lr-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:10px; padding:13px 22px; font-size:15.5px; font-weight:600; text-decoration:none; border:1px solid transparent; cursor:pointer; min-height:48px; }
    .lr-btn--primary { background:#1f5eff; color:#fff; }
    .lr-btn--primary:hover { background:#1a4fd8; color:#fff; }
    .lr-btn--primary[disabled] { opacity:.6; cursor:progress; }
    .lr-btn--ghost { background:#fff; color:#39414f; border-color:#d3d7de; }
    .lr-btn--block { width:100%; }

    .lr-track { font-size:13.5px; color:#6b7382; text-align:center; margin:14px 0 0; }

    .lr-done { background:#fff; border:1px solid #e6e8ec; border-radius:16px; padding:34px 24px; text-align:center; }
    .lr-done__icon { width:68px; height:68px; border-radius:50%; background:#e7f6ee; color:#0f8a5f; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
    .lr-done__title { font-size:clamp(20px, 4.4vw, 26px); font-weight:700; color:#141821; margin:0 0 12px; }
    .lr-done__code { font-size:18px; color:#141821; margin:0 0 16px; }
    .lr-done__code strong { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing:.02em; }
    .lr-done__note { font-size:14.5px; color:#5b6472; line-height:1.6; margin:0 0 8px; }
    .lr-done__note--warn { color:#8a6100; background:#fff8e6; border-radius:9px; padding:10px 14px; }
    .lr-done__actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:20px; }

    @media (max-width: 680px) {
        .lr-wrap { padding:80px 0 32px; }
        .lr-card { padding:16px; border-radius:12px; }
        .lr-grid { grid-template-columns:repeat(2, 1fr); }
        .lr-field, .lr-field--third, .lr-field--twothirds, .lr-field--full { grid-column:span 2; }
        .lr-choices { grid-template-columns:1fr; }
    }
</style>

<script>
(function () {
    var form = document.getElementById('lr-form');
    if (!form) { return; }

    /* Contadores de caracteres */
    document.querySelectorAll('[data-counter]').forEach(function (el) {
        var out = document.getElementById(el.dataset.counter);
        if (!out) { return; }
        var paint = function () { out.textContent = el.value.length; };
        paint();
        el.addEventListener('input', paint);
    });

    /* El apoderado sólo se pide si el consumidor es menor de edad */
    var minor = document.getElementById('is_minor');
    var guardian = document.getElementById('lr-guardian');
    var syncGuardian = function () {
        guardian.style.display = minor.checked ? '' : 'none';
        document.getElementById('guardian_name').required = minor.checked;
    };
    minor.addEventListener('change', syncGuardian);
    syncGuardian();

    /* El botón refleja lo que el consumidor eligió registrar */
    var submit = document.getElementById('lr-submit');
    form.querySelectorAll('input[name=type]').forEach(function (r) {
        r.addEventListener('change', function () {
            submit.textContent = 'Registrar mi ' + (r.value === 'queja' ? 'queja' : 'reclamo');
        });
    });

    /* Ubigeo en cascada, reutilizando los endpoints públicos de la tienda */
    var dep = document.getElementById('department_id');
    var prov = document.getElementById('province_id');
    var dist = document.getElementById('district_id');

    var fill = function (select, rows, placeholder) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        (rows || []).forEach(function (row) {
            var opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = row.description || row.name;
            select.appendChild(opt);
        });
    };

    fetch('/ecommerce/ubigeo/departments', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (rows) { fill(dep, rows.data || rows, 'Selecciona'); })
        .catch(function () {});

    dep.addEventListener('change', function () {
        prov.disabled = true; dist.disabled = true;
        fill(prov, [], 'Selecciona'); fill(dist, [], 'Selecciona');
        if (!dep.value) { return; }
        fetch('/ecommerce/ubigeo/provinces/' + dep.value, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (rows) { fill(prov, rows.data || rows, 'Selecciona'); prov.disabled = false; })
            .catch(function () {});
    });

    prov.addEventListener('change', function () {
        dist.disabled = true;
        fill(dist, [], 'Selecciona');
        if (!prov.value) { return; }
        fetch('/ecommerce/ubigeo/districts/' + prov.value, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (rows) { fill(dist, rows.data || rows, 'Selecciona'); dist.disabled = false; })
            .catch(function () {});
    });

    /* Doble envío: el token del formulario ya lo impide en el servidor, esto
       sólo evita que el consumidor crea que no pasó nada y vuelva a pulsar. */
    var sent = false;
    form.addEventListener('submit', function (e) {
        if (sent) { e.preventDefault(); return; }

        var firstInvalid = null;
        form.querySelectorAll('[required]').forEach(function (el) {
            if (el.offsetParent === null && el.type !== 'hidden') { return; }
            var bad = el.type === 'checkbox' ? !el.checked : !String(el.value).trim();
            el.setAttribute('aria-invalid', bad ? 'true' : 'false');
            if (bad && !firstInvalid) { firstInvalid = el; }
        });

        if (firstInvalid) {
            e.preventDefault();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
            return;
        }

        sent = true;
        submit.disabled = true;
        submit.textContent = 'Registrando...';
    });

    var errors = document.getElementById('lr-errors');
    if (errors) { errors.focus(); }
})();
</script>
@endsection
