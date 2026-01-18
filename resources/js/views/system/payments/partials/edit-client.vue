<template>
    <el-dialog title="Actualizar Información de Contacto y del Servicio" :visible="showDialogEditClient" @open="open" @close="close" :close-on-click-modal="false">
        <div class="sub-title text-muted">
            <small>Estos datos son utilizados para la generación de las órdenes automáticas, además del envío de notificaciones por correo y WhatsApp. Esto no modifica los datos de la cuenta de la empresa creada.</small>
        </div>
        <form
            autocomplete="off"
            @submit.prevent="submit"
        >
            <div class="form-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">
                            Nombre
                        </label>
                        <el-input type="text" v-model="form.client_name" >
                        </el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">
                            Correo
                        </label>
                        <el-input
                            type="text"
                            v-model="form.contact_email"
                            >
                        </el-input>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group position-relative form-whatsapp">
                        <span class="number-code-country text-muted">+51</span>
                        <label class="control-label">
                            Whatsapp
                        </label>
                        <el-input
                            type="text"
                            v-model="form.phone_ws"
                            >                            
                        </el-input>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label">
                            Plan
                        </label>
                    <el-select v-model="form.plan_id" placeholder="Seleccione un plan">                    
                        <el-option
                        v-for="plan in plans"
                        :key="plan.id"
                        :label="plan.name"
                        :value="plan.id">
                        </el-option>
                    </el-select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label">
                            Fecha de activación
                        </label>
                            <el-date-picker
                                clearable
                                v-model="form.start_billing_cycle"
                                value-format="yyyy-MM-dd"
                                default-time="00:00:00"
                                class="w-100">
                            </el-date-picker>
                        <small
                        v-if="errors.start_billing_cycle"
                        class="form-control-feedback"
                        v-text="
                            errors.start_billing_cycle[0]
                        "
                        ></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group" >
                        <label class="control-label">
                            Precio
                        </label>
                        <el-input
                            type="text"

                            v-model="form.price"
                            >
                        </el-input>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label">
                            Periodos
                        </label>
                    <el-select v-model="form.plan_period_id" placeholder="Seleccione un periodo">            
                        <el-option
                        v-for="period in periods"
                        :key="period.id"
                        :label="period.name"
                        :value="period.id">
                        </el-option>
                    </el-select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label">
                            Fecha de finalización
                        </label>
                            <el-date-picker
                                clearable
                                v-model="form.ending_billing_cycle"
                                value-format="yyyy-MM-dd"
                                default-time="00:00:00"
                                placeholder="Elija la fecha de finalización del servicio"
                                class="w-100">
                            </el-date-picker>
                    </div>
                </div>
                <div class="col-md-12 text-end pt-2">
                        <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                </div>
            </div>
            </div>
        </form>
    </el-dialog>
</template>

<script>
export default {
    props: ['showDialogEditClient', 'client', 'resource'],
    data(){
        return {
            plans: [],
            periods: [],
            form: {},
            errors: {},
            loading_submit: false
        }
    },

    methods:{
        open(){
            this.initForm()
            this.form = this.client
            this.getTables()
        },
        close(){
            this.errors = {}
            this.loading_submit = false
            this.$emit('update:showDialogEditClient', false)
        },
        initForm()
        {
            this.form = {
                client_id: null,
                price: null,
                phone_ws: null,
                contact_email: null,
                plan_id: null,
                plan_period_id: null,
                start_billing_cycle: null,
            }
        },
        getTables(){
            this.$http.get(`/payment-orders/client/tables`).then(({data}) => {
                this.plans = data.plans
                this.periods = data.periods
            })
        },

        submit()
        {
            this.loading_submit = true
            this.$http.post(`/payment-orders/updateClient/${this.client.id}`, this.form)
                .then(response => {
                        if (response.data.success) {
                        this.$emit('refresh-records')
                        this.close()
                        this.$notify({
                            title: 'Éxito',
                            message: 'Se actualizó correctamente',
                            type: 'success'
                        });
                        
                        } else {
                            this.$notify({
                                title: 'Error',
                                message: 'Ocurrió un error al actualizar',
                                type: 'error'
                            });

                            this.loading_submit = false
                        }
                })
                .catch(error => {
                    
                    this.loading_submit = false
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data;
                    } else {
                        this.$notify({
                            title: 'Error',
                            message: 'Ocurrió un error al actualizar',
                            type: 'error'
                        });
                    }
                });

    }
}
}
</script>