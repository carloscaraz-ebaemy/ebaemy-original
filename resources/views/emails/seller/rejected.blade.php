@component('emails.seller._layout')
    <h1 style="margin:0 0 16px;font-size:22px;font-weight:800;letter-spacing:-0.02em;color:#0f172a;">
        Respuesta a tu solicitud
    </h1>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Hola <strong>{{ $application->legal_representative_name }}</strong>,
    </p>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Lamentablemente no pudimos aprobar tu solicitud de vendedor en <strong>{{ config('app.name', 'ebaemy') }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;margin:20px 0;">
        <tr>
            <td style="padding:18px 20px;">
                <div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;">Motivo</div>
                <div style="font-size:14px;color:#0f172a;line-height:1.6;">
                    {{ $reason }}
                </div>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:15px;color:#475569;">
        Si consideras que se trata de un error o tienes información adicional para complementar tu solicitud, puedes respondernos a este mismo correo.
    </p>
    <p style="margin:0 0 16px;font-size:14px;color:#64748b;">
        También puedes enviar una nueva solicitud una vez que hayas resuelto los puntos observados.
    </p>
@endcomponent
