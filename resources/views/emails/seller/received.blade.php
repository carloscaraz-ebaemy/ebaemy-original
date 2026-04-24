@component('emails.seller._layout')
    <h1 style="margin:0 0 16px;font-size:24px;font-weight:800;letter-spacing:-0.02em;color:#0f172a;">
        ¡Recibimos tu solicitud!
    </h1>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Hola <strong>{{ $application->legal_representative_name }}</strong>,
    </p>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Gracias por querer vender en <strong>{{ config('app.name', 'ebaemy') }}</strong>. Tu solicitud fue registrada correctamente y está en revisión.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6fbfc;border:1px solid #e2e8f0;border-radius:12px;margin:20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:700;color:#0a6f68;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">Resumen de tu solicitud</div>
                <table role="presentation" width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;color:#0f172a;">
                    <tr><td style="width:140px;color:#64748b;">RUC:</td><td><strong>{{ $application->ruc }}</strong></td></tr>
                    <tr><td style="color:#64748b;">Razón social:</td><td>{{ $application->business_name }}</td></tr>
                    <tr><td style="color:#64748b;">Subdominio:</td><td><strong>{{ $application->requested_subdomain }}.{{ config('tenant.app_url_base') }}</strong></td></tr>
                    <tr><td style="color:#64748b;">Responsable:</td><td>{{ $application->legal_representative_name }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Nuestro equipo revisará tus datos y te notificaremos por correo (y WhatsApp si es posible) cuando tu tienda sea aprobada. Normalmente toma entre 24 y 48 horas hábiles.
    </p>

    @if($application->tracking_token)
        <p style="margin:0 0 16px;font-size:15px;color:#475569;">
            Puedes consultar el estado de tu solicitud en cualquier momento en el siguiente link:
        </p>
        <p style="margin:24px 0;">
            <a href="{{ url('/seller/application/' . $application->tracking_token) }}" style="display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#1fb1a6,#0f8a82);color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px;">Ver estado de mi solicitud</a>
        </p>
    @endif
@endcomponent
