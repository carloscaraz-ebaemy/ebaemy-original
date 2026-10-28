<template>
    <el-dialog
        :visible="visible"
        :title="title"
        width="94%"
        top="3vh"
        custom-class="prd-dialog"
        append-to-body
        @close="cerrar"
    >
        <!-- La pantalla de impresión (rótulo suelto, lote, Shalom) se carga
             aquí dentro en vez de en otra pestaña, como la Nota de Venta: el
             operador imprime y sigue en Pedidos. Esas pantallas avisan
             «impresion-cerrar» y «rotulo-impreso» con postMessage
             (partials/print-embed-js). -->
        <div class="prd-body">
            <p v-if="cargando" class="prd-load">Preparando la impresión…</p>
            <iframe v-if="src" ref="marco" :src="src" class="prd-frame" title="Impresión" @load="cargando = false"></iframe>
        </div>
        <span slot="footer" class="prd-foot">
            <small>¿Prefieres otra pestaña?</small>
            <a :href="src" target="_blank" rel="noopener" @click="cerrar">Abrir aparte</a>
        </span>
    </el-dialog>
</template>

<script>
export default {
    props: {
        visible: { type: Boolean, default: false },
        url: { type: String, default: "" },
        title: { type: String, default: "Imprimir" },
    },
    data() {
        return { src: "", cargando: false };
    },
    watch: {
        visible: {
            immediate: true,
            handler(v) {
                // Se vacía al cerrar: reabrir el mismo rótulo recarga la página
                // y vuelve a pedir el motivo si ya era una reimpresión.
                this.src = v ? this.url : "";
                this.cargando = v;
            },
        },
    },
    mounted() {
        this.alMensaje = ev => {
            const d = ev && ev.data;
            if (!d || !this.visible) return;
            if (this.$refs.marco && ev.source !== this.$refs.marco.contentWindow) return;
            if (d.tipo === "impresion-cerrar") this.cerrar();
            if (d.tipo === "rotulo-impreso") this.$emit("impreso", d.envio);
        };
        window.addEventListener("message", this.alMensaje);
    },
    beforeDestroy() {
        window.removeEventListener("message", this.alMensaje);
    },
    methods: {
        cerrar() {
            this.$emit("update:visible", false);
        },
    },
};
</script>

<style>
.prd-dialog { max-width: 1400px; }
.prd-dialog .el-dialog__body { padding: 0; }
.prd-dialog .el-dialog__header { padding: 14px 20px; border-bottom: 1px solid #e5e7eb; }
.prd-dialog .el-dialog__footer { padding: 8px 20px; border-top: 1px solid #e5e7eb; }
.prd-body { position: relative; height: 82vh; background: #e8eaee; }
.prd-frame { display: block; width: 100%; height: 100%; border: 0; }
.prd-load { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; margin: 0; color: #64748b; font-size: 14px; }
.prd-foot { display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
.prd-foot a { color: #3730a3; font-weight: 600; }
</style>
