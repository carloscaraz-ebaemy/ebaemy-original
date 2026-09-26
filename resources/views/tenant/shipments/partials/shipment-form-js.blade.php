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
    var step0 = document.querySelector('.step[data-step="0"]');   // tipo de entrega
    var step1 = document.querySelector('.step[data-step="1"]');   // tus datos
    var stepD = document.querySelector('.step[data-step="2"]');   // a donde llega
    var step2 = document.querySelector('.step[data-step="3"]');   // revisa y confirma
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
    // ── Que modalidades llegan a la ciudad elegida ───────────────────
    //
    // Antes se elegia la modalidad ANTES de que el sistema supiera donde vive
    // el cliente: se le ofrecian las tres y era el quien tenia que saber cual
    // le corresponde. Quien vive en provincia podia elegir moto, que no llega
    // alli, y lo descubria la tienda despues.
    //
    // La regla sale de la provincia de la TIENDA, no de una constante con
    // Lima: misma provincia -> reparto propio; distinta -> agencia. Recojo en
    // tienda siempre, porque no depende de donde viva el cliente.
    var PROV_TIENDA = @json($provTienda ?? null);

    function provinciaElegida() {
        var el = document.querySelector('[data-ubigeo-group="{{ $ubGroup ?? rtrim($p, '_') }}"] [data-ub="province"]');
        return el && el.value ? String(el.value) : '';
    }

    function aplicarCobertura() {
        var dtype = document.querySelector('.dtype');
        var prov  = provinciaElegida();
        if (!dtype) return;

        // Sin ciudad todavia no hay nada que ofrecer: las tarjetas aparecen
        // cuando el sistema ya sabe a donde va el paquete.
        if (!prov) { dtype.hidden = true; return; }
        dtype.hidden = false;

        // Sin saber donde esta la tienda no se recorta nada: recortar a ciegas
        // es peor que no recortar.
        var mismaCiudad = !PROV_TIENDA || prov === String(PROV_TIENDA);

        var moto = document.querySelector('.dcard.moto');
        var ag   = document.querySelector('.dcard.ag');
        if (moto) moto.hidden = !mismaCiudad;
        if (ag)   ag.hidden   = mismaCiudad;

        // Si el cliente ya habia elegido una que deja de aplicar --volvio
        // atras y cambio de ciudad-- se deselecciona, para que no siga un
        // camino que no llega a su destino.
        // Si el cliente ya habia elegido una modalidad que deja de aplicar
        // --volvio atras y cambio de ciudad-- se olvida, para que no siga un
        // camino que no llega a su destino.
        var elegida = document.querySelector('.dcard[data-type="' + (selectedType || '') + '"]');
        if (selectedType && elegida && elegida.hidden) {
            selectedType = null;
            if (dtInput) dtInput.value = '';
        }

        // El eco del paso de entrega: a donde va, sin volver a preguntarlo.
        var eco = document.getElementById('{{ $p }}dest_eco_txt');
        var disp = document.querySelector('[data-ubigeo-group="{{ $ubGroup ?? rtrim($p, '_') }}"] .ubigeo-display');
        if (eco && disp) eco.textContent = (disp.textContent || '').trim();
    }

    // El cascader escribe en los hidden; se vigila el de provincia.
    (function () {
        var campo = document.querySelector('[data-ubigeo-group="{{ $ubGroup ?? rtrim($p, '_') }}"]');
        if (!campo) return;
        campo.addEventListener('change', aplicarCobertura);
        campo.addEventListener('click', function () { setTimeout(aplicarCobertura, 60); });
        // El cascader no siempre emite `change` al fijar los hidden: se vigila
        // el valor directamente, que es lo unico que no falla.
        var ult = '';
        setInterval(function () {
            var v = provinciaElegida();
            if (v !== ult) { ult = v; aplicarCobertura(); }
        }, 300);
    })();

    aplicarCobertura();

    // «Cambiar» en el paso de entrega devuelve al primer paso.
    document.querySelectorAll('[data-edit-step="0"]').forEach(function (b) {
        b.addEventListener('click', function () {
            hide(step1); hide(stepD); hide(step2); show(step0); setStep(1);
        });
    });

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
            ajustarPasoDestino();
            progreso();
            guardarBorrador();
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
    /**
     * Suelta el campo del nombre para que se escriba a mano.
     *
     * Es la salida cuando RENIEC/SUNAT no resuelve: el campo es `required`, y
     * dejarlo bloqueado y vacio es un callejon sin salida --el cliente no puede
     * escribir ni continuar, y se queda en el paso 2 de 5 sin saber por que--.
     */
    function soltarNombre(motivo) {
        var nm = document.getElementById('{{ $p }}full_name');
        var nh = document.getElementById('{{ $p }}name_hint');
        if (!nm) return;
        nm.readOnly = false;
        nm.classList.remove('is-auto');
        nm.placeholder = 'Escribe tu nombre completo';
        if (nh) {
            nh.style.display = '';
            nh.classList.add('is-warn');
            nh.firstChild.nodeValue = motivo || 'Escribe tu nombre completo tal como figura en tu documento. ';
        }
        try { nm.focus({ preventScroll: true }); } catch (e) {}
    }

    /** Vuelve a dejarlo en automatico (al cambiar de documento). */
    function bloquearNombre() {
        var nm = document.getElementById('{{ $p }}full_name');
        var nh = document.getElementById('{{ $p }}name_hint');
        if (nm) {
            nm.readOnly = true;
            nm.classList.add('is-auto');
            nm.placeholder = 'Se completa con tu documento';
        }
        if (nh) {
            nh.classList.remove('is-warn');
            nh.firstChild.nodeValue = '\uD83D\uDD12 Se completa autom\u00e1ticamente al ingresar tu documento. ';
        }
    }

    // El cliente puede tomar el control cuando quiera, sin tener que provocar
    // primero el error de la consulta.
    var btnManual = document.getElementById('{{ $p }}name_manual');
    if (btnManual) btnManual.addEventListener('click', function () {
        soltarNombre('Escribe tu nombre completo tal como figura en tu documento. ');
    });

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

        // Con DNI/RUC el nombre lo trae RENIEC/SUNAT. Con carne o pasaporte
        // no hay a quien consultar, asi que se escribe a mano desde el primer
        // momento.
        var auto = (tp === 'dni' || tp === 'ruc');
        var nm = document.getElementById('{{ $p }}full_name');
        var nh = document.getElementById('{{ $p }}name_hint');
        if (auto) {
            // Por los helpers y no a mano: asi el aviso ambar de «no pudimos
            // obtener tus datos» se limpia al cambiar de documento, en vez de
            // quedarse contradiciendo al campo que vuelve a estar bloqueado.
            bloquearNombre();
            if (nm) nm.value = '';
        } else {
            soltarNombre('Escribe tu nombre completo tal como figura en tu documento. ');
            if (nh) nh.classList.remove('is-warn');
        }
        if (nh) nh.style.display = '';
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
                    if (!res || res.success === false || !res.data) {
                        // No se encontro, o el servicio no contesta. En los dos
                        // casos el cliente tiene que poder seguir: se suelta el
                        // campo y se le dice que escriba el nombre a mano.
                        if (status) {
                            status.style.color = '#b45309';
                            status.textContent = (res && res.message)
                                ? res.message
                                : 'No pudimos obtener tus datos con ese documento.';
                        }
                        soltarNombre('No pudimos obtener tus datos: escribe tu nombre completo. ');
                        return;
                    }
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
                }).catch(function () {
                    // Sin red o con el servicio caido el resultado para el
                    // cliente es el mismo que si no figurara: no puede
                    // quedarse con el nombre bloqueado y vacio.
                    if (status) { status.style.color = '#b45309'; status.textContent = 'No se pudo consultar.'; }
                    soltarNombre('No pudimos consultar tu documento: escribe tu nombre completo. ');
                });
        }, 450);
    });

    var cfUse = document.getElementById('cf_use'), cfNew = document.getElementById('cf_new');
    if (cfUse) cfUse.addEventListener('click', function () { fillFromClient(lastClient); if (found) found.hidden = true; });
    if (cfNew) cfNew.addEventListener('click', function () { if (found) found.hidden = true; });

    // ── Validación Paso 1 ──
    // El paso «a donde llega» dice cosas distintas segun la modalidad: en
    // recojo en tienda no hay destino que pedir, y llamarlo «destino» ahi
    // confundiria al cliente que solo va a pasar por la tienda.
    function ajustarPasoDestino() {
        var h   = document.querySelector('.step[data-step="2"] .step-h');
        var sub = document.getElementById('{{ $p }}dest_sub');
        var sec = document.getElementById('conf_sec_entrega');
        if (!h || !sub) return;

        if (selectedType === DTYPE.TIENDA) {
            h.textContent = 'Recojo en tienda';
            sub.textContent = 'Te avisamos por WhatsApp cuando tu pedido este listo.';
            if (sec) sec.textContent = 'Recojo';
        } else if (selectedType === DTYPE.DOM) {
            h.textContent = 'Donde te lo entregamos';
            sub.textContent = 'Escribe tu direccion y ajusta el marcador en el mapa.';
            if (sec) sec.textContent = 'Entrega a domicilio';
        } else {
            h.textContent = 'A donde enviamos tu pedido';
            sub.textContent = 'Elige tu ciudad y la agencia por la que lo recogeras.';
            if (sec) sec.textContent = 'Envio por agencia';
        }
    }

    // -- Errores con nombre y apellido --------------------------------
    //
    // Antes un campo incompleto solo se ponia rojo. En un movil, a pleno sol y
    // sin saber que se espera, un borde de color no dice nada: el cliente
    // pulsaba Continuar otra vez y no pasaba nada. Ahora cada problema tiene un
    // texto en castellano, aparece junto al campo Y en un resumen arriba, y el
    // primero se lleva el foco.
    function marcar(el, msg) {
        if (!el) return;
        el.classList.add('is-bad');
        var sig = el.nextElementSibling;
        if (!sig || !sig.classList || !sig.classList.contains('fld-err')) {
            sig = document.createElement('small');
            sig.className = 'fld-err';
            el.parentNode.insertBefore(sig, el.nextSibling);
        }
        sig.textContent = msg;
        sig.hidden = false;
    }

    function limpiar(el) {
        if (!el) return;
        el.classList.remove('is-bad');
        var sig = el.nextElementSibling;
        if (sig && sig.classList && sig.classList.contains('fld-err')) sig.hidden = true;
    }

    // El foco lleva al campo, pero en el cascader de ubigeo y en el select de
    // agencia el elemento que el cliente toca no es el input: se enfoca lo que
    // se ve.
    function enfocar(el) {
        if (!el) return;
        try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resumen(boxId, problemas) {
        var box = document.getElementById(boxId);
        if (!box) return;
        var ul = box.querySelector('.err-sum__l');
        ul.innerHTML = '';

        if (!problemas.length) { box.hidden = true; return; }

        problemas.forEach(function (pr) {
            var li = document.createElement('li');
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = pr.msg;
            b.addEventListener('click', function () { enfocar(pr.el); });
            li.appendChild(b);
            ul.appendChild(li);
        });

        box.hidden = false;
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        enfocar(problemas[0].el);
    }

    // -- Paso «Tus datos» ---------------------------------------------
    function validDatos(silencioso) {
        var problemas = [];
        var name  = document.getElementById('{{ $p }}full_name');
        var phone = document.getElementById('{{ $p }}phone');
        var pdig  = (phone.value || '').replace(/\D+/g, '');

        if (!name.value.trim()) {
            if (!silencioso) marcar(name, 'Escribe tu nombre y tus apellidos.');
            problemas.push({ msg: 'Falta tu nombre completo', el: name });
        } else limpiar(name);

        if (!(pdig.length === 9 && pdig[0] === '9')) {
            if (!silencioso) {
                marcar(phone, 'Revisa tu celular: son 9 digitos y empieza en 9.');
                var e = document.querySelector('.js-phone-err');
                if (e) e.textContent = '';
            }
            problemas.push({ msg: 'Revisa tu numero de celular', el: phone });
        } else limpiar(phone);

        // Empresa: sin la persona que recoge, la agencia no entrega el paquete.
        if (esRuc()) {
            var pn = document.getElementById('{{ $p }}pickup_name');
            var pd = document.getElementById('{{ $p }}pickup_dni');
            var pdig2 = pd ? (pd.value || '').replace(/\D+/g, '') : '';

            if (!pn || !pn.value.trim()) {
                if (!silencioso) marcar(pn, 'Nombre de la persona que recogera el paquete.');
                problemas.push({ msg: 'Falta quien recoge el paquete', el: pn });
            } else limpiar(pn);

            if (pdig2.length < 8) {
                if (!silencioso) marcar(pd, 'El DNI de quien recoge tiene 8 digitos.');
                problemas.push({ msg: 'Falta el DNI de quien recoge', el: pd });
            } else limpiar(pd);
        }

        if (!silencioso) resumen('{{ $p }}errsum_datos', problemas);

        return problemas.length === 0;
    }

    // -- Paso «A donde llega» -----------------------------------------
    function validDestino(silencioso) {
        var problemas = [];

        // El recojo en tienda no pide direccion ni ubigeo: con nombre y celular
        // basta para tener el pedido listo y avisarle.
        if (selectedType === DTYPE.TIENDA) {
            if (!silencioso) resumen('{{ $p }}errsum_dest', []);
            return true;
        }

        if (selectedType === DTYPE.DOM) {
            var addr = document.getElementById('{{ $p }}addr_domicilio');
            if (!addr.value.trim()) {
                if (!silencioso) marcar(addr, 'Escribe tu calle, avenida o jiron y el numero.');
                problemas.push({ msg: 'Falta tu direccion de entrega', el: addr });
            } else limpiar(addr);
        } else {
            var dist = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
            var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display');
            if (!dist || !dist.value) {
                if (!silencioso && disp) disp.classList.add('is-bad');
                problemas.push({ msg: 'Elige a que ciudad o distrito enviamos', el: disp });
            } else if (disp) disp.classList.remove('is-bad');

            // La AGENCIA es obligatoria: sin ella el almacen no sabe donde dejar
            // el paquete y salia un rotulo de provincia sin destino.
            var agSel = document.querySelector('.branch-agencia .agency-select');
            var agVal = txt('{{ $p }}shipping_agency');
            var agErr = document.getElementById('{{ $p }}agency_err');
            if (!agVal) {
                if (!silencioso) {
                    if (agSel) agSel.classList.add('is-bad');
                    if (agErr) agErr.hidden = false;
                }
                problemas.push({ msg: 'Elige la agencia de transporte', el: agSel });
            } else {
                if (agSel) agSel.classList.remove('is-bad');
                if (agErr) agErr.hidden = true;
            }

            // En provincia lo unico obligatorio es la AGENCIA y el ubigeo. Ni la
            // oficina de recojo ni la direccion bloquean el registro: son datos
            // que el cliente muchas veces no tiene todavia y que el encargado
            // completa despues. Se avisa, pero se deja continuar.
            var casa = document.getElementById('{{ $p }}addr_agencia');
            var de   = document.getElementById('{{ $p }}dest_err');
            if (casa) casa.classList.remove('is-bad');
            if (de && !silencioso) {
                var faltaDir = agHome && agHome.checked && !(casa && casa.value.trim());
                de.style.color = '#a16207';
                de.textContent = faltaDir
                    ? 'Pediste que la agencia lleve el paquete a tu domicilio pero no escribiste la direccion; podras indicarla despues.'
                    : '';
                de.hidden = !faltaDir;
            }
        }

        if (!silencioso) resumen('{{ $p }}errsum_dest', problemas);

        return problemas.length === 0;
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

    // Datos -> A donde llega
    var toDest = document.getElementById('toStepDest');
    if (toDest) toDest.addEventListener('click', function () {
        if (!validDatos(false)) return;
        hide(step1); show(stepD); stepD.classList.add('fade-in');
        setStep(3);
        guardarBorrador();
        // El mapa se mide mal si se inicializa mientras su contenedor esta
        // oculto: hasta ahora el paso estaba siempre visible y no hacia falta.
        if (selectedType === DTYPE.DOM && window.__initShipMapIfReady) window.__initShipMapIfReady();
    });

    var backDatos = document.getElementById('backStepDatos');
    if (backDatos) backDatos.addEventListener('click', function () {
        hide(stepD); show(step1); setStep(2);
    });

    // A donde llega -> Revisa
    var toStep2 = document.getElementById('toStep2');
    if (toStep2) toStep2.addEventListener('click', function () {
        if (!validDestino(false)) return;
        buildConfirm();
        hide(stepD); show(step2); step2.classList.add('fade-in');
        setStep(4);
        guardarBorrador();
    });

    var back1 = document.getElementById('backStep1');
    if (back1) back1.addEventListener('click', function () { hide(step2); show(stepD); setStep(3); });

    // «Editar» de cada seccion del resumen: vuelve al paso que la llena.
    document.querySelectorAll('.conf-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            var destino = b.getAttribute('data-edit-step');
            hide(step2);
            if (destino === '1') { show(step1); setStep(2); }
            else { show(stepD); setStep(3); }
        });
    });

    // ── Cuanto le falta ───────────────────────────────────────────────
    //
    // El porcentaje sale de los campos que de verdad exige el servidor para la
    // modalidad elegida (ver ShipmentController::validateShipment), no de un
    // total fijo: en recojo en tienda no hay direccion que pedir, y contarla
    // dejaria el progreso clavado para siempre.
    function requeridos() {
        var lista = [
            { ok: !!txt('{{ $p }}full_name') },
            { ok: (txt('{{ $p }}phone').replace(/\D+/g, '').length === 9) }
        ];

        if (esRuc()) {
            lista.push({ ok: !!txt('{{ $p }}pickup_name') });
            lista.push({ ok: (txt('{{ $p }}pickup_dni').replace(/\D+/g, '').length >= 8) });
        }

        if (selectedType === DTYPE.DOM) {
            lista.push({ ok: !!txt('{{ $p }}addr_domicilio') });
        } else if (selectedType === DTYPE.AG) {
            var d = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
            lista.push({ ok: !!(d && d.value) });
            lista.push({ ok: !!txt('{{ $p }}shipping_agency') });
        }

        return lista;
    }

    function progreso() {
        var caja = document.getElementById('prog');
        if (!caja) return;

        if (!selectedType) { caja.hidden = true; return; }

        var lista = requeridos();
        var hechos = lista.filter(function (x) { return x.ok; }).length;
        var pct = Math.round((hechos / lista.length) * 100);

        caja.hidden = false;
        caja.classList.toggle('is-done', pct === 100);
        document.getElementById('progFill').style.width = pct + '%';
        document.getElementById('progText').textContent = pct === 100
            ? 'Listo: ya tenemos todo lo necesario'
            : ('Tu informacion esta ' + pct + '% completa');
    }

    // ── Borrador ──────────────────────────────────────────────────────
    //
    // El enlace se abre desde WhatsApp: basta que entre una llamada para que el
    // navegador descarte la pestana y el cliente vuelva a un formulario vacio.
    // Se guarda en el propio dispositivo, nunca en la base: un registro a medias
    // en `shipping_requests` seria un envio fantasma para el encargado.
    var BORRADOR = 'ship_draft_{{ $draftKey ?? "pub" }}';

    function camposBorrador() {
        return form.querySelectorAll('input[name], select[name], textarea[name]');
    }

    function guardarBorrador() {
        try {
            var datos = { __type: selectedType || '' };
            camposBorrador().forEach(function (el) {
                if (el.type === 'hidden' && el.name === '_token') return;
                if (el.type === 'checkbox') datos[el.id || el.name] = el.checked ? 1 : 0;
                else if (el.type === 'radio') { if (el.checked) datos['r:' + el.name] = el.value; }
                else datos[el.id || el.name] = el.value;
            });
            localStorage.setItem(BORRADOR, JSON.stringify(datos));
        } catch (e) { /* modo privado o sin espacio: el formulario sigue */ }
    }

    function limpiarBorrador() {
        try { localStorage.removeItem(BORRADOR); } catch (e) {}
    }

    function restaurarBorrador() {
        var datos;
        try { datos = JSON.parse(localStorage.getItem(BORRADOR) || 'null'); } catch (e) { return; }
        if (!datos) return;

        camposBorrador().forEach(function (el) {
            if (el.type === 'hidden' && el.name === '_token') return;
            var clave = el.id || el.name;
            if (el.type === 'checkbox') { if (clave in datos) el.checked = !!datos[clave]; }
            else if (el.type === 'radio') { if (datos['r:' + el.name] === el.value) el.checked = true; }
            else if (clave in datos && datos[clave] !== '') el.value = datos[clave];
        });

        // El tipo de entrega gobierna que campos existen: se re-elige tal cual
        // lo dejo, para que las ramas y los `required` queden coherentes.
        if (datos.__type) {
            var card = document.querySelector('.dcard[data-type="' + datos.__type + '"]');
            if (card) card.click();
        }
    }

    // Cualquier cambio actualiza progreso y borrador. Delegado en el formulario:
    // el cascader de ubigeo y el mapa escriben en inputs ocultos que no existen
    // todavia cuando esto se registra.
    //
    // El progreso se repinta al vuelo, pero el borrador espera medio segundo:
    // escribir en localStorage en CADA tecla se nota en un telefono de gama
    // baja, que es justo el que abre este enlace desde WhatsApp.
    var guardarPronto = (function () {
        var t = null;
        return function () {
            if (t) clearTimeout(t);
            t = setTimeout(guardarBorrador, 500);
        };
    })();

    form.addEventListener('input', function () { progreso(); guardarPronto(); });
    form.addEventListener('change', function () { progreso(); guardarPronto(); });

    restaurarBorrador();
    progreso();

    if (form) form.addEventListener('submit', function () {
        limpiarBorrador();
        var b = document.getElementById('confirmBtn');
        if (b) { b.disabled = true; b.textContent = 'Registrando…'; }
    });

    // Restaurar tipo si hubo error de validación (old input).
    var oldType = dtInput.value;
    if (oldType) { var c = document.querySelector('.dcard[data-type="' + oldType + '"]'); if (c) c.click(); }
})();
</script>
