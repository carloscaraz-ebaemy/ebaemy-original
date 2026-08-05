<script>
/*
 * VISTA RÁPIDA DEL DETALLE DE PRODUCTO
 *
 * Al recorrer la tabla con el mouse, la fila que tenga contenido asoma sus
 * ítems en una tarjeta flotante. No es un modal: no bloquea, no hay que
 * cerrarla y no interrumpe lo que el operador está haciendo.
 *
 * POSICIONAMIENTO — es la parte delicada:
 *   · La tarjeta se cuelga del <body> con position:fixed. Dentro de
 *     .table-responsive quedaría recortada por el overflow (mismo motivo por
 *     el que los dropdowns de esta tabla usan estrategia fija).
 *   · NUNCA debe invadir el menú lateral: el borde izquierdo se limita al
 *     área del panel, no al viewport. Sin ese límite terminaba montada sobre
 *     la navegación, que se veía descuidado.
 *   · Orden de preferencia: a la derecha de la fila → a la izquierda (si cabe
 *     dentro del panel) → debajo/encima, alineada al panel.
 *
 * Todo por delegación en `document`: la tabla se reemplaza por AJAX en cada
 * filtro y acción, así que no se pueden guardar referencias a las filas.
 */
(function () {
    var card = null, arrow = null, hideTimer = null, current = null;
    var GAP = 12, EDGE = 10;

    function ensureCard() {
        if (card && document.body.contains(card)) return card;
        card = document.createElement('div');
        card.className = 'sh-peek';
        card.setAttribute('role', 'tooltip');
        document.body.appendChild(card);
        return card;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /** Borde izquierdo permitido: el área de contenido, nunca el menú. */
    function contentLeft() {
        var panel = document.getElementById('shPanel') || document.querySelector('.table-responsive');
        return panel ? Math.max(EDGE, panel.getBoundingClientRect().left) : EDGE;
    }

    function hide() {
        if (card) card.classList.remove('is-on');
        if (current) { current.classList.remove('sh-peek-on'); current = null; }
    }

    function place(el, tr) {
        var r    = tr.getBoundingClientRect();
        var cw   = el.offsetWidth, ch = el.offsetHeight;
        var vw   = window.innerWidth, vh = window.innerHeight;
        var minL = contentLeft();

        var left, top, side = null;

        if (r.right + GAP + cw <= vw - EDGE) {
            left = r.right + GAP;  side = 'right';          // preferido
        } else if (r.left - GAP - cw >= minL) {
            left = r.left - GAP - cw;  side = 'left';
        } else {
            // No cabe a los lados sin pisar el menú: se pone debajo (o encima)
            // alineada al inicio del contenido.
            left = Math.min(Math.max(minL, r.left), vw - cw - EDGE);
        }

        if (side) {
            top = r.top + (r.height / 2) - (ch / 2);
        } else {
            top = (r.bottom + GAP + ch <= vh - EDGE) ? r.bottom + GAP : r.top - GAP - ch;
        }

        top = Math.min(Math.max(EDGE, top), Math.max(EDGE, vh - ch - EDGE));

        el.style.left = Math.round(left) + 'px';
        el.style.top  = Math.round(top) + 'px';

        var below = !side && top > r.top;   // quedó debajo de la fila

        el.classList.remove('sh-peek--from-right', 'sh-peek--from-left', 'sh-peek--from-below', 'sh-peek--below');
        el.classList.add(side === 'right' ? 'sh-peek--from-right'
                       : side === 'left'  ? 'sh-peek--from-left'
                       : 'sh-peek--from-below');
        if (below) el.classList.add('sh-peek--below');

        // Punta apuntando a la fila de origen.
        if (arrow) {
            arrow.style.cssText = 'position:absolute;width:10px;height:10px;transform:rotate(45deg);';
            if (side) {
                var ay = Math.min(Math.max(10, r.top + r.height / 2 - top - 5), ch - 20);
                arrow.style.top = Math.round(ay) + 'px';
                arrow.style.background = '#fff';
                if (side === 'right') { arrow.style.left = '-6px';  arrow.style.borderLeft = '1px solid var(--sh-brand-line)'; arrow.style.borderTop = '1px solid var(--sh-brand-line)'; }
                else                  { arrow.style.right = '-6px'; arrow.style.borderRight = '1px solid var(--sh-brand-line)'; arrow.style.borderBottom = '1px solid var(--sh-brand-line)'; }
            } else {
                // Arriba/abajo: alineada al chip 📦 de la fila, no al centro.
                var ax = Math.min(Math.max(18, r.left + 40 - left), 260);
                arrow.style.left = Math.round(ax) + 'px';
                if (below) {
                    arrow.style.top = '-6px';
                    arrow.style.background = 'var(--sh-brand-weak)';
                    arrow.style.borderLeft = '1px solid var(--sh-brand-line)';
                    arrow.style.borderTop  = '1px solid var(--sh-brand-line)';
                } else {
                    arrow.style.bottom = '-6px';
                    arrow.style.background = '#fff';
                    arrow.style.borderRight  = '1px solid var(--sh-brand-line)';
                    arrow.style.borderBottom = '1px solid var(--sh-brand-line)';
                }
            }
        }
    }

    function show(tr) {
        var raw = tr.getAttribute('data-peek');
        if (!raw) return;

        var items;
        try { items = JSON.parse(raw); } catch (e) { return; }
        if (!items || !items.length) return;

        var el = ensureCard();
        var bultos = tr.getAttribute('data-peek-bultos') || '1';

        el.innerHTML =
            '<span class="sh-peek__arrow"></span>' +
            '<div class="sh-peek__h">' +
                '<span class="sh-peek__code">' + esc(tr.getAttribute('data-peek-code') || 'Contenido') + '</span>' +
                '<span class="sh-peek__meta">' + items.length + (items.length === 1 ? ' ítem' : ' ítems') +
                    ' · ' + esc(bultos) + ' bulto' + (bultos === '1' ? '' : 's') + '</span>' +
            '</div>' +
            '<div class="sh-peek__b"><ol>' +
                items.map(function (i) { return '<li>' + esc(i) + '</li>'; }).join('') +
            '</ol></div>';

        arrow = el.querySelector('.sh-peek__arrow');

        // Se mide fuera de pantalla para poder posicionarla con su alto real.
        el.style.left = '-9999px';
        el.style.top  = '0px';
        el.classList.add('is-on');
        place(el, tr);

        if (current && current !== tr) current.classList.remove('sh-peek-on');
        tr.classList.add('sh-peek-on');
        current = tr;
    }

    document.addEventListener('mouseover', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (!tr) return;
        clearTimeout(hideTimer);
        if (tr !== current) show(tr);
    });

    document.addEventListener('mouseout', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (!tr) return;
        // Retardo corto: mouseout salta en cada <td>, sin esto parpadearía al
        // recorrer la misma fila.
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hide, 90);
    });

    // Al desplazar o redimensionar quedaría flotando donde estaba.
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);

    // Accesible por teclado: al enfocar un control de la fila también se asoma.
    document.addEventListener('focusin', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (tr) show(tr); else hide();
    });
})();
</script>
