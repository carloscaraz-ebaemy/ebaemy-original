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

{{-- ── STEPPER VISUAL ──────────────────────────────────── --}}
<div class="ec-checkout-stepper" aria-label="Pasos del proceso de compra">
    <div class="ec-step ec-step--active">
        <div class="ec-step__num" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
        </div>
        <span class="ec-step__label">Mi carrito</span>
    </div>
    <div class="ec-step__line"></div>
    <div class="ec-step">
        <div class="ec-step__num" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
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
                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
        </div>
        <span class="ec-step__label">Pago</span>
    </div>
</div>

<div class="row" id="app" style="margin-top:20px;gap:0">

    {{-- ══════════════════════════════════════════════════
         LEFT: PRODUCTOS DEL CARRITO
    ══════════════════════════════════════════════════ --}}
    <div class="col-12 col-lg-7">
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Productos en tu carrito</span>
                <span class="ec-cart-badge">@{{ records.length }}</span>
            </div>

            {{-- Estado vacío --}}
            <div v-if="records.length === 0" class="ec-cart-empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <p>Tu carrito está vacío</p>
                <a href="/ecommerce" class="ec-btn-primary">Explorar productos</a>
            </div>

            {{-- Lista de productos --}}
            <div v-if="records.length > 0" class="ec-cart-items">
                <div v-for="(row, index) in records" class="ec-cart-item">
                    <div class="ec-cart-item__img">
                        <img :src="(row.image && row.image !== 'imagen-no-disponible.jpg') ? '{{ $itemsBasePath }}' + '/' + row.image : '{{ $defaultImagePath }}'" :alt="row.description || 'Producto'" onerror="this.src='{{ asset('logo/imagen-no-disponible.jpg') }}'">
                    </div>
                    <div class="ec-cart-item__info">
                        <p class="ec-cart-item__name">@{{ row.description }}</p>
                        <span v-if="row.variant_display_name" class="ec-cart-item__variant">@{{ row.variant_display_name }}</span>
                        <span class="ec-cart-item__price">@{{ row.currency_type_symbol }} @{{ row.sale_unit_price }}</span>
                    </div>
                    <div class="ec-cart-item__qty">
                        <div class="ec-qty-selector" style="margin-bottom:0">
                            <button type="button" class="ec-qty-btn" @click="decreaseQty(row)" aria-label="Reducir cantidad">−</button>
                            <input class="ec-qty-input input_quantity" :data-product="row.id"
                                   :value="row.cantidad || 1" type="number" min="1" :max="row.stock || 9999"
                                   @change="updateQtyItem(row, $event.target.value)">
                            <button type="button" class="ec-qty-btn" @click="increaseQty(row)" aria-label="Aumentar cantidad">+</button>
                        </div>
                        <small v-if="row.stock_warning" class="ec-stock-warning">Máx. @{{ row.stock }} disponibles</small>
                    </div>
                    <div class="ec-cart-item__subtotal">
                        <span class="ec-cart-item__subtotal-label">Subtotal</span>
                        <strong>S/ @{{ row.sub_total }}</strong>
                    </div>
                    <button type="button" class="ec-cart-item__remove" @click="deleteItem(row.id, index)" title="Eliminar" aria-label="Eliminar producto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            {{-- Footer del carrito --}}
            <div v-if="records.length > 0" class="ec-cart-footer">
                <a href="/ecommerce" class="ec-btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Continuar comprando
                </a>
                <a href="#" @click.prevent="clearShoppingCart" class="ec-btn-ghost ec-btn-ghost--danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Vaciar carrito
                </a>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════
         RIGHT: RESUMEN DEL PEDIDO
    ══════════════════════════════════════════════════ --}}
    <div class="col-12 col-lg-5">
        <div class="ec-checkout-card">
            <div class="ec-checkout-card__header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>Resumen del pedido</span>
            </div>

            {{-- Puntos de fidelidad --}}
            @auth('ecommerce')
            <div class="ec-points-box" v-if="points.enabled && points.balance > 0">
                <div class="ec-points-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="color:#f59e0b"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span>Mis puntos: <strong>@{{ points.balance }}</strong></span>
                    <span class="ec-points-hint">(= S/ @{{ points.balance.toFixed(2) }})</span>
                </div>
                <div v-if="!points.applied" class="ec-points-apply-wrap">
                    <span class="ec-points-apply-label">Usar hasta <strong>@{{ maxPointsToApply }}</strong> pts <em>(-S/ @{{ maxPointsToApply.toFixed(2) }})</em></span>
                    <button type="button" class="ec-points-btn" @click="applyPoints">Usar puntos</button>
                </div>
                <div v-else class="ec-points-applied-wrap">
                    <span class="ec-points-applied-label">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        @{{ points.discount.toFixed(2) }} pts canjeados (-S/ @{{ points.discount.toFixed(2) }})
                    </span>
                    <button type="button" class="ec-points-btn ec-points-btn--remove" @click="removePoints">Quitar</button>
                </div>
            </div>
            @endauth

            {{-- Cupón --}}
            <div class="ec-coupon-box">
                <div class="ec-coupon-input-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <input type="text" v-model="coupon.code" class="ec-coupon-input" placeholder="Código de cupón" :disabled="coupon.applied" @keydown.enter.prevent="applyCoupon" maxlength="30" style="padding-left:34px">
                    <button type="button" class="ec-coupon-btn" @click="coupon.applied ? removeCoupon() : applyCoupon()" :disabled="coupon.loading">
                        <span v-if="coupon.loading">...</span>
                        <span v-else-if="coupon.applied">Quitar</span>
                        <span v-else>Aplicar</span>
                    </button>
                </div>
                <p v-if="coupon.message" class="ec-coupon-msg" :class="coupon.applied ? 'ec-coupon-msg--ok' : 'ec-coupon-msg--err'">@{{ coupon.message }}</p>
            </div>

            {{-- Líneas de totales --}}
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
                <!-- Descuentos automáticos (volumen, canal, etc.) -->
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
                <span>Total</span>
                <strong>S/ @{{ totalFinal }}</strong>
            </div>

            {{-- CTA: ir al checkout --}}
            <div class="ec-payment-actions">
                <button type="button" class="ec-pay-btn ec-pay-btn--continue" @click="proceedToCheckout" :disabled="records.length === 0">
                    <span>Continuar al pago</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
                <p class="ec-secure-note">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Pago 100% seguro y encriptado
                </p>

                {{-- Trust badges --}}
                <div style="display:flex;justify-content:center;gap:1.5rem;padding:12px 20px 20px;flex-wrap:wrap">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:#6b7280;font-weight:600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        Envío seguro
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:#6b7280;font-weight:600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Compra protegida
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:#6b7280;font-weight:600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                        Garantía
                    </div>
                </div>
            </div>
        </div>
    </div>{{-- End col-lg-5 --}}
