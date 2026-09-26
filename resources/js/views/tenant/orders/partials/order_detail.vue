<template>
    <!-- «Qué lleva este pedido», y nada más.

         Se abre desde la caja del listado. Antes ese clic llevaba al
         formulario completo —canal, cliente, destino, productos—, que es
         mucho formulario para responder una sola pregunta: el operador venia
         a decir que hay dentro de la caja y se encontraba pidiendole el
         documento del cliente otra vez.

         El editor completo sigue donde estaba, en el menu de la fila.

         Las dos formas de contestar, en la MISMA ficha:

         - **Del sistema**: lineas del catalogo. Son una venta —llevan
           `item_id`, precio y reserva de stock— y alimentan la nota de venta.
         - **A mano**: texto libre que se imprime en el rotulo para que la
           agencia sepa que lleva la caja. No tiene precio, no mueve stock y
           no se factura, y por eso vive en el ENVIO y no en `orders.items`.

         Estaban en dos pantallas distintas y habia que saber cual de las dos
         tocaba. -->
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="titulo"
        top="7vh"
        width="720px"
        @close="cerrar"
    >
        <div class="od">
            <div v-if="problemas.length" class="od-alert">
                <strong>No se pudo guardar:</strong>
                <ul>
                    <li v-for="(p, i) in problemas" :key="i">{{ p }}</li>
                </ul>
            </div>

            <div v-if="cargando" class="od-loading">
                <i class="el-icon-loading"></i> Cargando el detalle…
            </div>

            <template v-else>
                <div v-if="!lineasEditables" class="od-frozen">
                    Este pedido ya está en preparación: los productos quedaron
                    fijados. Si hay que cambiar lo que va dentro de la caja,
                    anúlalo y crea uno nuevo. El texto del rótulo sí se puede
                    corregir aquí abajo.
                </div>

                <h4 class="od-sec">Del sistema</h4>
                <p class="od-hint">
                    Productos del catálogo. Descuentan stock y salen en el
                    comprobante.
                </p>

                <product-lines
                    v-model="items"
                    :channel-id="channelId"
                    :disabled="!lineasEditables"
                    :can-edit-price="puedeEditarPrecio"
                ></product-lines>

                <!-- `null` = el pedido no tiene envio, y entonces no hay
                     ningun sitio donde escribir esto. Pintar la caja seria
                     ofrecer un campo que se traga lo que le pongas. -->
                <template v-if="packageContent !== null">
                    <h4 class="od-sec od-sec--2">Escrito a mano</h4>
                    <p class="od-hint">
                        Un renglón por cosa. Se imprime en el rótulo del envío:
                        no suma al total ni descuenta stock.
                    </p>
                    <el-input
                        v-model="packageContent"
                        type="textarea"
                        :rows="4"
                        :disabled="bloqueado"
                        placeholder="2 polos talla M&#10;1 gorra azul"
                    ></el-input>
                </template>
                <p v-else class="od-hint od-hint--solo">
                    Este pedido no tiene envío, así que no hay rótulo donde
                    escribir el contenido a mano.
                </p>
            </template>
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <el-button
                type="primary"
                :loading="guardando"
                :disabled="!sePuedeGuardar"
                @click="guardar"
            >
                Guardar detalle
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
import ProductLines from "./product_lines.vue";

