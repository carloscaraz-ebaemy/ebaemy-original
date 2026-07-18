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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; background: #f5f5f5; display: flex; justify-content: center; padding: 64px 20px 20px; }
        .label { width: 10cm; background: #fff; border: 2px solid #000; padding: 12px; page-break-inside: avoid; }

        .qr-img { width: 2.4cm; height: 2.4cm; flex: 0 0 auto; }
        .bulto-box { display: inline-block; flex: 1; height: .9cm; border: 2px solid #000; border-radius: 3px; background: #fff; }
        .bulto-total { font-weight: bold; font-size: 16px; }
        body.fmt-a5 .bulto-box { height: 1.1cm; } body.fmt-a5 .bulto-total { font-size: 20px; }
        body.fmt-a4 .bulto-box { height: 1.7cm; } body.fmt-a4 .bulto-total { font-size: 30px; }

        /* ── Formatos de papel: escalan el contenido para llenar la hoja ── */
        body.fmt-a5 .label { width: 100%; max-width: 14cm; padding: 18px; }
        body.fmt-a4 .label { width: 100%; max-width: 19cm; padding: 26px; }

        /* A5 (~1.3x) */
        body.fmt-a5 .env-code { font-size: 27px; }
        body.fmt-a5 .brand { font-size: 16px; }
        body.fmt-a5 .big-text { font-size: 19px; }
        body.fmt-a5 .med-text { font-size: 14px; }
        body.fmt-a5 .grid .box .v { font-size: 18px; }
        body.fmt-a5 .status-chip { font-size: 15px; }
        body.fmt-a5 .guide-box .n { font-size: 26px; }
        body.fmt-a5 .qr-img { width: 3cm; height: 3cm; }
        body.fmt-a5 .footer { font-size: 12px; }

        /* A4 (~1.9x, ocupa la hoja) */
        body.fmt-a4 .env-code { font-size: 42px; }
        body.fmt-a4 .brand { font-size: 22px; }
        body.fmt-a4 .section-title { font-size: 13px; }
        body.fmt-a4 .big-text { font-size: 30px; }
        body.fmt-a4 .med-text { font-size: 20px; }
        body.fmt-a4 .grid .box .v { font-size: 27px; }
        body.fmt-a4 .status-chip { font-size: 21px; padding: 7px 18px; }
        body.fmt-a4 .guide-box .n { font-size: 42px; }
        body.fmt-a4 .guide-box .l { font-size: 13px; }
        body.fmt-a4 .qr-img { width: 4cm; height: 4cm; }
        body.fmt-a4 .footer { font-size: 15px; }
        body.fmt-a4 .label-header, body.fmt-a4 .section, body.fmt-a4 .grid { margin-bottom: 16px; }

        @media print {
            body { padding: 0; background: #fff; }
            body.fmt-a5 .label, body.fmt-a4 .label { width: 100%; max-width: none; }
        }
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
        .status-chip { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 12px; }
        .st-enviado { background: #198754; color: #fff; }
        .st-entregado { background: #0d6efd; color: #fff; }
        .st-otro { background: #6c757d; color: #fff; }
        .guide-box { border: 2px solid #000; padding: 6px 10px; text-align: center; margin-top: 6px; }
        .guide-box .l { font-size: 9px; text-transform: uppercase; }
        .guide-box .n { font-size: 20px; font-weight: bold; letter-spacing: 3px; }
        .footer { border-top: 2px solid #000; padding-top: 6px; margin-top: 8px; font-size: 10px; color: #555; text-align: center; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } }
    </style>
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

<div class="label">

    <div class="label-header">
        <div>
            <div class="section-title">N° Envío</div>
            <div class="env-code">{{ $shipment->shipment_code }}</div>
        </div>
        <div style="text-align:right;">
            <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            <div style="font-size:10px;color:#555;">{{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    {{-- Destinatario --}}
    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="big-text">{{ $shipment->full_name }}</div>
        @if($shipment->phone)<div class="med-text">Cel: {{ $shipment->phone }}</div>@endif
        @if($shipment->dni)<div class="med-text">DNI: {{ $shipment->dni }}</div>@endif
        @if($shipment->shipping_destination)<div class="med-text" style="font-weight:bold">{{ $shipment->shipping_destination }}</div>@endif
        @php
            $ubigeoLine = null;
            if (!empty($ubigeo)) {
                $ubigeoLine = $ubigeo['district'];
                if (!empty($ubigeo['province']))   $ubigeoLine .= ', ' . $ubigeo['province'];
                if (!empty($ubigeo['department'])) $ubigeoLine .= ', ' . $ubigeo['department'];
            } elseif ($shipment->destination_city) {
                $ubigeoLine = $shipment->destination_city;
            }
        @endphp
        @if($ubigeoLine)<div class="med-text" style="font-weight:bold">{{ $ubigeoLine }}</div>@endif
    </div>

    @if($shipment->package_content)
        <div class="section">
            <div class="section-title">Contenido del paquete</div>
            <div class="med-text">{{ $shipment->package_content }}</div>
        </div>
    @endif

    <div class="divider"></div>

    {{-- Agencia / Bulto N° (se escribe a mano) --}}
    <div class="grid">
        <div class="box">
            <div class="section-title">Agencia</div>
            <div class="v">{{ strtoupper($shipment->shipping_agency ?: '—') }}</div>
        </div>
        <div class="box">
            <div class="section-title">Bulto N° (escribir a mano)</div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
                <span class="bulto-box"></span>
                <span class="bulto-total">/ {{ (int) ($shipment->package_count ?: 1) }}</span>
            </div>
        </div>
    </div>

    @if($shipment->tracking_number)
        <div class="guide-box">
            <div class="l">Guía</div>
            <div class="n">{{ $shipment->tracking_number }}</div>
        </div>
    @else
        <div class="guide-box" style="border-style:dashed;">
            <div class="l">Guía</div>
            <div class="n" style="font-size:13px;letter-spacing:1px;color:#888;">PENDIENTE DE CARGAR</div>
        </div>
    @endif

    @if(!empty($qr))
        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;border-top:1px dashed #999;padding-top:8px;">
            <img class="qr-img" src="data:image/png;base64,{{ $qr }}" alt="QR estado del envío">
            <div style="font-size:11px;color:#222;line-height:1.35;">
                <strong>Escanea el QR</strong><br>
                para registrar el estado del paquete:<br>preparando · listo · enviado.
            </div>
        </div>
    @endif

    @if($shipment->notes)
        <div class="section" style="margin-top:8px;">
            <div class="section-title">Información adicional</div>
            <div class="med-text">{{ $shipment->notes }}</div>
        </div>
    @endif

    @if($shipment->observation)
        <div class="section" style="margin-top:8px;">
            <div class="section-title">Observación</div>
            <div class="med-text">{{ $shipment->observation }}</div>
        </div>
    @endif

    <div class="footer">
        Registro y Control de Envíos · {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}
    </div>

</div>

</body>
</html>
