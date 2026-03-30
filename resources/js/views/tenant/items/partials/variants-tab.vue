<template>
    <div class="variants-tab">

        <!-- Sin ID todavía: el producto debe guardarse primero -->
        <el-alert v-if="!itemId"
                  title="Guarda el producto primero para poder agregar variantes."
                  type="info" show-icon :closable="false" class="mb-3" />

        <template v-else>
            <!-- ── Cabecera ─────────────────────────────────────────────── -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <strong>Variantes del producto</strong>
                    <span class="text-muted ms-2 small">
                        ({{ variants.length }} combinaciones)
                    </span>
                </div>
                <el-button size="small" type="primary" plain
                           icon="el-icon-plus"
                           @click="showOptionsEditor = true">
                    Gestionar opciones
                </el-button>
            </div>

            <!-- ── Editor de opciones (modal inline) ──────────────────── -->
            <el-dialog title="Opciones y valores"
                       :visible.sync="showOptionsEditor"
                       append-to-body width="540px"
                       :close-on-click-modal="false">

                <div v-for="(opt, oi) in editOptions" :key="oi" class="mb-3 border rounded p-2">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <el-input v-model="opt.name" placeholder="Ej: Color" size="small"
                                  style="width:160px" />
                        <span class="text-muted small ms-1">Valores:</span>
                        <el-button size="mini" type="success" plain icon="el-icon-plus"
                                   @click="addValue(oi)" />
                        <el-button size="mini" type="danger" plain icon="el-icon-delete"
                                   @click="removeOption(oi)" />
                    </div>
                    <div v-for="(val, vi) in opt.values" :key="vi"
                         class="d-flex align-items-center gap-2 mb-1 ms-2">
                        <el-input v-model="val.value" placeholder="Ej: Rojo" size="mini"
                                  style="width:130px" />
                        <el-color-picker v-model="val.color_hex" size="mini"
                                         title="Color (solo si es opción de color)" />
                        <el-button size="mini" plain icon="el-icon-close"
                                   @click="removeValue(oi, vi)" />
                    </div>
                </div>

                <el-button size="small" plain icon="el-icon-plus" @click="addOption">
                    Agregar opción
                </el-button>

                <div slot="footer">
                    <el-button @click="showOptionsEditor = false">Cancelar</el-button>
                    <el-button type="primary" :loading="saving"
                               @click="saveOptions">
                        Guardar y generar variantes
                    </el-button>
                </div>
            </el-dialog>

            <!-- ── Tabla de variantes ──────────────────────────────────── -->
            <div v-if="variants.length > 0" class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Variante</th>
                            <th style="width:120px">Precio venta</th>
                            <th style="width:90px">SKU</th>
                            <th style="width:80px">Stock</th>
                            <th style="width:70px">Activo</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in variants" :key="v.id"
                            :class="{ 'table-secondary text-muted': !v.is_active }">
                            <td>
                                <span class="fw-semibold">{{ v.display_name }}</span>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <el-tag v-for="ov in v.option_values" :key="ov.id"
                                            size="mini" type="info">
                                        {{ ov.value }}
                                    </el-tag>
                                </div>
                            </td>
                            <td>
                                <el-input-number v-model="v.sale_unit_price"
                                                 :min="0" :precision="4"
                                                 size="mini" style="width:110px"
                                                 :placeholder="String(parentPrice)"
                                                 @change="patchVariant(v)" />
                            </td>
                            <td>
                                <el-input v-model="v.sku" size="mini" style="width:80px"
                                          @change="patchVariant(v)" />
                            </td>
                            <td class="text-center">
                                <el-tooltip :content="stockTooltip(v)" placement="top">
                                    <span>{{ v.stock }}</span>
                                </el-tooltip>
                                <el-button size="mini" type="text" icon="el-icon-edit"
                                           @click="openStockDialog(v)" />
                            </td>
                            <td class="text-center">
                                <el-switch v-model="v.is_active" size="mini"
                                           @change="patchVariant(v)" />
                            </td>
                            <td class="text-center">
                                <el-popconfirm title="¿Eliminar esta variante?"
                                               @confirm="deleteVariant(v)">
                                    <el-button slot="reference" size="mini" type="danger"
                                               plain icon="el-icon-delete" />
                                </el-popconfirm>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <el-empty v-else description="Sin variantes. Define las opciones y genera las combinaciones." />

            <!-- ── Dialog de ajuste de stock ──────────────────────────── -->
            <el-dialog title="Ajustar stock de variante"
                       :visible.sync="showStockDialog"
                       append-to-body width="420px">
                <template v-if="selectedVariant">
                    <p class="mb-2">
                        <strong>{{ selectedVariant.display_name }}</strong>
                    </p>
                    <div v-for="ws in selectedVariant.warehouse_stocks" :key="ws.warehouse_id"
                         class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:160px" class="small font-weight-bold">{{ ws.warehouse_name || 'Almacén #' + ws.warehouse_id }}</span>
                            <el-input-number v-model="ws.stock_physical" :min="0" :precision="4"
                                             size="small" style="width:130px" />
                        </div>
                        <div v-if="ws.stock_committed > 0" class="small text-warning ml-1">
                            Comprometido (pedidos): {{ ws.stock_committed }} &nbsp;|&nbsp;
                            Disponible: {{ Math.max(0, ws.stock_physical - ws.stock_committed) }}
                        </div>
                    </div>
                    <div v-if="!selectedVariant.warehouse_stocks || selectedVariant.warehouse_stocks.length === 0">
                        <el-alert type="warning" :closable="false"
                                  title="No hay almacenes asignados a este producto." />
                    </div>
                </template>
                <div slot="footer">
                    <el-button @click="showStockDialog = false">Cancelar</el-button>
                    <el-button type="primary" :loading="savingStock"
                               @click="saveStock">Guardar stock</el-button>
                </div>
            </el-dialog>

        </template>
    </div>
