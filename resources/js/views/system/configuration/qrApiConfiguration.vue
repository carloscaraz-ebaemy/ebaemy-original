<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Configuración de QR Api</h3>
        </div>
            <form class="row card-body px-0" autocomplete="off" @submit.prevent="submit">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="control-label">URL de Cliente</label>
                        <el-input v-model="form.qr_api_url"></el-input>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="control-label">Api Key</label>
                        <el-input v-model="form.qr_api_token"></el-input>
                    </div>
                </div>
                <div class="col-md-12 text-right pt-2 ">
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                </div>
            </form>
    </div>
</template>

<script>
export default {
    props: ['configuration'],
    created() {
        this.initForm();
    },
    data() {
        return {
            form: {
                qr_api_url: null,
                qr_api_token: null,
            },
            resource: 'configurations',
            loading_submit: false,
        };
    },
    methods: {
        initForm() {
            if (this.configuration) {
                this.form.qr_api_url = this.configuration.qr_api_url;
                this.form.qr_api_token = this.configuration.qr_api_token;
            }
        },
        async submit() {
            this.loading_submit = true;
            try {
                await this.$http.post(`${this.resource}/qrapi`, this.form);
                this.$message({
                    message: 'Configuración guardada',
                    type: 'success',
                });
            } catch (e) {
                this.$message({
                    message: 'Error al guardar la configuración',
                    type: 'error',
                });
            } finally {
                this.loading_submit = false;
            }
        },
    },
}
</script>