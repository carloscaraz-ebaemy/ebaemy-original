{{-- Arma en el navegador el PDF a imprimir con los PDF OFICIALES de Shalom.

     - Una copia por bulto: Shalom entrega UN rótulo aunque la guía tenga
       varios bultos, y la segunda caja se quedaba sin rótulo.
     - En el papel que use la tienda: el rótulo de Shalom es apaisado (medio
       A4), así que en A4 caben dos por hoja; el ticket va a la ticketera.
     - Varios envíos en un solo PDF (lote).

     Se hace aquí y no en el servidor porque pdf-lib abre cualquier PDF; el
     lector de PDF del servidor (FPDI libre) no abre los comprimidos.
     Uso: ShalomPdf.build([{url, copies}], formato, alAvanzar) → Promise<blobUrl>. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>
window.ShalomPdf = (function () {
    var MM = 72 / 25.4;
    // Papel de cada formato (puntos) y cuántos documentos por hoja.
    var FORMATOS = {
        'a4-2':   { label: 'A4 · 2 por hoja',      desc: 'Impresora de oficina. Ahorra papel.', w: 210 * MM, h: 297 * MM, slots: 2 },
        'a4-1':   { label: 'A4 · 1 por hoja',      desc: 'Más grande, para cajas grandes.', w: 210 * MM, h: 297 * MM, slots: 1 },
        'a5':     { label: 'A5',                   desc: 'Hoja A5 apaisada.', w: 210 * MM, h: 148 * MM, slots: 1 },
        'e10x15': { label: 'Etiqueta 10 × 15 cm',  desc: 'Impresora térmica de etiquetas.', w: 100 * MM, h: 150 * MM, slots: 1, rotar: true },
        't80':    { label: 'Ticketera 80 mm',      desc: 'La ticketera común.', w: 80 * MM,  h: null, slots: 1 },
        't58':    { label: 'Ticketera 58 mm',      desc: 'Ticketera chica.', w: 58 * MM,  h: null, slots: 1 },
        'a4':     { label: 'A4',                   desc: 'Impresora de oficina, a tamaño real.', w: 210 * MM, h: 297 * MM, slots: 1, arriba: true },
    };
    var POR_DOC = { rotulo: ['a4-2', 'a4-1', 'a5', 'e10x15'], ticket: ['t80', 't58', 'a4'] };

    function ajustar(sw, sh, bw, bh, sinAgrandar) {
        var k = Math.min(bw / sw, bh / sh);
        // El ticket en A4 va a su tamaño real: agrandado, el QR deja de leerse bien.
        if (sinAgrandar) k = Math.min(1, k);
        return { w: sw * k, h: sh * k };
    }

    async function build(fuentes, formato, alAvanzar) {
        var L = window.PDFLib;
        if (!L) throw new Error('No cargó el armador de PDF.');
        var F = FORMATOS[formato] || FORMATOS['a4-2'];
        var out = await L.PDFDocument.create();
        var margen = (F.h === null ? 2 : 6) * MM;
        var cola = [];

        for (var i = 0; i < fuentes.length; i++) {
            if (alAvanzar) alAvanzar(i, fuentes.length);
            var r = await fetch(fuentes[i].url, { credentials: 'same-origin' });
            if (!r.ok || (r.headers.get('content-type') || '').indexOf('pdf') === -1) {
                throw new Error('Shalom no entregó el PDF de ' + (fuentes[i].nombre || 'un envío') + '.');
            }
            var src = await L.PDFDocument.load(await r.arrayBuffer(), { ignoreEncryption: true });
            var pags = await out.embedPdf(src, src.getPageIndices());
            for (var c = 0; c < Math.max(1, fuentes[i].copies || 1); c++) {
                pags.forEach(function (p) { cola.push(p); });
            }
        }

        var hoja = null, usados = 0;
        cola.forEach(function (p) {
            var sw = p.width, sh = p.height;
            // Etiqueta vertical con un rótulo apaisado: se gira para llenarla.
            var girar = F.rotar && sw > sh;
            var vw = girar ? sh : sw, vh = girar ? sw : sh;

            if (F.h === null) {
                // Ticketera: el alto de la hoja es el del documento.
                var ancho = F.w - 2 * margen, alto = vh * ancho / vw;
                var pg = out.addPage([F.w, alto + 2 * margen]);
                pg.drawPage(p, { x: margen, y: margen, width: ancho, height: alto });
                return;
            }

            if (!hoja || usados >= F.slots) { hoja = out.addPage([F.w, F.h]); usados = 0; }
            var altoSlot = F.h / F.slots;
            var bw = F.w - 2 * margen, bh = altoSlot - 2 * margen;
            var t = ajustar(vw, vh, bw, bh, F.arriba);
            var x = (F.w - t.w) / 2;
            var yTop = F.h - usados * altoSlot - margen;
            var y = F.arriba ? yTop - t.h : (F.h - (usados + 1) * altoSlot) + (altoSlot - t.h) / 2;
            if (girar) {
                // Girado 90°: el ancho del documento queda en vertical.
                hoja.drawPage(p, { x: x + t.w, y: y, width: t.h, height: t.w, rotate: L.degrees(90) });
            } else {
                hoja.drawPage(p, { x: x, y: y, width: t.w, height: t.h });
            }
            usados++;
        });

        var bytes = await out.save();
        return URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
    }

    // El último formato elegido por documento se recuerda en este equipo.
    function recordado(doc) {
        try { var f = localStorage.getItem('shalom_fmt_' + doc); if (POR_DOC[doc].indexOf(f) !== -1) return f; } catch (e) {}
        return POR_DOC[doc][0];
    }
    function recordar(doc, f) { try { localStorage.setItem('shalom_fmt_' + doc, f); } catch (e) {} }

    return { build: build, FORMATOS: FORMATOS, POR_DOC: POR_DOC, recordado: recordado, recordar: recordar };
})();
</script>
