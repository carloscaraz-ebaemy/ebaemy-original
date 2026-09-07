<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        title="Con qué se factura este pedido"
        top="8vh"
        width="460px"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="error" class="bt-alert">{{ error }}</div>

        <p class="bt-intro">
            Lo eligió el cliente al comprar. Si sus datos cambiaron —dejó un RUC,
            o no dejó documento— corrígelo aquí antes de emitir.
        </p>

        <div class="bt-field">
            <label>Documento a emitir</label>
            <el-radio-group v-model="tipo" size="small">
                <el-radio-button
                    v-for="t in tipos"
                    :key="t.value"
                    :label="t.value"
                    >{{ t.label }}</el-radio-button
                >
            </el-radio-group>
            <small v-if="motivoActual" class="bt-hint">{{ motivoActual }}</small>
        </div>

        <div class="bt-field">
            <label>
                {{ tipo === "01" ? "RUC" : "DNI / C.E." }}
                <span v-if="tipo === '01'" class="bt-req">*</span>
            </label>
            <el-input
                v-model="numero"
                :placeholder="tipo === '01' ? '11 dígitos' : '8 dígitos'"
                maxlength="12"
            ></el-input>
        </div>

        <div class="bt-field">
            <label>
                {{ tipo === "01" ? "Razón social" : "Nombre del cliente" }}
                <span v-if="tipo === '01'" class="bt-req">*</span>
            </label>
            <el-input v-model="nombre" maxlength="255"></el-input>
        </div>

        <!-- Lo que sigue faltando, dicho antes de intentar emitir y no despues
             de que SUNAT lo rechace. -->
        <div v-if="faltan.length" class="bt-missing">
            Falta {{ faltan.join(" y ") }}.
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <el-button type="primary" :loading="guardando" @click="guardar">
                Guardar
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Corregir con qué documento se factura un pedido.
 *
 * Hasta ahora esto lo decidia el COMPRADOR en el checkout y no habia forma de
 * cambiarlo: si marcaba «boleta» y luego dejaba un RUC, el operador solo podia
 * emitir mal y anular con nota de credito.
 *
 * Esta pantalla NO emite nada. Guarda la eleccion y los datos tributarios; el
 * servidor decide si con eso ya se puede emitir y devuelve la propuesta
 * recalculada. Las reglas de SUNAT no se repiten aqui —los avisos que ve el
 * operador vienen resueltos de `BillingDocumentResolver`— porque tenerlas en
 * dos idiomas acaba con la pantalla y el servidor diciendo cosas distintas.
 *
 * Lo unico que este componente decide por su cuenta es que asterisco pintar,
 * que es presentacion pura.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
        // El bloque `billing` de la fila: tipo propuesto, motivo y lo que falta.
        billing: { type: Object, default: null },
    },
    data() {
        return {
            tipos: [
                { value: "80", label: "Nota de venta" },
                { value: "03", label: "Boleta" },
                { value: "01", label: "Factura" },
            ],
            tipo: "03",
            numero: "",
            nombre: "",
            faltan: [],
            guardando: false,
            error: null,
        };
    },
    computed: {
        /** Por qué el sistema propone lo que propone. */
        motivoActual() {
            if (!this.billing) return null;

            return this.tipo === this.billing.tipo ? this.billing.motivo : null;
        },
    },
    methods: {
        abrir() {
            const b = this.billing || {};
            this.tipo = b.tipo || "03";
            this.numero = b.documento || "";
            this.nombre = b.nombre_cliente || "";
            this.faltan = b.faltan || [];
            this.error = null;
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        guardar() {
            this.guardando = true;
            this.error = null;

            this.$http
                .post(`/orders/${this.orderId}/tipo-documento`, {
                    document_type_id: this.tipo,
                    numero: this.numero || null,
                    nombre: this.nombre || null,
                })
                .then(r => {
                    const d = r.data || {};

                    if (d.success === false) {
                        this.error = d.message || "No se pudo guardar.";
                        return;
                    }

                    // Se guarda igual aunque falten datos: el operador puede
                    // dejarlo a medias y volver. El mensaje lo dice.
                    this.faltan = (d.propuesta && d.propuesta.faltan) || [];
                    this.$message.success(d.message || "Guardado.");
                    this.$emit("saved");
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    const porCampo = d.errors
                        ? Object.values(d.errors)
                              .map(x => x[0])
                              .join(" ")
                        : null;
                    this.error = porCampo || d.message || "No se pudo guardar.";
                })
                .then(() => {
                    this.guardando = false;
                });
        },
    },
};
</script>

<style scoped>
.bt-intro {
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.45;
    margin: 0 0 16px;
}
.bt-field {
    margin-bottom: 14px;
}
.bt-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #334155;
}
.bt-req {
    color: #b91c1c;
}
.bt-hint {
    display: block;
    margin-top: 6px;
    font-size: 11.5px;
    color: #64748b;
    line-height: 1.4;
}
.bt-missing {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    border-radius: 4px;
    padding: 8px 11px;
    font-size: 12px;
    line-height: 1.4;
}
.bt-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 4px;
    padding: 9px 12px;
    margin-bottom: 12px;
    font-size: 13px;
}
</style>
