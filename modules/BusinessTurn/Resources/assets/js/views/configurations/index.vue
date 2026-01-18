<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0"> {{ title }}</h3>
            </div> -->
            <div class="card-body"> 
                <div class="row">
                    <div class="col-md-12 d-flex">
                        
                       <template  v-for="(option,ind) in filteredRecords">
                            <el-checkbox class="plan_documents d-block"  
                                v-model="option.active"  
                                :label="option.id"  
                                :key="ind"  
                                @change="submit(option.id)">
                                {{option.name}}
                            </el-checkbox>
                       </template>
                    </div>
                    <div v-show="form.is_pharmacy" class="col-md-12 mt-4">
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="border-bottom mb-0">Datos de farmacia</h4>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div :class="{'has-danger': errors.cod_digemid}"
                                     class="form-group">
                                    <label class="control-label">Código de observación DIGEMID</label>
                                    <el-input v-model="form.cod_digemid" 
                                              placeholder="Ingrese el código DIGEMID de la empresa"></el-input>
                                    <small v-if="errors.cod_digemid"
                                           class="form-control-feedback d-block"
                                           v-text="errors.cod_digemid[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-group mb-0">
                                    <el-button type="primary" 
                                               @click="saveCompanyData" 
                                               :loading="loading_submit">
                                        Guardar
                                    </el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <template v-if="showConfigFilling">
            <TapConfiguration />
        </template>
 
        </div>
    </div>
</template>

<script> 

    import TapConfiguration from  './partials/tap.vue'

    export default {
        data() {
            return {
                title: null, 
                business_turns:[],
                resource: 'bussiness_turns',
                records: [],
                loading_submit: false,
                errors: {},
                form: {
                    is_pharmacy: false,
                    cod_digemid: null,
                }
            }
        },
        components: {
            TapConfiguration
        },
        computed: {
            filteredRecords() {
                return this.records.filter(record => record.id !== 2);
            },
            showConfigFilling()
            {
                return this.records.find(record => record.id === 4).active; 
            }
        },
        async created() {
            
            this.title = 'Giros de negocio'
            this.initForm()
            await this.getRecords()
            await this.getCompanyData()
        },
        methods: {
            initForm() {
                this.errors = {}
                this.form = {
                    is_pharmacy: false,
                    cod_digemid: null,
                }
            },
            submit(id) {
                this.loading_submit = true;
                
                this.$http.post(`/${this.resource}`,{id}).then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.getRecords()
                    }
                    else {
                        this.$message.error(response.data.message);
                    }
                }).catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    }
                    else {
                        console.log(error);
                    }
                }).then(() => {
                    this.loading_submit = false;
                });
            },
            getRecords(){
                this.$http.get(`/${this.resource}/records`)
                    .then(response => { 
                        this.records = response.data
                        // Verificar si farmacia está activa
                        const pharmacyRecord = this.records.find(r => r.name === 'Farmacia')
                        if (pharmacyRecord) {
                            this.form.is_pharmacy = pharmacyRecord.active
                        }
                    }) 
            },
            async getCompanyData() {
                try {
                    const response = await this.$http.get('/companies/record')
                    if (response.data && response.data.data) {
                        this.form.cod_digemid = response.data.data.cod_digemid || null
                    }
                } catch (error) {
                    console.error('Error al cargar datos de empresa:', error)
                }
            },
            async saveCompanyData() {
                this.loading_submit = true
                
                try {
                    // Obtener el ID de la empresa actual
                    const companyResponse = await this.$http.get('/companies/record')
                    if (!companyResponse.data || !companyResponse.data.data) {
                        this.$message.error('No se pudo obtener la información de la empresa')
                        return
                    }
                    
                    const companyData = companyResponse.data.data
                    
                    // Actualizar solo el código DIGEMID
                    const response = await this.$http.post('/companies', {
                        id: companyData.id,
                        cod_digemid: this.form.cod_digemid,
                        // Enviar los demás campos requeridos
                        number: companyData.number,
                        name: companyData.name,
                        trade_name: companyData.trade_name,
                        soap_type_id: companyData.soap_type_id,
                        soap_send_id: companyData.soap_send_id,
                        soap_username: companyData.soap_username,
                        soap_password: companyData.soap_password,
                        certificate: companyData.certificate,
                        identity_document_type_id: companyData.identity_document_type_id,
                        country_id: companyData.country_id,
                        department_id: companyData.department_id,
                        province_id: companyData.province_id,
                        district_id: companyData.district_id,
                        address: companyData.address,
                        email: companyData.email,
                        telephone: companyData.telephone,
                    })
                    
                    if (response.data.success) {
                        this.$message.success('Código DIGEMID actualizado correctamente')
                        this.errors = {}
                    } else {
                        this.$message.error(response.data.message || 'Error al guardar')
                    }
                } catch (error) {
                    if (error.response && error.response.status === 422) {
                        this.errors = error.response.data.errors
                    } else {
                        this.$message.error('Error al guardar los datos')
                        console.error(error)
                    }
                } finally {
                    this.loading_submit = false
                }
            }
        }
    }
</script>
