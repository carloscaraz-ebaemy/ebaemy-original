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
        'a4-2':   { label: 'A4 · 2 por hoja',      desc: 'Dos pedidos por hoja. Ahorra papel.', w: 210 * MM, h: 297 * MM, slots: 2 },
        'a4-1':   { label: 'A4 · 1 por hoja',      desc: 'Más grande, para cajas grandes.', w: 210 * MM, h: 297 * MM, slots: 1 },
        'a5':     { label: 'A5',                   desc: 'Un pedido por hoja, con su contenido.', w: 210 * MM, h: 148 * MM, slots: 1 },
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

    var NEGRO = null, GRIS = null;

    /**
     * Lista numerada dentro de una caja (x, yTop, ancho, alto), en una o dos
     * columnas y achicando la letra hasta `min`. Si aun así no entra, dibuja
     * lo que cabe y cierra con «… y N más». Devuelve si entró completa.
     */
    function lista(pg, fonts, items, x, yTop, ancho, alto, size, min, k, aviso) {
        var filas, cols = 1, porCol, entra = false;
        for (var t = 0; t < 14; t++) {
            cols = (items.length > 5 && size < 11 * k) ? 2 : 1;
            var anchoCol = (ancho - (cols - 1) * 14 * k) / cols - 18 * k;
            filas = [];
            items.forEach(function (it, i) {
                envolver(it, fonts.r, size, anchoCol).forEach(function (l, j) { filas.push({ n: j === 0 ? (i + 1) + '.' : '', l: l, i: i }); });
            });
            porCol = Math.max(1, Math.floor(alto / (size * 1.3)));
            entra = filas.length <= porCol * cols;
            if (entra || size <= min) break;
            size = Math.max(min, size * 0.9);
        }
        var visibles = entra ? filas : filas.slice(0, porCol * cols - 1);
        var anchoC = (ancho - (cols - 1) * 14 * k) / cols;
        visibles.forEach(function (r, idx) {
            var col = Math.floor(idx / porCol), fila = idx % porCol;
            var xx = x + col * (anchoC + 14 * k), yy = yTop - (fila + 1) * size * 1.3 + size * 0.3;
            if (r.n) pg.drawText(r.n, { x: xx, y: yy, size: size, font: fonts.b, color: NEGRO });
            pg.drawText(r.l, { x: xx + 18 * k, y: yy, size: size, font: fonts.r, color: NEGRO });
        });
        if (!entra) {
            var hechos = visibles.length ? visibles[visibles.length - 1].i + 1 : 0;
            var col = Math.floor(visibles.length / porCol), fila = visibles.length % porCol;
            pg.drawText('... y ' + (items.length - hechos) + ' más' + (aviso || ''), {
                x: x + col * (anchoC + 14 * k), y: yTop - (fila + 1) * size * 1.3 + size * 0.3, size: size, font: fonts.b, color: NEGRO });
        }
        return entra;
    }

    // Hoja de contenido completa, dibujada en la caja (x, y, w, h) de la hoja.
    function dibujarDetalle(pg, fonts, f, x, y, w, h) {
        var k = Math.min(w, h) / 420, m = 22 * k;
        pg.drawRectangle({ x: x + m / 3, y: y + m / 3, width: w - 2 * m / 3, height: h - 2 * m / 3, borderColor: NEGRO, borderWidth: 1.1 * k });
        var yy = y + h - m - 14 * k;
        pg.drawText('CONTENIDO DEL PAQUETE', { x: x + m, y: yy, size: 17 * k, font: fonts.b, color: NEGRO });
        yy -= 16 * k;
        var cab = latin1([f.guia ? 'Guía ' + f.guia : null, f.ref, f.cliente].filter(Boolean).join('  ·  '));
        if (cab) { pg.drawText(cab, { x: x + m, y: yy, size: 10 * k, font: fonts.r, color: GRIS, maxWidth: w - 2 * m }); yy -= 7 * k; }
        pg.drawLine({ start: { x: x + m, y: yy }, end: { x: x + w - m, y: yy }, thickness: 0.7 * k, color: GRIS });
        lista(pg, fonts, (f.contenido || []).map(latin1).filter(Boolean), x + m, yy - 6 * k, w - 2 * m, yy - 6 * k - (y + m), 14 * k, 6 * k, k);
    }

    /**
     * Rótulo con su contenido debajo, en la caja (x, y, w, h): el rótulo arriba
     * y una franja con la lista. Así A5 lleva un pedido por hoja y A4 «2 por
     * hoja» dos pedidos, sin hojas aparte. Devuelve si la lista entró entera.
     */
    function dibujarRotuloLista(pg, fonts, pieza, x, y, w, h) {
        var s = w / pieza.width, k = s * pieza.p.width / 595, franja = pieza.franja * s, m = 10 * k;
        pg.drawPage(pieza.p, { x: x, y: y + franja, width: w, height: h - franja });
        pg.drawLine({ start: { x: x + m, y: y + franja - 3 * k }, end: { x: x + w - m, y: y + franja - 3 * k }, thickness: 0.7 * k, color: GRIS, dashArray: [4 * k, 3 * k] });
        var items = (pieza.f.contenido || []).map(latin1).filter(Boolean);
        var yt = y + franja - 15 * k;
        pg.drawText('CONTENIDO' + (items.length > 1 ? ' (' + items.length + ')' : ''), { x: x + m, y: yt, size: 9.5 * k, font: fonts.b, color: NEGRO });
        if (pieza.f.ref) { var ref = latin1(pieza.f.ref); pg.drawText(ref, { x: x + w - m - fonts.r.widthOfTextAtSize(ref, 9 * k), y: yt, size: 9 * k, font: fonts.r, color: GRIS }); }
        return lista(pg, fonts, items, x + m, yt - 4 * k, w - 2 * m, yt - 4 * k - (y + 3 * k), 11 * k, 7 * k, k, ': lista completa en otra hoja');
    }

    async function build(fuentes, formato, alAvanzar, opts) {
        opts = opts || {};
        var L = window.PDFLib;
        if (!L) throw new Error('No cargó el armador de PDF.');
        var F = FORMATOS[formato] || FORMATOS['a4-2'];
        var out = await L.PDFDocument.create();
        var margen = (F.h === null ? 2 : 6) * MM;
        var cola = [];
        NEGRO = L.rgb(0.1, 0.1, 0.12); GRIS = L.rgb(0.4, 0.41, 0.45);

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

        // Piezas: el rótulo tal cual, el rótulo con su lista debajo (el primero
        // de cada envío) o una hoja de contenido completa (solo si la lista no
        // entró en la franja, o en la etiqueta 10 × 15, que gira el rótulo).
        var fonts = null, detalles = [];
        for (var i = 0; i < fuentes.length; i++) {
            // Copia: pdf-lib puede dejar el ArrayBuffer inutilizable tras leerlo.
            var src = await L.PDFDocument.load(bufs[i].slice(0), { ignoreEncryption: true });
            var pags = await out.embedPdf(src, src.getPageIndices());
            var f = fuentes[i];
            var conLista = !!(opts.detalle && f.contenido && f.contenido.length && pags.length);
            var apaisado = pags.length && pags[0].width > pags[0].height;
            if (conLista && !fonts) fonts = { r: await out.embedFont(L.StandardFonts.Helvetica), b: await out.embedFont(L.StandardFonts.HelveticaBold) };
            for (var c = 0; c < Math.max(1, f.copies || 1); c++) {
                pags.forEach(function (p, j) {
                    if (conLista && c === 0 && j === 0 && !F.rotar && apaisado) {
                        var franja = 104 * p.width / 595;
                        cola.push({ tipo: 'rotlista', p: p, f: f, franja: franja, width: p.width, height: p.height + franja });
                    } else {
                        cola.push({ tipo: 'pdf', p: p, width: p.width, height: p.height });
                    }
                });
            }
            if (conLista && (F.rotar || !apaisado)) {
                var a = Math.max(pags[0].width, pags[0].height), b = Math.min(pags[0].width, pags[0].height);
                detalles.push({ tipo: 'detalle', f: f, width: F.rotar ? b : a, height: F.rotar ? a : b });
            }
        }

        var hoja = null, usados = 0;
        function colocar(p) {
            var sw = p.width, sh = p.height;
            // Etiqueta vertical con un rótulo apaisado: se gira para llenarla.
            var girar = F.rotar && sw > sh && p.tipo === 'pdf';
            var vw = girar ? sh : sw, vh = girar ? sw : sh;

            if (F.h === null) {
                // Ticketera: el alto de la hoja es el del documento.
                var ancho = F.w - 2 * margen, alto = vh * ancho / vw;
                var pg = out.addPage([F.w, alto + 2 * margen]);
                pg.drawPage(p.p, { x: margen, y: margen, width: ancho, height: alto });
                return;
            }

            if (!hoja || usados >= F.slots) { hoja = out.addPage([F.w, F.h]); usados = 0; }
            var altoSlot = F.h / F.slots;
            var bw = F.w - 2 * margen, bh = altoSlot - 2 * margen;
            var t = ajustar(vw, vh, bw, bh, F.arriba);
            var x = (F.w - t.w) / 2;
            var yTop = F.h - usados * altoSlot - margen;
            var y = F.arriba ? yTop - t.h : (F.h - (usados + 1) * altoSlot) + (altoSlot - t.h) / 2;
            if (p.tipo === 'rotlista') {
                if (!dibujarRotuloLista(hoja, fonts, p, x, y, t.w, t.h)) {
                    detalles.push({ tipo: 'detalle', f: p.f, width: p.p.width, height: p.p.height });
                }
            } else if (p.tipo === 'detalle') {
                dibujarDetalle(hoja, fonts, p.f, x, y, t.w, t.h);
            } else if (girar) {
                // Girado 90°: el ancho del documento queda en vertical.
                hoja.drawPage(p.p, { x: x + t.w, y: y, width: t.h, height: t.w, rotate: L.degrees(90) });
            } else {
                hoja.drawPage(p.p, { x: x, y: y, width: t.w, height: t.h });
            }
            usados++;
        }
        cola.forEach(colocar);
        // Hojas de contenido completas, al final y de dos en dos en A4.
        for (var d = 0; d < detalles.length; d++) colocar(detalles[d]);

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
