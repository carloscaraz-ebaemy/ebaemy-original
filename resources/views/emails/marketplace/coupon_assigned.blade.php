@php
    $appName = config('app.name', 'ebaemy');
    $couponsUrl = url('/marketplace/account/coupons');
    $shopUrl    = url('/marketplace');
    $valueLabel = $coupon->type === 'percent'
        ? '-' . (int) $coupon->value . '%'
        : '-S/ ' . number_format($coupon->value, 2);
    $expiresLabel = $expiresAt
        ? \Illuminate\Support\Carbon::parse($expiresAt)->locale('es')->isoFormat('D MMMM YYYY')
        : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tienes un cupn nuevo</title>
</head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#f1f5f9;color:#0f172a;line-height:1.5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f1f5f9;padding:32px 16px">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(15,23,42,.08)">
                    {{-- Header con marca --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f8a82 0%,#0a6f68 100%);padding:24px 28px;text-align:center;color:#fff">
                            <div style="font-size:22px;font-weight:800;letter-spacing:.3px">{{ $appName }}</div>
                            <div style="opacity:.85;font-size:13px;margin-top:4px">Marketplace</div>
                        </td>
                    </tr>

                    {{-- Cupn destacado --}}
                    <tr>
                        <td style="padding:32px 28px 8px;text-align:center">
                            <div style="font-size:42px;margin-bottom:6px">🎟️</div>
                            <h1 style="margin:0;font-size:22px;font-weight:800;color:#0f172a">
                                Hola {{ $user->name ?: 'comprador' }},
                            </h1>
                            <p style="margin:12px 0 0;color:#475569;font-size:15px">
                                Te asignamos un cupn de descuento para que lo uses en tu prximo pedido del marketplace.
                            </p>
                        </td>
                    </tr>

                    {{-- Caja del cupn --}}
                    <tr>
                        <td style="padding:24px 28px">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px dashed #f59e0b;border-radius:14px">
                                <tr>
                                    <td style="padding:22px 20px;text-align:center">
                                        <div style="font-size:11px;font-weight:700;color:#92400e;letter-spacing:.1em;text-transform:uppercase;margin-bottom:6px">CDIGO DEL CUPN</div>
                                        <div style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:26px;font-weight:800;color:#78350f;background:#fff;padding:10px 18px;border-radius:8px;display:inline-block;letter-spacing:.08em;border:1px solid #fde68a;">
                                            {{ $coupon->code }}
                                        </div>
                                        <div style="margin-top:14px;font-size:24px;font-weight:800;color:#b45309">{{ $valueLabel }}</div>
                                        @if($coupon->name)
                                            <div style="margin-top:6px;color:#92400e;font-size:14px">{{ $coupon->name }}</div>
                                        @endif
                                        @if($coupon->min_subtotal)
                                            <div style="margin-top:10px;color:#78350f;font-size:12px">
                                                Mnimo de compra: <strong>S/ {{ number_format($coupon->min_subtotal, 2) }}</strong>
                                            </div>
                                        @endif
                                        @if($expiresLabel)
                                            <div style="margin-top:6px;color:#78350f;font-size:12px">
                                                Vlido hasta: <strong>{{ $expiresLabel }}</strong>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cmo usarlo --}}
                    <tr>
                        <td style="padding:0 28px 8px;color:#475569;font-size:14px">
                            <p style="margin:0 0 10px;font-weight:700;color:#0f172a">Cmo usarlo:</p>
                            <ol style="margin:0;padding-left:18px;color:#475569">
                                <li style="margin-bottom:6px">Agrega productos al carrito en el marketplace</li>
                                <li style="margin-bottom:6px">En el checkout, encontrars el cupn precargado para esa tienda</li>
                                <li>El descuento se aplica automticamente al confirmar el pedido</li>
                            </ol>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td style="padding:24px 28px 28px;text-align:center">
                            <a href="{{ $shopUrl }}" style="display:inline-block;background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;text-decoration:none;font-weight:700;padding:14px 32px;border-radius:10px;font-size:15px">
                                Explorar marketplace
                            </a>
                            <div style="margin-top:14px">
                                <a href="{{ $couponsUrl }}" style="color:#0f8a82;font-size:13px;text-decoration:none">
                                    Ver todos mis cupones
                                </a>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f8fafc;padding:18px 28px;text-align:center;color:#94a3b8;font-size:11px;border-top:1px solid #e2e8f0">
                            Si no esperabas este correo, podes ignorarlo  el cupn quedar guardado en tu cuenta para cuando quieras usarlo.<br>
                            <span style="opacity:.7">{{ $appName }} {{ date('Y') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
