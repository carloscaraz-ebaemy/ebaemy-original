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
                        <span v-if="isColorOption(opt.name)" class="badge bg-info text-white small"
                              style="font-size:10px;font-weight:600">🎨 con color</span>
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
                        <el-color-picker v-if="isColorOption(opt.name)"
                                         v-model="val.color_hex" size="mini"
                                         title="Define el color para que aparezca como punto en las cards del marketplace" />
                        <el-button size="mini" plain icon="el-icon-close"
                                   @click="removeValue(oi, vi)" />
                    </div>
                    <div v-if="isColorOption(opt.name)" class="ms-2 mt-1" style="font-size:11px;color:#6b7280">
                        💡 Define el color de cada valor. Aparecerá como punto/círculo en el listado del marketplace.
                    </div>

                    <!-- Chips predefinidos: el seller agrega valores comunes con un click -->
                    <template v-if="isColorOption(opt.name)">
                        <div class="vt-pre-label">⚡ Agregar rápido (click para añadir):</div>
                        <div class="vt-pre-chips">
                            <button v-for="pc in preColors" :key="pc.value"
                                    type="button"
                                    class="vt-chip vt-chip--color"
                                    :title="pc.value"
                                    @click="addPredefined(oi, pc)">
                                <span class="vt-chip__swatch" :style="{ background: pc.color_hex }"></span>
                                {{ pc.value }}
                            </button>
                        </div>
                    </template>

                    <template v-if="isSizeOption(opt.name)">
                        <div class="vt-pre-label">⚡ Agregar rápido — Tallas de ropa:</div>
                        <div class="vt-pre-chips">
                            <button v-for="s in preSizesClothing" :key="'c'+s"
                                    type="button" class="vt-chip"
                                    @click="addPredefined(oi, { value: s, color_hex: null })">
                                {{ s }}
                            </button>
                        </div>
                        <div class="vt-pre-label">⚡ Calzado:</div>
                        <div class="vt-pre-chips">
                            <button v-for="s in preSizesShoes" :key="'sh'+s"
                                    type="button" class="vt-chip"
                                    @click="addPredefined(oi, { value: s, color_hex: null })">
                                {{ s }}
                            </button>
                        </div>
                    </template>
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
                            <th style="width:60px">Imagen</th>
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
                            <td class="text-center">
                                <el-upload :action="`/items/${itemId}/variants/${v.id}/image`"
                                           :headers="headers"
                                           :show-file-list="false"
                                           :on-success="(r) => onVariantImageSuccess(v, r)"
                                           :on-error="onVariantImageError"
                                           :before-upload="beforeVariantImage"
                                           accept="image/jpeg,image/jpg,image/png,image/webp,image/bmp"
                                           name="file"
                                           class="vt-img-uploader">
                                    <div v-if="v.image_url" class="vt-img-thumb"
                                         :style="`background-image:url('${v.image_url}')`">
                                        <button type="button" class="vt-img-del"
                                                @click.stop.prevent="deleteVariantImage(v)"
                                                title="Quitar imagen">×</button>
                                    </div>
                                    <div v-else class="vt-img-empty" title="Subir imagen para esta variante">
                                        📷+
                                    </div>
                                </el-upload>
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

            // Headers para uploads autenticados (Sanctum/CSRF) — la global
            // headers_token la setea el layout principal del tenant.
            headers: typeof headers_token !== 'undefined' ? headers_token : {},

            // Paletas predefinidas para que el seller agregue valores con
            // un click en lugar de teclear nombre + abrir color picker.
            // Cubren los casos típicos de ropa/calzado/accesorios.
            preColors: [
                { value: 'Rojo',     color_hex: '#dc2626' },
                { value: 'Negro',    color_hex: '#0a0a0a' },
                { value: 'Blanco',   color_hex: '#ffffff' },
                { value: 'Gris',     color_hex: '#6b7280' },
                { value: 'Azul',     color_hex: '#2563eb' },
                { value: 'Celeste',  color_hex: '#0ea5e9' },
                { value: 'Verde',    color_hex: '#16a34a' },
                { value: 'Amarillo', color_hex: '#eab308' },
                { value: 'Naranja',  color_hex: '#ea580c' },
                { value: 'Rosa',     color_hex: '#ec4899' },
                { value: 'Morado',   color_hex: '#9333ea' },
                { value: 'Marrón',   color_hex: '#78350f' },
                { value: 'Beige',    color_hex: '#d6b88a' },
                { value: 'Mostaza',  color_hex: '#ca8a04' },
                { value: 'Vino',     color_hex: '#7f1d1d' },
                { value: 'Plomo',    color_hex: '#9ca3af' },
            ],
            preSizesClothing: ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'ÚNICA'],
            preSizesShoes:    ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'],
            preSizesNumeric:  ['1', '2', '3', '4', '5', '6'],
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

        // Heurística por nombre: si la opción se llama "color" (en cualquier
        // mayúscula/minúscula, con o sin acentos), activamos el color picker
        // y los hints de UI específicos para colores.
        isColorOption(name) {
            if (!name) return false
            const n = String(name).toLowerCase().trim()
            return n === 'color' || n === 'colores' || n === 'colour'
                || n.includes('color') || n.includes('colour')
        },

        // Detecta si la opción es de "talla" para mostrar chips XS/S/M/L/etc.
        isSizeOption(name) {
            if (!name) return false
            const n = String(name).toLowerCase().trim()
            return n === 'talla' || n === 'tallas' || n === 'size' || n === 'sizes'
                || n.includes('talla') || n.includes('size')
        },

        // Agrega un valor predefinido al final del listado de la opción `oi`.
        // De-duplica por value (case-insensitive) para no insertar el mismo
        // 2 veces si el seller hace click 2 veces en el mismo chip.
        addPredefined(oi, predefined) {
            const opt = this.editOptions[oi]
            if (!opt) return
            const newVal  = String(predefined.value || '').trim()
            if (!newVal) return
            const exists = opt.values.some(v =>
                String(v.value || '').toLowerCase().trim() === newVal.toLowerCase()
            )
            if (exists) {
                this.$message.info(`"${newVal}" ya está en la lista.`)
                return
            }
            opt.values.push({
                value:     newVal,
                color_hex: predefined.color_hex || null,
                position:  opt.values.length,
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

        // ── Imagen por variante ──────────────────────────────────────────
        beforeVariantImage(file) {
            const ALLOWED = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/bmp']
            if (!ALLOWED.includes(file.type)) {
                this.$message.error('Formato no soportado. Usa JPG, PNG o WEBP.')
                return false
            }
            const sizeMB = file.size / 1024 / 1024
            if (sizeMB > 15) {
                this.$message.error(`La imagen es demasiado grande (${sizeMB.toFixed(1)} MB). Máximo 15 MB.`)
                return false
            }
            return true
        },

        onVariantImageSuccess(variant, response) {
            if (!response || !response.success) {
                this.$message.error(response && response.message ? response.message : 'Error al subir')
                return
            }
            // Refrescar la variante en la lista con la nueva info (image + image_url)
            const idx = this.variants.findIndex(v => v.id === variant.id)
            if (idx !== -1) this.$set(this.variants, idx, response.variant)
            this.$message.success('Imagen actualizada')
        },

        onVariantImageError(err) {
            console.error('[variants-tab] upload error:', err)
            this.$message.error('No se pudo subir la imagen. Intenta de nuevo.')
        },

        deleteVariantImage(variant) {
            this.$http.delete(`/items/${this.itemId}/variants/${variant.id}/image`)
                .then(({ data }) => {
                    if (!data.success) return this.$message.error('No se pudo quitar la imagen')
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) this.$set(this.variants, idx, data.variant)
                    this.$message.success('Imagen quitada')
                })
                .catch(() => this.$message.error('Error al quitar imagen'))
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

/* ─────── Thumbnail de imagen por variante ─────── */
.vt-img-uploader { display: inline-block; }
.vt-img-thumb {
    position: relative;
    width: 44px; height: 44px;
    border-radius: 8px;
    background-size: cover;
    background-position: center;
    border: 2px solid #e5e7eb;
    cursor: pointer;
    transition: border-color .15s, transform .12s;
}
.vt-img-thumb:hover {
    border-color: #10b981;
    transform: scale(1.06);
}
.vt-img-del {
    position: absolute;
    top: -6px; right: -6px;
    width: 18px; height: 18px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    border: 2px solid #fff;
    cursor: pointer;
    font-size: 11px; font-weight: 700;
    line-height: 1;
    display: none;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}
.vt-img-thumb:hover .vt-img-del { display: block; }
.vt-img-empty {
    width: 44px; height: 44px;
    border-radius: 8px;
    border: 2px dashed #d1d5db;
    background: #f9fafb;
    color: #6b7280;
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, color .15s, background .15s;
}
.vt-img-empty:hover {
    border-color: #10b981;
    color: #065f46;
    background: #ecfdf5;
}

/* ─────── Chips de valores predefinidos (colores/tallas) ─────── */
.vt-pre-label {
    font-size: 11px; font-weight: 600;
    color: #475569; text-transform: uppercase;
    letter-spacing: .3px;
    margin: 8px 0 4px 8px;
}
.vt-pre-chips {
    display: flex; flex-wrap: wrap; gap: 4px;
    margin: 0 8px 6px;
}
.vt-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px;
    font-size: 11.5px; font-weight: 600;
    color: #1f2937;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    cursor: pointer;
    transition: border-color .12s, background .12s, transform .1s;
}
.vt-chip:hover {
    border-color: #10b981;
    background: #ecfdf5;
    color: #065f46;
    transform: translateY(-1px);
}
.vt-chip__swatch {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,.08);
    flex-shrink: 0;
}
</style>
