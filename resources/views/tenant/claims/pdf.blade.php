<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de Reclamación {{ $claim->code }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10.5pt; color: #111; }
        h1 { font-size: 15pt; margin: 0 0 2mm; text-align: center; text-transform: uppercase; letter-spacing: .5pt; }
        .sub { text-align: center; font-size: 9pt; color: #555; margin: 0 0 6mm; }
        .box { border: .4mm solid #222; padding: 3mm; margin-bottom: 4mm; }
        .box h2 { font-size: 10pt; margin: 0 0 2.5mm; text-transform: uppercase; letter-spacing: .3pt; background: #f0f0f0; padding: 1.5mm 2mm; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1.2mm 0; vertical-align: top; font-size: 10pt; }
        td.k { color: #555; width: 32%; }
        .code { text-align: right; font-size: 12pt; font-weight: bold; }
        .free { white-space: pre-line; line-height: 1.45; }
        .legend { font-size: 8.5pt; color: #444; line-height: 1.5; border-top: .3mm solid #999; padding-top: 2.5mm; }
    </style>
</head>
<body>

    <div class="code">N° {{ $claim->code }}</div>
    <h1>Hoja de Reclamación</h1>
    <p class="sub">Libro de Reclamaciones — Código de Protección y Defensa del Consumidor (Ley N° 29571)</p>

    <div class="box">
        <h2>1. Identificación del proveedor</h2>
        <table>
            <tr><td class="k">Razón social</td><td>{{ $provider['name'] }}</td></tr>
            @if($provider['trade_name'] && $provider['trade_name'] !== $provider['name'])
                <tr><td class="k">Nombre comercial</td><td>{{ $provider['trade_name'] }}</td></tr>
            @endif
            <tr><td class="k">RUC</td><td>{{ $provider['ruc'] }}</td></tr>
            <tr><td class="k">Dirección</td><td>{{ $provider['address'] ?: '—' }}</td></tr>
            <tr><td class="k">Medio virtual</td><td>{{ $provider['domain'] }}</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>2. Identificación del consumidor reclamante</h2>
        <table>
            <tr><td class="k">Fecha del reclamo</td><td>{{ $claim->created_at->format('d/m/Y H:i') }}</td></tr>
            <tr><td class="k">Nombres y apellidos</td><td>{{ $claim->fullName() }}</td></tr>
            <tr><td class="k">Documento</td><td>{{ $claim->document_type }} {{ $claim->document_number }}</td></tr>
            <tr><td class="k">Domicilio</td><td>{{ $claim->address ?: '—' }}</td></tr>
            <tr><td class="k">Teléfono</td><td>{{ $claim->phone ?: '—' }}</td></tr>
            <tr><td class="k">Correo electrónico</td><td>{{ $claim->email }}</td></tr>
            @if($claim->is_minor)
                <tr><td class="k">Padre o apoderado</td><td>{{ $claim->guardian_name ?: '—' }}</td></tr>
            @endif
        </table>
    </div>

    <div class="box">
        <h2>3. Identificación del bien contratado</h2>
        <table>
            <tr><td class="k">Tipo</td><td>{{ ucfirst($claim->item_type) }}</td></tr>
            <tr><td class="k">Monto reclamado</td><td>{{ $claim->amount ? ($claim->currency === 'USD' ? '$' : 'S/') . ' ' . number_format($claim->amount, 2) : '—' }}</td></tr>
            @if($claim->order_reference)
                <tr><td class="k">N° de pedido</td><td>{{ $claim->order_reference }}</td></tr>
            @endif
            @if($claim->purchase_date)
                <tr><td class="k">Fecha de compra</td><td>{{ $claim->purchase_date->format('d/m/Y') }}</td></tr>
            @endif
            <tr><td class="k">Descripción</td><td class="free">{{ $claim->product_description }}</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>4. Detalle de la reclamación y pedido del consumidor</h2>
        <table>
            <tr><td class="k">Tipo</td><td><strong>{{ strtoupper($claim->typeLabel()) }}</strong></td></tr>
            <tr><td class="k">Detalle</td><td class="free">{{ $claim->detail }}</td></tr>
            <tr><td class="k">Pedido concreto</td><td class="free">{{ $claim->consumer_request }}</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>5. Acciones adoptadas por el proveedor</h2>
        <table>
            <tr><td class="k">Estado</td><td>{{ $claim->statusLabel() }}</td></tr>
            <tr><td class="k">Fecha límite de respuesta</td><td>{{ $claim->due_date->format('d/m/Y') }} (15 días hábiles)</td></tr>
            @if($claim->answered_at)
                <tr><td class="k">Fecha de respuesta</td><td>{{ $claim->answered_at->format('d/m/Y H:i') }}</td></tr>
                <tr><td class="k">Respuesta</td><td class="free">{{ $claim->answer }}</td></tr>
                @if($claim->actions_taken)
                    <tr><td class="k">Acciones adoptadas</td><td class="free">{{ $claim->actions_taken }}</td></tr>
                @endif
                @if($claim->request_accepted === false && $claim->rejection_grounds)
                    <tr><td class="k">Fundamento del rechazo</td><td class="free">{{ $claim->rejection_grounds }}</td></tr>
                @endif
            @else
                <tr><td class="k">Respuesta</td><td>Pendiente</td></tr>
            @endif
        </table>
    </div>

    <p class="legend">
        <strong>Reclamo:</strong> disconformidad relacionada a los productos o servicios.
        <strong>Queja:</strong> disconformidad no relacionada a los productos o servicios; o malestar o descontento respecto a la atención al público.<br>
        La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI.<br>
        El proveedor debe dar respuesta al reclamo o queja en un plazo no mayor a quince (15) días hábiles improrrogables.
    </p>

</body>
</html>
