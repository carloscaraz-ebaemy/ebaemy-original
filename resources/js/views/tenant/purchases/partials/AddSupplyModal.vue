<template>
    <el-dialog
        :visible.sync="showDialog"
        title="Añadir Insumo"
        :close-on-click-modal="false"
        :close-on-press-escape="false"
        @close="close"
    >
        <form autocomplete="off" @submit.prevent="addSupply">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">Insumo</label>
                            <el-select
                                v-model="supplyForm.supply_id"
                                filterable
                                remote
                                :remote-method="searchSupplies"
                                :loading="loading_search"
                                placeholder="Buscar insumo..."
                                style="width: 100%"
                                @change="handleSupplyChange"
                            >
                                <el-tooltip
                                    v-for="supply in filtered_supplies"
                                    :key="supply.id"
                                    placement="left"
                                >
                                    <div slot="content">
                                        <strong>{{ supply.name }}</strong><br>
                                        Unidad: {{ supply.unit_type }}<br>
                                        Merma: {{ supply.waste_percentage }}%<br>
                                        Stock actual: {{ supply.stock || 0 }}
                                    </div>
                                    <el-option
                                        :label="`${supply.name} (${supply.unit_type})`"
                                        :value="supply.id"
                                    >
                                        <span style="float: left">{{ supply.name }}</span>
                                        <span style="float: right; color: #8492a6; font-size: 13px">
                                            {{ supply.unit_type }} - Merma: {{ supply.waste_percentage }}%
                                        </span>
                                    </el-option>
                                </el-tooltip>
                            </el-select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Afectación IGV</label>
                            <el-select
                                v-model="supplyForm.affectation_igv_type_id"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="option in affectation_igv_types"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Cantidad</label>
                            <el-input-number
                                v-model="supplyForm.quantity"
                                :min="0.01"
                                :step="0.01"
                                style="width: 100%"
                            ></el-input-number>
                            <small v-if="selectedSupply" class="text-muted d-block mt-1">
                                Unidad: {{ selectedSupply.unit_type }}
                            </small>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Costo Unitario</label>
                            <el-input
                                v-model="supplyForm.cost"
                                type="number"
                                step="0.01"
                            ></el-input>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">IGV</label>
                            <el-input
                                :value="totalIgv"
                                readonly
                            ></el-input>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">Total</label>
                            <el-input
                                :value="totalLine"
                                readonly
                            ></el-input>
                        </div>
                    </div>
                </div>

                <div class="row" v-if="selectedSupply">
                    <div class="col-md-12">
                        <el-alert
                            :title="`Merma del ${selectedSupply.waste_percentage}%: Se añadirán ${effectiveQuantity} ${selectedSupply.unit_type} al stock`"
                            type="info"
                            :closable="false"
                            show-icon
                            class="mt-2"
                        />
                    </div>
                </div>
            </div>

            <div class="form-actions text-end mt-3">
                <el-button @click="close">Cancelar</el-button>
                <el-button
                    type="primary"
                    native-type="submit"
                    :disabled="!canAdd"
                >
                    Agregar
                </el-button>
            </div>
        </form>
    </el-dialog>
