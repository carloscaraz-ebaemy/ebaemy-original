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
    var timer = null, ctrl = null, abierto = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* Resalta lo tecleado dentro del nombre: con nombres largos y parecidos
       ("Cerezo ROJO 180cm", "Cerezo ROSADO 180cm") es lo que permite
       distinguirlos de un vistazo. */
    function marcar(texto, palabras) {
        var utiles = palabras.filter(function (p) { return p.length >= 2; })
            .map(function (p) { return p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
        var out = esc(texto);
        if (!utiles.length) return out;
        // Una sola pasada con todas las palabras: si se hiciera un replace por
        // palabra, la segunda encontraria el <mark> que dejo la primera.
        return out.replace(new RegExp('(' + utiles.join('|') + ')', 'ig'), '<mark>$1</mark>');
    }

    function cerrar() {
        document.querySelectorAll('.sh-pick__res').forEach(function (r) {
            r.hidden = true; r.innerHTML = '';
        });
        document.querySelectorAll('.sh-pick__q').forEach(function (q) {
            q.setAttribute('aria-expanded', 'false');
        });
        abierto = null;
    }

    /* La lista se pinta con position:fixed, así que hay que ubicarla a mano
       bajo el campo — y voltearla hacia arriba si no entra abajo. */
    function ubicar(pick) {
        var campo = pick.querySelector('.sh-pick__field') || pick;
        var box   = pick.querySelector('.sh-pick__res');
        if (!box || box.hidden) return;

        var r = campo.getBoundingClientRect();
        box.style.width = r.width + 'px';
        box.style.left  = r.left + 'px';

        var alto   = box.offsetHeight || 240;
        var abajo  = window.innerHeight - r.bottom;
        if (abajo < alto + 12 && r.top > abajo) {
            box.style.top = Math.max(8, r.top - alto - 4) + 'px';   // hacia arriba
        } else {
            box.style.top = (r.bottom + 4) + 'px';
        }
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

    function render(pick, rows, term) {
        var box = pick.querySelector('.sh-pick__res');
        if (!box) return;
        var q = pick.querySelector('.sh-pick__q');

        if (!rows.length) {
            box.innerHTML = '<div class="sh-pick__empty">Sin resultados para <strong>'
                          + esc(term) + '</strong>. Puedes escribirlo a mano abajo.</div>';
        } else {
            var palabras = term.split(/\s+/).filter(Boolean);
            box.innerHTML = '<div class="sh-pick__hint">Enter agrega · ↑↓ para elegir</div>'
                + rows.map(function (r, i) {
                    var sinStock = (r.stock !== null && r.stock <= 0);
                    return '<button type="button" data-name="' + esc(r.name) + '"'
                         + ' class="sh-pick__i' + (i === 0 ? ' is-on' : '')
                         + (r.off ? ' sh-pick__i--off' : '') + '">'
                         + '<span class="sh-pick__n">'
                         +   '<span class="sh-pick__t">' + marcar(r.name, palabras) + '</span>'
                         +   (r.code ? '<span class="sh-pick__c">' + marcar(r.code, palabras)
                                     + (r.off ? ' · inactivo' : '') + '</span>' : '')
                         + '</span>'
                         + (r.stock === null ? ''
                            : '<span class="sh-pick__s' + (sinStock ? ' sh-pick__s--no' : '') + '">'
                              + (sinStock ? 'sin stock' : r.stock + ' u.') + '</span>')
                         + '</button>';
                }).join('');
        }

        box.hidden = false;
        if (q) q.setAttribute('aria-expanded', 'true');
        abierto = pick;
        box.scrollTop = 0;
        ubicar(pick);
    }

    function mover(pick, paso) {
        var items = Array.prototype.slice.call(pick.querySelectorAll('.sh-pick__i'));
        if (!items.length) return;
        var i = items.findIndex(function (el) { return el.classList.contains('is-on'); });
        items.forEach(function (el) { el.classList.remove('is-on'); });
        var next = items[Math.max(0, Math.min(items.length - 1, (i < 0 ? 0 : i) + paso))];
        next.classList.add('is-on');
        next.scrollIntoView({ block: 'nearest' });
    }

    function elegir(pick, item) {
        var qty = pick.querySelector('.sh-pick__qty');
        addLine(pick, item.getAttribute('data-name'), qty ? qty.value : 1);

        // Listo para agregar el siguiente: se limpia y se vuelve al buscador.
        var q = pick.querySelector('.sh-pick__q');
        if (q) { q.value = ''; q.focus(); }
        if (qty) qty.value = 1;
        cerrar();
    }

    // Escribir → buscar (con espera para no consultar en cada tecla).
    document.addEventListener('input', function (ev) {
        var input = ev.target;
        if (!input.classList || !input.classList.contains('sh-pick__q')) return;

        var pick = input.closest('.sh-pick');
        var term = input.value.trim();

        clearTimeout(timer);
        if (term.length < 2) { cerrar(); return; }

        timer = setTimeout(function () {
            if (ctrl) { try { ctrl.abort(); } catch (e) {} }
            ctrl = ('AbortController' in window) ? new AbortController() : null;

            fetch(URL_BUSCAR + '?q=' + encodeURIComponent(term), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined
            })
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                // Puede haber cambiado lo tecleado mientras respondía.
                if (input.value.trim() === term) render(pick, rows || [], term);
            })
            .catch(function () {});
        }, 280);
    });

    document.addEventListener('click', function (ev) {
        var item = ev.target.closest ? ev.target.closest('.sh-pick__i') : null;
        if (item) {
            ev.preventDefault();
            elegir(item.closest('.sh-pick'), item);
            return;
        }
        // Clic fuera: cerrar los resultados abiertos.
        if (!ev.target.closest || !ev.target.closest('.sh-pick')) cerrar();
    });

    document.addEventListener('keydown', function (ev) {
        if (!ev.target.classList || !ev.target.classList.contains('sh-pick__q')) return;
        var pick = ev.target.closest('.sh-pick');
        var res  = pick.querySelector('.sh-pick__res');
        var hay  = res && !res.hidden;

        if (ev.key === 'ArrowDown' && hay) { ev.preventDefault(); mover(pick, 1); }
        else if (ev.key === 'ArrowUp' && hay) { ev.preventDefault(); mover(pick, -1); }
        else if (ev.key === 'Escape' && hay) { ev.preventDefault(); cerrar(); }
        else if (ev.key === 'Enter') {
            // Nunca debe enviar el formulario del modal.
            ev.preventDefault();
            var sel = hay ? pick.querySelector('.sh-pick__i.is-on') : null;
            if (sel) elegir(pick, sel);
        }
    });

    // Al cerrar el modal la lista quedaria flotando sobre la pantalla, porque
    // esta posicionada de forma fija y no cuelga visualmente del modal.
    document.addEventListener('hidden.bs.modal', cerrar);

    // La lista va fija: si algo se desplaza debajo, hay que reubicarla.
    window.addEventListener('scroll', function () { if (abierto) ubicar(abierto); }, true);
    window.addEventListener('resize', function () { if (abierto) ubicar(abierto); });
})();
</script>
