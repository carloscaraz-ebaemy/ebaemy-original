<template>
    <el-dialog
        :title="dialogTitle"
        :visible="visible"
        :close-on-click-modal="false"
        :width="dialogWidth"
        top="4vh"
        @close="close"
    >
        <div v-loading="loading" class="ord-ship">
            <!-- Cabecera: de qué pedido estamos hablando. Sin esto el modal
                 flota y el operador pierde de vista a quién le está armando
                 el envío. -->
            <div v-if="order" class="ord-ship-head">
                <div>
                    <span class="ord-ship-order">Pedido #{{ order.code }}</span>
                    <span v-if="order.channel" class="ord-ship-channel">{{ order.channel }}</span>
                </div>
                <div class="ord-ship-total">S/ {{ money(order.total) }}</div>
            </div>

            <div v-if="shipment" class="ord-ship-current">
                <span class="ord-ship-code">{{ shipment.code }}</span>
                <span
                    class="ord-ship-pill"
                    :style="pillStyle(shipment.delivery_meta)"
                >{{ shipment.delivery_short }}</span>
                <span class="ord-ship-status">{{ shipment.status_label }}</span>
                <span
                    v-if="shipment.aging_meta"
                    class="ord-ship-aging"
                    :style="{ color: shipment.aging_meta.color, background: shipment.aging_meta.bg }"
                >{{ shipment.aging_meta.label }}</span>
                <span v-if="shipment.locked_by_batch" class="ord-ship-lock">
                    <i class="fas fa-lock"></i> En lote impreso
                </span>
            </div>

            <!-- Modalidad. Es la primera decisión porque de ella dependen los
                 campos siguientes: no es lo mismo un rótulo de agencia que una
                 dirección con motorizado. -->
            <div class="ord-ship-section">
                <label class="ord-ship-label">Modalidad de entrega</label>
                <div class="ord-ship-modes">
                    <button
                        v-for="(label, value) in catalogs.delivery_types"
                        :key="value"
                        type="button"
                        class="ord-ship-mode"
                        :class="{ active: form.delivery_type === value }"
                        :disabled="modalityLocked"
                        @click="form.delivery_type = value"
                    >{{ label }}</button>
                </div>
                <small v-if="modalityLocked" class="ord-ship-hint">
                    El envío ya está en un lote impreso: retíralo del lote para cambiar su modalidad.
                </small>
            </div>

            <!-- Destinatario: común a las tres modalidades. -->
            <div class="ord-ship-section">
                <label class="ord-ship-label">Destinatario</label>
                <div class="ord-ship-grid">
                    <el-input v-model="form.full_name" placeholder="Nombre o razón social" />
                    <el-select v-model="form.document_type" placeholder="Tipo doc.">
                        <el-option
                            v-for="(label, value) in catalogs.doc_types"
                            :key="value"
                            :label="label"
                            :value="value"
                        />
                    </el-select>
                    <el-input v-model="form.dni" placeholder="DNI / RUC" />
                    <el-input v-model="form.phone" placeholder="Teléfono" />
                </div>
            </div>

            <!-- Quién recoge: la agencia no entrega un paquete a un RUC, pide
                 el DNI de una persona natural. -->
            <div v-if="isCompany" class="ord-ship-section">
                <label class="ord-ship-label">Quién recoge el paquete</label>
                <div class="ord-ship-grid">
                    <el-input v-model="form.pickup_person_name" placeholder="Nombre de quien recoge" />
                    <el-input v-model="form.pickup_person_dni" placeholder="DNI" />
                    <el-input v-model="form.pickup_person_phone" placeholder="Teléfono" />
                </div>
            </div>

            <!-- PROVINCIA: agencia + ubigeo. -->
            <div v-if="isAgencia" class="ord-ship-section">
                <label class="ord-ship-label">Envío a provincia</label>
                <div class="ord-ship-grid">
                    <el-select v-model="form.shipping_agency" filterable allow-create placeholder="Agencia">
                        <el-option v-for="a in catalogs.agencies" :key="a" :label="a" :value="a" />
                    </el-select>
                    <el-select v-model="form.department_id" placeholder="Departamento" @change="onDepartment">
                        <el-option
                            v-for="d in catalogs.departments"
                            :key="d.id"
                            :label="d.description"
                            :value="d.id"
                        />
                    </el-select>
                    <el-select v-model="form.province_id" placeholder="Provincia" @change="onProvince">
                        <el-option v-for="p in provinces" :key="p.id" :label="p.description" :value="p.id" />
                    </el-select>
                    <el-select v-model="form.district_id" placeholder="Distrito" @change="onDistrict">
                        <el-option v-for="d in districts" :key="d.id" :label="d.description" :value="d.id" />
                    </el-select>
                </div>
                <el-input
                    v-model="form.reference"
                    class="mt-2"
                    placeholder="Oficina de recojo o referencia (opcional)"
                />
            </div>

            <!-- LIMA: dirección + motorizado. -->
            <div v-if="isDomicilio" class="ord-ship-section">
                <label class="ord-ship-label">Entrega en Lima / Callao</label>
                <el-input v-model="form.shipping_destination" placeholder="Dirección de entrega" />
                <el-input v-model="form.reference" class="mt-2" placeholder="Referencia del domicilio" />
                <div class="ord-ship-grid mt-2">
                    <el-input v-model="form.courier_name" placeholder="Motorizado" />
                    <el-input v-model="form.courier_phone" placeholder="Teléfono del motorizado" />
                    <el-input v-model="form.delivery_price" placeholder="Precio delivery (S/)" />
                </div>
            </div>

            <!-- RECOJO EN TIENDA: sin dirección, sin agencia, sin rótulo. -->
            <div v-if="isTienda" class="ord-ship-section">
                <label class="ord-ship-label">Recojo en tienda</label>
                <div class="ord-ship-grid">
                    <el-input v-model="form.pickup_person_name" placeholder="Persona autorizada a recoger" />
                    <el-input v-model="form.pickup_person_dni" placeholder="DNI" />
                    <el-input v-model="form.pickup_person_phone" placeholder="Teléfono" />
                </div>
            </div>

            <!-- Paquete. -->
            <div class="ord-ship-section">
                <label class="ord-ship-label">Paquete</label>
                <el-input
                    v-model="form.package_content"
                    type="textarea"
                    :rows="2"
                    placeholder="Contenido del paquete"
                />
                <div class="ord-ship-grid mt-2">
                    <el-input v-model="form.package_count" placeholder="N° de bultos" />
                    <el-input v-model="form.weight" placeholder="Peso (kg)" />
                </div>
                <el-input v-model="form.notes" class="mt-2" placeholder="Información adicional" />
            </div>

            <div v-if="errors.length" class="ord-ship-errors">
                <div v-for="(e, i) in errors" :key="i">{{ e }}</div>
            </div>
        </div>

        <span slot="footer">
            <el-button @click="close">Cerrar</el-button>
            <el-button type="primary" :loading="saving" @click="save">
                {{ exists ? "Guardar cambios" : "Configurar envío" }}
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Envío del pedido — pestaña logística de Gestión de Pedidos.
 *
 * Es el reemplazo del alta suelta de /registro-envio: aquí el envío SIEMPRE
 * nace colgado de un pedido. El backend (ShipmentController@orderShipmentStore)
 * usa exactamente la misma validación por modalidad que el panel clásico, así
 * que un envío creado desde aquí es indistinguible de uno creado allá salvo por
 * tener `order_id`.
 *
 * No reimplementa reglas de negocio: etiquetas, semáforo de antigüedad y
 * bloqueos vienen resueltos desde PHP.
 */
