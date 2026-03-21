<template>
    <div class="wq-board">

        <!-- ── Header ─────────────────────────────────────────────── -->
        <div class="wq-header">
            <div class="wq-header__left">
                <div class="wq-title">
                    <i class="fa fa-warehouse me-2"></i>
                    Cola de Almacén
                </div>
                <div class="wq-connection" :title="isConnected ? 'Conectado vía WebSocket' : 'Sin WebSocket — modo polling'">
                    <span class="conn-dot" :class="isConnected ? 'conn-dot--online' : 'conn-dot--polling'"></span>
                    <span :class="isConnected ? 'text-success' : 'text-warning'">
                        {{ isConnected ? 'En vivo' : 'Cada 20s' }}
                    </span>
                </div>
            </div>
            <div class="wq-header__stats">
                <div class="wq-stat wq-stat--pending">
                    <span class="wq-stat__num">{{ columnOrders('confirmed').length }}</span>
                    <span class="wq-stat__label">Pendientes</span>
                </div>
                <div class="wq-stat wq-stat--prep">
                    <span class="wq-stat__num">{{ columnOrders('in_preparation').length }}</span>
                    <span class="wq-stat__label">Preparando</span>
                </div>
                <div class="wq-stat wq-stat--ready">
                    <span class="wq-stat__num">{{ columnOrders('ready').length }}</span>
                    <span class="wq-stat__label">Listos</span>
                </div>
                <button class="wq-refresh" @click="loadOrders" :disabled="loading" title="Actualizar">
                    <i class="fa fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- ── Alerta nuevo pedido ─────────────────────────────────── -->
        <transition name="alert-drop">
            <div v-if="newOrderAlert" class="wq-new-alert">
                <i class="fa fa-bell me-2"></i>
                <strong>¡Nuevo pedido!</strong>
                &nbsp;{{ newOrderAlert.recipient_name }}
                <button class="ms-auto btn-close btn-close-white btn-sm" @click="newOrderAlert = null"></button>
            </div>
        </transition>

        <!-- ── Spinner carga inicial ──────────────────────────────── -->
        <div v-if="loading && orders.length === 0" class="wq-loading">
            <div class="spinner-border text-primary"></div>
            <p>Cargando cola...</p>
        </div>

        <!-- ── Tablero Kanban ──────────────────────────────────────── -->
        <div v-else class="wq-kanban">

            <!-- Columna PENDIENTE -->
            <div class="wq-col">
                <div class="wq-col__header wq-col__header--pending">
                    <span class="col-icon"><i class="fa fa-inbox"></i></span>
                    <span class="col-title">Pendientes</span>
                    <span class="col-count">{{ columnOrders('confirmed').length }}</span>
                </div>
                <div class="wq-col__body">
                    <div v-if="columnOrders('confirmed').length === 0" class="wq-empty">
                        <i class="fa fa-check-circle text-success fa-2x mb-2"></i>
                        <p>Sin pedidos pendientes</p>
                    </div>
                    <order-card
                        v-for="order in columnOrders('confirmed')"
                        :key="order.id"
                        :order="order"
                        @start-preparation="startPreparation"
                        @mark-ready="markReady"
                        @dispatch="openDispatchModal"
                        @cancel="confirmCancel"
                        @view="openOrderDetail"
                        @complete-shipping="openShippingModal"
                    />
                </div>
            </div>

            <!-- Flecha -->
            <div class="wq-arrow"><i class="fa fa-arrow-right"></i></div>

            <!-- Columna EN PREPARACIÓN -->
            <div class="wq-col">
                <div class="wq-col__header wq-col__header--prep">
                    <span class="col-icon"><i class="fa fa-box-open"></i></span>
                    <span class="col-title">Preparando</span>
                    <span class="col-count">{{ columnOrders('in_preparation').length }}</span>
                </div>
                <div class="wq-col__body">
                    <div v-if="columnOrders('in_preparation').length === 0" class="wq-empty">
                        <i class="fa fa-box-open text-muted fa-2x mb-2"></i>
                        <p>Ningún pedido en preparación</p>
                    </div>
                    <order-card
                        v-for="order in columnOrders('in_preparation')"
                        :key="order.id"
                        :order="order"
                        @start-preparation="startPreparation"
                        @mark-ready="markReady"
                        @dispatch="openDispatchModal"
                        @cancel="confirmCancel"
                        @view="openOrderDetail"
                        @complete-shipping="openShippingModal"
                    />
                </div>
            </div>

            <!-- Flecha -->
            <div class="wq-arrow"><i class="fa fa-arrow-right"></i></div>

            <!-- Columna LISTO PARA DESPACHO -->
            <div class="wq-col">
                <div class="wq-col__header wq-col__header--ready">
                    <span class="col-icon"><i class="fa fa-truck"></i></span>
                    <span class="col-title">Listos para Despacho</span>
                    <span class="col-count">{{ columnOrders('ready').length }}</span>
                </div>
                <div class="wq-col__body">
                    <div v-if="columnOrders('ready').length === 0" class="wq-empty">
                        <i class="fa fa-truck text-muted fa-2x mb-2"></i>
                        <p>Ningún pedido listo</p>
                    </div>
                    <order-card
                        v-for="order in columnOrders('ready')"
                        :key="order.id"
                        :order="order"
                        @start-preparation="startPreparation"
                        @mark-ready="markReady"
                        @dispatch="openDispatchModal"
                        @cancel="confirmCancel"
                        @view="openOrderDetail"
                        @complete-shipping="openShippingModal"
                    />
                </div>
            </div>

        </div>

        <!-- ── Modales ─────────────────────────────────────────────── -->
        <order-detail-modal v-if="selectedOrder" :order="selectedOrder"
            @close="selectedOrder = null" @refresh="loadOrders" />

        <shipping-data-modal v-if="shippingOrder" :order="shippingOrder"
            @close="shippingOrder = null" @saved="onShippingSaved" />

        <dispatch-modal v-if="dispatchOrder" :order="dispatchOrder"
            @close="dispatchOrder = null" @dispatched="onOrderDispatched" />

    </div>
