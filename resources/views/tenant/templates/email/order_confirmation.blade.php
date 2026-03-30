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
        .ec-item-img   { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
        .ec-item-img-cell { width: 60px; padding-right: 0 !important; }

        /* Estimated delivery */
        .ec-delivery { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .ec-delivery__icon { font-size: 24px; flex-shrink: 0; }
        .ec-delivery__text { font-size: 14px; color: #065f46; }
        .ec-delivery__text strong { color: #047857; }

        /* Tracking */
        .ec-tracking { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; text-align: center; }
        .ec-tracking a { color: #2563eb; font-weight: 700; text-decoration: none; }

        /* Recommendations */
        .ec-recs { margin-top: 28px; }
        .ec-recs-grid { display: flex; gap: 12px; flex-wrap: wrap; }
        .ec-rec-card { flex: 1; min-width: 140px; max-width: 180px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
        .ec-rec-img { width: 100%; height: 100px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; background: #e2e8f0; }
        .ec-rec-name { font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px; line-height: 1.3; }
        .ec-rec-price { font-size: 14px; font-weight: 700; color: #16a34a; }
        .ec-rec-link { display: inline-block; margin-top: 6px; font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600; }

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
                    <th></th>
                    <th>Producto</th>
                    <th class="ec-item-qty">Cant.</th>
                    <th class="ec-item-price">Precio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                @php
                    $item         = (array) $item;
                    $desc         = $item['description'] ?? $item['descripcion'] ?? 'Producto';
                    $code         = $item['internal_id'] ?? $item['codigo_interno'] ?? '';
                    $qty          = (int)($item['cantidad'] ?? $item['quantity'] ?? 1);
                    $price        = (float)($item['sale_unit_price'] ?? $item['precio_unitario'] ?? 0);
                    $subtotal     = $qty * $price;
                    $variantLabel = $item['variant_label'] ?? null;
                    $itemImage    = $item['image_url'] ?? $item['image'] ?? null;
                    if (!$itemImage && !empty($item['item_id'])) {
                        $dbItem = \App\Models\Tenant\Item::find($item['item_id']);
                        $itemImage = $dbItem && $dbItem->image ? asset('storage/uploads/items/' . $dbItem->image) : null;
                    }
                    // Fallback: resolve variant name from DB if only variant_id stored
                    if (!$variantLabel && !empty($item['variant_id'])) {
                        $v = \App\Models\Tenant\ItemVariant::find($item['variant_id']);
                        $variantLabel = $v ? $v->display_name : null;
                    }
                @endphp
                <tr>
                    <td class="ec-item-img-cell">
                        @if($itemImage)
                        <img src="{{ $itemImage }}" alt="{{ $desc }}" class="ec-item-img">
                        @else
                        <div style="width:56px;height:56px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        </div>
                        @endif
                    </td>
                    <td>
                        <div class="ec-item-name">{{ $desc }}</div>
                        @if($variantLabel)
                        <div style="display:inline-block;margin-top:4px;padding:2px 8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;font-size:11px;color:#475569;font-weight:600;">
                            {{ $variantLabel }}
                        </div>
                        @endif
                        @if($code)<div class="ec-item-code">Cod: {{ $code }}</div>@endif
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

        {{-- Entrega estimada --}}
        <div class="ec-delivery">
            <div class="ec-delivery__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#047857" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <div class="ec-delivery__text">
                <strong>Entrega estimada: 3-5 dias habiles</strong><br>
                Te notificaremos cuando tu pedido sea despachado con el numero de seguimiento.
            </div>
        </div>

        {{-- Tracking link --}}
        @if(!empty($trackingUrl))
        <div class="ec-tracking">
            <p style="margin:0;font-size:14px;color:#1e40af;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="vertical-align:middle;margin-right:4px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <a href="{{ $trackingUrl }}">Rastrear mi pedido en tiempo real</a>
            </p>
        </div>
        @else
        <div class="ec-tracking">
            <p style="margin:0;font-size:14px;color:#1e40af;">
                El enlace de rastreo estara disponible una vez que tu pedido sea despachado.
            </p>
        </div>
        @endif

        {{-- Boton de seguimiento --}}
        <div class="ec-cta">
            <a href="{{ $confirmUrl }}">Ver detalles de mi pedido &rarr;</a>
        </div>

        {{-- Tambien te puede gustar --}}
        @php
            $ecConfig = \App\Models\Tenant\ConfigurationEcommerce::first();
            $recommendedItems = [];
            try {
                // Obtener IDs de items del pedido para excluirlos
                $orderItemIds = collect($items)->map(function($i) {
                    $i = (array) $i;
                    return $i['item_id'] ?? null;
                })->filter()->toArray();

                // Buscar 3 productos activos aleatorios excluyendo los del pedido
                $recommendedItems = \App\Models\Tenant\Item::where('active', 1)
                    ->whereNotIn('id', $orderItemIds)
                    ->where('unit_type_id', '!=', 'ZZ') // excluir servicios
                    ->inRandomOrder()
                    ->limit(3)
                    ->get();
            } catch (\Throwable $e) {
                $recommendedItems = collect();
            }
        @endphp
        @if($recommendedItems->count() > 0)
        <div class="ec-recs">
            <div class="ec-section-title">Tambien te puede gustar</div>
            <div class="ec-recs-grid">
                @foreach($recommendedItems as $recItem)
                @php
                    $recImage = $recItem->image ? asset('storage/uploads/items/' . $recItem->image) : null;
                    $recPrice = $recItem->sale_unit_price ?? 0;
                    $recUrl   = url('/ecommerce/item/' . $recItem->id);
                @endphp
                <div class="ec-rec-card">
                    @if($recImage)
                    <img src="{{ $recImage }}" alt="{{ $recItem->description }}" class="ec-rec-img">
                    @else
                    <div class="ec-rec-img" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    </div>
                    @endif
                    <div class="ec-rec-name">{{ \Illuminate\Support\Str::limit($recItem->description, 40) }}</div>
                    <div class="ec-rec-price">S/ {{ number_format($recPrice, 2) }}</div>
                    <a href="{{ $recUrl }}" class="ec-rec-link">Ver producto &rarr;</a>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <p style="font-size:13px;color:#64748b;text-align:center;line-height:1.6;">
            ¿Tienes alguna consulta? Responde este correo o contáctanos.
        </p>
        @if(!empty($ecConfig->phone_whatsapp))
        <div style="text-align:center;margin-top:12px;">
            <a href="https://wa.me/{{ preg_replace('/\D/','',$ecConfig->phone_whatsapp) }}?text={{ rawurlencode('Hola! Consulto por mi pedido #' . ($orderNumber ?? '')) }}"
               style="display:inline-block;background:#25d366;color:#fff;text-decoration:none;font-size:14px;font-weight:700;padding:10px 24px;border-radius:6px;">
                💬 Escribir por WhatsApp
            </a>
        </div>
        @endif

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
