@if(!request()->boolean('partial'))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas — Impresión en Lote ({{ $saleNotes->count() }} pedidos)</title>
@endif
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #f5f5f5;
            padding: 20px;
        }

        /* ── Barra de acciones (se oculta al imprimir) ──────────── */
        .print-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #198754;
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0,0,0,.3);
        }
        .print-bar button {
            padding: 8px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .btn-print  { background: white; color: #198754; }
        .btn-close2 { background: rgba(255,255,255,.2); color: white; }

        .labels-wrapper {
            margin-top: 60px; /* espacio para la barra fija */
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        /* ── Etiqueta individual ────────────────────────────────── */
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
        .doc-number  { font-size: 18px; font-weight: bold; }
        .delivery-type {
            font-size: 13px; font-weight: bold;
            padding: 4px 8px; border-radius: 4px;
            text-transform: uppercase;
        }
        .delivery-courier { background: #198754; color: white; }
        .delivery-pickup  { background: #6c757d; color: white; }

        .urgent-banner {
            background: #dc3545; color: white;
            text-align: center; font-weight: bold;
            font-size: 14px; padding: 4px;
            margin-bottom: 8px; letter-spacing: 2px;
        }

        .section-title {
            font-size: 9px; text-transform: uppercase;
            color: #666; letter-spacing: 1px; margin-bottom: 2px;
        }
        .section { margin-bottom: 8px; }
        .big-text { font-size: 14px; font-weight: bold; }
        .med-text  { font-size: 12px; }
        .divider   { border-top: 1px dashed #aaa; margin: 8px 0; }

        table.items-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.items-table th { background: #f0f0f0; border: 1px solid #ccc; padding: 3px 5px; }
        table.items-table td { border: 1px solid #ccc; padding: 3px 5px; vertical-align: top; }
        table.items-table td.item-qty { text-align: center; font-weight: bold; }

        .tracking-box {
            border: 2px solid #000; padding: 6px 10px;
            text-align: center; margin-top: 6px;
        }
        .tracking-box .tracking-label  { font-size: 9px; text-transform: uppercase; }
        .tracking-box .tracking-number { font-size: 20px; font-weight: bold; letter-spacing: 3px; }

        .footer {
            border-top: 2px solid #000; padding-top: 6px;
            margin-top: 8px; font-size: 10px; color: #555; text-align: center;
        }

        /* ── Impresión ──────────────────────────────────────────── */
        @media print {
            .print-bar { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .labels-wrapper { margin-top: 0; gap: 0; }
            .label {
                page-break-after: always;
                page-break-inside: avoid;
                width: 10cm;
                margin: 0 auto;
            }
            .label:last-child { page-break-after: avoid; }
        }
    </style>
</head>
<body>

<div class="print-bar no-print">
    <span style="font-size:15px;font-weight:bold;">
        Impresión en lote &mdash; {{ $saleNotes->count() }} etiqueta(s)
    </span>
    <button class="btn-print"  onclick="window.print()">&#128438; Imprimir Todo</button>
    <button class="btn-close2" onclick="window.close()">&times; Cerrar</button>
</div>

<div class="labels-wrapper">
@foreach($saleNotes as $saleNote)
<div class="label">

    @if($saleNote->is_urgent)
        <div class="urgent-banner">&#9889; PEDIDO URGENTE &#9889;</div>
    @endif

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
                <span class="delivery-type delivery-pickup">&#x1F3EA; Recojo Tienda</span>
            @else
                <span class="delivery-type delivery-courier">&#x1F69A; Courier</span>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="big-text">{{ $saleNote->shipping_recipient ?? $saleNote->customer->name ?? '—' }}</div>
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
            <div style="margin-top:4px;font-size:11px;color:#c0392b;font-weight:bold">&#9888; {{ $saleNote->shipping_notes }}</div>
        @endif
    </div>

    <div class="divider"></div>

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

    <div class="section">
        <div class="section-title">Contenido del paquete</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="width:40px;text-align:center;">Cant.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saleNote->items as $item)
                    <tr>
                        <td>{{ $item->relation_item->description ?? $item->description ?? '—' }}</td>
                        <td class="item-qty">{{ number_format($item->quantity, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        Preparado por: {{ $saleNote->user?->name ?? '—' }}
        &nbsp;·&nbsp;
        Impreso: {{ now()->format('d/m/Y H:i') }}
    </div>

</div>
@endforeach
</div>

@if(!request()->boolean('partial'))
</body>
</html>
@endif
