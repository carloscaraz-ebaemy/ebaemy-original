<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @open="create" :close-on-click-modal="false" :close-on-press-escape="false" @close="handleCloseDialog">
        <form autocomplete="off" @submit.prevent="clickAddItem">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.fixed_asset_item_id}">
                            <label class="control-label d-flex align-items-center">
                                Producto/Servicio
                                <span class="btn-add-new-product" href="#" @click.prevent="showDialogNewItem = true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                </span>
                            </label>
                            <el-select 
                                v-model="form.fixed_asset_item_id" 
                                @change="changeItem" 
                                filterable
                                remote
                                :remote-method="searchRemoteItems"
                                :loading="loading_search"
                                placeholder="Buscar producto">
                                <el-option v-for="option in items" :key="option.id" :value="option.id" :label="option.full_description"></el-option>
                                <template slot="empty">
                                    <p v-if="loading_search" class="el-select-dropdown__empty">
                                        Cargando...
                                    </p>
                                
                                    <p v-else class="el-select-dropdown__empty">
                                        No se encontraron resultados
                                    </p>
                                
                                    <div
                                        v-if="!loading_search"
                                        class="el-select-dropdown__item new-option"
                                        @click.stop="openNewItemDialog"
                                    >
                                        <span>{{ itemSearchTerm ? `Crear producto "${itemSearchTerm}"` : 'Crear producto' }}</span>
                                    </div>
                                </template>
                            </el-select>
                            <small class="form-control-feedback" v-if="errors.fixed_asset_item_id" v-text="errors.fixed_asset_item_id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.affectation_igv_type_id}">
                            <label class="control-label">Afectación Igv</label>
                            <el-select v-model="form.affectation_igv_type_id" :disabled="!change_affectation_igv_type_id" filterable>
                                <el-option v-for="option in affectation_igv_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                            </el-select>
                            <el-checkbox v-model="change_affectation_igv_type_id">Editar</el-checkbox>
                            <small class="form-control-feedback" v-if="errors.affectation_igv_type_id" v-text="errors.affectation_igv_type_id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.quantity}">
                            <label class="control-label">Cantidad</label>
                            <el-input-number v-model="form.quantity" :min="0.01"></el-input-number>
                            <small class="form-control-feedback" v-if="errors.quantity" v-text="errors.quantity[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.unit_price}">
                            <label class="control-label">Precio Unitario</label>
                            <el-input v-model="form.unit_price">
                                <template slot="prepend" v-if="form.item.currency_type_symbol">{{ form.item.currency_type_symbol }}</template>
                            </el-input>
                            <small class="form-control-feedback" v-if="errors.unit_price" v-text="errors.unit_price[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-12 mt-3">
                        <section :class="['card mb-2 card-transparent', {'card-collapsed': !showAdditionalInfo}]" id="card-section">
                                <header class="card-header hoverable bg-light border-top rounded-0 py-1" style="cursor: pointer;" id="card-click" @click="toggleAdditionalInfo">
                                    <div class="card-actions" style="margin-top: -12px;">
                                        <a href="#" class="card-action card-action-toggle text-info"
                                           :class="{'is-open': showAdditionalInfo}"
                                           @click.prevent.stop="toggleAdditionalInfo"></a>
                                    </div>

                                    <p class="ps-1">Información adicional atributos UBL 2.1</p>
                                </header>
                                <div class="card-body px-0 pt-2" v-show="showAdditionalInfo">
                                    <div class="col-md-12 px-0" v-if="discount_types.length > 0">
                                        <label class="control-label">
                                            Descuentos
                                            <a href="#" @click.prevent="clickAddDiscount">[+ Agregar]</a>
                                        </label>
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Porcentaje</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="(row, index) in form.discounts" :key="index">
                                                <td>
                                                    <el-select v-model="row.discount_type_id" @change="changeDiscountType(index)">
                                                        <el-option v-for="option in discount_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                                    </el-select>
                                                </td>
                                                <td>
                                                    <el-input v-model="row.description"></el-input>
                                                </td>
                                                <td>
                                                    <el-input v-model="row.percentage"></el-input>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger" @click.prevent="clickRemoveDiscount(index)">x</button>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-12 px-0" v-if="charge_types.length > 0">
                                        <label class="control-label">
                                            Cargos
                                            <a href="#" @click.prevent="clickAddCharge">[+ Agregar]</a>
                                        </label>
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Porcentaje</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="(row, index) in form.charges"  :key="index">
                                                <td>
                                                    <el-select v-model="row.charge_type_id" @change="changeChargeType(index)">
                                                        <el-option v-for="option in charge_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                                    </el-select>
                                                </td>
                                                <td>
                                                    <el-input v-model="row.description"></el-input>
                                                </td>
                                                <td>
                                                    <el-input v-model="row.percentage"></el-input>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger" @click.prevent="clickRemoveCharge(index)">x</button>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-12 px-0" v-if="attribute_types.length > 0">
                                        <label class="control-label">
                                            Atributos
                                            <a href="#" @click.prevent="clickAddAttribute">[+ Agregar]</a>
                                        </label>
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr v-for="(row, index) in form.attributes"  :key="index">
                                                <td>
                                                    <el-select v-model="row.attribute_type_id" filterable @change="changeAttributeType(index)">
                                                        <el-option v-for="option in attribute_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                                    </el-select>
                                                </td>
                                                <td>
                                                    <el-input v-model="row.value"></el-input>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger" @click.prevent="clickRemoveAttribute(index)">x</button>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button class="second-buton me-2" @click.prevent="handleCloseDialog()">Cerrar</el-button>
                <el-button type="primary" native-type="submit">Agregar</el-button>
            </div>
        </form>

        <fa-item-form :showDialog.sync="showDialogNewItem"
                   :external="true"
                   :input_item="itemSearchTerm"></fa-item-form>


    </el-dialog>
