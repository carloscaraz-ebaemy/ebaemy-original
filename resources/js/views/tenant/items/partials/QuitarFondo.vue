<template>
    <div v-if="visible" class="qf">
        <el-button size="mini"
                   :type="fondoMalo ? 'warning' : 'default'"
                   :loading="procesando"
                   class="qf__btn"
                   @click="quitar">
            {{ procesando ? 'Quitando el fondo…' : 'Quitar el fondo' }}
        </el-button>
        <div v-if="fondoMalo && !procesando" class="qf__hint">
            El fondo no es blanco
        </div>

        <el-dialog :visible.sync="dialogo"
                   title="Quitar el fondo"
                   width="92%"
                   custom-class="qf-dialog"
                   append-to-body>
            <div class="qf__comparar">
                <figure>
                    <img :src="previewUrl" alt="Foto original">
                    <figcaption>Original</figcaption>
                </figure>
                <figure>
                    <img :src="resultado && resultado.temp_image" alt="Foto sin fondo">
                    <figcaption>Sin fondo</figcaption>
                </figure>
            </div>
            <p v-if="resultado && resultado.remaining !== undefined" class="qf__cupo">
                Le quedan {{ resultado.remaining }} fotos este mes a tu tienda.
            </p>
            <span slot="footer">
                <el-button @click="dialogo = false">Quedarme con la original</el-button>
                <el-button type="primary" @click="aplicar">Usar sin fondo</el-button>
            </span>
        </el-dialog>
    </div>
</template>

<script>
// El estado se pide una vez por pagina: el formulario se abre y cierra muchas
// veces y la respuesta no cambia salvo el cupo, que se refresca al usarlo.
let estadoCompartido = null

/**
 * Boton «Quitar el fondo» de la imagen principal, con el antes y el despues.
 *
 * No toca el producto: si el vendedor acepta, emite `aplicar` con un
 * `temp_path` nuevo y el formulario lo guarda como cualquier foto subida.
 * Si el servicio no esta configurado (sin clave), no se pinta nada.
 */
export default {
    name: 'QuitarFondo',
    props: {
        tempPath:   { type: String, default: null },
        image:      { type: String, default: null },
        previewUrl: { type: String, default: null },
        fondoMalo:  { type: Boolean, default: false },
        subiendo:   { type: Boolean, default: false },
    },
    data() {
        return {
            habilitado: false,
            procesando: false,
            dialogo: false,
            resultado: null,
        }
    },
    computed: {
        tieneImagen() {
            return !!this.tempPath
                || (!!this.image && this.image !== 'imagen-no-disponible.jpg')
        },
        visible() {
            return this.habilitado && this.tieneImagen && !this.subiendo
        },
    },
    async created() {
        try {
            if (!estadoCompartido) {
                estadoCompartido = this.$http.get('/items/remove-background/status')
                    .then(({ data }) => data)
            }
            const estado = await estadoCompartido
            this.habilitado = !!(estado && estado.enabled)
        } catch (e) {
            estadoCompartido = null
            this.habilitado = false
        }
    },
    methods: {
        async quitar() {
            if (this.procesando) return
            this.procesando = true
            try {
                const { data } = await this.$http.post('/items/remove-background', {
                    temp_path: this.tempPath || null,
                    image: this.tempPath ? null : this.image,
                })

                if (!data || !data.success) {
                    this.$message.error((data && data.message) || 'No se pudo quitar el fondo.')
                    return
                }

                this.resultado = data.data
                this.dialogo = true
            } catch (e) {
                this.$message.error('No se pudo quitar el fondo. Inténtalo en un momento.')
            } finally {
                this.procesando = false
            }
        },
        aplicar() {
            this.dialogo = false
            this.$emit('aplicar', this.resultado)
            this.resultado = null
        },
    },
}
</script>

<style scoped>
.qf { margin-top: 8px; text-align: center; }
.qf__btn { width: 100%; min-height: 36px; }
.qf__hint { margin-top: 4px; font-size: 12px; color: #b26a00; }
.qf__comparar {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.qf__comparar figure { margin: 0; min-width: 0; text-align: center; }
.qf__comparar img {
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: contain;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
}
.qf__comparar figcaption { margin-top: 6px; font-size: 13px; color: #606266; }
.qf__cupo { margin: 12px 0 0; font-size: 12px; color: #909399; text-align: center; }
@media (max-width: 480px) {
    .qf__comparar { grid-template-columns: 1fr; }
}
</style>

<style>
/* append-to-body: el dialogo vive fuera del componente, sin scoped. */
.qf-dialog { max-width: 640px; }
</style>
