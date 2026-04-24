<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f6fbfc;font-family:'Helvetica Neue',Arial,sans-serif;color:#0f172a;line-height:1.55;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f6fbfc;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0a6f68 0%,#0f8a82 55%,#1fb1a6 100%);padding:28px 32px;color:#ffffff;">
                            <div style="font-size:20px;font-weight:800;letter-spacing:-0.02em;">
                                {{ config('app.name', 'ebaemy') }}
                                <span style="display:inline-block;margin-left:6px;padding:2px 10px;background:rgba(255,255,255,0.2);border-radius:6px;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;vertical-align:3px;">Sellers</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            {!! $slot !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#fafbfc;border-top:1px solid #e2e8f0;color:#475569;font-size:12px;text-align:center;">
                            © {{ date('Y') }} {{ config('app.name', 'ebaemy') }} — Este es un correo automático. Si tienes dudas responde a este mismo mensaje.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
