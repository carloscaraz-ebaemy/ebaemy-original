<template>
    <div class="quotations">
        <div class="page-header pe-0">
            <h2>
                <a href="/quotations">
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
                <li class="active"><span>Cotizaciones</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <a
                    :href="`/${resource}/create`"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    ><i class="fa fa-plus-circle"></i> Nuevo</a
                >
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar columnas<i
                            class="el-icon-arrow-down el-icon--right"
                        ></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item
                            v-for="(column, index) in columns"
                            :key="index"
                        >
                            <el-checkbox
                                v-model="column.visible"
                                @change="saveColumnVisibilityQuotations"
                                >{{ column.title }}</el-checkbox
                            >
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource" :state-types="state_types">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th class="text-start">Fecha Emisión</th>
                        <th
                            class="text-center"
                            v-if="columns.delivery_date.visible"
                        >
                            T. Entrega
                        </th>
                        <th>Registrado por</th>
                        <th>Vendedor</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Cotización</th>
                        <th>Comprobantes</th>
                        <th>Notas de venta</th>
                        <th v-if="columns.order_note.visible">Pedido</th>
                        <th>Oportunidad Venta</th>
                        <th v-if="columns.referential_information.visible">
                            Inf.Referencial
                        </th>
                        <th v-if="columns.contract.visible">Contrato</th>
                        <!-- <th>Estado</th> -->
                        <th v-if="columns.exchange_rate_sale.visible">T.C.</th>
                        <th class="text-center">Moneda</th>
                        <th class="text-center"></th>
                        <th
                            class="text-end"
                            v-if="columns.total_exportation.visible"
                        >
                            T.Exportación
                        </th>
                        <th
                            class="text-end"
                            v-if="columns.total_free.visible"
                        >
                            T.Gratuito
                        </th>
                        <th
                            class="text-end"
                            v-if="columns.total_unaffected.visible"
                        >
                            T.Inafecta
                        </th>
                        <th
                            class="text-end"
                            v-if="columns.total_exonerated.visible"
                        >
                            T.Exonerado
                        </th>
                        <th class="text-end">T.Gravado</th>
                        <th class="text-end">T.Igv</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">PDF</th>
                        <th class="text-end"></th>
                        <!-- <th class="text-right">Acciones</th> -->
                    </tr>
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ anulate_color: row.state_type_id == '11' }"
                    >
                        <td class="text-start">
                            {{ formatDate(row.date_of_issue) }}
                        </td>
                        <td
                            class="text-center"
                            v-if="columns.delivery_date.visible"
                        >
                            {{ row.delivery_date }}
                        </td>
                        <td>{{ row.user_name }}</td>
                        <td>{{ row.seller_name }}</td>
                        <td>
                            {{ row.customer_name }}<br /><small
                                v-text="row.customer_number"
                            ></small>
                        </td>
                        <td>
                            <template v-if="row.state_type_id == '11'">
                                {{ row.state_type_description }}
                            </template>
                            <template v-else>
                                <el-select
                                    v-model="row.state_type_id"
                                    @change="changeStateType(row)"
                                    style="width:120px !important"
                                >
                                    <el-option
                                        v-for="option in state_types"
                                        :key="option.id"
                                        :value="option.id"
                                        :label="option.description"
                                    ></el-option>
                                </el-select>
                            </template>
                        </td>
                        <td>{{ row.identifier }}</td>
                        <td>
                            <template v-for="(document, i) in row.documents">
                                <template v-if="document.is_voided_or_rejected">
                                    <label :key="i" class="d-block text-danger">
                                        {{ document.number_full }}
                                        <!-- {{ document.number_full }} ({{document.state_type_description}}) -->
                                    </label>
                                </template>
                                <template v-else>
                                    <label
                                        :key="i"
                                        v-text="document.number_full"
                                        class="d-block"
                                    ></label>
                                </template>
                            </template>
                        </td>
                        <td>
                            <template v-for="(sale_note, i) in row.sale_notes">
                                <label
                                    :key="i"
                                    v-text="sale_note.number_full"
                                    class="d-block"
                                ></label>
                            </template>
                        </td>
                        <td v-if="columns.order_note.visible">
                            <!-- Pedidos -->
                            <template
                                v-if="
                                    row.order_note !== undefined &&
                                        row.order_note.full_number !== undefined
                                "
                            >
                                <label class="d-block"
                                    >{{ row.order_note.full_number }}
                                </label>
                            </template>
                        </td>
                        <td>

                            <el-popover
                                placement="right"
                                v-if="row.sale_opportunity"
                                width="400"
                                trigger="click"
                            >
                                <div class="col-md-12 mt-4">
                                    <table>
                                        <tr>
                                            <td><strong>O. Venta: </strong></td>
                                            <td>
                                                <strong>{{
                                                    row.sale_opportunity_number_full
                                                }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Detalle: </strong></td>
                                            <td>
                                                <strong>{{
                                                    row.sale_opportunity.detail
                                                }}</strong>
                                            </td>
                                        </tr>
                                        <tr class="mt-4 mb-4">
                                            <td>
                                                <strong>F. Emisión:</strong>
                                            </td>
                                            <td>
                                                <strong>{{
                                                    row.date_of_issue
                                                }}</strong>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Descripción</th>
                                                    <th>Cantidad</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, index) in row
                                                        .sale_opportunity.items"
                                                    :key="index"
                                                >
                                                    <td>{{ index + 1 }}</td>
                                                    <td>
                                                        {{
                                                            row.item.description
                                                        }}
                                                    </td>
                                                    <td>{{ row.quantity }}</td>
                                                    <td>{{ row.total }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <el-button slot="reference"
                                    ><i class="fa fa-eye"></i
                                ></el-button>
                            </el-popover>
                        </td>
                        <!-- <td>{{ row.state_type_description }}</td> -->
                        <td v-if="columns.referential_information.visible">
                            {{ row.referential_information }}
                        </td>
                        <td v-if="columns.contract.visible">
                            {{ row.contract_number_full }}
                        </td>
                        <td v-if="columns.exchange_rate_sale.visible">
                            {{ row.exchange_rate_sale }}
                        </td>
                        <td class="text-center">{{ row.currency_type_id }}</td>

                        <td class="text-end">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickPayment(row.id)"
                            >
                                Pagos
                            </button>
                        </td>

                        <td
                            class="text-end"
                            v-if="columns.total_exportation.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_exportation }}
                        </td>
                        <td
                            class="text-end"
                            v-if="columns.total_free.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_free }}
                        </td>
                        <td
                            class="text-end"
                            v-if="columns.total_unaffected.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_unaffected }}
                        </td>
                        <td
                            class="text-end"
                            v-if="columns.total_exonerated.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_exonerated }}
                        </td>
                        <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_taxed }}</td>
                        <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_igv }}</td>
                        <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total }}</td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickOptionsPdf(row.id)"
                            >
                                PDF
                            </button>
                        </td>

                        <td class="text-end">
                            <el-dropdown trigger="click" placement="bottom-end">
                                <el-button class="btn-dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                    <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item
                                      v-if="row.btn_options"
                                      @click.native="clickGenerateDocument(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" class="me-2" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                                        <path d="M19 12v7a1.78 1.78 0 0 1 -3.1 1.4a1.65 1.65 0 0 0 -2.6 0a1.65 1.65 0 0 1 -2.6 0a1.65 1.65 0 0 0 -2.6 0a1.78 1.78 0 0 1 -3.1 -1.4v-14a2 2 0 0 1 2 -2h7l5 5v4.25"/>
                                      </svg>
                                      Generar comprobante
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                      v-if="row.btn_options"
                                      @click.native="clickOptions(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" class="me-2" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M5 4v17l2 -2l2 2l2 -2l2 2l2 -2l2 2l2 -2v-17z"/>
                                        <path d="M14 8h-4"/>
                                        <path d="M14 12h-4"/>
                                        <path d="M14 16h-4"/>
                                      </svg>
                                      Generar nota de venta
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                      @click.native="clickSendQuotation(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-arrow-right me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 15h6" /><path d="M12.5 17.5l2.5 -2.5l-2.5 -2.5" /></svg>
                                      Enviar cotización
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                      v-if="canMakeOrderNote(row)"
                                      @click.native="makeOrder(row.id)"
                                    >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                                      Generar Pedido
                                    </el-dropdown-item>

                                    <el-dropdown-item divided />

                                    <el-dropdown-item
                                      @click.native="goToDispatch(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck me-2">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path>
                                      </svg>
                                      Guía
                                    </el-dropdown-item>

                                    <template
                                      v-if="row.btn_generate_cnt && row.state_type_id != '11'"
                                    >
                                      <el-dropdown-item
                                        @click.native="goToContract(row.id)"
                                      >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-contract me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 21h-2a3 3 0 0 1 -3 -3v-1h5.5" /><path d="M17 8.5v-3.5a2 2 0 1 1 2 2h-2" /><path d="M19 3h-11a3 3 0 0 0 -3 3v11" /><path d="M9 7h4" /><path d="M9 11h4" /><path d="M18.42 12.61a2.1 2.1 0 0 1 2.97 2.97l-6.39 6.42h-3v-3z" /></svg>
                                        Generar contrato
                                      </el-dropdown-item>
                                    </template>

                                    <template v-else>
                                      <el-dropdown-item
                                        @click.native="clickPrintContract(row.external_id_contract)"
                                      >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-check me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 15l2 2l4 -4" /></svg>
                                        Ver contrato
                                      </el-dropdown-item>
                                    </template>

                                    <el-dropdown-item divided />

                                    <el-dropdown-item
                                      v-if="row.documents.length == 0 && row.state_type_id != '11'"
                                      @click.native="goToEdit(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                      Editar
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                      @click.native="duplicate(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-copy me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" /><path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" /></svg>
                                      Duplicar
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                      v-if="row.state_type_id != '11' && row.state_type_id != '05'"
                                      @click.native="clickConvertToSaleNote(row.id)"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-receipt me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h7l5 5v13l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M9 17h6"/><path d="M9 13h6"/></svg>
                                      Convertir a NV
                                    </el-dropdown-item>

                                    <el-dropdown-item divided />

                                    <el-dropdown-item
                                      v-if="row.documents.length == 0 && row.state_type_id != '11'"
                                      @click.native="clickAnulate(row.id)"
                                      class="text-danger option-delete"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-x me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M10 10l4 4m0 -4l-4 4" /></svg>
                                      Anular
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <quotation-options
                :showDialog.sync="showDialogOptions"
                :recordId="recordId"
                :showGenerate="true"
                :showClose="true"
            ></quotation-options>

            <quotation-options-pdf
                :showDialog.sync="showDialogOptionsPdf"
                :recordId="recordId"
                :showClose="true"
            ></quotation-options-pdf>

            <quotation-payments
                :showDialog.sync="showDialogPayments"
                :recordId="recordId"
            ></quotation-payments>

            <send-email-document
                :config="config"
                :showDialog.sync="showDialogSendEmailDocument"
                :recordId="recordId"
                :resource="resource"
            ></send-email-document>
        </div>
    </div>
