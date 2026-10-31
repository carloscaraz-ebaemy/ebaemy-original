<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        title="Revisar la nota de venta"
        top="6vh"
        width="560px"
        custom-class="nvr-dialog"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="cargando" class="nvr-loading" v-loading="true"></div>

        <template v-else>
            <div v-if="error" class="nvr-alert">{{ error }}</div>

            <p class="nvr-intro">
                Los productos de este pedido se escribieron en el envío y no
                traen precio. Revisa cada línea: es lo que saldrá impreso en la
                nota de venta y, después, en la boleta o factura.
            </p>

            <div v-for="(a, i) in avisos" :key="'a' + i" class="nvr-warn">{{ a }}</div>

            <div v-for="(l, i) in lineas" :key="i" class="nvr-line">
                <div class="nvr-desc">
                    <el-input
                        v-if="l.sin_catalogo"
                        v-model="l.description"
                        type="textarea"
                        :autosize="{ minRows: 1, maxRows: 3 }"
                        maxlength="250"
                    ></el-input>
                    <span v-else class="nvr-name">{{ l.description }}</span>
                    <small class="nvr-tag">
                        {{
                            l.sin_catalogo
                                ? "No está en tu catálogo: va como «Productos varios» con este texto"
                                : "Producto del catálogo" + (l.internal_id ? " · " + l.internal_id : "")
                        }}
                    </small>
                </div>
                <div class="nvr-nums">
                    <label>
                        Cant.
                        <el-input-number
                            v-model="l.quantity"
                            :min="1"
                            :precision="0"
                            size="small"
                            controls-position="right"
                        ></el-input-number>
                    </label>
                    <label>
                        Precio unit. (S/)
                        <el-input-number
                            v-model="l.unit_price"
                            :min="0"
                            :precision="2"
                            :step="1"
                            size="small"
                            controls-position="right"
                        ></el-input-number>
                    </label>
                    <span class="nvr-sub">S/ {{ fmt(l.quantity * l.unit_price) }}</span>
                </div>
            </div>

            <!-- Referencia: lo cobrado incluye el envío, que no va en la nota. -->
            <dl class="nvr-sum">
                <div>
                    <dt>Cobrado al cliente</dt>
                    <dd>S/ {{ fmt(ref.a_cobrar) }}</dd>
                </div>
                <div>
                    <dt>Envío (no va en la nota)</dt>
                    <dd>− S/ {{ fmt(ref.envio) }}</dd>
                </div>
                <div>
                    <dt>Productos, según lo cobrado</dt>
                    <dd>S/ {{ fmt(ref.sugerido) }}</dd>
                </div>
                <div class="nvr-total">
                    <dt>Total de la nota</dt>
                    <dd>S/ {{ fmt(total) }}</dd>
                </div>
            </dl>
            <p v-if="descuadre" class="nvr-diff">
                El total no coincide con lo cobrado por los productos
                (diferencia S/ {{ fmt(Math.abs(total - ref.sugerido)) }}). Puedes
                emitir igual si es correcto.
            </p>
        </template>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <el-button
                type="primary"
                :loading="emitiendo"
                :disabled="cargando || !puedeEmitir"
                @click="emitir"
            >
                Emitir nota de venta
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Nota de venta de un pedido con los productos escritos a mano.
 *
 * Las ventas del enlace de registro guardan los productos como texto en el
 * envío y el cobro en el envío: el pedido no tiene líneas ni total. El
 * servidor propone las líneas (producto del catálogo o «Productos varios» con
 * el texto del pedido) y el importe cobrado sin el envío; aquí el operador
 * pone los precios y emite. Al emitir, las líneas quedan en el pedido.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        row: { type: Object, default: null },
    },
    data() {
        return {
            cargando: false,
            emitiendo: false,
            error: null,
            avisos: [],
            lineas: [],
            ref: { a_cobrar: 0, envio: 0, sugerido: 0 },
        };
    },
    computed: {
        total() {
            return this.lineas.reduce(
                (t, l) => t + (Number(l.quantity) || 0) * (Number(l.unit_price) || 0),
                0
            );
        },
        descuadre() {
            return this.ref.sugerido > 0 && Math.abs(this.total - this.ref.sugerido) > 0.009;
        },
        puedeEmitir() {
            return (
                this.lineas.length > 0 &&
                this.lineas.every(
                    l => l.item_id && l.quantity > 0 && l.unit_price > 0 && String(l.description || "").trim()
                )
            );
        },
    },
    methods: {
        fmt(n) {
            return (Number(n) || 0).toFixed(2);
        },
        abrir() {
            this.cargando = true;
            this.error = null;
            this.avisos = [];
            this.lineas = [];

            this.$http
                .get(`/orders/${this.row.id}/nota-venta/preparar`)
                .then(r => {
                    const d = r.data || {};
                    this.lineas = d.lineas || [];
                    this.avisos = d.avisos || [];
                    this.ref = { a_cobrar: d.a_cobrar, envio: d.envio, sugerido: d.sugerido };
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    this.error = d.message || "No se pudo preparar la nota de venta.";
                })
                .then(() => {
                    this.cargando = false;
                });
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        emitir() {
            this.emitiendo = true;
            this.error = null;

            this.$http
                .post(`/orders/${this.row.id}/nota-venta`, {
                    lineas: this.lineas.map(l => ({
                        item_id: l.item_id,
                        description: l.description,
                        quantity: l.quantity,
                        unit_price: l.unit_price,
                    })),
                })
                .then(r => {
                    this.$message.success(r.data.message);
                    this.$emit("emitted", this.row.id);
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    this.error = d.message || "No se pudo emitir.";
                })
                .then(() => {
                    this.emitiendo = false;
                });
        },
    },
};
</script>

<style scoped>
.nvr-loading {
    min-height: 140px;
}
.nvr-intro {
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.45;
    margin: 0 0 14px;
}
.nvr-alert,
.nvr-warn {
    border-radius: 4px;
    padding: 8px 11px;
    font-size: 12px;
    margin-bottom: 10px;
    line-height: 1.4;
}
.nvr-alert {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.nvr-warn {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
}
.nvr-line {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 10px;
}
.nvr-name {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}
.nvr-tag {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #64748b;
}
.nvr-nums {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    margin-top: 10px;
}
.nvr-nums label {
    display: flex;
    flex-direction: column;
    font-size: 11.5px;
    color: #475569;
    gap: 3px;
}
.nvr-nums .el-input-number {
    width: 130px;
}
.nvr-sub {
    margin-left: auto;
    font-weight: 600;
    font-size: 13px;
    padding-bottom: 6px;
}
.nvr-sum {
    margin: 14px 0 0;
    font-size: 12.5px;
}
.nvr-sum > div {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    color: #475569;
}
.nvr-sum dt,
.nvr-sum dd {
    margin: 0;
}
.nvr-total {
    border-top: 1px solid #e2e8f0;
    margin-top: 4px;
    padding-top: 7px !important;
    font-weight: 700;
    color: #0f172a !important;
    font-size: 14px;
}
.nvr-diff {
    font-size: 12px;
    color: #92400e;
    margin: 8px 0 0;
}
</style>

<style>
@media (max-width: 600px) {
    .nvr-dialog {
        width: calc(100% - 24px) !important;
    }
}
</style>
