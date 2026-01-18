<template>
    <div class="sale_notes">
        <div class="page-header pr-0">
            <h2>
                <a href="/sale-notes">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        style="margin-top: -5px;"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="feather feather-file-text"
                    >
                        <path
                            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                        ></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Notas de Venta</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <a
                    href="#"
                    @click.prevent="clickCreate()"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    ><i class="fa fa-plus-circle"></i> Nuevo</a
                >
                <a
                    href="#"
                    @click.prevent="onOpenModalGenerateCPE"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    >Generar comprobante desde múltiples Notas</a
                >
                <a
                    href="#"
                    v-if="config.send_data_to_other_server === true"
                    @click.prevent="onOpenModalMigrateNv"
                    class="btn btn-custom btn-sm  mt-2 me-2"
                    >Migrar Datos</a
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
                                @change="getColumnsToShow(1)"
                                v-model="column.visible"
                                >{{ column.title }}</el-checkbox
                            >
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th
                            class="text-end"
                            v-if="columns.seller_name.visible"
                        >
                            Vendedor
                        </th>

                        <th class="text-center">Fecha Emisión</th>
                        <th
                            class="text-center"
                            v-if="columns.date_payment.visible"
                        >
                            Fecha de pago
                        </th>
                        <th>Cliente</th>
                        <th>Nota de Venta</th>
                        <th>Estado</th>
                        <th
                            class="text-end"
                            v-if="columns.exchange_rate_sale.visible"
                        >
                            T.C.
                        </th>
                        <th class="text-center">Moneda</th>
                        <th class="text-end" v-if="columns.due_date.visible">
                            F. Vencimiento
                        </th>
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
                        <th
                            class="text-end"
                            v-if="columns.total_taxed.visible"
                        >
                            T.Gravado
                        </th>
                        <th class="text-end" v-if="columns.total_igv.visible">
                            T.Igv
                        </th>
                        <th class="text-end">Total</th>

                        <th
                            class="text-center"
                            v-if="columns.total_paid.visible"
                        >
                            Pagado
                        </th>
                        <th
                            class="text-center"
                            v-if="columns.total_pending_paid.visible"
                        >
                            Por pagar
                        </th>

                        <th class="text-center">Comprobantes</th>
                        <th class="text-center">Estado pago</th>
                        <th class="text-center">Orden de compra</th>

                        <th class="text-center">Pagos</th>
                        <th class="text-center">Descarga</th>
                        <th
                            class="text-center"
                            v-if="columns.recurrence.visible"
                        >
                            Recurrencia
                        </th>
                        <th class="text-start" v-if="columns.region.visible">
                            Region
                        </th>
                        <th
                            class="text-end"
                            v-if="columns.dispatch_status.visible"
                        >
                            Estado de despacho
                        </th>
                        <th
                            class="text-center"
                            v-if="columns.type_period.visible"
                        >
                            Tipo Periodo
                        </th>
                        <th
                            class="text-center"
                            v-if="columns.quantity_period.visible"
                        >
                            Cantidad Periodo
                        </th>
                        <th class="text-center" v-if="columns.paid.visible">
                            Estado de Pago
                        </th>
                        <th
                            class="text-center"
                            v-if="columns.license_plate.visible"
                        >
                            Placa
                        </th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td
                            class="text-end"
                            v-if="columns.seller_name.visible"
                        >
                            {{ row.seller_name }}
                        </td>

                        <td class="text-center">
                            {{ formatDate(row.date_of_issue) }}
                        </td>
                        <td
                            class="text-center"
                            v-if="columns.date_payment.visible"
                        >
                            {{ formatDate(row.date_of_payment) }}
                        </td>
                        <td>
                            {{ row.customer_name }}<br /><small
                                v-text="row.customer_number"
                            ></small>
                        </td>
                        <td>{{ row.full_number }}</td>
                        <td>{{ row.state_type_description }}</td>
                        <td
                            class="text-center"
                            v-if="columns.exchange_rate_sale.visible"
                        >
                            {{ row.exchange_rate_sale }}
                        </td>
                        <td class="text-center">{{ row.currency_type_id }}</td>

                        <td class="text-end" v-if="columns.due_date.visible">
                            {{ formatDate(row.due_date) }}
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

                        <td
                            class="text-end"
                            v-if="columns.total_taxed.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_taxed }}
                        </td>
                        <td class="text-end" v-if="columns.total_igv.visible">
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_igv }}
                        </td>
                        <td class="text-end">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total }}</td>

                        <td
                            class="text-center"
                            v-if="columns.total_paid.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_paid }}
                        </td>
                        <td
                            class="text-center"
                            v-if="columns.total_pending_paid.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_pending_paid }}
                        </td>
                        <td>
                            <template v-for="(document, i) in row.documents">
                                <label
                                    :key="i"
                                    v-text="document.number_full"
                                    class="d-block"
                                ></label>
                            </template>
                        </td>
                        <td class="text-center">
                            <template v-if="row.state_type_id === '11'">
                                <span class="badge text-white bg-danger">{{
                                    row.state_type_description
                                }}</span>
                            </template>
                            <template v-else>
                                <span
                                    class="badge text-white"
                                    :class="{
                                        'bg-success': row.total_canceled,
                                        'bg-warning': !row.total_canceled
                                    }"
                                    >{{
                                        row.total_canceled
                                            ? "Pagado"
                                            : "Pendiente"
                                    }}</span
                                >
                            </template>
                        </td>

                        <td>{{ row.purchase_order }}</td>

                        <td class="text-center">
                            <button
                                type="button"
                                style="min-width: 41px"
                                class="btn waves-effect waves-light btn-xs btn-primary"
                                @click.prevent="clickPayment(row.id)"
                            >
                                <i class="fas fa-money-bill-alt"></i>
                            </button>
                        </td>

                        <td class="text-end">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickDownload(row.external_id)"
                            >
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </td>
                        <td
                            class="text-end"
                            v-if="columns.recurrence.visible"
                        >
                            <template
                                v-if="
                                    row.type_period && row.quantity_period > 0
                                "
                            >
                                <el-switch
                                    :disabled="row.apply_concurrency"
                                    v-model="row.enabled_concurrency"
                                    active-text="Si"
                                    inactive-text="No"
                                    @change="changeConcurrency(row)"
                                ></el-switch>
                            </template>
                        </td>

                        <td class="text-start" v-if="columns.region.visible">
                            {{ row.customer_region }}
                        </td>

                        <td
                            class="text-end"
                            v-if="columns.dispatch_status.visible"
                        >
                            <template
                                v-if="row.status_dispatch === 'ENTREGADO'"
                            >
                                <button
                                    type="button"
                                    style="min-width: 41px"
                                    class="btn waves-effect waves-light btn-xs btn-success"
                                    @click.prevent="
                                        clickDispatchStatus(row.id, false)
                                    "
                                >
                                    {{ row.status_dispatch }}
                                </button>
                            </template>

                            <template
                                v-if="row.status_dispatch === 'PENDIENTE'"
                            >
                                <button
                                    type="button"
                                    style="min-width: 41px"
                                    class="btn waves-effect waves-light btn-xs btn-danger"
                                    @click.prevent="
                                        clickDispatchStatus(row.id, true)
                                    "
                                >
                                    {{ row.status_dispatch }}
                                </button>
                            </template>

                            <template v-if="row.status_dispatch === 'PARCIAL'">
                                <button
                                    type="button"
                                    style="min-width: 41px"
                                    class="btn waves-effect waves-light btn-xs btn-warning"
                                    @click.prevent="
                                        clickDispatchStatus(row.id, true)
                                    "
                                >
                                    {{ row.status_dispatch }}
                                </button>
                            </template>
                        </td>

                        <td
                            class="text-end"
                            v-if="columns.type_period.visible"
                        >
                            {{ row.type_period | period }}
                        </td>
                        <td
                            class="text-end"
                            v-if="columns.quantity_period.visible"
                        >
                            {{ row.quantity_period }}
                        </td>

                        <td class="text-end" v-if="columns.paid.visible">
                            {{ row.paid ? "Pagado" : "Pendiente" }}
                        </td>

                        <td
                            class="text-end"
                            v-if="columns.license_plate.visible"
                        >
                            {{ row.license_plate }}
                        </td>

                        <td class="text-end">
                            <el-dropdown trigger="click" size="small">
                                <el-button class="btn-dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                    <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                  <el-dropdown-item
                                    v-if="row.btn_generate && row.state_type_id != '11' && typeUser != 'seller'"
                                    @click.native="clickCreate(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                    Editar
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item
                                    v-if="!row.changed && row.state_type_id != '11' && soapCompany != '03'"
                                    @click.native="clickGenerate(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M19 12v7a1.78 1.78 0 0 1 -3.1 1.4a1.65 1.65 0 0 0 -2.6 0a1.65 1.65 0 0 1 -2.6 0a1.65 1.65 0 0 0 -2.6 0a1.78 1.78 0 0 1 -3.1 -1.4v-14a2 2 0 0 1 2 -2h7l5 5v4.25"></path></svg>
                                    Generar comprobante
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item divided />
                              
                                  <template v-if="row.changed">
                                    <el-dropdown-item
                                      v-for="(document, i) in row.documents"
                                      :key="i"
                                    >
                                      <a
                                        :href="`/dispatches/create/${document.id}`"
                                        style="text-decoration: none; color: inherit;"
                                      >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M19 12v7a1.78 1.78 0 0 1 -3.1 1.4a1.65 1.65 0 0 0 -2.6 0a1.65 1.65 0 0 1 -2.6 0a1.65 1.65 0 0 0 -2.6 0a1.78 1.78 0 0 1 -3.1 -1.4v-14a2 2 0 0 1 2 -2h7l5 5v4.25"></path></svg>
                                        Generar guía desde CPE
                                      </a>
                                    </el-dropdown-item>
                                  </template>
                              
                                  <el-dropdown-item>
                                    <a
                                      :href="`/dispatches/create_new/sale_note/${row.id}`"
                                      style="text-decoration: none; color: inherit;"
                                    >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck me-2">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path>
                                        <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path>
                                      </svg>
                                      Generar guía
                                    </a>
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item divided />
                              
                                  <el-dropdown-item
                                    v-if="row.state_type_id != '11'"
                                    @click.native="clickOptions(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-printer me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" /><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" /><path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" /></svg>
                                    Imprimir
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item
                                    @click.native="duplicate(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-copy me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z" /><path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" /></svg>
                                    Duplicar nota de venta
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item
                                    v-if="row.state_type_id != '11' && row.send_other_server === true"
                                    @click.native="sendToServer(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-server-2 me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 12m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M7 8l0 .01" /><path d="M7 16l0 .01" /><path d="M11 8h6" /><path d="M11 16h6" /></svg>
                                    Enviar a otro servidor
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item divided />
                              
                                  <el-dropdown-item
                                    v-if="configuration.delete_relation_note_to_invoice && row.documents.length > 0"
                                    class="text-danger option-delete"
                                    @click.native="sendDeleteRelationInvoice(row.id)"
                                  >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                    Eliminar factura relacionada
                                  </el-dropdown-item>
                              
                                  <el-dropdown-item
                                    v-if="row.state_type_id != '11'"
                                    @click.native="clickVoided(row.id)"
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
        </div>
        <!-- <el-dialog
            title="Eliminar Documento Relacionado"
            :visible="showDialogDeleteRelationInvoice"
            >
            <table>
                <tr v-for="(document, index) in dataDeleteRelation.documents" :key="index">
                    <td>
                        <el-button
                            type="button"
                            class="btn waves-effect waves-light btn-xs btn-danger"
                            @click.prevent="deleteRelationInvoice(row.id)">
                            <i class="fas fa-trash"></i>
                        </el-button>
                    </td>
                    <td>
                        {{document.number_full}}
                    </td>
                </tr>
            </table>
        </el-dialog> -->

        <sale-note-payments
            :showDialog.sync="showDialogPayments"
            :documentId="recordId"
        ></sale-note-payments>

        <sale-notes-options
            :showDialog.sync="showDialogOptions"
            :recordId="saleNotesNewId"
            :showClose="true"
            :configuration="config"
        ></sale-notes-options>

        <sale-note-generate
            :show.sync="showDialogGenerate"
            :recordId="recordId"
            :showGenerate="true"
            :showClose="false"
        ></sale-note-generate>
        <ModalGenerateCPE :show.sync="showModalGenerateCPE"></ModalGenerateCPE>
        <UploadToOtherServer
            :configuration="config"
            :showMigrate.sync="showMigrateNv"
        ></UploadToOtherServer>

        <sale-note-dispatch-status
            :showDialog.sync="showDialogDispatch"
            :documentId="recordId"
            :statusDispatch="statusDispatch"
            :typeUser="typeUser"
        ></sale-note-dispatch-status>
    </div>
