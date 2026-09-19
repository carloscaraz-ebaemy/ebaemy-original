@php
    $accent = '#1f5eff';
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
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">

                <tr>
                    <td style="background:{{ $accent }};padding:22px 24px;color:#ffffff;">
                        <div style="font-size:13px;letter-spacing:.06em;text-transform:uppercase;opacity:.85;">Libro de Reclamaciones</div>
                        <div style="font-size:22px;font-weight:700;margin-top:4px;">{{ $claim->code }}</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 14px;font-size:16px;">Hola {{ $claim->names }},</p>

                        <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                            Recibimos tu <strong>{{ strtolower($claim->typeLabel()) }}</strong> y quedó registrado en el
                            Libro de Reclamaciones de <strong>{{ $provider['trade_name'] }}</strong>.
                            Adjuntamos la copia de tu hoja de reclamación en PDF.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fa;border-radius:8px;padding:16px;margin:0 0 20px;">
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;width:44%;">Código de registro</td><td style="font-size:14px;padding:4px 0;font-weight:700;">{{ $claim->code }}</td></tr>
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Fecha</td><td style="font-size:14px;padding:4px 0;">{{ $claim->created_at->format('d/m/Y H:i') }}</td></tr>
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Tipo</td><td style="font-size:14px;padding:4px 0;">{{ $claim->typeLabel() }}</td></tr>
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">{{ ucfirst($claim->item_type) }} contratado</td><td style="font-size:14px;padding:4px 0;">{{ $claim->product_description }}</td></tr>
                            @if($claim->order_reference)
                                <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Pedido</td><td style="font-size:14px;padding:4px 0;">{{ $claim->order_reference }}</td></tr>
                            @endif
                            @if($claim->amount)
                                <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Monto reclamado</td><td style="font-size:14px;padding:4px 0;">{{ $claim->currency === 'USD' ? '$' : 'S/' }} {{ number_format($claim->amount, 2) }}</td></tr>
                            @endif
                        </table>

                        <h3 style="margin:0 0 6px;font-size:15px;">Detalle de tu {{ strtolower($claim->typeLabel()) }}</h3>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;white-space:pre-line;color:#39414f;">{{ $claim->detail }}</p>

                        <h3 style="margin:0 0 6px;font-size:15px;">Tu pedido concreto</h3>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;white-space:pre-line;color:#39414f;">{{ $claim->consumer_request }}</p>

                        <p style="margin:0 0 6px;font-size:14px;line-height:1.6;">
                            Te responderemos en un plazo máximo de <strong>15 días hábiles</strong>, contados desde hoy.
                        </p>
                        <p style="margin:0 0 20px;font-size:13px;color:#5b6472;line-height:1.6;">
                            Puedes consultar el estado de tu registro con tu código y tu correo electrónico en
                            <a href="{{ $provider['domain'] }}/ecommerce/libro-reclamaciones/consulta" style="color:{{ $accent }};">{{ $provider['domain'] }}/ecommerce/libro-reclamaciones/consulta</a>.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e8ec;padding-top:16px;">
                            <tr><td style="font-size:13px;color:#5b6472;line-height:1.6;">
                                <strong style="color:#1f2430;">{{ $provider['name'] }}</strong><br>
                                RUC {{ $provider['ruc'] }}<br>
                                @if($provider['address']){{ $provider['address'] }}<br>@endif
                                @if($provider['phone']){{ $provider['phone'] }} · @endif{{ $provider['email'] }}
                            </td></tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f7f8fa;padding:14px 24px;font-size:12px;color:#7a8393;line-height:1.5;">
                        Este correo es la constancia de tu registro. Consérvalo.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
