
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Orden de pago {{ $order->order}}</title>
    <style>
        body {
            color: #000;
        }
        ul {
            list-style: none;
        }
    </style>
</head>
<body>
<p>Estimad@: 
    {{ $order->client->name }}
@if ($order->order_state_id == 1)
    <br>Le informamos que su orden de pago Nro. {{ $order->order }} se encuentra pendiente de pago.
@endif
</p>
<p>
    El total de su orden de pago es S/. {{ $order->amount}}
</p>
<ul>
    <li>Estado de la orden: {{ $order->order_state->name }}</li>
{{--    <li>Tipo de comprobante: {{ $document->document_type->description }}</li>--}}
    <li>Fecha de vencimiento: {{ $order->date_of_due->format('d/m/Y') }}</li>
</ul>
</body>
</html>