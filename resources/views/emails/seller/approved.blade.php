@component('emails.seller._layout')
    <h1 style="margin:0 0 16px;font-size:24px;font-weight:800;letter-spacing:-0.02em;color:#0a6f68;">
        🎉 ¡Tu tienda fue aprobada!
    </h1>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Hola <strong>{{ $application->legal_representative_name }}</strong>,
    </p>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Tu solicitud para vender en <strong>{{ config('app.name', 'ebaemy') }}</strong> fue aprobada. Tu tienda ya está activa y lista para configurarse.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;margin:20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">Acceso a tu tienda</div>
                <table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;color:#0f172a;">
                    <tr><td style="width:140px;color:#64748b;">URL de tu tienda:</td><td><strong>{{ $tenantUrl }}</strong></td></tr>
                    <tr><td style="color:#64748b;">Usuario (email):</td><td><strong>{{ $application->email }}</strong></td></tr>
                    <tr><td style="color:#64748b;">Contraseña:</td><td>La que elegiste al registrarte.</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:13.5px;color:#64748b;">
        Si olvidaste tu contraseña, puedes restablecerla desde la pantalla de login con la opción
        <em>"¿Olvidaste tu contraseña?"</em>.
    </p>

    <p style="margin:24px 0;text-align:center;">
        <a href="{{ $tenantUrl }}/login" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#1fb1a6,#0f8a82);color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">Ingresar a mi tienda</a>
    </p>

    <h3 style="margin:28px 0 12px;font-size:16px;font-weight:700;color:#0f172a;">Próximos pasos</h3>
    <ol style="margin:0 0 16px;padding-left:20px;font-size:14px;color:#475569;line-height:1.8;">
        <li>Inicia sesión y cambia tu contraseña.</li>
        <li>Configura tu certificado digital para facturación electrónica.</li>
        <li>Carga tus productos y define stock por almacén.</li>
        <li>Publica tus productos en el marketplace central.</li>
    </ol>
@endcomponent
