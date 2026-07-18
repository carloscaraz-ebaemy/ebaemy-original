@php
    $format = in_array($format ?? 'a5', ['sticker','a5','a4'], true) ? ($format ?? 'a5') : 'a5';
    $pageSize   = ['sticker' => 'auto', 'a5' => 'A5', 'a4' => 'A4'][$format];
    $pageMargin = $format === 'sticker' ? '4mm' : ($format === 'a5' ? '8mm' : '12mm');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulo {{ strtoupper($format) }} — {{ $shipment->shipment_code }}</title>
    <style>
        @page { size: {{ $pageSize }}; margin: {{ $pageMargin }}; }
        body { background: #f5f5f5; display: flex; justify-content: center; padding: 64px 20px 20px; }
        @media print { body { background: #fff; padding: 0; } }
    </style>
    @include('tenant.shipments.partials.label-styles')
</head>
<body class="fmt-{{ $format }}">

<div class="no-print" style="position:fixed;top:12px;left:0;right:0;z-index:999;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
    <div style="background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:5px;display:inline-flex;gap:4px;align-items:center;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">
        <span style="font-size:12px;color:#666;padding:0 6px;">Formato:</span>
        @foreach(['sticker'=>'Sticker','a5'=>'A5','a4'=>'A4'] as $fk => $fl)
            <a href="{{ route('shipments.print', $shipment->id) }}?format={{ $fk }}"
               style="padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;{{ $format === $fk ? 'background:#4f46e5;color:#fff;' : 'background:#f1f3f5;color:#333;' }}">{{ $fl }}</a>
        @endforeach
    </div>
    <button onclick="window.print()" style="padding:9px 18px;background:#198754;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;box-shadow:0 6px 18px -6px rgba(0,0,0,.2);">🖨️ Imprimir</button>
    <button onclick="window.close()" style="padding:9px 14px;background:#6c757d;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">✕</button>
</div>

@include('tenant.shipments.partials.label-body')

</body>
</html>