</template>
<style scoped>
.anulate_color {
    color: red;
}
</style>
<script>
import QuotationOptions from "./partials/options.vue";
import QuotationOptionsPdf from "./partials/options_pdf.vue";
import DataTable from "../../../components/DataTableQuotation.vue";
import { deletable } from "../../../mixins/deletable";
import QuotationPayments from "./partials/payments.vue";
import { mapActions, mapState } from "vuex";
import SendEmailDocument from "@components/secondary/SendEmailDocument.vue";

export default {
    props: ["typeUser", "soapCompany", "generateOrderNoteFromQuotation"],
    mixins: [deletable],
    components: {
        DataTable,
        QuotationOptions,
        QuotationOptionsPdf,
        QuotationPayments,
        SendEmailDocument
    },
    computed: {
        ...mapState(["config"])
    },
    data() {
        return {
            resource: "quotations",
            showDialogSendEmailDocument: false,
            recordId: null,
            showDialogPayments: false,
            showDialogOptions: false,
            showDialogOptionsPdf: false,
            state_types: [],
            columns: {
                total_exportation: {
                    title: "T.Exportación",
                    visible: false
                },
                total_unaffected: {
                    title: "T.Inafecto",
                    visible: false
                },
                total_exonerated: {
                    title: "T.Exonerado",
                    visible: false
                },
                total_free: {
                    title: "T.Gratuito",
                    visible: false
                },
                contract: {
                    title: "Contrato",
                    visible: false
                },
                delivery_date: {
                    title: "T.Entrega",
                    visible: false
                },
                referential_information: {
                    title: "Inf.Referencial",
                    visible: false
                },
                order_note: {
                    title: "Pedidos",
                    visible: false
                },
                exchange_rate_sale: {
                    title: "Tipo de cambio",
                    visible: false
                }
            }
        };
    },
    async created() {
        this.loadColumnVisibilityQuotations();
        await this.filter();
    },
    mounted() {
        this.loadConfiguration();
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            return moment(date).format("DD-MM-YYYY");
        },
        saveColumnVisibilityQuotations() {
            localStorage.setItem(
                "columnVisibilityQuotations",
                JSON.stringify(this.columns)
            );
        },
        loadColumnVisibilityQuotations() {
            const savedColumns = localStorage.getItem(
                "columnVisibilityQuotations"
            );
            if (savedColumns) {
                this.columns = JSON.parse(savedColumns);
            }
        },
        clickSendQuotation(id) {
            this.recordId = id;
            this.showDialogSendEmailDocument = true;
        },
        ...mapActions(["loadConfiguration"]),
        canMakeOrderNote(row) {
            let permission = true;

            // Si ya tiene Pedidos, no se genera uno nuevo
            if (row.order_note.full_number) {
                permission = false;
            } else {
                if (this.typeUser !== "admin") {
                    permission = this.generateOrderNoteFromQuotation;
                }
            }

            return permission;
        },
        clickPrintContract(external_id) {
            window.open(`/contracts/print/${external_id}/a4`, "_blank");
        },
        clickPayment(recordId) {
            this.recordId = recordId;
            this.showDialogPayments = true;
        },
        async changeStateType(row) {
            await this.updateStateType(
                `/${this.resource}/state-type/${row.state_type_id}/${row.id}`
            ).then(() => this.$eventHub.$emit("reloadData"));
        },
        async filter() {
            await this.$http.get(`/${this.resource}/filter`).then(response => {
                this.state_types = response.data.state_types;
            });
        },
        clickEdit(id) {
            this.recordId = id;
            this.showDialogFormEdit = true;
        },
        clickOptions(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptions = true;
        },
        clickOptionsPdf(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptionsPdf = true;
        },
        clickAnulate(id) {
            this.anular(`/${this.resource}/anular/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickConvertToSaleNote(id) {
            this.$confirm(
                'Se generará una Nota de Venta con los datos de esta cotización. La cotización quedará marcada como Aceptada. ¿Continuar?',
                'Convertir a Nota de Venta',
                {
                    confirmButtonText: 'Sí, generar NV',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            ).then(() => {
                this.$http.post(`/${this.resource}/${id}/generate-sale-note`)
                    .then(({ data }) => {
                        if (data.success) {
                            this.$message.success(data.message);
                            this.$eventHub.$emit("reloadData");
                            // Abrir la NV generada en una nueva pestaña para que el operador la complete
                            if (data.sale_note_id) {
                                window.open(`/sale-notes/edit/${data.sale_note_id}`, '_blank');
                            }
                        } else {
                            this.$message.error(data.message || 'No se pudo generar la NV');
                        }
                    })
                    .catch((err) => {
                        this.$message.error(err.response?.data?.message || 'Error de servidor al generar NV');
                    });
            }).catch(() => {});
        },
        makeOrder(quotation) {
            let tos = parseInt(quotation);
            localStorage.setItem("Quotation", tos);
            localStorage.setItem("FromQuotation", true);
            window.location.href = "/order-notes/create";
        },
        duplicate(id) {
            this.$http
                .post(`${this.resource}/duplicate`, { id })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(
                            "Se guardaron los cambios correctamente."
                        );
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error("No se guardaron los cambios");
                    }
                })
                .catch(error => {});
            this.$eventHub.$emit("reloadData");
        },
        clickGenerateDocument(recordId) {
            window.location.href = `/documents/create/quotations/${recordId}`;
        },
        // Métodos auxiliares para redirecciones en dropdown
        goToEdit(id) {
            window.location.href = `/${this.resource}/edit/${id}`;
        },
        goToDispatch(id) {
            window.location.href = `/dispatches/create_new/quotation/${id}`;
        },
        goToContract(id) {
            window.location.href = `/contracts/generate-quotation/${id}`;
        },
        go(url) {
          window.location.href = url;
        }
    }
};
</script>
