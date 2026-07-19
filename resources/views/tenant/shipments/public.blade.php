<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de envío — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#2563eb; --brand-d:#1d4ed8; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; --ok:#16a34a; --bg:#f1f5f9; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 18px 14px 48px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:20px; padding:22px; box-shadow:0 10px 40px -16px rgba(15,23,42,.2); }
        .head { text-align:center; margin-bottom:16px; }
        .brand { font-size:13px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; }
        .head h1 { font-size:20px; margin:6px 0 2px; }
        .head p { margin:0; color:var(--muted); font-size:13px; }

        /* ── Stepper ── */
        .stepper { display:flex; align-items:center; justify-content:space-between; margin:18px 4px 22px; }
        .st { display:flex; flex-direction:column; align-items:center; gap:6px; flex:0 0 auto; width:88px; text-align:center; }
        .st .dot { width:34px; height:34px; border-radius:999px; background:#e2e8f0; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; transition:.25s; }
        .st .t { font-size:11.5px; color:#94a3b8; font-weight:600; transition:.25s; }
        .st.active .dot { background:var(--brand); color:#fff; box-shadow:0 0 0 5px rgba(37,99,235,.15); }
        .st.active .t { color:var(--ink); }
        .st.done .dot { background:var(--ok); color:#fff; }
        .st.done .t { color:var(--ink); }
        .st-line { flex:1; height:3px; background:#e2e8f0; border-radius:2px; margin:0 -6px; align-self:flex-start; margin-top:16px; }
        .st-line.done { background:var(--ok); }

        label { display:block; font-size:13px; font-weight:600; margin:14px 0 5px; }
        label:first-child { margin-top:0; }
        .req::after { content:" *"; color:#dc2626; }
        input[type=text], input[type=tel], select, textarea {
            width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:12px; font-size:15px; background:#fff; transition:.15s;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .grid2 { display:flex; gap:12px; } .grid2 > div { flex:1; }
        .hint { font-size:12px; color:var(--muted); margin-top:3px; display:block; }

        .btn { width:100%; padding:14px; border:none; border-radius:14px; background:var(--brand); color:#fff; font-size:16px; font-weight:700; cursor:pointer; transition:.15s; }
        .btn:hover { background:var(--brand-d); } .btn:active { transform:scale(.99); }
        .btn-ghost { background:#f1f5f9; color:#334155; } .btn-ghost:hover { background:#e2e8f0; }
        .row-btns { display:flex; gap:10px; margin-top:20px; }
        .row-btns .btn { flex:1; }

        /* ── Cliente encontrado ── */
        .found { background:#eff6ff; border:1px solid #bfdbfe; border-radius:14px; padding:12px 14px; margin-top:10px; }
        .found .t { font-size:12px; color:var(--brand); font-weight:700; text-transform:uppercase; }
        .found .n { font-size:16px; font-weight:700; margin:2px 0 8px; }
        .found .acts { display:flex; gap:8px; }
        .found .acts button { flex:1; padding:9px; border-radius:10px; border:none; font-weight:700; font-size:13px; cursor:pointer; }
        .found .use { background:var(--brand); color:#fff; } .found .new { background:#e2e8f0; color:#334155; }

        /* ── Confirmación ── */
        .conf { border:1.5px solid var(--line); border-radius:16px; overflow:hidden; }
        .conf .h { background:#f8fafc; padding:10px 14px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brand); border-bottom:1px solid var(--line); }
        .conf .rows { padding:6px 14px; }
        .conf .r { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed #eef2f7; font-size:14px; }
        .conf .r:last-child { border-bottom:none; }
        .conf .r .k { color:var(--muted); flex:0 0 42%; }
        .conf .r .v { text-align:right; font-weight:600; word-break:break-word; }
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
        .step[hidden] { display:none; }
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
            <p>Completa tus datos para preparar tu paquete.</p>
        </div>

        {{-- ── Barra de progreso ── --}}
        <div class="stepper" id="stepper">
            <div class="st {{ $sent ? 'done' : 'active' }}" data-n="1"><div class="dot">1</div><div class="t">Datos</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="2"><div class="dot">2</div><div class="t">Confirmación</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'active' : '' }}" data-n="3"><div class="dot">3</div><div class="t">Finalizado</div></div>
        </div>

        @if($sent)
            {{-- ══════════ PASO 3: Éxito ══════════ --}}
            <div class="ok-wrap fade-in">
                <div class="ok-check">✓</div>
                <h2>Registro realizado correctamente</h2>
                <p style="color:var(--muted);font-size:14px;margin:2px 0;">Hemos recibido tus datos. Ahora prepararemos tu pedido para el embalaje. Te notificaremos cuando sea entregado a la agencia.</p>
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

                {{-- ══════════ PASO 1: Datos ══════════ --}}
                <div class="step fade-in" data-step="1">
                    <label>DNI / RUC</label>
                    <input type="text" name="dni" id="pub_dni" value="{{ old('dni') }}" class="js-doc-lookup"
                           data-target-name="pub_full_name" data-target-address="pub_shipping_destination" data-ubigeo-group="pub"
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
                    <input type="text" name="shipping_destination" id="pub_shipping_destination" value="{{ old('shipping_destination') }}" maxlength="255" placeholder="Av./Jr./Calle y número">

                    <label>Referencia</label>
                    <input type="text" name="reference" id="pub_reference" value="{{ old('reference') }}" maxlength="255" placeholder="Frente a…, cerca de…">

                    <label>Agencia de transporte (opcional)</label>
                    <div class="agency-field">
                        <select class="agency-select">
                            <option value="">— Selecciona —</option>
                            @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                            <option value="__otra__">Otra…</option>
                        </select>
                        <input type="text" class="agency-input" name="shipping_agency" id="pub_shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Nombre de la agencia" style="display:none;margin-top:8px;">
                    </div>

                    <label>Observaciones</label>
                    <input type="text" name="notes" id="pub_notes" value="{{ old('notes') }}" maxlength="255" placeholder="Indicaciones adicionales">

                    <button type="button" class="btn" id="toStep2" style="margin-top:22px;">Continuar →</button>
                </div>

                {{-- ══════════ PASO 2: Confirmación ══════════ --}}
                <div class="step" data-step="2" hidden>
                    <div class="conf">
                        <div class="h">Destinatario</div>
                        <div class="rows">
                            <div class="r"><span class="k">Nombre</span><span class="v" id="c_name">—</span></div>
                            <div class="r"><span class="k">Documento</span><span class="v" id="c_doc">—</span></div>
                            <div class="r"><span class="k">Celular</span><span class="v" id="c_phone">—</span></div>
                            <div class="r"><span class="k">Ubigeo</span><span class="v" id="c_ubigeo">—</span></div>
                            <div class="r"><span class="k">Dirección</span><span class="v" id="c_dir">—</span></div>
                            <div class="r"><span class="k">Referencia</span><span class="v" id="c_ref">—</span></div>
                            <div class="r"><span class="k">Agencia</span><span class="v" id="c_ag">—</span></div>
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
                                <li>Agencia de transporte.</li>
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
    // ── Consulta DNI/RUC (RENIEC/SUNAT) + cliente existente ──
    var LOOKUP = '{{ url("envio/consulta") }}', CLIENT = '{{ url("envio/cliente") }}';
    var dni = document.getElementById('pub_dni');
    var found = document.getElementById('clientFound');
    var lastClient = null, t = null;

    function fillFromClient(d) {
        if (!d) return;
        var set = function (id, v) { var el = document.getElementById(id); if (el && v) el.value = v; };
        set('pub_full_name', d.full_name); set('pub_phone', d.phone);
        set('pub_shipping_destination', d.shipping_destination); set('pub_reference', d.reference);
        set('pub_shipping_agency', d.shipping_agency);
        if (window.__syncAgency) window.__syncAgency();
        if (window.__ubPreset && (d.department_id || d.district_id)) window.__ubPreset('pub', d.department_id, d.province_id, d.district_id);
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
            // 1) Cliente existente en nuestros envíos previos.
            fetch(CLIENT + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.found) {
                        lastClient = res.data;
                        document.getElementById('cf_name').textContent = res.name || '—';
                        if (found) found.hidden = false;
                    }
                }).catch(function () {});
            // 2) RENIEC/SUNAT (autocompleta nombre/dirección/ubigeo).
            fetch(LOOKUP + '/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false || !res.data) { if (status) { status.style.color = '#dc2626'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; } return; }
                    var d = res.data, full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                    var nameEl = document.getElementById('pub_full_name'); if (nameEl && full && !nameEl.value) nameEl.value = full;
                    if (d.address) { var a = document.getElementById('pub_shipping_destination'); if (a && !a.value) a.value = d.address; }
                    var loc = d.location_id, dep = (loc && loc[0]) || d.department_id || '', prov = (loc && loc[1]) || d.province_id || '', dist = (loc && loc[2]) || d.district_id || '';
                    if ((dep || dist) && window.__ubPreset) window.__ubPreset('pub', dep, prov, dist);
                    if (status) { status.style.color = '#16a34a'; status.textContent = '✓ ' + (full || 'encontrado'); }
                }).catch(function () { if (status) { status.style.color = '#dc2626'; status.textContent = 'No se pudo consultar.'; } });
        }, 450);
    });

    var cfUse = document.getElementById('cf_use'), cfNew = document.getElementById('cf_new');
    if (cfUse) cfUse.addEventListener('click', function () { fillFromClient(lastClient); if (found) found.hidden = true; });
    if (cfNew) cfNew.addEventListener('click', function () { if (found) found.hidden = true; });

    // ── Wizard 3 pasos ──
    var form = document.getElementById('shipForm');
    var step1 = document.querySelector('.step[data-step="1"]');
    var step2 = document.querySelector('.step[data-step="2"]');
    var stepper = document.getElementById('stepper');

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
    function txt(id) { var el = document.getElementById(id); return el ? (el.value || '').trim() : ''; }

    function validStep1() {
        var ok = true;
        var name = document.getElementById('pub_full_name');
        var phone = document.getElementById('pub_phone');
        var dist = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
        var pdig = (phone.value || '').replace(/\D+/g, '');
        if (!name.value.trim()) { name.style.borderColor = '#dc2626'; ok = false; } else name.style.borderColor = '';
        if (!(pdig.length === 9 && pdig[0] === '9')) { phone.style.borderColor = '#dc2626'; var e = document.querySelector('.js-phone-err'); if (e) e.textContent = 'Ingresa un celular válido (9 dígitos).'; ok = false; } else phone.style.borderColor = '';
        if (!dist || !dist.value) { var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (disp) disp.style.borderColor = '#dc2626'; ok = false; } else { var d2 = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (d2) d2.style.borderColor = ''; }
        return ok;
    }
    function buildConfirm() {
        document.getElementById('c_name').textContent = txt('pub_full_name') || '—';
        document.getElementById('c_doc').textContent = txt('pub_dni') || '—';
        document.getElementById('c_phone').textContent = txt('pub_phone') || '—';
        var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display');
        document.getElementById('c_ubigeo').textContent = (disp && disp.classList.contains('has-value')) ? disp.textContent.trim() : '—';
        document.getElementById('c_dir').textContent = txt('pub_shipping_destination') || '—';
        document.getElementById('c_ref').textContent = txt('pub_reference') || '—';
        document.getElementById('c_ag').textContent = txt('pub_shipping_agency') || '—';
        document.getElementById('c_obs').textContent = txt('pub_notes') || '—';
    }

    var toStep2 = document.getElementById('toStep2');
    if (toStep2) toStep2.addEventListener('click', function () {
        if (!validStep1()) return;
        buildConfirm();
        step1.hidden = true; step2.hidden = false; step2.classList.add('fade-in');
        setStep(2);
    });
    var back = document.getElementById('backStep1');
    if (back) back.addEventListener('click', function () {
        step2.hidden = true; step1.hidden = false; step1.classList.add('fade-in');
        setStep(1);
    });
    // Evitar doble envío.
    if (form) form.addEventListener('submit', function () {
        var b = document.getElementById('confirmBtn');
        if (b) { b.disabled = true; b.textContent = 'Registrando…'; }
    });
})();
</script>
</body>
</html>
