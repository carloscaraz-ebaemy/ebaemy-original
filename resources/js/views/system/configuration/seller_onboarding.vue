<template>
<div class="card">
    <div class="card-header bg-info bg-info-customer-admin">
        <h3 class="my-0">Onboarding de sellers (marketplace)</h3>
    </div>
    <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="row">
                <div class="col-md-12">
                    <label class="control-label">
                        Autoaprobar sellers nuevos
                        <el-tooltip
                            class="item"
                            content="Si está activo, las solicitudes desde /seller/register que pasen la validación SUNAT (RUC ACTIVO + HABIDO) se aprueban automáticamente sin esperar revisión manual del SuperAdmin. El tenant se crea al instante con el plan seleccionado abajo."
                            effect="dark"
                            placement="top-start">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <div class="form-group">
                        <el-switch
                            v-model="form.auto_approve_sellers"
                            active-text="Sí"
                            inactive-text="No"
                            :disabled="loading"></el-switch>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="control-label">
                        Plan por defecto al autoaprobar
                        <el-tooltip
                            class="item"
                            content="Plan que se asignará a todos los sellers autoaprobados. Recomendado: Gratis (S/ 0) con límite de 25 productos en marketplace."
                            effect="dark"
                            placement="top-start">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <div class="form-group">
                        <el-select
                            v-model="form.seller_default_plan_id"
                            placeholder="Selecciona un plan"
                            :disabled="loading"
                            clearable
                            style="width: 100%">
                            <el-option
                                v-for="plan in plans"
                                :key="plan.id"
                                :label="plan.label"
                                :value="plan.id"></el-option>
                        </el-select>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="control-label">
                        Exigir RUC activo y habido
                        <el-tooltip
                            class="item"
                            content="Solo autoaprueba si SUNAT confirma que el RUC está ACTIVO y HABIDO. Si lo desactivas, también se autoaprobarán solicitudes con RUC suspendido o no hallado (no recomendado)."
                            effect="dark"
                            placement="top-start">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <div class="form-group">
                        <el-switch
                            v-model="form.seller_requires_active_ruc"
                            active-text="Sí"
                            inactive-text="No"
                            :disabled="loading"></el-switch>
                    </div>
                </div>
            </div>

            <div class="form-actions text-right pt-2">
                <el-button
                    type="primary"
                    native-type="submit"
                    :loading="loading_submit">
                    Guardar
                </el-button>
            </div>
        </form>
    </div>
</div>
</template>

<script>
export default {
    data() {
        return {
            resource: 'configurations',
            loading: true,
            loading_submit: false,
            plans: [],
            form: {
                auto_approve_sellers: false,
                seller_default_plan_id: null,
                seller_requires_active_ruc: true,
            },
        }
    },
    async created() {
        await this.getData()
    },
    methods: {
        async getData() {
            this.loading = true
            try {
                const { data } = await this.$http.get(`/${this.resource}/seller-onboarding`)
                this.plans = data.plans || []
                this.form = Object.assign({}, this.form, data.config || {})
            } catch (error) {
                console.error('seller-onboarding load error', error)
                this.$message.error('No se pudo cargar la configuración de sellers')
            } finally {
                this.loading = false
            }
        },
        submit() {
            if (this.form.auto_approve_sellers && !this.form.seller_default_plan_id) {
                this.$message.warning('Para activar la autoaprobación debes elegir un plan por defecto.')
                return
            }

            this.loading_submit = true
            this.$http
                .post(`/${this.resource}/seller-onboarding`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    const msg = error.response && error.response.data
                        ? (error.response.data.message || 'Error al guardar')
                        : 'Error al guardar'
                    this.$message.error(msg)
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
    },
}
</script>
