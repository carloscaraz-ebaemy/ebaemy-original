@extends('marketplace.layout')

@section('title', 'Mis pedidos')

@section('content')
<div class="mp-orders-wrap">
    <header class="mp-orders-head">
        <a href="{{ route('marketplace.account') }}" class="mp-orders-back">← Mi cuenta</a>
        <h1 class="mp-orders-title">Mis pedidos</h1>
    </header>

    @if($orders->isEmpty())
        <div class="mp-orders-empty">
            <p>Aun no hay pedidos confirmados en tu cuenta.</p>
            <a href="{{ route('marketplace.index') }}" class="mp-orders-cta">Explorar productos</a>
        </div>
    @else
        <div class="mp-orders-list">
            @foreach($orders as $o)
                @php
                    $sub = $o->tenant_fqdn ? strtok($o->tenant_fqdn, '.') : null;
                    $statusLabel = match($o->status) {
                        'confirmed' => 'Confirmado',
                        'completed' => 'Entregado',
                        'cancelled' => 'Cancelado',
                        default      => ucfirst($o->status),
                    };
                    $statusClass = 'is-' . $o->status;
                @endphp
                <article class="mp-order-card">
                    <div class="mp-order-card__head">
                        <div>
                            <p class="mp-order-card__store">
                                @if($sub)
                                    <a href="{{ route('marketplace.tenant', ['subdomain' => $sub]) }}">{{ $sub }}</a>
                                @else
                                    Tienda
                                @endif
                            </p>
                            <p class="mp-order-card__id">Pedido #{{ $o->order_id }}</p>
                        </div>
                        <span class="mp-order-card__status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="mp-order-card__body">
                        <p class="mp-order-card__total">{{ $o->currency }} {{ number_format((float) $o->total, 2) }}</p>
                        <p class="mp-order-card__meta">
                            {{ $o->items_count }} producto{{ $o->items_count === 1 ? '' : 's' }} ·
                            @if($o->confirmed_at)
                                {{ \Carbon\Carbon::parse($o->confirmed_at)->isoFormat('D MMM YYYY') }}
                            @elseif($o->cancelled_at)
                                {{ \Carbon\Carbon::parse($o->cancelled_at)->isoFormat('D MMM YYYY') }}
                            @endif
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

<style>
.mp-orders-wrap { max-width: 760px; margin: 32px auto 64px; padding: 0 16px; }
.mp-orders-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.mp-orders-back { color: #64748b; text-decoration: none; font-size: 13.5px; }
.mp-orders-back:hover { color: #0c6b65; }
.mp-orders-title { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; }

.mp-orders-empty { padding: 40px 24px; text-align: center; background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px; color: #64748b; }
.mp-orders-empty p { margin: 0 0 14px; font-size: 14.5px; }
.mp-orders-cta { display: inline-block; padding: 10px 18px; background: linear-gradient(135deg,#0f8a82,#0a6f68); color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; }

.mp-orders-list { display: flex; flex-direction: column; gap: 12px; }
.mp-order-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 18px; }
.mp-order-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.mp-order-card__store { margin: 0; font-size: 13px; color: #64748b; }
.mp-order-card__store a { color: #0c6b65; text-decoration: none; font-weight: 600; }
.mp-order-card__id { margin: 2px 0 0; font-size: 16px; font-weight: 700; color: #0f172a; }
.mp-order-card__status { padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.mp-order-card__status.is-confirmed { background: #fef3c7; color: #92400e; }
.mp-order-card__status.is-completed { background: #d1fae5; color: #065f46; }
.mp-order-card__status.is-cancelled { background: #fee2e2; color: #991b1b; }
.mp-order-card__body { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.mp-order-card__total { margin: 0; font-size: 17px; font-weight: 700; color: #0f172a; }
.mp-order-card__meta { margin: 0; font-size: 13px; color: #64748b; }
</style>
@endsection
