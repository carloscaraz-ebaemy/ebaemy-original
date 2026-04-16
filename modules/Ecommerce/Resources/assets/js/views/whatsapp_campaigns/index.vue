<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3" style="gap:10px;flex-wrap:wrap">
            <p class="text-muted mb-0">
                Historial de envios de descuentos por WhatsApp a clientes registrados.
            </p>
            <el-button type="primary" icon="el-icon-refresh" :loading="loading" @click="load">
                Actualizar
            </el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="campaigns" v-loading="loading" style="width:100%" size="small">
                <el-table-column label="ID" prop="id" width="70"></el-table-column>
                <el-table-column label="Campaña" min-width="210">
                    <template slot-scope="s">
                        <strong>{{ s.row.name }}</strong>
                        <div class="text-muted small">Flash Sale: {{ s.row.flash_sale_title || ('#' + (s.row.flash_sale_id || '-')) }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="120" align="center">
                    <template slot-scope="s">
                        <el-tag :type="tagType(s.row.status)" size="mini">{{ s.row.status }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Clientes" width="100" align="center" prop="total_customers"></el-table-column>
                <el-table-column label="Enviados" width="100" align="center">
                    <template slot-scope="s"><el-tag type="success" size="mini">{{ s.row.sent_count }}</el-tag></template>
                </el-table-column>
                <el-table-column label="Fallidos" width="100" align="center">
                    <template slot-scope="s"><el-tag :type="s.row.failed_count > 0 ? 'danger' : 'info'" size="mini">{{ s.row.failed_count }}</el-tag></template>
                </el-table-column>
                <el-table-column label="Fecha" min-width="160" prop="created_at"></el-table-column>

                <el-table-column label="Acciones" width="260" align="right">
                    <template slot-scope="s">
                        <el-button type="text" @click="openMessages(s.row)">Ver detalle</el-button>
                        <el-button
                            type="text"
                            style="color:#f59e0b"
                            :disabled="s.row.failed_count < 1 || retryLoadingId === s.row.id"
                            @click="retryFailed(s.row)">
                            Reintentar fallidos
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog
            title="Detalle de mensajes"
            :visible.sync="messagesDialog"
            width="1020px"
            top="5vh"
            :close-on-click-modal="false">
            <div class="d-flex mb-3" style="gap:10px;flex-wrap:wrap">
                <el-input
                    v-model="messagesFilters.search"
                    placeholder="Buscar por nombre o telefono..."
                    clearable
                    prefix-icon="el-icon-search"
                    style="width:320px"
                    @keyup.enter.native="loadMessages(1)">
                </el-input>

                <el-select v-model="messagesFilters.status" style="width:180px" @change="loadMessages(1)">
                    <el-option label="Todos" value=""></el-option>
                    <el-option label="Enviado" value="sent"></el-option>
                    <el-option label="Fallido" value="failed"></el-option>
                    <el-option label="Pendiente" value="pending"></el-option>
                    <el-option label="Omitido" value="skipped"></el-option>
                </el-select>

                <el-button type="primary" icon="el-icon-search" @click="loadMessages(1)">Filtrar</el-button>
            </div>

            <el-table :data="messages" v-loading="messagesLoading" size="small" style="width:100%">
                <el-table-column label="Cliente" min-width="220">
                    <template slot-scope="s">
                        <strong>{{ s.row.customer_name }}</strong>
                        <div class="text-muted small">{{ s.row.customer_phone || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="Estado" width="120" align="center">
                    <template slot-scope="s"><el-tag :type="tagType(s.row.status)" size="mini">{{ s.row.status }}</el-tag></template>
                </el-table-column>
                <el-table-column label="Enviado en" min-width="160" prop="sent_at"></el-table-column>
                <el-table-column label="Error" min-width="260">
                    <template slot-scope="s">
                        <span class="text-danger">{{ s.row.error_message || '-' }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Total: {{ messagesMeta.total }}
                </div>
                <el-pagination
                    layout="prev, pager, next"
                    :current-page="messagesMeta.current_page"
                    :page-size="messagesMeta.per_page"
                    :total="messagesMeta.total"
                    @current-change="loadMessages">
                </el-pagination>
            </div>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            loading: false,
            retryLoadingId: null,
            campaigns: [],
            messagesDialog: false,
            messagesLoading: false,
            selectedCampaign: null,
            messages: [],
            messagesMeta: {
                current_page: 1,
                per_page: 20,
                total: 0,
                last_page: 1,
            },
            messagesFilters: {
                search: '',
                status: '',
            },
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/ecommerce/whatsapp-campaigns/records')
                .then(({ data }) => {
                    this.campaigns = data.data || [];
                })
                .finally(() => { this.loading = false; });
        },
        tagType(status) {
            if (status === 'sent') return 'success';
            if (status === 'completed') return 'success';
            if (status === 'failed') return 'danger';
            if (status === 'pending') return 'warning';
            if (status === 'processing') return 'warning';
            if (status === 'skipped') return 'info';
            return 'info';
        },
        openMessages(campaign) {
            this.selectedCampaign = campaign;
            this.messagesDialog = true;
            this.loadMessages(1);
        },
        loadMessages(page = 1) {
            if (!this.selectedCampaign) return;
            this.messagesLoading = true;
            this.$http.get(`/ecommerce/whatsapp-campaigns/${this.selectedCampaign.id}/messages`, {
                params: {
                    page,
                    per_page: this.messagesMeta.per_page,
                    search: this.messagesFilters.search,
                    status: this.messagesFilters.status,
                }
            }).then(({ data }) => {
                this.messages = data.data || [];
                this.messagesMeta = data.meta || this.messagesMeta;
            }).finally(() => {
                this.messagesLoading = false;
            });
        },
        retryFailed(campaign) {
            this.$confirm(
                `Se reintentaran los mensajes fallidos de la campaña #${campaign.id}. Continuar?`,
                'Reintentar fallidos',
                { type: 'warning' }
            ).then(() => {
                this.retryLoadingId = campaign.id;
                this.$http.post(`/ecommerce/whatsapp-campaigns/${campaign.id}/retry-failed`)
                    .then(({ data }) => {
                        this.$message.success(
                            `Reintento listo. Reintentados: ${data.retried || 0}, Exito: ${data.sent || 0}, Fallidos: ${data.failed || 0}`
                        );
                        this.load();
                        if (this.selectedCampaign && this.selectedCampaign.id === campaign.id) {
                            this.loadMessages(this.messagesMeta.current_page || 1);
                        }
                    })
                    .catch((e) => {
                        this.$message.error(e.response?.data?.message || 'No se pudo reintentar la campaña');
                    })
                    .finally(() => { this.retryLoadingId = null; });
            }).catch(() => {});
        },
    }
};
</script>

