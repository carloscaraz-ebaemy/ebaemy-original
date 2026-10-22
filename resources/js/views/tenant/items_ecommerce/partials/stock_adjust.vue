<template>
    <!-- Ajustar stock desde la Tienda virtual. No tiene lógica de stock propia:
         guarda por /items/quick-stock, que pasa por StockAdjustmentService (el
         mismo camino que la pestaña de variantes) y deja kardex. -->
    <el-dialog
        :title="record ? 'Ajustar stock' : 'Cargando…'"
        :visible="visible"
        width="92%"
        custom-class="sa-dialog"
        append-to-body
        @close="close"
    >
        <div v-loading="loading" class="sa">
            <template v-if="record">
                <p class="sa__name">{{ record.description }}</p>

                <div v-if="record.bloqueo" class="sa__block">
                    {{ record.bloqueo }}
                </div>

                <template v-else>
                    <div v-if="record.warehouses.length > 1" class="sa__wh">
                        <label class="control-label">Almacén</label>
                        <el-radio-group v-model="warehouseId" size="small">
                            <el-radio-button
                                v-for="w in record.warehouses"
                                :key="w.id"
                                :label="w.id"
                            >{{ w.description }}</el-radio-button>
                        </el-radio-group>
                    </div>
                    <p v-else-if="record.warehouses.length" class="sa__hint">
                        {{ record.warehouses[0].description }}
                    </p>

                    <p class="sa__hint">
                        Escribe las unidades que tienes en físico. El cambio se registra en el
                        kardex y se refleja en ventas, tienda y marketplace.
                    </p>

                    <div class="sa__rows">
                        <div v-for="r in rows" :key="r.key" class="sa__row">
                            <div class="sa__label">
                                <strong>{{ r.label }}</strong>
                                <small class="text-muted">
                                    Actual: {{ fmt(current(r)) }}
                                    <span v-if="committed(r) > 0"> · {{ fmt(committed(r)) }} reservado</span>
                                </small>
                            </div>
                            <div class="sa__input">
                                <el-input-number
                                    v-model="draft[draftKey(r)]"
                                    :min="0"
                                    :step="1"
                                    size="small"
                                    controls-position="right"
                                ></el-input-number>
                                <span
                                    v-if="diff(r) !== 0"
                                    class="sa__diff"
                                    :class="diff(r) > 0 ? 'sa__diff--up' : 'sa__diff--down'"
                                >{{ diff(r) > 0 ? '+' : '' }}{{ fmt(diff(r)) }}</span>
                            </div>
                        </div>
                        <p v-if="!rows.length" class="sa__hint">Este producto no tiene variantes activas.</p>
                    </div>

                    <div class="form-group" style="margin-top:12px">
                        <label class="control-label">Motivo (opcional)</label>
                        <el-input v-model="reason" maxlength="150" placeholder="Ej. Conteo físico, mercadería dañada"></el-input>
                    </div>
                </template>
            </template>
        </div>

        <span slot="footer">
            <el-button @click="close">Cancelar</el-button>
            <el-button
                v-if="record && !record.bloqueo"
                type="primary"
                :loading="saving"
                :disabled="!changes.length"
                @click="save"
            >Guardar{{ changes.length > 1 ? ` (${changes.length})` : '' }}</el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    props: {
        visible: { type: Boolean, default: false },
        itemId: { type: Number, default: null },
    },
    data() {
        return {
            loading: false,
            saving: false,
            record: null,
            warehouseId: null,
            // Valor escrito por almacén y fila: "<warehouse>:<variante|item>"
            draft: {},
            reason: '',
        };
    },
    computed: {
        rows() {
            if (!this.record) return [];
            if (this.record.has_variants) {
                return this.record.variants.map(v => ({
                    key: 'v' + v.id,
                    variantId: v.id,
                    label: v.display_name + (v.sku ? ` · ${v.sku}` : ''),
                    stock: v.stock || {},
                }));
            }
            return [{ key: 'item', variantId: null, label: 'Stock del producto', stock: this.record.stock || {} }];
        },
        // Todos los cambios, de cualquier almacén: se guardan juntos.
        changes() {
            if (!this.record) return [];
            const out = [];
            this.record.warehouses.forEach(w => {
                this.rows.forEach(r => {
                    const k = w.id + ':' + r.key;
                    if (!(k in this.draft)) return;
                    const val = this.draft[k];
                    if (val === undefined || val === null) return;
                    if (Number(val) === this.stockOf(r, w.id)) return;
                    out.push({ warehouse_id: w.id, variant_id: r.variantId, stock: Number(val) });
                });
            });
            return out;
        },
    },
    watch: {
        visible(v) {
            if (v && this.itemId) this.load();
        },
        warehouseId() {
            this.seedDraft();
        },
    },
    methods: {
        load() {
            this.loading = true;
            this.record = null;
            this.draft = {};
            this.reason = '';
            this.$http
                .get(`/items/quick-stock/${this.itemId}`)
                .then(r => {
                    this.record = r.data;
                    const first = (r.data.warehouses || [])[0];
                    this.warehouseId = first ? first.id : null;
                    this.seedDraft();
                })
                .catch(() => {
                    this.$message.error('No se pudo cargar el stock');
                    this.close();
                })
                .then(() => { this.loading = false; });
        },
        // Prellena el almacén visible con el stock actual, sin pisar lo escrito.
        seedDraft() {
            if (!this.record || !this.warehouseId) return;
            this.rows.forEach(r => {
                const k = this.draftKey(r);
                if (!(k in this.draft)) this.$set(this.draft, k, this.stockOf(r, this.warehouseId));
            });
        },
        draftKey(r) {
            return this.warehouseId + ':' + r.key;
        },
        stockOf(r, warehouseId) {
            const s = r.stock[warehouseId];
            return s ? Number(s.physical) || 0 : 0;
        },
        current(r) {
            return this.stockOf(r, this.warehouseId);
        },
        committed(r) {
            const s = r.stock[this.warehouseId];
            return s ? Number(s.committed) || 0 : 0;
        },
        diff(r) {
            const v = this.draft[this.draftKey(r)];
            if (v === undefined || v === null) return 0;
            return Number(v) - this.current(r);
        },
        fmt(n) {
            return Number.isInteger(n) ? String(n) : Number(n).toFixed(2);
        },
        save() {
            if (!this.changes.length) return;
            this.saving = true;
            this.$http
                .post('/items/quick-stock', {
                    id: this.itemId,
                    reason: this.reason || null,
                    changes: this.changes,
                })
                .then(r => {
                    if (r.data && r.data.success) {
                        this.$message.success('Stock actualizado');
                        this.$emit('saved', { id: this.itemId, warehouses: r.data.warehouses });
                        this.close();
                    } else {
                        this.$message.error((r.data && r.data.message) || 'No se pudo actualizar');
                    }
                })
                .catch(e => {
                    const d = e.response && e.response.data;
                    const msg = (d && (d.message || (d.errors && Object.values(d.errors)[0][0]))) || 'Error al actualizar el stock';
                    this.$message.error(msg);
                })
                .then(() => { this.saving = false; });
        },
        close() {
            this.$emit('update:visible', false);
        },
    },
};
</script>

<style scoped>
.sa { min-height: 80px; }
.sa__name { font-weight: 600; margin: 0 0 10px; }
.sa__hint { color: #6b7280; font-size: 13px; margin: 0 0 10px; }
.sa__block {
    background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
    border-radius: 8px; padding: 10px 12px; font-size: 13px;
}
.sa__wh { margin-bottom: 10px; }
.sa__wh .el-radio-group { display: flex; flex-wrap: wrap; gap: 4px; }
.sa__rows { display: flex; flex-direction: column; gap: 8px; max-height: 50vh; overflow-y: auto; }
.sa__row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; flex-wrap: wrap;
}
.sa__label { display: flex; flex-direction: column; min-width: 0; flex: 1 1 160px; }
.sa__label strong { overflow-wrap: anywhere; }
.sa__input { display: flex; align-items: center; gap: 8px; }
.sa__diff { font-size: 12px; font-weight: 700; min-width: 36px; }
.sa__diff--up { color: #059669; }
.sa__diff--down { color: #dc2626; }
</style>

<style>
.sa-dialog { max-width: 480px; }
</style>