export default {
    components: { ProductLines },
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { default: null },
    },
    data() {
        return {
            items: [],
            packageContent: null,
            packageContentOriginal: null,
            channelId: null,
            puedeEditarPrecio: false,
            lineasEditables: true,
            // El pedido llego en un estado final: se puede mirar, no guardar.
            bloqueado: false,
            // Un pedido que llego sin lineas es un encargo logistico y puede
            // seguir sin ellas. Uno que las tenia no se puede vaciar.
            teniaLineas: true,
            // El editor de detalle no pregunta por el cliente, pero
            // `updateManual` lo exige y reescribe el bloque `customer` entero.
            // Se guarda tal como llego y se devuelve igual: sin este viaje,
            // tocar los productos borraba el correo y el telefono.
            cliente: null,
            cargando: false,
            guardando: false,
            problemas: [],
        };
    },
    computed: {
        titulo() {
            return this.orderId ? `Detalle del pedido #${this.orderId}` : "Detalle del pedido";
        },
        sePuedeGuardar() {
            if (this.cargando || this.guardando || this.bloqueado) return false;
            if (!this.cliente) return false;

            // Con las lineas congeladas lo unico que queda por guardar es el
            // rotulo. Y si tampoco lo ha tocado, no hay nada que guardar: el
            // boton encendido habria confirmado un guardado que no ocurre.
            if (!this.lineasEditables) {
                return (
                    this.packageContent !== null &&
                    this.packageContent !== this.packageContentOriginal
                );
            }

            // Vaciar un pedido no es editarlo: para eso esta anular, que
            // conserva el historico. Pero uno que nacio sin lineas —un
            // encargo logistico— puede seguir sin ellas.
            return this.items.length > 0 || !this.teniaLineas;
        },
    },
    /**
     * La carga va en `created()` y no en un watcher de `orderId`: el listado
     * monta el componente con `:key`, cada apertura es una instancia nueva y
     * un watcher sin `immediate` no llegaria a correr.
     */
    created() {
        this.cargarCatalogos();

        if (this.orderId) this.cargar();
    },
    methods: {
        /** Solo hace falta saber si este usuario puede tocar precios. */
        cargarCatalogos() {
            this.$http
                .get("/orders/channels")
                .then(r => {
                    this.puedeEditarPrecio = !!((r.data || {}).can_edit_prices);
                })
                .catch(() => {
                    // Sin el permiso confirmado se asume que NO: el servidor
                    // manda igual, y dejar editar para que luego lo ignore
                    // seria enseñar un precio que no se va a cobrar.
                    this.puedeEditarPrecio = false;
                });
        },

        cargar() {
            this.cargando = true;
            this.problemas = [];

            this.$http
                .get(`/orders/record/${this.orderId}`)
                .then(r => {
                    const d = r.data || {};

                    this.lineasEditables = d.lines_editable !== false;

                    if (!d.editable) {
                        this.bloqueado = true;
                        this.problemas = [
                            "Este pedido ya no se puede editar: su estado es final.",
                        ];
                    }

                    this.channelId = d.channel_id;
                    this.cliente = d.customer || {};

                    // El disponible llega sin valor a proposito: el que
                    // devuelve el buscador incluye lo que este mismo pedido ya
                    // tiene reservado, asi que pintarlo aqui diria
                    // «disponible 0» sobre algo que el pedido ya tiene cogido.
                    this.items = (d.items || []).map(l => ({
                        key: `${l.item_id}:${l.variant_id || ""}`,
                        item_id: l.item_id,
                        variant_id: l.variant_id,
                        name: l.name,
                        code: l.code,
                        available: undefined,
                        quantity: Number(l.quantity || 1),
                        unit_price: Number(l.unit_price || 0),
                        discount: Number(l.discount || 0),
                    }));

                    this.teniaLineas = this.items.length > 0;

                    this.packageContent =
                        d.package_content === null || d.package_content === undefined
                            ? null
                            : String(d.package_content);
                    this.packageContentOriginal = this.packageContent;
                })
                .catch(() => {
                    this.problemas = ["No se pudo cargar el detalle del pedido."];
                })
                .then(() => {
                    this.cargando = false;
                });
        },

        cerrar() {
            this.$emit("update:showDialog", false);
        },

        guardar() {
            this.guardando = true;
            this.problemas = [];

            // Con las lineas congeladas no hay nada que mandar a `actualizar`:
            // reescribiria el bloque `customer` para no cambiar ni una linea.
            const lineas = this.lineasEditables
                ? this.$http.post(`/orders/${this.orderId}/actualizar`, {
                    // El cliente vuelve tal como llego: este editor no lo
                    // pregunta, pero el endpoint reescribe el bloque entero.
                    customer: {
                        name: this.cliente.name,
                        document_number: this.cliente.document_number || null,
                        phone: this.cliente.phone || null,
                        email: this.cliente.email || null,
                    },
                    items: this.items.map(l => ({
                        item_id: l.item_id,
                        variant_id: l.variant_id,
                        quantity: l.quantity,
                        unit_price: l.unit_price,
                        discount: l.discount || 0,
                    })),
                  })
                : Promise.resolve({ data: { message: "Rótulo actualizado." } });

            lineas
                .then(r => {
                    // El texto del bulto vive en el envio y se guarda aparte.
                    // Solo si cambio: un POST en cada guardado escribiria una
                    // linea en la bitacora del envio sin que nadie lo tocara.
                    if (
                        this.packageContent === null ||
                        this.packageContent === this.packageContentOriginal
                    ) {
                        return r;
                    }

                    return this.$http
                        .post(`/orders/${this.orderId}/envio/contenido`, {
                            package_content: this.packageContent,
                        })
                        .then(() => {
                            this.packageContentOriginal = this.packageContent;
                            return r;
                        })
                        .catch(e => {
                            // Los productos SI se guardaron: el aviso tiene que
                            // decir exactamente que quedo fuera, o el operador
                            // creera que se perdio todo.
                            const err = (e.response && e.response.data) || {};
                            this.$message({
                                type: "warning",
                                duration: 9000,
                                message:
                                    "Los productos se guardaron, pero el texto del rótulo no: " +
                                    (err.message || "revísalo desde el envío."),
                            });
                            return r;
                        });
                })
                .then(r => {
                    const d = (r && r.data) || {};
                    this.$message.success(d.message || "Detalle guardado.");
                    this.$emit("saved", this.orderId);
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    const porCampo = d.errors ? Object.values(d.errors).map(x => x[0]) : null;
                    // El servidor manda TODOS los problemas de stock juntos.
                    this.problemas =
                        d.problemas || porCampo || [d.message || "No se pudo guardar el detalle."];
                })
                .then(() => {
                    this.guardando = false;
                });
        },
    },
};
</script>

<style scoped>
.od {
    --ink: #0f172a;
    --line: #e5e7eb;
    --muted: #6b7280;
    color: var(--ink);
}
.od-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 12px;
    padding: 11px 14px;
    margin-bottom: 14px;
    font-size: 13px;
    line-height: 1.5;
}
.od-alert ul {
    margin: 6px 0 0;
    padding-left: 18px;
}
.od-loading {
    color: var(--muted);
    font-size: 13px;
    padding: 18px 0;
}
.od-frozen {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    border-radius: 12px;
    padding: 10px 13px;
    margin-bottom: 14px;
    font-size: 12.5px;
    line-height: 1.5;
}
.od-sec {
    font-size: 15px;
    font-weight: 800;
    margin: 0 0 2px;
    letter-spacing: -0.01em;
    color: var(--ink);
}
.od-sec--2 {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}
.od-hint {
    font-size: 12.5px;
    color: var(--muted);
    margin: 0 0 10px;
    line-height: 1.45;
}
.od-hint--solo {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}
</style>
