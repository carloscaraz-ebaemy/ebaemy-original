<script>
/*
 * BUSCADOR DE PRODUCTOS PARA EL DETALLE DEL PAQUETE
 *
 * El detalle se escribía a mano y las faltas de tipeo terminaban impresas en
 * el rótulo. Esto permite tomar el nombre exacto del catálogo: se busca, se
 * elige y se agrega como una línea más del detalle.
 *
 * El campo sigue siendo un textarea libre a propósito: hay envíos con cosas
 * que no están en el catálogo ("duo #2", "colgantes boa"), y obligar a
 * elegir del sistema haría el formulario inservible para esos casos.
 *
 * Delegación en `document`: los modales se reconstruyen con el panel.
 */
(function () {
    var URL_BUSCAR = '{{ route('shipments.search_items') }}';
    var timer = null, ctrl = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function closeAll(except) {
        document.querySelectorAll('.sh-pick__res').forEach(function (r) {
            if (r !== except) { r.hidden = true; r.innerHTML = ''; }
        });
    }

    /** Agrega la línea al textarea del detalle, sin pisar lo ya escrito. */
    function addLine(pick, name, qty) {
        var ta = document.getElementById(pick.getAttribute('data-target'));
        if (!ta) return;

        // Cantidad con dos dígitos: es como el almacén ya lo escribe
        // ("01 palmera", "02 colgantes").
        var n = parseInt(qty, 10);
        var prefix = (isNaN(n) || n < 1) ? '01' : String(n).padStart(2, '0');
        var linea = prefix + ' ' + name;

        var actual = (ta.value || '').replace(/\s+$/, '');
        ta.value = actual ? (actual + '\n' + linea) : linea;

        // Que se vea lo recién agregado y quede listo para seguir editando.
        ta.scrollTop = ta.scrollHeight;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function render(pick, rows) {
        var box = pick.querySelector('.sh-pick__res');
        if (!box) return;

        if (!rows.length) {
            box.innerHTML = '<div class="sh-pick__empty">Sin resultados en el catálogo. '
                          + 'Puedes escribirlo a mano en el detalle.</div>';
            box.hidden = false;
            return;
        }

        box.innerHTML = rows.map(function (r) {
            var sinStock = (r.stock !== null && r.stock <= 0);
            return '<button type="button" class="sh-pick__i" data-name="' + esc(r.name) + '">'
                 + '<span class="sh-pick__n">' + esc(r.name)
                 + (r.code ? '<div class="sh-pick__c">' + esc(r.code) + '</div>' : '')
                 + '</span>'
                 + (r.stock === null ? ''
                    : '<span class="sh-pick__s' + (sinStock ? ' sh-pick__s--no' : '') + '">'
                      + (sinStock ? 'sin stock' : r.stock + ' u.') + '</span>')
                 + '</button>';
        }).join('');
        box.hidden = false;
    }

    // Escribir → buscar (con espera para no consultar en cada tecla).
    document.addEventListener('input', function (ev) {
        var input = ev.target;
        if (!input.classList || !input.classList.contains('sh-pick__q')) return;

        var pick = input.closest('.sh-pick');
        var term = input.value.trim();

        clearTimeout(timer);
        if (term.length < 2) { closeAll(); return; }

        timer = setTimeout(function () {
            if (ctrl) { try { ctrl.abort(); } catch (e) {} }
            ctrl = ('AbortController' in window) ? new AbortController() : null;

            fetch(URL_BUSCAR + '?q=' + encodeURIComponent(term), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined
            })
            .then(function (r) { return r.json(); })
            .then(function (rows) { render(pick, rows || []); })
            .catch(function () {});
        }, 280);
    });

    document.addEventListener('click', function (ev) {
        var item = ev.target.closest ? ev.target.closest('.sh-pick__i') : null;
        if (item) {
            ev.preventDefault();
            var pick = item.closest('.sh-pick');
            var qty  = pick.querySelector('.sh-pick__qty');
            addLine(pick, item.getAttribute('data-name'), qty ? qty.value : 1);

            // Listo para agregar el siguiente: se limpia y se vuelve al buscador.
            var q = pick.querySelector('.sh-pick__q');
            if (q) { q.value = ''; q.focus(); }
            if (qty) qty.value = 1;
            closeAll();
            return;
        }
        // Clic fuera: cerrar los resultados abiertos.
        if (!ev.target.closest || !ev.target.closest('.sh-pick')) closeAll();
    });

    // Enter en el buscador no debe enviar el formulario del modal.
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        if (!ev.target.classList || !ev.target.classList.contains('sh-pick__q')) return;
        ev.preventDefault();
        var first = ev.target.closest('.sh-pick').querySelector('.sh-pick__i');
        if (first) first.click();
    });
})();
</script>
