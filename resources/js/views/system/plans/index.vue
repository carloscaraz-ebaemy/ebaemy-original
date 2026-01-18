<template>
    <div>
        <header class="page-header">
            <h2><a href="/dashboard">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Planes</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button type="button" class="btn btn-custom btn-sm  mt-2 me-2" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>
            </div>
        </header>
        
                <div class="pricing-table row no-gutters mt-3 mb-3 d-flex justify-content-center">
					
                    <template v-for="(row, index) in records">

                        <div  class="col-lg-3 col-sm-6 text-center pb-4" style="padding:10px;" :key="index">
							<div class="plan most-popular h-100 d-flex flex-column">
                                <div class="d-flex align-items-center">
                                    <h3 class="text-start fw-semibold">{{row.name}}</h3>
                                    <span v-if="row.locked" class="tag-popular d-flex align-items-center ms-2">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-bolt"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 3l0 7l6 0l-8 11l0 -7l-6 0l8 -11" /></svg>
                                        Default
                                    </span>
                                </div>
                                <div>
                                    <span class="d-flex justify-content-start fw-bold mt-3">
                                        S/
                                        <h1 class="m-0" style="font-size: 3rem !important;">{{row.pricing}}</h1>
                                    </span>
                                    <p class="text-start mb-4 mt-0 text-muted">Servicio facturado mensualmente</p>
                                </div> 
                                <div class="price-content pt-3">
                                    <p class="text-start mb-2"><strong>Características:</strong></p>

                                    <p class="key-features" v-if="row.limit_users === 0">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        Usuarios ilimitados
                                    </p>
                                    <p class="key-features" v-else>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        {{row.limit_users}} usuarios
                                    </p>

                                    <p class="key-features" v-if="row.limit_documents === 0">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        Comprobantes ilimitados
                                    </p>
                                    <p class="key-features" v-else>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        {{row.limit_documents}} comprobantes
                                    </p>

                                    <p class="key-features" v-if="row.establishments_unlimited">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        Sucursales ilimitados
                                    </p>
                                    <p class="key-features" v-else>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        {{row.establishments_limit}} sucursales
                                    </p>

                                    <p class="key-features" v-if="row.sales_unlimited">
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        Ventas ilimitadas
                                    </p>
                                    <p class="key-features" v-else>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                                        Total ventas mensuales S/{{row.sales_limit}}
                                    </p>

                                </div>
								<!-- <ul>
 
                                    <li v-if="row.limit_users === 0"><strong>Usuarios</strong> ilimitados</li>
                                    <li v-else><strong>{{row.limit_users}}</strong> usuarios</li>

                                    <li v-if="row.limit_documents === 0"><strong>Comprobantes</strong> ilimitados</li>                                
                                    <li v-else><strong>{{row.limit_documents}}</strong> comprobantes</li>
                                
                                    <li v-if="row.establishments_unlimited"><strong>Sucursales</strong> ilimitados</li>                                
                                    <li v-else><strong>{{row.establishments_limit}}</strong> sucursales</li>

                                    <li v-if="row.sales_unlimited"><strong>Ventas </strong> ilimitadas</li>                                
                                    <li v-else>Total ventas mensuales <strong> S/{{row.sales_limit}}</strong></li>

                                    <template v-for="(plan_document, i) in getDescriptions(row.plan_documents)">
                                        <li :key="i" v-if="plan_document">{{plan_document.description}}</li>
                                    </template>                                   
								</ul>                                 -->
                                <div v-if="!row.locked" class="col-12 d-flex justify-content-center mt-auto">
                                    <button type="button" class="btn waves-effect waves-light btn-xs btn-danger col-6 me-1" style="margin-left:6px;" @click.prevent="clickDelete(row.id)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                        Eliminar plan
                                    </button>
                                    <button type="button" class="btn waves-effect waves-light btn-xs btn-primary col-6 ms-1"  @click.prevent="clickCreate(row.id)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                        Editar plan
                                    </button>
                                </div>
							</div>
						</div>
                        
                    </template>
						
						  
                </div>
            
        <system-plans-form :showDialog.sync="showDialog"
                            :plan_documents="plan_documents"
                             :recordId="recordId"></system-plans-form>
    </div>
</template>

<script>

    import PlansForm from './form.vue'
    import {deletable} from "../../../mixins/deletable" 

    export default {
        mixins: [deletable],
        components: {PlansForm},
        data() {
            return {
                showDialog: false,
                resource: 'plans',
                recordId: null,
                records: [],                
                plan_documents: [] ,
                aux:[]
            }
        },
        created() {            
                
            this.$eventHub.$on('reloadData', () => {
                this.getData()
            })
            this.getData()
            this.getPlanDocuments()
        },
        methods: {

            getPlanDocuments(){
                this.$http.get(`/${this.resource}/tables`).then(response => {
                            this.plan_documents = response.data.plan_documents 
                        })
            },
            getData() {
                this.$http.get(`/${this.resource}/records`)
                    .then(response => {
                        this.records = response.data.data                         
                    })
            },
            getDescriptions(plan_documents){

                let descriptions = []; 
                Object.values(plan_documents).forEach((itm, i) => {                    
                    descriptions.push(this.plan_documents[itm-1]) 
                });
                return descriptions
            },
            clickCreate(recordId = null) {
                this.recordId = recordId
                this.showDialog = true
            }, 
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            }
        }
    }
</script>
