<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">Crea ofertas relámpago con cuenta regresiva para impulsar tus ventas.</p>
            <el-button type="warning" icon="el-icon-plus" @click="openCreate">Nueva flash sale</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="sales" v-loading="loading" style="width:100%">

                <el-table-column label="Título" prop="title" min-width="160">
                    <template slot-scope="s">
                        <strong>{{ s.row.title }}</strong>
                        <div class="text-muted small" v-if="s.row.subtitle">{{ s.row.subtitle }}</div>
                    </template>
                </el-table-column>

                <el-table-column label="Inicio" width="150">
                    <template slot-scope="s">{{ s.row.starts_at || '—' }}</template>
                </el-table-column>

                <el-table-column label="Fin" width="150">
                    <template slot-scope="s">{{ s.row.ends_at }}</template>
                </el-table-column>

                <el-table-column label="Productos" width="100" align="center">
                    <template slot-scope="s">
                        <el-tag size="mini">{{ s.row.items_count }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Estado" width="120" align="center">
                    <template slot-scope="s">
                        <el-tag v-if="s.row.is_live" type="success" size="mini">En vivo</el-tag>
                        <el-tag v-else-if="s.row.active" type="warning" size="mini">Programada</el-tag>
                        <el-tag v-else type="info" size="mini">Inactiva</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Activa" width="80" align="center">
                    <template slot-scope="s">
                        <el-switch v-model="s.row.active" @change="toggleActive(s.row)"></el-switch>
                    </template>
                </el-table-column>

                <el-table-column label="Acciones" width="130" align="center">
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
            :title="form.id ? 'Editar Flash Sale' : 'Nueva Flash Sale'"
            :visible.sync="dialogVisible"
            width="680px"
            :close-on-click-modal="false"
        >
            <el-form :model="form" ref="fsForm" label-width="120px" @submit.native.prevent>

                <el-form-item label="Título" prop="title" :rules="[{required:true,message:'Requerido'}]">
                    <el-input v-model="form.title" placeholder="Ej: Oferta del día" maxlength="100" show-word-limit></el-input>
                </el-form-item>

                <el-form-item label="Subtítulo">
                    <el-input v-model="form.subtitle" placeholder="Ej: Solo por hoy" maxlength="120"></el-input>
                </el-form-item>

                <el-form-item label="Inicio">
                    <el-date-picker
                        v-model="form.starts_at"
                        type="datetime"
                        format="yyyy-MM-dd HH:mm"
                        value-format="yyyy-MM-dd HH:mm"
                        placeholder="Opcional (por defecto: ahora)"
                        style="width:100%"
                    ></el-date-picker>
                </el-form-item>

                <el-form-item label="Fin" prop="ends_at" :rules="[{required:true,message:'Requerido'}]">
                    <el-date-picker
                        v-model="form.ends_at"
                        type="datetime"
                        format="yyyy-MM-dd HH:mm"
                        value-format="yyyy-MM-dd HH:mm"
                        placeholder="Fecha y hora de cierre"
                        style="width:100%"
                    ></el-date-picker>
                </el-form-item>

                <el-form-item label="Activa">
                    <el-switch v-model="form.active"></el-switch>
                </el-form-item>

                <el-divider>Productos en oferta</el-divider>

                <el-form-item label="Agregar producto">
                    <el-autocomplete
                        v-model="itemSearch"
                        :fetch-suggestions="searchItems"
                        placeholder="Buscar producto..."
                        @select="addItem"
                        style="width:100%"
                        value-key="description"
                    >
                        <template slot-scope="{item}">
                            <span>{{ item.description }}</span>
                            <span class="text-muted ml-2 small">S/ {{ item.sale_unit_price }}</span>
                        </template>
                    </el-autocomplete>
                </el-form-item>

                <el-table :data="form.items" size="mini" v-if="form.items.length">
                    <el-table-column label="Producto" prop="description" min-width="180"></el-table-column>
                    <el-table-column label="Precio regular" width="120" align="right">
                        <template slot-scope="s">S/ {{ s.row.regular_price }}</template>
                    </el-table-column>
                    <el-table-column label="Precio flash" width="130">
                        <template slot-scope="s">
                            <el-input-number
                                v-model="s.row.flash_price"
                                :min="0.01"
                                :precision="2"
                                :step="1"
                                size="mini"
                                style="width:110px"
                            ></el-input-number>
                        </template>
                    </el-table-column>
                    <el-table-column label="%" width="60" align="center">
                        <template slot-scope="s">
                            <span class="text-danger small" v-if="s.row.regular_price > 0">
                                -{{ discount(s.row) }}%
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column width="50" align="center">
                        <template slot-scope="s">
                            <el-button type="text" icon="el-icon-close" @click="removeItem(s.$index)"></el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <p v-else class="text-muted text-center mt-2 small">Sin productos aún. Busca y agrega al menos uno.</p>

            </el-form>

            <span slot="footer">
                <el-button @click="dialogVisible = false">Cancelar</el-button>
                <el-button type="warning" :loading="saving" @click="save">Guardar</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            sales: [],
            loading: false,
            saving: false,
            dialogVisible: false,
            itemSearch: '',
            _allItems: null,
            form: {
                id: null,
                title: '',
                subtitle: '',
                starts_at: null,
                ends_at: null,
                active: true,
                items: []
            }
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        load() {
            this.loading = true;
            axios.get('/ecommerce/flash-sales/records')
                .then(r => { this.sales = r.data.data; })
                .finally(() => { this.loading = false; });
        },
        openCreate() {
            this.form = { id: null, title: '', subtitle: '', starts_at: null, ends_at: null, active: true, items: [] };
            this.itemSearch = '';
            this.dialogVisible = true;
            this.$nextTick(() => { if (this.$refs.fsForm) this.$refs.fsForm.clearValidate(); });
        },
        openEdit(row) {
            this.form = {
                id: row.id,
                title: row.title,
                subtitle: row.subtitle || '',
                starts_at: row.starts_at || null,
                ends_at: row.ends_at,
                active: row.active,
                items: row.items.map(i => ({ id: i.id, description: i.description, regular_price: i.regular_price, flash_price: i.flash_price }))
            };
            this.itemSearch = '';
            this.dialogVisible = true;
        },
        save() {
            this.$refs.fsForm.validate(valid => {
                if (!valid) return;
                if (!this.form.items.length) {
                    this.$message.warning('Agrega al menos un producto');
                    return;
                }
                this.saving = true;
                const payload = {
                    title: this.form.title,
                    subtitle: this.form.subtitle,
                    starts_at: this.form.starts_at,
                    ends_at: this.form.ends_at,
                    active: this.form.active,
                    items: this.form.items.map(i => ({ id: i.id, flash_price: i.flash_price }))
                };
                const req = this.form.id
                    ? axios.put('/ecommerce/flash-sales/' + this.form.id, payload)
                    : axios.post('/ecommerce/flash-sales', payload);

                req.then(r => {
                    this.$message.success(r.data.message || 'Guardado');
                    this.dialogVisible = false;
                    this.load();
                }).catch(e => {
                    const msg = e.response && e.response.data && e.response.data.message
                        ? e.response.data.message : 'Error al guardar';
                    this.$message.error(msg);
                }).finally(() => { this.saving = false; });
            });
        },
        toggleActive(row) {
            axios.put('/ecommerce/flash-sales/' + row.id, {
                title: row.title,
                ends_at: row.ends_at,
                active: row.active
            }).catch(() => { row.active = !row.active; });
        },
        remove(row) {
            this.$confirm('¿Eliminar "' + row.title + '"?', 'Confirmar', { type: 'warning' })
                .then(() => {
                    axios.delete('/ecommerce/flash-sales/' + row.id)
                        .then(r => {
                            this.$message.success(r.data.message || 'Eliminada');
                            this.load();
                        });
                }).catch(() => {});
        },
        searchItems(query, cb) {
            if (!query || query.length < 2) { cb([]); return; }
            if (this._allItems) { cb(this._filterItems(query)); return; }
            axios.get('/ecommerce/items_bar')
                .then(r => {
                    this._allItems = Array.isArray(r.data) ? r.data : (r.data.data || []);
                    cb(this._filterItems(query));
                }).catch(() => { cb([]); });
        },
        _filterItems(query) {
            const q = query.toLowerCase();
            return (this._allItems || []).filter(i =>
                i.description && i.description.toLowerCase().indexOf(q) !== -1
            ).slice(0, 20);
        },
        addItem(item) {
            const exists = this.form.items.find(i => i.id === item.id);
            if (exists) { this.$message.info('El producto ya está en la lista'); return; }
            const price = parseFloat(item.amount_sale_unit_price) || parseFloat(item.sale_unit_price) || 0;
            this.form.items.push({ id: item.id, description: item.description, regular_price: price, flash_price: price });
            this.itemSearch = '';
        },
        removeItem(index) {
            this.form.items.splice(index, 1);
        },
        discount(item) {
            if (!item.regular_price || item.regular_price <= 0) return 0;
            return Math.round(((item.regular_price - item.flash_price) / item.regular_price) * 100);
        }
    }
};
</script>
