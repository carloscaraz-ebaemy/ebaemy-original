<template>
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5)">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-box me-2"></i>
                        Detalle Orden #{{ order.id }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Info general -->
                        <div class="col-md-6">
                            <h6 class="text-muted border-bottom pb-1">Cliente</h6>
                            <div class="fw-semibold">{{ order.customer?.name || '—' }}</div>
                            <div class="text-muted small">{{ order.customer?.number || '' }}</div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-muted border-bottom pb-1">Estado</h6>
                            <span :class="`badge bg-${order.badge_color} fs-6`">
                                {{ order.status_label }}
                            </span>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-muted border-bottom pb-1">Destino</h6>
                            <div>{{ order.destination_district }}</div>
                            <div class="text-muted small">{{ order.destination_address }}</div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-muted border-bottom pb-1">Destinatario</h6>
                            <div>{{ order.recipient_name }}</div>
                            <div class="text-muted small">{{ order.recipient_phone || '—' }}</div>
                        </div>

                        <!-- Ítems -->
                        <div class="col-12">
                            <h6 class="text-muted border-bottom pb-1">Productos</h6>
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in order.items" :key="item.item_id">
                                        <td>{{ item.description }}</td>
                                        <td class="text-center">{{ item.quantity }}</td>
                                        <td class="text-end">{{ order.currency_type_id }} {{ parseFloat(item.unit_price || 0).toFixed(2) }}</td>
                                        <td class="text-end fw-semibold">{{ order.currency_type_id }} {{ parseFloat(item.total || 0).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total</td>
                                        <td class="text-end fw-bold text-success">
                                            {{ order.currency_type_id }} {{ parseFloat(order.total || 0).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Guía de remisión (si fue despachado) -->
                        <div v-if="order.shipping_guide" class="col-12">
                            <div class="alert alert-success border-success mb-0">
                                <h6 class="alert-heading">
                                    <i class="fa fa-truck me-1"></i> Guía de Remisión
                                </h6>
                                <div class="row">
                                    <div class="col-6">
                                        <strong>N° Guía:</strong> {{ order.shipping_guide.full_number }}<br>
                                        <strong>Courier:</strong> {{ order.shipping_guide.carrier_name || '—' }}<br>
                                        <strong>Tracking:</strong> {{ order.shipping_guide.tracking_code || '—' }}
                                    </div>
                                    <div class="col-6">
                                        <strong>Fecha:</strong> {{ order.shipping_guide.dispatch_date || '—' }}<br>
                                        <a v-if="order.shipping_guide.pdf_url"
                                           :href="order.shipping_guide.pdf_url"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-success mt-2">
                                            <i class="fa fa-file-pdf me-1"></i> Ver PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div v-if="order.notes" class="col-12">
                            <h6 class="text-muted border-bottom pb-1">Notas</h6>
                            <p class="text-muted small mb-0">{{ order.notes }}</p>
                        </div>

                        <!-- Movimientos de stock -->
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between border-bottom pb-1 mb-2">
                                <h6 class="text-muted mb-0">Movimientos de Stock</h6>
                                <button class="btn btn-sm btn-outline-secondary" @click="loadMovements" :disabled="loadingMovements || movementsDebounced">
                                    <i class="fa fa-sync-alt me-1" :class="{ 'fa-spin': loadingMovements }"></i>
                                    {{ movementsLoaded ? 'Recargar' : 'Cargar' }}
                                </button>
                            </div>
                            <div v-if="loadingMovements" class="text-center text-muted py-2">
                                <i class="fa fa-spinner fa-spin me-1"></i> Cargando…
                            </div>
                            <div v-else-if="movementsLoaded && movements.length === 0" class="text-muted small text-center py-2">
                                Sin movimientos registrados.
                            </div>
                            <div v-else-if="movements.length" class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="font-size:.78rem">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Producto</th>
                                            <th class="text-end">Físico Δ</th>
                                            <th class="text-end">Comprometido Δ</th>
                                            <th class="text-end">Disponible</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="m in movements" :key="m.id">
                                            <td>
                                                <span :class="movementBadgeClass(m)" class="badge">
                                                    {{ m.type_label }}
                                                </span>
                                            </td>
                                            <td class="text-truncate" style="max-width:180px">{{ m.item_description }}</td>
                                            <td class="text-end fw-semibold" :class="m.qty_physical < 0 ? 'text-danger' : m.qty_physical > 0 ? 'text-success' : 'text-secondary'">
                                                {{ m.qty_physical > 0 ? '+' : '' }}{{ m.qty_physical }}
                                            </td>
                                            <td class="text-end fw-semibold" :class="m.qty_committed > 0 ? 'text-warning' : m.qty_committed < 0 ? 'text-success' : 'text-secondary'">
                                                {{ m.qty_committed > 0 ? '+' : '' }}{{ m.qty_committed }}
                                            </td>
                                            <td class="text-end fw-semibold">{{ m.stock_available_after }}</td>
                                            <td class="text-muted">{{ m.created_at }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <div class="text-muted small me-auto">
                        Creado: {{ order.created_at }}
                        <span v-if="order.dispatched_at"> · Despachado: {{ order.dispatched_at }}</span>
                    </div>
                    <button class="btn btn-secondary" @click="$emit('close')">Cerrar</button>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'

export default {
    name: 'OrderDetailModal',
    emits: ['close', 'refresh'],
    props: {
        order: { type: Object, required: true },
    },
    data() {
        return {
            movements: [],
            loadingMovements: false,
            movementsLoaded: false,
            movementsDebounced: false,
        }
    },
    methods: {
        async loadMovements() {
            if (this.movementsDebounced) return
            this.movementsDebounced = true
            setTimeout(() => { this.movementsDebounced = false }, 2000)
            this.loadingMovements = true
            try {
                const { data } = await axios.get(`/logistic/queue-json/${this.order.id}/stock-movements`)
                this.movements = data.data ?? []
            } catch (e) {
                console.error('Error cargando movimientos:', e)
            } finally {
                this.loadingMovements = false
                this.movementsLoaded = true
            }
        },
        movementBadgeClass(m) {
            const t = m.type
            if (t === 'province_commit' || t === 'ecommerce_reserve')    return 'bg-warning text-dark'
            if (t === 'province_dispatch' || t === 'ecommerce_dispatch')  return 'bg-danger'
            if (t === 'province_cancel' || t === 'ecommerce_cancel')      return 'bg-secondary'
            if (['purchase_entry','adjustment_in','transfer_in','return_restock'].includes(t)) return 'bg-success'
            if (t === 'sale_store') return 'bg-primary'
            return 'bg-light text-dark border'
        },
    },
}
</script>
