@extends('ecommerce::layouts.master')

@section('page_title', 'Mi cuenta')
@section('meta_description', 'Gestiona tu cuenta, pedidos y datos personales.')

@section('content')
<div class="container ec-profile-page">
    <div class="ec-profile-header">
        <div class="ec-profile-avatar" aria-hidden="true">
            {{ strtoupper(substr($user->name ?? $user->email ?? 'U', 0, 1)) }}
        </div>
        <div class="ec-profile-info">
            <h1 class="ec-profile-name">{{ $user->name ?? 'Mi cuenta' }}</h1>
            <p class="ec-profile-email">{{ $user->email }}</p>
        </div>
        <form method="POST" action="{{ route('tenant_ecommerce_logout') }}" class="ec-profile-logout-form">
            @csrf
            <button type="submit" class="ec-profile-logout-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>

    {{-- Tabs --}}
    <div class="ec-profile-tabs" id="ec-profile-tabs" role="tablist">
        <button class="ec-profile-tab ec-profile-tab--active" data-tab="orders" role="tab" aria-selected="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            Mis pedidos
        </button>
        <button class="ec-profile-tab" data-tab="data" role="tab" aria-selected="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Mis datos
        </button>
        <button class="ec-profile-tab" data-tab="password" role="tab" aria-selected="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Contraseña
        </button>
        <button class="ec-profile-tab" data-tab="points" role="tab" aria-selected="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="currentColor" style="color:#f59e0b" aria-hidden="true">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            Mis puntos
        </button>
    </div>

    {{-- Tab: Mis pedidos --}}
    <div class="ec-profile-panel ec-profile-panel--active" id="ec-tab-orders" role="tabpanel">
        <div id="ec-orders-app">
            <div class="ec-profile-toolbar">
                <div class="ec-profile-filters">
                    <select v-model="filters.state_order_id" @change="getOrders()" class="ec-profile-select">
                        <option value="">Todos los estados</option>
                        <option value="1">Pago sin verificar</option>
                        <option value="2">Pago verificado</option>
                        <option value="3">Despachado</option>
                        <option value="4">Confirmado</option>
                    </select>
                </div>
            </div>

            <div class="ec-orders-list" v-if="orders.length">
                <div class="ec-order-card" v-for="row in orders" :key="row.order_id">
                    <div class="ec-order-card__head">
                        <span class="ec-order-card__id"># @{{ row.order_id }}</span>
                        <span class="ec-order-badge" :class="'ec-order-badge--' + row.status_order_id">
                            @{{ row.status_order_description }}
                        </span>
                    </div>
                    <div class="ec-order-card__body">
                        <div class="ec-order-card__meta">
                            <span class="ec-order-card__label">Fecha</span>
                            <span>@{{ row.created_at }}</span>
                        </div>
                        <div class="ec-order-card__meta">
                            <span class="ec-order-card__label">Total</span>
                            <span class="ec-order-card__total">S/ @{{ row.total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ec-orders-empty" v-else-if="!loading">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <p>Aún no tienes pedidos.</p>
                <a href="{{ route('tenant.ecommerce.index') }}" class="ec-btn-primary">Ir a la tienda</a>
            </div>

            <div class="ec-orders-loading" v-if="loading">
                <div class="ec-spinner"></div>
            </div>

            {{-- Pagination --}}
            <div class="ec-profile-pagination" v-if="lastPage > 1">
                <button class="ec-page-btn" :disabled="page <= 1" @click="page--; getOrders()">&#8249;</button>
                <span>@{{ page }} / @{{ lastPage }}</span>
                <button class="ec-page-btn" :disabled="page >= lastPage" @click="page++; getOrders()">&#8250;</button>
            </div>
        </div>
    </div>

    {{-- Tab: Mis datos --}}
    <div class="ec-profile-panel" id="ec-tab-data" role="tabpanel">
        <form class="ec-profile-form" id="ec-data-form">
            @csrf
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-name">Nombre completo</label>
                <input type="text" id="pf-name" name="name" class="ec-form-input"
                       value="{{ $user->name }}" placeholder="Tu nombre">
            </div>
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-email">Correo electrónico</label>
                <input type="email" id="pf-email" class="ec-form-input ec-form-input--disabled"
                       value="{{ $user->email }}" disabled>
                <small class="ec-form-hint">El correo no puede modificarse.</small>
            </div>
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-telephone">Teléfono</label>
                <input type="text" id="pf-telephone" name="telephone" class="ec-form-input"
                       value="{{ $user->telephone }}" placeholder="Ej: 999888777">
            </div>
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-address">Dirección</label>
                <input type="text" id="pf-address" name="address" class="ec-form-input"
                       value="{{ $user->address }}" placeholder="Ej: Av. Ejemplo 123, Lima">
            </div>
            <div class="ec-form-actions">
                <button type="submit" class="ec-btn-primary" id="ec-data-submit">
                    Guardar cambios
                </button>
                <span class="ec-form-msg" id="ec-data-msg"></span>
            </div>
        </form>
    </div>

    {{-- Tab: Mis puntos --}}
    <div class="ec-profile-panel" id="ec-tab-points" role="tabpanel">
        <div id="ec-points-app">
            <div class="ec-points-profile-card" v-if="ptData.enabled">
                <div class="ec-points-balance-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"
                         fill="#f59e0b" aria-hidden="true">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <div>
                        <p class="ec-points-balance-num">@{{ ptData.balance.toFixed(0) }} pts</p>
                        <p class="ec-points-balance-label">= S/ @{{ ptData.balance.toFixed(2) }} de descuento</p>
                    </div>
                </div>
                <div class="ec-points-rule">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Por cada S/ @{{ ptData.sale_amount.toFixed(0) }} en compras ganas @{{ ptData.earn_rate }} punto(s).
                    Úsalos en tu próximo pedido como descuento.
                </div>
            </div>
            <div class="ec-points-profile-card ec-points-disabled" v-else>
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                     fill="none" stroke="#d1d5db" stroke-width="1.5" aria-hidden="true">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <p>El programa de puntos no está activo en esta tienda.</p>
            </div>

            <div class="ec-points-history" v-if="ptData.enabled && orders.length">
                <h4 class="ec-points-history-title">Historial de puntos en pedidos</h4>
                <table class="ec-points-table">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Canjeados</th>
                            <th>Ganados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orders" :key="o.order_id">
                            <td>#@{{ o.order_id }}</td>
                            <td>@{{ o.created_at }}</td>
                            <td>S/ @{{ o.total }}</td>
                            <td class="ec-pts-neg">
                                <span v-if="o.points_redeemed > 0">-@{{ o.points_redeemed }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="ec-pts-pos">+@{{ o.points_earned }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tab: Contraseña --}}
    <div class="ec-profile-panel" id="ec-tab-password" role="tabpanel">
        <form class="ec-profile-form" id="ec-pwd-form">
            @csrf
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-current-pwd">Contraseña actual</label>
                <div class="ec-input-pwd-wrap">
                    <input type="password" id="pf-current-pwd" name="current_password"
                           class="ec-form-input" placeholder="Contraseña actual" autocomplete="current-password">
                    <button type="button" class="ec-pwd-toggle" data-target="pf-current-pwd" aria-label="Mostrar/ocultar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-new-pwd">Nueva contraseña</label>
                <div class="ec-input-pwd-wrap">
                    <input type="password" id="pf-new-pwd" name="new_password"
                           class="ec-form-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                    <button type="button" class="ec-pwd-toggle" data-target="pf-new-pwd" aria-label="Mostrar/ocultar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="ec-form-group">
                <label class="ec-form-label" for="pf-confirm-pwd">Confirmar contraseña</label>
                <div class="ec-input-pwd-wrap">
                    <input type="password" id="pf-confirm-pwd" name="new_password_confirmation"
                           class="ec-form-input" placeholder="Repite la nueva contraseña" autocomplete="new-password">
                    <button type="button" class="ec-pwd-toggle" data-target="pf-confirm-pwd" aria-label="Mostrar/ocultar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="ec-form-actions">
                <button type="submit" class="ec-btn-primary" id="ec-pwd-submit">
                    Cambiar contraseña
                </button>
                <span class="ec-form-msg" id="ec-pwd-msg"></span>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Tabs ───────────────────────────────────────────────────────
    document.querySelectorAll('.ec-profile-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');

            document.querySelectorAll('.ec-profile-tab').forEach(function (t) {
                t.classList.remove('ec-profile-tab--active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.ec-profile-panel').forEach(function (p) {
                p.classList.remove('ec-profile-panel--active');
            });

            this.classList.add('ec-profile-tab--active');
            this.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('ec-tab-' + target);
            if (panel) panel.classList.add('ec-profile-panel--active');
        });
    });

    // ── Points Vue app ─────────────────────────────────────────────
    if (document.getElementById('ec-points-app')) {
        new Vue({
            el: '#ec-points-app',
            data: { ptData: { enabled: false, balance: 0, sale_amount: 10, earn_rate: 1 }, orders: [] },
            created: function () {
                var self = this;
                axios.get('/ecommerce/points').then(function (r) { self.ptData = r.data; });
                axios.get('/ecommerce/orders?page=1&per_page=50').then(function (r) {
                    self.orders = (r.data.data || []).filter(function (o) {
                        return o.points_earned > 0 || o.points_redeemed > 0;
                    });
                });
            }
        });
    }

    // ── Orders Vue app ─────────────────────────────────────────────
    if (document.getElementById('ec-orders-app')) {
        new Vue({
            el: '#ec-orders-app',
            data: {
                orders:   [],
                page:     1,
                lastPage: 1,
                loading:  false,
                filters:  { state_order_id: '' }
            },
            created: function () { this.getOrders(); },
            methods: {
                getOrders: function () {
                    var self = this;
                    self.loading = true;
                    var qs = '?page=' + self.page;
                    if (self.filters.state_order_id) {
                        qs += '&state_order_id=' + self.filters.state_order_id;
                    }
                    axios.get('/ecommerce/orders' + qs)
                        .then(function (r) {
                            self.orders   = r.data.data || [];
                            self.lastPage = r.data.last_page || 1;
                        })
                        .catch(function () { self.orders = []; })
                        .finally(function () { self.loading = false; });
                }
            }
        });
    }

    // ── Edit data form ─────────────────────────────────────────────
    var dataForm = document.getElementById('ec-data-form');
    if (dataForm) {
        dataForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('ec-data-submit');
            var msg = document.getElementById('ec-data-msg');
            btn.disabled = true;
            btn.textContent = 'Guardando…';

            var fd = new FormData(dataForm);
            var body = {};
            fd.forEach(function (v, k) { body[k] = v; });

            axios.post('/ecommerce/saveDataUser', body)
                .then(function (r) {
                    if (r.data.success) {
                        showMsg(msg, '¡Datos actualizados!', 'success');
                    } else {
                        showMsg(msg, r.data.message || 'Error al guardar.', 'error');
                    }
                })
                .catch(function () { showMsg(msg, 'Error de red.', 'error'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Guardar cambios';
                });
        });
    }

    // ── Change password form ───────────────────────────────────────
    var pwdForm = document.getElementById('ec-pwd-form');
    if (pwdForm) {
        pwdForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('ec-pwd-submit');
            var msg = document.getElementById('ec-pwd-msg');
            btn.disabled = true;
            btn.textContent = 'Cambiando…';

            var fd = new FormData(pwdForm);
            var body = {};
            fd.forEach(function (v, k) { body[k] = v; });

            axios.post('/ecommerce/change-password', body)
                .then(function (r) {
                    if (r.data.success) {
                        showMsg(msg, r.data.message, 'success');
                        pwdForm.reset();
                    } else {
                        showMsg(msg, r.data.message, 'error');
                    }
                })
                .catch(function () { showMsg(msg, 'Error de red.', 'error'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Cambiar contraseña';
                });
        });
    }

    // ── Password visibility toggles ────────────────────────────────
    document.querySelectorAll('.ec-pwd-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(this.getAttribute('data-target'));
            if (!inp) return;
            inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    // ── Helper ─────────────────────────────────────────────────────
    function showMsg(el, text, type) {
        el.textContent = text;
        el.className = 'ec-form-msg ec-form-msg--' + type;
        setTimeout(function () { el.textContent = ''; el.className = 'ec-form-msg'; }, 4000);
    }
}());
</script>
@endpush
