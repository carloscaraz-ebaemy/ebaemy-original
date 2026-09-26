<template>
    <!-- Alta manual de pedido: se esta rehaciendo desde cero.
         De momento solo el documento del cliente, que trae su nombre.
         El resto del formulario anterior esta en el historial
         (git show <commit>:resources/js/views/tenant/orders/partials/manual_order.vue).
         Contrato con orders/index.vue: showDialog (.sync), orderId, created. -->
    <el-dialog
        :close-on-click-modal="false"
        :visible="showDialog"
        :title="titulo"
        top="5vh"
        width="70%"
        @close="cerrar"
    >
        <div class="mo-doc">
            <label class="mo-doc__lbl">Documento del cliente</label>

            <el-input
                v-model="documento"
                placeholder="DNI o RUC"
                maxlength="11"
                class="mo-doc__input"
                @input="alEscribir"
                @keyup.enter.native="buscar(true)"
            >
                <el-button
                    slot="append"
                    icon="el-icon-search"
                    :loading="buscando"
                    @click="buscar(true)"
                ></el-button>
            </el-input>

            <p class="mo-doc__hint">
                Con 8 dígitos (DNI) u 11 (RUC) la búsqueda sale sola.
            </p>

            <!-- El resultado es SOLO el nombre. Telefono y correo los devuelve
                 el endpoint, pero aqui no se pintan: esta pantalla identifica
                 a la persona, no lista su ficha. -->
            <div v-if="cliente" class="mo-res">
                <div class="mo-res__fila">
                    <span class="mo-res__lbl">Nombre</span>
                    <strong>{{ cliente.name || "—" }}</strong>
                </div>
                <small v-if="origen" class="mo-res__origen">{{ origen }}</small>
            </div>

            <div v-else-if="sinResultado" class="mo-res mo-res--vacio">
                {{ sinResultado }}
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
            documento: "",
            cliente: null,
            origen: "",
            sinResultado: "",
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
    },
    methods: {
        cerrar() {
            this.$emit("update:showDialog", false);
        },

        /**
         * Busca sola en cuanto el documento alcanza largo de DNI (8) o RUC (11).
         *
         * Con espera porque el operador teclea: sin ella, escribir un RUC
         * dispararia once consultas y las diez primeras se pagan para nada.
         */
        alEscribir() {
            clearTimeout(this.timerDoc);

            const doc = this.soloDigitos();

            // El documento cambio: lo que trajo la consulta anterior ya no es
            // de esta persona. Se suelta ahora, no cuando llegue la respuesta,
            // para que la pantalla no quede un segundo mostrando al anterior.
            if (doc !== this.docConsultado) this.limpiarResultado();

            if (doc.length !== 8 && doc.length !== 11) return;

            this.timerDoc = setTimeout(() => this.buscar(false), 450);
        },

        /**
         * `manual` = lo pidió el operador con el botón o con Enter, y entonces
         * sí se le responde aunque no haya nada. En la búsqueda automática se
         * calla: el cliente nuevo es un caso normal, no un error que avisar.
         */
        buscar(manual) {
            clearTimeout(this.timerDoc);

            const doc = this.soloDigitos();

            if (!doc) {
                if (manual) this.$message.warning("Ingresa el documento a buscar.");
                return;
            }

            this.limpiarResultado();

            const peticion = ++this.peticion;
            this.buscando = true;

            this.$http
                .get("/orders/search-customer", { params: { document_number: doc } })
                .then(r => {
                    if (peticion !== this.peticion) return;

                    const d = r.data || {};
                    this.docConsultado = doc;

                    if (!d.found) {
                        this.sinResultado =
                            d.message || "Sin datos para ese documento.";
                        return;
                    }

                    this.cliente = d.customer || {};
                    this.origen =
                        {
                            cartera: "Cliente de tu cartera.",
                            dni: "Datos traídos de RENIEC.",
                            ruc: "Datos traídos de SUNAT.",
                        }[d.source] || "";
                })
                .catch(() => {
                    if (peticion !== this.peticion) return;
                    this.sinResultado = "No se pudo consultar el documento.";
                })
                .then(() => {
                    if (peticion === this.peticion) this.buscando = false;
                });
        },

        soloDigitos() {
            return (this.documento || "").replace(/\D+/g, "");
        },
        limpiarResultado() {
            this.cliente = null;
            this.origen = "";
            this.sinResultado = "";
            this.docConsultado = "";
        },
    },
};
</script>

<style scoped>
.mo-doc {
    padding: 8px 0 24px;
}
.mo-doc__lbl {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
.mo-doc__input {
    max-width: 320px;
}
.mo-doc__hint {
    margin: 6px 0 0;
    font-size: 12px;
    color: #909399;
}
.mo-res {
    margin-top: 16px;
    padding: 12px 14px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    background: #fafafa;
    max-width: 480px;
}
.mo-res--vacio {
    color: #909399;
}
.mo-res__fila {
    display: flex;
    gap: 12px;
    padding: 4px 0;
}
.mo-res__lbl {
    min-width: 84px;
    color: #909399;
}
.mo-res__origen {
    display: block;
    margin-top: 6px;
    color: #909399;
}

/* Movil: el input a todo el ancho, que 320px sobran en una pantalla de 360. */
@media (max-width: 575px) {
    .mo-doc__input {
        max-width: 100%;
    }
}
</style>
