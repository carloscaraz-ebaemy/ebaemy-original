{{-- Estilos del rótulo, compartidos entre impresión individual y por lote.
     NO incluye @page (cada página define su tamaño de papel). --}}
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 12px; }
    .label { width: 10cm; background: #fff; border: 2px solid #000; padding: 12px; page-break-inside: avoid; }

    .qr-img { width: 2.4cm; height: 2.4cm; flex: 0 0 auto; }
    .barcode-img { display: block; height: 1cm; margin-top: 4px; max-width: 100%; }
    body.fmt-a5 .barcode-img { height: 1.2cm; }
    body.fmt-a4 .barcode-img { height: 1.6cm; }
    .bulto-box { display: inline-block; flex: 1; height: .9cm; border: 2px solid #000; border-radius: 3px; background: #fff; }
    .bulto-total { font-weight: bold; font-size: 16px; }
    body.fmt-a5 .bulto-box { height: 1.1cm; } body.fmt-a5 .bulto-total { font-size: 20px; }
    body.fmt-a4 .bulto-box { height: 1.7cm; } body.fmt-a4 .bulto-total { font-size: 30px; }

    /* ── Formatos de papel: escalan el contenido para llenar la hoja ── */
    body.fmt-a5 .label { width: 100%; max-width: 14cm; padding: 18px; }
    body.fmt-a4 .label { width: 100%; max-width: 19cm; padding: 26px; }

    body.fmt-a5 .env-code { font-size: 27px; }
    body.fmt-a5 .big-text { font-size: 19px; }
    body.fmt-a5 .med-text { font-size: 14px; }
    body.fmt-a5 .grid .box .v { font-size: 18px; }
    body.fmt-a5 .guide-box .n { font-size: 26px; }
    body.fmt-a5 .qr-img { width: 3cm; height: 3cm; }
    body.fmt-a5 .footer { font-size: 12px; }

    body.fmt-a4 .env-code { font-size: 42px; }
    body.fmt-a4 .section-title { font-size: 13px; }
    body.fmt-a4 .big-text { font-size: 30px; }
    body.fmt-a4 .med-text { font-size: 20px; }
    body.fmt-a4 .grid .box .v { font-size: 27px; }
    body.fmt-a4 .guide-box .n { font-size: 42px; }
    body.fmt-a4 .guide-box .l { font-size: 13px; }
    body.fmt-a4 .qr-img { width: 4cm; height: 4cm; }
    body.fmt-a4 .footer { font-size: 15px; }
    body.fmt-a4 .label-header, body.fmt-a4 .section, body.fmt-a4 .grid { margin-bottom: 16px; }

    .label-header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px;
                    display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .brand { font-size: 13px; font-weight: bold; text-transform: uppercase; }

    /* ── Cabecera: el código manda ───────────────────────────────────────
       El código de envío es el dato que se busca a un metro de distancia, y
       la razón social larga lo estaba partiendo en dos renglones robándole
       ancho. Ahora el código NO envuelve nunca y la marca cede el espacio. */
    .hdr-code  { flex: 1 1 auto; min-width: 0; }
    .hdr-brand { flex: 0 1 auto; text-align: right; max-width: 38%; }
    .env-code  { white-space: nowrap; }
    .hdr-date  { font-size: 10px; color: #555; margin-top: 2px; }

    /* Logo de la empresa. `print-color-adjust` obliga al navegador a
       imprimirlo aunque el usuario tenga desactivados los fondos. */
    .brand-logo { display: block; margin: 0 0 3px auto; max-height: 1.1cm; max-width: 100%;
                  object-fit: contain; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body.fmt-a5 .brand-logo { max-height: 1.5cm; }
    body.fmt-a4 .brand-logo { max-height: 2.2cm; }

    /* Con logo, el nombre pasa a ser pie de firma: la marca ya se ve, y
       repetirla en mayúsculas y negrita compite con el propio logo. */
    .label-header.has-logo .brand { font-size: 9px; font-weight: 600; text-transform: none;
                                    color: #444; line-height: 1.25; letter-spacing: 0; }
    body.fmt-a5 .label-header.has-logo .brand { font-size: 10.5px; }
    body.fmt-a4 .label-header.has-logo .brand { font-size: 13px; }
    /* Sin logo el nombre es lo único que identifica a la tienda: mantiene peso. */
    body.fmt-a5 .label-header:not(.has-logo) .brand { font-size: 16px; }
    body.fmt-a4 .label-header:not(.has-logo) .brand { font-size: 22px; }
    .env-code { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
    /* Con logo el codigo comparte fila con la marca; se baja un punto para
       que siga entrando en una sola linea aun con codigos de 19 caracteres. */
    body.fmt-a5 .has-logo .env-code { font-size: 24px; letter-spacing: .5px; }
    body.fmt-a4 .has-logo .env-code { font-size: 36px; letter-spacing: .5px; }
    .section-title { font-size: 9px; text-transform: uppercase; color: #666; letter-spacing: 1px; margin-bottom: 2px; }
    .section { margin-bottom: 8px; }
    .big-text { font-size: 15px; font-weight: bold; }
    .med-text { font-size: 12px; }
    .divider { border-top: 1px dashed #aaa; margin: 8px 0; }

    /* ── Contenido del paquete ──────────────────────────────────────────
       El detalle lo escribe el cliente y suele ser una lista de ítems más
       largos que el ancho del rótulo. Se pinta como lista con SANGRÍA
       FRANCESA: al envolver, la continuación queda bajo el texto y no
       invade la columna de las viñetas (que era lo que se veía desalineado).
       `overflow-wrap` evita que un código largo sin espacios se salga del
       marco del rótulo. */
    .pkg-list { list-style: none; margin: 0; padding: 0; counter-reset: pkg; }
    .pkg-list li { counter-increment: pkg; position: relative; padding-left: 16px; margin-bottom: 3px;
                   line-height: 1.35; overflow-wrap: anywhere; word-break: break-word; }
    .pkg-list li:last-child { margin-bottom: 0; }
    /* Numerada: quien embala va contando los ítems para verificar que estén
       todos, y un número se sigue mejor que una viñeta. */
    .pkg-list li::before { content: counter(pkg) "."; position: absolute; left: 0; top: 0;
                           font-weight: bold; }
    .pkg-single { line-height: 1.35; overflow-wrap: anywhere; word-break: break-word; }
    .pkg-count { float: right; font-weight: bold; color: #000; }

    body.fmt-a5 .pkg-list li { padding-left: 19px; margin-bottom: 4px; }
    body.fmt-a4 .pkg-list li { padding-left: 26px; margin-bottom: 6px; }

    /* ── Listas largas ───────────────────────────────────────────────────
       Cada ítem ocupa una línea entera aunque diga "02 maceta", así que una
       lista larga estira el rótulo hasta partirlo en dos hojas. Antes de
       achicar la letra de TODO el rótulo se aprovecha el ancho sobrante:
       las líneas reales miden ~37 caracteres y entran de a dos por fila.
       `break-inside` evita que un ítem quede cortado entre columnas. */
    .pkg-list--dense li { margin-bottom: 1px !important; line-height: 1.25; }
    .pkg-list--cols { column-count: 2; column-gap: 14px; }
    .pkg-list--cols li { break-inside: avoid; page-break-inside: avoid; }
    body.fmt-a4 .pkg-list--cols { column-gap: 22px; }
    /* En sticker (10cm) no hay ancho para dos columnas. */
    body.fmt-sticker .pkg-list--cols { column-count: 1; }
    .grid { display: flex; gap: 8px; }
    .grid .box { flex: 1; border: 1px solid #000; padding: 6px 8px; }
    .grid .box .v { font-size: 14px; font-weight: bold; }
    .guide-box { border: 2px solid #000; padding: 6px 10px; text-align: center; margin-top: 6px; }
    .guide-box .l { font-size: 9px; text-transform: uppercase; }
    .guide-box .n { font-size: 20px; font-weight: bold; letter-spacing: 3px; }
    .footer { border-top: 2px solid #000; padding-top: 6px; margin-top: 8px; font-size: 10px; color: #555; text-align: center; }
    @media print { .no-print { display: none !important; } }
</style>
