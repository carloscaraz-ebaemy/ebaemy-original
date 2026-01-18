<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="#"><i class="fas fa-cogs"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Configuración</span></li>
                <li><span class="text-muted">Empresa</span></li>
            </ol>
        </div>
        <div class="card card-config">
            <div class="card-header bg-info d-flex justify-content-between align-items-center">
                <h3 class="my-0">Datos de la Empresa</h3>
                <h4 class="d-flex m-0 align-items-center">RUC: {{ form.number }}</h4>
            </div>
            <div class="card-body">
                <form autocomplete="off"
                      @submit.prevent="submit">
                    <div class="form-body">
                        <div class="row">
                            <!-- <div class="col-md-6">
                                <div :class="{'has-danger': errors.number}"
                                     class="form-group">
                                    <label class="control-label">Número</label>
                                    <el-input v-model="form.number"
                                              :disabled="true"
                                              :maxlength="11"></el-input>
                                    <small v-if="errors.number"
                                           class="form-control-feedback"
                                           v-text="errors.number[0]"></small>
                                </div>
                            </div> -->
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.name}"
                                     class="form-group">
                                    <label class="control-label">Nombre <span class="text-danger">*</span></label>
                                    <el-input v-model="form.name"></el-input>
                                    <small v-if="errors.name"
                                           class="form-control-feedback"
                                           v-text="errors.name[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.trade_name}"
                                     class="form-group">
                                    <label class="control-label">Nombre comercial
                                        <span class="text-danger">*</span></label>
                                    <el-input v-model="form.trade_name"></el-input>
                                    <small v-if="errors.trade_name"
                                           class="form-control-feedback"
                                           v-text="errors.trade_name[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Título (nombre web)</label>
                                    <el-input v-model="form.title_web"></el-input>
                                    <div class="sub-title text-muted"><small>Requiere recargar la página</small></div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <h4 class="col-12 m-0 fw-medium">Logo y Marca</h4>
                            <div class="col-md-6 mt-2">
                                <div class="form-group">
                                    <label class="">Logo (modo claro)</label>
                                    <div v-if="loading_company_record" class="img-thumbnail w-100 d-flex align-items-center justify-content-center bg-light image-skeleton">
                                        <i class="el-icon-loading me-2"></i>
                                        <span>Cargando…</span>
                                    </div>
                                    <div v-else class="image-container">
                                        <img
                                            :src="logoLightPreviewUrl"
                                            alt="Vista previa"
                                            class="img-fluid img-small img-fluid-light img-thumbnail w-100"
                                        />
                                        <div class="overlay">
                                            <el-button
                                                type="primary"
                                                class="change-btn me-2"
                                                @click="onShowFilePicker('logo')"
                                                :loading="loading_logo"
                                                :disabled="loading_logo"
                                            >
                                                Cambiar logo
                                            </el-button>
                                            <el-button v-if="form.logo"
                                                @click="deleteLogo('logo')"
                                                :loading="loading_delete_logo"
                                                size="mini"
                                                type="danger"
                                                class="delete-logo-btn"
                                                plain>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                            </el-button>
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        @change="onGeneratePreview($event, 'logo')"
                                        ref="inputLogoLight"
                                        class="hidden"
                                        accept="image/*"
                                    />
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="sub-title text-muted">
                                            <small>Se recomienda resoluciones 700x300</small>
                                        </div>                                        
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <div class="form-group">
                                    <label class="">Logo (modo oscuro)</label>
                                    <div v-if="loading_company_record" class="img-thumbnail w-100 d-flex align-items-center justify-content-center bg-light image-skeleton">
                                        <i class="el-icon-loading me-2"></i>
                                        <span>Cargando…</span>
                                    </div>
                                    <div v-else class="image-container">
                                        <img
                                            :src="logoDarkPreviewUrl"
                                            alt="Vista previa"
                                            class="img-fluid img-small img-fluid-dark img-thumbnail w-100"
                                        />
                                        <div class="overlay">
                                            <el-button
                                                type="primary"
                                                class="change-btn me-2"
                                                @click="onShowFilePicker('logo_dark')"
                                                :loading="loading_logo_dark"
                                                :disabled="loading_logo_dark"
                                            >
                                                Cambiar logo
                                            </el-button>
                                            <el-button v-if="form.logo_dark"
                                                @click="deleteLogo('logo_dark')"
                                                :loading="loading_delete_logo_dark"
                                                size="mini"
                                                type="danger"
                                                class="delete-logo-btn"
                                                plain>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                            </el-button>
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        @change="onGeneratePreview($event, 'logo_dark')"
                                        ref="inputLogoDark"
                                        class="hidden"
                                        accept="image/*"
                                    />
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="sub-title text-muted">
                                            <small>Se recomienda resoluciones 700x300</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="form-group">
                                    <label class="">Favicon (ícono web)</label>
                                    <div v-if="loading_company_record" class="img-thumbnail w-100 d-flex align-items-center justify-content-center bg-light image-skeleton image-skeleton-small">
                                        <i class="el-icon-loading me-2"></i>
                                        <span>Cargando…</span>
                                    </div>
                                    <div v-else class="image-container">
                                        <img
                                            :src="faviconPreviewUrl"
                                            @error="onImageError('favicon')"
                                            alt="Vista previa"
                                            class="img-fluid img-fluid-dashed img-small img-thumbnail w-100"
                                        />
                                        <div class="overlay">
                                            <el-button
                                                type="primary"
                                                class="change-btn me-2"
                                                @click="onShowFilePicker('favicon')"
                                                :loading="loading_favicon"
                                                :disabled="loading_favicon"
                                            >
                                                Cambiar favicon
                                            </el-button>
                                            <el-button v-if="form.favicon"
                                                @click="deleteLogo('favicon')"
                                                :loading="loading_delete_favicon"
                                                size="mini"
                                                type="danger"
                                                class="delete-logo-btn"
                                                plain>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                            </el-button>
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        @change="onGeneratePreview($event, 'favicon')"
                                        ref="inputFavicon"
                                        class="hidden"
                                        accept="image/png"
                                    />
                                    <div class="sub-title text-muted mt-2"><small>Se recomienda una imagen con fondo transparente y cuadrada en PNG</small></div>
                                </div>
                            </div>                            

                            <div class="col-md-6 mt-3">
                                <div class="form-group">
                                    <label class="">Logo APP</label>
                                    <div v-if="loading_company_record" class="img-thumbnail w-100 d-flex align-items-center justify-content-center bg-light image-skeleton image-skeleton-small">
                                        <i class="el-icon-loading me-2"></i>
                                        <span>Cargando…</span>
                                    </div>
                                    <div v-else class="image-container">
                                        <img
                                            :src="appLogoPreviewUrl"
                                            @error="onImageError('app_logo')"
                                            alt="Vista previa"
                                            class="img-fluid img-fluid-dashed img-small img-thumbnail w-100"
                                        />
                                        <div class="overlay">
                                            <el-button
                                                type="primary"
                                                class="change-btn me-2"
                                                @click="onShowFilePicker('app_logo')"
                                                :loading="loading_app_logo"
                                                :disabled="loading_app_logo"
                                            >
                                                Cambiar logo
                                            </el-button>
                                            <el-button v-if="form.app_logo"
                                                @click="deleteLogo('app_logo')"
                                                :loading="loading_delete_app_logo"
                                                size="mini"
                                                type="danger"
                                                class="delete-logo-btn"
                                                plain>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                            </el-button>
                                        </div>
                                    </div>
                                    <input
                                        type="file"
                                        @change="onGeneratePreview($event, 'app_logo')"
                                        ref="inputAppLogo"
                                        class="hidden"
                                        accept="image/*"
                                    />
                                    <div class="sub-title text-muted mt-2"><small>Se recomienda color blanco</small></div>
                                </div>
                            </div>
                            <!-- <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Logo Tienda Virtual</label>
                                    <el-input v-model="form.logo_store" :readonly="true">
                                        <el-upload slot="append"
                                                   :headers="headers"
                                                   :data="{'type': 'logo_store'}"
                                                   action="/companies/uploads"
                                                   :show-file-list="false"
                                                   :on-success="successUpload">
                                            <el-button type="primary" icon="el-icon-upload"></el-button>
                                        </el-upload>
                                    </el-input>
                                    <div class="sub-title text-danger"><small>Se recomienda resoluciones 700x300</small></div>
                                </div>
                            </div> -->                                                                                                                

                            <div v-if="form.soap_type_id == '02'"
                                 class="col-md-6">
                                <div :class="{'has-danger': errors.certificate_due}"
                                     class="form-group">
                                    <label class="control-label">Vencimiento de Certificado</label>
                                    <el-date-picker v-model="form.certificate_due"
                                                    :clearable="true"
                                                    type="date"
                                                    value-format="yyyy-MM-dd"></el-date-picker>
                                    <small v-if="errors.certificate_due"
                                           class="form-control-feedback"
                                           v-text="errors.certificate_due[0]"></small>
                                </div>
                            </div>
                            <div v-show="false"
                                 class="col-md-6 mt-4">
                                <div :class="{'has-danger': errors.operation_amazonia}"
                                     class="form-group">
                                    <el-checkbox v-model="form.operation_amazonia">¿Emite en la Amazonía?</el-checkbox>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <h4 class="col-12 m-0 fw-medium">Campos adicionales</h4>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.detraction_account}"
                                     class="form-group">
                                    <label class="control-label">N° Cuenta de detracción</label>
                                    <el-input v-model="form.detraction_account"></el-input>
                                    <small v-if="errors.detraction_account"
                                           class="form-control-feedback"
                                           v-text="errors.detraction_account[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.mtc_code}"
                                     class="form-group">
                                    <label class="control-label">MTC
                                        <span class="text-danger">*</span></label>
                                    <el-input v-model="form.mtc_code"></el-input>
                                    <small v-if="errors.mtc_code"
                                           class="form-control-feedback"
                                           v-text="errors.mtc_code[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Rúbrica (Firma digital)</label>
                                    <el-input v-model="form.img_firm"
                                              :readonly="true">
                                        <el-upload slot="append"
                                                   :data="{'type': 'img_firm'}"
                                                   :headers="headers"
                                                   :on-success="successUpload"
                                                   :on-error="errorUpload"
                                                   :show-file-list="false"
                                                   action="/companies/uploads">
                                            <el-button icon="el-icon-upload"
                                                       type="primary"></el-button>
                                        </el-upload>
                                    </el-input>
                                    <div class="sub-title text-muted"><small>Se recomienda resoluciones 700x300</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Datos de farmacia -->
                        <!-- <div v-show="form.is_pharmacy"
                             class="row">
                            <div class="col-md-12 mt-2">
                                <h4 class="border-bottom">Datos de farmacia</h4>
                            </div>
                        </div>
                        <div v-show="form.is_pharmacy"
                             class="row">
                            <div class="col-md-12">
                                <div :class="{'has-danger': errors.cod_digemid}"
                                     class="form-group">
                                    <label class="control-label">Código de observación DIGEMID</label>
                                    <el-input v-model="form.cod_digemid"></el-input>
                                    <small v-if="errors.cod_digemid"
                                           class="form-control-feedback"
                                           v-text="errors.cod_digemid[0]"></small>
                                </div>
                            </div>
                        </div> -->
                    </div>
                    <div class="form-actions text-end pt-2">
                        <el-button :loading="loading_submit"
                                   native-type="submit"
                                   type="primary">Guardar
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card card-config">
            <div class="card-header bg-info">
                <h3 class="my-0">Consulta integrada de CPE - Validador de documentos
                    <el-tooltip class="item"
                                content="Obtener los datos desde el portal de Sunat"
                                effect="dark"
                                placement="top-start">
                        <i class="fa fa-info-circle"></i>
                    </el-tooltip>
                </h3>
            </div>
            <div class="card-body">
                <form autocomplete="off"
                      @submit.prevent="submit">
                    <div class="row">
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.integrated_query_client_id}"
                                 class="form-group">
                                <label class="control-label">Client ID</label>
                                <el-input v-model="form.integrated_query_client_id"></el-input>
                                <small v-if="errors.integrated_query_client_id"
                                       class="form-control-feedback"
                                       v-text="errors.integrated_query_client_id[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div :class="{'has-danger': errors.integrated_query_client_secret}"
                                 class="form-group">
                                <label class="control-label">Client Secret (Clave)</label>
                                <el-input v-model="form.integrated_query_client_secret"></el-input>
                                <small v-if="errors.integrated_query_client_secret"
                                       class="form-control-feedback"
                                       v-text="errors.integrated_query_client_secret[0]"></small>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions text-end pt-2">
                        <el-button :loading="loading_submit"
                                   native-type="submit"
                                   type="primary">Guardar
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card card-config">
            <div class="card-header bg-info">
                <h3 class="my-0">Guías electrónicas</h3>
            </div>
            <div class="card-body">
                <form autocomplete="off"
                      @submit.prevent="submit">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="border-bottom">Usuario Secundario Sunat</h4>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.soap_sunat_username}"
                                     class="form-group">
                                    <label class="control-label">SOAP Usuario</label>
                                    <el-input v-model="form.soap_sunat_username"
                                              :disabled="!form.config_system_env"></el-input>
                                    <div class="sub-title text-muted"><small>RUC + Usuario. Ejemplo:
                                        01234567890ELUSUARIO</small></div>
                                    <small v-if="errors.soap_sunat_username"
                                           class="form-control-feedback"
                                           v-text="errors.soap_sunat_username[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.soap_sunat_password}"
                                     class="form-group">
                                    <label class="control-label">SOAP Password</label>
                                    <el-input v-model="form.soap_sunat_password"
                                              :disabled="!form.config_system_env"></el-input>
                                    <small v-if="errors.soap_sunat_password"
                                           class="form-control-feedback"
                                           v-text="errors.soap_sunat_password[0]"></small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.api_sunat_id}"
                                     class="form-group">
                                    <label class="control-label">Client ID</label>
                                    <el-input v-model="form.api_sunat_id"></el-input>
                                    <small v-if="errors.api_sunat_id"
                                           class="form-control-feedback"
                                           v-text="errors.api_sunat_id[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.api_sunat_secret}"
                                     class="form-group">
                                    <label class="control-label">Client Secret (Clave)</label>
                                    <el-input v-model="form.api_sunat_secret"></el-input>
                                    <small v-if="errors.api_sunat_secret"
                                           class="form-control-feedback"
                                           v-text="errors.api_sunat_secret[0]"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions text-end pt-2">
                        <el-button :loading="loading_submit"
                                   native-type="submit"
                                   type="primary">Guardar
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
        <TokenRucDni></TokenRucDni>
        <SireConfiguration></SireConfiguration>
        <tenant-qr-chat></tenant-qr-chat>
    </div>
