<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/sale-opportunities">                    
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Oportunidad de venta</span></li>
            </ol>

            <div class="right-wrapper pull-right">
                <a :href="`/${resource}/create`" class="btn btn-custom btn-sm mt-2 mr-2">
                    <i class="fa fa-plus-circle"></i> Nuevo
                </a>
            </div>
        </div>

        <div class="card tab-content-default row-new mb-0">

            <!-- Mostrar/Ocultar columnas -->
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar/Ocultar columnas<i class="el-icon-arrow-down el-icon--right"></i>
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
                        <th class="text-right" v-if="columns.total_exportation.visible">T.Exportación</th>
                        <th class="text-right" v-if="columns.total_unaffected.visible">T.Inafecta</th>
                        <th class="text-right" v-if="columns.total_exonerated.visible">T.Exonerado</th>
                        <th class="text-right" v-if="columns.total_taxed.visible">T.Gravado</th>
                        <th class="text-right" v-if="columns.total_igv.visible">T.Igv</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Descarga</th>
                        <th class="text-right">Acciones</th>
                    </tr>

                    <!-- FILAS -->
                    <tr slot-scope="{ index, row }" :class="{ anulate_color: row.state_type_id == '11' }">
                        <td class="text-left">{{ row.date_of_issue }}</td>
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

                        <td v-if="columns.total_exportation.visible" class="text-right">
                            {{ money(row) }} {{ row.total_exportation }}
                        </td>
                        <td v-if="columns.total_unaffected.visible" class="text-right">
                            {{ money(row) }} {{ row.total_unaffected }}
                        </td>
                        <td v-if="columns.total_exonerated.visible" class="text-right">
                            {{ money(row) }} {{ row.total_exonerated }}
                        </td>
                        <td v-if="columns.total_taxed.visible" class="text-right">
                            {{ money(row) }} {{ row.total_taxed }}
                        </td>
                        <td v-if="columns.total_igv.visible" class="text-right">
                            {{ money(row) }} {{ row.total_igv }}
                        </td>

                        <td class="text-right">
                            {{ money(row) }} {{ row.total }}
                        </td>

                        <td class="text-right">
                            <button class="btn btn-xs btn-info"
                                @click.prevent="clickDownload(row.external_id)">PDF</button>
                        </td>

                        <td class="text-right">
                            <a v-if="row.btn_generate_oc && canGenerarte"
                                :href="`/purchase-orders/sale-opportunity/${row.id}`"
                                class="btn btn-xs btn-warning">
                                Generar O. Compra
                            </a>

                            <a v-if="row.btn_generate && canGenerarte"
                                :href="`/quotations/create/${row.id}`"
                                class="btn btn-xs btn-primary">
                                Generar cotización
                            </a>

                            <a v-if="row.state_type_id != '11' && (row.btn_generate && row.btn_generate_oc)"
                                :href="`/${resource}/create/${row.id}`"
                                class="btn btn-xs btn-info">
                                Editar
                            </a>

                            <button class="btn btn-xs btn-info"
                                @click.prevent="clickOptions(row.id)">
                                Opciones
                            </button>
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
                this.$message.warning('El archivo no existe o fue eliminado.')
            }
        },

        clickDownload(external_id) {
            window.open(`/${this.resource}/download/${external_id}`, '_blank')
        },

        clickOptions(id) {
            this.recordId = id
            this.showDialogOptions = true
        }
    }
}
</script>

<style scoped>
.anulate_color {
    color: red;
}
</style>
