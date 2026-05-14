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
                        ? '⏰ RECORDATORIO · PEDIDO PENDIENTE'
                        : '🛍️ Nuevo pedido en tu tienda';
                @endphp
                <tr><td style="background:{{ $headerBg }};color:#fff;padding:24px;text-align:center">
                    <div style="font-size:13px;letter-spacing:.05em;opacity:.9">EBAEMY MARKETPLACE</div>
                    <h1 style="margin:8px 0 0;font-size:21px">{{ $headerLabel }}</h1>
                    <div style="margin-top:8px;font-size:14px;font-family:ui-monospace,monospace;background:rgba(255,255,255,.15);display:inline-block;padding:4px 12px;border-radius:6px">
                        {{ $order->order_number }}
                    </div>
                </td></tr>

                {{-- Intro --}}
                <tr><td style="padding:24px 28px 8px">
                    @if(!empty($isReminder))
                        <div style="background:#fef2f2;border-left:4px solid #dc2626;padding:14px 16px;border-radius:8px;margin-bottom:14px">
                            <strong style="color:#991b1b;font-size:14px">⚠ Este pedido sigue pendiente de atención.</strong>
                            <p style="margin:6px 0 0;font-size:13px;color:#7f1d1d;line-height:1.5">
                                Es el recordatorio número <strong>{{ $reminderNumber ?? 1 }}</strong>.
                                Contacta al comprador para no perder la venta — si pasan más recordatorios sin acción,
                                el pedido podría cancelarse automáticamente.
                            </p>
                        </div>
                    @endif
                    <p style="margin:0 0 12px;font-size:15px;line-height:1.5">
                        @if(!empty($isReminder))
                            Hace unas horas llegó este pedido a <strong>{{ $tenantFqdn }}</strong> desde el marketplace y aún no lo has atendido.
                        @else
                            Llegó un pedido a <strong>{{ $tenantFqdn }}</strong> desde el marketplace central <strong>ebaemy.com/marketplace</strong>.
                        @endif
                    </p>
                    <p style="margin:0;font-size:13px;color:#6b7280">
                        Contáctalo cuanto antes para coordinar el pago y la entrega.
                    </p>
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
                            <td style="padding:12px 14px;font-size:14px;font-weight:700;color:#0c6b65">
                                Total tu subpedido
                            </td>
                            <td style="padding:12px 14px;text-align:right;font-size:16px;font-weight:800;color:#0c6b65">
                                S/ {{ number_format($subtotal, 2) }}
                            </td>
                        </tr>
                    </table>
                </td></tr>

                {{-- Datos del cliente --}}
                <tr><td style="padding:18px 28px">
                    <div style="background:#fef3c7;border-left:3px solid #f59e0b;border-radius:8px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">
                            👤 Datos del comprador
                        </div>
                        <div style="font-size:14px;font-weight:600;color:#111827">{{ $order->customer_name }}</div>
                        @if($order->customer_doc_number)
                            <div style="font-size:12.5px;color:#4b5563;margin-top:2px">
                                {{ $order->customer_doc_type ?? 'DOC' }} {{ $order->customer_doc_number }}
                            </div>
                        @endif
                        <div style="font-size:13px;color:#374151;margin-top:6px">📱 {{ $order->customer_phone }}</div>
                        @if($order->customer_email)
                            <div style="font-size:13px;color:#374151;margin-top:2px">✉️ {{ $order->customer_email }}</div>
                        @endif
                        <div style="font-size:13px;color:#374151;margin-top:8px">
                            <strong>Entrega:</strong> {{ $order->delivery_address }}
                            @if($order->delivery_district) — {{ $order->delivery_district }} @endif
                        </div>
                        @if($order->delivery_notes)
                            <div style="font-size:12.5px;color:#6b7280;margin-top:4px;font-style:italic">
                                💬 {{ $order->delivery_notes }}
                            </div>
                        @endif
                    </div>
                </td></tr>

                {{-- CTA --}}
                <tr><td align="center" style="padding:8px 28px 24px">
                    <a href="{{ $panelUrl }}"
                       style="display:inline-block;padding:13px 28px;background:#111827;color:#fff;text-decoration:none;font-weight:700;font-size:14px;border-radius:10px">
                        Ver pedido en mi panel →
                    </a>
                </td></tr>

                {{-- Footer --}}
                <tr><td style="padding:18px 28px;text-align:center;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:12px;line-height:1.55">
                    ebaemy Marketplace · Hecho en Perú 🇵🇪<br>
                    ¿Dudas? Responde a este correo o escribe a <a href="mailto:soporte@ebaemy.com" style="color:#0f8a82;text-decoration:none">soporte@ebaemy.com</a>.
                </td></tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
