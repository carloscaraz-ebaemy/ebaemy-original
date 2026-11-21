<template>
    <!-- «Video del producto» (2026-11-19): la tienda pega el enlace de un
         video ya publicado en TikTok, YouTube o Instagram (Reels). Queda
         pendiente hasta que ebaemy lo aprueba; aprobado, sale en el
         marketplace. Solo con el extra de videos activo. -->
    <details class="ie-collapse pvs" :open="open" @toggle="open = $event.target.open">
        <summary class="ie-collapse__summary">
            <span class="ie-collapse__chev">▼</span>
            <strong>Video del producto</strong>
            <span class="ie-collapse__hint">TikTok, YouTube o Instagram (opcional)</span>
            <span v-if="video" class="ie-collapse__badge" :class="'pvs-badge--' + video.status">{{ statusText(video.status) }}</span>
        </summary>

        <div class="pvs__body">
            <div v-if="loading" class="pvs__muted">Cargando…</div>

            <div v-else-if="!itemId" class="pvs__notice">
                <strong>Primero guarda el producto.</strong>
                <span>Después podrás pegar aquí el enlace de su video.</span>
            </div>

            <!-- Sin el extra: aviso corto, sin formulario. -->
            <div v-else-if="!enabled" class="pvs__notice">
                <strong>🎬 Video del producto es un extra.</strong>
                <span>Muestra en el marketplace un video tuyo de TikTok, YouTube o Instagram junto a este producto.
                    Pídelo al equipo de ebaemy para activarlo en tu tienda.</span>
            </div>

            <template v-else>
                <!-- Video actual -->
                <div v-if="video && !editing" class="pvs__current">
                    <div class="pvs__stage" :class="stageClass(video)">
                        <iframe v-if="playing === 'current' && safeSrc(video.embed_src)"
                                :src="playSrc(video.embed_src)" title="Video del producto"
                                allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                                allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        <button v-else type="button" class="pvs__lite" @click="playing = 'current'"
                                aria-label="Reproducir el video">
                            <img v-if="video.thumbnail" :src="video.thumbnail" alt="">
                            <span class="pvs__play" aria-hidden="true">▶</span>
                        </button>
                    </div>
                    <div class="pvs__info">
                        <span class="pvs__pill" :class="'pvs__pill--' + video.status">{{ statusText(video.status) }}</span>
                        <span v-if="video.is_featured && video.status === 'approved'" class="pvs__pill pvs__pill--approved">★ Destacado en ebaemy</span>
                        <p class="pvs__help">{{ statusHelp(video.status) }}</p>
                        <p v-if="video.status === 'rejected' && video.rejection_reason" class="pvs__reason">
                            Motivo: {{ video.rejection_reason }}
                        </p>
                        <a v-if="video.source_url" :href="video.source_url" target="_blank" rel="noopener noreferrer"
                           class="pvs__link">Abrir en {{ video.provider_label }} ↗</a>
                        <div class="pvs__actions">
                            <el-button size="small" @click="startEdit">Cambiar video</el-button>
                            <el-button size="small" type="danger" plain :loading="removing" @click="remove">Quitar</el-button>
                        </div>
                    </div>
                </div>

                <!-- Pegar enlace -->
                <div v-else class="pvs__form">
                    <label class="pvs__label" for="pvsUrl">Enlace del video</label>
                    <div class="pvs__row">
                        <el-input id="pvsUrl" v-model="url" class="pvs__input" clearable
                                  placeholder="Pega aquí el enlace de TikTok, YouTube o Instagram"
                                  @keyup.enter.native="doPreview" @input="onUrlInput"></el-input>
                        <el-button :loading="previewing" :disabled="!url.trim()" @click="doPreview">Vista previa</el-button>
                    </div>
                    <small class="pvs__hint">
                        En el video toca «Compartir» › «Copiar enlace» y pégalo aquí. Sirven los videos de TikTok,
                        los de YouTube (también Shorts) y los Reels de Instagram. Un video por producto.
                    </small>
                    <p v-if="error" class="pvs__error" role="alert">{{ error }}</p>

                    <div v-if="preview" class="pvs__current pvs__current--preview">
                        <div class="pvs__stage" :class="stageClass(preview)">
                            <iframe v-if="playing === 'preview' && safeSrc(preview.embed_src)"
                                    :src="playSrc(preview.embed_src)" title="Vista previa del video"
                                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                                    allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            <button v-else type="button" class="pvs__lite" @click="playing = 'preview'"
                                    aria-label="Reproducir la vista previa">
                                <img v-if="preview.thumbnail" :src="preview.thumbnail" alt="">
                                <span class="pvs__play" aria-hidden="true">▶</span>
                            </button>
                        </div>
                        <div class="pvs__info">
                            <span class="pvs__pill">Video de {{ preview.provider_label }}{{ preview.orientation === 'vertical' ? ' · vertical' : '' }}</span>
                            <p v-if="preview.title" class="pvs__help pvs__caption">«{{ preview.title }}»</p>
                            <p class="pvs__help">Revisa que sea el video de este producto. Al enviarlo queda
                                <strong>pendiente de aprobación</strong>; cuando ebaemy lo apruebe saldrá en el marketplace.</p>
                            <div class="pvs__actions">
                                <el-button type="primary" size="small" :loading="saving" @click="save">Enviar a aprobación</el-button>
                                <el-button v-if="video" size="small" @click="cancelEdit">Cancelar</el-button>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="video" class="pvs__actions">
                        <el-button size="small" @click="cancelEdit">Cancelar</el-button>
                    </div>
                </div>
            </template>
        </div>
    </details>
