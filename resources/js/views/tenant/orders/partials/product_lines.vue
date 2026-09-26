<template>
    <!-- Las lineas de producto de un pedido: buscador, tabla y total.

         Vive aparte porque lo usan DOS pantallas —el alta manual completa y
         el modal de «solo el detalle» que se abre desde la caja del listado—
         y son el mismo buscador contra el mismo endpoint. Copiarlo significaba
         que el dia que cambie la forma de contar el stock, una de las dos
         pantallas se quedaria contandolo como antes.

         Contrato: `value` son las lineas (v-model), y se emite `input` con el
         array nuevo. El padre decide si se pueden tocar y si se puede cambiar
         el precio; aqui solo se obedece. -->
    <div class="pl">
        <div class="pl-f">
            <el-select
                v-model="buscado"
                class="pl-sel"
                filterable
                remote
                reserve-keyword
                clearable
                :disabled="disabled"
                :placeholder="placeholder"
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
                    <span class="pl-op-name">{{ op.label }}</span>
                    <span class="pl-op-stock" :class="{ 'is-off': op.agotado }">{{ op.stockText }}</span>
                </el-option>

                <!-- Un fallo de red pintado como «no existe» es un problema
                     que no se arregla buscando otra cosa: se distingue. -->
                <div v-if="errorBusqueda" slot="empty" class="pl-crear">
                    <p class="pl-error">{{ errorBusqueda }}</p>
                    <el-button size="mini" plain @click="buscarProductos(termino)">Reintentar</el-button>
                </div>
                <div v-else-if="puedeCrear" slot="empty" class="pl-crear">
                    <p>«{{ termino }}» no está en el catálogo.</p>
                    <el-button size="mini" type="primary" plain :loading="creando" @click="crearProducto">
                        Crearlo y agregarlo
                    </el-button>
                    <small>Se crea como servicio: no controla stock.</small>
                </div>
            </el-select>
        </div>

        <div class="pl-scroll">
            <table class="pl-table" :class="{ 'is-sin-desc': !canEditPrice }">
                <thead>
                    <tr>
                        <th class="c-prod">Producto</th>
                        <th class="num c-disp">Disp.</th>
                        <th class="num c-cant">Cantidad</th>
                        <th class="num c-prec">Precio</th>
                        <th v-if="canEditPrice" class="num c-desc">Descuento</th>
                        <th class="num c-sub">Subtotal</th>
                        <th class="c-acc"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!lineas.length">
                        <td :colspan="canEditPrice ? 7 : 6" class="pl-empty">
                            Todavía no hay productos. Búscalos arriba.
                        </td>
                    </tr>
                    <tr v-for="(l, i) in lineas" :key="l.key">
                        <td class="c-prod">
                            <div class="pl-name" :title="l.name">{{ l.name }}</div>
                            <div v-if="l.code" class="pl-code">{{ l.code }}</div>
                        </td>
                        <td class="num c-disp">
                            <!-- Tres estados que no se pueden confundir:
                                 `undefined` = no se ha mirado en este almacen,
                                 `null` = el producto no controla stock,
                                 numero = lo que hay. -->
                            <span v-if="l.available === undefined" class="pl-muted" title="Se comprueba al guardar.">—</span>
                            <span v-else-if="l.available === null" class="pl-muted">sin control</span>
                            <span v-else :class="{ 'pl-over': l.quantity > l.available }">{{ l.available }}</span>
                        </td>
                        <td class="num c-cant">
                            <el-input-number
                                v-model="l.quantity"
                                :min="1"
                                :step="1"
                                size="mini"
                                controls-position="right"
                                :disabled="disabled"
                                @change="emitir"
                            ></el-input-number>
                        </td>
                        <td class="num c-prec">
                            <el-input-number
                                v-if="canEditPrice"
                                v-model="l.unit_price"
                                :min="0"
                                :precision="2"
                                size="mini"
                                controls-position="right"
                                :disabled="disabled"
                                @change="emitir"
                            ></el-input-number>
                            <span v-else title="No tienes permiso para cambiar precios: se cobra el del catálogo.">
                                {{ money(l.unit_price) }}
                            </span>
                        </td>
                        <td v-if="canEditPrice" class="num c-desc">
                            <el-input-number
                                v-model="l.discount"
                                :min="0"
                                :max="l.quantity * l.unit_price"
                                :precision="2"
                                size="mini"
                                controls-position="right"
                                :disabled="disabled"
                                @change="emitir"
                            ></el-input-number>
                        </td>
                        <td class="num pl-neto c-sub">S/ {{ money(neto(l)) }}</td>
                        <td class="num c-acc">
                            <el-button type="text" class="pl-del" :disabled="disabled" @click="quitar(i)">
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pl-total">
            <span v-if="descuentoTotal > 0" class="pl-desc">
                Descuento aplicado: −S/ {{ money(descuentoTotal) }}
            </span>
            <span>Total</span>
            <strong>S/ {{ money(total) }}</strong>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        /** Las lineas del pedido. v-model. */
        value: { type: Array, default: () => [] },
        /** Decide el almacen contra el que se mide el disponible. */
        channelId: { default: null },
        disabled: { type: Boolean, default: false },
        canEditPrice: { type: Boolean, default: false },
        placeholder: {
            type: String,
            default: "Busca por nombre o código y elige para agregarlo",
        },
    },
    data() {
        return {
            buscado: null,
            opciones: [],
            termino: "",
            buscando: false,
            errorBusqueda: "",
            creando: false,
            // Teclear rapido lanza varias consultas. Sin este numero, la lenta
            // llega la ultima y deja en pantalla los resultados de media
            // palabra, encima de los buenos.
            peticion: 0,
        };
    },
    computed: {
        /**
         * Se trabaja sobre el array del padre, no sobre una copia.
         *
         * Copiarlo obligaria a re-emitir en cada tecla del `el-input-number` y
         * a reconciliar las dos listas; asi el `v-model` del padre y estas
         * filas son el mismo objeto y no pueden desincronizarse.
         */
        lineas() {
            return this.value || [];
        },
        /** Se ofrece crear lo que no esta, pero no con dos letras sueltas. */
        puedeCrear() {
            return !this.disabled && (this.termino || "").trim().length >= 3;
        },
        descuentoTotal() {
            return this.lineas.reduce(
                (a, l) => a + Math.min(Number(l.discount || 0), this.bruto(l)),
                0
            );
        },
        total() {
            return this.lineas.reduce((a, l) => a + this.neto(l), 0);
        },
    },
    methods: {
        emitir() {
            this.$emit("input", this.lineas);
        },

        buscarProductos(q) {
            const termino = typeof q === "string" ? q : "";
            this.termino = termino;
            this.errorBusqueda = "";

            if (termino.length < 2) {
                this.opciones = [];
                return;
            }

            const peticion = ++this.peticion;
            this.buscando = true;

            this.$http
                .get("/orders/search-items", {
                    params: { q: termino, channel_id: this.channelId },
                })
                .then(r => {
                    if (peticion !== this.peticion) return;
                    this.opciones = this.aOpciones(r.data || []);
                })
                .catch(() => {
                    if (peticion !== this.peticion) return;
                    // Vaciar la lista aqui seria decir «ese producto no
                    // existe» cuando lo que ha pasado es que la consulta
                    // fallo. Son dos problemas distintos y el segundo no se
                    // arregla buscando otra cosa.
                    this.opciones = [];
                    this.errorBusqueda =
                        "No se pudo buscar en el catálogo. Revisa la conexión e inténtalo otra vez.";
                })
                .then(() => {
                    if (peticion === this.peticion) this.buscando = false;
                });
        },

        /**
         * Un producto con variantes se ofrece SOLO por sus variantes: el stock
         * vive ahi, y dejar elegir el padre seria vender algo que no existe
         * como tal.
         */
        aOpciones(items) {
            const out = [];

            items.forEach(it => {
                // Los dados de baja se muestran para explicar por que no
                // estan, no para venderlos. El servidor los rechaza igual.
                const baja = it.active === false;

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
                            stockText: baja ? "dado de baja" : this.stockText(v.available),
                            agotado: baja || (v.available !== null && v.available <= 0),
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
                    stockText: baja ? "dado de baja" : this.stockText(it.available),
                    agotado: baja || (it.available !== null && it.available <= 0),
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

            // Elegir dos veces el mismo producto es pedir dos unidades, no dos
            // lineas iguales que despues hay que sumar a ojo.
            const ya = this.lineas.find(l => l.key === key);
            if (ya) {
                ya.quantity += 1;
                this.emitir();
                return;
            }

            this.lineas.push({
                key: op.key,
                item_id: op.item_id,
                variant_id: op.variant_id,
                name: op.label,
                code: op.code,
                available: op.available,
                quantity: 1,
                unit_price: Number(op.price || 0),
                discount: 0,
            });

            this.emitir();
        },

        quitar(i) {
            this.lineas.splice(i, 1);
            this.emitir();
        },

        /**
         * Crea el producto que falta y lo agrega a la linea.
         *
         * Pide el precio ANTES de crearlo: un producto en el catalogo con
         * precio 0 es una trampa para la siguiente venta, que lo encontraria y
         * lo cobraria a nada.
         *
         * El servidor lo devuelve con la MISMA forma que el buscador, asi que
         * a partir de ahi la linea es una mas: se reserva igual y se factura
         * igual, sin ningun caso especial que arrastrar.
         */
        crearProducto() {
            const nombre = (this.termino || "").trim();
            if (nombre.length < 3) return;

            this.$prompt("Precio de venta de «" + nombre + "» (S/)", "Crear producto", {
                confirmButtonText: "Crear",
                cancelButtonText: "Cancelar",
                inputPlaceholder: "0.00",
                inputValidator: v =>
                    (v !== null && v !== "" && !isNaN(Number(v)) && Number(v) >= 0) ||
                    "Escribe un precio válido.",
            })
                .then(({ value }) => {
                    this.creando = true;

                    return this.$http
                        .post("/orders/producto-rapido", { nombre: nombre, precio: Number(value) })
                        .then(r => {
                            const d = r.data || {};
                            if (!d.success || !d.item) {
                                this.$message.error(d.message || "No se pudo crear.");
                                return;
                            }

                            this.$message.success(d.message);
                            this.opciones = this.aOpciones([d.item]);

                            // Se agrega solo: crearlo y tener que buscarlo otra
                            // vez seria pedir el mismo trabajo dos veces.
                            if (this.opciones.length) this.agregar(this.opciones[0].key);
                        })
                        .catch(e => {
                            const d = (e.response && e.response.data) || {};
                            const porCampo = d.errors
                                ? Object.values(d.errors).map(x => x[0]).join(" ")
                                : null;
                            this.$message.error(porCampo || d.message || "No se pudo crear.");
                        })
                        .then(() => {
                            this.creando = false;
                        });
                })
                .catch(() => {});
        },

        bruto(l) {
            return Number(l.quantity || 0) * Number(l.unit_price || 0);
        },

        /**
         * Lo que se cobra por la linea. El descuento se limita al importe: uno
         * mayor seria un pedido que devuelve dinero, y eso es una nota de
         * credito. El servidor aplica el mismo tope.
         */
        neto(l) {
            return Math.max(0, this.bruto(l) - Math.min(Number(l.discount || 0), this.bruto(l)));
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
.pl {
    --brand: #2563eb;
    --ink: #0f172a;
    --line: #e5e7eb;
    --muted: #6b7280;
    color: var(--ink);
}
.pl-f {
    margin-bottom: 2px;
}
.pl-sel {
    width: 100%;
}

/* Opciones del buscador: el disponible a la derecha, donde se compara. */
.pl-op-stock {
    float: right;
    color: #16a34a;
    font-size: 12px;
    margin-left: 14px;
}
.pl-op-stock.is-off {
    color: #b91c1c;
}
.pl-crear {
    padding: 14px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
}
.pl-crear p {
    margin: 0 0 8px;
}
.pl-crear small {
    display: block;
    margin-top: 7px;
    font-size: 11.5px;
}
.pl-error {
    color: #b91c1c;
}

/* `pl-scroll` porque en movil la tabla no cabe: se desplaza ella, no la
   pagina. Un hijo flex sin min-width empujaria el dialogo entero. */
.pl-scroll {
    margin-top: 12px;
    overflow-x: auto;
    min-width: 0;
}
.pl-table {
    width: 100%;
    /* Tiene que caber lo que hay dentro, no lo que sobra.
       200 producto + 60 disp + 96 cantidad + 104 precio + 104 descuento
       + 84 subtotal + 30 papelera = 678, que entra en el ancho util del
       dialogo (720-760px menos su padding) sin pedir scroll.

       Por debajo de eso la tabla se APLASTABA en vez de desplazarse: las
       columnas de numeros no ceden —llevan un `el-input-number` dentro—
       asi que todo el recorte caia sobre la del producto, que acababa
       partiendo las palabras por la mitad («Arbol Cerez / o AMA / RILLO»).
       Cuando no quepa —en movil—, se desplaza `pl-scroll`. */
    min-width: 678px;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 13px;
}
/* Sin permiso de precios no hay columna de descuento: 104px menos. */
.pl-table.is-sin-desc {
    min-width: 574px;
}

.pl-table .c-prod { width: auto; min-width: 200px; }
.pl-table .c-disp { width: 60px; }
.pl-table .c-cant { width: 96px; }
.pl-table .c-prec { width: 104px; }
.pl-table .c-desc { width: 104px; }
.pl-table .c-sub  { width: 84px; }
.pl-table .c-acc  { width: 30px; }

/* El nombre puede ocupar dos lineas, pero no partir una palabra: cortar
   «AMARILLO» en «AMA / RILLO» hace ilegible justo el dato que se lee. */
.pl-name {
    word-break: normal;
    overflow-wrap: break-word;
    line-height: 1.35;
}

/* Los controles de Element traen 130px propios y no caben en la celda. */
.pl-table .el-input-number {
    width: 100%;
}
/* Y su relleno esta pensado para esos 130px: en 96 recortaba el numero. */
.pl-table .el-input-number ::v-deep .el-input__inner {
    padding-left: 6px;
    padding-right: 26px;
}
.pl-table th,
.pl-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--line);
    text-align: left;
    vertical-align: middle;
}
.pl-table th {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--muted);
    border-bottom-width: 1.5px;
    /* «PRODUCTO» partido en «PROD / UCTO» no es una cabecera, es ruido. */
    white-space: nowrap;
}
.pl-table .num {
    text-align: right;
    white-space: nowrap;
}
/* Con poco sitio, el control se queda en su celda y no la desborda. */
.pl-table td.num {
    padding-left: 6px;
    padding-right: 6px;
}
/* «sin control» no cabe en una linea de 60px, y con `nowrap` se salia de
   la celda por encima de la siguiente. Aqui si puede partirse: son dos
   palabras, y romper entre ellas no hace ilegible ninguna. */
.pl-table td.c-disp {
    white-space: normal;
    font-size: 11.5px;
    line-height: 1.3;
}
.pl-empty {
    text-align: center;
    color: var(--muted);
    padding: 22px 10px;
}
.pl-code {
    font-size: 11.5px;
    color: var(--muted);
    margin-top: 2px;
}
.pl-muted {
    color: var(--muted);
}
/* Pedir mas de lo que hay: se ve antes de guardar, no en el 422. */
.pl-over {
    color: #b91c1c;
    font-weight: 700;
}
.pl-neto {
    font-weight: 600;
}
.pl-del {
    color: #b91c1c;
    padding: 0;
}

.pl-total {
    display: flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1.5px solid var(--line);
    font-size: 14px;
    color: var(--muted);
}
.pl-total strong {
    font-size: 21px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.02em;
}
.pl-desc {
    margin-right: auto;
    color: #16a34a;
    font-size: 12.5px;
}

@media (max-width: 767px) {
    .pl-total strong {
        font-size: 19px;
    }
}
</style>
