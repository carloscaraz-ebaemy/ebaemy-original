<template>
    <div class="supplies-tab">
        <!-- Selector de insumos -->
        <div class="row mb-3">
            <div class="col-md-6 form-group">
                <label class="control-label">Seleccionar Insumo</label>
                <el-select
                    v-model="selectedSupplyId"
                    placeholder="Seleccione un insumo"
                    filterable
                    style="width: 100%">
                    <el-option
                        v-for="supply in availableSupplies"
                        :key="supply.id"
                        :label="`${supply.name} (${supply.unit_type})`"
                        :value="supply.id">
                    </el-option>
                </el-select>
            </div>
            <div class="col-md-3 form-group">
                <label class="control-label">Cantidad</label>
                <el-input
                    v-model.number="selectedQuantity"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    placeholder="0.0000">
                </el-input>
            </div>
            <div class="col-md-3">
                <label class="control-label">&nbsp;</label>
                <el-button
                    type="primary"
                    @click="addSupply"
                    style="width: 100%"
                    :disabled="!selectedSupplyId || !selectedQuantity || selectedQuantity <= 0">
                    Agregar
                </el-button>
            </div>
        </div>

        <!-- Tabla de insumos agregados -->
        <el-table
            :data="itemSupplies"
            border
            style="width: 100%"
            :empty-text="'No hay insumos agregados'">
            <el-table-column
                label="Insumo"
                prop="supply_name">
            </el-table-column>
            <el-table-column
                label="Unidad"
                prop="unit_type"
                width="120">
            </el-table-column>
            <el-table-column
                label="Cantidad"
                prop="quantity"
                width="120"
                align="right">
                <template slot-scope="scope">
                    {{ parseFloat(scope.row.quantity).toFixed(4) }}
                </template>
            </el-table-column>
            <el-table-column
                label="Costo Unitario"
                width="140"
                align="right">
                <template slot-scope="scope">
                    S/ {{ calculateEffectiveCost(scope.row.cost, scope.row.waste_percentage).toFixed(2) }}
                </template>
            </el-table-column>
            <el-table-column
                label="Total"
                width="140"
                align="right">
                <template slot-scope="scope">
                    S/ {{ calculateLineCost(scope.row).toFixed(2) }}
                </template>
            </el-table-column>
            <el-table-column
                label="Acciones"
                width="100"
                align="center">
                <template slot-scope="scope">
                    <el-button
                        type="danger"
                        size="mini"
                        icon="el-icon-delete"
                        @click="removeSupply(scope.$index)">
                    </el-button>
                </template>
            </el-table-column>
        </el-table>

        <!-- Resumen de costos -->
        <div class="row mt-3" v-if="itemSupplies.length > 0">
            <div class="col-md-12 text-end mb-2">
                <el-button
                    type="success"
                    @click="saveSupplies"
                    :loading="saving">
                    {{ saving ? 'Guardando...' : 'Guardar Insumos' }}
                </el-button>
            </div>
            <div class="col-md-12">
                <el-card shadow="hover">
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <h4>
                                <strong>Costo de Producción Total: </strong>
                                <span class="text-primary">{{ productionCost.toFixed(2) }}</span>
                            </h4>
                        </div>
                        <div class="col-md-12 text-end">
                            <h4>
                                <strong>Precio Sugerido (Costo + 20%): </strong>
                                <span class="text-success">{{ suggestedPrice.toFixed(2) }}</span>
                            </h4>
                        </div>
                    </div>
                </el-card>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SuppliesTab',
    props: {
        itemId: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            availableSupplies: [],
            itemSupplies: [],
            selectedSupplyId: null,
            selectedQuantity: null,
            saving: false
        }
    },
    computed: {
        productionCost() {
            return this.itemSupplies.reduce((sum, item) => {
                return sum + this.calculateLineCost(item)
            }, 0)
        },
        suggestedPrice() {
            return this.productionCost * 1.20
        }
    },
    mounted() {
        this.loadAvailableSupplies()
        if (this.itemId) {
            this.loadItemSupplies()
        }
    },
    watch: {
        itemId(newVal) {
            if (newVal) {
                this.loadItemSupplies()
            } else {
                this.itemSupplies = []
            }
        }
    },
    methods: {
        /**
         * Calcula el costo efectivo de un insumo (costo + merma)
         */
        calculateEffectiveCost(cost, wastePercentage) {
            const wasteFactor = 1 + (parseFloat(wastePercentage) / 100)
            return parseFloat(cost) * wasteFactor
        },
        /**
         * Calcula el costo total de una línea (costo efectivo × cantidad)
         */
        calculateLineCost(item) {
            const effectiveCost = this.calculateEffectiveCost(item.cost, item.waste_percentage)
            return effectiveCost * parseFloat(item.quantity)
        },
        async loadAvailableSupplies() {
            try {
                const response = await this.$http.get('/restaurant/item-supplies/available/list')
                if (response.data.success) {
                    this.availableSupplies = response.data.data
                }
            } catch (error) {
                this.$message.error('Error al cargar insumos disponibles')
                console.error(error)
            }
        },
        async loadItemSupplies() {
            if (!this.itemId) return

            try {
                const response = await this.$http.get(`/restaurant/item-supplies/${this.itemId}`)
                if (response.data.success) {
                    this.itemSupplies = response.data.data
                }
            } catch (error) {
                this.$message.error('Error al cargar insumos del item')
                console.error(error)
            }
        },
        addSupply() {
            if (!this.selectedSupplyId || !this.selectedQuantity || this.selectedQuantity <= 0) {
                this.$message.warning('Seleccione un insumo y una cantidad válida')
                return
            }

            // Verificar si el insumo ya fue agregado
            const exists = this.itemSupplies.find(item => item.supply_id === this.selectedSupplyId)
            if (exists) {
                this.$message.warning('Este insumo ya fue agregado')
                return
            }

            // Buscar el insumo seleccionado
            const supply = this.availableSupplies.find(s => s.id === this.selectedSupplyId)
            if (!supply) return

            // Agregar a la lista
            this.itemSupplies.push({
                supply_id: supply.id,
                supply_name: supply.name,
                unit_type: supply.unit_type,
                quantity: this.selectedQuantity,
                cost: supply.cost,
                waste_percentage: supply.waste_percentage
            })

            // Limpiar selección
            this.selectedSupplyId = null
            this.selectedQuantity = null
        },
        removeSupply(index) {
            this.itemSupplies.splice(index, 1)
        },
        async saveSupplies() {
            if (!this.itemId) {
                this.$message.warning('Debe guardar el item antes de agregar insumos')
                return
            }

            if (this.itemSupplies.length === 0) {
                this.$message.warning('No hay insumos para guardar')
                return
            }

            this.saving = true
            try {
                const supplies = this.itemSupplies.map(item => ({
                    supply_id: item.supply_id,
                    quantity: item.quantity
                }))

                const response = await this.$http.post('/restaurant/item-supplies', {
                    item_id: this.itemId,
                    supplies: supplies
                })

                if (response.data.success) {
                    this.$message.success(response.data.message)
                    await this.loadItemSupplies()
                } else {
                    this.$message.error(response.data.message)
                }
            } catch (error) {
                if (error.response && error.response.data && error.response.data.message) {
                    this.$message.error(error.response.data.message)
                } else {
                    this.$message.error('Error al guardar insumos')
                }
                console.error(error)
            } finally {
                this.saving = false
            }
        }
    }
}
</script>

<style scoped>
.supplies-tab {
    padding: 20px 0;
}

.el-table {
    margin-top: 20px;
}

h4 {
    margin: 10px 0;
}

.text-primary {
    color: #409EFF;
}

.text-success {
    color: #67C23A;
}
</style>
