<template>
    <div :class="colClass?colClass:'col-12'">
        <el-input v-model="wsPhone">
            <template slot="prepend">+51</template>
            <template slot="append">
                <el-tooltip class="item"
                    :content="motivo"
                    :disabled="puede"
                    effect="dark"
                    placement="top-start">
                    <el-button
                        @click="sendQrChat"
                        :disabled="!puede"
                        :loading="loading_submit">
                        Enviar <i class="fab fa-whatsapp"></i>
                    </el-button>
                </el-tooltip>
            </template>
        </el-input>
        <small v-if="errors.customer_telephone"
            class="form-control-feedback"
            v-text="errors.customer_telephone[0]"></small>
    </div>
</template>

<script>
/**
 * Envía el comprobante en PDF por el WhatsApp de la tienda conectado por QR
 * (Evolution, como el Pro 9): el servidor lo manda con /whatsapp/conexion/documento.
 *
 * Antes posteaba directo desde el navegador al gateway de devaemy.com, muerto
 * desde 2026-08: el botón quedaba activo y nunca enviaba nada.
 */
// Una consulta de estado por página, aunque haya varios botones.
let estadoPromesa = null;

export default {
    props: ['colClass','wsPhone','wsFile','wsDocument','wsMessage', 'wsData', 'wsConfig'],
    data() {
        return {
            form: {},
            errors: {},
            estado: null,
            loading_submit: false,
        }
    },
    computed: {
        puede() {
            return !!(this.estado && this.estado.connected);
        },
        motivo() {
            if (!this.estado || !this.estado.available) return 'El envío por WhatsApp no está disponible.';
            return 'Conecta el WhatsApp de la tienda en Tienda Virtual → WhatsApp → Configuración.';
        },
    },
    mounted() {
        if (!estadoPromesa) {
            estadoPromesa = this.$http.get('/whatsapp/conexion').then(r => r.data).catch(() => null);
            setTimeout(() => { estadoPromesa = null; }, 60000);
        }
        estadoPromesa.then(e => { this.estado = e; });
    },
    methods: {
        async sendQrChat() {
            if (!this.wsPhone) {
                return this.$message.error('El número es obligatorio')
            }
            this.loading_submit = true
            const {extension_only, filename_only} = this.wsData || {};
            const base64 = await this.convertFileToBase64(this.wsFile);
            if (!base64) {
                this.loading_submit = false;
                return this.$message.error('No se pudo leer el PDF del comprobante.');
            }
            this.$http.post('/whatsapp/conexion/documento', {
                file: base64,
                number: `51${String(this.wsPhone).replace(/\D/g, '').slice(-9)}`,
                message: this.wsMessage,
                filename: `${filename_only || 'comprobante'}.${extension_only || 'pdf'}`,
            })
                .then(r => this.$message.success((r.data && r.data.message) || 'Documento enviado con éxito'))
                .catch(e => this.$message.error((e.response && e.response.data && e.response.data.message) || 'No se pudo enviar'))
                .finally(() => { this.loading_submit = false; });
        },
        async convertFileToBase64(url) {
            try {
                const response = await fetch(url);
                const blob = await response.blob();
                return await this.blobToBase64(blob);
            } catch (error) {
                console.error(error);
                return null;
            }
        },
        blobToBase64(blob) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => {
                    resolve(reader.result.split(',')[1]); // Quitar la parte 'data:*/*;base64,'
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        },
    }
}
</script>
