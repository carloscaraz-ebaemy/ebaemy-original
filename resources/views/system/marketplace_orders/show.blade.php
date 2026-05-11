@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('system.marketplace_orders.index') }}" class="text-decoration-none small text-muted">← Pedidos multi-tienda</a>
            <h3 class="mb-0 mt-1">Pedido <code>{{ $order->order_number }}</code></h3>
        </div>
        <div>
            @php
                $statusBadge = [
                    'pending' => 'bg-warning text-dark',
                    'partially_confirmed' => 'bg-info text-dark',
                    'confirmed' => 'bg-success',
                    'partially_cancelled' => 'bg-warning text-dark',
                    'cancelled' => 'bg-danger',
                    'completed' => 'bg-success',
                ][$order->status] ?? 'bg-secondary';
                $statusLabel = [
                    'pending' => 'Pendiente',
                    'partially_confirmed' => 'Parcialmente confirmado',
                    'confirmed' => 'Confirmado',
                    'partially_cancelled' => 'Parcialmente cancelado',
                    'cancelled' => 'Cancelado',
                    'completed' => 'Completado',
                ][$order->status] ?? $order->status;
            @endphp
            <span class="badge {{ $statusBadge }} fs-6">{{ $statusLabel }}</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            @foreach($order->tenantOrders as $sub)
                @php
                    $items = $itemsByStore->get($sub->hostname_id, collect());
                    $subBadge = [
                        'pending' => 'bg-warning text-dark',
                        'dispatched' => 'bg-success',
                        'failed' => 'bg-danger',
                        'cancelled' => 'bg-secondary',
                        'delivered' => 'bg-info text-dark',
                    ][$sub->status] ?? 'bg-secondary';
                    $subLabel = [
                        'pending' => '⏳ Pendiente',
                        'dispatched' => '✓ Despachado al tenant',
                        'failed' => '⚠ Falló',
                        'cancelled' => '✕ Cancelado',
                        'delivered' => '🚚 Entregado',
                    ][$sub->status] ?? $sub->status;
                @endphp
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $sub->client?->name ?? $sub->tenant_fqdn }}</strong>
                            <small class="text-muted ms-2">{{ $sub->tenant_fqdn }}</small>
                        </div>
                        <span class="badge {{ $subBadge }}">{{ $subLabel }}</span>
                    </div>
                    <div class="card-body py-2">
                        @if($sub->sync_error)
                            <div class="alert alert-danger py-2 mb-2">
                                <strong>Error de dispatch:</strong> {{ $sub->sync_error }}
                                <small class="d-block text-muted mt-1">Reintentos: {{ $sub->retry_count }}</small>
                            </div>
                        @endif

                        <table class="table table-sm mb-2">
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td style="width: 50px">
                                            @if($item->image_url)
                                                <img src="{{ $item->image_url }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">
                                            @endif
                                        </td>
                                        <td>{{ $item->title }}</td>
                                        <td class="text-center" style="width: 80px">{{ $item->quantity }}×</td>
                                        <td class="text-end" style="width: 120px">S/ {{ number_format($item->total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end">Subtotal tienda</td>
                                    <td class="text-end">S/ {{ number_format($sub->subtotal, 2) }}</td>
                                </tr>
                                @if(($sub->discount_amount ?? 0) > 0)
                                    <tr class="table-light" style="color:#15803d">
                                        <td colspan="3" class="text-end">Cupón <code>{{ $sub->coupon_code ?? '—' }}</code></td>
                                        <td class="text-end">-S/ {{ number_format($sub->discount_amount, 2) }}</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end fw-bold">Total tienda</td>
                                        <td class="text-end fw-bold">S/ {{ number_format(max(0, $sub->subtotal - $sub->discount_amount), 2) }}</td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>

                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <div>
                                @if($sub->tenant_order_id)
                                    Order tenant: <a href="https://{{ $sub->tenant_fqdn }}/orders" target="_blank">#{{ $sub->tenant_order_id }}</a>
                                    @if($sub->dispatched_at) — despachado {{ $sub->dispatched_at->diffForHumans() }} @endif
                                @else
                                    Sin Order en el tenant todavía
                                @endif
                            </div>
                            <div>
                                @if(in_array($sub->status, ['pending', 'failed']))
                                    <form method="POST" action="{{ route('system.marketplace_orders.sub_retry', ['id' => $order->id, 'subId' => $sub->id]) }}" class="d-inline" onsubmit="return confirm('¿Reintentar el dispatch a esta tienda?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning">↻ Reintentar este subpedido</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="d-flex gap-2">
                @php
                    $hasFailed = $order->tenantOrders->contains(fn ($s) => $s->status === 'failed');
                @endphp
                @if($hasFailed)
                    <form method="POST" action="{{ route('system.marketplace_orders.retry', $order->id) }}" onsubmit="return confirm('¿Reintentar todos los subpedidos fallidos?')">
                        @csrf
                        <button class="btn btn-warning">↻ Reintentar todos los subpedidos fallidos</button>
                    </form>
                @endif
                @if(!in_array($order->status, ['cancelled', 'completed']))
                    <form method="POST" action="{{ route('system.marketplace_orders.cancel', $order->id) }}" onsubmit="return confirm('¿Cancelar el pedido? Los subpedidos ya despachados a tenants NO se cancelan automáticamente.')">
                        @csrf
                        <button class="btn btn-outline-danger">✕ Cancelar pedido</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>👤 Comprador</strong></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $order->customer_name }}</strong></p>
                    @if($order->customer_doc_number)
                        <p class="mb-1 text-muted small">{{ $order->customer_doc_type }} {{ $order->customer_doc_number }}</p>
                    @endif
                    <p class="mb-1">📱 {{ $order->customer_phone }}</p>
                    @if($order->customer_email)
                        <p class="mb-1">✉️ {{ $order->customer_email }}</p>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>📦 Entrega</strong></div>
                <div class="card-body">
                    <p class="mb-1">{{ $order->delivery_address }}</p>
                    @if($order->delivery_district || $order->delivery_province)
                        <p class="mb-1 text-muted small">
                            {{ collect([$order->delivery_district, $order->delivery_province, $order->delivery_department])->filter()->join(' · ') }}
                        </p>
                    @endif
                    @if($order->delivery_notes)
                        <p class="mb-0 small">💬 {{ $order->delivery_notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>💰 Resumen</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Productos</span><span>{{ $order->items_count }}</span></div>
                    <div class="d-flex justify-content-between"><span>Tiendas</span><span>{{ $order->stores_count }}</span></div>
                    @if(($order->discount_total ?? 0) > 0)
                        <div class="d-flex justify-content-between mt-2"><span>Subtotal</span><span>S/ {{ number_format($order->subtotal, 2) }}</span></div>
                        <div class="d-flex justify-content-between" style="color:#15803d">
                            <span>Descuento cupones</span>
                            <span>-S/ {{ number_format($order->discount_total, 2) }}</span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span><span>S/ {{ number_format($order->total, 2) }}</span>
                    </div>
                    <small class="text-muted">Pago: {{ $order->payment_status }}</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>📅 Telemetría</strong></div>
                <div class="card-body small text-muted">
                    <div>Creado: {{ $order->created_at?->format('d/m/Y H:i') }}</div>
                    <div>Actualizado: {{ $order->updated_at?->format('d/m/Y H:i') }}</div>
                    @if($order->source_ip)
                        <div>IP: {{ $order->source_ip }}</div>
                    @endif
                    @if($order->source_ua)
                        <div title="{{ $order->source_ua }}">UA: {{ \Illuminate\Support\Str::limit($order->source_ua, 60) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