</div>{{-- End .row --}}

{{-- ── UPSELL: Productos que también te pueden interesar ── --}}
<div id="ec-upsell" class="ec-upsell-section" style="display:none">
    <div class="ec-section-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
        <h2 class="ec-section-title" style="margin:0">También te puede interesar</h2>
        <div class="ec-slider-nav" style="display:flex;gap:8px">
            <div class="ec-upsell-prev ec-slider-btn" role="button" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </div>
            <div class="ec-upsell-next ec-slider-btn" role="button" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
        </div>
    </div>
    <div class="swiper ec-upsell-swiper">
        <div class="swiper-wrapper ec-upsell-list"></div>
        <div class="swiper-pagination ec-upsell-pagination" style="margin-top:16px;position:relative;bottom:auto"></div>
    </div>
</div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<!-- script src="https://checkout.culqi.com/js/v3"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@7.31.1/dist/sweetalert2.all.min.js"></script>
<script src="https://momentjs.com/downloads/moment.min.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script -->


<script type="text/javascript">
    console.log('[Cart] Vue available:', typeof Vue !== 'undefined', 'ELEMENT available:', typeof ELEMENT !== 'undefined');
    if (typeof Vue === 'undefined') {
        console.error('[Cart] Vue is NOT loaded! Check script loading order.');
        document.getElementById('app').innerHTML = '<div style="padding:40px;text-align:center;color:red"><h3>Error: Vue.js no se cargó correctamente</h3><p>Recarga la página con Ctrl+Shift+R</p></div>';
    }
    var app_cart = new Vue({
        el: '#app',
        data: {
            form_contact: {
                address:   '',
                telephone:   '',
            },
            payment_cash: {
                amount: '',
                clicked: false
            },
            coupon: {
                code:     '',
                applied:  false,
                discount: 0,
                message:  '',
                loading:  false,
            },
            autoDiscount: { discount: 0, breakdown: [], loaded: false },
            points: {
                enabled:  false,
                balance:  0,
                applied:  false,
                discount: 0,
            },
            response_search: {},
            text_search: '',
            loading_search: false,
            identity_document_types: [{
                id: '1',
                description: 'DNI'
            }, {
                id: '6',
                description: 'RUC'
            }],
            formIdentity: {
                identity_document_type_id: ''
            },
            records: [],
            records_old: [],
            order_generated: {},
            summary: {
                subtotal: '0.0',
                tax: '0.0',
                total: '0.0'
            },
            aux_totals: {},
            form_document: {},
            user: {},
            typeDocumentSelected: '',
            response_order_total:0,
            errors: {},
            exchange_rate_sale: '',
            typeDocuments: '',
            typeDocumentList: [],
            numberDocument: '',
            phone_whatsapp: {!! \Illuminate\Support\Js::from($configuration->phone_whatsapp) !!},
            all_identity_document_types : [{id: '6', name: 'RUC'}, {id: '0', name: 'DOC'},{id: '4', name: 'CE'},{id: '1', name: 'DNI'}]
        },
        computed: {
            maxLength: function () {

                if (this.typeDocuments === '6') {
                    return 11
                }
                if (this.typeDocuments === '1') {
                    return 8
                }

                return 15
            }
        },
        async mounted() {
          // ── Cargar saldo de puntos (solo si está autenticado) ──────────
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

          // ── Tracking: InitiateCheckout ──────────────────
          var self = this;
          this.$nextTick(function () {
              if (typeof EcommerceTracker !== 'undefined' && self.records.length > 0) {
                  EcommerceTracker.initiateCheckout({
                      items:    self.records.map(function (r) { return { id: r.id, name: r.description, price: parseFloat(r.sale_unit_price) || 0, quantity: r.cantidad || 1 }; }),
                      total:    parseFloat(self.summary.total) || 0,
                      currency: 'PEN'
                  });
              }
          });


          this.records.forEach(function (item) {
            if(item.currency_type_id === 'USD') {
              item.sub_total = (parseFloat(item.sub_total) * exchange_rate_sale).toFixed(2)
              item.exchange_rate_sale = exchange_rate_sale
            }
            item.sale_unit_price = parseFloat(item.sale_unit_price).toFixed(2)
          })

          this.calculateSummary();
          // Validar stock real desde servidor (previene oversell por carrito desactualizado)
          this.validateCartStock();
          // Cargar descuentos automáticos (volumen, canal, etc.)
          this.$nextTick(() => this.loadAutoDiscounts());
        },
        created() {
            let array = localStorage.getItem('products_cart');
            array = JSON.parse(array)
            if (array) {
                this.records = array.map(function (item) {
                    let obj = item
                    obj.cantidad = item.quantity || 1
                    obj.sub_total = (parseFloat(item.sale_unit_price) * obj.cantidad).toFixed(2)
                    obj.exchange_rate_sale = ''
                    obj.stock_warning = false
                    return obj
                })
            }
            // console.log(this.records)
            this.initForm();

        },
        computed: {
            totalWithCoupon() {
                var base = parseFloat(this.summary.total) || 0;
                var disc = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                return Math.max(0, base - disc).toFixed(2);
            },
            totalFinal() {
                var base  = parseFloat(this.summary.total) || 0;
                var disc  = this.coupon.applied ? (this.coupon.discount || 0) : 0;
                var auto  = this.autoDiscount.discount || 0;
                var pdisc = this.points.applied ? (this.points.discount || 0) : 0;
                return Math.max(0, base - disc - auto - pdisc).toFixed(2);
            },
            maxPointsToApply() {
                var base      = parseFloat(this.summary.total) || 0;
                var couponDisc= this.coupon.applied ? (this.coupon.discount || 0) : 0;
                var auto      = this.autoDiscount.discount || 0;
                var afterCoupon = Math.max(0, base - couponDisc - auto);
                var maxByHalf = afterCoupon * 0.5;
                return Math.min(this.points.balance, maxByHalf);
            }
        },
        methods: {
            updateQtyItem(row, rawValue) {
                let value = parseInt(rawValue) || 1;
                if (value < 1) value = 1;
                const maxStock = parseInt(row.stock) || 9999;
                if (value > maxStock) {
                    value = maxStock;
                    this.$set(row, 'stock_warning', true);
                } else {
                    this.$set(row, 'stock_warning', false);
                }
                const rate = this.exchange_rate_sale || 1;
                if (row.currency_type_id === 'USD') {
                    row.sub_total = ((parseFloat(row.sale_unit_price) * value) * rate).toFixed(2);
                } else {
                    row.sub_total = (parseFloat(row.sale_unit_price) * value).toFixed(2);
                }
                row.cantidad = value;
                row.quantity = value;
                let cartArr = JSON.parse(localStorage.getItem('products_cart') || '[]');
                const rowVariantId = row.variant_id ?? null;
                let cartRow = cartArr.find(x => x.id == row.id && (x.variant_id ?? null) == rowVariantId);
                if (!cartRow) cartRow = cartArr.find(x => x.id == row.id);
                if (cartRow) { cartRow.quantity = value; localStorage.setItem('products_cart', JSON.stringify(cartArr)); }
                // Sync quantity change to server (FIX C3)
                if (typeof persistCartToServer === 'function') persistCartToServer();
                if (typeof productsCartDropDown === 'function') productsCartDropDown();
                if (typeof calculateTotalCart === 'function') calculateTotalCart();
                this.calculateSummary();
            },
            increaseQty(row) {
                const current = parseInt(row.cantidad) || 1;
                const maxStock = parseInt(row.stock) || 9999;
                if (current >= maxStock) {
                    this.$set(row, 'stock_warning', true);
                    return;
                }
                this.updateQtyItem(row, current + 1);
            },
            decreaseQty(row) {
                const current = parseInt(row.cantidad) || 1;
                if (current <= 1) return;
                this.updateQtyItem(row, current - 1);
            },
            validateCartStock() {
                if (!this.records.length) return;
                var self = this;
                var itemIds = this.records.map(function(r) { return r.id; });
                fetch('/ecommerce/stock-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ items: itemIds })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.stocks) {
                        var changed = false;
                        self.records.forEach(function(record) {
                            var fresh = data.stocks.find(function(s) { return s.id == record.id; });
                            if (fresh) {
                                self.$set(record, 'stock', fresh.stock);
                                if (record.cantidad > fresh.stock) {
                                    var newQty = Math.max(1, fresh.stock);
                                    if (fresh.stock <= 0) {
                                        self.$set(record, 'stock_warning', true);
                                    } else {
                                        self.updateQtyItem(record, newQty);
                                        self.$set(record, 'stock_warning', true);
                                    }
                                    changed = true;
                                }
                            }
                        });
                        if (changed) {
                            // Persist adjusted quantities to localStorage
                            localStorage.setItem('products_cart', JSON.stringify(self.records));
                            self.calculateSummary();
                        }
                    }
                })
                .catch(function() {});
            },
            proceedToCheckout() {
                if (this.records.length === 0) return;
                // Guardar estado de cupón y puntos para el checkout
                localStorage.setItem('checkout_state', JSON.stringify({
                    coupon: this.coupon,
                    points: this.points,
                    total:  this.totalFinal
                }));
                window.location.href = '/ecommerce/checkout';
            },
            async changeExchangeRate(exchange_rate_date){
                var response = await axios.get(`/exchange_rate/ecommence/${exchange_rate_date}`)
                this.exchange_rate_sale = parseFloat(response.data.sale)
            },
            optionDocument() {
                this.typeDocumentList = []
                this.typeDocuments = null
                // let voucher = [{id: '6', name: 'RUC'}]
                // let ticket = [{id: '0', name: 'DOC'},{id: '4', name: 'CE'},{id: '1', name: 'DNI'}]

                //   if(this.formIdentity.identity_document_type_id === '6') {
                //     this.typeDocumentList = voucher
                //   }else if (this.formIdentity.identity_document_type_id === '1' && this.payment_cash.amount >= 700) {
                //     this.typeDocumentList = [{id: '1', name: 'DNI'}]
                //     this.typeDocuments = ''
                //   } 
                //   else {
                //     this.typeDocumentList = ticket
                //   }

                if(this.form_document.codigo_tipo_documento == '01')
                {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['6'])
                }
                else if (this.form_document.codigo_tipo_documento == '03' && this.payment_cash.amount >= 700) 
                {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['1'])
                }
                else if (this.form_document.codigo_tipo_documento == '80') 
                {
                    this.typeDocumentList = (this.payment_cash.amount >= 700) ? this.getIdentityDocumentTypes(['6', '1']) : this.getIdentityDocumentTypes()
                } 
                else {
                    this.typeDocumentList = this.getIdentityDocumentTypes(['0', '1', '4'])
                }

            },
            getIdentityDocumentTypes(identity_document_types_id = null){

                if(!identity_document_types_id) return this.all_identity_document_types

                return this.all_identity_document_types.filter((item) => {
                    return identity_document_types_id.includes(item.id)
                })

            },
            refreshSetDataCustomer()
            {

                this.form_document.datos_del_cliente_o_receptor.direccion = this.form_contact.address
                this.form_document.datos_del_cliente_o_receptor.telefono = this.form_contact.telephone
                this.form_document.datos_del_cliente_o_receptor.codigo_tipo_documento_identidad = this.typeDocuments
                this.form_document.datos_del_cliente_o_receptor.numero_documento = this.numberDocument
                // this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.formIdentity.identity_document_type_id
                this.form_document.datos_del_cliente_o_receptor.identity_document_type_id = this.typeDocuments
                
            },
            async applyCoupon() {
                if (!this.coupon.code.trim()) return;
                this.coupon.loading = true;
                this.coupon.message = '';
                try {
                    var total = parseFloat(this.summary.total) || 0;
                    var res = await axios.post('{{ route("tenant_ecommerce_apply_coupon") }}', {
                        coupon_code: this.coupon.code.trim().toUpperCase(),
                        amount:      total,
                        items:       this.records,
                    }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                    if (res.data.success) {
                        this.coupon.applied       = true;
                        this.coupon.discount      = parseFloat(res.data.coupon_discount || res.data.discount) || 0;
                        this.coupon.code          = this.coupon.code.trim().toUpperCase();
                        this.coupon.message       = res.data.message;
                        this.autoDiscount.discount  = parseFloat(res.data.rule_discount) || 0;
                        this.autoDiscount.breakdown = res.data.breakdown || [];
                        this.autoDiscount.loaded    = true;
                        this.$nextTick(() => {
                            $("#total_amount").data('total', this.totalFinal);
                            this.payment_cash.amount = this.totalFinal;
                        });
                    } else {
                        this.coupon.applied  = false;
                        this.coupon.discount = 0;
                        this.coupon.message  = res.data.message;
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
                this.loadAutoDiscounts();
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            },
            async loadAutoDiscounts() {
                try {
                    var res = await axios.post('{{ route("tenant_ecommerce_preview_discounts") }}', {
                        amount: parseFloat(this.summary.total) || 0,
                        items:  this.records,
                    }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                    this.autoDiscount.discount  = parseFloat(res.data.rule_discount) || 0;
                    this.autoDiscount.breakdown = res.data.breakdown || [];
                    this.autoDiscount.loaded    = true;
                    this.$nextTick(() => {
                        $("#total_amount").data('total', this.totalFinal);
                        this.payment_cash.amount = this.totalFinal;
                    });
                } catch(e) { /* silencioso */ }
            },
            applyPoints() {
                var maxPts = this.maxPointsToApply;
                if (maxPts <= 0) return;
                this.points.applied  = true;
                this.points.discount = maxPts;
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            },
            removePoints() {
                this.points.applied  = false;
                this.points.discount = 0;
                this.$nextTick(() => {
                    $("#total_amount").data('total', this.totalFinal);
                    this.payment_cash.amount = this.totalFinal;
                });
            },
            async getFormPaymentCash() {

                this.refreshSetDataCustomer()

                var finalTotal = parseFloat(this.totalFinal);
                let precio = Math.round(finalTotal * 100).toFixed(2);
                let precio_culqi = finalTotal;
                return {
                    producto: 'Compras Ecommerce Facturador Pro',
                    precio: precio,
                    precio_culqi: precio_culqi,
                    customer: this.form_document.datos_del_cliente_o_receptor,
                    items: this.records,
                    purchase: await this.getDocument(),
                    coupon_code: this.coupon.applied ? this.coupon.code : '',
                    redeem_points: this.points.applied,
                    points_amount: this.points.applied ? this.points.discount : 0,
                }
            },
            showSwalMessage(title, text, type){

                swal({
                    title: title,
                    text: text,
                    type: type
                })

            },
            async paymentCash() {

                if(!this.form_document.codigo_tipo_documento) {
                    return this.showSwalMessage('Ocurrió un error!', 'El campo tipo de comprobante es obligatorio', 'error')
                }

                // verifica si tiene productos seleccionado
                let product = JSON.parse(localStorage.getItem('products_cart'));

                if (product.length < 1){
                    swal({
                        title: "No se han encontrado productos",
                        text: "Por favor seleccione algún producto de la tienda.",
                        type: "error"
                    })
                    return
                }

                swal({
                    title: "Estamos generando el Pago.",
                    text: `Por favor no cierre esta ventana hasta que el proceso termine.`,
                    focusConfirm: false,
                    onOpen: () => {
                        Swal.showLoading()
                    }
                });

                let url_finally = '{{ route("tenant_ecommerce_payment_cash")}}';
                let response = await axios.post(url_finally, await this.getFormPaymentCash(), this.getHeaderConfig()).then(response => {
                        if (response.data.success) {
                            // ── Tracking: Purchase ──────────────────────
                            if (typeof EcommerceTracker !== 'undefined') {
                                EcommerceTracker.purchase({
                                    orderId:  response.data.order ? String(response.data.order.id || '') : '',
                                    total:    parseFloat(this.summary.total) || 0,
                                    currency: 'PEN',
                                    items:    this.records.map(function (r) {
                                        return { id: r.id, name: r.description, price: parseFloat(r.sale_unit_price) || 0, quantity: r.cantidad || 1 };
                                    })
                                });
                            }
                            this.saveContactDataUser()
                            this.clearShoppingCart()
                            this.response_order_total = response.data.order.total
                            window.location = '/ecommerce/order/confirmation/' + response.data.order.external_id;
                        }
                    }).catch(error => {
                        let msg = 'Ocurrió un error al procesar el pago.';
                        let title = 'Error al procesar el pedido';
                        if (error.response) {
                            const status = error.response.status;
                            const d = error.response.data;
                            if (status === 422 && d && typeof d === 'object' && !d.message) {
                                this.errors = d;
                                const firstKey = Object.keys(d)[0];
                                title = 'Campos requeridos';
                                msg = Array.isArray(d[firstKey]) ? d[firstKey][0] : d[firstKey];
                            } else if (d && d.message) {
                                msg = d.message;
                                title = msg.toLowerCase().includes('stock') ? 'Stock insuficiente' : 'Error al procesar el pedido';
                            }
                        }
                        swal(title, msg, 'error');
                    });

            },
            redirectHome() {
                window.location = "{{ route('tenant.ecommerce.index') }}";
            },
            getHeaderConfig() {
                let token = this.user.api_token
                let axiosConfig = {
                    headers: {
                        "Content-Type": "application/json",
                        Authorization: `Bearer ${token}`
                    }
                };
                return axiosConfig;
            },
            async getDocument() {
                this.form_document.items = await this.getItemsDocument()
                this.form_document.totales = await this.getTotales()

                // if (this.formIdentity.identity_document_type_id === '6') {
                //     this.form_document.serie_documento = 'F001'
                //     this.form_document.codigo_tipo_documento = '01'
                // }
                // if (this.formIdentity.identity_document_type_id === '1') {
                //     this.form_document.serie_documento = 'B001'
                //     this.form_document.codigo_tipo_documento = '03'
                // }
                
                if (this.form_document.codigo_tipo_documento == '01')
                {
                    this.form_document.serie_documento = 'F001'
                }else if (this.form_document.codigo_tipo_documento == '03') 
                {
                    this.form_document.serie_documento = 'B001'
                }else
                {
                    this.form_document.serie_documento = null
                }


                return this.form_document
            },
            async getTotales() {

                let totals = await {
                    "total_exportacion": 0.00,
                    "total_operaciones_gravadas": this.aux_totals.total_taxed,
                    "total_operaciones_inafectas": 0.00,
                    "total_operaciones_exoneradas": this.aux_totals.total_exonerated,
                    "total_operaciones_gratuitas": 0.00,
                    "total_igv": this.aux_totals.total_igv,
                    "total_impuestos": this.aux_totals.total_igv,
                    "total_valor": this.aux_totals.total_value,
                    "total_venta": this.aux_totals.total
                }

                return totals
            },
            async getItemsDocument() {

                let rec = await this.records.map((item) => {

                    let sale_unit_price = 0
                    let unit_value = 0
                    let total_exonerated = 0
                    let total_igv = 0
                    let total_val = 0
                    let total = 0
                    let percentage_igv = 18
                    let nombre_producto_pdf = item.promotion_id ? item.description : null

                    if (item.sale_affectation_igv_type_id === '10') {

                        if(item.currency_type_id === 'USD') {
                            sale_unit_price = (parseFloat(item.sale_unit_price) * this.exchange_rate_sale).toFixed(2)
                        } else {
                            sale_unit_price = item.sale_unit_price
                        }

                        unit_value = sale_unit_price / (1 + percentage_igv / 100)
                        total_igv = item.cantidad * parseFloat(sale_unit_price - unit_value)
                        total = (item.cantidad * sale_unit_price)
                        //sale_unit_price = parseFloat(item.sale_unit_price)
                        total_val = (unit_value * item.cantidad)

                        return {
                            "codigo_interno": (item.internal_id) ? item.internal_id:"",
                            "descripcion": item.description,
                            "codigo_producto_sunat": "",
                            "unidad_de_medida": item.unit_type_id,
                            "cantidad": item.cantidad,
                            "valor_unitario": unit_value,
                            "codigo_tipo_precio": "01",
                            "precio_unitario": sale_unit_price,
                            "codigo_tipo_afectacion_igv": "10",
                            "total_base_igv": total_val,
                            "porcentaje_igv": percentage_igv,
                            "total_igv": total_igv,
                            "total_impuestos": total_igv,
                            "total_valor_item": total_val,
                            "total_item": total,
                            "actualizar_descripcion": false,
                            "nombre_producto_pdf": nombre_producto_pdf
                        }

                    }

                    if (item.sale_affectation_igv_type_id === '20') {

                        if(item.currency_type_id === 'USD') {
                            sale_unit_price = (parseFloat(item.sale_unit_price) * this.exchange_rate_sale).toFixed(2)
                        } else {
                            sale_unit_price = item.sale_unit_price
                        }

                        unit_value = parseFloat(sale_unit_price)
                        total_igv = 0
                        total = (parseFloat(item.cantidad) * parseFloat(sale_unit_price))
                        //sale_unit_price = parseFloat(item.sale_unit_price)
                        total_val = (parseFloat(unit_value) * parseFloat(item.cantidad))

                        return {
                            "codigo_interno": (item.internal_id) ? item.internal_id:"",
                            "descripcion": item.description,
                            "codigo_producto_sunat": "",
                            "unidad_de_medida": item.unit_type_id,
                            "cantidad": item.cantidad,
                            "valor_unitario": unit_value,
                            "codigo_tipo_precio": "01",
                            "precio_unitario": sale_unit_price,
                            "codigo_tipo_afectacion_igv": "20",
                            "total_base_igv": total_val,
                            "porcentaje_igv": percentage_igv,
                            "total_igv": 0,
                            "total_impuestos": 0,
                            "total_valor_item": total_val,
                            "total_item": total,
                            "actualizar_descripcion": false,
                            "nombre_producto_pdf": nombre_producto_pdf
                        }

                    }

                })

                return rec
            },
            initForm() {
              this.errors = {}
                this.user = {!! \Illuminate\Support\Js::from(Auth::guard("ecommerce")->user()) !!}
                if(!this.user){
                    return false
                }

                this.form_document = {
                    "acciones": {
                        "enviar_email": true,
                        "formato_pdf": "a4"
                    },
                    "serie_documento": "",
                    "numero_documento": "#",
                    "fecha_de_emision": moment().format('YYYY-MM-DD'),
                    "hora_de_emision": moment().format('HH:mm:ss'),
                    "codigo_tipo_operacion": "0101",
                    "codigo_tipo_documento": "03",
                    "codigo_tipo_moneda": "PEN",
                    "fecha_de_vencimiento": moment().format('YYYY-MM-DD'),
                    "datos_del_cliente_o_receptor": {
                        "codigo_tipo_documento_identidad": "0",
                        "numero_documento": "0",
                        "apellidos_y_nombres_o_razon_social": this.user.name,
                        "codigo_pais": "PE",
                        "ubigeo": "150101",
                        "direccion": this.user.address,
                        "correo_electronico": this.user.email,
                        "telefono": this.user.telephone
                    },
                    "totales": {},
                    "items": [],
                }


                // this.formIdentity = {
                //     identity_document_type_id: ''
                // }

                this.form_contact.address =  this.user.address
                this.form_contact.telephone =  this.user.telephone

                this.optionDocument()
            },
            deleteItem(id, index) {
                var removedRow = this.records[index];
                var removedVariantId = removedRow ? (removedRow.variant_id || null) : null;
                // Remove from Vue
                this.records.splice(index, 1);
                // Remove from localStorage
                var array = JSON.parse(localStorage.getItem('products_cart') || '[]');
                var indexFound = array.findIndex(function(x) { return x.id == id && (x.variant_id || null) == removedVariantId; });
                if (indexFound === -1) indexFound = array.findIndex(function(x) { return x.id == id; });
                if (indexFound !== -1) array.splice(indexFound, 1);
                localStorage.setItem('products_cart', JSON.stringify(array));

                // Sync to server (FIX C2)
                if (typeof persistCartToServer === 'function') persistCartToServer();
                if (typeof productsCartDropDown === 'function') productsCartDropDown();
                if (typeof calculateTotalCart === 'function') calculateTotalCart();

                // Si carrito quedó vacío, limpiar en servidor inmediatamente
                if (array.length === 0 && typeof clearCartOnServer === 'function') {
                    clearCartOnServer();
                }

                this.calculateSummary();
            },
            clearShoppingCart() {
                var self = this;
                self.errors = {};
                self.records_old = self.records;
                self.records = [];
                localStorage.setItem('products_cart', JSON.stringify([]));

                // Limpiar en servidor PRIMERO, luego reload (FIX C1)
                if (typeof clearCartOnServer === 'function') { clearCartOnServer(); }
                if (typeof productsCartDropDown === 'function') productsCartDropDown();
                if (typeof calculateTotalCart === 'function') calculateTotalCart();

                self.summary = {
                    subtotal: '0.0', tax: '0.0', total: '0.00',
                    total_taxed: '0.0', total_value: '0.0',
                    total_exonerated: '0.0', total_igv: '0.0'
                };
                self.payment_cash.amount = '0.00';

                // Esperar a que el servidor procese ANTES de recargar (FIX C1)
                setTimeout(function() { location.reload(); }, 500);
            },
            calculateSummary() {

                //let subtotal = 0.00
                let total_taxed = 0
                let total_value = 0
                let total_exonerated = 0
                let total_igv = 0
                let total = 0

                this.records.forEach(function (item) {

                    //subtotal += parseFloat(item.sub_total)

                    let unit_price = item.sub_total
                    let unit_value = unit_price
                    let percentage_igv = 18

                    if (item.sale_affectation_igv_type_id === '10') {
                        unit_value = item.sub_total / (1 + percentage_igv / 100)
                        total_taxed += parseFloat(unit_value)
                        total_igv += parseFloat(unit_price - unit_value)
                    }
                    if (item.sale_affectation_igv_type_id === '20') {
                        total_exonerated += parseFloat(unit_value)
                    }

                    total_value = total_taxed + total_exonerated
                    total += parseFloat(unit_price)
                })

                // console.log(total_taxed, total_exonerated, total_igv)

                this.summary.total_taxed = total_taxed.toFixed(2)
                this.summary.total_exonerated = total_exonerated.toFixed(2)
                this.summary.total_igv = total_igv.toFixed(2)
                this.summary.total_value = total_value.toFixed(2)
                this.summary.total = total.toFixed(2)
                this.aux_totals = this.summary

                // Reset coupon when cart changes (prices may differ)
                if (this.coupon && this.coupon.applied) {
                    this.removeCoupon();
                }

                // console.log(this.summary)


                $("#total_amount").data('total', this.summary.total);

                // this.formIdentity.identity_document_type_id = ''
                this.form_document.codigo_tipo_documento = null
                this.optionDocument()

                this.payment_cash.amount = this.summary.total;

                // let x =
                // console.log(x)

                // let subtotal = 0.00
                // this.records.forEach(function (item) {
                //     //console.log(item)
                //     subtotal += parseFloat(item.sub_total)
                // })

                // this.summary.subtotal = subtotal.toFixed(2)
                // let tax = (subtotal * 0.18)
                // this.summary.tax = tax.toFixed(2)
                // this.summary.total = (subtotal + tax).toFixed(2)
                // $("#total_amount").data('total', this.summary.total);

                // this.payment_cash.amount = this.summary.total
            },
            saveContactDataUser()
            {
                let url_finally = '{{ route("tenant_ecommerce_user_data")}}';
                axios.post(url_finally, this.form_contact, this.getHeaderConfig())
                    .then(response => {
                       console.log(response.data)
                    })
                    .catch(error => {

                    });
            },
            clickSendWhatsapp(order_id) {

                window.open(`https://wa.me/51${this.phone_whatsapp}?text=Se ha generado un nuevo pedido con código nro. ${order_id}`, '_blank');

            }
        }
    })

</script>

<script>
    Culqi.publicKey = {!! \Illuminate\Support\Js::from($configuration->token_public_culqui) !!};
    if(!Culqi.publicKey)
    {
      $('.culqi').hide()
/*
        swal({
            title: "Culqi configuración",
            text: "El pago con visa aun no esta disponible. Intente con efectivo.",
            type: "error",
            position: 'top-end',
            icon: 'warning',
        })
*/
    }
    Culqi.options({
        installments: true
    });

    async function askedDocument(order) {
        app_cart.order_generated = order
        $('#modal_ask_document').modal('show')
    }

    async function execCulqi() {

       console.log( 'errores', app_cart.errors)

       //app_cart.errors = 'demo'

    //   console.log( 'errores22', app_cart.errors)


        let precio = Math.round((Number($("#total_amount").data('total')) * 100).toFixed(2));
        if (precio > 0) {
            Culqi.settings({
                title: "Productos Ecommerce",
                currency: 'PEN',
                description: 'Compras Ecommerce Facturador Pro',
                amount: precio
            });
            Culqi.open();
        }
    }


    async function culqi() {
        if (Culqi.token) {

            swal({
                title: "Estamos hablando con su banco",
                text: `Por favor no cierre esta ventana hasta que el proceso termine.`,
                focusConfirm: false,
                onOpen: () => {
                    Swal.showLoading()
                }
            });

            let precio = Math.round((Number($("#total_amount").data('total')).toFixed(2) * 100));
            let precio_culqi = Number($("#total_amount").data('total')).toFixed(2);

            var url = "/culqi";
            var token = Culqi.token.id;
            var email = Culqi.token.email;
            var installments = Culqi.token.metadata.installments;

            const formpayment = await app_cart.getFormPaymentCash()

            var data = {
                producto: 'Compras Ecommerce Facturador Pro',
                precio: precio,
                precio_culqi: precio_culqi,
                token: token,
                email: email,
                installments: installments,
                customer: JSON.stringify(formpayment.customer),
                items: JSON.stringify(getItems()),
                purchase: JSON.stringify(formpayment.purchase),

            }

            $.ajax({
              url: "{{route('tenant_ecommerce_culqui')}}",
              method: 'post',
              headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              },
              data: data,
              dataType: 'JSON',
              success: function (data) {
                if (data.success == true) {
                  app_cart.saveContactDataUser();
                  // Marcar compra exitosa para evitar restauración del carrito
                  localStorage.setItem('ec_order_completed', '1');
                  localStorage.setItem('products_cart', JSON.stringify([]));
                  if (typeof clearCartOnServer === 'function') { clearCartOnServer(); }
                  if (typeof productsCartDropDown === 'function') productsCartDropDown();
                  if (typeof calculateTotalCart === 'function') calculateTotalCart();
                  window.location = '/ecommerce/order/confirmation/' + data.order.external_id;
                } else {
                  const message = data.message
                  swal("Pago No realizado", message, "error");
                }
              },
              error: function (error_data) {
                console.log(error_data)
                if (error_data.status === 422) {
                    app_cart.errors = JSON.parse( error_data.responseText);
                }
                swal("Pago No realizado", 'Faltan completar campos', "error");
              }
            });

        } else {
            console.log(Culqi.error);
            swal("Pago No realizado", Culqi.error.user_message, "error");
        }
    };

    function getCustomer() {
        let user = {!! \Illuminate\Support\Js::from(Auth::guard("ecommerce")->user()) !!}
        return {
            "codigo_tipo_documento_identidad": "0",
            "numero_documento": "0",
            "apellidos_y_nombres_o_razon_social": user.name,
            "codigo_pais": "PE",
            "ubigeo": "150101",
            "direccion": app_cart.user.address,
            "correo_electronico": user.email,
            "telefono": app_cart.user.telephone
        }
    }

    function getItems() {
        return app_cart.records
    }

    function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31 &&
            (charCode < 48 || charCode > 57))
            return false;
        return true;
    }

