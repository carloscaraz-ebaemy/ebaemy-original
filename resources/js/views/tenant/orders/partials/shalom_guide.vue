<template>
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="conGuia ? 'Guía de Shalom' : 'Crear guía en Shalom'"
        top="6vh"
        width="500px"
        custom-class="shg-dialog"
        @close="cerrar"
        @open="abrir"
    >
        <div v-if="error" class="shg-alert">{{ error }}</div>
        <div v-if="aviso" class="shg-okline">{{ aviso }}</div>

        <!-- ═══════════════ CON GUÍA: ver, imprimir y cambiar ═══════════════ -->
        <template v-if="conGuia">
            <div class="shg-card">
                <div class="shg-card__n">{{ tracking || "Número en camino…" }}<span v-if="sh.code"> · {{ sh.code }}</span></div>
                <div class="shg-card__grid">
                    <span>Oficina</span><b>{{ sh.office || "—" }}</b>
                    <span>Clave de recojo</span><b>{{ sh.clave || "—" }}</b>
                    <span>Bultos</span><b>{{ sh.bultos || 1 }}</b>
                    <span v-if="sh.status">Estado</span><b v-if="sh.status">{{ estadoTxt }}</b>
                </div>
                <div v-if="sh.label_url || sh.ticket_url" class="shg-card__print">
                    <el-button v-if="sh.label_url" size="small" type="primary" icon="el-icon-printer" @click="abrirUrl(sh.label_url)">Imprimir rótulo</el-button>
                    <el-button v-if="sh.ticket_url" size="small" icon="el-icon-tickets" @click="abrirUrl(sh.ticket_url)">Ticket con QR</el-button>
                </div>
            </div>

            <!-- Menú de cambios: una lista corta, cada opción dice qué hace. -->
            <template v-if="!modo">
                <div class="shg-sec">¿Cambió algo?</div>
                <p v-if="sh.manage_block" class="shg-warn">{{ sh.manage_block }}</p>
                <button type="button" class="shg-opt" :disabled="!!sh.manage_block" @click="ir('destino')">
                    <b>Cambiar la oficina de destino</b><span>El cliente recogerá en otra oficina de Shalom.</span>
                </button>
                <button type="button" class="shg-opt" :disabled="!!sh.manage_block" @click="ir('contacto')">
                    <b>Cambiar quién recoge</b><span>Otra persona retirará el paquete con su documento.</span>
                </button>
                <button type="button" class="shg-opt" :disabled="!!sh.manage_block" @click="ir('clave')">
                    <b>Cambiar la clave de recojo</b><span>Los 4 dígitos que el cliente dice en la agencia.</span>
                </button>
                <button type="button" class="shg-opt" :disabled="!sh.can_remove" @click="ir('bultos')">
                    <b>Cambiar la cantidad de bultos</b>
                    <span>{{ sh.can_remove ? 'Se rehace la guía con la nueva cantidad.' : 'Shalom ya recibió el paquete: se corrige en el mostrador de la agencia.' }}</span>
                </button>
                <p class="shg-foot">
                    ¿Reparto a domicilio, devolución o retener la carga? Pídelo en
                    <a href="https://pro.shalom.pe" target="_blank" rel="noopener">pro.shalom.pe</a>.
                </p>
                <button v-if="sh.can_remove" type="button" class="shg-danger" @click="ir('quitar')">Quitar esta guía del pedido</button>
            </template>

            <!-- Cambiar oficina / quién recoge: piden un código a Shalom. -->
            <template v-else-if="modo === 'destino' || modo === 'contacto'">
                <a class="shg-back" @click="volver">← Volver</a>
                <div class="shg-sec">{{ modo === 'destino' ? 'Nueva oficina de destino' : 'Nueva persona que recoge' }}</div>

                <template v-if="!challenge">
                    <div v-if="modo === 'destino'" class="shg-field">
                        <label>Oficina de Shalom <span class="shg-req">*</span></label>
                        <el-select v-model="f.destino" filterable remote :remote-method="buscarOficinas" :loading="buscando"
                                   placeholder="Escribe la ciudad o el nombre de la oficina" style="width:100%">
                            <el-option v-for="o in oficinas" :key="o.id" :value="o.id" :label="o.name + ' — ' + o.place">
                                <div class="shg-o"><b>{{ o.name }}</b><small>{{ o.place }}</small></div>
                            </el-option>
                        </el-select>
                    </div>
                    <template v-else>
                        <div class="shg-field">
                            <label>Documento de quien recoge <span class="shg-req">*</span></label>
                            <el-input v-model="f.destinatario" maxlength="11" placeholder="DNI (8) o RUC (11)"></el-input>
                        </div>
                        <div class="shg-field">
                            <label>Nombre <small class="shg-opt-l">(para el panel)</small></label>
                            <el-input v-model="f.nombre" maxlength="150"></el-input>
                        </div>
                    </template>
                    <div class="shg-field">
                        <label>Celular que recibe el código <span class="shg-req">*</span></label>
                        <el-input v-model="f.telefono" maxlength="9" placeholder="9xxxxxxxx" style="max-width:200px"></el-input>
                        <small class="shg-sub">Shalom envía un código por SMS para autorizar el cambio. Usa el celular registrado en tu cuenta de Shalom Pro.</small>
                    </div>
                </template>

                <div v-else class="shg-field">
                    <label>Código que llegó por SMS <span class="shg-req">*</span></label>
                    <el-input v-model="f.codigo" maxlength="8" placeholder="Ej. M9M7NT" style="max-width:200px"></el-input>
                    <small class="shg-sub">Vence en 10 minutos. Si no llegó, vuelve atrás y pídelo de nuevo.</small>
                </div>
            </template>

            <template v-else-if="modo === 'clave'">
                <a class="shg-back" @click="volver">← Volver</a>
                <div class="shg-sec">Nueva clave de recojo</div>
                <div class="shg-field">
                    <el-input v-model="f.clave" maxlength="4" placeholder="4 dígitos" style="max-width:160px"></el-input>
                    <small class="shg-sub">Se cambia al instante en Shalom. Avísale la nueva clave al cliente.</small>
                </div>
            </template>

            <template v-else-if="modo === 'bultos'">
                <a class="shg-back" @click="volver">← Volver</a>
                <div class="shg-sec">Cambiar la cantidad de bultos</div>
                <div class="shg-field">
                    <label>¿Cuántos bultos son ahora?</label>
                    <el-input-number v-model="f.bultos" :min="1" :max="50" size="small"></el-input-number>
                </div>
                <p class="shg-warn">
                    Shalom no permite editar la cantidad. Se quita la guía {{ tracking }} y se crea una nueva con
                    {{ f.bultos }} {{ f.bultos === 1 ? 'bulto' : 'bultos' }}. Después anula la guía anterior en pro.shalom.pe.
                </p>
            </template>

            <template v-else-if="modo === 'quitar'">
                <a class="shg-back" @click="volver">← Volver</a>
                <div class="shg-sec">Quitar la guía del pedido</div>
                <div class="shg-field">
                    <label>¿Por qué la quitas? <span class="shg-req">*</span></label>
                    <el-input v-model="f.motivo" maxlength="255" placeholder="Ej. no se llevó a la agencia, cambió el tamaño…"></el-input>
                    <small class="shg-sub">Podrás crear otra. Después anula esta también en pro.shalom.pe.</small>
                </div>
            </template>
        </template>

        <!-- ═══════════════ SIN GUÍA: crearla ═══════════════ -->
        <div v-else-if="hecho" class="shg-ok">
            <div class="shg-ok__t">Guía creada en Shalom{{ aereo ? ' · vía aérea' : '' }}</div>
            <div v-if="hecho.number" class="shg-ok__n">{{ hecho.number }} · código {{ hecho.code }}</div>
            <div class="shg-ok__s">Clave de recojo del cliente: <b>{{ hecho.clave }}</b> · {{ bultos }} {{ bultos === 1 ? 'bulto' : 'bultos' }}</div>
            <el-button type="primary" icon="el-icon-printer" style="margin-top:12px" @click="abrirUrl(`/orders/envio/${shipmentId}/shalom-imprimir`)">
                Imprimir rótulo de Shalom
            </el-button>
        </div>

        <template v-else>
            <p v-if="!sh.ready" class="shg-warn">
                Antes, en <b>Configuración de envíos</b>, conecta tu cuenta de Shalom Pro y elige
                la oficina desde donde despachas.
            </p>
            <p class="shg-hint">
                La guía se crea en tu cuenta de Shalom Pro{{ sh.office ? ' hacia la oficina ' + sh.office : '' }}.
                Después imprimes el rótulo oficial y llevas el paquete ya registrado.
            </p>

            <div class="shg-field">
                <label>Tamaño del paquete <span class="shg-req">*</span></label>
                <el-radio-group v-model="content" size="small">
                    <el-radio-button v-for="c in tamanos" :key="c" :label="c">{{ c.replace('PAQUETE ', '') }}</el-radio-button>
                </el-radio-group>
                <small class="shg-sub">Shalom calcula el costo según el tamaño. Si el paquete es más grande o pesado que lo elegido, en el mostrador lo miden y corrigen el precio.</small>
            </div>

            <div class="shg-field">
                <label>¿Cuántos bultos? <span class="shg-req">*</span></label>
                <el-input-number v-model="bultos" :min="1" :max="50" size="small"></el-input-number>
                <small class="shg-sub">
                    Cuéntalos al alistar el pedido. Es una sola guía y una sola clave para todos;
                    Shalom los registra juntos{{ bultos > 1 ? ' y en cada caja va su rótulo' : '' }}.
                </small>
            </div>

            <div v-if="aereo" class="shg-field">
                <label>Envío aéreo: ¿qué contiene? <span class="shg-req">*</span></label>
                <el-select v-model="declaracion" placeholder="Declaración jurada" style="width:100%">
                    <el-option v-for="d in declaraciones" :key="d" :label="d" :value="d"></el-option>
                </el-select>
                <small class="shg-sub">El cliente eligió vía aérea; Shalom pide declarar el contenido.</small>
            </div>

            <div class="shg-field">
                <label>Clave de recojo <small class="shg-opt-l">(4 dígitos, opcional)</small></label>
                <el-input v-model="clave" maxlength="4" :placeholder="sh.clave_fija ? 'Tu clave fija: ' + sh.clave_fija : 'Se genera sola'" style="max-width:180px"></el-input>
                <small class="shg-sub">
                    {{ sh.clave_fija ? 'Si la dejas vacía se usa tu clave fija ' + sh.clave_fija + '.' : 'El cliente la dice al recoger.' }}
                    Se la enviamos por WhatsApp cuando llegue.
                </small>
            </div>
        </template>

        <span slot="footer">
            <el-button @click="cerrar">{{ hecho || (conGuia && !modo) ? 'Cerrar' : 'Cancelar' }}</el-button>
            <el-button v-if="!conGuia && !hecho" type="primary" :loading="cargando"
                       :disabled="!content || !sh.ready || (aereo && !declaracion)" @click="crear">
                Crear guía
            </el-button>
            <el-button v-else-if="modo && accionTxt" :type="modo === 'quitar' ? 'danger' : 'primary'" :loading="cargando" @click="accion">
                {{ accionTxt }}
            </el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Todo lo de Shalom de un pedido en un solo lugar.
 *
 * Sin guía: se crea (tamaño, bultos, clave). Con guía: se ve, se imprime y se
 * cambia lo que Shalom permite cambiar. Antes eran seis entradas sueltas en
 * el menú de la fila; aquí cada opción dice qué hace y por qué no se puede
 * cuando no se puede.
 *
 * El servidor decide origen, destino y destinatario con los datos del envío,
 * y copia cada cambio confirmado al envío (ShalomGuideChanges).
 */
