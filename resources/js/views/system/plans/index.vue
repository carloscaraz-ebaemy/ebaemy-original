<template>
    <div>
        <header class="page-header">
            <h2><a href="/dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:-5px"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Planes</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button type="button" class="btn btn-custom btn-sm mt-2 me-2" @click.prevent="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </header>

        <!-- Pricing Cards -->
        <div class="pricing-table row no-gutters mt-3 mb-3 d-flex justify-content-center">
            <template v-for="(row, index) in records">
                <div class="col-lg-3 col-sm-6 text-center pb-4" style="padding:10px" :key="index">
                    <div class="plan h-100 d-flex flex-column"
                         :class="{'most-popular': row.name === 'Profesional' || row.name === 'Enterprise'}">

                        <!-- Header -->
                        <div class="d-flex align-items-center">
                            <h3 class="text-start fw-semibold mb-0">{{row.name}}</h3>
                            <span v-if="row.is_default" class="tag-popular d-flex align-items-center ms-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 3l0 7l6 0l-8 11l0 -7l-6 0l8 -11"/></svg>
                                Default
                            </span>
                            <span v-if="row.name === 'Profesional'" class="badge bg-primary ms-2" style="font-size:10px">Popular</span>
                        </div>

                        <!-- Precio -->
                        <div>
                            <span class="d-flex justify-content-start fw-bold mt-3">
                                <span v-if="row.pricing > 0">S/</span>
                                <h1 class="m-0" style="font-size:3rem!important">
                                    {{row.pricing > 0 ? row.pricing : 'Gratis'}}
                                </h1>
                            </span>
                            <p class="text-start mb-3 mt-0 text-muted" style="font-size:12px">
                                {{row.pricing > 0 ? 'Facturado mensualmente' : 'Sin costo mensual'}}
                            </p>
                        </div>

                        <!-- Límites -->
                        <div class="price-content pt-2">
                            <p class="text-start mb-2" style="font-weight:700;font-size:13px;color:#374151">Límites:</p>

                            <p class="key-features">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                {{row.limit_users === 0 ? 'Usuarios ilimitados' : row.limit_users + ' usuarios'}}
                            </p>
                            <p class="key-features">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                {{row.limit_documents === 0 ? 'Comprobantes ilimitados' : row.limit_documents + ' comprobantes/mes'}}
                            </p>
                            <p class="key-features">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                {{row.establishments_unlimited ? 'Sucursales ilimitadas' : row.establishments_limit + ' sucursal' + (row.establishments_limit > 1 ? 'es' : '')}}
                            </p>
                            <p class="key-features">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                {{row.sales_unlimited ? 'Ventas ilimitadas' : 'Hasta S/' + row.sales_limit + '/mes'}}
                            </p>
                        </div>

                        <!-- Features/Módulos -->
                        <div class="pt-3" v-if="row.feature_list && row.feature_list.length > 0">
                            <p class="text-start mb-2" style="font-weight:700;font-size:13px;color:#374151">Módulos incluidos:</p>
                            <p class="key-features" v-for="(feat, fi) in row.feature_list" :key="fi">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"
                                     :class="feat.category === 'integration' ? 'text-info' : 'text-primary'">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l5 5l10 -10"/>
                                </svg>
                                <span style="font-size:12px">{{feat.name}}</span>
                            </p>
                        </div>
                        <div class="pt-3" v-else>
                            <p class="text-start text-muted" style="font-size:12px">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted me-1"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                POS e inventario básico
                            </p>
                            <p class="text-start text-muted" style="font-size:12px">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted me-1"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>
                                Facturación electrónica
                            </p>
                        </div>

                        <!-- Botones -->
                        <div v-if="!row.is_default" class="col-12 d-flex justify-content-center flex-wrap gap-1 mt-auto pt-3">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-danger col-5 me-1" @click.prevent="clickDelete(row.id)">
                                <i class="fa fa-trash"></i> Eliminar
                            </button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-primary col-5 ms-1" @click.prevent="clickCreate(row.id)">
                                <i class="fa fa-edit"></i> Editar
                            </button>
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-success col-12 mt-1" @click.prevent="clickFeatures(row)">
                                <i class="fas fa-sliders-h mr-1"></i> Features ({{row.feature_count}})
                            </button>
                        </div>
                        <div v-else class="mt-auto pt-3">
                            <button type="button" class="btn waves-effect waves-light btn-xs btn-success col-12" @click.prevent="clickFeatures(row)">
                                <i class="fas fa-sliders-h mr-1"></i> Features ({{row.feature_count}})
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <system-plans-form :showDialog.sync="showDialog"
                            :plan_documents="plan_documents"
                             :recordId="recordId"></system-plans-form>

        <plan-features-modal v-model="showFeaturesModal"
                             :planId="featuresForPlan.id"
                             :planName="featuresForPlan.name">
        </plan-features-modal>
    </div>
</template>

<script>
    import PlansForm from './form.vue'
    import PlanFeaturesModal from './partials/features-modal.vue'
    import {deletable} from "../../../mixins/deletable"

    export default {
        mixins: [deletable],
        components: {PlansForm, PlanFeaturesModal},
        data() {
            return {
                showDialog: false,
                showFeaturesModal: false,
                featuresForPlan: { id: null, name: '' },
                resource: 'plans',
                recordId: null,
                records: [],
                plan_documents: [],
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
            getPlanDocuments() {
                this.$http.get(`/${this.resource}/tables`).then(response => {
                    this.plan_documents = response.data.plan_documents
                })
            },
            getData() {
                this.$http.get(`/${this.resource}/records`).then(response => {
                    this.records = response.data.data
                })
            },
            clickCreate(recordId = null) {
                this.recordId = recordId
                this.showDialog = true
            },
            clickFeatures(row) {
                this.featuresForPlan = { id: row.id, name: row.name }
                this.showFeaturesModal = true
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            }
        }
    }
</script>
