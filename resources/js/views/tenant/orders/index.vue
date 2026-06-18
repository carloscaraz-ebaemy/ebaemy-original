<template>
    <div class="orders" v-loading="loading_submit">
        <div class="page-header pe-0">
            <h2>
                <a href="/orders">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M17 17h-11v-14h-2" />
                        <path d="M6 5l14 1l-1 7h-13" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Pedidos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right"></div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading" width="100%">
                        <th>Codigo de Pedido</th>
                        <th>Cliente</th>
                        <th class="text-center">Detalle Productos</th>
                        <th class="text-end">Total</th>
                        <th>Fecha Emision</th>
                        <th>Medio Pago</th>
                        <th>Estatus del Pedido</th>
                        <th class="text-center">Documento</th>
                        <th class="text-end">Opciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ row.order_id }}</td>
                        <td>{{ row.customer }}</td>
                        <td class="text-center">
                            <template>
                                <el-popover
                                    placement="right"
                                    width="540"
                                    trigger="click"
                                >
                                    <el-table
                                        style="width: 100%"
                                        :data="row.items"
                                    >
                                        <el-table-column
                                            width="150"
                                            property="description"
                                            label="Nombre"
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            property="cantidad"
                                            label="Cant."
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            label="Precio"
                                        >
                                            <template slot-scope="scope">
                                                <span
                                                    >{{
                                                        scope.row
                                                            .currency_type_id ===
                                                        "USD"
                                                            ? "$"
                                                            : "S/"
                                                    }}
                                                    {{
                                                        Number(
                                                            scope.row
                                                                .sale_unit_price
                                                        ).toFixed(2)
                                                    }}</span
                                                >
                                            </template>
                                        </el-table-column>
                                        <el-table-column
                                            width="90"
                                            property="exchange_rate_sale"
                                            label="T/C"
                                        ></el-table-column>
                                        <el-table-column
                                            width="90"
                                            label="Subtotal"
                                        >
                                            <template slot-scope="scope">
                                                <span
                                                    >S/
                                                    {{
                                                        subtotal(scope.row)
                                                    }}</span
                                                >
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                    <table
                                        class="el-table--small el-table--fit el-table"
                                    >
                                        <thead class="has-gutter">
                                            <th colspan="2" class="text-center">
                                                Contacto
                                            </th>
                                        </thead>
                                        <tbody>
                                            <tr class="el-table tr">
                                                <td class="el-table--small td">
                                                    TELÉFONO:
                                                    {{ row.customer_telefono }}
                                                </td>
                                            </tr>
                                            <tr class="el-table tr">
                                                <td class="el-table--small td">
                                                    DIRECCIÓN:
                                                    {{ row.customer_direccion }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <el-button
                                        slot="reference"
                                        icon="el-icon-zoom-in"
                                    ></el-button>
                                </el-popover>
                            </template>
                        </td>
                        <td class="text-end">S/ {{ row.total }}</td>
                        <td>{{ formatDate(row.created_at) }}</td>
                        <td>
                            <span
                                v-if="isMarketplace(row)"
                                class="mp-pay-badge"
                                >{{ marketplaceLabel(row) }}</span
                            >
                            <template v-else>{{ row.reference_payment }}</template>
                        </td>
                        <td>
                            <div class="ord-status-cell">
                                <template v-if="row.status_order_id == 5">
                                    <span class="ord-badge-cancel"
                                        ><i class="fas fa-times-circle"></i>
                                        Cancelado</span
                                    >
                                </template>
                                <template v-else>
                                    <div class="ord-steps">
                                        <template
                                            v-for="(st, i) in statusSteps"
                                        >
                                            <span
                                                class="ord-step"
                                                :class="stepClass(row.status_order_id, i)"
                                                :title="st.label"
                                                :key="'s' + i"
                                            >
                                                <i
                                                    v-if="stepDone(row.status_order_id, i)"
                                                    class="fas fa-check"
                                                ></i>
                                                <template v-else>{{
                                                    i + 1
                                                }}</template>
                                            </span>
                                            <span
                                                v-if="i < statusSteps.length - 1"
                                                class="ord-sep"
                                                :class="{ done: stepDone(row.status_order_id, i + 1) }"
                                                :key="'l' + i"
                                            ></span>
                                        </template>
                                    </div>
                                    <div class="ord-step-label">
                                        {{ statusLabel(row.status_order_id) }}
                                    </div>
                                </template>
                                <div class="ord-status-editbar">
                                    <template
                                        v-if="editingStatusId === row.id"
                                    >
                                        <el-select
                                            v-model="row.status_order_id"
                                            size="mini"
                                            class="ord-status-edit"
                                            placeholder="Cambiar estado"
                                            @change="updateStatus(row)"
                                        >
                                            <el-option
                                                v-for="item in options"
                                                :key="item.id"
                                                :label="item.description"
                                                :value="item.id"
                                            ></el-option>
                                        </el-select>
                                        <button
                                            class="ord-lock-btn cancel"
                                            title="Cancelar"
                                            @click="editingStatusId = null"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </template>
                                    <button
                                        v-else
                                        class="ord-lock-btn"
                                        title="Desbloquear para cambiar el estado"
                                        @click="editingStatusId = row.id"
                                    >
                                        <i class="fas fa-lock"></i> Cambiar
                                    </button>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span
                                v-if="row.number_document"
                                class="ord-doc-badge ord-doc-ok"
                                >{{ row.number_document }}</span
                            >
                            <span
                                v-else-if="row.document_type_id == '80' && row.sale_note_number_full"
                                >{{ row.sale_note_number_full }}</span
                            >
                            <span
                                v-else-if="row.mp_invoice_state === 'external'"
                                class="ord-doc-badge ord-doc-ext"
                                title="Boleta emitida fuera de EBAEMY"
                                >Boleta externa</span
                            >
                            <span
                                v-else-if="row.mp_invoice_state === 'pending'"
                                class="ord-doc-badge ord-doc-pend"
                                title="Pedido de marketplace sin boleta"
                                >Sin boleta</span
                            >
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="text-end">
                            <template v-if="row.document_type_id == '80'">
                                <el-button
                                    v-if="row.sale_note_id"
                                    class="submit"
                                    type="success"
                                    icon="el-icon-tickets"
                                    @click.prevent="
                                        clickOptions(row.sale_note_id)
                                    "
                                ></el-button>
                            </template>
                            <template v-else>
                                <el-button
                                    v-if="row.document_external_id"
                                    class="submit"
                                    type="success"
                                    icon="el-icon-tickets"
                                    @click.prevent="
                                        clickDownload(row.document_external_id)
                                    "
                                ></el-button>
                            </template>
                            <el-button
                                v-if="canDownloadLabel(row)"
                                size="mini"
                                icon="el-icon-printer"
                                title="Hoja de despacho de Saga"
                                @click.prevent="downloadLabel(row)"
                                >Rótulo</el-button
                            >
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>

        <el-dialog
            title="Stock en almacén"
            width="40%"
            :visible="showDialog"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            append-to-body
            :show-close="false"
        >
            <div class="form-body">
                <div class="row">
                    <div class="col-lg-12 col-md-12 table-responsive">
                        <table width="100%" class="table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Almacén</th>
                                </tr>
                            </thead>
                            <tbody
                                v-for="(rowProduct,
                                indexProduct) in totalProduct"
                                :key="indexProduct"
                                width="100%"
                            >
                                <tr>
                                    <td>
                                        {{ record.items[indexProduct].name }}
                                    </td>
                                    <td>
                                        <el-select
                                            v-model="form[rowProduct]"
                                            placeholder="Almacenes"
                                        >
                                            <el-option
                                                v-if="
                                                    rowProduct === item.item_id
                                                "
                                                v-for="item in warehouses"
                                                :key="item.id"
                                                :label="
                                                    item.warehouse +
                                                        ' - ' +
                                                        'Stock -> ' +
                                                        Math.trunc(item.stock)
                                                "
                                                :value="item.id"
                                                :disabled="
                                                    optionDisable(
                                                        item.item_id,
                                                        item.stock
                                                    )
                                                "
                                            ></el-option>
                                        </el-select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button class="second-buton" @click="close"
                    >Cerrar</el-button
                >
                <el-button type="primary" @click="save">Guardar</el-button>
            </div>
        </el-dialog>

        <options-form
            :showDialog.sync="showDialogOptions"
            :recordId="documentNewId"
            :statusDocument="statusDocument"
            :resource="resource_options"
        ></options-form>

        <document-form
            :order_id="order_id"
            :user="user"
            :document_types="document_types"
            ref="document_form"
        >
        </document-form>

        <sale-note-form
            :showDialog.sync="showDialogSaleNote"
            :orderId="order_id"
            :dataSaleNote="dataSaleNote"
        >
        </sale-note-form>
    </div>
