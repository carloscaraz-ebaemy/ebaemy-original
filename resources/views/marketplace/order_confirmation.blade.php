@extends('marketplace.layout')

@section('title', 'Pedido ' . $order->order_number . ' confirmado — Marketplace ebaemy')
@section('description', 'Tu pedido fue recibido. Cada tienda te contactará para coordinar entrega.')
@section('canonical', route('marketplace.order.confirmation', ['number' => $order->order_number]))

@push('styles')
<style>
.mp-conf-card {
    background: #fff; border: 1px solid var(--mp-border, #e5e7eb);
    border-radius: 14px; padding: 28px; margin-bottom: 16px;
}
.mp-conf-success {
    text-align: center; background: linear-gradient(135deg, #ecfeff 0%, #e0f2fe 100%);
    border: 1px solid #a5f3fc; border-radius: 14px; padding: 32px 22px; margin-bottom: 24px;
}
.mp-conf-success .icon {
    width: 64px; height: 64px; border-radius: 50%; background: #10b981;
    margin: 0 auto 14px; display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 32px;
}
.mp-conf-success h1 { margin: 0; font-size: clamp(22px, 3vw, 28px); color: var(--mp-ink, #111827); }
.mp-conf-success .num { font-family: ui-monospace, monospace; font-size: 18px; color: var(--mp-primary-dark, #0c6b65); margin-top: 6px; }

.mp-conf-store {
    border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px;
    margin-bottom: 14px; background: #fafafa;
}
.mp-conf-store-head {
    display: flex; gap: 10px; align-items: center; padding-bottom: 12px;
    margin-bottom: 12px; border-bottom: 1px solid #e5e7eb;
}
.mp-conf-store-head .name { font-weight: 600; flex: 1; color: var(--mp-ink, #111827); }
.mp-conf-status {
    padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
}
.status-dispatched { background: #d1fae5; color: #065f46; }
.status-pending    { background: #fef3c7; color: #92400e; }
.status-failed     { background: #fee2e2; color: #991b1b; }
.status-delivered  { background: #cffafe; color: #155e75; }

.mp-conf-line { display: flex; justify-content: space-between; padding: 5px 0; font-size: 14px; }
.mp-conf-line .qty { color: #6b7280; margin-right: 6px; }

.mp-conf-totals { padding-top: 12px; border-top: 1px dashed #e5e7eb; margin-top: 8px; }
.mp-conf-totals .row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
.mp-conf-totals .total { font-weight: 700; font-size: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb; margin-top: 8px; }

.mp-conf-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
.mp-conf-btn {
    flex: 1; min-width: 200px;
    padding: 12px 18px; border-radius: 10px; text-align: center;
    text-decoration: none; font-weight: 600; font-size: 14px;
}
.mp-conf-btn--primary { background: var(--mp-primary, #0f8a82); color: #fff; }
.mp-conf-btn--primary:hover { background: var(--mp-primary-dark, #0c6b65); color: #fff; }
.mp-conf-btn--ghost { background: #fff; color: var(--mp-ink, #111827); border: 1px solid #e5e7eb; }
</style>
@endpush

@section('content')

@php
    $isPaid    = $order->payment_status === 'paid';
    $isUnpaid  = $order->payment_status === 'unpaid';
    $usingMP   = $order->payment_provider === 'mercadopago';
@endphp

<div class="mp-conf-success" style="{{ $isUnpaid && $usingMP ? 'background:#fff7ed;border:1px solid #fed7aa' : '' }}">
    @if($isPaid)
        <div class="icon">✓</div>
        <h1>¡Pago confirmado!</h1>
    @elseif($isUnpaid && $usingMP)
        <div class="icon" style="background:#f59e0b">⏳</div>
        <h1>Tu pedido está pendiente de pago</h1>
    @else
        <div class="icon">✓</div>
        <h1>¡Tu pedido fue recibido!</h1>
    @endif
    <div class="num">{{ $order->order_number }}</div>

    @if($isPaid)
        <p style="color:#475569; margin: 14px auto 0; max-width: 520px">
            Recibimos tu pago vía MercadoPago. Cada tienda fue notificada y te contactarán para coordinar entrega y comprobante.
        </p>
    @elseif($isUnpaid && $usingMP)
        <p style="color:#92400e; margin: 14px auto 0; max-width: 520px; font-weight: 500">
            Aún no completaste el pago. Si cerraste la pestaña antes de tiempo, puedes retomar el pago abajo. Las tiendas serán notificadas cuando MercadoPago confirme la transacción.
        </p>
        <div style="margin-top: 18px">
            <a href="{{ route('marketplace.payment.retry', ['number' => $order->order_number]) }}"
               style="display:inline-block;background:#0ea5e9;color:#fff;font-weight:600;padding:12px 24px;border-radius:10px;text-decoration:none;font-size:15px">
                💳 Pagar ahora con MercadoPago
            </a>
        </div>
    @else
        <p style="color:#475569; margin: 14px auto 0; max-width: 520px">
            Cada tienda recibió tu pedido por WhatsApp/email. Te contactarán para confirmar disponibilidad, coordinar el envío y el comprobante.
        </p>
    @endif
</div>

<div class="mp-conf-card">
    <h3 style="margin: 0 0 14px">📦 {{ $itemsByStore->count() }} {{ $itemsByStore->count() === 1 ? 'tienda involucrada' : 'tiendas involucradas' }}</h3>

    @foreach($itemsByStore as $hostnameId => $items)
        @php
            $first = $items->first();
            $sub = $subOrders->get($hostnameId);
            $statusClass = 'status-' . ($sub->status ?? 'pending');
            $statusLabel = match($sub->status ?? 'pending') {
                'dispatched' => '✓ Enviado a la tienda',
                'failed'     => '⚠ Falló — reintentaremos',
                'cancelled'  => '✕ Cancelado',
                'delivered'  => '✓ Entregado',
                default      => '⏳ Pendiente',
            };
            $subtotal = $items->sum('total');
        @endphp
        <div class="mp-conf-store">
            <div class="mp-conf-store-head">
                <div class="name">{{ $first->title ? $first->tenant_fqdn : $first->tenant_fqdn }}</div>
                <span class="mp-conf-status {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            @foreach($items as $item)
                <div class="mp-conf-line">
                    <span><span class="qty">{{ $item->quantity }}×</span>{{ $item->title }}</span>
                    <span>S/ {{ number_format($item->total, 2) }}</span>
                </div>
            @endforeach
            <div class="mp-conf-totals">
                @php
                    $storeDiscount = (float) ($sub?->discount_amount ?? 0);
                @endphp
                <div class="row"><span>Subtotal tienda</span><span>S/ {{ number_format($subtotal, 2) }}</span></div>
                @if($storeDiscount > 0 && !empty($sub?->coupon_code))
                    <div class="row" style="color:#16a34a">
                        <span>Cupón {{ $sub->coupon_code }}</span>
                        <span>-S/ {{ number_format($storeDiscount, 2) }}</span>
                    </div>
                    <div class="row total"><span>Total tienda</span><span>S/ {{ number_format(max(0, $subtotal - $storeDiscount), 2) }}</span></div>
                @else
                    <div class="row total"><span>Total tienda</span><span>S/ {{ number_format($subtotal, 2) }}</span></div>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mp-conf-totals">
        <div class="row"><span>Productos</span><span>{{ $order->items_count }}</span></div>
        <div class="row"><span>Tiendas</span><span>{{ $order->stores_count }}</span></div>
        @if(($order->discount_total ?? 0) > 0)
            <div class="row"><span>Subtotal</span><span>S/ {{ number_format($order->subtotal, 2) }}</span></div>
            <div class="row" style="color:#16a34a">
                <span>Descuento total cupones</span>
                <span>-S/ {{ number_format($order->discount_total, 2) }}</span>
            </div>
        @endif
        <div class="row total"><span>Total general</span><span>S/ {{ number_format($order->total, 2) }}</span></div>
    </div>

    <div class="mp-conf-actions">
        <a href="{{ route('marketplace.index') }}" class="mp-conf-btn mp-conf-btn--primary">
            Seguir comprando →
        </a>
        @if($order->customer_phone)
            <a href="https://wa.me/?text={{ urlencode('Hola, soy ' . $order->customer_name . '. Pedido marketplace ' . $order->order_number) }}" class="mp-conf-btn mp-conf-btn--ghost" target="_blank">
                💬 Compartir nº de pedido por WhatsApp
            </a>
        @endif
    </div>
</div>

<div class="mp-conf-card">
    <h3 style="margin: 0 0 10px">📬 Datos del pedido</h3>
    <p style="margin: 4px 0; font-size: 14px"><strong>Comprador:</strong> {{ $order->customer_name }}</p>
    @if($order->customer_doc_number)
        <p style="margin: 4px 0; font-size: 14px"><strong>{{ $order->customer_doc_type }}:</strong> {{ $order->customer_doc_number }}</p>
    @endif
    <p style="margin: 4px 0; font-size: 14px"><strong>Teléfono:</strong> {{ $order->customer_phone }}</p>
    @if($order->customer_email)
        <p style="margin: 4px 0; font-size: 14px"><strong>Email:</strong> {{ $order->customer_email }}</p>
    @endif
    <p style="margin: 4px 0; font-size: 14px"><strong>Dirección:</strong> {{ $order->delivery_address }}@if($order->delivery_district) — {{ $order->delivery_district }}@endif</p>
    @if($order->delivery_notes)
        <p style="margin: 4px 0; font-size: 14px"><strong>Notas:</strong> {{ $order->delivery_notes }}</p>
    @endif
</div>

@endsection
