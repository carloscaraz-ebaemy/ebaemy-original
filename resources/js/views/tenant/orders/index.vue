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
                <div class="ord-kpis">
                    <div class="ord-kpi">
                        <div class="ord-kpi-label">Por despachar</div>
                        <div class="ord-kpi-val">{{ chipCounts.todispatch || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-warn">
                        <div class="ord-kpi-label">Sin boleta</div>
                        <div class="ord-kpi-val">{{ chipCounts.no_invoice || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-ok">
                        <div class="ord-kpi-label">Entregados</div>
                        <div class="ord-kpi-val">{{ chipCounts.delivered || 0 }}</div>
                    </div>
                    <div class="ord-kpi ord-kpi-rev">
                        <div class="ord-kpi-label">Vendido del mes</div>
                        <div class="ord-kpi-val">
                            S/ {{ formatMoney(stats.revenueMonth) }}
                        </div>
                    </div>
                </div>
                <div v-if="countsError" class="ord-counts-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    No se pudieron cargar los contadores: {{ countsError }}
                    <button class="ord-counts-retry" @click="loadChipCounts">Reintentar</button>
                </div>
                <div class="ord-chips">
                    <button
                        v-for="chip in orderChips"
                        :key="chip.key"
                        class="ord-chip"
                        :class="{ active: mpFilter === chip.key }"
                        @click="applyMpFilter(chip.key)"
                    >
                        {{ chip.label }}
                        <span
                            v-if="chipCounts[chip.key] !== undefined"
                            class="ord-chip-n"
                            >{{ chipCounts[chip.key] }}</span
                        >
                    </button>
                </div>
                <!-- Barra de filtros. Antes era una caja titulada "Gestión de
                     pedidos para facturar" (de Saga) con los controles
                     logísticos metidos dentro: se leían como si filtraran la
                     facturación. Ahora cada control lleva su etiqueta y van en
                     una rejilla que se apila sola en móvil. -->
                <div class="ord-filters">
                    <div class="ord-filter">
                        <label>Periodo</label>
                        <el-select v-model="dateRange" @change="applyDateFilters">
                            <el-option
                                v-for="opt in rangeOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter">
                        <label>Fecha a considerar</label>
                        <el-select v-model="dateType" @change="applyDateFilters">
                            <el-option
                                v-for="opt in dateTypeOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <!-- El selector de fechas concretas solo aparece cuando el
                         periodo es "personalizado": si no, compite con el
                         rango rápido y no se sabe cuál manda. -->
                    <div v-if="dateRange === 'custom'" class="ord-filter ord-filter-wide">
                        <label>Desde / hasta</label>
                        <el-date-picker
                            v-model="invoiceDateRange"
                            type="daterange"
                            range-separator="hasta"
                            start-placeholder="Desde"
                            end-placeholder="Hasta"
                            value-format="yyyy-MM-dd"
                            :clearable="true"
                            @change="applyDateFilters"
                        ></el-date-picker>
                    </div>

                    <div class="ord-filter">
                        <label>Modalidad de entrega</label>
                        <el-select v-model="deliveryTypeFilter" @change="applyLogisticFilters">
                            <el-option
                                v-for="opt in deliveryTypeOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter">
                        <label>Antigüedad</label>
                        <el-select v-model="agingFilter" @change="applyLogisticFilters">
                            <el-option
                                v-for="opt in agingOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            ></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter">
                        <label>Origen del pedido</label>
                        <el-select v-model="orderSource" @change="applyOrderSource">
                            <el-option label="Todos los pedidos" value="all"></el-option>
                            <el-option label="Solo Saga Falabella" value="saga"></el-option>
                            <el-option label="Otros pedidos" value="other"></el-option>
                        </el-select>
                    </div>

                    <div class="ord-filter ord-filter-reset">
                        <button
                            v-if="hasActiveFilters"
                            class="ord-filter-clear"
                            @click="clearFilters"
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>

                <div v-if="selectedIds.length" class="ord-bulkbar">
                    <span class="ord-bulk-count"
                        >{{ selectedIds.length }} seleccionado(s)</span
                    >
                    <button class="ord-bulk-btn" @click="bulkMarkInvoiced">
                        <i class="fas fa-check"></i> Marcar boleta (externa)
                    </button>
                    <button class="ord-bulk-btn" @click="bulkDownloadLabels">
                        <i class="fas fa-printer"></i> Descargar rótulos
                    </button>
                    <!-- Lote de impresión desde los pedidos seleccionados: es
                         la operación que antes obligaba a saltar al módulo de
                         Registro de Envíos. -->
                    <button class="ord-bulk-btn" @click="bulkCreatePrintBatch">
                        <i class="fas fa-layer-group"></i> Crear lote de impresión
                    </button>
                    <button class="ord-bulk-btn ghost" @click="selectedIds = []">
                        Limpiar
                    </button>
                </div>
                <data-table
                    ref="ordersTable"
                    :resource="resource"
                    @records-changed="onRecordsChanged"
                >
                    <tr slot="heading" width="100%">
                        <th class="text-center" style="width: 36px">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleAll($event)"
                            />
                        </th>
                        <th>Codigo de Pedido</th>
                        <th>Cliente</th>
                        <th class="text-center">Detalle Productos</th>
                        <th class="text-end">Total</th>
                        <th>Fecha del pedido</th>
                        <th>Medio Pago</th>
                        <th>Estatus del Pedido</th>
                        <th>Entrega</th>
                        <th class="text-center">Documento</th>
                        <th class="text-end">Opciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <td class="text-center">
                            <input
                                type="checkbox"
                                :value="row.id"
                                v-model="selectedIds"
                            />
                        </td>
                        <td data-label="Código">{{ row.order_id }}</td>
                        <td data-label="Cliente">
                            {{ row.customer }}
                            <small v-if="row.customer_telefono" class="ord-cust-contact">
                                <i class="fas fa-phone"></i> {{ row.customer_telefono }}
                            </small>
                            <small v-if="row.customer_direccion" class="ord-cust-contact">
                                <i class="fas fa-map-marker-alt"></i> {{ row.customer_direccion }}
                            </small>
                            <!-- El documento decide a nombre de quien sale la
                                 boleta. Sin verlo, un pedido que saldria como
                                 "00000000" pasa desapercibido. -->
                            <small
                                v-if="row.customer_doc"
                                class="ord-cust-doc"
                                >{{ row.customer_doc }}</small
                            >
                            <small
                                v-else-if="row.mp_order_id"
                                class="ord-cust-doc ord-cust-doc-none"
                                title="La boleta saldria como Cliente Final 00000000"
                                >sin documento</small
                            >
                        </td>
                        <td class="text-center" data-label="Detalle">
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
                        <td class="text-end" data-label="Total">
                            <template v-if="pagoEnElEnvio(row)">
                                <!-- El importe del encargo vive en el envío y se
                                     lee DERIVADO: el operador puede cargarlo
                                     después del alta, y una copia en el pedido
                                     se quedaría vieja sin avisar. -->
                                <template v-if="row.shipment.has_amount">
                                    S/ {{ formatMoney(row.shipment.amount_to_collect) }}
                                    <div
                                        v-if="row.shipment.pending_total > 0"
                                        class="ord-pay-pend"
                                        :title="'Cobrado S/ ' + formatMoney(row.shipment.paid_total)"
                                    >
                                        debe S/ {{ formatMoney(row.shipment.pending_total) }}
                                    </div>
                                    <div v-else class="ord-pay-ok">pagado</div>
                                </template>
                                <span v-else class="text-muted" title="Nadie cargó el monto del encargo">
                                    sin monto
                                </span>
                            </template>
                            <template v-else>S/ {{ row.total }}</template>
                        </td>
                        <td data-label="Fecha del pedido">{{ formatDate(row.created_at) }}</td>
                        <td data-label="Medio pago">
                            <span
                                v-if="isMarketplace(row)"
                                class="mp-pay-badge"
                                >{{ marketplaceLabel(row) }}</span
                            >
                            <template v-else-if="row.reference_payment">{{
                                row.reference_payment
                            }}</template>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td data-label="Estado">
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
                                <div v-if="!isMarketplace(row)" class="ord-status-editbar">
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
                                <small v-else class="ord-saga-status">
                                    Sincronizado desde Saga
                                </small>
                            </div>
                        </td>
                        <!-- Entrega: modalidad, estado logístico y semáforo de
                             antigüedad. Es la columna que hace innecesario
                             abrir el módulo de Registro de Envíos. -->
                        <td data-label="Entrega">
                            <template v-if="row.shipment">
                                <div class="ord-ship-cell">
                                    <span
                                        class="ord-ship-tag"
                                        :style="{
                                            color: row.shipment.delivery_meta.color,
                                            background: row.shipment.delivery_meta.bg,
                                            borderColor: row.shipment.delivery_meta.line
                                        }"
                                        >{{ row.shipment.delivery_short }}</span
                                    >
                                    <span
                                        v-if="row.shipment.aging_meta"
                                        class="ord-ship-dot"
                                        :style="{ background: row.shipment.aging_meta.color }"
                                        :title="
                                            row.shipment.aging_meta.label +
                                            ' · ' +
                                            row.shipment.aging_days +
                                            ' día(s) hábil(es)'
                                        "
                                    ></span>
                                </div>
                                <div class="ord-ship-sub">
                                    {{ row.shipment.status_label }}
                                </div>
                                <div class="ord-ship-dest" :title="shipmentDestination(row)">
                                    {{ shipmentDestination(row) }}
                                </div>
                                <div
                                    v-if="row.shipment.tracking_number"
                                    class="ord-ship-track"
                                >
                                    {{ row.shipment.tracking_number }}
                                </div>
                                <a
                                    class="ord-ship-link"
                                    href="#"
                                    @click.prevent="openShipment(row)"
                                    >Ver envío</a
                                >
                            </template>
                            <template v-else>
                                <button
                                    class="ord-ship-cta"
                                    @click="openShipment(row)"
                                >
                                    <i class="fas fa-truck"></i> Configurar envío
                                </button>
                            </template>
                        </td>
                        <td class="text-center" data-label="Documento">
                            <span
                                v-if="row.mp_invoice_state === 'alert'"
                                class="ord-doc-badge ord-doc-alert"
                                title="Boleta emitida y el pedido fue devuelto/cancelado en Saga: corresponde Nota de Crédito"
                                >⚠ {{ row.number_document || "Facturado" }} · devuelto</span
                            >
                            <span
                                v-else-if="row.number_document"
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
                        <td class="text-end" data-label="Opciones">
                            <!-- Todas las acciones en un menu: sueltas no
                                 caben, y con el tiempo se fueron sumando
                                 (boleta, rotulo, subir a Saga, PDF...). -->
                            <el-dropdown
                                trigger="click"
                                @command="runAction($event, row)"
                            >
                                <el-button size="mini" class="ord-actions-btn">
                                    <i class="fas fa-ellipsis-v"></i>
                                </el-button>
                                <el-dropdown-menu slot="dropdown">
                                    <el-dropdown-item
                                        v-if="canGenerateInvoice(row)"
                                        command="invoice"
                                    >
                                        <i class="el-icon-document"></i>
                                        {{
                                            invoiceIsRisky(row)
                                                ? "Generar boleta ⚠ (sin entrega confirmada)"
                                                : "Generar boleta"
                                        }}
                                    </el-dropdown-item>

                                    <!-- Emitida aqui pero todavia no esta en
                                         Saga: es el paso que falta y antes no
                                         se veia por ningun lado. -->
                                    <el-dropdown-item command="payments">
                                        <i class="el-icon-wallet"></i>
                                        Pagos del pedido
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="canUploadInvoice(row)"
                                        command="upload"
                                    >
                                        <i class="el-icon-upload2"></i>
                                        Subir boleta a Saga
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="isSagaOrder(row) && row.mp_invoice_state === 'pending'"
                                        command="markExternal"
                                    >
                                        <i class="el-icon-check"></i>
                                        Marcar boleta hecha en Saga
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="row.document_type_id == '80' && row.sale_note_id"
                                        command="saleNote"
                                        divided
                                    >
                                        <i class="el-icon-tickets"></i>
                                        Nota de venta / convertir
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="row.document_external_id"
                                        command="document"
                                        divided
                                    >
                                        <i class="el-icon-tickets"></i>
                                        Ver comprobante
                                    </el-dropdown-item>

                                    <el-dropdown-item
                                        v-if="canDownloadLabel(row)"
                                        command="label"
                                    >
                                        <i class="el-icon-printer"></i>
                                        Rótulo de Saga
                                    </el-dropdown-item>

                                    <!-- Enlace para que el CLIENTE complete sus
                                         datos de entrega. Reemplaza al
                                         formulario público suelto: llega al
                                         pedido, no crea uno nuevo. -->
                                    <el-dropdown-item command="shippingLink" divided>
                                        <i class="el-icon-link"></i>
                                        Copiar enlace de datos de envío
                                    </el-dropdown-item>

                                    <el-dropdown-item command="timeline">
                                        <i class="el-icon-time"></i>
                                        Ver historial
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>

        <!-- Envío del pedido: el detalle logístico vive DENTRO del pedido. -->
        <shipment-form
            :visible.sync="showShipmentDialog"
            :order-id="shipmentOrderId"
            @saved="onShipmentSaved"
        ></shipment-form>

        <!-- Pagos del pedido: mismo panel que usa Nota de Venta. -->
        <record-payments
            v-if="showPaymentsDialog"
            :showDialog.sync="showPaymentsDialog"
            :recordId="paymentsOrderId"
            resource="order_payments"
            foreignKey="order_id"
            fileType="orders"
            title="Pagos del pedido"
            @updated="onPaymentsUpdated"
        ></record-payments>

        <!-- Historial: estados del pedido + bitácora del envío + impresiones. -->
        <order-timeline
            :visible.sync="showTimelineDialog"
            :order-id="timelineOrderId"
        ></order-timeline>

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
/* Saldo del encargo logistico. Su dinero vive en el envio, asi que la celda
   de Total muestra el importe a cobrar y, debajo, lo que falta. En rojo solo
   cuando queda deuda: es la unica parte que pide accion. */
.ord-pay-pend {
    font-size: 11.5px;
    font-weight: 600;
    color: #b91c1c;
    white-space: nowrap;
}
.ord-pay-ok {
    font-size: 11.5px;
    font-weight: 600;
    color: #166534;
    white-space: nowrap;
}
.ord-actions-btn {
    padding: 5px 9px;
}
.ord-cust-doc {
    display: block;
    font-size: 11px;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.ord-cust-doc-none {
    color: #b45309;
    font-weight: 600;
}
.ord-cust-contact {
    display: block;
    color: #64748b;
    font-size: 11px;
    line-height: 1.35;
    margin-top: 2px;
}
.ord-cust-contact i { width: 13px; }
.ord-doc-alert {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-weight: 700;
}
.ord-doc-ext {
    background: #e0e7ff;
    color: #3730a3;
}
.ord-doc-pend {
    background: #fef3c7;
    color: #92400e;
}
/* ── Columna "Entrega" ───────────────────────────────────────────────── */
.ord-ship-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ord-ship-tag {
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 1px 8px;
    font-size: 11px;
    font-weight: 700;
}
/* Punto del semáforo de antigüedad: el color lo decide PHP, no el Vue. */
.ord-ship-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex: 0 0 auto;
}
.ord-ship-sub {
    font-size: 12px;
    color: #334155;
    margin-top: 2px;
}
.ord-ship-dest,
.ord-ship-track {
    font-size: 11px;
    color: #64748b;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ord-ship-track {
    font-family: monospace;
}
.ord-ship-link {
    font-size: 11px;
    color: #4f46e5;
}
.ord-ship-cta {
    border: 1px dashed #c7d2fe;
    background: #eef2ff;
    color: #4338ca;
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.ord-ship-cta:hover {
    background: #e0e7ff;
}

.ord-counts-error {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 13px;
    margin-bottom: 10px;
}
.ord-counts-retry {
    margin-left: auto;
    border: 1px solid #fecaca;
    background: #fff;
    color: #b91c1c;
    border-radius: 8px;
    padding: 3px 10px;
    font-size: 12px;
    cursor: pointer;
}
.ord-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
}
.ord-chip {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 999px;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}
.ord-chip:hover {
    border-color: #c7d2fe;
    color: #4f46e5;
}
.ord-chip.active {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #fff;
}
.ord-chip-n {
    display: inline-block;
    min-width: 18px;
    text-align: center;
    background: rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    padding: 0 6px;
    font-size: 11px;
    margin-left: 4px;
}
.ord-chip.active .ord-chip-n {
    background: rgba(255, 255, 255, 0.25);
}
.ord-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px 12px;
    padding: 12px;
    margin-bottom: 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fbfcfe;
}
.ord-filter {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}
/* Cada control lleva su etiqueta: sin ella, cuatro desplegables seguidos no
   dicen qué filtran y la barra se lee como piezas sueltas. */
.ord-filter label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin: 0;
}
.ord-filter .el-select,
.ord-filter .el-date-editor {
    width: 100%;
}
.ord-filter-wide {
    grid-column: span 2;
}
.ord-filter-reset {
    justify-content: flex-end;
}
.ord-filter-clear {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.ord-filter-clear:hover {
    border-color: #c7d2fe;
    color: #4f46e5;
}

@media (max-width: 640px) {
    .ord-filters {
        grid-template-columns: 1fr;
    }
    .ord-filter-wide {
        grid-column: span 1;
    }
}
/* KPIs */
.ord-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
.ord-kpi {
    border: 1px solid #eef2f7;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.ord-kpi-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}
.ord-kpi-val {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 2px;
}
.ord-kpi-warn .ord-kpi-val {
    color: #b45309;
}
.ord-kpi-ok .ord-kpi-val {
    color: #166534;
}
.ord-kpi-rev .ord-kpi-val {
    color: #4f46e5;
}
/* Barra de acciones masivas */
.ord-bulkbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    padding: 8px 12px;
    margin-bottom: 12px;
}
.ord-bulk-count {
    font-weight: 700;
    color: #3730a3;
    margin-right: 6px;
}
.ord-bulk-btn {
    border: 1px solid #c7d2fe;
    background: #fff;
    color: #4f46e5;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.ord-bulk-btn:hover {
    background: #4f46e5;
    color: #fff;
}
.ord-bulk-btn.ghost {
    border-color: #e2e8f0;
    color: #64748b;
}
/* Vista móvil: tabla → tarjetas */
@media (max-width: 768px) {
    .orders .ord-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
    .orders table thead {
        display: none;
    }
    .orders table tbody tr {
        display: block;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        margin-bottom: 10px;
        padding: 6px 10px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    .orders table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        border: none !important;
        padding: 6px 0;
        text-align: right;
    }
    .orders table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        text-align: left;
        flex: 0 0 auto;
    }
}
.ord-status-editbar {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}
.ord-saga-status {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-top: 5px;
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
import ShipmentForm from "./partials/shipment_form.vue";
import OrderTimeline from "./partials/order_timeline.vue";
import RecordPayments from "../partials/record_payments.vue";

export default {
    props: ["user"],

    components: {
        DataTable,
        OptionsForm,
        DocumentForm,
        SaleNoteForm,
        ShipmentForm,
        OrderTimeline,
        RecordPayments,
    },
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
            // Chips de filtro rápido (estilo Saga).
            mpFilter: "all",
            // Evita mezclar la cola de facturacion de Saga con pedidos propios.
            orderSource: "all",
            // Rango que se usa para seleccionar el lote de pedidos a facturar.
            invoiceDateRange: [],
            chipCounts: {},
            countsError: null,
            stats: {},
            selectedIds: [],
            currentRecords: [],
            // Envío del pedido (pestaña logística unificada).
            showShipmentDialog: false,
            shipmentOrderId: null,
            // Historial unificado (comercial + logístico).
            showTimelineDialog: false,
            timelineOrderId: null,
            // Chips = preguntas de trabajo, en el orden del flujo real:
            // confirmar → preparar → imprimir → embalar → despachar → tránsito
            // → entregar. Los tres últimos son de control, no de cola.
            orderChips: [
                { key: "all", label: "Todos" },
                { key: "por_confirmar", label: "Por confirmar" },
                { key: "por_preparar", label: "Por preparar" },
                { key: "por_imprimir", label: "Por imprimir" },
                { key: "por_embalar", label: "Por embalar" },
                { key: "por_despachar", label: "Por despachar" },
                { key: "en_transito", label: "En tránsito" },
                { key: "listos_recojo", label: "Listos para recojo" },
                { key: "entregados", label: "Entregados" },
                { key: "anulados", label: "Anulados" },
                { key: "sin_envio", label: "Sin envío" },
                { key: "no_invoice", label: "Sin boleta" },
            ],
            // Filtros logísticos de la barra superior.
            deliveryTypeFilter: "",
            agingFilter: "",
            // Periodo y fecha a considerar. Existían en el backend (`range` y
            // `date_type`) desde el primer commit, pero ninguna pantalla los
            // ofrecía: por eso Pedidos no filtraba como Registro de Envíos.
            dateRange: "",
            dateType: "order",
            rangeOptions: [
                { value: "", label: "Todo el histórico" },
                { value: "hoy", label: "Hoy" },
                { value: "ayer", label: "Ayer" },
                { value: "7dias", label: "Últimos 7 días" },
                { value: "30dias", label: "Últimos 30 días" },
                { value: "mes", label: "Este mes" },
                { value: "mes_pasado", label: "Mes pasado" },
                { value: "custom", label: "Personalizado…" },
            ],
            // Deben coincidir con OrderController::DATE_FIELDS.
            dateTypeOptions: [
                { value: "order", label: "Fecha del pedido" },
                { value: "paid", label: "Fecha de pago" },
                { value: "prepared", label: "Fecha de preparación" },
                { value: "printed", label: "Fecha de impresión" },
                { value: "dispatched", label: "Fecha de despacho" },
                { value: "delivered", label: "Fecha de entrega" },
                { value: "pickup", label: "Fecha de recojo" },
            ],
            deliveryTypeOptions: [
                { value: "", label: "Toda modalidad" },
                { value: "domicilio", label: "Lima / Callao" },
                { value: "agencia", label: "Provincia" },
                { value: "tienda", label: "Recojo en tienda" },
            ],
            agingOptions: [
                { value: "", label: "Cualquier antigüedad" },
                { value: "urgentes", label: "Urgentes" },
                { value: "vencidos", label: "Vencidos" },
            ],
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
            invoicing: null,   // mp_order_id que se esta emitiendo
            statusDocument: {},
            resource_options: null,
            loading_submit: false,
            document_types: [],
            order_id: null,
            dataSaleNote: {},
            showDialogSaleNote: false,
            showPaymentsDialog: false,
            paymentsOrderId: null
        };
    },
    async created() {
        this.$http.get(`/statusOrder/records`).then(response => {
            this.options = response.data;
        });
        this.loadChipCounts();
        this.loadStats();
        this.events();
    },
    computed: {
        hasActiveFilters() {
            return (
                !!this.dateRange ||
                this.dateType !== "order" ||
                !!this.deliveryTypeFilter ||
                !!this.agingFilter ||
                this.orderSource !== "all"
            );
        },
        allSelected() {
            return (
                this.currentRecords.length > 0 &&
                this.currentRecords.every(r =>
                    this.selectedIds.includes(r.id)
                )
            );
        },
    },
    methods: {
        onRecordsChanged(records) {
            this.currentRecords = records || [];
            this.selectedIds = []; // limpia selección al cambiar de página/filtro
            // Chips y KPI se recalculan CADA vez que la tabla se recarga, no
            // solo desde pushFilters(): la busqueda del DataTable recarga por
            // su cuenta y antes dejaba los tres numeros desincronizados.
            this.loadChipCounts();
            this.loadStats();
        },
        toggleAll(e) {
            if (e.target.checked) {
                this.selectedIds = this.currentRecords.map(r => r.id);
            } else {
                this.selectedIds = [];
            }
        },
        selectedRows() {
            return this.currentRecords.filter(r =>
                this.selectedIds.includes(r.id)
            );
        },
        runAction(cmd, row) {
            const acciones = {
                invoice: () => this.generateInvoice(row),
                upload: () => this.uploadInvoice(row),
                markExternal: () => this.markOneExternal(row),
                saleNote: () => this.clickOptions(row.sale_note_id),
                document: () => this.clickDownload(row.document_external_id),
                label: () => this.downloadLabel(row),
                shippingLink: () => this.copyShippingLink(row),
                timeline: () => this.openTimeline(row),
                payments: () => this.clickPayments(row.id)
            };
            if (acciones[cmd]) acciones[cmd]();
        },
        // Emitida en EBAEMY pero aun no cargada en Saga: sin esto la boleta
        // existe solo de nuestro lado y Saga la sigue esperando.
        canUploadInvoice(row) {
            return (
                this.isSagaOrder(row) &&
                row.mp_invoice_state === "ebaemy" &&
                !row.mp_invoice_uploaded
            );
        },
        async uploadInvoice(row) {
            try {
                const { data } = await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/upload-invoice`
                );
                this.$message.success(data.message || "Boleta subida a Saga.");
                this.$refs.ordersTable.getRecords();
            } catch (e) {
                const msg =
                    (e.response && e.response.data && (e.response.data.error || e.response.data.message)) ||
                    "No se pudo subir la boleta a Saga.";
                this.$message({ type: "error", message: msg, duration: 8000 });
            }
        },
        async markOneExternal(row) {
            if (!confirm("¿Marcar este pedido como ya facturado en el sistema de Saga?")) return;
            try {
                await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/mark-invoiced`
                );
                this.$message.success("Marcado.");
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();
            } catch (e) {
                this.$message.error("No se pudo marcar.");
            }
        },
        canGenerateInvoice(row) {
            // Solo pedidos ENTREGADOS o en camino. Un cancelado/devuelto no se
            // factura: en Peru esa boleta solo se deshace con nota de credito.
            return (
                this.isSagaOrder(row) &&
                !row.number_document &&
                row.mp_invoice_state === "pending" &&
                ["delivered", "shipped"].indexOf(row.mp_status) !== -1
            );
        },
        // 'shipped' = viajando: se puede facturar, pero el cliente todavia
        // puede rechazar el paquete. Se avisa antes.
        invoiceIsRisky(row) {
            return row.mp_status === "shipped";
        },
        invoiceButtonLabel(row) {
            return this.invoiceIsRisky(row) ? "Boleta ⚠" : "Boleta";
        },
        async generateInvoice(row) {
            // Es un comprobante ante SUNAT: se confirma con el monto a la vista
            // porque no se deshace con un boton.
            if (
                !confirm(
                    "Se emitira la boleta del pedido " +
                        (row.mp_external_order_id || row.mp_order_id) +
                        " por S/ " +
                        this.formatMoney(row.total) +
                        ". " +
                        (this.invoiceIsRisky(row)
                            ? "OJO: Saga todavia NO confirma la entrega. Si el cliente rechaza el paquete, la boleta quedaria emitida y habria que anularla con nota de credito. "
                            : "") +
                        "Es un comprobante ante SUNAT y no se puede deshacer. ¿Continuar?"
                )
            )
                return;

            this.invoicing = row.mp_order_id;
            try {
                const extra = this.invoiceIsRisky(row)
                    ? "?allow_undelivered=1"
                    : "";
                const { data } = await this.$http.post(
                    `/ecommerce/marketplace/channels/${row.mp_channel_id}/orders/${row.mp_order_id}/invoice${extra}`
                );
                this.$message.success(data.message || "Boleta generada.");
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();
            } catch (e) {
                // El motivo importa: casi siempre es un dato que falta (serie,
                // documento del cliente). Tragarselo dejaria al operador sin
                // saber que corregir.
                const msg =
                    (e.response && e.response.data && (e.response.data.error || e.response.data.message)) ||
                    "No se pudo generar la boleta.";
                this.$message({ type: "error", message: msg, duration: 8000 });
            } finally {
                this.invoicing = null;
            }
        },
        async bulkMarkInvoiced() {
            var rows = this.selectedRows().filter(r => this.isSagaOrder(r));
            if (!rows.length) {
                return this.$message.warning(
                    "Selecciona pedidos de marketplace."
                );
            }
            if (
                !confirm(
                    "¿Marcar " +
                        rows.length +
                        " pedido(s) como 'ya tiene boleta' (emitida fuera de EBAEMY)?"
                )
            )
                return;
            // Antes el catch estaba vacio y SIEMPRE decia "Listo: N marcados",
            // aunque fallaran todos. Se cuenta lo que de verdad se aplico.
            let ok = 0;
            let fallaron = 0;
            for (const r of rows) {
                try {
                    await this.$http.post(
                        `/ecommerce/marketplace/channels/${r.mp_channel_id}/orders/${r.mp_order_id}/mark-invoiced`
                    );
                    ok++;
                } catch (e) {
                    fallaron++;
                }
            }
            if (fallaron) {
                this.$message({
                    type: ok ? "warning" : "error",
                    message: `Marcados: ${ok}. No se pudo con ${fallaron}.`,
                    duration: 8000
                });
            } else {
                this.$message.success("Listo: " + ok + " marcados.");
            }
            this.selectedIds = [];
            this.$refs.ordersTable.getRecords();
        },
        bulkDownloadLabels() {
            var rows = this.selectedRows().filter(r => this.canDownloadLabel(r));
            if (!rows.length) {
                return this.$message.warning(
                    "Ningún pedido seleccionado tiene rótulo disponible."
                );
            }
            rows.forEach(r => this.downloadLabel(r));
        },
        loadChipCounts() {
            this.$http
                .get(`/orders/status-counts`, { params: this.countsParams() })
                .then(response => {
                    this.chipCounts = response.data || {};
                    this.countsError = null;
                })
                .catch(error => {
                    // El `catch` vacío que había aquí convertía cualquier fallo
                    // en "los chips salen sin número", que es indistinguible de
                    // "no hay nada que contar". Se perdía media hora antes de
                    // saber siquiera que la petición se estaba cayendo.
                    this.chipCounts = {};
                    this.countsError = this.describeError(error);
                    console.error("[pedidos] fallo al cargar los contadores", error);
                });
        },
        loadStats() {
            this.$http
                .get(`/orders/stats`, { params: this.countsParams() })
                .then(response => {
                    this.stats = response.data || {};
                })
                .catch(error => {
                    console.error("[pedidos] fallo al cargar los indicadores", error);
                });
        },
        /** Mensaje corto y accionable a partir de un error de axios. */
        describeError(error) {
            const res = error && error.response;
            if (!res) return "Sin respuesta del servidor (¿se cortó la conexión?).";
            if (res.status === 419) return "La sesión expiró. Recarga la página.";
            if (res.status === 403) return "No tienes permiso para ver estos contadores.";
            if (res.status === 500) return "Error del servidor al calcular los contadores.";
            if (res.status === 504) return "El cálculo de los contadores tardó demasiado.";
            return "El servidor respondió " + res.status + ".";
        },
        formatMoney(v) {
            return Number(v || 0).toLocaleString("es-PE", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        /**
         * ¿Este pedido viene de Saga/Falabella?
         *
         * Se llamaba desde cinco sitios —el menu de acciones de la fila,
         * `canGenerateInvoice`, `canUploadInvoice`, `canDownloadLabel` y el
         * marcado masivo— y NUNCA estuvo definida: entro asi en `0aee854e`.
         * No se notaba porque la tabla no llegaba a pintar ni una fila (el
         * filtro fantasma de almacen la dejaba siempre vacia), de modo que la
         * plantilla de fila jamas se ejecutaba. Al arreglar los datos, el
         * `TypeError` tumbaba el render y la pantalla se quedaba en la mascara
         * de carga: un bug tapaba al otro.
         *
         * Se exige la plataforma `falabella` y no solo «tiene pedido de
         * marketplace externo» porque estas acciones dicen literalmente «Subir
         * boleta a Saga» y «Marcar boleta hecha en Saga». Un pedido de otro
         * canal externo no debe verlas: el endpoint las rechazaria igual, pero
         * el operador ya habria leido una etiqueta que le miente. Cuando entre
         * en produccion una segunda plataforma habra que generalizar los
         * textos, y entonces esta condicion, no antes.
         */
        isSagaOrder(row) {
            return !!row.mp_order_id
                && String(row.mp_platform || "").toLowerCase() === "falabella";
        },
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
        /**
         * ¿El dinero de este pedido vive en el envío y no en el pedido?
         *
         * Los encargos de `/registro-envio` se espejan como pedido sin líneas y
         * sin importe: su monto (`amount_due`) y sus cobros viven en el ENVÍO,
         * en `shipping_payments`, que es el módulo de pagos bueno. Abrirles el
         * panel de `order_payments` mostraba un formulario vacío mientras había
         * cobros reales del otro lado.
         */
        pagoEnElEnvio(row) {
            return !!(row.shipment && row.shipment.id)
                && (!row.items || !row.items.length)
                && Number(row.total || 0) === 0;
        },
        clickPayments(orderId) {
            const row = (this.currentRecords || []).find(r => r.id === orderId);

            // Delegar, no clonar: el módulo de pagos de Envíos es un modal
            // Blade + JS, no un componente Vue, así que incrustarlo aquí exige
            // arrastrar su CSS y sobrevivir al re-render de Vue sobre
            // #main-wrapper — el mismo intento que ya obligó a un revert. Se
            // abre su pantalla, que es la que el operador ya conoce.
            if (row && this.pagoEnElEnvio(row)) {
                // `q` acota el listado a ese envio (el boton de pagos solo
                // existe si su fila esta en pantalla) y `pagos` dispara la
                // apertura de la ficha. Ver el partial
                // shipments/partials/open-payments-from-url-js.
                const q = encodeURIComponent(row.shipment.code || "");
                window.open(
                    `/registro-envio?q=${q}&pagos=${row.shipment.id}`,
                    "_blank"
                );
                return;
            }

            this.paymentsOrderId = orderId;
            this.showPaymentsDialog = true;
        },
        // El saldo cambio: se refresca la fila y tambien los contadores, que
        // dependen del estado de pago (un pedido que se salda deja de estar
        // "por confirmar").
        onPaymentsUpdated() {
            this.refreshAfterPayment();
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
        applyMpFilter(key) {
            this.mpFilter = key;
            var dt = this.$refs.ordersTable;
            if (!dt) return;
            // Inyecta el filtro en la consulta del DataTable (se hace spread de
            // search en getQueryParameters) y recarga desde el server.
            // `chip` es el parámetro unificado; `mp_filter` se limpia para que
            // un chip antiguo guardado no se quede aplicado por debajo.
            dt.search.chip = key === "all" ? null : key;
            dt.search.mp_filter = null;
            dt.pagination.current_page = 1;
            dt.getRecords();
        },

        /**
         * Filtros logísticos (modalidad y antigüedad).
         * Se recalculan los contadores porque acotan la base de los chips.
         */
        applyLogisticFilters() {
            this.pushFilters();
        },

        /**
         * Crea un lote de impresión con los pedidos seleccionados.
         *
         * El backend traduce pedidos → envíos y descarta los que no son
         * elegibles; aquí solo se informa el resultado, incluido qué pedidos
         * quedaron fuera por no tener envío configurado (que es accionable:
         * hay que configurárselo).
         */
        async bulkCreatePrintBatch() {
            if (!this.selectedIds.length) return;

            try {
                const { data } = await this.$http.post("/orders/print-batch", {
                    order_ids: this.selectedIds,
                    format: "a4",
                });

                let message = data.message;
                if (data.orders_without_shipment && data.orders_without_shipment.length) {
                    message +=
                        " Sin envío configurado: " +
                        data.orders_without_shipment.length +
                        " pedido(s).";
                }
                this.$message.success(message);

                this.selectedIds = [];
                this.$refs.ordersTable.getRecords();
                this.loadChipCounts();

                if (data.print_url) window.open(data.print_url, "_blank");
            } catch (e) {
                const body = e.response && e.response.data;
                this.$message.error((body && body.message) || "No se pudo crear el lote.");
            }
        },

        /**
         * Copia el enlace público para que el cliente complete sus datos de
         * entrega. El token es el `external_id` del pedido, así que el enlace
         * cae SIEMPRE sobre ese pedido y no puede crear uno nuevo.
         */
        async copyShippingLink(row) {
            const url =
                window.location.origin + "/pedido/" + row.external_id + "/datos-envio";

            try {
                // `clipboard` no existe fuera de HTTPS/localhost: sin el
                // fallback el operador se queda sin enlace y sin explicación.
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const helper = document.createElement("textarea");
                    helper.value = url;
                    helper.style.position = "fixed";
                    helper.style.opacity = "0";
                    document.body.appendChild(helper);
                    helper.select();
                    document.execCommand("copy");
                    document.body.removeChild(helper);
                }
                this.$message.success("Enlace copiado. Envíaselo al cliente.");
            } catch (e) {
                this.$alert(url, "Enlace de datos de envío", {
                    confirmButtonText: "Cerrar",
                });
            }
        },

        /** Abre el historial unificado del pedido. */
        openTimeline(row) {
            this.timelineOrderId = row.id;
            this.showTimelineDialog = true;
        },

        /** Abre la pestaña de envío del pedido. */
        openShipment(row) {
            this.shipmentOrderId = row.id;
            this.showShipmentDialog = true;
        },

        /** Tras configurar el envío, la fila debe reflejarlo sin recargar. */
        onShipmentSaved() {
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
        },

        /** Texto del destino para la columna de entrega. */
        shipmentDestination(row) {
            const s = row.shipment;
            if (!s) return "";
            return s.destination || "—";
        },
        /**
         * Filtros que POSEE esta pantalla: periodo, origen y logistica. Son los
         * que pushFilters() vuelca sobre `dt.search`. Los chips y los KPI NO
         * los consumen directamente — para eso esta countsParams().
         */
        invoiceDateParams() {
            const custom = this.dateRange === "custom";
            return {
                range: custom ? null : this.dateRange || null,
                date_type: this.dateType,
                date_from: custom ? (this.invoiceDateRange || [])[0] || null : null,
                date_to: custom ? (this.invoiceDateRange || [])[1] || null : null,
                order_source: this.orderSource,
                delivery_type: this.deliveryTypeFilter || null,
                aging: this.agingFilter || null,
            };
        },

        /**
         * Filtros de los chips y los KPI: EXACTAMENTE los de la tabla, menos el
         * chip activo (el numero de un chip debe ser lo que veras al pulsarlo).
         *
         * Se leen de `dt.search`, que es donde vive el estado real de la
         * consulta, y no solo de los filtros de esta pantalla: la busqueda del
         * DataTable tambien acota la tabla, y contarla aparte hacia que los
         * chips siguieran mostrando el histórico completo mientras la tabla
         * mostraba un cliente.
         *
         * `chip`/`mp_filter` se excluyen a proposito. `warehouse_id` viaja
         * porque la tabla lo manda: si los dos no mandan lo mismo, el numero
         * del chip vuelve a mentir.
         */
        countsParams() {
            const dt = this.$refs.ordersTable;
            const propios = this.invoiceDateParams();
            if (!dt) return propios;

            const heredados = Object.assign({}, dt.search);
            delete heredados.chip;
            delete heredados.mp_filter;

            return Object.assign(heredados, propios, {
                warehouse_id: dt.warehouse_id,
            });
        },

        /** Vuelca los filtros actuales en la tabla y recarga todo. */
        pushFilters() {
            const dt = this.$refs.ordersTable;
            if (!dt) return;

            Object.assign(dt.search, this.invoiceDateParams());
            dt.pagination.current_page = 1;
            dt.getRecords();
            this.loadChipCounts();
            this.loadStats();
        },

        applyDateFilters() {
            // Al elegir "personalizado" todavía no hay fechas: no se recarga
            // hasta que el usuario elija el rango, o se perdería el filtro
            // anterior mostrando el histórico completo sin haberlo pedido.
            if (this.dateRange === "custom" && !(this.invoiceDateRange || []).length) return;
            this.pushFilters();
        },

        clearFilters() {
            this.dateRange = "";
            this.dateType = "order";
            this.invoiceDateRange = [];
            this.deliveryTypeFilter = "";
            this.agingFilter = "";
            this.orderSource = "all";
            this.pushFilters();
        },
        applyOrderSource() {
            this.pushFilters();
        },
        canDownloadLabel(row) {
            // Solo pedidos de Saga ya despachables tienen rótulo en Saga.
            return (
                this.isSagaOrder(row) &&
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
                    // Verificar el pago registra los pagos y genera la nota de
                    // venta, pero antes no se refrescaba nada: la fila seguia
                    // mostrando el estado viejo y habia que recargar la pagina
                    // a mano para verlo.
                    this.refreshAfterPayment();
                })
                .catch(error => {
                    // Sin este catch, un fallo se veia igual que un exito: no
                    // pasaba nada en pantalla y el pago quedaba sin registrar.
                    this.$message.error(this.describeError(error) || 'No se pudo actualizar el pedido');
                });
        },

        /**
         * Refresca solo lo que cambia al registrar un pago: la fila del
         * listado, los contadores de los chips y las metricas de arriba.
         *
         * getRecords() conserva la pagina, el orden, la busqueda y los filtros
         * activos, asi que el operador no pierde el contexto.
         */
        refreshAfterPayment() {
            const dt = this.$refs.ordersTable;
            if (dt) dt.getRecords();
            this.loadChipCounts();
            this.loadStats();
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
                    this.refreshAfterPayment();
                })
                .catch(error => {
                    this.$message.error(this.describeError(error) || 'No se pudo guardar el pedido');
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
