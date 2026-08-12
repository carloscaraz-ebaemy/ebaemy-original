<script>
/*
 * FILTROS PLEGABLES EN MÓVIL.
 *
 * En pantalla chica los filtros ocupaban cinco franjas y la tabla quedaba
 * fuera de la vista. Se pliegan detrás de un botón; en escritorio el CSS
 * los muestra siempre y este script no hace nada visible.
 *
 * Delegación en `document`: Vue monta sobre #main-wrapper y regenera el DOM,
 * así que un listener puesto sobre el botón se perdería (ver
 * feedback_vue_mainwrapper_rerender).
 */
(function () {
    var CLAVE = 'sh_filtros_abiertos';

    function pintar(abierto) {
        var btn  = document.getElementById('shFoldBtn');
        var wrap = document.getElementById('shFold');
        if (!btn || !wrap) return;
        btn.classList.toggle('is-open', abierto);
        wrap.classList.toggle('is-open', abierto);
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    }

    function guardado() {
        try { return sessionStorage.getItem(CLAVE) === '1'; } catch (e) { return false; }
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('#shFoldBtn') : null;
        if (!btn) return;
        var abierto = !btn.classList.contains('is-open');
        pintar(abierto);
        // Se recuerda por sesion: al filtrar la pagina recarga, y volver a
        // abrirlo en cada busqueda seria insoportable.
        try { sessionStorage.setItem(CLAVE, abierto ? '1' : '0'); } catch (e) {}
    });

    function iniciar() {
        // Si hay filtros puestos se abre solo: dejarlos ocultos hace que la
        // tabla parezca vacia sin motivo visible.
        var btn = document.getElementById('shFoldBtn');
        if (!btn) return;
        pintar(guardado() || !!btn.querySelector('.sh-fold__n'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
    // Vue puede repintar el panel despues de cargar.
    document.addEventListener('sh:panel-updated', iniciar);
})();
</script>
