<template>
    <el-dialog :visible="showDialog"
               class="dialog-import"
               title="Generar Etiquetas de Productos"
               @open="open"
               @close="close">
        <form autocomplete="off"
              @submit.prevent="submit">
            <div class="form-body">
                <div class="row">


                    <!-- Minimo -->
                    <div class="col-6">
                        <div class="form-group">
                            <label class="control-label">
                                Tipo de etiqueta
                                <a href="/" @click.prevent="showDialogEditor = true">[Abrir editor de etiqueta]</a>
                            </label>
                            <div>
                                <el-select v-model="form.template_id">
                                    <el-option
                                        v-for="option in templates"
                                        :key="option.id"
                                        :label="option.name"
                                        :value="option.id"
                                    ></el-option>
                                </el-select>
                            </div>
                        </div>
                    </div>
                    <el-tabs class="mt-4" type="card" @tab-click="handClick">
                        <el-tab-pane label="Selección individual" >
                        <div class="col-12">
                            <div class="row">
                                <div class="col-8">
                                <label class="control-label">
                                    Seleccionar producto
                                </label>
                                        <el-select
                                            id="select-width"
                                            ref="selectBarcode"
                                            slot="prepend"
                                            multiple
                                            v-model="form.items"
                                            :loading="loading_search"
                                            :remote-method="searchRemoteItems"
                                            filterable
                                            placeholder="Buscar"
                                            popper-class="el-select-items"
                                            remote
                                            value-key="id"
                                        >
                                            <el-option
                                                v-for="option in all_items"
                                                :key="option.id"
                                                :label="option.full_description"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                </div>
                                <div class="col-4 col-sm-4">
                                    <div
                                        class="form-group"
                                    >
                                        <label class="control-label">Etiquetas por producto</label>
                                        <el-input-number
                                            ref="inputQuantity"
                                            v-model="form.quantity"
                                            :min="0.01"
                                        ></el-input-number>
                                    </div>
                                </div>

                            </div>
                        </div>

                        </el-tab-pane>
                        <el-tab-pane label="Todo los productos">
                                <div class="col-12">
                                    <div
                                        class="form-group"
                                    >
                                        <label class="control-label">Etiquetas por producto</label>
                                        <el-input-number
                                            ref="inputQuantity"
                                            v-model="form.quantity_per_item"
                                            :min="0.01"
                                        ></el-input-number>
                                    </div>
                                </div>

                        </el-tab-pane>
                    </el-tabs>
                </div>
                <div class="row text-end mt-4">
                    <span>
                        Total: {{ total_records }} etiquetas 
                    </span>
                </div>
                <div class="form-actions text-end mt-4">
                    <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                    <el-button :loading="loading_submit"
                            native-type="submit"
                            type="primary">Procesar
                    </el-button>
                </div>
            </div>
        </form>
    <el-dialog :visible.sync="showDialogEditor"
               width="80%"
               custom-class="no-top"

               >
               <iframe src="/item-editor-tag" width="100%" height="800px"></iframe>
    </el-dialog>
    </el-dialog>
</template>

<style>
    .no-top {
  margin-top: 2vh !important;
}
</style>

<script>
import { param } from 'jquery';
import queryString from 'query-string';


export default {
    props: [
        'showDialog',
    ],
    data() {
        return {
            loading_submit: false,
            headers: headers_token,
            showDialogEditor: false,
            loading_search: false,
            resource: 'item-editor-tag',
            errors: {},
            form: {},
            templates: [],
            items: [],
            all_items: [],
            count_items: 0,
        }
    },

    created() {
        if (this.pharmacy !== undefined && this.pharmacy === true) {
            this.fromPharmacy = true;
        }
    },
    computed: {
        total_records() {
            if (this.form.type === 'individual' && this.form.items) {
                return this.form.items.length * this.form.quantity
            } else if (this.form.type === 'all' && this.items) {
                return this.count_items * this.form.quantity_per_item
            }
            return 0
        }
    },
    methods: {
        open() {
            this.initForm()
            this.getTables()
        },
        handClick(tab, event) {
            this.form.type = tab.paneName === "0" ? 'individual' : 'all';
        },
        initForm() {
            this.errors = {}
            this.form = {
                template_id: null,
                items: null,
                quantity: 1,
                quantity_per_item: 1,
                type: 'individual',
            }
        },
        close() {
            this.$emit('update:showDialog', false)
            this.initForm()
        },
        submit() {
            let form

            if (this.form.template_id == null) {
                this.$message.warning('Debe seleccionar una plantilla de etiqueta')
                return
            }

            if (this.form.type === 'individual') {

                if (this.form.items == null || this.form.items.length === 0) {
                    this.$message.warning('Debe seleccionar al menos un producto')
                    return
                }
                form = {
                    template_id: this.form.template_id,
                    items: this.form.items,
                    quantity_per_item: this.form.quantity,
                    type : this.form.type,
                }
                
            } else if (this.form.type === 'all') {
                form = {
                    template_id: this.form.template_id,
                    type : this.form.type,
                    quantity_per_item: this.form.quantity_per_item,
                }
            }

            let params =  queryString.stringify(form, { arrayFormat: 'bracket' })
            window.open(`${this.resource}/export?${params}` , '_blank')

            // this.$http.get(`${this.resource}/export`, {
            //     params: form
            // })
            //     .then(response => {
            //         this.loading_submit = false
            //         window.open(response.data.file, '_blank')
            //         this.$emit('update:showDialog', false)
            //         this.initForm()
            //     })
            //     .catch(error => {
            //         this.loading_submit = false
            //         if (error.response.status === 422) {
            //             this.errors = error.response.data.errors
            //         }
            //     })
            

            // this.$emit('update:showDialog', false)
            // this.initForm()
        },
        getTables() {
            this.$http.get(`${this.resource}/tables`)
                .then(response => {
                    this.templates = response.data.templates;
                    this.all_items = response.data.items;
                    this.count_items = response.data.count_items;
                    this.form.template_id = this.templates.length > 0 ? this.templates.filter( el => el.is_default)[0].id : null;
                })
        },
        async searchRemoteItems(input) {
            if (input.length > 2) {
                this.loading_search = true;
                const params = {
                    input: input,
                };
                await this.$http
                    .get(`/documents/search-items/`, { params })
                    .then(response => {
                        this.all_items = response.data.items;
                        this.loading_search = false;
                        if (this.items.length == 0) {
                            this.items = [];
                        }
                    });
            } 
        },
    }
}
</script>
