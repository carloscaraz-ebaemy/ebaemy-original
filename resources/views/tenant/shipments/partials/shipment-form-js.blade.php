{{-- JS de la ficha de envio: Google Maps, modalidad, validaciones y
     calculo del precio. Mismo comportamiento en el formulario publico y en
     el modal del panel; solo cambia el prefijo de los ids.

     Parametro: $p (prefijo, debe coincidir con el del cuerpo).
--}}
@php $p = $p ?? 'pub_'; @endphp
<script>
(function () {
    var DTYPE = { DOM: 'domicilio', AG: 'agencia', TIENDA: 'tienda' };
    var form = document.getElementById('shipForm');
    if (!form) return;
    var dtInput = document.getElementById('delivery_type');
    var stepper = document.getElementById('stepper');
    var step0 = document.querySelector('.step[data-step="0"]');
    var step1 = document.querySelector('.step[data-step="1"]');
    var step2 = document.querySelector('.step[data-step="2"]');
    var branchDom = document.querySelector('.branch-domicilio');
    var branchAg = document.querySelector('.branch-agencia');
    var branchTienda = document.querySelector('.branch-tienda');
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

            // Tres modalidades: se muestra la rama elegida y se ocultan las otras.
            branchDom.hidden    = selectedType !== DTYPE.DOM;
            branchAg.hidden     = selectedType !== DTYPE.AG;
            if (branchTienda) branchTienda.hidden = selectedType !== DTYPE.TIENDA;

            document.getElementById('tag-moto').hidden = selectedType !== DTYPE.DOM;
            document.getElementById('tag-ag').hidden   = selectedType !== DTYPE.AG;
            var tagT = document.getElementById('tag-tienda');
            if (tagT) tagT.hidden = selectedType !== DTYPE.TIENDA;

            // Evitar que campos ocultos "required" bloqueen el submit del navegador.
            syncRequired();
            hide(step0); show(step1); step1.classList.add('fade-in');
            setStep(2);
            if (isDom && window.__initShipMapIfReady) window.__initShipMapIfReady();
        });
    });

    // Solo la rama VISIBLE envía datos: las ocultas se deshabilitan para que sus
    // `required` no bloqueen el submit ni se manden campos de otra modalidad
    // (hay names repetidos entre ramas, como shipping_destination y reference).
    function syncRequired() {
        [[branchDom, DTYPE.DOM], [branchAg, DTYPE.AG], [branchTienda, DTYPE.TIENDA]]
            .forEach(function (pair) {
                var el = pair[0], type = pair[1];
                if (!el) return;
                var off = selectedType !== type;
                el.querySelectorAll('input,select,textarea').forEach(function (f) { f.disabled = off; });
            });
        syncAgHome();
    }

    // Rama agencia: la dirección solo aparece si la agencia hace reparto. Si no,
    // el paquete se queda en la agencia y no hay dirección que registrar.
    var agHome = document.getElementById('{{ $p }}ag_home');
    var agHomeWrap = document.getElementById('agHomeWrap');
    function syncAgHome() {
        if (!agHome || !agHomeWrap) return;
        var on = agHome.checked && !agHome.disabled;
        agHomeWrap.hidden = !on;
        // La fila "hasta tu puerta" del resumen de costos aparece con el check:
        // si no, el cliente ve un costo extra que no pidio.
        var filaHome = document.getElementById('costHome');
        if (filaHome) filaHome.hidden = !on;
        var a = document.getElementById('{{ $p }}addr_agencia');
        if (a) { if (!on) a.value = ''; a.disabled = !on; }
    }
    if (agHome) agHome.addEventListener('change', syncAgHome);

    // El campo "oficina" nombra la agencia elegida: "¿En qué oficina de Shalom…?".
    // Las oficinas tienen nombre propio y es el dato que necesita el almacén.
    function syncOfficeLabel() {
        var lbl = document.getElementById('{{ $p }}office_label');
        var inp = document.getElementById('{{ $p }}reference_ag');
        if (!lbl || !inp) return;
        var ag = txt('{{ $p }}shipping_agency');
        // El "(opcional)" se reescribe junto con el nombre de la agencia: si se
        // pierde, el campo vuelve a parecer obligatorio.
        lbl.innerHTML = (ag ? ('Oficina de ' + ag + ' donde recogerás') : 'Oficina donde recogerás')
            + ' <span style="color:#94a3b8;font-weight:400;">(opcional)</span>';
        inp.placeholder = ag
            ? ('Ej. ' + ag + ' Terminal Terrestre, Av. Aviación 123…')
            : 'Ej. Terminal Terrestre, Av. Aviación 123…';
    }
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.classList && ev.target.classList.contains('agency-select')) {
            setTimeout(syncOfficeLabel, 0);
        }
    });
    var agInp = document.getElementById('{{ $p }}shipping_agency');
    if (agInp) agInp.addEventListener('input', syncOfficeLabel);
    syncOfficeLabel();

    var back0 = document.getElementById('backStep0');
    if (back0) back0.addEventListener('click', function () { hide(step1); show(step0); setStep(1); });

    // ── Consulta DNI/RUC (RENIEC/SUNAT) + cliente existente ──
    var LOOKUP = '{{ url("envio/consulta") }}', CLIENT = '{{ url("envio/cliente") }}';
    var dni = document.getElementById('{{ $p }}dni');
    var found = document.getElementById('clientFound');
    var lastClient = null, t = null;

    function fillFromClient(d) {
        if (!d) return;
        var set = function (id, v) { var el = document.getElementById(id); if (el && v) el.value = v; };
        var nmEl = document.getElementById('{{ $p }}full_name');
        if (nmEl && d.full_name) nmEl.value = d.full_name;
        set('{{ $p }}phone', d.phone);
        // Dirección/referencia según la rama activa.
        if (selectedType === DTYPE.DOM) { set('{{ $p }}addr_domicilio', d.shipping_destination); set('{{ $p }}reference_dom', d.reference); }
        else {
            set('{{ $p }}addr_agencia', d.shipping_destination); set('{{ $p }}reference_ag', d.reference);
            set('{{ $p }}shipping_agency', d.shipping_agency);
            if (window.__syncAgency) window.__syncAgency();
            if (window.__ubPreset && (d.department_id || d.district_id)) window.__ubPreset('pub', d.department_id, d.province_id, d.district_id);
        }
    }

    // Tipo de documento: adapta el campo y decide si se puede consultar en línea.
    function docType() {
        var r = document.querySelector('input[name="document_type"]:checked');
        return r ? r.value : 'dni';
    }
    function syncDocField() {
        if (!dni) return;
        var tp = docType();
        var cfg = {
            dni:       { ml: 11, im: 'numeric', ph: '8 dígitos (DNI) u 11 (RUC)' },
            ce:        { ml: 20, im: 'text',    ph: 'N° de carné de extranjería' },
            pasaporte: { ml: 20, im: 'text',    ph: 'N° de pasaporte' }
        }[tp] || { ml: 11, im: 'numeric', ph: '8 dígitos (DNI) u 11 (RUC)' };
        dni.maxLength = cfg.ml;
        dni.setAttribute('inputmode', cfg.im);
        dni.placeholder = cfg.ph;
        var st = document.querySelector('.js-doc-status');
        if (st) st.textContent = '';
        if (found) found.hidden = true;

        // Con DNI/RUC el nombre lo trae RENIEC/SUNAT: no se escribe a mano.
        var auto = (tp === 'dni' || tp === 'ruc');
        var nm = document.getElementById('{{ $p }}full_name');
        var nh = document.getElementById('{{ $p }}name_hint');
        if (nm) {
            nm.readOnly = auto;
            nm.classList.toggle('is-auto', auto);
            nm.placeholder = auto ? 'Se completa con tu documento' : 'Escribe tu nombre completo';
            if (auto) nm.value = '';
        }
        if (nh) nh.style.display = auto ? '' : 'none';
    }
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.name === 'document_type') syncDocField();
    });
    syncDocField();

    /* Empresa (RUC = 11 digitos) -> hay que decir QUIEN recoge. En este
       formulario DNI y RUC comparten opcion, asi que se distingue por la
       cantidad de digitos, igual que lo hace el servidor. */
    function esRuc() {
        var tp = docType();
        if (tp !== 'dni') return false;                 // CE/pasaporte no son empresa
        return (txt('{{ $p }}dni').replace(/\D+/g, '')).length === 11;
    }
    function syncPickupBox() {
        var box = document.getElementById('{{ $p }}pickup_box');
        if (!box) return;
        var on = esRuc();
        box.hidden = !on;
        // Los campos ocultos no deben viajar con datos de un cliente anterior.
        if (!on) {
            ['{{ $p }}pickup_name', '{{ $p }}pickup_dni', '{{ $p }}pickup_phone'].forEach(function (id) {
                var el = document.getElementById(id); if (el) el.value = '';
            });
            var err = document.getElementById('{{ $p }}pickup_err'); if (err) err.hidden = true;
        }
    }
    if (dni) dni.addEventListener('input', syncPickupBox);
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.name === 'document_type') syncPickupBox();
    });
    syncPickupBox();

    if (dni) dni.addEventListener('input', function () {
        var num = (dni.value || '').replace(/\D+/g, '');
        var status = document.querySelector('.js-doc-status');
        if (found) found.hidden = true;
        clearTimeout(t);
        // Solo DNI y RUC se consultan contra RENIEC/SUNAT.
        var tp = docType();
        if (tp !== 'dni' && tp !== 'ruc') { if (status) status.textContent = ''; return; }
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
                    var nameEl = document.getElementById('{{ $p }}full_name'); if (nameEl && full) nameEl.value = full;
                    if (d.address) {
                        var a = selectedType === DTYPE.DOM ? document.getElementById('{{ $p }}addr_domicilio') : document.getElementById('{{ $p }}addr_agencia');
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
        var name = document.getElementById('{{ $p }}full_name');
        var phone = document.getElementById('{{ $p }}phone');
        var pdig = (phone.value || '').replace(/\D+/g, '');
        if (!name.value.trim()) { name.style.borderColor = '#dc2626'; ok = false; } else name.style.borderColor = '';
        if (!(pdig.length === 9 && pdig[0] === '9')) { phone.style.borderColor = '#dc2626'; var e = document.querySelector('.js-phone-err'); if (e) e.textContent = 'Ingresa un celular válido (9 dígitos).'; ok = false; } else phone.style.borderColor = '';

        // Empresa: sin la persona que recoge, la agencia no entrega el paquete.
        if (esRuc()) {
            var pn = document.getElementById('{{ $p }}pickup_name');
            var pd = document.getElementById('{{ $p }}pickup_dni');
            var pe = document.getElementById('{{ $p }}pickup_err');
            var pdig2 = pd ? (pd.value || '').replace(/\D+/g, '') : '';
            var falta = [];
            if (!pn || !pn.value.trim()) { if (pn) pn.style.borderColor = '#dc2626'; falta.push('el nombre'); }
            else if (pn) pn.style.borderColor = '';
            if (pdig2.length < 8) { if (pd) pd.style.borderColor = '#dc2626'; falta.push('el DNI'); }
            else if (pd) pd.style.borderColor = '';
            if (falta.length) {
                if (pe) {
                    pe.textContent = 'Falta ' + falta.join(' y ') + ' de la persona que recoge el paquete.';
                    pe.hidden = false;
                }
                ok = false;
            } else if (pe) {
                pe.hidden = true;
            }
        }

        // El recojo en tienda no pide dirección ni ubigeo: con nombre y celular
        // basta para tener el pedido listo y avisarle.
        if (selectedType === DTYPE.TIENDA) {
            return ok;
        }

        if (selectedType === DTYPE.DOM) {
            var addr = document.getElementById('{{ $p }}addr_domicilio');
            if (!addr.value.trim()) { addr.style.borderColor = '#dc2626'; ok = false; } else addr.style.borderColor = '';
        } else {
            var dist = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
            if (!dist || !dist.value) { var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (disp) disp.style.borderColor = '#dc2626'; ok = false; }
            else { var d2 = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (d2) d2.style.borderColor = ''; }

            // La AGENCIA es obligatoria: sin ella el almacén no sabe dónde dejar
            // el paquete y salía un rótulo de provincia sin destino.
            var agSel = document.querySelector('.branch-agencia .agency-select');
            var agVal = txt('{{ $p }}shipping_agency');
            var agErr = document.getElementById('{{ $p }}agency_err');
            if (!agVal) {
                if (agSel) agSel.style.borderColor = '#dc2626';
                if (agErr) agErr.hidden = false;
                ok = false;
            } else {
                if (agSel) agSel.style.borderColor = '';
                if (agErr) agErr.hidden = true;
            }

            // En provincia lo único obligatorio es la AGENCIA y el ubigeo. Ni la
            // oficina de recojo ni la dirección bloquean el registro: son datos
            // que el cliente muchas veces no tiene todavía y que el encargado
            // completa después. Se avisa, pero se deja continuar.
            var casa = document.getElementById('{{ $p }}addr_agencia');
            var de   = document.getElementById('{{ $p }}dest_err');
            if (casa) casa.style.borderColor = '';
            if (de) {
                var faltaDir = agHome && agHome.checked && !(casa && casa.value.trim());
                de.style.color = '#a16207';
                de.textContent = faltaDir
                    ? 'Pediste que la agencia lleve el paquete a tu domicilio pero no escribiste la dirección; podrás indicarla después.'
                    : '';
                de.hidden = !faltaDir;
            }
        }
        return ok;
    }

    function buildConfirm() {
        var isDom    = selectedType === DTYPE.DOM;
        var isPickup = selectedType === DTYPE.TIENDA;

        // Aviso de tiempos: se muestra el de la modalidad elegida. Van los
        // tres en el HTML y aquí se decide, igual que las ramas del paso 1.
        ['domicilio', 'agencia', 'tienda'].forEach(function (t) {
            var box = document.getElementById('eta-' + t);
            if (box) box.hidden = (selectedType !== t);
        });

        // Solo aplica a agencia con reparto pedido; se apaga por defecto para
        // que no sobreviva al cambiar de modalidad.
        var homeExtra = document.getElementById('r_home_extra');
        if (homeExtra) homeExtra.hidden = true;

        document.getElementById('c_type').textContent = isPickup
            ? '🏬 Recojo en tienda'
            : (isDom ? '🏍️ Entrega a domicilio · LIMA' : '📦 Envío por agencia · PROVINCIA');
        document.getElementById('c_name').textContent = txt('{{ $p }}full_name') || '—';
        var dt = document.querySelector('input[name="document_type"]:checked');
        var dtv = dt ? dt.value : 'dni';
        var dnum = txt('{{ $p }}dni');
        var dtl = dtv === 'dni'
            ? (dnum.replace(/\D+/g, '').length === 11 ? 'RUC' : 'DNI')
            : (dt ? dt.parentNode.querySelector('span').textContent : '');
        document.getElementById('c_doc').textContent = dnum ? (dtl + ' ' + dnum) : '—';

        // Empresa: se confirma tambien quien recoge, que es a quien la agencia
        // le va a entregar el paquete.
        var rp = document.getElementById('r_pickup');
        if (rp) {
            var pnom = txt('{{ $p }}pickup_name'), pdoc = txt('{{ $p }}pickup_dni');
            rp.hidden = !esRuc();
            document.getElementById('c_pickup').textContent =
                (pnom || pdoc) ? (pnom + (pdoc ? ' · DNI ' + pdoc : '')) : '—';
        }
        document.getElementById('c_phone').textContent = txt('{{ $p }}phone') || '—';

        if (isPickup) {
            // Recojo: no hay ubigeo, agencia, coordenadas ni costo de envío.
            document.getElementById('r_ubigeo').hidden = true;
            document.getElementById('r_ag').hidden     = true;
            document.getElementById('r_coords').hidden = true;
            document.getElementById('r_price').hidden  = true;
            {{-- json_encode y no @json(): el directive se rompe con ternarios
                 (ver feedback_blade_json_parser_trap). --}}
            document.getElementById('c_dir').textContent = {!! json_encode($storeAddress ?: 'Recojo en la tienda') !!};
            document.getElementById('k_ref').textContent = 'Piensa pasar';
            document.getElementById('c_ref').textContent = txt('{{ $p }}reference_tienda') || '—';
        } else if (isDom) {
            document.getElementById('r_ubigeo').hidden = true;
            document.getElementById('r_ag').hidden = true;
            document.getElementById('c_dir').textContent = txt('{{ $p }}addr_domicilio') || txt('{{ $p }}formatted') || '—';
            document.getElementById('k_ref').textContent = 'Referencia';
            document.getElementById('c_ref').textContent = txt('{{ $p }}reference_dom') || '—';
            var lat = txt('{{ $p }}lat'), lng = txt('{{ $p }}lng');
            document.getElementById('r_coords').hidden = !(lat && lng);
            document.getElementById('c_coords').textContent = (lat && lng) ? (parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)) : '—';
            var price = txt('{{ $p }}delivery_price');
            document.getElementById('r_price').hidden = !price;
            document.querySelector('#r_price .k').textContent = 'Costo aprox. de envío';
            document.getElementById('c_price').textContent = price ? ('S/ ' + price) : '—';
        } else {
            document.getElementById('r_ubigeo').hidden = false;
            document.getElementById('r_ag').hidden = false;
            document.getElementById('r_coords').hidden = true;
            // Costo tienda→agencia (fijo por paquete). "Gratis" es un estado
            // propio: se muestra la fila diciendo GRATIS, no se esconde.
            var af    = {{ (float) ($agencyFee ?? 0) }};
            var afFree = {{ !empty($agencyFree) ? 'true' : 'false' }};
            document.getElementById('r_price').hidden = !(afFree || af > 0);
            document.querySelector('#r_price .k').textContent = 'Servicio tienda→agencia';
            document.getElementById('c_price').textContent =
                afFree ? '¡GRATIS!' : (af > 0 ? ('S/ ' + af.toFixed(2)) : '—');
            var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display');
            document.getElementById('c_ubigeo').textContent = (disp && disp.classList.contains('has-value')) ? disp.textContent.trim() : '—';
            var pidioReparto = !!(agHome && agHome.checked && txt('{{ $p }}addr_agencia'));
            if (homeExtra) homeExtra.hidden = !pidioReparto;
            document.getElementById('c_dir').textContent = txt('{{ $p }}addr_agencia') || 'Recojo en la agencia';
            document.getElementById('k_ref').textContent = 'Oficina de recojo';
            document.getElementById('c_ref').textContent = txt('{{ $p }}reference_ag') || '—';
            document.getElementById('c_ag').textContent = txt('{{ $p }}shipping_agency') || '—';
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
