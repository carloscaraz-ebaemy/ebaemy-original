@php
    $money = fn ($v) => number_format((float) $v, 2, '.', ',');
    $p = $h['person'];
    $s = $h['summary'];
    $receivables = collect($h['documents'])->concat($h['sale_notes'])->filter(fn ($x) => $x['pending'] > 0);
    $fieldLabels = [
        'name' => 'Nombre', 'trade_name' => 'Nombre comercial', 'number' => 'N° de documento',
        'identity_document_type_id' => 'Tipo de documento', 'email' => 'Correo', 'telephone' => 'Teléfono',
        'address' => 'Dirección', 'department_id' => 'Departamento', 'province_id' => 'Provincia',
        'district_id' => 'Distrito', 'country_id' => 'País', 'enabled' => 'Habilitado',
        'credit_days' => 'Días de crédito', 'observation' => 'Observación', 'contact' => 'Contacto',
        'person_type_id' => 'Tipo de cliente', 'seller_id' => 'Vendedor', 'status' => 'Estado',
    ];
    $payStatus = function ($row) {
        if (in_array($row['state'], ['09', '11', '13'])) {
            return $row['state_label'];
        }
        if (!empty($row['converted'])) {
            return 'Convertida';
        }
        if ($row['pending'] <= 0) {
            return 'Pagada';
        }
        return $row['paid'] > 0 ? 'Pago parcial' : 'Pendiente';
    };
