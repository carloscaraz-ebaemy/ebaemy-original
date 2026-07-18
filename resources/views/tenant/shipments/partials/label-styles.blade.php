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
    body.fmt-a5 .brand { font-size: 16px; }
    body.fmt-a5 .big-text { font-size: 19px; }
    body.fmt-a5 .med-text { font-size: 14px; }
    body.fmt-a5 .grid .box .v { font-size: 18px; }
    body.fmt-a5 .guide-box .n { font-size: 26px; }
    body.fmt-a5 .qr-img { width: 3cm; height: 3cm; }
    body.fmt-a5 .footer { font-size: 12px; }

    body.fmt-a4 .env-code { font-size: 42px; }
    body.fmt-a4 .brand { font-size: 22px; }
    body.fmt-a4 .section-title { font-size: 13px; }
    body.fmt-a4 .big-text { font-size: 30px; }
    body.fmt-a4 .med-text { font-size: 20px; }
    body.fmt-a4 .grid .box .v { font-size: 27px; }
    body.fmt-a4 .guide-box .n { font-size: 42px; }
    body.fmt-a4 .guide-box .l { font-size: 13px; }
    body.fmt-a4 .qr-img { width: 4cm; height: 4cm; }
    body.fmt-a4 .footer { font-size: 15px; }
    body.fmt-a4 .label-header, body.fmt-a4 .section, body.fmt-a4 .grid { margin-bottom: 16px; }

    .label-header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-start; }
    .brand { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .env-code { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
    .section-title { font-size: 9px; text-transform: uppercase; color: #666; letter-spacing: 1px; margin-bottom: 2px; }
    .section { margin-bottom: 8px; }
    .big-text { font-size: 15px; font-weight: bold; }
    .med-text { font-size: 12px; }
    .divider { border-top: 1px dashed #aaa; margin: 8px 0; }
    .grid { display: flex; gap: 8px; }
    .grid .box { flex: 1; border: 1px solid #000; padding: 6px 8px; }
    .grid .box .v { font-size: 14px; font-weight: bold; }
    .guide-box { border: 2px solid #000; padding: 6px 10px; text-align: center; margin-top: 6px; }
    .guide-box .l { font-size: 9px; text-transform: uppercase; }
    .guide-box .n { font-size: 20px; font-weight: bold; letter-spacing: 3px; }
    .footer { border-top: 2px solid #000; padding-top: 6px; margin-top: 8px; font-size: 10px; color: #555; text-align: center; }
    @media print { .no-print { display: none !important; } }
</style>
