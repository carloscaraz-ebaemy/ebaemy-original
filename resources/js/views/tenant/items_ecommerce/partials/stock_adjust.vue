<template>
    <!-- Ajustar stock desde la Tienda virtual. No tiene lógica de stock propia:
         guarda por /items/quick-stock, que pasa por StockAdjustmentService (el
         mismo camino que la pestaña de variantes) y deja kardex. -->
    <el-dialog
        :title="record ? 'Ajustar stock' : 'Cargando…'"
        :visible="visible"
        width="360px"
        top="12vh"
        custom-class="sa-dialog"
        append-to-body
        @close="close"
    >
        <div v-loading="loading" class="sa">
            <template v-if="record">
                <p class="sa__name" :title="record.description">{{ record.description }}</p>

                <div v-if="record.bloqueo" class="sa__block">
                    {{ record.bloqueo }}
                </div>

                <template v-else>
                    <el-select
                        v-if="record.warehouses.length > 1"
                        v-model="warehouseId"
                        size="mini"
                        class="sa__wh"
                    >
                        <el-option
                            v-for="w in record.warehouses"
                            :key="w.id"
                            :label="w.description"
                            :value="w.id"
                        ></el-option>
                    </el-select>

                    <div class="sa__rows">
                        <div v-for="r in rows" :key="r.key" class="sa__row">
                            <div class="sa__label">
                                <span class="sa__title">{{ r.label }}</span>
                                <small>
                                    Actual {{ fmt(current(r)) }}<template v-if="committed(r) > 0"> · {{ fmt(committed(r)) }} reserv.</template>
                                    <b
                                        v-if="diff(r) !== 0"
                                        :class="diff(r) > 0 ? 'sa__up' : 'sa__down'"
                                    >{{ diff(r) > 0 ? '+' : '' }}{{ fmt(diff(r)) }}</b>
                                </small>
                            </div>
                            <el-input-number
                                v-model="draft[draftKey(r)]"
                                :min="0"
                                :step="1"
                                size="mini"
                                controls-position="right"
                                class="sa__num"
                            ></el-input-number>
                        </div>
                        <p v-if="!rows.length" class="sa__hint">Sin variantes activas.</p>
                    </div>

                    <el-input
                        v-model="reason"
                        size="mini"
                        maxlength="150"
                        class="sa__reason"
                        placeholder="Motivo (opcional)"
                    ></el-input>
                </template>
            </template>
        </div>

        <span slot="footer">
            <el-button size="small" @click="close">Cancelar</el-button>
            <el-button
                v-if="record && !record.bloqueo"
                type="primary"
                size="small"
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
.sa { min-height: 60px; font-size: 13px; }
.sa__name {
    font-weight: 600; margin: 0 0 8px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sa__hint { color: #6b7280; font-size: 12px; margin: 0; }
.sa__block {
    background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
    border-radius: 6px; padding: 8px 10px; font-size: 12px;
}
.sa__rows { display: flex; flex-direction: column; max-height: 40vh; overflow-y: auto; }
.sa__row {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 6px 0; border-bottom: 1px solid #f1f5f9;
}
.sa__row:last-child { border-bottom: 0; }
.sa__label { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.3; }
.sa__title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sa__label small { color: #6b7280; font-size: 11px; }
.sa__up { color: #059669; margin-left: 4px; }
.sa__down { color: #dc2626; margin-left: 4px; }
</style>

<style>
/* erp-redesign.scss fuerza en TODO diálogo `max-width: 95vw !important` y los
   inputs a `width: 100% !important`; sin la especificidad extra el diálogo se
   abría casi a pantalla completa y cada fila ocupaba dos líneas. */
.el-dialog.sa-dialog { width: 360px !important; max-width: 94vw !important; }
.el-dialog.sa-dialog .el-dialog__header { padding: 12px 16px 6px; }
.el-dialog.sa-dialog .el-dialog__body { padding: 6px 16px 4px !important; }
.el-dialog.sa-dialog .el-dialog__footer { padding: 8px 16px 12px; }
.el-dialog.sa-dialog .sa__num { width: 96px !important; margin: 0 !important; flex: none; }
.el-dialog.sa-dialog .sa__num .el-input { margin: 0 !important; }
.el-dialog.sa-dialog .sa__wh { width: 100% !important; margin: 0 0 6px !important; }
.el-dialog.sa-dialog .sa__reason { margin: 8px 0 0 !important; }
.el-dialog.sa-dialog .el-dialog__footer .el-button { width: auto !important; margin: 0 0 0 6px !important; }
</style>
