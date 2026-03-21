<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">Crea y gestiona cupones de descuento para tus clientes.</p>
            <el-button type="primary" icon="el-icon-plus" @click="openCreate">Nuevo cupón</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="coupons" v-loading="loading" style="width:100%">

                <el-table-column label="Código" width="150">
                    <template slot-scope="s">
                        <code style="font-size:13px;font-weight:700;letter-spacing:.5px">{{ s.row.code }}</code>
                    </template>
                </el-table-column>

                <el-table-column label="Descuento" width="140">
                    <template slot-scope="s">
                        <span v-if="s.row.type === 'percentage'">{{ s.row.value }}%</span>
                        <span v-else>S/ {{ s.row.value.toFixed(2) }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Mín. compra" width="120" align="right">
                    <template slot-scope="s">
                        <span v-if="s.row.min_amount">S/ {{ s.row.min_amount.toFixed(2) }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column label="Usos" width="100" align="center">
                    <template slot-scope="s">
                        <span>{{ s.row.used_count }}</span>
                        <span v-if="s.row.max_uses" class="text-muted"> / {{ s.row.max_uses }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="Vencimiento" width="150">
                    <template slot-scope="s">
                        <span v-if="s.row.expires_at" :class="{ 'text-danger': s.row.is_expired }">
                            {{ s.row.expires_at }}
                        </span>
                        <span v-else class="text-muted">Sin vencimiento</span>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="150" align="center">
                    <template slot-scope="s">
                        <el-tag v-if="!s.row.active" type="info" size="mini">Inactivo</el-tag>
                        <el-tag v-else-if="s.row.is_expired" type="danger" size="mini">Expirado</el-tag>
                        <el-tag v-else-if="s.row.is_maxed" type="warning" size="mini">Agotado</el-tag>
                        <el-tag v-else type="success" size="mini">Activo</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Activo" width="80" align="center">
                    <template slot-scope="s">
                        <el-switch v-model="s.row.active" @change="toggleActive(s.row)"></el-switch>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="110" align="center">
                    <template slot-scope="s">
                        <el-button-group>
                            <el-button size="mini" icon="el-icon-edit" @click="openEdit(s.row)"></el-button>
                            <el-button size="mini" type="danger" icon="el-icon-delete" @click="remove(s.row)"></el-button>
                        </el-button-group>
                    </template>
                </el-table-column>

            </el-table>
        </el-card>

        <el-dialog
            :title="form.id ? 'Editar Cupón' : 'Nuevo Cupón'"
            :visible.sync="dialogVisible"
            width="520px"
            :close-on-click-modal="false"
        >
            <el-form :model="form" ref="couponForm" label-width="130px" @submit.native.prevent>

                <el-form-item label="Código" prop="code"
                    :rules="[{required:true,message:'Requerido'},{pattern:/^[A-Z0-9_-]{2,50}$/i,message:'Solo letras, números, guion y guion bajo'}]">
                    <el-input v-model="form.code" placeholder="Ej: BIENVENIDA20" maxlength="50"
                        @input="form.code = form.code.toUpperCase()" style="width:100%"></el-input>
                </el-form-item>

                <el-form-item label="Tipo" prop="type" :rules="[{required:true}]">
                    <el-radio-group v-model="form.type">
                        <el-radio label="percentage">Porcentaje (%)</el-radio>
                        <el-radio label="fixed">Fijo (S/)</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item label="Valor" prop="value"
                    :rules="[{required:true,message:'Requerido'},{type:'number',min:0.01,message:'Debe ser mayor a 0'}]">
                    <el-input-number v-model="form.value" :min="0.01" :precision="2" :step="5"
                        style="width:160px"></el-input-number>
                    <span class="ml-2 text-muted">{{ form.type === 'percentage' ? '%' : 'S/' }}</span>
                </el-form-item>

                <el-form-item label="Monto mínimo">
                    <el-input-number v-model="form.min_amount" :min="0" :precision="2" :step="10"
                        placeholder="Opcional" style="width:160px"></el-input-number>
                    <span class="ml-2 text-muted">S/ (opcional)</span>
                </el-form-item>

                <el-form-item label="Límite de usos">
                    <el-input-number v-model="form.max_uses" :min="1" :precision="0" :step="10"
                        placeholder="Sin límite" style="width:160px"></el-input-number>
                    <span class="ml-2 text-muted">usos (vacío = sin límite)</span>
                </el-form-item>

                <el-form-item label="Vencimiento">
                    <el-date-picker
                        v-model="form.expires_at"
                        type="datetime"
                        format="yyyy-MM-dd HH:mm"
                        value-format="yyyy-MM-dd HH:mm"
                        placeholder="Sin vencimiento"
                        style="width:100%"
                    ></el-date-picker>
                </el-form-item>

                <el-form-item label="Activo">
                    <el-switch v-model="form.active"></el-switch>
                </el-form-item>

            </el-form>

            <span slot="footer">
                <el-button @click="dialogVisible = false">Cancelar</el-button>
                <el-button type="primary" :loading="saving" @click="save">Guardar</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            coupons: [],
            loading: false,
            saving: false,
            dialogVisible: false,
            form: this.emptyForm()
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        emptyForm() {
            return { id: null, code: '', type: 'percentage', value: 10, min_amount: null, max_uses: null, expires_at: null, active: true };
        },
        load() {
            this.loading = true;
            axios.get('/ecommerce/coupons/records')
                .then(r => { this.coupons = r.data.data; })
                .finally(() => { this.loading = false; });
        },
        openCreate() {
            this.form = this.emptyForm();
            this.dialogVisible = true;
            this.$nextTick(() => { if (this.$refs.couponForm) this.$refs.couponForm.clearValidate(); });
        },
        openEdit(row) {
            this.form = {
                id: row.id,
                code: row.code,
                type: row.type,
                value: row.value,
                min_amount: row.min_amount,
                max_uses: row.max_uses,
                expires_at: row.expires_at || null,
                active: row.active
            };
            this.dialogVisible = true;
        },
        save() {
            this.$refs.couponForm.validate(valid => {
                if (!valid) return;
                this.saving = true;
                const req = this.form.id
                    ? axios.put('/ecommerce/coupons/' + this.form.id, this.form)
                    : axios.post('/ecommerce/coupons', this.form);
                req.then(r => {
                    this.$message.success(r.data.message || 'Guardado');
                    this.dialogVisible = false;
                    this.load();
                }).catch(e => {
                    const msg = e.response && e.response.data && e.response.data.message
                        ? e.response.data.message
                        : (e.response && e.response.data && e.response.data.errors
                            ? Object.values(e.response.data.errors).flat().join(' ') : 'Error al guardar');
                    this.$message.error(msg);
                }).finally(() => { this.saving = false; });
            });
        },
        toggleActive(row) {
            axios.put('/ecommerce/coupons/' + row.id, { ...row, active: row.active })
                .catch(() => { row.active = !row.active; });
        },
        remove(row) {
            this.$confirm('¿Eliminar el cupón "' + row.code + '"?', 'Confirmar', { type: 'warning' })
                .then(() => {
                    axios.delete('/ecommerce/coupons/' + row.id)
                        .then(r => {
                            this.$message.success(r.data.message || 'Eliminado');
                            this.load();
                        });
                }).catch(() => {});
        }
    }
};
</script>
