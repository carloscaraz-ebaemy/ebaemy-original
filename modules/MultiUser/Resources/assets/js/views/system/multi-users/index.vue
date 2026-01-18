<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/dashboard">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users-group" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm  mt-2 me-2" type="button" @click.prevent="clickCreate()"><i
                    class="fa fa-plus-circle"></i> Agregar
                </button>
            </div>
        </div>
        <div class="card mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <th>#</th>
                        <th>Empresa Origen</th>
                        <th>Empresa Destino</th>
                        <!-- <th>Subdominio </th> -->
                        <th class="text-center">Usuario</th>
                        <th class="text-center">Perfil</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ index }}</td>
                        <td>
                            {{ row.client_origin_full_name }}
                            <br/>
                            <small><b> {{ row.origin_hostname }} </b> </small>
                        </td>
                        <td>
                            {{ row.client_destination_full_name }}
                            <br/>
                            <small><b> {{ row.destination_hostname }} </b> </small>
                        </td>
                        <!-- <td>{{ row.hostname }}</td> -->
                        <td class="text-center">{{ row.user_full_name }}</td>
                        <td class="text-center">{{ row.description_type }}</td>

                        <td class="text-end">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                        </td>
                    </tr>
                </data-table>
            </div>

            <multi-user-form :recordId="recordId"
                          :showDialog.sync="showDialog"></multi-user-form>


        </div>
    </div>
</template>

<script>

import MultiUserForm from './form.vue'
import DataTable from '@components/DataTable.vue'
import { deletable } from '@mixins/deletable'

export default {
    mixins: [deletable],
    components: {
        MultiUserForm,
        DataTable
    },
    data() {
        return {
            title: null,
            showDialog: false,
            resource: 'multi-users',
            recordId: null,
        }
    },
    created()
    {
        this.title = 'Multi Usuarios'
    },
    computed:
    {
    },
    methods:
    {
        clickCreate(recordId = null)
        {
            this.recordId = recordId
            this.showDialog = true
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                    // this.$eventHub.$emit('reloadData')
                    console.log(id)
                ).finally(() =>
                    this.$eventHub.$emit('reloadData')
                )
        },
    }
}
</script>
