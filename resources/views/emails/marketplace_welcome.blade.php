<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bienvenido a ebaemy</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;padding:32px 16px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.08)">
                <tr><td style="padding:32px 32px 8px">
                    <div style="display:inline-block;width:44px;height:44px;background:linear-gradient(135deg,#0f8a82,#0a6f68);border-radius:12px;color:#fff;font-size:22px;font-weight:800;text-align:center;line-height:44px">e</div>
                    <span style="font-size:20px;font-weight:700;margin-left:10px;vertical-align:6px">ebaemy</span>
                </td></tr>
                <tr><td style="padding:16px 32px 8px">
                    <h1 style="margin:0;font-size:24px;font-weight:700;color:#0f172a">¡Bienvenido, {{ $user->name }}! 🎉</h1>
                    <p style="margin:14px 0 0;font-size:15.5px;line-height:1.55;color:#475569">
                        Tu cuenta en <strong>ebaemy</strong> esta lista. Una sola cuenta para comprar en cualquier tienda de la red.
                    </p>
                </td></tr>
                <tr><td style="padding:24px 32px 8px">
                    <h2 style="margin:0 0 10px;font-size:15px;font-weight:700;color:#0f172a">Que puedes hacer ahora</h2>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        <tr><td style="padding:8px 0;color:#475569;font-size:14px;line-height:1.5">🔍 <strong>Explorar productos</strong> de tiendas verificadas en Peru.</td></tr>
                        <tr><td style="padding:8px 0;color:#475569;font-size:14px;line-height:1.5">💾 <strong>Guardar favoritos</strong> — te avisamos si bajan de precio.</td></tr>
                        <tr><td style="padding:8px 0;color:#475569;font-size:14px;line-height:1.5">🛒 <strong>Comprar de varias tiendas</strong> en un solo checkout.</td></tr>
                        @if(!empty($showOffersCta))
                            <tr><td style="padding:8px 0;color:#475569;font-size:14px;line-height:1.5">🎟️ <strong>Recibir ofertas y cupones</strong> en tu email (suscrito).</td></tr>
                        @endif
                    </table>
                </td></tr>
                <tr><td style="padding:24px 32px">
                    <p style="margin:0">
                        <a href="{{ url('/marketplace') }}" style="display:inline-block;padding:14px 24px;background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px">Explorar productos</a>
                    </p>
                </td></tr>
                <tr><td style="padding:0 32px 32px;border-top:1px solid #e5e7eb;margin-top:18px">
                    <p style="margin:18px 0 0;font-size:12px;color:#94a3b8;line-height:1.5">
                        Recibes este correo porque te registraste en ebaemy con <strong>{{ $user->email }}</strong>.
                        ¿No fuiste tu? Escribenos a <a href="mailto:soporte@ebaemy.com" style="color:#0c6b65">soporte@ebaemy.com</a>.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
