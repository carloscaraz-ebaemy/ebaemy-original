@extends('ecommerce::layouts.master')

@section('page_title', '¡Pedido confirmado! #' . strtoupper(substr($order->external_id, 0, 8)) . ' — ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', 'Tu pedido ha sido recibido y está siendo procesado con seguridad.')

@php
    $customer    = $order->customer;
    $items       = is_array($order->items) ? $order->items : (array)($order->items ?? []);
    $name        = optional($customer)->apellidos_y_nombres_o_razon_social
                   ?? optional($customer)->name ?? 'Cliente';
    $firstName   = explode(' ', trim($name))[0];
    $email       = optional($customer)->correo_electronico ?? optional($customer)->email ?? '';
    $phone       = optional($customer)->telefono ?? '';
    $address     = optional($customer)->direccion ?? '';
    $orderNumber = strtoupper(substr($order->external_id, 0, 8));
    $homeUrl     = route('tenant.ecommerce.index');
    $ordersUrl   = route('tenant_order_list');
    $isLoggedIn  = auth('ecommerce')->check();

    // Estado amigable para el cliente
    $statusId   = $order->status_order_id ?? 1;
    $statusMap  = [
        1 => ['label' => 'Pendiente de verificación', 'icon' => '⏳', 'color' => '#f59e0b', 'bg' => '#fffbeb', 'desc' => 'Estamos verificando tu pago. Esto toma entre 1 y 24 horas hábiles.'],
        2 => ['label' => 'Pago verificado', 'icon' => '✅', 'color' => '#10b981', 'bg' => '#ecfdf5', 'desc' => 'Tu pago fue confirmado. Estamos preparando tu pedido.'],
        3 => ['label' => 'En camino', 'icon' => '🚚', 'color' => '#3b82f6', 'bg' => '#eff6ff', 'desc' => 'Tu pedido está en camino. Te llegará pronto.'],
        4 => ['label' => 'Entregado', 'icon' => '📦', 'color' => '#6d28d9', 'bg' => '#f5f3ff', 'desc' => '¡Tu pedido fue entregado! Gracias por tu compra.'],
    ];
    $status     = $statusMap[$statusId] ?? $statusMap[1];

    // Totales del pedido
    $subtotal = collect($items)->sum(function($i) {
        $i = (array)$i;
        return ($i['sale_unit_price'] ?? 0) * ($i['quantity'] ?? $i['cantidad'] ?? 1);
    });
    $igv = $order->total - ($order->total / 1.18);
@endphp

