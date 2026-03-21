<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de pedido</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; color: #1e293b; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 24px 16px; }

        /* Header */
        .ec-header { background: #16a34a; border-radius: 12px 12px 0 0; padding: 32px 32px 24px; text-align: center; }
        .ec-header__icon { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: rgba(255,255,255,.2); border-radius: 50%; margin-bottom: 14px; }
        .ec-header__title { color: #fff; font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .ec-header__sub   { color: rgba(255,255,255,.85); font-size: 14px; }

        /* Body card */
        .ec-card { background: #fff; padding: 32px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,.07); }

        /* Greeting */
        .ec-greeting { font-size: 16px; margin-bottom: 20px; }
        .ec-greeting strong { color: #16a34a; }

        /* Order meta */
        .ec-meta { display: flex; gap: 12px; margin-bottom: 28px; flex-wrap: wrap; }
        .ec-meta__item { flex: 1; min-width: 120px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        .ec-meta__label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 4px; }
        .ec-meta__value { font-size: 14px; font-weight: 700; color: #1e293b; }

        /* Section title */
        .ec-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; }

        /* Items table */
        .ec-items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .ec-items thead th { font-size: 12px; color: #94a3b8; text-align: left; padding: 8px 10px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .ec-items tbody td { font-size: 14px; padding: 10px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .ec-items tbody tr:last-child td { border-bottom: none; }
        .ec-item-name { font-weight: 600; color: #1e293b; }
        .ec-item-code { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .ec-item-price { text-align: right; white-space: nowrap; font-weight: 700; color: #1e293b; }
        .ec-item-qty   { text-align: center; color: #64748b; }

        /* Totals */
        .ec-totals { margin-bottom: 28px; }
        .ec-totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        .ec-totals-row:last-child { border-bottom: none; padding-top: 10px; margin-top: 4px; border-top: 2px solid #e2e8f0; }
        .ec-totals-row--total { font-weight: 800; font-size: 18px; color: #16a34a; }

        /* CTA button */
        .ec-cta { text-align: center; margin: 28px 0 24px; }
        .ec-cta a { display: inline-block; background: #16a34a; color: #fff !important; text-decoration: none !important; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 8px; letter-spacing: .02em; }

        /* Shipping info */
        .ec-shipping { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 18px; margin-bottom: 24px; font-size: 14px; }
        .ec-shipping__row { display: flex; gap: 8px; align-items: flex-start; padding: 4px 0; }
        .ec-shipping__label { color: #94a3b8; min-width: 80px; font-size: 12px; padding-top: 1px; }
        .ec-shipping__value { color: #1e293b; font-weight: 600; word-break: break-word; }

        /* Footer */
        .ec-footer { text-align: center; padding: 20px 0 0; font-size: 12px; color: #94a3b8; line-height: 1.6; }
        .ec-footer a { color: #16a34a; text-decoration: none; }

        @media (max-width: 480px) {
            .ec-card { padding: 20px 16px; }
            .ec-header { padding: 24px 16px 18px; }
            .ec-meta { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="wrapper">

    {{-- HEADER --}}
    <div class="ec-header">
        <div class="ec-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                 fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="ec-header__title">¡Pedido recibido!</div>
        <div class="ec-header__sub">Gracias por tu compra en {{ $company->name ?? 'nuestra tienda' }}</div>
    </div>

    {{-- BODY --}}
    <div class="ec-card">

        {{-- Saludo --}}
        <p class="ec-greeting">
            Hola, <strong>{{ $customerName }}</strong>.<br>
            Hemos recibido tu pedido correctamente y lo estamos procesando.
            Te notificaremos cuando sea despachado.
        </p>

        {{-- Datos del pedido --}}
        <div class="ec-meta">
            <div class="ec-meta__item">
                <div class="ec-meta__label">Pedido #</div>
                <div class="ec-meta__value">{{ $orderNumber }}</div>
            </div>
            <div class="ec-meta__item">
                <div class="ec-meta__label">Fecha</div>
                <div class="ec-meta__value">{{ $order->created_at->format('d/m/Y') }}</div>
            </div>
            <div class="ec-meta__item">
                <div class="ec-meta__label">Estado</div>
                <div class="ec-meta__value" style="color:#16a34a;">
                    {{ $order->status_order->description ?? 'Pendiente' }}
                </div>
            </div>
        </div>

        {{-- Productos --}}
        <div class="ec-section-title">Resumen de tu pedido</div>
        <table class="ec-items">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="ec-item-qty">Cant.</th>
                    <th class="ec-item-price">Precio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                @php
                    $desc     = $item['description'] ?? $item['descripcion'] ?? 'Producto';
                    $code     = $item['internal_id'] ?? $item['codigo_interno'] ?? '';
                    $qty      = (int)($item['cantidad'] ?? $item['quantity'] ?? 1);
                    $price    = (float)($item['sale_unit_price'] ?? $item['precio_unitario'] ?? 0);
                    $subtotal = $qty * $price;
                @endphp
                <tr>
                    <td>
                        <div class="ec-item-name">{{ $desc }}</div>
                        @if($code)<div class="ec-item-code">Cód: {{ $code }}</div>@endif
                    </td>
                    <td class="ec-item-qty">{{ $qty }}</td>
                    <td class="ec-item-price">S/ {{ number_format($subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Total --}}
        <div class="ec-totals">
            <div class="ec-totals-row ec-totals-row--total">
                <span>Total pagado</span>
                <span>S/ {{ $total }}</span>
            </div>
        </div>

        {{-- Dirección de envío --}}
        @php
            $customer = $order->customer;
            $customerAddr  = is_object($customer) ? ($customer->direccion ?? '') : ($customer['direccion'] ?? '');
            $customerPhone = is_object($customer) ? ($customer->telefono ?? '') : ($customer['telefono'] ?? '');
        @endphp
        @if($customerAddr || $customerPhone)
        <div class="ec-section-title">Datos de entrega</div>
        <div class="ec-shipping">
            @if($customerAddr)
            <div class="ec-shipping__row">
                <span class="ec-shipping__label">Dirección</span>
                <span class="ec-shipping__value">{{ $customerAddr }}</span>
            </div>
            @endif
            @if($customerPhone)
            <div class="ec-shipping__row">
                <span class="ec-shipping__label">Teléfono</span>
                <span class="ec-shipping__value">{{ $customerPhone }}</span>
            </div>
            @endif
            <div class="ec-shipping__row">
                <span class="ec-shipping__label">Email</span>
                <span class="ec-shipping__value">{{ $customerEmail }}</span>
            </div>
        </div>
        @endif

        {{-- Botón de seguimiento --}}
        <div class="ec-cta">
            <a href="{{ $confirmUrl }}">Ver detalles de mi pedido →</a>
        </div>

        <p style="font-size:13px;color:#64748b;text-align:center;line-height:1.6;">
            ¿Tienes alguna consulta? Responde este correo o
            contáctanos por WhatsApp y te ayudamos.
        </p>

    </div>

    {{-- Footer --}}
    <div class="ec-footer">
        <p>© {{ date('Y') }} {{ $company->name ?? 'Tienda Online' }}. Todos los derechos reservados.</p>
        @if(!empty($company->address))
        <p>{{ $company->address }}</p>
        @endif
        <p style="margin-top:8px;">
            Este correo fue enviado a <strong>{{ $customerEmail }}</strong> porque realizaste una compra.
        </p>
    </div>

</div>
</body>
</html>
