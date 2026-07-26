<template>
    <el-dialog
        title="Vista 360° del Cliente"
        :visible.sync="showDialog"
        width="860px"
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
                    <div class="customer-360-contact" v-if="data.person.email || data.person.telephone">
                        <span v-if="data.person.email"><i class="fas fa-envelope"></i> {{ data.person.email }}</span>
                        <span v-if="data.person.telephone"><i class="fas fa-phone"></i> {{ data.person.telephone }}</span>
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

                <!-- KPIs -->
                <div class="customer-360-kpis">
                    <div class="kpi-card kpi-blue">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.total_lifetime) }}</div>
                        <div class="kpi-label">Total histórico</div>
                    </div>
                    <div class="kpi-card kpi-green">
                        <div class="kpi-value">{{ data.summary.doc_count + data.summary.sn_count + (data.summary.orders_count || 0) }}</div>
                        <div class="kpi-label">Operaciones</div>
                    </div>
                    <div class="kpi-card kpi-purple">
                        <div class="kpi-value">{{ data.summary.doc_count }}</div>
                        <div class="kpi-label">Facturas / Boletas</div>
                    </div>
                    <div class="kpi-card kpi-amber">
                        <div class="kpi-value">{{ data.summary.sn_count }}</div>
                        <div class="kpi-label">Notas de venta</div>
                    </div>
                    <div class="kpi-card kpi-cyan" v-if="data.summary.orders_count">
                        <div class="kpi-value">{{ data.summary.orders_count }}</div>
                        <div class="kpi-label">Pedidos online</div>
                    </div>
                    <div class="kpi-card kpi-red" v-if="data.summary.overdue_count > 0">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.overdue_amount) }}</div>
                        <div class="kpi-label">Mora ({{ data.summary.overdue_count }} doc)</div>
                    </div>
                    <div class="kpi-card kpi-gray" v-if="data.summary.last_purchase">
                        <div class="kpi-value kpi-value--sm">{{ data.summary.last_purchase }}</div>
                        <div class="kpi-label">Última compra</div>
                    </div>
                </div>

                <!-- Tabs documentos / notas de venta -->
                <el-tabs v-model="activeTab" class="mt-3">
                    <el-tab-pane :label="`Facturas/Boletas (${data.documents.length})`" name="documents">
                        <el-table :data="data.documents" size="mini" stripe style="width:100%">
                            <el-table-column prop="label"    label="Comprobante" width="120" />
                            <el-table-column prop="date"     label="Fecha"       width="100" />
                            <el-table-column prop="type"     label="Tipo"        width="60"  />
                            <el-table-column prop="currency" label="Mon."        width="55"  />
                            <el-table-column prop="total"    label="Total"       width="100" align="right">
                                <template slot-scope="s">
                                    <strong>{{ formatAmount(s.row.total) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column prop="state"    label="Estado"      width="80"  align="center">
                                <template slot-scope="s">
                                    <el-tag :type="stateTag(s.row.state)" size="mini">{{ s.row.state }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="data.documents.length === 0" class="text-center text-muted py-3">Sin comprobantes registrados.</div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Notas de venta (${data.sale_notes.length})`" name="sale_notes">
                        <el-table :data="data.sale_notes" size="mini" stripe style="width:100%">
                            <el-table-column prop="label"    label="Nota"    width="120" />
                            <el-table-column prop="date"     label="Fecha"   width="100" />
                            <el-table-column prop="currency" label="Mon."    width="55"  />
                            <el-table-column prop="total"    label="Total"   width="100" align="right">
                                <template slot-scope="s">
                                    <strong>{{ formatAmount(s.row.total) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column prop="state"    label="Estado"  width="80"  align="center">
                                <template slot-scope="s">
                                    <el-tag :type="stateTag(s.row.state)" size="mini">{{ s.row.state }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="data.sale_notes.length === 0" class="text-center text-muted py-3">Sin notas de venta registradas.</div>
                    </el-tab-pane>

                    <el-tab-pane :label="`Pedidos online (${(data.orders || []).length})`" name="orders" v-if="(data.orders || []).length > 0">
                        <el-table :data="data.orders" size="mini" stripe style="width:100%">
                            <el-table-column prop="number"   label="N°"      width="120" />
                            <el-table-column prop="date"     label="Fecha"   width="100" />
                            <el-table-column prop="currency" label="Mon."    width="55"  />
                            <el-table-column prop="total"    label="Total"   width="100" align="right">
                                <template slot-scope="s">
                                    <strong>{{ formatAmount(s.row.total) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column prop="status_id" label="Estado" width="80" align="center">
                                <template slot-scope="s">
                                    <el-tag size="mini">{{ s.row.status_id }}</el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane :label="`Mora (${(data.overdue_docs || []).length})`" name="overdue" v-if="(data.overdue_docs || []).length > 0">
                        <el-table :data="data.overdue_docs" size="mini" stripe style="width:100%">
                            <el-table-column prop="label"       label="Comprobante" width="120" />
                            <el-table-column prop="date_of_due" label="Vence"       width="110" />
                            <el-table-column prop="days_late"   label="Días" width="70" align="center">
                                <template slot-scope="s">
                                    <el-tag type="danger" size="mini">{{ Math.abs(s.row.days_late) }}d</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="currency"    label="Mon."        width="55"  />
                            <el-table-column prop="total"       label="Importe"     width="110" align="right">
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
                </el-tabs>
            </template>

        </div>

        <span slot="footer">
            <el-button @click="showDialog = false">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        personId:   { type: Number, default: null },
    },
    data() {
        return {
            loading:   false,
            data:      null,
            activeTab: 'documents',
            openWin:   null,   // id del sorteo ganado cuya ficha está desplegada
        }
    },
    methods: {
        async load() {
            if (!this.personId) return
            this.loading = true
            try {
                const { data } = await this.$http.get(`/persons/${this.personId}/history`)
                this.data = data
            } catch(e) {
                this.$message.error('No se pudo cargar el historial del cliente.')
            } finally {
                this.loading = false
            }
        },
        reset() {
            this.data      = null
            this.activeTab = 'documents'
            this.openWin   = null
            this.$emit('update:showDialog', false)
        },
        formatAmount(v) {
            return Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 })
        },
        stateTag(state) {
            const map = { '01': 'success', '03': 'warning', '11': 'danger', '13': 'danger' }
            return map[state] || 'info'
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
</style>
