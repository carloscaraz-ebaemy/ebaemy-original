<script>
/*
 * VISTA RÁPIDA DEL DETALLE DE PRODUCTO
 *
 * Al recorrer la tabla con el mouse, la fila que tenga contenido asoma sus
 * ítems en una tarjeta flotante. No es un modal: no bloquea, no hay que
 * cerrarla y no interrumpe lo que el operador está haciendo.
 *
 * La tarjeta se cuelga del <body> con position:fixed a propósito. Dentro de
 * .table-responsive quedaría recortada por el overflow — el mismo motivo por
 * el que los dropdowns de esta tabla usan estrategia fija.
 *
 * Todo por delegación en `document`: la tabla se reemplaza por AJAX en cada
 * filtro y acción, así que no se pueden guardar referencias a las filas.
 */
(function () {
    var card = null;
    var hideTimer = null;

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

    function hide() {
        if (card) card.classList.remove('is-on');
    }

    /** Coloca la tarjeta junto a la fila, sin salirse de la pantalla. */
    function place(el, tr) {
        var r  = tr.getBoundingClientRect();
        var cw = el.offsetWidth, ch = el.offsetHeight;
        var vw = window.innerWidth, vh = window.innerHeight;

        // Por defecto a la derecha de la fila; si no cabe, a la izquierda.
        var left = r.right + 12;
        if (left + cw > vw - 8) left = r.left - cw - 12;
        if (left < 8) left = Math.max(8, (vw - cw) / 2);

        // Centrada verticalmente respecto a la fila, dentro del viewport.
        var top = r.top + (r.height / 2) - (ch / 2);
        if (top < 8) top = 8;
        if (top + ch > vh - 8) top = Math.max(8, vh - ch - 8);

        el.style.left = Math.round(left) + 'px';
        el.style.top  = Math.round(top) + 'px';
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
            '<div class="sh-peek__h">' +
                '<span>' + esc(tr.getAttribute('data-peek-code') || 'Contenido') + '</span>' +
                '<span>' + esc(bultos) + ' bulto' + (bultos === '1' ? '' : 's') + '</span>' +
            '</div>' +
            '<ul>' + items.map(function (i) { return '<li>' + esc(i) + '</li>'; }).join('') + '</ul>';

        // Se mide con la tarjeta ya visible para poder posicionarla bien.
        el.style.left = '-9999px';
        el.style.top  = '0px';
        el.classList.add('is-on');
        place(el, tr);
    }

    document.addEventListener('mouseover', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (!tr) return;
        clearTimeout(hideTimer);
        show(tr);
    });

    document.addEventListener('mouseout', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (!tr) return;
        // Pequeño retardo: evita el parpadeo al moverse entre celdas de la
        // misma fila (mouseout salta en cada <td>).
        clearTimeout(hideTimer);
        hideTimer = setTimeout(hide, 90);
    });

    // Al desplazar la tabla la tarjeta quedaría flotando donde estaba.
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);

    // Accesible por teclado: al enfocar un control de la fila también se asoma.
    document.addEventListener('focusin', function (ev) {
        var tr = ev.target.closest ? ev.target.closest('tr[data-peek]') : null;
        if (tr) show(tr); else hide();
    });
})();
</script>