@section('content')
<div class="ecp-wrap">

    {{-- ══════════════════════════════════════════════════════════
         HERO — Encabezado de éxito
    ══════════════════════════════════════════════════════════ --}}
    <div class="ecp-hero">
        <div class="ecp-hero__inner">
            {{-- Checkmark animado --}}
            <div class="ecp-check" aria-hidden="true">
                <svg class="ecp-check__circle" viewBox="0 0 52 52">
                    <circle class="ecp-check__circle-bg" cx="26" cy="26" r="25" fill="none"/>
                    <polyline class="ecp-check__tick" points="14,27 22,35 38,17"/>
                </svg>
            </div>
            <h1 class="ecp-hero__title">¡Hola, {{ $firstName }}! Tu pedido está confirmado</h1>
            <p class="ecp-hero__sub">
                @if($email)
                    Enviamos los detalles de tu pedido a <strong>{{ $email }}</strong>.
                @else
                    Tu pedido ha sido registrado y será procesado pronto.
                @endif
            </p>

            {{-- Trust signals --}}
            <div class="ecp-trust">
                <span class="ecp-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Pago 100% seguro
                </span>
                <span class="ecp-trust__sep">·</span>
                <span class="ecp-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Seguimiento en tiempo real
                </span>
                <span class="ecp-trust__sep">·</span>
                <span class="ecp-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07"/><path d="M2.56 13.22a19.79 19.79 0 0 0 3.07 8.63"/></svg>
                    Soporte disponible
                </span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         META BAR — número de pedido, fecha, estado, total
    ══════════════════════════════════════════════════════════ --}}
    <div class="ecp-meta">
        <div class="ecp-meta__item">
            <span class="ecp-meta__label">N.° de pedido</span>
            <span class="ecp-meta__value ecp-meta__value--mono">{{ $orderNumber }}</span>
        </div>
        <div class="ecp-meta__item">
            <span class="ecp-meta__label">Fecha</span>
            <span class="ecp-meta__value">{{ $order->created_at->format('d/m/Y') }}</span>
            <span class="ecp-meta__sub">{{ $order->created_at->format('H:i') }}</span>
        </div>
        <div class="ecp-meta__item">
            <span class="ecp-meta__label">Estado</span>
            <span class="ecp-meta__value ecp-meta__status" style="color:{{ $status['color'] }};background:{{ $status['bg'] }}">
                {{ $status['icon'] }} {{ $status['label'] }}
            </span>
        </div>
        <div class="ecp-meta__item">
            <span class="ecp-meta__label">Total</span>
            <span class="ecp-meta__value ecp-meta__value--total">S/ {{ number_format($order->total, 2) }}</span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         TIMELINE DE ESTADO
    ══════════════════════════════════════════════════════════ --}}
    <div class="ecp-timeline">
        @php
            $steps = [
                ['id' => 1, 'label' => 'Pedido recibido', 'icon' => 'cart'],
                ['id' => 2, 'label' => 'Pago verificado', 'icon' => 'check'],
                ['id' => 3, 'label' => 'En camino', 'icon' => 'truck'],
                ['id' => 4, 'label' => 'Entregado', 'icon' => 'package'],
            ];
        @endphp
        @foreach($steps as $i => $step)
            <div class="ecp-step {{ $statusId >= $step['id'] ? 'ecp-step--done' : '' }} {{ $statusId == $step['id'] ? 'ecp-step--active' : '' }}">
                <div class="ecp-step__node">
                    @if($step['icon'] === 'cart')
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    @elseif($step['icon'] === 'check')
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    @elseif($step['icon'] === 'truck')
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    @endif
                </div>
                <span class="ecp-step__label">{{ $step['label'] }}</span>
            </div>
            @if($i < count($steps) - 1)
                <div class="ecp-step__line {{ $statusId > $step['id'] ? 'ecp-step__line--done' : '' }}"></div>
            @endif
        @endforeach
    </div>

    {{-- Estado explicado --}}
    <div class="ecp-status-explain" style="border-left-color:{{ $status['color'] }};background:{{ $status['bg'] }}">
        <strong style="color:{{ $status['color'] }}">{{ $status['icon'] }} {{ $status['label'] }}</strong>
        — {{ $status['desc'] }}
    </div>

    {{-- ══════════════════════════════════════════════════════════
         BODY — 2 columnas
    ══════════════════════════════════════════════════════════ --}}
    <div class="row ecp-body">

        {{-- ── Columna izquierda: productos ──────────────────── --}}
        <div class="col-lg-7">
            <div class="ecp-card">
                <h2 class="ecp-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Resumen del pedido
                </h2>

                <div class="ecp-items">
                    @forelse($items as $item)
                    @php
                        $item     = (array)$item;
                        $imgFile  = $item['image'] ?? '';
                        $imgUrl   = ($imgFile && $imgFile !== 'imagen-no-disponible.jpg')
                                    ? asset('storage/uploads/items/' . $imgFile)
                                    : asset('logo/imagen-no-disponible.jpg');
                        $qty      = (int)($item['quantity'] ?? $item['cantidad'] ?? 1);
                        $price    = (float)($item['sale_unit_price'] ?? 0);
                        $lineTotal = $price * $qty;

                        // Variantes
                        $variantLabel = $item['variant_label'] ?? $item['variant_display_name'] ?? null;
                        if (!$variantLabel && !empty($item['variant_id'])) {
                            $v = \App\Models\Tenant\ItemVariant::find($item['variant_id']);
                            $variantLabel = $v ? $v->display_name : null;
                        }
                    @endphp
                    <div class="ecp-item">
                        <div class="ecp-item__img-wrap">
                            <img src="{{ $imgUrl }}" alt="{{ $item['description'] ?? 'Producto' }}" class="ecp-item__img"
                                 onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                            <span class="ecp-item__qty-badge">{{ $qty }}</span>
                        </div>
                        <div class="ecp-item__info">
                            <p class="ecp-item__name">{{ $item['description'] ?? 'Producto' }}</p>
                            @if($variantLabel)
                                <p class="ecp-item__variant">{{ $variantLabel }}</p>
                            @endif
                            <p class="ecp-item__unit-price">S/ {{ number_format($price, 2) }} c/u</p>
                        </div>
                        <p class="ecp-item__total">S/ {{ number_format($lineTotal, 2) }}</p>
                    </div>
                    @empty
                        <p style="font-size:1.3rem;color:#999;text-align:center;padding:1rem">Sin detalle disponible.</p>
                    @endforelse
                </div>

                {{-- Totales --}}
                <div class="ecp-totals">
                    <div class="ecp-totals__row">
                        <span>Subtotal</span>
                        <span>S/ {{ number_format($subtotal, 2) }}</span>
                    </div>
                    @if($order->points_redeemed > 0)
                    <div class="ecp-totals__row ecp-totals__row--discount">
                        <span>🎁 Puntos canjeados</span>
                        <span>− S/ {{ number_format($order->points_redeemed, 2) }}</span>
                    </div>
                    @endif
                    <div class="ecp-totals__row ecp-totals__row--total">
                        <span>Total</span>
                        <span>S/ {{ number_format($order->total, 2) }}</span>
                    </div>
                </div>

                @if($order->points_earned > 0)
                <div class="ecp-points-earned">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Ganaste <strong>{{ number_format($order->points_earned, 0) }} puntos</strong> con esta compra
                </div>
                @endif
            </div>
        </div>

        {{-- ── Columna derecha ────────────────────────────────── --}}
        <div class="col-lg-5 mt-3 mt-lg-0">

            {{-- Datos de entrega --}}
            <div class="ecp-card">
                <h2 class="ecp-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Datos de entrega
                </h2>
                <dl class="ecp-dl">
                    <div class="ecp-dl__row">
                        <dt>Nombre</dt>
                        <dd>{{ $name }}</dd>
                    </div>
                    @if($email)
                    <div class="ecp-dl__row">
                        <dt>Email</dt>
                        <dd>{{ $email }}</dd>
                    </div>
                    @endif
                    @if($phone)
                    <div class="ecp-dl__row">
                        <dt>Teléfono</dt>
                        <dd>{{ $phone }}</dd>
                    </div>
                    @endif
                    @if($address)
                    <div class="ecp-dl__row">
                        <dt>Dirección</dt>
                        <dd>{{ $address }}</dd>
                    </div>
                    @endif
                    <div class="ecp-dl__row">
                        <dt>Método de pago</dt>
                        <dd>{{ strtoupper($order->reference_payment ?? 'Efectivo') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Próximos pasos --}}
            <div class="ecp-card ecp-card--steps mt-3">
                <h2 class="ecp-card__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    ¿Qué pasa ahora?
                </h2>
                <ol class="ecp-steps">
                    <li>
                        <span class="ecp-steps__num">1</span>
                        <span>
                            <strong>Confirmación por email</strong><br>
                            <small>Revisa tu bandeja de entrada — puede tardar unos minutos.</small>
                        </span>
                    </li>
                    <li>
                        <span class="ecp-steps__num">2</span>
                        <span>
                            <strong>Verificación del pago</strong><br>
                            <small>Nuestro equipo confirma tu pago en 1–24 h hábiles.</small>
                        </span>
                    </li>
                    <li>
                        <span class="ecp-steps__num">3</span>
                        <span>
                            <strong>Preparación y despacho</strong><br>
                            <small>Tu pedido es empacado y enviado a tu dirección.</small>
                        </span>
                    </li>
                    <li>
                        <span class="ecp-steps__num">4</span>
                        <span>
                            <strong>Te contactamos</strong><br>
                            <small>Te avisamos por WhatsApp o email cuando salga tu pedido.</small>
                        </span>
                    </li>
                </ol>
            </div>

            {{-- Soporte rápido --}}
            @php $ecCfg = \App\Models\Tenant\ConfigurationEcommerce::first(); @endphp
            @if($ecCfg && $ecCfg->phone_whatsapp)
            <div class="ecp-support mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/></svg>
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $ecCfg->phone_whatsapp) }}?text={{ urlencode('Hola, tengo una consulta sobre mi pedido #'.$orderNumber) }}"
                   target="_blank" rel="noopener">
                    ¿Tienes dudas? Escríbenos por WhatsApp
                </a>
            </div>
            @endif

        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         ACCIONES
    ══════════════════════════════════════════════════════════ --}}
    <div class="ecp-actions">
        <a href="{{ $homeUrl }}" class="ecp-btn ecp-btn--primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Seguir comprando
        </a>
        @if($isLoggedIn)
        <a href="{{ $ordersUrl }}" class="ecp-btn ecp-btn--outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Ver mis pedidos
        </a>
        @else
        <a href="{{ route('tenant_ecommerce_login') }}" class="ecp-btn ecp-btn--outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Crea tu cuenta para rastrear
        </a>
        @endif
    </div>

