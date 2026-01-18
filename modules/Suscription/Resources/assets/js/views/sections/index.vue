<template>
    <div>
        <!-- <div class="page-header pr-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
        </div> -->
        <div class="card mb-0">
            <div class="card-header bg-info d-flex align-items-center justify-content-between">
                <h3 class="my-0">Listado de {{ title }}</h3>
                
                <div class="right-wrapper pull-right">
                    <button type="button" class="btn btn-info btn-info-default btn-sm  mt-2 me-2" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>
                </div>
            </div>
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>Nombre</th>
                        <th class="text-end">Acciones</th>
                    <tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.name }}</td>
                        <td class="text-end">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info me-2" @click.prevent="clickCreate(row.id)">Editar</button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                        </td>
                    </tr>
                </data-table>
            </div>

            <record-form 
                :showDialog.sync="showDialog"
                :recordId="recordId"></record-form> 
        </div>
    </div>
</template>

<script>

    import RecordForm from './form.vue' 
    import DataTable from '@components/BasicDataTable.vue'
    import {deletable} from '@mixins/deletable'

    export default {
        mixins: [deletable],
        components: {DataTable, RecordForm},
        data() {
            return {
                title: null,
                showDialog: false, 
                resource: 'suscription/sections',
                recordId: null,
            }
        },
        created() {
            this.title = 'Secciones'
        },
        methods: { 
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
