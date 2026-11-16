<template>
    <el-dialog
        title="Vista 360° del Cliente"
        :visible.sync="showDialog"
        width="min(920px, 96vw)"
        top="5vh"
        :close-on-click-modal="false"
        @open="load"
        @close="reset"
    >
        <div v-loading="loading">

            <!-- Resumen del cliente -->
            <template v-if="data">
                <div class="customer-360-header">
                    <div class="customer-360-name">
                        <i class="fas fa-user-circle"></i>
                        <div>
                            <strong>{{ data.person.name }}</strong>
                            <span class="customer-360-doc">{{ data.person.number }}</span>
                        </div>
                    </div>
                    <div class="customer-360-contact">
                        <span v-if="data.person.created_at"><i class="fas fa-user-plus"></i> Cliente desde {{ data.person.created_at }}</span>
                        <span v-if="data.person.email"><i class="fas fa-envelope"></i> {{ data.person.email }}</span>
                        <span v-if="data.person.telephone"><i class="fas fa-phone"></i> {{ data.person.telephone }}</span>
                        <span v-for="d in (data.destinations || []).slice(0, 2)" :key="d.label">
                            <i class="fas fa-truck"></i> {{ d.label }}<template v-if="d.count > 1"> ({{ d.count }})</template>
                        </span>
                    </div>
                </div>

                <!-- Insignias de sorteos ganados. Clic → ficha del premio. -->
                <div class="raffle-badges" v-if="(data.raffle_wins || []).length > 0">
                    <button
                        v-for="win in data.raffle_wins"
                        :key="win.id"
                        type="button"
                        class="raffle-badge"
                        :class="{ 'is-open': openWin === win.id }"
                        @click="openWin = (openWin === win.id ? null : win.id)"
                    >
                        🏆 Ganador del Sorteo "{{ win.raffle_name }}"
                    </button>

                    <div v-for="win in data.raffle_wins" :key="'d-' + win.id" v-show="openWin === win.id" class="raffle-detail">
                        <img v-if="win.prize_image" :src="win.prize_image" class="raffle-detail__img" alt="">
                        <div class="raffle-detail__body">
                            <div class="raffle-detail__row"><span>Sorteo</span><strong>{{ win.raffle_name }} ({{ win.raffle_code }})</strong></div>
                            <div class="raffle-detail__row"><span>Fecha</span><strong>{{ win.drawn_at || '—' }}</strong></div>
                            <div class="raffle-detail__row"><span>Premio</span><strong>{{ win.prize_name || '—' }}</strong></div>
                            <div class="raffle-detail__row">
                                <span>Estado del premio</span>
                                <strong :class="win.delivery_status === 'delivered' ? 'text-success' : 'text-warning'">
                                    {{ win.delivery_label }}<template v-if="win.delivered_at"> · {{ win.delivered_at }}</template>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Periodo: filtra ventas, totales, productos y el PDF. -->
                <div class="c360-period">
                    <span class="c360-period__label"><i class="el-icon-date"></i> Periodo</span>
                    <el-date-picker
                        v-model="period"
                        type="daterange"
                        size="small"
                        unlink-panels
                        range-separator="al"
                        start-placeholder="Desde"
                        end-placeholder="Hasta"
                        value-format="yyyy-MM-dd"
                        format="dd/MM/yyyy"
                        :picker-options="periodOptions"
                        @change="load"
                    />
                    <span class="text-muted c360-period__hint">{{ period ? 'Mostrando solo ese periodo' : 'Todo el historial' }}</span>
                </div>

                <!-- KPIs -->
                <div class="customer-360-kpis">
                    <div class="kpi-card kpi-blue">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.total_lifetime) }}</div>
                        <div class="kpi-label">Total comprado</div>
                    </div>
                    <div class="kpi-card kpi-green">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.total_paid) }}</div>
                        <div class="kpi-label">Pagado</div>
                    </div>
                    <div class="kpi-card" :class="data.summary.total_pending > 0 ? 'kpi-red' : 'kpi-gray'">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.total_pending) }}</div>
                        <div class="kpi-label">Pendiente de pago<template v-if="data.summary.pending_count"> ({{ data.summary.pending_count }})</template></div>
                    </div>
                    <template v-if="data.summary.annulled_paid > 0">
                        <div class="kpi-card kpi-green">
                            <div class="kpi-value">S/ {{ formatAmount(data.summary.annulled_paid) }}</div>
                            <div class="kpi-label">Cobrado en anuladas (a favor)</div>
                        </div>
                        <div class="kpi-card" :class="data.summary.net_pending > 0 ? 'kpi-red' : 'kpi-green'">
                            <div class="kpi-value">S/ {{ formatAmount(Math.abs(data.summary.net_pending)) }}</div>
                            <div class="kpi-label">{{ data.summary.net_pending >= 0 ? 'Saldo neto por cobrar' : 'Saldo a favor del cliente' }}</div>
                        </div>
                    </template>
                    <div class="kpi-card kpi-red" v-if="data.summary.payable_pending > 0">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.payable_pending) }}</div>
                        <div class="kpi-label">Por pagar (es proveedor)</div>
                    </div>
                    <div class="kpi-card kpi-amber">
                        <div class="kpi-value">{{ data.summary.doc_count + data.summary.sn_count + (data.summary.orders_count || 0) }}</div>
                        <div class="kpi-label">Compras</div>
                    </div>
                    <div class="kpi-card kpi-purple">
                        <div class="kpi-value">{{ data.summary.quotations_count || 0 }}</div>
                        <div class="kpi-label">Cotizaciones</div>
                    </div>
                    <div class="kpi-card kpi-red" v-if="data.summary.overdue_count > 0">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.overdue_amount) }}</div>
                        <div class="kpi-label">Mora ({{ data.summary.overdue_count }} doc)</div>
                    </div>
                    <div class="kpi-card kpi-gray">
                        <div class="kpi-value kpi-value--sm">{{ data.summary.last_purchase || '—' }}</div>
                        <div class="kpi-label">Última compra</div>
                    </div>
                </div>

                <el-alert v-if="data.summary.annulled_paid > 0" type="warning" :closable="false" show-icon class="mt-2"
                    :title="`Hay S/ ${formatAmount(data.summary.annulled_paid)} cobrados en ventas que luego se anularon. Si no se devolvieron, están a favor del cliente y se descuentan de lo que debe.`" />

                <!-- Pestañas. Cada venta se despliega (flecha) para ver productos y pagos. -->
                <el-tabs v-model="activeTab" class="mt-3">
                    <el-tab-pane :label="`Notas de venta (${data.sale_notes.length})`" name="sale_notes">
                        <el-table :data="data.sale_notes" size="mini" stripe style="width:100%" :row-class-name="annulledRow">
                            <el-table-column type="expand">
                                <template slot-scope="s"><history-detail :row="s.row" /></template>
                            </el-table-column>
                            <el-table-column label="Nota" min-width="95">
                                <template slot-scope="s">
                                    <a href="#" class="c360-doc" title="Ver PDF" @click.prevent.stop="openPdf(s.row)"><i class="el-icon-document"></i> {{ s.row.label }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column prop="date"  label="Fecha" min-width="90" />
                            <el-table-column label="Cotización → Comprobante" min-width="150">
                                <template slot-scope="s">
                                    <span v-if="s.row.from_quotation" class="c360-link">{{ s.row.from_quotation }} →</span>
                                    <el-tag v-for="d in s.row.to_documents" :key="d.label" size="mini"
                                        :type="d.valid ? 'success' : 'info'" class="c360-link-tag">{{ d.label }}</el-tag>
                                    <span v-if="!s.row.from_quotation && !s.row.to_documents.length" class="text-muted">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Total" min-width="85" align="right">
                                <template slot-scope="s"><strong>{{ formatAmount(s.row.total) }}</strong></template>
                            </el-table-column>
                            <el-table-column label="Pagado" min-width="85" align="right">
                                <template slot-scope="s"><span class="text-success">{{ formatAmount(s.row.paid) }}</span></template>
                            </el-table-column>
                            <el-table-column label="Saldo" min-width="85" align="right">
                                <template slot-scope="s">
                                    <strong v-if="s.row.pending > 0" class="text-danger">{{ formatAmount(s.row.pending) }}</strong>
                                    <span v-else class="text-muted">0.00</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Estado" min-width="100" align="center">
                                <template slot-scope="s">
                                    <el-tag :type="payTag(s.row)" size="mini">{{ payLabel(s.row) }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="data.sale_notes.length === 0" class="text-center text-muted py-3">Sin notas de venta registradas.</div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Facturas/Boletas (${data.documents.length})`" name="documents">
                        <el-table :data="data.documents" size="mini" stripe style="width:100%" :row-class-name="annulledRow">
                            <el-table-column type="expand">
                                <template slot-scope="s"><history-detail :row="s.row" /></template>
                            </el-table-column>
                            <el-table-column label="Comprobante" min-width="105">
                                <template slot-scope="s">
                                    <a href="#" class="c360-doc" title="Ver PDF" @click.prevent.stop="openPdf(s.row)"><i class="el-icon-document"></i> {{ s.row.label }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column prop="type"  label="Tipo"  min-width="70" />
                            <el-table-column label="Viene de" min-width="110">
                                <template slot-scope="s">
                                    <span v-if="s.row.from_quotation" class="c360-link">{{ s.row.from_quotation }}</span>
                                    <span v-if="s.row.from_quotation && s.row.from_sale_note"> → </span>
                                    <span v-if="s.row.from_sale_note" class="c360-link">{{ s.row.from_sale_note }}</span>
                                    <span v-if="!s.row.from_quotation && !s.row.from_sale_note" class="text-muted">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="date"  label="Fecha" min-width="90" />
                            <el-table-column label="Total" min-width="85" align="right">
                                <template slot-scope="s"><strong>{{ formatAmount(s.row.total) }}</strong></template>
                            </el-table-column>
                            <el-table-column label="Saldo" min-width="85" align="right">
                                <template slot-scope="s">
                                    <strong v-if="s.row.pending > 0" class="text-danger">{{ formatAmount(s.row.pending) }}</strong>
                                    <span v-else class="text-muted">0.00</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Estado" min-width="100" align="center">
                                <template slot-scope="s">
                                    <el-tag :type="payTag(s.row)" size="mini">{{ payLabel(s.row) }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="data.documents.length === 0" class="text-center text-muted py-3">Sin comprobantes registrados.</div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Cotizaciones (${(data.quotations || []).length})`" name="quotations">
                        <el-table :data="data.quotations || []" size="mini" stripe style="width:100%">
                            <el-table-column type="expand">
                                <template slot-scope="s"><history-detail :row="s.row" /></template>
                            </el-table-column>
                            <el-table-column label="Cotización" min-width="95">
                                <template slot-scope="s">
                                    <a href="#" class="c360-doc" title="Ver PDF" @click.prevent.stop="openPdf(s.row)"><i class="el-icon-document"></i> {{ s.row.label }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column prop="date"  label="Fecha" min-width="90" />
                            <el-table-column label="Total" min-width="85" align="right">
                                <template slot-scope="s"><strong>{{ formatAmount(s.row.total) }}</strong></template>
                            </el-table-column>
                            <el-table-column label="Pasó a" min-width="130" align="center">
                                <template slot-scope="s">
                                    <el-tag v-if="s.row.state === '11'" type="info" size="mini">Anulada</el-tag>
                                    <template v-else-if="s.row.to_sales.length">
                                        <el-tag v-for="v in s.row.to_sales" :key="v.label" size="mini"
                                            :type="v.valid ? 'success' : 'info'" class="c360-link-tag">{{ v.label }}</el-tag>
                                    </template>
                                    <el-tag v-else-if="s.row.converted" type="success" size="mini">Se convirtió en venta</el-tag>
                                    <el-tag v-else type="warning" size="mini">Sin venta</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="!(data.quotations || []).length" class="text-center text-muted py-3">Sin cotizaciones registradas.</div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Compras / por pagar (${(data.purchases || []).length})`" name="purchases" v-if="(data.purchases || []).length > 0">
                        <el-table :data="data.purchases" size="mini" stripe style="width:100%" :row-class-name="annulledRow">
                            <el-table-column type="expand">
                                <template slot-scope="s"><history-detail :row="s.row" /></template>
                            </el-table-column>
                            <el-table-column label="Compra" min-width="105">
                                <template slot-scope="s">
                                    <a href="#" class="c360-doc" title="Ver PDF" @click.prevent.stop="openPdf(s.row)"><i class="el-icon-document"></i> {{ s.row.label }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column prop="date"  label="Fecha"  min-width="90" />
                            <el-table-column label="Total" min-width="85" align="right">
                                <template slot-scope="s"><strong>{{ formatAmount(s.row.total) }}</strong></template>
                            </el-table-column>
                            <el-table-column label="Por pagar" min-width="85" align="right">
                                <template slot-scope="s">
                                    <strong v-if="s.row.pending > 0" class="text-danger">{{ formatAmount(s.row.pending) }}</strong>
                                    <span v-else class="text-muted">0.00</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Estado" min-width="100" align="center">
                                <template slot-scope="s">
                                    <el-tag :type="payTag(s.row)" size="mini">{{ payLabel(s.row) }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane :label="`Pedidos (${(data.orders || []).length})`" name="orders" v-if="(data.orders || []).length > 0">
                        <el-table :data="data.orders" size="mini" stripe style="width:100%" :row-class-name="cancelledRow">
                            <el-table-column type="expand">
                                <template slot-scope="s"><history-detail :row="s.row" /></template>
                            </el-table-column>
                            <el-table-column prop="number" label="Pedido" min-width="80" />
                            <el-table-column prop="date"   label="Fecha"  min-width="90" />
                            <el-table-column prop="channel" label="Canal" min-width="110" />
                            <el-table-column label="Estado" min-width="110" align="center">
                                <template slot-scope="s">
                                    <el-tag :type="s.row.cancelled ? 'info' : 'primary'" size="mini">{{ s.row.status }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Total" min-width="95" align="right">
                                <template slot-scope="s">
                                    <strong>{{ formatAmount(s.row.total) }}</strong>
                                    <div v-if="s.row.shipping_extra > 0" class="c360-sub">incl. envío {{ formatAmount(s.row.shipping_extra) }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Pagado" min-width="85" align="right">
                                <template slot-scope="s"><span class="text-success">{{ formatAmount(s.row.paid) }}</span></template>
                            </el-table-column>
                            <el-table-column label="Saldo" min-width="80" align="right">
                                <template slot-scope="s">
                                    <strong v-if="s.row.pending > 0" class="text-danger">{{ formatAmount(s.row.pending) }}</strong>
                                    <span v-else class="text-muted">0.00</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Cobro" min-width="105" align="center">
                                <template slot-scope="s">
                                    <el-tag :type="orderPayTag(s.row)" size="mini">{{ s.row.pay_state }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Envío / Venta" min-width="150">
                                <template slot-scope="s">
                                    <div v-if="s.row.shipment" class="c360-link">{{ s.row.shipment }}</div>
                                    <div v-if="s.row.document" class="c360-link">→ {{ s.row.document }}</div>
                                    <span v-if="!s.row.shipment && !s.row.document" class="text-muted">—</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <p class="c360-note">
                            Un pedido que ya generó su nota de venta o comprobante se cuenta una sola vez, en esa venta.
                        </p>
                    </el-tab-pane>

                    <el-tab-pane :label="`Envíos y destinos (${(data.shipments || []).length})`" name="shipments" v-if="(data.shipments || []).length > 0">
                        <div class="c360-destinations" v-if="(data.destinations || []).length">
                            <strong>Destinos:</strong>
                            <el-tag v-for="d in data.destinations" :key="d.label" size="small" type="info" class="c360-link-tag">
                                <i class="el-icon-location-outline"></i> {{ d.label }}<template v-if="d.count > 1"> ({{ d.count }})</template>
                            </el-tag>
                        </div>
                        <div v-for="e in data.shipments" :key="e.code" class="c360-ship" :class="{ 'is-cancelled': e.cancelled }">
                            <div class="c360-ship__head">
                                <strong>{{ e.code }}</strong>
                                <span class="text-muted">{{ e.date }}</span>
                                <el-tag size="mini" :type="e.delivery_short === 'Lima' ? 'warning' : (e.delivery_short === 'Recojo' ? 'info' : 'primary')">{{ e.delivery_type }}</el-tag>
                                <el-tag size="mini" :type="e.cancelled ? 'info' : (e.status === 'Entregado' ? 'success' : '')">{{ e.status }}</el-tag>
                            </div>
                            <div class="c360-ship__grid">
                                <div><span>Destino</span>{{ e.place || '—' }}</div>
                                <div v-if="e.address"><span>Dirección</span>{{ e.address }}</div>
                                <div v-if="e.agency"><span>Agencia</span>{{ e.agency }}</div>
                                <div v-if="e.office"><span>Oficina</span>{{ e.office }}</div>
                                <div><span>Destinatario</span>{{ e.recipient || '—' }}<template v-if="e.recipient_doc"> · {{ e.recipient_doc }}</template></div>
                                <div v-if="e.phone"><span>Teléfono</span>{{ e.phone }}</div>
                                <div v-if="e.pickup_person"><span>Recoge</span>{{ e.pickup_person }}</div>
                                <div v-if="e.tracking"><span>Guía / tracking</span><strong>{{ e.tracking }}</strong></div>
                                <div v-if="e.amount"><span>Monto del encargo</span>S/ {{ formatAmount(e.amount) }}</div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Mora (${(data.overdue_docs || []).length})`" name="overdue" v-if="(data.overdue_docs || []).length > 0">
                        <el-table :data="data.overdue_docs" size="mini" stripe style="width:100%">
                            <el-table-column prop="label"       label="Comprobante" min-width="120" />
                            <el-table-column prop="date_of_due" label="Vence"       min-width="110" />
                            <el-table-column prop="days_late"   label="Días" min-width="70" align="center">
                                <template slot-scope="s">
                                    <el-tag type="danger" size="mini">{{ Math.abs(s.row.days_late) }}d</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="total"       label="Importe"     min-width="110" align="right">
                                <template slot-scope="s">
                                    <strong class="text-danger">{{ formatAmount(s.row.total) }}</strong>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane :label="`Devoluciones (${(data.returns || []).length})`" name="returns" v-if="(data.returns || []).length > 0">
                        <el-table :data="data.returns" size="mini" stripe style="width:100%">
                            <el-table-column prop="id"            label="ID"     width="60"  />
                            <el-table-column prop="sale_note_id"  label="NV"     width="80"  />
                            <el-table-column prop="status_label"  label="Estado" width="120" />
                            <el-table-column prop="reason"        label="Motivo" />
                            <el-table-column prop="date"          label="Fecha"  width="100" />
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane :label="`Productos más comprados (${(data.top_products || []).length})`" name="products">
                        <el-table :data="data.top_products || []" size="mini" stripe style="width:100%">
                            <el-table-column label="Código" min-width="100">
                                <template slot-scope="s"><strong>{{ s.row.code || '—' }}</strong></template>
                            </el-table-column>
                            <el-table-column prop="description" label="Producto" min-width="200" />
                            <el-table-column label="Cantidad" min-width="80" align="right">
                                <template slot-scope="s">{{ s.row.quantity }}</template>
                            </el-table-column>
                            <el-table-column label="Monto" min-width="90" align="right">
                                <template slot-scope="s"><strong>{{ formatAmount(s.row.total) }}</strong></template>
                            </el-table-column>
                            <el-table-column prop="sales" label="Ventas" min-width="65" align="center" />
                            <el-table-column prop="last_date" label="Última vez" min-width="95" />
                        </el-table>
                        <div v-if="!(data.top_products || []).length" class="text-center text-muted py-3">Sin ventas vigentes en el periodo.</div>
                        <p class="c360-note">Solo ventas vigentes: sin anuladas y sin contar dos veces una nota convertida en comprobante.</p>
                    </el-tab-pane>

                    <el-tab-pane label="Registro y cambios" name="activity">
                        <ul class="c360-timeline">
                            <li v-for="(a, i) in data.activity || []" :key="i">
                                <div class="c360-timeline__head">
                                    <strong>{{ activityLabel(a.action) }}</strong>
                                    <span>{{ a.date }}<template v-if="a.user"> · {{ a.user }}</template></span>
                                </div>
                                <ul v-if="a.changes && a.changes.length" class="c360-timeline__changes">
                                    <li v-for="c in a.changes" :key="c.field">
                                        {{ fieldLabel(c.field) }}:
                                        <del>{{ emptyText(c.old) }}</del> → <ins>{{ emptyText(c.new) }}</ins>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <p class="c360-note">
                            Quién registró al cliente y los cambios en sus datos se guardan desde el 16/11/2026.
                            De los clientes anteriores solo se conoce la fecha de registro.
                        </p>
                    </el-tab-pane>
                </el-tabs>
            </template>

        </div>

        <span slot="footer">
            <el-button type="primary" icon="el-icon-printer" :disabled="!data" @click="printPdf">Reporte PDF</el-button>
            <el-button @click="showDialog = false">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
import HistoryDetail from './history360_detail.vue'

// [primer día, último día] del mes actual + offset (0 = este mes, -1 = anterior).
function monthRange(offset) {
    const now = new Date()
    return [
        new Date(now.getFullYear(), now.getMonth() + offset, 1),
        new Date(now.getFullYear(), now.getMonth() + offset + 1, 0),
    ]
}

const FIELD_LABELS = {
    name: 'Nombre', trade_name: 'Nombre comercial', number: 'N° de documento',
    identity_document_type_id: 'Tipo de documento', email: 'Correo', telephone: 'Teléfono',
    address: 'Dirección', department_id: 'Departamento', province_id: 'Provincia',
    district_id: 'Distrito', country_id: 'País', enabled: 'Habilitado',
    credit_days: 'Días de crédito', observation: 'Observación', contact: 'Contacto',
    person_type_id: 'Tipo de cliente', seller_id: 'Vendedor', status: 'Estado',
}

export default {
    components: { HistoryDetail },
    props: {
        showDialog: { type: Boolean, default: false },
        personId:   { type: Number, default: null },
    },
    data() {
        return {
            loading:   false,
            data:      null,
            activeTab: 'sale_notes',
            openWin:   null,   // id del sorteo ganado cuya ficha está desplegada
            period:    null,   // [desde, hasta] en Y-m-d, o null = todo
            periodOptions: {
                shortcuts: [
                    { text: 'Este mes', onClick: (p) => p.$emit('pick', monthRange(0)) },
                    { text: 'Mes anterior', onClick: (p) => p.$emit('pick', monthRange(-1)) },
                    { text: 'Últimos 3 meses', onClick: (p) => p.$emit('pick', [monthRange(-2)[0], monthRange(0)[1]]) },
                    { text: 'Este año', onClick: (p) => p.$emit('pick', [new Date(new Date().getFullYear(), 0, 1), new Date()]) },
                ],
            },
        }
    },
    computed: {
        periodQuery() {
            return this.period ? `?from=${this.period[0]}&to=${this.period[1]}` : ''
        },
    },
    methods: {
        async load() {
            if (!this.personId) return
            this.loading = true
            try {
                const { data } = await this.$http.get(`/persons/${this.personId}/history${this.periodQuery}`)
                this.data = data
                // Al cambiar de periodo se queda en la pestaña elegida.
                if (this.period) return
                // Abre la primera pestaña que tenga contenido.
                this.activeTab = data.sale_notes.length ? 'sale_notes'
                    : data.documents.length ? 'documents'
                    : (data.orders || []).length ? 'orders'
                    : (data.quotations || []).length ? 'quotations'
                    : 'activity'
            } catch(e) {
                this.$message.error('No se pudo cargar el historial del cliente.')
            } finally {
                this.loading = false
            }
        },
        reset() {
            this.data      = null
            this.activeTab = 'sale_notes'
            this.openWin   = null
            this.period    = null
            this.$emit('update:showDialog', false)
        },
        formatAmount(v) {
            return Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })
        },
        isAnnulled(row) {
            return ['09', '11', '13'].includes(row.state)
        },
        payLabel(row) {
            if (this.isAnnulled(row)) return row.state === '09' ? 'Rechazada' : 'Anulada'
            if (row.converted) return 'Pasó a comprobante'
            if (row.pending <= 0) return 'Pagada'
            return row.paid > 0 ? 'Pago parcial' : 'Pendiente'
        },
        payTag(row) {
            if (this.isAnnulled(row)) return 'info'
            if (row.converted || row.pending <= 0) return 'success'
            return row.paid > 0 ? 'warning' : 'danger'
        },
        cancelledRow({ row }) {
            return row.cancelled ? 'c360-annulled' : ''
        },
        orderPayTag(row) {
            return { 'Anulado': 'info', 'Pagado': 'success', 'Pago parcial': 'warning', 'Pago pendiente': 'danger' }[row.pay_state] || 'info'
        },
        annulledRow({ row }) {
            return this.isAnnulled(row) ? 'c360-annulled' : ''
        },
        activityLabel(action) {
            return { create: 'Registro del cliente', update: 'Cambio de datos', delete: 'Eliminado' }[action] || action
        },
        printPdf() {
            window.open(`/persons/${this.personId}/history/pdf${this.periodQuery}`, '_blank')
        },
        openPdf(row) {
            if (row.pdf) window.open(row.pdf, '_blank')
        },
        fieldLabel(field) {
            return FIELD_LABELS[field] || field
        },
        emptyText(v) {
            return v === null || v === undefined || v === '' ? 'vacío' : v
        },
    },
}
</script>

<style scoped>
/* ── Insignias de sorteos ganados ─────────────────────────────── */
.raffle-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}
.raffle-badge {
    border: 1px solid #fde3b0;
    background: linear-gradient(135deg, #fef6e7, #fffdf7);
    color: #b45309;
    font-size: 12px;
    font-weight: 700;
    border-radius: 999px;
    padding: 5px 12px;
    cursor: pointer;
    transition: .15s;
}
.raffle-badge:hover, .raffle-badge.is-open {
    background: #fdecc8;
    border-color: #f5c775;
}
.raffle-detail {
    flex: 0 0 100%;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: #fffdf7;
    border: 1px solid #fde3b0;
    border-radius: 10px;
    padding: 10px 12px;
}
.raffle-detail__img {
    width: 78px;
    height: 78px;
    object-fit: cover;
    border-radius: 9px;
    border: 1px solid #fde3b0;
}
.raffle-detail__body { flex: 1; min-width: 0; }
.raffle-detail__row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    padding: 3px 0;
    border-bottom: 1px solid #fdf3e0;
}
.raffle-detail__row:last-child { border-bottom: 0; }
.raffle-detail__row span { color: #92764a; }

.customer-360-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    gap: 12px;
}
.customer-360-name {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
}
.customer-360-name i { font-size: 28px; color: #6366f1; }
.customer-360-doc { display: block; font-size: 12px; color: #64748b; }
.customer-360-contact { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #475569; }
.customer-360-contact span { display: flex; align-items: center; gap: 5px; }
.customer-360-kpis {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
.kpi-card {
    flex: 1;
    min-width: 110px;
    border-radius: 8px;
    padding: 10px 14px;
    text-align: center;
    border: 1px solid;
}
.kpi-value { font-size: 18px; font-weight: 700; }
.kpi-value--sm { font-size: 13px; }
.kpi-label { font-size: 11px; margin-top: 2px; opacity: .8; }
.kpi-blue   { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.kpi-green  { background:#f0fdf4; border-color:#bbf7d0; color:#15803d; }
.kpi-purple { background:#faf5ff; border-color:#e9d5ff; color:#7e22ce; }
.kpi-amber  { background:#fffbeb; border-color:#fde68a; color:#92400e; }
.kpi-cyan   { background:#ecfeff; border-color:#a5f3fc; color:#0e7490; }
.kpi-red    { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
.kpi-gray   { background:#f8fafc; border-color:#e2e8f0; color:#475569; }

/* ── Registro y cambios ───────────────────────────────────────── */
.c360-timeline { list-style: none; margin: 0; padding: 0 0 0 14px; border-left: 2px solid #e2e8f0; }
.c360-timeline > li { position: relative; padding: 0 0 12px 10px; }
.c360-timeline > li::before {
    content: '';
    position: absolute;
    left: -21px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6366f1;
}
.c360-timeline__head { display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 13px; }
.c360-timeline__head span { color: #64748b; font-size: 12px; }
.c360-timeline__changes { margin: 4px 0 0; padding-left: 16px; font-size: 12px; color: #334155; }
.c360-timeline__changes del { color: #b91c1c; }
.c360-timeline__changes ins { color: #15803d; text-decoration: none; }
.c360-link { font-size: 12px; color: #4f46e5; }
.c360-doc { font-weight: 600; color: #4f46e5; white-space: nowrap; }
.c360-sub { font-size: 10px; color: #64748b; }
.c360-destinations { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-bottom: 10px; font-size: 13px; }
.c360-ship { border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; }
.c360-ship.is-cancelled { opacity: .55; }
.c360-ship__head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 13px; }
.c360-ship__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 4px 14px; font-size: 12px; }
.c360-ship__grid span { display: block; font-size: 10px; text-transform: uppercase; color: #94a3b8; }
.c360-doc:hover { text-decoration: underline; }
.c360-period { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; }
.c360-period__label { font-size: 13px; font-weight: 600; color: #334155; }
.c360-period__hint { font-size: 12px; }
.c360-link-tag { margin: 1px 2px; }
.c360-note { font-size: 11px; color: #94a3b8; margin-top: 8px; }

/* Venta anulada: tachada, sin contar como deuda. */
::v-deep .c360-annulled td { color: #94a3b8; text-decoration: line-through; }
::v-deep .c360-annulled td .el-tag { text-decoration: none; }

@media (max-width: 640px) {
    .customer-360-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .kpi-card { min-width: calc(50% - 5px); padding: 8px 10px; }
    .kpi-value { font-size: 16px; }
}
</style>
