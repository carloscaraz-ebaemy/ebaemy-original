<template>
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fa fa-map-marker-alt me-2"></i>
                        Completar datos de envío — Orden #{{ order.id }}
                    </h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                        <i class="fa fa-exclamation-triangle"></i>
                        <small>Estos datos son necesarios para generar la guía de remisión.</small>
                    </div>

                    <!-- ── Buscar destinatario ── -->
                    <div class="form-group position-relative mb-3">
                        <label class="control-label fw-semibold">Cliente</label>
                        <el-select
                            v-model="selectedCustomerId"
                            filterable
                            remote
                            style="width:100%"
                            placeholder="Escriba el nombre o número de documento del cliente"
                            :remote-method="searchRemoteCustomers"
                            :loading="loading_search"
                            @change="onCustomerSelected">
                            <el-option
                                v-for="c in customers"
                                :key="c.id"
                                :value="c.id"
                                :label="c.description">
                            </el-option>
                            <template slot="empty">
                                <p v-if="loading_search" class="el-select-dropdown__empty">Cargando...</p>
                                <p v-else class="el-select-dropdown__empty">No se encontraron resultados</p>
                                <div v-if="!loading_search"
                                     class="el-select-dropdown__item new-option"
                                     @click.stop="showPersonForm = true">
                                    <span>{{ customerSearchTerm ? `Crear cliente "${customerSearchTerm}"` : 'Crear cliente' }}</span>
                                </div>
                            </template>
                        </el-select>
                        <span class="btn-add-new" @click.prevent="showPersonForm = true" title="Agregar nuevo cliente">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 class="icon icon-tabler icons-tabler-outline icon-tabler-user-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                                <path d="M16 19h6"/>
                                <path d="M19 16v6"/>
                                <path d="M6 21v-2a4 4 0 0 1 4 -4h4"/>
                            </svg>
                        </span>
                    </div>

                    <!-- ── Destinatario ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Destinatario <span class="text-danger">*</span></label>
                        <input v-model="form.shipping_recipient" type="text" class="form-control"
                               :class="{'is-invalid': errors.shipping_recipient}"
                               placeholder="Nombre completo del destinatario">
                        <div v-if="errors.shipping_recipient" class="invalid-feedback">{{ errors.shipping_recipient[0] }}</div>
                    </div>

                    <!-- ── Teléfono ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Teléfono</label>
                        <input v-model="form.shipping_phone" type="text" class="form-control" placeholder="Ej: 987654321">
                    </div>

                    <!-- ── Dirección ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección de destino <span class="text-danger">*</span></label>
                        <input v-model="form.shipping_address" type="text" class="form-control"
                               :class="{'is-invalid': errors.shipping_address}"
                               placeholder="Av. / Jr. / Calle + número + referencia">
                        <div v-if="errors.shipping_address" class="invalid-feedback">{{ errors.shipping_address[0] }}</div>
                    </div>

                    <!-- ── Ubigeo — mismo cascader que Nuevo Cliente ── -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Ubigeo <span class="text-danger">*</span>
                        </label>
                        <el-cascader
                            v-model="form.location_id"
                            :options="locations"
                            :clearable="true"
                            filterable
                            :filter-method="customFilterMethod"
                            style="width:100%"
                            @change="onLocationChange">
                        </el-cascader>
                        <small v-if="errors.shipping_district_id" class="text-danger d-block mt-1">
                            {{ errors.shipping_district_id[0] }}
                        </small>
                    </div>

                    <!-- ── Notas ── -->
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notas adicionales</label>
                        <textarea v-model="form.shipping_notes" class="form-control" rows="2"
                                  placeholder="Indicaciones extra para el almacén o courier"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="$emit('close')" :disabled="saving">Cancelar</button>
                    <button class="btn btn-warning text-dark fw-semibold" @click="save" :disabled="saving">
                        <span v-if="saving"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
                        <span v-else><i class="fa fa-save me-1"></i> Guardar datos</span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Formulario Nuevo Cliente -->
    <person-form
        v-if="showPersonForm"
        :showDialog.sync="showPersonForm"
        type="customers"
        :external="true"
        :input_person="{ number: customerSearchTerm }"
    ></person-form>
</template>

<script>
import axios from 'axios'
import PersonForm from '../../persons/form.vue'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content ?? ''