</script>

<script>
// ── Upsell: carga productos aleatorios del catálogo excluyendo los del carrito ──
(function () {
    var PLACEHOLDER = '/porto-ecommerce/assets/images/placeholder.svg';

    function getCartIds() {
        try {
            var cart = JSON.parse(localStorage.getItem('products_cart')) || [];
            return cart.map(function (p) { return String(p.id); });
        } catch (e) { return []; }
    }

    function buildCard(p) {
        var img  = (p.image_url_small && !p.image_url_small.includes('imagen-no-disponible'))
                   ? p.image_url_small : PLACEHOLDER;
        var href = p.slug ? '/ecommerce/item/' + p.slug : '/ecommerce/item/' + p.id;
        var rawPrice = parseFloat(p.amount_sale_unit_price || p.sale_unit_price) || 0;
        var price = rawPrice > 0 ? 'S/ ' + rawPrice.toFixed(2) : (p.sale_unit_price || '');

        var col = document.createElement('div');
        col.className = 'swiper-slide';
        col.innerHTML = [
            '<article class="ec-product-card">',
            '  <button type="button" class="ec-btn-wishlist" data-wishlist-id="' + p.id + '" aria-pressed="false" title="Guardar en favoritos">',
            '    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            '  </button>',
            '  <a href="' + href + '" class="ec-product-card__img-wrap" tabindex="-1">',
            '    <img src="' + PLACEHOLDER + '" data-src="' + img + '"',
            '         alt="' + (p.description || '') + '"',
            '         class="ec-product-card__img ec-img-lazy" width="300" height="300"',
            '         onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">',
            '  </a>',
            '  <div class="ec-product-card__body">',
            '    <h3 class="ec-product-card__title"><a href="' + href + '">' + (p.description || '') + '</a></h3>',
            '    <div class="ec-product-card__footer">',
            '      <div class="ec-product-card__price"><span class="ec-price-current">' + price + '</span></div>',
            '      <button type="button" class="ec-btn-cart paction add-cart"',
            '              data-product=\'' + JSON.stringify(p).replace(/'/g, "&#39;") + '\'',
            '              title="Agregar al carrito">',
            '        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
            '        <span class="ec-btn-cart__text">Agregar</span>',
            '      </button>',
            '    </div>',
            '  </div>',
            '</article>'
        ].join('');
        return col;
    }

    function shuffle(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
        }
        return arr;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var section = document.getElementById('ec-upsell');
        if (!section) return;

        fetch('/ecommerce/items_bar')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var cartIds = getCartIds();
                var catalog = (data.data || []).filter(function (p) {
                    return !cartIds.includes(String(p.id));
                });
                var picks = shuffle(catalog).slice(0, 10);
                if (!picks.length) return;

                var list = section.querySelector('.ec-upsell-list');
                picks.forEach(function (p) { list.appendChild(buildCard(p)); });
                section.style.display = '';

                // Inicializar Swiper
                if (typeof Swiper !== 'undefined') {
                    new Swiper('.ec-upsell-swiper', {
                        slidesPerView: 2,
                        spaceBetween: 16,
                        grabCursor: true,
                        navigation: {
                            nextEl: '.ec-upsell-next',
                            prevEl: '.ec-upsell-prev',
                        },
                        pagination: {
                            el: '.ec-upsell-pagination',
                            clickable: true,
                        },
                        breakpoints: {
                            576: { slidesPerView: 3, spaceBetween: 16 },
                            768: { slidesPerView: 4, spaceBetween: 16 },
                            1024: { slidesPerView: 5, spaceBetween: 16 },
                        }
                    });
                }

                if (window.EcLazyLoad) window.EcLazyLoad.scan();
                if (window.Wishlist)   window.Wishlist.getAll && document.querySelectorAll('[data-wishlist-id]').forEach(function (b) {
                    var id = String(b.getAttribute('data-wishlist-id'));
                    if (Wishlist.has(id)) {
                        b.classList.add('ec-btn-wishlist--active');
                        b.setAttribute('aria-pressed', 'true');
                        var svg = b.querySelector('svg');
                        if (svg) { svg.setAttribute('fill','#e53e3e'); svg.setAttribute('stroke','#e53e3e'); }
                    }
                });
            })
            .catch(function () {});
    });
}());
</script>

@endpush
