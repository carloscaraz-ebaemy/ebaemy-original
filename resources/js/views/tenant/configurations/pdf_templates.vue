<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="#"><i class="fas fa-cogs"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Configuración</span></li>
                <li><span class="text-muted">Plantilla PDF</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm  mt-2 me-2"
                        type="button"
                        @click="addSeeder"><i class="el-icon-refresh"></i>
                    Actualizar listado
                </button>
            </div>
        </div>
        <div class="card tab-content-default row-new">
            <div class="card-body pb-5">
                <h5> <i class="fa fa-info-circle"></i>  Seleccione un sucursal para ver su plantilla</h5>
                <div class="row">
                    <div class="col-3 form-modern">
                        <label class="control-label">Sucursal</label>
                        <el-select v-model="form.establishment_id"
                                   @change="changeEstablishment()">
                            <el-option v-for="option in establishments"
                                       :key="option.id"
                                       :label="option.description"
                                       :value="option.id"></el-option>
                        </el-select>
                    </div>
                    <div class="col-3 form-modern">
                        <label class="control-label">Plantilla actual</label>
                        <el-input v-model="form.current_format"
                                  readonly></el-input>
                        <small v-if="form.current_format"
                               style="cursor:pointer">
                            <!-- <a @click="viewModalImage(form.current_format)">
                                Ver plantilla
                            </a> -->
                        </small>
                    </div>
                </div>
                <div class="row justify-content-center" v-if="form.establishment_id">
                    <div v-for="template in formatos" class="pdf-template-content my-2 m-2 mt-4 col-sm-12 col-md-6 col-lg-4 col-xl-3">
                        <el-card class="el-card-template" style="height: 99% !important;" :class="['pdf-template-border', { 'active-border': form.current_format === template.name }]"
                            :id="template.id"
                            :body-style="{ padding: '0px' }">
                            <a class="image-direccion" @click="viewImage(template)">
                                <img :src="path.origin+'/'+template.urls.invoice"
                                     class="image img-template"
                                     style="width: 100%;"></a>
                            <div style="padding: 14px; border-top: 2px solid #f7f8fa">
                                <span class="text-bold">Plantilla: </span>
                                <span class="text-center">{{ template.name }}</span>
                                <div v-if="form.establishment_id"
                                    class="bottom clearfix text-end d-flex gap-1">
                                    <el-button 
                                        v-if="template.name === 'Plantilla_personalizable'" 
                                        type="info" 
                                        size="small"
                                        class="col-2 position-relative d-flex align-items-center justify-content-center p-0"
                                        @click.stop="openColumnsDialog(template)"
                                    >
                                       <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                                    </el-button>  
                                    <el-radio
                                        v-model="form.current_format"
                                        :label="template.name"
                                        @change="changeFormat(template.name)"
                                        class="m-0"
                                        :class="[
                                            'radio-button',
                                            form.current_format === template.name ? 'active-button' : '',
                                            template.name === 'Plantilla_personalizable' ? 'col-10' : 'col-12'
                                        ]"
                                    >
                                        <span v-if="form.current_format == template.name">Plantilla activa</span>
                                        <span v-else>Activar plantilla</span>
                                    </el-radio>                                 
                                </div>
                            </div>
                            <i class="fas fa-search-plus icon-overlay" @click="viewImage(template)"></i>
                        </el-card>
                    </div>
                </div>
            </div>
        </div>
        <el-dialog
            :visible.sync="modalImage"
            width="100">
            <div class="d-flex align-items-center justify-content-start" style="margin-top: -30px !important;">
                <h4 class="text-bold">Plantilla:</h4>
                <span style="font-size: 16px;" class="text-center ms-2">{{ template.name }}</span>
            </div>
            <span>
                <div class="block">
                    <el-carousel arrow="always" :interval="10000" height="550px">
                        <div style="overflow: auto;" class="bg-light img-scroll text-center">
                                <img  
                                    :src="path.origin+'/'+template.urls.invoice"
                                    class="image zoomable-image w-90 h-auto"
                                >
                        </div>
                    </el-carousel>
                </div>
            </span>
            <span slot="footer"
                  class="dialog-footer">
                <el-button class="second-buton" @click="modalImage = false">Cerrar</el-button>
                <el-button v-if="form.establishment_id"
                           type="primary"
                           @click="changeFormat(template.name)">Activar</el-button>
            </span>
        </el-dialog>

        <!-- Modal para configurar columnas -->
        <el-dialog
            title="Configurar columnas del documento"
            :visible.sync="modalColumns"
            class="dialog-config">
            <div class="columns-config">
                <p class="mb-3">Selecciona las columnas que deseas mostrar en el documento:</p>
                <div class="row">
                    <div class="col-12 col-md-6 mb-2">
                        <div class="column-item">
                            <el-checkbox v-model="columns.codigo">Código</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.cantidad">Cantidad</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.unidad">Unidad</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.descripcion">Descripción</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.serie">Serie</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.modelo">Modelo</el-checkbox>
                        </div>                                                
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                        <div class="column-item">
                            <el-checkbox v-model="columns.marca">Marca</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.lote">Lote</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.fecha_vencimiento">Fecha de venc.</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.precio_unitario">Precio Unitario</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.descuento">Descuento</el-checkbox>
                        </div>
                        
                        <div class="column-item">
                            <el-checkbox v-model="columns.total">Total</el-checkbox>
                        </div>
                    </div>
                </div>
            </div>
            <span slot="footer" class="dialog-footer">
                <el-button @click="modalColumns = false">Cancelar</el-button>
                <el-button type="primary" @click="saveColumnsConfig">Guardar configuración</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<style scoped>
