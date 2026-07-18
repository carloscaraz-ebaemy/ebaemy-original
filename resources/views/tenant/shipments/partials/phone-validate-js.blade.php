{{-- Validación en vivo de celular peruano (9 dígitos, empieza en 9) para
     cualquier input con clase .js-phone-pe. Muestra el error en un
     <small class="js-phone-err"> hermano y bloquea el envío si es inválido. --}}
<script>
(function () {
    function errEl(inp) {
        var n = inp.nextElementSibling;
        return (n && n.classList && n.classList.contains('js-phone-err')) ? n : null;
    }
    function check(inp, showEmpty) {
        var d = (inp.value || '').replace(/\D+/g, '');
        var empty = d.length === 0;
        var ok = d.length === 9 && d[0] === '9';
        var bad = (!empty && !ok) || (showEmpty && empty);
        inp.style.borderColor = bad ? '#dc2626' : '';
        var e = errEl(inp);
        if (e) e.textContent = bad ? (empty ? 'Ingresa tu celular.' : 'Debe ser un celular de 9 dígitos (empieza en 9).') : '';
        return !bad;
    }
    document.addEventListener('input', function (ev) {
        var inp = ev.target;
        if (!inp.classList || !inp.classList.contains('js-phone-pe')) return;
        var d = inp.value.replace(/\D+/g, '').slice(0, 9);
        if (inp.value !== d) inp.value = d;
        check(inp, false);
    });
    // Bloquear envío si algún teléfono es inválido (fase de captura).
    document.addEventListener('submit', function (ev) {
        var form = ev.target, bad = false, focused = false;
        Array.prototype.forEach.call(form.querySelectorAll('.js-phone-pe'), function (inp) {
            if (!check(inp, inp.required)) { bad = true; if (!focused) { inp.focus(); focused = true; } }
        });
        if (bad) { ev.preventDefault(); ev.stopPropagation(); }
    }, true);
})();
</script>