// Mismos hitos que ShalomTracking::STEPS, contados desde la tienda.
const ESTADOS = { registrado: "Registrado", origen: "En la agencia de origen", transito: "En camino", destino: "En la oficina de destino", reparto: "En reparto a domicilio", entregado: "Entregado" };

export default {
    props: {
        showDialog: { type: Boolean, default: false },
        orderId: { type: Number, default: null },
        shipmentId: { type: Number, default: null },
        shalom: { type: Object, default: null },
        packageCount: { type: Number, default: 1 },
        tracking: { type: String, default: null },
    },
    data() {
        return {
            tamanos: ["SOBRE", "PAQUETE XXS", "PAQUETE XS", "PAQUETE S", "PAQUETE M", "PAQUETE L"],
            content: "",
            bultos: 1,
            clave: "",
            declaracion: "",
            declaraciones: ["Artículos de uso personal", "Documentos", "Ropa", "Electrodomésticos"],
            cargando: false,
            error: null,
            aviso: null,
            hecho: null,
            // Con guía: qué cambio se está haciendo y sus datos.
            quitada: false,
            modo: null,
            challenge: null,
            f: {},
            oficinas: [],
            buscando: false,
        };
    },
    computed: {
        sh() {
            return this.shalom || {};
        },
        conGuia() {
            return !!this.sh.has_guide && !this.quitada;
        },
        aereo() {
            return !!this.sh.air;
        },
        estadoTxt() {
            return ESTADOS[this.sh.status] || this.sh.status;
        },
        accionTxt() {
            if (this.modo === "destino" || this.modo === "contacto") return this.challenge ? "Confirmar cambio" : "Enviar código";
            return { clave: "Guardar clave", bultos: "Rehacer la guía", quitar: "Quitar guía" }[this.modo] || null;
        },
    },
    methods: {
        abrir() {
            // El tamaño típico de la tienda (configuración de Shalom) ya viene marcado.
            this.content = this.sh.default_content || "";
            this.bultos = this.packageCount || 1;
            this.clave = "";
            this.declaracion = "";
            this.error = null;
            this.aviso = null;
            this.hecho = null;
            this.quitada = false;
            this.volver();
        },
        cerrar() {
            this.$emit("update:showDialog", false);
        },
        ir(modo) {
            this.modo = modo;
            this.challenge = null;
            this.error = null;
            this.aviso = null;
            this.f = {
                telefono: this.sh.store_phone || "",
                destinatario: "",
                nombre: "",
                destino: null,
                codigo: "",
                clave: "",
                bultos: Math.max(1, (this.sh.bultos || 1) + (modo === "bultos" ? 1 : 0)),
                motivo: "",
            };
        },
        volver() {
            this.modo = null;
            this.challenge = null;
            this.f = {};
        },
        abrirUrl(u) {
            window.open(u, "_blank");
        },
        buscarOficinas(q) {
            if (!q || q.length < 2) return;
            this.buscando = true;
            this.$http
                .get("/store-shipping/shalom-offices", { params: { q } })
                .then(r => { this.oficinas = Array.isArray(r.data) ? r.data : []; })
                .catch(() => { this.oficinas = []; })
                .then(() => { this.buscando = false; });
        },
        fallo(e, porDefecto) {
            const d = (e && e.response && e.response.data) || {};
            const porCampo = d.errors ? Object.values(d.errors).map(x => x[0]).join(" ") : null;
            this.error = porCampo || d.message || porDefecto;
        },
        post(url, body) {
            this.cargando = true;
            this.error = null;
            return this.$http
                .post(`/orders/${this.orderId}/envio/${url}`, body)
                .then(r => {
                    const d = r.data || {};
                    if (d.success === false) throw { response: { data: d } };
                    return d;
                })
                .finally(() => { this.cargando = false; });
        },
        terminado(msg) {
            this.$message.success(msg);
            this.$emit("saved");
            this.cerrar();
        },
        accion() {
            const m = this.modo;
            if (m === "clave") {
                if (!/^\d{4}$/.test(this.f.clave || "")) { this.error = "La clave son 4 dígitos."; return; }
                this.post("shalom-clave", { clave: this.f.clave })
                    .then(d => this.terminado(d.message))
                    .catch(e => this.fallo(e, "Shalom no cambió la clave."));
            } else if (m === "destino" || m === "contacto") {
                if (this.challenge) {
                    this.post("shalom-cambio/confirmar", { challenge: this.challenge, codigo: this.f.codigo })
                        .then(d => this.terminado(d.message))
                        .catch(e => this.fallo(e, "Shalom no aceptó el código."));
                    return;
                }
                if (!/^9\d{8}$/.test(this.f.telefono || "")) { this.error = "El celular son 9 dígitos y empieza con 9."; return; }
                this.post("shalom-cambio", {
                    tipo: m === "destino" ? "cambio_destino" : "cambiar_contacto",
                    telefono: this.f.telefono,
                    destino: this.f.destino,
                    destinatario: this.f.destinatario,
                    nombre: this.f.nombre,
                })
                    .then(d => {
                        this.challenge = d.challenge;
                        this.aviso = "Shalom envió un código por " + (d.canal === "email" ? "correo" : "SMS") + ". Escríbelo abajo.";
                    })
                    .catch(e => this.fallo(e, "Shalom no envió el código."));
            } else if (m === "bultos" || m === "quitar") {
                if (m === "quitar" && (this.f.motivo || "").trim().length < 3) { this.error = "Escribe el motivo."; return; }
                const reason = m === "bultos" ? `Cambio de bultos: ${this.sh.bultos || 1} → ${this.f.bultos}` : this.f.motivo;
                const nuevos = this.f.bultos;
                this.post("shalom-quitar", { reason })
                    .then(d => {
                        this.$emit("saved");
                        if (m === "quitar") { this.terminado(d.message); return; }
                        // Sigue en el mismo diálogo: crear la guía nueva con los bultos.
                        this.quitada = true;
                        this.bultos = nuevos;
                        this.volver();
                        this.aviso = "Guía anterior quitada. Anúlala en pro.shalom.pe y crea la nueva aquí.";
                    })
                    .catch(e => this.fallo(e, "No se pudo quitar la guía."));
            }
        },
        crear() {
            if (this.clave && !/^\d{4}$/.test(this.clave)) {
                this.error = "La clave de recojo son 4 dígitos.";
                return;
            }
            this.post("shalom-guia", {
                content: this.content,
                bultos: this.bultos,
                clave: this.clave || null,
                declaracion: this.aereo ? this.declaracion : null,
            })
                .then(d => {
                    this.hecho = d;
                    this.aviso = null;
                    this.$message.success(d.message || "Guía creada.");
                    this.$emit("saved");
                })
                .catch(e => this.fallo(e, "Shalom no creó la guía."));
        },
    },
};
</script>

