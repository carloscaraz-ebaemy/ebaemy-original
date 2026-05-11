<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:-apple-system,system-ui,'Helvetica Neue',sans-serif;color:#111827">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f9fafb;padding:24px 0">
    <tr>
        <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04)">

                {{-- Header --}}
                <tr><td style="background:linear-gradient(135deg,#0f8a82,#0a6f68);color:#fff;padding:28px;text-align:center">
                    <div style="font-size:14px;letter-spacing:.05em;opacity:.85">EBAEMY MARKETPLACE</div>
                    <h1 style="margin:6px 0 0;font-size:22px">¡Tu pedido fue recibido! ✓</h1>
                    <div style="margin-top:8px;font-size:16px;font-family:ui-monospace,monospace;background:rgba(255,255,255,.15);display:inline-block;padding:4px 12px;border-radius:6px">
                        {{ $order->order_number }}
                    </div>
                </td></tr>

                {{-- Intro --}}
                <tr><td style="padding:24px 28px 8px">
                    <p style="margin:0 0 14px;font-size:15px;line-height:1.5">
                        Hola <strong>{{ $order->customer_name }}</strong>, recibimos tu pedido con éxito.
                    </p>
                    <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.5">
                        Tu compra incluye productos de <strong>{{ $itemsByStore->count() }}</strong>
                        {{ $itemsByStore->count() === 1 ? 'tienda' : 'tiendas' }}.
                        Cada vendedor te contactará por WhatsApp/email para coordinar el pago, envío y comprobante.
                    </p>
                </td></tr>

                {{-- Subpedidos --}}
                <tr><td style="padding:18px 28px">
                    @foreach($itemsByStore as $hostnameId => $items)
                        @php
                            $first = $items->first();
                            $sub = $subOrders->get($hostnameId);
                            $subStatus = $sub->status ?? 'pending';
                            $statusColor = match($subStatus) {
                                'dispatched' => '#10b981',
                                'failed'     => '#ef4444',
                                'cancelled'  => '#6b7280',
                                'delivered'  => '#06b6d4',
                                default      => '#f59e0b',
                            };
                            $statusLabel = match($subStatus) {
                                'dispatched' => '✓ Enviado a la tienda',
                                'failed'     => '⚠ En reintento',
                                'cancelled'  => '✕ Cancelado',
                                'delivered'  => '🚚 Entregado',
                                default      => '⏳ Pendiente',
                            };
                            $subtotal = $items->sum('total');
                        @endphp
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid #e5e7eb;border-radius:10px;margin-bottom:14px">
                            <tr>
                                <td style="padding:14px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb">
                                    <table width="100%"><tr>
                                        <td style="font-weight:600;font-size:14px">{{ $first->tenant_fqdn }}</td>
                                        <td align="right" style="font-size:11px;color:{{ $statusColor }};font-weight:600">{{ $statusLabel }}</td>
                                    </tr></table>
                                </td>
                            </tr>
                            <tr><td style="padding:10px 16px">
                                @foreach($items as $item)
                                    <table width="100%" style="font-size:13px"><tr>
                                        <td style="padding:4px 0;color:#374151">
                                            <span style="color:#9ca3af">{{ $item->quantity }}×</span> {{ $item->title }}
                                        </td>
                                        <td align="right" style="padding:4px 0;color:#111827;font-weight:500">S/ {{ number_format($item->total, 2) }}</td>
                                    </tr></table>
                                @endforeach
                                @php
                                    $storeDiscount = (float) ($sub->discount_amount ?? 0);
                                    $hasCoupon    = $storeDiscount > 0 && !empty($sub->coupon_code);
                                @endphp
                                <table width="100%" style="margin-top:8px;padding-top:8px;border-top:1px dashed #e5e7eb;font-size:13px">
                                    <tr>
                                        <td style="padding:2px 0">Subtotal tienda</td>
                                        <td align="right">S/ {{ number_format($subtotal, 2) }}</td>
                                    </tr>
                                    @if($hasCoupon)
                                        <tr>
                                            <td style="padding:2px 0;color:#16a34a;font-weight:600">Cupón {{ $sub->coupon_code }}</td>
                                            <td align="right" style="color:#16a34a;font-weight:600">-S/ {{ number_format($storeDiscount, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding:6px 0 0;font-weight:700">Total tienda</td>
                                        <td align="right" style="padding:6px 0 0;font-weight:700;color:#0c6b65">S/ {{ number_format(max(0, $subtotal - $storeDiscount), 2) }}</td>
                                    </tr>
                                </table>
                            </td></tr>
                        </table>
                    @endforeach
                </td></tr>

                {{-- Totales --}}
                <tr><td style="padding:0 28px 8px">
                    <table width="100%" style="border-top:2px solid #e5e7eb;padding-top:14px;font-size:14px">
                        <tr><td style="padding:4px 0">Productos</td><td align="right">{{ $order->items_count }}</td></tr>
                        <tr><td style="padding:4px 0">Tiendas</td><td align="right">{{ $order->stores_count }}</td></tr>
                        @if(($order->discount_total ?? 0) > 0)
                            <tr><td style="padding:4px 0">Subtotal</td><td align="right">S/ {{ number_format($order->subtotal, 2) }}</td></tr>
                            <tr>
                                <td style="padding:4px 0;color:#16a34a;font-weight:600">Descuento cupones</td>
                                <td align="right" style="color:#16a34a;font-weight:600">-S/ {{ number_format($order->discount_total, 2) }}</td>
                            </tr>
                        @endif
                        <tr><td style="padding:10px 0;font-weight:700;font-size:18px">Total</td>
                            <td align="right" style="padding:10px 0;font-weight:800;font-size:22px;color:#0c6b65">S/ {{ number_format($order->total, 2) }}</td>
                        </tr>
                    </table>
                </td></tr>

                {{-- CTA --}}
                <tr><td align="center" style="padding:14px 28px 8px">
                    <a href="{{ $trackingUrl }}" style="display:inline-block;padding:12px 28px;background:#0f8a82;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px">
                        Ver estado del pedido →
                    </a>
                </td></tr>

                {{-- Datos de entrega --}}
                <tr><td style="padding:18px 28px">
                    <div style="background:#fef3c7;border-radius:8px;padding:14px 16px;font-size:13px;color:#92400e">
                        <strong>📦 Dirección de entrega</strong><br>
                        {{ $order->delivery_address }}@if($order->delivery_district), {{ $order->delivery_district }}@endif
                        @if($order->delivery_notes)<br><em style="opacity:.85">Notas: {{ $order->delivery_notes }}</em>@endif
                    </div>
                </td></tr>

                {{-- Footer --}}
                <tr><td style="padding:18px 28px 28px;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #f3f4f6">
                    Este correo se envió a {{ $order->customer_email }} porque hiciste un pedido en
                    <a href="{{ url('/marketplace') }}" style="color:#0c6b65">ebaemy.com/marketplace</a>.
                </td></tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
