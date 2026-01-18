<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Configuración de correo</h3>
        </div>
            <form class="row card-body px-0" autocomplete="off" @submit.prevent="submit">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Dirección del host de correo</label>
                        <el-input v-model="form.mail_host"></el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Puerto del host de correo</label>
                        <el-input v-model="form.mail_port"></el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Nombre de usuario de correo</label>
                        <el-input v-model="form.mail_username"></el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Contraseña del usuario de correo</label>
                        <el-input v-model="form.mail_password" type="password"></el-input>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Encriptación de correo</label>
                        <el-select v-model="form.mail_encryption" style="width: 100%">
                            <el-option label="SSL" value="ssl"></el-option>
                            <el-option label="TLS" value="tls"></el-option>
                            <el-option label="Ninguna" value=""></el-option>
                        </el-select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group pt-3">
                        <a
                            href="https://manual.uio.la/Pro7/guias-adicionales/configuracion-smtp-segura"
                            class="btn btn-sm btn-outline-primary"
                            target="_blank"
                            rel="noopener noreferrer"
                            role="button"
                        >
                            Para correos Gmail verificar el manual
                        </a>
                    </div>
                </div>
                <div class="col-md-12 text-end pt-2">
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
            loading_submit: false,
            resource: 'configurations',
            errors: {},
            form: {
                mail_host: '',
                mail_port: '',
                mail_username: '',
                mail_password: '',
                mail_encryption: ''
            }
        }
    },
    methods: {
        initForm() {
            this.form = {
                mail_host: this.configuration.mail_host || '',
                mail_port: this.configuration.mail_port || '',
                mail_username: this.configuration.mail_username || '',
                mail_password: this.configuration.mail_password || '',
                mail_encryption: this.configuration.mail_encryption || ''
            };
        },
        submit() {
            this.loading_submit = true;
            // Logic to submit the form data
            // After submission, reset loading state
            this.$http.post(`${this.resource}/emails`, this.form)
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

            this.loading_submit = false;
        }
    },
}
</script>
