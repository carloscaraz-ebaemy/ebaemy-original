{{-- Diálogo de impresión del panel de Envíos: el rótulo, el lote y los de
     Shalom se imprimen DENTRO de la página, como la Nota de Venta y como en
     Pedidos (print_dialog.vue). Intercepta los enlaces y formularios que
     abrían la impresión en otra pestaña; las pantallas de impresión avisan
     «impresion-cerrar» y «rotulo-impreso» (partials/print-embed-js). --}}
<style>
    .ebpm { position: fixed; inset: 0; z-index: 2000; display: none; background: oklch(0.2 0.02 258 / 0.45); }
    .ebpm.on { display: flex; align-items: center; justify-content: center; }
    .ebpm-box { width: min(1400px, 96vw); height: 94vh; display: flex; flex-direction: column; background: oklch(0.992 0.002 250);
                border-radius: 12px; overflow: hidden; box-shadow: 0 24px 60px -20px oklch(0.2 0.02 258 / 0.5); }
    .ebpm-h { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid oklch(0.9 0.006 250);
              font: 600 14.5px/1.3 -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; color: oklch(0.24 0.02 258); }
    .ebpm-h span { flex: 1; }
    .ebpm-h a { font-size: 13px; font-weight: 600; color: oklch(0.47 0.13 262); text-decoration: none; }
    .ebpm-x { border: 0; background: none; font-size: 22px; line-height: 1; color: oklch(0.42 0.018 258); cursor: pointer; padding: 2px 6px; }
    .ebpm-f { flex: 1; border: 0; width: 100%; background: oklch(0.925 0.006 250); }
</style>
<div class="ebpm" id="ebpm" role="dialog" aria-modal="true" aria-labelledby="ebpmT">
    <div class="ebpm-box">
        <div class="ebpm-h">
            <span id="ebpmT">Imprimir</span>
            <a href="#" id="ebpmAparte" target="_blank" rel="noopener">Abrir aparte</a>
            <button type="button" class="ebpm-x" id="ebpmX" aria-label="Cerrar">&times;</button>
        </div>
        <iframe class="ebpm-f" id="ebpmF" title="Impresión"></iframe>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('ebpm'), fr = document.getElementById('ebpmF'),
        tit = document.getElementById('ebpmT'), aparte = document.getElementById('ebpmAparte');
    var huboImpresion = false;
    // Las rutas que abren una pantalla de impresión.
    var ES_IMPRESION = /\/registro-envio\/(\d+\/imprimir|lote-imprimir|lotes\/\d+\/imprimir|shalom-lote)|\/orders\/envio\/\d+\/(rotulo|shalom-imprimir)/;

    window.ebAbrirImpresion = function (url, titulo) {
        huboImpresion = false;
        tit.textContent = titulo || 'Imprimir';
        aparte.href = url;
        fr.src = url;
        box.classList.add('on');
    };
    function cerrar() {
        box.classList.remove('on');
        fr.src = 'about:blank';
        // El listado muestra contadores y chips de «impreso»: se refresca.
        if (huboImpresion) location.reload();
    }
    document.getElementById('ebpmX').addEventListener('click', cerrar);
    aparte.addEventListener('click', function () { setTimeout(cerrar, 0); });
    box.addEventListener('click', function (e) { if (e.target === box) cerrar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('on')) cerrar(); });
    window.addEventListener('message', function (e) {
        if (!e.data || e.source !== fr.contentWindow) return;
        if (e.data.tipo === 'impresion-cerrar') cerrar();
        if (e.data.tipo === 'rotulo-impreso') huboImpresion = true;
    });

    // Enlaces que abrían la impresión en otra pestaña.
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || a.classList.contains('disabled')) return;
        var href = a.getAttribute('href') || '';
        if (!ES_IMPRESION.test(href)) return;
        e.preventDefault();
        window.ebAbrirImpresion(a.href, (a.textContent || '').trim() || 'Imprimir');
    }, true);

    // Formularios GET que imprimían en otra pestaña (reimpresión del lote con motivo).
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || (f.method || 'get').toLowerCase() !== 'get' || !ES_IMPRESION.test(f.getAttribute('action') || '')) return;
        e.preventDefault();
        var q = new URLSearchParams(new FormData(f)).toString();
        var url = f.action + (q ? (f.action.indexOf('?') === -1 ? '?' : '&') + q : '');
        var modal = f.closest('.modal');
        if (modal && window.bootstrap && bootstrap.Modal.getInstance(modal)) bootstrap.Modal.getInstance(modal).hide();
        window.ebAbrirImpresion(url, 'Reimprimir lote');
    }, true);
})();
</script>
