<template>
    <div class="multi-warehouse-selector">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 text-muted">
                <i class="fas fa-warehouse me-1"></i>
                Almacén de origen por producto
            </h6>
            <small class="text-muted">
                Cambia el almacén si el stock principal es insuficiente
            </small>
        </div>

        <div v-if="loading" class="text-center py-3">
            <span class="spinner-border spinner-border-sm text-secondary"></span>
            <span class="ms-2 text-muted small">Cargando stock...</span>
        </div>

        <table v-else class="table table-sm">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th>Almacén Origen</th>
                    <th class="text-center">Disponible</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, idx) in rows" :key="idx" class="align-middle">
                    <td>
                        <div class="fw-semibold">{{ row.description }}</div>
                    </td>
                    <td class="text-center">{{ row.quantity }}</td>
                    <td>
                        <select v-model="row.selectedWarehouseId"
                                class="form-select form-select-sm"
                                @change="loadStock(row)">
                            <option :value="null">— Almacén por defecto —</option>
                            <option v-for="wh in warehouses"
                                    :key="wh.id"
                                    :value="wh.id">
                                {{ wh.description }}
                            </option>
                        </select>
                    </td>
                    <td class="text-center">
                        <span v-if="row.loadingStock"
                              class="spinner-border spinner-border-sm text-secondary"></span>
                        <template v-else-if="row.selectedWarehouseId && row.stockData">
                            <span :class="`badge bg-${row.stockData.stock_available >= row.quantity ? 'success' : 'danger'}`"
                                  :title="`Físico: ${row.stockData.stock_physical} | Comprometido: ${row.stockData.stock_committed}`">
                                {{ parseFloat(row.stockData.stock_available).toFixed(2) }}
                                {{ row.stockData.stock_available >= row.quantity ? '✓' : '⚠' }}
                            </span>
                        </template>
                        <span v-else class="text-muted small">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: 'MultiWarehouseSelector',
    emits: ['update:overrides'],

    props: {
        /** Array de ítems: [{item_id, description, quantity}] */
        items: { type: Array, required: true },
        /** Array de almacenes disponibles: [{id, description}] */
        warehouses: { type: Array, required: true },
    },

    data() {
        return {
            loading: false,
            rows: [],
        }
    },

    watch: {
        items: {
            immediate: true,
            handler(val) {
                this.rows = val.map(item => ({
                    item_id:           item.item_id,
                    description:       item.description,
                    quantity:          item.quantity,
                    selectedWarehouseId: null,
                    stockData:         null,
                    loadingStock:      false,
                }))
            },
        },
        rows: {
            deep: true,
            handler(rows) {
                // Emite solo las filas con almacén seleccionado
                const overrides = rows
                    .filter(r => r.selectedWarehouseId)
                    .map(r => ({
                        item_id:      r.item_id,
                        warehouse_id: r.selectedWarehouseId,
                    }))
                this.$emit('update:overrides', overrides)
            },
        },
    },

    methods: {
        async loadStock(row) {
            if (!row.selectedWarehouseId) {
                row.stockData = null
                return
            }

            row.loadingStock = true
            try {
                const { data } = await this.$http.get(
                    `/api/logistic/sale-notes/stock-by-item/${row.item_id}`
                )
                row.stockData = data.stocks?.find(
                    s => s.warehouse_id === row.selectedWarehouseId
                ) || null
            } catch (e) {
                console.error('[MultiWarehouseSelector] Error cargando stock:', e)
                row.stockData = null
            } finally {
                row.loadingStock = false
            }
        },
    },
}
</script>
