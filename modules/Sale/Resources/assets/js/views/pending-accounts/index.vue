<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Comisiones</span></li>
                <li><span class="text-muted">Cuentas pendientes</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button type="button" class="btn btn-custom btn-sm mt-2 me-2" @click.prevent="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </div>
        <div class="card mb-0 tab-content-default row-new">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading" width="100%">
                        <th>#</th>
                        <th>Vendedor</th>
                        <th>Tipo</th>
                        <th>Comisión</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ index }}</td>
                        <td>{{ row.seller_name }}</td>
                        <td>{{ row.commission_type }}</td>
                        <td>{{ row.amount }}</td>
                        <td class="text-end">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-info me-1d" @click.prevent="clickCreate(row.id)">Editar</button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                        </td>
                    </tr>
                </data-table>
            </div>
            <pending-account-commission-form :showDialog.sync="showDialog" :recordId="recordId"></pending-account-commission-form>
        </div>
    </div>
</template>

<script>
import PendingAccountCommissionForm from './form.vue'
import DataTable from '@components/DataTable.vue'
import { deletable } from '@mixins/deletable'

export default {
    mixins: [deletable],
    components: { PendingAccountCommissionForm, DataTable },
    data() {
        return {
            showDialog: false,
            resource: 'pending-account-commissions',
            recordId: null,
        }
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