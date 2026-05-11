@extends('marketplace.layout')

@section('title', 'Finalizar pedido — Marketplace ebaemy')
@section('description', 'Completa tus datos de entrega. Cada tienda gestiona su propio envío y comprobante.')
@section('canonical', route('marketplace.checkout'))

@push('styles')
<style>
.mp-co-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; margin-bottom: 40px; }
@media (max-width: 899px) { .mp-co-grid { grid-template-columns: 1fr; } }
.mp-co-card {
    background: #fff; border: 1px solid var(--mp-border, #e5e7eb);
    border-radius: 14px; padding: 22px; margin-bottom: 16px;
}
.mp-co-card h3 { margin: 0 0 14px; font-size: 16px; color: var(--mp-ink, #111827); }
.mp-co-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mp-co-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
@media (max-width: 599px) {
    .mp-co-row, .mp-co-row-3 { grid-template-columns: 1fr; }
}
.mp-co-card label {
    display: block; font-size: 13px; font-weight: 500; color: #4b5563; margin: 8px 0 4px;
}
.mp-co-card input, .mp-co-card select, .mp-co-card textarea {
    width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px;
    font-size: 14px; font-family: inherit;
}
.mp-co-card input:focus, .mp-co-card select:focus, .mp-co-card textarea:focus {
    outline: none; border-color: var(--mp-primary, #0f8a82);
    box-shadow: 0 0 0 3px rgba(15,138,130,.1);
}
.mp-co-summary {
    position: sticky; top: 16px; height: fit-content;
}
.mp-co-store-block { padding: 12px 0; border-bottom: 1px dashed #f3f4f6; }
.mp-co-store-block:last-child { border-bottom: none; }
.mp-co-store-block .name { font-weight: 600; font-size: 13px; color: var(--mp-ink, #111827); }
.mp-co-store-block .meta { font-size: 12px; color: #6b7280; }
.mp-co-line { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.mp-co-line .price { color: #6b7280; }
.mp-co-totals { padding-top: 14px; border-top: 1px solid #e5e7eb; margin-top: 12px; }
.mp-co-totals .total {
    display: flex; justify-content: space-between; align-items: center; padding: 6px 0;
}
.mp-co-totals .total .v {
    font-size: 22px; font-weight: 800; color: var(--mp-primary-dark, #0c6b65);
}
.mp-co-submit {
    display: block; width: 100%; padding: 14px;
    background: var(--mp-primary, #0f8a82); color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 700;
    text-align: center; cursor: pointer; margin-top: 14px;
}
.mp-co-submit:hover { background: var(--mp-primary-dark, #0c6b65); }
.mp-co-submit:disabled { background: #d1d5db; cursor: not-allowed; }
.mp-co-warn {
    background: #fef3c7; border: 1px solid #fde68a; color: #92400e;
    padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-top: 14px;
}
.mp-co-error {
    background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
    padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px;
}
</style>
@endpush

@section('content')

<nav class="mp-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('marketplace.index') }}">Marketplace</a>
    <span class="sep">›</span>
    <a href="{{ route('marketplace.cart') }}">Carrito</a>
    <span class="sep">›</span>
    <span style="color:var(--mp-ink);font-weight:500">Finalizar pedido</span>
</nav>

<h1 style="margin:0 0 20px; font-size: clamp(22px, 3vw, 28px); color: var(--mp-ink, #111827);">
    Finalizar pedido
</h1>

@if($errors->any())
    <div class="mp-co-error">
        <strong>Revisa estos puntos:</strong>
        <ul style="margin: 6px 0 0 20px">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('marketplace.checkout.store') }}">
    @csrf
    <input type="text" name="website" tabindex="-1" autocomplete="off"
           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true">

    <div class="mp-co-grid">
        <div>
            <section class="mp-co-card">
                <h3>👤 Datos del comprador</h3>
                <label>Nombre completo o razón social *</label>
                <input type="text" name="customer_name" required maxlength="180" value="{{ old('customer_name') }}">

                <div class="mp-co-row">
                    <div>
                        <label>Tipo de documento</label>
                        <select name="customer_doc_type">
                            <option value="">— Selecciona —</option>
                            @foreach(['DNI','RUC','CE','Pasaporte'] as $t)
                                <option value="{{ $t }}" {{ old('customer_doc_type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Número de documento</label>
                        <input type="text" name="customer_doc_number" maxlength="20" value="{{ old('customer_doc_number') }}">
                    </div>
                </div>

                <div class="mp-co-row">
                    <div>
                        <label>Teléfono / WhatsApp *</label>
                        <input type="tel" name="customer_phone" required maxlength="40" placeholder="9XX XXX XXX" value="{{ old('customer_phone') }}">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="customer_email" maxlength="180" value="{{ old('customer_email') }}">
                    </div>
                </div>
            </section>

            <section class="mp-co-card">
                <h3>📦 Dirección de entrega</h3>
                <label>Dirección completa *</label>
                <input type="text" name="delivery_address" required maxlength="500"
                       placeholder="Av. ejemplo 123, dpto 4B"
                       value="{{ old('delivery_address') }}">

                <div class="mp-co-row-3">
                    <div>
                        <label>Departamento</label>
                        <input type="text" name="delivery_department" maxlength="80" value="{{ old('delivery_department') }}">
                    </div>
                    <div>
                        <label>Provincia</label>
                        <input type="text" name="delivery_province" maxlength="80" value="{{ old('delivery_province') }}">
                    </div>
                    <div>
                        <label>Distrito</label>
                        <input type="text" name="delivery_district" maxlength="80" value="{{ old('delivery_district') }}">
                    </div>
                </div>

                <label>Notas para los vendedores <span style="color:#9ca3af;font-weight:400">(opcional)</span></label>
                <textarea name="delivery_notes" rows="3" maxlength="1000" placeholder="Referencias de la dirección, horarios, preferencias…">{{ old('delivery_notes') }}</textarea>
            </section>

            <section class="mp-co-card">
                <h3>💳 Forma de pago</h3>
                <p style="color:#6b7280;margin:0">
                    Cada tienda coordinará el pago contigo después de recibir el pedido (Yape, Plin, depósito o pago contraentrega según su política).
                    Por ahora ebaemy no procesa cobros centralizados.
                </p>
            </section>

            <section class="mp-co-card">
                <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-size:14px">
                    <input type="checkbox" name="accepts_marketing" value="1" {{ old('accepts_marketing') ? 'checked' : '' }} style="margin-top:3px">
                    <span>
                        <strong>Acepto recibir promociones y novedades</strong> de ebaemy y sus tiendas verificadas por WhatsApp/email.
                        Podré cancelar la suscripción en cualquier momento desde el enlace incluido en cada mensaje.
                    </span>
                </label>
            </section>
        </div>

        <aside class="mp-co-card mp-co-summary">
            <h3>📋 Resumen del pedido</h3>

            @foreach($stores as $store)
                <div class="mp-co-store-block" data-store-block data-hostname-id="{{ $store['hostname_id'] }}" data-subtotal="{{ $store['subtotal'] }}">
                    <div class="name">{{ $store['tenant_name'] }}</div>
                    <div class="meta">{{ $store['item_count'] }} {{ $store['item_count'] === 1 ? 'unidad' : 'unidades' }} · S/ {{ number_format($store['subtotal'], 2) }}</div>
                    @foreach($store['items'] as $line)
                        <div class="mp-co-line">
                            <span>{{ $line['quantity'] }}× {{ \Illuminate\Support\Str::limit($line['title'], 36) }}</span>
                            <span class="price">S/ {{ number_format($line['price'] * $line['quantity'], 2) }}</span>
                        </div>
                    @endforeach

                    {{-- Cupón por tienda: cada seller administra sus propios cupones
                         en el panel del tenant. AJAX valida y persiste en sesión
                         para sobrevivir navegación/recarga. --}}
                    @php
                        $appliedCoupon = $appliedCoupons[$store['hostname_id']] ?? null;
                    @endphp
                    <div class="mp-co-coupon" data-applied="{{ $appliedCoupon ? '1' : '0' }}">
                        <div class="mp-co-coupon__input-row">
                            <input type="text"
                                   class="mp-co-coupon__input"
                                   placeholder="Cupón de descuento"
                                   maxlength="60"
                                   data-coupon-input
                                   value="{{ $appliedCoupon['code'] ?? '' }}"
                                   {{ $appliedCoupon ? 'readonly' : '' }}
                                   autocomplete="off">
                            @if($appliedCoupon)
                                <button type="button" class="mp-co-coupon__btn is-applied" data-coupon-btn data-applied="1">
                                    Aplicado ✓
                                </button>
                                <button type="button" class="mp-co-coupon__remove" data-coupon-remove title="Quitar cupón">
                                    ✕
                                </button>
                            @else
                                <button type="button" class="mp-co-coupon__btn" data-coupon-btn>
                                    Aplicar
                                </button>
                            @endif
                        </div>
                        <div class="mp-co-coupon__msg {{ $appliedCoupon ? 'is-ok' : '' }}" data-coupon-msg>
                            @if($appliedCoupon)
                                ✓ Cupón aplicado: -S/ {{ number_format($appliedCoupon['discount'], 2) }}
                            @endif
                        </div>
                        <input type="hidden" name="coupons[{{ $store['hostname_id'] }}]" data-coupon-hidden value="{{ $appliedCoupon['code'] ?? '' }}">
                        <input type="hidden" data-applied-discount value="{{ $appliedCoupon['discount'] ?? 0 }}">
                    </div>
                </div>
            @endforeach

            <div class="mp-co-totals">
                <div class="mp-co-line"><span>Productos</span><span>{{ $summary['count'] }}</span></div>
                <div class="mp-co-line"><span>Tiendas</span><span>{{ $stores->count() }}</span></div>
                <div class="mp-co-line"><span>Subtotal</span><span data-summary-subtotal>S/ {{ number_format($summary['total'], 2) }}</span></div>
                <div class="mp-co-line mp-co-line--discount" data-summary-discount-row style="display:none">
                    <span>Descuento (cupones)</span>
                    <span data-summary-discount style="color:#16a34a;font-weight:700">-S/ 0.00</span>
                </div>
                <div class="mp-co-line"><span>Envío</span><span style="color:#6b7280">A coordinar con cada tienda</span></div>
                <div class="total"><span><strong>Total</strong></span><span class="v" data-summary-total>S/ {{ number_format($summary['total'], 2) }}</span></div>
            </div>

            <button type="submit" class="mp-co-submit">Confirmar pedido →</button>

            <div class="mp-co-warn">
                ⚠️ Recibirás un mensaje por WhatsApp/email de cada tienda. Cada vendedor emite su propio comprobante por separado (productos de distintos RUC no van en una sola factura).
            </div>
        </aside>
    </div>
</form>

@push('styles')
<style>
.mp-co-coupon { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb; }
.mp-co-coupon__input-row { display: flex; gap: 6px; }
.mp-co-coupon__input {
    flex: 1;
    min-width: 0;
    padding: 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    text-transform: uppercase;
}
.mp-co-coupon__input:focus { border-color: #3b82f6; outline: 0; }
.mp-co-coupon__btn {
    padding: 7px 14px;
    background: #1f2937;
    color: #fff;
    border: 0;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background .12s;
}
.mp-co-coupon__btn:hover { background: #111827; }
.mp-co-coupon__btn:disabled { background: #9ca3af; cursor: not-allowed; }
.mp-co-coupon__btn.is-applied { background: #16a34a; cursor: default; }
.mp-co-coupon__remove {
    width: 32px;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    flex-shrink: 0;
}
.mp-co-coupon__remove:hover { background: #fecaca; }
.mp-co-coupon__msg {
    font-size: 11.5px;
    margin-top: 5px;
    min-height: 14px;
}
.mp-co-coupon__msg.is-ok    { color: #16a34a; font-weight: 600; }
.mp-co-coupon__msg.is-error { color: #b91c1c; font-weight: 500; }
.mp-co-line--discount { color: #166534; }
</style>
@endpush

@push('scripts')
<script>
(function(){
    const VALIDATE_URL = @json(route('marketplace.checkout.coupon'));
    const REMOVE_URL_BASE = @json(url('/marketplace/checkout/coupon'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Trackea descuento por tienda. Se hidrata desde data-applied-discount al
    // cargar para que el resumen ya refleje los cupones persistidos.
    const appliedDiscounts = {};

    function recalcSummary() {
        const subtotalEl = document.querySelector('[data-summary-subtotal]');
        const totalEl    = document.querySelector('[data-summary-total]');
        const discRow    = document.querySelector('[data-summary-discount-row]');
        const discEl     = document.querySelector('[data-summary-discount]');
        if (!subtotalEl || !totalEl) return;

        let subtotal = 0;
        document.querySelectorAll('[data-store-block]').forEach(el => {
            subtotal += parseFloat(el.dataset.subtotal || 0);
        });
        const discount = Object.values(appliedDiscounts).reduce((a, b) => a + b, 0);
        const total = Math.max(0, subtotal - discount);

        const fmt = n => 'S/ ' + n.toFixed(2);
        subtotalEl.textContent = fmt(subtotal);
        if (discount > 0) {
            discRow.style.display = '';
            discEl.textContent = '-' + fmt(discount);
        } else {
            discRow.style.display = 'none';
        }
        totalEl.textContent = fmt(total);
    }

    document.querySelectorAll('[data-store-block]').forEach(block => {
        const hostnameId = block.dataset.hostnameId;
        const input  = block.querySelector('[data-coupon-input]');
        const btn    = block.querySelector('[data-coupon-btn]');
        const msg    = block.querySelector('[data-coupon-msg]');
        const hidden = block.querySelector('[data-coupon-hidden]');
        const couponBox = block.querySelector('.mp-co-coupon');
        const removeBtn = block.querySelector('[data-coupon-remove]');
        const appliedDiscountEl = block.querySelector('[data-applied-discount]');

        // Hidratar descuento ya aplicado (cupón sobrevivió a recarga)
        const initialDiscount = parseFloat(appliedDiscountEl?.value || 0);
        if (initialDiscount > 0) {
            appliedDiscounts[hostnameId] = initialDiscount;
        }

        async function apply() {
            const code = (input.value || '').trim().toUpperCase();
            if (!code) {
                msg.textContent = 'Ingresa un código.';
                msg.className = 'mp-co-coupon__msg is-error';
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Validando…';
            msg.textContent = '';
            try {
                const res = await fetch(VALIDATE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({ hostname_id: hostnameId, code }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    // Recargamos para que el server-rendered state refleje
                    // todo (boton "Aplicado", X de remover, descuento). Es
                    // más simple que mutar el DOM y mantiene server como
                    // fuente de verdad.
                    window.location.reload();
                } else {
                    msg.textContent = data.message || 'No se pudo validar el cupón.';
                    msg.className = 'mp-co-coupon__msg is-error';
                    btn.textContent = 'Aplicar';
                    btn.disabled = false;
                }
            } catch (e) {
                msg.textContent = 'Error de red. Intenta de nuevo.';
                msg.className = 'mp-co-coupon__msg is-error';
                btn.textContent = 'Aplicar';
                btn.disabled = false;
            }
        }

        async function remove() {
            if (!confirm('¿Quitar el cupón aplicado en esta tienda?')) return;
            try {
                const res = await fetch(REMOVE_URL_BASE + '/' + hostnameId, {
                    method: 'DELETE',
                    headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': CSRF },
                });
                if (res.ok) window.location.reload();
                else alert('No se pudo quitar el cupón.');
            } catch (e) {
                alert('Error de red. Intenta de nuevo.');
            }
        }

        // Si ya está aplicado, el boton Aplicar no debería re-validar — el
        // usuario quita primero, luego aplica otro.
        if (couponBox?.dataset.applied !== '1') {
            btn.addEventListener('click', apply);
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); apply(); }
            });
        }
        if (removeBtn) removeBtn.addEventListener('click', remove);
    });

    // Render inicial del resumen reflejando cupones persistidos
    recalcSummary();
})();
</script>
@endpush

@endsection
