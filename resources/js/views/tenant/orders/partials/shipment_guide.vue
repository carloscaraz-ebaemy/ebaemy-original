<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="'Subir guía de ' + (code || 'envío')"
        top="8vh"
        width="480px"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="error" class="sg-alert">{{ error }}</div>

        <div class="sg-field">
            <label>N.º de guía de la agencia <span class="sg-req">*</span></label>
            <el-input v-model="tracking" placeholder="El número que imprime la agencia"></el-input>
        </div>

        <div class="sg-field">
            <label>Archivo de la guía <span class="sg-req">*</span></label>
            <input
                ref="archivo"
                type="file"
                accept="image/jpeg,image/jpg,image/png,application/pdf"
                @change="elegir"
            />
            <small class="sg-hint">
                Foto o PDF, hasta 8 MB. Es el comprobante de que el paquete se
                entregó a la agencia.
            </small>
        </div>

        <div class="sg-field">
            <label>Observación</label>
            <el-input v-model="observacion" type="textarea" :rows="2"></el-input>
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <el-button type="primary" :loading="subiendo" :disabled="!sePuede" @click="subir">
                Subir guía
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Subir la guía de la agencia desde Pedidos.
 *
 * El único trabajo de esta pantalla es recoger el número, el archivo y una
 * observación. Todo lo demás lo decide el servidor y no se replica aquí: que el
 * pago esté confirmado, el tipo y tamaño del archivo, dónde se guarda, el paso
 * del envío a «enviado» y el sello de la fecha de salida.
 *
 * El archivo se manda como multipart de verdad —no por el subidor genérico de
 * Finanzas, que devuelve una ruta temporal— porque `uploadGuide` espera el
 * fichero en la petición, igual que desde el formulario de Envíos.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
        code: { type: String, default: "" },
    },
    data() {
        return {
            tracking: "",
            observacion: "",
            fichero: null,
            subiendo: false,
            error: null,
        };
    },
    computed: {
        sePuede() {
            return !!(this.tracking || "").trim() && !!this.fichero;
        },
    },
    methods: {
        abrir() {
            this.tracking = "";
            this.observacion = "";
            this.fichero = null;
            this.error = null;
            if (this.$refs.archivo) this.$refs.archivo.value = "";
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        elegir(ev) {
            this.fichero = (ev.target.files || [])[0] || null;
            this.error = null;
        },
        subir() {
            if (!this.sePuede) return;

            const datos = new FormData();
            datos.append("tracking_number", this.tracking.trim());
            datos.append("guide_file", this.fichero);
            if (this.observacion) datos.append("observation", this.observacion);

            this.subiendo = true;
            this.error = null;

            this.$http
                .post(`/orders/${this.orderId}/envio/guia`, datos, {
                    headers: { "Content-Type": "multipart/form-data" },
                })
                .then(r => {
                    const d = r.data || {};

                    // El servidor responde 200 aunque rechace: son reglas de
                    // negocio (pago sin confirmar, envío anulado), no errores.
                    if (d.success === false) {
                        this.error = d.message || "No se pudo subir la guía.";
                        return;
                    }

                    this.$message.success(d.message || "Guía subida.");
                    this.$emit("saved");
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    // 422 de validación trae los mensajes por campo.
                    const porCampo = d.errors
                        ? Object.values(d.errors).map(x => x[0]).join(" ")
                        : null;
                    this.error = porCampo || d.message || "No se pudo subir la guía.";
                })
                .then(() => {
                    this.subiendo = false;
                });
        },
    },
};
</script>

<style scoped>
.sg-field {
    margin-bottom: 14px;
}
.sg-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #334155;
}
.sg-req {
    color: #b91c1c;
}
.sg-hint {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #64748b;
    line-height: 1.4;
}
.sg-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 4px;
    padding: 9px 12px;
    margin-bottom: 12px;
    font-size: 13px;
}
</style>
