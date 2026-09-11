<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="titulo"
        top="5vh"
        width="70%"
        @close="cerrar"
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
                <el-input
                    v-model="form.customer.document_number"
                    placeholder="DNI o RUC"
                    maxlength="11"
                    @input="alEscribirDocumento"
                    @keyup.enter.native="buscarCliente(true)"
                >
                    <el-button
                        slot="append"
                        icon="el-icon-search"
                        :loading="buscandoCliente"
                        @click="buscarCliente(true)"
                    ></el-button>
                </el-input>
                <small class="mo-hint" v-if="origenCliente">{{ origenCliente }}</small>
                <small class="mo-hint" v-else>
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
        <h4 class="mo-sec">Del catálogo</h4>
        <p class="mo-hint">
            Lo que existe en el sistema se busca aquí. Lo que no, se escribe a
            mano más abajo.
        </p>
        <!-- En preparacion el pedido ya tiene stock comprometido y casi
             siempre el rotulo impreso: cambiar los productos dejaria una
             etiqueta que dice una cosa y una caja que lleva otra. Los datos
             del cliente si se corrigen. -->
        <div v-if="!lineasEditables" class="mo-frozen">
            Este pedido ya está en preparación: los productos quedaron fijados.
            Puedes corregir los datos del cliente. Si hay que cambiar el
            contenido, anúlalo y crea uno nuevo.
        </div>
        <div class="mo-row">
            <div class="mo-field">
                <el-select
                    v-model="buscado"
                    filterable
                    remote
                    reserve-keyword
                    clearable
                    :disabled="!lineasEditables"
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

                    <!-- Lo que no esta en el catalogo se CREA, no se escribe
                         suelto: una linea sin producto no se puede facturar
                         —`sale_note_items.item_id` es NOT NULL— y dejaria el
                         pedido cobrado y sin manera de documentarlo. -->
                    <div v-if="puedeCrear" slot="empty" class="mo-crear">
                        <p>«{{ termino }}» no está en el catálogo.</p>
                        <el-button
                            size="mini"
                            type="primary"
                            plain
                            :loading="creando"
                            @click="crearProducto"
                            >Crearlo y agregarlo</el-button
                        >
                        <small>Se crea como servicio: no controla stock.</small>
                    </div>
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
                        <th v-if="puedeEditarPrecio" class="num">Descuento</th>
                        <th class="num">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!form.items.length">
                        <td :colspan="puedeEditarPrecio ? 7 : 6" class="mo-empty">Todavía no hay productos. Búscalos arriba.</td>
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
                            <el-input-number v-model="l.quantity" :min="1" :step="1" size="mini" controls-position="right" :disabled="!lineasEditables"></el-input-number>
                        </td>
                        <td class="num">
                            <el-input-number
                                v-if="puedeEditarPrecio"
                                v-model="l.unit_price"
                                :min="0"
                                :precision="2"
                                size="mini"
                                controls-position="right"
                                :disabled="!lineasEditables"
                            ></el-input-number>
                            <span v-else :title="'No tienes permiso para cambiar precios: se cobra el del catálogo.'">
                                {{ money(l.unit_price) }}
                            </span>
                        </td>
                        <td v-if="puedeEditarPrecio" class="num">
                            <el-input-number
                                v-model="l.discount"
                                :min="0"
                                :max="l.quantity * l.unit_price"
                                :precision="2"
                                size="mini"
                                controls-position="right"
                                :disabled="!lineasEditables"
                            ></el-input-number>
                        </td>
                        <td class="num mo-sub">S/ {{ money(neto(l)) }}</td>
                        <td class="num">
                            <el-button type="text" class="mo-del" :disabled="!lineasEditables" @click="quitar(i)">
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Segunda forma de agregar, en la MISMA ficha. NO es una linea de
             venta: es el texto que se imprime en el rotulo para que la agencia
             sepa que lleva la caja. No tiene precio, no mueve stock y no se
             factura, y por eso vive en el envio y no en `orders.items`. Antes
             habia que salir a la pantalla de Envios para escribirlo. -->
        <div v-if="packageContent !== null" class="mo-libre">
            <h4 class="mo-sec">Escrito a mano</h4>
            <p class="mo-hint">
                Un renglón por cosa. Se imprime en el rótulo del envío: no suma
                al total ni descuenta stock.
            </p>
            <el-input
                v-model="packageContent"
                type="textarea"
                :rows="4"
                :disabled="!lineasEditables"
                placeholder="2 polos talla M&#10;1 gorra azul"
            ></el-input>
        </div>

        <div class="mo-total">
            <span v-if="descuentoTotal > 0" class="mo-desc">
                Descuento aplicado: −S/ {{ money(descuentoTotal) }}
            </span>
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
    watch: {
        /**
         * Sustituye al evento `@open` del dialogo, que aqui NO se disparaba.
         *
         * «Editar pedido» cambia `manualOrderId` y `showManualDialog` en el
         * mismo tick. Como el componente lleva `:key="manualOrderId"`, Vue lo
         * REMONTA, y el dialogo nace con `visible` ya en true. Element UI emite
         * `open` unicamente desde el watcher de `visible` —su `mounted()` llama
         * al metodo interno `open()`, que abre el overlay pero no emite nada—,
         * y un watcher sin `immediate` no corre cuando el valor nace true.
         *
         * Resultado: `abrir()` no se ejecutaba, y con el ni la carga de canales
         * ni `cargar()`. El formulario salia en blanco. Con «Nuevo pedido» no
         * pasaba porque ahi el componente ya estaba montado y `visible` SI
         * cambiaba de false a true.
         */
        showDialog: {
            immediate: true,
            handler(val) {
                if (val) this.abrir();
            },
        },
    },
    data() {
        return {
            canales: [],
            // El servidor decide; esto solo evita ofrecer algo que va a ignorar.
            puedeEditarPrecio: false,
            // Distinto de `editable`: en preparacion se corrige el cliente
            // pero no los productos. Lo manda el servidor en record().
            lineasEditables: true,
            // Texto del bulto. `null` = este pedido no tiene envio donde
            // escribirlo, y entonces la seccion no se pinta.
            packageContent: null,
            packageContentOriginal: null,
            opciones: [],
            buscado: null,
            buscando: false,
            // Lo ultimo tecleado en el buscador: hace falta para ofrecer el
            // alta rapida con ese nombre.
            termino: "",
            creando: false,
            guardando: false,
            cargando: false,
            problemas: [],
            buscandoCliente: false,
            origenCliente: "",
            timerDoc: null,
            form: this.formVacio(),
        };
    },
    computed: {
        /** ¿Tiene sentido ofrecer crear el producto con lo tecleado? */
        puedeCrear() {
            return (
                this.lineasEditables &&
                !this.buscando &&
                !this.opciones.length &&
                (this.termino || "").trim().length >= 3
            );
        },
        editando() {
            return !!this.orderId;
        },
        titulo() {
            return this.editando ? `Editar pedido #${this.orderId}` : "Nuevo pedido manual";
        },
        total() {
            return this.form.items.reduce((a, l) => a + this.neto(l), 0);
        },
        descuentoTotal() {
            return this.form.items.reduce(
                (a, l) => a + Math.min(Number(l.discount || 0), this.bruto(l)),
                0
            );
        },
        /**
         * Al ALTA hace falta al menos un producto: un pedido nuevo sin nada no
         * describe una venta.
         *
         * Editando no. Un encargo logistico nace sin lineas —es un envio, no
         * una venta— y desde que el contenido escrito a mano se edita en esta
         * misma ficha, exigir un producto del catalogo dejaba el boton
         * bloqueado justo cuando lo unico que se estaba escribiendo era el
         * contenido del paquete. Tambien impedia corregir solo el cliente.
         */
        sePuedeGuardar() {
            if (!this.form.channel_id) return false;
            if (!(this.form.customer.name || "").trim()) return false;

            return this.editando ? true : this.form.items.length > 0;
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
            this.origenCliente = "";

            this.$http.get("/orders/channels").then(r => {
                const d = r.data || {};
                this.canales = d.channels || [];
                this.puedeEditarPrecio = !!d.can_edit_prices;
                // Un solo canal activo: no tiene sentido preguntar.
                if (!this.editando && this.canales.length === 1) {
                    this.form.channel_id = this.canales[0].id;
                }
            });

            // En un alta todavia no hay envio: el texto del bulto se escribe
            // despues, al configurar el envio o reabriendo el pedido.
            this.packageContent = null;
            this.packageContentOriginal = null;

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

                    this.lineasEditables = d.lines_editable !== false;

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
                        discount: Number(l.discount || 0),
                    }));

                    // `null` cuando el pedido no tiene envio: entonces no hay
                    // donde escribir el texto y la seccion no se pinta.
                    this.packageContent = d.package_content === null || d.package_content === undefined
                        ? null
                        : String(d.package_content);
                    this.packageContentOriginal = this.packageContent;
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

        // ── Cliente ───────────────────────────────────────────────────
        /**
         * Busca sola en cuanto el documento alcanza largo de DNI (8) o RUC (11).
         *
         * Con espera porque el operador teclea: sin ella, escribir un RUC
         * dispararia once consultas y las diez primeras se pagan para nada.
         */
        alEscribirDocumento() {
            this.origenCliente = "";
            clearTimeout(this.timerDoc);

            const doc = (this.form.customer.document_number || "").replace(/\D+/g, "");
            if (doc.length !== 8 && doc.length !== 11) return;

            this.timerDoc = setTimeout(() => this.buscarCliente(false), 450);
        },
        /**
         * `manual` = lo pidió el operador con el botón, y entonces sí se le
         * responde aunque no haya nada. En la búsqueda automática se calla:
         * el cliente nuevo es un caso normal, no un error que avisar.
         */
        buscarCliente(manual) {
            const doc = (this.form.customer.document_number || "").replace(/\D+/g, "");

            if (!doc) {
                if (manual) this.$message.warning("Ingresa el documento a buscar.");
                return;
            }

            this.buscandoCliente = true;
            this.$http
                .get("/orders/search-customer", { params: { document_number: doc } })
                .then(r => {
                    const d = r.data || {};

                    if (!d.found) {
                        this.origenCliente = "";
                        if (manual) {
                            this.$message.info(
                                d.message || "Sin datos para ese documento: escríbelos a mano."
                            );
                        }
                        return;
                    }

                    this.aplicarCliente(d);
                })
                .catch(() => {
                    if (manual) this.$message.error("No se pudo consultar el documento.");
                })
                .then(() => {
                    this.buscandoCliente = false;
                });
        },
        /**
         * Nunca pisa lo que el operador ya escribió: si corrigió el nombre que
         * devuelve el servicio, esa corrección vale más que el servicio.
         */
        aplicarCliente(d) {
            const c = d.customer || {};

            ["name", "phone", "email"].forEach(k => {
                if (c[k] && !(this.form.customer[k] || "").trim()) {
                    this.form.customer[k] = c[k];
                }
            });

            this.origenCliente = {
                cartera: "Cliente de tu cartera.",
                dni: "Datos traídos de RENIEC.",
                ruc: "Datos traídos de SUNAT.",
            }[d.source] || "";
        },

        // ── Buscador ──────────────────────────────────────────────────
        buscarProductos(q) {
            const termino = typeof q === "string" ? q : "";
            this.termino = termino;

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
                discount: 0,
            });
        },
        /**
         * Crea el producto que falta y lo agrega a la linea.
         *
         * Pide el precio antes de crearlo: un producto en el catalogo con
         * precio 0 es una trampa para la siguiente venta, que lo encontraria y
         * lo cobraria a nada.
         *
         * El servidor devuelve el item con la MISMA forma que el buscador, asi
         * que a partir de ahi la linea es una mas: se reserva igual, se factura
         * igual y no arrastra ningun caso especial.
         */
        crearProducto() {
            const nombre = (this.termino || "").trim();
            if (nombre.length < 3) return;

            this.$prompt(
                'Precio de venta de «' + nombre + '» (S/)',
                "Crear producto",
                {
                    confirmButtonText: "Crear",
                    cancelButtonText: "Cancelar",
                    inputPlaceholder: "0.00",
                    inputValidator: v =>
                        (v !== null && v !== "" && !isNaN(Number(v)) && Number(v) >= 0) ||
                        "Escribe un precio válido.",
                }
            )
                .then(({ value }) => {
                    this.creando = true;

                    return this.$http
                        .post("/orders/producto-rapido", {
                            nombre: nombre,
                            precio: Number(value),
                        })
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
                        discount: l.discount || 0,
                    })),
                })
                .then(r => {
                    const d = r.data || {};
                    // El texto del bulto vive en el envio y se guarda aparte.
                    // Solo si cambio: un POST por cada guardado escribiria una
                    // linea en la bitacora del envio sin que nadie lo tocara.
                    if (
                        this.editando
                        && this.packageContent !== null
                        && this.packageContent !== this.packageContentOriginal
                    ) {
                        return this.$http
                            .post(`/orders/${this.orderId}/envio/contenido`, {
                                package_content: this.packageContent,
                            })
                            .then(() => {
                                this.packageContentOriginal = this.packageContent;
                                return r;
                            })
                            .catch(e => {
                                // El pedido SI se guardo: el aviso tiene que
                                // decir exactamente que quedo fuera, o el
                                // operador creera que se perdio todo.
                                this.$message.warning(
                                    "El pedido se guardó, pero el contenido del paquete no: "
                                        + (((e.response && e.response.data) || {}).message
                                            || "revisa el envío.")
                                );
                                return r;
                            });
                    }

                    return r;
                })
                .then(r => {
                    const d = (r && r.data) || {};
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
        bruto(l) {
            return Number(l.quantity || 0) * Number(l.unit_price || 0);
        },
        /**
         * Lo que se cobra por la linea. El descuento se limita al importe: uno
         * mayor seria un pedido que devuelve dinero, y eso es una nota de
         * credito, no un descuento. El servidor aplica el mismo tope.
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
/* Alta rapida cuando el buscador no encuentra nada. */
.mo-crear {
    padding: 14px 16px;
    text-align: center;
}
.mo-crear p {
    margin: 0 0 8px;
    color: #475569;
    font-size: 13px;
}
.mo-crear small {
    display: block;
    margin-top: 8px;
    color: #94a3b8;
    font-size: 11.5px;
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
.mo-frozen {
    margin-bottom: 10px;
    padding: 9px 12px;
    border-radius: 6px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
    font-size: 12.5px;
    line-height: 1.5;
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
/* Aclaracion bajo un titulo de seccion. Distinta de `.mo-sub`, que es el
   subtotal de una linea y va en negrita. */
.mo-hint {
    margin: -2px 0 8px;
    font-size: 12px;
    color: #64748b;
    line-height: 1.45;
}
.mo-libre {
    margin-top: 14px;
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
.mo-desc {
    color: #b45309;
    font-size: 13px;
    margin-right: auto;
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
