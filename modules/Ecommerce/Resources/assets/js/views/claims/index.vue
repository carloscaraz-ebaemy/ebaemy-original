<template>
    <div>
        <!-- Cabecera: lo primero que importa es cuántos plazos están vencidos -->
        <div class="claims-summary">
            <div class="claims-summary__stat">
                <span class="claims-summary__n">{{ summary.open }}</span>
                <span class="claims-summary__l">Abiertos</span>
            </div>
            <div class="claims-summary__stat" :class="{ 'is-bad': summary.overdue > 0 }">
                <span class="claims-summary__n">{{ summary.overdue }}</span>
                <span class="claims-summary__l">Plazo vencido</span>
            </div>
            <div class="claims-summary__mail">
                <template v-if="summary.recipient">
                    Los avisos llegan a <strong>{{ summary.recipient }}</strong>
                </template>
                <template v-else>
                    <el-tag type="danger" size="mini">Sin buzón configurado</el-tag>
                    Configura el correo del Libro en Ecommerce &rsaquo; Configuración.
                </template>
            </div>
        </div>

        <el-card shadow="never">
            <div class="claims-filters">
                <el-input v-model="filters.search" placeholder="Código, documento, correo o pedido..."
                          prefix-icon="el-icon-search" clearable style="width:280px"
                          @keyup.enter.native="load(1)" @clear="load(1)"></el-input>

                <el-select v-model="filters.status" placeholder="Estado" clearable style="width:150px" @change="load(1)">
                    <el-option v-for="(label, value) in statuses" :key="value" :label="label" :value="value"></el-option>
                </el-select>

                <el-select v-model="filters.type" placeholder="Tipo" clearable style="width:130px" @change="load(1)">
                    <el-option label="Reclamo" value="reclamo"></el-option>
                    <el-option label="Queja" value="queja"></el-option>
                </el-select>

                <el-select v-model="filters.deadline" placeholder="Plazo" clearable style="width:150px" @change="load(1)">
                    <el-option label="Abiertos" value="open"></el-option>
                    <el-option label="Vencidos" value="overdue"></el-option>
                </el-select>

                <el-date-picker v-model="dateRange" type="daterange" range-separator="a"
                                start-placeholder="Desde" end-placeholder="Hasta" value-format="yyyy-MM-dd"
                                style="width:240px" @change="load(1)"></el-date-picker>

                <el-button icon="el-icon-search" type="primary" @click="load(1)">Buscar</el-button>
            </div>

            <el-table :data="rows" v-loading="loading" size="small" style="width:100%"
                      empty-text="No hay reclamos ni quejas con esos criterios.">

                <el-table-column label="Código" width="140">
                    <template slot-scope="s">
                        <strong>{{ s.row.code }}</strong>
                        <div class="text-muted small">{{ s.row.created_at }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Consumidor" min-width="190">
                    <template slot-scope="s">
                        {{ s.row.customer }}
                        <div class="text-muted small">{{ s.row.document }} · {{ s.row.email }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Tipo" width="100" align="center">
                    <template slot-scope="s">
                        <el-tag :type="s.row.type === 'queja' ? 'warning' : 'danger'" size="mini">
                            {{ s.row.type_label }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Pedido" width="110" align="center">
                    <template slot-scope="s">{{ s.row.order_reference || '—' }}</template>
                </el-table-column>

                <el-table-column label="Estado" width="130" align="center">
                    <template slot-scope="s">
                        <el-tag :type="statusTag(s.row.status)" size="mini">{{ s.row.status_label }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Plazo" width="180">
                    <template slot-scope="s">
                        <div class="claims-deadline" :class="'is-' + s.row.deadline_state">
                            <span class="claims-deadline__dot"></span>
                            <span>
                                {{ s.row.due_date }}
                                <span class="text-muted small" v-if="s.row.deadline_state !== 'done'">
                                    · {{ deadlineText(s.row) }}
                                </span>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column width="150" align="right">
                    <template slot-scope="s">
                        <el-tooltip content="Algún correo no salió" placement="top" v-if="!s.row.mail_ok">
                            <i class="el-icon-warning" style="color:#e6a23c;margin-right:8px"></i>
                        </el-tooltip>
                        <el-button size="mini" @click="open(s.row)">Ver</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination class="mt-3" layout="total, prev, pager, next" background
                           :total="pagination.total" :page-size="pagination.per_page"
                           :current-page="pagination.current_page"
                           @current-change="load"></el-pagination>
        </el-card>

        <!-- Detalle -->
        <el-drawer :visible.sync="drawer" :with-header="false" size="640px" direction="rtl">
            <div class="claims-detail" v-if="detail" v-loading="detailLoading">

                <div class="claims-detail__head">
                    <div>
                        <div class="claims-detail__code">{{ detail.code }}</div>
                        <div class="text-muted small">
                            {{ detail.type_label }} · registrado el {{ detail.created_at }}
                        </div>
                    </div>
                    <el-tag :type="statusTag(detail.status)" size="small">{{ detail.status_label }}</el-tag>
                </div>

                <div class="claims-detail__deadline" :class="'is-' + detail.deadline_state">
                    Fecha límite de respuesta: <strong>{{ detail.due_date }}</strong>
                    <span v-if="detail.deadline_state !== 'done'"> — {{ deadlineText(detail) }}</span>
                    <span v-else> — plazo detenido</span>
                </div>

                <el-tabs v-model="tab">
                    <el-tab-pane label="Hoja" name="sheet">
                        <dl class="claims-dl">
                            <dt>Consumidor</dt>
                            <dd>
                                {{ detail.customer }}<br>
                                {{ detail.document_type }} {{ detail.document_number }}<br>
                                {{ detail.email }}<span v-if="detail.phone"> · {{ detail.phone }}</span><br>
                                <span v-if="detail.address">{{ detail.address }}</span>
                                <div v-if="detail.is_minor" class="text-muted small">
                                    Menor de edad — apoderado: {{ detail.guardian_name || '—' }}
                                </div>
                            </dd>

                            <dt>{{ detail.item_type === 'servicio' ? 'Servicio' : 'Bien' }} contratado</dt>
                            <dd>
                                {{ detail.product_description }}
                                <div class="text-muted small">
                                    <span v-if="detail.order_reference">Pedido N° {{ detail.order_reference }} · </span>
                                    <span v-if="detail.purchase_date">Comprado el {{ detail.purchase_date }} · </span>
                                    <span v-if="detail.amount">{{ detail.currency === 'USD' ? '$' : 'S/' }} {{ detail.amount }}</span>
                                </div>
                            </dd>

                            <dt>Detalle</dt>
                            <dd class="claims-pre">{{ detail.detail }}</dd>

                            <dt>Pedido concreto del consumidor</dt>
                            <dd class="claims-pre">{{ detail.consumer_request }}</dd>

                            <dt v-if="detail.files && detail.files.length">Adjuntos</dt>
                            <dd v-if="detail.files && detail.files.length">
                                <a v-for="f in detail.files" :key="f.url" :href="f.url" target="_blank"
                                   class="claims-file">{{ f.name }}</a>
                            </dd>
                        </dl>

                        <div class="claims-actions">
                            <el-select v-model="detail.status" size="small" style="width:170px" @change="changeStatus">
                                <el-option v-for="(label, value) in statuses" :key="value"
                                           :label="label" :value="value"></el-option>
                            </el-select>
                            <el-button size="small" icon="el-icon-document"
                                       @click="openPdf">Ver hoja en PDF</el-button>
                        </div>
                    </el-tab-pane>

                    <el-tab-pane label="Respuesta" name="answer">
                        <div v-if="detail.answered_at" class="claims-answered">
                            Respondido el {{ detail.answered_at }}. Al guardar de nuevo se reenvía la respuesta al consumidor.
                        </div>

                        <el-form label-position="top" size="small">
                            <el-form-item label="Respuesta al consumidor" required>
                                <el-input type="textarea" :rows="6" v-model="answerForm.answer"
                                          placeholder="Lo que recibirá el consumidor por correo."></el-input>
                            </el-form-item>

                            <el-form-item label="Acciones adoptadas">
                                <el-input type="textarea" :rows="3" v-model="answerForm.actions_taken"></el-input>
                            </el-form-item>

                            <el-form-item label="¿Se acoge el pedido del consumidor?" required>
                                <el-radio-group v-model="answerForm.request_accepted">
                                    <el-radio :label="true">Sí, se acoge</el-radio>
                                    <el-radio :label="false">No se acoge</el-radio>
                                </el-radio-group>
                            </el-form-item>

                            <el-form-item label="Fundamento" v-if="answerForm.request_accepted === false" required>
                                <el-input type="textarea" :rows="3" v-model="answerForm.rejection_grounds"
                                          placeholder="Por qué no se acoge el pedido."></el-input>
                            </el-form-item>

                            <el-button type="primary" :loading="saving" @click="submitAnswer">
                                Guardar y enviar respuesta
                            </el-button>
                        </el-form>
                    </el-tab-pane>

                    <el-tab-pane label="Correos" name="mails">
                        <table class="claims-mails">
                            <tr v-for="ch in mailChannels" :key="ch.key">
                                <td>
                                    <strong>{{ ch.label }}</strong>
                                    <div class="text-muted small" v-if="mail(ch.key).to">{{ mail(ch.key).to }}</div>
                                    <div class="text-muted small" v-if="mail(ch.key).sent_at">{{ mail(ch.key).sent_at }}</div>
                                    <div class="claims-mails__err" v-if="mail(ch.key).error">{{ mail(ch.key).error }}</div>
                                </td>
                                <td align="right">
                                    <el-tag :type="mailTag(mail(ch.key).status)" size="mini">
                                        {{ mailLabel(mail(ch.key).status) }}
                                    </el-tag>
                                    <el-button size="mini" style="margin-left:8px"
                                               :loading="resending === ch.key"
                                               @click="resend(ch.key)">Reenviar</el-button>
                                </td>
                            </tr>
                        </table>
                    </el-tab-pane>

                    <el-tab-pane label="Historial" name="history">
                        <ul class="claims-timeline">
                            <li v-for="(e, i) in detail.events" :key="i">
                                <span class="claims-timeline__date">{{ e.created_at }}</span>
                                <span>{{ e.description || e.action }}</span>
                                <span class="text-muted small" v-if="e.user_name"> — {{ e.user_name }}</span>
                            </li>
                        </ul>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-drawer>
    </div>
</template>

<script>
export default {
    data() {
        return {
            rows: [],
            loading: false,
            pagination: { total: 0, per_page: 25, current_page: 1 },
            filters: { search: '', status: '', type: '', deadline: '' },
            dateRange: null,
            summary: { open: 0, overdue: 0, recipient: null },
            statuses: {
                registered: 'Registrado',
                in_review: 'En revisión',
                answered: 'Respondido',
                closed: 'Cerrado',
            },
            drawer: false,
            detail: null,
            detailLoading: false,
            tab: 'sheet',
            answerForm: { answer: '', actions_taken: '', request_accepted: true, rejection_grounds: '' },
            saving: false,
            resending: null,
            mailChannels: [
                { key: 'customer', label: 'Copia al consumidor' },
                { key: 'tenant', label: 'Aviso a la tienda' },
                { key: 'answer', label: 'Respuesta al consumidor' },
            ],
        };
    },
    mounted() {
        this.load(1);
        this.loadSummary();
    },
    methods: {
        load(page) {
            this.loading = true;
            const params = Object.assign({}, this.filters, {
                page: page || 1,
                limit: this.pagination.per_page,
                date_from: this.dateRange ? this.dateRange[0] : null,
                date_to: this.dateRange ? this.dateRange[1] : null,
            });

            this.$http.get('/ecommerce/claims/records', { params })
                .then(r => {
                    this.rows = r.data.data;
                    this.pagination.total = r.data.meta ? r.data.meta.total : r.data.total;
                    this.pagination.current_page = r.data.meta ? r.data.meta.current_page : r.data.current_page;
                })
                .finally(() => { this.loading = false; });
        },
        loadSummary() {
            this.$http.get('/ecommerce/claims/summary').then(r => { this.summary = r.data; });
        },
        open(row) {
            this.drawer = true;
            this.tab = 'sheet';
            this.detailLoading = true;
            this.$http.get('/ecommerce/claims/' + row.id)
                .then(r => {
                    this.detail = r.data.data;
                    this.answerForm = {
                        answer: this.detail.answer || '',
                        actions_taken: this.detail.actions_taken || '',
                        request_accepted: this.detail.request_accepted === null ? true : !!this.detail.request_accepted,
                        rejection_grounds: this.detail.rejection_grounds || '',
                    };
                })
                .finally(() => { this.detailLoading = false; });
        },
        submitAnswer() {
            if (!this.answerForm.answer || this.answerForm.answer.trim().length < 10) {
                this.$message.warning('Escribe la respuesta que se le enviará al consumidor.');
                return;
            }
            if (this.answerForm.request_accepted === false && !this.answerForm.rejection_grounds) {
                this.$message.warning('Si no se acoge el pedido, hay que fundamentar por qué.');
                return;
            }

            this.saving = true;
            this.$http.post('/ecommerce/claims/' + this.detail.id + '/answer', this.answerForm)
                .then(r => {
                    this.$message({ type: r.data.success ? 'success' : 'warning', message: r.data.message });
                    this.open({ id: this.detail.id });
                    this.load(this.pagination.current_page);
                    this.loadSummary();
                })
                .catch(e => {
                    const errors = e.response && e.response.data && e.response.data.errors;
                    this.$message.error(errors ? Object.values(errors)[0][0] : 'No se pudo guardar la respuesta.');
                })
                .finally(() => { this.saving = false; });
        },
        changeStatus(status) {
            this.$http.post('/ecommerce/claims/' + this.detail.id + '/status', { status })
                .then(r => {
                    this.$message.success(r.data.message);
                    this.load(this.pagination.current_page);
                    this.loadSummary();
                })
                .catch(e => {
                    const msg = e.response && e.response.data && e.response.data.message;
                    this.$message.error(msg || 'No se pudo cambiar el estado.');
                    this.open({ id: this.detail.id });
                });
        },
        resend(channel) {
            this.resending = channel;
            this.$http.post('/ecommerce/claims/' + this.detail.id + '/resend-mail', { channel })
                .then(r => {
                    this.$message({ type: r.data.success ? 'success' : 'error', message: r.data.message });
                    this.open({ id: this.detail.id });
                })
                .finally(() => { this.resending = null; });
        },
        openPdf() {
            window.open('/ecommerce/claims/' + this.detail.id + '/pdf', '_blank');
        },
        mail(key) {
            return (this.detail && this.detail.mails && this.detail.mails[key]) || {};
        },
        mailLabel(status) {
            return {
                sent: 'Enviado',
                failed: 'Error',
                pending: 'Pendiente',
                missing_recipient: 'Sin destinatario',
                invalid_address: 'Correo inválido',
            }[status] || '—';
        },
        mailTag(status) {
            if (status === 'sent') return 'success';
            if (status === 'pending' || !status) return 'info';
            return 'danger';
        },
        statusTag(status) {
            return { registered: 'info', in_review: 'warning', answered: 'success', closed: '' }[status] || 'info';
        },
        deadlineText(row) {
            if (row.days_left === null) return '';
            if (row.days_left < 0) return 'vencido hace ' + Math.abs(row.days_left) + ' días hábiles';
            if (row.days_left === 0) return 'vence hoy';
            return 'quedan ' + row.days_left + ' días hábiles';
        },
    },
};
</script>

<style scoped>
.claims-summary { display:flex; align-items:center; gap:24px; flex-wrap:wrap; margin-bottom:16px; }
.claims-summary__stat { display:flex; flex-direction:column; }
.claims-summary__n { font-size:26px; font-weight:700; line-height:1; color:#303133; }
.claims-summary__stat.is-bad .claims-summary__n { color:#f56c6c; }
.claims-summary__l { font-size:12px; color:#909399; margin-top:4px; }
.claims-summary__mail { font-size:13px; color:#606266; margin-left:auto; }

.claims-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }

.claims-deadline { display:flex; align-items:center; gap:7px; }
.claims-deadline__dot { width:8px; height:8px; border-radius:50%; background:#c0c4cc; flex:none; }
.claims-deadline.is-ok .claims-deadline__dot { background:#67c23a; }
.claims-deadline.is-warning .claims-deadline__dot { background:#e6a23c; }
.claims-deadline.is-overdue .claims-deadline__dot { background:#f56c6c; }

.claims-detail { padding:22px; }
.claims-detail__head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:14px; }
.claims-detail__code { font-size:20px; font-weight:700; }
.claims-detail__deadline { font-size:13px; padding:10px 12px; border-radius:8px; background:#f4f4f5; color:#606266; margin-bottom:14px; }
.claims-detail__deadline.is-warning { background:#fdf6ec; color:#a5690a; }
.claims-detail__deadline.is-overdue { background:#fef0f0; color:#b23b3b; }

.claims-dl dt { font-size:11.5px; text-transform:uppercase; letter-spacing:.05em; color:#909399; font-weight:700; margin-top:16px; }
.claims-dl dd { margin:5px 0 0; font-size:14px; color:#303133; line-height:1.6; }
.claims-pre { white-space:pre-line; }
.claims-file { display:block; font-size:13px; }

.claims-actions { display:flex; gap:10px; align-items:center; margin-top:22px; padding-top:16px; border-top:1px solid #ebeef5; }

.claims-answered { font-size:13px; background:#f0f9eb; color:#4f8a2f; padding:9px 12px; border-radius:8px; margin-bottom:14px; }

.claims-mails { width:100%; }
.claims-mails td { padding:12px 0; border-bottom:1px solid #ebeef5; vertical-align:top; }
.claims-mails__err { font-size:12px; color:#f56c6c; margin-top:4px; word-break:break-word; }

.claims-timeline { list-style:none; padding:0; margin:0; }
.claims-timeline li { font-size:13.5px; padding:9px 0; border-bottom:1px solid #f2f3f5; color:#303133; }
.claims-timeline__date { display:inline-block; min-width:118px; color:#909399; font-size:12.5px; }

@media (max-width: 768px) {
    .claims-summary__mail { margin-left:0; width:100%; }
    .claims-detail { padding:16px; }
}
</style>
