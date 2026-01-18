<template>
    <el-dialog title="Crear Orden de Pago Manual" :visible="showDialogOrder" @open="open" @close="close">
        <form
            autocomplete="off"
            @submit.prevent="submit"
        >
            <div class="form-body">
                <div class="row">
                    <div class="col-12">
                            <div class="form-group">
                                <label class="control-label"
                                    >Empresa</label
                                >
                                <el-select v-model="form.client_id" 
                                >
                                    <el-option
                                    v-for="client in clients.filter(c => c.name !== 'Todos' && c.id !== 'all')"
                                    :key="client.id"
                                    :label="client.name"
                                    :value="client.id">
                                    </el-option>
                                </el-select>

                            </div>
                    </div>
                    <div class="col-6">
                            <div class="form-group">
                                <label class="control-label"
                                    >Fecha de vencimiento</label
                                >
                            <el-date-picker
                                v-model="form.date_of_due"
                                class="w-100"
                                value-format="yyyy-MM-dd"
                                default-time="00:00:00"
                                placeholder="Elija la fecha de vencimiento de la orden">
                            </el-date-picker>

                            </div>

                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="control-label"
                                >Monto</label
                            >
                                <el-input v-model="form.amount" type="text" placeholder="Ingrese el monto"
                                >
                                </el-input>
                        </div>
                    </div>
                    <div class="col-12">
                            <div class="form-group">
                                <label class="control-label"
                                    >Concepto</label
                                >
                                <el-input v-model="form.description" type="text" placeholder="Ingrese el concepto"
                                >
                                </el-input>
                            </div>                            
                    </div>
                    <div class="sub-title text-muted my-3">
                        <small>Recuerda que las órdenes manuales le ayudaran a dar seguimiento de pagos como cotizaciones u otros de forma interna. Estas NO generan bloqueos automáticos en las cuentas de tus clientes.</small>
                    </div>
                    <div class="col-md-12 text-end pt-2">
                        <el-button type="primary" native-type="submit" @click="form.notify = false" :loading="loading_submit">Crear orden</el-button>
                        <el-button type="primary" native-type="submit" @click="form.notify = true" :loading="loading_submit">Crear y notificar Orden</el-button>
                    </div>
                </div>
            </div>
        </form>
    </el-dialog>
</template>

<script>
import moment from 'moment';

export default{
    props: ['showDialogOrder', 'clients', 'recordId'],
    data(){
        return {
            form: {},
            resource: 'payment-orders',
            loading_submit: false
        }
    },
    methods:{
        open(){
            this.initForm()
            if (this.recordId) {
                this.form.client_id = this.recordId 
            }
        },
        close(){
            this.$emit('update:showDialogOrder', false)
        },
        initForm()
        {
            this.form = {
                client_id: null,
                amount: null,
                description: null,
                date_of_due: moment().format('YYYY-MM-DD'),
            }
        },
        submit()
        {
            this.$http.post(`/${this.resource}/create`, this.form)
                .then(response => {
                    console.log(response.data);
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.$emit('refresh-records')
                        this.close()
                    } else {
                        this.$message.warning(response.data.message);
                    }
                })
        },

    }
}
</script>