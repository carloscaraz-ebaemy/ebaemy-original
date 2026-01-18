<template>
    <el-dialog :close-on-click-modal="false"
               :title="titleDialog"
               :visible="showDialog"
               top="7vh"
               @close="close"
               @open="create">
        <form autocomplete="off"
              @submit.prevent="clickAddItem">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-8">
                        <div id="custom-select"
                             :class="{'has-danger': errors.item_id}"
                             class="form-group">
                            <label class="control-label d-flex align-items-center">
                                Producto/Servicio
                                <span class="btn-add-new-product" v-if="can_add_new_product"
                                   @click.prevent="showDialogNewItem = true">
                                   <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                </span>
                            </label>
                            <template id="select-append">
                                <el-input id="custom-input" class="input-search-product">

                                    <el-select
                                        id="select-width"
                                        ref="selectSearchNormal"
                                        slot="prepend"
                                        v-model="form.item_id"
                                        :disabled="recordItem != null"
                                        :loading="loading_search"
                                        :remote-method="searchRemoteItems"
                                        filterable
                                        placeholder="Buscar"
                                        popper-class="el-select-items"
                                        remote
                                        @change="changeItem"
                                        @focus="focusSelectItem">


                                        <el-tooltip
                                            v-for="option in items"
                                            :key="option.id"
                                            placement="left">
                                            <div
                                                slot="content"
                                                v-html="ItemSlotTooltipView(option)"
                                            ></div>
                                            <el-option
                                                :label="ItemOptionDescriptionView(option)"
                                                :value="option.id"
                                            ></el-option>
                                        </el-tooltip>
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
                                    <!--
                                    <el-tooltip
                                        slot="append"
                                        :disabled="recordItem != null"
                                        class="item"
                                        content="Ver Stock del Producto"
                                        effect="dark"
                                        placement="bottom">
                                        <el-button
                                            @click.prevent="clickWarehouseDetail()">
                                            <i class="fa fa-search"></i>
                                        </el-button>
                                    </el-tooltip>
                                    -->
                                </el-input>
                            </template>

                            <small v-if="errors.item_id"
                                   class="form-control-feedback"
                                   v-text="errors.item_id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div :class="{'has-danger': errors.quantity}"
                             class="form-group">
                            <label class="control-label">Cantidad</label>
                            <el-input-number v-model="form.quantity"
                                             :disabled="form.item.calculate_quantity"
                                             :min="0.01"></el-input-number>
                            <small v-if="errors.quantity"
                                   class="form-control-feedback"
                                   v-text="errors.quantity[0]"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cerrar</el-button>
                <el-button v-if="form.item_id"
                           class="add"
                           native-type="submit"
                           type="primary">Agregar
                </el-button>
            </div>
        </form>
        <item-form :external="true"
                   :showDialog.sync="showDialogNewItem"
                   :input_item="itemSearchTerm"></item-form>

        <warehouses-detail
            :showDialog.sync="showWarehousesDetail"
            :warehouses="warehousesDetail">
        </warehouses-detail>

    </el-dialog>
</template>
<style>
.el-select-dropdown {
    margin-right: 5% !important;
    max-width: 80% !important;
}
</style>
<script>

import itemForm from '../../../../../../../../resources/js/views/tenant/items/form.vue'
import WarehousesDetail from '@views/documents/partials/select_warehouses.vue'
import {mapActions, mapState} from "vuex/dist/vuex.mjs";
import {ItemOptionDescription, ItemSlotTooltip} from "@helpers/modal_item";

