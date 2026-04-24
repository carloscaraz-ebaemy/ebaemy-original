@component('emails.seller._layout')
    <h1 style="margin:0 0 16px;font-size:22px;font-weight:800;letter-spacing:-0.02em;color:#0f172a;">
        Necesitamos información adicional
    </h1>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Hola <strong>{{ $application->legal_representative_name }}</strong>,
    </p>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Estamos revisando tu solicitud para vender en <strong>{{ config('app.name', 'ebaemy') }}</strong>. Para continuar necesitamos que nos envíes los siguientes documentos o información:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef3c7;border:1px solid #fde68a;border-radius:12px;margin:20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">Qué necesitamos</div>
                <div style="font-size:14px;color:#0f172a;line-height:1.7;white-space:pre-line;">{{ $documentsRequested }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Puedes enviarnos la información respondiendo a este correo o accediendo al portal de seguimiento:
    </p>

    <p style="margin:24px 0;text-align:center;">
        <a href="{{ $trackingUrl }}" style="display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#1fb1a6,#0f8a82);color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px;">Ver estado de mi solicitud</a>
    </p>

    <p style="margin:0;font-size:13px;color:#64748b;">
        Una vez recibida la información, retomaremos la revisión de tu solicitud.
    </p>
@endcomponent
