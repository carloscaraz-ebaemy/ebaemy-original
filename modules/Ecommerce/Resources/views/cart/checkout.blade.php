@extends('ecommerce::layouts.layout_ecommerce_cart.index')
@section('content')

@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $itemsBasePath = asset('storage/uploads/items');
    $paypalScriptSrc = null;
    if (!empty($configuration->script_paypal)) {
        $storedPaypal = html_entity_decode($configuration->script_paypal, ENT_QUOTES, 'UTF-8');
        if (preg_match('/<script[^>]*\ssrc=["\']([^"\']+)["\'][^>]*><\/script>/i', $storedPaypal, $matches)) {
            $storedPaypal = $matches[1];
        }
        $parsedPaypal = parse_url($storedPaypal);
        $paypalHost = strtolower($parsedPaypal['host'] ?? '');
        $paypalPath = $parsedPaypal['path'] ?? '';
        if (($parsedPaypal['scheme'] ?? '') === 'https' &&
            in_array($paypalHost, ['www.paypal.com', 'paypal.com'], true) &&
            str_ends_with($paypalPath, '/sdk/js')) {
            $paypalScriptSrc = $storedPaypal;
        }
    }
@endphp

{{-- ── STEPPER (Paso 2 activo) ─────────────────────────── --}}
<div class="ec-checkout-stepper" aria-label="Pasos del proceso de compra">
    <div class="ec-step ec-step--done">
        <div class="ec-step__num" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span class="ec-step__label">Mi carrito</span>
    </div>
    <div class="ec-step__line ec-step__line--done"></div>
    <div class="ec-step ec-step--active">
        <div class="ec-step__num" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
        </div>
        <span class="ec-step__label">Mis datos</span>
    </div>
    <div class="ec-step__line"></div>
    <div class="ec-step">
        <div class="ec-step__num" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
        </div>
        <span class="ec-step__label">Pago</span>
    </div>
</div>

