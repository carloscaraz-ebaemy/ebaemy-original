<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rótulo — {{ $shipment->shipment_code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; background: #f5f5f5; display: flex; justify-content: center; padding: 20px; }
        .label { width: 10cm; background: #fff; border: 2px solid #000; padding: 12px; page-break-inside: avoid; }
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
<body>

<div class="no-print" style="position:fixed;top:15px;right:15px;z-index:999;">
    <button onclick="window.print()" style="padding:10px 20px;background:#198754;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">🖨️ Imprimir rótulo</button>
    <button onclick="window.close()" style="padding:10px 16px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;margin-left:8px;">✕ Cerrar</button>
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
        @if(!empty($ubigeo))
            <div class="med-text" style="font-weight:bold">{{ $ubigeo['district'] }}@if($ubigeo['province']), {{ $ubigeo['province'] }}@endif@if($ubigeo['department']), {{ $ubigeo['department'] }}@endif</div>
        @elseif($shipment->destination_city)
            <div class="med-text">{{ $shipment->destination_city }}</div>
        @endif
    </div>

    @if($shipment->package_content || $shipment->package_count)
        <div class="section">
            <div class="section-title">Contenido del paquete</div>
            <div class="med-text">
                {{ $shipment->package_content ?: '—' }}
                <span style="float:right;font-weight:bold;">{{ (int) ($shipment->package_count ?: 1) }} bulto{{ (int) ($shipment->package_count ?: 1) === 1 ? '' : 's' }}</span>
            </div>
        </div>
    @endif

    <div class="divider"></div>

    {{-- Agencia / Estado / Guía --}}
    <div class="grid">
        <div class="box">
            <div class="section-title">Agencia</div>
            <div class="v">{{ strtoupper($shipment->shipping_agency ?: '—') }}</div>
        </div>
        <div class="box">
            <div class="section-title">Estado</div>
            <div class="v">
                @php $stClass = $shipment->status === 'enviado' ? 'st-enviado' : ($shipment->status === 'entregado' ? 'st-entregado' : 'st-otro'); @endphp
                <span class="status-chip {{ $stClass }}">{{ strtoupper($shipment->status_label) }}</span>
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

<script>
window.addEventListener('load', function(){ if (window.top === window) window.print(); });
</script>

</body>
</html>
