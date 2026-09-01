{{-- Widget cascader de ubigeo: 1 campo → popup con BUSCADOR + 3 columnas
     (Departamento → Provincia → Distrito). Reusable en el formulario público
     y en los modales del panel. Requiere $departments en el scope. --}}
<style>
    .ubigeo-field { position: relative; }
    .ubigeo-display { border: 1px solid #dee2e6; border-radius: .5rem; padding: 10px 12px; cursor: pointer; background: #fff; color: #6c757d; font-size: 14px; min-height: 40px; }
    .ubigeo-display.has-value { color: #212529; font-weight: 500; }
    .ubigeo-pop { position: absolute; z-index: 5000; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid #dee2e6; border-radius: .6rem; box-shadow: 0 14px 36px -10px rgba(15,23,42,.3); overflow: hidden; }
    .ubigeo-bar { padding: 8px; border-bottom: 1px solid #f1f3f5; }
    .ubigeo-search { width: 100%; border: 1px solid #dee2e6; border-radius: .4rem; padding: 8px 10px; font-size: 13px; outline: none; }
    .ubigeo-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .ubigeo-cols { display: flex; }
    .ubigeo-col { flex: 1; min-width: 33%; max-height: 220px; overflow-y: auto; border-right: 1px solid #f1f3f5; }
    .ubigeo-col:last-child { border-right: none; }
    .ubigeo-results { max-height: 260px; overflow-y: auto; }
    .ubigeo-item { padding: 9px 11px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f8f9fa; white-space: nowrap; }
    .ubigeo-item:hover, .ubigeo-item.active { background: #eef2ff; color: #4f46e5; font-weight: 600; }
    /* Columna vacía: en vez de un guion mudo, dice qué falta elegir. */
    .ubigeo-col:empty::before { content: attr(data-empty); display: block; text-align: center; color: #adb5bd; padding: 16px 10px; font-size: 12px; line-height: 1.4; }
    .ubigeo-hint { padding: 0 8px 8px; font-size: 11.5px; color: #adb5bd; }
    .ubigeo-empty { padding: 14px; text-align: center; color: #adb5bd; font-size: 13px; }
    @media (max-width: 520px) { .ubigeo-cols { overflow-x: auto; } .ubigeo-col { min-width: 130px; } }

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
        var PH = 'Seleccionar departamento / provincia / distrito…';

        // Vue toma su plantilla del DOM VIVO al montar, o sea del markup que
        // esta init ya modifico. Al re-renderizar reproduce la barra y el
        // contenedor de columnas, y como el nodo es nuevo (sin la marca _ub)
        // volvemos a entrar aca: sin esta limpieza el campo sale DUPLICADO.
        // Las tres columnas se conservan por referencia y se re-insertan abajo.
        pop.querySelectorAll('.ubigeo-bar, .ubigeo-cols, .ubigeo-results').forEach(function (el) { el.remove(); });
        [cDep, cProv, cDist].forEach(function (c) { c.innerHTML = ''; });

        // Construir barra de búsqueda + envolver columnas + panel de resultados.
        var bar = document.createElement('div'); bar.className = 'ubigeo-bar';
        var search = document.createElement('input'); search.type = 'text'; search.className = 'ubigeo-search';
        search.placeholder = 'Busca tu distrito… (ej. Chiclayo)'; search.autocomplete = 'off';
        bar.appendChild(search);
        var hint = document.createElement('div'); hint.className = 'ubigeo-hint';
        hint.textContent = 'Escribe el nombre de tu distrito, o elígelo en las columnas.';
        bar.appendChild(hint);
        var cols = document.createElement('div'); cols.className = 'ubigeo-cols';
        // Las columnas 2 y 3 se llenan al elegir la anterior: hasta entonces
        // explican qué falta en vez de mostrar un guion.
        cProv.setAttribute('data-empty', 'Elige un departamento');
        cDist.setAttribute('data-empty', 'Elige una provincia');
        cols.appendChild(cDep); cols.appendChild(cProv); cols.appendChild(cDist);
        var results = document.createElement('div'); results.className = 'ubigeo-results'; results.hidden = true;
        pop.appendChild(bar); pop.appendChild(cols); pop.appendChild(results);

        function setValue(dep, depN, prov, provN, dist, distN, labelOverride) {
            hDep.value = dep || ''; hProv.value = prov || ''; hDist.value = dist || '';
            if (dist) { disp.textContent = labelOverride || (depN + ' / ' + provN + ' / ' + distN); disp.classList.add('has-value'); }
            else { disp.textContent = PH; disp.classList.remove('has-value'); }
        }

        var sel = { dep: '', depN: '', prov: '', provN: '', dist: '', distN: '' };
        function pickDep(it) {
            sel.dep = it.id; sel.depN = it.description; hDep.value = it.id; hProv.value = ''; hDist.value = ''; sel.prov = sel.dist = ''; cDist.innerHTML = '';
            ubJSON(UB_PROV + '/' + it.id).then(function (x) { ubRender(cProv, x, pickProv, sel.prov); });
        }
        function pickProv(it) {
            sel.prov = it.id; sel.provN = it.description; hProv.value = it.id; hDist.value = ''; sel.dist = '';
            ubJSON(UB_DIST + '/' + it.id).then(function (x) { ubRender(cDist, x, pickDist, sel.dist); });
        }
        function pickDist(it) { sel.dist = it.id; sel.distN = it.description; setValue(sel.dep, sel.depN, sel.prov, sel.provN, sel.dist, sel.distN); pop.hidden = true; }

        // Buscador por texto (distrito) → resultados planos.
        var st = null;
        search.addEventListener('input', function () {
            clearTimeout(st);
            var q = search.value.trim();
            if (q.length < 2) { results.hidden = true; cols.style.display = ''; return; }
            st = setTimeout(function () {
                ubJSON(UB_SEARCH + '?q=' + encodeURIComponent(q)).then(function (list) {
                    results.innerHTML = '';
                    (list || []).forEach(function (it) {
                        var r = document.createElement('div'); r.className = 'ubigeo-item'; r.textContent = it.label;
                        r.addEventListener('click', function (e) {
                            e.stopPropagation();
                            setValue(it.department_id, '', it.province_id, '', it.district_id, '', it.label);
                            pop.hidden = true;
                        });
                        results.appendChild(r);
                    });
                    if (!list || !list.length) { results.innerHTML = '<div class="ubigeo-empty">Sin resultados</div>'; }
                    cols.style.display = 'none'; results.hidden = false;
                });
            }, 300);
        });

        // Abrir/cerrar NO se ata al nodo: lo llama la delegacion de abajo.
        field._toggle = function () {
            var wasOpen = !pop.hidden;
            ubCerrarTodos();
            if (!wasOpen) {
                pop.hidden = false;
                search.value = ''; results.hidden = true; cols.style.display = '';
                ubRender(cDep, UB_DEPTS, pickDep, sel.dep);
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
