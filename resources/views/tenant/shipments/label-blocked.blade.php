<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No se pudo imprimir el rótulo</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 560px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 26px; box-shadow: 0 12px 40px -18px rgba(15,23,42,.25); }
        .ic { width: 56px; height: 56px; border-radius: 16px; background: #fff7ed; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        h1 { font-size: 20px; margin: 14px 0 6px; }
        p { margin: 0 0 6px; color: #475569; font-size: 14px; line-height: 1.5; }
        ul { margin: 14px 0 0; padding: 0; list-style: none; border-top: 1px solid #f1f5f9; }
        li { display: flex; justify-content: space-between; gap: 12px; padding: 10px 2px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        li b { font-family: ui-monospace, "SF Mono", Menlo, monospace; }
        li span { color: #64748b; }
        .tip { margin-top: 18px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 12px 14px; font-size: 13px; color: #075985; }
        .btns { display: flex; gap: 10px; margin-top: 20px; }
        .btn { flex: 1; text-align: center; padding: 12px; border-radius: 12px; border: none; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-ghost { background: #f1f5f9; color: #334155; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="ic">🚫</div>
        <h1>Ninguno de estos envíos se puede rotular</h1>
        <p>
            Los {{ count($blocked) }} envío(s) que seleccionaste no son elegibles para
            imprimir su rótulo. Abajo está el motivo de cada uno.
        </p>

        <ul>
            @foreach($blocked as $s)
                <li>
                    <b>{{ $s->shipment_code ?: ('#'.$s->id) }}</b>
                    <span>{{ $s->full_name }} — {{ ($reasons ?? [])[$s->id] ?? 'No elegible' }}</span>
                </li>
            @endforeach
        </ul>

        <div class="tip">
            Si el motivo es <b>pago sin confirmar</b>, confírmalo (o selecciónalos y usa
            <b>“Confirmar pago”</b> en la barra de selección) y vuelve a imprimir; también
            puedes desactivar la regla en <b>Configuración de la tienda → Requerir pago</b>.
            Los <b>anulados</b> y los que <b>ya salieron de la tienda</b> se excluyen a
            propósito: para volver a rotular uno, ábrelo y usa <b>Reimprimir</b> indicando
            el motivo. El <b>recojo en tienda</b> no lleva rótulo, se imprime su comprobante.
        </div>

        <div class="btns">
            <a href="javascript:history.back()" class="btn btn-primary">← Volver al panel</a>
            <button onclick="window.close()" class="btn btn-ghost">Cerrar</button>
        </div>
    </div>
</div>
</body>
</html>