</template>

<script>
import DataTable from "../../../components/DataTableSaleNote.vue";
import UploadToOtherServer from "./partials/upload_other_server_group.vue";
import SaleNotePayments from "./partials/payments.vue";
import SaleNotesOptions from "./partials/options.vue";
import SaleNoteGenerate from "./partials/option_documents.vue";
import { deletable } from "../../../mixins/deletable";
import ModalGenerateCPE from "./ModalGenerateCPE.vue";
import { mapActions, mapState } from "vuex/dist/vuex.mjs";
import SaleNoteDispatchStatus from "./partials/dispatch_status.vue";

export default {
    props: ["soapCompany", "typeUser", "configuration"],
    mixins: [deletable],
    components: {
        DataTable,
        SaleNotePayments,
        SaleNotesOptions,
        SaleNoteGenerate,
        ModalGenerateCPE,
        UploadToOtherServer,
        SaleNoteDispatchStatus
    },
    computed: {
        ...mapState(["config"])
    },
    data() {
        return {
            showModalGenerateCPE: false,
            showMigrateNv: false,
            resource: "sale-notes",
            showDialogPayments: false,
            showDialogOptions: false,
            showDialogGenerate: false,
            showDialogDispatch: false,
            saleNotesNewId: null,
            recordId: null,
            statusDispatch: null,
            columns: {
                due_date: {
                    title: "Fecha de Vencimiento",
                    visible: false
                },
                exchange_rate_sale: {
                    title: "Tipo de cambio",
                    visible: false
                },
                total_free: {
                    title: "T.Gratuito",
                    visible: false
                },
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
                total_taxed: {
                    title: "T.Gravado",
                    visible: false
                },
                total_igv: {
                    title: "T.IGV",
                    visible: false
                },
                paid: {
                    title: "Estado de Pago",
                    visible: false
                },
                type_period: {
                    title: "Tipo Periodo",
                    visible: true
                },
                quantity_period: {
                    title: "Cantidad Periodo",
                    visible: true
                },
                license_plate: {
                    title: "Placa",
                    visible: true
                },
                total_paid: {
                    title: "Pagado",
                    visible: false
                },
                total_pending_paid: {
                    title: "Por pagar",
                    visible: false
                },
                seller_name: {
                    title: "Vendedor",
                    visible: false
                },
                recurrence: {
                    title: "Recurrencia",
                    visible: false
                },
                region: {
                    title: "Region",
                    visible: false
                },
                date_payment: {
                    title: "Fecha de pago",
                    visible: false
                },
                dispatch_status: {
                    title: "Estado de despacho",
                    visible: false
                }
            }
            // showDialogDeleteRelationInvoice: false,
            // dataDeleteRelation: {
            //     documents: {},
            //     id: ''
            // }
        };
    },
    created() {
        this.loadConfiguration();
        this.$store.commit("setConfiguration", this.configuration);
        this.getColumnsToShow();
    },
    filters: {
        period(name) {
            let res = "";
            switch (name) {
                case "month":
                    res = "Mensual";
                    break;
                case "year":
                    res = "Anual";
                    break;
                default:
                    break;
            }

            return res;
        }
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            return moment(date).format("DD-MM-YYYY");
        },
        ...mapActions(["loadConfiguration"]),
        getColumnsToShow(updated) {
            this.$http
                .post("/validate_columns", {
                    columns: this.columns,
                    report: "sale_notes_index", // Nombre del reporte.
                    updated: updated !== undefined
                })
                .then(response => {
                    if (updated === undefined) {
                        let currentCols = response.data.columns;
                        if (currentCols !== undefined) {
                            this.columns = currentCols;
                        }
                    }
                })
                .catch(error => {
                    console.error(error);
                });
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
        onOpenModalGenerateCPE() {
            this.showModalGenerateCPE = true;
        },
        onOpenModalMigrateNv() {
            this.showMigrateNv = true;
        },
        clickDownload(external_id) {
            window.open(
                `/sale-notes/downloadExternal/${external_id}`,
                "_blank"
            );
        },
        clickOptions(recordId) {
            this.saleNotesNewId = recordId;
            this.showDialogOptions = true;
        },
        sendToServer(recordId) {
            this.$http
                .post("/sale-notes/UpToOther", { sale_note_id: recordId })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (
                        error.response !== undefined &&
                        error.response.status !== undefined &&
                        error.response.status.errors !== undefined &&
                        error.response.status === 422
                    ) {
                        this.errors = error.response.data.errors;
                    } else {
                        console.log(error);
                    }
                })
                .then(() => {});
        },
        clickGenerate(recordId) {
            this.recordId = recordId;
            this.showDialogGenerate = true;
        },
        clickPayment(recordId) {
            this.recordId = recordId;
            this.showDialogPayments = true;
        },
        clickCreate(id = "") {
            location.href = `/${this.resource}/create/${id}`;
        },
        changeConcurrency(row) {
            this.$http
                .post(`/${this.resource}/enabled-concurrency`, row)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        console.log(error);
                    }
                })
                .then(() => {});
        },
        clickVoided(id) {
            this.anular(`/${this.resource}/anulate/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickDispatchStatus(recordId, status) {
            this.recordId = recordId;
            this.showDialogDispatch = true;
            this.statusDispatch = status;
        },

        // deleteRelationInvoice(saleNote) {
        //     this.dataDeleteRelation.documents = saleNote.documents
        //     this.dataDeleteRelation.id = saleNote.id
        //     this.showDialogDeleteRelationInvoice = true
        // },
        sendDeleteRelationInvoice(id) {
            this.$http
                .post(`${this.resource}/delete-relation-invoice`, { id })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(
                            "Se ha eliminado el comprobante relacionado correctamente."
                        );
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error("No se guardaron los cambios");
                    }
                })
                .catch(error => {
                    console.log(error);
                });
            this.$eventHub.$emit("reloadData");
        }
    }
};
</script>

<style>
/* Estilos para el dropdown de Element UI en la tabla */
.el-dropdown-selfdefine {
    border: none;
    padding: 0.375rem 0.75rem;
    background-color: #f8f9fa;
    color: #6c757d;
}

.el-dropdown-selfdefine:hover,
.el-dropdown-selfdefine:focus {
    background-color: #e9ecef;
    color: #495057;
}
</style>
