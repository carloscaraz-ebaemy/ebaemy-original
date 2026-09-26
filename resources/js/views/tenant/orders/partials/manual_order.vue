<template>
    <!-- Alta manual de pedido, rehecho desde cero.
         Bloques: canal · cliente · destino · productos · guardado.
         El formulario anterior sigue en el historial de este archivo
         (`git show cb9b13d9~1:` esta ruta) como referencia.
         Contrato con orders/index.vue: showDialog (.sync), orderId, created.

         El diseño es el del formulario publico de envios, con sus mismas
         medidas y colores, pero COMPACTADO: alli cada campo ocupa una fila
         porque se llena en un movil y una sola vez en la vida; aqui lo usa un
         operador en pantalla ancha y todo el dia, asi que los cuatro campos
         caben en dos filas y se ve el bloque entero de un vistazo. -->
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="titulo"
        top="6vh"
        width="760px"
        @close="cerrar"
    >
        <div class="mo">
            <!-- Completitud: la misma barra del publico, aqui mas fina porque
                 acompaña, no protagoniza. -->
            <div class="mo-prog" :class="{ 'is-done': pct === 100 }">
                <div class="mo-prog__bar"><span :style="{ width: pct + '%' }"></span></div>
                <small>{{ pct === 100 ? "Datos del cliente completos" : "Datos del cliente " + pct + "% completos" }}</small>
            </div>

            <!-- Lo que el servidor rechazo, arriba y entero. El stock
                 devuelve TODOS los problemas juntos: enseñar solo el primero
                 obliga a guardar una vez por linea para descubrirlos. -->
            <div v-if="problemas.length" class="mo-alert">
                <strong>{{ editando ? "No se pudo guardar:" : "No se pudo crear el pedido:" }}</strong>
                <ul>
                    <li v-for="(p, i) in problemas" :key="i">{{ p }}</li>
                </ul>
            </div>

            <div v-if="cargando" class="mo-loading">
                <i class="el-icon-loading"></i> Cargando el pedido…
            </div>

            <!-- El canal decide el ALMACEN del que sale el stock, asi que va
                 antes de los productos: elegirlo despues cambiaria el
                 disponible de lo que ya esta en la tabla. -->
            <div class="mo-f mo-f--full mo-canal">
                <label class="mo-lbl req">¿Por dónde entró el pedido?</label>
                <el-select
                    v-model="form.channel_id"
                    class="mo-sel"
                    filterable
                    :disabled="editando"
                    placeholder="— Selecciona el canal —"
                    @change="alCambiarCanal"
                >
                    <el-option v-for="c in canales" :key="c.id" :label="c.name" :value="c.id" />
                </el-select>
                <small v-if="editando" class="mo-hint">
                    El canal no se cambia al editar: decide el almacén y el stock ya está reservado en él.
                </small>
            </div>

            <div class="mo-head">
                <h2 class="mo-h">Tus datos</h2>
                <p class="mo-sub">Para saber a nombre de quién va el pedido y cómo avisarle.</p>
            </div>

            <!-- Tipo de documento: chips, no desplegable. Es lo que decide si
                 el nombre se puede consultar o hay que escribirlo. -->
            <label class="mo-lbl">Documento</label>
            <div class="mo-doctypes">
                <label v-for="t in tiposDoc" :key="t.v" class="mo-doctype">
                    <input type="radio" :value="t.v" v-model="docType" @change="alCambiarTipo" />
                    <span>{{ t.l }}</span>
                </label>
            </div>

            <div class="mo-grid">
                <!-- Documento y nombre juntos: el primero rellena al segundo, y
                     verlos en la misma linea hace evidente de donde sale. -->
                <div class="mo-f">
                    <label class="mo-lbl" for="mo-doc">Número</label>
                    <div class="mo-input-wrap">
                        <input
                            id="mo-doc"
                            ref="doc"
                            v-model="documento"
                            type="text"
                            class="mo-input"
                            :maxlength="consultable ? 11 : 20"
                            :inputmode="consultable ? 'numeric' : 'text'"
                            autocomplete="off"
                            :placeholder="consultable ? '8 dígitos (DNI) u 11 (RUC)' : 'Número de documento'"
                            @input="alEscribir"
                            @keydown.enter.prevent="buscar(true)"
                        />
                        <i v-if="buscando" class="mo-spin el-icon-loading"></i>
                    </div>
                    <small v-if="estadoDoc" class="mo-hint" :class="{ 'is-warn': estadoEsAviso }">
                        {{ estadoDoc }}
                    </small>
                    <small v-else-if="consultable" class="mo-hint">
                        Con 8 u 11 dígitos se consulta solo.
                    </small>
                </div>

                <div class="mo-f">
                    <label class="mo-lbl req" for="mo-name">Nombre completo</label>
                    <input
                        id="mo-name"
                        ref="name"
                        v-model="form.name"
                        type="text"
                        class="mo-input"
                        :class="{ 'is-auto': nombreBloqueado }"
                        :readonly="nombreBloqueado"
                        maxlength="160"
                        :placeholder="nombreBloqueado ? 'Se completa con el documento' : 'Nombre o razón social'"
                    />
                    <!-- La salida del callejon sin salida, igual que en el
                         publico: si el documento no figura o el servicio no
                         responde, el campo no puede quedarse vacio, bloqueado
                         y obligatorio a la vez. -->
                    <small v-if="nombreBloqueado" class="mo-hint">
                        🔒 Se completa al ingresar el documento.
                        <button type="button" class="mo-link" @click="escribirAMano">Escribirlo a mano</button>
                    </small>
                </div>

                <div class="mo-f">
                    <label class="mo-lbl req" for="mo-phone">Celular (WhatsApp)</label>
                    <input
                        id="mo-phone"
                        ref="phone"
                        v-model="form.phone"
                        type="tel"
                        class="mo-input"
                        maxlength="9"
                        inputmode="numeric"
                        placeholder="999 999 999"
                        @input="soloDigitosEn('phone')"
                    />
                    <small v-if="errorPhone" class="mo-err">{{ errorPhone }}</small>
                </div>

                <div v-if="shippingModule" class="mo-f">
                    <label class="mo-lbl" for="mo-phone2">
                        Teléfono adicional <span class="mo-opt">(opcional)</span>
                    </label>
                    <input
                        id="mo-phone2"
                        v-model="form.alternate_phone"
                        type="tel"
                        class="mo-input"
                        maxlength="9"
                        inputmode="numeric"
                        placeholder="999 999 999"
                        @input="soloDigitosEn('alternate_phone')"
                    />
                    <small class="mo-hint">A quién llamar si el primero no contesta.</small>
                </div>
            </div>

            <!-- ══ A dónde va el paquete ═══════════════════════════════════
                 El buscador de ubigeo y el catalogo de agencias son los MISMOS
                 que usa el formulario de envio: `/orders/ubigeo/buscar` y
                 `ShippingRequest::AGENCIES` servido por `/orders/channels`. No
                 hay una segunda lista ni un segundo buscador que mantener.

                 Sin el modulo de Envios el bloque entero desaparece: pintarlo
                 seria pedir unos datos que no tienen donde guardarse. -->
            <template v-if="shippingModule">
            <div class="mo-head mo-head--2">
                <h2 class="mo-h">A dónde enviamos el pedido</h2>
                <p class="mo-sub">La ciudad y la agencia por la que lo recogerá.</p>
            </div>

            <div class="mo-grid">
                <div class="mo-f mo-f--full">
                    <label class="mo-lbl req">Ciudad de destino</label>
                    <!-- Antes de esto eran tres selectores encadenados y habia
                         que saber Piura → Talara → Pariñas para poder elegir.
                         Se escribe el nombre que se conoce y el buscador hace
                         el resto, tildes incluidas. -->
                    <el-select
                        v-model="envio.district_id"
                        class="mo-sel"
                        filterable
                        remote
                        clearable
                        :remote-method="buscarUbigeo"
                        :loading="ubigeoLoading"
                        placeholder="Busca ciudad, provincia o distrito…"
                        :no-data-text="ubigeoQuery.length < 2 ? 'Escribe al menos 2 letras' : 'No encontramos «' + ubigeoQuery + '». Revisa la escritura.'"
                        no-match-text="Sin coincidencias"
                        @change="alElegirUbigeo"
                    >
                        <el-option
                            v-for="r in ubigeoResults"
                            :key="r.district_id"
                            :label="r.name + ' — ' + r.province_name + ', ' + r.department_name"
                            :value="r.district_id"
                        >
                            <span class="mo-ub-name">{{ r.name }}</span>
                            <span class="mo-ub-ctx">{{ r.context }}</span>
                        </el-option>
                    </el-select>
                    <small v-if="destinoElegido" class="mo-hint">{{ destinoElegido }}</small>
                </div>

                <div class="mo-f">
                    <label class="mo-lbl req">Agencia de transporte</label>
                    <!-- `allow-create` porque el catalogo son 12 nombres, no una
                         tabla: si el cliente pide una que no esta, se escribe. -->
                    <el-select
                        v-model="envio.shipping_agency"
                        class="mo-sel"
                        filterable
                        allow-create
                        clearable
                        default-first-option
                        placeholder="— Selecciona —"
                    >
                        <el-option v-for="a in agencias" :key="a" :label="a" :value="a" />
                    </el-select>
                </div>

                <div class="mo-f">
                    <label class="mo-lbl">
                        Oficina donde recoge <span class="mo-opt">(opcional)</span>
                    </label>
                    <input
                        v-model="envio.reference"
                        type="text"
                        class="mo-input"
                        maxlength="255"
                        placeholder="Ej. Terminal Terrestre, Av. Aviación 123…"
                    />
                    <small class="mo-hint">Si no se sabe, en blanco: la agencia lo indica.</small>
                </div>

                <!-- El paquete normalmente solo viaja hasta la agencia y la
                     direccion no la usa nadie. Solo se pide si hay reparto. -->
                <div class="mo-f mo-f--full">
                    <label class="mo-chk">
                        <input type="checkbox" v-model="envio.a_domicilio" />
                        <span>La agencia lleva el paquete hasta el domicilio</span>
                    </label>
                </div>

                <!-- Cliente EMPRESA: la agencia no le entrega a un RUC,
                     pide el DNI y el nombre de la persona que va a recoger.
                     Sin esto salian rotulos a nombre de una razon social y
                     sin nadie a quien entregarle la caja. -->
                <template v-if="esEmpresa">
                    <div class="mo-f mo-f--full mo-empresa-aviso">
                        Es un RUC: la agencia necesita saber qué persona recoge el paquete.
                    </div>

                    <div class="mo-f">
                        <label class="mo-lbl req">Quién recoge</label>
                        <input
                            v-model="envio.pickup_person_name"
                            type="text"
                            class="mo-input"
                            maxlength="160"
                            placeholder="Nombre y apellidos"
                        />
                    </div>

                    <div class="mo-f">
                        <label class="mo-lbl req">DNI de quien recoge</label>
                        <input
                            v-model="envio.pickup_person_dni"
                            type="text"
                            class="mo-input"
                            maxlength="20"
                            inputmode="numeric"
                            placeholder="8 dígitos"
                            @input="soloDigitosEnEnvio('pickup_person_dni')"
                        />
                    </div>
                </template>

                <div v-if="envio.a_domicilio" class="mo-f mo-f--full">
                    <label class="mo-lbl req">Dirección de reparto</label>
                    <input
                        v-model="envio.shipping_destination"
                        type="text"
                        class="mo-input"
                        maxlength="255"
                        placeholder="Av./Jr./Calle y número"
                    />
                </div>
            </div>
            </template>

            <!-- == Qué lleva el pedido =====================================
                 Una linea es una VENTA: lleva `item_id`, precio y reserva de
                 stock, y alimenta la nota de venta. Por eso no hay campo de
                 texto libre: lo que no esta en el catalogo se CREA. -->
            <div class="mo-head mo-head--2">
                <h2 class="mo-h">Qué lleva el pedido</h2>
                <p class="mo-sub">Busca por nombre o código. El disponible es el mismo que valida el guardado.</p>
            </div>

            <div v-if="!lineasEditables" class="mo-frozen">
                Este pedido ya está en preparación: los productos quedaron fijados.
                Puedes corregir los datos del cliente. Si hay que cambiar el
                contenido, anúlalo y crea uno nuevo.
            </div>

            <div class="mo-f mo-f--full">
                <el-select
                    v-model="buscado"
                    class="mo-sel"
                    filterable
                    remote
                    reserve-keyword
                    clearable
                    :disabled="!lineasEditables"
                    placeholder="Busca por nombre o código y elige para agregarlo"
                    :remote-method="buscarProductos"
                    :loading="buscandoItems"
                    @change="agregar"
                >
                    <el-option
                        v-for="op in opciones"
                        :key="op.key"
                        :label="op.label"
                        :value="op.key"
                        :disabled="op.agotado"
                    >
                        <span class="mo-op-name">{{ op.label }}</span>
                        <span class="mo-op-stock" :class="{ 'is-off': op.agotado }">{{ op.stockText }}</span>
                    </el-option>

                    <!-- Un fallo de red pintado como «no existe» es un problema
                         que no se arregla buscando otra cosa: se distingue. -->
                    <div v-if="errorBusqueda" slot="empty" class="mo-crear">
                        <p class="mo-busqueda-error">{{ errorBusqueda }}</p>
                        <el-button size="mini" plain @click="buscarProductos(termino)">Reintentar</el-button>
                    </div>
                    <div v-else-if="puedeCrear" slot="empty" class="mo-crear">
                        <p>«{{ termino }}» no está en el catálogo.</p>
                        <el-button size="mini" type="primary" plain :loading="creando" @click="crearProducto">
                            Crearlo y agregarlo
                        </el-button>
                        <small>Se crea como servicio: no controla stock.</small>
                    </div>
                </el-select>
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
                            <td :colspan="puedeEditarPrecio ? 7 : 6" class="mo-empty">
                                Todavía no hay productos. Búscalos arriba.
                            </td>
                        </tr>
                        <tr v-for="(l, i) in form.items" :key="l.key">
                            <td>
                                {{ l.name }}
                                <div v-if="l.code" class="mo-code">{{ l.code }}</div>
                            </td>
                            <td class="num">
                                <!-- Tres estados que no se pueden confundir:
                                     `undefined` = no se ha mirado en este almacen,
                                     `null` = el producto no controla stock,
                                     numero = lo que hay. -->
                                <span v-if="l.available === undefined" class="mo-muted" title="Se comprueba al guardar.">—</span>
                                <span v-else-if="l.available === null" class="mo-muted">sin control</span>
                                <span v-else :class="{ 'mo-over': l.quantity > l.available }">{{ l.available }}</span>
                            </td>
                            <td class="num">
                                <el-input-number
                                    v-model="l.quantity"
                                    :min="1"
                                    :step="1"
                                    size="mini"
                                    controls-position="right"
                                    :disabled="!lineasEditables"
                                ></el-input-number>
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
                                <span v-else title="No tienes permiso para cambiar precios: se cobra el del catálogo.">
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
                            <td class="num mo-neto">S/ {{ money(neto(l)) }}</td>
                            <td class="num">
                                <el-button type="text" class="mo-del" :disabled="!lineasEditables" @click="quitar(i)">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mo-total">
                <span v-if="descuentoTotal > 0" class="mo-desc">
                    Descuento aplicado: −S/ {{ money(descuentoTotal) }}
                </span>
                <span>Total</span>
                <strong>S/ {{ money(total) }}</strong>
            </div>
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
export default {
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { default: null },
    },
    data() {
        return {
            // Mismos valores que ShippingRequest::DOC_TYPES, para que el dia
            // que esto guarde no haya que traducir nada.
            tiposDoc: [
                { v: "dni", l: "DNI / RUC" },
                { v: "ce", l: "C. Extranjería" },
                { v: "pasaporte", l: "Pasaporte" },
            ],
            docType: "dni",
            documento: "",
            // `items` vive dentro de `form` porque es lo que se envia tal
            // cual: el resto del formulario tambien, y tenerlo suelto era la
            // forma de olvidarse de limpiarlo.
            // `email` no se pide en ningun campo: viene del pedido y vuelve
            // tal cual. Sin ese viaje de ida y vuelta, guardar una correccion
            // del telefono borraba el correo del cliente sin decir nada.
            form: {
                name: "",
                phone: "",
                email: "",
                alternate_phone: "",
                channel_id: null,
                items: [],
            },
            // Mismos nombres de campo que `shipping_requests`: `reference` es
            // la oficina de la agencia y `shipping_destination` la direccion
            // de reparto, tal como los guarda el modulo de Envios.
            envio: {
                district_id: "",
                province_id: "",
                department_id: "",
                destination_city: "",
                shipping_agency: "",
                reference: "",
                a_domicilio: false,
                shipping_destination: "",
                pickup_person_name: "",
                pickup_person_dni: "",
            },
            agencias: [],
            canales: [],
            // Lo que puede hacer ESTE usuario y lo que tiene ESTE negocio.
            // Los dos los decide el servidor: aqui solo se reflejan, y el
            // guardado los vuelve a comprobar. Deshabilitar un campo en
            // pantalla nunca ha sido un control.
            puedeEditarPrecio: false,
            shippingModule: false,
            // Un pedido en preparacion admite corregir al cliente pero no los
            // productos. Lo dice `record`; en un alta siempre se puede.
            lineasEditables: true,
            // El pedido llego en un estado final: se puede mirar, no guardar.
            // Se avisa arriba Y se apaga el boton; solo el aviso dejaba pulsar
            // para recibir el mismo 422 del servidor.
            bloqueado: false,
            // Un pedido que llego sin lineas es un encargo logistico y puede
            // seguir sin ellas. Uno que las tenia no se puede vaciar.
            teniaLineas: true,
            destinoElegido: "",
            ubigeoResults: [],
            ubigeoQuery: "",
            ubigeoLoading: false,
            nombreManual: false,
            nombreTraido: "",
            origen: "",
            estadoDoc: "",
            estadoEsAviso: false,
            buscando: false,
            timerDoc: null,
            // Corregir un digito lanza otra consulta antes de que vuelva la
            // primera. Sin este numero, la lenta llega ultima y pinta al
            // cliente equivocado encima del bueno.
            peticion: 0,
            docConsultado: "",

            // ── Productos ────────────────────────────────────────────────
            buscado: null,
            opciones: [],
            termino: "",
            buscandoItems: false,
            errorBusqueda: "",
            creando: false,
            // El mismo descarte de respuestas tardias que el buscador de
            // documentos: teclear rapido lanza varias consultas y la lenta
            // llegaba la ultima, dejando en pantalla media palabra.
            peticionItems: 0,

            // ── Carga y guardado ─────────────────────────────────────────
            cargando: false,
            guardando: false,
            problemas: [],
            // Copia de los datos de envio tal como llegaron. Sirve para no
            // reescribir el envio —ni dejar una linea en su bitacora— cuando
            // el operador solo vino a corregir un telefono.
            envioOriginal: null,
        };
    },
    computed: {
        editando() {
            return !!this.orderId;
        },
        titulo() {
            return this.editando ? "Editar pedido" : "Nuevo pedido";
        },
        /** Solo DNI y RUC se consultan: carne y pasaporte no estan en RENIEC. */
        consultable() {
            return this.docType === "dni";
        },
        nombreBloqueado() {
            return this.consultable && !this.nombreManual;
        },
        errorPhone() {
            const p = this.form.phone || "";
            // Avisar mientras se teclea seria regañar por no haber terminado:
            // el error solo sale cuando ya hay 9 digitos y aun asi no cuadran.
            if (p.length < 9) return "";
            if (!/^9\d{8}$/.test(p)) return "Un celular peruano tiene 9 dígitos y empieza por 9.";
            return "";
        },
        /**
         * La misma regla que `ShippingRequest::documentIsRuc()`: once digitos
         * es un RUC aunque el chip diga «DNI / RUC», porque ese chip cubre los
         * dos. Si aqui no coincidiera, el formulario dejaria guardar algo que
         * el servidor rechaza despues.
         */
        esEmpresa() {
            return this.docType === "dni" && (this.documento || "").length === 11;
        },
        /** Se ofrece crear lo que no esta, pero no con dos letras sueltas. */
        puedeCrear() {
            return this.lineasEditables && (this.termino || "").trim().length >= 3;
        },
        descuentoTotal() {
            return this.form.items.reduce(
                (a, l) => a + Math.min(Number(l.discount || 0), this.bruto(l)),
                0
            );
        },
        total() {
            return this.form.items.reduce((a, l) => a + this.neto(l), 0);
        },
        /**
         * Cuando el destino es obligatorio.
         *
         * Sin el modulo de Envios, nunca: el bloque no existe, y exigirlo
         * dejaria la barra clavada y el boton apagado sin nada que el operador
         * pudiera hacer.
         *
         * En un alta, siempre: este formulario pregunta a donde va el paquete
         * y ese es justo el dato que antes se quedaba sin registrar.
         *
         * Al EDITAR, solo si el pedido ya tiene envio o si el operador empezo
         * a llenarlo. Un pedido que nacio sin envio —se lo llevo el cliente
         * del mostrador— no puede volverse imposible de corregir porque ahora
         * se le pida un destino que nunca tuvo. Pero uno a medio llenar si se
         * termina: guardar media direccion es peor que no guardar ninguna.
         */
        destinoRequerido() {
            if (!this.shippingModule) return false;
            if (!this.editando) return true;

            return (
                !!this.envioOriginal ||
                !!this.envio.district_id ||
                !!(this.envio.shipping_agency || "").trim()
            );
        },
        sePuedeGuardar() {
            if (this.guardando || this.cargando || this.bloqueado) return false;
            if (!this.form.channel_id) return false;
            if (!(this.form.name || "").trim()) return false;
            if (!/^9\d{8}$/.test(this.form.phone || "")) return false;

            // Vaciar un pedido no es editarlo: para eso esta anular, que
            // conserva el historico. El servidor lo rechaza igual.
            //
            // Pero un pedido que YA nacio sin lineas es un encargo logistico
            // —lo que lleva la caja se escribe a mano en el envio—, y exigirle
            // una linea dejaba su ficha imposible de guardar: ni para
            // corregirle el telefono al cliente.
            if (!this.form.items.length && !(this.editando && !this.teniaLineas)) {
                return false;
            }

            if (this.destinoRequerido) {
                if (!this.envio.district_id) return false;
                if (!(this.envio.shipping_agency || "").trim()) return false;
                if (this.envio.a_domicilio && !(this.envio.shipping_destination || "").trim()) {
                    return false;
                }
                if (this.esEmpresa) {
                    if (!(this.envio.pickup_person_name || "").trim()) return false;
                    if ((this.envio.pickup_person_dni || "").length < 8) return false;
                }
            }

            return true;
        },
        pct() {
            const req = [
                !!this.form.channel_id,
                !!(this.form.name || "").trim(),
                /^9\d{8}$/.test(this.form.phone || ""),
            ];

            // Ver `sePuedeGuardar`: al encargo logistico no se le piden.
            if (!(this.editando && !this.teniaLineas)) {
                req.push(!!this.form.items.length);
            }

            if (this.destinoRequerido) {
                req.push(!!this.envio.district_id);
                req.push(!!(this.envio.shipping_agency || "").trim());
            }
            // La direccion solo cuenta cuando se ha pedido reparto: si no,
            // exigirla dejaria la barra clavada al 80% sin nada que falte.
            if (this.destinoRequerido && this.envio.a_domicilio) {
                req.push(!!(this.envio.shipping_destination || "").trim());
            }
            if (this.destinoRequerido && this.esEmpresa) {
                req.push(!!(this.envio.pickup_person_name || "").trim());
                req.push((this.envio.pickup_person_dni || "").length >= 8);
            }
            const hechos = req.filter(Boolean).length;
            return Math.round((hechos / req.length) * 100);
        },
    },
    watch: {
        /**
         * Crear dos pedidos seguidos reutiliza ESTA instancia: el listado
         * monta el componente con `:key="manualOrderId || 'nuevo'"`, y entre
         * dos altas esa clave no cambia, asi que Vue no lo vuelve a crear.
         *
         * Sin esto, el segundo «Nuevo pedido» se abria con el cliente, el
         * destino y los productos del primero ya escritos, y el operador
         * duplicaba el pedido anterior sin darse cuenta.
         *
         * La edicion no pasa por aqui: su clave si cambia, la instancia es
         * nueva y quien la llena es `created()`.
         */
        showDialog(abierto) {
            if (abierto && !this.editando) this.reiniciar();
        },
    },

    /**
     * Las agencias son catalogo del servidor, no una copia en el front.
     *
     * La carga del pedido va AQUI y no en un watcher de `orderId`: el listado
     * monta el componente con `:key="manualOrderId || 'nuevo'"`, asi que cada
     * edicion es una instancia nueva y un watcher sin `immediate` no llegaria
     * a correr nunca.
     */
    created() {
        this.cargarCatalogos();

        if (this.editando) this.cargar();
    },
    methods: {
        /**
         * Deja el formulario como recien nacido, menos los catalogos: las
         * agencias y los canales no cambian entre un pedido y el siguiente, y
         * volver a pedirlos seria una consulta por alta para el mismo dato.
         */
        reiniciar() {
            const canalUnico = this.canales.length === 1 ? this.canales[0].id : null;

            clearTimeout(this.timerDoc);

            this.docType = "dni";
            this.documento = "";
            this.form = {
                name: "",
                phone: "",
                email: "",
                alternate_phone: "",
                channel_id: canalUnico,
                items: [],
            };
            this.envio = {
                district_id: "",
                province_id: "",
                department_id: "",
                destination_city: "",
                shipping_agency: "",
                reference: "",
                a_domicilio: false,
                shipping_destination: "",
                pickup_person_name: "",
                pickup_person_dni: "",
            };

            this.destinoElegido = "";
            this.ubigeoResults = [];
            this.ubigeoQuery = "";
            this.ubigeoLoading = false;
            this.nombreManual = false;
            this.nombreTraido = "";
            this.origen = "";
            this.estadoDoc = "";
            this.estadoEsAviso = false;
            this.buscando = false;
            this.docConsultado = "";

            this.buscado = null;
            this.opciones = [];
            this.termino = "";
            this.buscandoItems = false;
            this.errorBusqueda = "";
            this.creando = false;

            this.cargando = false;
            this.guardando = false;
            this.problemas = [];
            this.envioOriginal = null;
            this.lineasEditables = true;
            this.teniaLineas = true;
            this.bloqueado = false;

            // Las respuestas en vuelo son del pedido ANTERIOR: se descartan
            // subiendo el contador, o llegarian a pintar sobre el nuevo.
            this.peticion++;
            this.peticionItems++;
        },

        cargarCatalogos() {
            this.$http
                .get("/orders/channels")
                .then(r => {
                    const d = r.data || {};
                    this.agencias = d.agencies || [];
                    this.canales = d.channels || [];
                    this.puedeEditarPrecio = !!d.can_edit_prices;
                    this.shippingModule = !!d.shipping_module;

                    // Con un solo canal no hay nada que elegir: preguntarlo es
                    // un clic obligatorio con una unica respuesta posible.
                    if (!this.editando && !this.form.channel_id && this.canales.length === 1) {
                        this.form.channel_id = this.canales[0].id;
                    }
                })
                .catch(() => {
                    // Sin catalogo el campo sigue siendo usable: `allow-create`
                    // deja escribir la agencia a mano. Peor seria un desplegable
                    // vacio que no admite nada.
                    this.agencias = [];
                    this.canales = [];
                });
        },

        /**
         * Trae el pedido para editarlo.
         *
         * El disponible de cada linea llega SIN valor a proposito: el que
         * devuelve el buscador incluye lo que este mismo pedido ya tiene
         * reservado, asi que pintarlo aqui diria «disponible 0» sobre un
         * producto que el pedido ya tiene cogido.
         */
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

                    this.form.channel_id = d.channel_id;

                    const c = d.customer || {};
                    this.form.name = c.name || "";
                    this.form.phone = (c.phone || "").replace(/\D+/g, "");
                    this.form.email = c.email || "";
                    this.documento = (c.document_number || "").replace(/\D+/g, "");

                    // Un pedido guardado ya trae nombre: dejarlo bloqueado
                    // esperando una consulta que nadie va a lanzar seria un
                    // campo obligatorio que no se puede rellenar.
                    this.nombreManual = true;
                    this.docConsultado = this.documento;

                    this.form.items = (d.items || []).map(l => ({
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

                    this.teniaLineas = this.form.items.length > 0;

                    this.pintarEnvio(d.shipping);
                })
                .catch(() => {
                    this.problemas = ["No se pudo cargar el pedido."];
                })
                .then(() => {
                    this.cargando = false;
                });
        },

        /**
         * Reconstruye el bloque de destino con lo que guardo el envio.
         *
         * El desplegable de ubigeo es remoto: sin una opcion en la lista, el
         * `district_id` cargado no tendria etiqueta que pintar y el campo se
         * veria vacio teniendo valor. Por eso se siembra la fila del distrito
         * guardado antes de asignarlo.
         */
        pintarEnvio(envio) {
            this.envioOriginal = envio ? JSON.parse(JSON.stringify(envio)) : null;

            if (!envio) return;

            this.envio.department_id = envio.department_id || "";
            this.envio.province_id = envio.province_id || "";
            this.envio.destination_city = envio.destination_city || "";
            this.envio.shipping_agency = envio.shipping_agency || "";
            this.envio.reference = envio.reference || "";
            this.envio.shipping_destination = envio.shipping_destination || "";
            this.envio.a_domicilio = !!(envio.shipping_destination || "").trim();
            this.envio.pickup_person_name = envio.pickup_person_name || "";
            this.envio.pickup_person_dni = envio.pickup_person_dni || "";
            this.form.alternate_phone = (envio.alternate_phone || "").replace(/\D+/g, "");

            if (envio.district_id) {
                this.ubigeoResults = [
                    {
                        district_id: envio.district_id,
                        name: envio.destination_city || envio.district_id,
                        province_name: "",
                        department_name: "",
                        context: "Destino guardado",
                    },
                ];
                this.envio.district_id = envio.district_id;
                this.destinoElegido = envio.destination_city || "";
            }
        },

        /**
         * El canal decide el almacen, y el almacen decide el disponible. Lo
         * que ya estaba en la tabla se midio contra otro: dejar el numero
         * viejo seria peor que no enseñar ninguno.
         */
        alCambiarCanal() {
            this.opciones = [];
            this.form.items.forEach(l => {
                this.$set(l, "available", undefined);
            });
        },

        /**
         * El buscador de ubigeo ya existe y es el mismo de Envios: busca por
         * distrito, provincia y departamento, y aguanta las tildes. No se
         * reimplementa aqui.
         */
        buscarUbigeo(q) {
            this.ubigeoQuery = (q || "").trim();

            if (this.ubigeoQuery.length < 2) {
                this.ubigeoResults = [];
                return;
            }

            this.ubigeoLoading = true;
            this.$http
                .get("/orders/ubigeo/buscar", { params: { q: this.ubigeoQuery } })
                .then(r => {
                    this.ubigeoResults = r.data || [];
                })
                .catch(() => {
                    // Un fallo de red pintado como «no hay resultados» es un
                    // problema que no se arregla buscando otra cosa.
                    this.ubigeoResults = [];
                    this.$message.error("No se pudo buscar la ciudad. Reintenta.");
                })
                .then(() => {
                    this.ubigeoLoading = false;
                });
        },

        /**
         * Provincia y departamento se derivan del distrito. El servidor lo
         * repite al guardar, pero el formulario tiene que quedar coherente YA
         * o el resumen miente hasta entonces.
         */
        alElegirUbigeo(districtId) {
            if (!districtId) {
                this.envio.province_id = "";
                this.envio.department_id = "";
                this.envio.destination_city = "";
                this.destinoElegido = "";
                return;
            }

            const r = (this.ubigeoResults || []).find(x => x.district_id === districtId);
            if (!r) return;

            this.envio.province_id = r.province_id || "";
            this.envio.department_id = r.department_id || "";
            this.envio.destination_city = r.name || "";
            this.destinoElegido = r.name + " — " + r.province_name + ", " + r.department_name;
        },

        cerrar() {
            this.$emit("update:showDialog", false);
        },

        alCambiarTipo() {
            clearTimeout(this.timerDoc);
            this.documento = "";
            this.estadoDoc = "";
            this.estadoEsAviso = false;
            this.origen = "";
            this.docConsultado = "";
            this.nombreTraido = "";
            // Con carne o pasaporte no hay a quien consultar: el nombre se
            // escribe siempre, y dejarlo bloqueado seria un callejon sin salida.
            this.nombreManual = !this.consultable;
        },

        escribirAMano() {
            this.nombreManual = true;
            this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
        },

        soloDigitosEn(campo) {
            this.form[campo] = (this.form[campo] || "").replace(/\D+/g, "");
        },

        soloDigitosEnEnvio(campo) {
            this.envio[campo] = (this.envio[campo] || "").replace(/\D+/g, "");
        },

        /**
         * Busca sola en cuanto el documento alcanza largo de DNI (8) o RUC (11).
         *
         * Con espera porque el operador teclea: sin ella, escribir un RUC
         * dispararia once consultas y las diez primeras se pagan para nada.
         */
        alEscribir() {
            if (!this.consultable) return;

            this.documento = (this.documento || "").replace(/\D+/g, "");
            clearTimeout(this.timerDoc);

            const doc = this.documento;

            // El documento cambio: lo que trajo la consulta anterior ya no es
            // de esta persona. Se suelta ahora, no cuando llegue la respuesta,
            // para que la pantalla no quede un segundo mostrando al anterior.
            if (doc !== this.docConsultado) this.soltarTraido();

            if (doc.length !== 8 && doc.length !== 11) return;

            this.timerDoc = setTimeout(() => this.buscar(false), 450);
        },

        /**
         * Suelta el nombre SOLO si sigue siendo el que puso la consulta. Si el
         * operador lo corrigio a mano, su correccion vale mas que el servicio.
         */
        soltarTraido() {
            if (this.nombreTraido && this.form.name === this.nombreTraido) {
                this.form.name = "";
            }
            this.nombreTraido = "";
            this.origen = "";
            this.estadoDoc = "";
            this.estadoEsAviso = false;
            this.docConsultado = "";
        },

        /**
         * `manual` = lo pidió el operador con Enter, y entonces sí se le
         * responde aunque no haya nada. En la búsqueda automática se calla: el
         * cliente nuevo es un caso normal, no un error que avisar.
         */
        buscar(manual) {
            if (!this.consultable) return;

            clearTimeout(this.timerDoc);
            const doc = (this.documento || "").replace(/\D+/g, "");

            if (!doc) {
                if (manual) this.$message.warning("Ingresa el documento a buscar.");
                return;
            }

            this.soltarTraido();

            const peticion = ++this.peticion;
            this.buscando = true;

            this.$http
                .get("/orders/search-customer", { params: { document_number: doc } })
                .then(r => {
                    if (peticion !== this.peticion) return;

                    const d = r.data || {};
                    this.docConsultado = doc;

                    if (!d.found) {
                        // Que no figure no puede dejar el nombre bloqueado y
                        // vacio: se abre a mano en el acto.
                        this.nombreManual = true;
                        this.estadoDoc = d.message || "Sin datos para ese documento: escribe el nombre.";
                        this.estadoEsAviso = true;
                        return;
                    }

                    const c = d.customer || {};

                    if (c.name) {
                        this.form.name = c.name;
                        this.nombreTraido = c.name;
                        this.nombreManual = false;
                    } else {
                        this.nombreManual = true;
                    }

                    this.estadoEsAviso = false;
                    this.origen =
                        {
                            cartera: "Cliente de tu cartera.",
                            dni: "Datos traídos de RENIEC.",
                            ruc: "Datos traídos de SUNAT.",
                        }[d.source] || "";
                    this.estadoDoc = this.origen;
                })
                .catch(() => {
                    if (peticion !== this.peticion) return;
                    this.nombreManual = true;
                    this.estadoDoc = "No se pudo consultar: escribe el nombre a mano.";
                    this.estadoEsAviso = true;
                })
                .then(() => {
                    if (peticion === this.peticion) this.buscando = false;
                });
        },

        // ── Productos ───────────────────────────────────────
        buscarProductos(q) {
            const termino = typeof q === "string" ? q : "";
            this.termino = termino;
            this.errorBusqueda = "";

            if (termino.length < 2) {
                this.opciones = [];
                return;
            }

            const peticion = ++this.peticionItems;
            this.buscandoItems = true;

            this.$http
                .get("/orders/search-items", {
                    params: { q: termino, channel_id: this.form.channel_id },
                })
                .then(r => {
                    if (peticion !== this.peticionItems) return;
                    this.opciones = this.aOpciones(r.data || []);
                })
                .catch(() => {
                    if (peticion !== this.peticionItems) return;
                    // Vaciar la lista aqui seria decir «ese producto no
                    // existe» cuando lo que ha pasado es que la consulta
                    // fallo. Son dos problemas distintos y el segundo no se
                    // arregla buscando otra cosa.
                    this.opciones = [];
                    this.errorBusqueda =
                        "No se pudo buscar en el catálogo. Revisa la conexión e inténtalo otra vez.";
                })
                .then(() => {
                    if (peticion === this.peticionItems) this.buscandoItems = false;
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

            // Elegir dos veces el mismo producto es pedir dos unidades, no
            // dos lineas iguales que despues hay que sumar a ojo.
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

        quitar(i) {
            this.form.items.splice(i, 1);
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

        // ── Guardar ─────────────────────────────────────────
        /**
         * Lo que se manda a `POST /orders/{order}/envio`, con los MISMOS
         * nombres de campo que devuelve `record`. No hay traduccion entre lo
         * que el formulario lee y lo que escribe.
         *
         * La modalidad es siempre agencia: el interruptor de esta pantalla
         * pregunta si la AGENCIA reparte a domicilio, que no es lo mismo que
         * la modalidad «domicilio» del modulo —esa es el motorizado local y
         * pide mapa, distancia y precio de reparto—.
         */
        payloadEnvio() {
            return {
                delivery_type: "agencia",
                full_name: (this.form.name || "").trim(),
                phone: this.form.phone,
                alternate_phone: this.form.alternate_phone || null,
                dni: this.documento || null,
                document_type: this.documento.length === 11 ? "ruc" : this.docType,
                district_id: this.envio.district_id,
                province_id: this.envio.province_id || null,
                department_id: this.envio.department_id || null,
                destination_city: this.envio.destination_city || null,
                shipping_agency: this.envio.shipping_agency,
                reference: this.envio.reference || null,
                // Sin reparto no se manda direccion: dejar la de un intento
                // anterior mandaria el paquete a una casa que ya no aplica.
                shipping_destination: this.envio.a_domicilio
                    ? this.envio.shipping_destination
                    : null,
                // El servidor los ignora si el cliente no es empresa: no hace
                // falta condicionarlos aqui tambien.
                pickup_person_name: this.envio.pickup_person_name || null,
                pickup_person_dni: this.envio.pickup_person_dni || null,
            };
        },

        /**
         * ¿Hay algo que escribir en el envio?
         *
         * En una edicion, guardar el envio siempre dejaria una linea en su
         * bitacora aunque el operador solo hubiera venido a corregir un
         * telefono. Se compara con lo que llego.
         */
        envioCambio(payload) {
            if (!this.envioOriginal) return true;

            return [
                "district_id",
                "shipping_agency",
                "reference",
                "shipping_destination",
                "alternate_phone",
                "pickup_person_name",
                "pickup_person_dni",
            ].some(k => (this.envioOriginal[k] || null) !== (payload[k] || null));
        },

        guardar() {
            this.guardando = true;
            this.problemas = [];

            const url = this.editando
                ? `/orders/${this.orderId}/actualizar`
                : "/orders/manual";

            this.$http
                .post(url, {
                    channel_id: this.form.channel_id,
                    customer: {
                        name: (this.form.name || "").trim(),
                        document_number: this.documento || null,
                        phone: this.form.phone || null,
                        email: this.form.email || null,
                    },
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
                    const id = (d.order && d.order.id) || this.orderId || null;
                    const payload = this.payloadEnvio();

                    if (!this.destinoRequerido || !id || !this.envioCambio(payload)) {
                        return r;
                    }

                    return this.$http
                        .post(`/orders/${id}/envio`, payload)
                        .then(() => r)
                        .catch(e => {
                            // El pedido SI se guardo. El aviso tiene que decir
                            // exactamente que quedo fuera, o el operador creera
                            // que se perdio todo y lo volvera a crear.
                            const err = (e.response && e.response.data) || {};
                            const porCampo = err.errors
                                ? Object.values(err.errors).map(x => x[0]).join(" ")
                                : null;

                            this.$message({
                                type: "warning",
                                duration: 9000,
                                message:
                                    "El pedido se guardó, pero los datos de envío no: " +
                                    (porCampo || err.message || "revísalos desde el propio pedido."),
                            });

                            return r;
                        });
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

                    // Se emite el ID, no un aviso pelado: es lo que permite al
                    // listado volver a ponerle el ojo encima, tambien cuando es
                    // nuevo y el orden lo manda a la pagina 3.
                    //
                    // Segundo argumento null: el destino ya se registro aqui,
                    // asi que no hay que encadenar el formulario de envio.
                    this.$emit("created", (d.order && d.order.id) || this.orderId || null, null);
                    this.cerrar();
                })
                .catch(e => {
                    const d = (e.response && e.response.data) || {};
                    const porCampo = d.errors
                        ? Object.values(d.errors).map(x => x[0])
                        : null;
                    // El servidor manda TODOS los problemas de stock juntos.
                    this.problemas =
                        d.problemas || porCampo || [d.message || "No se pudo guardar el pedido."];
                })
                .then(() => {
                    this.guardando = false;
                });
        },
    },
};
</script>

<style scoped>
/* Mismas variables que el formulario publico de envios, para que las dos
   pantallas se reconozcan como la misma casa. */
.mo {
    --brand: #2563eb;
    --brand-d: #1d4ed8;
    --ink: #0f172a;
    --line: #e5e7eb;
    --muted: #6b7280;
    color: var(--ink);
}

/* Completitud */
.mo-prog {
    margin-bottom: 14px;
}
.mo-prog__bar {
    height: 4px;
    border-radius: 999px;
    background: #e8edf5;
    overflow: hidden;
}
.mo-prog__bar span {
    display: block;
    height: 100%;
    background: var(--brand);
    border-radius: 999px;
    transition: width 0.25s ease;
}
.mo-prog small {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--muted);
}
.mo-prog.is-done .mo-prog__bar span {
    background: #16a34a;
}

/* Cabecera del bloque */
.mo-head {
    margin: 2px 0 14px;
}
.mo-h {
    font-size: 19px;
    font-weight: 800;
    margin: 0 0 2px;
    letter-spacing: -0.01em;
    color: var(--ink);
}
.mo-sub {
    font-size: 13.5px;
    color: var(--muted);
    margin: 0;
    line-height: 1.45;
}

/* Campos */
.mo-lbl {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin: 0 0 5px;
    color: var(--ink);
}
.mo-lbl.req::after {
    content: " *";
    color: #dc2626;
}
.mo-opt {
    font-weight: 400;
    color: #64748b;
}
.mo-input {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    font-size: 14.5px;
    background: #fff;
    color: var(--ink);
    transition: 0.15s;
    box-sizing: border-box;
}
.mo-input:focus {
    outline: none;
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
.mo-input.is-auto {
    background: #f8fafc;
    font-weight: 600;
    cursor: not-allowed;
}
.mo-input.is-auto:focus {
    border-color: var(--line);
    box-shadow: none;
}
.mo-input-wrap {
    position: relative;
}
.mo-spin {
    position: absolute;
    right: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--brand);
    font-size: 15px;
}
.mo-hint {
    display: block;
    font-size: 12px;
    color: var(--muted);
    margin-top: 3px;
    line-height: 1.4;
}
.mo-hint.is-warn {
    color: #b45309;
}
.mo-err {
    display: block;
    font-size: 12px;
    color: #dc2626;
    margin-top: 3px;
}
.mo-link {
    background: none;
    border: 0;
    padding: 0 0 0 4px;
    color: var(--brand);
    font: inherit;
    font-size: 12px;
    text-decoration: underline;
    cursor: pointer;
}

/* Tipo de documento */
.mo-doctypes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}
.mo-doctype {
    position: relative;
    margin: 0;
}
.mo-doctype input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.mo-doctype span {
    display: inline-block;
    padding: 7px 14px;
    border: 1.5px solid var(--line);
    border-radius: 11px;
    font-size: 13.5px;
    font-weight: 600;
    color: #475569;
    background: #fff;
    cursor: pointer;
    transition: 0.15s;
}
.mo-doctype span:hover {
    border-color: #cbd5e1;
}
.mo-doctype input:checked + span {
    border-color: var(--brand);
    background: #eff6ff;
    color: var(--brand-d);
}
.mo-doctype input:focus-visible + span {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* La compactacion: cuatro campos en dos filas. El nombre se lleva el resto
   del ancho porque es el unico que puede ser largo de verdad. */
.mo-grid {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 14px 16px;
    align-items: start;
}
.mo-f {
    min-width: 0;
}
/* La ciudad, el checkbox y la direccion cruzan las dos columnas: son una
   decision sola, no media fila. */
.mo-f--full {
    grid-column: 1 / -1;
}

/* El bloque de destino, separado del de datos sin una linea de por medio:
   el aire ya dice que empieza otra cosa. */
.mo-head--2 {
    margin-top: 22px;
}

/* Element UI dentro del bloque: se le fuerzan las medidas de los inputs de
   arriba, o conviven dos alturas y dos radios en la misma rejilla. */
.mo-sel {
    width: 100%;
}
.mo .mo-sel >>> .el-input__inner {
    height: auto;
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    font-size: 14.5px;
    line-height: 1.3;
    color: var(--ink);
}
.mo .mo-sel >>> .el-input__inner:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
.mo .mo-sel >>> .el-input__icon {
    line-height: 42px;
}
.mo-ub-name {
    font-weight: 600;
}
.mo-ub-ctx {
    margin-left: 8px;
    font-size: 12px;
    color: var(--muted);
}

/* Checkbox nativo: el de Element trae su propio tamaño de letra y su propio
   azul, y aqui desentona con los chips. */
.mo-chk {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 2px 0 0;
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: 0.15s;
}
.mo-chk:hover {
    border-color: #cbd5e1;
}
.mo-chk input {
    width: 17px;
    height: 17px;
    accent-color: var(--brand);
    cursor: pointer;
    margin: 0;
    flex: none;
}

/* Avisos del servidor y carga */
.mo-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 12px;
    padding: 11px 14px;
    margin-bottom: 14px;
    font-size: 13px;
    line-height: 1.5;
}
.mo-alert ul {
    margin: 6px 0 0;
    padding-left: 18px;
}
.mo-loading {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 12px;
}
.mo-canal {
    margin-bottom: 16px;
}

/* Lineas congeladas: el pedido ya esta en preparacion. */
.mo-frozen {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    border-radius: 12px;
    padding: 10px 13px;
    margin-bottom: 12px;
    font-size: 12.5px;
    line-height: 1.5;
}

/* Opciones del buscador: el disponible a la derecha, donde se compara. */
.mo-op-stock {
    float: right;
    color: #16a34a;
    font-size: 12px;
    margin-left: 14px;
}
.mo-op-stock.is-off {
    color: #b91c1c;
}
.mo-crear {
    padding: 14px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
}
.mo-crear p {
    margin: 0 0 8px;
}
.mo-crear small {
    display: block;
    margin-top: 7px;
    font-size: 11.5px;
}
.mo-busqueda-error {
    color: #b91c1c;
}

/* Tabla de lineas. `mo-scroll` porque en movil no cabe: se desplaza ella,
   no la pagina. Un hijo sin min-width empujaria el dialogo entero. */
.mo-scroll {
    margin-top: 12px;
    overflow-x: auto;
    min-width: 0;
}
.mo-table {
    width: 100%;
    min-width: 560px;
    border-collapse: collapse;
    font-size: 13px;
}
.mo-table th,
.mo-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--line);
    text-align: left;
    vertical-align: middle;
}
.mo-table th {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--muted);
    border-bottom-width: 1.5px;
}
.mo-table .num {
    text-align: right;
    white-space: nowrap;
}
.mo-empty {
    text-align: center;
    color: var(--muted);
    padding: 22px 10px;
}
.mo-code {
    font-size: 11.5px;
    color: var(--muted);
    margin-top: 2px;
}
.mo-muted {
    color: var(--muted);
}
/* Pedir mas de lo que hay: se ve antes de guardar, no en el 422. */
.mo-over {
    color: #b91c1c;
    font-weight: 700;
}
.mo-neto {
    font-weight: 600;
}
.mo-del {
    color: #b91c1c;
    padding: 0;
}

/* Total */
.mo-total {
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
.mo-total strong {
    font-size: 21px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.02em;
}
.mo-desc {
    margin-right: auto;
    color: #16a34a;
    font-size: 12.5px;
}

/* Aviso de cliente empresa */
.mo-empresa-aviso {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    border-radius: 12px;
    padding: 9px 13px;
    font-size: 12.5px;
    line-height: 1.5;
}

/* Movil: una columna, como el formulario publico. */
@media (max-width: 767px) {
    .mo-grid {
        grid-template-columns: 1fr;
    }
    .mo-h {
        font-size: 17px;
    }
    .mo-total strong {
        font-size: 19px;
    }
}
</style>