<div class="row checkout-container" id="app" style="margin-top:20px;">

    {{-- ══ LEFT: FORMULARIOS POR PASOS ══ --}}
    <div class="col-lg-7">

        {{-- Progress bar visual --}}
        <div class="ec-progress-bar">
            <div class="ec-progress-step" :class="{ 'ec-progress-step--active': step >= 1, 'ec-progress-step--done': step > 1 }" @click="goToStep(1)">
                <span class="ec-progress-num">1</span>
                <span class="ec-progress-label">Contacto</span>
            </div>
            <div class="ec-progress-line" :class="{ 'ec-progress-line--done': step > 1 }"></div>
            <div class="ec-progress-step" :class="{ 'ec-progress-step--active': step >= 2, 'ec-progress-step--done': step > 2 }" @click="step > 1 ? goToStep(2) : null">
                <span class="ec-progress-num">2</span>
                <span class="ec-progress-label">Entrega</span>
            </div>
            <div class="ec-progress-line" :class="{ 'ec-progress-line--done': step > 2 }"></div>
            <div class="ec-progress-step" :class="{ 'ec-progress-step--active': step >= 3, 'ec-progress-step--done': step > 3 }" @click="step > 2 ? goToStep(3) : null">
                <span class="ec-progress-num">3</span>
                <span class="ec-progress-label">Comprobante</span>
            </div>
            <div class="ec-progress-line" :class="{ 'ec-progress-line--done': step > 3 }"></div>
            <div class="ec-progress-step" :class="{ 'ec-progress-step--active': step >= 4 }">
                <span class="ec-progress-num">4</span>
                <span class="ec-progress-label">Pago</span>
            </div>
        </div>

        {{-- ═══ PASO 1: CONTACTO ═══ --}}
        <div class="ec-checkout-card" v-show="step === 1">
            <div class="ec-checkout-card__header">
                <span class="ec-checkout-step-num">1</span>
                <span>Contacto</span>
            </div>
            <form autocomplete="off" action="#" class="ec-checkout-form">
                @guest('ecommerce')
                <div class="row">
                    <div class="col-md-7">
                        <div class="ec-field ec-guest-notice" :class="{'ec-field--error': errors.guest_email}">
                            <label>Correo electrónico <span class="ec-field-badge-guest">Invitado</span></label>
                            <input v-model="guest_email" type="email" autocomplete="email" class="ec-field__input" placeholder="tu@correo.com">
                            <small class="ec-field__error" v-if="errors.guest_email">@{{ errors.guest_email }}</small>
                            <p class="ec-guest-login-hint">¿Ya tienes cuenta? <a href="{{ route('tenant_ecommerce_login') }}">Inicia sesión</a></p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="ec-field" :class="{'ec-field--error': errors.telefono}">
                            <label>Teléfono</label>
                            <input v-model="form_contact.telephone" type="tel" autocomplete="tel" class="ec-field__input" placeholder="987 654 321">
                            <small class="ec-field__error" v-if="errors.telefono" v-text="errors.telefono[0]"></small>
                        </div>
                    </div>
                </div>
                @else
                <div class="row">
                    <div class="col-md-6">
                        <div class="ec-field" :class="{'ec-field--error': errors.telefono}">
                            <label>Teléfono</label>
                            <input v-model="form_contact.telephone" type="tel" autocomplete="tel" class="ec-field__input" placeholder="987 654 321">
                            <small class="ec-field__error" v-if="errors.telefono" v-text="errors.telefono[0]"></small>
                        </div>
                    </div>
                </div>
                @endguest
                <div class="ec-step-nav">
                    <a href="{{ route('tenant_detail_cart') }}" class="ec-step-btn ec-step-btn--back">← Volver al carrito</a>
                    <button type="button" class="ec-step-btn ec-step-btn--next" @click="nextStep(1)">Continuar a Entrega →</button>
                </div>
            </form>
        </div>

        {{-- Resumen paso 1 (colapsado cuando paso > 1) --}}
        <div class="ec-step-summary" v-show="step > 1" @click="goToStep(1)">
            <span class="ec-step-summary__num">1</span>
            <div class="ec-step-summary__info">
                <strong>Contacto</strong>
                <span>@{{ guest_email || (user && user.email ? user.email : '') }} · @{{ form_contact.telephone || 'Sin teléfono' }}</span>
            </div>
            <span class="ec-step-summary__edit">Editar</span>
        </div>

        {{-- ═══ PASO 2: ENTREGA ═══ --}}
        <div class="ec-checkout-card" v-show="step === 2">
            <div class="ec-checkout-card__header">
                <span class="ec-checkout-step-num">2</span>
                <span>Entrega</span>
            </div>
            <div class="ec-checkout-form">

                {{-- Tipo de entrega --}}
                <div class="ec-field" style="margin-bottom:16px">
                    <label>¿Cómo deseas recibir tu pedido?</label>
                    <div class="ec-delivery-options">
                        <label class="ec-delivery-opt" :class="{ 'ec-delivery-opt--active': deliveryType === 'delivery' }" @click="deliveryType = 'delivery'">
                            <span class="ec-delivery-opt__icon">🚚</span>
                            <span class="ec-delivery-opt__text">
                                <strong>Envío a domicilio</strong>
                                <small>Lima y provincias</small>
                            </span>
                        </label>
                        <label class="ec-delivery-opt" :class="{ 'ec-delivery-opt--active': deliveryType === 'pickup' }" @click="deliveryType = 'pickup'">
                            <span class="ec-delivery-opt__icon">🏪</span>
                            <span class="ec-delivery-opt__text">
                                <strong>Recojo en tienda</strong>
                                <small>Gratis — sin costo de envío</small>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Dirección (solo si envío a domicilio) --}}
                <template v-if="deliveryType === 'delivery'">
                    <div class="ec-field" style="margin-bottom:14px">
                        <label>Buscar ubicación</label>
                        <div style="position:relative">
                            <input type="text" id="ubigeo-search" class="ec-field__input" placeholder="Escribe tu distrito, ej: Miraflores" autocomplete="off" style="padding-left:36px">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" style="position:absolute;left:12px;top:50%;transform:translateY(-50%)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="ec-field" :class="{'ec-field--error': errors.department_id}">
                                <label>Departamento</label>
                                <select v-model="ubigeo.department_id" class="ec-field__select ec-field__input" @change="loadProvinces">
                                    <option value="">Seleccionar</option>
                                    <option v-for="d in ubigeo.departments" :key="d.id" :value="d.id">@{{ d.description }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ec-field" :class="{'ec-field--error': errors.province_id}">
                                <label>Provincia</label>
                                <select v-model="ubigeo.province_id" class="ec-field__select ec-field__input" @change="loadDistricts" :disabled="!ubigeo.provinces.length">
                                    <option value="">Seleccionar</option>
                                    <option v-for="p in ubigeo.provinces" :key="p.id" :value="p.id">@{{ p.description }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ec-field" :class="{'ec-field--error': errors.district_id}">
                                <label>Distrito <span style="color:#ef4444">*</span></label>
                                <select v-model="ubigeo.district_id" class="ec-field__select ec-field__input" :disabled="!ubigeo.districts.length">
                                    <option value="">Seleccionar</option>
                                    <option v-for="d in ubigeo.districts" :key="d.id" :value="d.id">@{{ d.description }}</option>
                                </select>
                                <small class="ec-field__error" v-if="errors.district_id">Selecciona tu distrito.</small>
                            </div>
                        </div>
                    </div>

                    <div class="ec-field" :class="{'ec-field--error': errors.address}">
                        <label>Dirección completa</label>
                        <textarea v-model="form_contact.address" class="ec-field__input" placeholder="Calle, número, urbanización, referencia..." rows="2" style="resize:vertical"></textarea>
                        <small class="ec-field__error" v-if="errors.address" v-text="errors.address[0]"></small>
                    </div>
                </template>

                {{-- Recojo en tienda --}}
                <template v-if="deliveryType === 'pickup'">
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;display:flex;gap:10px;align-items:flex-start">
                        <span style="font-size:20px">📍</span>
                        <div>
                            <strong style="color:#166534">Dirección de recojo</strong>
                            <p style="margin:4px 0 0;color:#374151;font-size:13px">{{ $configuration->information_contact_address ?? 'Consultar dirección en tienda' }}</p>
                            <p style="margin:4px 0 0;color:#6b7280;font-size:12px">Horario: Lunes a Sábado 9:00am - 6:00pm</p>
                        </div>
                    </div>
                </template>

                <div class="ec-step-nav">
                    <button type="button" class="ec-step-btn ec-step-btn--back" @click="goToStep(1)">← Contacto</button>
                    <button type="button" class="ec-step-btn ec-step-btn--next" @click="nextStep(2)">Continuar a Comprobante →</button>
                </div>
            </div>
        </div>

        {{-- Resumen paso 2 (colapsado) --}}
        <div class="ec-step-summary" v-show="step > 2" @click="goToStep(2)">
            <span class="ec-step-summary__num">2</span>
            <div class="ec-step-summary__info">
                <strong>Entrega</strong>
                <span>@{{ deliveryType === 'pickup' ? 'Recojo en tienda' : (form_contact.address || 'Sin dirección') }}</span>
            </div>
            <span class="ec-step-summary__edit">Editar</span>
        </div>

        {{-- ═══ PASO 3: COMPROBANTE (OPCIONAL) ═══ --}}
        <div class="ec-checkout-card" v-show="step === 3">
            <div class="ec-checkout-card__header">
                <span class="ec-checkout-step-num">3</span>
                <span>Comprobante</span>
                <small style="color:#6b7280;font-weight:400;margin-left:4px">(opcional)</small>
            </div>
            <div class="ec-checkout-form">

                {{-- Toggle comprobante fiscal --}}
                <div class="ec-invoice-toggle">
                    <label class="ec-toggle-card" :class="{ 'ec-toggle-card--active': !wantsInvoice }" @click="wantsInvoice = false">
                        <span class="ec-toggle-card__radio" :class="{ 'ec-toggle-card__radio--on': !wantsInvoice }"></span>
                        <span class="ec-toggle-card__text">
                            <strong>Sin comprobante fiscal</strong>
                            <small>Nota de venta (sin datos adicionales)</small>
                        </span>
                    </label>
                    <label class="ec-toggle-card" :class="{ 'ec-toggle-card--active': wantsInvoice }" @click="wantsInvoice = true">
                        <span class="ec-toggle-card__radio" :class="{ 'ec-toggle-card__radio--on': wantsInvoice }"></span>
                        <span class="ec-toggle-card__text">
                            <strong>Necesito boleta o factura</strong>
                            <small>Requiere documento de identidad</small>
                        </span>
                    </label>
                </div>

                {{-- Campos de comprobante (solo si quiere) --}}
                <template v-if="wantsInvoice">
                    <div class="row" style="margin-top:14px">
                        <div class="col-md-5">
                            <div class="ec-field" :class="{'ec-field--error': errors.codigo_tipo_documento}">
                                <label>Tipo de comprobante</label>
                                <select v-model="form_document.codigo_tipo_documento" class="ec-field__select ec-field__input" @change="optionDocument">
                                    <option value="03">Boleta de venta</option>
                                    <option value="01">Factura</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="ec-field" :class="{'ec-field--error': errors.codigo_tipo_documento_identidad}">
                                <label>@{{ form_document.codigo_tipo_documento === '01' ? 'RUC' : 'Tipo documento' }}</label>
                                <select v-model="typeDocuments" class="ec-field__select ec-field__input">
                                    <option value="" disabled>Seleccionar</option>
                                    <option v-for="item in typeDocumentList" :value="item.id">@{{ item.name }}</option>
                                </select>
                                <small class="ec-field__error" v-if="errors.codigo_tipo_documento_identidad" v-text="errors.codigo_tipo_documento_identidad[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ec-field" :class="{'ec-field--error': errors.numero_documento}">
                                <label>Nro. documento</label>
                                <input v-model="numberDocument" :maxlength="maxLength" type="text" class="ec-field__input" :placeholder="form_document.codigo_tipo_documento === '01' ? '20123456789' : '12345678'">
                                <small class="ec-field__error" v-if="errors.numero_documento" v-text="errors.numero_documento[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4" v-if="form_document.codigo_tipo_documento === '01'">
                            <div class="ec-field">
                                <label>Razón social</label>
                                <input v-model="form_document.razon_social" type="text" class="ec-field__input" placeholder="Empresa S.A.C.">
                            </div>
                        </div>
                    </div>
                    <div class="row" v-if="form_document.codigo_tipo_documento === '01'">
                        <div class="col-md-12">
                            <div class="ec-field">
                                <label>Dirección fiscal</label>
                                <input v-model="form_document.direccion_fiscal" type="text" class="ec-field__input" placeholder="Av. Principal 123, Lima">
                            </div>
                        </div>
                    </div>
                </template>

                <div class="ec-step-nav">
                    <button type="button" class="ec-step-btn ec-step-btn--back" @click="goToStep(2)">← Entrega</button>
                    <button type="button" class="ec-step-btn ec-step-btn--next" @click="nextStep(3)">Continuar al Pago →</button>
                </div>
            </div>
        </div>

        {{-- Resumen paso 3 (colapsado) --}}
        <div class="ec-step-summary" v-show="step > 3" @click="goToStep(3)">
            <span class="ec-step-summary__num">3</span>
            <div class="ec-step-summary__info">
                <strong>Comprobante</strong>
                <span v-if="!wantsInvoice">Nota de venta (sin comprobante fiscal)</span>
                <span v-else>@{{ form_document.codigo_tipo_documento === '01' ? 'Factura' : 'Boleta' }} · @{{ numberDocument || 'Sin documento' }}</span>
            </div>
            <span class="ec-step-summary__edit">Editar</span>
        </div>

    </div>

    {{-- ══ RIGHT: RESUMEN + PAGO (siempre visible en desktop, paso 4 en mobile) ══ --}}
    <div class="col-lg-5" :class="{ 'ec-mobile-step4': step < 4 }">

        {{-- Resumen de productos --}}
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Tu pedido</span>
                <span class="ec-cart-badge">@{{ records.length }}</span>
            </div>
            <div class="ec-co-items">
                <div v-for="row in records" class="ec-co-item">
                    <div class="ec-co-item__img">
                        <img :src="(row.image && row.image !== 'imagen-no-disponible.jpg') ? '{{ $itemsBasePath }}' + '/' + row.image : '{{ $defaultImagePath }}'" :alt="row.description" onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                    </div>
                    <div class="ec-co-item__info">
                        <span class="ec-co-item__name">@{{ row.description }}</span>
                        <span class="ec-co-item__qty">× @{{ row.cantidad || 1 }}</span>
                    </div>
                    <span class="ec-co-item__price">S/ @{{ row.sub_total }}</span>
                </div>
            </div>

            {{-- Totales --}}
            <div class="ec-order-totals">
                <div class="ec-order-line" v-if="summary.total_exonerated > 0">
                    <span>Op. Exoneradas</span><span>S/ @{{ summary.total_exonerated }}</span>
                </div>
                <div class="ec-order-line" v-if="summary.total_taxed > 0">
                    <span>Op. Gravada</span><span>S/ @{{ summary.total_taxed }}</span>
                </div>
                <div class="ec-order-line" v-if="summary.total_igv > 0">
                    <span>IGV (18%)</span><span>S/ @{{ summary.total_igv }}</span>
                </div>
                <!-- Descuentos automáticos por regla (volumen, canal, etc.) -->
                <div v-for="(line, idx) in autoDiscount.breakdown" :key="'auto-'+idx"
                     v-if="line.type !== 'coupon' && line.type !== 'points' && line.amount < 0"
                     class="ec-order-line ec-order-line--discount">
                    <span style="display:flex;align-items:center;gap:4px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        @{{ line.label }}
                    </span>
                    <span style="color:#16a34a;font-weight:700;">- S/ @{{ Math.abs(line.amount).toFixed(2) }}</span>
                </div>
                <div class="ec-order-line ec-order-line--discount" v-if="coupon.applied && coupon.discount > 0">
                    <span><span class="ec-coupon-tag"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>@{{ coupon.code }}</span></span>
                    <span style="color:#16a34a;font-weight:700;">- S/ @{{ coupon.discount.toFixed(2) }}</span>
                </div>
                <div class="ec-order-line ec-order-line--discount" v-if="points.applied && points.discount > 0">
                    <span><span class="ec-coupon-tag" style="background:#fef3c7;color:#92400e;border-color:#fde68a;"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>Puntos</span></span>
                    <span style="color:#16a34a;font-weight:700;">- S/ @{{ points.discount.toFixed(2) }}</span>
                </div>
            </div>
            <div class="ec-order-total">
                <span>Total a pagar</span>
                <strong>S/ @{{ totalFinal }}</strong>
            </div>
        </div>

        {{-- Cupón de descuento --}}
        <div class="ec-checkout-card ec-coupon-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <span>¿Tienes un cupón?</span>
            </div>
            <div class="ec-coupon-form" v-if="!coupon.applied">
                <div class="ec-coupon-input-row">
                    <input v-model="coupon.code"
                           type="text"
                           class="ec-field__input ec-coupon-code-input"
                           placeholder="Ingresa tu código"
                           @keyup.enter="applyCoupon"
                           :disabled="coupon.loading"
                           maxlength="30">
                    <button @click="applyCoupon" class="ec-coupon-apply-btn" :disabled="coupon.loading || !coupon.code.trim()">
                        <span v-if="!coupon.loading">Aplicar</span>
                        <span v-else class="ec-spinner-sm"></span>
                    </button>
                </div>
                <p class="ec-coupon-msg ec-coupon-msg--error" v-if="coupon.message && !coupon.applied">@{{ coupon.message }}</p>
            </div>
            <div class="ec-coupon-applied" v-if="coupon.applied">
                <div class="ec-coupon-applied__inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span class="ec-coupon-applied__code">@{{ coupon.code }}</span>
                    <span class="ec-coupon-applied__disc">- S/ @{{ coupon.discount.toFixed(2) }}</span>
                    <button @click="removeCoupon" class="ec-coupon-remove" title="Quitar cupón" aria-label="Quitar cupón">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Métodos de pago --}}
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>Elige tu método de pago</span>
            </div>

            <div class="ec-payment-methods">
                {{-- Opción 1: Solo pedir (contra entrega) --}}
                <label class="ec-payment-option" :class="{ 'ec-payment-option--active': paymentMethod === 'cash' }" @click="paymentMethod = 'cash'">
                    <span class="ec-payment-option__radio"></span>
                    <span class="ec-payment-option__icon" style="background:#f0fdf4;color:#16a34a">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    </span>
                    <span class="ec-payment-option__text">
                        <strong>Pagar al recibir</strong>
                        <small>Efectivo o transferencia al momento de la entrega</small>
                    </span>
                </label>

                {{-- Opción 2: Pagar con tarjeta --}}
                <label class="ec-payment-option" :class="{ 'ec-payment-option--active': paymentMethod === 'card' }" @click="paymentMethod = 'card'">
                    <span class="ec-payment-option__radio"></span>
                    <span class="ec-payment-option__icon" style="background:#eef2ff;color:#4f46e5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </span>
                    <span class="ec-payment-option__text">
                        <strong>Pagar con tarjeta</strong>
                        <small>Visa, Mastercard, American Express</small>
                    </span>
                    <span class="ec-payment-cards">
                        <img src="{{ asset('porto-ecommerce/assets/images/visa.svg') }}" alt="Visa" width="32" height="20" onerror="this.style.display='none'">
                        <img src="{{ asset('porto-ecommerce/assets/images/mastercard.svg') }}" alt="Mastercard" width="32" height="20" onerror="this.style.display='none'">
                    </span>
                </label>

                @if($paypalScriptSrc)
                {{-- Opción 3: PayPal --}}
                <label class="ec-payment-option" :class="{ 'ec-payment-option--active': paymentMethod === 'paypal' }" @click="paymentMethod = 'paypal'">
                    <span class="ec-payment-option__radio"></span>
                    <span class="ec-payment-option__icon" style="background:#fff7ed;color:#ea580c">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 0 0-2 2v4"/></svg>
                    </span>
                    <span class="ec-payment-option__text">
                        <strong>PayPal</strong>
                        <small>Paga con tu cuenta PayPal</small>
                    </span>
                </label>
                @endif
            </div>

            {{-- Botón de acción según método seleccionado --}}
            <div class="ec-payment-action-wrap">
                {{-- Efectivo: confirmar pedido directo --}}
                <button v-if="paymentMethod === 'cash'"
                        @click="paymentCash"
                        class="ec-pay-btn ec-pay-btn--main"
                        :disabled="loading_payment">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span v-if="!loading_payment">Confirmar pedido — S/ @{{ totalFinal }}</span>
                    <span v-else>Procesando...</span>
                </button>

                {{-- Tarjeta: abrir Culqi --}}
                <button v-if="paymentMethod === 'card'"
                        class="ec-pay-btn ec-pay-btn--main ec-pay-btn--card culqi"
                        onclick="execCulqi()"
                        :disabled="loading_payment">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Pagar S/ @{{ totalFinal }} con tarjeta
                </button>

                {{-- PayPal --}}
                <div v-if="paymentMethod === 'paypal'" class="ec-paypal-wrap">
                    @if($paypalScriptSrc)
                        <script src="{{ e($paypalScriptSrc) }}"></script>
                    @endif
                </div>
            </div>

            <p class="ec-secure-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Pago 100% seguro y encriptado
            </p>
        </div>

    </div>
