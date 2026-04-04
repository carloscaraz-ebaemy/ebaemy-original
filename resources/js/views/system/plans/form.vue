<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" width="680px">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">

                <!-- ── Información básica ──────────────────────── -->
                <div style="background:#f8fafc;border-radius:10px;padding:16px 20px;margin-bottom:20px">
                    <h6 style="font-weight:700;color:#374151;margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Información del plan
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.name}">
                                <label class="control-label">Nombre del plan</label>
                                <el-input v-model="form.name" placeholder="Ej: Pro, Negocio, Enterprise"></el-input>
                                <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" :class="{'has-danger': errors.pricing}">
                                <label class="control-label">Precio mensual (S/)</label>
                                <el-input v-model="form.pricing" placeholder="0.00">
                                    <template slot="prepend">S/</template>
                                </el-input>
                                <small class="form-control-feedback" v-if="errors.pricing" v-text="errors.pricing[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Estado</label>
                                <div style="padding-top:8px">
                                    <el-switch v-model="form.locked"
                                               active-text="Bloqueado"
                                               inactive-text="Activo"
                                               active-color="#ef4444"
                                               inactive-color="#22c55e">
                                    </el-switch>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Límites de uso ──────────────────────────── -->
                <div style="background:#f0fdf4;border-radius:10px;padding:16px 20px;margin-bottom:20px;border:1px solid #bbf7d0">
                    <h6 style="font-weight:700;color:#166534;margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Límites del plan
                    </h6>
                    <div class="row">
                        <!-- Usuarios -->
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.limit_users || errorLUser.limit_users}">
                                <label class="control-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Usuarios
                                </label>
                                <el-input v-model="limit_users" @input="validateLUsers" :disabled="users_unlimited" :placeholder="users_unlimited ? '∞' : 'Ej: 5'"></el-input>
                                <el-checkbox v-model="users_unlimited" @change="setUnlimitUsers" style="margin-top:4px">Ilimitado</el-checkbox>
                                <small class="form-control-feedback d-block" v-if="errors.limit_users" v-text="errors.limit_users[0]"></small>
                                <small class="form-control-feedback" v-if="errorLUser.limit_users" v-text="errorLUser.limit_users[0]"></small>
                            </div>
                        </div>

                        <!-- Sucursales -->
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.establishments_limit}">
                                <label class="control-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    Sucursales
                                </label>
                                <el-input v-model="form.establishments_limit" :disabled="form.establishments_unlimited" :placeholder="form.establishments_unlimited ? '∞' : 'Ej: 3'"></el-input>
                                <el-checkbox v-model="form.establishments_unlimited" style="margin-top:4px">Ilimitado</el-checkbox>
                                <small class="form-control-feedback d-block" v-if="errors.establishments_limit" v-text="errors.establishments_limit[0]"></small>
                            </div>
                        </div>

                        <!-- Documentos -->
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.limit_documents || errorLDocument.limit_documents}">
                                <label class="control-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    Comprobantes / mes
                                </label>
                                <el-input v-model="limit_documents" @input="validateLDocuments" :disabled="documents_unlimited" :placeholder="documents_unlimited ? '∞' : 'Ej: 500'"></el-input>
                                <el-checkbox v-model="documents_unlimited" @change="setUnlimitDocuments" style="margin-top:4px">Ilimitado</el-checkbox>
                                <el-checkbox v-model="form.include_sale_notes_limit_documents" style="margin-top:2px">Incluir notas de venta</el-checkbox>
                                <small class="form-control-feedback d-block" v-if="errors.limit_documents" v-text="errors.limit_documents[0]"></small>
                                <small class="form-control-feedback" v-if="errorLDocument.limit_documents" v-text="errorLDocument.limit_documents[0]"></small>
                            </div>
                        </div>

                        <!-- Ventas -->
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.sales_limit}">
                                <label class="control-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    Ventas mensual (S/)
                                    <el-tooltip :content="form.include_sale_notes_sales_limit ? 'Aplica para CPE y Nota de venta' : 'Aplica solo para CPE'" effect="dark" placement="top">
                                        <i class="fa fa-info-circle" style="color:#9ca3af"></i>
                                    </el-tooltip>
                                </label>
                                <el-input v-model="form.sales_limit" :disabled="form.sales_unlimited" :placeholder="form.sales_unlimited ? '∞' : 'Ej: 50000'">
                                    <template slot="prepend">S/</template>
                                </el-input>
                                <el-checkbox v-model="form.sales_unlimited" style="margin-top:4px">Ilimitado</el-checkbox>
                                <el-checkbox v-model="form.include_sale_notes_sales_limit" style="margin-top:2px">Incluir notas de venta</el-checkbox>
                                <small class="form-control-feedback d-block" v-if="errors.sales_limit" v-text="errors.sales_limit[0]"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Info adicional ──────────────────────────── -->
                <div style="background:#eff6ff;border-radius:10px;padding:12px 20px;margin-bottom:12px;border:1px solid #bfdbfe">
                    <p style="margin:0;font-size:12px;color:#1e40af">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        Los <strong>módulos</strong> (Ecommerce, Smart Stock, Logístico, etc.) se asignan desde el botón <strong>"Features"</strong> en la tarjeta del plan después de guardarlo.
                    </p>
                </div>

            </div>
            <div class="form-actions text-right pt-2" style="border-top:1px solid #e5e7eb;padding-top:16px">
                <el-button @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Guardar plan
                </el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'recordId', 'plan_documents'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'plans',
                documents_unlimited: null,
                users_unlimited: null,
                limit_users: null,
                limit_documents: null,
                errors: {},
                errorLDocument: {},
                errorLUser: {},
                form: {},
            }
        },
        created() {
            this.initForm()
        },
        methods: {
            initForm() {
                this.limit_users = null
                this.limit_documents = null
                this.documents_unlimited = false
                this.users_unlimited = false
                this.errors = {}
                this.errorLDocument = {}
                this.errorLUser = {}

                this.form = {
                    id: null,
                    name: null,
                    pricing: null,
                    limit_users: null,
                    limit_documents: null,
                    plan_documents: [],
                    locked: false,
                    establishments_limit: 0,
                    establishments_unlimited: true,
                    sales_limit: 0,
                    sales_unlimited: true,
                    include_sale_notes_sales_limit: false,
                    include_sale_notes_limit_documents: false,
                }
            },
            create() {
                this.titleDialog = (this.recordId) ? 'Editar plan' : 'Nuevo plan'
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                        this.setData(response.data.data)
                    })
                }
            },
            validateInputs() {
                if (!this.form.establishments_unlimited) {
                    if (isNaN(this.form.establishments_limit)) return this.getResponseValidations(false, 'Límite de sucursales no es un número válido.')
                }
                if (!this.form.sales_unlimited) {
                    if (isNaN(this.form.sales_limit)) return this.getResponseValidations(false, 'Límite de ventas no es un número válido.')
                }
                return this.getResponseValidations()
            },
            submit() {
                if (this.validateLUsers().limit_users || this.validateLDocuments().limit_documents) return

                const validate_inputs = this.validateInputs()
                if (!validate_inputs.success) return this.$message.error(validate_inputs.message)

                this.transform()

                this.loading_submit = true
                this.$http.post(`${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data
                        } else {
                            console.log(error.response)
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })
            },
            setData(data) {
                this.form = data
                this.form.plan_documents = Object.values(data.plan_documents || [])
                this.form.locked = !!data.locked
                this.users_unlimited = (data.limit_users == 0) ? true : false
                this.documents_unlimited = (data.limit_documents == 0) ? true : false
                this.limit_users = (this.users_unlimited) ? "∞" : data.limit_users
                this.limit_documents = (this.documents_unlimited) ? "∞" : data.limit_documents
            },
            transform() {
                if (this.users_unlimited) {
                    this.form.limit_users = 0
                } else {
                    this.form.limit_users = this.limit_users
                }
                if (this.documents_unlimited) {
                    this.form.limit_documents = 0
                } else {
                    this.form.limit_documents = this.limit_documents
                }
            },
            validateLDocuments() {
                this.errorLDocument = {}
                if (!this.documents_unlimited) {
                    if (this.limit_documents < 1)
                        this.$set(this.errorLDocument, 'limit_documents', ['Límite de documentos debe ser mayor a cero']);
                }
                return this.errorLDocument
            },
            validateLUsers() {
                this.errorLUser = {}
                if (!this.users_unlimited) {
                    if (this.limit_users < 1)
                        this.$set(this.errorLUser, 'limit_users', ['Límite de usuarios debe ser mayor a cero']);
                }
                return this.errorLUser
            },
            setUnlimitDocuments() {
                this.limit_documents = (this.documents_unlimited) ? "∞" : null
                this.form.limit_documents = (this.limit_documents == "∞") ? 0 : this.limit_documents
            },
            setUnlimitUsers() {
                this.limit_users = (this.users_unlimited) ? "∞" : null
                this.form.limit_users = (this.limit_users == "∞") ? 0 : this.limit_users
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            }
        }
    }
</script>
