<template>
    <el-dialog :title="titleDialog" width="55%" :visible="showDialog" @open="create" @close="close"
        :close-on-click-modal="false" :close-on-press-escape="false" append-to-body>

        <div class="form-body">
            <div class="row">
                <div class="col-lg-12 col-md-12 table-responsive">
                    <div class="col-lg-5 col-md-5 col-sm-12 pb-2">
                        <el-input placeholder="Buscar lote ..."
                            v-model="search"
                            style="width: 100%;"
                            prefix-icon="el-icon-search"
                            @input="filterLots">
                        </el-input>
                    </div>
                    <table width="100%" class="table">
                        <thead>
                            <tr width="100%">
                                <th class="text-center">Seleccionar</th>
                                <th>Código</th>
                                <th>Lote</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in filtered_lots" :key="index" width="100%">
                                <td class="text-center">
                                    <el-checkbox v-model="row.checked" :disabled="isUpdateItem"
                                        @change="changeLotChecked(row)"></el-checkbox>
                                </td>
                                <td>{{ row.code }}</td>
                                <td>{{ row.lot_code }}</td>
                                <td class="text-center">{{ row.quantity }}</td>
                                <td class="text-center">
                                    <el-input-number v-model="row.compromise_quantity"
                                        :min="0"
                                        :max="row.quantity"
                                        :disabled="!row.checked || isUpdateItem"
                                        controls-position="right"
                                        size="mini"
                                        @change="changeCompromiseQuantity(row)">
                                    </el-input-number>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="text-end" v-if="getSelectedLots.length > 0">
                        <strong>Total seleccionado: {{ totalCompromiseQuantity }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions text-end pt-2">
            <el-button @click.prevent="close()">Cancelar</el-button>
            <el-button type="primary" @click="submit" :disabled="isUpdateItem">Aceptar</el-button>
        </div>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'lots_group', 'quantity', 'isUpdateItem', 'oldSelectedLotsGroup'],
        data() {
            return {
                titleDialog: 'Seleccionar Lotes',
                loading: false,
                errors: {},
                search: '',
                all_lots: [],
                filtered_lots: [],
            }
        },
        computed: {
            getSelectedLots() {
                return this.filtered_lots.filter(lot => lot.checked)
            },
            totalCompromiseQuantity() {
                return _.round(this.getSelectedLots.reduce((sum, lot) => {
                    return sum + (parseFloat(lot.compromise_quantity) || 0)
                }, 0), 4)
            }
        },
        watch: {
            lots_group(val) {
                if (val) {
                    this.all_lots = val.map(lot => {
                        return {
                            ...lot,
                            checked: lot.checked || false,
                            compromise_quantity: lot.compromise_quantity || 0
                        }
                    })
                    this.setOldSelectedLots()
                    this.filtered_lots = this.all_lots
                }
            }
        },
        methods: {
            create() {
                if (this.lots_group) {
                    this.all_lots = this.lots_group.map(lot => {
                        return {
                            ...lot,
                            checked: lot.checked || false,
                            compromise_quantity: lot.compromise_quantity || 0
                        }
                    })
                    this.setOldSelectedLots()
                    this.filtered_lots = this.all_lots
                }
            },
            setOldSelectedLots() {
                if (this.oldSelectedLotsGroup && this.oldSelectedLotsGroup.length > 0) {
                    this.oldSelectedLotsGroup.forEach(oldLot => {
                        let lot = _.find(this.all_lots, { id: oldLot.id })
                        if (lot) {
                            lot.checked = true
                            lot.compromise_quantity = oldLot.compromise_quantity || 0
                        }
                    })
                }
            },
            filterLots() {
                if (this.search) {
                    this.filtered_lots = this.all_lots.filter(x => {
                        let code = x.code ? x.code.toUpperCase() : ''
                        let lot_code = x.lot_code ? x.lot_code.toUpperCase() : ''
                        let term = this.search.toUpperCase()
                        return code.includes(term) || lot_code.includes(term)
                    })
                } else {
                    this.filtered_lots = this.all_lots
                }
            },
            changeLotChecked(row) {
                if (!row.checked) {
                    row.compromise_quantity = 0
                }
            },
            changeCompromiseQuantity(row) {
                if (row.compromise_quantity > 0 && !row.checked) {
                    row.checked = true
                }
            },
            validateLots() {
                let total = this.totalCompromiseQuantity

                if (this.getSelectedLots.length === 0) {
                    return {
                        success: false,
                        message: 'Debe seleccionar al menos un lote'
                    }
                }

                if (total <= 0) {
                    return {
                        success: false,
                        message: 'La cantidad comprometida debe ser mayor a 0'
                    }
                }

                return { success: true }
            },
            submit() {
                let validate = this.validateLots()
                if (!validate.success) {
                    return this.$message.error(validate.message)
                }

                let selected = this.getSelectedLots.map(lot => {
                    return {
                        id: lot.id,
                        code: lot.code,
                        lot_code: lot.lot_code,
                        quantity: lot.quantity,
                        compromise_quantity: lot.compromise_quantity,
                        checked: lot.checked
                    }
                })

                this.$emit('addRowLotGroup', selected)
                this.$emit('update:showDialog', false)
            },
            close() {
                this.$emit('update:showDialog', false)
            }
        }
    }
</script>
