<template>
    <div>
        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <el-select v-model="filterType" placeholder="Tipo de regla" clearable size="small" style="width:200px" @change="load">
                    <el-option v-for="t in types" :key="t.id" :label="t.label" :value="t.id"></el-option>
                </el-select>
                <el-input v-model="search" placeholder="Buscar nombre..." size="small" style="width:200px"
                    prefix-icon="el-icon-search" clearable @clear="load" @keyup.enter.native="load"></el-input>
            </div>
            <el-button type="primary" icon="el-icon-plus" size="small" @click="openCreate">Nueva regla</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="records" v-loading="loading" style="width:100%" row-key="id">

                <el-table-column label="Nombre" min-width="180">
                    <template slot-scope="s">
                        <strong>{{ s.row.name }}</strong>
                        <div class="text-muted" style="font-size:11px">Prioridad: {{ s.row.priority }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Tipo" width="160">
                    <template slot-scope="s">
                        <el-tag :type="typeColor(s.row.type)" size="mini">{{ typeLabel(s.row.type) }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Descuento" width="130">
                    <template slot-scope="s">
                        <span v-if="s.row.discount_type === 'percentage'">{{ s.row.discount_value }}%</span>
                        <span v-else>S/ {{ Number(s.row.discount_value).toFixed(2) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Aplica a" width="110">
                    <template slot-scope="s">
                        <span>{{ appliesToLabel(s.row.applies_to) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Usos" width="90" align="center">
                    <template slot-scope="s">
                        <span>{{ s.row.used_count }}</span>
                        <span v-if="s.row.max_uses" class="text-muted"> / {{ s.row.max_uses }}</span>
                        <span v-else class="text-muted"> / ∞</span>
                    </template>
                </el-table-column>

                <el-table-column label="Vigencia" width="180">
                    <template slot-scope="s">
                        <div v-if="s.row.starts_at || s.row.ends_at" style="font-size:12px">
                            <span>{{ s.row.starts_at || '—' }}</span>
                            <span class="text-muted"> → </span>
                            <span :class="{ 'text-danger': isExpired(s.row.ends_at) }">{{ s.row.ends_at || '∞' }}</span>
                        </div>
                        <span v-else class="text-muted">Sin límite</span>
                    </template>
                </el-table-column>

                <el-table-column label="Acumulable" width="100" align="center">
                    <template slot-scope="s">
                        <el-tag v-if="s.row.stackable" type="success" size="mini">Sí</el-tag>
                        <el-tag v-else type="info" size="mini">No</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Activo" width="80" align="center">
                    <template slot-scope="s">
                        <el-switch v-model="s.row.is_active" @change="toggle(s.row)"></el-switch>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="90" align="center">
                    <template slot-scope="s">
                        <el-button-group>
                            <el-button size="mini" icon="el-icon-edit" @click="openEdit(s.row)"></el-button>
                            <el-button size="mini" type="danger" icon="el-icon-delete" @click="remove(s.row)"></el-button>
                        </el-button-group>
                    </template>
                </el-table-column>

            </el-table>

            <el-pagination
                v-if="pagination.total > pagination.per_page"
                class="mt-3 text-right"
                background
                layout="prev, pager, next"
                :page-size="pagination.per_page"
                :total="pagination.total"
                :current-page.sync="pagination.current_page"
                @current-change="load">
            </el-pagination>
        </el-card>

        <!-- Dialog Form — Estilo Shopify -->
        <el-dialog
            :visible.sync="dialogVisible"
            width="680px"
            :close-on-click-modal="false"
            :show-close="false"
            custom-class="sp-dialog"
        >
            <!-- Header -->
            <template slot="title">
                <div class="sp-dialog-header">
                    <button type="button" class="sp-back" @click="dialogVisible = false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <h3 class="sp-dialog-title">{{ form.id ? 'Editar regla de descuento' : 'Crear regla de descuento' }}</h3>
                </div>
            </template>

            <el-form :model="form" ref="ruleForm" label-position="top" @submit.native.prevent class="sp-form">

                <!-- SECCIÓN 1: Información general -->
                <div class="sp-section">
                    <el-form-item label="Nombre de la regla" prop="name" :rules="[{required:true,message:'Dale un nombre a esta regla'}]">
                        <el-input v-model="form.name" maxlength="100" placeholder="Ej: Descuento por volumen 3x15%" show-word-limit></el-input>
                    </el-form-item>

                    <div class="row">
                        <div class="col-8">
                            <el-form-item label="Tipo de regla" prop="type" :rules="[{required:true,message:'Selecciona un tipo'}]">
                                <el-select v-model="form.type" style="width:100%" @change="onTypeChange" placeholder="Selecciona tipo">
                                    <el-option v-for="t in types" :key="t.id" :label="t.label" :value="t.id"></el-option>
                                </el-select>
                            </el-form-item>
                        </div>
                        <div class="col-4">
                            <el-form-item label="Estado">
                                <el-switch v-model="form.is_active" active-text="Activa" inactive-text="Inactiva" active-color="#22c55e"></el-switch>
                            </el-form-item>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: Condiciones -->
                <div class="sp-section" v-if="form.type">
                    <h4 class="sp-section-title">Condiciones</h4>

                    <template v-if="form.type === 'volume'">
                        <div class="row">
                            <div class="col-6">
                                <el-form-item label="Cantidad mínima de productos">
                                    <el-input-number v-model="form.trigger.min_quantity" :min="1" :precision="0" controls-position="right" style="width:100%"></el-input-number>
                                    <p class="sp-help">unidades</p>
                                </el-form-item>
                            </div>
                        </div>
                    </template>

                    <template v-if="form.type === 'auto'">
                        <div class="row">
                            <div class="col-6">
                                <el-form-item label="Monto mínimo de compra (S/)">
                                    <el-input-number v-model="form.trigger.min_amount" :min="0" :precision="2" :step="10" controls-position="right" style="width:100%"></el-input-number>
                                </el-form-item>
                            </div>
                        </div>
                    </template>

                    <template v-if="form.type === 'channel'">
                        <el-form-item label="Canal de venta">
                            <el-select v-model="form.channel_id" style="width:100%" placeholder="Selecciona un canal">
                                <el-option v-for="c in channels" :key="c.id" :label="c.name" :value="c.id"></el-option>
                            </el-select>
                        </el-form-item>
                    </template>

                    <template v-if="form.type === 'flash_sale'">
                        <div class="row">
                            <div class="col-6">
                                <el-form-item label="Inicio">
                                    <el-date-picker v-model="form.starts_at" type="datetime"
                                        format="yyyy-MM-dd HH:mm" value-format="yyyy-MM-dd HH:mm"
                                        placeholder="Fecha inicio" style="width:100%"></el-date-picker>
                                </el-form-item>
                            </div>
                            <div class="col-6">
                                <el-form-item label="Fin">
                                    <el-date-picker v-model="form.ends_at" type="datetime"
                                        format="yyyy-MM-dd HH:mm" value-format="yyyy-MM-dd HH:mm"
                                        placeholder="Fecha fin" style="width:100%"></el-date-picker>
                                </el-form-item>
                            </div>
                        </div>
                    </template>

                    <el-form-item label="Aplica a" prop="applies_to" :rules="[{required:true}]">
                        <el-select v-model="form.applies_to" style="width:100%">
                            <el-option label="Todo el carrito" value="all"></el-option>
                            <el-option label="Producto específico" value="item"></el-option>
                            <el-option label="Pack / Bundle" value="bundle"></el-option>
                            <el-option label="Categoría" value="category"></el-option>
                        </el-select>
                    </el-form-item>
                </div>

                <!-- SECCIÓN 3: Descuento -->
                <div class="sp-section">
                    <h4 class="sp-section-title">Descuento</h4>

                    <el-form-item label="Tipo de descuento" prop="discount_type" :rules="[{required:true}]">
                        <div class="sp-radio-cards">
                            <label class="sp-radio-card" :class="{ 'sp-radio-card--active': form.discount_type === 'percentage' }" @click="form.discount_type = 'percentage'">
                                <span class="sp-radio-card__icon">%</span>
                                <span class="sp-radio-card__label">Porcentaje</span>
                            </label>
                            <label class="sp-radio-card" :class="{ 'sp-radio-card--active': form.discount_type === 'fixed' }" @click="form.discount_type = 'fixed'">
                                <span class="sp-radio-card__icon">S/</span>
                                <span class="sp-radio-card__label">Monto fijo</span>
                            </label>
                        </div>
                    </el-form-item>

                    <div class="row">
                        <div class="col-6">
                            <el-form-item :label="'Valor del descuento (' + (form.discount_type === 'percentage' ? '%' : 'S/') + ')'" prop="discount_value" :rules="[{required:true,message:'Requerido'},{type:'number',min:0.01,message:'Debe ser mayor a 0'}]">
                                <el-input-number v-model="form.discount_value" :min="0.01" :precision="2" :step="5" controls-position="right" style="width:100%"></el-input-number>
                            </el-form-item>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 4: Configuración avanzada -->
                <div class="sp-section sp-section--muted">
                    <h4 class="sp-section-title">Configuración avanzada</h4>

                    <div class="row">
                        <div class="col-6">
                            <el-form-item label="Prioridad">
                                <el-input-number v-model="form.priority" :min="0" :max="999" :precision="0" controls-position="right" style="width:100%"></el-input-number>
                                <p class="sp-help">Mayor = primero</p>
                            </el-form-item>
                        </div>
                        <div class="col-6">
                            <el-form-item label="Límite de usos">
                                <el-input-number v-model="form.max_uses" :min="0" :precision="0" :step="10" controls-position="right" style="width:100%"></el-input-number>
                                <p class="sp-help">0 = ilimitado</p>
                            </el-form-item>
                        </div>
                    </div>

                    <el-form-item label="Acumulable con otras reglas">
                        <div class="sp-toggle-row">
                            <el-switch v-model="form.stackable" active-color="#22c55e"></el-switch>
                            <span class="sp-toggle-label">{{ form.stackable ? 'Sí, puede combinarse' : 'No, es exclusiva' }}</span>
                        </div>
                    </el-form-item>
                </div>

            </el-form>

            <!-- Footer -->
            <template slot="footer">
                <div class="sp-dialog-footer">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" :loading="saving" @click="save">
                        {{ form.id ? 'Guardar cambios' : 'Crear regla' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            records: [],
            types: [],
            channels: [],
            loading: false,
            saving: false,
            dialogVisible: false,
            filterType: '',
            search: '',
            pagination: { total: 0, per_page: 15, current_page: 1 },
            form: this.emptyForm(),
        };
    },
    mounted() {
        this.loadTables();
        this.load();
    },
    methods: {
        emptyForm() {
            return {
                id: null,
                name: '',
                type: 'auto',
                discount_type: 'percentage',
                discount_value: 10,
                applies_to: 'all',
                channel_id: null,
                max_uses: null,
                starts_at: null,
                ends_at: null,
                is_active: true,
                priority: 0,
                stackable: false,
                trigger: { min_quantity: 3, min_amount: 100 },
            };
        },
        loadTables() {
            this.$http.get('/discount-rules/tables').then(r => {
                this.types    = r.data.types;
                this.channels = r.data.channels;
            });
        },
        load(page) {
            this.loading = true;
            const p = typeof page === 'number' ? page : this.pagination.current_page;
            this.$http.get('/discount-rules/records', {
                params: { type: this.filterType, search: this.search, page: p }
            }).then(r => {
                this.records = r.data.data;
                this.pagination = {
                    total: r.data.total,
                    per_page: r.data.per_page,
                    current_page: r.data.current_page,
                };
            }).finally(() => { this.loading = false; });
        },
        openCreate() {
            this.form = this.emptyForm();
            this.dialogVisible = true;
            this.$nextTick(() => { if (this.$refs.ruleForm) this.$refs.ruleForm.clearValidate(); });
        },
        openEdit(row) {
            this.form = {
                id:             row.id,
                name:           row.name,
                type:           row.type,
                discount_type:  row.discount_type,
                discount_value: Number(row.discount_value),
                applies_to:     row.applies_to,
                channel_id:     row.channel_id,
                max_uses:       row.max_uses,
                starts_at:      row.starts_at || null,
                ends_at:        row.ends_at   || null,
                is_active:      !!row.is_active,
                priority:       row.priority  || 0,
                stackable:      !!row.stackable,
                trigger:        row.trigger_json || { min_quantity: 3, min_amount: 100 },
            };
            this.dialogVisible = true;
        },
        onTypeChange() {
            this.form.trigger = { min_quantity: 3, min_amount: 100 };
        },
        save() {
            this.$refs.ruleForm.validate(valid => {
                if (!valid) return;
                this.saving = true;
                const payload = {
                    ...this.form,
                    trigger_json: JSON.stringify(this.form.trigger),
                };
                this.$http.post('/discount-rules', payload)
                    .then(r => {
                        this.$message.success(r.data.message || 'Guardado');
                        this.dialogVisible = false;
                        this.load();
                    }).catch(e => {
                        const msg = e.response?.data?.message
                            || Object.values(e.response?.data?.errors || {}).flat().join(' ')
                            || 'Error al guardar';
                        this.$message.error(msg);
                    }).finally(() => { this.saving = false; });
            });
        },
        toggle(row) {
            this.$http.post('/discount-rules/' + row.id + '/toggle')
                .then(r => { row.is_active = r.data.is_active; })
                .catch(() => { row.is_active = !row.is_active; });
        },
        remove(row) {
            this.$confirm('¿Eliminar la regla "' + row.name + '"?', 'Confirmar', { type: 'warning' })
                .then(() => {
                    this.$http.delete('/discount-rules/' + row.id)
                        .then(r => {
                            this.$message.success(r.data.message || 'Eliminado');
                            this.load();
                        });
                }).catch(() => {});
        },
        typeLabel(type) {
            const map = {
                volume:     'Por volumen',
                auto:       'Automático',
                channel:    'Por canal',
                flash_sale: 'Flash Sale',
                bundle:     'Pack/Bundle',
            };
            return map[type] || type;
        },
        typeColor(type) {
            const map = {
                volume:     'primary',
                auto:       'success',
                channel:    'warning',
                flash_sale: 'danger',
                bundle:     '',
            };
            return map[type] || '';
        },
        appliesToLabel(v) {
            const map = { all: 'Todo', item: 'Producto', bundle: 'Pack', category: 'Categoría' };
            return map[v] || v;
        },
        isExpired(date) {
            if (!date) return false;
            return new Date(date) < new Date();
        },
    },
};
</script>

<style scoped>
/* ═══ SHOPIFY-STYLE DIALOG ═══ */

/* Dialog override */
.sp-dialog >>> .el-dialog { border-radius: 14px; overflow: hidden; }
.sp-dialog >>> .el-dialog__header { padding: 0; border-bottom: 1px solid #e5e7eb; }
.sp-dialog >>> .el-dialog__body { padding: 0; max-height: 75vh; overflow-y: auto; }
.sp-dialog >>> .el-dialog__footer { border-top: 1px solid #e5e7eb; padding: 14px 20px; }

/* Header */
.sp-dialog-header { display: flex; align-items: center; gap: 12px; padding: 14px 20px; }
.sp-back { background: none; border: none; cursor: pointer; padding: 4px; border-radius: 8px; color: #64748b; display: flex; }
.sp-back:hover { background: #f1f5f9; color: #1e293b; }
.sp-dialog-title { margin: 0; font-size: 16px; font-weight: 600; color: #1e293b; }

/* Form sections */
.sp-form { padding: 0; }
.sp-section { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; }
.sp-section:last-child { border-bottom: none; }
.sp-section--muted { background: #f9fafb; }
.sp-section-title { font-size: 14px; font-weight: 600; color: #374151; margin: 0 0 16px 0; }

/* Input number full width inside col */
.sp-form .el-input-number { max-width: 100% !important; }

/* Radio cards (tipo descuento) */
.sp-radio-cards { display: flex; gap: 10px; }
.sp-radio-card {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 14px 12px; border: 2px solid #e5e7eb; border-radius: 10px;
    cursor: pointer; transition: all .15s ease; text-align: center;
}
.sp-radio-card:hover { border-color: #94a3b8; }
.sp-radio-card--active { border-color: #4f46e5; background: #eef2ff; }
.sp-radio-card__icon { font-size: 20px; font-weight: 700; color: #64748b; }
.sp-radio-card--active .sp-radio-card__icon { color: #4f46e5; }
.sp-radio-card__label { font-size: 12px; font-weight: 500; color: #64748b; }
.sp-radio-card--active .sp-radio-card__label { color: #4338ca; }

/* Toggle row */
.sp-toggle-row { display: flex; align-items: center; gap: 10px; }
.sp-toggle-label { font-size: 13px; color: #64748b; }

/* Help text */
.sp-help { font-size: 11px; color: #9ca3af; margin: 4px 0 0; }

/* Footer */
.sp-dialog-footer { display: flex; justify-content: flex-end; gap: 8px; }

/* Element UI overrides inside dialog */
.sp-form >>> .el-form-item { margin-bottom: 18px; }
.sp-form >>> .el-form-item__label { font-size: 13px; font-weight: 600; color: #374151; padding-bottom: 4px; }
.sp-form >>> .el-input__inner { border-radius: 8px; }
.sp-form >>> .el-select .el-input__inner { border-radius: 8px; }
.sp-form >>> .el-input-number { width: 100%; max-width: 180px; }
.sp-form >>> .el-input-number .el-input__inner { border-radius: 8px; }

/* Responsive */
@media (max-width: 640px) {
    .sp-section { padding: 16px; }
    .sp-radio-cards { flex-direction: row; }
    .sp-form .col-6, .sp-form .col-8, .sp-form .col-4 { flex: 0 0 100%; max-width: 100%; }
}
</style>
