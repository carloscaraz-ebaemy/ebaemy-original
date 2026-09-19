@php
    $accent = '#0f8a5f';
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
                        <div style="font-size:13px;letter-spacing:.06em;text-transform:uppercase;opacity:.9;">Respuesta del proveedor</div>
                        <div style="font-size:22px;font-weight:700;margin-top:4px;">{{ $claim->code }}</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 14px;font-size:16px;">Hola {{ $claim->names }},</p>

                        <p style="margin:0 0 18px;font-size:15px;line-height:1.55;">
                            Esta es nuestra respuesta al {{ strtolower($claim->typeLabel()) }} que presentaste el
                            <strong>{{ $claim->created_at->format('d/m/Y') }}</strong>.
                        </p>

                        <h3 style="margin:0 0 6px;font-size:15px;">Respuesta</h3>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;white-space:pre-line;color:#39414f;">{{ $claim->answer }}</p>

                        @if($claim->actions_taken)
                            <h3 style="margin:0 0 6px;font-size:15px;">Acciones adoptadas</h3>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;white-space:pre-line;color:#39414f;">{{ $claim->actions_taken }}</p>
                        @endif

                        @if($claim->request_accepted === false && $claim->rejection_grounds)
                            <h3 style="margin:0 0 6px;font-size:15px;">Fundamento</h3>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;white-space:pre-line;color:#39414f;">{{ $claim->rejection_grounds }}</p>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8fa;border-radius:8px;padding:16px;margin:0 0 20px;">
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;width:44%;">Código</td><td style="font-size:14px;padding:4px 0;font-weight:700;">{{ $claim->code }}</td></tr>
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Fecha de presentación</td><td style="font-size:14px;padding:4px 0;">{{ $claim->created_at->format('d/m/Y') }}</td></tr>
                            <tr><td style="font-size:14px;padding:4px 0;color:#5b6472;">Fecha de respuesta</td><td style="font-size:14px;padding:4px 0;">{{ optional($claim->answered_at)->format('d/m/Y') }}</td></tr>
                        </table>

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
            </table>
        </td>
    </tr>
</table>
</body>
</html>
