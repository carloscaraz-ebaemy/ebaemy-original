<template>
    <el-dialog width="75%" :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" @close="close" @open="create" append-to-body top="5vh" class="bundle-dialog">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="bundle-form">

                <!-- ═══ SECCION 1: INFO BASICA ═══ -->
                <div class="bundle-section">
                    <div class="bundle-section__title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        Informacion del Pack
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group" :class="{'has-danger': errors.description}">
                                <label class="bundle-label">Nombre del pack <span class="text-danger">*</span></label>
                                <el-input v-model="form.description" placeholder="Ej: Pack Decoracion Sala"></el-input>
                                <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="bundle-label">Descripcion corta</label>
                                <el-input v-model="form.name" placeholder="Descripcion para la tienda"></el-input>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="bundle-label">Nombre secundario</label>
                                <el-input v-model="form.second_name" placeholder="Opcional"></el-input>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECCION 2: PRODUCTOS DEL PACK ═══ -->
                <div class="bundle-section">
                    <div class="bundle-section__title d-flex justify-content-between align-items-center">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            Productos incluidos
                            <span class="badge bg-primary ms-2" v-if="form.individual_items.length">{{ form.individual_items.length }}</span>
                        </span>
                        <el-button type="primary" size="small" icon="el-icon-plus" @click.prevent="showDialogAddItem = true">
                            Agregar producto
                        </el-button>
                    </div>

                    <div v-if="form.individual_items.length > 0" class="bundle-items-table">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:5%">#</th>
                                    <th style="width:40%">Producto</th>
                                    <th style="width:15%" class="text-center">P. Unitario</th>
                                    <th style="width:15%" class="text-center">Cantidad</th>
                                    <th style="width:15%" class="text-end">Subtotal</th>
                                    <th style="width:10%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.individual_items" :key="index">
                                    <td class="text-muted">{{ index + 1 }}</td>
                                    <td>
                                        <strong>{{ row.full_description }}</strong>
                                    </td>
                                    <td class="text-center text-muted">{{ row.sale_unit_price | toDecimals }}</td>
                                    <td class="text-center">
                                        <el-input-number
                                            v-model="row.quantity"
                                            @change="calculateTotal"
                                            :min="1"
                                            size="small"
                                            style="width:100px"/>
                                    </td>
                                    <td class="text-end">
                                        <strong>{{ row.sale_unit_price * row.quantity | toDecimals }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <el-tooltip content="Quitar" placement="top">
                                            <button class="btn btn-sm btn-outline-danger" type="button" @click.prevent="clickRemoveItem(index)">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </el-tooltip>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="bundle-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <p>Aun no has agregado productos al pack</p>
                        <el-button size="small" type="primary" plain @click.prevent="showDialogAddItem = true">
                            <i class="el-icon-plus"></i> Agregar primer producto
                        </el-button>
                    </div>
                </div>

                <!-- ═══ SECCION 3: PRECIOS ═══ -->
                <div class="bundle-section">
                    <div class="bundle-section__title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Precio del Pack
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="bundle-label">Moneda</label>
                                <el-select v-model="form.currency_type_id">
                                    <el-option v-for="option in currency_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                </el-select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" :class="{'has-danger': errors.sale_unit_price}">
                                <label class="bundle-label">Precio del Pack <span class="text-danger">*</span></label>
                                <el-input v-model="form.sale_unit_price" @input="calculatePercentageOfProfitBySale">
                                    <template slot="prepend">S/</template>
                                </el-input>
                                <small class="form-control-feedback" v-if="errors.sale_unit_price" v-text="errors.sale_unit_price[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bundle-price-summary">
                                <div class="bundle-price-row">
                                    <span class="text-muted">Suma productos:</span>
                                    <strong>{{ total | toDecimals }}</strong>
                                </div>
                                <div class="bundle-price-row" v-if="savings > 0">
                                    <span class="text-success">Ahorro cliente:</span>
                                    <strong class="text-success">{{ savings | toDecimals }} ({{ savingsPercent }}%)</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="bundle-label">Unidad</label>
                                <el-select v-model="form.unit_type_id">
                                    <el-option v-for="option in unit_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                </el-select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECCION 4: IMAGEN Y DETALLES ═══ -->
                <div class="bundle-section">
                    <div class="bundle-section__title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Imagen y detalles
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <label class="bundle-label">Imagen del Pack</label>
                            <el-upload class="avatar-uploader"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp"
                                    :data="{'type': 'items'}"
                                    :headers="headers"
                                    :action="`/${resource}/upload`"
                                    :show-file-list="false"
                                    :on-success="onSuccess"
                                    :before-upload="beforeUpload">
                                <img v-if="form.image_url" :src="form.image_url" class="avatar" style="max-width:100%;height:auto;border-radius:8px">
                                <div v-else class="bundle-img-placeholder">
                                    <i class="el-icon-camera" style="font-size:28px;color:#ccc"></i>
                                    <small class="text-muted">Subir imagen</small>
                                </div>
                            </el-upload>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Codigo Interno
                                            <el-tooltip content="Codigo interno para control de inventario" placement="top"><i class="fa fa-info-circle text-muted"></i></el-tooltip>
                                        </label>
                                        <el-input v-model="form.internal_id" placeholder="PACK-001"></el-input>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Categoria</label>
                                        <div class="d-flex gap-1 align-items-center" v-if="form_category.add">
                                            <el-input v-model="form_category.name" size="small" placeholder="Nueva categoria"></el-input>
                                            <el-button size="small" type="success" @click="saveCategory()">OK</el-button>
                                            <el-button size="small" @click="form_category.add = false">X</el-button>
                                        </div>
                                        <div v-else>
                                            <el-select v-model="form.category_id" clearable filterable placeholder="Seleccionar" style="width:100%">
                                                <el-option v-for="option in categories" :key="option.id" :label="option.name" :value="option.id"></el-option>
                                            </el-select>
                                            <a href="#" class="small text-primary" @click.prevent="form_category.add = true">+ Nueva categoria</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Marca</label>
                                        <div class="d-flex gap-1 align-items-center" v-if="form_brand.add">
                                            <el-input v-model="form_brand.name" size="small" placeholder="Nueva marca"></el-input>
                                            <el-button size="small" type="success" @click="saveBrand()">OK</el-button>
                                            <el-button size="small" @click="form_brand.add = false">X</el-button>
                                        </div>
                                        <div v-else>
                                            <el-select v-model="form.brand_id" clearable filterable placeholder="Seleccionar" style="width:100%">
                                                <el-option v-for="option in brands" :key="option.id" :label="option.name" :value="option.id"></el-option>
                                            </el-select>
                                            <a href="#" class="small text-primary" @click.prevent="form_brand.add = true">+ Nueva marca</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Modelo</label>
                                        <el-input v-model="form.model" placeholder="Opcional"></el-input>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Tipo afectacion (Venta)</label>
                                        <el-select v-model="form.sale_affectation_igv_type_id" @change="changeAffectationIgvType">
                                            <el-option v-for="option in affectation_igv_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                        </el-select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="bundle-label">Codigo Sunat</label>
                                        <el-input v-model="form.item_code" placeholder="Opcional"></el-input>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <item-set-form-item
                    :showDialog.sync="showDialogAddItem"
                    @add="addRow"></item-set-form-item>
            </div>

            <div class="bundle-form__actions">
                <el-button @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">
                    <i class="fa fa-save" v-if="!loading_submit"></i>
                    {{ form.id ? 'Actualizar Pack' : 'Crear Pack' }}
                </el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>
import ItemSetFormItem from './partials/item.vue'

export default {
    props: ['showDialog', 'recordId', 'external'],
    components: { ItemSetFormItem },
    data() {
        return {
            form_category: { add: false, name: null, id: null },
            form_brand: { add: false, name: null, id: null },
            brands: [],
            categories: [],
            showDialogAddItem: false,
            warehouses: [],
            loading_submit: false,
            titleDialog: null,
            resource: 'ecommerce/item-sets',
            total: 0,
            errors: {},
            headers: headers_token,
            form: {},
            unit_types: [],
            currency_types: [],
            individual_items: [],
            system_isc_types: [],
            affectation_igv_types: [],
            accounts: [],
            show_has_igv: true,
            web_platforms: [],
        }
    },
    computed: {
        savings() {
            let price = parseFloat(this.form.sale_unit_price) || 0;
            return Math.max(0, this.total - price);
        },
        savingsPercent() {
            if (this.total <= 0) return 0;
            return Math.round((this.savings / this.total) * 100);
        }
    },
    created() {
        this.initForm()
        this.total = 0;
        this.$http.get(`/${this.resource}/tables`)
            .then(response => {
                this.unit_types = response.data.unit_types
                this.accounts = response.data.accounts
                this.currency_types = response.data.currency_types
                this.system_isc_types = response.data.system_isc_types
                this.affectation_igv_types = response.data.affectation_igv_types
                this.warehouses = response.data.warehouses
                this.web_platforms = response.data.web_platforms
                this.categories = response.data.categories
                this.brands = response.data.brands
                this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
                this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
            })
    },
    methods: {
        beforeUpload(file) {
            return new Promise((resolve) => {
                if (!file || (!file.type.startsWith('image/') && !file.name.match(/\.(heic|heif)$/i))) return resolve(file);
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = new Image();
                    img.onload = function() {
                        var canvas = document.createElement('canvas');
                        var w = img.width, h = img.height;
                        if (w > 1200) { h = Math.round(h * 1200 / w); w = 1200; }
                        if (h > 1200) { w = Math.round(w * 1200 / h); h = 1200; }
                        canvas.width = w; canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        canvas.toBlob(function(blob) {
                            var name = file.name.replace(/\.[^.]+$/, '.jpg');
                            resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : file);
                        }, 'image/jpeg', 0.82);
                    };
                    img.onerror = function() { resolve(file); };
                    img.src = e.target.result;
                };
                reader.onerror = function() { resolve(file); };
                reader.readAsDataURL(file);
            });
        },
        calculateTotal() {
            this.total = 0;
            this.form.individual_items.forEach(row => {
                this.total += row.sale_unit_price * row.quantity;
            });
        },
        clickRemoveItem(index) {
            this.form.individual_items.splice(index, 1)
            this.changeIndividualItems()
        },
        addRow(row) {
            let exist = this.form.individual_items.find((item) => item.individual_item_id == row.individual_item_id)
            if (exist) {
                exist.quantity += row.quantity;
            } else {
                this.form.individual_items.push(row)
            }
            this.changeIndividualItems()
        },
        changeIndividualItems() {
            this.calculateTotal();
        },
        initForm() {
            this.loading_submit = false
            this.errors = {}
            this.form = {
                id: null,
                item_type_id: '01',
                internal_id: null,
                item_code: null,
                item_code_gs1: null,
                description: null,
                name: null,
                second_name: null,
                unit_type_id: 'NIU',
                currency_type_id: 'PEN',
                sale_unit_price: 0,
                purchase_unit_price: 0,
                has_isc: false,
                system_isc_type_id: null,
                percentage_isc: 0,
                suggested_price: 0,
                sale_affectation_igv_type_id: null,
                purchase_affectation_igv_type_id: null,
                calculate_quantity: false,
                stock: 0,
                stock_min: 1,
                has_igv: true,
                has_perception: false,
                item_unit_types: [],
                percentage_of_profit: 0,
                percentage_perception: 0,
                image: null,
                image_url: null,
                temp_path: null,
                account_id: null,
                is_set: true,
                sale_unit_price_set: 0,
                date_of_due: null,
                web_platform_id: null,
                individual_items: [],
            }
            this.show_has_igv = true
        },
        onSuccess(response) {
            if (response.success) {
                this.form.image = response.data.filename
                this.form.image_url = response.data.temp_image
                this.form.temp_path = response.data.temp_path
            } else {
                this.$message.error(response.message)
            }
        },
        changeAffectationIgvType() {
            let exonerated = [20, 21, 30, 31, 32, 33, 34, 35, 36, 37]
            let is_exonerated = exonerated.includes(parseInt(this.form.sale_affectation_igv_type_id));
            if (is_exonerated) {
                this.show_has_igv = false
                this.form.has_igv = true
            } else {
                this.show_has_igv = true
            }
        },
        resetForm() {
            this.initForm()
            this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
            this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0) ? this.affectation_igv_types[0].id : null
        },
        create() {
            this.titleDialog = (this.recordId) ? 'Editar Pack' : 'Nuevo Pack'
            this.total = 0;
            if (this.recordId) {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        this.changeAffectationIgvType()
                        this.calculateTotal();
                    })
            }
        },
        calculatePercentageOfProfitBySale() {
            // Recalculate on price change
        },
        submit() {
            if (!this.form.description) {
                return this.$message.warning('Ingresa el nombre del pack');
            }
            if (this.form.individual_items.length === 0) {
                return this.$message.warning('Agrega al menos un producto al pack');
            }

            this.form.sale_unit_price_set = this.form.sale_unit_price
            this.loading_submit = true
            this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        if (this.external) {
                            this.$eventHub.$emit('reloadDataItems', response.data.id)
                        } else {
                            this.$eventHub.$emit('reloadData')
                        }
                        this.close()
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else {
                        this.$message.error(error.response.data.message)
                    }
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
        close() {
            this.$emit('update:showDialog', false)
            this.resetForm()
        },
        saveCategory() {
            this.form_category.add = false
            this.$http.post(`/categories`, this.form_category)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.categories.push(response.data.data)
                        this.form_category.name = null
                    }
                })
        },
        saveBrand() {
            this.form_brand.add = false
            this.$http.post(`/brands`, this.form_brand)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.brands.push(response.data.data)
                        this.form_brand.name = null
                    }
                })
        },
    }
}
</script>

<style scoped>
.bundle-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.bundle-section {
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 16px 20px;
    background: #fafbfc;
}
.bundle-section__title {
    font-size: 14px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.bundle-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
}
.bundle-items-table {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}
.bundle-items-table table {
    margin: 0;
}
.bundle-items-table th {
    font-size: 11px;
    text-transform: uppercase;
    color: #999;
    font-weight: 600;
    letter-spacing: 0.3px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    padding: 8px 12px;
}
.bundle-items-table td {
    padding: 10px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f5f5f5;
}
.bundle-empty {
    text-align: center;
    padding: 30px;
    color: #999;
}
.bundle-empty p {
    margin: 10px 0;
    font-size: 13px;
}
.bundle-price-summary {
    background: #f0f7ff;
    border-radius: 8px;
    padding: 10px 14px;
}
.bundle-price-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    line-height: 1.8;
}
.bundle-img-placeholder {
    width: 100%;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    cursor: pointer;
}
.bundle-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f0;
    margin-top: 8px;
}
</style>
