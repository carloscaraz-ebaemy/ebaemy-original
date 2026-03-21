<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }

        .page { padding: 20px; }

        /* Cabecera */
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 15px; }
        .header-logo { font-size: 18pt; font-weight: bold; color: #1a56db; }
        .header-doc { text-align: right; }
        .header-doc .doc-type { font-size: 14pt; font-weight: bold; border: 2px solid #333; padding: 5px 15px; }
        .header-doc .doc-number { font-size: 11pt; color: #555; margin-top: 4px; }

        /* Secciones */
        .section { margin-bottom: 12px; }
        .section-title { background: #1a56db; color: white; padding: 4px 10px; font-weight: bold; font-size: 9pt; margin-bottom: 6px; }

        /* Grid de datos */
        .data-grid { width: 100%; border-collapse: collapse; }
        .data-grid td { padding: 4px 8px; border: 1px solid #ddd; font-size: 9pt; }
        .data-grid td.label { background: #f8f9fa; font-weight: bold; width: 35%; }

        /* Tabla de ítems */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items-table th { background: #1a56db; color: white; padding: 5px 8px; font-size: 9pt; text-align: left; }
        .items-table td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9pt; }
        .items-table tr:nth-child(even) td { background: #f9f9f9; }

        /* Totales */
        .totals { margin-top: 10px; text-align: right; }
        .totals table { display: inline-block; }
        .totals td { padding: 3px 8px; font-size: 10pt; }
        .totals .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 12pt; }

        /* Firmas */
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .signature-box { text-align: center; width: 45%; border-top: 1px solid #333; padding-top: 5px; font-size: 9pt; }

        /* Footer */
        .footer { margin-top: 20px; border-top: 1px dashed #999; padding-top: 8px; text-align: center; font-size: 8pt; color: #777; }

        /* Alerta multitenant */
        .tenant-badge { background: #f0fdf4; border: 1px solid #86efac; padding: 3px 8px; border-radius: 4px; font-size: 8pt; color: #166534; }
    </style>
</head>
<body>
<div class="page">

    <!-- Cabecera -->
    <div class="header">
        <div>
            <div class="header-logo">
                {{ $order->warehouse?->establishment?->company?->name ?? config('app.name') }}
            </div>
            <div style="font-size: 9pt; color: #555; margin-top: 4px;">
                {{ $order->warehouse?->establishment?->company?->address ?? '' }}<br>
                RUC: {{ $order->warehouse?->establishment?->company?->number ?? '' }}
            </div>
            <div class="tenant-badge mt-1">Almacén: {{ $order->warehouse?->description }}</div>
        </div>
        <div class="header-doc">
            <div class="doc-type">GUÍA DE REMISIÓN</div>
            <div class="doc-number">
                @if($guide->series && $guide->number)
                    {{ $guide->series }}-{{ $guide->number }}
                @else
                    Pendiente de numeración
                @endif
            </div>
            <div style="font-size: 9pt; color: #555; margin-top: 4px;">
                Fecha: {{ $guide->dispatch_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}
            </div>
            @if($guide->tracking_code)
            <div style="font-size: 9pt; color: #1a56db; margin-top: 2px;">
                Tracking: <strong>{{ $guide->tracking_code }}</strong>
            </div>
            @endif
        </div>
    </div>

    <!-- Datos del destinatario -->
    <div class="section">
        <div class="section-title">DATOS DEL DESTINATARIO</div>
        <table class="data-grid">
            <tr>
                <td class="label">Nombre / Razón Social</td>
                <td><strong>{{ $order->recipient_name ?? $order->customer?->name }}</strong></td>
                <td class="label">Teléfono</td>
                <td>{{ $order->recipient_phone ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección de entrega</td>
                <td colspan="3">{{ $order->destination_address }}</td>
            </tr>
            <tr>
                <td class="label">Distrito / Provincia</td>
                <td>{{ $order->destination_district }}</td>
                <td class="label">Ubigeo</td>
                <td>{{ $guide->destination_ubigeo ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <!-- Datos del transportista -->
    <div class="section">
        <div class="section-title">DATOS DEL TRANSPORTISTA</div>
        <table class="data-grid">
            <tr>
                <td class="label">Empresa transportista</td>
                <td>{{ $guide->carrier_name ?? '—' }}</td>
                <td class="label">RUC</td>
                <td>{{ $guide->carrier_ruc ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Conductor</td>
                <td>{{ $guide->driver_name ?? '—' }}</td>
                <td class="label">Licencia</td>
                <td>{{ $guide->driver_license ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Placa del vehículo</td>
                <td>{{ $guide->carrier_plate ?? '—' }}</td>
                <td class="label">Dirección de partida</td>
                <td>{{ $guide->origin_address ?? $order->warehouse?->establishment?->address ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <!-- Ítems del pedido -->
    <div class="section">
        <div class="section-title">DETALLE DE BIENES A TRASLADAR</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 10%">Cód.</th>
                    <th style="width: 45%">Descripción</th>
                    <th style="width: 10%">Unidad</th>
                    <th style="width: 10%">Cantidad</th>
                    <th style="width: 10%">P. Unit</th>
                    <th style="width: 10%">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $item->item?->internal_id ?? '—' }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->unit_type_id }}</td>
                    <td style="text-align:center">{{ number_format($item->quantity, 2) }}</td>
                    <td style="text-align:right">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align:right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totales -->
        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align:right">{{ $order->currency_type_id }} {{ number_format($order->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td>IGV (18%):</td>
                    <td style="text-align:right">{{ $order->currency_type_id }} {{ number_format($order->igv, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td style="text-align:right">{{ $order->currency_type_id }} {{ number_format($order->total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Comprobante vinculado -->
    @if($order->document)
    <div class="section">
        <div class="section-title">COMPROBANTE ELECTRÓNICO VINCULADO</div>
        <table class="data-grid">
            <tr>
                <td class="label">Tipo</td>
                <td>{{ $order->document->document_type_id === '01' ? 'Factura' : 'Boleta de Venta' }}</td>
                <td class="label">Número</td>
                <td><strong>{{ $order->document->series }}-{{ $order->document->number }}</strong></td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Firmas -->
    <div class="signatures">
        <div class="signature-box">
            <strong>Almacenero / Emisor</strong><br>
            {{ auth()->user()?->name ?? '___________________' }}<br>
            <small>Firma y sello</small>
        </div>
        <div class="signature-box">
            <strong>Receptor / Conformidad</strong><br>
            {{ $order->recipient_name }}<br>
            <small>Firma y documento de identidad</small>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i:s') }} —
        Orden Logística #{{ $order->id }} — Sistema ERP SaaS
    </div>

</div>
</body>
</html>
