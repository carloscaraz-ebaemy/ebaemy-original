{{-- Arma en el navegador el PDF a imprimir con los PDF OFICIALES de Shalom.

     - Una copia por bulto: Shalom entrega UN rótulo aunque la guía tenga
       varios bultos, y la segunda caja se quedaba sin rótulo.
     - En el papel que use la tienda: el rótulo de Shalom es apaisado (medio
       A4), así que en A4 caben dos por hoja; el ticket va a la ticketera.
     - Varios envíos en un solo PDF (lote).

     Se hace aquí y no en el servidor porque pdf-lib abre cualquier PDF; el
     lector de PDF del servidor (FPDI libre) no abre los comprimidos.
     - Opcional: una hoja con el CONTENIDO del paquete detrás de cada envío
       (el rótulo de Shalom solo dice el tamaño; quien embala necesita la
       lista de productos). Mismo tamaño que el rótulo, así comparte la hoja.
     Uso: ShalomPdf.build([{url, copies, contenido?, guia?, ref?, cliente?}], formato, alAvanzar, {detalle}) → Promise<blobUrl>. --}}
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
    // PDF ya bajados en esta pantalla: cambiar de papel no vuelve a pedirlos.
    var MEMO = {};
    var POR_DOC = { rotulo: ['a4-2', 'a4-1', 'a5', 'e10x15'], ticket: ['t80', 't58', 'a4'] };

    function ajustar(sw, sh, bw, bh, sinAgrandar) {
        var k = Math.min(bw / sw, bh / sh);
        // El ticket en A4 va a su tamaño real: agrandado, el QR deja de leerse bien.
        if (sinAgrandar) k = Math.min(1, k);
        return { w: sw * k, h: sh * k };
    }

    // Helvetica estándar solo dibuja Latin-1: lo demás (emojis) se quita.
    function latin1(t) { return String(t || '').replace(/[^\x20-\x7E\xA0-\xFF]/g, '').replace(/\s+/g, ' ').trim(); }

    function envolver(texto, font, size, ancho) {
        var palabras = texto.split(' '), lineas = [], act = '';
        palabras.forEach(function (p) {
            var prueba = act ? act + ' ' + p : p;
            if (font.widthOfTextAtSize(prueba, size) <= ancho || !act) { act = prueba; } else { lineas.push(act); act = p; }
        });
        if (act) lineas.push(act);
        return lineas;
    }

    // Hoja de contenido del tamaño del rótulo (w × h puntos).
    async function hojaContenido(L, doc, fonts, f, w, h) {
        var pg = doc.addPage([w, h]);
        var k = Math.min(w, h) / 420, m = 26 * k, ancho = w - 2 * m;
        var negro = L.rgb(0.1, 0.1, 0.12), gris = L.rgb(0.35, 0.36, 0.4);
        pg.drawRectangle({ x: m / 2, y: m / 2, width: w - m, height: h - m, borderColor: negro, borderWidth: 1.2 * k });
        var y = h - m - 16 * k;
        pg.drawText('CONTENIDO DEL PAQUETE', { x: m, y: y, size: 18 * k, font: fonts.b, color: negro });
        y -= 18 * k;
        var cab = latin1([f.guia ? 'Guía ' + f.guia : null, f.ref, f.cliente].filter(Boolean).join('  ·  '));
        if (cab) { pg.drawText(cab, { x: m, y: y, size: 10.5 * k, font: fonts.r, color: gris, maxWidth: ancho }); y -= 8 * k; }
        pg.drawLine({ start: { x: m, y: y }, end: { x: w - m, y: y }, thickness: 0.8 * k, color: gris });

        // Se achica la letra hasta que entre todo.
        var items = (f.contenido || []).map(latin1).filter(Boolean);
        var size = 15 * k, filas;
        for (var t = 0; t < 8; t++) {
            filas = [];
            items.forEach(function (it, i) {
                envolver(it, fonts.r, size, ancho - 22 * k).forEach(function (l, j) { filas.push({ n: j === 0 ? (i + 1) + '.' : '', l: l }); });
            });
            if (filas.length * size * 1.35 <= y - m - 4 * k) break;
            size *= 0.86;
        }
        y -= size * 1.6;
        filas.forEach(function (r) {
            if (y < m) return;
            if (r.n) pg.drawText(r.n, { x: m, y: y, size: size, font: fonts.b, color: negro });
            pg.drawText(r.l, { x: m + 22 * k, y: y, size: size, font: fonts.r, color: negro });
            y -= size * 1.35;
        });
        return pg;
    }

    async function build(fuentes, formato, alAvanzar, opts) {
        opts = opts || {};
        var L = window.PDFLib;
        if (!L) throw new Error('No cargó el armador de PDF.');
        var F = FORMATOS[formato] || FORMATOS['a4-2'];
        var out = await L.PDFDocument.create();
        var margen = (F.h === null ? 2 : 6) * MM;
        var cola = [];

        // Se bajan en paralelo (de a 4) y se arman en orden: uno tras otro, un
        // lote de 20 envíos sumaba el tiempo de cada PDF.
        var bufs = new Array(fuentes.length), hechos = 0, sig = 0;
        async function bajar() {
            while (sig < fuentes.length) {
                var i = sig++;
                if (MEMO[fuentes[i].url]) { bufs[i] = MEMO[fuentes[i].url]; if (alAvanzar) alAvanzar(hechos++, fuentes.length); continue; }
                var r = await fetch(fuentes[i].url, { credentials: 'same-origin' });
                if (!r.ok || (r.headers.get('content-type') || '').indexOf('pdf') === -1) {
                    var err = new Error('Shalom no entregó el PDF de ' + (fuentes[i].nombre || 'un envío') + '.');
                    err.descarga = true;
                    throw err;
                }
                bufs[i] = MEMO[fuentes[i].url] = await r.arrayBuffer();
                if (alAvanzar) alAvanzar(hechos++, fuentes.length);
            }
        }
        var trabajadores = [];
        for (var w = 0; w < Math.min(4, fuentes.length); w++) trabajadores.push(bajar());
        await Promise.all(trabajadores);

        // Primero todos los rótulos (de dos en dos por hoja en A4) y después
        // las hojas de contenido. Con un solo rótulo, su contenido ocupa la
        // otra mitad de la misma hoja.
        var notas = null, fonts = null, detalles = [];
        for (var i = 0; i < fuentes.length; i++) {
            // Copia: pdf-lib puede dejar el ArrayBuffer inutilizable tras leerlo.
            var src = await L.PDFDocument.load(bufs[i].slice(0), { ignoreEncryption: true });
            var pags = await out.embedPdf(src, src.getPageIndices());
            for (var c = 0; c < Math.max(1, fuentes[i].copies || 1); c++) {
                pags.forEach(function (p) { cola.push(p); });
            }
            if (opts.detalle && fuentes[i].contenido && fuentes[i].contenido.length && pags.length) {
                if (!notas) {
                    notas = await L.PDFDocument.create();
                    fonts = { r: await notas.embedFont(L.StandardFonts.Helvetica), b: await notas.embedFont(L.StandardFonts.HelveticaBold) };
                }
                var w0 = pags[0].width, h0 = pags[0].height;
                // Ticket alto y angosto: la hoja de contenido va apaisada igual que un rótulo.
                var pc = await hojaContenido(L, notas, fonts, fuentes[i], Math.max(w0, h0), Math.min(w0, h0));
                detalles.push(await out.embedPage(pc));
            }
        }
        cola = cola.concat(detalles);

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