</div>

<style>
/* ── Layout ─────────────────────────────────────────────── */
.ecp-wrap {
    max-width: 960px;
    padding: 8rem 16px 4rem;
    margin: 0 auto;
}

/* ── Hero ────────────────────────────────────────────────── */
.ecp-hero { text-align: center; padding: 3rem 0 2rem; }
.ecp-hero__inner { max-width: 600px; margin: 0 auto; }
.ecp-hero__title { font-size: 2.6rem; font-weight: 900; color: #111; margin: 20px 0 10px; line-height: 1.2; }
.ecp-hero__sub   { font-size: 1.45rem; color: #666; margin: 0 0 20px; }

/* Checkmark SVG animation */
.ecp-check {
    display: inline-flex;
    margin-bottom: 8px;
}
.ecp-check__circle {
    width: 72px; height: 72px;
    animation: ecp-pop .4s cubic-bezier(.175,.885,.32,1.275) forwards;
}
.ecp-check__circle-bg {
    stroke: #10b981;
    stroke-width: 2;
    fill: #ecfdf5;
}
.ecp-check__tick {
    fill: none;
    stroke: #10b981;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 30;
    stroke-dashoffset: 30;
    animation: ecp-tick .4s .35s ease forwards;
}
@keyframes ecp-pop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}
@keyframes ecp-tick {
    to { stroke-dashoffset: 0; }
}

/* Trust signals */
.ecp-trust {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
    gap: 6px 14px; font-size: 1.2rem; color: #888;
}
.ecp-trust__item { display: inline-flex; align-items: center; gap: 5px; }
.ecp-trust__sep  { color: #ddd; }

/* ── Meta bar ────────────────────────────────────────────── */
.ecp-meta {
    display: flex; flex-wrap: wrap; gap: 0;
    background: #fff; border: 1px solid #e9ecef; border-radius: 14px;
    overflow: hidden; margin: 2rem 0 1.5rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.ecp-meta__item {
    flex: 1; min-width: 130px;
    padding: 18px 20px; border-right: 1px solid #f0f0f0;
    display: flex; flex-direction: column; gap: 4px;
}
.ecp-meta__item:last-child { border-right: none; }
.ecp-meta__label { font-size: 1.05rem; color: #aaa; text-transform: uppercase; letter-spacing: .5px; }
.ecp-meta__value { font-size: 1.5rem; font-weight: 700; color: #222; }
.ecp-meta__value--mono { font-family: 'Courier New', monospace; letter-spacing: .05em; }
.ecp-meta__value--total { color: #059669; font-size: 1.8rem; }
.ecp-meta__sub  { font-size: 1.1rem; color: #bbb; }
.ecp-meta__status {
    display: inline-block; padding: 4px 12px; border-radius: 20px;
    font-size: 1.25rem; font-weight: 700; width: fit-content;
}

/* ── Timeline ────────────────────────────────────────────── */
.ecp-timeline {
    display: flex; align-items: center; justify-content: center;
    gap: 0; margin: 1.5rem 0 .8rem; flex-wrap: wrap;
}
.ecp-step {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    flex: 0 0 auto;
}
.ecp-step__node {
    width: 38px; height: 38px; border-radius: 50%;
    border: 2px solid #e5e7eb;
    background: #fff; color: #d1d5db;
    display: flex; align-items: center; justify-content: center;
    transition: all .3s;
}
.ecp-step--done .ecp-step__node  { border-color: #10b981; background: #10b981; color: #fff; }
.ecp-step--active .ecp-step__node { border-color: #f59e0b; background: #fffbeb; color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.15); }
.ecp-step__label { font-size: 1.1rem; color: #9ca3af; white-space: nowrap; font-weight: 600; }
.ecp-step--done .ecp-step__label, .ecp-step--active .ecp-step__label { color: #374151; }
.ecp-step__line {
    flex: 1; height: 2px; background: #e5e7eb;
    min-width: 30px; max-width: 80px; margin: 0 4px;
    margin-bottom: 20px;
}
.ecp-step__line--done { background: #10b981; }

/* Status explanation */
.ecp-status-explain {
    border-left: 3px solid;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 1.3rem;
    color: #374151;
    margin-bottom: 2rem;
    line-height: 1.5;
}

/* ── Cards ───────────────────────────────────────────────── */
.ecp-card {
    background: #fff; border: 1px solid #e9ecef; border-radius: 14px;
    padding: 22px 24px; box-shadow: 0 1px 6px rgba(0,0,0,.05);
}
.ecp-card--steps { background: #f9fafb; }
.ecp-card__title {
    font-size: 1.5rem; font-weight: 700; color: #222;
    display: flex; align-items: center; gap: 8px;
    margin: 0 0 16px; padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

/* ── Items ───────────────────────────────────────────────── */
.ecp-items { display: flex; flex-direction: column; gap: 16px; }
.ecp-item {
    display: flex; align-items: center; gap: 14px;
    padding-bottom: 14px; border-bottom: 1px solid #f5f5f5;
}
.ecp-item:last-child { border-bottom: none; padding-bottom: 0; }
.ecp-item__img-wrap { position: relative; flex-shrink: 0; }
.ecp-item__img {
    width: 64px; height: 64px; object-fit: contain;
    border-radius: 10px; border: 1px solid #eee; background: #fafafa;
    display: block;
}
.ecp-item__qty-badge {
    position: absolute; top: -6px; right: -8px;
    background: #374151; color: #fff; border-radius: 50%;
    width: 20px; height: 20px; font-size: 1.05rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff;
}
.ecp-item__info { flex: 1; min-width: 0; }
.ecp-item__name {
    font-size: 1.35rem; font-weight: 600; color: #222;
    margin: 0 0 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ecp-item__variant {
    display: inline-flex; align-items: center; gap: 4px;
    background: #f0f4ff; color: #3b5bdb;
    border-radius: 4px; padding: 2px 8px;
    font-size: 1.1rem; font-weight: 600; margin: 0 0 3px;
}
.ecp-item__unit-price { font-size: 1.15rem; color: #9ca3af; margin: 0; }
.ecp-item__total { font-size: 1.45rem; font-weight: 700; color: #111; white-space: nowrap; }

/* ── Totals ──────────────────────────────────────────────── */
.ecp-totals {
    margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f0;
    display: flex; flex-direction: column; gap: 6px;
}
.ecp-totals__row { display: flex; justify-content: space-between; font-size: 1.3rem; color: #666; }
.ecp-totals__row--discount { color: #059669; }
.ecp-totals__row--total { font-size: 1.7rem; font-weight: 800; color: #111; padding-top: 8px; border-top: 2px solid #f0f0f0; margin-top: 4px; }

/* Points earned */
.ecp-points-earned {
    display: flex; align-items: center; gap: 6px;
    margin-top: 14px; padding: 10px 14px;
    background: #fefce8; border: 1px solid #fde68a; border-radius: 8px;
    font-size: 1.25rem; color: #92400e;
}

/* ── DL ──────────────────────────────────────────────────── */
.ecp-dl { margin: 0; display: flex; flex-direction: column; gap: 10px; }
.ecp-dl__row { display: flex; flex-direction: column; gap: 2px; }
.ecp-dl__row dt { font-size: 1.05rem; color: #aaa; text-transform: uppercase; letter-spacing: .04em; }
.ecp-dl__row dd { font-size: 1.35rem; color: #222; font-weight: 600; margin: 0; word-break: break-word; }

/* ── Steps ───────────────────────────────────────────────── */
.ecp-steps { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 14px; }
.ecp-steps li { display: flex; align-items: flex-start; gap: 12px; font-size: 1.3rem; color: #555; }
.ecp-steps strong { display: block; color: #222; font-size: 1.35rem; }
.ecp-steps small  { font-size: 1.15rem; color: #888; }
.ecp-steps__num {
    width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0;
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff; font-size: 1.1rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin-top: 2px;
}

/* ── Support ─────────────────────────────────────────────── */
.ecp-support {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
    padding: 12px 16px; display: flex; align-items: center; gap: 10px;
    font-size: 1.3rem;
}
.ecp-support a { color: #15803d; font-weight: 600; text-decoration: none; }
.ecp-support a:hover { text-decoration: underline; }

/* ── Actions ─────────────────────────────────────────────── */
.ecp-actions {
    display: flex; gap: 12px; flex-wrap: wrap;
    justify-content: center; margin: 3rem 0 2rem;
}
.ecp-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 14px 28px; border-radius: 10px;
    font-size: 1.5rem; font-weight: 700; text-decoration: none;
    transition: opacity .18s, transform .18s;
}
.ecp-btn:hover { opacity: .88; transform: translateY(-2px); text-decoration: none; }
.ecp-btn--primary {
    background: hsl(var(--primary-h),var(--primary-s),var(--primary-l));
    color: #fff;
}
.ecp-btn--outline {
    background: #fff; color: #374151;
    border: 2px solid #e5e7eb;
}
.ecp-btn--outline:hover { border-color: #9ca3af; color: #111; }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 575px) {
    .ecp-hero__title { font-size: 2rem; }
    .ecp-meta__item  { min-width: calc(50% - 1px); }
    .ecp-timeline    { gap: 2px; }
    .ecp-step__label { font-size: .95rem; }
    .ecp-step__line  { min-width: 16px; }
}
/* ── Payment capture polling banner ─────────────────────── */
#ecp-capture-banner {
    display: flex; align-items: center; gap: 10px;
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
    padding: 12px 18px; margin: 1rem 0; font-size: 1.3rem; color: #92400e;
}
#ecp-capture-banner .spinner {
    width: 18px; height: 18px; border: 3px solid #fcd34d;
    border-top-color: #f59e0b; border-radius: 50%;
    animation: spin .8s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endsection

@push('scripts')
<script>
// ── Tracking: Purchase ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (window.EcommerceTracker) {
        try {
            var rawItems = {!! \Illuminate\Support\Js::from($items) !!};
            var items = rawItems.map(function (i) {
                return {
                    id:       String(i.item_id || i.id || ''),
                    name:     String(i.description || i.name || ''),
                    price:    parseFloat(i.sale_unit_price || 0),
                    quantity: parseInt(i.quantity || i.cantidad || 1)
                };
            });
            EcommerceTracker.purchase({
                orderId:  '{{ $order->external_id }}',
                items:    items,
                total:    {{ $order->total }},
                currency: 'PEN'
            });
        } catch (e) { /* silencioso */ }
    }
});
</script>
@endpush

@if(($order->payment_status ?? null) === 'pending_capture')
@push('scripts')
<script>
(function () {
    var externalId  = @json($order->external_id);
    var statusUrl   = '/ecommerce/order/' + externalId + '/payment-status';
    var maxAttempts = 40;   // 40 × 3 s ≈ 2 min
    var attempts    = 0;
    var intervalId  = null;

    // Insert banner below status explain block
    var explainEl = document.querySelector('.ecp-status-explain');
    if (explainEl) {
        var banner = document.createElement('div');
        banner.id = 'ecp-capture-banner';
        banner.innerHTML = '<div class="spinner"></div><span>Verificando tu pago… puede tomar unos segundos.</span>';
        explainEl.parentNode.insertBefore(banner, explainEl.nextSibling);
    }

    function updateUI(icon, label, color, bg, desc) {
        var statusEl  = document.querySelector('.ecp-meta__status');
        var explainEl = document.querySelector('.ecp-status-explain');
        var banner    = document.getElementById('ecp-capture-banner');

        if (statusEl)  { statusEl.textContent = icon + ' ' + label; statusEl.style.color = color; statusEl.style.background = bg; }
        if (explainEl) { explainEl.querySelector('strong').textContent = icon + ' ' + label; explainEl.style.borderLeftColor = color; explainEl.style.background = bg; explainEl.style.color = color; }
        if (banner)    { banner.remove(); }

        // Mark step 2 (Pago verificado) as done in timeline
        if (color === '#10b981') {
            var steps = document.querySelectorAll('.ecp-step');
            if (steps[1]) {
                steps[1].classList.add('ecp-step--done');
                steps[1].classList.remove('ecp-step--active');
                var line = steps[1].nextElementSibling;
                if (line && line.classList.contains('ecp-step__line')) {
                    line.classList.add('ecp-step__line--done');
                }
            }
        }
    }

    function poll() {
        attempts++;
        if (attempts > maxAttempts) {
            var banner = document.getElementById('ecp-capture-banner');
            if (banner) banner.innerHTML = '<span>⏳ Tu pago sigue siendo procesado. Revisa tu correo para la confirmación.</span>';
            return;
        }

        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.payment_status === 'captured') {
                    updateUI('✅', 'Pago verificado', '#10b981', '#ecfdf5', 'Tu pago fue confirmado. Estamos preparando tu pedido.');
                } else if (data.payment_status === 'capture_failed') {
                    updateUI('❌', 'Error en el pago', '#ef4444', '#fef2f2', 'Hubo un problema al procesar tu pago. Contáctanos.');
                } else {
                    // still pending_capture
                    intervalId = setTimeout(poll, 3000);
                }
            })
            .catch(function () {
                intervalId = setTimeout(poll, 5000);
            });
    }

    // Start after 2 s to give the job time to process
    intervalId = setTimeout(poll, 2000);
})();
</script>
@endpush
@endif
