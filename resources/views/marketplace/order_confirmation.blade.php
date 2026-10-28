@extends('marketplace.layout')

@section('title', 'Pedido ' . $order->order_number . ' confirmado — Marketplace ebaemy')
@section('description', 'Tu pedido fue recibido. Cada tienda te contactará para coordinar entrega.')
@section('canonical', route('marketplace.order.confirmation', ['number' => $order->order_number]))
@section('robots', 'noindex, nofollow')

@php
    $isPaid    = $order->payment_status === 'paid';
    $isUnpaid  = $order->payment_status === 'unpaid';
    $usingMP   = $order->payment_provider === 'mercadopago';
    $multi     = $packages->count() > 1;
    $firstName = \Illuminate\Support\Str::of((string) $order->customer_name)->trim()->explode(' ')->first();
    $firstName = $firstName ? mb_convert_case(mb_strtolower($firstName), MB_CASE_TITLE) : null;
    // Envio estimado: solo lo que tiene precio; lo demas se dice «por confirmar».
    $shipKnown   = $packages->sum(fn ($p) => (float) ($p->ship['amount'] ?? 0));
    $shipPending = $packages->contains(fn ($p) => !$p->ship || !empty($p->ship['pending']));
    $place = collect([$order->delivery_district, $order->delivery_province])->filter()->map(fn ($t) => mb_convert_case(mb_strtolower($t), MB_CASE_TITLE))->unique()->implode(', ');
    $statusText = [
        'dispatched' => ['La tienda ya tiene tu pedido', 'is-ok'],
        'delivered'  => ['Entregado', 'is-ok'],
        'failed'     => ['Avisando a la tienda…', 'is-wait'],
        'cancelled'  => ['Cancelado', 'is-bad'],
    ];
@endphp

