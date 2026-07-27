{{-- ══════════════════════════════════════════════════════════════════════
     MANIFIESTO / CARGO DE DESPACHO
     Última hoja del lote de rótulos. Lista todo lo que se imprimió en la
     tanda: código, cliente, destino, producto y una celda en blanco para
     anotar a mano (peso, bulto N°, incidencia). El almacén lo firma y lo
     archiva como comprobante de qué salió y con quién.
     Espera: $items (los mismos del lote), $company, $printedAt (Carbon).
     ══════════════════════════════════════════════════════════════════════ --}}
<style>
    .manifest { width: 100%; background: #fff; page-break-before: always; break-before: page; color: #000; }
    .mf-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
    .mf-head .t { font-size: 18px; font-weight: bold; letter-spacing: .5px; }
    .mf-head .sub { font-size: 11px; color: #333; margin-top: 2px; }
    .mf-head .meta { text-align: right; font-size: 11px; line-height: 1.6; }
    .mf-head .meta b { font-size: 13px; }

    table.mf { width: 100%; border-collapse: collapse; font-size: 11px; }
    table.mf th, table.mf td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }
    table.mf thead th { background: #eee; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; text-align: left; }
    table.mf tbody tr { page-break-inside: avoid; break-inside: avoid; }
    table.mf .c-n     { width: 5%;  text-align: center; font-weight: bold; }
    table.mf .c-code  { width: 15%; font-weight: bold; white-space: nowrap; }
    table.mf .c-cli   { width: 22%; }
    table.mf .c-dest  { width: 20%; }
    table.mf .c-prod  { width: 20%; }
    table.mf .c-obs   { width: 18%; background: #fff; }
    table.mf td.c-obs { height: 34px; } /* celda alta para escribir a mano */
    table.mf .muted { color: #666; }
    table.mf .tag { display: inline-block; font-size: 9px; font-weight: bold; border: 1px solid #000; border-radius: 3px; padding: 0 4px; margin-right: 3px; }

    .mf-foot { display: flex; justify-content: space-between; gap: 24px; margin-top: 22px; }
    .mf-foot .sign { flex: 1; text-align: center; font-size: 11px; }
    .mf-foot .sign .line { border-top: 1px solid #000; margin-top: 34px; padding-top: 4px; }
    .mf-total { margin-top: 10px; font-size: 12px; font-weight: bold; text-align: right; }

    @media print { .manifest { padding: 0; } }
</style>

<div class="manifest">
    <div class="mf-head">
        <div>
            <div class="t">MANIFIESTO DE DESPACHO</div>
            <div class="sub">{{ $company->trade_name ?? $company->name ?? 'Registro y Control de Envíos' }}</div>
        </div>
        <div class="meta">
            @php $bt = $batch ?? null; @endphp
            @if($bt)
                @if($bt->manifest_code)Manifiesto <b>{{ $bt->manifest_code }}</b><br>@endif
                Lote <b>{{ $bt->code }}</b><br>
            @endif
            Fecha de impresión<br>
            <b>{{ $printedAt->format('d/m/Y') }}</b> · {{ $printedAt->format('h:i a') }}<br>
            @if($bt && $bt->printed_by_name)
                Responsable: <b>{{ $bt->printed_by_name }}</b><br>
            @endif
            Paquetes en esta tanda: <b>{{ count($items) }}</b>
            @php
                // Modalidad del manifiesto: la común a todos, o "Mixto".
                $mods = collect($items)->map(fn ($it) => $it['shipment']->delivery_short)->unique()->values();
            @endphp
            <br>Modalidad: <b>{{ $mods->count() === 1 ? $mods->first() : 'Mixto' }}</b>
            @if($bt && $bt->label_range)
                <br><span style="font-size:10px">Etiquetas: {{ $bt->label_range }}</span>
            @endif
        </div>
    </div>

    <table class="mf">
        <thead>
            <tr>
                <th class="c-n">N°</th>
                <th class="c-code">Código</th>
                <th class="c-cli">Cliente</th>
                <th class="c-dest">Destino</th>
                <th class="c-prod">Producto</th>
                <th class="c-obs">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $it)
                @php
                    $s = $it['shipment'];
                    $ub = $it['ubigeo'] ?? null;
                    // Destino legible según el tipo de entrega.
                    if ($s->is_domicilio) {
                        $dest = 'Domicilio · ' . ($s->destination_city ?: 'Lima');
                    } else {
                        $ciudad = $ub['district'] ?? $s->destination_city ?: '—';
                        $dest = trim(($s->shipping_agency ? strtoupper($s->shipping_agency) . ' · ' : '') . $ciudad);
                    }
                @endphp
                <tr>
                    <td class="c-n">{{ $i + 1 }}</td>
                    <td class="c-code">{{ $s->shipment_code ?: ('#' . $s->id) }}</td>
                    <td class="c-cli">
                        {{ $s->full_name }}
                        @if($s->phone)<br><span class="muted">{{ $s->phone }}</span>@endif
                        @if($s->dni)<br><span class="muted">{{ $s->document_label }}: {{ $s->dni }}</span>@endif
                    </td>
                    <td class="c-dest">
                        <span class="tag">{{ $s->is_domicilio ? 'DOM' : 'AG' }}</span>{{ $dest }}
                        @if(!$s->is_domicilio && $s->reference)<br><span class="muted">Ofic: {{ $s->reference }}</span>@endif
                    </td>
                    <td class="c-prod">{{ $s->package_content ?: '—' }}</td>
                    <td class="c-obs"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mf-total">Total de paquetes: {{ count($items) }}</div>

    <div class="mf-foot">
        <div class="sign"><div class="line">Despachado por (nombre y firma)</div></div>
        <div class="sign"><div class="line">Recibido por / Transportista</div></div>
    </div>
</div>
