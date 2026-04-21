<template>
    <div class="ec-orders" v-loading="loading_submit">

        <!-- ── Page header ─────────────────────────────────────────────── -->
        <div class="page-header pe-0">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:-4px">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                    <path d="M17 17h-11v-14h-2"/>
                    <path d="M6 5l14 1l-1 7h-13"/>
                </svg>
                Pedidos
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Tienda Virtual / Pedidos</span></li>
            </ol>
        </div>

        <!-- ── KPI Cards ────────────────────────────────────────────────── -->
        <div class="eco-kpi-grid">
            <div class="eco-kpi eco-kpi--purple">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2"/><path d="M12 12v4"/><path d="M8 12v4"/><path d="M16 12v4"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">{{ stats.total }}</span>
                    <span class="eco-kpi__label">Total pedidos</span>
                </div>
            </div>
            <div class="eco-kpi eco-kpi--amber">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">{{ stats.pending }}</span>
                    <span class="eco-kpi__label">Sin verificar</span>
                </div>
            </div>
            <div class="eco-kpi eco-kpi--blue">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">{{ stats.verified }}</span>
                    <span class="eco-kpi__label">Verificados</span>
                </div>
            </div>
            <div class="eco-kpi eco-kpi--green">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">{{ stats.dispatched }}</span>
                    <span class="eco-kpi__label">Despachados</span>
                </div>
            </div>
            <div class="eco-kpi eco-kpi--teal">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">S/ {{ Number(stats.revenueMonth).toLocaleString('es-PE', {minimumFractionDigits:2}) }}</span>
                    <span class="eco-kpi__label">Ventas del mes</span>
                </div>
            </div>
            <div class="eco-kpi eco-kpi--rose">
                <div class="eco-kpi__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="eco-kpi__body">
                    <span class="eco-kpi__value">S/ {{ Number(stats.revenueToday).toLocaleString('es-PE', {minimumFractionDigits:2}) }}</span>
                    <span class="eco-kpi__label">Ventas de hoy</span>
                </div>
            </div>
        </div>

        <!-- ── Toolbar: filtros + búsqueda ─────────────────────────────── -->
        <div class="eco-toolbar">
            <!-- Status tabs -->
            <div class="eco-status-tabs">
                <button class="eco-stab" :class="{'eco-stab--active': filterStatus === null}" @click="setStatus(null)">
                    Todos <span class="eco-stab__count">{{ stats.total }}</span>
                </button>
                <button class="eco-stab eco-stab--amber" :class="{'eco-stab--active': filterStatus === 1}" @click="setStatus(1)">
                    Pendiente <span class="eco-stab__count">{{ stats.pending }}</span>
                </button>
                <button class="eco-stab eco-stab--blue" :class="{'eco-stab--active': filterStatus === 2}" @click="setStatus(2)">
                    Verificado <span class="eco-stab__count">{{ stats.verified }}</span>
                </button>
                <button class="eco-stab eco-stab--green" :class="{'eco-stab--active': filterStatus === 3}" @click="setStatus(3)">
                    En preparación <span class="eco-stab__count">{{ stats.dispatched }}</span>
                </button>
                <button class="eco-stab eco-stab--purple" :class="{'eco-stab--active': filterStatus === 4}" @click="setStatus(4)">
                    Enviado
                </button>
                <button class="eco-stab eco-stab--gray" :class="{'eco-stab--active': filterStatus === 5}" @click="setStatus(5)">
                    Cancelado
                </button>
            </div>
            <!-- Canal filter -->
            <el-select
                v-model="filterChannel"
                size="small"
                clearable
                placeholder="Canal"
                class="eco-channel-select"
                @change="applyFilters"
            >
                <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id">
                    <span>{{ channelIcon(ch.type) }} {{ ch.name }}</span>
                </el-option>
            </el-select>
            <!-- Search -->
            <div class="eco-search">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="searchValue" @input="onSearch" type="text" placeholder="Buscar por código, dirección..." class="eco-search__input">
            </div>
        </div>

        <!-- ── Canal report mini ──────────────────────────────────────── -->
        <div v-if="stats.byChannel && stats.byChannel.length" class="eco-channel-report">
            <div v-for="ch in stats.byChannel" :key="ch.channel_id" class="eco-cr-item">
                <span class="eco-cr-icon">{{ channelIcon(ch.channel_type) }}</span>
                <div class="eco-cr-body">
                    <span class="eco-cr-name">{{ ch.channel_name }}</span>
                    <span class="eco-cr-count">{{ ch.count }} pedidos</span>
                </div>
                <span class="eco-cr-revenue">S/ {{ Number(ch.revenue).toLocaleString('es-PE', {minimumFractionDigits:2}) }}</span>
            </div>
        </div>

        <!-- ── Table ────────────────────────────────────────────────────── -->
        <div class="card eco-table-card">
            <div class="card-body p-0">
                <data-table :resource="resource" ref="dataTable">
                    <tr slot="heading">
                        <th class="eco-th eco-th--id">Pedido</th>
                        <th class="eco-th">Cliente</th>
                        <th class="eco-th text-center">Productos</th>
                        <th class="eco-th text-end">Total</th>
                        <th class="eco-th">Fecha</th>
                        <th class="eco-th">Canal</th>
                        <th class="eco-th">Estado</th>
                        <th class="eco-th text-center">Documento</th>
                        <th class="eco-th text-center">Acciones</th>
                    </tr>
                    <tr></tr>
                    <tr slot-scope="{ index, row }" class="eco-row" :class="rowClass(row)">

                        <!-- Código -->
                        <td class="eco-td">
                            <span class="eco-order-id">#{{ row.order_id }}</span>
                        </td>

                        <!-- Cliente -->
                        <td class="eco-td">
                            <div class="eco-customer">
                                <span class="eco-customer__avatar">{{ avatarLetter(row.customer) }}</span>
                                <div class="eco-customer__info">
                                    <span class="eco-customer__name">{{ row.customer || 'Invitado' }}</span>
                                    <span class="eco-customer__email">{{ row.customer_email }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Productos -->
                        <td class="eco-td text-center">
                            <el-popover placement="right" width="560" trigger="click">
                                <div class="eco-popover">
                                    <el-table :data="row.items" size="mini">
                                        <el-table-column property="description" label="Producto" min-width="160"></el-table-column>
                                        <el-table-column property="cantidad" label="Cant." width="60" align="center"></el-table-column>
                                        <el-table-column label="Precio" width="90" align="right">
                                            <template slot-scope="s">S/ {{ Number(s.row.sale_unit_price).toFixed(2) }}</template>
                                        </el-table-column>
                                        <el-table-column label="Subtotal" width="90" align="right">
                                            <template slot-scope="s">S/ {{ subtotal(s.row) }}</template>
                                        </el-table-column>
                                    </el-table>
                                    <div class="eco-popover__contact">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12"/></svg>
                                        {{ row.customer_telefono || '-' }}
                                        &nbsp;·&nbsp;
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>
                                        {{ row.customer_email || '-' }}
                                        &nbsp;|&nbsp;
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        {{ row.customer_direccion || '-' }}
                                    </div>
                                </div>
                                <button slot="reference" class="eco-detail-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <span class="eco-detail-btn__count">{{ row.item_count }}</span>
                                    {{ row.item_count === 1 ? 'producto' : 'productos' }}
                                </button>
                            </el-popover>
                        </td>

                        <!-- Total -->
                        <td class="eco-td text-end">
                            <span class="eco-total">S/ {{ Number(row.total).toFixed(2) }}</span>
                        </td>

                        <!-- Fecha -->
                        <td class="eco-td">
                            <div class="eco-date">
                                <span class="eco-date__day">{{ formatDate(row.created_at, 'day') }}</span>
                                <span class="eco-date__time">{{ formatDate(row.created_at, 'time') }}</span>
                            </div>
                        </td>

                        <!-- Canal de venta -->
                        <td class="eco-td">
                            <span v-if="row.channel_name" class="eco-channel-badge" :class="`eco-ch--${row.channel_type}`">
                                {{ channelIcon(row.channel_type) }} {{ row.channel_name }}
                            </span>
                            <span v-else class="eco-doc-empty">—</span>
                            <!-- Indicador extra cuando viene del marketplace central de ebaemy -->
                            <div v-if="row.channel_type === 'marketplace'" class="eco-mkt-flag" title="Pedido recibido desde ebaemy.com/marketplace">
                                🌐 Ebaemy
                            </div>
                        </td>

                        <!-- Estado -->
                        <td class="eco-td">
                            <div class="eco-status-wrap">
                                <span class="eco-status-badge" :class="statusBadgeClass(row.status_order_id)">{{ statusLabel(row.status_order_id) }}</span>
                                <div v-if="primaryActionFor(row.status_order_id)" class="eco-status-actions">
                                    <el-button
                                        size="mini"
                                        :type="primaryActionFor(row.status_order_id).type"
                                        :title="primaryActionFor(row.status_order_id).label"
                                        @click.prevent="updateStatus(row, primaryActionFor(row.status_order_id).target)">
                                        {{ primaryActionFor(row.status_order_id).label }}
                                    </el-button>
                                    <el-button
                                        v-if="canCancel(row.status_order_id)"
                                        size="mini"
                                        type="danger"
                                        plain
                                        title="Cancelar pedido"
                                        @click.prevent="confirmCancel(row)">
                                        ✕
                                    </el-button>
                                </div>
                                <span v-else class="eco-status-final">final</span>
                            </div>
                        </td>

                        <!-- Documento -->
                        <td class="eco-td text-center">
                            <span v-if="row.sale_note_number_full" class="eco-doc-number">{{ row.sale_note_number_full }}</span>
                            <span v-else-if="row.number_document" class="eco-doc-number">{{ row.number_document }}</span>
                            <span v-else class="eco-doc-empty">—</span>
                        </td>

                        <!-- Acciones -->
                        <td class="eco-td text-center">
                            <div class="eco-actions">
                                <!-- Ver opciones de NV (modal: A4, ticket 80mm, email, etc.) -->
                                <el-button v-if="row.sale_note_id" size="mini" type="success" icon="el-icon-tickets" title="Ver nota de venta"
                                           @click.prevent="clickOptions(row.sale_note_id)"></el-button>
                                <!-- Imprimir PDF directo en nueva pestaña -->
                                <el-button v-if="row.sale_note_external_id" size="mini" type="primary" icon="el-icon-printer" title="Imprimir PDF (A4)"
                                           @click.prevent="printSaleNote(row.sale_note_external_id, 'a4')"></el-button>
                                <!-- Ver comprobante si es boleta/factura -->
                                <el-button v-if="row.document_external_id" size="mini" type="warning" icon="el-icon-document" title="Ver comprobante"
                                           @click.prevent="clickDownload(row.document_external_id)"></el-button>
                                <el-button size="mini" type="info" icon="el-icon-time" title="Ver historial del pedido"
                                           @click.prevent="openHistory(row)"></el-button>
                            </div>
                        </td>

                    </tr>
                </data-table>
            </div>
        </div>

        <!-- ── Dialogs (sin cambios funcionales) ────────────────────────── -->
        <el-dialog title="Stock en almacén" width="40%" :visible="showDialog"
                   :close-on-click-modal="false" :close-on-press-escape="false" append-to-body :show-close="false">
            <div class="form-body">
                <div class="row">
                    <div class="col-lg-12 table-responsive">
                        <table width="100%" class="table">
                            <thead><tr><th>Producto</th><th class="text-center">Almacén</th></tr></thead>
                            <tbody v-for="(rowProduct, indexProduct) in totalProduct" :key="indexProduct">
                                <tr>
                                    <td>{{ record.items[indexProduct].name }}</td>
                                    <td>
                                        <el-select v-model="form[rowProduct]" placeholder="Almacenes">
                                            <el-option v-if="rowProduct === item.item_id"
                                                       v-for="item in warehouses" :key="item.id"
                                                       :label="item.warehouse + ' - Stock → ' + Math.trunc(item.stock)"
                                                       :value="item.id"
                                                       :disabled="optionDisable(item.item_id, item.stock)"></el-option>
                                        </el-select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2">
                <el-button @click="close">Cerrar</el-button>
                <el-button type="primary" @click="save">Guardar</el-button>
            </div>
        </el-dialog>

        <options-form :showDialog.sync="showDialogOptions" :recordId="documentNewId"
                      :statusDocument="statusDocument" :resource="resource_options"></options-form>

        <document-form :order_id="order_id" :user="user" :document_types="document_types" ref="document_form"></document-form>

        <sale-note-form :showDialog.sync="showDialogSaleNote" :orderId="order_id" :dataSaleNote="dataSaleNote"></sale-note-form>

        <!-- ── Verificar pago (registro de pagos) ─────────────────────── -->
        <el-dialog :title="`Verificar pago — Pedido #${paymentDlg.orderIdPad}`" width="65%"
                   :visible.sync="showDialogPayment" :close-on-click-modal="false" append-to-body>
            <div v-loading="paymentDlg.loading" class="eco-payment-dlg">
                <div class="eco-payment-dlg__summary">
                    <div>Total del pedido: <b>S/ {{ Number(paymentDlg.total).toFixed(2) }}</b></div>
                    <div>Registrado: <b>S/ {{ paymentsSum.toFixed(2) }}</b></div>
                    <div :class="{ 'eco-payment-dlg__ok': paymentsBalance === 0, 'eco-payment-dlg__warn': paymentsBalance !== 0 }">
                        Diferencia: <b>S/ {{ paymentsBalance.toFixed(2) }}</b>
                    </div>
                </div>

                <table class="eco-payment-table">
                    <thead>
                        <tr>
                            <th style="width:160px">Fecha</th>
                            <th>Método</th>
                            <th>Destino</th>
                            <th>Referencia</th>
                            <th style="width:130px">Monto</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, idx) in paymentDlg.payments" :key="idx">
                            <td><el-date-picker v-model="p.date_of_payment" type="date" size="mini" format="dd/MM/yyyy" value-format="yyyy-MM-dd" :clearable="false" style="width:100%"></el-date-picker></td>
                            <td>
                                <el-select v-model="p.payment_method_type_id" size="mini" placeholder="Método" @change="onChangePaymentMethod(p)" style="width:100%">
                                    <el-option v-for="m in paymentDlg.methods" :key="m.id" :label="m.description" :value="m.id"></el-option>
                                </el-select>
                            </td>
                            <td>
                                <el-select v-model="p.payment_destination_id" size="mini" placeholder="Destino" style="width:100%">
                                    <el-option v-for="d in paymentDlg.destinations" :key="d.id" :label="d.description" :value="String(d.id)"></el-option>
                                </el-select>
                            </td>
                            <td><el-input v-model="p.reference" size="mini" placeholder="Nro. operación/voucher"></el-input></td>
                            <td><el-input-number v-model="p.payment" size="mini" :min="0" :precision="2" :controls="false" style="width:100%"></el-input-number></td>
                            <td>
                                <el-button v-if="paymentDlg.payments.length > 1" size="mini" type="danger" plain icon="el-icon-delete" @click="removePaymentRow(idx)"></el-button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="eco-payment-dlg__actions">
                    <el-button size="mini" type="primary" plain icon="el-icon-plus" @click="addPaymentRow">Agregar pago</el-button>
                    <el-button size="mini" plain @click="distributeTotal">Distribuir total</el-button>
                </div>
            </div>

            <div slot="footer">
                <el-button @click="showDialogPayment = false">Cancelar</el-button>
                <el-button type="primary" :disabled="!canConfirmPayment" @click="confirmPaymentVerification">
                    Confirmar pago y generar comprobante
                </el-button>
            </div>
        </el-dialog>

        <!-- ── Historial del pedido (timeline) ──────────────────────────── -->
        <el-dialog :title="history.title" width="48%" :visible.sync="showDialogHistory"
                   :close-on-click-modal="true" append-to-body>
            <div v-loading="history.loading" class="eco-history">
                <div v-if="!history.loading && !history.logs.length" class="eco-history__empty">
                    Sin transiciones registradas todavía.
                </div>

                <div v-if="history.current" class="eco-history__header">
                    <span class="eco-status-badge" :class="statusBadgeClass(history.current.id)">{{ history.current.label }}</span>
                    <span v-if="history.payment_status" class="eco-history__payment">Pago: <b>{{ history.payment_status }}</b></span>
                </div>

                <ul class="eco-timeline">
                    <li v-for="log in history.logs" :key="log.id" class="eco-timeline__item">
                        <span class="eco-timeline__dot" :class="statusBadgeClass(log.to_status)"></span>
                        <div class="eco-timeline__body">
                            <div class="eco-timeline__title">
                                <span v-if="log.from_label">{{ log.from_label }} → </span>
                                <b>{{ log.to_label }}</b>
                            </div>
                            <div class="eco-timeline__meta">
                                <span v-if="log.actor">👤 {{ log.actor.name || log.actor.email }}</span>
                                <span v-if="log.payment_status">• pago: {{ log.payment_status }}</span>
                                <span>• {{ log.created_at }}</span>
                                <span class="eco-timeline__human">({{ log.created_at_human }})</span>
                            </div>
                            <div v-if="log.payload && Object.keys(log.payload).length" class="eco-timeline__payload">
                                <template v-if="log.payload.reason">
                                    <i>Motivo:</i> {{ log.payload.reason }}
                                </template>
                                <template v-else-if="log.payload.mode">
                                    <i>Modo:</i> {{ log.payload.mode }}
                                </template>
                                <template v-else-if="log.payload.discount">
                                    <i>Items descontados:</i> {{ log.payload.discount.length }}
                                </template>
                            </div>
                        </div>
                    </li>
                </ul>

                <div v-if="history.phases && (history.phases.prepared_at || history.phases.dispatched_at || history.phases.delivered_at)"
                     class="eco-history__phases">
                    <div v-if="history.phases.prepared_at">📦 Preparado: <b>{{ history.phases.prepared_at }}</b></div>
                    <div v-if="history.phases.dispatched_at">🚚 Despachado: <b>{{ history.phases.dispatched_at }}</b></div>
                    <div v-if="history.phases.delivered_at">🎉 Entregado: <b>{{ history.phases.delivered_at }}</b></div>
                </div>
            </div>
        </el-dialog>

    </div>
