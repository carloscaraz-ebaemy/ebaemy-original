<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu pedido {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,system-ui,'Helvetica Neue',sans-serif;color:#111827">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f9fafb;padding:24px 0">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04)">

                {{-- Header --}}
                <tr><td style="background:linear-gradient(135deg,#f59e0b,#dc2626);color:#fff;padding:28px;text-align:center">
                    <div style="font-size:14px;letter-spacing:.05em;opacity:.9">EBAEMY MARKETPLACE</div>
                    <h1 style="margin:6px 0 0;font-size:22px">
                        @if($isFinal)
                            ⏰ Última oportunidad
                        @else
                            🛍️ ¿Olvidaste algo?
                        @endif
                    </h1>
                    <div style="margin-top:8px;font-size:14px;font-family:ui-monospace,monospace;background:rgba(255,255,255,.15);display:inline-block;padding:4px 12px;border-radius:6px">
                        Pedido {{ $order->order_number }}
                    </div>
                </td></tr>

                {{-- Intro --}}
                <tr><td style="padding:24px 28px 8px">
                    <p style="margin:0 0 14px;font-size:15px;line-height:1.5">
                        Hola <strong>{{ $order->customer_name }}</strong>,
                    </p>
                    <p style="margin:0 0 12px;font-size:14px;color:#4b5563;line-height:1.5">
                        @if($isFinal)
                            Notamos que tu pedido sigue pendiente de pago. <strong>Es la última vez que te recordamos</strong> — después de hoy lo cerraremos automáticamente y el stock volverá a estar disponible para otros compradores.
                        @else
                            Notamos que iniciaste un pedido en ebaemy pero no lo terminaste. Te dejamos el link para completar la compra cuando puedas. <strong>Tus productos siguen reservados</strong>.
                        @endif
                    </p>
                </td></tr>

                {{-- Tabla resumen items --}}
                <tr><td style="padding:8px 28px">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                           style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <tr style="background:#f9fafb">
                            <td style="padding:10px 14px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">
                                Tus productos ({{ $items->count() }})
                            </td>
                        </tr>
                        @foreach($items as $it)
                            <tr style="border-top:1px solid #e5e7eb">
                                <td style="padding:12px 14px">
                                    <div style="font-size:14px;font-weight:600;color:#111827">{{ $it->title }}</div>
                                    <div style="font-size:13px;color:#6b7280;margin-top:2px">
                                        Cantidad: {{ $it->quantity }} · S/ {{ number_format($it->subtotal, 2) }}
                                        @if($it->tenant_name) · <span style="color:#9ca3af">{{ $it->tenant_name }}</span> @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr style="background:#f0fdfa;border-top:2px solid #0f8a82">
                            <td style="padding:12px 14px;font-size:15px;font-weight:700;color:#0c6b65">
                                Total: S/ {{ number_format($order->total, 2) }}
                            </td>
                        </tr>
                    </table>
                </td></tr>

                {{-- CTA --}}
                <tr><td align="center" style="padding:24px 28px 8px">
                    <a href="{{ $checkoutUrl }}"
                       style="display:inline-block;padding:14px 32px;background:#0f8a82;color:#fff;text-decoration:none;font-weight:700;font-size:15px;border-radius:10px;box-shadow:0 4px 10px rgba(15,138,130,.25)">
                        Completar mi compra →
                    </a>
                    <p style="margin:14px 0 0;font-size:12px;color:#9ca3af">
                        O copia este link en tu navegador:<br>
                        <span style="color:#6b7280;font-family:ui-monospace,monospace;font-size:11px;word-break:break-all">{{ $checkoutUrl }}</span>
                    </p>
                </td></tr>

                {{-- Confianza --}}
                <tr><td style="padding:18px 28px">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                           style="background:#f9fafb;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#4b5563;line-height:1.55">
                        <tr><td>
                            🔒 <strong>Compra protegida.</strong> Todas las tiendas en ebaemy tienen RUC validado en SUNAT y facturación electrónica.
                        </td></tr>
                    </table>
                </td></tr>

                {{-- Footer --}}
                <tr><td style="padding:22px 28px;text-align:center;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:12px;line-height:1.55">
                    Si ya pagaste o decidiste no continuar, puedes ignorar este correo — el pedido se cerrará solo.<br>
                    ¿Dudas? Responde a este correo o escríbenos a <a href="mailto:soporte@ebaemy.com" style="color:#0f8a82;text-decoration:none">soporte@ebaemy.com</a>.
                </td></tr>

            </table>
            <div style="font-size:11px;color:#9ca3af;margin-top:12px">
                ebaemy Marketplace · Hecho en Perú 🇵🇪
            </div>
        </td>
    </tr>
</table>
</body>
</html>
