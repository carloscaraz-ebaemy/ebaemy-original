@extends('marketplace.layout')

@section('title', 'Finalizar pedido — Marketplace ebaemy')
@section('description', 'Completa tus datos de entrega. Cada tienda gestiona su propio envío y comprobante.')
@section('robots', 'noindex, follow')
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
/* La regla de arriba ponia width:100% tambien a la casilla de promociones,
   que se quedaba flotando en medio de la tarjeta. */
.mp-co-card input[type=checkbox], .mp-co-card input[type=radio] {
    width: 18px; height: 18px; padding: 0; flex: none; accent-color: var(--mp-primary, #0f8a82);
}
.mp-co-optin { display: flex !important; gap: 12px; align-items: flex-start; cursor: pointer; margin: 0 !important; font-size: 14px; line-height: 1.5; }
.mp-co-optin input { margin-top: 2px; }
/* Mismo alto y misma flecha en todos los campos: los <select> nativos salian
   mas altos, con otra flecha y otro color de texto. */
.mp-co-card input:not([type=checkbox]):not([type=radio]), .mp-co-card select { height: 44px; }
.mp-co-card select {
    -webkit-appearance: none; appearance: none; background-color: #fff; color: var(--mp-ink, #111827);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center; padding-right: 34px;
}
.mp-co-card select:disabled { background-color: #f9fafb; color: #9ca3af; cursor: not-allowed; }
.mp-co-card input:focus, .mp-co-card select:focus, .mp-co-card textarea:focus {
    outline: none; border-color: var(--mp-primary, #0f8a82);
    box-shadow: 0 0 0 3px rgba(15,138,130,.1);
}
.mp-co-summary {
    position: sticky; top: 16px; height: fit-content;
}
/* Separador ENTRE tiendas, no despues de la ultima: el ultimo bloque no es
   el ultimo hijo (vienen los totales), asi que :last-child nunca aplicaba y
   salia una linea punteada suelta encima de la de los totales. */
.mp-co-store-block { padding: 12px 0; }
.mp-co-store-block + .mp-co-store-block { border-top: 1px dashed #e5e7eb; }
.mp-co-store-block .name { font-weight: 600; font-size: 13px; color: var(--mp-ink, #111827); }
.mp-co-store-block .meta { font-size: 12px; color: #6b7280; }
.mp-co-line { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13px; }
/* El importe nunca se parte («S/» en una linea y la cifra en otra). */
.mp-co-line .price, .mp-co-line [data-summary-subtotal], .mp-co-line [data-summary-discount] { white-space: nowrap; }
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

/* Texto de ayuda bajo un campo */
.mp-co-hint { font-size: 11.5px; color: #9ca3af; margin-top: 4px; line-height: 1.3; }

/* Etiqueta tranquilizadora "Sin pago adelantado" */
.mp-co-paytag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 999px;
    background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;
    font-size: 12.5px; font-weight: 700;
}

/* Validación inline */
.mp-co-field-err { font-size: 12px; color: #b91c1c; margin-top: 4px; min-height: 14px; line-height: 1.3; }
input.mp-co-invalid, select.mp-co-invalid, textarea.mp-co-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239,68,68,.12);
}

/* Estado del autocompletado de documento (DNI/RUC) */
.mp-co-doc-status {
    font-size: 12px; margin-top: 4px; min-height: 15px; line-height: 1.3;
}
.mp-co-doc-status.is-loading { color: #6b7280; }
.mp-co-doc-status.is-ok      { color: #047857; font-weight: 600; }
.mp-co-doc-status.is-warn    { color: #b45309; }

/* ── Barra fija de Total + Confirmar (solo móvil) ───────────────────────── */
.mp-co-sticky { display: none; }
@media (max-width: 768px) {
    .mp-co-sticky {
        display: flex;
        position: fixed;
        left: 0; right: 0; bottom: 0;
        z-index: 60;
        align-items: center;
        gap: 12px;
        padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
        background: #fff;
        border-top: 1px solid #e5e7eb;
        box-shadow: 0 -6px 20px -8px rgba(0,0,0,.2);
    }
    .mp-co-sticky__total { display: flex; flex-direction: column; line-height: 1.1; flex-shrink: 0; }
    .mp-co-sticky__label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .mp-co-sticky__value { font-size: 20px; font-weight: 800; color: var(--mp-primary-dark, #0c6b65); }
    .mp-co-sticky__btn {
        flex: 1;
        padding: 14px;
        background: var(--mp-primary, #0f8a82); color: #fff; border: none;
        border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer;
    }
    .mp-co-sticky__btn:active { background: var(--mp-primary-dark, #0c6b65); }
    /* Espacio para que la barra no tape el último contenido al hacer scroll. */
    body { padding-bottom: 76px; }
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

<form method="POST" action="{{ route('marketplace.checkout.store') }}" id="mpCheckoutForm" novalidate>
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
                        @php $selDoc = old('customer_doc_type', 'DNI'); @endphp
                        <select name="customer_doc_type">
                            @foreach(['DNI','RUC','CE','Pasaporte'] as $t)
                                <option value="{{ $t }}" {{ $selDoc === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Número de documento</label>
                        <input type="text" name="customer_doc_number" maxlength="20" inputmode="numeric" autocomplete="off" value="{{ old('customer_doc_number') }}">
                        <div class="mp-co-doc-status" data-doc-status aria-live="polite"></div>
                    </div>
                </div>

                <div class="mp-co-row">
                    <div>
                        <label>Teléfono / WhatsApp *</label>
                        <input type="tel" name="customer_phone" required maxlength="40" inputmode="numeric" placeholder="9XX XXX XXX" value="{{ old('customer_phone') }}">
                        <div class="mp-co-hint">Aquí te llega la confirmación de tu pedido.</div>
                    </div>
                    <div>
                        <label>Email <span style="color:#9ca3af;font-weight:400">(opcional)</span></label>
                        <input type="email" name="customer_email" maxlength="180" value="{{ old('customer_email') }}">
                        <div class="mp-co-hint">Para enviarte tu comprobante.</div>
                    </div>
                </div>
            </section>

            <section class="mp-co-card">
                <h3>📦 Dirección de entrega</h3>
                <label>Dirección completa *</label>
                <input type="text" name="delivery_address" required maxlength="500"
                       placeholder="Av. ejemplo 123, dpto 4B"
                       value="{{ old('delivery_address') }}">

                {{-- Ubigeo dependiente (Departamento → Provincia → Distrito).
                     Carga marketplace/ubigeo.json (INEI) y cascada client-side.
                     Mantiene los mismos name= para no tocar el backend. --}}
                <div class="mp-co-row-3">
                    <div>
                        <label>Departamento</label>
                        <select name="delivery_department" data-ubigeo="dep" data-old="{{ old('delivery_department') }}">
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div>
                        <label>Provincia</label>
                        <select name="delivery_province" data-ubigeo="prov" data-old="{{ old('delivery_province') }}" disabled>
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                    <div>
                        <label>Distrito</label>
                        <select name="delivery_district" data-ubigeo="dist" data-old="{{ old('delivery_district') }}" disabled>
                            <option value="">— Selecciona —</option>
                        </select>
                    </div>
                </div>

                <label>Notas para los vendedores <span style="color:#9ca3af;font-weight:400">(opcional)</span></label>
                <textarea name="delivery_notes" rows="3" maxlength="1000" placeholder="Referencias de la dirección, horarios, preferencias…">{{ old('delivery_notes') }}</textarea>
            </section>

            <section class="mp-co-card">
                <h3>💳 Forma de pago</h3>
                <p style="margin:0 0 8px"><span class="mp-co-paytag">✓ Sin pago adelantado</span></p>
                <p style="color:#6b7280;margin:0">
                    Cada tienda coordinará el pago contigo después de recibir el pedido (Yape, Plin, depósito o pago contraentrega según su política).
                    Por ahora ebaemy no procesa cobros centralizados.
                </p>
            </section>

            <section class="mp-co-card">
                <label class="mp-co-optin">
                    <input type="checkbox" name="accepts_marketing" value="1" {{ old('accepts_marketing') ? 'checked' : '' }}>
                    <span>
                        <strong>Acepto recibir promociones y novedades</strong> de ebaemy y sus tiendas verificadas por WhatsApp/email.
                        Podré cancelar la suscripción en cualquier momento desde el enlace incluido en cada mensaje.
                    </span>
                </label>
            </section>
        </div>

        <aside class="mp-co-card mp-co-summary">
            <h3>📋 Resumen del pedido</h3>

            {{-- Paquetes, no «tiendas»: el comprador quiere saber como le llega.
                 Si hay mas de una tienda se dice desde el principio, porque
                 le escribiran, le enviaran y le facturaran por separado. --}}
            @if($stores->count() > 1)
                <p class="mp-co-pk-intro">📦 Tu pedido llega en <strong>{{ $stores->count() }} paquetes</strong>: cada tienda envía el suyo.</p>
            @endif

            @foreach($stores as $store)
                @php
                    $shipLima = \App\Services\Marketplace\ShippingSummaryText::forZone($store['shipping'] ?? null, 'lima', (float) $store['subtotal']);
                    $shipProv = \App\Services\Marketplace\ShippingSummaryText::forZone($store['shipping'] ?? null, 'province', (float) $store['subtotal']);
                @endphp
                <div class="mp-co-store-block" data-store-block data-hostname-id="{{ $store['hostname_id'] }}" data-subtotal="{{ $store['subtotal'] }}"
                     data-ship-lima='@json($shipLima)' data-ship-province='@json($shipProv)'>
                    <div class="mp-co-pk-head">
                        @if($stores->count() > 1)<span class="mp-co-pk-tag">Paquete {{ $loop->iteration }}</span>@endif
                        <span class="name">Lo envía {{ $store['tenant_name'] }}</span>
                    </div>
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
                        {{-- Sin espacios dentro: vacio tiene que ser :empty para que no deje hueco. --}}
                        <div class="mp-co-coupon__msg {{ $appliedCoupon ? 'is-ok' : '' }}" data-coupon-msg>{{ $appliedCoupon ? '✓ Cupón aplicado: -S/ ' . number_format($appliedCoupon['discount'], 2) : '' }}</div>
                        <input type="hidden" name="coupons[{{ $store['hostname_id'] }}]" data-coupon-hidden value="{{ $appliedCoupon['code'] ?? '' }}">
                        <input type="hidden" data-applied-discount value="{{ $appliedCoupon['discount'] ?? 0 }}">
                    </div>

                    {{-- Cupones disponibles para el comprador en esta tienda
                         (asignados al user en marketplace_user_coupons que
                         cumplan ventana de validez + scope + min_subtotal).
                         Click en cualquiera  autocompleta el input + dispara
                         Aplicar. As el comprador no tiene que recordar el
                         cdigo: ve sus cupones y elige el ms conveniente. --}}
                    @if(isset($platformCoupons) && ($plat = $platformCoupons->get($store['hostname_id'])) && $plat->isNotEmpty())
                        @php
                            $bestPlat = $plat->sortByDesc('discount')->first();
                        @endphp
                        <div class="mp-co-avail-coupons">
                            <p class="mp-co-avail-coupons__title">
                                🎟️ Tienes {{ $plat->count() }} {{ $plat->count() === 1 ? 'cupn disponible' : 'cupones disponibles' }} para esta tienda
                            </p>
                            <div class="mp-co-avail-coupons__list">
                                @foreach($plat->sortByDesc('discount') as $item)
                                    @php $c = $item['coupon']; @endphp
                                    <button type="button"
                                            class="mp-co-avail-coupon {{ $loop->first ? 'is-best' : '' }}"
                                            data-coupon-suggest="{{ $c->code }}"
                                            title="{{ $c->name }} - Aplicar"
                                    >
                                        <span class="mp-co-avail-coupon__code">{{ $c->code }}</span>
                                        <span class="mp-co-avail-coupon__value">
                                            {{ $c->type === 'percent' ? '-' . (int) $c->value . '%' : '-S/ ' . number_format($c->value, 0) }}
                                        </span>
                                        <span class="mp-co-avail-coupon__save">Ahorras S/ {{ number_format($item['discount'], 2) }}</span>
                                        @if($loop->first)<span class="mp-co-avail-coupon__badge">MEJOR</span>@endif
                                    </button>
                                @endforeach
                            </div>
                            <p class="mp-co-avail-coupons__hint">Toca uno para aplicarlo automticamente</p>
                        </div>
                    @endif
                    <div class="mp-co-pk-ship" data-ship-line>
                        <span>🚚 Envío</span>
                        <span class="mp-co-pk-ship__v" data-ship-text>Elige tu distrito</span>
                    </div>
                    <div class="mp-co-pk-ship__detail" data-ship-detail></div>
                </div>
            @endforeach

            @php
                $platDiscount = (float) ($platformDiscountTotal ?? 0);
                $totalAfterPlat = max(0, (float) $summary['total'] - $platDiscount);
            @endphp
            <div class="mp-co-totals">
                <div class="mp-co-line"><span>Productos ({{ $summary['count'] }})</span><span data-summary-subtotal>S/ {{ number_format($summary['total'], 2) }}</span></div>
                <div class="mp-co-line mp-co-line--discount" data-summary-discount-row style="display:none">
                    <span>Descuento (cupones)</span>
                    <span data-summary-discount style="color:#16a34a;font-weight:700">-S/ 0.00</span>
                </div>
                @if($platDiscount > 0)
                    <div class="mp-co-line mp-co-line--discount" data-plat-discount="{{ $platDiscount }}">
                        <span>Descuento (plataforma)</span>
                        <span style="color:#16a34a;font-weight:700">-S/ {{ number_format($platDiscount, 2) }}</span>
                    </div>
                @endif
                {{-- El envio no se cobra aqui: cada tienda lo cobra al coordinar.
                     Se calcula para el distrito elegido (ver forZone) para que
                     el total estimado no sorprenda. --}}
                <div class="mp-co-line"><span>Envío</span><span data-ship-total style="color:#6b7280">Elige tu distrito</span></div>
                <div class="total"><span><strong>Total estimado</strong></span><span class="v" data-summary-total>S/ {{ number_format($totalAfterPlat, 2) }}</span></div>
                <p class="mp-co-total-note" data-ship-note>Elige tu distrito arriba para sumar el envío.</p>
            </div>

            <button type="submit" class="mp-co-submit">Confirmar pedido →</button>

            {{-- Que pasa despues, en positivo: antes era un recuadro amarillo
                 de «advertencia» que asustaba justo al confirmar. --}}
            <div class="mp-co-next">
                <p class="mp-co-next__title">Después de confirmar</p>
                <ol>
                    <li>{{ $stores->count() > 1 ? 'Cada tienda te escribe' : 'La tienda te escribe' }} por WhatsApp para coordinar el pago y la entrega.</li>
                    <li>Pagas {{ $stores->count() > 1 ? 'a cada tienda' : 'a la tienda' }} con Yape, Plin, transferencia o contraentrega, según acepte.</li>
                    <li>Recibes tu boleta o factura{{ $stores->count() > 1 ? ' de cada tienda' : '' }} y tu {{ $stores->count() > 1 ? 'paquete' : 'pedido' }}.</li>
                </ol>
            </div>
        </aside>
    </div>

    {{-- Barra fija solo-móvil: total siempre visible + Confirmar sin tener que
         scrollear hasta el final. Vive dentro del <form> para que el botón
         submitee normalmente. El total se sincroniza desde [data-summary-total]. --}}
    <div class="mp-co-sticky" aria-hidden="false">
        <div class="mp-co-sticky__total">
            <span class="mp-co-sticky__label">Total</span>
            <span class="mp-co-sticky__value" data-summary-total-sticky>S/ {{ number_format($totalAfterPlat, 2) }}</span>
        </div>
        <button type="submit" class="mp-co-sticky__btn">Confirmar pedido →</button>
    </div>
</form>

@push('styles')
<style>
/* Paquetes y envio por destino */
.mp-co-pk-intro { background: #f0fdfa; border: 1px solid #99f6e4; color: #115e59; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin: 0 0 4px; }
.mp-co-pk-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
.mp-co-pk-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; background: #0f8a82; color: #fff; border-radius: 6px; padding: 2px 7px; }
.mp-co-pk-ship { display: flex; justify-content: space-between; gap: 12px; margin-top: 10px; padding: 8px 10px; background: #f8fafc; border-radius: 8px; font-size: 13px; }
.mp-co-pk-ship__v { font-weight: 700; color: #6b7280; text-align: right; }
.mp-co-pk-ship__v.is-free { color: #15803d; }
.mp-co-pk-ship__v.is-pending { color: #b45309; }
.mp-co-pk-ship__detail { font-size: 12px; color: #6b7280; margin: 4px 2px 0; }
.mp-co-pk-ship__detail:empty { display: none; }
.mp-co-total-note { font-size: 12px; color: #6b7280; margin: 6px 0 0; }
.mp-co-next { margin-top: 14px; border: 1px solid #e5e7eb; background: #f8fafc; border-radius: 10px; padding: 12px 14px; font-size: 13px; color: #374151; }
.mp-co-next__title { font-weight: 700; margin: 0 0 6px; color: var(--mp-ink, #111827); }
.mp-co-next ol { margin: 0; padding-left: 18px; }
.mp-co-next li + li { margin-top: 4px; }
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
}
.mp-co-coupon__msg:empty { display: none; }
.mp-co-card .mp-co-coupon__input { padding: 0 12px; border-radius: 8px; }
.mp-co-coupon__input::placeholder { text-transform: none; }
.mp-co-coupon__btn { border-radius: 8px; padding: 0 16px; height: 44px; }
.mp-co-coupon__msg.is-ok    { color: #16a34a; font-weight: 600; }
.mp-co-coupon__msg.is-error { color: #b91c1c; font-weight: 500; }

/* Cupones disponibles del usuario para esta tienda (clickeables) */
.mp-co-avail-coupons {
    margin-top: 12px;
    padding: 12px 14px;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1.5px solid #fbbf24;
    border-radius: 10px;
    box-shadow: 0 2px 8px -2px rgba(245, 158, 11, .2);
}
.mp-co-avail-coupons__title {
    margin: 0 0 10px;
    font-weight: 700;
    color: #92400e;
    font-size: 12.5px;
    line-height: 1.3;
}
.mp-co-avail-coupons__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.mp-co-avail-coupon {
    flex: 1 1 auto;
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: #fff;
    border: 1.5px solid #fcd34d;
    border-radius: 10px;
    cursor: pointer;
    font-size: 12.5px;
    color: #78350f;
    text-align: left;
    transition: all .15s ease;
    position: relative;
    touch-action: manipulation;
}
.mp-co-avail-coupon:hover,
.mp-co-avail-coupon:focus {
    background: #fffbeb;
    border-color: #f59e0b;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px -2px rgba(245, 158, 11, .35);
}
.mp-co-avail-coupon.is-best {
    border-color: #16a34a;
    background: linear-gradient(135deg, #fff 0%, #ecfdf5 100%);
}
.mp-co-avail-coupon.is-best:hover { border-color: #15803d; }
.mp-co-avail-coupon__code {
    font-family: 'SF Mono', Menlo, Consolas, monospace;
    background: #fef3c7;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 11.5px;
    color: #92400e;
    letter-spacing: .04em;
    font-weight: 700;
    border: 1px solid #fcd34d;
}
.mp-co-avail-coupon.is-best .mp-co-avail-coupon__code {
    background: #dcfce7;
    color: #166534;
    border-color: #86efac;
}
.mp-co-avail-coupon__value {
    font-weight: 800;
    color: #b45309;
    font-size: 13px;
}
.mp-co-avail-coupon.is-best .mp-co-avail-coupon__value { color: #15803d; }
.mp-co-avail-coupon__save {
    margin-left: auto;
    font-size: 11.5px;
    color: #6b7280;
    white-space: nowrap;
}
.mp-co-avail-coupon__badge {
    position: absolute;
    top: -8px;
    right: 6px;
    background: #16a34a;
    color: #fff;
    font-size: 9px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 999px;
    letter-spacing: .05em;
    box-shadow: 0 2px 4px rgba(0,0,0,.15);
}
.mp-co-avail-coupons__hint {
    margin: 10px 0 0;
    font-size: 11px;
    color: #92400e;
    opacity: .75;
    text-align: center;
}
.mp-co-line--discount { color: #166534; }
</style>
@endpush

@push('scripts')
<script>
(function(){
    const VALIDATE_URL = @json(route('marketplace.checkout.coupon'));
    const REMOVE_URL_BASE = @json(url('/marketplace/checkout/coupon'));

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
        // El descuento de plataforma ya venia restado en el total del servidor;
        // al recalcular hay que volver a restarlo o desaparecia del total.
        const platEl = document.querySelector('[data-plat-discount]');
        const plat = platEl ? parseFloat(platEl.dataset.platDiscount || 0) : 0;

        // Envio del distrito elegido (window.__mpShipZone lo pone el selector).
        const zone = window.__mpShipZone || null;
        const shipTotalEl = document.querySelector('[data-ship-total]');
        const shipNoteEl  = document.querySelector('[data-ship-note]');
        let ship = 0, pending = 0;
        document.querySelectorAll('[data-store-block]').forEach(el => {
            const txt = el.querySelector('[data-ship-text]');
            const det = el.querySelector('[data-ship-detail]');
            if (!zone) {
                if (txt) { txt.textContent = 'Elige tu distrito'; txt.className = 'mp-co-pk-ship__v'; }
                if (det) det.textContent = '';
                return;
            }
            let r = null;
            try { r = JSON.parse(el.dataset[zone === 'lima' ? 'shipLima' : 'shipProvince'] || 'null'); } catch (e) {}
            if (!r) return;
            if (txt) { txt.textContent = r.text; txt.className = 'mp-co-pk-ship__v' + (r.free ? ' is-free' : '') + (r.pending ? ' is-pending' : ''); }
            if (det) det.textContent = r.detail || '';
            if (r.amount !== null && r.amount !== undefined) ship += parseFloat(r.amount);
            if (r.pending) pending++;
        });

        const total = Math.max(0, subtotal - discount - plat) + (zone ? ship : 0);

        const fmt = n => 'S/ ' + n.toFixed(2);
        subtotalEl.textContent = fmt(subtotal);
        if (discount > 0) {
            discRow.style.display = '';
            discEl.textContent = '-' + fmt(discount);
        } else {
            discRow.style.display = 'none';
        }
        if (shipTotalEl) {
            shipTotalEl.textContent = !zone ? 'Elige tu distrito'
                : (ship > 0 ? fmt(ship) + (pending ? ' + por confirmar' : '') : (pending ? 'Por confirmar' : 'Gratis'));
        }
        if (shipNoteEl) {
            shipNoteEl.textContent = !zone ? 'Elige tu distrito arriba para sumar el envío.'
                : (pending ? 'Parte del envío te lo confirma la tienda por WhatsApp. Pagas a cada tienda al coordinar.'
                           : 'Pagas a cada tienda cuando te escriba para coordinar la entrega.');
        }
        totalEl.textContent = fmt(total);
    }
    window.mpRecalcSummary = recalcSummary;

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
                    headers: await window.mpCsrfHeaders({
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    }),
                    credentials: 'same-origin',
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
                    headers: await window.mpCsrfHeaders({ 'Accept': 'application/json' }),
                    credentials: 'same-origin',
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

        // Click en un cupn sugerido (item 3 del roadmap) → autocompleta el
        // input y dispara apply. Si ya hay un cupn aplicado, primero confirmar
        // que el user quiere reemplazarlo (remove + apply nuevo).
        block.querySelectorAll('[data-coupon-suggest]').forEach(chip => {
            chip.addEventListener('click', async (e) => {
                e.preventDefault();
                const suggestedCode = chip.dataset.couponSuggest;
                if (!suggestedCode) return;
                if (couponBox?.dataset.applied === '1') {
                    if (!confirm('Ya tienes un cupn aplicado en esta tienda. Quers reemplazarlo por ' + suggestedCode + '?')) return;
                    // Quitamos el actual y al cargar de nuevo el user podr aplicar el nuevo
                    try {
                        await fetch(REMOVE_URL_BASE + '/' + hostnameId, {
                            method: 'DELETE',
                            headers: await window.mpCsrfHeaders({ 'Accept': 'application/json' }),
                            credentials: 'same-origin',
                        });
                    } catch (e) {}
                }
                input.value = suggestedCode;
                input.readOnly = false;
                // Reusamos la lgica de apply (mismo endpoint, mismo flow)
                apply();
            });
        });
    });

    // Render inicial del resumen reflejando cupones persistidos
    recalcSummary();

    // ── Barra fija móvil: espejar el Total en tiempo real ──────────────────
    (function () {
        const src = document.querySelector('[data-summary-total]');
        const dst = document.querySelector('[data-summary-total-sticky]');
        if (!src || !dst) return;
        const sync = () => { dst.textContent = src.textContent; };
        sync();
        // El total cambia cuando se aplican/quitan cupones (recalcSummary edita
        // el texto). Un MutationObserver mantiene la barra sincronizada sin
        // acoplarnos a la lógica interna de cupones.
        new MutationObserver(sync).observe(src, { childList: true, characterData: true, subtree: true });
    })();

    // ── Autocompletado DNI/RUC → nombre + ubigeo ───────────────────────────
    (function () {
        const LOOKUP_URL = @json(route('marketplace.lookup.document'));
        const docType = document.querySelector('[name="customer_doc_type"]');
        const docNum  = document.querySelector('[name="customer_doc_number"]');
        const nameEl  = document.querySelector('[name="customer_name"]');
        const statusEl = document.querySelector('[data-doc-status]');
        const addrEl  = document.querySelector('[name="delivery_address"]');
        if (!docNum || !nameEl) return;

        // Marca si el nombre fue autocompletado para poder sobreescribirlo en
        // una nueva consulta, pero NO pisar lo que el usuario tipeó a mano.
        let autofilled = false;
        nameEl.addEventListener('input', () => { autofilled = false; });
        // Igual para la dirección: la rellenamos con la fiscal del RUC, pero si
        // el comprador la edita dejamos de pisarla (la entrega puede diferir).
        let addrAutofilled = false;
        if (addrEl) addrEl.addEventListener('input', () => { addrAutofilled = false; });

        function setStatus(text, cls) {
            statusEl.textContent = text || '';
            statusEl.className = 'mp-co-doc-status' + (cls ? ' ' + cls : '');
        }

        let lastQuery = '';
        let timer = null;

        async function lookup() {
            const raw = (docNum.value || '').replace(/\D+/g, '');
            let type = (docType && docType.value || '').toUpperCase();
            // Autodetecta tipo por longitud si no se eligió uno.
            if (!type) {
                if (raw.length === 8) type = 'DNI';
                else if (raw.length === 11) type = 'RUC';
            }
            const valid = (type === 'DNI' && raw.length === 8) || (type === 'RUC' && raw.length === 11);
            if (!valid) { setStatus(''); return; }

            const key = type + ':' + raw;
            if (key === lastQuery) return;
            lastQuery = key;

            // Auto-selecciona el tipo en el dropdown por comodidad.
            if (docType && !docType.value && (type === 'DNI' || type === 'RUC')) docType.value = type;

            setStatus('Buscando ' + type + '…', 'is-loading');
            try {
                const url = LOOKUP_URL + '?type=' + type + '&number=' + raw;
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const data = await resp.json();
                if (data && data.ok && data.name) {
                    if (!nameEl.value.trim() || autofilled) {
                        nameEl.value = data.name;
                        autofilled = true;
                    }
                    if (type === 'RUC') {
                        // Dirección fiscal de SUNAT (editable: la entrega puede diferir).
                        if (addrEl && data.address && (!addrEl.value.trim() || addrAutofilled)) {
                            addrEl.value = data.address;
                            addrAutofilled = true;
                            addrEl.classList.remove('mp-co-invalid');
                        }
                        // Selecciona el ubigeo del RUC en los selects (best-effort:
                        // si el nombre SUNAT no matchea el INEI, se elige a mano).
                        if (window.__mpUbigeoSet) {
                            window.__mpUbigeoSet(data.department, data.province, data.district);
                        }
                    }
                    setStatus('✓ ' + data.name, 'is-ok');
                } else {
                    setStatus('No encontramos ese ' + type + '. Puedes escribir el nombre a mano.', 'is-warn');
                }
            } catch (e) {
                setStatus('', '');
            }
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(lookup, 400);
        }
        docNum.addEventListener('input', schedule);
        docNum.addEventListener('blur', lookup);
        if (docType) docType.addEventListener('change', () => { lastQuery = ''; lookup(); });
    })();

    // ── Ubigeo dependiente: Departamento → Provincia → Distrito ─────────────
    (function () {
        const depSel  = document.querySelector('[data-ubigeo="dep"]');
        const provSel = document.querySelector('[data-ubigeo="prov"]');
        const distSel = document.querySelector('[data-ubigeo="dist"]');
        if (!depSel || !provSel || !distSel) return;

        const URL = @json(asset('static/ubigeo-pe.json'));
        let data = null;
        const norm = (s) => (s || '').toString().trim().toUpperCase();

        function opt(v) { const o = document.createElement('option'); o.value = v; o.textContent = v; return o; }
        function reset(sel) { sel.innerHTML = '<option value="">— Selecciona —</option>'; }

        function onDep() {
            reset(provSel); reset(distSel);
            distSel.disabled = true;
            const dep = data && data.find(d => d.n === depSel.value);
            provSel.disabled = !dep;
            if (dep) {
                dep.p.forEach(p => provSel.appendChild(opt(p.n)));
                if (provSel.dataset.old) { provSel.value = provSel.dataset.old; if (provSel.value) onProv(); }
            }
        }
        function onProv() {
            reset(distSel);
            const dep = data && data.find(d => d.n === depSel.value);
            const prov = dep && dep.p.find(p => p.n === provSel.value);
            distSel.disabled = !prov;
            if (prov) {
                prov.d.forEach(dd => distSel.appendChild(opt(dd)));
                if (distSel.dataset.old) distSel.value = distSel.dataset.old;
            }
        }

        // Zona de envio: Lima Metropolitana (provincia Lima) y Callao son
        // «Lima»; el resto, «provincias». Mismo criterio que StoreShipping.
        function zoneNow() {
            const d = norm(depSel.value), p = norm(provSel.value);
            if (!d || !p) return null;
            return (p === 'LIMA' || d === 'CALLAO' || p === 'CALLAO') ? 'lima' : 'province';
        }
        function onZone() {
            window.__mpShipZone = zoneNow();
            if (window.mpRecalcSummary) window.mpRecalcSummary();
        }

        depSel.addEventListener('change', onDep);
        provSel.addEventListener('change', onProv);
        depSel.addEventListener('change', onZone);
        provSel.addEventListener('change', onZone);

        // Selección por nombre (la usa el autocompletado de RUC).
        window.__mpUbigeoSet = function (dep, prov, dist) {
            if (!data || !dep) return;
            const depObj = data.find(d => norm(d.n) === norm(dep));
            if (!depObj) return;
            depSel.value = depObj.n; onDep();
            if (prov) {
                const pObj = depObj.p.find(p => norm(p.n) === norm(prov));
                if (pObj) {
                    provSel.value = pObj.n; onProv();
                    if (dist) {
                        const dObj = pObj.d.find(x => norm(x) === norm(dist));
                        if (dObj) distSel.value = dObj;
                    }
                }
            }
            onZone();
        };

        fetch(URL, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                data = d;
                data.forEach(dep => depSel.appendChild(opt(dep.n)));
                if (depSel.dataset.old) { depSel.value = depSel.dataset.old; if (depSel.value) onDep(); }
                onZone();
            })
            .catch(() => { /* sin ubigeo: el envío igual funciona (campos vacíos) */ });
    })();

    // ── Validación inline antes de enviar ──────────────────────────────────
    (function () {
        const form = document.getElementById('mpCheckoutForm');
        if (!form) return;
        const $ = (sel) => form.querySelector(sel);

        // Inserta el mensaje JUSTO después del input (no al final del card) y lo
        // recuerda por input para limpiarlo después.
        const boxes = new Map();
        function errBox(input) {
            if (boxes.has(input)) return boxes.get(input);
            const box = document.createElement('div');
            box.className = 'mp-co-field-err';
            input.insertAdjacentElement('afterend', box);
            boxes.set(input, box);
            return box;
        }
        function setErr(input, msg) {
            if (!input) return;
            input.classList.add('mp-co-invalid');
            errBox(input).textContent = msg;
        }
        function clearErr(input) {
            if (!input) return;
            input.classList.remove('mp-co-invalid');
            if (boxes.has(input)) boxes.get(input).textContent = '';
        }

        function validate() {
            const errors = [];
            const name  = $('[name="customer_name"]');
            const dtype = $('[name="customer_doc_type"]');
            const dnum  = $('[name="customer_doc_number"]');
            const phone = $('[name="customer_phone"]');
            const email = $('[name="customer_email"]');
            const addr  = $('[name="delivery_address"]');

            [name, dnum, phone, email, addr].forEach(clearErr);

            if (!name.value.trim() || name.value.trim().length < 3) {
                setErr(name, 'Ingresa el nombre completo o razón social.');
                errors.push(name);
            }

            // Documento: opcional, pero si se llena debe cuadrar con el tipo.
            const dv = (dnum.value || '').replace(/\D+/g, '');
            const dt = (dtype && dtype.value || '').toUpperCase();
            if (dnum.value.trim()) {
                if (dt === 'DNI' && dv.length !== 8) { setErr(dnum, 'El DNI debe tener 8 dígitos.'); errors.push(dnum); }
                else if (dt === 'RUC' && dv.length !== 11) { setErr(dnum, 'El RUC debe tener 11 dígitos.'); errors.push(dnum); }
            }

            // Teléfono: requerido, celular peruano 9 dígitos (acepta prefijo 51).
            let pv = (phone.value || '').replace(/\D+/g, '');
            if (pv.length === 11 && pv.startsWith('51')) pv = pv.slice(2);
            if (!pv) { setErr(phone, 'Ingresa tu teléfono / WhatsApp.'); errors.push(phone); }
            else if (!/^9\d{8}$/.test(pv)) { setErr(phone, 'Debe ser un celular válido de 9 dígitos (empieza en 9).'); errors.push(phone); }

            // Email: opcional, pero válido si se llena.
            if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                setErr(email, 'Revisa el formato del correo.');
                errors.push(email);
            }

            if (!addr.value.trim() || addr.value.trim().length < 5) {
                setErr(addr, 'Ingresa la dirección de entrega.');
                errors.push(addr);
            }

            return errors;
        }

        // Limpia el error del campo apenas el usuario corrige.
        ['customer_name','customer_doc_number','customer_phone','customer_email','delivery_address'].forEach(n => {
            const el = $('[name="' + n + '"]');
            if (el) el.addEventListener('input', () => clearErr(el));
        });

        let submitting = false;
        form.addEventListener('submit', function (e) {
            if (submitting) return;
            const errors = validate();
            if (errors.length) {
                e.preventDefault();
                errors[0].focus();
                errors[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            // Evita doble submit (doble tap en móvil sobre la barra fija).
            submitting = true;
            form.querySelectorAll('.mp-co-submit, .mp-co-sticky__btn').forEach(b => { b.disabled = true; });
        });
    })();
})();

/* InitiateCheckout: el comprador llego al formulario con el carrito lleno.
   Es el evento que Meta y TikTok usan para optimizar hacia gente que de
   verdad empieza a comprar, no solo hacia quien mira. */
if (window.mpTrack) window.mpTrack({!! json_encode($adsCheckoutPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!});
</script>
@endpush

@endsection
