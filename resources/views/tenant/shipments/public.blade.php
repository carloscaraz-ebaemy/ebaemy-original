<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de envío — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#2563eb; --brand-d:#1d4ed8; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; --ok:#16a34a; --bg:#f1f5f9; --moto:#7c3aed; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 18px 14px 48px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:20px; padding:22px; box-shadow:0 10px 40px -16px rgba(15,23,42,.2); }
        .head { text-align:center; margin-bottom:16px; }
        .brand { font-size:13px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; }
        .head h1 { font-size:20px; margin:6px 0 2px; }
        .head p { margin:0; color:var(--muted); font-size:13px; }

        /* ── Stepper ── */
        .stepper { display:flex; align-items:center; justify-content:space-between; margin:18px 2px 22px; }
        .st { display:flex; flex-direction:column; align-items:center; gap:6px; flex:0 0 auto; width:70px; text-align:center; }
        .st .dot { width:32px; height:32px; border-radius:999px; background:#e2e8f0; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; transition:.25s; }
        .st .t { font-size:11px; color:#94a3b8; font-weight:600; transition:.25s; }
        .st.active .dot { background:var(--brand); color:#fff; box-shadow:0 0 0 5px rgba(37,99,235,.15); }
        .st.active .t { color:var(--ink); }
        .st.done .dot { background:var(--ok); color:#fff; }
        .st.done .t { color:var(--ink); }
        .st-line { flex:1; height:3px; background:#e2e8f0; border-radius:2px; margin:0 -4px; align-self:flex-start; margin-top:15px; }
        .st-line.done { background:var(--ok); }

        label { display:block; font-size:13px; font-weight:600; margin:14px 0 5px; }
        .step > label:first-of-type { margin-top:0; }
        .req::after { content:" *"; color:#dc2626; }
        input[type=text], input[type=tel], select, textarea {
            width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:12px; font-size:15px; background:#fff; transition:.15s;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .hint { font-size:12px; color:var(--muted); margin-top:3px; display:block; }

        .btn { width:100%; padding:14px; border:none; border-radius:14px; background:var(--brand); color:#fff; font-size:16px; font-weight:700; cursor:pointer; transition:.15s; }
        .btn:hover { background:var(--brand-d); } .btn:active { transform:scale(.99); }
        .btn-ghost { background:#f1f5f9; color:#334155; } .btn-ghost:hover { background:#e2e8f0; }
        .row-btns { display:flex; gap:10px; margin-top:20px; }
        .row-btns .btn { flex:1; }

        /* ── Paso 0: tarjetas de tipo de entrega ── */
        .dtype { display:flex; flex-direction:column; gap:14px; margin-top:4px; }
        .dcard { display:flex; align-items:center; gap:14px; text-align:left; width:100%; border:2px solid var(--line); background:#fff; border-radius:18px; padding:18px; cursor:pointer; transition:.18s; }
        .dcard:hover { border-color:var(--brand); transform:translateY(-2px); box-shadow:0 12px 28px -18px rgba(37,99,235,.5); }
        .dcard .ic { flex:0 0 auto; width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:28px; }
        .dcard.moto .ic { background:#f3e8ff; } .dcard.ag .ic { background:#dbeafe; }
        .dcard .tx { flex:1; }
        .dcard .tx b { display:block; font-size:16px; }
        .dcard .tx span { display:block; font-size:12.5px; color:var(--muted); margin-top:2px; line-height:1.35; }
        .dcard .go { flex:0 0 auto; font-size:13px; font-weight:800; color:var(--brand); border:1.5px solid var(--brand); border-radius:10px; padding:8px 12px; }
        .dcard.moto .go { color:var(--moto); border-color:var(--moto); }

        .tag { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; margin-bottom:6px; }
        .tag.moto { background:#f3e8ff; color:var(--moto); } .tag.ag { background:#dbeafe; color:var(--brand-d); }

        /* ── Cliente encontrado ── */
        .found { background:#eff6ff; border:1px solid #bfdbfe; border-radius:14px; padding:12px 14px; margin-top:10px; }
        .found .t { font-size:12px; color:var(--brand); font-weight:700; text-transform:uppercase; }
        .found .n { font-size:16px; font-weight:700; margin:2px 0 8px; }
        .found .acts { display:flex; gap:8px; }
        .found .acts button { flex:1; padding:9px; border-radius:10px; border:none; font-weight:700; font-size:13px; cursor:pointer; }
        .found .use { background:var(--brand); color:#fff; } .found .new { background:#e2e8f0; color:#334155; }

        /* ── Google Maps ── */
        .map-search { position:relative; }
        #shipMap { width:100%; height:260px; border-radius:14px; border:1.5px solid var(--line); margin-top:10px; background:#e5e7eb; }
        .map-picked { margin-top:10px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; font-size:13.5px; display:none; }
        .map-picked.show { display:block; }
        .map-picked .a { font-weight:700; color:#15803d; word-break:break-word; }
        .map-picked .c { color:#166534; font-size:12.5px; margin-top:2px; }
        .map-note { font-size:12px; color:var(--muted); margin-top:6px; }
        .map-off { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:12px 14px; font-size:13px; color:#92400e; margin-top:8px; }

        /* ── Confirmación ── */
        .conf { border:1.5px solid var(--line); border-radius:16px; overflow:hidden; }
        .conf .h { background:#f8fafc; padding:10px 14px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brand); border-bottom:1px solid var(--line); }
        .conf .rows { padding:6px 14px; }
        .conf .r { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed #eef2f7; font-size:14px; }
        .conf .r:last-child { border-bottom:none; }
        .conf .r .k { color:var(--muted); flex:0 0 40%; }
        .conf .r .v { text-align:right; font-weight:600; word-break:break-word; }
        .conf .r[hidden] { display:none; }
        .terms-box { margin-top:16px; }
        .chk { display:flex; align-items:flex-start; gap:10px; background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:12px; font-size:14px; font-weight:600; }
        .chk input { margin-top:3px; width:18px; height:18px; }
        .cond { font-size:12px; color:var(--muted); margin-top:12px; line-height:1.5; background:#f8fafc; border:1px solid var(--line); border-radius:12px; padding:12px 14px; }
        .cond strong { color:#334155; }
        .cond ul { margin:6px 0 0; padding-left:18px; }

        /* ── Éxito ── */
        .ok-wrap { text-align:center; padding:6px 0; }
        .ok-check { width:78px; height:78px; border-radius:999px; background:var(--ok); color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; margin:6px auto 10px; box-shadow:0 10px 30px -8px rgba(22,163,74,.5); animation:pop .35s ease; }
        @keyframes pop { from{ transform:scale(.6); opacity:0;} to{ transform:scale(1); opacity:1;} }
        .ok-wrap h2 { font-size:20px; margin:4px 0; }
        .ok-code { font-size:24px; font-weight:800; letter-spacing:1px; color:var(--brand); background:#eff6ff; border:1px dashed var(--brand); border-radius:14px; padding:14px; margin:12px 0; }
        .ok-btns { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
        .ok-btns a { text-decoration:none; display:block; }
        .btn-wa { background:#22c55e; } .btn-wa:hover { background:#16a34a; }

        .foot { text-align:center; color:var(--muted); font-size:11.5px; margin-top:18px; }
        .step[hidden], [hidden] { display:none; }
        .fade-in { animation:fade .25s ease; }
        @keyframes fade { from{ opacity:0; transform:translateY(6px);} to{ opacity:1; transform:none;} }
        .alert-err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:11px 13px; border-radius:12px; font-size:13.5px; margin-bottom:14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            @if(!empty($company->logo))
                <img src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="{{ $company->trade_name ?? $company->name ?? '' }}" style="max-height:56px;max-width:70%;margin:0 auto 8px;display:block;object-fit:contain;">
            @else
                <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            @endif
            <h1>📦 Registro de envío</h1>
            <p>Elige cómo deseas recibir tu pedido.</p>
        </div>

        {{-- ── Barra de progreso ── --}}
        <div class="stepper" id="stepper">
            <div class="st {{ $sent ? 'done' : 'active' }}" data-n="1"><div class="dot">1</div><div class="t">Tipo</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="2"><div class="dot">2</div><div class="t">Datos</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="3"><div class="dot">3</div><div class="t">Confirmar</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'active' : '' }}" data-n="4"><div class="dot">4</div><div class="t">Listo</div></div>
        </div>

        @if($sent)
            {{-- ══════════ PASO 4: Éxito ══════════ --}}
            @php $isMoto = ($sentType === \App\Models\Tenant\ShippingRequest::DELIVERY_DOMICILIO); @endphp
            <div class="ok-wrap fade-in">
                <div class="ok-check">✓</div>
                <h2>Registro realizado correctamente</h2>
                <p style="color:var(--muted);font-size:14px;margin:2px 0;">
                    @if($isMoto)
                        Hemos recibido tus datos. Tu pedido será entregado por nuestro <b>motorizado</b> hasta tu dirección.
                    @else
                        Hemos recibido tus datos. Tu pedido será enviado mediante <b>agencia de transporte</b>. Cuando sea despachado recibirás la guía de envío.
                    @endif
                </p>
                <div class="ok-code">{{ $sent }}</div>
                <div class="ok-btns">
                    <a href="{{ route('shipments.public.tracking', ['code' => $sent]) }}"><button type="button" class="btn">🔎 Ver seguimiento</button></a>
                    @php
                        $waText = rawurlencode("Registré mi envío en " . ($company->trade_name ?? $company->name ?? 'la tienda') . ". Código: {$sent}. Seguimiento: " . route('shipments.public.tracking', ['code' => $sent]));
                    @endphp
                    <a href="https://wa.me/?text={{ $waText }}" target="_blank"><button type="button" class="btn btn-wa">💬 Compartir por WhatsApp</button></a>
                    <a href="{{ route('shipments.public.form') }}"><button type="button" class="btn btn-ghost">Registrar otro envío</button></a>
                </div>
            </div>
        @else
            @if($errors->any())
                <div class="alert-err"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('shipments.public.store') }}" id="shipForm">
                @csrf
                <input type="hidden" name="delivery_type" id="delivery_type" value="{{ old('delivery_type') }}">

                {{-- ══════════ PASO 0: Tipo de entrega ══════════ --}}
                <div class="step fade-in" data-step="0">
                    <div class="dtype">
                        <button type="button" class="dcard moto" data-type="domicilio">
                            <div class="ic">🏍️</div>
                            <div class="tx"><b>Entrega a domicilio</b><span>Un motorizado lleva tu pedido hasta tu dirección. Ubicación por mapa.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard ag" data-type="agencia">
                            <div class="ic">📦</div>
                            <div class="tx"><b>Envío por agencia</b><span>Enviamos tu pedido por agencia de transporte a tu provincia.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 1: Datos ══════════ --}}
                <div class="step" data-step="1" hidden>
                    <span class="tag moto" id="tag-moto" hidden>🏍️ Entrega a domicilio</span>
                    <span class="tag ag" id="tag-ag" hidden>📦 Envío por agencia</span>

                    <label>DNI / RUC</label>
                    <input type="text" name="dni" id="pub_dni" value="{{ old('dni') }}"
                           maxlength="11" inputmode="numeric" autocomplete="off" placeholder="8 dígitos (DNI) u 11 (RUC)">
                    <small class="js-doc-status hint"></small>
                    <div id="clientFound" class="found" hidden>
                        <div class="t">Cliente encontrado</div>
                        <div class="n" id="cf_name">—</div>
                        <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">¿Deseas utilizar esta información?</div>
                        <div class="acts">
                            <button type="button" class="use" id="cf_use">Usar datos</button>
                            <button type="button" class="new" id="cf_new">Ingresar nuevos</button>
                        </div>
                    </div>

                    <label class="req">Nombre completo</label>
                    <input type="text" name="full_name" id="pub_full_name" value="{{ old('full_name') }}" required maxlength="160">

                    <label class="req">Celular (WhatsApp)</label>
                    <input type="tel" name="phone" id="pub_phone" value="{{ old('phone') }}" required maxlength="9" inputmode="numeric" placeholder="999 999 999" class="js-phone-pe">
                    <small class="js-phone-err" style="color:#dc2626;display:block;font-size:12px;margin-top:2px;"></small>

                    {{-- ─────── Rama DOMICILIO (Google Maps) ─────── --}}
                    <div class="branch-domicilio" hidden>
                        <label class="req">Buscar dirección en el mapa</label>
                        <div class="map-search">
                            <input type="text" id="mapSearch" placeholder="Ej. Av. Arequipa 1234, Miraflores" autocomplete="off">
                        </div>
                        @if(!empty($mapsKey))
                            <div id="shipMap"></div>
                            <div class="map-note">Arrastra el marcador para ajustar la ubicación exacta.</div>
                            <div class="map-picked" id="mapPicked">
                                <div class="a" id="mp_addr">—</div>
                                <div class="c" id="mp_city">—</div>
                            </div>
                        @else
                            <div class="map-off">⚠️ El mapa no está disponible por ahora. Escribe tu dirección completa en el campo de abajo y una referencia clara.</div>
                        @endif

                        <label class="req">Dirección de entrega</label>
                        <input type="text" name="shipping_destination" id="pub_addr_domicilio" value="{{ old('shipping_destination') }}" maxlength="500" placeholder="Av./Jr./Calle, número, urbanización">

                        {{-- Campos ocultos que llena Google Maps --}}
                        <input type="hidden" name="latitude" id="pub_lat" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="pub_lng" value="{{ old('longitude') }}">
                        <input type="hidden" name="google_place_id" id="pub_place_id" value="{{ old('google_place_id') }}">
                        <input type="hidden" name="formatted_address" id="pub_formatted" value="{{ old('formatted_address') }}">
                        <input type="hidden" name="google_maps_url" id="pub_maps_url" value="{{ old('google_maps_url') }}">
                        <input type="hidden" name="destination_city" id="pub_city_domicilio" value="{{ old('destination_city') }}">
                        <input type="hidden" name="distance_km" id="pub_dist_km" value="{{ old('distance_km') }}">
                        <input type="hidden" name="distance_text" id="pub_dist_text" value="{{ old('distance_text') }}">
                        <input type="hidden" name="duration_text" id="pub_dur_text" value="{{ old('duration_text') }}">

                        <label>Referencia</label>
                        <input type="text" name="reference" id="pub_reference_dom" value="{{ old('reference') }}" maxlength="255" placeholder="Casa blanca, frente al parque, portón negro…">
                    </div>

                    {{-- ─────── Rama AGENCIA (ubigeo) ─────── --}}
                    <div class="branch-agencia" hidden>
                        <label class="req">Destino (Departamento / Provincia / Distrito)</label>
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

                        <label>Dirección</label>
                        <input type="text" name="shipping_destination" id="pub_addr_agencia" value="{{ old('shipping_destination') }}" maxlength="255" placeholder="Av./Jr./Calle y número">

                        <label>Referencia</label>
                        <input type="text" name="reference" id="pub_reference_ag" value="{{ old('reference') }}" maxlength="255" placeholder="Frente a…, cerca de…">

                        <label>Agencia de transporte</label>
                        <div class="agency-field">
                            <select class="agency-select">
                                <option value="">— Selecciona —</option>
                                @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                                <option value="__otra__">Otra…</option>
                            </select>
                            <input type="text" class="agency-input" name="shipping_agency" id="pub_shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Nombre de la agencia" style="display:none;margin-top:8px;">
                        </div>
                    </div>

                    <label>Observaciones</label>
                    <input type="text" name="notes" id="pub_notes" value="{{ old('notes') }}" maxlength="255" placeholder="Indicaciones adicionales">

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep0">← Volver</button>
                        <button type="button" class="btn" id="toStep2">Continuar →</button>
                    </div>
                </div>

                {{-- ══════════ PASO 2: Confirmación ══════════ --}}
                <div class="step" data-step="2" hidden>
                    <div class="conf">
                        <div class="h" id="c_type_h">Resumen</div>
                        <div class="rows">
                            <div class="r"><span class="k">Tipo de entrega</span><span class="v" id="c_type">—</span></div>
                            <div class="r"><span class="k">Nombre</span><span class="v" id="c_name">—</span></div>
                            <div class="r"><span class="k">Documento</span><span class="v" id="c_doc">—</span></div>
                            <div class="r"><span class="k">Celular</span><span class="v" id="c_phone">—</span></div>
                            <div class="r" id="r_ubigeo"><span class="k">Ubigeo</span><span class="v" id="c_ubigeo">—</span></div>
                            <div class="r"><span class="k">Dirección</span><span class="v" id="c_dir">—</span></div>
                            <div class="r"><span class="k">Referencia</span><span class="v" id="c_ref">—</span></div>
                            <div class="r" id="r_ag"><span class="k">Agencia</span><span class="v" id="c_ag">—</span></div>
                            <div class="r" id="r_coords"><span class="k">Ubicación GPS</span><span class="v" id="c_coords">—</span></div>
                            <div class="r"><span class="k">Observaciones</span><span class="v" id="c_obs">—</span></div>
                        </div>
                    </div>

                    <div class="terms-box">
                        <label class="chk">
                            <input type="checkbox" name="accepted_terms" id="pub_terms" value="1" required>
                            <span>Confirmo que todos los datos ingresados son correctos.</span>
                        </label>
                        <div class="cond">
                            Autorizo el uso de mis datos únicamente para gestionar el envío de mi pedido.<br><br>
                            Entiendo que el tiempo estimado de despacho es de <strong>2 a 4 días hábiles</strong>. Este tiempo puede variar dependiendo de:
                            <ul>
                                <li>Disponibilidad del producto.</li>
                                <li>Disponibilidad de materiales para embalaje.</li>
                                <li>Horarios de despacho.</li>
                                <li>Agencia de transporte o disponibilidad del motorizado.</li>
                                <li>Eventos externos que puedan retrasar la entrega.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep1">← Volver</button>
                        <button type="submit" class="btn" id="confirmBtn">Confirmar registro</button>
                    </div>
                </div>
            </form>

            <div style="text-align:center;margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
                <a href="{{ route('shipments.public.tracking') }}" style="color:var(--brand);font-size:14px;text-decoration:none;font-weight:600;">🔎 ¿Ya te registraste? Consulta el estado de tu envío</a>
            </div>
        @endif

        <div class="foot">Powered by ebaemy · Registro y Control de Envíos</div>
    </div>
</div>

@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')

<script>
(function () {
    var DTYPE = { DOM: 'domicilio', AG: 'agencia' };
    var form = document.getElementById('shipForm');
    if (!form) return;
    var dtInput = document.getElementById('delivery_type');
    var stepper = document.getElementById('stepper');
    var step0 = document.querySelector('.step[data-step="0"]');
    var step1 = document.querySelector('.step[data-step="1"]');
    var step2 = document.querySelector('.step[data-step="2"]');
    var branchDom = document.querySelector('.branch-domicilio');
    var branchAg = document.querySelector('.branch-agencia');
    var selectedType = null;

    function setStep(n) {
        var sts = stepper.querySelectorAll('.st'), lines = stepper.querySelectorAll('.st-line');
        sts.forEach(function (s) {
            var k = parseInt(s.getAttribute('data-n'), 10);
            s.classList.toggle('active', k === n);
            s.classList.toggle('done', k < n);
        });
        lines.forEach(function (l, i) { l.classList.toggle('done', (i + 1) < n); });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function show(el){ if(el) el.hidden=false; } function hide(el){ if(el) el.hidden=true; }
    function txt(id){ var el=document.getElementById(id); return el?(el.value||'').trim():''; }

    // ── Paso 0: elegir tipo ──
    document.querySelectorAll('.dcard').forEach(function (c) {
        c.addEventListener('click', function () {
            selectedType = c.getAttribute('data-type');
            dtInput.value = selectedType;
            var isDom = selectedType === DTYPE.DOM;
            branchDom.hidden = !isDom; branchAg.hidden = isDom;
            document.getElementById('tag-moto').hidden = !isDom;
            document.getElementById('tag-ag').hidden = isDom;
            // Evitar que campos ocultos "required" bloqueen el submit del navegador.
            syncRequired(isDom);
            hide(step0); show(step1); step1.classList.add('fade-in');
            setStep(2);
            if (isDom && window.__initShipMapIfReady) window.__initShipMapIfReady();
        });
    });

    // Marca required solo en la rama visible (los name duplicados no molestan
    // porque solo enviamos la rama activa; igual desactivamos la oculta).
    function syncRequired(isDom) {
        // Desactivar los inputs de la rama oculta para que NO se envíen.
        branchDom.querySelectorAll('input,select,textarea').forEach(function(el){ el.disabled = !isDom; });
        branchAg.querySelectorAll('input,select,textarea').forEach(function(el){ el.disabled = isDom; });
    }

    var back0 = document.getElementById('backStep0');
    if (back0) back0.addEventListener('click', function () { hide(step1); show(step0); setStep(1); });

    // ── Consulta DNI/RUC (RENIEC/SUNAT) + cliente existente ──
    var LOOKUP = '{{ url("envio/consulta") }}', CLIENT = '{{ url("envio/cliente") }}';
    var dni = document.getElementById('pub_dni');
    var found = document.getElementById('clientFound');
    var lastClient = null, t = null;

    function fillFromClient(d) {
        if (!d) return;
        var set = function (id, v) { var el = document.getElementById(id); if (el && v) el.value = v; };
        set('pub_full_name', d.full_name); set('pub_phone', d.phone);
        // Dirección/referencia según la rama activa.
        if (selectedType === DTYPE.DOM) { set('pub_addr_domicilio', d.shipping_destination); set('pub_reference_dom', d.reference); }
        else {
            set('pub_addr_agencia', d.shipping_destination); set('pub_reference_ag', d.reference);
            set('pub_shipping_agency', d.shipping_agency);
            if (window.__syncAgency) window.__syncAgency();
            if (window.__ubPreset && (d.department_id || d.district_id)) window.__ubPreset('pub', d.department_id, d.province_id, d.district_id);
        }
    }

    if (dni) dni.addEventListener('input', function () {
        var num = (dni.value || '').replace(/\D+/g, '');
        var status = document.querySelector('.js-doc-status');
        if (found) found.hidden = true;
        clearTimeout(t);
        if (num.length !== 8 && num.length !== 11) { if (status) status.textContent = ''; return; }
        var kind = num.length === 8 ? 'dni' : 'ruc';
        if (status) { status.style.color = '#6b7280'; status.textContent = 'Consultando ' + kind.toUpperCase() + '…'; }
        t = setTimeout(function () {
            fetch(CLIENT + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.found) {
                        lastClient = res.data;
                        document.getElementById('cf_name').textContent = res.name || '—';
                        if (found) found.hidden = false;
                    }
                }).catch(function () {});
            fetch(LOOKUP + '/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false || !res.data) { if (status) { status.style.color = '#dc2626'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; } return; }
                    var d = res.data, full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                    var nameEl = document.getElementById('pub_full_name'); if (nameEl && full && !nameEl.value) nameEl.value = full;
                    if (d.address) {
                        var a = selectedType === DTYPE.DOM ? document.getElementById('pub_addr_domicilio') : document.getElementById('pub_addr_agencia');
                        if (a && !a.value) a.value = d.address;
                    }
                    if (selectedType === DTYPE.AG) {
                        var loc = d.location_id, dep = (loc && loc[0]) || d.department_id || '', prov = (loc && loc[1]) || d.province_id || '', dist = (loc && loc[2]) || d.district_id || '';
                        if ((dep || dist) && window.__ubPreset) window.__ubPreset('pub', dep, prov, dist);
                    }
                    if (status) { status.style.color = '#16a34a'; status.textContent = '✓ ' + (full || 'encontrado'); }
                }).catch(function () { if (status) { status.style.color = '#dc2626'; status.textContent = 'No se pudo consultar.'; } });
        }, 450);
    });

    var cfUse = document.getElementById('cf_use'), cfNew = document.getElementById('cf_new');
    if (cfUse) cfUse.addEventListener('click', function () { fillFromClient(lastClient); if (found) found.hidden = true; });
    if (cfNew) cfNew.addEventListener('click', function () { if (found) found.hidden = true; });

    // ── Validación Paso 1 ──
    function validStep1() {
        var ok = true;
        var name = document.getElementById('pub_full_name');
        var phone = document.getElementById('pub_phone');
        var pdig = (phone.value || '').replace(/\D+/g, '');
        if (!name.value.trim()) { name.style.borderColor = '#dc2626'; ok = false; } else name.style.borderColor = '';
        if (!(pdig.length === 9 && pdig[0] === '9')) { phone.style.borderColor = '#dc2626'; var e = document.querySelector('.js-phone-err'); if (e) e.textContent = 'Ingresa un celular válido (9 dígitos).'; ok = false; } else phone.style.borderColor = '';

        if (selectedType === DTYPE.DOM) {
            var addr = document.getElementById('pub_addr_domicilio');
            if (!addr.value.trim()) { addr.style.borderColor = '#dc2626'; ok = false; } else addr.style.borderColor = '';
        } else {
            var dist = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
            if (!dist || !dist.value) { var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (disp) disp.style.borderColor = '#dc2626'; ok = false; }
            else { var d2 = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (d2) d2.style.borderColor = ''; }
        }
        return ok;
    }

    function buildConfirm() {
        var isDom = selectedType === DTYPE.DOM;
        document.getElementById('c_type').textContent = isDom ? '🏍️ Entrega a domicilio' : '📦 Envío por agencia';
        document.getElementById('c_name').textContent = txt('pub_full_name') || '—';
        document.getElementById('c_doc').textContent = txt('pub_dni') || '—';
        document.getElementById('c_phone').textContent = txt('pub_phone') || '—';
        document.getElementById('c_obs').textContent = txt('pub_notes') || '—';

        if (isDom) {
            document.getElementById('r_ubigeo').hidden = true;
            document.getElementById('r_ag').hidden = true;
            document.getElementById('c_dir').textContent = txt('pub_formatted') || txt('pub_addr_domicilio') || '—';
            document.getElementById('c_ref').textContent = txt('pub_reference_dom') || '—';
            var lat = txt('pub_lat'), lng = txt('pub_lng');
            document.getElementById('r_coords').hidden = !(lat && lng);
            document.getElementById('c_coords').textContent = (lat && lng) ? (parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)) : '—';
        } else {
            document.getElementById('r_ubigeo').hidden = false;
            document.getElementById('r_ag').hidden = false;
            document.getElementById('r_coords').hidden = true;
            var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display');
            document.getElementById('c_ubigeo').textContent = (disp && disp.classList.contains('has-value')) ? disp.textContent.trim() : '—';
            document.getElementById('c_dir').textContent = txt('pub_addr_agencia') || '—';
            document.getElementById('c_ref').textContent = txt('pub_reference_ag') || '—';
            document.getElementById('c_ag').textContent = txt('pub_shipping_agency') || '—';
        }
    }

    var toStep2 = document.getElementById('toStep2');
    if (toStep2) toStep2.addEventListener('click', function () {
        if (!validStep1()) return;
        buildConfirm();
        hide(step1); show(step2); step2.classList.add('fade-in');
        setStep(3);
    });
    var back1 = document.getElementById('backStep1');
    if (back1) back1.addEventListener('click', function () { hide(step2); show(step1); setStep(2); });

    if (form) form.addEventListener('submit', function () {
        var b = document.getElementById('confirmBtn');
        if (b) { b.disabled = true; b.textContent = 'Registrando…'; }
    });

    // Restaurar tipo si hubo error de validación (old input).
    var oldType = dtInput.value;
    if (oldType) { var c = document.querySelector('.dcard[data-type="' + oldType + '"]'); if (c) c.click(); }
})();
</script>

@if(!empty($mapsKey))
<script>
    // ── Google Maps: Autocomplete + marcador arrastrable + geocoding ──
    (function () {
        var LIMA = { lat: -12.0464, lng: -77.0428 };
        @if(!empty($storeLat) && !empty($storeLng))
        var STORE = { lat: {{ $storeLat }}, lng: {{ $storeLng }} };
        @else
        var STORE = null;
        @endif
        var map, marker, geocoder, ac, ready = false, pending = false, distSvc = null;

        // Distancia de manejo tienda → cliente (Google Distance Matrix).
        function computeDistance(lat, lng) {
            if (!STORE) return;
            try {
                if (!distSvc) distSvc = new google.maps.DistanceMatrixService();
                distSvc.getDistanceMatrix({
                    origins: [STORE], destinations: [{ lat: lat, lng: lng }],
                    travelMode: 'DRIVING', unitSystem: google.maps.UnitSystem.METRIC
                }, function (res, status) {
                    if (status !== 'OK') return;
                    var el = res.rows[0] && res.rows[0].elements[0];
                    if (!el || el.status !== 'OK') return;
                    var km = (el.distance.value / 1000);
                    // Solo se guarda (lo ven el motorizado y el panel, no el cliente).
                    document.getElementById('pub_dist_km').value = km.toFixed(2);
                    document.getElementById('pub_dist_text').value = el.distance.text;
                    document.getElementById('pub_dur_text').value = el.duration.text;
                });
            } catch (e) {}
        }

        function pickComponent(components, type) {
            for (var i = 0; i < components.length; i++) {
                if (components[i].types.indexOf(type) !== -1) return components[i].long_name;
            }
            return '';
        }
        function applyPlace(lat, lng, formatted, components, placeId) {
            document.getElementById('pub_lat').value = lat;
            document.getElementById('pub_lng').value = lng;
            document.getElementById('pub_place_id').value = placeId || '';
            document.getElementById('pub_formatted').value = formatted || '';
            document.getElementById('pub_maps_url').value = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
            var city = '';
            if (components) {
                city = pickComponent(components, 'locality') || pickComponent(components, 'sublocality') ||
                       pickComponent(components, 'administrative_area_level_2') || pickComponent(components, 'administrative_area_level_1');
            }
            document.getElementById('pub_city_domicilio').value = city;
            var addrInput = document.getElementById('pub_addr_domicilio');
            if (formatted && !addrInput.value) addrInput.value = formatted;
            var box = document.getElementById('mapPicked');
            if (box) { box.classList.add('show'); document.getElementById('mp_addr').textContent = formatted || (lat + ', ' + lng); document.getElementById('mp_city').textContent = city || ''; }
            computeDistance(lat, lng);
        }
        function reverse(latlng) {
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    applyPlace(latlng.lat(), latlng.lng(), results[0].formatted_address, results[0].address_components, results[0].place_id);
                } else {
                    applyPlace(latlng.lat(), latlng.lng(), '', null, '');
                }
            });
        }
        function placeMarker(latlng) {
            marker.setPosition(latlng); map.panTo(latlng); if (map.getZoom() < 16) map.setZoom(16);
        }

        window.initShipMap = function () {
            geocoder = new google.maps.Geocoder();
            map = new google.maps.Map(document.getElementById('shipMap'), { center: LIMA, zoom: 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
            marker = new google.maps.Marker({ map: map, position: LIMA, draggable: true });
            marker.addListener('dragend', function () { reverse(marker.getPosition()); });
            map.addListener('click', function (e) { placeMarker(e.latLng); reverse(e.latLng); });

            var input = document.getElementById('mapSearch');
            ac = new google.maps.places.Autocomplete(input, { componentRestrictions: { country: 'pe' }, fields: ['geometry', 'formatted_address', 'address_components', 'place_id'] });
            ac.bindTo('bounds', map);
            ac.addListener('place_changed', function () {
                var p = ac.getPlace();
                if (!p.geometry) return;
                var loc = p.geometry.location;
                placeMarker(loc);
                applyPlace(loc.lat(), loc.lng(), p.formatted_address, p.address_components, p.place_id);
            });
            ready = true;
            if (pending) { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }
        };
        // El mapa está oculto hasta elegir "domicilio": forzar resize al mostrarlo.
        window.__initShipMapIfReady = function () {
            if (ready && map) { setTimeout(function () { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }, 120); }
            else { pending = true; }
        };
    })();
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initShipMap&language=es&region=PE"></script>
@endif
</body>
</html>
