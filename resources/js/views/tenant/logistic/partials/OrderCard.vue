<template>
    <div class="order-card" :class="['order-card--' + order.status, order.is_urgent ? 'order-card--urgent' : '']">

        <!-- Barra de progreso del flujo -->
        <div class="order-card__progress">
            <div class="progress-step" :class="{ active: true, done: stepIndex >= 0 }">
                <span class="step-dot"><i class="fa fa-inbox"></i></span>
            </div>
            <div class="progress-line" :class="{ done: stepIndex >= 1 }"></div>
            <div class="progress-step" :class="{ active: stepIndex >= 1, done: stepIndex >= 1 }">
                <span class="step-dot"><i class="fa fa-box-open"></i></span>
            </div>
            <div class="progress-line" :class="{ done: stepIndex >= 2 }"></div>
            <div class="progress-step" :class="{ active: stepIndex >= 2, done: stepIndex >= 2 }">
                <span class="step-dot"><i class="fa fa-check"></i></span>
            </div>
            <div class="progress-line" :class="{ done: stepIndex >= 3 }"></div>
            <div class="progress-step" :class="{ active: stepIndex >= 3, done: stepIndex >= 3 }">
                <span class="step-dot"><i class="fa fa-truck"></i></span>
            </div>
        </div>

        <!-- Cabecera -->
        <div class="order-card__header">
            <div class="d-flex align-items-center gap-2">
                <span class="order-number">NV #{{ order.number_full || order.id }}</span>
                <span v-if="order.is_urgent" class="badge-urgent">
                    <i class="fa fa-bolt me-1"></i>URGENTE
                </span>
                <span v-if="order.source === 'ecommerce'" class="badge-web">
                    <i class="fa fa-globe me-1"></i>Web
                </span>
            </div>
            <span class="order-time">{{ order.confirmed_at }}</span>
        </div>

        <!-- Alerta datos faltantes -->
        <button v-if="order.missing_shipping" type="button" class="missing-alert" @click="$emit('complete-shipping', order)">
            <i class="fa fa-exclamation-triangle me-2"></i>
            <span>Faltan datos de envío</span>
            <span class="ms-auto badge bg-warning text-dark">Completar →</span>
        </button>

        <!-- Destinatario -->
        <div class="order-card__recipient">
            <div class="recipient-name">
                <i class="fa fa-user-circle text-primary me-2"></i>
                {{ order.recipient_name || '—' }}
            </div>
            <div v-if="order.recipient_phone" class="recipient-detail">
                <i class="fa fa-phone me-1"></i>{{ order.recipient_phone }}
            </div>
            <div class="recipient-detail" :class="order.destination_address === '—' ? 'text-warning' : ''">
                <i class="fa fa-map-marker-alt me-1"></i>
                {{ order.destination_district ? order.destination_district + ' · ' : '' }}{{ order.destination_address }}
            </div>
        </div>

        <!-- Ítems -->
        <div class="order-card__items">
            <div v-for="item in order.items.slice(0, 3)" :key="item.item_id" class="item-row">
                <span class="item-qty">× {{ item.quantity }}</span>
                <span class="item-name text-truncate">{{ item.description }}</span>
            </div>
            <div v-if="order.items.length > 3" class="item-more">
                +{{ order.items.length - 3 }} productos más
            </div>
        </div>

        <!-- Total + almacén -->
        <div class="order-card__footer">
            <div class="d-flex align-items-center gap-1 text-muted" style="font-size:.78rem" v-if="order.warehouse_id">
                <i class="fa fa-warehouse"></i>
                <span>Almacén #{{ order.warehouse_id }}</span>
            </div>
            <div class="order-total">
                <span class="currency">{{ order.currency_type_id }}</span>
                <span class="amount">{{ formatAmount(order.total) }}</span>
            </div>
        </div>

        <!-- Botón de acción principal -->
        <div class="order-card__actions">

            <button v-if="order.status === 'confirmed'"
                    class="btn-action btn-action--primary"
                    @click="$emit('start-preparation', order)">
                <i class="fa fa-play-circle me-2"></i>
                Iniciar Preparación
            </button>

            <button v-if="order.status === 'in_preparation'"
                    class="btn-action btn-action--info"
                    :disabled="order.missing_shipping"
                    @click="$emit('mark-ready', order)">
                <i class="fa fa-check-circle me-2"></i>
                Listo para Despacho
            </button>

            <button v-if="order.status === 'ready'"
                    class="btn-action btn-action--success"
                    :disabled="order.missing_shipping"
                    @click="$emit('dispatch', order)">
                <i class="fa fa-truck me-2"></i>
                Despachar ahora
            </button>

            <!-- Acciones secundarias -->
            <div class="d-flex gap-1 mt-1">
                <button class="btn-secondary-action flex-fill" @click="$emit('view', order)">
                    <i class="fa fa-eye me-1"></i> Detalle
                </button>
                <button v-if="!['dispatched','delivered','cancelled'].includes(order.status)"
                        class="btn-secondary-action btn-secondary-action--danger"
                        @click="$emit('cancel', order)">
                    <i class="fa fa-times me-1"></i> Cancelar
                </button>
            </div>
        </div>

    </div>
