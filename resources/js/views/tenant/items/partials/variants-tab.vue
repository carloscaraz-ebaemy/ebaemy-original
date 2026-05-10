<template>
    <div class="variants-tab">

        <!-- Sin ID todavía: el producto debe guardarse primero -->
        <el-alert v-if="!itemId"
                  title="Guarda el producto primero para poder agregar variantes."
                  type="info" show-icon :closable="false" class="mb-3" />

        <template v-else>
            <!-- ── Cabecera ─────────────────────────────────────────────── -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <strong>Variantes del producto</strong>
                    <span class="text-muted ms-2 small">
                        ({{ variants.length }} combinaciones)
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Toggle vista matriz: solo cuando hay EXACTAMENTE 2 opciones
                         (típicamente Color × Talla). Útil para ver/editar stock
                         de muchas combinaciones de un vistazo, estilo Excel. -->
                    <el-radio-group v-if="canShowMatrix" v-model="viewMode" size="small">
                        <el-radio-button label="list">Lista</el-radio-button>
                        <el-radio-button label="matrix">Matriz</el-radio-button>
                    </el-radio-group>
                    <el-button v-if="variants.length > 0" size="small" plain
                               icon="el-icon-magic-stick"
                               @click="generateSkus"
                               title="Genera SKUs automáticos basados en el código del producto y los valores de cada variante">
                        Generar SKUs
                    </el-button>
                    <el-button size="small" type="primary" plain
                               icon="el-icon-plus"
                               @click="showOptionsEditor = true">
                        Gestionar opciones
                    </el-button>
                </div>
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
            <div v-if="variants.length > 0 && viewMode === 'list'" class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Variante</th>
                            <th style="width:60px">Imagen</th>
                            <th style="width:60px" title="Variante destacada — su imagen aparece primero en el marketplace">⭐ Principal</th>
                            <th style="width:120px">Precio venta</th>
                            <th style="width:90px">SKU</th>
                            <th style="width:80px">Stock</th>
                            <th style="width:70px">Activo</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groupedVariants">
                            <!-- Header del grupo: solo cuando hay 2+ opciones (ej. Color × Talla).
                                 Reduce el ruido visual al no repetir el color en cada fila. -->
                            <tr v-if="hasMultipleOptions" :key="`hdr-${group.key}`" class="vt-group-header">
                                <td colspan="8">
                                    <span v-if="group.color_hex"
                                          class="vt-group-swatch"
                                          :style="`background:${group.color_hex}`"></span>
                                    <strong>{{ group.label }}</strong>
                                    <span class="text-muted small ms-2">
                                        ({{ group.variants.length }} {{ group.variants.length === 1 ? 'variante' : 'variantes' }})
                                    </span>
                                </td>
                            </tr>
                            <tr v-for="v in group.variants" :key="v.id"
                                :class="{ 'table-secondary text-muted': !v.is_active }">
                                <td>
                                    <span class="fw-semibold">{{ variantSubLabel(v) }}</span>
                                    <div v-if="!hasMultipleOptions" class="d-flex flex-wrap gap-1 mt-1">
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
                            <td class="text-center">
                                <!-- Radio exclusivo: solo UNA variante puede ser la
                                     principal. La imagen de esta es la que aparece
                                     en la card del marketplace cuando aún no se
                                     pasa el cursor por ningún dot. -->
                                <el-radio :value="primaryVariantId" :label="v.id"
                                          @change="setPrimary(v)">
                                    <span class="sr-only">Marcar como principal</span>
                                </el-radio>
                            </td>
                            <td>
                                <!-- Precio: si está vacío usa el del producto padre.
                                     Mostramos el placeholder con el valor heredado para
                                     que el seller no tenga que llenar el mismo precio
                                     en cada variante (caso típico: solo cambia el color). -->
                                <el-input-number v-model="v.sale_unit_price"
                                                 :min="0" :precision="4" :controls="false"
                                                 size="mini" style="width:110px"
                                                 :placeholder="parentPriceLabel"
                                                 @change="patchVariant(v)" />
                                <div v-if="!v.sale_unit_price && parentPrice > 0"
                                     class="vt-inherit-hint">
                                    hereda S/ {{ formatMoney(parentPrice) }}
                                </div>
                            </td>
                            <td>
                                <el-input v-model="v.sku" size="mini" style="width:80px"
                                          placeholder="Auto"
                                          @change="patchVariant(v)" />
                            </td>
                            <td class="text-center">
                                <el-tooltip :content="stockTooltip(v)" placement="top">
                                    <span :class="{ 'vt-stock-zero': v.stock <= 0 }">
                                        {{ v.stock }}
                                    </span>
                                </el-tooltip>
                                <el-button size="mini" type="text" icon="el-icon-edit"
                                           @click="openStockDialog(v)" />
                                <!-- Aviso visible solo cuando la variante NO se mostrará
                                     en marketplace por stock=0 (cards filtran stock>0).
                                     Solo aparece si el producto tiene marketplace activo. -->
                                <div v-if="isMarketplacePublishable && v.stock <= 0 && v.is_active"
                                     class="vt-stock-warn"
                                     title="Esta variante no se mostrará en el marketplace mientras tenga stock 0">
                                    ⚠ oculta en marketplace
                                </div>
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
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- ── Vista matriz (solo con 2 opciones) ───────────────────── -->
            <div v-if="variants.length > 0 && viewMode === 'matrix'" class="vt-matrix-wrap">
                <div class="vt-matrix-hint">
                    <i class="el-icon-info"></i>
                    Edita el stock de cada combinación directamente. Click en la celda
                    para abrir más opciones (precio, SKU, imagen).
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 vt-matrix">
                        <thead class="table-light">
                            <tr>
                                <th class="vt-matrix-corner">
                                    {{ matrixOptions.rowName }} ↓ / {{ matrixOptions.colName }} →
                                </th>
                                <th v-for="col in matrixOptions.cols" :key="`c-${col.id}`"
                                    class="text-center">
                                    {{ col.value }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in matrixOptions.rows" :key="`r-${row.id}`">
                                <th class="vt-matrix-rowhead">
                                    <span v-if="row.color_hex"
                                          class="vt-group-swatch"
                                          :style="`background:${row.color_hex}`"></span>
                                    {{ row.value }}
                                </th>
                                <td v-for="col in matrixOptions.cols" :key="`cell-${row.id}-${col.id}`"
                                    class="vt-matrix-cell"
                                    :class="{ 'vt-matrix-cell--missing': !findVariantByValues(row.id, col.id) }">
                                    <template v-if="findVariantByValues(row.id, col.id)">
                                        <el-input-number :value="findVariantByValues(row.id, col.id).stock"
                                                         :min="0" :precision="0" :controls="false"
                                                         size="mini" style="width:60px"
                                                         @change="onMatrixStockChange(findVariantByValues(row.id, col.id), $event)" />
                                        <button type="button"
                                                class="vt-matrix-edit"
                                                @click="openStockDialog(findVariantByValues(row.id, col.id))"
                                                title="Más opciones">⋯</button>
                                    </template>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <el-empty v-if="variants.length === 0" description="Sin variantes. Define las opciones y genera las combinaciones." />

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
        itemId:                   { type: Number,  default: null },
        parentPrice:              { type: Number,  default: 0 },
        // Código del producto padre (internal_id o item_code) — base para el
        // generador de SKUs automáticos. Si no llega, el botón avisa al seller.
        itemCode:                 { type: String,  default: '' },
        // Si el producto está publicado en marketplace, mostramos un aviso por
        // variante cuando su stock=0 (porque el card del marketplace la oculta).
        isMarketplacePublishable: { type: Boolean, default: false },
    },

    computed: {
        // Texto del placeholder del input de precio. Si el padre no tiene
        // precio (común en producto recién creado) dejamos el placeholder
        // genérico, así no pone "0" engañoso.
        parentPriceLabel() {
            return this.parentPrice > 0 ? this.formatMoney(this.parentPrice) : 'Precio'
        },
        // ID de la variante marcada como principal — usado por el radio
        // group para que solo una esté seleccionada a la vez.
        primaryVariantId() {
            const found = (this.variants || []).find(v => v.is_primary)
            return found ? found.id : null
        },

        // Hay 2+ opciones definidas (ej. Color × Talla). Solo cuando es true
        // mostramos los headers de grupo y filtramos los chips secundarios.
        hasMultipleOptions() {
            return (this.editOptions || []).length > 1
        },

        // ID de la opción "principal" — la que tiene menor position.
        // Es la que usamos para agrupar las filas (Blanco / Rosa / etc).
        primaryOptionId() {
            if (!this.editOptions || !this.editOptions.length) return null
            const sorted = [...this.editOptions].sort(
                (a, b) => (a.position || 0) - (b.position || 0)
            )
            return sorted[0].id
        },

        // Vista matriz solo disponible cuando hay EXACTAMENTE 2 opciones.
        // Con 1 opción la lista basta; con 3+ no cabe en una tabla 2D.
        canShowMatrix() {
            return (this.editOptions || []).length === 2 && this.variants.length > 0
        },

        // Configuración de la matriz: ordenamos las opciones por position
        // — la primera (ej. Color) va en filas, la segunda (ej. Talla)
        // en columnas. Esto coincide con la convención de los thumbs en
        // Falabella/Saga donde el color es el primer selector visual.
        matrixOptions() {
            if (!this.canShowMatrix) return null
            const sorted = [...this.editOptions].sort(
                (a, b) => (a.position || 0) - (b.position || 0)
            )
            const [rowOpt, colOpt] = sorted
            return {
                rowName: rowOpt.name,
                colName: colOpt.name,
                rows: (rowOpt.values || []).map(v => ({
                    id: v.id, value: v.value, color_hex: v.color_hex,
                })),
                cols: (colOpt.values || []).map(v => ({
                    id: v.id, value: v.value,
                })),
            }
        },

        // Agrupa las variantes por el valor de la opción principal.
        // Si hay solo 1 opción, devuelve un único grupo con todas (el render
        // omite el header y muestra los chips completos como antes).
        groupedVariants() {
            if (!this.hasMultipleOptions) {
                return [{ key: '_all', label: null, color_hex: null, variants: this.variants }]
            }
            const primaryId = this.primaryOptionId
            const groups = new Map()
            for (const v of this.variants) {
                const primaryVal = (v.option_values || []).find(ov =>
                    Number(ov.item_option_id) === Number(primaryId)
                )
                const key = primaryVal ? primaryVal.value : '__sin'
                if (!groups.has(key)) {
                    groups.set(key, {
                        key,
                        label: primaryVal ? primaryVal.value : 'Sin opción',
                        color_hex: (primaryVal && primaryVal.color_hex) || null,
                        variants: [],
                    })
                }
                groups.get(key).variants.push(v)
            }
            return Array.from(groups.values())
        },
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

            // Modo de visualización de variantes: 'list' (default, una fila
            // por combinación con todos los campos) o 'matrix' (Excel con
            // primera opción en filas y segunda en columnas, celdas=stock).
            // Solo aplica cuando hay 2 opciones (ej. Color × Talla).
            viewMode: 'list',
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
                    // Normalizar precio: backend envía 0 cuando la variante no
                    // tiene override; convertimos a null para que el input
                    // muestre el placeholder con el precio heredado del padre
                    // en vez de "0.0000" (que confunde al seller).
                    this.variants = (data.variants || []).map(v => ({
                        ...v,
                        sale_unit_price: Number(v.sale_unit_price) > 0
                            ? Number(v.sale_unit_price)
                            : null,
                    }))
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
            // Mandamos null si el seller no puso precio — el backend lo
            // persiste como NULL y la variante hereda el precio del padre
            // automáticamente. Mandar 0 quedaría como "regalado" (S/ 0.00).
            const price = Number(variant.sale_unit_price) > 0
                ? Number(variant.sale_unit_price)
                : null
            this.$http.patch(`/items/${this.itemId}/variants/${variant.id}`, {
                sale_unit_price: price,
                sku:             variant.sku || null,
                is_active:       variant.is_active,
            })
                .then(({ data }) => {
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) {
                        // Re-aplicar la misma normalización del load para no
                        // pintar "0.0000" tras un patch sin precio.
                        this.$set(this.variants, idx, {
                            ...data.variant,
                            sale_unit_price: Number(data.variant.sale_unit_price) > 0
                                ? Number(data.variant.sale_unit_price)
                                : null,
                        })
                    }
                })
                .catch(() => this.$message.error('Error al actualizar variante'))
        },

        // ── Generador de SKUs ────────────────────────────────────────────
        // Toma el código del producto padre + slug 3-char por cada valor de
        // opción. Solo rellena variantes que NO tengan SKU (no sobrescribe).
        // Ej: producto "00012", variante (Color: Rojo, Talla: M) → "00012-ROJ-M"
        generateSkus() {
            if (!this.itemCode) {
                this.$message.warning('El producto no tiene código interno. Configúralo en la pestaña General antes de generar SKUs.')
                return
            }
            let count = 0
            this.variants.forEach(v => {
                if (v.sku) return
                const valSlug = (v.option_values || [])
                    .map(ov => this.skuSlug(ov.value))
                    .filter(s => s)
                    .join('-')
                if (!valSlug) return
                v.sku = `${this.itemCode}-${valSlug}`
                this.patchVariant(v)
                count++
            })
            if (count > 0) {
                this.$message.success(`SKUs generados para ${count} variantes.`)
            } else {
                this.$message.info('Todas las variantes ya tienen SKU.')
            }
        },

        // Slug de 3 chars sin acentos en mayúsculas. "Rojo"→"ROJ", "M"→"M",
        // "38"→"38". Si el valor está vacío, retorna ''.
        skuSlug(value) {
            // ̀-ͯ = combining diacritical marks (acentos en NFD).
            // Quita acentos y caracteres no alfanuméricos, retorna 3 chars max.
            return String(value || '')
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '')
                .substring(0, 3)
        },

        formatMoney(n) {
            const num = Number(n) || 0
            return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
        },

        // Busca la variante que combina rowValueId × colValueId. Devuelve
        // null si esa combinación no fue generada (ej. seller la borró).
        findVariantByValues(rowValueId, colValueId) {
            return this.variants.find(v => {
                const ids = (v.option_values || []).map(ov => Number(ov.id))
                return ids.includes(Number(rowValueId)) && ids.includes(Number(colValueId))
            }) || null
        },

        // Stock rápido desde la matriz: se aplica al PRIMER almacén de la
        // variante (típicamente "Oficina Principal"). Si la variante tiene
        // múltiples almacenes y el seller necesita ajuste fino, click en
        // "⋯" abre el dialog completo. Mantiene la matriz simple sin perder
        // capacidad.
        onMatrixStockChange(variant, newStock) {
            const stocks = variant.warehouse_stocks || []
            if (stocks.length === 0) {
                this.$message.warning('Esta variante no tiene almacenes configurados. Abre el dialog para asignar uno.')
                this.openStockDialog(variant)
                return
            }
            const wh = stocks[0]
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/stock`, {
                warehouse_id: wh.warehouse_id,
                stock:        Number(newStock) || 0,
            })
                .then(({ data }) => {
                    const idx = this.variants.findIndex(v => v.id === variant.id)
                    if (idx !== -1) this.$set(this.variants, idx, data.variant)
                })
                .catch(() => this.$message.error('No se pudo actualizar el stock.'))
        },

        // Label de la fila cuando se renderiza dentro de un grupo: muestra
        // SOLO los valores secundarios (ya que el primario está en el header).
        // Cuando hay 1 sola opción usa el display_name completo.
        variantSubLabel(v) {
            if (!this.hasMultipleOptions) return v.display_name
            const primaryId = this.primaryOptionId
            const secondary = (v.option_values || [])
                .filter(ov => Number(ov.item_option_id) !== Number(primaryId))
                .map(ov => ov.value)
                .join(' · ')
            return secondary || v.display_name
        },

        // ── Variante principal ───────────────────────────────────────────
        // El backend hace exclusivo en transacción: marca esta como is_primary
        // y todas las demás del item como false. Refrescamos la lista local
        // con el flag actualizado para que el radio resalte la nueva.
        setPrimary(variant) {
            this.$http.post(`/items/${this.itemId}/variants/${variant.id}/primary`)
                .then(() => {
                    this.variants.forEach(v => {
                        v.is_primary = (v.id === variant.id)
                    })
                    this.$message.success(`"${variant.display_name}" es ahora la variante principal del marketplace.`)
                })
                .catch(() => this.$message.error('No se pudo marcar como principal.'))
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

/* ─────── Hint de precio heredado ─────── */
.vt-inherit-hint {
    font-size: 10px;
    color: #9ca3af;
    margin-top: 2px;
    line-height: 1.2;
    font-style: italic;
}

/* ─────── Vista matriz (Color × Talla en grilla) ─────── */
.vt-matrix-wrap { margin-top: 4px; }
.vt-matrix-hint {
    font-size: 11.5px;
    color: #475569;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 6px 10px;
    margin-bottom: 8px;
}
.vt-matrix-hint i { margin-right: 4px; color: #2563eb; }
.vt-matrix th, .vt-matrix td {
    vertical-align: middle;
    text-align: center;
}
.vt-matrix-corner {
    background: #f1f5f9 !important;
    font-size: 10.5px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .3px;
    text-align: left !important;
}
.vt-matrix-rowhead {
    background: #f3f4f6 !important;
    text-align: left !important;
    padding-left: 10px !important;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
}
.vt-matrix-cell {
    padding: 4px !important;
    position: relative;
    min-width: 76px;
}
.vt-matrix-cell--missing {
    background: #f9fafb;
}
.vt-matrix-edit {
    position: absolute;
    top: 2px; right: 3px;
    background: transparent;
    border: 0;
    color: #9ca3af;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
}
.vt-matrix-edit:hover { color: #1f2937; }

/* ─────── Header de grupo (Color principal en variantes Color × Talla) ─────── */
.vt-group-header td {
    background: #f3f4f6 !important;
    border-top: 2px solid #d1d5db !important;
    padding: 6px 10px !important;
}
.vt-group-swatch {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 999px;
    border: 1.5px solid #9ca3af;
    margin-right: 6px;
    vertical-align: middle;
}

/* ─────── Stock=0 en rojo + warning marketplace ─────── */
.vt-stock-zero {
    color: #dc2626;
    font-weight: 700;
}
.vt-stock-warn {
    font-size: 10px;
    color: #b45309;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 1px 5px;
    margin-top: 3px;
    display: inline-block;
    line-height: 1.3;
    cursor: help;
}
</style>
