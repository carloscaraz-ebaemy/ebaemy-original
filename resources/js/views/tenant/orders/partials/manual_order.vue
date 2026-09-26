<template>
    <!-- Alta manual de pedido: se esta rehaciendo desde cero.
         De momento solo el bloque del cliente. El resto (canal, productos,
         entrega y el guardado) esta por reconstruir; el formulario anterior
         completo esta en el historial de este archivo.
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

                <div class="mo-f">
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
                 hay una segunda lista ni un segundo buscador que mantener. -->
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
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
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
            form: { name: "", phone: "", alternate_phone: "" },
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
            },
            agencias: [],
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
        pct() {
            const req = [
                !!(this.form.name || "").trim(),
                /^9\d{8}$/.test(this.form.phone || ""),
                !!this.envio.district_id,
                !!(this.envio.shipping_agency || "").trim(),
            ];
            // La direccion solo cuenta cuando se ha pedido reparto: si no,
            // exigirla dejaria la barra clavada al 80% sin nada que falte.
            if (this.envio.a_domicilio) {
                req.push(!!(this.envio.shipping_destination || "").trim());
            }
            const hechos = req.filter(Boolean).length;
            return Math.round((hechos / req.length) * 100);
        },
    },
    /** Las agencias son catalogo del servidor, no una copia en el front. */
    created() {
        this.cargarCatalogos();
    },
    methods: {
        cargarCatalogos() {
            this.$http
                .get("/orders/channels")
                .then(r => {
                    this.agencias = (r.data && r.data.agencies) || [];
                })
                .catch(() => {
                    // Sin catalogo el campo sigue siendo usable: `allow-create`
                    // deja escribir la agencia a mano. Peor seria un desplegable
                    // vacio que no admite nada.
                    this.agencias = [];
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

/* Movil: una columna, como el formulario publico. */
@media (max-width: 767px) {
    .mo-grid {
        grid-template-columns: 1fr;
    }
    .mo-h {
        font-size: 17px;
    }
}
</style>
