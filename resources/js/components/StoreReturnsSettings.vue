<template>
    <!-- «Cambios y devoluciones»: datos → texto de la página pública de la tienda
         (/store-returns, StoreReturnsPolicy). Sin plantillas con huecos: el texto
         se genera, así nunca se publica un «[7]» sin completar. -->
    <el-dialog
        title="Cambios y devoluciones"
        :visible="visible"
        width="520px"
        top="6vh"
        custom-class="ss-dialog"
        append-to-body
        :close-on-click-modal="false"
        @close="close"
    >
        <div v-loading="loading" class="ss">
            <p class="ss__hint">
                Indica tus condiciones y armamos el texto de tu página «Cambios y devoluciones».
                Se muestra en tu tienda y en ebaemy.com, y es lo que piden Google y la ley.
            </p>
            <p v-if="f && f.has_free_text" class="ss__reason">
                Ya tienes un texto escrito a mano. Al guardar, se reemplaza por el que se genera aquí.
            </p>

            <template v-if="f">
                <section class="ss__block">
                    <h4 class="ss__h">Producto con falla o dañado</h4>
                    <div class="ss__row">
                        <span>El cliente puede avisar hasta</span>
                        <el-input-number v-model="f.defect_days" :min="1" :max="365" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>días después de recibirlo</span>
                    </div>
                    <p class="ss__small">Lo cambias por uno igual o devuelves el dinero, y el envío lo pagas tú.</p>
                </section>

                <section class="ss__block">
                    <div class="ss__row ss__row--between">
                        <h4 class="ss__h" style="margin:0">Cambio de talla, color o modelo</h4>
                        <el-switch v-model="f.change_enabled"></el-switch>
                    </div>
                    <template v-if="f.change_enabled">
                        <div class="ss__row">
                            <span>Hasta</span>
                            <el-input-number v-model="f.change_days" :min="1" :max="365" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                            <span>días de recibido</span>
                        </div>
                        <div class="ss__row">
                            <span>El envío del cambio lo paga</span>
                            <el-radio-group v-model="f.change_shipping" size="small">
                                <el-radio-button label="customer">El cliente</el-radio-button>
                                <el-radio-button label="store">La tienda</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                </section>

                <section class="ss__block">
                    <h4 class="ss__h">Reembolso y exclusiones</h4>
                    <div class="ss__row">
                        <span>Devuelves el dinero en</span>
                        <el-input-number v-model="f.refund_days" :min="1" :max="60" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>días hábiles</span>
                    </div>
                    <div class="ss__row">
                        <span>No aceptan cambio (opcional)</span>
                        <el-input v-model="f.excluded" size="small" maxlength="300"
                                  placeholder="Ej. ropa interior, cosméticos abiertos, productos en liquidación" class="ss__sel ss__sel--wide"></el-input>
                    </div>
                </section>

                <section class="ss__block">
                    <h4 class="ss__h">Dónde te escriben para pedirlo</h4>
                    <div class="ss__row">
                        <span>WhatsApp</span>
                        <el-input v-model="f.contact_whatsapp" size="small" maxlength="30" placeholder="Ej. 987 654 321" class="ss__sel"></el-input>
                    </div>
                    <div class="ss__row">
                        <span>Correo</span>
                        <el-input v-model="f.contact_email" size="small" maxlength="120" placeholder="ventas@tutienda.com" class="ss__sel ss__sel--wide"></el-input>
                    </div>
                </section>

                <section class="ss__preview" aria-live="polite">
                    <div class="ss__preview-t">Así se publicará</div>
                    <div class="sr__html" v-html="previewHtml"></div>
                </section>
            </template>
        </div>

        <span slot="footer">
            <el-button size="small" @click="close">Cancelar</el-button>
            <el-button type="primary" size="small" :loading="saving" @click="save">Publicar política</el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    name: 'StoreReturnsSettings',
    props: { visible: { type: Boolean, default: false } },
    data() {
        return { loading: false, saving: false, f: null, previewHtml: '', timer: null };
    },
    watch: {
        visible(v) { if (v) this.load(); },
        f: { deep: true, handler() { this.schedulePreview(); } },
    },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/store-returns')
                .then(r => { this.f = Object.assign({}, r.data); })
                .catch(() => { this.$message.error('No se pudo cargar la política de cambios'); this.close(); })
                .then(() => { this.loading = false; });
        },
        payload() {
            const f = this.f;
            return {
                defect_days: f.defect_days, change_enabled: !!f.change_enabled, change_days: f.change_days,
                change_shipping: f.change_shipping, refund_days: f.refund_days, excluded: f.excluded,
                contact_whatsapp: f.contact_whatsapp, contact_email: f.contact_email || null,
            };
        },
        schedulePreview() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                if (!this.f) return;
                this.$http.post('/store-returns/preview', this.payload())
                    .then(r => { this.previewHtml = (r.data && r.data.html) || ''; })
                    .catch(() => {});
            }, 300);
        },
        save() {
            const el = document.activeElement;
            if (el && typeof el.blur === 'function') el.blur();
            this.$nextTick(() => {
                this.saving = true;
                this.$http.post('/store-returns', this.payload())
                    .then(r => {
                        this.$message.success((r.data && r.data.message) || 'Política publicada');
                        this.$emit('saved');
                        this.close();
                    })
                    .catch(e => {
                        const d = e.response && e.response.data;
                        this.$message.error((d && d.errors && Object.values(d.errors)[0][0]) || (d && d.message) || 'No se pudo guardar');
                    })
                    .then(() => { this.saving = false; });
            });
        },
        close() { this.$emit('update:visible', false); },
    },
};
</script>

<style scoped>
.ss { font-size: 13px; word-break: normal; overflow-wrap: break-word; }
.ss__reason { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 8px; padding: 8px 10px; margin: 0 0 10px; }
.ss__hint { color: #64748b; margin: 0 0 12px; line-height: 1.4; }
.ss__block { border-top: 1px solid #f1f5f9; padding: 10px 0; }
.ss__h { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
.ss__row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; color: #334155; }
.ss__row--between { justify-content: space-between; margin-top: 0; }
.ss__small { color: #64748b; font-size: 12px; margin: 4px 0 0; }
.ss__preview { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 10px 12px; margin-top: 6px; }
.ss__preview-t { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 6px; }
.sr__html { font-size: 12.5px; color: #334155; line-height: 1.45; }
.sr__html >>> h3 { font-size: 13px !important; line-height: 1.3 !important; font-weight: 700; margin: 10px 0 4px !important; color: #1e293b; }
.sr__html >>> ul { padding-left: 18px; margin: 0 0 6px; }
.sr__html >>> p { margin: 0 0 6px; }
</style>