.el-carousel__item:nth-child(2n) {
background-color: #99a9bf;
}
.el-carousel__item:nth-child(2n+1) {
background-color: #d3dce6;
}
.pdf-template-border {
    border: 2px solid #bad6f1;
}
.pdf-template-border.active-border {
    border: 2px solid #409eff;
}
.radio-button {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px 20px;
    border: 1px solid #DCDFE6;
    border-radius: 4px;
    background-color: #fff;
    color: #606266;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s, color 0.3s;
}
.radio-button:hover {
    border-color: #c6e2ff;
    background-color: #ecf5ff;
    color: #409EFF;
} 
.radio-button.el-radio--checked {
    background-color: #409eff;
    color: #fff;
} 
.radio-button span {
    font-weight: normal;
}
.radio-button.el-radio--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.radio-button.active-button {
    background-color: #0074ff;
    border-color: #0074ff;
} 
.el-radio__input.is-checked + .el-radio__label {
    color: #fff !important;
}
.pdf-template-content {
    transition: transform 0.2s, opacity 0.2s;
}
.el-card-template {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s ease-in-out, opacity 0.2s ease-in-out;
}
.el-card-template::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(12, 114, 134, 0);
    transition: background-color 0.3s ease-in-out;
    pointer-events: none;
}
.pdf-template-content:hover > .el-card-template::before {
    background-color: rgba(0, 115, 255, 0.385);
}
.pdf-template-content:hover > .el-card-template {
    transform: scale(1.02);
}
.icon-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
    z-index: 10;
    cursor: pointer;
    font-size: 30px;
}
.pdf-template-content:hover .icon-overlay {
    opacity: 1;
}
.img-scroll{
    overlay: auto;
    height: 550px;
}
.img-scroll::-webkit-scrollbar {
    width: 8px;
}
.img-scroll::-webkit-scrollbar-track {
    background: #f0f9ff;
    border-radius: 8px;
}
.img-scroll::-webkit-scrollbar-thumb {
    background: #5fa7fe;
    border-radius: 8px;
}
.img-scroll::-webkit-scrollbar-thumb:hover {
    background: #0074ff;
}
.pdf-template-content:hover {
    transform: scale(1.02);
}
.image-direccion{
    cursor: pointer;
}
.columns-config {
    padding: 10px 0;
}
</style>
<style>
    @media only screen and (max-width: 650px){
        .dialog-config .el-dialog {
            width: 70% !important;
        }
    }
