<template>
    <div class="modifiers-tab">
        <div class="row mb-3">
            <div class="col-md-8 form-group">
                <label class="control-label">Grupos de Modificadores</label>
                <el-select
                    v-model="selectedGroupIds"
                    placeholder="Seleccione uno o varios grupos"
                    filterable
                    multiple
                    style="width: 100%">
                    <el-option
                        v-for="group in availableGroups"
                        :key="group.id"
                        :label="group.name"
                        :value="group.id">
                    </el-option>
                </el-select>
            </div>
            <div class="col-md-4">
                <label class="control-label">&nbsp;</label>
                <el-button
                    type="primary"
                    @click="assignGroups"
                    style="width: 100%"
                    :disabled="!selectedGroupIds || selectedGroupIds.length === 0 || saving">
                    {{ saving ? 'Guardando...' : 'Agregar Grupos' }}
                </el-button>
            </div>
        </div>

        <el-table
            :data="itemGroups"
            border
            style="width: 100%"
            :empty-text="'No hay grupos asignados'">
            <el-table-column
                label="Grupo"
                prop="name"
                width="200">
            </el-table-column>
            <el-table-column
                label="Selección"
                width="140">
                <template slot-scope="scope">
                    <span>{{ scope.row.selection_type }}</span>
                </template>
            </el-table-column>
            <el-table-column
                label="Productos"
                min-width="300">
                <template slot-scope="scope">
                    <div class="">
                        <el-tag
                            v-for="(item, idx) in scope.row.items"
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
                        <span v-if="!scope.row.items || scope.row.items.length === 0" class="text-muted">Sin productos</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column
                align="center">
                <template slot-scope="scope">
                    <el-button class="btn btn-mini" type="danger" size="mini" icon="el-icon-delete" @click="removeGroup(scope.row.pivot.modifier_group_id)"></el-button>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script>
export default {
    name: 'ModifiersTab',
    props: {
        itemId: {
            type: Number,
            default: null
        }
    },
    data() {
        return {
            availableGroups: [],
            itemGroups: [],
            selectedGroupIds: [],
            saving: false
        }
    },
    mounted() {
        this.loadAvailableGroups()
        if (this.itemId) this.loadItemGroups()
    },
    watch: {
        itemId(newVal) {
            if (newVal) this.loadItemGroups()
            else this.itemGroups = []
        }
    },
    methods: {
        async loadAvailableGroups() {
            try {
                const res = await this.$http.get('/restaurant/modifier-groups/records')
                if (res.data && res.data.data) this.availableGroups = res.data.data
            } catch (e) {
                this.$message.error('Error al cargar grupos de modificadores')
                console.error(e)
            }
        },
        async loadItemGroups() {
            if (!this.itemId) return
            try {
                const res = await this.$http.get(`/restaurant/items/${this.itemId}/modifier-groups`)
                if (res.data && res.data.data) {
                    this.itemGroups = res.data.data
                }
            } catch (e) {
                this.$message.error('Error al cargar grupos asignados')
                console.error(e)
            }
        },
        async assignGroups() {
            if (!this.itemId) {
                this.$message.warning('Debe guardar el item antes de asignar grupos')
                return
            }
            this.saving = true
            try {
                // Combinar grupos existentes con los nuevos seleccionados
                const existingIds = this.itemGroups.map(g => g.id)
                const newIds = this.selectedGroupIds.filter(id => !existingIds.includes(id))

                if (newIds.length === 0) {
                    this.$message.warning('Los grupos seleccionados ya están asignados')
                    this.saving = false
                    return
                }

                const allIds = [...existingIds, ...newIds]

                const payload = {
                    group_ids: allIds
                }
                const res = await this.$http.post(`/restaurant/items/${this.itemId}/modifier-groups`, payload)
                if (res.data && res.data.data) {
                    this.$message.success('Grupos agregados correctamente')
                    await this.loadItemGroups()
                    this.selectedGroupIds = []
                } else {
                    this.$message.error('No se pudo asignar los grupos')
                }
            } catch (e) {
                this.$message.error('Error al asignar grupos')
                console.error(e)
            } finally {
                this.saving = false
            }
        },
        async removeGroup(groupId) {
            if (!this.itemId) return
            // Sync without this group
            const remaining = this.itemGroups.map(g => g.id).filter(id => id !== groupId)
            try {
                const res = await this.$http.post(`/restaurant/items/${this.itemId}/modifier-groups`, { group_ids: remaining })
                if (res.data && res.data.data) {
                    this.$message.success('Grupo removido')
                    await this.loadItemGroups()
                } else {
                    this.$message.error('No se pudo remover el grupo')
                }
            } catch (e) {
                this.$message.error('Error al remover grupo')
                console.error(e)
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
.modifiers-tab {
    padding: 12px 0;
}

.products-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
</style>
