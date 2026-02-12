<template>
    <div class="col-lg-6 col-md-12 ">
        <div class="card card-config">
            
            <div class="card-header bg-info">
            <h3 class="my-0">Gestión de Políticas y Condiciones</h3>
            </div>
            
            <div class="card-body p-0">
                <el-table :data="policyList" stripe style="width: 100%" :show-header="true">
                    <el-table-column label="Documento" min-width="200">
                        <template slot-scope="scope">
                            <div class="d-flex align-items-center">
                                <i :class="scope.row.icon" class="text-primary mr-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <div class="font-weight-bold text-dark">{{ scope.row.title }}</div>
                                    <small class="text-muted">{{ scope.row.description }}</small>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    
                    <el-table-column label="Estado" width="120" align="center">
                        <template slot-scope="scope">
                            <el-tooltip :content="hasContent(scope.row.field) ? 'Configurado' : 'Pendiente'" placement="top">
                                <i :class="hasContent(scope.row.field) ? 'el-icon-success text-success' : 'el-icon-warning text-warning'" 
                                   style="font-size: 1.2rem;"></i>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acción" width="150" align="right">
                        <template slot-scope="scope">
                            <el-button 
                                type="primary" 
                                size="mini" 
                                icon="el-icon-edit" 
                                plain
                                @click="openModal(scope.row.modal)">
                                Editar
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            
            <div class="card-footer bg-light text-right py-3">
                <p class="small text-muted float-left mt-2">
                    <i class="el-icon-info"></i> Los cambios se verán reflejados en el pie de página de tu tienda.
                </p>
                <el-button type="success" icon="el-icon-check" @click="submit" :loading="loading_submit">
                    Guardar Todo
                </el-button>
            </div>
        </div>

        <el-dialog title="Términos y condiciones" :visible.sync="showDialogTermsCondition" append-to-body top="7vh" width="70%">
            <vue-ckeditor type="classic" v-model="form.termino_conditions" :editors="editors" />
            <div slot="footer" class="dialog-footer">
                <el-button @click="showDialogTermsCondition = false">Cerrar</el-button>
                <el-button type="primary" @click="submit" :loading="loading_submit">Guardar Cambios</el-button>
            </div>
        </el-dialog>

        <el-dialog title="Políticas de Privacidad" :visible.sync="showDialogPoliticaPrivacy" append-to-body top="7vh" width="70%">
            <vue-ckeditor type="classic" v-model="form.politica_privacy" :editors="editors" />
            <div slot="footer" class="dialog-footer">
                <el-button @click="showDialogPoliticaPrivacy = false">Cerrar</el-button>
                <el-button type="primary" @click="submit" :loading="loading_submit">Guardar Cambios</el-button>
            </div>
        </el-dialog>

        <el-dialog title="Cambios y Devoluciones" :visible.sync="showDialogCambiosdevolucion" append-to-body top="7vh" width="70%">
            <vue-ckeditor type="classic" v-model="form.cambios_devolucion" :editors="editors" />
            <div slot="footer" class="dialog-footer">
                <el-button @click="showDialogCambiosdevolucion = false">Cerrar</el-button>
                <el-button type="primary" @click="submit" :loading="loading_submit">Guardar Cambios</el-button>
            </div>
        </el-dialog>

        <el-dialog title="Políticas de Envío" :visible.sync="showDialogPoliticaEnvio" append-to-body top="7vh" width="70%">
            <vue-ckeditor type="classic" v-model="form.politica_envio" :editors="editors" />
            <div slot="footer" class="dialog-footer">
                <el-button @click="showDialogPoliticaEnvio = false">Cerrar</el-button>
                <el-button type="primary" @click="submit" :loading="loading_submit">Guardar Cambios</el-button>
            </div>
        </el-dialog>
    </div>
</template>

<script>
import ClassicEditor from '@ckeditor/ckeditor5-build-classic'
import VueCkeditor from 'vue-ckeditor5'

export default {
    components: { 'vue-ckeditor': VueCkeditor.component },
    data() {
        return {
            loading_submit: false,
            showDialogTermsCondition: false,
            showDialogPoliticaPrivacy: false,
            showDialogCambiosdevolucion: false,
            showDialogPoliticaEnvio: false,
            resource: "ecommerce",
            form: {
                id: null,
                termino_conditions: "",
                politica_privacy: "",
                cambios_devolucion: "",
                politica_envio: ""
            },
            editors: { classic: ClassicEditor },
            // Nueva data para la tabla
            policyList: [
                { title: 'Términos y Condiciones', field: 'termino_conditions', icon: 'el-icon-document-checked', modal: 'showDialogTermsCondition', description: 'Reglas de uso de la plataforma' },
                { title: 'Políticas de Privacidad', field: 'politica_privacy', icon: 'el-icon-lock', modal: 'showDialogPoliticaPrivacy', description: 'Tratamiento de datos personales' },
                { title: 'Cambios y Devoluciones', field: 'cambios_devolucion', icon: 'el-icon-refresh', modal: 'showDialogCambiosdevolucion', description: 'Garantías y retornos de productos' },
                { title: 'Políticas de Envío', field: 'politica_envio', icon: 'el-icon-truck', modal: 'showDialogPoliticaEnvio', description: 'Tiempos y costos de despacho' }
            ]
        };
    },
    async created() {
        this.loadData();
    },
    methods: {
        async loadData() {
            await this.$http.get(`/${this.resource}/record`).then(response => {
                if (response.data && response.data.data) {
                    this.form = Object.assign({}, this.form, response.data.data);
                }
            });
        },
        hasContent(field) {
            return this.form[field] && this.form[field].length > 10;
        },
        openModal(modalName) {
            this[modalName] = true;
        },
        submit() {
            this.loading_submit = true;
            this.$http.post(`/${this.resource}/configuration_terms`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => console.log(error))
                .finally(() => this.loading_submit = false);
        }
    }
};
</script>