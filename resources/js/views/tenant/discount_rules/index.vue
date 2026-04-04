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
                        <span class="dr-discount-badge">
                            <span v-if="s.row.discount_type === 'percentage'">{{ s.row.discount_value }}%</span>
                            <span v-else>S/ {{ Number(s.row.discount_value).toFixed(2) }}</span>
                        </span>
                    </template>
                </el-table-column>

                <el-table-column label="Aplica a" width="110">
                    <template slot-scope="s">
                        <span>{{ appliesToLabel(s.row.applies_to) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Usos" width="100" align="center">
                    <template slot-scope="s">
                        <div class="dr-usage">
                            <span class="dr-usage-count">{{ s.row.used_count }}</span>
                            <span class="dr-usage-max" v-if="s.row.max_uses && s.row.max_uses > 0"> / {{ s.row.max_uses }}</span>
                            <span class="dr-usage-max" v-else> / &infin;</span>
                        </div>
                        <div v-if="s.row.max_uses && s.row.max_uses > 0" class="dr-usage-bar">
                            <div class="dr-usage-bar-fill" :style="{ width: Math.min(100, (s.row.used_count / s.row.max_uses) * 100) + '%' }"></div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Vigencia" width="180">
                    <template slot-scope="s">
                        <div v-if="s.row.starts_at || s.row.ends_at" style="font-size:12px">
                            <span>{{ formatDate(s.row.starts_at) || 'Sin inicio' }}</span>
                            <span class="text-muted"> &rarr; </span>
                            <span :class="{ 'text-danger font-weight-bold': isExpired(s.row.ends_at) }">
                                {{ formatDate(s.row.ends_at) || 'Sin fin' }}
                            </span>
                            <div v-if="isExpired(s.row.ends_at)" style="font-size:10px" class="text-danger">Expirada</div>
                        </div>
                        <span v-else class="text-muted">Permanente</span>
                    </template>
                </el-table-column>

                <el-table-column label="Acumulable" width="100" align="center">
                    <template slot-scope="s">
                        <el-tag v-if="s.row.stackable" type="success" size="mini" effect="light">Acumulable</el-tag>
                        <el-tag v-else type="info" size="mini" effect="light">Exclusiva</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Activo" width="70" align="center">
                    <template slot-scope="s">
                        <el-switch v-model="s.row.is_active" @change="toggle(s.row)" active-color="#22c55e" inactive-color="#e5e7eb"></el-switch>
                    </template>
                </el-table-column>

                <el-table-column label="" width="90" align="center">
                    <template slot-scope="s">
                        <el-button-group>
                            <el-button size="mini" icon="el-icon-edit" @click="openEdit(s.row)" title="Editar"></el-button>
                            <el-button size="mini" type="danger" icon="el-icon-delete" @click="remove(s.row)" title="Eliminar"></el-button>
                        </el-button-group>
                    </template>
                </el-table-column>

            </el-table>

            <el-pagination
                v-if="pagination.total > pagination.per_page"
                class="mt-3 text-right"
                background
                layout="prev, pager, next, total"
                :page-size="pagination.per_page"
                :total="pagination.total"
                :current-page.sync="pagination.current_page"
                @current-change="load">
            </el-pagination>
        </el-card>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- Dialog Form — Rediseño profesional                            -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <el-dialog
            :visible.sync="dialogVisible"
            width="720px"
            :close-on-click-modal="false"
            :show-close="false"
            custom-class="dr-dialog"
        >
            <template slot="title">
                <div class="dr-dialog-header">
                    <button type="button" class="dr-back" @click="dialogVisible = false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div>
                        <h3 class="dr-dialog-title">{{ form.id ? 'Editar regla' : 'Nueva regla de descuento' }}</h3>
                        <p class="dr-dialog-subtitle" v-if="!form.id">Configura condiciones y el descuento que se aplicara automaticamente</p>
                    </div>
                </div>
            </template>

            <el-form :model="form" ref="ruleForm" label-position="top" @submit.native.prevent class="dr-form">

                <!-- SECCION 1: Identificacion -->
                <div class="dr-section">
                    <div class="dr-section-header">
                        <span class="dr-section-number">1</span>
                        <h4 class="dr-section-title">Informacion general</h4>
                    </div>

                    <el-form-item label="Nombre de la regla" prop="name" :rules="[{required:true,message:'Requerido'}]">
                        <el-input v-model="form.name" maxlength="100" placeholder="Ej: 10% dcto en compras mayores a S/ 200" show-word-limit></el-input>
                    </el-form-item>

                    <div class="row">
                        <div class="col-8">
                            <el-form-item label="Tipo de regla" prop="type" :rules="[{required:true,message:'Selecciona un tipo'}]">
                                <el-select v-model="form.type" style="width:100%" @change="onTypeChange" placeholder="Selecciona tipo">
                                    <el-option v-for="t in types" :key="t.id" :value="t.id">
                                        <span>{{ t.icon }} {{ t.label }}</span>
                                    </el-option>
                                </el-select>
                            </el-form-item>
                        </div>
                        <div class="col-4 d-flex align-items-center pt-2">
                            <el-switch v-model="form.is_active" active-text="Activa" inactive-text="Inactiva" active-color="#22c55e"></el-switch>
                        </div>
                    </div>
                </div>

                <!-- SECCION 2: Condiciones -->
                <div class="dr-section" v-if="form.type">
                    <div class="dr-section-header">
                        <span class="dr-section-number">2</span>
                        <h4 class="dr-section-title">Condiciones de activacion</h4>
                    </div>

                    <!-- Tipo: Volumen -->
                    <template v-if="form.type === 'volume'">
                        <div class="dr-condition-card">
                            <div class="dr-condition-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                            </div>
                            <div class="dr-condition-body">
                                <label class="dr-condition-label">Cantidad minima de productos</label>
                                <el-input-number v-model="form.trigger.min_quantity" :min="2" :precision="0" controls-position="right" style="width:160px"></el-input-number>
                                <span class="dr-condition-hint">unidades del mismo producto</span>
                            </div>
                        </div>
                    </template>

                    <!-- Tipo: Auto (monto minimo) -->
                    <template v-if="form.type === 'auto'">
                        <div class="dr-condition-card">
                            <div class="dr-condition-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                            </div>
                            <div class="dr-condition-body">
                                <label class="dr-condition-label">Monto minimo de compra</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dr-currency">S/</span>
                                    <el-input-number v-model="form.trigger.min_amount" :min="0" :precision="2" :step="10" controls-position="right" style="width:180px"></el-input-number>
                                </div>
                                <span class="dr-condition-hint">Se activa cuando el carrito supera este monto</span>
                            </div>
                        </div>
                    </template>

                    <!-- Tipo: Canal -->
                    <template v-if="form.type === 'channel'">
                        <div class="dr-condition-card">
                            <div class="dr-condition-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div class="dr-condition-body">
                                <label class="dr-condition-label">Canal de venta</label>
                                <el-select v-model="form.channel_id" style="width:100%" placeholder="Selecciona un canal">
                                    <el-option v-for="c in channels" :key="c.id" :label="c.name + ' (' + c.type + ')'" :value="c.id"></el-option>
                                </el-select>
                                <span class="dr-condition-hint">Solo aplica en pedidos de este canal</span>
                            </div>
                        </div>
                    </template>

                    <!-- Tipo: Flash Sale -->
                    <template v-if="form.type === 'flash_sale'">
                        <div class="dr-condition-card dr-condition-card--flash">
                            <div class="dr-condition-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2"><path d="M13 3l0 7l6 0l-8 11l0-7l-6 0l8-11"/></svg>
                            </div>
                            <div class="dr-condition-body">
                                <label class="dr-condition-label">Periodo de la oferta</label>
                                <div class="row">
                                    <div class="col-6">
                                        <el-date-picker v-model="form.starts_at" type="datetime"
                                            format="dd/MM/yyyy HH:mm" value-format="yyyy-MM-dd HH:mm"
                                            placeholder="Inicio" style="width:100%" size="small"></el-date-picker>
                                    </div>
                                    <div class="col-6">
                                        <el-date-picker v-model="form.ends_at" type="datetime"
                                            format="dd/MM/yyyy HH:mm" value-format="yyyy-MM-dd HH:mm"
                                            placeholder="Fin" style="width:100%" size="small"></el-date-picker>
                                    </div>
                                </div>
                                <span class="dr-condition-hint" v-if="form.starts_at && form.ends_at">
                                    Duracion: {{ dateDiff(form.starts_at, form.ends_at) }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <!-- Tipo: Bundle -->
                    <template v-if="form.type === 'bundle'">
                        <div class="dr-condition-card">
                            <div class="dr-condition-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>
                            </div>
                            <div class="dr-condition-body">
                                <label class="dr-condition-label">Se activa al comprar un pack/bundle</label>
                                <span class="dr-condition-hint">Aplica automaticamente cuando el carrito contiene un conjunto</span>
                            </div>
                        </div>
                    </template>

                    <el-form-item label="Aplica a" prop="applies_to" :rules="[{required:true}]" class="mt-3">
                        <el-radio-group v-model="form.applies_to" class="dr-applies-group">
                            <el-radio-button label="all">Todo el carrito</el-radio-button>
                            <el-radio-button label="item">Producto</el-radio-button>
                            <el-radio-button label="bundle">Pack</el-radio-button>
                            <el-radio-button label="category">Categoria</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                </div>

                <!-- SECCION 3: Descuento -->
                <div class="dr-section">
                    <div class="dr-section-header">
                        <span class="dr-section-number">3</span>
                        <h4 class="dr-section-title">Descuento</h4>
                    </div>

                    <div class="dr-discount-selector">
                        <div class="dr-discount-option"
                             :class="{ 'dr-discount-option--active': form.discount_type === 'percentage' }"
                             @click="form.discount_type = 'percentage'">
                            <div class="dr-discount-option-icon">%</div>
                            <div class="dr-discount-option-text">
                                <strong>Porcentaje</strong>
                                <small>Descuento proporcional al monto</small>
                            </div>
                        </div>
                        <div class="dr-discount-option"
                             :class="{ 'dr-discount-option--active': form.discount_type === 'fixed' }"
                             @click="form.discount_type = 'fixed'">
                            <div class="dr-discount-option-icon">S/</div>
                            <div class="dr-discount-option-text">
                                <strong>Monto fijo</strong>
                                <small>Descuento de un valor exacto</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-5">
                            <el-form-item :label="form.discount_type === 'percentage' ? 'Porcentaje de descuento' : 'Monto del descuento (S/)'"
                                          prop="discount_value" :rules="[{required:true,message:'Requerido'},{type:'number',min:0.01,message:'Mayor a 0'}]">
                                <el-input-number v-model="form.discount_value" :min="0.01"
                                    :max="form.discount_type === 'percentage' ? 100 : 99999"
                                    :precision="2" :step="5" controls-position="right" style="width:100%"></el-input-number>
                            </el-form-item>
                        </div>
                        <div class="col-7 d-flex align-items-center">
                            <div class="dr-preview-pill" v-if="form.discount_value > 0">
                                <template v-if="form.discount_type === 'percentage'">
                                    Ejemplo: En S/ 100 → descuenta <strong>S/ {{ (100 * form.discount_value / 100).toFixed(2) }}</strong>
                                </template>
                                <template v-else>
                                    Descuenta <strong>S/ {{ Number(form.discount_value).toFixed(2) }}</strong> fijo
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCION 4: Avanzado -->
                <div class="dr-section dr-section--muted">
                    <div class="dr-section-header">
                        <span class="dr-section-number">4</span>
                        <h4 class="dr-section-title">Configuracion avanzada</h4>
                    </div>

                    <div class="row">
                        <div class="col-4">
                            <el-form-item label="Prioridad">
                                <el-input-number v-model="form.priority" :min="0" :max="999" :precision="0" controls-position="right" style="width:100%"></el-input-number>
                                <p class="dr-help">Mayor numero = se evalua primero</p>
                            </el-form-item>
                        </div>
                        <div class="col-4">
                            <el-form-item label="Limite de usos">
                                <el-input-number v-model="form.max_uses" :min="0" :precision="0" :step="10" controls-position="right" style="width:100%"></el-input-number>
                                <p class="dr-help">0 = ilimitado</p>
                            </el-form-item>
                        </div>
                        <div class="col-4 d-flex align-items-center pt-1">
                            <div>
                                <label class="dr-help" style="display:block;margin-bottom:8px;font-weight:600;color:#374151">Acumulable</label>
                                <el-switch v-model="form.stackable" active-color="#22c55e"
                                    :active-text="form.stackable ? 'Si' : 'No'"
                                ></el-switch>
                                <p class="dr-help">{{ form.stackable ? 'Se combina con otras reglas' : 'Solo aplica sola (exclusiva)' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Vigencia para tipos que no son flash_sale -->
                    <div class="row" v-if="form.type !== 'flash_sale'">
                        <div class="col-6">
                            <el-form-item label="Vigencia desde (opcional)">
                                <el-date-picker v-model="form.starts_at" type="datetime"
                                    format="dd/MM/yyyy HH:mm" value-format="yyyy-MM-dd HH:mm"
                                    placeholder="Sin limite de inicio" style="width:100%" size="small" clearable></el-date-picker>
                            </el-form-item>
                        </div>
                        <div class="col-6">
                            <el-form-item label="Vigencia hasta (opcional)">
                                <el-date-picker v-model="form.ends_at" type="datetime"
                                    format="dd/MM/yyyy HH:mm" value-format="yyyy-MM-dd HH:mm"
                                    placeholder="Sin limite de fin" style="width:100%" size="small" clearable></el-date-picker>
                            </el-form-item>
                        </div>
                    </div>
                </div>

            </el-form>

            <!-- Footer -->
            <template slot="footer">
                <div class="dr-dialog-footer">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" :loading="saving" @click="save" icon="el-icon-check">
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
                max_uses: 0,
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
                this.types = (r.data.types || []).map(t => ({
                    ...t,
                    icon: { volume: '📦', auto: '💰', channel: '🏪', flash_sale: '⚡', bundle: '🎁' }[t.id] || '',
                }));
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
                max_uses:       row.max_uses || 0,
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

                // Validacion frontend: fechas
                if (this.form.starts_at && this.form.ends_at && this.form.ends_at < this.form.starts_at) {
                    return this.$message.error('La fecha de fin debe ser posterior a la de inicio');
                }
                // Validacion frontend: porcentaje max 100
                if (this.form.discount_type === 'percentage' && this.form.discount_value > 100) {
                    return this.$message.error('El porcentaje no puede ser mayor a 100%');
                }

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
            return { volume: 'Por volumen', auto: 'Automatico', channel: 'Por canal', flash_sale: 'Flash Sale', bundle: 'Pack/Bundle' }[type] || type;
        },
        typeColor(type) {
            return { volume: 'primary', auto: 'success', channel: 'warning', flash_sale: 'danger', bundle: '' }[type] || '';
        },
        appliesToLabel(v) {
            return { all: 'Todo', item: 'Producto', bundle: 'Pack', category: 'Categoria' }[v] || v;
        },
        isExpired(date) {
            return date ? new Date(date) < new Date() : false;
        },
        formatDate(d) {
            if (!d) return '';
            try { return new Date(d).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
            catch(e) { return d; }
        },
        dateDiff(start, end) {
            if (!start || !end) return '';
            var ms = new Date(end) - new Date(start);
            var h = Math.floor(ms / 3600000);
            if (h < 24) return h + ' horas';
            return Math.floor(h / 24) + ' dias, ' + (h % 24) + 'h';
        },
    },
};
</script>

<style scoped>
/* ═══ DIALOG ═══ */
.dr-dialog >>> .el-dialog { border-radius: 16px; overflow: hidden; }
.dr-dialog >>> .el-dialog__header { padding: 0; border-bottom: 1px solid #e5e7eb; }
.dr-dialog >>> .el-dialog__body { padding: 0; max-height: 78vh; overflow-y: auto; }
.dr-dialog >>> .el-dialog__footer { border-top: 1px solid #e5e7eb; padding: 14px 24px; }

.dr-dialog-header { display: flex; align-items: flex-start; gap: 14px; padding: 18px 24px; }
.dr-back { background: none; border: none; cursor: pointer; padding: 6px; border-radius: 8px; color: #64748b; display: flex; margin-top: 2px; }
.dr-back:hover { background: #f1f5f9; color: #1e293b; }
.dr-dialog-title { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }
.dr-dialog-subtitle { margin: 2px 0 0; font-size: 13px; color: #94a3b8; }

/* Sections */
.dr-form { padding: 0; }
.dr-section { padding: 24px; border-bottom: 1px solid #f1f5f9; }
.dr-section:last-child { border-bottom: none; }
.dr-section--muted { background: #f8fafc; }
.dr-section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.dr-section-number { width: 28px; height: 28px; border-radius: 50%; background: #4F46E5; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.dr-section-title { margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; }

/* Condition cards */
.dr-condition-card { display: flex; gap: 16px; padding: 18px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
.dr-condition-card--flash { border-color: #fecaca; background: #fef2f2; }
.dr-condition-icon { flex-shrink: 0; width: 48px; height: 48px; border-radius: 12px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }
.dr-condition-body { flex: 1; }
.dr-condition-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
.dr-condition-hint { display: block; font-size: 11px; color: #9ca3af; margin-top: 6px; }
.dr-currency { font-size: 16px; font-weight: 700; color: #64748b; }

/* Discount selector */
.dr-discount-selector { display: flex; gap: 12px; }
.dr-discount-option { flex: 1; display: flex; align-items: center; gap: 12px; padding: 16px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all .15s; }
.dr-discount-option:hover { border-color: #a5b4fc; }
.dr-discount-option--active { border-color: #4F46E5; background: #eef2ff; }
.dr-discount-option-icon { width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; color: #64748b; }
.dr-discount-option--active .dr-discount-option-icon { background: #4F46E5; color: #fff; }
.dr-discount-option-text strong { display: block; font-size: 14px; color: #1e293b; }
.dr-discount-option-text small { font-size: 11px; color: #9ca3af; }

/* Preview pill */
.dr-preview-pill { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 8px 14px; font-size: 13px; color: #166534; }

/* Applies to group */
.dr-applies-group >>> .el-radio-button__inner { border-radius: 8px !important; font-size: 13px; }

/* Help */
.dr-help { font-size: 11px; color: #9ca3af; margin: 4px 0 0; }

/* Footer */
.dr-dialog-footer { display: flex; justify-content: flex-end; gap: 8px; }

/* Table badges */
.dr-discount-badge { font-weight: 700; color: #4F46E5; }
.dr-usage { font-size: 13px; }
.dr-usage-count { font-weight: 600; }
.dr-usage-max { color: #9ca3af; }
.dr-usage-bar { width: 100%; height: 4px; background: #e5e7eb; border-radius: 2px; margin-top: 4px; }
.dr-usage-bar-fill { height: 100%; background: #4F46E5; border-radius: 2px; transition: width .3s; }

/* Element UI overrides */
.dr-form >>> .el-form-item { margin-bottom: 16px; }
.dr-form >>> .el-form-item__label { font-size: 13px; font-weight: 600; color: #374151; padding-bottom: 4px; }
.dr-form >>> .el-input__inner { border-radius: 8px; }
.dr-form >>> .el-select .el-input__inner { border-radius: 8px; }
.dr-form >>> .el-input-number .el-input__inner { border-radius: 8px; }

@media (max-width: 640px) {
    .dr-section { padding: 16px; }
    .dr-discount-selector { flex-direction: column; }
    .dr-form .col-4, .dr-form .col-5, .dr-form .col-6, .dr-form .col-7, .dr-form .col-8 { flex: 0 0 100%; max-width: 100%; }
}
</style>
