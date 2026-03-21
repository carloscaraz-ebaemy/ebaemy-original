@extends('ecommerce::layouts.layout_ecommerce_cart.index')
@section('content')

@php
    $configurationModel = \App\Models\Tenant\Configuration::first();
    $defaultImage = $configurationModel->product_default_image ?? 'imagen-no-disponible.jpg';
    $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
        ? asset('logo/imagen-no-disponible.jpg')
        : asset('storage/defaults/' . $defaultImage);
    $itemsBasePath = asset('storage/uploads/items');
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

<div class="row" id="app" style="margin-top:20px;">

    {{-- ══ LEFT: FORMULARIOS ══ --}}
    <div class="col-lg-7">

        {{-- Datos de contacto --}}
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>Datos de contacto y envío</span>
            </div>
            <form autocomplete="off" action="#" class="ec-checkout-form">

                {{-- Email — solo visible para invitados --}}
                @guest('ecommerce')
                <div class="ec-field ec-guest-notice" :class="{'ec-field--error': errors.guest_email}">
                    <label>
                        Correo electrónico
                        <span class="ec-field-badge-guest">Invitado</span>
                    </label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0 1.1.9 2 2 2z"/><polyline points="22,6 12,12 2,6"/></svg>
                        <input v-model="guest_email" type="email" autocomplete="email" class="ec-field__input" placeholder="tu@correo.com — para tu confirmación">
                    </div>
                    <small class="ec-field__error" v-if="errors.guest_email">@{{ errors.guest_email }}</small>
                    <p class="ec-guest-login-hint">
                        ¿Ya tienes cuenta?
                        <a href="{{ route('tenant_ecommerce_login') }}">Inicia sesión</a>
                        para ver tu historial de pedidos.
                    </p>
                </div>
                @endguest

                <div class="ec-field" :class="{'ec-field--error': errors.telefono}">
                    <label>Teléfono</label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.22 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.09a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 15z"/></svg>
                        <input v-model="form_contact.telephone" type="text" autocomplete="off" class="ec-field__input" placeholder="Ej: 987 654 321">
                    </div>
                    <small class="ec-field__error" v-if="errors.telefono" v-text="errors.telefono[0]"></small>
                </div>
                {{-- Ubigeo: Departamento → Provincia → Distrito --}}
                <div class="ec-ubigeo-row">
                    <div class="ec-field" :class="{'ec-field--error': errors.department_id}">
                        <label>Departamento</label>
                        <div class="ec-field__input-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            <select v-model="ubigeo.department_id" class="ec-field__select" @change="loadProvinces">
                                <option value="">Departamento</option>
                                <option v-for="d in ubigeo.departments" :key="d.id" :value="d.id">@{{ d.description }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="ec-field" :class="{'ec-field--error': errors.province_id}">
                        <label>Provincia</label>
                        <div class="ec-field__input-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            <select v-model="ubigeo.province_id" class="ec-field__select" @change="loadDistricts" :disabled="!ubigeo.provinces.length">
                                <option value="">Provincia</option>
                                <option v-for="p in ubigeo.provinces" :key="p.id" :value="p.id">@{{ p.description }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="ec-field" :class="{'ec-field--error': errors.district_id}">
                        <label>Distrito <span style="color:#ef4444;font-size:1.1rem">*</span></label>
                        <div class="ec-field__input-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            <select v-model="ubigeo.district_id" class="ec-field__select" :disabled="!ubigeo.districts.length">
                                <option value="">Distrito</option>
                                <option v-for="d in ubigeo.districts" :key="d.id" :value="d.id">@{{ d.description }}</option>
                            </select>
                        </div>
                        <small class="ec-field__error" v-if="errors.district_id">Selecciona tu distrito.</small>
                    </div>
                </div>

                <div class="ec-field" :class="{'ec-field--error': errors.address}">
                    <label>Dirección de envío</label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" style="align-self:flex-start;margin-top:10px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <textarea v-model="form_contact.address" class="ec-field__input ec-field__textarea" placeholder="Calle, número, referencia..." rows="2"></textarea>
                    </div>
                    <small class="ec-field__error" v-if="errors.address" v-text="errors.address[0]"></small>
                </div>
            </form>
        </div>

        {{-- Datos de facturación --}}
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <span>Datos de facturación</span>
            </div>
            <div class="ec-checkout-form">
                <div class="ec-field" :class="{'ec-field--error': errors.codigo_tipo_documento}">
                    <label>Tipo de comprobante</label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        <select v-model="form_document.codigo_tipo_documento" class="ec-field__select" @change="optionDocument">
                            <option value="" disabled>Seleccionar comprobante</option>
                            <option value="01">Factura</option>
                            <option value="03">Boleta de venta</option>
                            <option value="80">Nota de venta</option>
                        </select>
                    </div>
                    <small class="ec-field__error" v-if="errors.codigo_tipo_documento">El campo comprobante es obligatorio.</small>
                </div>
                <div class="ec-field" :class="{'ec-field--error': errors.codigo_tipo_documento_identidad}">
                    <label>Tipo de documento</label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        <select v-model="typeDocuments" class="ec-field__select">
                            <option value="" disabled>Seleccionar documento</option>
                            <option v-for="item in typeDocumentList" :value="item.id">@{{ item.name }}</option>
                        </select>
                    </div>
                    <small class="ec-field__error" v-if="errors.codigo_tipo_documento_identidad" v-text="errors.codigo_tipo_documento_identidad[0]"></small>
                </div>
                <div class="ec-field" :class="{'ec-field--error': errors.numero_documento}">
                    <label>Número de documento</label>
                    <div class="ec-field__input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="17" y2="13"/><line x1="7" y1="17" x2="12" y2="17"/></svg>
                        <input v-model="numberDocument" :maxlength="maxLength" type="text" class="ec-field__input" placeholder="Ingrese número">
                    </div>
                    <small class="ec-field__error" v-if="errors.numero_documento" v-text="errors.numero_documento[0]"></small>
                </div>
            </div>
        </div>

        {{-- Link volver al carrito --}}
        <a href="{{ route('tenant_detail_cart') }}" class="ec-btn-ghost" style="margin-bottom:20px;display:inline-flex;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Volver al carrito
        </a>
    </div>

    {{-- ══ RIGHT: RESUMEN + PAGO ══ --}}
    <div class="col-lg-5">

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
                <span>Método de pago</span>
            </div>
            <div class="ec-payment-actions">
                {{-- Pagar con tarjeta (Culqi) --}}
                <button class="ec-pay-btn ec-pay-btn--visa culqi" onclick="execCulqi()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Pagar con Tarjeta
                </button>

                {{-- Pagar con efectivo --}}
                <button @click="payment_cash.clicked = !payment_cash.clicked" class="ec-pay-btn ec-pay-btn--cash">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    Pagar con Efectivo / Contra entrega
                </button>
                <div v-show="payment_cash.clicked" class="ec-cash-input-wrap">
                    <div class="ec-cash-input-group">
                        <span class="ec-cash-prefix">S/</span>
                        <input readonly placeholder="0.00" v-model="payment_cash.amount" type="text"
                               onkeypress="return isNumberKey(event)" maxlength="14" class="ec-cash-input" aria-label="Monto">
                        <button @click="paymentCash" class="ec-cash-ok">Confirmar pedido</button>
                    </div>
                </div>

                @if($information->script_paypal)
                    {!!html_entity_decode($information->script_paypal)!!}
                @endif

                <p class="ec-secure-note">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Pago 100% seguro y encriptado
                </p>
            </div>
        </div>

    </div>
</div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<script type="text/javascript">
    var app_cart = new Vue({
        el: '#app',
        data: {
            form_contact: { address: '', telephone: '' },
            payment_cash: { amount: '', clicked: false },
            coupon: { code: '', applied: false, discount: 0, message: '', loading: false },
            ubigeo: { department_id: '', province_id: '', district_id: '', departments: [], provinces: [], districts: [] },
            points: { enabled: false, balance: 0, applied: false, discount: 0 },
            records: [],
            records_old: [],
            order_generated: {},
            summary: { subtotal: '0.0', tax: '0.0', total: '0.0' },
            aux_totals: {},
            form_document: {},
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
            phone_whatsapp: {!! json_encode($configuration->phone_whatsapp) !!},
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
                var base  = parseFloat(this.summary.total) || 0;
                var disc  = this.coupon.applied  ? (this.coupon.discount  || 0) : 0;
                var pdisc = this.points.applied   ? (this.points.discount  || 0) : 0;
                return Math.max(0, base - disc - pdisc).toFixed(2);
            },
            maxPointsToApply() {
                var base = parseFloat(this.summary.total) || 0;
                var couponDisc = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                return Math.min(this.points.balance, Math.max(0, base - couponDisc) * 0.5);
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
            this.records.forEach(item => {
                if (item.currency_type_id === 'USD') {
                    item.sub_total = (parseFloat(item.sub_total) * this.exchange_rate_sale).toFixed(2);
                    item.exchange_rate_sale = this.exchange_rate_sale;
                }
                item.sale_unit_price = parseFloat(item.sale_unit_price).toFixed(2);
            });
            this.calculateSummary();
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
                this.form_document.datos_del_cliente_o_receptor.direccion = this.form_contact.address;
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
                        amount: parseFloat(this.summary.total) || 0
                    });
                    if (r.data.success) {
                        this.coupon.applied  = true;
                        this.coupon.code     = r.data.code;
                        this.coupon.discount = r.data.discount;
                        this.coupon.message  = '';
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
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            },
            async getFormPaymentCash() {
                this.refreshSetDataCustomer();
                var finalTotal = parseFloat(this.totalFinal);
                return {
                    producto:      'Compras Ecommerce Facturador Pro',
                    precio:        Math.round(finalTotal * 100).toFixed(2),
                    precio_culqi:  finalTotal,
                    customer:      this.form_document.datos_del_cliente_o_receptor,
                    items:         this.records,
                    purchase:      await this.getDocument(),
                    coupon_code:   this.coupon.applied ? this.coupon.code : '',
                    redeem_points: this.points.applied,
                    points_amount: this.points.applied ? this.points.discount : 0,
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
                if (!this.form_document.codigo_tipo_documento) {
                    return this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error');
                }
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
                        swal({ title: "Error", text: "Ocurrió un error al procesar el pago.", type: "error" });
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
                this.user = JSON.parse('{!! json_encode(Auth::guard("ecommerce")->user()) !!}');
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
    Culqi.publicKey = {!! json_encode($configuration->token_public_culqui) !!};
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
