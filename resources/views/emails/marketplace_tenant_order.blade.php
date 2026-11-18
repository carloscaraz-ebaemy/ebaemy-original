<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido marketplace {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,system-ui,'Helvetica Neue',sans-serif;color:#111827">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f9fafb;padding:24px 0">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04)">

                {{-- Header --}}
                @php
                    $headerBg = !empty($isReminder)
                        ? 'linear-gradient(135deg,#f59e0b,#dc2626)'
                        : 'linear-gradient(135deg,#0f8a82,#0a6f68)';
                    $headerLabel = !empty($isReminder)
                        ? 'Pedido pendiente de atención'
                        : 'Tienes un nuevo pedido';
                    $headerKicker = !empty($isReminder)
                        ? 'RECORDATORIO ' . ($reminderNumber ?? 1) . ' DE 3'
                        : 'NOTIFICACIÓN DE PEDIDO';
                @endphp
                <tr><td style="background:{{ $headerBg }};color:#fff;padding:28px 24px;text-align:center">
                    <div style="font-size:11px;letter-spacing:.12em;opacity:.85;font-weight:600">{{ $headerKicker }}</div>
                    <h1 style="margin:10px 0 0;font-size:22px;font-weight:700;letter-spacing:-.01em">{{ $headerLabel }}</h1>
                    <div style="margin-top:12px;font-size:13px;font-family:ui-monospace,'SF Mono',Menlo,monospace;background:rgba(255,255,255,.18);display:inline-block;padding:5px 14px;border-radius:6px;letter-spacing:.02em">
                        N° {{ $order->order_number }}
                    </div>
                </td></tr>

                {{-- Intro --}}
                <tr><td style="padding:26px 28px 6px">
                    @if(!empty($isReminder))
                        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;margin-bottom:18px">
                            <div style="color:#991b1b;font-size:14px;font-weight:700;margin-bottom:4px">
                                Acción requerida
                            </div>
                            <p style="margin:0;font-size:13.5px;color:#7f1d1d;line-height:1.55">
                                Este pedido lleva varias horas pendiente en tu panel. Te recomendamos contactar al cliente cuanto antes para coordinar el pago y la entrega. Si no se atiende, el pedido podrá cancelarse automáticamente tras el último recordatorio.
                            </p>
                        </div>
                        <p style="margin:0 0 12px;font-size:15px;line-height:1.55;color:#111827">
                            Hola, este es un recordatorio del pedido <strong>{{ $order->order_number }}</strong> que llegó a <strong>{{ $tenantFqdn }}</strong> desde el marketplace de ebaemy y aún figura como pendiente.
                        </p>
                    @else
                        <p style="margin:0 0 12px;font-size:15.5px;line-height:1.55;color:#111827">
                            Hola, recibiste un nuevo pedido en <strong>{{ $tenantFqdn }}</strong> a través del marketplace central <strong>ebaemy.com</strong>.
                        </p>
                        <p style="margin:0;font-size:14px;line-height:1.55;color:#4b5563">
                            A continuación encontrarás el detalle del pedido y los datos del cliente. Te sugerimos contactarlo dentro de las próximas <strong>2 horas</strong> para confirmar la disponibilidad de stock y coordinar entrega.
                        </p>
                    @endif
                </td></tr>

                {{-- Tabla items del subpedido --}}
                <tr><td style="padding:8px 28px">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                           style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <tr style="background:#f9fafb">
                            <td colspan="2" style="padding:10px 14px;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em">
                                Productos pedidos ({{ $items->count() }})
                            </td>
                        </tr>
                        @foreach($items as $line)
                            <tr style="border-top:1px solid #e5e7eb">
                                <td style="padding:10px 14px;font-size:14px">
                                    <div style="font-weight:600;color:#111827">{{ $line->title }}</div>
                                    <div style="font-size:12.5px;color:#6b7280;margin-top:2px">×{{ (int) $line->quantity }}</div>
                                </td>
                                <td style="padding:10px 14px;text-align:right;font-size:14px;font-weight:600;color:#111827">
                                    S/ {{ number_format($line->total ?? 0, 2) }}
                                </td>
                            </tr>
                        @endforeach
                        <tr style="background:#f0fdfa;border-top:2px solid #0f8a82">
                            <td style="padding:14px 14px;font-size:14px;font-weight:600;color:#0c6b65">
                                Total del pedido
                            </td>
                            <td style="padding:14px 14px;text-align:right;font-size:17px;font-weight:800;color:#0c6b65;letter-spacing:-.01em">
                                S/ {{ number_format($subtotal, 2) }}
                            </td>
                        </tr>
                    </table>
                </td></tr>

                {{-- Datos del cliente --}}
                <tr><td style="padding:18px 28px">
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px 18px">
                        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
                            Datos del cliente
                        </div>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:13.5px;color:#1f2937;line-height:1.55">
                            <tr>
                                <td style="padding:2px 0;width:100px;color:#6b7280">Nombre:</td>
                                <td style="padding:2px 0;font-weight:600">{{ $order->customer_name }}</td>
                            </tr>
                            @if($order->customer_doc_number)
                                <tr>
                                    <td style="padding:2px 0;color:#6b7280">{{ $order->customer_doc_type ?? 'Documento' }}:</td>
                                    <td style="padding:2px 0">{{ $order->customer_doc_number }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:2px 0;color:#6b7280">Teléfono:</td>
                                <td style="padding:2px 0">
                                    <a href="tel:{{ $order->customer_phone }}" style="color:#0c6b65;text-decoration:none;font-weight:600">{{ $order->customer_phone }}</a>
                                    &nbsp;·&nbsp;
                                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $order->customer_phone) }}" style="color:#16a34a;text-decoration:none;font-weight:600">WhatsApp →</a>
                                </td>
                            </tr>
                            @if($order->customer_email)
                                <tr>
                                    <td style="padding:2px 0;color:#6b7280">Correo:</td>
                                    <td style="padding:2px 0"><a href="mailto:{{ $order->customer_email }}" style="color:#0c6b65;text-decoration:none">{{ $order->customer_email }}</a></td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:6px 0 2px;color:#6b7280;vertical-align:top">Entrega:</td>
                                <td style="padding:6px 0 2px;line-height:1.45">
                                    {{ $order->delivery_address }}@if($order->delivery_district) — {{ $order->delivery_district }}@endif
                                </td>
                            </tr>
                            @if($order->delivery_notes)
                                <tr>
                                    <td style="padding:2px 0;color:#6b7280;vertical-align:top">Indicaciones:</td>
                                    <td style="padding:2px 0;font-style:italic;color:#4b5563">{{ $order->delivery_notes }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </td></tr>

                {{-- Pasos sugeridos --}}
                <tr><td style="padding:16px 28px 4px">
                    <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
                        Próximos pasos
                    </div>
                    <ol style="margin:0;padding-left:20px;font-size:13.5px;color:#374151;line-height:1.65">
                        <li>Contacta al cliente para confirmar disponibilidad y método de pago.</li>
                        <li>Confirma el pedido desde tu panel y coordina la entrega.</li>
                        <li>Emite el comprobante electrónico correspondiente.</li>
                    </ol>
                </td></tr>

                {{-- CTA --}}
                <tr><td align="center" style="padding:22px 28px 8px">
                    <a href="{{ $panelUrl }}"
                       style="display:inline-block;padding:14px 32px;background:#0f8a82;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;border-radius:10px;letter-spacing:.01em">
                        Atender este pedido
                    </a>
                </td></tr>

                {{-- Cita / linea de credibilidad --}}
                <tr><td style="padding:6px 28px 18px;text-align:center">
                    <div style="font-size:11.5px;color:#9ca3af;line-height:1.5">
                        Acceso directo: <a href="{{ $panelUrl }}" style="color:#0f8a82;text-decoration:none">{{ $panelUrl }}</a>
                    </div>
                </td></tr>

                {{-- Footer --}}
                <tr><td style="padding:20px 28px;text-align:center;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:12px;line-height:1.6">
                    Recibiste este correo porque eres vendedor activo en <strong>ebaemy Marketplace</strong>.<br>
                    Para consultas o reclamos escríbenos a <a href="mailto:soporte@ebaemy.com" style="color:#0f8a82;text-decoration:none">soporte@ebaemy.com</a>.<br>
                    <span style="display:inline-block;margin-top:6px">© {{ date('Y') }} ebaemy · Marketplace peruano</span>
                </td></tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
