{{-- Las pantallas de impresión se abren en una pestaña aparte o DENTRO de
     Pedidos (diálogo de impresión, como la Nota de Venta). «Cerrar» y el aviso
     de «rótulo impreso» tienen que llegar a quien la abrió en los dos casos. --}}
<script>
window.ebImpresion = {
    enMarco: window.parent && window.parent !== window,
    avisar: function (msg) {
        [window.opener, window.parent !== window ? window.parent : null].forEach(function (w) {
            try { if (w && !w.closed) w.postMessage(msg, '*'); } catch (e) {}
        });
    },
    cerrar: function () {
        if (this.enMarco) this.avisar({ tipo: 'impresion-cerrar' });
        else window.close();
    },
};
</script>
