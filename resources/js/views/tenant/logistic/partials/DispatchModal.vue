<template>
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5)">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-truck me-2"></i>
                        Despachar Orden #{{ order.id }} — Generar Guía de Remisión
                    </h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Datos de envío -->
                        <div class="col-12">
                            <h6 class="text-muted border-bottom pb-1">
                                <i class="fa fa-map-marker-alt me-1"></i> Destino
                            </h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Dirección destino <span class="text-danger">*</span></label>
                            <input v-model="form.destination_address" type="text"
                                   class="form-control form-control-sm"
                                   :placeholder="order.destination_address" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Ubigeo</label>
                            <input v-model="form.destination_ubigeo" type="text"
                                   class="form-control form-control-sm" placeholder="Ej: 150101" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Fecha de despacho <span class="text-danger">*</span></label>
                            <input v-model="form.dispatch_date" type="date"
                                   class="form-control form-control-sm" />
                        </div>

                        <!-- Transportista -->
                        <div class="col-12 mt-2">
                            <h6 class="text-muted border-bottom pb-1">
                                <i class="fa fa-id-card me-1"></i> Transportista
                            </h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Empresa / Nombre <span class="text-danger">*</span></label>
                            <input v-model="form.carrier_name" type="text"
                                   class="form-control form-control-sm" placeholder="Olva Courier, etc." />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">RUC transportista</label>
                            <input v-model="form.carrier_ruc" type="text"
                                   class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">Placa</label>
                            <input v-model="form.carrier_plate" type="text"
                                   class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Tracking / Código</label>
                            <input v-model="form.tracking_code" type="text"
                                   class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Conductor</label>
                            <input v-model="form.driver_name" type="text"
                                   class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Licencia</label>
                            <input v-model="form.driver_license" type="text"
                                   class="form-control form-control-sm" />
                        </div>

                        <!-- Comprobante -->
                        <div class="col-12 mt-2">
                            <h6 class="text-muted border-bottom pb-1">
                                <i class="fa fa-file-invoice me-1"></i> Comprobante (si no fue emitido antes)
                            </h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Tipo de comprobante</label>
                            <select v-model="form.document_type_id" class="form-select form-select-sm">
                                <option value="">Sin comprobante adicional</option>
                                <option value="03">Boleta de Venta (03)</option>
                                <option value="01">Factura (01)</option>
                            </select>
                        </div>

                        <!-- Resumen del pedido -->
                        <div class="col-12 mt-1">
                            <div class="alert alert-light border">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Destinatario:</strong> {{ order.recipient_name }}<br>
                                        <strong>Teléfono:</strong> {{ order.recipient_phone || '—' }}
                                    </div>
                                    <div class="col-6 text-end">
                                        <strong>Total pedido:</strong>
                                        <span class="fs-5 text-success ms-1">
                                            {{ order.currency_type_id }} {{ parseFloat(order.total).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="$emit('close')" :disabled="loading">
                        Cancelar
                    </button>
                    <button class="btn btn-success" @click="dispatch" :disabled="loading">
                        <span v-if="loading">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Procesando...
                        </span>
                        <span v-else>
                            <i class="fa fa-truck me-1"></i>
                            Confirmar Despacho + Generar Guía
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content ?? ''

export default {
    name: 'DispatchModal',
    emits: ['close', 'dispatched'],
    props: {
        order: { type: Object, required: true },
    },
    data() {
        return {
            loading: false,
            form: {
                carrier_name:        '',
                carrier_ruc:         '',
                carrier_plate:       '',
                driver_name:         '',
                driver_license:      '',
                origin_address:      '',
                destination_address: this.order.destination_address || '',
                destination_ubigeo:  '',
                dispatch_date:       new Date().toISOString().split('T')[0],
                tracking_code:       '',
                document_type_id:    '',
            },
        }
    },
    methods: {
        async dispatch() {
            this.loading = true
            try {
                const payload = {
                    courier_name:    this.form.carrier_name,
                    tracking_number: this.form.tracking_code,
                    notes:           this.form.driver_name
                                        ? `Conductor: ${this.form.driver_name} — Licencia: ${this.form.driver_license}`
                                        : null,
                }
                const { data } = await this.$http.post(
                    `/logistic/queue-json/${this.order.id}/dispatch`,
                    payload
                )
                this.$emit('dispatched', data.data)
            } catch (e) {
                const msg = e.response?.data?.message || 'Error al despachar el pedido'
                alert(msg)
            } finally {
                this.loading = false
            }
        },
    },
}
</script>