</template>

<script>
export default {
    name: 'VariantsTab',

    props: {
        itemId:     { type: Number, default: null },
        parentPrice:{ type: Number, default: 0 },
    },

    data() {
        return {
            // Estado principal
            variants:    [],
            editOptions: [],

            // UI
            showOptionsEditor: false,
            showStockDialog:   false,
            saving:            false,
            savingStock:       false,
            selectedVariant:   null,
        }
    },

    watch: {
        itemId(newId) {
            if (newId) this.loadVariants()
        },
    },

    mounted() {
        if (this.itemId) this.loadVariants()
    },

    methods: {
        // ── Cargar datos ──────────────────────────────────────────────────

        loadVariants() {
            this.$http.get(`/items/${this.itemId}/variants`)
                .then(({ data }) => {
                    this.variants    = data.variants || []
                    this.editOptions = this.cloneOptions(data.options || [])
                })
                .catch(() => this.$message.error('Error al cargar variantes'))
        },

        // ── Gestión de opciones (editor) ─────────────────────────────────

        addOption() {
            this.editOptions.push({ name: '', position: this.editOptions.length, values: [] })
        },

        removeOption(oi) {
            this.editOptions.splice(oi, 1)
        },

        addValue(oi) {
            this.editOptions[oi].values.push({
                value: '', color_hex: null, position: this.editOptions[oi].values.length
            })
        },

        removeValue(oi, vi) {
            this.editOptions[oi].values.splice(vi, 1)
        },

        saveOptions() {
            // Validación básica
            for (const opt of this.editOptions) {
                if (!opt.name.trim()) {
                    return this.$message.warning('El nombre de la opción es obligatorio.')
                }
                if (opt.values.length === 0) {
                    return this.$message.warning(`La opción "${opt.name}" debe tener al menos un valor.`)
                }
                for (const v of opt.values) {
                    if (!v.value.trim()) {
                        return this.$message.warning('Todos los valores deben tener nombre.')
                    }
                }
            }

            this.saving = true
            this.$http.post(`/items/${this.itemId}/variants/options`, { options: this.editOptions })
                .then(({ data }) => {
                    this.variants          = data.variants || []
                    this.editOptions       = this.cloneOptions(data.options || [])
                    this.showOptionsEditor = false
                    this.$message.success(
                        `Variantes generadas: ${data.stats.created} nuevas, ${data.stats.deactivated} desactivadas.`
                    )
                    this.$emit('variants-updated', this.variants)
                })
                .catch(err => {
                    const msg = err.response?.data?.message || 'Error al guardar opciones'
                    this.$message.error(msg)
                })
                .finally(() => { this.saving = false })
        },

        // ── Edición de variante individual ───────────────────────────────

        patchVariant(variant) {
            this.$http.patch(`/items/${this.itemId}/variants/${variant.id}`, {
                sale_unit_price:    variant.sale_unit_price,
                sku:                variant.sku,
                is_active:          variant.is_active,
            })
                .then(({ data }) => {
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) this.$set(this.variants, idx, data.variant)
                })
                .catch(() => this.$message.error('Error al actualizar variante'))
        },

        deleteVariant(variant) {
            this.$http.delete(`/items/${this.itemId}/variants/${variant.id}`)
                .then(({ data }) => {
                    if (data.result === 'deleted') {
                        this.variants = this.variants.filter(v => v.id !== variant.id)
                        this.$message.success('Variante eliminada.')
                    } else {
                        // Desactivada: refrescar
                        this.loadVariants()
                        this.$message.info('Variante desactivada (tiene stock).')
                    }
                    this.$emit('variants-updated', this.variants)
                })
                .catch(() => this.$message.error('Error al eliminar variante'))
        },

        // ── Stock ─────────────────────────────────────────────────────────

        openStockDialog(variant) {
            // Clonar para editar sin mutar el array principal
            this.selectedVariant = JSON.parse(JSON.stringify(variant))
            this.showStockDialog = true
        },

        saveStock() {
            if (!this.selectedVariant) return
            const stocks = this.selectedVariant.warehouse_stocks || []
            if (stocks.length === 0) return this.$message.warning('Sin almacenes configurados.')

            this.savingStock = true
            const promises = stocks.map(ws =>
                this.$http.post(`/items/${this.itemId}/variants/${this.selectedVariant.id}/stock`, {
                    warehouse_id: ws.warehouse_id,
                    stock:        ws.stock_physical,
                })
            )

            Promise.all(promises)
                .then(() => {
                    this.showStockDialog = false
                    this.loadVariants()
                    this.$message.success('Stock actualizado.')
                })
                .catch(() => this.$message.error('Error al guardar stock'))
                .finally(() => { this.savingStock = false })
        },

        stockTooltip(v) {
            if (!v.warehouse_stocks || v.warehouse_stocks.length === 0) return 'Sin almacenes'
            return v.warehouse_stocks.map(ws =>
                `${ws.warehouse_name || 'Almacén'}: ${ws.stock_available} disp.`
            ).join(' | ')
        },

        // ── Helpers ───────────────────────────────────────────────────────

        cloneOptions(options) {
            return JSON.parse(JSON.stringify(options))
        },
    },
}
</script>

<style scoped>
.variants-tab .gap-1 { gap: 4px; }
.variants-tab .gap-2 { gap: 8px; }
.variants-tab table th,
.variants-tab table td { vertical-align: middle; }
</style>