</template>
<style>
.mp-pay-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: #eef2ff;
    color: #3730a3;
    white-space: nowrap;
}
/* Stepper de estado del pedido */
.ord-status-cell {
    min-width: 170px;
}
.ord-steps {
    display: flex;
    align-items: center;
}
.ord-step {
    flex: 0 0 auto;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    border: 1.5px solid #cbd5e1;
    background: #fff;
    color: #94a3b8;
}
.ord-step.done {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}
.ord-step.current {
    border-color: #4f46e5;
    color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}
.ord-sep {
    flex: 1 1 auto;
    height: 2px;
    min-width: 8px;
    background: #e2e8f0;
    margin: 0 2px;
}
.ord-sep.done {
    background: #16a34a;
}
.ord-step-label {
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin: 4px 0 2px;
}
.ord-badge-cancel {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: #fee2e2;
    color: #b91c1c;
}
.ord-badge-cancel i {
    margin-right: 3px;
}
.ord-status-edit {
    width: 100%;
    margin-top: 2px;
}
.ord-doc-badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}
.ord-doc-ok {
    background: #dcfce7;
    color: #166534;
}
.ord-doc-ext {
    background: #e0e7ff;
    color: #3730a3;
}
.ord-doc-pend {
    background: #fef3c7;
    color: #92400e;
}
.ord-status-editbar {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}
.ord-lock-btn {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.ord-lock-btn:hover {
    border-color: #4f46e5;
    color: #4f46e5;
}
.ord-lock-btn i {
    margin-right: 3px;
}
.ord-lock-btn.cancel {
    color: #b91c1c;
}
.ord-lock-btn.cancel i {
    margin-right: 0;
}
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0px;
        & .btn-filter-content,
        .btn-container-mobile {
            display: flex;
            align-items: center;
            justify-content: start;
        }
    }
}
</style>
<script>
import DataTable from "../../../components/DataTable.vue";
import queryString from "query-string";
import OptionsForm from "../pos/partials/options.vue";
import DocumentForm from "./partials/document_form.vue";
import SaleNoteForm from "./partials/sale_note_form.vue";