export default {
    props: {
        visible: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
    },

    data() {
        return {
            loading: false,
            saving: false,
            viewport: typeof window !== "undefined" ? window.innerWidth : 1200,
            exists: false,
            order: null,
            shipment: null,
            errors: [],
            provinces: [],
            districts: [],
            catalogs: {
                delivery_types: {},
                statuses: {},
                agencies: [],
                doc_types: {},
                departments: [],
            },
            form: this.emptyForm(),
        };
    },

    computed: {
        dialogTitle() {
            return this.exists ? "Envío del pedido" : "Configurar envío";
        },
        /**
         * El ancho fijo del modal dejaba el formulario cortado en el móvil, que
         * es donde el encargado lo usa mientras arma los paquetes.
         */
        dialogWidth() {
            return this.viewport < 768 ? "96%" : "720px";
        },
        isDomicilio() {
            return this.form.delivery_type === "domicilio";
        },
        isAgencia() {
            return this.form.delivery_type === "agencia";
        },
        isTienda() {
            return this.form.delivery_type === "tienda";
        },
        // Un RUC son 11 dígitos. Misma regla que ShippingRequest::documentIsRuc.
        isCompany() {
            if (this.isTienda) return false;
            if ((this.form.document_type || "").toLowerCase() === "ruc") return true;
            return String(this.form.dni || "").replace(/\D+/g, "").length === 11;
        },
        modalityLocked() {
            return !!(this.shipment && this.shipment.locked_by_batch);
        },
    },

    watch: {
        // El watcher solo no basta: al abrir el modal por segunda vez con el
        // mismo pedido no dispararía. Por eso `open()` también se llama al
        // hacerse visible.
        visible(value) {
            if (value) this.open();
        },
    },

    mounted() {
        this.onResize = () => { this.viewport = window.innerWidth; };
        window.addEventListener("resize", this.onResize);
    },

    beforeDestroy() {
        window.removeEventListener("resize", this.onResize);
    },

    methods: {
        emptyForm() {
            return {
                delivery_type: "agencia",
                full_name: "",
                document_type: "dni",
                dni: "",
                phone: "",
                pickup_person_name: "",
                pickup_person_dni: "",
                pickup_person_phone: "",
                shipping_destination: "",
                reference: "",
                destination_city: "",
                department_id: "",
                province_id: "",
                district_id: "",
                shipping_agency: "",
                courier_name: "",
                courier_phone: "",
                delivery_price: "",
                package_content: "",
                package_count: 1,
                weight: "",
                notes: "",
            };
        },

        async open() {
            if (!this.orderId) return;

            this.loading = true;
            this.errors = [];

            try {
                const { data } = await this.$http.get(`/orders/${this.orderId}/envio`);

                this.order = data.order;
                this.exists = data.exists;
                this.shipment = data.shipment;
                this.catalogs = data.catalogs;

                // Envío existente → se edita. Todavía no existe → se arranca con
                // lo que el pedido ya sabe del cliente, para no volver a pedirlo.
                const source = data.exists ? data.shipment : data.prefill;
                this.form = Object.assign(this.emptyForm(), this.pick(source));

                // El ubigeo guardado no se hidrata solo: hay que traer las
                // listas dependientes explícitamente o los selectores salen
                // vacíos aunque el valor esté puesto.
                if (this.form.department_id) await this.loadProvinces(this.form.department_id);
                if (this.form.province_id) await this.loadDistricts(this.form.province_id);
            } catch (e) {
                this.$message.error("No se pudo cargar el envío del pedido.");
            } finally {
                this.loading = false;
            }
        },

        /** Solo los campos del formulario: el payload trae mucho más. */
        pick(source) {
            if (!source) return {};
            const out = {};
            Object.keys(this.emptyForm()).forEach(key => {
                if (source[key] !== undefined && source[key] !== null) out[key] = source[key];
            });
            return out;
        },

        async onDepartment(id) {
            this.form.province_id = "";
            this.form.district_id = "";
            this.districts = [];
            await this.loadProvinces(id);
        },

        async onProvince(id) {
            this.form.district_id = "";
            await this.loadDistricts(id);
        },

        onDistrict(id) {
            const found = this.districts.find(d => d.id === id);
            if (found) this.form.destination_city = found.description;
        },

        async loadProvinces(departmentId) {
            if (!departmentId) return;
            const { data } = await this.$http.get(`/envio/ubigeo/provincias/${departmentId}`);
            this.provinces = data;
        },

        async loadDistricts(provinceId) {
            if (!provinceId) return;
            const { data } = await this.$http.get(`/envio/ubigeo/distritos/${provinceId}`);
            this.districts = data;
        },

        async save() {
            this.saving = true;
            this.errors = [];

            try {
                const { data } = await this.$http.post(`/orders/${this.orderId}/envio`, this.form);

                this.$message.success(data.message);
                this.exists = true;
                this.shipment = data.shipment;
                this.$emit("saved", data.shipment);
                this.close();
            } catch (e) {
                // 422: se muestran los mensajes del backend tal cual — son los
                // que explican qué falta según la modalidad elegida.
                const errors = e.response && e.response.data && e.response.data.errors;
                this.errors = errors
                    ? Object.values(errors).flat()
                    : ["No se pudo guardar el envío."];
            } finally {
                this.saving = false;
            }
        },

        close() {
            this.$emit("update:visible", false);
            this.$emit("close");
        },

        pillStyle(meta) {
            if (!meta) return {};
            return { color: meta.color, background: meta.bg, borderColor: meta.line };
        },

        money(value) {
            return Number(value || 0).toFixed(2);
        },
    },
};
</script>

