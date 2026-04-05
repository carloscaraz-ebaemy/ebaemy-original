<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">Webhooks</h4>
                <p class="text-muted mb-0" style="font-size:13px">Notifica a sistemas externos cuando ocurren eventos en tu negocio</p>
            </div>
            <el-button type="primary" icon="el-icon-plus" size="small" @click="openCreate">Nuevo webhook</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="records" v-loading="loading" style="width:100%">
                <el-table-column label="Nombre" min-width="160">
                    <template slot-scope="s">
                        <strong>{{ s.row.name }}</strong>
                        <div class="text-muted" style="font-size:11px;word-break:break-all">{{ s.row.url }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Eventos" width="200">
                    <template slot-scope="s">
                        <el-tag v-for="e in (s.row.events || []).slice(0,3)" :key="e" size="mini" class="mr-1 mb-1">{{ e }}</el-tag>
                        <el-tag v-if="(s.row.events||[]).length > 3" size="mini" type="info">+{{ s.row.events.length - 3 }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Exito" width="90" align="center">
                    <template slot-scope="s">
                        <span v-if="s.row.success_rate !== null" :class="s.row.success_rate >= 90 ? 'text-success' : s.row.success_rate >= 50 ? 'text-warning' : 'text-danger'"
                              style="font-weight:600">{{ s.row.success_rate }}%</span>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>

                <el-table-column label="Fallos" width="80" align="center">
                    <template slot-scope="s">
                        <span :class="s.row.failure_count > 5 ? 'text-danger font-weight-bold' : ''">{{ s.row.failure_count }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Activo" width="70" align="center">
                    <template slot-scope="s">
                        <el-switch v-model="s.row.is_active" @change="toggle(s.row)" active-color="#22c55e" inactive-color="#e5e7eb"></el-switch>
                    </template>
                </el-table-column>

                <el-table-column label="" width="140" align="center">
                    <template slot-scope="s">
                        <el-button-group>
                            <el-button size="mini" @click="testWebhook(s.row)" title="Enviar ping de prueba"><i class="fas fa-bolt"></i></el-button>
                            <el-button size="mini" @click="viewLogs(s.row)" title="Ver logs"><i class="fas fa-list"></i></el-button>
                            <el-button size="mini" @click="openEdit(s.row)" title="Editar"><i class="fas fa-edit"></i></el-button>
                            <el-button size="mini" type="danger" @click="remove(s.row)" title="Eliminar"><i class="fas fa-trash"></i></el-button>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Dialog Form -->
        <el-dialog :visible.sync="dialogVisible" width="560px" :close-on-click-modal="false" title="">
            <template slot="title">
                <h4 class="mb-0">{{ form.id ? 'Editar webhook' : 'Nuevo webhook' }}</h4>
            </template>
            <el-form :model="form" ref="whForm" label-position="top">
                <el-form-item label="Nombre" prop="name" :rules="[{required:true,message:'Requerido'}]">
                    <el-input v-model="form.name" placeholder="Ej: Notificar a Zapier"></el-input>
                </el-form-item>
                <el-form-item label="URL del endpoint" prop="url" :rules="[{required:true,message:'Requerido'},{type:'url',message:'URL invalida'}]">
                    <el-input v-model="form.url" placeholder="https://hooks.zapier.com/..."></el-input>
                </el-form-item>
                <el-form-item label="Eventos a escuchar" prop="events" :rules="[{required:true,message:'Selecciona al menos un evento',type:'array',min:1}]">
                    <el-checkbox-group v-model="form.events">
                        <div class="row">
                            <div class="col-6" v-for="ev in availableEvents" :key="ev">
                                <el-checkbox :label="ev" style="margin-bottom:6px">
                                    <span style="font-size:12px">{{ ev }}</span>
                                </el-checkbox>
                            </div>
                            <div class="col-6">
                                <el-checkbox label="*" style="margin-bottom:6px">
                                    <span style="font-size:12px;font-weight:600">Todos los eventos</span>
                                </el-checkbox>
                            </div>
                        </div>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item label="Estado">
                    <el-switch v-model="form.is_active" active-text="Activo" inactive-text="Inactivo" active-color="#22c55e"></el-switch>
                </el-form-item>
            </el-form>
            <template slot="footer">
                <el-button @click="dialogVisible = false">Cancelar</el-button>
                <el-button type="primary" :loading="saving" @click="save">{{ form.id ? 'Guardar' : 'Crear webhook' }}</el-button>
            </template>
        </el-dialog>

        <!-- Logs Dialog -->
        <el-dialog :visible.sync="logsVisible" width="720px" title="Logs del webhook">
            <el-table :data="logs" v-loading="logsLoading" style="width:100%" size="mini">
                <el-table-column label="Evento" width="150" prop="event"></el-table-column>
                <el-table-column label="Status" width="80" align="center">
                    <template slot-scope="s">
                        <el-tag :type="s.row.success ? 'success' : 'danger'" size="mini">{{ s.row.response_status || 'ERR' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Tiempo" width="80" align="center">
                    <template slot-scope="s">{{ s.row.duration_ms }}ms</template>
                </el-table-column>
                <el-table-column label="Fecha" width="160" prop="created_at"></el-table-column>
                <el-table-column label="Respuesta" min-width="200">
                    <template slot-scope="s">
                        <span style="font-size:11px;word-break:break-all">{{ (s.row.response_body || '').substring(0, 200) }}</span>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            records: [],
            availableEvents: [],
            loading: false,
            saving: false,
            dialogVisible: false,
            logsVisible: false,
            logsLoading: false,
            logs: [],
            form: { id: null, name: '', url: '', events: [], is_active: true },
        };
    },
    mounted() {
        this.load();
        this.$http.get('/webhooks/tables').then(r => { this.availableEvents = r.data.events || []; });
    },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/webhooks/records').then(r => { this.records = r.data.data || []; }).finally(() => { this.loading = false; });
        },
        openCreate() {
            this.form = { id: null, name: '', url: '', events: [], is_active: true };
            this.dialogVisible = true;
        },
        openEdit(row) {
            this.form = { id: row.id, name: row.name, url: row.url, events: row.events || [], is_active: row.is_active };
            this.dialogVisible = true;
        },
        save() {
            this.$refs.whForm.validate(valid => {
                if (!valid) return;
                this.saving = true;
                this.$http.post('/webhooks', this.form).then(r => {
                    this.$message.success(r.data.message);
                    if (r.data.secret) {
                        this.$alert('Guarda este secret, no se mostrara de nuevo:\n\n' + r.data.secret, 'Secret del webhook', { confirmButtonText: 'Entendido' });
                    }
                    this.dialogVisible = false;
                    this.load();
                }).catch(e => {
                    this.$message.error(e.response?.data?.message || 'Error');
                }).finally(() => { this.saving = false; });
            });
        },
        toggle(row) {
            this.$http.post('/webhooks/' + row.id + '/toggle').then(r => { row.is_active = r.data.is_active; }).catch(() => { row.is_active = !row.is_active; });
        },
        remove(row) {
            this.$confirm('¿Eliminar webhook "' + row.name + '"?', 'Confirmar', { type: 'warning' }).then(() => {
                this.$http.delete('/webhooks/' + row.id).then(() => { this.$message.success('Eliminado'); this.load(); });
            }).catch(() => {});
        },
        testWebhook(row) {
            this.$http.post('/webhooks/' + row.id + '/test').then(r => {
                this.$message.success(r.data.message || 'Ping enviado');
            }).catch(() => { this.$message.error('Error al enviar ping'); });
        },
        viewLogs(row) {
            this.logsVisible = true;
            this.logsLoading = true;
            this.$http.get('/webhooks/' + row.id + '/logs').then(r => { this.logs = r.data.data || []; }).finally(() => { this.logsLoading = false; });
        },
    },
};
</script>
