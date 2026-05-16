<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu acceso a ebaemy</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0f172a">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;padding:32px 16px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,.08)">
                    <tr>
                        <td style="padding:32px 32px 8px">
                            <div style="display:inline-block;width:44px;height:44px;background:linear-gradient(135deg,#0f8a82,#0a6f68);border-radius:12px;color:#fff;font-size:22px;font-weight:800;text-align:center;line-height:44px">e</div>
                            <span style="font-size:20px;font-weight:700;margin-left:10px;vertical-align:6px">ebaemy</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 8px">
                            <h1 style="margin:0;font-size:22px;font-weight:700;color:#0f172a">Tu acceso a ebaemy</h1>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.55;color:#475569">
                                Hola, alguien (esperamos que tu) pidio acceder con el email
                                <strong>{{ $email }}</strong>. Tienes dos formas de entrar:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 8px">
                            <p style="margin:0;font-size:13px;font-weight:600;text-transform:uppercase;color:#64748b;letter-spacing:.05em">1. Tu codigo</p>
                            <div style="margin-top:8px;padding:16px 20px;background:#f0fdfa;border:1px solid #5eead4;border-radius:12px;font-size:28px;font-weight:800;letter-spacing:.3em;color:#0c6b65;font-family:'SF Mono',Menlo,Consolas,monospace;text-align:center">{{ $code }}</div>
                            <p style="margin:8px 0 0;font-size:13px;color:#64748b">Ingresa este codigo en la pagina de acceso si abriste el correo desde tu celular.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px">
                            <p style="margin:0;font-size:13px;font-weight:600;text-transform:uppercase;color:#64748b;letter-spacing:.05em">2. O entra con un click</p>
                            <p style="margin:12px 0 0">
                                <a href="{{ $link }}" style="display:inline-block;padding:14px 24px;background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px">Acceder a ebaemy</a>
                            </p>
                            <p style="margin:12px 0 0;font-size:12px;color:#94a3b8;word-break:break-all">{{ $link }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 24px">
                            <p style="margin:0;padding:14px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#78350f">
                                Este acceso expira en <strong>{{ $ttl_min }} minutos</strong>. Si no fuiste tu, puedes ignorar este correo: nadie podra entrar a tu cuenta sin este codigo.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;border-top:1px solid #e5e7eb">
                            <p style="margin:20px 0 0;font-size:12px;color:#94a3b8;line-height:1.5">
                                ebaemy · marketplace y tiendas verificadas en Peru<br>
                                ¿Problemas? Escribenos a <a href="mailto:soporte@ebaemy.com" style="color:#0c6b65">soporte@ebaemy.com</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
