<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de entrega — {{ $shipment->shipment_code }}</title>
    <style>
        /*
         * Comprobante INTERNO de entrega para los recojos en tienda.
         * No es un rótulo de transporte: no lleva agencia, guía ni manifiesto,
         * solo sirve para dejar constancia de a quién se le entregó el pedido.
         */
        @page { size: A5; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               color:#0f172a; background:#f1f5f9; }
        .sheet { background:#fff; max-width:148mm; margin:0 auto; padding:12mm 10mm;
                 border:1px solid #e2e8f0; }
        .bar { display:flex; justify-content:space-between; align-items:center; gap:.5rem;
               border-bottom:2px solid #16a34a; padding-bottom:8px; margin-bottom:12px; }
        .brand { font-size:15px; font-weight:800; }
        .tag { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:999px;
               font-size:10px; font-weight:800; padding:3px 10px; text-transform:uppercase;
               letter-spacing:.04em; }
        h1 { font-size:16px; margin:0 0 2px; }
        .code { font-size:22px; font-weight:800; letter-spacing:.02em; font-family:monospace; }
        .muted { color:#64748b; font-size:11px; }
        table { width:100%; border-collapse:collapse; margin-top:12px; font-size:12px; }
        td { padding:6px 4px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
        td.k { color:#64748b; width:38%; }
        td.v { font-weight:600; }
        .note { margin-top:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
                padding:8px 10px; font-size:11px; color:#475569; }
        .signs { display:flex; gap:14px; margin-top:26px; }
        .sign { flex:1; text-align:center; }
        .sign .line { border-top:1px solid #94a3b8; margin-bottom:4px; height:38px; }
        .sign .lbl { font-size:10px; color:#64748b; }
        .toolbar { text-align:center; padding:12px; }
        .btn { display:inline-block; background:#16a34a; color:#fff; border:0; border-radius:8px;
               padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; }
        @media print { .toolbar { display:none; } body { background:#fff; } .sheet { border:0; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ Imprimir comprobante</button>
</div>

<div class="sheet">
    <div class="bar">
        <div class="brand">{{ $company->trade_name ?? $company->name ?? 'Tienda' }}</div>
        <div class="tag">🟢 Recojo en tienda</div>
    </div>

    <h1>Comprobante interno de entrega</h1>
    <div class="muted">Este documento acredita la entrega del pedido en tienda. No es guía de transporte.</div>

    <div style="margin-top:10px" class="code">{{ $shipment->shipment_code }}</div>

    <table>
        <tr><td class="k">Cliente</td><td class="v">{{ $shipment->full_name }}</td></tr>
        <tr><td class="k">Documento</td><td class="v">{{ $shipment->dni ?: '—' }}</td></tr>
        <tr><td class="k">Teléfono</td><td class="v">{{ $shipment->phone ?: '—' }}</td></tr>
        <tr><td class="k">Contenido</td><td class="v">{{ $shipment->package_content ?: '—' }}</td></tr>
        <tr><td class="k">Bultos</td><td class="v">{{ $shipment->package_count ?: 1 }}</td></tr>
        <tr><td class="k">Registrado</td><td class="v">{{ optional($shipment->created_at)->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="k">Estado</td><td class="v">{{ $shipment->status_label }}</td></tr>
        @if($shipment->picked_up_at)
            <tr><td class="k">Recogido el</td><td class="v">{{ $shipment->picked_up_at->format('d/m/Y H:i') }}</td></tr>
        @endif
        @if($shipment->picked_up_by)
            <tr><td class="k">Recogido por</td><td class="v">{{ $shipment->picked_up_by }}</td></tr>
        @endif
    </table>

    <div class="note">
        El cliente debe presentar su documento de identidad. Si recoge un tercero,
        anotar su nombre y documento en la línea de recepción.
    </div>

    <div class="signs">
        <div class="sign">
            <div class="line"></div>
            <div class="lbl">Entregado por (tienda)</div>
        </div>
        <div class="sign">
            <div class="line"></div>
            <div class="lbl">Recibido por (nombre y DNI)</div>
        </div>
    </div>
</div>

</body>
</html>
