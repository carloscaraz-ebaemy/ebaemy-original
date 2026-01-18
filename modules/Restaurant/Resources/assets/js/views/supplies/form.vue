<template>
    <el-dialog
        :title="form.id ? 'Editar Insumo' : 'Nuevo Insumo'"
        :visible.sync="showDialog"
        width="60%"
        :close-on-click-modal="false"
        @close="close"
    >
        <el-form :model="form" label-width="140px" size="small">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Nombre</label>
                        <el-input
                            v-model="form.name"
                            placeholder="Nombre del insumo"
                        ></el-input>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Unidad</label>
                        <el-select
                            v-model="form.unit_type_id"
                            placeholder="Seleccionar"
                            filterable
                            style="width: 100%"
                        >
                            <el-option
                                v-for="unit in unitTypes"
                                :key="unit.id"
                                :label="unit.description"
                                :value="unit.id"
                            ></el-option>
                        </el-select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Costo</label>
                        <el-input
                            v-model="form.cost"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        >
                            <template slot="prepend">S/</template>
                        </el-input>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Merma %</label>
                        <el-input
                            v-model="form.waste_percentage"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        >
                            <template slot="append">%</template>
                        </el-input>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Stock</label>
                        <el-input
                            v-model="form.stock"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        ></el-input>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Stock Mínimo</label>
                        <el-input
                            v-model="form.minimum_stock"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                        ></el-input>
                    </div>
                </div>
            </div>
        </el-form>

        <span slot="footer" class="dialog-footer">
            <el-button @click="close">Cancelar</el-button>
            <el-button
                type="primary"
                @click="submit"
                :loading="loading"
            >
                {{ form.id ? 'Actualizar' : 'Guardar' }}
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    props: {
        showDialog: {
            type: Boolean,
            default: false
        },
        recordId: {
            type: Number,
            default: null
        },
        unitTypes: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            loading: false,
            resource: "restaurant/supplies",
            form: {}
        };
    },
    watch: {
        showDialog(value) {
            if (value) {
                this.initForm();
                if (this.recordId) {
                    this.loadRecord();
                }
            }
        }
    },
    methods: {
        /**
         * Inicializa el formulario vacío
         */
        initForm() {
            this.form = {
                id: null,
                name: null,
                cost: 0,
                unit_type_id: null,
                waste_percentage: 0,
                stock: 0,
                minimum_stock: 0
            };
        },

        /**
         * Carga los datos de un registro para editar
         */
        loadRecord() {
            // Los datos se pasan desde el componente padre
            this.form = {
                id: this.recordId,
                ...this.form
            };
        },

        /**
         * Guarda o actualiza un insumo
         */
        async submit() {
            // Validaciones
            if (!this.form.name || this.form.name.trim() === "") {
                this.$message.error("El nombre es requerido");
                return;
            }

            if (!this.form.unit_type_id) {
                this.$message.error("La unidad es requerida");
                return;
            }

            if (this.form.cost < 0) {
                this.$message.error("El costo no puede ser negativo");
                return;
            }

            this.loading = true;

            try {
                const response = await this.$http.post(`/${this.resource}`, this.form);

                if (response.data.success) {
                    this.$message.success(response.data.message);
                    this.$emit('save');
                    this.close();
                } else {
                    this.$message.error("Error al guardar");
                }
            } catch (error) {
                console.error("Error al guardar:", error);
                if (error.response && error.response.data && error.response.data.errors) {
                    const errors = error.response.data.errors;
                    const firstError = Object.values(errors)[0][0];
                    this.$message.error(firstError);
                } else {
                    this.$message.error("Error al guardar el insumo");
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * Cierra el dialog
         */
        close() {
            this.$emit('update:showDialog', false);
            this.initForm();
        },

        /**
         * Establece los datos del formulario (usado desde el padre)
         */
        setFormData(data) {
            this.form = { ...data };
        }
    }
};
</script>