</template>

<script>
const STEP_MAP = { confirmed: 0, in_preparation: 1, ready: 2, dispatched: 3 }

export default {
    name: 'OrderCard',
    emits: ['start-preparation', 'mark-ready', 'dispatch', 'cancel', 'view', 'complete-shipping'],
    props: {
        order: { type: Object, required: true },
    },
    computed: {
        stepIndex() {
            return STEP_MAP[this.order.status] ?? 0
        },
    },
    methods: {
        formatAmount(val) {
            return parseFloat(val || 0).toFixed(2)
        },
    },
}
</script>

<style scoped>
/* ── Card base ───────────────────────────────── */
.order-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform .15s, box-shadow .15s;
    border-top: 4px solid #dee2e6;
}
.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,.12);
}
.order-card--confirmed   { border-top-color: #f59e0b; }
.order-card--in_preparation { border-top-color: #3b82f6; }
.order-card--ready       { border-top-color: #10b981; }
.order-card--dispatched  { border-top-color: #6b7280; }
.order-card--urgent      { animation: pulse-card 1.2s ease-in-out infinite; border-top-color: #ef4444 !important; }

/* ── Barra de progreso ───────────────────────── */
.order-card__progress {
    display: flex;
    align-items: center;
    padding: 10px 16px 6px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.progress-step { display: flex; align-items: center; }
.step-dot {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #94a3b8;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem;
    transition: all .2s;
}
.progress-step.active .step-dot,
.progress-step.done .step-dot {
    background: #3b82f6;
    color: #fff;
}
.progress-step.done .step-dot { background: #10b981; }
.progress-line {
    flex: 1; height: 3px;
    background: #e2e8f0;
    margin: 0 4px;
    border-radius: 2px;
    transition: background .2s;
}
.progress-line.done { background: #10b981; }

/* ── Cabecera ────────────────────────────────── */
.order-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px 6px;
}
.order-number {
    font-weight: 700;
    font-size: .95rem;
    color: #1e293b;
}
.order-time {
    font-size: .75rem;
    color: #94a3b8;
}
.badge-urgent {
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 1px 7px;
    font-size: .7rem;
    font-weight: 700;
    animation: pulse-badge 1.5s infinite;
}
.badge-web {
    background: #eff6ff;
    color: #3b82f6;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 1px 7px;
    font-size: .7rem;
}
@keyframes pulse-badge {
    0%,100% { opacity:1; }
    50%      { opacity:.6; }
}
@keyframes pulse-card {
    0%,100% { box-shadow: 0 0 0 2px #ef4444, 0 4px 16px rgba(239,68,68,.25); }
    50%      { box-shadow: 0 0 0 4px #ef4444, 0 6px 28px rgba(239,68,68,.55); }
}

/* ── Alerta envío ────────────────────────────── */
.missing-alert {
    margin: 0 12px 4px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    width: calc(100% - 24px);
    text-align: left;
    font-size: .78rem;
    color: #92400e;
    cursor: pointer;
    transition: background .15s;
}
.missing-alert:hover { background: #fef3c7; }

/* ── Destinatario ────────────────────────────── */
.order-card__recipient {
    padding: 8px 16px;
}
.recipient-name {
    font-weight: 600;
    font-size: .88rem;
    color: #1e293b;
    margin-bottom: 3px;
}
.recipient-detail {
    font-size: .78rem;
    color: #64748b;
    margin-bottom: 2px;
}

/* ── Ítems ───────────────────────────────────── */
.order-card__items {
    padding: 6px 16px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}
.item-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 2px 0;
}
.item-qty {
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 4px;
    padding: 1px 6px;
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
    min-width: 36px;
    text-align: center;
}
.item-name {
    font-size: .78rem;
    color: #475569;
    flex: 1;
    min-width: 0;
}
.item-more {
    font-size: .72rem;
    color: #94a3b8;
    padding-top: 2px;
}

/* ── Footer ──────────────────────────────────── */
.order-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 16px 4px;
}
.order-total {
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.currency { font-size: .72rem; color: #64748b; }
.amount   { font-size: 1.1rem; font-weight: 700; color: #059669; }

/* ── Acciones ────────────────────────────────── */
.order-card__actions { padding: 8px 12px 12px; }

.btn-action {
    width: 100%;
    padding: 9px;
    border: none;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
}
.btn-action:disabled { opacity: .35; cursor: not-allowed; filter: grayscale(.4); }

.btn-action--primary { background: #3b82f6; color: #fff; }
.btn-action--primary:hover:not(:disabled) { background: #2563eb; }

.btn-action--info    { background: #0ea5e9; color: #fff; }
.btn-action--info:hover:not(:disabled)    { background: #0284c7; }

.btn-action--success { background: #10b981; color: #fff; }
.btn-action--success:hover:not(:disabled) { background: #059669; }

.btn-secondary-action {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    padding: 5px 10px;
    font-size: .78rem;
    color: #475569;
    cursor: pointer;
    transition: background .15s;
}
.btn-secondary-action:hover { background: #e2e8f0; }
.btn-secondary-action--danger { color: #ef4444; }
.btn-secondary-action--danger:hover { background: #fef2f2; border-color: #fecaca; }
</style>