export default {
    props: [
        'recordItem',
        'showDialog',
    ],
    components: {
        itemForm,
        WarehousesDetail,
    },
    data() {
        return {
            can_add_new_product: true,
            loading_search: false,
            titleDialog: 'Agregar Producto o Servicio',
            resource: 'purchase-quotations',
            showDialogNewItem: false,
            errors: {},
            form: {},
            all_items: [],
            items: [],
            aux_items: [],
            showWarehousesDetail: false,
            warehousesDetail: [],
            itemSearchTerm: '',
        }
    },
    watch: {
        showDialog(newVal) {
            if (newVal) {
                this.itemSearchTerm = ''
            }
        }
    },
    async created() {
        this.loadConfiguration()
        await this.initForm()
        await this.getItems();

        this.$eventHub.$on('reloadDataItems', (item_id) => {
            this.reloadDataItems(item_id)
            this.itemSearchTerm = ''
        })
    },

    computed: {

        ...mapState([
            'config',
        ]),
    },
    methods: {
        ...mapActions([
            'loadConfiguration',
        ]),

        clickWarehouseDetail() {

            if (!this.form.item_id) {
                return this.$message.error('Seleccione un item');
            }

            let item = _.find(this.items, {'id': this.form.item_id});

            this.warehousesDetail = item.warehouses
            this.showWarehousesDetail = true
        },
        async searchRemoteItems(input) {
            this.itemSearchTerm = input;

            if (input.length > 2) {
                this.loading_search = true
                const params = {
                    'input': input,
                    'search_by_barcode': this.search_item_by_barcode ? 1 : 0
                }
                await this.$http.get(`/${this.resource}/search-items/`, {params})
                    .then(response => {
                        this.items = response.data.items
                        this.loading_search = false
                        this.enabledSearchItemsBarcode()
                        this.enabledSearchItemBySeries()
                        if (this.items.length == 0) {
                            this.filterItems()
                        }
                    })
            } else {
                await this.filterItems()
            }
        },

        filterItems() {
            this.items = this.all_items
        },
        enabledSearchItemsBarcode() {
            if (this.search_item_by_barcode) {
                this.$refs.selectBarcode.$data.selectedLabel = '';
                if (this.items.length == 1) {
                    this.form.item_id = this.items[0].id;
                    this.$refs.selectBarcode.blur();
                    this.changeItem();
                }
            }
        },
        async enabledSearchItemBySeries() {

            if (this.config.search_item_by_series && this.items.length == 1) {

                this.$notify({title: "Serie ubicada", message: "Producto añadido!", type: "success", duration: 1200});
                this.form.item_id = this.items[0].id;
                this.$refs.selectSearchNormal.$data.selectedLabel = '';

                await this.changeItem();

                this.lots = await this.form.item.lots.map((lot) => {
                    lot.has_sale = true
                })

                await this.clickAddItem()

                this.$refs.selectSearchNormal.$data.selectedLabel = '';
            }

            if (this.config.search_item_by_series && this.items.length == 0) {
                this.$notify({title: "Serie no ubicada", message: "", type: "warning", duration: 1200});
            }

        },

        initForm() {
            this.errors = {};

            this.form = {
                item_id: null,
                item: {},
                quantity: 1,
                unit_type_id: null,
                is_set: false,
            };

        },

        ItemSlotTooltipView(item) {
            return ItemSlotTooltip(item);
        },
        ItemOptionDescriptionView(item) {
            return ItemOptionDescription(item)
        },
        create() {
        },
        close() {
            this.initForm()
            this.$emit('update:showDialog', false)
        },
        changeItem() {
            this.form.item = _.find(this.items, {'id': this.form.item_id});
            this.form.quantity = 1;
        },
        clickAddItem() {

            // this.form.affectation_igv_type = _.find(this.affectation_igv_types, {'id': this.form.affectation_igv_type_id});
            // this.row = calculateRowItem(this.form, this.currencyTypeIdActive, this.exchangeRateSale);
            this.$emit('add', this.form);
            this.initForm();
            this.setFocusSelectItem()
        },
        setFocusSelectItem() {
            this.$refs.selectSearchNormal.$el.getElementsByTagName('input')[0].focus()
        },
        getItems() {
            this.$http.get(`/${this.resource}/item/tables`).then(response => {
                this.items = response.data.items
            })
        },
        reloadDataItems(item_id) {
            this.$http.get(`/${this.resource}/table/items`).then((response) => {
                this.items = response.data
                this.form.item_id = item_id
                this.changeItem()
                // this.filterItems()

            })
        },
        focusSelectItem() {
            this.$refs.selectSearchNormal.$el.getElementsByTagName('input')[0].focus()
        },
        openNewItemDialog() {
            this.showDialogNewItem = true;
        },
    }
}

</script>
