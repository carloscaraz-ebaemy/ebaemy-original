<style>
/* ── Etiqueta de despacho (partial — modo batch) ── */
.lbl-wrap * { box-sizing: border-box; margin: 0; padding: 0; }
.lbl-wrap { font-family: Arial, sans-serif; font-size: 12px; }

.lbl { width: 10cm; background: white; border: 2px solid #000; padding: 12px; page-break-inside: avoid; page-break-after: always; }
.lbl:last-child { page-break-after: avoid; }

.lbl-header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-start; }
.lbl-header .doc-num { font-size: 18px; font-weight: bold; }

.delivery-type  { font-size: 13px; font-weight: bold; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
.del-courier    { background: #198754; color: white; }
.del-pickup     { background: #6c757d; color: white; }

.urgent-banner  { background: #dc3545; color: white; text-align: center; font-weight: bold; font-size: 14px; padding: 4px; margin-bottom: 8px; letter-spacing: 2px; }

.section-title  { font-size: 9px; text-transform: uppercase; color: #666; letter-spacing: 1px; margin-bottom: 2px; }
.section        { margin-bottom: 8px; }
.text-large     { font-size: 14px; font-weight: bold; }
.text-normal    { font-size: 12px; }
.divider        { border-top: 1px dashed #aaa; margin: 8px 0; }

table.items-table { width: 100%; border-collapse: collapse; font-size: 11px; }
table.items-table th { background: #f0f0f0; border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
table.items-table td { border: 1px solid #ccc; padding: 3px 5px; vertical-align: top; }
table.items-table td.item-qty { text-align: center; font-weight: bold; }

.tracking-box           { border: 2px solid #000; padding: 6px 10px; text-align: center; margin-top: 6px; }
.tracking-box .tracking-label  { font-size: 9px; text-transform: uppercase; }
.tracking-box .tracking-number { font-size: 20px; font-weight: bold; letter-spacing: 3px; }

.lbl-footer { border-top: 2px solid #000; padding-top: 6px; margin-top: 8px; font-size: 10px; color: #555; text-align: center; }
</style>

<div class="lbl-wrap">
<div class="lbl">

    @if($saleNote->is_urgent)
        <div class="urgent-banner">&#9889; PEDIDO URGENTE &#9889;</div>
    @endif

    <div class="lbl-header">
        <div>
            <div class="section-title">Nota de Venta</div>
            <div class="doc-num">{{ $saleNote->number_full }}</div>
            <div style="font-size:11px;color:#555;">{{ \Carbon\Carbon::parse($saleNote->date_of_issue)->format('d/m/Y') }}</div>
        </div>
        <div>
            @if($saleNote->logistic_status === \App\Enums\LogisticStatusEnum::RECOGIDO || $saleNote->delivery_type === \App\Enums\DeliveryTypeEnum::PICKUP)
                <span class="delivery-type del-pickup">&#x1F3EA; Recojo Tienda</span>
            @else
                <span class="delivery-type del-courier">&#x1F69A; Courier</span>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="text-large">{{ $saleNote->shipping_recipient ?? $saleNote->customer->name ?? '—' }}</div>
        @if($saleNote->shipping_phone)
            <div class="text-normal">Cel: {{ $saleNote->shipping_phone }}</div>
        @elseif($saleNote->customer->number ?? false)
            <div class="text-normal">RUC/DNI: {{ $saleNote->customer->number }}</div>
        @endif
        @if($saleNote->shipping_address)
            <div class="text-normal" style="font-weight:bold">{{ $saleNote->shipping_address }}</div>
            @if($saleNote->shipping_city)<div class="text-normal">{{ $saleNote->shipping_city }}</div>@endif
        @elseif($saleNote->customer->address ?? false)
            <div class="text-normal">{{ $saleNote->customer->address }}</div>
        @endif
        @if($saleNote->shipping_notes)
            <div style="margin-top:4px;font-size:11px;color:#c0392b;font-weight:bold">&#9888; {{ $saleNote->shipping_notes }}</div>
        @endif
    </div>

    <div class="divider"></div>

    @if($saleNote->courier_name)
        <div class="section">
            <div class="section-title">Courier</div>
            <div class="text-normal"><strong>{{ $saleNote->courier_name }}</strong></div>
        </div>
    @endif
    @if($saleNote->pickup_person)
        <div class="section">
            <div class="section-title">Recoge</div>
            <div class="text-normal"><strong>{{ $saleNote->pickup_person }}</strong></div>
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
            <div class="text-normal">{{ \Carbon\Carbon::parse($saleNote->dispatch_date)->format('d/m/Y H:i') }}</div>
        </div>
    @endif

    <div class="divider"></div>

    <div class="section">
        <div class="section-title">Contenido del paquete</div>
        <table class="items-table">
            <thead><tr><th>Producto</th><th style="width:40px;text-align:center;">Cant.</th></tr></thead>
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

    <div class="lbl-footer">
        Preparado por: {{ $saleNote->user?->name ?? '—' }}
        &nbsp;·&nbsp;
        Impreso: {{ now()->format('d/m/Y H:i') }}
    </div>

</div>
</div>
