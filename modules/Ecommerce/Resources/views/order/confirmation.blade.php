@extends('ecommerce::layouts.master')

@section('page_title', 'Pedido confirmado — ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', 'Tu pedido ha sido recibido y está siendo procesado.')

@php
    $customer    = $order->customer;
    $items       = $order->items ?? [];
    $name        = $customer->apellidos_y_nombres_o_razon_social
                   ?? $customer->name
                   ?? 'Cliente';
    $email       = $customer->correo_electronico ?? $customer->email ?? '';
    $phone       = $customer->telefono ?? '';
    $address     = $customer->direccion ?? '';
    $statusLabel = $order->status_order->description ?? 'Pendiente';
    $homeUrl     = route('tenant.ecommerce.index');
    $ordersUrl   = route('tenant_order_list');
@endphp

@section('content')
<div class="container ec-confirmation-wrap">

    {{-- ── Encabezado de éxito ──────────────────────────── --}}
    <div class="ec-confirmation-hero">
        <div class="ec-confirmation-check" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <h1 class="ec-confirmation-title">¡Pedido recibido!</h1>
        <p class="ec-confirmation-sub">
            Gracias, <strong>{{ $name }}</strong>. Tu pedido ha sido registrado correctamente.
            @if($email)
                Te enviamos los detalles a <strong>{{ $email }}</strong>.
            @endif
        </p>
    </div>

    {{-- ── Número de pedido y estado ───────────────────── --}}
    <div class="ec-confirmation-meta">
        <div class="ec-confirmation-meta__item">
            <span class="ec-confirmation-meta__label">N.° de pedido</span>
            <span class="ec-confirmation-meta__value">{{ strtoupper(substr($order->external_id, 0, 8)) }}</span>
        </div>
        <div class="ec-confirmation-meta__item">
            <span class="ec-confirmation-meta__label">Fecha</span>
            <span class="ec-confirmation-meta__value">{{ $order->created_at->format('d/m/Y H:i') }}</span>
        </div>
        <div class="ec-confirmation-meta__item">
            <span class="ec-confirmation-meta__label">Estado</span>
            <span class="ec-confirmation-meta__value ec-confirmation-status">{{ $statusLabel }}</span>
        </div>
        <div class="ec-confirmation-meta__item">
            <span class="ec-confirmation-meta__label">Total</span>
            <span class="ec-confirmation-meta__value ec-confirmation-total">
                S/ {{ number_format($order->total, 2) }}
            </span>
        </div>
    </div>

    <div class="row ec-confirmation-body">

        {{-- ── Detalle de productos ─────────────────────── --}}
        <div class="col-lg-7">
            <div class="ec-confirmation-card">
                <h2 class="ec-confirmation-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Productos pedidos
                </h2>

                <div class="ec-confirmation-items">
                    @forelse($items as $item)
                    @php
                        $itemImg = !empty($item->image) && $item->image !== 'imagen-no-disponible.jpg'
                            ? asset('storage/uploads/items/' . $item->image)
                            : asset('logo/imagen-no-disponible.jpg');
                        $itemQty   = $item->quantity ?? $item->cantidad ?? 1;
                        $itemPrice = $item->sale_unit_price ?? 0;
                        $itemTotal = $itemPrice * $itemQty;
                    @endphp
                    <div class="ec-confirm-item">
                        <img src="{{ $itemImg }}"
                             alt="{{ $item->description ?? 'Producto' }}"
                             class="ec-confirm-item__img"
                             onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                        <div class="ec-confirm-item__info">
                            <p class="ec-confirm-item__name">{{ $item->description ?? 'Producto' }}</p>
                            <p class="ec-confirm-item__qty">Cantidad: {{ $itemQty }}</p>
                        </div>
                        <p class="ec-confirm-item__price">S/ {{ number_format($itemTotal, 2) }}</p>
                    </div>
                    @empty
                    <p class="text-muted" style="font-size:1.3rem">No hay detalle de productos disponible.</p>
                    @endforelse
                </div>

                <div class="ec-confirmation-totals">
                    <div class="ec-confirmation-totals__row">
                        <span>Subtotal</span>
                        <span>S/ {{ number_format($order->total, 2) }}</span>
                    </div>
                    <div class="ec-confirmation-totals__row ec-confirmation-totals__row--total">
                        <span>Total</span>
                        <span>S/ {{ number_format($order->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Datos del cliente ────────────────────────── --}}
        <div class="col-lg-5 mt-3 mt-lg-0">
            <div class="ec-confirmation-card">
                <h2 class="ec-confirmation-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Datos de entrega
                </h2>
                <dl class="ec-confirm-dl">
                    <dt>Nombre</dt>
                    <dd>{{ $name }}</dd>
                    @if($email)
                    <dt>Email</dt>
                    <dd>{{ $email }}</dd>
                    @endif
                    @if($phone)
                    <dt>Teléfono</dt>
                    <dd>{{ $phone }}</dd>
                    @endif
                    @if($address)
                    <dt>Dirección</dt>
                    <dd>{{ $address }}</dd>
                    @endif
                </dl>
            </div>

            {{-- Próximos pasos --}}
            <div class="ec-confirmation-card ec-confirmation-card--steps mt-3">
                <h2 class="ec-confirmation-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    ¿Qué sigue?
                </h2>
                <ol class="ec-confirm-steps">
                    <li>Recibirás un correo de confirmación con los detalles.</li>
                    <li>Nuestro equipo procesará tu pedido.</li>
                    <li>Te contactaremos para coordinar la entrega.</li>
                </ol>
            </div>
        </div>

    </div>

    {{-- ── Acciones ─────────────────────────────────────── --}}
    <div class="ec-confirmation-actions">
        @if(auth('ecommerce')->check())
        <a href="{{ $ordersUrl }}"
           class="ec-confirmation-btn ec-confirmation-btn--secondary"
           style="background:#e8f5e9; color:#2e7d32;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <rect x="1" y="3" width="15" height="13"/>
                <path d="M16 8h4l3 3v5h-7V8z"/>
                <circle cx="5.5" cy="18.5" r="2.5"/>
                <circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
            Rastrear pedido
        </a>
        @endif
        <a href="{{ $homeUrl }}" class="ec-confirmation-btn ec-confirmation-btn--primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Seguir comprando
        </a>
        @if(auth('ecommerce')->check())
        <a href="{{ $ordersUrl }}" class="ec-confirmation-btn ec-confirmation-btn--secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            Ver mis pedidos
        </a>
        @endif
    </div>

</div>

<style>
.ec-confirmation-wrap {
    padding: 8rem 15px 4rem;
    max-width: 960px;
}
.ec-confirmation-hero {
    text-align: center;
    padding: 3rem 0 2rem;
}
.ec-confirmation-check {
    width: 80px; height: 80px;
    background: hsl(var(--primary-h),var(--primary-s),92%);
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    animation: ec-pop .4s cubic-bezier(.175,.885,.32,1.275);
}
@keyframes ec-pop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}
.ec-confirmation-title {
    font-size: 2.8rem;
    font-weight: 800;
    color: #222;
    margin: 0 0 10px;
}
.ec-confirmation-sub {
    font-size: 1.5rem;
    color: #666;
    margin: 0;
}
/* Meta bar */
.ec-confirmation-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    margin: 2rem 0;
}
.ec-confirmation-meta__item {
    flex: 1;
    min-width: 140px;
    padding: 16px 20px;
    border-right: 1px solid #e9ecef;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ec-confirmation-meta__item:last-child { border-right: none; }
.ec-confirmation-meta__label { font-size: 1.1rem; color: #999; text-transform: uppercase; letter-spacing: .5px; }
.ec-confirmation-meta__value { font-size: 1.5rem; font-weight: 700; color: #333; }
.ec-confirmation-status {
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
}
.ec-confirmation-total {
    color: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
}
/* Cards */
.ec-confirmation-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px 24px;
}
.ec-confirmation-card--steps { background: #f8f9fa; }
.ec-confirmation-card__title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
}
/* Items */
.ec-confirmation-items { display: flex; flex-direction: column; gap: 14px; }
.ec-confirm-item {
    display: flex;
    align-items: center;
    gap: 14px;
}
.ec-confirm-item__img {
    width: 60px; height: 60px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #eee;
    flex-shrink: 0;
    background: #fafafa;
}
.ec-confirm-item__info { flex: 1; min-width: 0; }
.ec-confirm-item__name {
    font-size: 1.3rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ec-confirm-item__qty { font-size: 1.2rem; color: #999; margin: 0; }
.ec-confirm-item__price { font-size: 1.4rem; font-weight: 700; color: #333; white-space: nowrap; }
/* Totals */
.ec-confirmation-totals {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #e9ecef;
}
.ec-confirmation-totals__row {
    display: flex;
    justify-content: space-between;
    font-size: 1.3rem;
    color: #666;
    margin-bottom: 6px;
}
.ec-confirmation-totals__row--total {
    font-size: 1.6rem;
    font-weight: 800;
    color: #222;
    margin-bottom: 0;
}
/* Customer data */
.ec-confirm-dl { margin: 0; }
.ec-confirm-dl dt { font-size: 1.1rem; color: #999; margin-bottom: 2px; margin-top: 10px; }
.ec-confirm-dl dt:first-child { margin-top: 0; }
.ec-confirm-dl dd { font-size: 1.35rem; color: #333; font-weight: 600; margin: 0; }
/* Next steps */
.ec-confirm-steps {
    margin: 0;
    padding-left: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ec-confirm-steps li { font-size: 1.3rem; color: #555; }
/* Actions */
.ec-confirmation-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
    margin: 3rem 0 2rem;
}
.ec-confirmation-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 28px;
    border-radius: 10px;
    font-size: 1.5rem;
    font-weight: 700;
    text-decoration: none;
    transition: opacity .18s, transform .18s;
}
.ec-confirmation-btn:hover { opacity: .85; transform: translateY(-2px); text-decoration: none; }
.ec-confirmation-btn--primary {
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
}
.ec-confirmation-btn--secondary {
    background: #f1f3f5;
    color: #444;
}
@media (max-width: 575px) {
    .ec-confirmation-meta__item { min-width: calc(50% - 1px); }
    .ec-confirmation-title { font-size: 2.2rem; }
}
</style>
@endsection
