<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/categories">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-category-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 4h6v6h-6z" /><path d="M4 14h6v6h-6z" /><path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M7 7m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                    <button type="button" class="btn btn-custom btn-sm  mt-2 me-2" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>

            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de {{ title }}</h3>
            </div> -->
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>ID</th>
                        <th class="text-center">Imagen</th>
                        <th>Nombre</th>
                        <th>Grupo</th>
                        <th class="text-center">Productos</th>
                        <th class="text-center">En tienda</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.id }}</td>
                        <td class="text-center">
                            <img v-if="row.image" :src="row.image_url" alt width="32" height="32" style="object-fit: contain;" />
                        </td>
                        <td>{{ row.name }}</td>
                        <td>
                            <span v-if="row.parent_name" class="badge bg-light text-dark">{{ row.parent_name }}</span>
                            <span v-else class="badge bg-primary">Grupo principal</span>
                        </td>
                        <td class="text-center">{{ row.items_count }}</td>
                        <td class="text-center">
                            <span v-if="row.visible_ecommerce" class="text-success">Sí</span>
                            <span v-else class="text-muted">No</span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info me-1" @click.prevent="clickCreate(row.id)">Editar</button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-secondary me-1" @click.prevent="clickMerge(row)">Fusionar</button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger me-1" @click.prevent="clickDelete(row.id)">Eliminar</button>
                        </td>
                    </tr>
                </data-table>
            </div>

            <category-form 
                :showDialog.sync="showDialog"
                :recordId="recordId"
                    ></category-form> 

            <!-- Fusion: los productos de la categoria se mudan a la elegida y
                 la original desaparece. Util con los cientos de categorias de
                 un solo producto que deja la importacion. -->
            <el-dialog title="Fusionar categoría" :visible.sync="showMerge" width="460px">
                <p v-if="mergeFrom" class="mb-3">
                    Los <strong>{{ mergeFrom.items_count }}</strong> producto(s) de
                    <strong>{{ mergeFrom.name }}</strong> pasarán a la categoría que elijas,
                    y <strong>{{ mergeFrom.name }}</strong> se eliminará.
                </p>
                <el-select v-model="mergeTo" filterable clearable class="w-100"
                           placeholder="Elige la categoría destino">
                    <el-option v-for="c in mergeTargets" :key="c.id" :label="c.label" :value="c.id"></el-option>
                </el-select>
                <span slot="footer">
                    <el-button @click="showMerge = false">Cancelar</el-button>
                    <el-button type="primary" :loading="merging" :disabled="!mergeTo" @click="doMerge">
                        Fusionar
                    </el-button>
                </span>
            </el-dialog>
        </div>
    </div>
</template>
<style>
@media only screen and (max-width: 485px){
    .filter-container{
      margin-top: 0px;
      & .btn-filter-content, .btn-container-mobile{
        display: flex;
        align-items: center;
        justify-content: start;
      }
    }
}
</style>
<script>

    import CategoryForm from './form.vue' 
    import DataTable from '../../../../../../../resources/js/components/DataTable.vue'
    import {deletable} from '../../../../../../../resources/js/mixins/deletable'

    export default {
        mixins: [deletable],
        components: {DataTable, CategoryForm},
        data() {
            return {
                title: null,
                showDialog: false, 
                resource: 'categories',
                recordId: null,
                showMerge: false,
                merging: false,
                mergeFrom: null,
                mergeTo: null,
                mergeTargets: [],
            }
        },
        created() {
            this.title = 'Categorías'
        },
        methods: { 
            clickMerge(row) {
                this.mergeFrom = row
                this.mergeTo = null
                this.showMerge = true
                this.$http.get(`/${this.resource}/list`).then(response => {
                    this.mergeTargets = response.data
                        .filter(c => c.id !== row.id)
                        .map(c => ({ id: c.id, label: c.name }))
                })
            },
            doMerge() {
                this.merging = true
                this.$http.post(`/${this.resource}/merge`, { from_id: this.mergeFrom.id, to_id: this.mergeTo })
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.showMerge = false
                            this.$eventHub.$emit('reloadData')
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(() => this.$message.error('No se pudo fusionar.'))
                    .then(() => { this.merging = false })
            },
            clickCreate(recordId = null) {
                this.recordId = recordId
                this.showDialog = true
            }, 
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            }
        }
    }
</script>
