<template>
    <el-drawer
        :visible="visible"
        :with-header="false"
        direction="rtl"
        size="480px"
        custom-class="od-drawer"
        @close="cerrar"
    >
        <div v-if="row" class="od">
            <!-- Cabecera fija: el pedido no se pierde al bajar por el panel. -->
            <header class="od-head">
                <div class="od-head-l">
                    <span class="od-id">#{{ row.order_id }}</span>
                    <span
                        class="od-canal"
                        :style="{ background: canalColor }"
                        :title="row.channel_name || 'Sin canal declarado'"
                    ></span>
                    <span class="od-fecha">{{ fecha }}</span>
                </div>
                <button class="od-x" title="Cerrar" @click="cerrar">
                    <i class="el-icon-close"></i>
                </button>
            </header>

            <div class="od-body">
                <!-- ── Resumen ─────────────────────────────────────── -->
                <section class="od-sec">
                    <h4>Resumen</h4>
                    <div class="od-cli">{{ row.customer }}</div>
                    <dl class="od-dl">
                        <template v-if="row.customer_doc">
                            <dt>Documento</dt><dd>{{ row.customer_doc }}</dd>
                        </template>
                        <template v-if="row.customer_telefono">
                            <dt>Teléfono</dt><dd>{{ row.customer_telefono }}</dd>
                        </template>
                        <template v-if="row.customer_email">
                            <dt>Correo</dt><dd>{{ row.customer_email }}</dd>
                        </template>
                        <template v-if="row.customer_direccion">
                            <dt>Dirección</dt><dd>{{ row.customer_direccion }}</dd>
                        </template>
                        <dt>Canal</dt><dd>{{ row.channel_name || "—" }}</dd>
                        <template v-if="row.mp_external_order_id">
                            <dt>Nº en el canal</dt><dd>{{ row.mp_external_order_id }}</dd>
                        </template>
                        <template v-if="row.warehouse_description">
                            <dt>Almacén</dt><dd>{{ row.warehouse_description }}</dd>
                        </template>
                    </dl>
                </section>

                <!-- ── Productos ───────────────────────────────────── -->
                <section class="od-sec">
                    <h4>
                        Productos
                        <span class="od-count">{{ items.length || lineasEnvio.length }}</span>
                    </h4>
                    <table v-if="items.length" class="od-items">
                        <tbody>
                            <tr v-for="(it, i) in items" :key="i">
                                <td class="od-it-name">{{ it.description }}</td>
                                <td class="od-it-qty">×{{ it.cantidad }}</td>
                                <td class="od-it-price">{{ precio(it) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- El detalle del ENVIO: el espejo de un encargo no tiene
                         lineas de venta, su contenido es texto libre que el
                         almacen escribe en el envio. Se muestra derivado, y por
                         eso editarlo alli se ve aqui sin sincronizar nada. -->
                    <template v-else-if="lineasEnvio.length">
                        <ul class="od-envio">
                            <li v-for="(l, i) in lineasEnvio" :key="i">{{ l }}</li>
                        </ul>
                        <p class="od-nota">
                            Es el detalle del envío, no líneas de venta: se edita
                            desde el envío y no lleva precio.
                        </p>
                    </template>

                    <p v-else class="od-empty">
                        Este pedido no tiene líneas: es el encargo de un envío.
                    </p>
                </section>

                <!-- ── Cobro ───────────────────────────────────────── -->
                <section class="od-sec">
                    <h4>Cobro</h4>
                    <dl class="od-dl">
                        <dt>Total</dt><dd class="od-money">S/ {{ dinero.total }}</dd>
                        <template v-if="dinero.pagado !== null">
                            <dt>Pagado</dt><dd class="od-money">S/ {{ dinero.pagado }}</dd>
                        </template>
                        <template v-if="dinero.saldo !== null">
                            <dt>Saldo</dt>
                            <dd class="od-money" :class="{ 'is-debt': dinero.saldo > 0 }">
                                S/ {{ dinero.saldo }}
                            </dd>
                        </template>
                        <dt>Método</dt><dd>{{ medio }}</dd>
                    </dl>
                    <!-- El dinero del encargo logistico vive en el envio, no en
                         el pedido: se dice, para que nadie lo busque aqui. -->
                    <p v-if="pagoEnElEnvio" class="od-nota">
                        El importe y los cobros de este encargo viven en el envío.
                    </p>
                    <el-button size="mini" @click="$emit('payments', row)">
                        Gestionar pagos
                    </el-button>
                </section>

                <!-- ── Documentos ──────────────────────────────────── -->
                <section class="od-sec">
                    <h4>Documentos</h4>
                    <!-- El MISMO componente que abre el dialogo de los chips.
                         No es una copia: si cambia una regla, cambia en los dos
                         sitios a la vez. -->
                    <documents-block
                        :row="row"
                        @emit-sale-note="$emit('emit-sale-note', row)"
                        @emit-document="$emit('emit-document', row)"
                        @dispatch-guide="$emit('dispatch-guide', row)"
                        @print-label="$emit('print-label', row)"
                        @fix-billing="$emit('fix-billing', row)"
                    ></documents-block>
                </section>

                <!-- ── Envío ───────────────────────────────────────── -->
                <section class="od-sec">
                    <h4>Envío</h4>
                    <template v-if="row.shipment">
                        <dl class="od-dl">
                            <dt>Modalidad</dt><dd>{{ row.shipment.delivery_label }}</dd>
                            <dt>Estado</dt><dd>{{ row.shipment.status_label }}</dd>
                            <template v-if="row.shipment.destination">
                                <dt>Destino</dt><dd>{{ row.shipment.destination }}</dd>
                            </template>
                            <template v-if="row.shipment.tracking_number">
                                <dt>Tracking</dt><dd>{{ row.shipment.tracking_number }}</dd>
                            </template>
                            <template v-if="row.shipment.batch_label">
                                <dt>Lote</dt><dd>{{ row.shipment.batch_label }}</dd>
                            </template>
                            <template v-if="row.shipment.sent_at">
                                <dt>Salida</dt><dd>{{ row.shipment.sent_at }}</dd>
                            </template>
                        </dl>
                        <!-- El aviso de datos incompletos se repite aqui a
                             proposito: es el sitio donde se van a completar. -->
                        <p
                            v-if="row.shipment.missing_data && row.shipment.missing_data.length"
                            class="od-warn"
                        >
                            Faltan datos para rotular:
                            {{ row.shipment.missing_data.join(", ") }}.
                        </p>
                        <div class="od-acts">
                            <el-button size="mini" @click="$emit('shipment', row)"
                                >Ver / editar</el-button
                            >
                            <el-button
                                v-if="!row.shipment.is_pickup && !row.shipment.has_guide"
                                size="mini"
                                @click="$emit('upload-guide', row)"
                                >Subir guía</el-button
                            >
                            <el-button
                                v-if="row.shipment.guide_url"
                                size="mini"
                                @click="$emit('view-guide', row)"
                                >Ver guía</el-button
                            >
                        </div>
                    </template>
                    <template v-else-if="row.shipment_cancelled">
                        <p class="od-nota">
                            El envío {{ row.shipment_cancelled.code }} está anulado.
                        </p>
                        <el-button size="mini" @click="$emit('restore-shipment', row)"
                            >Restaurar envío</el-button
                        >
                    </template>
                    <template v-else>
                        <p class="od-empty">Sin envío configurado.</p>
                        <el-button size="mini" @click="$emit('shipment', row)"
                            >Configurar envío</el-button
                        >
                    </template>
                </section>

                <!-- ── Historial ───────────────────────────────────── -->
                <section class="od-sec">
                    <h4>Historial</h4>
                    <!-- El historial es un componente con su propia carga y su
                         propio dialogo. Se abre, no se empotra: montarlo aqui
                         significaria pedir la bitacora de cada pedido cada vez
                         que alguien abre el panel, la mire o no. -->
                    <el-button size="mini" @click="$emit('timeline', row)">
                        Ver historial del pedido
                    </el-button>
                </section>

                <!-- ── Acciones ────────────────────────────────────── -->
                <section class="od-sec od-sec-last">
                    <h4>Acciones</h4>
                    <div class="od-acts">
                        <el-button
                            v-if="editable"
                            size="mini"
                            @click="$emit('edit', row)"
                            >Editar pedido</el-button
                        >
                        <el-button size="mini" @click="$emit('shipping-link', row)"
                            >Copiar enlace de datos</el-button
                        >
                    </div>
                </section>
            </div>
        </div>
    </el-drawer>
</template>

<script>
import DocumentsBlock from "./documents_block.vue";

/**
 * Detalle del pedido, en un cajon lateral.
 *
 * Es el sitio donde vive todo lo que la fila dejo de mostrar cuando la tabla
 * paso de diez columnas a seis: el telefono y la direccion del cliente, las
 * lineas del pedido, el destino y el tracking del envio. No es informacion
 * nueva ni una consulta nueva — sale entera del payload de la fila, que ya la
 * traia y la pintaba toda a la vez.
 *
 * ── Aloja, no reimplementa ────────────────────────────────────────────────
 *
 * Los documentos son `documents_block`, el MISMO componente que abre el
 * dialogo de los chips. El resto de acciones se emiten hacia arriba, donde ya
 * viven: pagos, envio, rotulado, historial y edicion son pantallas que
 * existian antes de este panel y siguen siendo las mismas.
 *
 * El historial se abre en vez de empotrarse: tiene su propia carga, y montarlo
 * aqui pediria la bitacora de cada pedido cada vez que alguien abre el cajon,
 * la mire o no.
 */
export default {
    components: { DocumentsBlock },
    props: {
        visible: { type: Boolean, default: false },
        row: { type: Object, default: null },
    },
    computed: {
        items() {
            const it = (this.row && this.row.items) || [];

            return Array.isArray(it) ? it : [];
        },
        /** El contenido del paquete, cuando el pedido no tiene lineas propias. */
        lineasEnvio() {
            if (this.items.length) return [];

            const s = (this.row && this.row.shipment) || {};

            return s.content_lines || [];
        },

        /** ¿El dinero de este pedido vive en el envío? (encargo logístico) */
        pagoEnElEnvio() {
            const r = this.row || {};

            return !!(r.shipment && Number(r.total) === 0 && !this.items.length);
        },
        /**
         * Total, pagado y saldo, del sitio que corresponda.
         *
         * La regla es la misma que usa la fila y no se decide aqui: en un
         * encargo el importe esta en el envio, y una copia en el pedido se
         * quedaria vieja sin avisar.
         */
        dinero() {
            const r = this.row || {};

            if (this.pagoEnElEnvio && r.shipment) {
                if (!r.shipment.has_amount) {
                    return { total: "—", pagado: null, saldo: null };
                }

                return {
                    total: this.money(r.shipment.amount_to_collect),
                    pagado: this.money(r.shipment.paid_total),
                    saldo: r.shipment.pending_total,
                };
            }

            return {
                total: this.money(r.total),
                pagado: r.paid_total != null ? this.money(r.paid_total) : null,
                saldo: r.pending_total != null ? r.pending_total : null,
            };
        },
        medio() {
            const r = this.row || {};

            return r.reference_payment || "—";
        },
        fecha() {
            return this.row ? this.row.created_at : "";
        },
        canalColor() {
            const porTipo = {
                ecommerce: "#4f46e5",
                marketplace: "#b45309",
                pos: "#0f766e",
                other: "#64748b",
            };

            return porTipo[(this.row || {}).channel_type] || "#94a3b8";
        },
        /** Editar solo antes de despachar. El servidor lo vuelve a comprobar. */
        editable() {
            return [1, 2, 3].indexOf(Number((this.row || {}).status_order_id)) !== -1;
        },
    },
    methods: {
        cerrar() {
            this.$emit("update:visible", false);
        },
        money(v) {
            const n = Number(v || 0);

            return n.toFixed(2);
        },
        precio(it) {
            const v = it.sale_unit_price || it.unit_price || it.precio_unitario;

            return v ? "S/ " + this.money(v) : "";
        },
    },
};
</script>

<style scoped>
.od {
    display: flex;
    flex-direction: column;
    height: 100%;
    font-size: 13px;
    line-height: 1.5;
}
.od-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    flex: 0 0 auto;
}
.od-head-l {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.od-id {
    font-weight: 700;
    font-size: 15px;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}
.od-canal {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex: 0 0 auto;
}
.od-fecha {
    color: #64748b;
    font-size: 12px;
}
.od-x {
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
}
.od-x:hover {
    color: #0f172a;
}
.od-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 4px 18px 24px;
}
.od-sec {
    padding: 14px 0;
    border-bottom: 1px solid #eef2f7;
}
.od-sec-last {
    border-bottom: 0;
}
.od-sec h4 {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
    margin: 0 0 8px;
    display: flex;
    align-items: center;
    gap: 7px;
}
.od-count {
    background: #eef2ff;
    color: #4338ca;
    border-radius: 999px;
    font-size: 10.5px;
    padding: 1px 7px;
}
.od-cli {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 6px;
}
/* Dos columnas: la etiqueta no crece, el valor se lleva el resto y parte
   linea si hace falta. Una direccion larga no debe desbordar el cajon. */
.od-dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 3px 12px;
    margin: 0;
}
.od-dl dt {
    color: #94a3b8;
    font-weight: 500;
    font-size: 12px;
    white-space: nowrap;
}
.od-dl dd {
    margin: 0;
    color: #334155;
    word-break: break-word;
}
.od-money {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}
.od-money.is-debt {
    color: #b91c1c;
}
.od-items {
    width: 100%;
    border-collapse: collapse;
}
.od-items td {
    padding: 4px 0;
    border-bottom: 1px dashed #eef2f7;
    vertical-align: top;
}
.od-items tr:last-child td {
    border-bottom: 0;
}
.od-it-name {
    color: #334155;
}
.od-it-qty {
    color: #64748b;
    white-space: nowrap;
    padding-left: 10px !important;
    text-align: right;
}
.od-it-price {
    white-space: nowrap;
    text-align: right;
    padding-left: 10px !important;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
}
.od-envio {
    margin: 0;
    padding-left: 18px;
    color: #334155;
}
.od-envio li {
    margin-bottom: 3px;
}
.od-empty,
.od-nota {
    color: #64748b;
    font-size: 12px;
    margin: 0 0 8px;
}
.od-warn {
    color: #92400e;
    background: #fef3c7;
    border-radius: 4px;
    padding: 6px 9px;
    font-size: 12px;
    margin: 8px 0;
}
.od-acts {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 8px;
}
</style>
