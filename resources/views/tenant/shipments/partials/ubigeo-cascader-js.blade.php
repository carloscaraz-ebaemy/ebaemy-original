{{-- Widget cascader de ubigeo: 1 campo → popup con 3 columnas
     (Departamento → Provincia → Distrito). Reusable en el formulario público
     y en los modales del panel. Requiere $departments en el scope. --}}
<script>
(function () {
    var UB_DEPTS = {!! json_encode($departments->map(function ($d) { return ['id' => $d->id, 'description' => $d->description]; })->values()) !!};
    var UB_PROV  = '{{ url("envio/ubigeo/provincias") }}';
    var UB_DIST  = '{{ url("envio/ubigeo/distritos") }}';

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
                d.classList.add('active');
                onClick(it);
            });
            col.appendChild(d);
        });
    }
    function ubInit(field) {
        if (field._ub) return; field._ub = true;
        var disp = field.querySelector('.ubigeo-display'), pop = field.querySelector('.ubigeo-pop');
        var cDep = field.querySelector('[data-col="dep"]'), cProv = field.querySelector('[data-col="prov"]'), cDist = field.querySelector('[data-col="dist"]');
        var hDep = field.querySelector('[data-ub="department"]'), hProv = field.querySelector('[data-ub="province"]'), hDist = field.querySelector('[data-ub="district"]');
        var ch = { dep: '', depN: '', prov: '', provN: '', dist: '', distN: '' };
        var PH = 'Seleccionar departamento / provincia / distrito…';

        function draw() {
            if (ch.dist) { disp.textContent = ch.depN + ' / ' + ch.provN + ' / ' + ch.distN; disp.classList.add('has-value'); }
            else { disp.textContent = PH; disp.classList.remove('has-value'); }
        }
        function pickDep(it) {
            ch.dep = it.id; ch.depN = it.description; hDep.value = it.id;
            hProv.value = ''; hDist.value = ''; ch.prov = ch.dist = ''; cDist.innerHTML = '';
            ubJSON(UB_PROV + '/' + it.id).then(function (x) { ubRender(cProv, x, pickProv, ch.prov); });
        }
        function pickProv(it) {
            ch.prov = it.id; ch.provN = it.description; hProv.value = it.id; hDist.value = ''; ch.dist = '';
            ubJSON(UB_DIST + '/' + it.id).then(function (x) { ubRender(cDist, x, pickDist, ch.dist); });
        }
        function pickDist(it) { ch.dist = it.id; ch.distN = it.description; hDist.value = it.id; draw(); pop.hidden = true; }

        disp.addEventListener('click', function (e) {
            e.stopPropagation();
            var wasOpen = !pop.hidden;
            document.querySelectorAll('.ubigeo-pop').forEach(function (p) { p.hidden = true; });
            if (!wasOpen) { pop.hidden = false; ubRender(cDep, UB_DEPTS, pickDep, ch.dep); }
        });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });

        // Preset (edición o autocompletado por DNI/RUC).
        field._preset = function (dep, prov, dist) {
            hDep.value = dep || ''; hProv.value = prov || ''; hDist.value = dist || '';
            ch = { dep: dep || '', depN: '', prov: prov || '', provN: '', dist: dist || '', distN: '' };
            if (!dep) { draw(); return; }
            var dObj = UB_DEPTS.filter(function (x) { return String(x.id) === String(dep); })[0];
            ch.depN = dObj ? dObj.description : dep;
            if (!prov) { draw(); return; }
            ubJSON(UB_PROV + '/' + dep).then(function (provs) {
                var pObj = provs.filter(function (x) { return String(x.id) === String(prov); })[0];
                ch.provN = pObj ? pObj.description : prov;
                if (!dist) { draw(); return; }
                ubJSON(UB_DIST + '/' + prov).then(function (dists) {
                    var tObj = dists.filter(function (x) { return String(x.id) === String(dist); })[0];
                    ch.distN = tObj ? tObj.description : dist; draw();
                });
            });
        };
        draw();
    }

    window.__ubInitAll = function () { document.querySelectorAll('.ubigeo-field').forEach(ubInit); };
    window.__ubPreset  = function (group, dep, prov, dist) {
        var f = document.querySelector('.ubigeo-field[data-ubigeo-group="' + group + '"]');
        if (f) { if (!f._ub) ubInit(f); if (f._preset) f._preset(dep, prov, dist); }
    };
    // Cerrar popups al hacer clic fuera.
    document.addEventListener('click', function () { document.querySelectorAll('.ubigeo-pop').forEach(function (p) { p.hidden = true; }); });
    window.__ubInitAll();
})();
</script>
