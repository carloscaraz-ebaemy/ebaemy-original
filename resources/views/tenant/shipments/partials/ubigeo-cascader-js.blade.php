{{-- Widget de destino: 1 campo → popup con BUSCADOR GLOBAL (departamento,
     provincia y distrito a la vez) y, plegado debajo, el selector manual de
     3 columnas. Reusable en el formulario público y en los modales del panel.
     Requiere $departments en el scope.

     El buscador va primero y con el foco puesto porque el cliente no piensa
     "Piura → Talara → Pariñas": piensa "quiero enviar a Talara". El selector
     manual se queda —plegado— porque hay quien ya sabe el departamento y
     porque de él dependen tres flujos de precarga (edición, DNI/RUC, pedido)
     vía __ubPreset(). --}}
<style>
    .ubigeo-field { position: relative; }
    .ubigeo-display { border: 1px solid #dee2e6; border-radius: .5rem; padding: 10px 12px; cursor: pointer; background: #fff; color: #6c757d; font-size: 14px; min-height: 40px; }
    .ubigeo-display.has-value { color: #212529; font-weight: 500; }
    .ubigeo-pop { position: absolute; z-index: 5000; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid #dee2e6; border-radius: .6rem; box-shadow: 0 14px 36px -10px rgba(15,23,42,.3); overflow: hidden; }
    .ubigeo-bar { padding: 8px; border-bottom: 1px solid #f1f3f5; }
    .ubigeo-search { width: 100%; border: 1px solid #dee2e6; border-radius: .4rem; padding: 10px 12px; font-size: 15px; outline: none; }
    .ubigeo-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .ubigeo-cols { display: flex; }
    .ubigeo-col { flex: 1; min-width: 33%; max-height: 220px; overflow-y: auto; border-right: 1px solid #f1f3f5; }
    .ubigeo-col:last-child { border-right: none; }
    .ubigeo-results { max-height: 300px; overflow-y: auto; }
    .ubigeo-item { padding: 9px 11px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f8f9fa; white-space: nowrap; }
    .ubigeo-item:hover, .ubigeo-item.active { background: #eef2ff; color: #4f46e5; font-weight: 600; }
    /* Columna vacía: en vez de un guion mudo, dice qué falta elegir. */
    .ubigeo-col:empty::before { content: attr(data-empty); display: block; text-align: center; color: #adb5bd; padding: 16px 10px; font-size: 12px; line-height: 1.4; }
    .ubigeo-hint { padding: 0 8px 8px; font-size: 11.5px; color: #adb5bd; }
    .ubigeo-empty { padding: 14px; text-align: center; color: #adb5bd; font-size: 13px; }
    @media (max-width: 520px) { .ubigeo-cols { overflow-x: auto; } .ubigeo-col { min-width: 130px; } }

    /* ── Resultados de la búsqueda global ───────────────────────────────── */
    /* Dos líneas por resultado: el nombre que el cliente escribió y, debajo,
       dónde queda. Hay 99 nombres de distrito repetidos en el catálogo
       ("Santa Rosa" está 10 veces): sin el contexto no se puede elegir. */
    .ub-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f8f9fa; }
    .ub-row:last-child { border-bottom: none; }
    .ub-row:hover, .ub-row.active { background: #eef2ff; }
    .ub-row-main { min-width: 0; flex: 1; }
    .ub-row-name { font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ub-row.active .ub-row-name { color: #4338ca; }
    .ub-row-ctx { font-size: 11.5px; color: #94a3b8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ub-tag { flex: 0 0 auto; font-size: 10px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; padding: 3px 7px; border-radius: 999px; background: #f1f5f9; color: #64748b; }
    .ub-tag.is-prov { background: #fef3c7; color: #92400e; }
    .ub-tag.is-dep { background: #e0e7ff; color: #3730a3; }
    .ub-chevron { flex: 0 0 auto; color: #cbd5e1; font-size: 15px; }

    .ub-head { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #eef2f6; font-size: 12px; color: #475569; position: sticky; top: 0; z-index: 1; }
    .ub-back { cursor: pointer; color: #4f46e5; font-weight: 600; }
    .ub-back:hover { text-decoration: underline; }

    /* Vacío: decirle qué escribió y qué puede hacer, no un "Sin resultados". */
    .ub-void { padding: 20px 16px; text-align: center; }
    .ub-void-t { font-size: 13.5px; color: #334155; font-weight: 600; margin-bottom: 4px; }
    .ub-void-s { font-size: 12px; color: #94a3b8; line-height: 1.5; }
    .ub-void-acts { margin-top: 12px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
    .ub-btn { border: 1px solid #dee2e6; background: #fff; border-radius: .4rem; padding: 7px 12px; font-size: 12px; color: #334155; cursor: pointer; }
    .ub-btn:hover { border-color: #6366f1; color: #4f46e5; }

    /* El selector manual de 3 columnas, plegado. */
    .ub-manual-toggle { padding: 9px 12px; border-top: 1px solid #f1f3f5; font-size: 12px; color: #64748b; cursor: pointer; background: #fcfcfd; display: flex; justify-content: space-between; align-items: center; }
    .ub-manual-toggle:hover { color: #4f46e5; }
    .ub-manual-toggle b { font-weight: 600; }

    @media (max-width: 520px) {
        .ubigeo-results { max-height: 46vh; }
        .ub-row { padding: 12px; }           /* área táctil cómoda en celular */
        .ub-row-name { font-size: 15px; white-space: normal; }
        .ub-row-ctx { white-space: normal; }
    }

    /* Encabezados de sección del formulario de envío */
    .sh-section { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #4f46e5; border-bottom: 1px solid #eef2ff; padding-bottom: 5px; margin: 20px 0 12px; }
    .sh-section:first-child { margin-top: 4px; }
</style>
<script>
(function () {
    var UB_DEPTS  = {!! json_encode($departments->map(function ($d) { return ['id' => $d->id, 'description' => $d->description]; })->values()) !!};
    var UB_PROV   = '{{ url("envio/ubigeo/provincias") }}';
    var UB_DIST   = '{{ url("envio/ubigeo/distritos") }}';
    var UB_SEARCH = '{{ url("envio/ubigeo/buscar") }}';

    function ubCerrarTodos() {
        document.querySelectorAll('.ubigeo-pop').forEach(function (p) { p.hidden = true; });
    }
    function ubJSON(u) {
        return fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
    }
    function ubEsc(s) {
        var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML;
    }
    function ubRender(col, items, onClick, selId) {
        col.innerHTML = '';
        (items || []).forEach(function (it) {
            var d = document.createElement('div');
            d.className = 'ubigeo-item' + (selId && String(selId) === String(it.id) ? ' active' : '');
            d.textContent = it.description;
            d.addEventListener('click', function (e) {
                e.stopPropagation();
                Array.prototype.forEach.call(col.children, function (c) { c.classList.remove('active'); });
                d.classList.add('active'); onClick(it);
            });
            col.appendChild(d);
        });
    }
    function ubInit(field) {
        if (field._ub) return; field._ub = true;
        var disp = field.querySelector('.ubigeo-display'), pop = field.querySelector('.ubigeo-pop');
        var cDep = pop.querySelector('[data-col="dep"]'), cProv = pop.querySelector('[data-col="prov"]'), cDist = pop.querySelector('[data-col="dist"]');
        var hDep = field.querySelector('[data-ub="department"]'), hProv = field.querySelector('[data-ub="province"]'), hDist = field.querySelector('[data-ub="district"]');
        var PH = 'Busca ciudad, provincia o distrito…';

        // Vue toma su plantilla del DOM VIVO al montar, o sea del markup que
        // esta init ya modifico. Al re-renderizar reproduce la barra y el
        // contenedor de columnas, y como el nodo es nuevo (sin la marca _ub)
        // volvemos a entrar aca: sin esta limpieza el campo sale DUPLICADO.
        // Las tres columnas se conservan por referencia y se re-insertan abajo.
        pop.querySelectorAll('.ubigeo-bar, .ubigeo-cols, .ubigeo-results, .ub-manual-toggle, .ub-manual').forEach(function (el) { el.remove(); });
        [cDep, cProv, cDist].forEach(function (c) { c.innerHTML = ''; });

        // ── Barra de búsqueda ───────────────────────────────────────────
        var bar = document.createElement('div'); bar.className = 'ubigeo-bar';
        var search = document.createElement('input'); search.type = 'text'; search.className = 'ubigeo-search';
        search.placeholder = 'Busca ciudad, provincia o distrito…'; search.autocomplete = 'off';
        search.setAttribute('enterkeyhint', 'search');
        bar.appendChild(search);
        var hint = document.createElement('div'); hint.className = 'ubigeo-hint';
        hint.textContent = 'Escribe el nombre del lugar. No necesitas saber el departamento.';
        bar.appendChild(hint);

        // ── Resultados ──────────────────────────────────────────────────
        var results = document.createElement('div'); results.className = 'ubigeo-results'; results.hidden = true;

        // ── Selector manual (las 3 columnas de siempre), plegado ─────────
        var manualBtn = document.createElement('div'); manualBtn.className = 'ub-manual-toggle';
        manualBtn.innerHTML = '<span>O elegir por <b>departamento → provincia → distrito</b></span><span>▾</span>';
        var cols = document.createElement('div'); cols.className = 'ubigeo-cols ub-manual'; cols.hidden = true;
        cProv.setAttribute('data-empty', 'Elige un departamento');
        cDist.setAttribute('data-empty', 'Elige una provincia');
        cols.appendChild(cDep); cols.appendChild(cProv); cols.appendChild(cDist);

        pop.appendChild(bar); pop.appendChild(results); pop.appendChild(manualBtn); pop.appendChild(cols);

        function abrirManual() {
            cols.hidden = false;
            manualBtn.querySelector('span:last-child').textContent = '▴';
            ubRender(cDep, UB_DEPTS, pickDep, sel.dep);
        }
        function cerrarManual() {
            cols.hidden = true;
            manualBtn.querySelector('span:last-child').textContent = '▾';
        }
        manualBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (cols.hidden) abrirManual(); else cerrarManual();
        });

        function setValue(dep, depN, prov, provN, dist, distN, labelOverride) {
            hDep.value = dep || ''; hProv.value = prov || ''; hDist.value = dist || '';
            if (dist) { disp.textContent = labelOverride || (depN + ' / ' + provN + ' / ' + distN); disp.classList.add('has-value'); }
            else { disp.textContent = PH; disp.classList.remove('has-value'); }
        }

        var sel = { dep: '', depN: '', prov: '', provN: '', dist: '', distN: '' };

        // ── Cascada manual ──────────────────────────────────────────────
        function pickDep(it) {
            sel.dep = it.id; sel.depN = it.description; hDep.value = it.id; hProv.value = ''; hDist.value = ''; sel.prov = sel.dist = ''; cDist.innerHTML = '';
            ubJSON(UB_PROV + '/' + it.id).then(function (x) { ubRender(cProv, x, pickProv, sel.prov); });
        }
        function pickProv(it) {
            sel.prov = it.id; sel.provN = it.description; hProv.value = it.id; hDist.value = ''; sel.dist = '';
            ubJSON(UB_DIST + '/' + it.id).then(function (x) { ubRender(cDist, x, pickDist, sel.dist); });
        }
        function pickDist(it) { sel.dist = it.id; sel.distN = it.description; setValue(sel.dep, sel.depN, sel.prov, sel.provN, sel.dist, sel.distN); pop.hidden = true; }

        // ── Elegir un resultado de la búsqueda ──────────────────────────
        //
        // Antes esto sólo escribia los inputs ocultos y dejaba `sel` en
        // blanco: el envio se guardaba bien, pero al reabrir el popup no
        // habia nada marcado y el campo parecia vacio. Ahora `sel` queda
        // sincronizado y las columnas manuales muestran la seleccion.
        function elegir(r) {
            sel = {
                dep: r.department_id || '', depN: r.department_name || '',
                prov: r.province_id || '',  provN: r.province_name || '',
                dist: r.district_id || '',  distN: r.name || ''
            };
            setValue(sel.dep, sel.depN, sel.prov, sel.provN, sel.dist, sel.distN,
                     r.name + ' — ' + (r.province_name || '') + ', ' + (r.department_name || ''));
            pop.hidden = true;
        }

        // ── Pintado de resultados ───────────────────────────────────────
        var filas = [];      // filas navegables con teclado
        var cursor = -1;

        function marcar(i) {
            filas.forEach(function (f) { f.el.classList.remove('active'); });
            cursor = i;
            if (i >= 0 && filas[i]) {
                filas[i].el.classList.add('active');
                filas[i].el.scrollIntoView({ block: 'nearest' });
            }
        }

        function fila(r, onPick) {
            var tag = r.type === 'province' ? 'Provincia' : (r.type === 'department' ? 'Departamento' : 'Distrito');
            var cls = r.type === 'province' ? ' is-prov' : (r.type === 'department' ? ' is-dep' : '');
            var drill = r.type !== 'district';

            var el = document.createElement('div');
            el.className = 'ub-row';
            el.innerHTML =
                '<div class="ub-row-main">' +
                    '<div class="ub-row-name">' + ubEsc(r.name) + '</div>' +
                    '<div class="ub-row-ctx">' + ubEsc(r.context) + (drill ? ubEsc(cuantos(r)) : '') + '</div>' +
                '</div>' +
                '<span class="ub-tag' + cls + '">' + tag + '</span>' +
                (drill ? '<span class="ub-chevron">›</span>' : '');
            el.addEventListener('click', function (e) { e.stopPropagation(); onPick(); });
            return el;
        }

        function cuantos(r) {
            if (r.type === 'province' && r.district_count) return ' · ' + r.district_count + ' distritos';
            if (r.type === 'department' && r.province_count) return ' · ' + r.province_count + ' provincias';
            return '';
        }

        function pintar(rows) {
            results.innerHTML = ''; filas = []; cursor = -1;

            rows.forEach(function (r) {
                var el;
                if (r.type === 'district') {
                    el = fila(r, function () { elegir(r); });
                } else if (r.type === 'province') {
                    el = fila(r, function () { abrirProvincia(r); });
                } else {
                    el = fila(r, function () { abrirDepartamento(r); });
                }
                results.appendChild(el);
                filas.push({ el: el });
            });

            results.hidden = false;
        }

        function cabecera(texto, volver) {
            var h = document.createElement('div'); h.className = 'ub-head';
            var b = document.createElement('span'); b.className = 'ub-back'; b.textContent = '‹ Volver';
            b.addEventListener('click', function (e) { e.stopPropagation(); volver(); });
            var t = document.createElement('span'); t.textContent = texto;
            h.appendChild(b); h.appendChild(t);
            return h;
        }

        // Una provincia no es un destino: el envio necesita un distrito.
        // Por eso pulsarla abre sus distritos en vez de cerrar el popup.
        function abrirProvincia(r) {
            ubJSON(UB_DIST + '/' + r.province_id).then(function (list) {
                results.innerHTML = ''; filas = []; cursor = -1;
                results.appendChild(cabecera('Distritos de ' + r.name, buscar));
                (list || []).forEach(function (d) {
                    var row = {
                        type: 'district', district_id: d.id, province_id: r.province_id,
                        department_id: r.department_id, name: d.description,
                        province_name: r.name, department_name: r.department_name,
                        context: 'Distrito · ' + r.name + ' · ' + (r.department_name || '')
                    };
                    var el = fila(row, function () { elegir(row); });
                    results.appendChild(el); filas.push({ el: el });
                });
                results.hidden = false;
            });
        }

        function abrirDepartamento(r) {
            ubJSON(UB_PROV + '/' + r.department_id).then(function (list) {
                results.innerHTML = ''; filas = []; cursor = -1;
                results.appendChild(cabecera('Provincias de ' + r.name, buscar));
                (list || []).forEach(function (p) {
                    var row = {
                        type: 'province', province_id: p.id, department_id: r.department_id,
                        name: p.description, province_name: p.description,
                        department_name: r.name, context: 'Provincia · ' + r.name
                    };
                    var el = fila(row, function () { abrirProvincia(row); });
                    results.appendChild(el); filas.push({ el: el });
                });
                results.hidden = false;
            });
        }

        // "Sin resultados" no le dice al cliente qué hacer. Esto sí: repite
        // lo que escribio, sugiere revisar, y le ofrece las dos salidas.
        function vacio(q) {
            results.innerHTML = '';
            var v = document.createElement('div'); v.className = 'ub-void';
            v.innerHTML =
                '<div class="ub-void-t">No encontramos “' + ubEsc(q) + '”</div>' +
                '<div class="ub-void-s">Revisa la escritura o prueba con menos letras.<br>' +
                'También puedes elegir tu ubicación por departamento.</div>' +
                '<div class="ub-void-acts">' +
                    '<button type="button" class="ub-btn" data-act="limpiar">Limpiar búsqueda</button>' +
                    '<button type="button" class="ub-btn" data-act="manual">Elegir por departamento</button>' +
                '</div>';
            v.querySelector('[data-act="limpiar"]').addEventListener('click', function (e) {
                e.stopPropagation(); search.value = ''; search.focus(); reposo();
            });
            v.querySelector('[data-act="manual"]').addEventListener('click', function (e) {
                e.stopPropagation(); abrirManual(); cols.scrollIntoView({ block: 'nearest' });
            });
            results.appendChild(v);
            results.hidden = false;
            filas = []; cursor = -1;
        }

        function reposo() {
            results.hidden = true; results.innerHTML = ''; filas = []; cursor = -1;
        }

        // ── Búsqueda ────────────────────────────────────────────────────
        var st = null, seq = 0;
        function buscar() {
            var q = search.value.trim();
            if (q.length < 2) { reposo(); return; }
            var mio = ++seq;
            ubJSON(UB_SEARCH + '?v=2&q=' + encodeURIComponent(q)).then(function (list) {
                // Respuesta de una pulsacion anterior que llego tarde: si la
                // pintamos, el cliente ve resultados de lo que ya borro.
                if (mio !== seq) return;
                if (!list || !list.length) { vacio(q); return; }
                pintar(list);
            });
        }

        search.addEventListener('input', function () {
            clearTimeout(st);
            st = setTimeout(buscar, 250);
        });

        // Teclado: el que escribe rapido no quiere soltar el teclado para
        // apuntar con el mouse, y en celular Enter cierra sin mas vueltas.
        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (filas.length) marcar(Math.min(cursor + 1, filas.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (filas.length) marcar(Math.max(cursor - 1, 0)); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(st);
                if (cursor >= 0 && filas[cursor]) filas[cursor].el.click();
                else if (filas.length) filas[0].el.click();
                else buscar();
            }
            else if (e.key === 'Escape') { pop.hidden = true; }
        });

        // Abrir/cerrar NO se ata al nodo: lo llama la delegacion de abajo.
        field._toggle = function () {
            var wasOpen = !pop.hidden;
            ubCerrarTodos();
            if (!wasOpen) {
                pop.hidden = false;
                search.value = ''; reposo(); cerrarManual();
                setTimeout(function () { search.focus(); }, 30);
            }
        };

        // Preset (edición o autocompletado por DNI/RUC).
        field._preset = function (dep, prov, dist) {
            if (!dep) { setValue('', '', '', '', '', ''); return; }
            var dObj = UB_DEPTS.filter(function (x) { return String(x.id) === String(dep); })[0];
            var depN = dObj ? dObj.description : dep;
            if (!prov || !dist) { setValue(dep, depN, prov, '', '', ''); }
            ubJSON(UB_PROV + '/' + dep).then(function (provs) {
                var pObj = provs.filter(function (x) { return String(x.id) === String(prov); })[0];
                var provN = pObj ? pObj.description : prov;
                if (!dist) { setValue(dep, depN, prov, provN, '', ''); return; }
                ubJSON(UB_DIST + '/' + prov).then(function (dists) {
                    var tObj = dists.filter(function (x) { return String(x.id) === String(dist); })[0];
                    var distN = tObj ? tObj.description : dist;
                    sel = { dep: dep, depN: depN, prov: prov, provN: provN, dist: dist, distN: distN };
                    setValue(dep, depN, prov, provN, dist, distN);
                });
            });
        };
    }

    window.__ubInitAll = function () { document.querySelectorAll('.ubigeo-field').forEach(ubInit); };
    window.__ubPreset  = function (group, dep, prov, dist) {
        var f = document.querySelector('.ubigeo-field[data-ubigeo-group="' + group + '"]');
        if (f) { ubInit(f); if (f._preset) f._preset(dep, prov, dist); }
    };

    // ── Un solo listener en `document`, y la inicializacion es perezosa ──────
    //
    // En el PANEL el ERP monta Vue sobre #main-wrapper, que envuelve toda la
    // pagina, y al re-renderizar reemplaza estos nodos: un listener atado al
    // .ubigeo-display se pierde y el campo queda mudo. Por eso el ubigeo
    // funcionaba en la ficha publica (otro layout, sin Vue) y no en el modal
    // "Registrar envio". Delegando en `document` y llamando a ubInit() al
    // abrir, el campo revive solo despues de cada re-render.
    // Mismo motivo que en logistics-js y mobile-fold-js.
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        // Dentro del popup manda el widget (elegir, buscar): no tocar nada.
        if (t.closest('.ubigeo-pop')) return;
        var disp = t.closest('.ubigeo-display');
        if (!disp) { ubCerrarTodos(); return; }
        var field = disp.closest('.ubigeo-field');
        if (!field) return;
        ubInit(field);                       // idempotente: se salta si ya vive
        if (field._toggle) field._toggle();
    });
    window.__ubInitAll();
})();
</script>