</template>

<style scoped>
/* ── KPI Grid ─────────────────────────────────────────── */
.eco-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.eco-kpi {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fff;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
    border-left: 4px solid;
}
.eco-kpi--purple { border-color: #7c3aed; }
.eco-kpi--amber  { border-color: #f59e0b; }
.eco-kpi--blue   { border-color: #3b82f6; }
.eco-kpi--green  { border-color: #10b981; }
.eco-kpi--teal   { border-color: #14b8a6; }
.eco-kpi--rose   { border-color: #f43f5e; }
.eco-kpi__icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: #f8f7ff;
}
.eco-kpi--amber .eco-kpi__icon  { background: #fffbeb; color: #d97706; }
.eco-kpi--blue .eco-kpi__icon   { background: #eff6ff; color: #2563eb; }
.eco-kpi--green .eco-kpi__icon  { background: #ecfdf5; color: #059669; }
.eco-kpi--teal .eco-kpi__icon   { background: #f0fdfa; color: #0d9488; }
.eco-kpi--rose .eco-kpi__icon   { background: #fff1f2; color: #e11d48; }
.eco-kpi--purple .eco-kpi__icon { background: #f5f3ff; color: #6d28d9; }
.eco-kpi__body { display: flex; flex-direction: column; min-width: 0; }
.eco-kpi__value { font-size: 1.5rem; font-weight: 800; color: #111; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eco-kpi__label { font-size: 1.1rem; color: #6b7280; margin-top: 2px; }

/* ── Toolbar ──────────────────────────────────────────── */
.eco-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.eco-status-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
}
.eco-stab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    border: 1.5px solid #e5e7eb;
    background: #fff;
    font-size: 1.2rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all .15s;
}
.eco-stab:hover { border-color: #9ca3af; color: #374151; }
.eco-stab__count {
    background: #f3f4f6;
    border-radius: 20px;
    padding: 1px 7px;
    font-size: 1.1rem;
    font-weight: 700;
}
.eco-stab--active { color: #fff !important; border-color: transparent !important; }
.eco-stab--active .eco-stab__count { background: rgba(255,255,255,.25); }
.eco-stab.eco-stab--active:not(.eco-stab--amber):not(.eco-stab--blue):not(.eco-stab--green):not(.eco-stab--purple) { background: #374151; }
.eco-stab--amber.eco-stab--active { background: #f59e0b; }
.eco-stab--blue.eco-stab--active  { background: #3b82f6; }
.eco-stab--green.eco-stab--active { background: #10b981; }
.eco-stab--purple.eco-stab--active { background: #7c3aed; }

.eco-search {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px 12px;
    min-width: 240px;
}
.eco-search__input {
    border: none;
    outline: none;
    font-size: 1.3rem;
    color: #374151;
    width: 100%;
    background: transparent;
}

/* ── Table ────────────────────────────────────────────── */
.eco-table-card { border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.eco-th {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #9ca3af;
    padding: 12px 14px;
    background: #f9fafb;
    border-bottom: 1px solid #f0f0f0;
    white-space: nowrap;
}
.eco-th--id { width: 90px; }
.eco-td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
.eco-row:hover td { background: #fafafe; }
.eco-row--dispatched { opacity: .85; }

/* Order ID */
.eco-order-id { font-size: 1.3rem; font-weight: 700; color: #374151; font-family: monospace; }

/* Customer */
.eco-customer { display: flex; align-items: center; gap: 10px; }
.eco-customer__avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #6c47d6, #4f8ef7);
    color: #fff; font-size: 1.2rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.eco-customer__info { display: flex; flex-direction: column; min-width: 0; }
.eco-customer__name { font-size: 1.3rem; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.eco-customer__email { font-size: 1.1rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }

/* Detail button */
.eco-detail-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 10px; border-radius: 6px;
    border: 1.5px solid #e5e7eb; background: #fff;
    font-size: 1.2rem; font-weight: 600; color: #374151;
    cursor: pointer; transition: all .15s;
}
.eco-detail-btn:hover { border-color: #6c47d6; color: #6c47d6; }
.eco-detail-btn__count {
    background: #f5f3ff; color: #6c47d6;
    border-radius: 20px; padding: 1px 7px; font-size: 1.1rem; font-weight: 800;
}

/* Total */
.eco-total { font-size: 1.4rem; font-weight: 700; color: #059669; }

/* Date */
.eco-date { display: flex; flex-direction: column; }
.eco-date__day  { font-size: 1.25rem; font-weight: 600; color: #374151; }
.eco-date__time { font-size: 1.1rem; color: #9ca3af; }

/* Payment */
.eco-payment {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 1.1rem; font-weight: 700; letter-spacing: .03em;
    background: #f3f4f6; color: #374151;
}
.eco-payment--efectivo { background: #ecfdf5; color: #065f46; }
.eco-payment--culqi, .eco-payment--tarjeta { background: #eff6ff; color: #1d4ed8; }
.eco-payment--paypal  { background: #fffbeb; color: #92400e; }

/* Status badge */
.eco-status-wrap { display: flex; flex-direction: column; gap: 4px; }
.eco-status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
    width: fit-content; letter-spacing: .02em;
}
.eco-badge--1 { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.eco-badge--1::before { content: '⏳'; }
.eco-badge--2 { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.eco-badge--2::before { content: '✅'; }
.eco-badge--3 { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.eco-badge--3::before { content: '📦'; }
.eco-badge--4 { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.eco-badge--4::before { content: '🚚'; }
.eco-badge--5 { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.eco-badge--5::before { content: '❌'; }
.eco-badge--6 { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.eco-badge--6::before { content: '🎉'; }
.eco-status-select { width: 100% !important; }
.eco-status-actions { display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap; }
.eco-status-actions .el-button { padding: 6px 10px; font-size: 1.1rem; }
.eco-status-final {
    display: inline-block; padding: 2px 10px; margin-top: 4px;
    font-size: 1rem; color: #6b7280; font-style: italic;
    background: #f3f4f6; border-radius: 10px;
}

/* ── Historial / timeline del pedido ────────────────────────────── */
.eco-history { font-size: 1.25rem; }
.eco-history__header {
    display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
    padding-bottom: 10px; border-bottom: 1px dashed #e5e7eb;
}
.eco-history__payment { color: #374151; }
.eco-history__empty { padding: 24px; text-align: center; color: #9ca3af; }

.eco-timeline { list-style: none; padding: 0; margin: 0; }
.eco-timeline__item {
    position: relative; padding: 0 0 16px 26px;
    border-left: 2px solid #e5e7eb;
    margin-left: 8px;
}
.eco-timeline__item:last-child { border-left-color: transparent; padding-bottom: 0; }
.eco-timeline__dot {
    position: absolute; left: -9px; top: 2px;
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid #fff; box-shadow: 0 0 0 2px #d1d5db;
    background: #9ca3af;
}
.eco-timeline__dot.eco-badge--1 { background: #f59e0b; box-shadow: 0 0 0 2px #fde68a; }
.eco-timeline__dot.eco-badge--2 { background: #22c55e; box-shadow: 0 0 0 2px #bbf7d0; }
.eco-timeline__dot.eco-badge--3 { background: #3b82f6; box-shadow: 0 0 0 2px #bfdbfe; }
.eco-timeline__dot.eco-badge--4 { background: #6366f1; box-shadow: 0 0 0 2px #c7d2fe; }
.eco-timeline__dot.eco-badge--5 { background: #ef4444; box-shadow: 0 0 0 2px #fecaca; }
.eco-timeline__dot.eco-badge--6 { background: #10b981; box-shadow: 0 0 0 2px #a7f3d0; }
.eco-timeline__dot::before { content: none; }
.eco-timeline__title { font-weight: 500; color: #111827; }
.eco-timeline__meta { margin-top: 4px; color: #6b7280; font-size: 1.1rem; display: flex; flex-wrap: wrap; gap: 6px; }
.eco-timeline__human { color: #9ca3af; font-style: italic; }
.eco-timeline__payload {
    margin-top: 6px; padding: 6px 10px;
    background: #f9fafb; border-radius: 6px; color: #374151;
}

.eco-history__phases {
    margin-top: 16px; padding: 10px 14px;
    background: #f9fafb; border-radius: 8px; font-size: 1.2rem;
    display: flex; flex-direction: column; gap: 4px;
}

/* ── Modal Verificar pago ───────────────────────────────────────── */
.eco-payment-dlg { font-size: 1.25rem; }
.eco-payment-dlg__summary {
    display: flex; gap: 24px; flex-wrap: wrap;
    padding: 10px 14px; margin-bottom: 12px;
    background: #f9fafb; border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.eco-payment-dlg__ok   b { color: #059669; }
.eco-payment-dlg__warn b { color: #dc2626; }

.eco-payment-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
.eco-payment-table th {
    text-align: left; padding: 6px 8px; color: #6b7280;
    font-weight: 500; font-size: 1.1rem;
    border-bottom: 1px solid #e5e7eb;
}
.eco-payment-table td { padding: 4px 6px; vertical-align: middle; }
.eco-payment-table tbody tr { background: #fff; }

.eco-payment-dlg__actions {
    display: flex; gap: 8px; margin-top: 12px;
    padding-top: 10px; border-top: 1px dashed #e5e7eb;
}

/* Status tab gray (cancelado) */
.eco-stab--gray { color: #6b7280; }
.eco-stab--gray.eco-stab--active { background: #6b7280; color: #fff; }

/* Canal de venta badge */
.eco-channel-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 12px;
    font-size: 1.1rem; font-weight: 600; white-space: nowrap;
}
.eco-ch--ecommerce   { background: #eef2ff; color: #4338ca; }
.eco-ch--pos         { background: #d1fae5; color: #065f46; }
.eco-ch--whatsapp    { background: #dcfce7; color: #15803d; }
.eco-ch--phone       { background: #fef3c7; color: #92400e; }
.eco-ch--marketplace { background: #f3e8ff; color: #7e22ce; }
.eco-mkt-flag { display:inline-block; margin-top:4px; padding:2px 8px; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; border-radius:6px; font-size:10px; font-weight:700; letter-spacing:.3px; }
.eco-ch--other       { background: #f3f4f6; color: #374151; }

/* Canal select en toolbar */
.eco-channel-select { min-width: 140px; margin-right: 8px; }

/* Reporte mini de canales */
.eco-channel-report {
    display: flex; flex-wrap: wrap; gap: 12px;
    padding: 12px 16px; margin-bottom: 16px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
}
.eco-cr-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px; background: #f9fafb; border-radius: 8px;
    border: 1px solid #e5e7eb; min-width: 200px;
}
.eco-cr-icon  { font-size: 1.4rem; }
.eco-cr-body  { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.eco-cr-name  { font-size: 1.2rem; font-weight: 700; color: #111; }
.eco-cr-count { font-size: 1.1rem; color: #6b7280; }
.eco-cr-revenue { font-size: 1.2rem; font-weight: 800; color: #059669; white-space: nowrap; }

/* Document number */
.eco-doc-number { font-size: 1.2rem; font-weight: 600; color: #374151; font-family: monospace; }
.eco-doc-empty  { color: #d1d5db; }

/* Actions */
.eco-actions { display: flex; align-items: center; justify-content: center; gap: 4px; }

/* Popover */
.eco-popover__contact {
    display: flex; align-items: center; gap: 6px;
    padding: 8px 6px; font-size: 1.2rem; color: #6b7280;
    border-top: 1px solid #f0f0f0; margin-top: 8px;
}

@media (max-width: 768px) {
    .eco-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .eco-toolbar { flex-direction: column; align-items: stretch; }
    .eco-search { min-width: unset; }
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
            resource: "orders",
            options: [],
            warehouses: [],
            totalProduct: [],
            showDialog: false,
            form: [],
            record: "",
            showDialogOptions: false,
            documentNewId: null,
            statusDocument: {},
            resource_options: null,
            loading_submit: false,
            document_types: [],
            order_id: null,
            dataSaleNote: {},
            showDialogSaleNote: false,
            showDialogHistory: false,
            history: {
                title: 'Historial del pedido',
                loading: false,
                logs: [],
                current: null,
                payment_status: null,
                phases: null,
            },
            showDialogPayment: false,
            paymentDlg: {
                loading: false,
                orderId: null,
                orderIdPad: '',
                record: null,
                total: 0,
                methods: [],
                destinations: [],
                cardBrands: [],
                payments: [],
            },
            filterStatus: null,
            filterChannel: null,
            searchValue: "",
            searchTimer: null,
            channels: [],
            stats: { total: 0, pending: 0, verified: 0, dispatched: 0, revenueMonth: 0, revenueToday: 0, byChannel: [] },
        };
    },
    async created() {
        this.$http.get('/statusOrder/records').then(r => { this.options = r.data; });
        this.$http.get('/orders/stats').then(r => { this.stats = r.data; });
        this.$http.get('/orders/channels').then(r => { this.channels = r.data; });
        this.events();
    },
    computed: {
        paymentsSum() {
            return (this.paymentDlg.payments || []).reduce((acc, p) => acc + (Number(p.payment) || 0), 0);
        },
        paymentsBalance() {
            return Number((this.paymentDlg.total - this.paymentsSum).toFixed(2));
        },
        canConfirmPayment() {
            if (!this.paymentDlg.payments.length) return false;
            if (this.paymentsBalance !== 0) return false;
            return this.paymentDlg.payments.every(p => p.payment_method_type_id && p.date_of_payment && Number(p.payment) > 0);
        },
    },
    methods: {
        setStatus(id) {
            this.filterStatus = id;
            this.applyFilters();
        },
        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.applyFilters(), 350);
        },
        applyFilters() {
            const qs = queryString.stringify({
                column: 'id',
                value: this.searchValue || '',
                status_order_id: this.filterStatus ?? '',
                channel_id: this.filterChannel ?? '',
            }, { skipEmptyString: true });
            this.$eventHub.$emit('reloadDataTable', qs);
        },
        formatDate(date, part) {
            if (!date) return '';
            const m = moment(date);
            if (!m.isValid()) return '';
            return part === 'day' ? m.format('DD/MM/YYYY') : m.format('h:mm A');
        },
        avatarLetter(name) {
            if (!name || name === 'Invitado') return '?';
            return name.trim()[0].toUpperCase();
        },
        statusLabel(id) {
            const statusId = Number(id);
            const map = {
                1: 'Pago pendiente',
                2: 'Pago verificado',
                3: 'En preparación',
                4: 'Enviado',
                5: 'Cancelado',
                6: 'Entregado',
            };
            return map[statusId] || '-';
        },
        channelIcon(type) {
            const icons = { ecommerce: '🛒', pos: '🏪', whatsapp: '💬', phone: '📞', marketplace: '🏬', social: '📱', other: '📦' };
            return icons[type] || '📦';
        },
        statusBadgeClass(id) {
            return `eco-badge--${Number(id) || 0}`;
        },
        /**
         * Retorna las opciones del `el-select` filtradas según las transiciones
         * permitidas desde el estado actual. Debe reflejar el mapa del backend
         * en OrderController::updateStatusOrders:
         *   1 → [2, 5]   (Pendiente → Pago verificado / Cancelado)
         *   2 → [3, 5]   (Pago verificado → En preparación / Cancelado)
         *   3 → [4, 5]   (En preparación → Despachado / Cancelado)
         *   4 → [6, 5]   (Despachado → Entregado / Cancelado)
         *   5, 6 → []    (finales, solo lectura)
         */
        allowedOptionsFor(currentStatusId) {
            const allowed = {
                1: [2, 5],
                2: [3, 5],
                3: [4, 5],
                4: [6, 5],
                5: [],
                6: [],
            };
            const targets = allowed[Number(currentStatusId)] ?? [];
            if (!targets.length || !Array.isArray(this.options)) return [];
            return this.options.filter(o => targets.includes(Number(o.id)));
        },
        /**
         * Acción principal del pedido según estado actual.
         * Devuelve {target, label, type} o null si el estado es final.
         */
        primaryActionFor(currentStatusId) {
            const map = {
                1: { target: 2, label: 'Verificar pago',  type: 'primary' },
                2: { target: 3, label: 'Preparar',        type: 'primary' },
                3: { target: 4, label: 'Despachar',       type: 'success' },
                4: { target: 6, label: 'Marcar entregado', type: 'success' },
            };
            return map[Number(currentStatusId)] || null;
        },
        canCancel(currentStatusId) {
            return [1, 2, 3, 4].includes(Number(currentStatusId));
        },
        async confirmCancel(row) {
            try {
                const { value: reason } = await this.$prompt(
                    'Escribe el motivo de la cancelación (opcional):',
                    'Cancelar pedido',
                    {
                        confirmButtonText: 'Cancelar pedido',
                        cancelButtonText: 'No, volver',
                        inputPattern: /.*/,
                        inputPlaceholder: 'ej: cliente pidió cancelar',
                        type: 'warning',
                    }
                );
                const previousStatusId = Number(row.status_order_id);
                row.status_order_id = 5;
                this.record = row;
                try {
                    await this.$http.post('/statusOrder/update', {
                        record: row,
                        cancel_reason: reason || ''
                    });
                    this.$message.success('Pedido cancelado');
                    this.$eventHub.$emit('reloadDataTable');
                    this.$http.get('/orders/stats').then(r => { this.stats = r.data; });
                } catch (err) {
                    row.status_order_id = previousStatusId;
                    this.$message.error(err?.response?.data?.message || 'No se pudo cancelar.');
                }
            } catch (_) {
                // usuario canceló el prompt — no-op
            }
        },
        rowClass(row) {
            return Number(row.status_order_id) === 3 ? 'eco-row--dispatched' : '';
        },
        paymentClass(ref) {
            if (!ref) return '';
            const r = ref.toLowerCase();
            if (r.includes('efectivo')) return 'eco-payment--efectivo';
            if (r.includes('culqi') || r.includes('tarjeta') || r.includes('visa')) return 'eco-payment--culqi';
            if (r.includes('paypal')) return 'eco-payment--paypal';
            return '';
        },
        subtotal(item) {
            if (item.currency_type_id === 'USD') {
                const s = Number(item.cantidad * item.exchange_rate_sale * parseFloat(item.sale_unit_price)).toFixed(2);
                return isNaN(s) ? '—' : s;
            }
            return parseFloat(item.cantidad * item.sale_unit_price).toFixed(2);
        },
        clickOptions(recordId) {
            this.documentNewId = recordId;
            this.statusDocument.send = "";
            this.resource_options = "sale-notes";
            this.showDialogOptions = true;
        },
        async clickDownload(row) {
            await this.$http.get(`/documents/search/externalId/${row}`).then(r => { this.documentNewId = r.data.id; });
            this.statusDocument.send = "";
            this.resource_options = "documents";
            this.showDialogOptions = true;
        },
        /**
         * Abre el PDF de la Nota de Venta en una pestaña nueva.
         * Usa la ruta `/sale-notes/print/{external_id}/{format}` que ya existe
         * en el sistema (SaleNoteController@toPrint).
         */
        printSaleNote(externalId, format = 'a4') {
            if (!externalId) return;
            const url = `/sale-notes/print/${externalId}/${format}`;
            window.open(url, '_blank');
        },
        optionDisable(product, stock) {
            for (let i = 0; i < this.record.items.length; i++) {
                if (product === this.record.items[i].id) return stock >= this.record.items[i].cantidad ? false : true;
            }
        },
        openDialogSaleNote(sale_note) { this.dataSaleNote = sale_note; this.showDialogSaleNote = true; },
        // ── Modal "Verificar pago" ────────────────────────────────────
        async openPaymentVerificationDialog(record) {
            const total = Number(record.total) || 0;
            this.paymentDlg = {
                loading: true,
                orderId: record.id,
                orderIdPad: String(record.id).padStart(6, '0'),
                record: record,
                total: total,
                methods: [],
                destinations: [],
                cardBrands: [],
                payments: [],
            };
            this.showDialogPayment = true;

            try {
                const r = await this.$http.get('/orders/payment-catalogs');
                this.paymentDlg.methods = r.data.payment_method_types || [];
                this.paymentDlg.destinations = (r.data.payment_destinations || []).map(d => ({ id: d.id ?? 'cash', description: d.description }));
                this.paymentDlg.cardBrands = r.data.card_brands || [];
                // Prepagar una fila con método efectivo por el total completo
                const today = new Date().toISOString().slice(0, 10);
                const defaultMethod = this.paymentDlg.methods.find(m => m.id === '01')?.id || this.paymentDlg.methods[0]?.id;
                const defaultDest = String(this.paymentDlg.destinations[0]?.id || 'cash');
                this.paymentDlg.payments = [{
                    date_of_payment: today,
                    payment_method_type_id: defaultMethod,
                    payment_destination_id: defaultDest,
                    reference: null,
                    payment: total,
                    has_card: false,
                    card_brand_id: null,
                }];
            } catch (err) {
                this.$message.error('No se pudieron cargar los catálogos de pago');
            } finally {
                this.paymentDlg.loading = false;
            }
        },
        addPaymentRow() {
            const today = new Date().toISOString().slice(0, 10);
            const defaultMethod = this.paymentDlg.methods[0]?.id;
            const defaultDest = String(this.paymentDlg.destinations[0]?.id || 'cash');
            this.paymentDlg.payments.push({
                date_of_payment: today,
                payment_method_type_id: defaultMethod,
                payment_destination_id: defaultDest,
                reference: null,
                payment: Math.max(0, this.paymentsBalance),
                has_card: false,
                card_brand_id: null,
            });
        },
        removePaymentRow(idx) {
            this.paymentDlg.payments.splice(idx, 1);
        },
        distributeTotal() {
            const n = this.paymentDlg.payments.length;
            if (!n) return;
            const per = Number((this.paymentDlg.total / n).toFixed(2));
            this.paymentDlg.payments.forEach((p, i) => {
                p.payment = i === n - 1
                    ? Number((this.paymentDlg.total - per * (n - 1)).toFixed(2))
                    : per;
            });
        },
        onChangePaymentMethod(payment) {
            // '02'=Crédito, '03'=Débito (tarjetas) → marca has_card
            payment.has_card = ['02', '03'].includes(String(payment.payment_method_type_id));
            if (!payment.has_card) payment.card_brand_id = null;
        },
        async confirmPaymentVerification() {
            if (!this.canConfirmPayment) return;
            const record = this.paymentDlg.record;
            const payload = {
                record: { ...record, status_order_id: 2 },
                payments: this.paymentDlg.payments.map(p => ({
                    date_of_payment: p.date_of_payment,
                    payment_method_type_id: p.payment_method_type_id,
                    payment_destination_id: p.payment_destination_id,
                    reference: p.reference,
                    payment: Number(p.payment),
                    has_card: !!p.has_card,
                    card_brand_id: p.card_brand_id,
                })),
            };
            try {
                const r = await this.$http.post('/statusOrder/update', payload);
                this.$message.success(r.data.message || 'Pago verificado y comprobante generado');
                this.showDialogPayment = false;
                this.$eventHub.$emit('reloadDataTable');
                this.$http.get('/orders/stats').then(x => { this.stats = x.data; });
            } catch (err) {
                const msg = err?.response?.data?.message || 'No se pudo verificar el pago.';
                this.$message.error(msg);
            }
        },
        openHistory(row) {
            this.history = {
                title: `Historial del pedido #${String(row.id).padStart(6, '0')}`,
                loading: true,
                logs: [],
                current: null,
                payment_status: null,
                phases: null,
            };
            this.showDialogHistory = true;
            this.$http.get(`/orders/${row.id}/status-logs`).then(r => {
                const d = r.data || {};
                this.history.logs = d.logs || [];
                this.history.current = d.current_status || null;
                this.history.payment_status = d.payment_status || null;
                this.history.phases = d.phases || null;
            }).catch(() => {
                this.$message.error('No se pudo cargar el historial del pedido.');
            }).finally(() => {
                this.history.loading = false;
            });
        },
        async updateStatus(record, value) {
            const previousStatusId = Number(record.status_order_id);
            const statusId = Number(value);

            if (!Number.isFinite(statusId) || statusId === previousStatusId) {
                return;
            }

            // Optimistic UI update
            record.status_order_id = statusId;
            this.record = record;
            this.record.status_order_id = statusId;

            try {
                if (statusId === 2) {
                    // 1→2 (Verificar pago): abre modal de pagos (método, banco, fecha,
                    // referencia). Los pagos se guardan como OrderPayments y se copian
                    // al SaleNotePayment al generar el comprobante automáticamente.
                    record.status_order_id = previousStatusId; // revertir optimistic
                    this.openPaymentVerificationDialog(record);
                    return;
                }
                // Resto de transiciones (2→3, 3→4, 4→6, *→5): directo sin modal.
                // 3→4 (Despachar) descuenta stock físico automático en backend
                // usando warehouse del pedido (OrderService::buildDiscountItemsFromOrder).
                await this.saveUpdateStatus();
            } catch (error) {
                record.status_order_id = previousStatusId;
                const msg = error?.response?.data?.message || "No se pudo actualizar el estado.";
                this.$message.error(msg);
                // Recargar la tabla para re-sincronizar con el backend.
                // Si el UI tenía un estado stale (ej. por un intento previo fallido
                // que no se revirtió), esto lo corrige.
                this.$eventHub.$emit('reloadDataTable');
            }

            // Refresh stats
            this.$http.get('/orders/stats').then(r => { this.stats = r.data; });
        },
        saveUpdateStatus() {
            return this.$http.post('/statusOrder/update', { record: this.record }).then(r => {
                this.$message.success(r.data.message);
                this.$eventHub.$emit('reloadDataTable');
            });
        },
        async save() {
            const save = [];
            for (let i = 0; i < this.record.items.length; i++) {
                if (this.totalProduct[i] === this.record.items[i].id) {
                    save.push({ id: this.form[this.totalProduct[i]], cantidad: this.record.items[i].cantidad });
                }
            }
            await this.$http.post('/statusOrder/update', { record: this.record, discount: save }).then(r => {
                this.$message.success(r.data.message);
                this.$eventHub.$emit('reloadDataTable');
                this.close();
            });
        },
        close() { this.form = []; this.showDialog = false; this.record = ""; },
        products(products) { return products.items.map(i => i.id); },
        async events() {
            await this.$eventHub.$on("cancelSale", () => { this.showDialogOptions = false; });
        },
        getHeaderConfig() {
            return { headers: { "Content-Type": "application/json", Authorization: `Bearer ${this.user.api_token}` } };
        }
    }
};
</script>

