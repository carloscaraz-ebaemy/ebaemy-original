<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu carrito te está esperando</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; color: #1e293b; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 24px 16px; }

        .ac-header { border-radius: 12px 12px 0 0; padding: 32px 32px 24px; text-align: center; }
        .ac-header--step1 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .ac-header--step2 { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .ac-header--step3 { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .ac-header__icon { font-size: 48px; margin-bottom: 12px; display: block; }
        .ac-header__title { color: #fff; font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .ac-header__sub   { color: rgba(255,255,255,.9); font-size: 14px; }

        .ac-card { background: #fff; padding: 32px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,.07); }

        .ac-greeting { font-size: 16px; margin-bottom: 20px; line-height: 1.6; }

        .ac-items { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .ac-items td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .ac-items tr:last-child td { border-bottom: none; }
        .ac-item-name { font-weight: 600; color: #1e293b; }
        .ac-item-qty  { color: #64748b; font-size: 13px; }
        .ac-item-price { text-align: right; font-weight: 700; color: #059669; white-space: nowrap; }

        .ac-subtotal { text-align: right; font-size: 16px; font-weight: 700; color: #1e293b; margin: 8px 0 24px; }

        .ac-cta { display: block; color: #fff; text-decoration: none; text-align: center; padding: 16px 32px; border-radius: 10px; font-size: 17px; font-weight: 700; margin: 24px 0; letter-spacing: .02em; }
        .ac-cta--step1 { background: #f59e0b; }
        .ac-cta--step2 { background: #ef4444; }
        .ac-cta--step3 { background: #8b5cf6; }

        .ac-urgency { border-radius: 8px; padding: 12px 16px; font-size: 13px; text-align: center; margin-bottom: 20px; }
        .ac-urgency--step1 { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
        .ac-urgency--step2 { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .ac-urgency--step3 { background: #ede9fe; border: 1px solid #ddd6fe; color: #5b21b6; }

        .ac-discount { background: #f5f3ff; border: 2px dashed #8b5cf6; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
        .ac-discount__code { display: inline-block; background: #8b5cf6; color: #fff; font-size: 22px; font-weight: 800; padding: 8px 24px; border-radius: 6px; letter-spacing: 2px; margin: 8px 0; }
        .ac-discount__label { font-size: 14px; color: #5b21b6; font-weight: 600; }

        .ac-footer { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 24px; line-height: 1.6; }
        .ac-footer a { color: #94a3b8; }
    </style>
</head>
<body>
@php $step = $step ?? 1; @endphp
<div class="wrapper">
    <div class="ac-header ac-header--step{{ $step }}">
        @if($step === 1)
        <span class="ac-header__icon">&#128722;</span>
        <div class="ac-header__title">Tu carrito te esta esperando!</div>
        @elseif($step === 2)
        <span class="ac-header__icon">&#9200;</span>
        <div class="ac-header__title">Stock limitado! No te quedes sin tus productos</div>
        @else
        <span class="ac-header__icon">&#127873;</span>
        <div class="ac-header__title">10% de descuento exclusivo para ti!</div>
        @endif
        <div class="ac-header__sub">{{ $storeName }}</div>
    </div>

    <div class="ac-card">
        <p class="ac-greeting">
            Hola, <strong>{{ $firstName }}</strong>!<br><br>
            @if($step === 1)
            Notamos que dejaste algunos articulos en tu carrito. No te preocupes, los guardamos para ti!
            @elseif($step === 2)
            Tus articulos favoritos siguen en tu carrito, pero el stock es limitado. No dejes pasar esta oportunidad!
            @else
            Queremos que completes tu compra, por eso te ofrecemos un <strong>10% de descuento exclusivo</strong>. Usa el codigo antes de que expire!
            @endif
        </p>

        @if($step === 3 && !empty($discountCode))
        <div class="ac-discount">
            <div class="ac-discount__label">Tu codigo de descuento exclusivo:</div>
            <div class="ac-discount__code">{{ $discountCode }}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:6px;">Valido por 48 horas. Aplica a toda tu compra.</div>
        </div>
        @endif

        <p style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:8px;">
            Articulos en tu carrito
        </p>

        <table class="ac-items">
            @foreach(array_slice((array)$items, 0, 5) as $item)
            @php $item = (array) $item; @endphp
            <tr>
                <td>
                    <div class="ac-item-name">{{ $item['description'] ?? $item['name'] ?? 'Producto' }}</div>
                    @if(!empty($item['variant_display_name']))
                        <div class="ac-item-qty">Variante: {{ $item['variant_display_name'] }}</div>
                    @endif
                    <div class="ac-item-qty">Cantidad: {{ $item['quantity'] ?? $item['cantidad'] ?? 1 }}</div>
                </td>
                <td class="ac-item-price">
                    S/ {{ number_format(($item['sale_unit_price'] ?? 0) * ($item['quantity'] ?? $item['cantidad'] ?? 1), 2) }}
                </td>
            </tr>
            @endforeach
            @if(count((array)$items) > 5)
            <tr>
                <td colspan="2" style="text-align:center;color:#94a3b8;font-size:13px;padding:8px">
                    + {{ count((array)$items) - 5 }} articulo(s) mas...
                </td>
            </tr>
            @endif
        </table>

        <div class="ac-subtotal">
            Subtotal: S/ {{ $subtotal }}
            @if($step === 3 && !empty($discountCode))
            <br><span style="color:#8b5cf6;font-size:14px;">Con descuento: S/ {{ number_format((float)str_replace(',', '', $subtotal) * 0.9, 2) }}</span>
            @endif
        </div>

        <div class="ac-urgency ac-urgency--step{{ $step }}">
            @if($step === 1)
            Los articulos en tu carrito tienen stock limitado. Completa tu compra antes de que se agoten.
            @elseif($step === 2)
            <strong>ULTIMA OPORTUNIDAD:</strong> Varios de tus articulos tienen menos de 5 unidades disponibles. No esperes mas!
            @else
            Esta oferta de <strong>10% OFF</strong> es exclusiva y expira en 48 horas. Aprovechala ahora!
            @endif
        </div>

        <a href="{{ $cartUrl }}" class="ac-cta ac-cta--step{{ $step }}">
            @if($step === 1)
            Volver a mi carrito &rarr;
            @elseif($step === 2)
            Completar mi compra ahora &rarr;
            @else
            Usar mi 10% OFF &rarr;
            @endif
        </a>

        <p style="font-size:13px;color:#64748b;text-align:center">
            Tambien puedes visitar nuestra tienda en
            <a href="{{ $storeUrl }}" style="color:#f59e0b;font-weight:600">{{ $storeUrl }}</a>
        </p>
    </div>

    <div class="ac-footer">
        Este mensaje fue enviado por <strong>{{ $storeName }}</strong> porque tienes articulos pendientes en tu carrito.<br>
        Si ya completaste tu compra, ignora este mensaje.
    </div>
</div>
</body>
</html>