@endphp
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 9pt; color: #1e293b; }
    h1 { font-size: 15pt; margin: 0; }
    h2 { font-size: 11pt; margin: 14px 0 6px; padding: 4px 6px; background: #eef2ff; color: #3730a3; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .muted { color: #64748b; }
    .kv td { padding: 2px 4px; }
    .kv td.k { color: #64748b; width: 22%; }
    .kpi td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 4px; }
    .kpi .v { font-size: 11pt; font-weight: bold; }
    .kpi .l { font-size: 7.5pt; color: #64748b; }
    .grid th { background: #f1f5f9; font-size: 8pt; text-align: left; padding: 3px 4px; border-bottom: 1px solid #cbd5e1; }
    .grid td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; }
    .r, .grid th.r, .sale-sub th.r { text-align: right; }
    .c { text-align: center; }
    .sale { margin-bottom: 8px; border: 1px solid #cbd5e1; page-break-inside: avoid; }
    .sale-head td { background: #f8fafc; padding: 4px; font-size: 8.5pt; }
    .sale-sub td { padding: 2px 4px 2px 14px; font-size: 8pt; }
    .sale-sub th { padding: 2px 4px 2px 14px; font-size: 7.5pt; color: #64748b; text-align: left; }
    .red { color: #b91c1c; }
    .green { color: #15803d; }
    .void { color: #94a3b8; }
    .tag { font-size: 7.5pt; font-weight: bold; }
</style>
</head>
<body>

<table class="head">
    <tr>
        <td style="width: 60%;">
            @if($logo)
                <img src="{{ $logo }}" style="max-height: 50px; max-width: 160px;"><br>
            @endif
            <strong>{{ optional($company)->name }}</strong><br>
            <span class="muted">RUC {{ optional($company)->number }}</span>
        </td>
        <td class="r">
            <h1>Historial del cliente</h1>
            <span class="muted">Impreso el {{ $printed }}</span>
        </td>
    </tr>
</table>

<h2>Datos del cliente</h2>
<table class="kv">
    <tr><td class="k">Nombre</td><td><strong>{{ $p['name'] }}</strong></td><td class="k">Documento</td><td>{{ $p['number'] }}</td></tr>
    <tr><td class="k">Correo</td><td>{{ $p['email'] ?: '—' }}</td><td class="k">Teléfono</td><td>{{ $p['telephone'] ?: '—' }}</td></tr>
    <tr><td class="k">Dirección</td><td colspan="3">{{ $p['address'] ?: '—' }}</td></tr>
    <tr><td class="k">Cliente desde</td><td>{{ $p['created_at'] }}</td><td class="k">Última modificación</td><td>{{ $p['updated_at'] }}</td></tr>
</table>

<h2>Resumen</h2>
<table class="kpi">
    <tr>
        <td><div class="v">S/ {{ $money($s['total_lifetime']) }}</div><div class="l">Total comprado</div></td>
        <td><div class="v green">S/ {{ $money($s['total_paid']) }}</div><div class="l">Pagado</div></td>
        <td><div class="v red">S/ {{ $money($s['total_pending']) }}</div><div class="l">Por cobrar ({{ $s['pending_count'] }})</div></td>
        <td><div class="v">{{ $s['doc_count'] + $s['sn_count'] }}</div><div class="l">Ventas</div></td>
        <td><div class="v">{{ $s['annulled_count'] }}</div><div class="l">Anuladas</div></td>
        <td><div class="v">{{ $s['quotations_count'] }}</div><div class="l">Cotizaciones</div></td>
        <td><div class="v">{{ $s['last_purchase'] ?: '—' }}</div><div class="l">Última compra</div></td>
    </tr>
</table>
@if($s['purchases_count'] > 0)
    <p>Además es proveedor: {{ $s['purchases_count'] }} compra(s) por S/ {{ $money($s['payable_total']) }};
        <strong class="red">por pagar S/ {{ $money($s['payable_pending']) }}</strong>.</p>
@endif
<p class="muted" style="font-size: 7.5pt;">
    Una nota de venta convertida en boleta o factura se cuenta una sola vez, en el comprobante.
    Lo anulado o rechazado por SUNAT no suma como venta ni como deuda.
</p>

<h2>Cuentas por cobrar</h2>
@if($receivables->isEmpty())
    <p class="muted">El cliente no tiene saldos pendientes.</p>
@else
    <table class="grid">
        <tr><th>Documento</th><th>Fecha</th><th>Vence</th><th class="r">Total</th><th class="r">Pagado</th><th class="r">Saldo</th></tr>
        @foreach($receivables as $r)
            <tr>
                <td>{{ $r['label'] }}</td>
                <td>{{ $r['date'] }}</td>
                <td>{{ $r['date_of_due'] ?? '—' }}</td>
                <td class="r">{{ $money($r['total']) }}</td>
                <td class="r">{{ $money($r['paid']) }}</td>
                <td class="r red"><strong>{{ $money($r['pending']) }}</strong></td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" class="r"><strong>Total por cobrar</strong></td>
            <td class="r red"><strong>{{ $money($receivables->sum('pending')) }}</strong></td>
        </tr>
    </table>
@endif

@php
    $sections = [
        ['title' => 'Notas de venta', 'rows' => $h['sale_notes']],
        ['title' => 'Facturas y boletas', 'rows' => $h['documents']],
    ];
@endphp
@foreach($sections as $section)
    <h2>{{ $section['title'] }} ({{ count($section['rows']) }})</h2>
    @if(count($section['rows']) === 0)
        <p class="muted">Sin registros.</p>
    @endif
    @foreach($section['rows'] as $sale)
        @php $void = in_array($sale['state'], ['09', '11', '13']); @endphp
        <table class="sale">
            <tr class="sale-head">
                <td><strong class="{{ $void ? 'void' : '' }}">{{ $sale['label'] }}</strong>
                    @if(!empty($sale['type'])) <span class="muted">{{ $sale['type'] }}</span> @endif
                    · {{ $sale['date'] }}</td>
                <td class="r">Total <strong>{{ $money($sale['total']) }}</strong>
                    · Pagado <span class="green">{{ $money($sale['paid']) }}</span>
                    · Saldo <span class="red">{{ $money($sale['pending']) }}</span>
                    · <span class="tag {{ $void ? 'void' : ($sale['pending'] > 0 ? 'red' : 'green') }}">{{ $payStatus($sale) }}</span></td>
            </tr>
            @if(!empty($sale['from_quotation']) || !empty($sale['from_sale_note']) || !empty($sale['to_documents']) && count($sale['to_documents']))
                <tr class="sale-sub"><td colspan="2" class="muted">
                    @if(!empty($sale['from_quotation']))
                        Viene de la cotización {{ $sale['from_quotation'] }}.
                    @endif
                    @if(!empty($sale['from_sale_note']))
                        Emitida desde la nota de venta {{ $sale['from_sale_note'] }}.
                    @endif
                    @foreach($sale['to_documents'] ?? [] as $doc)
                        Pasó a {{ $doc['label'] }}{{ $doc['valid'] ? '' : ' (anulado/rechazado)' }}.
                    @endforeach
                </td></tr>
            @endif
            <tr><td colspan="2">
                <table class="sale-sub">
                    <tr><th>Cant.</th><th>Producto</th><th class="r">P. unit.</th><th class="r">Importe</th></tr>
                    @foreach($sale['items'] as $it)
                        <tr>
                            <td style="width: 8%;">{{ rtrim(rtrim(number_format($it['quantity'], 2, '.', ''), '0'), '.') }}</td>
                            <td>{{ $it['description'] }}</td>
                            <td class="r" style="width: 13%;">{{ $money($it['unit_price']) }}</td>
                            <td class="r" style="width: 13%;">{{ $money($it['total']) }}</td>
                        </tr>
                    @endforeach
                </table>
                @if(count($sale['payments']))
                    <table class="sale-sub">
                        <tr><th>Pago</th><th>Método</th><th>Operación</th><th class="r">Monto</th></tr>
                        @foreach($sale['payments'] as $pay)
                            <tr>
                                <td style="width: 15%;">{{ $pay['date'] }}</td>
                                <td>{{ $pay['method'] }}</td>
                                <td>{{ $pay['reference'] ?: '—' }}</td>
                                <td class="r green" style="width: 13%;">{{ $money($pay['amount']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @elseif(!$void && empty($sale['converted']))
                    <div class="red" style="padding: 2px 14px; font-size: 8pt;">Sin pagos registrados.</div>
                @endif
            </td></tr>
        </table>
    @endforeach
@endforeach

<h2>Cotizaciones ({{ count($h['quotations']) }})</h2>
@if(count($h['quotations']) === 0)
    <p class="muted">Sin cotizaciones.</p>
@endif
@foreach($h['quotations'] as $q)
    <table class="sale">
        <tr class="sale-head">
            <td><strong>{{ $q['label'] }}</strong> · {{ $q['date'] }}</td>
            <td class="r">Total <strong>{{ $money($q['total']) }}</strong> ·
                @if($q['state'] === '11')
                    <span class="tag void">Anulada</span>
                @elseif(count($q['to_sales']))
                    <span class="tag green">Pasó a {{ collect($q['to_sales'])->pluck('label')->implode(', ') }}</span>
                @elseif($q['converted'])
                    <span class="tag green">Convertida en venta</span>
                @else
                    <span class="tag red">Sin venta</span>
                @endif
            </td>
        </tr>
        <tr><td colspan="2">
            <table class="sale-sub">
                <tr><th>Cant.</th><th>Producto</th><th class="r">P. unit.</th><th class="r">Importe</th></tr>
                @foreach($q['items'] as $it)
                    <tr>
                        <td style="width: 8%;">{{ rtrim(rtrim(number_format($it['quantity'], 2, '.', ''), '0'), '.') }}</td>
                        <td>{{ $it['description'] }}</td>
                        <td class="r" style="width: 13%;">{{ $money($it['unit_price']) }}</td>
                        <td class="r" style="width: 13%;">{{ $money($it['total']) }}</td>
                    </tr>
                @endforeach
            </table>
        </td></tr>
    </table>
@endforeach

@if(count($h['purchases']))
    <h2>Compras a este contacto — cuentas por pagar ({{ count($h['purchases']) }})</h2>
    <table class="grid">
        <tr><th>Documento</th><th>Fecha</th><th>Vence</th><th>Estado</th><th class="r">Total</th><th class="r">Pagado</th><th class="r">Por pagar</th></tr>
        @foreach($h['purchases'] as $c)
            <tr class="{{ in_array($c['state'], ['09', '11', '13']) ? 'void' : '' }}">
                <td>{{ $c['label'] }}</td>
                <td>{{ $c['date'] }}</td>
                <td>{{ $c['date_of_due'] ?: '—' }}</td>
                <td>{{ $c['state_label'] }}</td>
                <td class="r">{{ $money($c['total']) }}</td>
                <td class="r">{{ $money($c['paid']) }}</td>
                <td class="r red">{{ $money($c['pending']) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>Registro y cambios de datos</h2>
<table class="grid">
    <tr><th style="width: 18%;">Fecha</th><th style="width: 22%;">Acción</th><th>Detalle</th></tr>
    @foreach($h['activity'] as $a)
        <tr>
            <td>{{ $a['date'] }}</td>
            <td>{{ ['create' => 'Registro del cliente', 'update' => 'Cambio de datos', 'delete' => 'Eliminado'][$a['action']] ?? $a['action'] }}</td>
            <td>
                @if($a['user'])
                    Por {{ $a['user'] }}.
                @endif
                @foreach($a['changes'] as $ch)
                    <br>{{ $fieldLabels[$ch['field']] ?? $ch['field'] }}: {{ $ch['old'] === null || $ch['old'] === '' ? 'vacío' : (is_scalar($ch['old']) ? $ch['old'] : json_encode($ch['old'])) }} → {{ $ch['new'] === null || $ch['new'] === '' ? 'vacío' : (is_scalar($ch['new']) ? $ch['new'] : json_encode($ch['new'])) }}
                @endforeach
            </td>
        </tr>
    @endforeach
</table>
<p class="muted" style="font-size: 7.5pt;">
    Quién registró al cliente y los cambios en sus datos se guardan desde el 16/11/2026.
    De los clientes anteriores solo se conoce la fecha de registro.
</p>

</body>
</html>
