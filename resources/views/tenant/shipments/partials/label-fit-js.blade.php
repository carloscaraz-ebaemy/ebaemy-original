<script>
/*
 * UN RÓTULO = UNA HOJA.
 *
 * Con muchos ítems el rótulo crecía más que la hoja y el navegador lo partía:
 * salía una segunda página con el pie y dos líneas sueltas. La densidad y las
 * dos columnas del CSS resuelven el caso normal; esto es la garantía para
 * cuando aun así no entra.
 *
 * Se mide el alto real y se aplica `zoom`, que SÍ reflúye el texto (a
 * diferencia de `transform: scale`, que solo lo dibuja más chico dejando el
 * hueco reservado). Como `zoom` también angosta la caja, se compensa el ancho
 * para que el rótulo siga llenando la hoja.
 */
(function () {
    // Alto util de cada formato = alto de la hoja menos los margenes de @page.
    var HOJA = {
        sticker: null,                              // rollo continuo: no hay corte
        a5: { altoMm: 210 - 2 * 8,  anchoCm: 14 },
        a4: { altoMm: 297 - 2 * 12, anchoCm: 19 }
    };

    var MIN_ZOOM = 0.62;   // por debajo deja de ser legible a un brazo de distancia
    var HOLGURA  = 4;      // px de colchon: redondeos del navegador

    function mmAPx(mm) { return mm * 96 / 25.4; }

    function formatoActual() {
        var m = (document.body.className || '').match(/fmt-(\w+)/);
        return m ? m[1] : 'a4';
    }

    function ajustar() {
        var cfg = HOJA[formatoActual()];
        var labels = document.querySelectorAll('.label');
        if (!labels.length) return;

        labels.forEach(function (el) {
            // Siempre se parte de escala 1: si no, cada recalculo mediria el
            // alto ya encogido y el rotulo se iria achicando sin fondo.
            el.style.zoom = '';
            el.style.maxWidth = '';

            if (!cfg) return;                       // sticker: sin limite de alto

            var disponible = mmAPx(cfg.altoMm) - HOLGURA;
            var alto = el.offsetHeight;
            if (alto <= disponible) return;         // entra tal cual

            var z = Math.max(MIN_ZOOM, disponible / alto);
            el.style.zoom = z;
            // Compensacion del ancho: sin esto el rotulo encogido queda flaco
            // y descentrado en la hoja.
            el.style.maxWidth = (cfg.anchoCm / z).toFixed(2) + 'cm';

            // Encoger reflúye el texto (menos lineas), asi que suele sobrar
            // espacio: una segunda pasada recupera tamaño.
            if (el.offsetHeight < disponible * 0.92 && z < 1) {
                var z2 = Math.min(1, z * (disponible / Math.max(1, el.offsetHeight)) * 0.98);
                if (z2 > z) {
                    el.style.zoom = z2;
                    el.style.maxWidth = (cfg.anchoCm / z2).toFixed(2) + 'cm';
                    if (el.offsetHeight > disponible) {   // se paso: volver
                        el.style.zoom = z;
                        el.style.maxWidth = (cfg.anchoCm / z).toFixed(2) + 'cm';
                    }
                }
            }
        });
    }

    window.ajustarRotulos = ajustar;   // el cambio de formato lo vuelve a llamar

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ajustar);
    } else {
        ajustar();
    }
    // Las imagenes (logo, QR, codigo de barras) cambian el alto al cargar.
    window.addEventListener('load', ajustar);
    // Chrome recalcula el layout al abrir la vista previa de impresion.
    if (window.matchMedia) {
        var mq = window.matchMedia('print');
        if (mq.addEventListener) mq.addEventListener('change', ajustar);
    }
    window.addEventListener('beforeprint', ajustar);
})();
</script>
