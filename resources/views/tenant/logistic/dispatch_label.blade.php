<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta — {{ $saleNote->number_full }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .label {
            width: 10cm;
            background: white;
            border: 2px solid #000;
            padding: 12px;
            page-break-inside: avoid;
        }

        .label-header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .label-header .doc-number {
            font-size: 18px;
            font-weight: bold;
        }

        .label-header .delivery-type {
            font-size: 13px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .delivery-courier { background: #198754; color: white; }
        .delivery-pickup  { background: #6c757d; color: white; }

        .urgent-banner {
            background: #dc3545;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 4px;
            margin-bottom: 8px;
            letter-spacing: 2px;
        }

        .section-title {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .section {
            margin-bottom: 8px;
        }

        .big-text { font-size: 14px; font-weight: bold; }
        .med-text  { font-size: 12px; }

        .divider { border-top: 1px dashed #aaa; margin: 8px 0; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.items th {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 3px 5px;
            text-align: left;
        }
        table.items td {
            border: 1px solid #ccc;
            padding: 3px 5px;
            vertical-align: top;
        }
        table.items td.qty { text-align: center; font-weight: bold; }

        .footer {
            border-top: 2px solid #000;
            padding-top: 6px;
            margin-top: 8px;
            font-size: 10px;
            color: #555;
            text-align: center;
        }

        .tracking-box {
            border: 2px solid #000;
            padding: 6px 10px;
            text-align: center;
            margin-top: 6px;
        }
        .tracking-box .tracking-label { font-size: 9px; text-transform: uppercase; }
        .tracking-box .tracking-number { font-size: 20px; font-weight: bold; letter-spacing: 3px; }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .label { border: 2px solid #000; }
        }
    </style>
</head>
<body>

{{-- Botón imprimir (se oculta al imprimir) --}}
<div class="no-print" style="position:fixed;top:15px;right:15px;z-index:999;">
    <button onclick="window.print()"
            style="padding:10px 20px;background:#198754;color:white;border:none;border-radius:6px;font-size:14px;cursor:pointer;">
        🖨️ Imprimir Etiqueta
    </button>
    <button onclick="window.close()"
            style="padding:10px 16px;background:#6c757d;color:white;border:none;border-radius:6px;font-size:14px;cursor:pointer;margin-left:8px;">
        ✕ Cerrar
    </button>
</div>

<div class="label">

    {{-- Banner URGENTE --}}
    @if($saleNote->is_urgent)
        <div class="urgent-banner">&#9889; PEDIDO URGENTE &#9889;</div>
    @endif

    {{-- Cabecera --}}
    <div class="label-header">
        <div>
            <div class="section-title">Nota de Venta</div>
            <div class="doc-number">{{ $saleNote->number_full }}</div>
            <div style="font-size:11px;color:#555;">
                {{ \Carbon\Carbon::parse($saleNote->date_of_issue)->format('d/m/Y') }}
            </div>
        </div>
        <div>
            @if($saleNote->logistic_status === \App\Enums\LogisticStatusEnum::RECOGIDO || $saleNote->delivery_type === \App\Enums\DeliveryTypeEnum::PICKUP)
                <span class="delivery-type delivery-pickup">🏪 Recojo Tienda</span>
            @else
                <span class="delivery-type delivery-courier">🚚 Courier</span>
            @endif
        </div>
    </div>

    {{-- Destinatario --}}
    <div class="section">
        <div class="section-title">Destinatario</div>
        {{-- Usa datos de envío si existen, sino datos del cliente --}}
        <div class="big-text">
            {{ $saleNote->shipping_recipient ?? $saleNote->customer->name ?? '—' }}
        </div>
        @if($saleNote->shipping_phone)
            <div class="med-text">Cel: {{ $saleNote->shipping_phone }}</div>
        @elseif($saleNote->customer->number ?? false)
            <div class="med-text">RUC/DNI: {{ $saleNote->customer->number }}</div>
        @endif
        @if($saleNote->shipping_address)
            <div class="med-text" style="font-weight:bold">{{ $saleNote->shipping_address }}</div>
            @if($saleNote->shipping_city)
                <div class="med-text">{{ $saleNote->shipping_city }}</div>
            @endif
        @elseif($saleNote->customer->address ?? false)
            <div class="med-text">{{ $saleNote->customer->address }}</div>
        @endif
        @if($saleNote->shipping_notes)
            <div style="margin-top:4px;font-size:11px;color:#c0392b;font-weight:bold">
                ⚠ {{ $saleNote->shipping_notes }}
            </div>
        @endif
    </div>

    <div class="divider"></div>

    {{-- Courier / Persona que recoge --}}
    @if($saleNote->courier_name)
        <div class="section">
            <div class="section-title">Courier</div>
            <div class="med-text"><strong>{{ $saleNote->courier_name }}</strong></div>
        </div>
    @endif

    @if($saleNote->pickup_person)
        <div class="section">
            <div class="section-title">Recoge</div>
            <div class="med-text"><strong>{{ $saleNote->pickup_person }}</strong></div>
        </div>
    @endif

    {{-- Número de guía --}}
    @if($saleNote->tracking_number)
        <div class="tracking-box">
            <div class="tracking-label">N° de Guía / Tracking</div>
            <div class="tracking-number">{{ $saleNote->tracking_number }}</div>
        </div>
    @endif

    @if($saleNote->dispatch_date)
        <div class="section" style="margin-top:6px;">
            <div class="section-title">Fecha de despacho</div>
            <div class="med-text">{{ \Carbon\Carbon::parse($saleNote->dispatch_date)->format('d/m/Y H:i') }}</div>
        </div>
    @endif

    <div class="divider"></div>

    {{-- Ítems --}}
    <div class="section">
        <div class="section-title">Contenido del paquete</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="width:40px;text-align:center;">Cant.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saleNote->items as $item)
                    <tr>
                        <td>
                            {{ $item->relation_item->description ?? $item->description ?? '—' }}
                            @if($item->relation_item->internal_id ?? false)
                                <br><span style="font-size:10px;color:#888;">Cód: {{ $item->relation_item->internal_id }}</span>
                            @endif
                        </td>
                        <td class="qty">{{ number_format($item->quantity, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Preparado por: {{ $saleNote->user?->name ?? '—' }}
        &nbsp;·&nbsp;
        Impreso: {{ now()->format('d/m/Y H:i') }}
    </div>

</div>

<script>
// Auto-imprimir solo si se abre directamente (no dentro de un iframe/modal)
window.addEventListener('load', function(){
    if (window.top === window) window.print();
});
</script>

</body>
</html>