</template>

<script>
import axios from 'axios'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
import OrderCard from './partials/OrderCard.vue'
import OrderDetailModal from './partials/OrderDetailModal.vue'
import ShippingDataModal from './partials/ShippingDataModal.vue'
import DispatchModal from './partials/DispatchModal.vue'

export default {
    name: 'WarehouseQueue',
    components: { OrderCard, OrderDetailModal, ShippingDataModal, DispatchModal },

    props: {
        tenantUuid: { type: String, required: true },
        warehouseId: { type: Number, default: null },
    },

    computed: {
        columnOrders() {
            return (status) => this.orders.filter(o => o.status === status)
        },
    },

    data() {
        return {
            orders: [],
            summary: { pending_count: 0, in_preparation: 0 },
            pagination: { current_page: 1, last_page: 1, total: 0, per_page: 100 },
            filters: { status: '', source: '', page: 1 },
            loading: false,
            isConnected: false,
            newOrderAlert: null,
            selectedOrder: null,
            dispatchOrder: null,
            shippingOrder: null,
            channel: null,
            pollTimer: null,
            lastTotalPending: 0,
        }
    },

    mounted() {
        this.loadOrders()
        this.subscribeToWarehouseChannel()
        this.startPolling()
    },

    beforeUnmount() {
        this.leaveChannel()
        this.stopPolling()
    },

    methods: {
        // ─── Carga de datos ────────────────────────────────────────────────────

        async loadOrders() {
            this.loading = true
            try {
                const params = {
                    ...this.filters,
                    per_page: this.pagination.per_page,
                }
                const { data } = await axios.get('/logistic/queue-json', { params })
                this.orders     = data.data
                this.summary    = data.summary
                this.pagination = data.meta
            } catch (e) {
                console.error('[WarehouseQueue] Error cargando cola:', e)
            } finally {
                this.loading = false
            }
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page) return
            this.filters.page = page
            this.loadOrders()
        },

        // ─── Acciones de orden ─────────────────────────────────────────────────

        async startPreparation(order) {
            try {
                const { data } = await axios.post(`/logistic/queue-json/${order.id}/start-preparation`)
                // Actualizar la orden en la lista sin recargar todo
                this.replaceOrder(data.data)
                this.$notify?.({ type: 'success', text: `Preparación iniciada — Orden #${order.id}` })
            } catch (e) {
                const msg = e.response?.data?.message || 'Error iniciando preparación'
                this.$notify?.({ type: 'error', text: msg })
                console.error(e)
            }
        },

        async markReady(order) {
            try {
                const { data } = await axios.post(`/logistic/queue-json/${order.id}/ready`)
                this.replaceOrder(data.data)
                this.$notify?.({ type: 'success', text: `Orden #${order.id} lista para despacho.` })
            } catch (e) {
                const msg = e.response?.data?.message || 'Error al marcar como listo'
                alert(msg)
            }
        },

        openDispatchModal(order) {
            this.dispatchOrder = order
        },

        onOrderDispatched(updatedOrder) {
            this.dispatchOrder = null
            if (updatedOrder) {
                this.replaceOrder(updatedOrder)
            } else {
                this.loadOrders()
            }
            this.$notify?.({ type: 'success', text: 'Pedido despachado correctamente.' })
        },

        async confirmCancel(order) {
            if (!confirm(`¿Cancelar la orden #${order.id}?\nEsto liberará el stock reservado.`)) return
            try {
                await axios.post(`/logistic/queue-json/${order.id}/cancel`, {
                    reason: 'Cancelado manualmente desde almacén'
                })
                this.$notify?.({ type: 'warning', text: `Orden #${order.id} cancelada. Stock liberado.` })
                this.loadOrders()
            } catch (e) {
                this.$notify?.({ type: 'error', text: e.response?.data?.message || 'Error cancelando' })
            }
        },

        openOrderDetail(order) {
            this.selectedOrder = order
        },

        // ─── Completar datos de envío ──────────────────────────────────────────

        openShippingModal(order) {
            this.shippingOrder = order
        },

        onShippingSaved(updatedOrder) {
            this.shippingOrder = null
            this.replaceOrder(updatedOrder)
            this.$notify?.({ type: 'success', text: `Datos de envío guardados — Orden #${updatedOrder.id}` })
        },

        // ─── Helper: reemplaza una orden en la lista local ─────────────────────

        replaceOrder(updatedOrder) {
            const idx = this.orders.findIndex(o => o.id === updatedOrder.id)
            if (idx !== -1) {
                this.orders.splice(idx, 1, updatedOrder)
            }
        },

        // ─── Polling fallback ──────────────────────────────────────────────────

        startPolling() {
            this.pollTimer = setInterval(async () => {
                if (this.isConnected) return
                try {
                    const { data } = await axios.get('/logistic/queue-json', {
                        params: { per_page: this.pagination.per_page, ...this.filters }
                    })
                    const newTotal = data.summary?.pending_count ?? 0
                    if (newTotal > this.lastTotalPending) {
                        this.playAlert()
                        this.newOrderAlert = { id: '—', recipient_name: 'Nuevo pedido recibido', total: '' }
                        setTimeout(() => { this.newOrderAlert = null }, 6000)
                    }
                    this.lastTotalPending = newTotal
                    this.orders     = data.data
                    this.summary    = data.summary
                    this.pagination = data.meta
                } catch {}
            }, 20000)
        },

        stopPolling() {
            if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null }
        },

        // ─── Broadcasting ──────────────────────────────────────────────────────

        subscribeToWarehouseChannel() {
            if (!window.Echo) {
                console.warn('[WarehouseQueue] Echo no configurado. Sin notificaciones en tiempo real.')
                return
            }

            this.channel = window.Echo.private(`warehouse.${this.tenantUuid}`)

            this.channel.subscribed(() => {
                this.isConnected = true
            })

            .listen('ProvinceOrderCreated', (e) => {
                this.newOrderAlert = e.order
                this.playAlert()
                this.orders.unshift(e.order)
                this.summary.pending_count++
                setTimeout(() => { this.newOrderAlert = null }, 8000)
            })

            .listen('OrderStatusChanged', (e) => {
                const idx = this.orders.findIndex(o => o.id === e.order_id)
                if (idx !== -1) {
                    this.orders[idx].status       = e.new_status
                    this.orders[idx].status_label = e.new_status_label
                    this.orders[idx].badge_color  = e.badge_color
                }
                this.loadSummary()
            })

            .listen('OrderDispatched', (e) => {
                this.orders = this.orders.filter(o => o.id !== e.order_id)
                this.loadSummary()
            })

            .error((err) => {
                this.isConnected = false
                console.error('[WarehouseQueue] Error canal:', err)
            })
        },

        leaveChannel() {
            if (window.Echo && this.tenantUuid) {
                window.Echo.leave(`warehouse.${this.tenantUuid}`)
            }
            this.isConnected = false
        },

        async loadSummary() {
            try {
                const { data } = await axios.get('/logistic/queue-json', { params: { per_page: 1 } })
                this.summary = data.summary
            } catch {}
        },

        playAlert() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)()
                const osc = ctx.createOscillator()
                osc.connect(ctx.destination)
                osc.frequency.setValueAtTime(880, ctx.currentTime)
                osc.frequency.setValueAtTime(660, ctx.currentTime + 0.1)
                osc.start()
                osc.stop(ctx.currentTime + 0.2)
            } catch {}
        },
    },
}
</script>

