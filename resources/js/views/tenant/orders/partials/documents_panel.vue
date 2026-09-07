<template>
    <el-dialog
        :close-on-click-modal="true"
        :visible="showDialog"
        :title="'Documentos del pedido ' + (row ? row.order_id : '')"
        top="7vh"
        width="560px"
        @close="cerrar"
    >
        <div v-if="row" class="dp">
            <!-- Con qué corresponde facturar. Va arriba porque condiciona todo
                 lo de abajo: el chip sugerido, qué se puede emitir y qué falta. -->
            <div v-if="row.billing" class="dp-billing">
                <div class="dp-billing-main">
                    <span class="dp-billing-lbl">Corresponde</span>
                    <strong>{{ row.billing.nombre }}</strong>
                    <el-tag v-if="row.billing.elegido_por_operador" size="mini" type="info"
                        >elegido a mano</el-tag
                    >
                </div>
                <p class="dp-billing-why">{{ row.billing.motivo }}</p>
                <p v-if="!row.billing.puede_emitir" class="dp-billing-missing">
                    Falta {{ row.billing.faltan.join(" y ") }}.
                </p>
                <el-button size="mini" @click="$emit('fix-billing', row)">
                    Corregir
                </el-button>
            </div>

            <section class="dp-group">
                <h4>Comercial</h4>
                <p v-if="!comerciales.length" class="dp-empty">
                    Este pedido no documenta una venta: es el encargo de un envío,
                    sin productos ni importe.
                </p>
                <div v-for="s in comerciales" :key="s.tipo" class="dp-item">
                    <div class="dp-item-head">
                        <span class="dp-name">{{ s.nombre }}</span>
                        <span class="dp-state" :class="'is-' + tono(s)">{{
                            s.estado_label
                        }}</span>
                    </div>

                    <div v-if="s.existe" class="dp-line">
                        <span class="dp-num">{{ s.numero }}</span>
                        <span v-if="s.fecha" class="dp-date">{{ s.fecha }}</span>
                        <a
                            v-if="s.pdf_url"
                            :href="s.pdf_url"
                            target="_blank"
                            rel="noopener"
                            class="dp-act"
                            >Ver PDF</a
                        >
                    </div>

                    <template v-else>
                        <p v-if="s.bloqueo" class="dp-why">{{ s.bloqueo }}</p>
                        <div v-else class="dp-line">
                            <span class="dp-ready">Se puede emitir.</span>
                            <el-button
                                size="mini"
                                type="primary"
                                plain
                                class="dp-btn"
                                @click="emitir(s)"
                                >Emitir</el-button
                            >
                        </div>
                    </template>

                    <p v-if="s.motivo_error" class="dp-err">
                        SUNAT: {{ s.motivo_error }}
                    </p>
                </div>
            </section>

            <section class="dp-group">
                <h4>Logística</h4>

                <div v-if="guia" class="dp-item">
                    <div class="dp-item-head">
                        <span class="dp-name">{{ guia.nombre }}</span>
                        <span class="dp-state" :class="'is-' + tono(guia)">{{
                            guia.estado_label
                        }}</span>
                    </div>
                    <div v-if="guia.existe" class="dp-line">
                        <span class="dp-num">{{ guia.numero }}</span>
                        <span v-if="guia.fecha" class="dp-date">{{ guia.fecha }}</span>
                        <a
                            v-if="guia.pdf_url"
                            :href="guia.pdf_url"
                            target="_blank"
                            rel="noopener"
                            class="dp-act"
                            >Ver PDF</a
                        >
                    </div>
                    <template v-else>
                        <p v-if="guia.bloqueo" class="dp-why">{{ guia.bloqueo }}</p>
                        <div v-else class="dp-line">
                            <span class="dp-ready">Se puede generar.</span>
                            <el-button
                                size="mini"
                                type="primary"
                                plain
                                class="dp-btn"
                                @click="$emit('dispatch-guide', row)"
                                >Generar</el-button
                            >
                        </div>
                    </template>
                </div>

                <!-- El rotulado NO es un comprobante y por eso no tiene chip en
                     la tabla, pero es el documento que el encargado imprime y
                     pega en el paquete: en el panel pertenece aqui. Su estado
                     sale del envio, no de `documents`. -->
                <div v-if="row.shipment" class="dp-item">
                    <div class="dp-item-head">
                        <span class="dp-name">{{ rotulo.nombre }}</span>
                        <span class="dp-state" :class="'is-' + rotulo.tono">{{
                            rotulo.estado
                        }}</span>
                    </div>
                    <div class="dp-line">
                        <span v-if="row.shipment.printed_at" class="dp-date">{{
                            row.shipment.printed_at
                        }}</span>
                        <span v-if="row.shipment.batch_label" class="dp-date"
                            >Lote {{ row.shipment.batch_label }}</span
                        >
                        <el-button
                            v-if="!row.shipment.print_block"
                            size="mini"
                            plain
                            class="dp-btn"
                            @click="$emit('print-label', row)"
                            >{{ rotulo.accion }}</el-button
                        >
                    </div>
                    <p v-if="row.shipment.print_block" class="dp-why">
                        {{ row.shipment.print_block }}
                    </p>
                </div>

                <p v-if="!guia && !row.shipment" class="dp-empty">
                    El pedido no tiene envío configurado.
                </p>
            </section>

            <!-- Datos de Saga que no salen de `documents`. Es informacion real
                 y no hay que perderla al reorganizar el panel. -->
            <p v-if="row.mp_invoice_state === 'external'" class="dp-note">
                El comprobante lo emitió el vendedor en el portal de Saga, fuera de
                EBAEMY.
            </p>
            <p v-if="row.mp_invoice_state === 'alert'" class="dp-err">
                Saga devolvió o canceló este pedido con la boleta ya emitida: hay que
                emitir una Nota de Crédito.
            </p>
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cerrar</el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Panel de documentos del pedido.
 *
 * Sustituye al popover de la columna, que se quedo corto: en 320px no caben el
 * numero, la fecha, el estado, el motivo del bloqueo y la accion de cinco
 * documentos. Los chips siguen siendo el vistazo rapido; esto es el detalle.
 *
 * Dos grupos, porque son dos mundos distintos y el operador los consulta en
 * momentos distintos: **Comercial** (nota de venta, boleta, factura) y
 * **Logistica** (guia de remision y rotulado).
 *
 * No decide NADA. Los estados, los bloqueos y que documento corresponde vienen
 * resueltos de `OrderDocuments` y `BillingDocumentResolver`; las acciones se
 * emiten hacia arriba, donde ya viven desde la Fase D. Este componente solo
 * elige colores y agrupa.
 *
 * El rotulado es la excepcion: no es un comprobante, no esta en `documents` y su
 * estado sale del envio. Se pinta aparte, a mano, y por eso su tono se calcula
 * aqui — es lo unico que este archivo decide.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        row: { type: Object, default: null },
    },
    computed: {
        docs() {
            return (this.row && this.row.documents) || {};
        },
        comerciales() {
            return ["nota_venta", "boleta", "factura"]
                .map(k => this.docs[k])
                .filter(Boolean);
        },
        guia() {
            return this.docs.guia || null;
        },
        /** Estado del rotulado, que no viene de `documents`. */
        rotulo() {
            const s = (this.row && this.row.shipment) || {};
            const esRecibo = s.label_kind === "receipt";
            const nombre = esRecibo ? "Comprobante de recojo" : "Rótulo de envío";

            if (s.print_block) {
                return { nombre, estado: "No disponible", tono: "off", accion: "" };
            }
            if (s.print_count > 0) {
                return {
                    nombre,
                    estado: "Impreso" + (s.print_count > 1 ? " ×" + s.print_count : ""),
                    tono: "ok",
                    accion: "Reimprimir",
                };
            }

            return { nombre, estado: "Sin imprimir", tono: "ready", accion: "Imprimir" };
        },
    },
    methods: {
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        /** Mismo criterio de color que los chips de la tabla. */
        tono(s) {
            if (!s.existe) return s.bloqueo ? "off" : "ready";

            switch (s.estado) {
                case "aceptado":
                case "emitido":
                    return "ok";
                case "registrado":
                case "enviado":
                    return "pend";
                case "observado":
                case "por_anular":
                    return "warn";
                case "rechazado":
                case "anulado":
                    return "err";
                default:
                    return "ok";
            }
        },
        emitir(s) {
            // La nota de venta tiene su propio endpoint; boleta y factura salen
            // por el modal de comprobante, que necesita la NV ya emitida.
            this.$emit(
                s.tipo === "nota_venta" ? "emit-sale-note" : "emit-document",
                this.row
            );
            this.cerrar();
        },
    },
};
</script>