</template>

<script>
import {mapActions, mapState} from "vuex";
import TokenRucDni from './token_ruc_dni.vue'
import SireConfiguration from '../sire/partials/configuration.vue'

const PLACEHOLDER_IMAGE_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-polaroid img-default"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path /><path d="M4 12l3 -3c.928 -.893 2.072 -.893 3 0l4 4" /><path d="M13 12l2 -2c.928 -.893 2.072 -.893 3 0l2 2" /><path d="M14 7l.01 0" /></svg>`
const PLACEHOLDER_IMAGE_DATA_URI = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(PLACEHOLDER_IMAGE_SVG)}`


export default {
    components: {
        TokenRucDni,
        SireConfiguration,
    },
    computed: {
        ...mapState([
            'config',
        ]),
    },
    data() {
        return {
            loading_company_record: true,
            loading_submit: false,
            loading_delete_logo: false,
            loading_delete_logo_dark: false,
            loading_delete_favicon: false,
            loading_delete_app_logo: false,
            loading_logo: false,
            loading_logo_dark: false,
            loading_favicon: false,
            loading_app_logo: false,
            headers: headers_token,
            resource: 'companies',
            errors: {},
            form: {},
            logoLightPreviewUrl: '/logo/tulogo.png',
            logoDarkPreviewUrl: '/logo/tulogo.png',
            faviconPreviewUrl: PLACEHOLDER_IMAGE_DATA_URI,
            appLogoPreviewUrl: PLACEHOLDER_IMAGE_DATA_URI,
        }
    },
    async created() {
        this.loading_company_record = true

        await this.initForm()
        await this.$http.get(`/${this.resource}/record`)
            .then(response => {
                if (response.data !== '') {
                    this.form = response.data.data
                }
            })
            .catch(() => {
                // Si falla la carga inicial, igual quitamos el loading para no bloquear la vista.
            })
            .finally(() => {
                this.syncLogoPreviews()
                this.loading_company_record = false
            })

        this.events()
    },
    methods: {
        ...mapActions([
            'loadConfiguration',
        ]),
        events(){

            this.$eventHub.$on('reloadDataCompany', () => {
                this.getRecord()
            })

        },
        async getRecord(){
            this.loading_company_record = true

            await this.$http.get(`/${this.resource}/record`)
                .then(response => {
                    if (response.data !== '') {
                        this.form = response.data.data
                    }
                })
                .finally(() => {
                    this.syncLogoPreviews()
                    this.loading_company_record = false
                })
        },
        syncLogoPreviews() {
            this.logoLightPreviewUrl = this.getCompanyImageUrl('logo', this.form.logo)
            this.logoDarkPreviewUrl = this.getCompanyImageUrl('logo_dark', this.form.logo_dark)
            this.faviconPreviewUrl = this.getCompanyImageUrl('favicon', this.form.favicon)
            this.appLogoPreviewUrl = this.getCompanyImageUrl('app_logo', this.form.app_logo)
        },
        getCompanyImageUrl(type, value) {
            const defaultUrl = (type === 'favicon' || type === 'app_logo')
                ? PLACEHOLDER_IMAGE_DATA_URI
                : '/logo/tulogo.png'
            if (!value) return defaultUrl
            if (typeof value !== 'string') return defaultUrl
            if (value.startsWith('http://') || value.startsWith('https://')) return value
            if (value.startsWith('/')) return value

            // En algunos campos (p.ej. favicon) el backend guarda "storage/...".
            if (value.startsWith('storage/')) return `/${value}`

            if (type === 'logo' || type === 'logo_dark' || type === 'app_logo') {
                return `/storage/uploads/logos/${value}`
            }

            return value
        },
        onImageError(type) {
            if (type === 'favicon') {
                this.faviconPreviewUrl = PLACEHOLDER_IMAGE_DATA_URI
            }
            if (type === 'app_logo') {
                this.appLogoPreviewUrl = PLACEHOLDER_IMAGE_DATA_URI
            }
        },
        onShowFilePicker(type) {
            if (type === 'logo') {
                this.$refs.inputLogoLight && this.$refs.inputLogoLight.click()
            }
            if (type === 'logo_dark') {
                this.$refs.inputLogoDark && this.$refs.inputLogoDark.click()
            }
            if (type === 'favicon') {
                this.$refs.inputFavicon && this.$refs.inputFavicon.click()
            }
            if (type === 'app_logo') {
                this.$refs.inputAppLogo && this.$refs.inputAppLogo.click()
            }
        },
        onGeneratePreview(event, type) {
            const files = event?.target?.files
            const file = files && files.length ? files[0] : null
            if (!file) return

            const fileReader = new FileReader()
            fileReader.addEventListener('load', () => {
                if (type === 'logo') {
                    this.logoLightPreviewUrl = fileReader.result
                }
                if (type === 'logo_dark') {
                    this.logoDarkPreviewUrl = fileReader.result
                }
                if (type === 'favicon') {
                    this.faviconPreviewUrl = fileReader.result
                }
                if (type === 'app_logo') {
                    this.appLogoPreviewUrl = fileReader.result
                }
            })
            fileReader.readAsDataURL(file)

            this.uploadCompanyFile(file, type)

            // Permite volver a seleccionar el mismo archivo
            if (event && event.target) event.target.value = ''
        },
        uploadCompanyFile(file, type) {
            const payload = new FormData()
            payload.append('file', file)
            payload.append('type', type)

            if (type === 'logo') this.loading_logo = true
            if (type === 'logo_dark') this.loading_logo_dark = true
            if (type === 'favicon') this.loading_favicon = true
            if (type === 'app_logo') this.loading_app_logo = true

            this.$http
                .post('/companies/uploads', payload, { headers: this.headers })
                .then((response) => {
                    const data = response?.data
                    if (data && data.success) {
                        this.successUpload(data)
                        if (type === 'logo') {
                            this.logoLightPreviewUrl = this.getCompanyImageUrl('logo', data.name)
                        }
                        if (type === 'logo_dark') {
                            this.logoDarkPreviewUrl = this.getCompanyImageUrl('logo_dark', data.name)
                        }
                        if (type === 'favicon') {
                            this.faviconPreviewUrl = this.getCompanyImageUrl('favicon', data.name)
                        }
                        if (type === 'app_logo') {
                            this.appLogoPreviewUrl = this.getCompanyImageUrl('app_logo', data.name)
                        }
                    } else {
                        this.$message.error((data && data.message) ? data.message : 'Error al subir el archivo')
                        this.syncLogoPreviews()
                    }
                })
                .catch((error) => {
                    const message = error?.response?.data?.message
                    this.$message.error(message || 'Error al subir el archivo')
                    this.syncLogoPreviews()
                })
                .finally(() => {
                    if (type === 'logo') this.loading_logo = false
                    if (type === 'logo_dark') this.loading_logo_dark = false
                    if (type === 'favicon') this.loading_favicon = false
                    if (type === 'app_logo') this.loading_app_logo = false
                })
        },
        initForm() {
            this.errors = {}
            this.form = {
                id: null,
                identity_document_type_id: '06000006',
                number: null,
                name: null,
                trade_name: null,
                soap_send_id: '01',
                soap_type_id: '01',
                soap_username: null,
                soap_password: null,
                soap_url: null,
                certificate: null,
                certificate_due: null,
                logo: null,
                logo_dark: null,
                logo_store: null,
                detraction_account: null,
                operation_amazonia: false,
                toggle: false,
                config_system_env: false,
                img_firm: null,
                is_pharmacy: false,
                cod_digemid: null,
                integrated_query_client_id: null,
                integrated_query_client_secret: null,
                app_logo: null,
                soap_sunat_username: null,
                soap_sunat_password: null,
                api_sunat_id: null,
                api_sunat_secret: null,
                title_web: null
            }
        },
        submit() {
            this.loading_submit = true
            this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else {
                        console.log(error)
                    }
                })
                .then(() => {
                    this.loading_submit = false
                })
        },
        successUpload(response, file, fileList) {

            if (response.success) {
                this.$message.success(response.message)
                this.form[response.type] = response.name
            } else {
                this.$message({message: 'Error al subir el archivo', type: 'error'})
            }
        },
        errorUpload(error)
        {
            this.$message({message: 'Error al subir el archivo', type: 'error'})
        },
        deleteLogo(type) {
            const logoNames = {
                'logo': 'Logo (modo claro)',
                'logo_dark': 'Logo (modo oscuro)',
                'favicon': 'Favicon (ícono web)',
                'app_logo': 'Logo APP'
            };

            this.$confirm(`¿Está seguro de eliminar el ${logoNames[type]}?`, 'Confirmar eliminación', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            }).then(() => {
                // Determinar qué variable de loading usar
                const loadingVar = type === 'logo'
                    ? 'loading_delete_logo'
                    : (type === 'logo_dark'
                        ? 'loading_delete_logo_dark'
                        : (type === 'favicon' ? 'loading_delete_favicon' : 'loading_delete_app_logo'));
                
                this[loadingVar] = true;

                this.$http.delete('/companies/delete-logo', {
                    data: { type: type }
                })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.form[type] = null; // Limpiar el campo en el formulario
                        if (type === 'logo') {
                            this.logoLightPreviewUrl = this.getCompanyImageUrl('logo', null)
                        }
                        if (type === 'logo_dark') {
                            this.logoDarkPreviewUrl = this.getCompanyImageUrl('logo_dark', null)
                        }
                        if (type === 'favicon') {
                            this.faviconPreviewUrl = this.getCompanyImageUrl('favicon', null)
                        }
                        if (type === 'app_logo') {
                            this.appLogoPreviewUrl = this.getCompanyImageUrl('app_logo', null)
                        }
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response && error.response.data && error.response.data.message) {
                        this.$message.error(error.response.data.message);
                    } else {
                        this.$message.error('Error al eliminar el logo');
                    }
                    console.error(error);
                })
                .finally(() => {
                    this[loadingVar] = false;
                });
            }).catch(() => {
                // Usuario canceló la acción
            });
        }
    }
}
</script>

<style scoped>
.image-container {
    position: relative;
    display: inline-block;
    width: 100%;
}
.image-skeleton {
    height: 150px;
}
.image-skeleton-small {
    height: 65px;
}
.overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.908);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 0.375rem;
}
.image-container:hover .overlay {
    opacity: 1;
}
.change-btn:active {
    transform: translateY(0);
}
.img-small{
    height: auto;
    max-height: 80px;
    object-fit: contain;
}
</style>