<style scoped>
.ord-ship-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 10px;
    margin-bottom: 14px;
    border-bottom: 1px solid #e5e7eb;
}
.ord-ship-order {
    font-weight: 700;
    font-size: 15px;
}
.ord-ship-channel {
    margin-left: 8px;
    font-size: 12px;
    color: #6b7280;
}
.ord-ship-total {
    font-weight: 700;
}
.ord-ship-current {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 14px;
}
.ord-ship-code {
    font-family: monospace;
    font-weight: 700;
}
.ord-ship-pill,
.ord-ship-aging,
.ord-ship-lock {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    border: 1px solid transparent;
}
.ord-ship-status {
    font-size: 12px;
    color: #374151;
}
.ord-ship-lock {
    background: #fef3c7;
    color: #92400e;
}
.ord-ship-section {
    margin-bottom: 16px;
}
.ord-ship-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 6px;
}
.ord-ship-modes {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.ord-ship-mode {
    flex: 1 1 160px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
}
.ord-ship-mode.active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 600;
}
.ord-ship-mode:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.ord-ship-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
}
.ord-ship-hint {
    display: block;
    margin-top: 6px;
    color: #92400e;
}
.ord-ship-errors {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 13px;
}
.mt-2 {
    margin-top: 8px;
}

/* Móvil: el modal ocupa la pantalla y los campos van a una columna. */
@media (max-width: 640px) {
    .ord-ship-grid {
        grid-template-columns: 1fr;
    }
    .ord-ship-mode {
        flex: 1 1 100%;
    }
}
</style>
