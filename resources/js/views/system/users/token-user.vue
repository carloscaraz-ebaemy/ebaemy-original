<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Token de Usuario</h3>
        </div>
        <div class="card-body">
            <form autocomplete="off" @submit.prevent="submit">
                <div class="form-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group" :class="{'has-danger': errors.api_token}">
                                <label class="control-label">Token</label>
                                <el-input 
                                    v-model="displayToken" 
                                    :disabled="true" 
                                    type="text">
                                </el-input>
                                <small class="form-control-feedback" v-if="errors.api_token" v-text="errors.api_token[0]"></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions text-end pt-2">
                    <el-button type="primary" @click="toggleToken" :disabled="!form.api_token">
                        {{ showToken ? 'Ocultar Token' : 'Mostrar Token' }}
                    </el-button>
                    <el-button type="primary" v-clipboard:copy="form.api_token" v-clipboard:success="onCopy" :disabled="!form.api_token">
                        Copiar Token
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
            loading_submit: false,
            resource: 'users',
            errors: {},
            form: {},
            showToken: false,
        }
    },
    computed: {
        displayToken() {
            if (!this.form.api_token) return '';

            const token = this.form.api_token;
            if (this.showToken) {
                return token;
            } else {
                if (token.length <= 6) return token;
                const start = token.slice(0, 3);
                const end = token.slice(-3);
                return `${start}●●●●●●●●${end}`;
            }
        }
    },
    created() {
        this.initForm();
        this.$http.get(`/${this.resource}/record`)
            .then(response => {
                if (response.data !== '') {
                    this.form = response.data.data;
                }
            });
    },
    methods: {
        initForm() {
            this.errors = {};
            this.form = {
                id: null,
                api_token: null,
            };
        },
        submit() {
            this.loading_submit = true;
            this.$http.post(`/${this.resource}/token`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.form.api_token = response.data.data.api_token;
                        this.$message.success(response.data.message);
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data;
                    } else {
                        console.log(error);
                    }
                })
                .then(() => {
                    this.loading_submit = false;
                });
        },
        toggleToken() {
            this.showToken = !this.showToken;
        },
        onCopy() {
            this.$message.success('Token copiado al portapapeles');
        }
    }
};
</script>