</template><script>
export default {
    props: {
        showDialog: {
            type: Boolean,
            default: false
        },
        affectation_igv_types: {
            type: Array,
            default: () => []
        },
        percentage_igv: {
            type: Number,
            default: 18
        }
    },
    data() {
        return {
            loading_search: false,
            filtered_supplies: [],
            supplyForm: {
                supply_id: null,
                quantity: 1,
                cost: 0,
                affectation_igv_type_id: '10' // Gravado por defecto
            }
        };
    },
    watch: {
        showDialog(val) {
            if (val) {
                // Limpiar filtered_supplies al abrir
                this.filtered_supplies = [];
            }
        },
        'supplyForm.quantity'() {
            // Auto-calcular cuando cambie la cantidad
        },
        'supplyForm.cost'() {
            // Auto-calcular cuando cambie el costo
        },
        'supplyForm.affectation_igv_type_id'() {
            // Auto-calcular cuando cambie el tipo de afectación
        }
    },
    computed: {
        selectedSupply() {
            if (!this.supplyForm.supply_id) return null;
            return this.filtered_supplies.find(s => s.id === this.supplyForm.supply_id);
        },
        unitValue() {
            const cost = parseFloat(this.supplyForm.cost) || 0;
            const percentage_igv = this.percentage_igv;

            if (this.supplyForm.affectation_igv_type_id === '10') {
                // percentage_igv viene como decimal (0.18 = 18%)
                return (cost / (1 + percentage_igv)).toFixed(2);
            }
            return cost.toFixed(2);
        },
        totalIgv() {
            const quantity = parseFloat(this.supplyForm.quantity) || 0;
            const unit_value = parseFloat(this.unitValue);
            const percentage_igv = this.percentage_igv;

            if (this.supplyForm.affectation_igv_type_id === '10') {
                // percentage_igv viene como decimal (0.18 = 18%)
                return (unit_value * quantity * percentage_igv).toFixed(2);
            }
            return '0.00';
        },
        totalLine() {
            const quantity = parseFloat(this.supplyForm.quantity) || 0;
            const cost = parseFloat(this.supplyForm.cost) || 0;
            return (quantity * cost).toFixed(2);
        },
        effectiveQuantity() {
            if (!this.selectedSupply) return '0.00';
            const quantity = parseFloat(this.supplyForm.quantity) || 0;
            const wastePercentage = this.selectedSupply.waste_percentage || 0;
            const effective = quantity * (1 - (wastePercentage / 100));
            return effective.toFixed(2);
        },
        canAdd() {
            return this.supplyForm.supply_id &&
                   parseFloat(this.supplyForm.quantity) > 0 &&
                   parseFloat(this.supplyForm.cost) >= 0;
        }
    },
    methods: {
        searchSupplies(query) {
            if (query !== '') {
                this.loading_search = true;
                this.$http.get(`/restaurant/item-supplies/available/list?search=${query}`)
                    .then(response => {
                        this.filtered_supplies = response.data.data || response.data;
                        this.loading_search = false;
                    })
                    .catch(error => {
                        console.error('Error al buscar insumos:', error);
                        this.filtered_supplies = [];
                        this.loading_search = false;
                    });
            } else {
                this.filtered_supplies = [];
            }
        },
        handleSupplyChange() {
            if (this.selectedSupply) {
                // Asignar el costo del insumo
                this.supplyForm.cost = this.selectedSupply.cost || 0;
            }
        },
        addSupply() {
            if (!this.canAdd) {
                this.$message.warning('Complete todos los campos requeridos');
                return;
            }

            const affectation_igv_type = this.affectation_igv_types.find(
                t => t.id === this.supplyForm.affectation_igv_type_id
            );

            const quantity = parseFloat(this.supplyForm.quantity);
            const cost = parseFloat(this.supplyForm.cost);
            const percentage_igv = this.percentage_igv;

            // Calcular impuestos para el registro contable
            let unit_value = cost;
            let total_igv = 0;

            if (this.supplyForm.affectation_igv_type_id === '10') {
                // percentage_igv viene como decimal (0.18 = 18%)
                unit_value = cost / (1 + percentage_igv);
                total_igv = (unit_value * quantity) * percentage_igv;
            }

            const total_value = unit_value * quantity;
            const total = total_value + total_igv;

            // Preparar estructura supply con unit_type como objeto
            const supplyWithStructure = {
                ...this.selectedSupply,
                unit_type: {
                    id: this.selectedSupply.unit_type_id,
                    description: this.selectedSupply.unit_type
                }
            };

            const supplyData = {
                supply_id: this.supplyForm.supply_id,
                supply: supplyWithStructure,
                quantity: quantity,
                cost: cost,
                affectation_igv_type_id: this.supplyForm.affectation_igv_type_id,
                affectation_igv_type: affectation_igv_type,
                // Campos calculados para el registro contable
                unit_value: _.round(unit_value, 6),
                total_value: _.round(total_value, 2),
                total_base_igv: _.round(total_value, 2),
                total_igv: _.round(total_igv, 2),
                total: _.round(total, 2),
                effective_quantity: parseFloat(this.effectiveQuantity),
                // Campos en 0 para compatibilidad con calculateTotal
                total_discount: 0,
                total_charge: 0,
                total_isc: 0,
                total_base_isc: 0,
                unit_price: cost, // Alias para compatibilidad
                warehouse_description: '', // Campo requerido para tabla
                lot_code: '', // Campo requerido para tabla
                item: { unit_type_id: this.selectedSupply.unit_type_id }, // Para mostrar unidad
                is_supply: true
            };

            this.$emit('supply-added', supplyData);
            this.close();
        },
        close() {
            this.$emit('update:showDialog', false);
            this.resetForm();
        },
        resetForm() {
            this.supplyForm = {
                supply_id: null,
                quantity: 1,
                cost: 0,
                affectation_igv_type_id: '10'
            };
        }
    }
};
</script>
