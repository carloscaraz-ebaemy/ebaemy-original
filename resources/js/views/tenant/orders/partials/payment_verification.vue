<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        title="Verificar los cobros"
        top="8vh"
        width="620px"
        @close="cerrar"
        @open="cargar"
    >
        <div v-if="error" class="pv-alert">{{ error }}</div>

        <p class="pv-intro">
            Registrar un cobro no es comprobarlo. Aquí se confirma que el dinero
            entró de verdad, o se rechaza diciendo por qué.
        </p>

        <div v-loading="cargando" class="pv-lista">
            <p v-if="!cargando && !pagos.length" class="pv-vacio">
                Este pedido todavía no tiene cobros registrados.
            </p>

            <div v-for="p in pagos" :key="p.id" class="pv-item">
                <div class="pv-item-l">
                    <div class="pv-linea">
                        <span class="pv-monto">S/ {{ money(p.payment) }}</span>
                        <span v-if="p.payment_method_type_description" class="pv-metodo">{{
                            p.payment_method_type_description
                        }}</span>
                        <span
                            v-if="p.verification_label"
                            class="pv-tag"
                            :class="'is-' + p.verification_status"
                            >{{ p.verification_label }}</span
                        >
                    </div>
                    <div class="pv-sub">
                        {{ p.date_of_payment }}
                        <template v-if="p.reference"> · {{ p.reference }}</template>
                        <template v-if="p.verified_at"> · revisado {{ p.verified_at }}</template>
                    </div>
                    <p v-if="p.rejection_reason" class="pv-motivo">
                        Rechazado: {{ p.rejection_reason }}
                    </p>
                </div>

                <div class="pv-item-r">
                    <!-- Un cobro rechazado no se «des-rechaza»: si el dinero si
                         entro, se registra otra vez y quedan los dos. Por eso
                         ahi no se ofrece nada. -->
                    <template v-if="p.verification_status !== 'rechazado'">
                        <el-button
                            v-if="p.verification_status !== 'verificado'"
                            size="mini"
                            type="success"
                            plain
                            :loading="ocupado === p.id"
                            @click="verificar(p)"
                            >Verificar</el-button
                        >
                        <el-button
                            size="mini"
                            type="danger"
                            plain
                            :loading="ocupado === p.id"
                            @click="rechazar(p)"
                            >Rechazar</el-button
                        >
                    </template>
                </div>
            </div>
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Verificar o rechazar los cobros de un pedido.
 *
 * Es la pantalla que faltaba para que la verificacion sirviera de algo: el
 * modelo de datos y las reglas ya existian, pero no habia manera de pulsar.
 *
 * NO registra cobros ni edita importes — para eso esta el panel de pagos, que
 * es otro. Aqui solo se comprueba, que es justo el hecho que hasta ahora estaba
 * confundido con el registro.
 *
 * Los cobros se leen del MISMO endpoint que usa el panel de pagos
 * (`{resource}/records/{id}`) y las acciones van a `/cobros/{tipo}/...`, que
 * sirve a las dos tablas de dinero. Aqui no se decide nada: si un cobro se
 * puede verificar, si hace falta motivo y si el rechazo baja el saldo lo
 * resuelve `PaymentVerification` en el servidor.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        /** `order` o `shipment`: las dos tablas donde entra dinero. */
        tipo: { type: String, default: "order" },
        /** Id del pedido o del envío, según el tipo. */
        recordId: { type: Number, default: null },
    },
    data() {
        return { pagos: [], cargando: false, ocupado: null, error: null };
    },
    computed: {
        recurso() {
            return this.tipo === "shipment" ? "shipment_payments" : "order_payments";
        },
    },
    methods: {
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        money(v) {
            return Number(v || 0).toFixed(2);
        },
        cargar() {
            if (!this.recordId) return;

            this.cargando = true;
            this.error = null;
            this.pagos = [];

            this.$http
                .get(`/${this.recurso}/records/${this.recordId}`)
                .then(r => {
                    this.pagos = (r.data && r.data.data) || [];
                })
                .catch(() => {
                    this.error = "No se pudieron cargar los cobros.";
                })
                .then(() => {
                    this.cargando = false;
                });
        },
        verificar(p) {
            this.enviar(p, "verificar", {});
        },
        rechazar(p) {
            this.$prompt(
                "¿Por qué se rechaza este cobro de S/ " +
                    this.money(p.payment) +
                    "? El motivo queda guardado, y el pedido vuelve a contar el saldo como pendiente.",
                "Rechazar cobro",
                {
                    confirmButtonText: "Rechazar",
                    cancelButtonText: "Cancelar",
                    inputPlaceholder: "Ej.: el voucher no coincide con el importe",
                    // El servidor tambien lo exige; esto solo evita el viaje.
                    inputValidator: v =>
                        (v || "").trim().length > 3 || "Escribe el motivo.",
                }
            )
                .then(({ value }) => this.enviar(p, "rechazar", { motivo: value }))
                .catch(() => {});
        },
        enviar(p, accion, datos) {
            this.ocupado = p.id;
            this.error = null;

            this.$http
                .post(`/cobros/${this.tipo}/${p.id}/${accion}`, datos)
                .then(r => {
                    const d = r.data || {};

                    // El servidor responde 200 con `success:false` cuando es una
                    // regla de negocio y no un error: no es lo mismo.
                    if (d.success === false) {
                        this.error = d.message;
                        return;
                    }

                    this.$message.success(d.message);
                    this.cargar();
                    // El saldo del pedido cambia si se rechaza: la fila tiene
                    // que enterarse.
                    this.$emit("changed");
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    const porCampo = d.errors
                        ? Object.values(d.errors)
                              .map(x => x[0])
                              .join(" ")
                        : null;
                    this.error = porCampo || d.message || "No se pudo completar la acción.";
                })
                .then(() => {
                    this.ocupado = null;
                });
        },
    },
};
</script>

<style scoped>
.pv-intro {
    font-size: 12.5px;
    color: #64748b;
    margin: 0 0 14px;
    line-height: 1.45;
}
.pv-lista {
    min-height: 60px;
}
.pv-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f7;
}
.pv-item:last-child {
    border-bottom: 0;
}
.pv-item-l {
    min-width: 0;
}
.pv-item-r {
    display: flex;
    gap: 6px;
    flex: 0 0 auto;
}
.pv-linea {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.pv-monto {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
}
.pv-metodo {
    color: #475569;
    font-size: 12.5px;
}
.pv-tag {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 3px;
    white-space: nowrap;
}
.pv-tag.is-pendiente { background: #fef3c7; color: #92400e; }
.pv-tag.is-verificado { background: #dcfce7; color: #166534; }
.pv-tag.is-rechazado { background: #fee2e2; color: #b91c1c; }
.pv-sub {
    color: #94a3b8;
    font-size: 11.5px;
    margin-top: 2px;
}
.pv-motivo {
    margin: 4px 0 0;
    color: #b91c1c;
    font-size: 12px;
}
.pv-vacio {
    color: #64748b;
    font-size: 12.5px;
    margin: 8px 0;
}
.pv-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 4px;
    padding: 9px 12px;
    margin-bottom: 12px;
    font-size: 13px;
}
</style>