</div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<script src="{{ asset('porto-ecommerce/assets/js/ubigeo-filter.js') }}"></script>
<script src="{{ asset('porto-ecommerce/assets/js/ubigeo-autocomplete.js') }}"></script>
<script type="text/javascript">
    // Ubigeo autocomplete — se conecta al Vue instance después de crearlo
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof UbigeoAutocomplete !== 'undefined') {
            UbigeoAutocomplete.init('ubigeo-search', {
                onSelect: function(item) {
                    if (typeof app_cart !== 'undefined' && app_cart.ubigeo) {
                        app_cart.ubigeo.department_id = item.department_id;
                        app_cart.loadProvinces && app_cart.loadProvinces();
                        setTimeout(function() {
                            app_cart.ubigeo.province_id = item.province_id;
                            app_cart.loadDistricts && app_cart.loadDistricts();
                            setTimeout(function() {
                                app_cart.ubigeo.district_id = item.district_id;
                            }, 300);
                        }, 300);
                    }
                }
            });
        }
    });
</script>
<script type="text/javascript">
    var app_cart = new Vue({
        el: '#app',
        data: {
            form_contact: { address: '', telephone: '' },
            payment_cash: { amount: '', clicked: false },
            coupon: { code: '', applied: false, discount: 0, message: '', loading: false },
            autoDiscount: { discount: 0, breakdown: [], loaded: false },
            ubigeo: { department_id: '', province_id: '', district_id: '', departments: [], provinces: [], districts: [] },
            points: { enabled: false, balance: 0, applied: false, discount: 0 },
            deliveryType: 'delivery', // delivery | pickup
            paymentMethod: 'cash', // cash | card | paypal
            loading_payment: false,
            wantsInvoice: false, // comprobante fiscal opcional
            step: 1, // checkout wizard step (1-4)
            records: [],
            records_old: [],
            order_generated: {},
            summary: { subtotal: '0.0', tax: '0.0', total: '0.0' },
            aux_totals: {},
            form_document: { codigo_tipo_documento: '03', razon_social: '', direccion_fiscal: '' },
            user: {},
            guest_email: '',   // solo para invitados
            is_guest: {!! auth('ecommerce')->check() ? 'false' : 'true' !!},
            typeDocumentSelected: '',
            response_order_total: 0,
            errors: {},
            exchange_rate_sale: '',
            typeDocuments: '',
            typeDocumentList: [],
            numberDocument: '',
            phone_whatsapp: {!! \Illuminate\Support\Js::from($configuration->phone_whatsapp) !!},
            all_identity_document_types: [{id:'6',name:'RUC'},{id:'0',name:'DOC'},{id:'4',name:'CE'},{id:'1',name:'DNI'}]
        },
        computed: {
            maxLength() {
                if (this.typeDocuments === '6') return 11;
                if (this.typeDocuments === '1') return 8;
                return 15;
            },
            totalWithCoupon() {
                var base = parseFloat(this.summary.total) || 0;
                var disc = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                return Math.max(0, base - disc).toFixed(2);
            },
            totalFinal() {
                var base   = parseFloat(this.summary.total) || 0;
                var disc   = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                var auto   = this.autoDiscount.discount || 0;
                var pdisc  = this.points.applied ? (this.points.discount || 0) : 0;
                return Math.max(0, base - disc - auto - pdisc).toFixed(2);
            },
            maxPointsToApply() {
                var base = parseFloat(this.summary.total) || 0;
                var couponDisc = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                var auto = this.autoDiscount.discount || 0;
                return Math.min(this.points.balance, Math.max(0, base - couponDisc - auto) * 0.5);
            }
        },
        async mounted() {
            // Cargar departamentos
            try {
                var depRes = await axios.get('/ecommerce/ubigeo/departments');
                this.ubigeo.departments = depRes.data;
            } catch(e) {}

            @auth('ecommerce')
            try {
                var ptRes = await axios.get('/ecommerce/points');
                if (ptRes.data.enabled) {
                    this.points.enabled = true;
                    this.points.balance = ptRes.data.balance;
                }
            } catch(e) {}
            @endauth
            try { await this.changeExchangeRate(moment().format("YYYY-MM-DD")); } catch(e) {}
            // Cargar descuentos automáticos (volumen, canal, etc.) sin cupón
            this.$nextTick(() => this.loadAutoDiscounts());
            this.records.forEach(item => {
                if (item.currency_type_id === 'USD') {
                    item.sub_total = (parseFloat(item.sub_total) * this.exchange_rate_sale).toFixed(2);
                    item.exchange_rate_sale = this.exchange_rate_sale;
                }
                item.sale_unit_price = parseFloat(item.sale_unit_price).toFixed(2);
            });
            this.calculateSummary();

            // ── Tracking: InitiateCheckout ───────────────────────
            this.$nextTick(() => {
                if (window.EcommerceTracker && this.records.length) {
                    try {
                        var items = this.records.map(i => ({
                            id:       String(i.id || i.item_id || ''),
                            name:     String(i.description || i.name || ''),
                            price:    parseFloat(i.sale_unit_price) || 0,
                            quantity: parseInt(i.cantidad || i.quantity) || 1
                        }));
                        var total = items.reduce((s, i) => s + i.price * i.quantity, 0);
                        EcommerceTracker.initiateCheckout({
                            items:    items,
                            total:    total,
                            currency: 'PEN'
                        });
                    } catch (e) { /* silencioso */ }
                }
            });
        },
        created() {
            // Cargar productos del carrito
            var arr = localStorage.getItem('products_cart');
            arr = arr ? JSON.parse(arr) : [];
            if (Array.isArray(arr)) {
                this.records = arr.map(item => {
                    item.cantidad = item.quantity || 1;
                    item.sub_total = (parseFloat(item.sale_unit_price) * item.cantidad).toFixed(2);
                    item.exchange_rate_sale = '';
                    return item;
                });
            }
            // Restaurar estado del cupón/puntos desde el carrito
            try {
                var state = JSON.parse(localStorage.getItem('checkout_state') || '{}');
                if (state.coupon) this.coupon = state.coupon;
                if (state.points) this.points = state.points;
            } catch(e) {}
            this.initForm();
        },
        methods: {
            // ── Checkout wizard navigation ──
            nextStep(currentStep) {
                // Validación por paso
                if (currentStep === 1) {
                    if (!this.form_contact.telephone && !this.guest_email) {
                        this.$message.warning('Ingresa al menos un teléfono o correo');
                        return;
                    }
                }
                if (currentStep === 2 && this.deliveryType === 'delivery') {
                    if (!this.ubigeo.district_id) {
                        this.$message.warning('Selecciona tu distrito');
                        return;
                    }
                    if (!this.form_contact.address) {
                        this.$message.warning('Ingresa tu dirección');
                        return;
                    }
                }
                this.step = currentStep + 1;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            goToStep(s) {
                this.step = s;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            async changeExchangeRate(date) {
                var response = await axios.get(`/exchange_rate/ecommence/${date}`);
                this.exchange_rate_sale = parseFloat(response.data.sale);
            },
            optionDocument() {
                this.typeDocumentList = [];
                this.typeDocuments = null;
                if (this.form_document.codigo_tipo_documento == '01') {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['6']);
                } else if (this.form_document.codigo_tipo_documento == '03' && this.payment_cash.amount >= 700) {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['1']);
                } else if (this.form_document.codigo_tipo_documento == '80') {
                    this.typeDocumentList = (this.payment_cash.amount >= 700)
                        ? this.getIdentityDocumentTypes(['6','1'])
                        : this.getIdentityDocumentTypes();
                } else {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['0','1','4']);
                }
            },
            getIdentityDocumentTypes(ids = null) {
                if (!ids) return this.all_identity_document_types;
                return this.all_identity_document_types.filter(i => ids.includes(i.id));
            },
            refreshSetDataCustomer() {
                this.form_document.datos_del_cliente_o_receptor.direccion = this.deliveryType === 'pickup' ? 'Recojo en tienda' : this.form_contact.address;
                this.form_document.datos_del_cliente_o_receptor.telefono  = this.form_contact.telephone;
                this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments;
                this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument;
                this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments;
                // Ubigeo: usar el distrito seleccionado o defecto Lima
                this.form_document.datos_del_cliente_o_receptor.ubigeo = this.ubigeo.district_id || '150101';
                // Para invitados: usar el email ingresado manualmente
                if (this.is_guest && this.guest_email) {
                    this.form_document.datos_del_cliente_o_receptor.correo_electronico = this.guest_email;
                }
            },
            async loadProvinces() {
                this.ubigeo.province_id = '';
                this.ubigeo.district_id = '';
                this.ubigeo.provinces   = [];
                this.ubigeo.districts   = [];
                if (!this.ubigeo.department_id) return;
                try {
                    var r = await axios.get('/ecommerce/ubigeo/provinces/' + this.ubigeo.department_id);
                    this.ubigeo.provinces = r.data;
                } catch(e) {}
            },
            async loadDistricts() {
                this.ubigeo.district_id = '';
                this.ubigeo.districts   = [];
                if (!this.ubigeo.province_id) return;
                try {
                    var r = await axios.get('/ecommerce/ubigeo/districts/' + this.ubigeo.province_id);
                    this.ubigeo.districts = r.data;
                } catch(e) {}
            },
            async applyCoupon() {
                var code = (this.coupon.code || '').trim().toUpperCase();
                if (!code) return;
                this.coupon.loading = true;
                this.coupon.message = '';
                try {
                    var r = await axios.post('/ecommerce/apply-coupon', {
                        coupon_code: code,
                        amount: parseFloat(this.summary.total) || 0,
                        items:  this.records,
                    });
                    if (r.data.success) {
                        this.coupon.applied       = true;
                        this.coupon.code          = code;
                        this.coupon.discount      = parseFloat(r.data.coupon_discount || r.data.discount) || 0;
                        this.coupon.message       = '';
                        // Descuentos automáticos devueltos junto con el cupón
                        this.autoDiscount.discount  = parseFloat(r.data.rule_discount) || 0;
                        this.autoDiscount.breakdown = r.data.breakdown || [];
                        this.autoDiscount.loaded    = true;
                        this.$nextTick(() => {
                            $("#total_amount").data('total', this.totalFinal);
                            this.payment_cash.amount = this.totalFinal;
                        });
                    } else {
                        this.coupon.message = r.data.message || 'Cupón no válido.';
                    }
                } catch(e) {
                    this.coupon.message = 'Error al validar el cupón.';
                } finally {
                    this.coupon.loading = false;
                }
            },
            removeCoupon() {
                this.coupon = { code: '', applied: false, discount: 0, message: '', loading: false };
                this.autoDiscount = { discount: 0, breakdown: [], loaded: false };
                this.loadAutoDiscounts(); // recargar descuentos automáticos sin cupón
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            },
            async loadAutoDiscounts() {
                try {
                    var r = await axios.post('/ecommerce/preview-discounts', {
                        amount: parseFloat(this.summary.total) || 0,
                        items:  this.records,
                    });
                    this.autoDiscount.discount  = parseFloat(r.data.rule_discount) || 0;
                    this.autoDiscount.breakdown = r.data.breakdown || [];
                    this.autoDiscount.loaded    = true;
                    this.$nextTick(() => {
                        $("#total_amount").data('total', this.totalFinal);
                        this.payment_cash.amount = this.totalFinal;
                    });
                } catch(e) { /* silencioso */ }
            },
            async getFormPaymentCash() {
                this.refreshSetDataCustomer();
                var finalTotal = parseFloat(this.totalFinal);
                return {
                    producto:      'Compras Ecommerce Facturador Pro',
                    precio:        Math.round(finalTotal * 100).toFixed(2),
                    precio_culqi:  finalTotal,
                    delivery_type: this.deliveryType, // delivery | pickup
                    customer:      this.form_document.datos_del_cliente_o_receptor,
                    items:         this.records,
                    purchase:      await this.getDocument(),
                    coupon_code:   this.coupon.applied ? this.coupon.code : '',
                    redeem_points: this.points.applied,
                    points_amount: this.points.applied ? this.points.discount : 0,
                    session_token: localStorage.getItem('ec_cart_token') || null,
                };
            },
            showSwalMessage(title, text, type) {
                swal({ title, text, type });
            },
            async paymentCash() {
                // Validar email para invitados
                if (this.is_guest) {
                    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!this.guest_email || !emailRe.test(this.guest_email)) {
                        this.errors = { guest_email: 'Ingresa un correo electrónico válido para recibir tu confirmación.' };
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }
                    this.errors = {};
                }
                // Si no quiere comprobante fiscal, usar nota de venta
                if (!this.wantsInvoice) {
                    this.form_document.codigo_tipo_documento = '80';
                    this.typeDocuments = '0';
                    this.numberDocument = '00000000';
                }
                if (!this.form_document.codigo_tipo_documento) {
                    return this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error');
                }
                // Validación client-side de campos requeridos
                this.refreshSetDataCustomer();
                const cust = this.form_document.datos_del_cliente_o_receptor;
                let clientErrors = {};
                if (!cust.telefono || String(cust.telefono).trim() === '') clientErrors.telefono = ['El teléfono es requerido.'];
                if (this.deliveryType === 'delivery' && (!cust.direccion || String(cust.direccion).trim() === '')) clientErrors.direccion = ['La dirección de envío es requerida.'];
                if (this.wantsInvoice && (!cust.numero_documento || cust.numero_documento == '0')) clientErrors.numero_documento = ['El número de documento es requerido.'];
                if (Object.keys(clientErrors).length > 0) {
                    this.errors = clientErrors;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    const firstMsg = Object.values(clientErrors)[0][0];
                    return this.showSwalMessage('Campos requeridos', firstMsg, 'warning');
                }
                this.errors = {};
                let product = JSON.parse(localStorage.getItem('products_cart'));
                if (!product || product.length < 1) {
                    swal({ title: "No se han encontrado productos", text: "Por favor seleccione algún producto.", type: "error" });
                    return;
                }
                swal({ title: "Procesando pago...", text: "Por favor no cierre esta ventana.", focusConfirm: false, onOpen: () => Swal.showLoading() });
                let url = '{{ route("tenant_ecommerce_payment_cash")}}';
                await axios.post(url, await this.getFormPaymentCash(), this.getHeaderConfig())
                    .then(response => {
                        if (response.data.success) {
                            localStorage.setItem('products_cart', '[]');
                            localStorage.removeItem('checkout_state');
                            if (response.data.redirect_route) {
                                window.location.href = response.data.redirect_route;
                            } else {
                                swal({ title: "Pago realizado!", text: "Tu pedido fue generado con éxito.", type: "success" });
                            }
                        } else {
                            swal({ title: "Error", text: response.data.message, type: "error" });
                        }
                    }).catch(error => {
                        let msg = "Ocurrió un error al procesar el pago.";
                        let title = "Error";
                        if (error.response) {
                            const status = error.response.status;
                            const d = error.response.data;
                            if (status === 422 && d && typeof d === 'object' && !d.message) {
                                // Errores de validación Laravel — mostrarlos en el formulario
                                this.errors = d;
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                const labels = {
                                    telefono: 'Teléfono',
                                    direccion: 'Dirección de envío',
                                    numero_documento: 'Número de documento',
                                    codigo_tipo_documento_identidad: 'Tipo de documento',
                                    identity_document_type_id: 'Tipo de documento'
                                };
                                const firstKey = Object.keys(d)[0];
                                const label = labels[firstKey] || firstKey;
                                title = "Campos requeridos";
                                msg = `Por favor completa el campo "${label}": ` + (Array.isArray(d[firstKey]) ? d[firstKey][0] : d[firstKey]);
                            } else if (d && d.message) {
                                msg = d.message;
                                // Detectar error de stock para título apropiado
                                title = msg.toLowerCase().includes('stock') ? 'Stock insuficiente' : 'Error al procesar el pedido';
                            } else if (typeof d === 'string') {
                                msg = d;
                            }
                        } else if (error.message) {
                            msg = error.message;
                        }
                        swal({ title: title, text: msg, type: "error" });
                    });
            },
            getHeaderConfig() {
                return { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } };
            },
            async getDocument() {
                this.form_document.items   = await this.getItemsDocument();
                this.form_document.totales = await this.getTotales();
                if (this.form_document.codigo_tipo_documento == '01') {
                    this.form_document.serie_documento = 'F001';
                } else if (this.form_document.codigo_tipo_documento == '03') {
                    this.form_document.serie_documento = 'B001';
                } else {
                    this.form_document.serie_documento = null;
                }
                return this.form_document;
            },
            async getTotales() {
                return {
                    total_exportacion: 0,
                    total_operaciones_gravadas: this.aux_totals.total_taxed,
                    total_operaciones_inafectas: 0,
                    total_operaciones_exoneradas: this.aux_totals.total_exonerated,
                    total_operaciones_gratuitas: 0,
                    total_igv: this.aux_totals.total_igv,
                    total_impuestos: this.aux_totals.total_igv,
                    total_valor: this.aux_totals.total_value,
                    total_venta: this.aux_totals.total
                };
            },
            async getItemsDocument() {
                return this.records.map(item => {
                    let pct = 18;
                    let price = item.currency_type_id === 'USD'
                        ? (parseFloat(item.sale_unit_price) * this.exchange_rate_sale).toFixed(2)
                        : item.sale_unit_price;
                    if (item.sale_affectation_igv_type_id === '10') {
                        let uv = price / (1 + pct/100);
                        return {
                            codigo_interno: item.internal_id || '',
                            descripcion: item.description,
                            codigo_producto_sunat: '',
                            unidad_de_medida: item.unit_type_id,
                            cantidad: item.cantidad,
                            valor_unitario: uv,
                            codigo_tipo_precio: '01',
                            precio_unitario: price,
                            codigo_tipo_afectacion_igv: '10',
                            total_base_igv: uv * item.cantidad,
                            porcentaje_igv: pct,
                            total_igv: item.cantidad * (price - uv),
                            total_impuestos: item.cantidad * (price - uv),
                            total_valor_item: uv * item.cantidad,
                            total_item: item.cantidad * price,
                            actualizar_descripcion: false,
                            nombre_producto_pdf: null
                        };
                    }
                    return {
                        codigo_interno: item.internal_id || '',
                        descripcion: item.description,
                        codigo_producto_sunat: '',
                        unidad_de_medida: item.unit_type_id,
                        cantidad: item.cantidad,
                        valor_unitario: price,
                        codigo_tipo_precio: '01',
                        precio_unitario: price,
                        codigo_tipo_afectacion_igv: '20',
                        total_base_igv: price * item.cantidad,
                        porcentaje_igv: pct,
                        total_igv: 0,
                        total_impuestos: 0,
                        total_valor_item: price * item.cantidad,
                        total_item: item.cantidad * price,
                        actualizar_descripcion: false,
                        nombre_producto_pdf: null
                    };
                });
            },
            initForm() {
                this.errors = {};
                this.user = {!! \Illuminate\Support\Js::from(Auth::guard("ecommerce")->user()) !!};
                var userName  = this.user ? this.user.name  : '';
                var userEmail = this.user ? this.user.email : '';
                var userAddr  = this.user ? (this.user.address   || '') : '';
                var userPhone = this.user ? (this.user.telephone || '') : '';
                this.form_document = {
                    acciones: { enviar_email: true, formato_pdf: 'a4' },
                    serie_documento: '',
                    numero_documento: '#',
                    fecha_de_emision: moment().format('YYYY-MM-DD'),
                    hora_de_emision:  moment().format('HH:mm:ss'),
                    codigo_tipo_operacion: '0101',
                    codigo_tipo_documento: '03',
                    codigo_tipo_moneda: 'PEN',
                    fecha_de_vencimiento: moment().format('YYYY-MM-DD'),
                    datos_del_cliente_o_receptor: {
                        codigo_tipo_documento_identidad: '0',
                        numero_documento: '0',
                        apellidos_y_nombres_o_razon_social: userName,
                        codigo_pais: 'PE',
                        ubigeo: this.ubigeo.district_id || '150101',
                        direccion: userAddr,
                        correo_electronico: userEmail,
                        telefono: userPhone
                    },
                    totales: {},
                    items: [],
                };
                this.form_contact.address   = userAddr;
                this.form_contact.telephone = userPhone;
                this.optionDocument();
            },
            calculateSummary() {
                let total_taxed = 0, total_value = 0, total_exonerated = 0, total_igv = 0, total = 0;
                this.records.forEach(item => {
                    let pct = 18;
                    if (item.sale_affectation_igv_type_id === '10') {
                        let uv = item.sub_total / (1 + pct/100);
                        total_taxed += parseFloat(uv);
                        total_igv   += parseFloat(item.sub_total - uv);
                    }
                    if (item.sale_affectation_igv_type_id === '20') {
                        total_exonerated += parseFloat(item.sub_total);
                    }
                    total_value = total_taxed + total_exonerated;
                    total += parseFloat(item.sub_total);
                });
                this.summary.total_taxed     = total_taxed.toFixed(2);
                this.summary.total_exonerated = total_exonerated.toFixed(2);
                this.summary.total_igv       = total_igv.toFixed(2);
                this.summary.total_value     = total_value.toFixed(2);
                this.summary.total           = total.toFixed(2);
                this.aux_totals = this.summary;
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            }
        }
    });
