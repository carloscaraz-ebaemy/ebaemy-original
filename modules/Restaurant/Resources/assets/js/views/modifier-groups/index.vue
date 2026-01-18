<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/restaurant/modifier-groups">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-tools-kitchen-2"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M19 3v12h-5c-.023 -3.681 .184 -7.406 5 -12zm0 12v6h-1v-3m-10 -14v17m-3 -17v3a3 3 0 1 0 6 0v-3"
                        />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Modificadores</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm me-2" @click.prevent="openCreate">
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </div>

        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <!-- <th>#</th> -->
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Productos</th>
                                <!-- <th class="text-center">Activo</th> -->
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in records" :key="row.id">
                                <!-- <td>{{ index + 1 }}</td> -->
                                <td>{{ row.name }}</td>
                                <td>{{ row.selection_type == 'single' ? 'Única selección' : 'Múltiple selección' }}</td>
                                <td>
                                    <div class="products-tags">
                                        <el-tag
                                            v-for="(item, idx) in row.items"
                                            :key="idx"
                                            size="small"
                                            type="info"
                                            class="me-1 mb-1"
                                        >
                                            {{ item.name || `Item #${item.item_id}` }}
                                            <el-tag
                                                size="mini"
                                                :type="item.price > 0 ? 'success' : ''"
                                                class="ms-1"
                                                style="margin-left: 6px;"
                                            >
                                                {{ formatPrice(item.price) }}
                                            </el-tag>
                                        </el-tag>
                                        <span v-if="!row.items || row.items.length === 0" class="text-muted">Sin productos</span>
                                    </div>
                                </td>
                                <!-- <td class="text-center">
                                    <el-tag v-if="row.active" type="success" size="small">Sí</el-tag>
                                    <el-tag v-else type="danger" size="small">No</el-tag>
                                </td> -->
                                <td class="text-end">
                                    <button class="btn btn-xs btn-info me-2" @click.prevent="clickEdit(row)">Editar</button>
                                    <button class="btn btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                                </td>
                            </tr>
                            <tr v-if="records.length === 0">
                                <td colspan="6" class="text-center text-muted">
                                    <empty-state></empty-state>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <modifier-group-form :show-dialog.sync="showDialog" ref="form" @save="getRecords"></modifier-group-form>
    </div>
</template>

<script>
import ModifierGroupForm from './form.vue'
import EmptyState from '@/components/EmptyState.vue'

export default {
    components: { ModifierGroupForm, EmptyState },
    data() {
        return {
            records: [],
            showDialog: false
        }
    },
    async created() {
        await this.getRecords()
    },
    methods: {
        async getRecords() {
            try {
                const res = await this.$http.get('/restaurant/modifier-groups/records')
                if (res.data && res.data.data) this.records = res.data.data
            } catch (e) {
                console.error(e)
                this.$message.error('Error al cargar registros')
            }
        },
        openCreate() {
            this.showDialog = true
            this.$nextTick(() => {
                this.$refs.form.setFormData({})
            })
        },
        clickEdit(row) {
            this.showDialog = true
            this.$nextTick(() => {
                this.$refs.form.setFormData(row)
            })
        },
        async clickDelete(id) {
            try {
                await this.$confirm('¿Seguro de eliminar?', 'Confirmar', { confirmButtonText: 'Sí', cancelButtonText: 'Cancelar', type: 'warning' })
                const res = await this.$http.delete(`/restaurant/modifier-groups/${id}`)
                if (res.data && res.data.success) {
                    this.$message.success(res.data.message)
                    await this.getRecords()
                }
            } catch (e) {
                if (e !== 'cancel') {
                    console.error(e)
                    this.$message.error('Error al eliminar')
                }
            }
        },
        formatPrice(price) {
            const p = Number(price) || 0
            return p === 0 ? 'Gratis' : `S/ ${p.toFixed(2)}`
        }
    }
}
</script>

<style scoped>
.page-header { display:flex; align-items:center; justify-content:space-between; }
.right-wrapper { margin-left:auto }
.products-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
</style>