@push('styles')
<style>
.mp-ok { max-width: 980px; margin: 0 auto 40px; }
.mp-ok-hero { display: flex; gap: 18px; align-items: center; background: linear-gradient(135deg, #ecfdf5, #f0fdfa); border: 1px solid #a7f3d0; border-radius: 18px; padding: 22px 24px; margin-bottom: 18px; }
.mp-ok-hero.is-wait { background: #fff7ed; border-color: #fed7aa; }
.mp-ok-hero__icon { width: 56px; height: 56px; border-radius: 50%; background: #10b981; color: #fff; font-size: 28px; display: grid; place-items: center; flex: none; }
.mp-ok-hero.is-wait .mp-ok-hero__icon { background: #f59e0b; }
.mp-ok-hero h1 { margin: 0 0 4px; font-size: clamp(20px, 2.6vw, 26px); color: var(--mp-ink, #0f172a); }
.mp-ok-hero p { margin: 0; color: #475569; font-size: 14.5px; }
.mp-ok-num { display: inline-flex; align-items: center; gap: 8px; margin-top: 10px; background: #fff; border: 1px solid #d1fae5; border-radius: 999px; padding: 5px 6px 5px 12px; font-size: 13px; color: #334155; }
.mp-ok-num b { font-family: ui-monospace, Consolas, monospace; color: var(--mp-primary-dark, #0c6b65); font-size: 14px; }
.mp-ok-num button { border: 0; background: #f0fdfa; color: var(--mp-primary, #0f8a82); font-weight: 700; font-size: 12px; border-radius: 999px; padding: 4px 10px; cursor: pointer; }
.mp-ok-pay { display: inline-block; margin-top: 12px; background: #0ea5e9; color: #fff; font-weight: 700; padding: 11px 20px; border-radius: 10px; text-decoration: none; }
.mp-ok-pay:hover { color: #fff; background: #0284c7; text-decoration: none; }

.mp-ok-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
@media (max-width: 860px) { .mp-ok-grid { grid-template-columns: 1fr; } .mp-ok-hero { align-items: flex-start; } }
.mp-ok-card { background: #fff; border: 1px solid var(--mp-border, #e5e7eb); border-radius: 16px; padding: 18px 20px; margin-bottom: 16px; }
.mp-ok-card h2 { font-size: 16px; margin: 0 0 12px; color: var(--mp-ink, #0f172a); }

.mp-ok-steps { list-style: none; margin: 0; padding: 0; counter-reset: s; }
.mp-ok-steps li { counter-increment: s; position: relative; padding: 0 0 14px 38px; font-size: 14px; color: #334155; }
.mp-ok-steps li:last-child { padding-bottom: 0; }
.mp-ok-steps li::before { content: counter(s); position: absolute; left: 0; top: -1px; width: 26px; height: 26px; border-radius: 50%; background: #f0fdfa; border: 1.5px solid #5eead4; color: #0f766e; font-weight: 800; font-size: 12.5px; display: grid; place-items: center; }
.mp-ok-steps li:not(:last-child)::after { content: ""; position: absolute; left: 12.5px; top: 27px; bottom: 2px; width: 1.5px; background: #ccfbf1; }
.mp-ok-steps b { color: var(--mp-ink, #0f172a); }

.mp-ok-pk { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; margin-bottom: 14px; }
.mp-ok-pk:last-child { margin-bottom: 0; }
.mp-ok-pk__head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap; }
.mp-ok-pk__logo { width: 34px; height: 34px; border-radius: 9px; border: 1px solid #e5e7eb; background: #fff; object-fit: contain; flex: none; }
.mp-ok-pk__logo--txt { display: grid; place-items: center; font-weight: 800; color: #fff; background: linear-gradient(135deg, #0f8a82, #22c3b6); border: 0; }
.mp-ok-pk__who { flex: 1; min-width: 160px; }
.mp-ok-pk__tag { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #0f766e; }
.mp-ok-pk__name { font-weight: 700; font-size: 14.5px; color: var(--mp-ink, #0f172a); }
.mp-ok-pk__name a { color: inherit; text-decoration: none; }
.mp-ok-st { font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 4px 10px; white-space: nowrap; }
.mp-ok-st.is-ok { background: #dcfce7; color: #166534; }
.mp-ok-st.is-wait { background: #fef3c7; color: #92400e; }
.mp-ok-st.is-bad { background: #fee2e2; color: #991b1b; }
.mp-ok-pk__body { padding: 6px 14px 12px; }
.mp-ok-item { display: flex; gap: 12px; align-items: center; padding: 8px 0; border-bottom: 1px dashed #eef2f7; }
.mp-ok-item img { width: 52px; height: 52px; border-radius: 10px; object-fit: contain; background: #fff; border: 1px solid #eef2f7; flex: none; }
.mp-ok-item__t { flex: 1; font-size: 13.5px; color: #1f2937; line-height: 1.35; min-width: 0; }
.mp-ok-item__t small { display: block; color: #6b7280; }
.mp-ok-item__p { font-size: 13.5px; font-weight: 600; white-space: nowrap; }
.mp-ok-row { display: flex; justify-content: space-between; gap: 12px; font-size: 13.5px; padding: 6px 0 0; color: #374151; }
.mp-ok-row .v { white-space: nowrap; font-weight: 600; }
.mp-ok-row .v.is-free { color: #15803d; }
.mp-ok-row .v.is-pending { color: #b45309; }
.mp-ok-row--total { font-weight: 700; color: var(--mp-ink, #0f172a); font-size: 14.5px; }
.mp-ok-wa { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; background: #16a34a; color: #fff; border-radius: 10px; padding: 10px 14px; font-weight: 700; font-size: 14px; text-decoration: none; }
.mp-ok-wa:hover { background: #15803d; color: #fff; text-decoration: none; }

.mp-ok-sum .mp-ok-row { padding: 5px 0; }
.mp-ok-sum .mp-ok-big { display: flex; justify-content: space-between; align-items: baseline; border-top: 1px solid #e5e7eb; margin-top: 8px; padding-top: 10px; }
.mp-ok-sum .mp-ok-big b { font-size: 22px; color: var(--mp-primary-dark, #0c6b65); }
.mp-ok-note { font-size: 12.5px; color: #6b7280; margin: 8px 0 0; }
.mp-ok-dl { margin: 0; font-size: 13.5px; color: #374151; }
.mp-ok-dl dt { font-size: 12px; color: #6b7280; margin-top: 8px; }
.mp-ok-dl dt:first-child { margin-top: 0; }
.mp-ok-dl dd { margin: 1px 0 0; }
.mp-ok-actions { display: grid; gap: 8px; }
.mp-ok-btn { display: block; text-align: center; border-radius: 10px; padding: 11px 14px; font-weight: 700; font-size: 14px; text-decoration: none; }
.mp-ok-btn--primary { background: var(--mp-primary, #0f8a82); color: #fff; }
.mp-ok-btn--primary:hover { background: var(--mp-primary-dark, #0c6b65); color: #fff; text-decoration: none; }
.mp-ok-btn--ghost { background: #fff; border: 1px solid #e5e7eb; color: var(--mp-ink, #0f172a); }
.mp-ok-btn--ghost:hover { border-color: #94a3b8; color: var(--mp-ink, #0f172a); text-decoration: none; }
</style>
@endpush

@section('content')
<div class="mp-ok">

    <section class="mp-ok-hero {{ $isUnpaid && $usingMP ? 'is-wait' : '' }}">
        <div class="mp-ok-hero__icon" aria-hidden="true">{{ $isUnpaid && $usingMP ? '⏳' : '✓' }}</div>
        <div>
            @if($isPaid)
                <h1>¡Pago confirmado{{ $firstName ? ', ' . $firstName : '' }}!</h1>
                <p>Recibimos tu pago. {{ $multi ? 'Cada tienda ya fue avisada' : 'La tienda ya fue avisada' }} y te escribirá para coordinar la entrega.</p>
            @elseif($isUnpaid && $usingMP)
                <h1>Falta completar el pago</h1>
                <p>Si cerraste la ventana antes de tiempo, puedes retomarlo aquí. Avisaremos a {{ $multi ? 'las tiendas' : 'la tienda' }} cuando Mercado Pago lo confirme.</p>
                <a class="mp-ok-pay" href="{{ route('marketplace.payment.retry', ['number' => $order->order_number]) }}">💳 Pagar ahora con Mercado Pago</a>
            @else
                <h1>¡Listo{{ $firstName ? ', ' . $firstName : '' }}! Recibimos tu pedido</h1>
                <p>
                    @if($multi)Llega en <b>{{ $packages->count() }} paquetes</b>: cada tienda te escribirá por WhatsApp para coordinar el pago y la entrega.
                    @else La tienda te escribirá por WhatsApp para coordinar el pago y la entrega.
                    @endif
                </p>
            @endif
            <div class="mp-ok-num">
                Nº de pedido <b id="mpOrderNum">{{ $order->order_number }}</b>
                <button type="button" onclick="(function(b){var t=document.getElementById('mpOrderNum').textContent;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent='Copiado ✓';setTimeout(function(){b.textContent='Copiar'},1500)}).catch(function(){})})(this)">Copiar</button>
            </div>
        </div>
    </section>

    <div class="mp-ok-grid">
        <div>
            @unless($isUnpaid && $usingMP)
                <section class="mp-ok-card">
                    <h2>Qué pasa ahora</h2>
                    <ol class="mp-ok-steps">
                        <li><b>{{ $multi ? 'Cada tienda te escribe' : 'La tienda te escribe' }}</b> al {{ $order->customer_phone }} para confirmar tu pedido{{ $multi ? '' : '' }}.</li>
                        <li><b>Coordinas el pago</b> {{ $isPaid ? '— ya está pagado' : 'con Yape, Plin, transferencia o contraentrega, según acepte' }}{{ $multi && !$isPaid ? ' cada una' : '' }}.</li>
                        <li><b>Recibes tu {{ $multi ? 'paquete' : 'pedido' }}</b> y tu boleta o factura{{ $multi ? ' de cada tienda' : '' }}.</li>
                    </ol>
                </section>
            @endunless

            <section class="mp-ok-card">
                <h2>{{ $multi ? 'Tus ' . $packages->count() . ' paquetes' : 'Tu pedido' }}</h2>
                @foreach($packages as $pk)
                    @php
                        [$stLabel, $stClass] = $statusText[$pk->status] ?? ['Avisando a la tienda…', 'is-wait'];
                        $ship = $pk->ship;
                        $waText = 'Hola ' . $pk->name . ', hice el pedido ' . $order->order_number . ' en ebaemy'
                                . ($order->customer_name ? ' a nombre de ' . $order->customer_name : '') . '.';
                    @endphp
                    <article class="mp-ok-pk">
                        <header class="mp-ok-pk__head">
                            @if($pk->logo)
                                <img class="mp-ok-pk__logo" src="{{ $pk->logo }}" alt="" width="34" height="34">
                            @else
                                <span class="mp-ok-pk__logo mp-ok-pk__logo--txt" aria-hidden="true">{{ mb_strtoupper(mb_substr($pk->name, 0, 1)) }}</span>
                            @endif
                            <div class="mp-ok-pk__who">
                                @if($multi)<div class="mp-ok-pk__tag">Paquete {{ $loop->iteration }}</div>@endif
                                <div class="mp-ok-pk__name">Lo envía @if($pk->store)<a href="{{ $pk->store }}">{{ $pk->name }}</a>@else{{ $pk->name }}@endif</div>
                            </div>
                            <span class="mp-ok-st {{ $stClass }}">{{ $stLabel }}</span>
                        </header>
                        <div class="mp-ok-pk__body">
                            @foreach($pk->items as $item)
                                <div class="mp-ok-item">
                                    @if($item->image_url)<img src="{{ $item->image_url }}" alt="" loading="lazy" width="52" height="52">@endif
                                    <div class="mp-ok-item__t">{{ $item->title }}<small>{{ $item->quantity }} {{ $item->quantity === 1 ? 'unidad' : 'unidades' }} · S/ {{ number_format($item->unit_price, 2) }} c/u</small></div>
                                    <div class="mp-ok-item__p">S/ {{ number_format($item->total, 2) }}</div>
                                </div>
                            @endforeach
                            @if($pk->discount > 0)
                                <div class="mp-ok-row" style="color:#16a34a"><span>Cupón {{ $pk->coupon }}</span><span class="v">-S/ {{ number_format($pk->discount, 2) }}</span></div>
                            @endif
                            <div class="mp-ok-row">
                                <span>🚚 Envío{{ $place ? ' a ' . $place : '' }}</span>
                                <span class="v {{ $ship && $ship['free'] ? 'is-free' : '' }} {{ !$ship || $ship['pending'] ? 'is-pending' : '' }}">{{ $ship['text'] ?? 'Te lo confirma la tienda' }}</span>
                            </div>
                            <div class="mp-ok-row mp-ok-row--total"><span>Productos de este paquete</span><span class="v">S/ {{ number_format($pk->total, 2) }}</span></div>
                            @if($pk->whatsapp)
                                <a class="mp-ok-wa" href="https://wa.me/{{ $pk->whatsapp }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener">💬 Escribir a {{ $pk->name }}</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        </div>

        <aside>
            <section class="mp-ok-card mp-ok-sum">
                <h2>Resumen</h2>
                <div class="mp-ok-row"><span>Productos ({{ $order->items_count }})</span><span class="v">S/ {{ number_format($order->subtotal ?: $order->total, 2) }}</span></div>
                @if(($order->discount_total ?? 0) > 0)
                    <div class="mp-ok-row" style="color:#16a34a"><span>Descuentos</span><span class="v">-S/ {{ number_format($order->discount_total, 2) }}</span></div>
                @endif
                <div class="mp-ok-row">
                    <span>Envío</span>
                    <span class="v {{ $shipPending ? 'is-pending' : ($shipKnown == 0 ? 'is-free' : '') }}">
                        @if($shipKnown > 0)S/ {{ number_format($shipKnown, 2) }}{{ $shipPending ? ' + por confirmar' : '' }}@else{{ $shipPending ? 'Por confirmar' : 'Gratis' }}@endif
                    </span>
                </div>
                <div class="mp-ok-big"><span>Total estimado</span><b>S/ {{ number_format((float) $order->total + $shipKnown, 2) }}</b></div>
                <p class="mp-ok-note">{{ $isPaid ? 'Los productos ya están pagados.' : 'Pagas a ' . ($multi ? 'cada tienda' : 'la tienda') . ' cuando te escriba.' }}{{ $shipPending ? ' El envío pendiente te lo confirma la tienda.' : '' }}</p>
            </section>

            <section class="mp-ok-card">
                <h2>Entrega</h2>
                <dl class="mp-ok-dl">
                    <dt>Recibe</dt><dd>{{ $order->customer_name }}</dd>
                    <dt>WhatsApp</dt><dd>{{ $order->customer_phone }}</dd>
                    <dt>Dirección</dt><dd>{{ $order->delivery_address }}{{ $place ? ' — ' . $place : '' }}</dd>
                    @if($order->delivery_notes)<dt>Referencia</dt><dd>{{ $order->delivery_notes }}</dd>@endif
                </dl>
            </section>

            <div class="mp-ok-actions">
                <a href="{{ route('marketplace.index') }}" class="mp-ok-btn mp-ok-btn--primary">Seguir comprando</a>
                @auth('marketplace')
                    <a href="{{ route('marketplace.account.orders') }}" class="mp-ok-btn mp-ok-btn--ghost">Ver mis pedidos</a>
                @endauth
                <a href="{{ route('marketplace.shipping_returns') }}#garantia-7-dias" class="mp-ok-btn mp-ok-btn--ghost">Garantía de 7 días</a>
            </div>
        </aside>
    </div>
</div>

{{-- Purchase del navegador. Lleva el mismo event_id que el envio server-side
     (sembrado con el numero de pedido), para que Meta y TikTok deduplican en
     vez de contar la venta dos veces. Si el comprador recarga esta pagina, el
     event_id es identico y la plataforma lo descarta — que es lo correcto. --}}
@isset($adsPurchasePayload)
<script>
if (window.mpTrack) window.mpTrack({!! json_encode($adsPurchasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!});
</script>
@endisset

@endsection
