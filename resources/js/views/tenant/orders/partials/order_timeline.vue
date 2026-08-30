<template>
    <el-dialog
        title="Historial del pedido"
        :visible="visible"
        :width="dialogWidth"
        top="5vh"
        @close="$emit('update:visible', false)"
    >
        <div v-loading="loading" class="ord-tl">
            <div v-if="current" class="ord-tl-head">
                <span class="ord-tl-current">{{ current.label }}</span>
                <span v-if="paymentStatus" class="ord-tl-pay">{{ paymentStatus }}</span>
            </div>

            <div v-if="!loading && !events.length" class="ord-tl-empty">
                Este pedido todavía no tiene movimientos registrados.
            </div>

            <ul class="ord-tl-list">
                <li v-for="(e, i) in events" :key="i" class="ord-tl-item">
                    <span class="ord-tl-dot" :class="'src-' + e.source"></span>
                    <div class="ord-tl-body">
                        <div class="ord-tl-title">{{ e.title }}</div>
                        <div v-if="e.detail" class="ord-tl-detail">{{ e.detail }}</div>
                        <div class="ord-tl-meta">
                            {{ e.at || "sin fecha" }}
                            <template v-if="e.actor"> · {{ e.actor }}</template>
                            <span class="ord-tl-src">{{ sourceLabel(e.source) }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <span slot="footer">
            <el-button @click="$emit('update:visible', false)">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Historial unificado del pedido.
 *
 * Una sola línea de tiempo con lo comercial (cambios de estado) y lo logístico
 * (bitácora del envío, impresiones y reimpresiones). Las tablas de origen
 * siguen separadas — fusionarlas sería una migración destructiva —; lo que se
 * unifica es la lectura, que es lo que el operador necesita.
 *
 * El orden y las etiquetas los resuelve PHP: aquí no se reconstruye nada.
 */
export default {
    props: {
        visible: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
    },

    data() {
        return {
            loading: false,
            events: [],
            current: null,
            paymentStatus: null,
            viewport: typeof window !== "undefined" ? window.innerWidth : 1200,
        };
    },

    computed: {
        dialogWidth() {
            return this.viewport < 768 ? "96%" : "620px";
        },
    },

    watch: {
        visible(value) {
            if (value) this.load();
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
        async load() {
            if (!this.orderId) return;

            this.loading = true;
            try {
                const { data } = await this.$http.get(`/orders/${this.orderId}/status-logs`);
                this.events = data.timeline || [];
                this.current = data.current_status;
                this.paymentStatus = data.payment_status;
            } catch (e) {
                this.$message.error("No se pudo cargar el historial.");
            } finally {
                this.loading = false;
            }
        },

        sourceLabel(source) {
            return (
                {
                    order: "Pedido",
                    shipment: "Envío",
                    print: "Impresión",
                    // Movido por el envío, no por una persona: si no se dice,
                    // parece que alguien cambió el estado y nadie lo recuerda.
                    sync: "Automático",
                }[source] || source
            );
        },
    },
};
</script>

<style scoped>
.ord-tl-head {
    display: flex;
    gap: 8px;
    align-items: center;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}
.ord-tl-current {
    font-weight: 700;
}
.ord-tl-pay {
    font-size: 12px;
    color: #6b7280;
}
.ord-tl-empty {
    color: #6b7280;
    font-size: 13px;
    padding: 12px 0;
}
.ord-tl-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.ord-tl-item {
    display: flex;
    gap: 10px;
    padding: 8px 0;
    border-left: 2px solid #e5e7eb;
    margin-left: 5px;
    padding-left: 14px;
    position: relative;
}
.ord-tl-dot {
    position: absolute;
    left: -6px;
    top: 13px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #94a3b8;
    border: 2px solid #fff;
}
/* Un color por origen: de un vistazo se distingue lo que hizo una persona
   de lo que disparó el envío. */
.src-order { background: #4f46e5; }
.src-shipment { background: #ea580c; }
.src-print { background: #0891b2; }
.src-sync { background: #16a34a; }
.ord-tl-title {
    font-size: 13.5px;
    font-weight: 600;
    color: #1f2937;
}
.ord-tl-detail {
    font-size: 12.5px;
    color: #475569;
}
.ord-tl-meta {
    font-size: 11.5px;
    color: #94a3b8;
}
.ord-tl-src {
    margin-left: 6px;
    padding: 0 6px;
    border-radius: 999px;
    background: #f1f5f9;
}
</style>