</template>

<script>
// Solo estos reproductores llegan a un iframe (el servidor ya arma el src
// desde el id validado; esto es una segunda barrera).
const EMBEDS = [
    /^https:\/\/www\.youtube-nocookie\.com\/embed\/[A-Za-z0-9_-]{11}$/,
    /^https:\/\/www\.tiktok\.com\/player\/v1\/\d{8,25}$/,
    /^https:\/\/www\.instagram\.com\/reel\/[A-Za-z0-9_-]{5,40}\/embed$/,
];

export default {
    name: 'ProductVideoSection',
    props: {
        itemId: { type: [Number, String], default: null },
        // En la pestaña «Video» de Productos sale ya abierta.
        startOpen: { type: Boolean, default: false },
    },
    data() {
        return {
            open: this.startOpen,
            loading: false,
            enabled: false,
            video: null,
            editing: false,
            url: '',
            preview: null,
            previewing: false,
            saving: false,
            removing: false,
            playing: null,
            error: '',
        };
    },
    watch: {
        itemId: { immediate: true, handler() { this.load(); } },
    },
    methods: {
        load() {
            this.reset();
            if (!this.itemId) { this.enabled = false; this.video = null; return; }
            this.loading = true;
            this.$http.get(`/items/${this.itemId}/promo-video`)
                .then(({ data }) => {
                    this.enabled = !!(data && data.enabled);
                    this.video = (data && data.video) || null;
                    // Abierta si ya hay video o si lo rechazaron (que lo vea).
                    if (this.video) this.open = true;
                })
                .catch(() => { this.enabled = false; })
                .finally(() => { this.loading = false; });
        },
        reset() {
            this.editing = false;
            this.url = '';
            this.preview = null;
            this.playing = null;
            this.error = '';
        },
        startEdit() { this.reset(); this.editing = true; },
        cancelEdit() { this.reset(); },
        onUrlInput() { this.preview = null; this.error = ''; if (this.playing === 'preview') this.playing = null; },
        doPreview() {
            if (!this.url.trim() || this.previewing) return;
            this.previewing = true;
            this.error = '';
            this.preview = null;
            this.$http.post(`/items/${this.itemId}/promo-video/preview`, { url: this.url.trim() })
                .then(({ data }) => {
                    if (data && data.success) this.preview = data.preview;
                    else this.error = (data && data.message) || 'No pudimos revisar ese enlace.';
                })
                .catch((e) => { this.error = this.reason(e, 'No pudimos revisar ese enlace.'); })
                .finally(() => { this.previewing = false; });
        },
        save() {
            if (this.saving) return;
            this.saving = true;
            this.error = '';
            this.$http.post(`/items/${this.itemId}/promo-video`, { url: this.url.trim() })
                .then(({ data }) => {
                    if (data && data.success) {
                        this.video = data.video;
                        this.reset();
                        this.$message.success(data.message || 'Video enviado a aprobación.');
                    } else {
                        this.error = (data && data.message) || 'No se pudo guardar el video.';
                    }
                })
                .catch((e) => { this.error = this.reason(e, 'No se pudo guardar el video.'); })
                .finally(() => { this.saving = false; });
        },
        remove() {
            this.$confirm('El video deja de verse en el marketplace. ¿Quitarlo?', 'Quitar video', {
                confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', type: 'warning',
            }).then(() => {
                this.removing = true;
                return this.$http.delete(`/items/${this.itemId}/promo-video`)
                    .then(() => {
                        this.video = null;
                        this.reset();
                        this.$message.success('Video quitado.');
                    })
                    .catch((e) => { this.$message.error(this.reason(e, 'No se pudo quitar el video.')); })
                    .finally(() => { this.removing = false; });
            }).catch(() => {});
        },
        // 422 del panel: {message} propio o {campo: [msg]} sin envoltorio
        // (ver feedback_422_sin_envoltorio).
        reason(e, fallback) {
            const d = e && e.response && e.response.data;
            if (!d || typeof d !== 'object') return fallback;
            if (typeof d.message === 'string' && d.message && !/given data was invalid/i.test(d.message)) return d.message;
            const bag = d.errors && typeof d.errors === 'object' ? d.errors : d;
            for (const k in bag) {
                if (Array.isArray(bag[k]) && bag[k].length) return String(bag[k][0]);
            }
            if (e.response.status === 429) return 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.';
            return fallback;
        },
        safeSrc(src) { return EMBEDS.some((re) => re.test(String(src || ''))); },
        playSrc(src) {
            if (src.indexOf('youtube-nocookie.com') !== -1) return src + '?autoplay=1&playsinline=1&rel=0';
            // TikTok: sin la fila de «más videos» al terminar.
            if (src.indexOf('tiktok.com/player') !== -1) return src + '?rel=0&description=0&music_info=0&autoplay=1';
            return src;
        },
        stageClass(v) {
            return [v.orientation === 'horizontal' ? 'is-horizontal' : 'is-vertical', 'pvs__stage--' + v.provider];
        },
        statusText(s) {
            return {
                pending_review: 'Pendiente de aprobación',
                approved: 'Aprobado',
                rejected: 'Rechazado',
                hidden: 'Oculto',
            }[s] || s;
        },
        statusHelp(s) {
            return {
                pending_review: 'Lo estamos revisando. Cuando lo aprobemos saldrá en el marketplace, debajo de las fotos de este producto.',
                approved: 'Se ve en el marketplace, debajo de las fotos de este producto.',
                rejected: 'No se publicó. Puedes pegar otro enlace con «Cambiar video» y lo volvemos a revisar.',
                hidden: 'ebaemy lo ocultó del marketplace. Escríbenos si crees que es un error.',
            }[s] || '';
        },
    },
};
</script>

