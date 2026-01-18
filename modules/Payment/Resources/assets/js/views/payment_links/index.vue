<template>
<div>
    <div class="page-header pe-0">
        <h2>
            <a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a>
        </h2>
        <ol class="breadcrumbs">
            <li class="active"><span>Links de pago</span></li>
        </ol>
        <div class="right-wrapper pull-right">
            <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="clickCreate()">
                <i class="fa fa-plus-circle"></i> Nuevo
            </button>
        </div>
    </div>
    <div class="card tab-content-default row-new mb-0">
        <!-- <div class="card-header bg-info">
            <h3 class="my-0">Generador de links de pago</h3>
        </div> -->
        <div class="card-body">
            <data-table :resource="resource">

                <tr slot="heading" width="100%">
                    <th>#</th>
                    <th>Identificador</th>
                    <th>Pago asociado</th>
                    <th>Tipo</th>
                    <th>Link</th>
                    <th>Total</th>
                    <th class="text-end"></th>
                </tr>

                <tr></tr>
                <tr slot-scope="{ index, row }">
                    <td>{{ index }}</td>
                    <td>{{ row.uuid }}</td>
                    <td>{{ row.payment_number_full }}</td>
                    <td>{{ row.payment_link_type_description }}</td>
                    <td>

                        <button type="button"
                                style="min-width: 41px"
                                class="btn waves-effect waves-light btn-xs btn-info m-1__2"
                                v-clipboard:copy="row.user_payment_link"
                                v-clipboard:success="onCopyText"
                                v-clipboard:error="onErrorCopyText">
                                Copiar
                        </button>

                    </td>
                    <td>{{ row.total }}</td>

                    <td class="text-end">
                        <el-dropdown
                            trigger="click"
                            @command="handleDropdownCommand($event, row)"
                        >
                            <el-button
                                class="btn btn-default btn-sm btn-dropdown-toggle"
                                size="mini"
                                native-type="button"
                            >
                                <i class="fas fa-ellipsis-v"></i>
                                <i class="fas fa-ellipsis-h" style="display: none;"></i>
                            </el-button>
                            <el-dropdown-menu slot="dropdown">
                                <template v-if="typeUser === 'admin'">
                                    <el-dropdown-item
                                        v-if="row.payment_link_type_id == '02'"
                                        command="transactions"
                                    >
                                        Transacciones
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="!row.has_payment"
                                        command="edit"
                                    >
                                        Editar
                                    </el-dropdown-item>
                                    <el-dropdown-item command="delete">
                                        Eliminar
                                    </el-dropdown-item>
                                </template>
                            </el-dropdown-menu>
                        </el-dropdown>
                    </td>
                </tr>
            </data-table>
        </div>

        <payment-link-form 
            :recordId="recordId" 
            :showDialog.sync="showDialog"
            ></payment-link-form>

        <payment-link-transactions 
            :recordId="recordId" 
            :showDialog.sync="showDialogTransactions"
            ></payment-link-transactions>
    </div>
</div>
</template>

<script>

    import PaymentLinkForm from "./form.vue";
    import PaymentLinkTransactions from "./partials/transactions.vue";
    import DataTable from "@components/DataTable.vue";
    import {deletable} from "@mixins/deletable";

    export default {
        props: [
            'typeUser'
        ],
        mixins: [deletable],
        components: {
            PaymentLinkForm,
            PaymentLinkTransactions,
            DataTable
        },
        data() {
            return { 
                resource: 'payment-links',
                recordId: null, 
                showDialog: false,
                showDialogTransactions: false,
            }
        },
        async created() {
        },
        methods: { 
            handleDropdownCommand(command, row) {
                switch (command) {
                    case 'transactions':
                        if (row.payment_link_type_id == '02') {
                            this.clickShowTransactions(row.id);
                        }
                        break;
                    case 'edit':
                        if (!row.has_payment) {
                            this.clickCreate(row.id);
                        }
                        break;
                    case 'delete':
                        this.clickDelete(row.id);
                        break;
                    default:
                        break;
                }
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit("reloadData")
                );
            },
            clickShowTransactions(recordId){
                this.recordId = recordId
                this.showDialogTransactions = true
            },
            onCopyText: function(e) {
                this.$message.success('Texto copiado al portapapeles')
            },
            onErrorCopyText: function(e) {
                this.$message.error('No se pudo copiar el texto al portapapeles')
                console.log(e)
            },
            clickCreate(recordId = null){
                this.recordId = recordId
                this.showDialog = true
            }
        }
    }
</script>
