<template>
    <!-- El WhatsApp de la tienda por QR (como en el Pro 9). Solo aparece si el
         servidor tiene la conexión configurada. -->
    <div v-if="estado && estado.available" class="wa-section wqr">
        <h3>Conecta el WhatsApp de tu tienda</h3>
        <p class="wa-hint">
            Escanea un código QR con el celular de la tienda y el sistema enviará los
            avisos de pedidos y los comprobantes (nota de venta, boleta, factura) desde
            tu propio número.
        </p>

        <div v-if="estado.connected" class="wqr__ok">
            <div>
                <b>✓ Conectado</b>
                <span v-if="estado.phone"> · +{{ estado.phone }}</span>
                <span v-if="estado.name"> · {{ estado.name }}</span>
            </div>
            <el-button size="small" :loading="trabajando" @click="desconectar">Desconectar</el-button>
        </div>

        <div v-else-if="qr" class="wqr__scan">
            <img :src="qr" alt="Código QR para vincular WhatsApp" />
            <ol>
                <li>Abre WhatsApp en el celular de la tienda.</li>
                <li>Toca <b>⋮</b> o <b>Configuración</b> → <b>Dispositivos vinculados</b> → <b>Vincular un dispositivo</b>.</li>
                <li>Apunta la cámara a este código. Se actualiza solo cada pocos segundos.</li>
            </ol>
            <el-button size="small" @click="cancelar">Cancelar</el-button>
        </div>

        <div v-else class="wqr__off">
            <el-button type="success" size="small" :loading="trabajando" @click="conectar">
                Conectar con QR
            </el-button>
            <small>Usa un número que la tienda tenga siempre a mano: si se desvincula, los avisos dejan de salir.</small>
        </div>

        <p v-if="error" class="wqr__err">{{ error }}</p>
    </div>
</template>

<script>
/**
 * Conexión del WhatsApp de la tienda por QR (Evolution API, como el Pro 9).
 * Pide el QR, lo refresca mientras no se escanee y pregunta el estado hasta
 * que el número queda vinculado.
 */
export default {
    data() {
        return { estado: null, qr: null, trabajando: false, error: null, timer: null, vueltas: 0 };
    },
    created() {
        this.cargar();
    },
    beforeDestroy() {
        clearTimeout(this.timer);
    },
    methods: {
        cargar() {
            return this.$http.get("/whatsapp/conexion").then(r => {
                this.estado = r.data || null;
                if (this.estado && this.estado.connected) {
                    this.qr = null;
                    this.$emit("conectado");
                }
            }).catch(() => {});
        },
        conectar() {
            this.trabajando = true;
            this.error = null;
            this.$http.post("/whatsapp/conexion/conectar").then(r => {
                this.qr = r.data.qr || null;
                this.vueltas = 0;
                this.vigilar();
            }).catch(e => {
                this.error = (e.response && e.response.data && e.response.data.message) || "No se pudo iniciar la conexión.";
            }).then(() => { this.trabajando = false; });
        },
        // Cada 3 s: ¿ya escaneó? Cada 30 s se pide un QR nuevo (caduca).
        vigilar() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.vueltas++;
                this.cargar().then(() => {
                    if (!this.qr || (this.estado && this.estado.connected)) return;
                    if (this.vueltas % 10 === 0) {
                        this.$http.get("/whatsapp/conexion/qr").then(r => { if (r.data.qr) this.qr = r.data.qr; }).catch(() => {});
                    }
                    // Diez minutos sin escanear: se deja de preguntar.
                    if (this.vueltas < 200) this.vigilar();
                    else { this.qr = null; this.error = "El código venció. Pulsa «Conectar con QR» otra vez."; }
                });
            }, 3000);
        },
        cancelar() {
            clearTimeout(this.timer);
            this.qr = null;
        },
        desconectar() {
            this.$confirm("Los avisos y comprobantes dejarán de salir desde este número.", "Desconectar WhatsApp", {
                confirmButtonText: "Desconectar", cancelButtonText: "Cancelar", type: "warning",
            }).then(() => {
                this.trabajando = true;
                return this.$http.post("/whatsapp/conexion/desconectar").then(() => this.cargar());
            }).catch(() => {}).then(() => { this.trabajando = false; });
        },
    },
};
</script>

<style scoped>
.wqr__ok { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: 12px 14px; }
.wqr__scan { display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
.wqr__scan img { width: 240px; height: 240px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
.wqr__scan ol { flex: 1 1 220px; margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.6; color: #334155; }
.wqr__off { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.wqr__off small { color: #64748b; font-size: 12px; }
.wqr__err { color: #b91c1c; font-size: 12.5px; margin: 10px 0 0; }
@media (max-width: 576px) { .wqr__scan img { width: 100%; height: auto; max-width: 280px; } }
</style>
