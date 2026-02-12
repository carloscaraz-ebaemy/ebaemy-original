<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Envio de nuevo reclamo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #0044cc;
        }
        p {
            font-size: 16px;
            color: #555;
        }
        .company-name {
            font-weight: bold;
            color: #0044cc;
        }
        ul {
            padding-left: 20px;
            margin: 20px 0;
        }
        li {
            margin-bottom: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th, .data-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .data-table th {
            background-color: #f2f2f2;
            color: #333;
        }
        .footer {
            font-size: 14px;
            color: #777;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Notificación de nuevo reclamo</h1>

        <p>Estimad@ <span class="company-name">{{ $company->name }}</span>,</p>
        <p>Le informamos que ha recibido una nueva notificación en el libro de reclamaciones. A continuación, se detallan los datos del reclamo:</p>

        <table class="data-table">
            <tr>
                <th>Nombre del Consumidor</th>
                <td>{{ $datos_formulario['nombres'] }} {{ $datos_formulario['apellidos'] }}</td>
            </tr>
            <tr>
                <th>Correo Electrónico</th>
                <td>{{ $datos_formulario['correo'] }}</td>
            </tr>
            <tr>
                <th>Fecha del Reclamo</th>
                <td>{{ $datos_formulario['fecha_reclamo'] }}</td>
            </tr>
            <tr>
                <th>Tipo de Reclamo</th>
                <td>{{ $datos_formulario['tipo_reclamo'] }}</td>
            </tr>
            <tr>
                <th>Moneda</th>
                <td>{{ $datos_formulario['moneda'] }}</td>
            </tr>
            <tr>
                <th>Monto Reclamado</th>
                <td>{{ $datos_formulario['moneda'] }}. {{ $datos_formulario['monto'] }}</td>
            </tr>
            <tr>
                <th>¿Bien o Servicio?</th>
                <td>{{ $datos_formulario['tipo_bien'] }}</td>
            </tr>
            <tr>
                <th>Descripción del Bien o Servicio</th>
                <td>{{ $datos_formulario['descripcion'] }}</td>
            </tr>
            <tr>
                <th>Detalles del reclamo o queja</th>
                <td>{{ $datos_formulario['detalle_reclamo'] }}</td>
            </tr>
            <tr>
                <th>Pedido del Consumidor</th>
                <td>{{ $datos_formulario['pedido_consumidor'] }}</td>
            </tr>
            {{-- <tr>
                <th>Archivos Adjuntos</th>
                <td>
                    @foreach($datos_formulario['archivos'] as $archivo)
                        <a href="{{ asset('storage/'.$archivo) }}" target="_blank">{{ basename($archivo) }}</a><br>
                    @endforeach
                </td>
            </tr> --}}
        </table>

        <div class="footer">
            <p>Gracias por su atención. Si tiene alguna duda, no dude en ponerse en contacto con nosotros.</p>
        </div>
    </div>
</body>
</html>