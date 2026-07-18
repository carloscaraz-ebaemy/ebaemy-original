<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulos ({{ count($items) }})</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { background: #e5e7eb; padding: 64px 10px 20px; }
        .batch { display: flex; flex-wrap: wrap; gap: 6mm; justify-content: center; align-content: flex-start; }
        .batch .label { width: 8.8cm; }
        @media print { body { background: #fff; padding: 0; } .batch { gap: 5mm; } }
    </style>
    @include('tenant.shipments.partials.label-styles')
</head>
<body>

<div class="no-print" style="position:fixed;top:12px;left:0;right:0;z-index:999;display:flex;justify-content:center;gap:8px;">
    <button onclick="window.print()" style="padding:10px 20px;background:#198754;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">🖨️ Imprimir {{ count($items) }} rótulos</button>
    <button onclick="window.close()" style="padding:10px 14px;background:#6c757d;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">✕ Cerrar</button>
</div>

<div class="batch">
    @foreach($items as $it)
        @include('tenant.shipments.partials.label-body', ['shipment' => $it['shipment'], 'ubigeo' => $it['ubigeo'], 'qr' => $it['qr']])
    @endforeach
</div>

</body>
</html>