<style>
.pvs__body { padding: 12px 14px; }
.pvs__muted { color: var(--ie-text-muted, #64748b); font-size: 13px; }
.pvs__notice {
    display: flex; flex-direction: column; gap: 4px; padding: 12px 14px;
    background: var(--ie-surface-sunken, #f8fafc); border: 1px dashed var(--ie-border-strong, #cbd5e1);
    border-radius: var(--ie-radius, 10px); font-size: 13px; color: var(--ie-text-muted, #475569);
}
.pvs__notice strong { color: var(--ie-text, #0f172a); }
.pvs__current { display: flex; gap: 14px; align-items: flex-start; }
.pvs__current--preview { margin-top: 12px; }
.pvs__stage { position: relative; flex: 0 0 auto; background: #0f172a; border-radius: 10px; overflow: hidden; }
.pvs__stage.is-vertical { width: 150px; aspect-ratio: 9 / 16; }
.pvs__stage.is-vertical.pvs__stage--tiktok, .pvs__stage.is-vertical.pvs__stage--instagram { aspect-ratio: 9 / 17; }
.pvs__stage.is-horizontal { width: 240px; aspect-ratio: 16 / 9; }
.pvs__stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.pvs__lite {
    position: absolute; inset: 0; width: 100%; height: 100%; padding: 0; border: 0;
    background: #0f172a; cursor: pointer; display: grid; place-items: center;
}
.pvs__lite img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.pvs__play {
    position: relative; display: grid; place-items: center; width: 46px; height: 46px; border-radius: 999px;
    background: rgba(15, 23, 42, .75); color: #fff; font-size: 17px; padding-left: 3px;
}
.pvs__info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
.pvs__pill {
    display: inline-block; font-size: 12px; font-weight: 700; border-radius: 999px; padding: 3px 10px;
    background: #e2e8f0; color: #334155;
}
.pvs__pill--pending_review { background: #fef3c7; color: #92400e; }
.pvs__pill--approved { background: #dcfce7; color: #166534; }
.pvs__pill--rejected, .pvs__pill--hidden { background: #fee2e2; color: #991b1b; }
.pvs__help { margin: 0; font-size: 13px; color: var(--ie-text-muted, #475569); line-height: 1.4; }
.pvs__caption { font-style: italic; word-break: break-word; }
.pvs__reason { margin: 0; font-size: 13px; color: #991b1b; font-weight: 600; word-break: break-word; }
.pvs__link { font-size: 13px; font-weight: 600; }
.pvs__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.pvs__actions .el-button + .el-button { margin-left: 0; }
.pvs__label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--ie-text, #0f172a); }
.pvs__row { display: flex; gap: 8px; }
.pvs__input { flex: 1 1 auto; min-width: 0; }
.pvs__hint { display: block; margin-top: 6px; font-size: 12px; color: var(--ie-text-muted, #64748b); line-height: 1.4; }
.pvs__error { margin: 8px 0 0; font-size: 13px; color: #b91c1c; font-weight: 600; }
.pvs-badge--pending_review { background: #fef3c7 !important; color: #92400e !important; }
.pvs-badge--rejected, .pvs-badge--hidden { background: #fee2e2 !important; color: #991b1b !important; }
@media (max-width: 600px) {
    .pvs__current { flex-direction: column; }
    .pvs__stage.is-vertical { width: min(220px, 100%); align-self: center; }
    .pvs__stage.is-horizontal { width: 100%; }
    .pvs__row { flex-direction: column; }
    .pvs__row .el-button { width: 100%; min-height: 44px; margin-left: 0; }
    .pvs__actions .el-button { min-height: 44px; }
    /* iOS hace zoom al enfocar un input de menos de 16 px. */
    .pvs__input .el-input__inner { font-size: 16px; height: 44px; }
}
</style>