<style scoped>
.dp {
    font-size: 13px;
    line-height: 1.5;
}
.dp-billing {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 11px 13px;
    margin-bottom: 18px;
}
.dp-billing-main {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.dp-billing-lbl {
    color: #64748b;
    font-size: 12px;
}
.dp-billing-why,
.dp-billing-missing {
    margin: 4px 0 8px;
    font-size: 12px;
    color: #64748b;
}
.dp-billing-missing {
    color: #92400e;
}
.dp-group + .dp-group {
    margin-top: 20px;
}
.dp-group h4 {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #94a3b8;
    margin: 0 0 8px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e2e8f0;
}
.dp-item + .dp-item {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
}
.dp-item-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
}
.dp-name {
    font-weight: 600;
    color: #0f172a;
}
.dp-state {
    font-size: 10.5px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 3px;
    white-space: nowrap;
}
.dp-state.is-ok { background: #dcfce7; color: #166534; }
.dp-state.is-pend { background: #dbeafe; color: #1e40af; }
.dp-state.is-warn { background: #fef3c7; color: #92400e; }
.dp-state.is-err { background: #fee2e2; color: #b91c1c; }
.dp-state.is-ready,
.dp-state.is-off { background: #f1f5f9; color: #64748b; }
.dp-line {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 5px;
}
.dp-num {
    font-weight: 600;
    color: #1e293b;
    font-variant-numeric: tabular-nums;
}
.dp-date {
    color: #64748b;
    font-size: 12px;
}
.dp-act {
    margin-left: auto;
    font-weight: 600;
}
.dp-btn {
    margin-left: auto;
}
.dp-ready {
    color: #15803d;
    font-size: 12px;
}
.dp-why,
.dp-empty,
.dp-note {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 12px;
}
.dp-empty {
    margin: 0;
}
.dp-note {
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}
.dp-err {
    margin: 6px 0 0;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 500;
}
</style>
