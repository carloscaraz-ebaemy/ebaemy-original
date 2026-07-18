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
    .ubigeo-col:empty::before { content: '—'; display: block; text-align: center; color: #ced4da; padding: 12px 0; font-size: 12px; }
    .ubigeo-empty { padding: 14px; text-align: center; color: #adb5bd; font-size: 13px; }
    @media (max-width: 520px) { .ubigeo-cols { overflow-x: auto; } .ubigeo-col { min-width: 130px; } }
</style>
<script>
(function () {
    var UB_DEPTS  = {!! json_encode($departments->map(function ($d) { return ['id' => $d->id, 'description' => $d->description]; })->values()) !!};
    var UB_PROV   = '{{ url("envio/ubigeo/provincias") }}';
    var UB_DIST   = '{{ url("envio/ubigeo/distritos") }}';
    var UB_SEARCH = '{{ url("envio/ubigeo/buscar") }}';

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

        // Construir barra de búsqueda + envolver columnas + panel de resultados.
        var bar = document.createElement('div'); bar.className = 'ubigeo-bar';
        var search = document.createElement('input'); search.type = 'text'; search.className = 'ubigeo-search'; search.placeholder = 'Escribe un distrito…'; search.autocomplete = 'off';
        bar.appendChild(search);
        var cols = document.createElement('div'); cols.className = 'ubigeo-cols';
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
        search.addEventListener('click', function (e) { e.stopPropagation(); });
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

        disp.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = !pop.hidden;
            document.querySelectorAll('.ubigeo-pop').forEach(function (p) { p.hidden = true; });
            if (!wasOpen) {
                pop.hidden = false;
                search.value = ''; results.hidden = true; cols.style.display = '';
                ubRender(cDep, UB_DEPTS, pickDep, sel.dep);
                setTimeout(function () { search.focus(); }, 30);
            }
        });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });

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
        if (f) { if (!f._ub) ubInit(f); if (f._preset) f._preset(dep, prov, dist); }
    };
    document.addEventListener('click', function () { document.querySelectorAll('.ubigeo-pop').forEach(function (p) { p.hidden = true; }); });
    window.__ubInitAll();
})();
</script>
