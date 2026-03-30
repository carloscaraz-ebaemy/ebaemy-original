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

                <!-- KPIs -->
                <div class="customer-360-kpis">
                    <div class="kpi-card kpi-blue">
                        <div class="kpi-value">S/ {{ formatAmount(data.summary.total_lifetime) }}</div>
                        <div class="kpi-label">Total histórico</div>
                    </div>
                    <div class="kpi-card kpi-green">
                        <div class="kpi-value">{{ data.summary.doc_count + data.summary.sn_count }}</div>
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
.kpi-gray   { background:#f8fafc; border-color:#e2e8f0; color:#475569; }
</style>