export default {
    name: 'ShippingDataModal',
    components: { PersonForm },
    emits: ['close', 'saved'],
    props: {
        order: { type: Object, required: true },
    },

    data() {
        return {
            saving: false,
            errors: {},
            // Búsqueda cliente (patrón el-select remote)
            selectedCustomerId: null,
            customers:          [],
            loading_search:     false,
            customerSearchTerm: '',
            // Nuevo Cliente dialog
            showPersonForm: false,
            // Ubigeo cascader
            locations:    [],
            form: {
                shipping_recipient:   this.order.shipping_recipient   || '',
                shipping_phone:       this.order.shipping_phone       || '',
                shipping_address:     this.order.shipping_address     || '',
                shipping_city:        this.order.shipping_city        || '',
                shipping_district_id: this.order.shipping_district_id || '',
                shipping_notes:       this.order.shipping_notes       || '',
                location_id:          [],
            },
        }
    },

    mounted() {
        this.loadUbigeo()
        this.$eventHub.$on('reloadDataPersons', this.onNewPersonSaved)
    },

    beforeDestroy() {
        this.$eventHub.$off('reloadDataPersons', this.onNewPersonSaved)
    },

    watch: {
        showPersonForm(val) {
            if (!val) this.customerSearchTerm = ''
        },
    },

    methods: {
        // ─── Búsqueda remota de cliente ───────────────────────────────────────

        searchRemoteCustomers(input) {
            this.customerSearchTerm = input
            if (!input) { this.customers = []; return }
            this.loading_search = true
            this.$http.get('/sale-notes/search/customers', { params: { input } })
                .then(({ data }) => { this.customers = data.customers || [] })
                .catch(() => { this.customers = [] })
                .finally(() => { this.loading_search = false })
        },

        async onCustomerSelected(id) {
            if (!id) return
            try {
                const { data } = await this.$http.get(`/persons/record/${id}`)
                const p = data.data ?? data
                this.form.shipping_recipient = p.name      || ''
                this.form.shipping_phone     = p.telephone || ''
                this.form.shipping_address   = p.address   || ''
                if (p.district_id) this.preselectDistrict(p.district_id)
            } catch (e) {
                console.error('[ShippingDataModal] Error cargando cliente:', e)
            }
        },

        // ─── Nuevo Cliente guardado desde PersonForm ──────────────────────────

        async onNewPersonSaved(personId) {
            this.showPersonForm = false
            if (!personId) return
            try {
                const { data } = await this.$http.get(`/persons/record/${personId}`)
                const p = data.data ?? data
                this.form.shipping_recipient = p.name      || ''
                this.form.shipping_phone     = p.telephone || ''
                this.form.shipping_address   = p.address   || ''
                // Mostrar en el select
                this.customers          = [{ id: personId, description: p.name || '' }]
                this.selectedCustomerId = personId
                if (p.district_id) this.preselectDistrict(p.district_id)
            } catch (e) {
                console.error('[ShippingDataModal] Error cargando nuevo cliente:', e)
            }
        },

        // ─── Ubigeo ───────────────────────────────────────────────────────────

        async loadUbigeo() {
            try {
                const { data } = await this.$http.get('/persons/tables')
                this.locations = data.locations || []
                if (this.form.shipping_district_id) {
                    this.preselectDistrict(this.form.shipping_district_id)
                }
            } catch (e) {
                console.error('[ShippingDataModal] Error cargando ubigeo:', e)
            }
        },

        preselectDistrict(districtId) {
            this.form.shipping_district_id = districtId
            for (const dept of this.locations) {
                for (const prov of (dept.children || [])) {
                    const dist = (prov.children || []).find(d => d.value === districtId)
                    if (dist) {
                        this.form.location_id   = [dept.value, prov.value, districtId]
                        this.form.shipping_city = dist.label
                        return
                    }
                }
            }
        },

        onLocationChange(val) {
            if (val && val.length === 3) {
                this.form.shipping_district_id = val[2]
                for (const dept of this.locations) {
                    for (const prov of (dept.children || [])) {
                        const dist = (prov.children || []).find(d => d.value === val[2])
                        if (dist) { this.form.shipping_city = dist.label; return }
                    }
                }
            } else {
                this.form.shipping_district_id = ''
                this.form.shipping_city        = ''
            }
        },

        customFilterMethod(node, keyword) {
            return node.label.toLowerCase().includes(keyword.toLowerCase())
        },

        // ─── Guardar ──────────────────────────────────────────────────────────

        async save() {
            this.saving = true
            this.errors = {}
            try {
                const { data } = await this.$http.patch(
                    `/logistic/queue-json/${this.order.id}/update-shipping`,
                    this.form
                )
                this.$emit('saved', data.data)
            } catch (e) {
                if (e.response?.status === 422) {
                    this.errors = e.response.data.errors || {}
                } else {
                    alert('Error guardando datos de envío')
                }
            } finally {
                this.saving = false
            }
        },
    },
}
</script>
