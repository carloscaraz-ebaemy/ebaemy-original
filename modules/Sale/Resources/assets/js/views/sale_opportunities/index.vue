<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/sale-opportunities">   
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-edit"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"
                        />
                        <path
                            d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"
                        />
                        <path d="M16 5l3 3" />
                    </svg>                 
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Oportunidad de venta</span></li>
            </ol>

            <div class="right-wrapper pull-right">
                <a :href="`/${resource}/create`" class="btn btn-custom btn-sm mt-2 me-2">
                    <i class="fa fa-plus-circle"></i> Nuevo
                </a>
            </div>
        </div>

        <div class="card tab-content-default row-new mb-0">

            <!-- Mostrar columnas -->
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar columnas<i class="el-icon-arrow-down el-icon--right"></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item v-for="(column, index) in columns" :key="index">
                            <el-checkbox v-model="column.visible" @change="saveColumnVisibility">
                                {{ column.title }}
                            </el-checkbox>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>

            <div class="card-body">

                <data-table
                    ref="dt"
                    :resource="resource"
                    @success="onTableLoaded"
                >
                    <!-- ENCABEZADOS -->
                    <tr slot="heading">
                        <th class="text-left">Fecha Emisión</th>
                        <th v-if="columns.sale.visible">Vendedor</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>O. Venta</th>
                        <th v-if="columns.quotation.visible">Cotización</th>
                        <th>O. Compra</th>
                        <th class="text-center">Moneda</th>
                        <th class="text-center">Archivos</th>
                        <th class="text-end" v-if="columns.total_exportation.visible">T.Exportación</th>
                        <th class="text-end" v-if="columns.total_unaffected.visible">T.Inafecta</th>
                        <th class="text-end" v-if="columns.total_exonerated.visible">T.Exonerado</th>
                        <th class="text-end" v-if="columns.total_taxed.visible">T.Gravado</th>
                        <th class="text-end" v-if="columns.total_igv.visible">T.Igv</th>
                        <th class="text-end">Total</th>
                        <!-- <th class="text-center">Descarga</th> -->
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }" :class="{ anulate_color : row.state_type_id == '11' }">
                        <!-- <td>{{ index }}</td> -->
                        <td class="text-start">{{ row.date_of_issue }}</td>
                        <td v-if="columns.sale.visible">{{ row.user_name }}</td>
                        <td>{{ row.customer_name }}<br/><small>{{ row.customer_number }}</small></td>
                        <td>{{ row.state_type_description }}</td>
                        <td>{{ row.number_full }}</td>
                        <td v-if="columns.quotation.visible">{{ row.quotation_number_full }}</td>
                        <td>{{ row.purchase_order_number_full }}</td>
                        <td class="text-center">{{ row.currency_type_id }}</td>

                        <td class="text-center">
                            <el-popover placement="right" width="400" trigger="click">
                                <div class="col-md-12">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nombre</th>
                                                    <th>Descarga</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(rowFile, i) in row.files" :key="i">
                                                    <td>{{ i + 1 }}</td>
                                                    <td>{{ rowFile.filename }}</td>
                                                    <td class="text-center">
                                                        <button class="btn btn-xs btn-primary"
                                                            @click.prevent="clickDownloadFile(rowFile.filename)">
                                                            <i class="fas fa-file-download"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <el-button slot="reference" class="second-buton">
                                    <i class="fa fa-eye"></i>
                                </el-button>
                            </el-popover>
                        </td>
                        <td class="text-end"  v-if="columns.total_exportation.visible" >{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_exportation }}</td>
                        <td class="text-end" v-if="columns.total_unaffected.visible">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_unaffected }}</td>
                        <td class="text-end" v-if="columns.total_exonerated.visible">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_exonerated }}</td>
                        <td class="text-end" v-if="columns.total_taxed.visible">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}}{{ row.total_taxed }}</td>
                        <td class="text-end" v-if="columns.total_igv.visible">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_igv }}</td>
                        <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total }}</td>

                        <td class="text-end">
                            <el-dropdown trigger="click" @command="handleCommand">
                                <el-button class="btn-dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                    <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item 
                                        v-if="row.btn_generate_oc && canGenerarte" 
                                        :command="{action: 'generateOC', id: row.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg> 
                                        Generar O. Compra
                                    </el-dropdown-item>
                                    <el-dropdown-item 
                                        v-if="row.btn_generate && canGenerarte" 
                                        :command="{action: 'generateQuotation', id: row.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg> 
                                        Generar Cotización
                                    </el-dropdown-item>
                                    <el-dropdown-item divided />
                                    <el-dropdown-item 
                                        v-if="row.state_type_id != '11' && (row.btn_generate && row.btn_generate_oc)" 
                                        :command="{action: 'edit', id: row.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg> 
                                        Editar
                                    </el-dropdown-item>
                                    <el-dropdown-item 
                                        :command="{action: 'options', id: row.id}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg> 
                                        Opciones
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>
                    
                </data-table>               
            </div>

            <sale-opportunities-options
                :showDialog.sync="showDialogOptions"
                :recordId="recordId"
                :showGenerate="true"
                :showClose="true"
            />
        </div>
    
    </div>
