<template>
    <el-dialog title="Nuevo Modificador" :visible.sync="showDialog" :close-on-click-modal="false" @close="close">
        <div>
            <!-- Información básica -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Nombre de Categoría <span class="text-danger">*</span></label>
                        <el-input v-model="form.name" placeholder="Ej: Cremas"></el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Tipo de Selección <span class="text-danger">*</span></label>
                        <el-select v-model="form.selection_type" class="w-100">
                            <el-option label="Única selección" value="single"></el-option>
                            <el-option label="Múltiple selección" value="multiple"></el-option>
                        </el-select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label d-flex control-label-modifier">Buscar Producto <span class="text-danger">*</span>
                            <el-checkbox class="ms-auto" v-model="addManual" size="small">Manual</el-checkbox>
                        </label>                    
                        <div class="">
                            <el-input v-if="addManual" v-model="addName" placeholder="Nombre..." size="small"></el-input>
                            <el-autocomplete
                                v-else
                                v-model="addItemQuery"
                                :fetch-suggestions="querySearchItems"
                                placeholder="Buscar producto..."
                                @select="onSelectItem"
                                :trigger-on-focus="false"
                                size="small"
                                class="w-100"
                                prefix-icon="el-icon-search"
                            >                                
                            </el-autocomplete>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label d-flex control-label-modifier">Precio
                            <el-checkbox class="ms-auto" v-model="addFree" size="small">Gratis</el-checkbox>
                        </label>
                         <el-input-number
                            v-model.number="addPrice"
                            :min="0"
                            :disabled="addFree"
                            placeholder="0.00"
                            size="small"
                            :precision="2"
                            class="w-100"
                        ></el-input-number>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="control-label opacity-0">Botón</label>
                        <el-button class="w-100" type="primary" @click="addOption" size="small" icon="el-icon-plus">
                            Añadir
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- Lista de modificadores -->
            <div class="form-group mt-3" v-if="form.items && form.items.length > 0">
                <label class="control-label">Modificadores Agregados</label>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th width="120">Precio</th>
                            <th width="100" class="text-center">Por defecto</th>
                            <th width="80" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(opt, idx) in form.items" :key="idx">
                            <td class="align-middle">
                                <strong>{{ opt.name || ('Item #' + (opt.item_id || '')) }}</strong>
                                <br>
                                <small class="text-muted">{{ opt.type === 'manual' ? 'Manual' : 'Item' }}</small>
                            </td>
                            <td class="align-middle">{{ formatPrice(opt.price) }}</td>
                            <td class="text-center align-middle">
                                <input
                                    type="checkbox"
                                    :checked="opt.default === true"
                                    @change="setDefaultOption(idx)"
                                    class="form-check-input"
                                    style="cursor: pointer;"
                                >
                            </td>
                            <td class="text-center align-middle">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger"
                                    @click="removeOption(idx)"
                                    title="Eliminar"
                                >
                                    <i class="el-icon-delete"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-muted text-center py-3 my-3" style="background-color: #f5f7fa; border-radius: 4px;">
                <small>No hay opciones agregadas</small>
            </div>

            <!-- Estado (oculto) -->
            <div class="form-group d-none">
                <el-checkbox v-model="form.active">Activo</el-checkbox>
            </div>
        </div>

        <span slot="footer" class="dialog-footer">
            <el-button @click="close" size="medium">Cancelar</el-button>
            <el-button type="primary" :loading="saving" @click="save" size="medium">
                {{ saving ? 'Guardando...' : (form.id ? 'Actualizar' : 'Crear Modificador') }}
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    name: 'ModifierGroupForm',
    props: {
        showDialog: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            form: {
                id: null,
                name: '',
                selection_type: 'single',
                items: [],
                active: true
            },
            addManual: false,
            addFree: false,
            addName: '',
            addItemId: '',
            addItemQuery: '',
            selectedItemName: '',
            addPrice: 0,
            saving: false
        }
    },
    methods: {
        setFormData(data) {
            this.form.id = data.id || null
            this.form.name = data.name || ''
            this.form.selection_type = data.selection_type || 'single'
            this.form.items = Array.isArray(data.items) ? data.items.slice() : []
            this.form.active = data.active === undefined ? true : !!data.active
        },
        close() {
            this.$emit('update:showDialog', false)
            this.setFormData({})
        },
        addOption() {
            if (!this.form.items) this.form.items = []

            if (this.addManual) {
                if (!this.addName) {
                    this.$message.warning('Ingresa nombre para opción manual')
                    return
                }
                const opt = {
                    type: 'manual',
                    name: this.addName,
                    price: this.addFree ? 0 : (Number(this.addPrice) || 0),
                    price_type: 'fixed',
                    default: false,
                    order: this.form.items.length + 1
                }
                this.form.items.push(opt)
                this.addName = ''
                this.addPrice = 0
                this.addFree = false
            } else {
                if (!this.addItemId) {
                    this.$message.warning('Selecciona un producto')
                    return
                }
                const opt = {
                    type: 'item',
                    item_id: this.addItemId,
                    name: this.selectedItemName || null,
                    price: this.addFree ? 0 : (Number(this.addPrice) || 0),
                    price_type: 'fixed',
                    default: false,
                    order: this.form.items.length + 1
                }
                this.form.items.push(opt)
                this.addItemId = ''
                this.addItemQuery = ''
                this.selectedItemName = ''
                this.addPrice = 0
                this.addFree = false
            }
        },
        async querySearchItems(queryString, cb) {
            const q = (queryString || '').trim()
            if (!q) { cb([]); return }
            try {
                const res = await this.$http.get(`/documents/search-items/`, { params: { input: q } })
                if (res.data && Array.isArray(res.data.items)) {
                    const suggestions = res.data.items.map(it => ({ 
                        value: `${it.description || it.name}`, 
                        id: it.id,
                        sale_unit_price: it.sale_unit_price || it.amount_sale_unit_price || 0
                    }))
                    cb(suggestions)
                    return
                }
            } catch (e) {
                // fallback silencioso
            }
            try {
                const res2 = await this.$http.get(`/restaurant/orders/tables/item/${encodeURIComponent(q)}`)
                if (res2.data && res2.data.item) {
                    const it = res2.data.item
                    cb([{ 
                        value: `${it.description || it.name || it.internal_id}`, 
                        id: it.id,
                        sale_unit_price: it.sale_unit_price || it.amount_sale_unit_price || 0
                    }])
                    return
                }
            } catch (e2) {
                // sin resultados
            }
            cb([])
        },
        onSelectItem(suggestion) {
            this.addItemId = suggestion.id
            this.selectedItemName = suggestion.value
            // Set price from sale_unit_price
            this.addPrice = Number(suggestion.sale_unit_price) || 0
        },
        removeOption(idx) {
            this.form.items.splice(idx, 1)
            this.form.items.forEach((o, i) => { o.order = i + 1 })
        },
        setDefaultOption(idx) {
            // Unset all defaults first
            this.form.items.forEach(opt => { opt.default = false })
            // Set selected as default
            this.form.items[idx].default = true
        },
        formatPrice(price) {
            const p = Number(price) || 0
            return p === 0 ? 'Gratuito' : `S/ ${p.toFixed(2)}`
        },
        priceLabel(opt) {
            const p = Number(opt.price) || 0
            const isManual = opt.type === 'manual'
            const base = p === 0 ? 'Gratuito' : `S/ ${p}`
            return isManual ? `${base} · Manual` : `${base} · Item`
        },
        async save() {
            if (!this.form.name) {
                this.$message.warning('Ingresa el nombre de categoría')
                return
            }

            this.saving = true
            try {
                const items = Array.isArray(this.form.items) ? this.form.items : []
                const payload = {
                    name: this.form.name,
                    selection_type: this.form.selection_type,
                    items: items,
                    active: this.form.active
                }

                const url = this.form.id ? `/restaurant/modifier-groups/${this.form.id}` : `/restaurant/modifier-groups`
                const method = this.form.id ? 'put' : 'post'

                const res = await this.$http[method](url, payload)
                if (res.data && (res.data.success || res.status === 201 || res.status === 200)) {
                    this.$message.success(res.data.message || 'Guardado con éxito')
                    this.$emit('save')
                    this.close()
                } else {
                    this.$message.error(res.data.message || 'Error al guardar')
                }
            } catch (e) {
                console.error(e)
                this.$message.error('Error en la petición')
            } finally {
                this.saving = false
            }
        }
    }
}
</script>

<style scoped>
.table {
    margin-bottom: 0.5rem;
}

.table thead th {
    background-color: #F5F7FA;
    color: #606266;
    font-weight: 600;
    font-size: 13px;
    padding: 10px 12px;
}

.table tbody td {
    padding: 10px 12px;
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: #F5F7FA;
}

.form-check-input {
    width: 18px;
    height: 18px;
    margin: 0;
}

.dialog-footer {
    text-align: right;
}
</style>
