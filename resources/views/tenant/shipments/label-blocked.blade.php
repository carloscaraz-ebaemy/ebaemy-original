<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ count($blocked) === 1 ? 'Este rótulo no se puede imprimir' : 'Estos rótulos no se pueden imprimir' }}</title>
    @include('tenant.shipments.partials.print-bar-styles')
    <style>
        body { margin: 0; background: oklch(0.925 0.006 250); }
        .bl { max-width: 560px; margin: 48px auto; padding: 0 16px; }
        .bl-card { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 22px 24px; }
        .bl-card h1 { font-size: 17px; font-weight: 650; margin: 0 0 4px; }
        .bl-card > p { margin: 0; color: var(--ink-2); font-size: 13.5px; }
        .bl-list { margin: 16px 0 0; padding: 0; list-style: none; border: 1px solid var(--line); border-radius: 10px; }
        .bl-list li { padding: 10px 14px; border-top: 1px solid var(--line); font-size: 13.5px; }
        .bl-list li:first-child { border-top: 0; }
        .bl-list b { display: block; font-variant-numeric: tabular-nums; }
        .bl-list span { color: var(--warn); }
        .bl-card > p.bl-tip { margin: 14px 0 0; font-size: 12.5px; color: var(--muted); line-height: 1.5; }
        .bl-acts { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
    </style>
</head>
<body>
@include('tenant.shipments.partials.print-embed-js')
@php $uno = count($blocked) === 1; @endphp
<div class="pb bl">
    <div class="bl-card">
        <h1>{{ $uno ? 'Este rótulo no se puede imprimir todavía' : 'Ninguno de estos ' . count($blocked) . ' envíos se puede rotular' }}</h1>
        <p>{{ $uno ? 'Resuelve esto y vuelve a imprimir:' : 'Este es el motivo de cada uno:' }}</p>

        <ul class="bl-list">
            @foreach($blocked as $s)
                <li>
                    <b>{{ $s->shipment_code ?: ('#' . $s->id) }} · {{ $s->full_name }}</b>
                    <span>{{ ($reasons ?? [])[$s->id] ?? 'No elegible' }}</span>
                </li>
            @endforeach
        </ul>

        <p class="bl-tip">
            Pago sin confirmar: confírmalo desde «Pagos del pedido» o desactiva «Requerir pago» en la configuración de la tienda.
            Los anulados y los que ya salieron se reimprimen desde el pedido, indicando el motivo.
            El recojo en tienda no lleva rótulo: se imprime su comprobante.
        </p>

        <div class="bl-acts">
            <button type="button" class="pb-btn pb-btn--primary" onclick="ebImpresion.cerrar()">Entendido</button>
        </div>
    </div>
</div>
</body>
</html>
