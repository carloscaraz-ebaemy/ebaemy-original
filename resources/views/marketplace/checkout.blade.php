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
/* Formulario en dos pasos, compacto */
.mp-co-step { padding: 20px 22px 22px; }
.mp-co-step h3 { display: flex; align-items: center; gap: 10px; font-size: 17px; margin-bottom: 6px; }
.mp-co-step__n { width: 26px; height: 26px; border-radius: 50%; background: var(--mp-primary, #0f8a82); color: #fff; font-size: 13px; font-weight: 800; display: grid; place-items: center; flex: none; }
.mp-co-step label { margin: 12px 0 5px; font-weight: 600; color: #374151; }
.mp-co-opt { color: #9ca3af; font-weight: 400; }
.mp-co-doc { display: grid; grid-template-columns: 130px 1fr; gap: 12px; }
.mp-co-doc .mp-co-doc-status:empty { display: none; }
.mp-co-place { position: relative; }
.mp-co-place__list { position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px); margin: 0; padding: 4px; list-style: none; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 12px 28px rgba(15,23,42,.14); max-height: 300px; overflow: auto; }
.mp-co-place__list li { padding: 9px 10px; border-radius: 8px; cursor: pointer; font-size: 14px; line-height: 1.3; }
.mp-co-place__list li small { display: block; color: #6b7280; font-size: 12px; }
.mp-co-place__list li[aria-selected="true"], .mp-co-place__list li:hover { background: #f0fdfa; }
.mp-co-place__list .is-empty { color: #6b7280; cursor: default; background: none !important; }
.mp-co-ubigeo-store { display: none; }
.mp-co-notes { margin-top: 10px; }
.mp-co-notes summary { cursor: pointer; font-size: 13.5px; font-weight: 600; color: var(--mp-primary, #0f8a82); list-style: none; padding: 4px 0; }
.mp-co-notes summary::-webkit-details-marker { display: none; }
.mp-co-notes[open] summary { margin-bottom: 6px; }
.mp-co-foot { padding: 4px 4px 0; display: grid; gap: 12px; }
.mp-co-foot__pay { margin: 0; font-size: 13px; color: #4b5563; display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; }
.mp-co-foot .mp-co-optin { font-size: 13px; color: #4b5563; font-weight: 400; }
@media (max-width: 599px) {
    .mp-co-step { padding: 18px 16px 20px; }
    .mp-co-doc { grid-template-columns: 110px 1fr; }
}
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
.mp-co-field-err:empty { display: none; }
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
            {{-- Dos pasos. 1: quién eres (el documento va primero porque
                 autocompleta el nombre). 2: ¿Cómo lo recibes? — el distrito
                 es UN buscador (los tres <select> siguen ocultos como almacén,
                 con los mismos name= que espera el servidor) y, por cada tienda
                 del carrito, tarjetas de recojo / delivery Lima / agencia con su
                 costo y plazo. Con cuenta, todo viene precargado ($prefill). --}}
            @php
                $pf = $prefill ?? [];
                $selDoc = old('customer_doc_type', $pf['doc_type'] ?? 'DNI');
                $moreOpen = old('customer_email', $pf['email'] ?? '') !== '' || old('delivery_notes', $pf['notes'] ?? '') !== '' || $errors->has('customer_email');
            @endphp
            <section class="mp-co-card mp-co-step">
                <h3><span class="mp-co-step__n">1</span> Tus datos</h3>
                @if(!empty($pf['name']))
                    <p class="mp-co-hint" style="margin:0 0 4px">Datos de tu cuenta ya cargados. Revísalos y sigue.</p>
                @endif
                <div class="mp-co-doc">
                    <div>
                        <label for="coDocType">Documento</label>
                        <select id="coDocType" name="customer_doc_type">
                            @foreach(['DNI','RUC','CE','Pasaporte'] as $t)
                                <option value="{{ $t }}" {{ $selDoc === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="coDocNum">Número <span class="mp-co-opt" data-doc-hint>· completa tu nombre solo</span></label>
                        <input id="coDocNum" type="text" name="customer_doc_number" maxlength="20" inputmode="numeric" autocomplete="off" value="{{ old('customer_doc_number', $pf['doc_number'] ?? '') }}">
                        <div class="mp-co-doc-status" data-doc-status aria-live="polite"></div>
                        @error('customer_doc_number')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror
                    </div>
                </div>

                <label for="coName">Nombre completo o razón social *</label>
                <input id="coName" type="text" name="customer_name" required maxlength="180" autocomplete="name" value="{{ old('customer_name', $pf['name'] ?? '') }}">
                @error('customer_name')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror

                <label for="coPhone">WhatsApp * <span class="mp-co-opt">· aquí te confirman</span></label>
                <input id="coPhone" type="tel" name="customer_phone" required maxlength="40" inputmode="numeric" autocomplete="tel" placeholder="9XX XXX XXX" value="{{ old('customer_phone', $pf['phone'] ?? '') }}">
                @error('customer_phone')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror
            </section>

            <section class="mp-co-card mp-co-step">
                <h3><span class="mp-co-step__n">2</span> ¿Cómo lo recibes?</h3>

                <label for="coPlace">¿A qué distrito va? <span class="mp-co-opt" data-place-opt hidden>(no hace falta si recoges)</span></label>
                <div class="mp-co-place">
                    <input id="coPlace" type="text" autocomplete="off" placeholder="Escribe tu distrito, p. ej. Miraflores o Talara"
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="coPlaceList" data-place-input>
                    <ul id="coPlaceList" class="mp-co-place__list" role="listbox" hidden data-place-list></ul>
                </div>
                @error('delivery_district')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror
                {{-- Almacen del ubigeo: ocultos pero vivos (los usa el envio por
                     zona y el autocompletado del RUC). El código de 6 cifras lo
                     pone la cotización; el servidor lo vuelve a resolver. --}}
                <div class="mp-co-ubigeo-store" aria-hidden="true">
                    <select name="delivery_department" data-ubigeo="dep" data-old="{{ old('delivery_department', $pf['department'] ?? '') }}" tabindex="-1"><option value="">—</option></select>
                    <select name="delivery_province" data-ubigeo="prov" data-old="{{ old('delivery_province', $pf['province'] ?? '') }}" tabindex="-1" disabled><option value="">—</option></select>
                    <select name="delivery_district" data-ubigeo="dist" data-old="{{ old('delivery_district', $pf['district'] ?? '') }}" tabindex="-1" disabled><option value="">—</option></select>
                    <input type="hidden" name="delivery_ubigeo" value="{{ old('delivery_ubigeo') }}" data-ubigeo-code>
                </div>

                {{-- Una tarjeta de opciones por tienda del carrito (las pinta la
                     cotización). Sin JS no hay cómo elegir: el checkout lo exige. --}}
                <div class="mp-co-ship" data-ship-root aria-live="polite">
                    <p class="mp-co-hint">Escribe tu distrito para ver cómo te llega cada pedido.</p>
                </div>

                <div data-address-wrap hidden>
                    <label for="coAddr">Dirección de entrega *</label>
                    <input id="coAddr" type="text" name="delivery_address" maxlength="500" autocomplete="street-address"
                           placeholder="Ej. Av. Arequipa 123, dpto 4B"
                           value="{{ old('delivery_address', $pf['address'] ?? '') }}">
                    @error('delivery_address')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror
                </div>

                <details class="mp-co-notes" {{ $moreOpen ? 'open' : '' }}>
                    <summary>+ Más opciones (email, referencia)</summary>
                    <label for="coEmail">Email <span class="mp-co-opt">(opcional)</span></label>
                    <input id="coEmail" type="email" name="customer_email" maxlength="180" autocomplete="email" placeholder="Para tu comprobante" value="{{ old('customer_email', $pf['email'] ?? '') }}">
                    @error('customer_email')<div class="mp-co-field-err is-server">{{ $message }}</div>@enderror
                    <label for="coNotes">Referencia o indicaciones <span class="mp-co-opt">(opcional)</span></label>
                    <textarea id="coNotes" name="delivery_notes" rows="2" maxlength="1000" placeholder="Frente al parque, tocar el timbre 2, horario…">{{ old('delivery_notes', $pf['notes'] ?? '') }}</textarea>
                </details>
            </section>

            <div class="mp-co-foot">
                @if($paysOnline ?? false)
                    <p class="mp-co-foot__pay"><span class="mp-co-paytag mp-co-paytag--online">💳 Pago en línea</span> Al confirmar te llevamos a Mercado Pago para pagar con tarjeta, Yape u otros medios.</p>
                @else
                    <p class="mp-co-foot__pay"><span class="mp-co-paytag">✓ Sin pago adelantado</span> Pagas a cada tienda cuando te escribe (Yape, Plin, transferencia o contraentrega).</p>
                @endif
                <label class="mp-co-optin">
                    <input type="checkbox" name="accepts_marketing" value="1" {{ old('accepts_marketing') ? 'checked' : '' }}>
                    <span>Quiero recibir ofertas de ebaemy y sus tiendas por WhatsApp o email. Puedo darme de baja cuando quiera.</span>
                </label>
            </div>
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
                <div class="mp-co-store-block" data-store-block data-hostname-id="{{ $store['hostname_id'] }}" data-subtotal="{{ $store['subtotal'] }}">
                    <div class="mp-co-pk-head">
                        @if($stores->count() > 1)<span class="mp-co-pk-tag">Paquete {{ $loop->iteration }}</span>@endif
                        <span class="name">Lo envía {{ $store['tenant_name'] }}</span>
                    </div>
                    @foreach($store['items'] as $line)
                        <div class="mp-co-line">
                            <span>{{ $line['quantity'] }}× {{ \Illuminate\Support\Str::limit($line['title'] . (!empty($line['variant_name']) ? ' · ' . $line['variant_name'] : ''), 44) }}</span>
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
                        <span class="mp-co-pk-ship__v" data-ship-text>Elige cómo lo recibes</span>
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
                <div class="mp-co-line"><span>Envío</span><span data-ship-total style="color:#6b7280">Elige cómo lo recibes</span></div>
                <div class="total"><span><strong>Total estimado</strong></span><span class="v" data-summary-total>S/ {{ number_format($totalAfterPlat, 2) }}</span></div>
                <p class="mp-co-total-note" data-ship-note>Elige cómo recibes cada pedido para sumar el envío.</p>
            </div>

            <button type="submit" class="mp-co-submit">{{ ($paysOnline ?? false) ? 'Ir a pagar →' : 'Confirmar pedido →' }}</button>

            {{-- Que pasa despues, en positivo: antes era un recuadro amarillo
                 de «advertencia» que asustaba justo al confirmar. --}}
            <div class="mp-co-next">
                <p class="mp-co-next__title">Después de confirmar</p>
                <ol>
                    <li>{{ $stores->count() > 1 ? 'Cada tienda te escribe' : 'La tienda te escribe' }} por WhatsApp para coordinar {{ ($paysOnline ?? false) ? 'la entrega' : 'el pago y la entrega' }}.</li>
                    @if($paysOnline ?? false)
                        <li>Pagas en línea con Mercado Pago; {{ $stores->count() > 1 ? 'las tiendas reciben' : 'la tienda recibe' }} tu pedido cuando se confirma el pago.</li>
                    @else
                        <li>Pagas {{ $stores->count() > 1 ? 'a cada tienda' : 'a la tienda' }} con Yape, Plin, transferencia o contraentrega, según acepte.</li>
                    @endif
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
            <span class="mp-co-sticky__label">Total con envío</span>
            <span class="mp-co-sticky__value" data-summary-total-sticky>S/ {{ number_format($totalAfterPlat, 2) }}</span>
            <span class="mp-co-sticky__ship" data-sticky-ship>Elige cómo lo recibes</span>
        </div>
        <button type="submit" class="mp-co-sticky__btn">{{ ($paysOnline ?? false) ? 'Ir a pagar →' : 'Confirmar pedido →' }}</button>
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
/* ¿Cómo lo recibes? — una tarjeta de opciones por tienda */
.mp-co-paytag--online { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.mp-co-ship { margin-top: 12px; display: grid; gap: 14px; }
.mp-co-ship fieldset { border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 14px 14px; margin: 0; min-width: 0; }
.mp-co-ship legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: #115e59; }
.mp-co-opts { display: grid; gap: 8px; margin-top: 4px; }
.mp-co-card label.mp-co-opt { display: flex; gap: 10px; align-items: flex-start; margin: 0; padding: 12px; border: 1.5px solid #e5e7eb; border-radius: 12px; cursor: pointer; background: #fff; font-weight: 400; color: #111827; font-size: 14px; }
.mp-co-card label.mp-co-opt.is-checked { border-color: var(--mp-primary, #0f8a82); background: #f0fdfa; box-shadow: 0 0 0 3px rgba(15,138,130,.08); }
.mp-co-opt input { margin-top: 2px; }
.mp-co-opt__body { flex: 1; min-width: 0; display: grid; gap: 2px; }
.mp-co-opt__top { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }
.mp-co-opt__title { font-weight: 700; }
.mp-co-opt__price { font-weight: 800; color: #0c6b65; text-align: right; }
.mp-co-opt__price.is-pending { color: #b45309; font-size: 13px; }
.mp-co-opt__meta { font-size: 12.5px; color: #6b7280; line-height: 1.35; }
.mp-co-office { margin-top: 8px; }
.mp-co-office label { margin-top: 4px; }
.mp-co-final { margin-top: 10px; padding: 9px 11px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; color: #065f46; font-size: 13px; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
.mp-co-final:empty { display: none; }
.mp-co-ship-note { margin: 8px 0 0; padding: 8px 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; color: #92400e; font-size: 12.5px; line-height: 1.4; }
.mp-co-sticky__ship { font-size: 11px; color: #6b7280; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mp-co-field-err.is-server { margin-top: 4px; }
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
    const PAYS_ONLINE = @json((bool) ($paysOnline ?? false));

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

        // Envío elegido por tienda (window.__mpShipSel lo llena «¿Cómo lo recibes?»):
        // {hostname_id: {option, line}}. Sin elección no se suma nada.
        const chosen = window.__mpShipSel || {};
        const shipTotalEl = document.querySelector('[data-ship-total]');
        const shipNoteEl  = document.querySelector('[data-ship-note]');
        const shipStickyEl = document.querySelector('[data-sticky-ship]');
        let ship = 0, pending = 0, missing = 0;
        document.querySelectorAll('[data-store-block]').forEach(el => {
            const txt = el.querySelector('[data-ship-text]');
            const det = el.querySelector('[data-ship-detail]');
            const s = chosen[el.dataset.hostnameId];
            if (!s || !s.option) {
                missing++;
                if (txt) { txt.textContent = 'Elige cómo lo recibes'; txt.className = 'mp-co-pk-ship__v'; }
                if (det) det.textContent = '';
                return;
            }
            const r = s.option;
            if (txt) { txt.textContent = r.price; txt.className = 'mp-co-pk-ship__v' + (r.free ? ' is-free' : '') + (r.pending ? ' is-pending' : ''); }
            if (det) det.textContent = s.line || '';
            if (r.amount !== null && r.amount !== undefined) ship += parseFloat(r.amount);
            if (r.pending) pending++;
        });

        const total = Math.max(0, subtotal - discount - plat) + ship;

        const fmt = n => 'S/ ' + n.toFixed(2);
        subtotalEl.textContent = fmt(subtotal);
        if (discount > 0) {
            discRow.style.display = '';
            discEl.textContent = '-' + fmt(discount);
        } else {
            discRow.style.display = 'none';
        }
        const shipText = missing ? 'Elige cómo lo recibes'
            : (ship > 0 ? fmt(ship) + (pending ? ' + por confirmar' : '') : (pending ? 'Por confirmar' : 'Gratis'));
        if (shipTotalEl) shipTotalEl.textContent = shipText;
        if (shipStickyEl) shipStickyEl.textContent = missing ? 'Elige cómo lo recibes' : 'Envío: ' + shipText;
        if (shipNoteEl) {
            shipNoteEl.textContent = missing ? 'Elige cómo recibes cada pedido para sumar el envío.'
                : (pending ? 'Parte del envío te lo confirma la tienda por WhatsApp.' + (PAYS_ONLINE ? '' : ' Pagas a cada tienda al coordinar.')
                           : (PAYS_ONLINE ? 'El envío lo coordinas con cada tienda.' : 'Pagas a cada tienda cuando te escriba para coordinar la entrega.'));
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

    // ── ¿Cómo lo recibes?: tarjetas por tienda ─────────────────────────────
    // Cotiza en el servidor (marketplace.checkout.quote) cada vez que cambia el
    // distrito y pinta, por tienda: recojo, delivery Lima/Callao o agencia a
    // provincia, con costo, plazo y oficina de Shalom. Lo elegido queda en
    // window.__mpShipSel para el resumen y se envía como ship[hostname][mode].
    (function () {
        const QUOTE_URL = @json(route('marketplace.checkout.quote'));
        const root = document.querySelector('[data-ship-root]');
        if (!root) return;
        const OLD_SHIP = @json(old('ship', []));
        const PREFILL_MODE = @json($prefill['mode'] ?? null);
        const OLD_OFFICE = {};
        Object.keys(OLD_SHIP || {}).forEach(h => { if (OLD_SHIP[h] && OLD_SHIP[h].office_id) OLD_OFFICE[h] = String(OLD_SHIP[h].office_id); });
        const sel = window.__mpShipSel = {};
        const ctxs = {};
        const addrWrap = document.querySelector('[data-address-wrap]');
        const addrEl = document.querySelector('[name="delivery_address"]');
        const ubigeoEl = document.querySelector('[data-ubigeo-code]');
        const placeOpt = document.querySelector('[data-place-opt]');
        let data = null, seq = 0, timer = null;

        function el(tag, cls, text) {
            const n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text !== undefined && text !== null) n.textContent = text;
            return n;
        }
        // La frase que se ve ANTES de pagar y que el servidor guarda tal cual.
        function lineFor(o, officeName) {
            if (o.mode === 'pickup') return 'Recojo en tienda' + (o.where ? ' · ' + o.where : '');
            const parts = [];
            if (o.mode === 'agency') parts.push('Envío a ' + (data.place || 'provincia') + (officeName ? ' · Agencia Shalom ' + officeName : ' por agencia'));
            else parts.push('Delivery a ' + (data.place || 'Lima/Callao'));
            parts.push(o.price);
            if (o.eta) parts.push(o.eta);
            return parts.filter(Boolean).join(' · ');
        }
        function officeNameOf(s) {
            if (!s || !s.officeId || !data || !data.offices) return null;
            const all = (data.offices.offices || []).concat(data.offices.others || []);
            const f = all.find(x => String(x.id) === String(s.officeId));
            return f ? f.name : null;
        }
        function updateAddress() {
            const modes = Object.values(sel).map(s => s.mode);
            const anyDelivery = modes.includes('delivery');
            const allPickup = modes.length > 0 && Object.keys(sel).length === Object.keys(ctxs).length && modes.every(m => m === 'pickup');
            if (addrWrap) addrWrap.hidden = !anyDelivery;
            if (placeOpt) placeOpt.hidden = !allPickup;
        }
        function refreshLine(hid) {
            const c = ctxs[hid], s = sel[hid];
            if (!c) return;
            if (!s || !s.option) { c.final.textContent = ''; s && (s.line = ''); return; }
            s.line = lineFor(s.option, officeNameOf(s));
            c.final.textContent = s.line;
        }
        function buildOffice(c, hid, o, s) {
            const box = c.officeBox;
            box.innerHTML = '';
            const offices = (data.offices && data.offices.offices) || [];
            const others = (data.offices && data.offices.others) || [];
            const lab = el('label', null, 'Oficina de Shalom donde recoges' + (o.office_required ? ' *' : ' (opcional)'));
            lab.setAttribute('for', 'coOffice' + hid);
            const select = document.createElement('select');
            select.id = 'coOffice' + hid;
            select.name = 'ship[' + hid + '][office_id]';
            select.appendChild(new Option(o.office_required ? 'Elige la oficina…' : 'La tienda me confirma la agencia', ''));
            const scopeLabel = { district: 'En tu distrito', province: 'En tu provincia', department: 'En tu departamento' }[data.offices.scope] || 'Cerca de ti';
            [[scopeLabel, offices], ['Otras del departamento', others]].forEach(([title, list]) => {
                if (!list.length) return;
                const g = document.createElement('optgroup');
                g.label = title;
                list.forEach(x => g.appendChild(new Option(x.name + (x.address ? ' — ' + x.address : ''), x.id)));
                select.appendChild(g);
            });
            if (s.officeId) select.value = String(s.officeId);
            if (select.value !== String(s.officeId || '')) s.officeId = null;
            select.addEventListener('change', () => {
                s.officeId = select.value || null;
                select.classList.remove('mp-co-invalid');
                c.err.textContent = '';
                refreshLine(hid);
                if (window.mpRecalcSummary) window.mpRecalcSummary();
            });
            box.appendChild(lab);
            box.appendChild(select);
            c.officeSelect = select;
        }
        function choose(hid, mode) {
            const c = ctxs[hid];
            const o = c.st.options.find(x => x.mode === mode);
            if (!o) return;
            const s = (sel[hid] && sel[hid].mode === mode) ? sel[hid] : (sel[hid] = { mode: mode, officeId: OLD_OFFICE[hid] || null });
            s.option = o;
            c.labels.forEach(l => l.classList.toggle('is-checked', l.dataset.mode === mode));
            c.officeBox.innerHTML = '';
            c.officeSelect = null;
            if (o.needs_office) buildOffice(c, hid, o, s);
            c.note.hidden = !(o.mode !== 'pickup' && !c.st.has_policy);
            c.err.textContent = '';
            refreshLine(hid);
            updateAddress();
            if (window.mpRecalcSummary) window.mpRecalcSummary();
        }
        function render() {
            root.innerHTML = '';
            Object.keys(ctxs).forEach(k => delete ctxs[k]);
            if (ubigeoEl) ubigeoEl.value = data.ubigeo || '';
            data.stores.forEach(st => {
                const hid = String(st.hostname_id);
                const fs = el('fieldset');
                fs.appendChild(el('legend', null, 'Lo envía ' + st.name));
                const opts = el('div', 'mp-co-opts');
                fs.appendChild(opts);
                const c = { st: st, labels: [], officeBox: el('div', 'mp-co-office'), final: el('div', 'mp-co-final'),
                            note: el('p', 'mp-co-ship-note', data.pending_note + '.'), err: el('div', 'mp-co-field-err'), officeSelect: null };
                c.note.hidden = true;

                const modes = st.options.map(o => o.mode);
                // Elección: la que ya tenía → la que quedó tras un error → la de su última compra
                // → la de envío (más rápido comprar con un toque menos). Recojo solo si lo pide.
                let cur = sel[hid] && modes.includes(sel[hid].mode) ? sel[hid].mode : null;
                if (!cur && OLD_SHIP[hid] && modes.includes(OLD_SHIP[hid].mode)) { cur = OLD_SHIP[hid].mode; delete OLD_SHIP[hid].mode; }
                if (!cur && PREFILL_MODE && modes.includes(PREFILL_MODE)) cur = PREFILL_MODE;
                if (!cur) { const ship = st.options.find(o => o.mode !== 'pickup'); if (ship) cur = ship.mode; }
                if (!cur) delete sel[hid];

                st.options.forEach(o => {
                    const lab = el('label', 'mp-co-opt');
                    lab.dataset.mode = o.mode;
                    const r = document.createElement('input');
                    r.type = 'radio'; r.name = 'ship[' + hid + '][mode]'; r.value = o.mode;
                    r.addEventListener('change', () => choose(hid, o.mode));
                    const body = el('span', 'mp-co-opt__body');
                    const top = el('span', 'mp-co-opt__top');
                    top.appendChild(el('span', 'mp-co-opt__title', o.title));
                    top.appendChild(el('span', 'mp-co-opt__price' + (o.pending && (o.amount === null || o.amount === undefined) ? ' is-pending' : ''), o.price));
                    body.appendChild(top);
                    const meta = [o.detail, o.eta].filter(Boolean).join(' · ');
                    if (meta) body.appendChild(el('span', 'mp-co-opt__meta', meta));
                    lab.appendChild(r); lab.appendChild(body);
                    opts.appendChild(lab);
                    c.labels.push(lab);
                });
                if (!st.options.some(o => o.mode !== 'pickup')) {
                    opts.appendChild(el('p', 'mp-co-hint', 'Escribe tu distrito para ver el envío a domicilio o por agencia.'));
                }
                fs.appendChild(c.officeBox);
                fs.appendChild(c.note);
                fs.appendChild(c.final);
                fs.appendChild(c.err);
                root.appendChild(fs);
                ctxs[hid] = c;
                if (cur) {
                    const r = c.labels.find(l => l.dataset.mode === cur).querySelector('input');
                    r.checked = true;
                    choose(hid, cur);
                }
            });
            updateAddress();
            if (window.mpRecalcSummary) window.mpRecalcSummary();
        }
        function go() {
            const v = (k) => ((document.querySelector('[data-ubigeo="' + k + '"]') || {}).value || '');
            const q = new URLSearchParams({ department: v('dep'), province: v('prov'), district: v('dist') });
            const my = ++seq;
            fetch(QUOTE_URL + '?' + q.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => {
                    if (my !== seq) return;   // llegó tarde: ya hay una consulta más nueva
                    if (!d || !d.ok) { root.innerHTML = ''; root.appendChild(el('p', 'mp-co-hint', (d && d.message) || 'No pudimos cargar las opciones de envío.')); data = null; return; }
                    data = d;
                    render();
                })
                .catch(() => {
                    if (my !== seq) return;
                    data = null;
                    root.innerHTML = '';
                    root.appendChild(el('p', 'mp-co-field-err', 'No pudimos cargar las opciones de envío. Revisa tu conexión y recarga la página.'));
                });
        }
        window.__mpShipRefresh = function () { clearTimeout(timer); timer = setTimeout(go, 80); };
        // Lo que Confirmar necesita saber: ¿falta elegir algo? Pinta el error en la tienda.
        window.__mpShipCheck = function () {
            const errs = [];
            if (!data) { errs.push({ target: root, msg: 'Aún no cargan las opciones de envío.' }); return errs; }
            Object.keys(ctxs).forEach(hid => {
                const c = ctxs[hid], s = sel[hid];
                c.err.textContent = '';
                if (!s || !s.option) {
                    c.err.textContent = data.zone ? 'Elige cómo recibes este pedido.' : 'Elige tu distrito arriba para ver el envío, o elige recojo en tienda.';
                    errs.push({ target: c.labels[0] ? c.labels[0].querySelector('input') : root, msg: c.err.textContent });
                } else if (s.option.office_required && !s.officeId) {
                    c.err.textContent = 'Elige la oficina de Shalom donde recogerás tu pedido.';
                    if (c.officeSelect) c.officeSelect.classList.add('mp-co-invalid');
                    errs.push({ target: c.officeSelect || root, msg: c.err.textContent });
                }
            });
            return errs;
        };
        window.__mpShipMode = function () {
            const modes = Object.values(sel).map(s => s.mode);
            return {
                anyDelivery: modes.includes('delivery'),
                allPickup: Object.keys(ctxs).length > 0 && modes.length === Object.keys(ctxs).length && modes.every(m => m === 'pickup'),
            };
        };
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

        // Cambió el destino: se vuelve a cotizar en el servidor (zona Lima/provincia,
        // costo, plazo y oficinas). La zona ya no se deduce aquí: la decide
        // StoreShipping::isLima sobre el ubigeo de 6 cifras.
        function onZone() {
            if (window.__mpShipRefresh) window.__mpShipRefresh();
            else if (window.mpRecalcSummary) window.mpRecalcSummary();
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
            syncPlace();
        };

        // ── Buscador de distrito: un solo campo en vez de tres desplegables ──
        const placeIn   = document.querySelector('[data-place-input]');
        const placeList = document.querySelector('[data-place-list]');
        const fold = (t) => (t || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        const title = (t) => (t || '').toString().toLowerCase().replace(/(^|[\s(-])\S/g, c => c.toUpperCase());
        let index = [], hits = [], active = -1;
        // Algunos distritos vienen en MAYUSCULAS en el dataset: solo se muestran
        // en formato normal; el valor que se guarda no cambia.
        const pretty = (t) => (t && t === t.toUpperCase()) ? title(t).replace(/ (De|Del|La|Las|Los|Y) /g, m => m.toLowerCase()) : t;

        function syncPlace() {
            if (!placeIn || !distSel.value) return;
            placeIn.value = pretty(distSel.value) + ', ' + provSel.value
                + (norm(provSel.value) !== norm(depSel.value) ? ' (' + title(depSel.value) + ')' : '');
        }
        function closeList() {
            if (!placeList) return;
            placeList.hidden = true; placeIn.setAttribute('aria-expanded', 'false'); active = -1;
        }
        function render() {
            placeList.innerHTML = '';
            if (!hits.length) {
                placeList.innerHTML = '<li class="is-empty">No encontramos ese distrito. Revisa cómo se escribe.</li>';
            }
            hits.forEach((h, i) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', i === active ? 'true' : 'false');
                li.innerHTML = '<strong></strong><small></small>';
                li.firstChild.textContent = pretty(h.dist);
                li.lastChild.textContent = h.prov + ', ' + title(h.dep);
                li.addEventListener('mousedown', (e) => { e.preventDefault(); pick(h); });
                placeList.appendChild(li);
            });
            placeList.hidden = false;
            placeIn.setAttribute('aria-expanded', 'true');
        }
        // Orden: nombre exacto, luego empieza por, luego provincia/departamento;
        // dentro de cada grupo, Lima y Callao primero (de donde vienen casi
        // todos los pedidos: «Miraflores» es el de Lima, no el de Arequipa).
        function search() {
            const q = fold(placeIn.value);
            if (q.length < 2) { closeList(); return; }
            if (!index.length) {
                placeList.innerHTML = '<li class="is-empty">Cargando distritos…</li>';
                placeList.hidden = false;
                return;
            }
            const found = [];
            for (const e of index) {
                const rank = e.d === q ? 0 : (e.d.startsWith(q) ? 1 : (e.d.includes(' ' + q) ? 2 : (e.all.includes(q) ? 3 : -1)));
                if (rank >= 0) found.push([rank * 2 + (e.lima ? 0 : 1), e]);
            }
            found.sort((a, b) => a[0] - b[0]);
            hits = found.slice(0, 8).map(x => x[1]);
            active = hits.length ? 0 : -1;
            render();
        }
        function pick(h) {
            window.__mpUbigeoSet(h.dep, h.prov, h.dist);
            closeList();
            if (window.__mpClearErr) window.__mpClearErr(placeIn);
        }
        if (placeIn && placeList) {
            placeIn.addEventListener('input', () => {
                // Escribir de nuevo anula la eleccion anterior.
                if (distSel.value) { depSel.value = ''; onDep(); onZone(); }
                search();
            });
            placeIn.addEventListener('keydown', (e) => {
                if (placeList.hidden || !hits.length) return;
                if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % hits.length; render(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + hits.length) % hits.length; render(); }
                else if (e.key === 'Enter') { e.preventDefault(); if (active >= 0) pick(hits[active]); }
                else if (e.key === 'Escape') { closeList(); }
            });
            placeIn.addEventListener('blur', () => setTimeout(closeList, 120));
        }

        fetch(URL, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                data = d;
                data.forEach(dep => dep.p.forEach(pr => pr.d.forEach(dist => {
                    const lima = norm(pr.n) === 'LIMA' || norm(dep.n) === 'CALLAO';
                    index.push({ dep: dep.n, prov: pr.n, dist: dist, lima: lima, d: fold(dist), all: fold(dist + ' ' + pr.n + ' ' + dep.n) });
                })));
                // Si el comprador ya estaba escribiendo mientras cargaba la lista.
                if (placeIn && document.activeElement === placeIn) search();
                data.forEach(dep => depSel.appendChild(opt(dep.n)));
                if (depSel.dataset.old) { depSel.value = depSel.dataset.old; if (depSel.value) onDep(); }
                onZone();
                syncPlace();
            })
            .catch(() => { onZone(); /* sin lista de distritos: solo recojo hasta que cargue */ });
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
        // El error de servidor (tras un POST rechazado) vive pegado al campo; al
        // corregir se quita para que no conviva con el del navegador.
        function dropServer(input) {
            const anchor = input.closest('.mp-co-place') || input;
            let n = anchor.nextElementSibling;
            while (n && n.classList.contains('mp-co-field-err')) {
                const next = n.nextElementSibling;
                if (n.classList.contains('is-server')) n.remove();
                n = next;
            }
        }
        function setErr(input, msg) {
            if (!input) return;
            dropServer(input);
            input.classList.add('mp-co-invalid');
            errBox(input).textContent = msg;
        }
        function clearErr(input) {
            if (!input) return;
            dropServer(input);
            input.classList.remove('mp-co-invalid');
            if (boxes.has(input)) boxes.get(input).textContent = '';
        }
        window.__mpClearErr = clearErr;

        // Mismas reglas que el servidor (MarketplaceCheckoutRules): el documento
        // es opcional salvo con RUC; CE y pasaporte llevan letras.
        function docError(type, raw) {
            const t = (type || '').toUpperCase();
            const n = (t === 'CE' || t === 'PASAPORTE') ? raw.replace(/[^A-Za-z0-9]/g, '').toUpperCase() : raw.replace(/\D+/g, '');
            if (!n) return t === 'RUC' ? 'Ingresa el RUC de 11 dígitos.' : null;
            if (t === 'DNI') return /^\d{8}$/.test(n) ? null : 'El DNI debe tener 8 dígitos.';
            if (t === 'RUC') return /^\d{11}$/.test(n) ? null : 'El RUC debe tener 11 dígitos.';
            if (t === 'CE') return /^[A-Z0-9]{9,12}$/.test(n) ? null : 'El carné de extranjería debe tener entre 9 y 12 letras o números.';
            if (t === 'PASAPORTE') return /^[A-Z0-9]{6,12}$/.test(n) ? null : 'El pasaporte debe tener entre 6 y 12 letras o números.';
            return null;
        }

        // El teclado numérico solo sirve para DNI y RUC; CE y pasaporte llevan letras.
        (function () {
            const t = $('[name="customer_doc_type"]'), n = $('[name="customer_doc_number"]');
            if (!t || !n) return;
            const sync = () => { n.inputMode = (t.value === 'DNI' || t.value === 'RUC') ? 'numeric' : 'text'; n.maxLength = (t.value === 'DNI' || t.value === 'RUC') ? 11 : 20; };
            t.addEventListener('change', () => { sync(); clearErr(n); });
            sync();
        })();

        function validate() {
            const errors = [];
            const name  = $('[name="customer_name"]');
            const dtype = $('[name="customer_doc_type"]');
            const dnum  = $('[name="customer_doc_number"]');
            const phone = $('[name="customer_phone"]');
            const email = $('[name="customer_email"]');
            const addr  = $('[name="delivery_address"]');
            const place = $('[data-place-input]');
            const dist  = $('[name="delivery_district"]');

            [name, dnum, phone, email, addr, place].forEach(clearErr);

            if (!name.value.trim() || name.value.trim().length < 3) {
                setErr(name, 'Ingresa el nombre completo o razón social.');
                errors.push(name);
            }

            const de = docError(dtype && dtype.value, dnum.value || '');
            if (de) { setErr(dnum, de); errors.push(dnum); }

            // Teléfono: requerido, celular peruano 9 dígitos (acepta prefijo 51).
            let pv = (phone.value || '').replace(/\D+/g, '');
            if (pv.length === 11 && pv.startsWith('51')) pv = pv.slice(2);
            if (!pv) { setErr(phone, 'Ingresa tu celular o WhatsApp.'); errors.push(phone); }
            else if (!/^9\d{8}$/.test(pv)) { setErr(phone, 'Debe ser un celular de 9 dígitos que empiece en 9 (con o sin +51).'); errors.push(phone); }

            // Email: opcional, pero válido si se llena.
            if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                const det = email.closest('details'); if (det) det.open = true;
                setErr(email, 'Revisa el formato del correo.');
                errors.push(email);
            }

            // ¿Cómo lo recibes? Una elección por tienda. Si TODO se recoge no hace
            // falta distrito; con delivery sí hace falta la dirección de calle.
            const shipErrs = window.__mpShipCheck ? window.__mpShipCheck() : [];
            const mode = window.__mpShipMode ? window.__mpShipMode() : { anyDelivery: true, allPickup: false };
            if (place && !mode.allPickup && (!dist || !dist.value)) {
                setErr(place, 'Escribe tu distrito y elígelo de la lista: así sabemos a dónde va y cuánto cuesta.');
                errors.push(place);
            }
            if (mode.anyDelivery && (!addr.value.trim() || addr.value.trim().length < 5)) {
                setErr(addr, 'Ingresa la dirección de entrega (calle, número y distrito).');
                errors.push(addr);
            }
            shipErrs.forEach(e => { if (e.target) errors.push(e.target); });

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
