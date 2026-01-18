<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Configuración de tareas automáticas </h3>
        </div>
            <form class="row card-body px-0" autocomplete="off" @submit.prevent="submit">
                <div class="col-md-12">
                    <div class="form-group">
                        <el-checkbox @change="submit" v-model="form.active_cron">
                            Activar tareas automáticas CRON
                        </el-checkbox>
                        <div class="ms-2" >
                        <span style="opacity: 0.4;">
                            Habilita el sistema para generar y enviar recordatorios de pagos de pago automáticamente.
                        </span>
                        </div>
                    </div>

                    <div v-if="form.active_cron" class="col-md-12 container">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group position-relative form-days-cron">
                                    <label class="control-label">
                                        Dias antes del vencimiento
                                    </label>
                                    <el-input type="number" :min="1" :max="25"  placeholder="Ingrese su dia" v-model="form.day_before_due">                                        
                                    </el-input>
                                    <span class="day-span-cron">Dias</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">
                                        Hora para generar la orden
                                    </label>
                                    <el-time-select
                                        v-model="form.hour_generate_payment_order"
                                        class="w-100"
                                        :picker-options="{
                                            start: '00:00',
                                            end: '23:59',
                                            step: '01:00'   // ← salto de 1 hora
                                        }"
                                        placeholder="Ingrese su hora"
                                        >
                                    </el-time-select>

                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <el-switch v-model="form.send_notification_cron" @change="submit">
                                </el-switch>
                                <span>
                                    Habilitar notificaciones:
                                </span>
                            </div>
                            <div v-if="form.send_notification_cron">
                                <span style="opacity: 0.4;">
                                    Luego de haberse generado las ordenes, las notificaciones se enviarán automáticamente por WhatsApp y Email (debe configurar previamente ambos servicios).
                                </span>

                                <div class="mt-2">
                                    <h4>
                                        Plantilla de mensaje
                                    </h4>
                                    <p style="opacity: 0.7;">
                                        Personaliza el mensaje que recibirán los clientes. Usa variables para datos dinámicos.
                                    </p>
                                    <div class="h-auto">
                                        <el-input
                                            ref="textarea"
                                            type="textarea"
                                            placeholder="Please input"
                                            rows="6"
                                            autosize
                                            v-model="form.qr_api_msg">
                                        </el-input>
                                    </div>

                                    <div>
                                        <nav style="opacity: 0.4;">
                                            Variables disponibles:
                                                <div class="d-flex align-items-center justify-content-center flex-wrap gap-1">
                                                    <el-tooltip  content="Nombre del cliente a quien se notifica" placement="bottom" effect="light">
                                                        <el-button>
                                                            @variable_nombre           
                                                        </el-button>
                                                    </el-tooltip>
                                                    <el-tooltip  content="Nombre del plan del cliente" placement="bottom" effect="light">
                                                        <el-button>
                                                            @variable_plan              
                                                        </el-button>
                                                    </el-tooltip>
                                                    <el-tooltip  content="El precio del plan (bien si esta configurado uno nuevo o del plan por defecto)" placement="bottom" effect="light">
                                                        <el-button>
                                                            @variable_precios
                                                        </el-button>
                                                    </el-tooltip>
                                                    <el-tooltip  content="Fecha de vencimiento de la orden (una vez pasada, se vence y se bloquea el cliente)" placement="bottom" effect="light">
                                                        <el-button>
                                                            @variable_fecha_vencimiento
                                                        </el-button>
                                                    </el-tooltip>

                                                </div>
                                        </nav>
                                    </div>

                                </div>


                            </div>
                            <div class="col-md-12 text-end pt-2">
                                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                            </div>

                        </div>
                    </div>
                </div>
            </form>
    </div>
</template>

<script>
export default {
    props: ['configuration'],
    data() {
        return {
            form: {
                active_cron: false,
                hour_generate_payment_order: '',
                day_before_due: '',
                send_notification_cron: false,
                qr_api_msg: ''
            },
            resource : 'configurations',
            textarea: null,
            loading_submit: false,
        };
    },
    created() {
        if (this.configuration) {
            this.form.day_before_due = this.configuration.day_before_due || 3;
            this.form.hour_generate_payment_order = this.configuration.hour_generate_payment_order || '09:00:00';
            this.form.active_cron = Boolean(this.configuration.active_cron);
            this.form.send_notification_cron = Boolean(this.configuration.send_notification_cron);
            this.form.qr_api_msg = this.configuration.qr_api_msg
        }
    },
    methods: {
        submit(){
            this.loading_submit = true;
            this.$http.post(`${this.resource}/`, this.form)
                .then(response => {
                    if (response.data.success) {
                        return this.$message.success('Configuración guardada correctamente');
                    }
                })
                .catch(error => {
                    this.errors = error.response.data.errors || {};
                    this.$message.error('Error al guardar la configuración');
                })
                .finally(() => {
                    this.loading_submit = false;
                });

        }

    }
}
</script>