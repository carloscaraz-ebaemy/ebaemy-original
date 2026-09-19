@php
    $accent = $claim->type === 'queja' ? '#b45309' : '#b91c1c';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $claim->code }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2430;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:10px;overflow:hidden;">

                <tr>
                    <td style="background:{{ $accent }};padding:20px 24px;color:#ffffff;">
                        <div style="font-size:13px;letter-spacing:.06em;text-transform:uppercase;opacity:.9;">Nuevo {{ $claim->typeLabel() }} · Libro de Reclamaciones</div>
                        <div style="font-size:22px;font-weight:700;margin-top:4px;">{{ $claim->code }}</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 24px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                            Plazo legal de respuesta: <strong>hasta el {{ $claim->due_date->format('d/m/Y') }}</strong>
                            (15 días hábiles improrrogables).
                        </p>

                        <h3 style="margin:18px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:#5b6472;">Consumidor</h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                            <tr><td style="padding:3px 0;color:#5b6472;width:38%;">Nombre</td><td style="padding:3px 0;">{{ $claim->fullName() }}</td></tr>
                            <tr><td style="padding:3px 0;color:#5b6472;">Documento</td><td style="padding:3px 0;">{{ $claim->document_type }} {{ $claim->document_number }}</td></tr>
                            <tr><td style="padding:3px 0;color:#5b6472;">Correo</td><td style="padding:3px 0;">{{ $claim->email }}</td></tr>
                            <tr><td style="padding:3px 0;color:#5b6472;">Teléfono</td><td style="padding:3px 0;">{{ $claim->phone ?: '—' }}</td></tr>
                            <tr><td style="padding:3px 0;color:#5b6472;">Domicilio</td><td style="padding:3px 0;">{{ $claim->address ?: '—' }}</td></tr>
                            @if($claim->is_minor)
                                <tr><td style="padding:3px 0;color:#5b6472;">Menor de edad</td><td style="padding:3px 0;">Sí — apoderado: {{ $claim->guardian_name ?: '—' }}</td></tr>
                            @endif
                        </table>

                        <h3 style="margin:18px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:#5b6472;">{{ ucfirst($claim->item_type) }} contratado</h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                            <tr><td style="padding:3px 0;color:#5b6472;width:38%;">Descripción</td><td style="padding:3px 0;">{{ $claim->product_description }}</td></tr>
                            @if($claim->order_reference)
                                <tr><td style="padding:3px 0;color:#5b6472;">Pedido</td><td style="padding:3px 0;">{{ $claim->order_reference }}</td></tr>
                            @endif
                            @if($claim->purchase_date)
                                <tr><td style="padding:3px 0;color:#5b6472;">Fecha de compra</td><td style="padding:3px 0;">{{ $claim->purchase_date->format('d/m/Y') }}</td></tr>
                            @endif
                            @if($claim->amount)
                                <tr><td style="padding:3px 0;color:#5b6472;">Monto</td><td style="padding:3px 0;">{{ $claim->currency === 'USD' ? '$' : 'S/' }} {{ number_format($claim->amount, 2) }}</td></tr>
                            @endif
                        </table>

                        <h3 style="margin:18px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:#5b6472;">Detalle</h3>
                        <p style="margin:0;font-size:14px;line-height:1.6;white-space:pre-line;">{{ $claim->detail }}</p>

                        <h3 style="margin:18px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:#5b6472;">Pedido del consumidor</h3>
                        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;white-space:pre-line;">{{ $claim->consumer_request }}</p>

                        <a href="{{ $provider['domain'] }}/ecommerce/claims"
                           style="display:inline-block;background:#1f5eff;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-size:15px;font-weight:600;">
                            Abrir en el panel
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f7f8fa;padding:14px 24px;font-size:12px;color:#7a8393;line-height:1.5;">
                        {{ $provider['name'] }} · RUC {{ $provider['ruc'] }} — registro generado en {{ $provider['domain'] }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
