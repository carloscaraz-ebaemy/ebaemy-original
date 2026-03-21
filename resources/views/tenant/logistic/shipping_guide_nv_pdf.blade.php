<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
        .page { padding: 20px; }

        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1a56db; padding-bottom: 12px; margin-bottom: 15px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1a56db; }
        .company-info { font-size: 8pt; color: #555; margin-top: 4px; }
        .doc-box { text-align: right; border: 2px solid #1a56db; padding: 8px 15px; border-radius: 4px; }
        .doc-type { font-size: 11pt; font-weight: bold; color: #1a56db; }
        .doc-number { font-size: 10pt; color: #333; margin-top: 3px; }
        .doc-date { font-size: 8pt; color: #777; margin-top: 2px; }

        .section { margin-bottom: 12px; }
        .section-title { background: #1a56db; color: white; padding: 4px 10px; font-weight: bold; font-size: 9pt; margin-bottom: 6px; }

        .data-grid { width: 100%; border-collapse: collapse; }
        .data-grid td { padding: 4px 8px; border: 1px solid #ddd; font-size: 9pt; }
        .data-grid td.label { background: #f0f4ff; font-weight: bold; width: 30%; }

        .two-col { display: flex; gap: 12px; margin-bottom: 12px; }
        .two-col > div { flex: 1; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .items-table th { background: #1a56db; color: white; padding: 5px 8px; font-size: 9pt; text-align: left; }
        .items-table td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9pt; }
        .items-table tr:nth-child(even) td { background: #f9f9f9; }
        .items-table .text-right { text-align: right; }

        .totals { text-align: right; margin-top: 8px; }
        .totals table { display: inline-table; border-collapse: collapse; }
        .totals td { padding: 3px 10px; font-size: 9pt; border: 1px solid #ddd; }
        .totals td.label { background: #f0f4ff; font-weight: bold; }
        .totals .total-row td { border-top: 2px solid #1a56db; font-weight: bold; font-size: 11pt; color: #1a56db; }

        .signatures { display: flex; justify-content: space-around; margin-top: 50px; }
        .signature-box { text-align: center; width: 40%; }
        .signature-line { border-top: 1px solid #333; padding-top: 5px; font-size: 9pt; }

        .footer { margin-top: 20px; border-top: 1px dashed #aaa; padding-top: 8px; text-align: center; font-size: 8pt; color: #888; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 8pt; font-weight: bold; }
        .badge-urgent { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="page">

    {{-- Cabecera --}}
    <div class="header">
        <div>
            <div class="company-name">
                {{ $company->name ?? config('app.name') }}
            </div>
            <div class="company-info">
                RUC: {{ $company->number ?? '' }}<br>
                {{ $company->address ?? '' }}
            </div>
        </div>
        <div class="doc-box">
            <div class="doc-type">GUÍA DE REMISIÓN</div>
            <div class="doc-number">NV {{ $saleNote->number_full }}</div>
            <div class="doc-date">{{ \Carbon\Carbon::parse($guide->dispatch_date)->format('d/m/Y') }}</div>
            @if($guide->tracking_code)
                <div class="doc-date" style="margin-top:4px; font-weight:bold;">
                    Tracking: {{ $guide->tracking_code }}
                </div>
            @endif
        </div>
    </div>

    {{-- Remitente y Destinatario --}}
    <div class="two-col">
        <div>
            <div class="section-title">REMITENTE</div>
            <table class="data-grid">
                <tr>
                    <td class="label">Empresa</td>
                    <td>{{ $company->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">RUC</td>
                    <td>{{ $company->number ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Dirección</td>
                    <td>{{ $guide->origin_address ?? $company->address ?? '—' }}</td>
                </tr>
            </table>
        </div>
        <div>
            <div class="section-title">DESTINATARIO</div>
            <table class="data-grid">
                <tr>
                    <td class="label">Nombre</td>
                    <td>
                        {{ $saleNote->shipping_recipient ?? optional($saleNote->person)->name ?? '—' }}
                        @if($saleNote->is_urgent)
                            <span class="badge badge-urgent">URGENTE</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Teléfono</td>
                    <td>{{ $saleNote->shipping_phone ?? optional($saleNote->person)->telephone ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Dirección</td>
                    <td>{{ $saleNote->shipping_address ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Ciudad</td>
                    <td>{{ $saleNote->shipping_city ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Transportista --}}
    <div class="section">
        <div class="section-title">DATOS DEL TRANSPORTE</div>
        <table class="data-grid">
            <tr>
                <td class="label">Courier / Empresa</td>
                <td>{{ $guide->carrier_name }}</td>
                <td class="label">N° Tracking</td>
                <td>{{ $guide->tracking_code ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Fecha despacho</td>
                <td>{{ \Carbon\Carbon::parse($guide->dispatch_date)->format('d/m/Y') }}</td>
                <td class="label">Bultos</td>
                <td>{{ $saleNote->shipping_packages ?? 1 }}</td>
            </tr>
            @if($saleNote->shipping_notes)
            <tr>
                <td class="label">Observaciones</td>
                <td colspan="3">{{ $saleNote->shipping_notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Productos --}}
    <div class="section">
        <div class="section-title">PRODUCTOS</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:8%">Código</th>
                    <th>Descripción</th>
                    <th style="width:10%" class="text-right">Cant.</th>
                    <th style="width:12%" class="text-right">P. Unit.</th>
                    <th style="width:13%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saleNote->items as $item)
                <tr>
                    <td>{{ optional($item->relation_item)->internal_id ?? '—' }}</td>
                    <td>{{ optional($item->relation_item)->description ?? $item->description ?? '—' }}</td>
                    <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">{{ $saleNote->currency_type_id }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $saleNote->currency_type_id }} {{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal</td>
                    <td>{{ $saleNote->currency_type_id }} {{ number_format($saleNote->subtotal ?? $saleNote->total, 2) }}</td>
                </tr>
                @if(($saleNote->total_igv ?? 0) > 0)
                <tr>
                    <td class="label">IGV (18%)</td>
                    <td>{{ $saleNote->currency_type_id }} {{ number_format($saleNote->total_igv, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="label">TOTAL</td>
                    <td>{{ $saleNote->currency_type_id }} {{ number_format($saleNote->total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Firmas --}}
    <div class="signatures">
        <div class="signature-box">
            <div style="height: 40px;"></div>
            <div class="signature-line">Almacenero / Despachador</div>
        </div>
        <div class="signature-box">
            <div style="height: 40px;"></div>
            <div class="signature-line">Transportista / Courier</div>
        </div>
        <div class="signature-box">
            <div style="height: 40px;"></div>
            <div class="signature-line">Recibido conforme</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i:s') }} &nbsp;|&nbsp; Pedido NV {{ $saleNote->number_full }}
        &nbsp;|&nbsp; {{ config('app.name') }}
    </div>

</div>
</body>
</html>
