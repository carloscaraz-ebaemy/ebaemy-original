<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3" style="gap:10px;flex-wrap:wrap">
            <p class="text-muted mb-0">
                Avisos por WhatsApp a tus clientes: elige productos, a quién, y el sistema los envía espaciados desde tu número.
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <el-button icon="el-icon-refresh" :loading="loading" @click="load">Actualizar</el-button>
                <el-button type="primary" icon="el-icon-chat-dot-round" @click="abrirNueva">Nueva campaña</el-button>
            </div>
        </div>
        <el-alert
            v-if="info && !info.whatsapp_ready"
            type="warning"
            :closable="false"
            show-icon
            class="mb-3"
            :title="info.whatsapp_issue || 'WhatsApp no está conectado en esta tienda.'"
            description="Conecta tu WhatsApp en WhatsApp → Conexión (escanea el QR) para poder enviar campañas.">
        </el-alert>

        <el-card shadow="never">
            <el-table :data="campaigns" v-loading="loading" style="width:100%" size="small">
                <el-table-column label="ID" prop="id" width="70"></el-table-column>
                <el-table-column label="Campaña" min-width="210">
                    <template slot-scope="s">
                        <strong>{{ s.row.name }}</strong>
                        <div v-if="s.row.kind === 'products'" class="text-muted small">
                            {{ (s.row.products || []).join(', ') }}<span v-if="s.row.audience"> · {{ etiquetaAudiencia(s.row.audience) }}</span>
                        </div>
                        <div v-else class="text-muted small">Flash Sale: {{ s.row.flash_sale_title || ('#' + (s.row.flash_sale_id || '-')) }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="150" align="center">
                    <template slot-scope="s">
                        <el-tag :type="tagType(s.row.status)" size="mini">{{ etiquetaEstado(s.row.status) }}</el-tag>
                        <div v-if="s.row.pending_count > 0" class="text-muted small" style="margin-top:2px">
                            {{ s.row.pending_count }} por enviar<span v-if="s.row.next_scheduled_at"> · próximo {{ s.row.next_scheduled_at.substr(11, 5) }}</span>
                        </div>
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
                        <el-button
                            v-if="s.row.kind === 'products' && (s.row.status === 'queued' || s.row.status === 'processing')"
                            type="text"
                            style="color:#dc2626"
                            @click="cancelar(s.row)">
                            Detener
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog
            title="Nueva campaña de WhatsApp"
            :visible.sync="nuevaDialog"
            width="860px"
            top="4vh"
            :close-on-click-modal="false">
            <el-form label-position="top" size="small">
                <el-row :gutter="16">
                    <el-col :xs="24" :md="12">
                        <el-form-item label="Nombre (solo lo ves tú)">
                            <el-input v-model="nueva.name" maxlength="120" placeholder="Ej. Árboles de Navidad – clientes 2026"></el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item label="¿A quién?">
                            <el-select v-model="nueva.audience" style="width:100%" @change="previewPendiente = true">
                                <el-option value="all" :label="'Todos mis clientes con celular (' + cuenta('all') + ')'"></el-option>
                                <el-option value="buyers_12m" :label="'Compraron en los últimos 12 meses (' + cuenta('buyers_12m') + ')'"></el-option>
                                <el-option value="buyers_3m" :label="'Compraron en los últimos 3 meses (' + cuenta('buyers_3m') + ')'"></el-option>
                            </el-select>
                            <div class="text-muted small">No entran quienes pidieron BAJA ni quienes recibieron una campaña en los últimos 3 días.</div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="Productos (hasta 5, de tu tienda virtual y con stock)">
                    <el-select
                        v-model="nueva.items"
                        multiple
                        filterable
                        :multiple-limit="5"
                        :loading="itemsLoading"
                        placeholder="Escribe para buscar…"
                        style="width:100%"
                        @change="previewPendiente = true">
                        <el-option v-for="it in items" :key="it.id" :value="it.id" :label="it.description + ' — S/ ' + Number(it.sale_unit_price).toFixed(2)"></el-option>
                    </el-select>
                </el-form-item>

                <el-form-item label="Tu mensaje de inicio (puedes usar {nombre})">
                    <el-input
                        type="textarea"
                        v-model="nueva.message"
                        :rows="3"
                        maxlength="600"
                        show-word-limit
                        placeholder="Hola {nombre}, ya llegaron los árboles de Navidad. Te comparto los que más están saliendo:"
                        @input="previewPendiente = true">
                    </el-input>
                    <div class="text-muted small">Debajo van los productos con precio y enlace, y al final «Responde BAJA» para quien no quiera más avisos.</div>
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :xs="24" :md="12">
                        <el-form-item label="Segundos entre mensajes">
                            <el-input-number v-model="nueva.spacing_seconds" :min="20" :max="600" :step="5" style="width:100%"></el-input-number>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item label="Máximo por día">
                            <el-input-number v-model="nueva.daily_cap" :min="10" :max="300" :step="10" style="width:100%"></el-input-number>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-alert type="info" :closable="false" show-icon class="mb-3"
                    title="Sale desde tu propio WhatsApp, uno por uno"
                    description="Enviar cientos de mensajes seguidos hace que WhatsApp bloquee el número. Por eso el sistema los espacia y respeta un máximo diario; si hay más clientes, sigue al día siguiente a las 10:30.">
                </el-alert>

                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <el-button :loading="previewLoading" :disabled="!nueva.items.length" @click="previsualizar">Ver cómo llegará</el-button>
                    <span v-if="preview" class="text-muted small">{{ preview.customers }} clientes recibirán {{ preview.products }} producto(s).</span>
                </div>
                <pre v-if="preview" style="white-space:pre-wrap;background:#f6f8fa;border-radius:8px;padding:12px;margin-top:10px;font-family:inherit;font-size:13px;line-height:1.45">{{ preview.text }}</pre>
            </el-form>

            <span slot="footer">
                <el-button @click="nuevaDialog = false">Cancelar</el-button>
                <el-button type="primary" :loading="guardando" :disabled="!nueva.items.length || !preview || previewPendiente" @click="crearCampana">
                    Programar envío
                </el-button>
            </span>
        </el-dialog>

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
                <el-table-column label="Enviado en" min-width="150">
                    <template slot-scope="s">
                        <span v-if="s.row.sent_at">{{ s.row.sent_at }}</span>
                        <span v-else-if="s.row.scheduled_at" class="text-muted">programado {{ s.row.scheduled_at.substr(0, 16) }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="Error" min-width="220">
                    <template slot-scope="s">
                        <span class="text-danger">{{ s.row.error_message || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Baja" width="120" align="center">
                    <template slot-scope="s">
                        <el-button
                            type="text"
                            :style="s.row.opt_out ? 'color:#16a34a' : 'color:#6b7280'"
                            :title="s.row.opt_out ? 'El cliente no recibirá más campañas. Clic para reactivar.' : 'Si respondió BAJA, márcalo aquí y no volverá a entrar en ninguna campaña.'"
                            @click="toggleBaja(s.row)">
                            {{ s.row.opt_out ? 'Dado de baja' : 'Marcar BAJA' }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    Total: {{ messagesMeta.total }}
                </div>
                <el-pagination
                    layout="sizes, prev, pager, next"
                    :page-sizes="opcionesTamano(messagesMeta.per_page)"
                    @size-change="cambiarTamano($event, loadMessages, messagesMeta)"
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
import { perPage } from '@mixins/per_page'
export default {
    mixins: [perPage],
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
            // Nueva campaña por productos
            info: null,
            nuevaDialog: false,
            items: [],
            itemsLoading: false,
            nueva: this.nuevaVacia(),
            preview: null,
            previewPendiente: true,
            previewLoading: false,
            guardando: false,
        };
    },
    mounted() {
        this.load();
        this.cargarInfo();
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
                    per_page: this.filasPorPagina() || this.messagesMeta.per_page,
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
        // ── Nueva campaña por productos ──────────────────────────────
        nuevaVacia() {
            return { name: '', audience: 'all', items: [], message: '', spacing_seconds: 45, daily_cap: 120 };
        },
        cargarInfo() {
            this.$http.get('/ecommerce/whatsapp-campaigns/audiences')
                .then(({ data }) => {
                    this.info = data;
                    if (data.defaults) {
                        this.nueva.spacing_seconds = data.defaults.spacing_seconds || 45;
                        this.nueva.daily_cap = data.defaults.daily_cap || 120;
                    }
                })
                .catch(() => { this.info = null; });
        },
        cuenta(aud) {
            return this.info && this.info.audiences ? (this.info.audiences[aud] ?? 0) : '…';
        },
        etiquetaAudiencia(aud) {
            return { all: 'todos los clientes', buyers_12m: 'compraron en 12 meses', buyers_3m: 'compraron en 3 meses' }[aud] || aud;
        },
        etiquetaEstado(st) {
            return {
                queued: 'programada', processing: 'enviando', completed: 'terminada',
                cancelled: 'detenida', failed: 'fallida', paused: 'en pausa',
            }[st] || st;
        },
        abrirNueva() {
            if (this.info && !this.info.whatsapp_ready) {
                this.$message.warning(this.info.whatsapp_issue || 'Conecta tu WhatsApp antes de crear una campaña.');
                return;
            }
            this.nueva = this.nuevaVacia();
            if (this.info && this.info.defaults) {
                this.nueva.spacing_seconds = this.info.defaults.spacing_seconds || 45;
                this.nueva.daily_cap = this.info.defaults.daily_cap || 120;
            }
            this.preview = null;
            this.previewPendiente = true;
            this.nuevaDialog = true;
            if (!this.items.length) this.cargarItems();
        },
        cargarItems() {
            this.itemsLoading = true;
            this.$http.get('/ecommerce/items_ecommerce/records_all')
                .then(({ data }) => { this.items = (data.data || []).filter(i => Number(i.stock) > 0 || i.has_variants); })
                .finally(() => { this.itemsLoading = false; });
        },
        previsualizar() {
            this.previewLoading = true;
            this.$http.post('/ecommerce/whatsapp-campaigns/preview', {
                items: this.nueva.items, message: this.nueva.message, audience: this.nueva.audience,
            }).then(({ data }) => {
                this.preview = data;
                this.previewPendiente = false;
            }).catch((e) => {
                this.preview = null;
                this.$message.error(e.response?.data?.message || 'No se pudo generar la vista previa');
            }).finally(() => { this.previewLoading = false; });
        },
        crearCampana() {
            const n = this.preview ? this.preview.customers : 0;
            this.$confirm(
                `Se programará el envío a ${n} clientes desde tu WhatsApp, uno cada ${this.nueva.spacing_seconds} segundos (máximo ${this.nueva.daily_cap} por día). ¿Continuar?`,
                'Programar envío',
                { type: 'warning', confirmButtonText: 'Sí, programar' }
            ).then(() => {
                this.guardando = true;
                this.$http.post('/ecommerce/whatsapp-campaigns', this.nueva)
                    .then(({ data }) => {
                        this.$message.success(data.message || 'Campaña programada');
                        this.nuevaDialog = false;
                        this.load();
                        this.cargarInfo();
                    })
                    .catch((e) => {
                        const d = e.response?.data || {};
                        const msg = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'No se pudo crear la campaña');
                        this.$message.error(msg);
                    })
                    .finally(() => { this.guardando = false; });
            }).catch(() => {});
        },
        cancelar(campaign) {
            this.$confirm(
                `Se detendrá la campaña #${campaign.id}. Los mensajes ya enviados no se pueden deshacer; los ${campaign.pending_count || 0} pendientes no saldrán.`,
                'Detener campaña',
                { type: 'warning', confirmButtonText: 'Detener' }
            ).then(() => {
                this.$http.post(`/ecommerce/whatsapp-campaigns/${campaign.id}/cancel`)
                    .then(({ data }) => { this.$message.success(data.message || 'Campaña detenida'); this.load(); })
                    .catch((e) => { this.$message.error(e.response?.data?.message || 'No se pudo detener'); });
            }).catch(() => {});
        },
        toggleBaja(row) {
            const nuevo = !row.opt_out;
            this.$http.post(`/ecommerce/whatsapp-campaigns/opt-out/${row.person_id}`, { opt_out: nuevo })
                .then(() => {
                    row.opt_out = nuevo;
                    this.$message.success(nuevo ? 'Cliente dado de baja: no entrará en más campañas.' : 'Cliente reactivado.');
                    this.cargarInfo();
                })
                .catch(() => { this.$message.error('No se pudo actualizar'); });
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

