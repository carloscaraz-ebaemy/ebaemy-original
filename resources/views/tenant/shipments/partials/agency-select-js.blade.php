{{-- Desplegable de agencia con opción "Otra…". Sincroniza un <select
     class="agency-select"> con el <input class="agency-input" name="shipping_agency">
     dentro de un contenedor .agency-field. Expone window.__syncAgency(). --}}
<script>
(function () {
    function optionExists(sel, val) {
        return Array.prototype.some.call(sel.options, function (o) { return o.value === val; });
    }
    function syncField(field) {
        var sel = field.querySelector('.agency-select'), inp = field.querySelector('.agency-input');
        if (!sel || !inp) return;
        var v = (inp.value || '').trim();
        if (v && v !== '__otra__' && optionExists(sel, v)) { sel.value = v; inp.style.display = 'none'; }
        else if (v) { sel.value = '__otra__'; inp.style.display = ''; }
        else { sel.value = ''; inp.style.display = 'none'; }
    }
    document.addEventListener('change', function (ev) {
        var sel = ev.target;
        if (!sel.classList || !sel.classList.contains('agency-select')) return;
        var field = sel.closest('.agency-field'); if (!field) return;
        var inp = field.querySelector('.agency-input'); if (!inp) return;
        if (sel.value === '__otra__') { inp.style.display = ''; inp.value = ''; inp.focus(); }
        else { inp.value = sel.value; inp.style.display = 'none'; }
    });
    window.__syncAgency = function () { document.querySelectorAll('.agency-field').forEach(syncField); };
    window.__syncAgency();
})();
</script>