<style scoped>
.shg-field { margin-bottom: 14px; }
.shg-field label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #334155; }
.shg-req { color: #dc2626; }
.shg-opt-l { font-weight: 400; color: #64748b; }
.shg-sub { display: block; font-size: 12px; color: #64748b; margin-top: 4px; }
.shg-hint { font-size: 13px; color: #475569; margin: 0 0 14px; line-height: 1.45; }
.shg-warn { font-size: 13px; background: #fff7e6; color: #8a5300; border-radius: 8px; padding: 10px 12px; margin: 0 0 12px; line-height: 1.45; }
.shg-alert { background: #fef2f2; color: #b91c1c; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.shg-okline { background: #f0fdf4; color: #166534; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.shg-ok { background: #f0fdf4; border-radius: 10px; padding: 14px; text-align: center; }
.shg-ok__t { font-weight: 700; color: #166534; }
.shg-ok__n { font-size: 20px; font-weight: 800; margin-top: 6px; letter-spacing: .5px; }
.shg-ok__s { font-size: 13px; color: #166534; margin-top: 4px; }

.shg-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; background: #f8fafc; }
.shg-card__n { font-size: 18px; font-weight: 800; letter-spacing: .3px; color: #0f172a; }
.shg-card__grid { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; margin-top: 8px; font-size: 13px; }
.shg-card__grid span { color: #64748b; }
.shg-card__grid b { color: #0f172a; font-weight: 600; }
.shg-card__print { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.shg-card__print .el-button + .el-button { margin-left: 0; }

.shg-sec { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; margin: 4px 0 8px; }
.shg-opt { display: block; width: 100%; text-align: left; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 10px 14px; margin-bottom: 8px; cursor: pointer; font: inherit; }
.shg-opt:hover:not(:disabled) { border-color: #94a3b8; background: #f8fafc; }
.shg-opt:disabled { cursor: not-allowed; opacity: .55; }
.shg-opt b { display: block; font-size: 14px; color: #0f172a; }
.shg-opt span { display: block; font-size: 12.5px; color: #64748b; margin-top: 2px; }
.shg-foot { font-size: 12.5px; color: #64748b; margin: 10px 0 4px; }
.shg-danger { border: none; background: none; color: #b91c1c; font-size: 13px; padding: 6px 0; cursor: pointer; text-decoration: underline; }
.shg-back { display: inline-block; font-size: 13px; color: #475569; cursor: pointer; margin-bottom: 8px; }
.shg-o b { display: block; font-size: 13.5px; line-height: 1.3; }
.shg-o small { color: #64748b; }
</style>