export default {
    props: ["user"],

    components: { DataTable, OptionsForm, DocumentForm, SaleNoteForm },
    data() {
        return {
            showDialog: false,
            showImportDialog: false,
            showImageDetail: false,
            resource: "orders",
            recordId: null,
            options: [],
            // Id del pedido cuyo estado está desbloqueado para editar (candado).
            editingStatusId: null,
            // Ruta lineal del pedido para el stepper (Cancelado=5 va aparte).
            statusSteps: [
                { id: 1, label: "Pendiente" },
                { id: 2, label: "Pago verificado" },
                { id: 3, label: "En preparación" },
                { id: 4, label: "Despachado" },
                { id: 6, label: "Entregado" },
            ],
            warehouses: [],
            estableciment_id: "",
            totalProduct: [], // items_id
            form: [],
            record: "", // record orders
            stocks: "",
            showDialogOptions: false,
            documentNewId: null,
            statusDocument: {},
            resource_options: null,
            loading_submit: false,
            document_types: [],
            order_id: null,
            dataSaleNote: {},
            showDialogSaleNote: false
        };
    },
    async created() {
        this.$http.get(`/statusOrder/records`).then(response => {
            this.options = response.data;
        });
        this.events();
    },
    computed: {},
    methods: {
        isMarketplace(row) {
            const ref = (row.reference_payment || "").toUpperCase();
            return ref.startsWith("MARKETPLACE") || row.channel_type === "marketplace";
        },
        marketplaceLabel(row) {
            const ref = (row.reference_payment || "").toUpperCase();
            if (ref.indexOf("FALABELLA") !== -1) return "Saga Falabella";
            if (ref.indexOf("MERCADOLIBRE") !== -1) return "MercadoLibre";
            if (ref.indexOf("TIKTOK") !== -1) return "TikTok Shop";
            if (ref.indexOf("META") !== -1) return "Meta";
            return row.channel_name || "Marketplace";
        },
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        },
        clickOptions(recordId) {
            this.documentNewId = recordId;
            this.statusDocument.send = "";
            this.resource_options = "sale-notes";
            this.showDialogOptions = true;
        },
        async clickDownload(row) {
            await this.$http
                .get(`/documents/search/externalId/${row}`)
                .then(response => {
                    this.documentNewId = response.data.id;
                });
            this.statusDocument.send = "";
            this.resource_options = "documents";
            this.showDialogOptions = true;
        },
        canDownloadLabel(row) {
            // Solo pedidos de Saga ya despachables tienen rótulo en Saga.
            return (
                row.mp_order_id &&
                row.mp_channel_id &&
                ["ready_to_ship", "shipped", "delivered"].indexOf(
                    row.mp_status
                ) !== -1
            );
        },
        downloadLabel(row) {
            window.open(
                "/ecommerce/marketplace/channels/" +
                    row.mp_channel_id +
                    "/orders/" +
                    row.mp_order_id +
                    "/document/shippingLabel",
                "_blank"
            );
        },
        statusIndex(statusId) {
            // Posición en la ruta lineal; -1 si no está (ej. Cancelado=5).
            return this.statusSteps.findIndex(function (s) {
                return String(s.id) === String(statusId);
            });
        },
        stepDone(statusId, i) {
            var cur = this.statusIndex(statusId);
            return cur >= 0 && i <= cur;
        },
        stepClass(statusId, i) {
            var cur = this.statusIndex(statusId);
            if (cur < 0) return "pending";
            if (i < cur) return "done";
            if (i === cur) return "current";
            return "pending";
        },
        statusLabel(statusId) {
            var found = this.options.find(function (o) {
                return String(o.id) === String(statusId);
            });
            if (found) return found.description;
            var step = this.statusSteps.find(function (s) {
                return String(s.id) === String(statusId);
            });
            return step ? step.label : "";
        },
        subtotal(item) {
            var subtotal;
            if (item.currency_type_id === "USD") {
                subtotal = Number(
                    item.cantidad *
                        item.exchange_rate_sale *
                        parseFloat(item.sale_unit_price)
                ).toFixed(2);
                if (isNaN(subtotal)) {
                    return "-";
                } else {
                    return subtotal;
                }
            } else {
                return parseFloat(item.cantidad * item.sale_unit_price);
            }
        },
        optionDisable(product, stock) {
            for (var i = 0; i < this.record.items.length; i++) {
                if (product === this.record.items[i].id) {
                    return stock >= this.record.items[i].cantidad
                        ? false
                        : true;
                }
            }
        },
        openDialogSaleNote(sale_note) {
            this.dataSaleNote = sale_note;
            this.showDialogSaleNote = true;
        },
        async updateStatus(record) {
            this.record = record;
            // Re-bloquea (candado) tras intentar el cambio.
            this.editingStatusId = null;

            if (record.status_order_id === 2) {
                this.order_id = record.id;

                if (record.purchase.codigo_tipo_documento == "80") {
                    if (record.has_sale_note)
                        return this.$message.success(
                            "Ya existe una nota de venta"
                        );
                    this.openDialogSaleNote(record.purchase);
                } else {
                    if (record.document_external_id) {
                        return this.$message.success(
                            "Ya existe un comprobante."
                        );
                    }
                    this.$refs.document_form.sendPreview(record.purchase);
                }
            } else if (record.status_order_id === 3) {
                this.totalProduct = await this.products(record);
                await this.$http
                    .post(`/orders/warehouse`, { item_id: this.totalProduct })
                    .then(response => {
                        this.warehouses = response.data.data;
                        this.showDialog = true;
                    });
                return;
            } else {
                this.saveUpdateStatus();
            }
        },
        saveUpdateStatus() {
            this.$http
                .post(`/statusOrder/update`, { record: this.record })
                .then(response => {
                    this.$message.success(response.data.message);
                });
        },
        async save() {
            var save = [];

            for (var i = 0; i < this.record.items.length; i++) {
                if (this.totalProduct[i] === this.record.items[i].id) {
                    save.push({
                        id: this.form[this.totalProduct[i]],
                        cantidad: this.record.items[i].cantidad
                    });
                }
            }

            await this.$http
                .post(`/statusOrder/update`, {
                    record: this.record,
                    discount: save
                })
                .then(response => {
                    this.$message.success(response.data.message);
                    this.close();
                });
        },
        close() {
            this.form = [];
            this.showDialog = false;
            this.recoard = "";
        },
        products(products) {
            let listProduct = [];

            for (var i = 0; i <= products.items.length - 1; i++) {
                listProduct.push(products.items[i].id);
            }
            return listProduct;
        },
        async events() {
            await this.$eventHub.$on("cancelSale", () => {
                this.showDialogOptions = false;
            });
        },

        getHeaderConfig() {
            let token = this.user.api_token;
            let httpConfig = {
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${token}`
                }
            };
            return httpConfig;
        }
    }
};
</script>