</template>
<style>
.el-select-dropdown {
    max-width: 80% !important;
    margin-right: 5% !important;
}
</style>
<script>

    import FaItemForm from '../../fixed_asset_items/form.vue'
    import {calculateRowItem} from '@helpers/functions'

    export default {
        props: ['showDialog', 'currencyTypeIdActive', 'exchangeRateSale', 'percentageIgv'],
        components: {FaItemForm},
        data() {
            return {
                titleDialog: 'Agregar Producto o Servicio',
                showDialogLots:false,
                resource: 'fixed-asset/purchases',
                showDialogNewItem: false,
                errors: {},
                form: {},
                items: [],
                all_items: [],
                affectation_igv_types: [],
                system_isc_types: [],
                discount_types: [],
                change_affectation_igv_type_id: false,
                charge_types: [],
                attribute_types: [],
                showAdditionalInfo: false,
                loading_search: false,
                itemSearchTerm: ''
            }
        },
        watch: {
            showDialog(newVal) {
                if (!newVal) {
                    this.itemSearchTerm = ''
                }
            },
            showDialogNewItem(newVal) {
                if (!newVal) {
                    this.itemSearchTerm = ''
                }
            }
        },
        created() {
            this.initForm()
            this.$http.get(`/${this.resource}/item/tables`).then(response => {

                this.items = response.data.fixed_asset_items
                this.all_items = response.data.fixed_asset_items
                this.affectation_igv_types = response.data.affectation_igv_types
                this.system_isc_types = response.data.system_isc_types
                this.discount_types = response.data.discount_types
                this.charge_types = response.data.charge_types
                this.attribute_types = response.data.attribute_types
            })

            this.$eventHub.$on('reloadDataFixedAssetItems', (fixed_asset_item_id) => {
                this.reloadDataFixedAssetItems(fixed_asset_item_id)
                this.itemSearchTerm = ''
            })
        },
        methods: {
            handleCloseDialog() {
              if (this.hasUnsavedChanges()) {
                this.$confirm('¿Estás seguro de cerrar el formulario? Se perderán los datos no guardados.', 'Confirmar', {
                  confirmButtonText: 'Cerrar sin guardar',
                  cancelButtonText: 'Cancelar',
                  type: 'warning'
                }).then(() => {
                  this.forceCloseDialog()
                }).catch(() => {
                
                });
              } else {
                this.forceCloseDialog()
              }
            },
            forceCloseDialog() {
              this.initForm()
              this.$emit('update:showDialog', false)
            },
            hasUnsavedChanges() {
                return (
                  this.form.fixed_asset_item_id !== null ||
                  (this.form.quantity && this.form.quantity !== 1) ||
                  (this.form.unit_price && parseFloat(this.form.unit_price) > 0) ||
                  (Array.isArray(this.form.discounts) && this.form.discounts.length > 0) ||
                  (Array.isArray(this.form.charges) && this.form.charges.length > 0) ||
                  (Array.isArray(this.form.attributes) && this.form.attributes.length > 0)
                );
            },
            initForm() {
                this.errors = {}
                this.form = {
                    fixed_asset_item_id: null,
                    item: {},
                    affectation_igv_type_id: null,
                    affectation_igv_type: {},
                    has_isc: false,
                    system_isc_type_id: null,
                    percentage_isc: 0,
                    suggested_price: 0,
                    quantity: 1,
                    unit_price: 0,
                    charges: [],
                    discounts: [],
                    attributes: [],
                }

                this.showAdditionalInfo = false
            },
            create() {
            //     this.initializeFields()
            },
            clickAddDiscount() {
                this.ensureAdditionalInfoOpen()
                this.form.discounts.push({
                    discount_type_id: null,
                    discount_type: null,
                    description: null,
                    percentage: 0,
                    factor: 0,
                    amount: 0,
                    base: 0
                })
            },
            clickRemoveDiscount(index) {
                this.form.discounts.splice(index, 1)
            },
            changeDiscountType(index) {
                let discount_type_id = this.form.discounts[index].discount_type_id
                this.form.discounts[index].discount_type = _.find(this.discount_types, {id: discount_type_id})
            },
            clickAddCharge() {
                this.ensureAdditionalInfoOpen()
                this.form.charges.push({
                    charge_type_id: null,
                    charge_type: null,
                    description: null,
                    percentage: 0,
                    factor: 0,
                    amount: 0,
                    base: 0
                })
            },
            clickRemoveCharge(index) {
                this.form.charges.splice(index, 1)
            },
            changeChargeType(index) {
                let charge_type_id = this.form.charges[index].charge_type_id
                this.form.charges[index].charge_type = _.find(this.charge_types, {id: charge_type_id})
            },
            clickAddAttribute() {
                this.ensureAdditionalInfoOpen()
                this.form.attributes.push({
                    attribute_type_id: null,
                    description: null,
                    value: null,
                    start_date: null,
                    end_date: null,
                    duration: null,
                })
            },
            clickRemoveAttribute(index) {
                this.form.attributes.splice(index, 1)
            },
            changeAttributeType(index) {
                let attribute_type_id = this.form.attributes[index].attribute_type_id
                let attribute_type = _.find(this.attribute_types, {id: attribute_type_id})
                this.form.attributes[index].description = attribute_type.description
            },
            toggleAdditionalInfo() {
                this.showAdditionalInfo = !this.showAdditionalInfo
            },
            ensureAdditionalInfoOpen() {
                if (!this.showAdditionalInfo) {
                    this.showAdditionalInfo = true
                }
            },
            changeItem() {
                this.form.item = _.find(this.items, {'id': this.form.fixed_asset_item_id})
                this.form.unit_price = this.form.item.purchase_unit_price
                this.form.affectation_igv_type_id = this.form.item.purchase_affectation_igv_type_id
            },
            async clickAddItem() {


                this.form.item.unit_price = this.form.unit_price
                this.form.item.presentation = this.item_unit_type;
                this.form.affectation_igv_type = _.find(this.affectation_igv_types, {'id': this.form.affectation_igv_type_id})
                this.row = await calculateRowItem(this.form, this.currencyTypeIdActive, this.exchangeRateSale, this.percentageIgv)
                this.row.fixed_asset_item_id = await this.row.item_id
                this.initForm()
                this.$emit('add', this.row)
            },
            reloadDataFixedAssetItems(fixed_asset_item_id) {
                this.$http.get(`/${this.resource}/table/fixed_asset_items`).then((response) => {
                    this.items = response.data
                    this.all_items = response.data
                    this.form.fixed_asset_item_id = fixed_asset_item_id
                    this.changeItem()
                })
            },
            async searchRemoteItems(input) {
                this.itemSearchTerm = input;

                if (input.length > 2) {
                    this.loading_search = true;
                    const params = {
                        input: input
                    };
                    await this.$http
                        .get(`/${this.resource}/search-items/`, { params })
                        .then(response => {
                            this.items = response.data.fixed_asset_items || response.data.items;
                            this.loading_search = false;
                        })
                        .catch(() => {
                            this.loading_search = false;
                        });
                } else {
                    this.filterItems();
                }
            },
            filterItems() {
                this.items = this.all_items;
            },
            openNewItemDialog() {
                this.showDialogNewItem = true;
            },
        }
    }

</script>
