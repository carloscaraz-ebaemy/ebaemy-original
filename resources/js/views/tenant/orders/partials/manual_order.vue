<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="titulo"
        top="5vh"
        width="70%"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="problemas.length" class="mo-alert">
            <strong>{{ editando ? "No se pudo guardar:" : "No se pudo crear el pedido:" }}</strong>
            <ul>
                <li v-for="(p, i) in problemas" :key="i">{{ p }}</li>
            </ul>
        </div>

        <!-- ── Origen ────────────────────────────────────────────────── -->
        <div class="mo-row">
            <div class="mo-field">
                <label>Canal de venta <span class="mo-req">*</span></label>
                <el-select v-model="form.channel_id" placeholder="¿De dónde vino el pedido?" @change="buscarProductos">
                    <el-option
                        v-for="c in canales"
                        :key="c.id"
                        :label="c.name"
                        :value="c.id"
                    ></el-option>
                </el-select>
                <small class="mo-hint">Decide el almacén contra el que se descuenta el stock.</small>
            </div>
        </div>

        <!-- ── Cliente ───────────────────────────────────────────────── -->
        <h4 class="mo-sec">Cliente</h4>
        <div class="mo-row">
            <div class="mo-field mo-sm">
                <label>Documento</label>
                <el-input v-model="form.customer.document_number" placeholder="DNI o RUC" maxlength="11"></el-input>
                <small class="mo-hint">
                    Con documento, el pedido queda enlazado al cliente en tu cartera.
                    Sin él se crea igual, pero suelto.
                </small>
            </div>
            <div class="mo-field">
                <label>Nombre <span class="mo-req">*</span></label>
                <el-input v-model="form.customer.name" placeholder="Nombre o razón social"></el-input>
            </div>
            <div class="mo-field mo-sm">
                <label>Teléfono</label>
                <el-input v-model="form.customer.phone"></el-input>
            </div>
            <div class="mo-field mo-sm">
                <label>Correo</label>
                <el-input v-model="form.customer.email"></el-input>
            </div>
        </div>

        <!-- ── Productos ─────────────────────────────────────────────── -->
        <h4 class="mo-sec">Productos</h4>
        <div class="mo-row">
            <div class="mo-field">
                <el-select
                    v-model="buscado"
                    filterable
                    remote
                    reserve-keyword
                    clearable
                    placeholder="Busca por nombre o código y elige para agregarlo"
                    :remote-method="buscarProductos"
                    :loading="buscando"
                    @change="agregar"
                >
                    <el-option
                        v-for="op in opciones"
                        :key="op.key"
                        :label="op.label"
                        :value="op.key"
                        :disabled="op.agotado"
                    >
                        <span>{{ op.label }}</span>
                        <span class="mo-op-stock" :class="{ 'is-off': op.agotado }">{{ op.stockText }}</span>
                    </el-option>
                </el-select>
            </div>
        </div>

        <div class="mo-scroll">
            <table class="mo-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="num">Disponible</th>
                        <th class="num">Cantidad</th>
                        <th class="num">Precio</th>
                        <th class="num">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!form.items.length">
                        <td colspan="6" class="mo-empty">Todavía no hay productos. Búscalos arriba.</td>
                    </tr>
                    <tr v-for="(l, i) in form.items" :key="l.key">
                        <td>
                            {{ l.name }}
                            <div v-if="l.code" class="mo-code">{{ l.code }}</div>
                        </td>
                        <td class="num">
                            <span v-if="l.available === null" class="mo-muted">sin control</span>
                            <span v-else :class="{ 'mo-over': l.quantity > l.available }">{{ l.available }}</span>
                        </td>
                        <td class="num">
                            <el-input-number v-model="l.quantity" :min="1" :step="1" size="mini" controls-position="right"></el-input-number>
                        </td>
                        <td class="num">
                            <el-input-number v-model="l.unit_price" :min="0" :precision="2" size="mini" controls-position="right"></el-input-number>
                        </td>
                        <td class="num mo-sub">S/ {{ money(l.quantity * l.unit_price) }}</td>
                        <td class="num">
                            <el-button type="text" class="mo-del" @click="quitar(i)">
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mo-total">
            <span>Total</span>
            <strong>S/ {{ money(total) }}</strong>
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <el-button
                type="primary"
                :loading="guardando"
                :disabled="!sePuedeGuardar || cargando"
                @click="guardar"
            >
                {{ editando ? "Guardar cambios" : "Crear pedido" }}
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Alta manual de un pedido.
 *
 * Existe porque no todo pedido llega por marketplace o ecommerce: hay clientes
 * que piden por WhatsApp, por teléfono o en persona, y hasta ahora el endpoint
 * `orders/manual` no tenía ninguna pantalla detrás.
 *
 * ── Lo que esta pantalla NO hace, a propósito ─────────────────────────────
 *
 * No calcula stock ni resuelve el cliente: los dos los decide el servidor.
 * El buscador muestra el disponible que devuelve `orders/search-items`, que sale
 * de la MISMA función que valida el guardado, así que lo que se ve aquí es lo
 * que el alta va a aceptar. Y el cliente se enlaza a la cartera por documento
 * en `Person::resolveCustomer()`, no aquí.
 *
 * Tampoco pide los datos de envío: eso ya tiene su flujo propio con «Configurar
 * envío» desde la fila del pedido, y duplicarlo aquí crearía una segunda forma
 * de capturarlos.
 *
 * Fuera de esta primera versión: descuentos por línea y permisos para editar
 * el precio. El precio se puede cambiar, sin control de permisos todavía.
 */
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        // null = alta. Con id, el mismo formulario edita ese pedido: es la
        // misma informacion y separarlos daria dos pantallas que divergen.
        orderId: { type: Number, default: null },
    },
    data() {
        return {
            canales: [],
            opciones: [],
            buscado: null,
            buscando: false,
            guardando: false,
            cargando: false,
            problemas: [],
            form: this.formVacio(),
        };
    },
    computed: {
        editando() {
            return !!this.orderId;
        },
        titulo() {
            return this.editando ? `Editar pedido #${this.orderId}` : "Nuevo pedido manual";
        },
        total() {
            return this.form.items.reduce(
                (a, l) => a + Number(l.quantity || 0) * Number(l.unit_price || 0),
                0
            );
        },
        sePuedeGuardar() {
            return (
                !!this.form.channel_id &&
                !!(this.form.customer.name || "").trim() &&
                this.form.items.length > 0
            );
        },
    },
    methods: {
        formVacio() {
            return {
                channel_id: null,
                customer: { name: "", document_number: "", phone: "", email: "" },
                items: [],
            };
        },
        abrir() {
            this.form = this.formVacio();
            this.opciones = [];
            this.problemas = [];
            this.buscado = null;

            this.$http.get("/orders/channels").then(r => {
                this.canales = r.data || [];
                // Un solo canal activo: no tiene sentido preguntar.
                if (!this.editando && this.canales.length === 1) {
                    this.form.channel_id = this.canales[0].id;
                }
            });

            if (this.editando) this.cargar();
        },
        /**
         * Trae el pedido para editarlo.
         *
         * El disponible de cada linea llega vacio a proposito: el que muestra el
         * buscador incluye lo que ESTE pedido ya tiene reservado, asi que
         * pintarlo aqui diria «disponible 0» en un producto que el pedido ya
         * tiene cogido. Al servidor no le hace falta y al operador le confunde.
         */
        cargar() {
            this.cargando = true;
            this.$http
                .get(`/orders/record/${this.orderId}`)
                .then(r => {
                    const d = r.data || {};

                    if (!d.editable) {
                        this.problemas = [
                            "Este pedido ya no se puede editar: su estado es final.",
                        ];
                    }

                    this.form.channel_id = d.channel_id;
                    this.form.customer = Object.assign(this.formVacio().customer, d.customer || {});
                    this.form.items = (d.items || []).map(l => ({
                        key: `${l.item_id}:${l.variant_id || ""}`,
                        item_id: l.item_id,
                        variant_id: l.variant_id,
                        name: l.name,
                        code: l.code,
                        available: null,
                        quantity: Number(l.quantity || 1),
                        unit_price: Number(l.unit_price || 0),
                    }));
                })
                .catch(() => {
                    this.problemas = ["No se pudo cargar el pedido."];
                })
                .then(() => {
                    this.cargando = false;
                });
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },

        // ── Buscador ──────────────────────────────────────────────────
        buscarProductos(q) {
            const termino = typeof q === "string" ? q : "";
            if (termino.length < 2) {
                this.opciones = [];
                return;
            }

            this.buscando = true;
            this.$http
                .get("/orders/search-items", {
                    params: { q: termino, channel_id: this.form.channel_id },
                })
                .then(r => {
                    this.opciones = this.aOpciones(r.data || []);
                })
                .catch(() => {
                    this.opciones = [];
                })
                .then(() => {
                    this.buscando = false;
                });
        },
        /**
         * Un producto con variantes se ofrece SOLO por sus variantes: el stock
         * vive ahí, y dejar elegir el padre sería vender algo que no existe
         * como tal.
         */
        aOpciones(items) {
            const out = [];

            items.forEach(it => {
                if (it.variants && it.variants.length) {
                    it.variants.forEach(v => {
                        out.push({
                            key: `${it.id}:${v.id}`,
                            item_id: it.id,
                            variant_id: v.id,
                            label: `${it.name} — ${v.name}`,
                            code: it.code,
                            price: v.price,
                            available: v.available,
                            stockText: this.stockText(v.available),
                            agotado: v.available !== null && v.available <= 0,
                        });
                    });
                    return;
                }

                out.push({
                    key: `${it.id}:`,
                    item_id: it.id,
                    variant_id: null,
                    label: it.name,
                    code: it.code,
                    price: it.price,
                    available: it.available,
                    stockText: this.stockText(it.available),
                    agotado: it.available !== null && it.available <= 0,
                });
            });

            return out;
        },
        stockText(disp) {
            if (disp === null || disp === undefined) return "sin control";
            return disp > 0 ? `${disp} disp.` : "agotado";
        },
        agregar(key) {
            if (!key) return;

            const op = this.opciones.find(o => o.key === key);
            this.buscado = null;
            if (!op) return;

            const ya = this.form.items.find(l => l.key === key);
            if (ya) {
                ya.quantity += 1;
                return;
            }

            this.form.items.push({
                key: op.key,
                item_id: op.item_id,
                variant_id: op.variant_id,
                name: op.label,
                code: op.code,
                available: op.available,
                quantity: 1,
                unit_price: Number(op.price || 0),
            });
        },
        quitar(i) {
            this.form.items.splice(i, 1);
        },

        // ── Guardar ───────────────────────────────────────────────────
        guardar() {
            this.guardando = true;
            this.problemas = [];

            const url = this.editando
                ? `/orders/${this.orderId}/actualizar`
                : "/orders/manual";

            this.$http
                .post(url, {
                    channel_id: this.form.channel_id,
                    customer: this.form.customer,
                    items: this.form.items.map(l => ({
                        item_id: l.item_id,
                        variant_id: l.variant_id,
                        quantity: l.quantity,
                        unit_price: l.unit_price,
                    })),
                })
                .then(r => {
                    const d = r.data || {};
                    this.$message.success(d.message || "Pedido guardado.");

                    // Decirlo importa: un pedido sin cliente en la cartera no
                    // sale en su historial ni se le puede facturar directo.
                    if (!this.editando && !d.person) {
                        this.$message({
                            type: "warning",
                            duration: 7000,
                            message:
                                "El pedido no quedó enlazado a un cliente de tu cartera: " +
                                "faltó el documento. Puedes completarlo después.",
                        });
                    }

                    this.$emit("created");
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    // El servidor manda TODOS los problemas de stock juntos.
                    this.problemas = d.problemas || [d.message || "No se pudo guardar el pedido."];
                })
                .then(() => {
                    this.guardando = false;
                });
        },
        money(v) {
            return Number(v || 0).toLocaleString("es-PE", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    },
};
</script>

<style scoped>
.mo-sec {
    margin: 18px 0 8px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}
.mo-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.mo-field {
    flex: 1 1 220px;
    min-width: 0;
}
.mo-field.mo-sm {
    flex: 0 1 170px;
}
.mo-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    color: #334155;
}
.mo-req {
    color: #b91c1c;
}
.mo-hint {
    display: block;
    margin-top: 3px;
    font-size: 11px;
    color: #64748b;
    line-height: 1.4;
}
.mo-op-stock {
    float: right;
    margin-left: 14px;
    font-size: 11.5px;
    color: #64748b;
}
.mo-op-stock.is-off {
    color: #b91c1c;
}
.mo-scroll {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    margin-top: 10px;
}
.mo-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.mo-table th {
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    background: #f8fafc;
    padding: 8px 10px;
    white-space: nowrap;
}
.mo-table td {
    padding: 8px 10px;
    border-top: 1px solid #eef2f6;
    vertical-align: middle;
}
.mo-table .num {
    text-align: right;
    white-space: nowrap;
}
.mo-code {
    font-size: 11px;
    color: #94a3b8;
}
.mo-empty {
    text-align: center;
    color: #94a3b8;
    padding: 18px;
}
.mo-muted {
    color: #94a3b8;
}
.mo-over {
    color: #b91c1c;
    font-weight: 700;
}
.mo-sub {
    font-weight: 600;
}
.mo-del {
    color: #b91c1c;
}
.mo-total {
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    gap: 14px;
    margin-top: 12px;
    font-size: 16px;
}
.mo-total strong {
    font-size: 20px;
}
.mo-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 4px;
    padding: 10px 14px;
    margin-bottom: 12px;
    font-size: 13px;
}
.mo-alert ul {
    margin: 6px 0 0;
    padding-left: 18px;
}
</style>
