<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        title="Crear guía en Shalom"
        top="8vh"
        width="480px"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="error" class="shg-alert">{{ error }}</div>

        <!-- Resultado: la guía ya existe en Shalom. -->
        <div v-if="hecho" class="shg-ok">
            <div class="shg-ok__t">Guía creada en Shalom</div>
            <div v-if="hecho.number" class="shg-ok__n">{{ hecho.number }} · código {{ hecho.code }}</div>
            <div class="shg-ok__s">Clave de recojo del cliente: <b>{{ hecho.clave }}</b></div>
            <el-button type="primary" icon="el-icon-printer" style="margin-top:12px" @click="imprimir">
                Imprimir rótulo de Shalom
            </el-button>
        </div>

        <template v-else>
            <p v-if="shalom && !shalom.ready" class="shg-warn">
                Antes, en <b>Configuración de envíos</b>, conecta tu cuenta de Shalom Pro y elige
                la oficina desde donde despachas.
            </p>
            <p class="shg-hint">
                La guía se crea en tu cuenta de Shalom Pro{{ office ? ' hacia la oficina ' + office : '' }}.
                Después imprimes el rótulo oficial y llevas el paquete ya registrado.
            </p>

            <div class="shg-field">
                <label>Tamaño del paquete <span class="shg-req">*</span></label>
                <el-radio-group v-model="content" size="small">
                    <el-radio-button v-for="c in tamanos" :key="c" :label="c">{{ c.replace('PAQUETE ', '') }}</el-radio-button>
                </el-radio-group>
                <small class="shg-sub">Shalom calcula el costo según el tamaño.</small>
            </div>

            <div class="shg-field">
                <label>Clave de recojo <small style="font-weight:400;color:#64748b">(4 dígitos, opcional)</small></label>
                <el-input v-model="clave" maxlength="4" :placeholder="claveFija ? 'Tu clave fija: ' + claveFija : 'Se genera sola'" style="max-width:180px"></el-input>
                <small class="shg-sub">
                    {{ claveFija ? 'Si la dejas vacía se usa tu clave fija ' + claveFija + '.' : 'El cliente la dice al recoger.' }}
                    Se la enviamos por WhatsApp cuando llegue.
                </small>
            </div>
        </template>

        <span slot="footer">
            <el-button @click="cerrar">{{ hecho ? 'Cerrar' : 'Cancelar' }}</el-button>
            <el-button v-if="!hecho" type="primary" :loading="creando" :disabled="!content || (shalom && !shalom.ready)" @click="crear">
                Crear guía
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Crear la guía de Shalom desde Pedidos (plan con instancias).
 *
 * El servidor decide todo lo demás —origen, destino, destinatario, DNI,
 * nombres— con los datos del envío; aquí solo se elige el tamaño y, si se
 * quiere, la clave de recojo.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
        shipmentId: { type: Number, default: null },
        shalom: { type: Object, default: null },
    },
    data() {
        return {
            tamanos: ["SOBRE", "PAQUETE XXS", "PAQUETE XS", "PAQUETE S", "PAQUETE M", "PAQUETE L"],
            content: "",
            clave: "",
            creando: false,
            error: null,
            hecho: null,
        };
    },
    computed: {
        office() {
            return this.shalom ? this.shalom.office : null;
        },
        claveFija() {
            return this.shalom ? this.shalom.clave_fija : null;
        },
    },
    methods: {
        abrir() {
            this.content = "";
            this.clave = "";
            this.error = null;
            this.hecho = null;
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        crear() {
            if (this.clave && !/^\d{4}$/.test(this.clave)) {
                this.error = "La clave de recojo son 4 dígitos.";
                return;
            }
            this.creando = true;
            this.error = null;
            this.$http
                .post(`/orders/${this.orderId}/envio/shalom-guia`, { content: this.content, clave: this.clave || null })
                .then(r => {
                    const d = r.data || {};
                    if (d.success === false) {
                        this.error = d.message || "Shalom no creó la guía.";
                        return;
                    }
                    this.hecho = d;
                    this.$message.success(d.message || "Guía creada.");
                    this.$emit("saved");
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    const porCampo = d.errors ? Object.values(d.errors).map(x => x[0]).join(" ") : null;
                    this.error = porCampo || d.message || "Shalom no creó la guía.";
                })
                .then(() => {
                    this.creando = false;
                });
        },
        imprimir() {
            window.open(`/orders/envio/${this.shipmentId}/shalom-rotulo`, "_blank");
        },
    },
};
</script>

<style scoped>
.shg-field { margin-bottom: 14px; }
.shg-field label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #334155; }
.shg-req { color: #dc2626; }
.shg-sub { display: block; font-size: 12px; color: #64748b; margin-top: 4px; }
.shg-hint { font-size: 13px; color: #475569; margin: 0 0 14px; line-height: 1.45; }
.shg-warn { font-size: 13px; background: #fff7e6; color: #8a5300; border-radius: 8px; padding: 10px 12px; margin: 0 0 12px; }
.shg-alert { background: #fef2f2; color: #b91c1c; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.shg-ok { background: #f0fdf4; border-radius: 10px; padding: 14px; text-align: center; }
.shg-ok__t { font-weight: 700; color: #166534; }
.shg-ok__n { font-size: 20px; font-weight: 800; margin-top: 6px; letter-spacing: .5px; }
.shg-ok__s { font-size: 13px; color: #166534; margin-top: 4px; }
</style>
