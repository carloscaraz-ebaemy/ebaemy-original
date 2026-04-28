<template>
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">🛍️ Pedidos multi-tienda</h3>
            <div class="text-muted small">Pedidos creados desde ebaemy.com/marketplace</div>
        </div>

        <div class="row g-2 mb-3">
            <div v-for="t in tiles" :key="t.label" class="col-md-3 col-lg">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-2">
                        <div class="text-muted small">{{ t.label }}</div>
                        <div class="h4 mb-0" :class="`text-${t.color}`">{{ t.value }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" v-model="filters.q" @input="debouncedLoad"
                               class="form-control form-control-sm"
                               placeholder="Buscar por número, cliente, teléfono o email…">
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.status" @change="load(1)" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendiente</option>
                            <option value="partially_confirmed">Parcialmente confirmado</option>
                            <option value="confirmed">Confirmado</option>
                            <option value="partially_cancelled">Parcialmente cancelado</option>
                            <option value="cancelled">Cancelado</option>
                            <option value="completed">Completado</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end ms-auto">
                        <button class="btn btn-outline-secondary btn-sm" @click="load(filters.page)" :disabled="loading">
                            <span v-if="loading">…</span>
                            <span v-else>Refrescar</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th class="text-center">Tiendas</th>
                            <th class="text-center">Productos</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Fallos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="9" class="text-center py-4 text-muted">Cargando…</td>
                        </tr>
                        <tr v-else-if="!rows.length">
                            <td colspan="9" class="text-center py-4 text-muted">Sin pedidos para los filtros seleccionados.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td><small>{{ formatDate(row.created_at) }}</small></td>
                            <td><code>{{ row.order_number }}</code></td>
                            <td>
                                <div>{{ row.customer_name }}</div>
                                <small class="text-muted">{{ row.customer_phone }}</small>
                            </td>
                            <td class="text-center">{{ row.stores_count }}</td>
                            <td class="text-center">{{ row.items_count }}</td>
                            <td class="text-end">S/ {{ Number(row.total).toFixed(2) }}</td>
                            <td class="text-center">
                                <span class="badge" :class="statusBadge(row.status)">{{ statusLabel(row.status) }}</span>
                            </td>
                            <td class="text-center">
                                <span v-if="row.failed_count > 0" class="badge bg-danger">{{ row.failed_count }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="text-end">
                                <a :href="`/admin/marketplace/orders/${row.id}`" class="btn btn-sm btn-outline-primary">Detalle</a>
                                <button v-if="row.failed_count > 0" class="btn btn-sm btn-warning" @click="retry(row)">Reintentar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center bg-white">
                <small class="text-muted">{{ pageInfo }}</small>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            :disabled="filters.page <= 1 || loading"
                            @click="load(filters.page - 1)">← Anterior</button>
                    <button class="btn btn-sm btn-outline-secondary"
                            :disabled="filters.page >= lastPage || loading"
                            @click="load(filters.page + 1)">Siguiente →</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SystemMarketplaceOrders',
    props: {
        // Stats iniciales del controller (PHP -> blade -> componente).
        // Se serializan como JSON en el atributo del custom element.
        initialStats: {
            type: Object,
            default: () => ({
                total: 0,
                pending: 0,
                partially_confirmed: 0,
                confirmed: 0,
                partially_cancelled: 0,
                cancelled: 0,
                failed_dispatches: 0,
                last_24h: 0,
            }),
        },
    },
    data() {
        return {
            loading: false,
            rows: [],
            total: 0,
            lastPage: 1,
            filters: { q: '', status: '', page: 1 },
            debounceTimer: null,
        };
    },
    computed: {
        tiles() {
            const s = this.initialStats || {};
            return [
                { label: 'Total',                 value: s.total ?? 0,                                          color: 'secondary' },
                { label: 'Pendientes',            value: s.pending ?? 0,                                        color: 'warning' },
                { label: 'Confirmados',           value: s.confirmed ?? 0,                                      color: 'success' },
                { label: 'Parcialmente confirmados', value: s.partially_confirmed ?? 0,                         color: 'info' },
                { label: 'Cancelados',            value: (s.cancelled ?? 0) + (s.partially_cancelled ?? 0),     color: 'danger' },
                { label: 'Subpedidos fallidos',   value: s.failed_dispatches ?? 0,                              color: 'dark' },
                { label: 'Últimas 24h',           value: s.last_24h ?? 0,                                       color: 'primary' },
            ];
        },
        pageInfo() {
            return `Mostrando página ${this.filters.page} de ${this.lastPage} — ${this.total} resultado(s)`;
        },
    },
    mounted() {
        this.load(1);
    },
    methods: {
        debouncedLoad() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.load(1), 300);
        },
        load(page) {
            this.loading = true;
            this.filters.page = page;
            this.$http.get('/admin/marketplace/orders/records', { params: this.filters })
                .then(({ data }) => {
                    this.rows = data.data || [];
                    this.total = data.total || 0;
                    this.lastPage = data.last || 1;
                })
                .finally(() => { this.loading = false; });
        },
        retry(row) {
            if (!confirm(`Reintentar dispatch de ${row.failed_count} subpedido(s) fallidos del pedido ${row.order_number}?`)) return;
            this.$http.post(`/admin/marketplace/orders/${row.id}/retry`)
                .then(({ data }) => {
                    alert(data.message || 'Reintento ejecutado.');
                    this.load(this.filters.page);
                });
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
        },
        statusLabel(s) {
            return ({
                'pending': 'Pendiente',
                'partially_confirmed': 'Parcial',
                'confirmed': 'Confirmado',
                'partially_cancelled': 'Parcial-cancel.',
                'cancelled': 'Cancelado',
                'completed': 'Completado',
            })[s] || s;
        },
        statusBadge(s) {
            return ({
                'pending': 'bg-warning text-dark',
                'partially_confirmed': 'bg-info text-dark',
                'confirmed': 'bg-success',
                'partially_cancelled': 'bg-warning text-dark',
                'cancelled': 'bg-danger',
                'completed': 'bg-success',
            })[s] || 'bg-secondary';
        },
    },
};
</script>