</script>

<script>
    // ── Tracking: InitiateCheckout ───────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        if (window.EcommerceTracker) {
            try {
                var cartItems = JSON.parse(localStorage.getItem('products_cart') || '[]');
                var total = cartItems.reduce(function (sum, i) {
                    return sum + (parseFloat(i.sale_unit_price) || 0) * (parseInt(i.quantity) || 1);
                }, 0);
                EcommerceTracker.initiateCheckout({
                    items: cartItems.map(function (i) {
                        return { id: i.id, name: i.description, price: parseFloat(i.sale_unit_price) || 0, quantity: parseInt(i.quantity) || 1 };
                    }),
                    total:    total,
                    currency: 'PEN'
                });
            } catch (e) { /* silencioso */ }
        }
    });

    Culqi.publicKey = {!! \Illuminate\Support\Js::from($configuration->token_public_culqui) !!};
    if (!Culqi.publicKey) { $('.culqi').hide(); }
    Culqi.options({ installments: true });

    async function execCulqi() {
        let precio = Math.round((Number($("#total_amount").data('total')) * 100).toFixed(2));
        if (precio > 0) {
            Culqi.settings({ title: "Productos Ecommerce", currency: 'PEN', description: 'Compras Ecommerce', amount: precio });
            Culqi.open();
        }
    }

    async function culqi() {
        if (Culqi.token) {
            swal({ title: "Procesando pago con tarjeta...", focusConfirm: false, onOpen: () => Swal.showLoading() });
            let form = await app_cart.getFormPaymentCash();
            form.token_id = Culqi.token.id;
            await axios.post('{{ route("tenant_ecommerce_culqui") }}', form, app_cart.getHeaderConfig())
                .then(r => {
                    if (r.data.success) {
                        localStorage.setItem('products_cart', '[]');
                        localStorage.removeItem('checkout_state');
                        if (r.data.redirect_route) window.location.href = r.data.redirect_route;
                        else swal({ title: "Pago realizado!", type: "success" });
                    } else {
                        swal({ title: "Error", text: r.data.message, type: "error" });
                    }
                }).catch(() => swal({ title: "Error", text: "Error al procesar el pago.", type: "error" }));
        }
    }
</script>
@endpush