</style>
<script>

export default {
    props: [
        'path_image',
        'typeUser',
        'establishments'
    ],

    data() {
        return {
            zoomed: false,
            zoomPosition: { x: 50, y: 50 },
            loading_submit: false,
            resource: 'configurations',
            errors: {},
            form: {},
            formatos: [],
            path: location,
            modalImage: false,
            modalColumns: false,
            template: {
                name: '',
                urls: {}
            },
            columns: {
                codigo: true,
                descripcion: true,
                cantidad: true,
                unidad: true,
                serie: false,
                modelo: false,
                marca: false,
                lote: false,
                fecha_vencimiento: false,
                precio_unitario: true,
                descuento: true,
                total: true
            }
        }
    },
    async created() {
        await this.$http.get(`/${this.resource}/getFormats`).then(response => {
            if (response.data !== '') this.formatos = response.data.formats
            // if (response.data !== '') this.formatos = response.data.filter(r => this.image(r.formats))
        });
    },
    methods: {
        changeFormat(value) {
            this.modalImage = false
            this.form = {
                formats: value,
                establishment: this.form.establishment_id,
            }

            this.$http.post(`/${this.resource}/changeFormat`, this.form).then(response => {
                this.$message.success(response.data.message);
                location.reload()
            })

        },
        changeEstablishment() {
            var establishment = this.form.establishment_id;
            var selected = _.filter(this.establishments, {'id': establishment})[0];
            // console.log(selected.template_pdf);
            this.form.current_format = selected.template_pdf;
            
            // Cargar configuración de columnas si la plantilla actual es Plantilla_personalizable
            if (selected.template_pdf === 'Plantilla_personalizable') {
                this.loadColumnsConfigFromServer(establishment);
            }
        },
        addSeeder() {
            this.$http.all([
                this.$http.get(`/${this.resource}/templates/ticket/refresh`),
                this.$http.get(`/${this.resource}/addSeeder`)
            ]).then(
                this.$http.spread((...responses) => {
                    this.$message.success(responses[0].data.message);
                    this.$message.success(responses[1].data.message);
                    location.reload()
                })
            )

        },
        viewImage(template) {
            this.template = template
            this.modalImage = true
        },
        viewModalImage(name) {
            this.template = this.formatos.filter(template => template.name == name)[0]
            this.modalImage = true
        },
        openColumnsDialog(template) {
            this.template = template
            this.loadColumnsConfigFromServer(this.form.establishment_id)
            this.modalColumns = true
        },
        loadColumnsConfigFromServer(establishmentId) {
            if (!establishmentId) return
            
            // Cargar configuración desde el servidor
            this.$http.get(`/${this.resource}/getColumnsConfig`, {
                params: { establishment_id: establishmentId }
            }).then(response => {
                if (response.data.success && response.data.data) {
                    this.columns = response.data.data
                }
            }).catch(error => {
                console.error('Error al cargar configuración:', error)
            })
        },
        saveColumnsConfig() {
            if (!this.form.establishment_id) {
                this.$message.warning('Debe seleccionar un establecimiento primero');
                return
            }
            
            // Guardar configuración en el servidor
            this.$http.post(`/${this.resource}/saveColumnsConfig`, {
                establishment: this.form.establishment_id,
                columns: this.columns
            }).then(response => {
                this.$message.success('Configuración de columnas guardada exitosamente');
                this.modalColumns = false
            }).catch(error => {
                this.$message.error('Error al guardar la configuración');
                console.error(error)
            })
        }
    }
}
</script>