</template>

<script>
import SaleOpportunitiesOptions from './partials/options.vue'
import DataTable from '@components/DataTable.vue'
import { deletable } from '@mixins/deletable'
import axios from 'axios'

export default {
    props: ['typeUser', 'canGenerate'],
    mixins: [deletable],
    components: { DataTable, SaleOpportunitiesOptions },

    data() {
        return {
            resource: 'sale-opportunities',
            recordId: null,
            showDialogOptions: false,
            // items: [],

            columns: {
                total_exportation: { title: 'T.Exportación', visible: false },
                total_unaffected: { title: 'T.Inafecto', visible: false },
                total_exonerated: { title: 'T.Exonerado', visible: false },
                total_taxed: { title: 'T.Gravado', visible: false },
                total_igv: { title: 'T.IGV', visible: false },
                quotation: { title: 'Cotización', visible: true },
                sale: { title: 'Vendedor', visible: false },
            }
        }
    },

    computed: {
        canGenerarte() {
            return this.typeUser == 'admin' || this.canGenerate == true
        }
    },

    created() {
        this.loadColumnVisibility()
    },

    methods: {

        money(row) {
            return row.currency_type_id === 'PEN' ? 'S/' : '$'
        },

        saveColumnVisibility() {
            localStorage.setItem('columnVisibility', JSON.stringify(this.columns));
        },

        loadColumnVisibility() {
            const savedColumns = localStorage.getItem('columnVisibility');
            if (savedColumns) {
                this.columns = JSON.parse(savedColumns);
            }
        },

        async clickDownloadFile(filename) {
            try {
                const response = await axios.head(`/${this.resource}/download-file/${filename}`)
                if (response.status === 200) {
                    window.open(`/${this.resource}/download-file/${filename}`, '_blank')
                } else {
                    this.$message.warning('El archivo no está disponible.')
                }
            } catch (error) {
                this.$message.warning('El archivo no está disponible.')
            }
        },
            clickDownload(external_id) {
                window.open(`/${this.resource}/download/${external_id}`, '_blank');
            },
            clickEdit(id)
            {
                this.recordId = id
                this.showDialogFormEdit = true
            },
            clickOptions(recordId = null) {
                this.recordId = recordId
                this.showDialogOptions = true
            },
            clickOptionsPdf(recordId = null) {
                this.recordId = recordId
                this.showDialogOptionsPdf = true
            },
            handleCommand(command) {
                switch(command.action) {
                    case 'generateOC':
                        window.location.href = `/purchase-orders/sale-opportunity/${command.id}`;
                        break;
                    case 'generateQuotation':
                        window.location.href = `/quotations/create/${command.id}`;
                        break;
                    case 'edit':
                        window.location.href = `/${this.resource}/create/${command.id}`;
                        break;
                    case 'options':
                        this.clickOptions(command.id);
                        break;
                }
            }
    }
}
</script>

<style scoped>
.anulate_color {
    color: red;
}
</style>
