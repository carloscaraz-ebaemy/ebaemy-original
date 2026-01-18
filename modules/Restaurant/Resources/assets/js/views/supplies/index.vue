<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/restaurant/supplies">
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
                    <span>Gestión de Insumos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <template>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm mt-2 me-2"
                        @click.prevent="openDialog()"
                    >
                        <i class="fa fa-plus-circle"></i> Nuevo
                    </button>
                </template>
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <!-- Tabla de insumos -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <!-- <th>#</th> -->
                                <th>Nombre</th>
                                <th class="text-end">Costo</th>
                                <th class="text-center">Unidad</th>
                                <th class="text-end">Merma %</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Stock Mínimo</th>
                                <th class="text-center">Estado Stock</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in records"
                                :key="row.id"
                                :class="{ 'disable_color': row.stock < row.minimum_stock }"
                            >
                                <!-- <td>{{ index + 1 }}</td> -->
                                <td>{{ row.name }}</td>
                                <td class="text-end">S/ {{ formatNumber(row.cost) }}</td>
                                <td class="text-center">{{ row.unit_type ? row.unit_type.description : '-' }}</td>
                                <td class="text-end">{{ formatNumber(row.waste_percentage) }}%</td>
                                <td class="text-end">{{ formatNumber(row.stock) }}</td>
                                <td class="text-end">{{ formatNumber(row.minimum_stock) }}</td>
                                <td class="text-center">
                                    <el-tag
                                        v-if="row.stock < row.minimum_stock"
                                        type="danger"
                                        size="small"
                                    >
                                        Stock Bajo
                                    </el-tag>
                                    <el-tag
                                        v-else
                                        type="success"
                                        size="small"
                                    >
                                        Normal
                                    </el-tag>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn waves-effect waves-light btn-xs btn-info me-2"
                                        @click.prevent="clickEdit(row)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="btn waves-effect waves-light btn-xs btn-danger"
                                        @click.prevent="clickDelete(row.id)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="records.length === 0">
                                <td colspan="9" class="text-center text-muted">
                                    <empty-state/>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Componente de formulario -->
        <supply-form
            :show-dialog.sync="showDialog"
            :unit-types="unitTypes"
            ref="supplyForm"
            @save="handleSave"
        ></supply-form>
    </div>
</template>

<script>
import { deletable } from "@mixins/deletable";
import SupplyForm from './form.vue';
import EmptyState from '@/components/EmptyState.vue';

export default {
    mixins: [deletable],
    components: {
        SupplyForm,
        EmptyState
    },
    data() {
        return {
            loading: false,
            resource: "restaurant/supplies",
            records: [],
            unitTypes: [],
            showDialog: false
        };
    },
    async created() {
        await this.getUnitTypes();
        await this.getRecords();
    },
    methods: {
        /**
         * Abre el dialog para crear un nuevo insumo
         */
        openDialog() {
            this.showDialog = true;
        },

        /**
         * Maneja el evento de guardado desde el formulario
         */
        async handleSave() {
            await this.getRecords();
        },

        /**
         * Obtiene los tipos de unidad disponibles
         */
        async getUnitTypes() {
            try {
                const response = await this.$http.get(`/${this.resource}/unit-types`);
                this.unitTypes = response.data.unitTypes;
            } catch (error) {
                console.error("Error al cargar tipos de unidad:", error);
                this.$message.error("Error al cargar tipos de unidad");
            }
        },

        /**
         * Obtiene todos los registros de insumos
         */
        async getRecords() {
            try {
                const response = await this.$http.get(`/${this.resource}/records`);
                this.records = response.data.records;
            } catch (error) {
                console.error("Error al cargar insumos:", error);
                this.$message.error("Error al cargar insumos");
            }
        },

        /**
         * Prepara el formulario para editar un registro
         */
        clickEdit(row) {
            const formData = {
                id: row.id,
                name: row.name,
                cost: row.cost,
                unit_type_id: row.unit_type_id,
                waste_percentage: row.waste_percentage,
                stock: row.stock,
                minimum_stock: row.minimum_stock
            };

            this.showDialog = true;
            this.$nextTick(() => {
                this.$refs.supplyForm.setFormData(formData);
            });
        },

        /**
         * Formatea números para mostrar
         */
        formatNumber(value) {
            return parseFloat(value || 0).toFixed(2);
        }
    }
};
</script>

<style scoped>
.table-warning {
    background-color: #fff3cd !important;
}
</style>