<style scoped>
/* ── Board ───────────────────────────────────────────── */
.wq-board {
    min-height: 100vh;
    background: #f1f5f9;
    padding: 20px;
    font-family: system-ui, -apple-system, sans-serif;
}

/* ── Header ──────────────────────────────────────────── */
.wq-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background: #fff;
    border-radius: 12px;
    padding: 14px 20px;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
}
.wq-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e293b;
}
.wq-connection {
    font-size: .75rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 3px;
}
.conn-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.conn-dot--online  { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.2); }
.conn-dot--polling { background: #f59e0b; }

.wq-header__stats { display: flex; align-items: center; gap: 12px; }
.wq-stat {
    text-align: center;
    padding: 6px 16px;
    border-radius: 10px;
    min-width: 72px;
}
.wq-stat--pending { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); }
.wq-stat--prep    { background: rgba(59,130,246,.10); border: 1px solid rgba(59,130,246,.25); }
.wq-stat--ready   { background: rgba(16,185,129,.10); border: 1px solid rgba(16,185,129,.25); }
.wq-stat__num {
    display: block;
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1.1;
}
.wq-stat--pending .wq-stat__num { color: #b45309; }
.wq-stat--prep    .wq-stat__num { color: #1d4ed8; }
.wq-stat--ready   .wq-stat__num { color: #047857; }
.wq-stat__label { font-size: .7rem; color: #64748b; }
.wq-refresh {
    background: #f1f5f9;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    color: #64748b;
    cursor: pointer;
    transition: background .15s;
}
.wq-refresh:hover { background: #e2e8f0; }

/* ── Alerta nuevo pedido ─────────────────────────────── */
.wq-new-alert {
    background: linear-gradient(135deg, #059669, #10b981);
    color: #fff;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    font-size: .88rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16,185,129,.3);
}
.alert-drop-enter-active, .alert-drop-leave-active { transition: all .3s ease; }
.alert-drop-enter-from, .alert-drop-leave-to { opacity: 0; transform: translateY(-12px); }

/* ── Loading ─────────────────────────────────────────── */
.wq-loading {
    text-align: center;
    padding: 60px 0;
    color: #94a3b8;
}

/* ── Kanban ──────────────────────────────────────────── */
.wq-kanban {
    display: flex;
    align-items: flex-start;
    gap: 0;
}
.wq-col {
    flex: 1;
    min-width: 0;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}
.wq-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 12px;
    color: #cbd5e1;
    font-size: 1.2rem;
    padding-top: 60px;
    flex-shrink: 0;
}

/* ── Cabecera de columna ─────────────────────────────── */
.wq-col__header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    font-weight: 700;
    font-size: .88rem;
}
.wq-col__header--pending { background: #f59e0b; color: #fff; border-bottom: 2px solid #d97706; }
.wq-col__header--prep    { background: #3b82f6; color: #fff; border-bottom: 2px solid #2563eb; }
.wq-col__header--ready   { background: #10b981; color: #fff; border-bottom: 2px solid #059669; }

.col-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.wq-col__header--pending .col-icon { background: rgba(255,255,255,.25); }
.wq-col__header--prep    .col-icon { background: rgba(255,255,255,.25); }
.wq-col__header--ready   .col-icon { background: rgba(255,255,255,.25); }

.col-title { flex: 1; }
.col-count {
    background: rgba(0,0,0,.08);
    border-radius: 20px;
    padding: 2px 10px;
    font-size: .78rem;
}

/* ── Cuerpo de columna ───────────────────────────────── */
.wq-col__body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 200px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}
.wq-empty {
    text-align: center;
    padding: 30px 0;
    color: #94a3b8;
    font-size: .82rem;
}
</style>
