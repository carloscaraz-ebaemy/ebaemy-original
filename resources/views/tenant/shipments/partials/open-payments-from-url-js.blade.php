{{--
    Abre la ficha de pagos de un envío indicado en la URL: ?pagos=<id>

    Existe para que «Pagos del pedido», en el panel de Pedidos, lleve al módulo
    de pagos REAL en vez de a un formulario vacío. El dinero de un encargo
    logístico vive en el envío (`amount_due` + `shipping_payments`), no en el
    pedido espejo, que se crea sin líneas y con total 0.

    Se abre disparando el mismo botón que usa el operador, en vez de duplicar la
    lógica del modal: si mañana cambia cómo se abre, esto sigue funcionando.

    Quien enlaza debe mandar también `q=<codigo>` para acotar el listado: el
    botón solo existe si la fila está en la página que se está viendo, y sin
    filtrar, un envío antiguo caería en la página 7.
--}}
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var id = new URLSearchParams(window.location.search).get('pagos');
        if (!id || !/^\d+$/.test(id)) return;

        var boton = document.querySelector('.js-pay-open[data-id="' + id + '"]');
        if (!boton) {
            // La fila no esta en pantalla (paginacion o filtro). Se deja el
            // listado tal cual: es preferible a abrir un modal en blanco.
            console.warn('[envios] no se encontro el envio ' + id + ' en esta pagina; no se abre la ficha de pagos');
            return;
        }

        // Un tick para que el modal de Bootstrap y los listeners delegados ya
        // esten montados; sin esto el click se pierde en la carga.
        setTimeout(function () { boton.click(); }, 150);
    });
})();
</script>
