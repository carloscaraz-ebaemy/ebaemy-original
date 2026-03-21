<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                Clientes que pidieron aviso cuando un producto vuelva a tener stock.
            </p>
            <el-button type="success" icon="el-icon-bell" :loading="sending" @click="sendNow">
                Enviar avisos ahora
            </el-button>
        </div>

        <el-card shadow="never">
            <div class="d-flex mb-3" style="gap:12px;flex-wrap:wrap">
                <el-input v-model="search" placeholder="Buscar por email o producto..." prefix-icon="el-icon-search"
                    clearable style="width:280px" @input="filterData"></el-input>
                <el-select v-model="filterStatus" placeholder="Estado" style="width:150px" @change="filterData">
                    <el-option label="Todos" value=""></el-option>
                    <el-option label="Pendientes" value="pending"></el-option>
                    <el-option label="Notificados" value="notified"></el-option>
                </el-select>
                <el-tag type="info" size="small" style="align-self:center">
                    {{ filtered.length }} registro{{ filtered.length !== 1 ? 's' : '' }}
                </el-tag>
            </div>

            <el-table :data="filtered" v-loading="loading" style="width:100%" size="small">

                <el-table-column label="Producto" min-width="200">
                    <template slot-scope="s">
                        <strong>{{ s.row.item_description }}</strong>
                        <div class="text-muted small" v-if="s.row.item_internal_id">
                            Cód: {{ s.row.item_internal_id }}
                        </div>
                    </template>
                </el-table-column>

                <el-table-column label="Stock actual" width="110" align="center">
                    <template slot-scope="s">
                        <el-tag :type="s.row.item_stock > 0 ? 'success' : 'danger'" size="mini">
                            {{ s.row.item_stock > 0 ? s.row.item_stock + ' uds.' : 'Sin stock' }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="Email" min-width="180" prop="email"></el-table-column>

                <el-table-column label="Nombre" width="140">
                    <template slot-scope="s">{{ s.row.name || '—' }}</template>
                </el-table-column>

                <el-table-column label="Solicitado" width="140">
                    <template slot-scope="s">{{ s.row.created_at }}</template>
                </el-table-column>

                <el-table-column label="Estado" width="120" align="center">
                    <template slot-scope="s">
                        <el-tag v-if="s.row.notified" type="success" size="mini">Notificado</el-tag>
                        <el-tag v-else-if="s.row.item_stock > 0" type="warning" size="mini">Stock disponible</el-tag>
                        <el-tag v-else type="info" size="mini">Esperando stock</el-tag>
                    </template>
                </el-table-column>

                <el-table-column width="60" align="center">
                    <template slot-scope="s">
                        <el-button type="text" icon="el-icon-delete" style="color:#f56c6c"
                            @click="remove(s.row)"></el-button>
                    </template>
                </el-table-column>

            </el-table>
        </el-card>
    </div>
</template>

<script>
export default {
    data() {
        return {
            rows: [],
            filtered: [],
            loading: false,
            sending: false,
            search: '',
            filterStatus: '',
        };
    },
    mounted() {
        this.load();
    },
    methods: {
        load() {
            this.loading = true;
            axios.get('/ecommerce/stock-notifications/records')
                .then(r => { this.rows = r.data.data; this.filterData(); })
                .finally(() => { this.loading = false; });
        },
        filterData() {
            let list = this.rows;
            if (this.search) {
                const q = this.search.toLowerCase();
                list = list.filter(r =>
                    (r.email && r.email.toLowerCase().includes(q)) ||
                    (r.item_description && r.item_description.toLowerCase().includes(q))
                );
            }
            if (this.filterStatus === 'pending') list = list.filter(r => !r.notified);
            if (this.filterStatus === 'notified') list = list.filter(r => r.notified);
            this.filtered = list;
        },
        sendNow() {
            this.$confirm('¿Enviar emails a todos los suscriptores de productos con stock disponible?', 'Confirmar', { type: 'info' })
                .then(() => {
                    this.sending = true;
                    axios.post('/ecommerce/stock-notifications/send')
                        .then(r => {
                            this.$message.success(r.data.message || 'Enviados');
                            this.load();
                        })
                        .catch(() => { this.$message.error('Error al enviar'); })
                        .finally(() => { this.sending = false; });
                }).catch(() => {});
        },
        remove(row) {
            this.$confirm('¿Eliminar la suscripción de ' + row.email + '?', 'Confirmar', { type: 'warning' })
                .then(() => {
                    axios.delete('/ecommerce/stock-notifications/' + row.id)
                        .then(() => { this.$message.success('Eliminado'); this.load(); });
                }).catch(() => {});
        }
    }
};
</script>
