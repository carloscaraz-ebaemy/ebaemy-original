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
                    ref="canal"
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

            <!-- Un cliente que repite ya dicto su ciudad, su agencia y la
                 oficina donde recoge. Volver a pedirselo es hacerle el trabajo
                 dos veces, y cada vez que se redicta es una ocasion mas de
                 escribirlo distinto.

                 Se ofrecen TODOS sus destinos distintos, no solo el ultimo: la
                 casa y el trabajo, su ciudad y la de su madre. Cual toca hoy
                 lo sabe el, no el sistema. -->
            <div v-if="anteriores.length" class="mo-prev">
                <p class="mo-prev-t">
                    Ya le hemos enviado antes. ¿Va al mismo sitio?
                </p>
                <div class="mo-prev-list">
                    <button
                        v-for="(a, i) in anteriores"
                        :key="i"
                        type="button"
                        class="mo-prev-c"
                        :class="{ active: anteriorElegido === i }"
                        @click="usarAnterior(i)"
                    >
                        <span class="mo-prev-city">{{ a.ciudad }}</span>
                        <span class="mo-prev-ctx">{{ a.contexto }}</span>
                        <span class="mo-prev-ag">
                            {{ a.shipping_agency || "Sin agencia" }}<template v-if="a.reference"> · {{ a.reference }}</template>
                        </span>
                        <span v-if="a.shipping_destination" class="mo-prev-dir">
                            A domicilio: {{ a.shipping_destination }}
                        </span>
                        <span class="mo-prev-meta">
                            <template v-if="a.veces > 1">{{ a.veces }} envíos</template>
                            <template v-else-if="a.ultima_vez">Último: {{ a.ultima_vez }}</template>
                        </span>
                    </button>
                </div>
                <small class="mo-hint">
                    O rellena abajo si esta vez va a otro sitio.
                </small>
            </div>

            <div class="mo-grid">
                <div class="mo-f mo-f--full">
                    <label class="mo-lbl req">Ciudad de destino</label>
                    <!-- Antes de esto eran tres selectores encadenados y habia
                         que saber Piura → Talara → Pariñas para poder elegir.
                         Se escribe el nombre que se conoce y el buscador hace
                         el resto, tildes incluidas. -->
                    <el-select
                        ref="ciudad"
                        v-model="envio.district_id"
                        class="mo-sel"
                        filterable
                        remote
                        clearable
                        :remote-method="buscarUbigeo"
                        :loading="ubigeoLoading"
                        placeholder="Busca ciudad, provincia o distrito…"
                        :no-data-text="ubigeoFallo || (ubigeoQuery.length < 2 ? 'Escribe al menos 2 letras' : 'No encontramos «' + ubigeoQuery + '». Revisa la escritura.')"
                        no-match-text="Sin coincidencias"
                        @change="alElegirUbigeo"
                    >
                        <!-- Tres secciones con titulo, y no una lista
                             corrida de filas que hay que adivinar. La version
                             anterior mezclaba lo que se elige con lo que se
                             abre, distinguiendolos solo por un gris y un
                             chevron: se reporto como «es muy confuso».

                             Aqui cada seccion dice que es y cada fila dice que
                             hace. Lo que se elige va primero, porque es lo que
                             se busca el 90% de las veces. -->
                        <el-option-group v-if="ubigeoRuta" :label="ubigeoRuta">
                            <!-- Sin esto, entrar en una provincia era un viaje
                                 de ida: borrar lo escrito para rehacer la
                                 busqueda no es una salida, es una penitencia. -->
                            <el-option
                                key="__volver"
                                value="__volver"
                                label="Volver"
                                disabled
                                class="mo-ub-act"
                                @mousedown.native.stop.prevent="volverAlaBusqueda"
                            >
                                <span class="mo-ub-back">
                                    &#8249; Volver a los resultados de «{{ ubigeoQuery }}»
                                </span>
                            </el-option>
                        </el-option-group>

                        <!-- Misma estructura que el buscador del enlace de
                             registro: dos lineas por fila —el nombre y donde
                             queda— y una etiqueta a la derecha que dice de que
                             nivel es. Hay 99 nombres de distrito repetidos en
                             el catalogo ("Santa Rosa" sale 10 veces): sin el
                             contexto no se puede elegir bien.

                             Distritos, provincias y departamentos van en UNA
                             sola lista y en el orden que manda el buscador. La
                             version anterior los partia en dos secciones y
                             mandaba las provincias al final, bajo un titulo
                             («¿No está en la lista?») que se leia como aviso y
                             no como camino: por eso parecia que solo salia lo
                             tecleado. -->
                        <el-option-group v-if="ubigeoFilas.length" :label="ubigeoTitulo">
                            <el-option
                                v-for="r in ubigeoFilas"
                                :key="r.type + '-' + (r.district_id || '') + '-' + (r.province_id || '') + '-' + (r.department_id || '')"
                                :label="r.type === 'district'
                                    ? r.name + ' — ' + r.province_name + ', ' + r.department_name
                                    : r.name"
                                :value="r.type === 'district'
                                    ? r.district_id
                                    : '__g' + r.type + (r.province_id || '') + (r.department_id || '')"
                                :disabled="r.type !== 'district'"
                                :class="r.type === 'district' ? 'mo-ub-row' : 'mo-ub-row mo-ub-act'"
                                @mousedown.native="r.type === 'district' ? null : abrirZona($event, r)"
                            >
                                <span class="mo-ub-main">
                                    <span class="mo-ub-name">{{ r.name }}</span>
                                    <span class="mo-ub-ctx">{{ contextoZona(r) }}</span>
                                </span>
                                <span class="mo-ub-tag" :class="'is-' + r.type">{{ etiquetaZona(r) }}</span>
                                <span v-if="r.type !== 'district'" class="mo-ub-chev">&#8250;</span>
                            </el-option>
                        </el-option-group>
                    </el-select>
                    <!-- Que la lista se pueda abrir por provincia hay que
                         DECIRLO: quien no lo sabe, cuando su distrito no sale
                         a la primera, da por hecho que no esta. -->
                    <small v-if="destinoElegido" class="mo-hint mo-hint--ok">
                        Enviamos a {{ destinoElegido }}
                    </small>
                    <small v-else class="mo-hint">
                        Escribe la ciudad. Si no sale la que buscas, abre su
                        provincia desde la misma lista.
                    </small>
                </div>

                <div class="mo-f">
                    <label class="mo-lbl req">Agencia de transporte</label>

                    <!-- Dos grupos y no una lista sola: el catalogo son 12
                         transportistas nacionales, y no es con lo que trabaja
                         una tienda. Las que este negocio ya ha usado van
                         primero porque son las que va a elegir casi siempre.

                         Y «Otros» explicito: antes esto era `allow-create` a
                         secas, que funciona pero no se ve. Quien no sabia que
                         podia escribir encima del desplegable daba por hecho
                         que su agencia no se podia poner. -->
                    <el-select
                        v-if="agenciaModo === 'lista'"
                        v-model="envio.shipping_agency"
                        class="mo-sel"
                        filterable
                        clearable
                        placeholder="— Selecciona —"
                        @change="alElegirAgencia"
                    >
                        <el-option-group v-if="agenciasUsadas.length" label="Las que usas">
                            <el-option v-for="a in agenciasUsadas" :key="'u-' + a" :label="a" :value="a" />
                        </el-option-group>
                        <el-option-group v-if="agenciasCatalogo.length" label="Catálogo">
                            <el-option v-for="a in agenciasCatalogo" :key="'c-' + a" :label="a" :value="a" />
                        </el-option-group>
                        <el-option label="Otros (escribirla a mano)" :value="OTRA_AGENCIA" />
                    </el-select>

                    <template v-else>
                        <input
                            ref="agencia"
                            v-model="envio.shipping_agency"
                            type="text"
                            class="mo-input"
                            maxlength="120"
                            placeholder="Nombre de la agencia"
                        />
                        <small class="mo-hint">
                            <button type="button" class="mo-link" @click="volverALaLista">
                                Elegir una de la lista
                            </button>
                        </small>
                    </template>
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
                            ref="recogeNombre"
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
                            ref="recogeDni"
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
                        ref="domicilio"
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
                 Dos formas de contestarlo, y no son la misma cosa:

                 - **Del sistema**: una linea del catalogo es una VENTA. Lleva
                   `item_id`, precio y reserva de stock, y alimenta la nota de
                   venta —donde `sale_note_items.item_id` es NOT NULL—.
                 - **A mano**: texto que se imprime en el rotulo para que la
                   agencia sepa que lleva la caja. No tiene precio, no mueve
                   stock y no se factura, y por eso vive en el ENVIO.

                 Lo segundo NO sustituye a lo primero: un pedido cobrado con
                 solo texto libre no se podria documentar. -->
            <div class="mo-head mo-head--2">
                <h2 class="mo-h">Qué lleva el pedido</h2>
                <p class="mo-sub">Del catálogo, o escrito a mano para el rótulo.</p>
            </div>

            <div v-if="!lineasEditables" class="mo-frozen">
                Este pedido ya está en preparación: los productos quedaron fijados.
                Puedes corregir los datos del cliente. Si hay que cambiar el
                contenido, anúlalo y crea uno nuevo.
            </div>

            <h4 class="mo-sec">Del sistema</h4>
            <p class="mo-sub mo-sub--2">
                Busca por nombre o código. Descuentan stock y salen en el comprobante.
            </p>

            <product-lines
                ref="productos"
                v-model="form.items"
                :channel-id="form.channel_id"
                :disabled="!lineasEditables"
                :can-edit-price="puedeEditarPrecio"
            ></product-lines>

            <!-- Sin modulo de Envios no hay rotulo donde imprimir esto, y el
                 campo se tragaria lo que le escribieran. -->
            <template v-if="shippingModule">
                <h4 class="mo-sec mo-sec--2">Escrito a mano</h4>
                <p class="mo-sub mo-sub--2">
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
        </div>

        <span slot="footer">
            <el-button @click="cerrar">Cancelar</el-button>
            <!-- El boton NO se apaga por campos sin llenar.
                 Apagado no explicaba nada: el operador llegaba al final, lo
                 veia gris y no tenia forma de saber cual de los nueve
                 requisitos faltaba. Ahora se pulsa siempre y, si falta algo,
                 lo dice y lleva el cursor al campo. Solo sigue bloqueado
                 mientras guarda o carga, que es cuando pulsarlo otra vez
                 crearia el pedido dos veces. -->
            <el-button
                type="primary"
                :loading="guardando"
                :disabled="guardando || cargando || bloqueado"
                :title="queFalta.length ? 'Falta: ' + queFalta.map(x => x.texto).join(' ') : ''"
                @click="guardar"
            >
                {{ editando ? "Guardar cambios" : "Crear pedido" }}
                <span v-if="queFalta.length" class="mo-falta-n">{{ queFalta.length }}</span>
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
            // Las que este negocio ya ha usado, por uso. Subconjunto de
            // `agencias`: de ahi sale el grupo «Las que usas».
            agenciasUsadas: [],
            // Centinela del desplegable. Empieza por `__` para que no pueda
            // chocar con el nombre de una agencia de verdad.
            OTRA_AGENCIA: "__otra__",
            agenciaModo: "lista",
            // Los destinos a los que ya se le ha enviado a este documento.
            anteriores: [],
            anteriorElegido: null,
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
            // Donde estamos cuando se ha entrado en una provincia o un
            // departamento: vacio = lo que devolvio la busqueda.
            ubigeoRuta: "",
            ubigeoQuery: "",
            ubigeoLoading: false,
            // Por que no hay resultados, cuando el motivo NO es «no existe esa
            // ciudad». Se pinta dentro del propio desplegable.
            ubigeoFallo: "",
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

            // ── Carga y guardado ─────────────────────────────────────────
            cargando: false,
            guardando: false,
            problemas: [],
            // Copia de los datos de envio tal como llegaron. Sirve para no
            // reescribir el envio —ni dejar una linea en su bitacora— cuando
            // el operador solo vino a corregir un telefono.
            envioOriginal: null,
            // El texto del rotulo. Vive en el ENVIO, no en el pedido, asi que
            // viaja dentro de `payloadEnvio()` y no del alta.
            packageContent: "",
            packageContentOriginal: "",
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
        /**
         * Lo que queda del catalogo una vez quitadas las que ya usa.
         *
         * El servidor manda `agencias` ya mergeadas y `agenciasUsadas` aparte;
         * aqui solo se resta, para no pintar la misma agencia en los dos
         * grupos. La comparacion es en minusculas porque en produccion
         * conviven «marvisur» y «Marvisur».
         */
        agenciasCatalogo() {
            const usadas = this.agenciasUsadas.map(a => a.toLowerCase());

            return this.agencias.filter(a => usadas.indexOf(a.toLowerCase()) === -1);
        },
        esEmpresa() {
            return this.docType === "dni" && (this.documento || "").length === 11;
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
                !!(this.envio.shipping_agency || "").trim() ||
                // Escribir el rotulo exige tener rotulo: sin envio no hay
                // donde imprimirlo, asi que hay que dar tambien el destino.
                this.packageContent !== this.packageContentOriginal
            );
        },
        /**
         * QUE falta para poder guardar, con su nombre y donde esta.
         *
         * Es la UNICA lista: `sePuedeGuardar` se deriva de aqui. Antes eran
         * nueve `return false` seguidos y el boton se apagaba sin decir nada:
         * el operador veia un boton gris, no sabia cual de los nueve le
         * faltaba, y lo reportaba —con razon— como «el boton guardar no
         * funciona». Dos listas habrian divergido al primer campo nuevo.
         */
        /**
         * UNA lista, en el orden que manda el buscador.
         *
         * Separarla en «lo que se elige» y «lo que se abre» mandaba las
         * provincias al final, debajo de un titulo que sonaba a disculpa. El
         * operador que escribia «Piura» veia una fila, la de Piura, y daba por
         * hecho que el resto de la provincia no estaba. El buscador ya puntua:
         * respetar su orden es mas honesto que reordenar por tipo.
         */
        ubigeoFilas() {
            return (this.ubigeoResults || []).filter(r => r && r.type);
        },

        /** El titulo dice que se hace con la lista, no que contiene. */
        ubigeoTitulo() {
            if (!this.ubigeoRuta) {
                return "Elige tu ciudad, o abre una provincia para ver sus distritos";
            }

            return (this.ubigeoFilas[0] || {}).type === "province"
                ? "Elige la provincia"
                : "Elige el distrito";
        },

        /**
         * ¿El pedido dice QUE lleva?
         *
         * Por cualquiera de las dos vias, no solo por el catalogo. Un encargo
         * logistico —una caja que se manda por agencia y cuyo contenido se
         * escribe a mano en el rotulo— no tiene producto del sistema que
         * agregar, y exigirselo obligaba a inventarse uno. Se reporto asi:
         * «me condiciona a agregar un producto del sistema, no importa si
         * esta escrito manualmente».
         *
         * Sin modulo de Envios no hay rotulo: ahi la unica via es el catalogo.
         */
        hayContenido() {
            if (this.form.items.length) return true;

            return this.shippingModule && !!(this.packageContent || "").trim();
        },
        queFalta() {
            const f = [];
            const pide = (cond, texto, donde) => { if (cond) f.push({ texto, donde }); };

            pide(!this.form.channel_id, "Elige el canal de venta.", "canal");
            pide(!(this.form.name || "").trim(), "Falta el nombre del cliente.", "name");
            pide(
                !/^9\d{8}$/.test(this.form.phone || ""),
                "El celular tiene que ser de 9 dígitos y empezar por 9.",
                "phone"
            );

            // Vaciar un pedido no es editarlo: para eso esta anular, que
            // conserva el historico. El servidor lo rechaza igual.
            //
            // Pero un pedido que YA nacio sin lineas es un encargo logistico
            // —lo que lleva la caja se escribe a mano en el envio—, y exigirle
            // una linea dejaba su ficha imposible de guardar: ni para
            // corregirle el telefono al cliente.
            pide(
                !this.hayContenido && !(this.editando && !this.teniaLineas),
                this.shippingModule
                    ? "Di qué lleva el pedido: un producto del catálogo, o escríbelo a mano para el rótulo."
                    : "Agrega al menos un producto del catálogo.",
                "productos"
            );

            if (this.destinoRequerido) {
                pide(!this.envio.district_id, "Falta la ciudad de destino.", "ciudad");
                pide(
                    !(this.envio.shipping_agency || "").trim(),
                    "Falta la agencia de transporte.",
                    "agencia"
                );
                pide(
                    this.envio.a_domicilio && !(this.envio.shipping_destination || "").trim(),
                    "Marcaste que la agencia lleva el paquete a domicilio: escribe la dirección.",
                    "domicilio"
                );
                if (this.esEmpresa) {
                    pide(
                        !(this.envio.pickup_person_name || "").trim(),
                        "El documento es un RUC: la agencia no entrega a una razón social. Escribe quién recoge.",
                        "recogeNombre"
                    );
                    pide(
                        (this.envio.pickup_person_dni || "").length < 8,
                        "Falta el DNI de quien recoge (8 dígitos).",
                        "recogeDni"
                    );
                }
            }

            return f;
        },

        sePuedeGuardar() {
            if (this.guardando || this.cargando || this.bloqueado) return false;

            return this.queFalta.length === 0;
        },
        pct() {
            const req = [
                !!this.form.channel_id,
                !!(this.form.name || "").trim(),
                /^9\d{8}$/.test(this.form.phone || ""),
            ];

            // Ver `sePuedeGuardar`: al encargo logistico no se le piden.
            if (!(this.editando && !this.teniaLineas)) {
                req.push(this.hayContenido);
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
            this.agenciaModo = "lista";
            this.anteriores = [];
            this.anteriorElegido = null;
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
            this.ubigeoFallo = "";
            this.ubigeoQuery = "";
            this.ubigeoRuta = "";
            this.ubigeoLoading = false;
            this.nombreManual = false;
            this.nombreTraido = "";
            this.origen = "";
            this.estadoDoc = "";
            this.estadoEsAviso = false;
            this.buscando = false;
            this.docConsultado = "";

            this.cargando = false;
            this.guardando = false;
            this.problemas = [];
            this.envioOriginal = null;
            this.packageContent = "";
            this.packageContentOriginal = "";
            this.lineasEditables = true;
            this.teniaLineas = true;
            this.bloqueado = false;

            // Las respuestas en vuelo son del pedido ANTERIOR: se descartan
            // subiendo el contador, o llegarian a pintar sobre el nuevo. El
            // buscador de productos hace lo suyo: `product-lines` se
            // reconstruye con el `form.items` vacio.
            this.peticion++;
        },

        cargarCatalogos() {
            this.$http
                .get("/orders/channels")
                .then(r => {
                    const d = r.data || {};
                    this.agencias = d.agencies || [];
                    this.agenciasUsadas = d.agencies_used || [];
                    this.canales = d.channels || [];

                    // Un pedido viejo puede llevar una agencia que ya no esta
                    // en ninguna lista. Se abre en modo «a mano» para que se
                    // vea, en vez de quedar en blanco y borrarla al guardar.
                    this.ajustarModoAgencia();
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
                    this.agenciasUsadas = [];
                    this.canales = [];
                    this.ajustarModoAgencia();
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

                    // `null` = el pedido no tiene envio todavia. Como
                    // cadena vacia se compara igual y no falsea un cambio.
                    this.packageContent =
                        d.package_content === null || d.package_content === undefined
                            ? ""
                            : String(d.package_content);
                    this.packageContentOriginal = this.packageContent;

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
            // `cargarCatalogos()` y `cargar()` corren en paralelo: gane quien
            // gane, el que llega segundo deja el modo bien.
            this.ajustarModoAgencia();
            this.envio.reference = envio.reference || "";
            this.envio.shipping_destination = envio.shipping_destination || "";
            this.envio.a_domicilio = !!(envio.shipping_destination || "").trim();
            this.envio.pickup_person_name = envio.pickup_person_name || "";
            this.envio.pickup_person_dni = envio.pickup_person_dni || "";
            this.form.alternate_phone = (envio.alternate_phone || "").replace(/\D+/g, "");

            if (envio.district_id) {
                // Un destino anterior trae ademas `ciudad` y `contexto` ya
                // resueltos por el servidor; el de `record` no, y ahi hay que
                // conformarse con `destination_city`.
                const ciudad = envio.ciudad || envio.destination_city || envio.district_id;
                const ctx = envio.contexto || "";

                this.ubigeoResults = [
                    {
                        // El `type` no es decorativo: las filas que no son
                        // distrito se pintan deshabilitadas, y sin el la fila
                        // sembrada del destino guardado se dibujaba gris y no
                        // se podia volver a elegir.
                        type: "district",
                        district_id: envio.district_id,
                        name: ciudad,
                        province_name: "",
                        department_name: "",
                        context: ctx || "Destino guardado",
                    },
                ];
                this.envio.district_id = envio.district_id;
                this.destinoElegido = ctx ? ciudad + " — " + ctx : ciudad;
            }
        },

        /**
         * Si la agencia guardada no esta en la lista, el campo tiene que
         * abrirse escrito a mano. Un desplegable que no puede representar su
         * propio valor lo muestra vacio, y el siguiente guardado lo borra.
         */
        ajustarModoAgencia() {
            const a = (this.envio.shipping_agency || "").trim();

            if (!a) return;

            const estaEnLaLista = this.agencias.some(
                x => x.toLowerCase() === a.toLowerCase()
            );

            this.agenciaModo = estaEnLaLista ? "lista" : "otra";
        },

        /**
         * Copia un destino anterior al formulario.
         *
         * Se reusa `pintarEnvio()`, que es quien ya sabe sembrar la fila del
         * ubigeo para que el desplegable remoto pueda pintar su etiqueta, y
         * ajustar el modo de la agencia cuando no esta en la lista. Escribir
         * los campos a mano aqui habria sido una segunda copia de esa logica.
         */
        usarAnterior(i) {
            const a = this.anteriores[i];
            if (!a) return;

            this.anteriorElegido = i;
            this.pintarEnvio(a);

            // `pintarEnvio` guarda lo que llega como «lo que ya estaba» para
            // no reescribir el envio sin cambios. Aqui es al reves: esto es un
            // destino NUEVO para este pedido y si hay que guardarlo.
            this.envioOriginal = null;

            // El telefono del envio anterior solo se pone si no hay uno: lo
            // que el operador acaba de escribir manda sobre lo historico.
            if (!this.form.phone && a.phone) {
                this.form.phone = String(a.phone).replace(/\D+/g, "");
            }
        },

        alElegirAgencia(valor) {
            if (valor !== this.OTRA_AGENCIA) return;

            // El centinela no es una agencia: no puede quedarse en el campo.
            this.envio.shipping_agency = "";
            this.agenciaModo = "otra";
            this.$nextTick(() => this.$refs.agencia && this.$refs.agencia.focus());
        },

        volverALaLista() {
            this.envio.shipping_agency = "";
            this.agenciaModo = "lista";
        },

        /**
         * El canal decide el almacen, y el almacen decide el disponible. Lo
         * que ya estaba en la tabla se midio contra otro: dejar el numero
         * viejo seria peor que no enseñar ninguno.
         */
        alCambiarCanal() {
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
            this.ubigeoRuta = "";

            if (this.ubigeoQuery.length < 2) {
                this.ubigeoResults = [];
                return;
            }

            this.ubigeoLoading = true;
            this.ubigeoFallo = "";
            this.$http
                // `v=2` pide ademas las filas de provincia y departamento. Sin
                // ellas la respuesta son distritos sueltos y punto: quien
                // escribia «Piura» veia cuatro o cinco y no tenia por donde
                // llegar a los demas de esa misma provincia. El servicio ya
                // sabia devolverlas —las usa el formulario publico de envios—,
                // esta pantalla simplemente no las pedia.
                .get("/orders/ubigeo/buscar", { params: { q: this.ubigeoQuery, v: 2 } })
                .then(r => {
                    // La respuesta TIENE que ser una lista. Si llega otra cosa
                    // —lo tipico es el HTML del login cuando la sesion caduco,
                    // que axios entrega con un 200 porque siguio el redirect—,
                    // asignarla dejaba el desplegable vacio y MUDO: ni un
                    // resultado, ni un error, ni una pista. Es exactamente lo
                    // que se reporto como «escribo y no sale ninguna lista».
                    if (!Array.isArray(r.data)) {
                        this.ubigeoResults = [];
                        this.ubigeoFallo =
                            "Tu sesión caducó. Recarga la página (Ctrl+F5) y vuelve a entrar.";
                        this.$message.error(this.ubigeoFallo);

                        return;
                    }

                    this.ubigeoResults = r.data;
                })
                .catch(e => {
                    // Un fallo de red pintado como «no hay resultados» es un
                    // problema que no se arregla buscando otra cosa.
                    this.ubigeoResults = [];

                    const code = (e.response && e.response.status) || 0;
                    this.ubigeoFallo =
                        code === 401 || code === 419
                            ? "Tu sesión caducó. Recarga la página (Ctrl+F5)."
                            : code === 404
                            ? "El buscador de ciudades no está disponible en esta tienda."
                            : "No se pudo buscar la ciudad" + (code ? " (error " + code + ")" : "") + ". Reintenta.";
                    this.$message.error(this.ubigeoFallo);
                })
                .then(() => {
                    this.ubigeoLoading = false;
                });
        },

        /** La etiqueta de nivel, igual que en el enlace de registro. */
        etiquetaZona(r) {
            if (r.type === "province") return "Provincia";
            if (r.type === "department") return "Departamento";

            return "Distrito";
        },

        /**
         * La segunda linea: donde queda, y cuanto hay dentro si se puede abrir.
         *
         * La cuenta («10 distritos») es lo que convierte la fila en un camino
         * visible. Sin ella, una provincia parecia una opcion elegible que
         * ademas estaba deshabilitada, que es justo lo contrario de lo que es.
         */
        contextoZona(r) {
            const base = r.context || "";

            if (r.type === "province" && r.district_count) {
                return base + " · " + r.district_count + " distritos";
            }

            if (r.type === "department" && r.province_count) {
                return base + " · " + r.province_count + " provincias";
            }

            return base;
        },

        /**
         * Abrir una zona va en `mousedown`, no en `click`.
         *
         * El `click` sobre una opcion deshabilitada llega DESPUES de que el
         * desplegable haya reaccionado al foco, y el `preventDefault` del
         * mousedown es lo unico que evita que el input pierda el cursor y la
         * lista se cierre antes de repintarse. Con `click` la fila se pulsaba
         * y no pasaba nada visible.
         */
        abrirZona(ev, r) {
            if (ev) {
                ev.preventDefault();
                ev.stopPropagation();
            }

            this.abrirGrupo(r);
        },

        /**
         * Abre una provincia (sus distritos) o un departamento (sus
         * provincias) DENTRO del mismo desplegable.
         *
         * El buscador puntua y corta: de una provincia grande solo asoman los
         * distritos que mas se parecen a lo tecleado, y de un departamento no
         * asoma ninguno —Lima son 171—. Eso esta bien para no llenar la lista
         * de ruido, pero dejaba sin camino al operador que busca un distrito
         * cuyo nombre no se parece al de su ciudad: Pariñas en Talara, Veintiseis
         * de Octubre en Piura. Se reporto como «no me filtra las demas
         * provincias de ese destino».
         *
         * Son los MISMOS endpoints de la cascada de Envios: no hay un segundo
         * catalogo que mantener.
         */
        abrirGrupo(r) {
            if (!r || r.type === "district") return;

            const esProvincia = r.type === "province";
            const url = esProvincia
                ? "/orders/ubigeo/distritos/" + r.province_id
                : "/orders/ubigeo/provincias/" + r.department_id;

            this.ubigeoLoading = true;
            this.ubigeoFallo = "";
            this.$http
                .get(url)
                .then(resp => {
                    // Misma trampa que en la busqueda: la sesion caducada
                    // llega como el HTML del login con un 200.
                    if (!Array.isArray(resp.data)) {
                        this.ubigeoFallo =
                            "Tu sesión caducó. Recarga la página (Ctrl+F5) y vuelve a entrar.";
                        this.$message.error(this.ubigeoFallo);

                        return;
                    }

                    this.ubigeoResults = resp.data.map(x =>
                        esProvincia
                            ? {
                                  type: "district",
                                  district_id: x.id,
                                  province_id: r.province_id,
                                  department_id: r.department_id,
                                  name: x.description,
                                  province_name: r.province_name || r.name,
                                  department_name: r.department_name,
                                  context:
                                      "Distrito · " +
                                      (r.province_name || r.name) +
                                      " · " +
                                      r.department_name,
                              }
                            : {
                                  type: "province",
                                  district_id: null,
                                  province_id: x.id,
                                  department_id: r.department_id,
                                  name: x.description,
                                  province_name: x.description,
                                  department_name: r.name,
                                  context: "Provincia · " + r.name,
                              }
                    );

                    this.ubigeoRuta = esProvincia
                        ? "Distritos de " + r.name
                        : "Provincias de " + r.name;
                })
                .catch(() => {
                    this.ubigeoFallo =
                        "No se pudo abrir " + r.name + ". Reintenta.";
                    this.$message.error(this.ubigeoFallo);
                })
                .then(() => {
                    this.ubigeoLoading = false;
                });
        },

        /** Deshace el `abrirGrupo` sin obligar a reescribir la búsqueda. */
        volverAlaBusqueda() {
            this.buscarUbigeo(this.ubigeoQuery);
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
            // Los destinos eran de la persona ANTERIOR. Se sueltan aqui, no
            // cuando llegue la respuesta, o la pantalla pasaria un segundo
            // ofreciendo la direccion de otro cliente.
            this.anteriores = [];
            this.anteriorElegido = null;
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

                    // Llegan encuentre o no al cliente en la cartera: el envio
                    // guarda el documento por su cuenta.
                    this.anteriores = d.shipments || [];
                    this.anteriorElegido = null;

                    // Con un solo destino no hay nada que elegir: se pone. Con
                    // varios se pregunta, que es justo lo que no se puede
                    // adivinar. En una EDICION no se toca nada: el pedido ya
                    // tiene su destino y no es esto quien debe cambiarlo.
                    if (!this.editando && this.anteriores.length === 1) {
                        this.usarAnterior(0);
                    }

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
            const p = {
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

            // El rotulo vacio se OMITE en un alta, no se manda como null:
            // `OrderShipmentLinker::prefill()` arma un resumen con los
            // productos del pedido, y `ensure()` hace
            // `array_merge(prefill, overrides)` — un null explicito ganaria y
            // dejaria el rotulo en blanco teniendo de que llenarlo.
            //
            // Vaciarlo A PROPOSITO al editar si se respeta: ahi el null es
            // una decision del operador, no un campo que nadie toco.
            if (this.packageContent !== this.packageContentOriginal) {
                p.package_content = this.packageContent || null;
            } else if (this.packageContent) {
                p.package_content = this.packageContent;
            }

            return p;
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
            ].some(k => (this.envioOriginal[k] || null) !== (payload[k] || null))
                // `envioOriginal` viene de `shipping`, que no trae el texto
                // del bulto: se compara aparte o no se guardaria nunca.
                || this.packageContent !== this.packageContentOriginal;
        },

        /**
         * Lleva la vista y el cursor al campo que falta.
         *
         * El modal tiene scroll propio y el aviso se pinta ARRIBA del todo:
         * sin esto, decir «falta la ciudad de destino» obligaba a buscarla a
         * mano en un formulario de cinco bloques.
         */
        irA(donde) {
            this.$nextTick(() => {
                const r = this.$refs[donde];
                if (!r) return;

                const el = r.$el || r;
                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ behavior: "smooth", block: "center" });
                }

                // `el-select` y `el-input` enfocan por metodo; un <input>
                // pelado, por el suyo. Y el de product-lines no enfoca nada:
                // su buscador vive dentro del hijo.
                if (r.focus) {
                    r.focus();
                } else if (el && el.querySelector) {
                    const dentro = el.querySelector("input, textarea");
                    if (dentro) dentro.focus();
                }
            });
        },

        guardar() {
            // Primero lo que falta. Antes esto no existia porque el boton
            // estaba apagado hasta que todo estuviera: el precio era que nadie
            // sabia QUE faltaba.
            const falta = this.queFalta;
            if (falta.length) {
                this.problemas = falta.map(x => x.texto);
                this.$message.warning(
                    falta.length === 1
                        ? falta[0].texto
                        : "Faltan " + falta.length + " datos para crear el pedido."
                );
                this.irA(falta[0].donde);

                return;
            }

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
                    // El rotulo se guarda en el ENVIO, en la peticion de
                    // despues. Viaja tambien aqui porque es lo que permite al
                    // servidor aceptar un pedido sin lineas: sin el, un
                    // encargo escrito a mano seria indistinguible de un pedido
                    // vacio.
                    package_content: (this.packageContent || "").trim() || null,
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
/* Despues de `.mo-hint` y no antes: misma especificidad, gana la ultima. */
.mo-hint--ok {
    color: #15803d;
    font-weight: 600;
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
/* ── Filas del buscador de destino ──────────────────────────────────────
   Misma estructura que el buscador del enlace de registro: dos lineas y una
   etiqueta de nivel a la derecha.

   Sin prefijo `.mo` y con colores literales a proposito: el desplegable de
   Element se cuelga del <body>, fuera del formulario, asi que ni un selector
   descendiente ni las variables declaradas en `.mo` llegan hasta aqui. */
.mo-ub-row.el-select-dropdown__item {
    display: flex;
    align-items: center;
    gap: 10px;
    height: auto;
    padding: 9px 14px;
    line-height: 1.25;
}
.mo-ub-main {
    min-width: 0;
    flex: 1;
}
.mo-ub-name {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mo-ub-ctx {
    display: block;
    margin-top: 2px;
    font-size: 11.5px;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mo-ub-tag {
    flex: 0 0 auto;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    padding: 3px 7px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
}
.mo-ub-tag.is-province {
    background: #fef3c7;
    color: #92400e;
}
.mo-ub-tag.is-department {
    background: #e0e7ff;
    color: #3730a3;
}

/* Las filas que se ABREN (volver, provincia, departamento) van deshabilitadas
   para que no se puedan elegir —el envio guarda un distrito—, y Element las
   pinta en el gris de «aqui no hay nada», que es justo lo contrario de lo que
   hacen. Se les devuelve el color, el cursor y el peso. */
.mo-ub-act.is-disabled {
    cursor: pointer;
    background: #fff;
}
.mo-ub-act.is-disabled .mo-ub-name {
    color: #1e293b;
}
.mo-ub-act.is-disabled .mo-ub-ctx {
    color: #94a3b8;
}
.mo-ub-act.is-disabled:hover {
    background: #eef2ff;
}
.mo-ub-back {
    font-size: 13px;
    font-weight: 600;
    color: #2563eb;
}
.mo-ub-chev {
    flex: 0 0 auto;
    color: #cbd5e1;
    font-size: 15px;
    font-weight: 700;
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

/* Destinos anteriores del cliente */
.mo-prev {
    margin-bottom: 16px;
}
.mo-prev-t {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 8px;
}
.mo-prev-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 10px;
}
.mo-prev-c {
    display: flex;
    flex-direction: column;
    gap: 2px;
    text-align: left;
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    transition: 0.15s;
    min-width: 0;
}
.mo-prev-c:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.mo-prev-c.active {
    border-color: var(--brand);
    background: #eff6ff;
}
.mo-prev-city {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
}
.mo-prev-ctx,
.mo-prev-ag,
.mo-prev-dir {
    font-size: 12px;
    color: var(--muted);
    /* Una direccion larga no puede ensanchar la tarjeta y con ella la
       rejilla: se corta con puntos suspensivos y el titulo la completa. */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mo-prev-ag {
    color: #475569;
    font-weight: 600;
}
.mo-prev-meta {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
}

/* Subsecciones dentro de un bloque: «Del sistema» / «Escrito a mano» */
.mo-sec {
    font-size: 15px;
    font-weight: 800;
    margin: 0 0 2px;
    letter-spacing: -0.01em;
    color: var(--ink);
}
.mo-sec--2 {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}
.mo-sub--2 {
    font-size: 12.5px;
    margin-bottom: 10px;
}

/* Avisos del servidor y carga */
/* Cuantos datos faltan, en el propio boton. Un numero pequeno no asusta y
   evita el otro extremo: una lista de nueve avisos nada mas abrir el modal,
   cuando todavia no se ha escrito nada y «faltar» es lo normal. */
.mo-falta-n {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    margin-left: 7px;
    padding: 0 5px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.28);
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

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
}
</style>
