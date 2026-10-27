<template>
    <!-- «¿Cómo envías tus productos?» — la política de envío de la tienda.
         Una sola para su tienda online, ebaemy.com y los anuncios. Se guarda en
         /store-shipping (StoreShipping) y es obligatoria para publicar en el
         marketplace. La vista previa la redacta el servidor con las mismas
         frases que verá el comprador. -->
    <el-dialog
        title="¿Cómo envías tus productos?"
        :visible="visible"
        width="520px"
        top="6vh"
        custom-class="ss-dialog"
        append-to-body
        :close-on-click-modal="false"
        @close="close"
    >
        <div v-loading="loading" class="ss">
            <p v-if="reason" class="ss__reason">{{ reason }}</p>
            <p class="ss__hint">
                Se muestra en tu tienda online y en ebaemy.com, para que tus clientes sepan cuánto pagan y
                cuándo llega. Lo configuras una vez y lo cambias cuando quieras.
            </p>

            <template v-if="f">
                <!-- Lima -->
                <section class="ss__block">
                    <h4 class="ss__h">Lima y Callao</h4>
                    <el-radio-group v-model="f.lima_mode" size="small" class="ss__modes">
                        <el-radio-button label="free">Gratis</el-radio-button>
                        <el-radio-button label="fixed">Costo fijo</el-radio-button>
                        <el-radio-button label="quote">A cotizar</el-radio-button>
                    </el-radio-group>
                    <div v-if="f.lima_mode !== 'free'" class="ss__row">
                        <span>{{ f.lima_mode === 'fixed' ? 'Cobro' : 'Desde (opcional)' }}</span>
                        <el-input-number v-model="f.lima_cost" :min="0" :max="9999" :precision="2" :step="1" size="small" controls-position="right" class="ss__num"></el-input-number>
                    </div>
                    <div class="ss__row">
                        <span>Llega en</span>
                        <el-input-number v-model="f.lima_days_min" :min="0" :max="30" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>a</span>
                        <el-input-number v-model="f.lima_days_max" :min="0" :max="30" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>días hábiles</span>
                    </div>
                </section>

                <!-- Provincias -->
                <section class="ss__block">
                    <h4 class="ss__h">Provincias</h4>
                    <el-radio-group v-model="f.province_mode" size="small" class="ss__modes">
                        <el-radio-button label="destination">Pago en destino</el-radio-button>
                        <el-radio-button label="fixed">Costo fijo</el-radio-button>
                        <el-radio-button label="free">Gratis</el-radio-button>
                        <el-radio-button label="quote">A cotizar</el-radio-button>
                    </el-radio-group>
                    <p v-if="f.province_mode === 'destination'" class="ss__small">
                        Tú lo llevas a la agencia y el cliente paga el flete al recogerlo.
                    </p>
                    <div v-if="f.province_mode === 'destination'" class="ss__row">
                        <span>Llevarlo a la agencia</span>
                        <el-select v-model="f.agency_fee_mode" size="small" class="ss__sel">
                            <el-option label="Es gratis" value="free"></el-option>
                            <el-option label="Cobro un monto" value="amount"></el-option>
                            <el-option label="No mencionarlo" value="hidden"></el-option>
                        </el-select>
                        <el-input-number v-if="f.agency_fee_mode === 'amount'" v-model="f.agency_fee" :min="0" :max="999" :precision="2" size="small" controls-position="right" class="ss__num"></el-input-number>
                    </div>
                    <div v-if="f.province_mode === 'fixed' || f.province_mode === 'quote'" class="ss__row">
                        <span>{{ f.province_mode === 'fixed' ? 'Cobro' : 'Desde (opcional)' }}</span>
                        <el-input-number v-model="f.province_cost" :min="0" :max="9999" :precision="2" :step="1" size="small" controls-position="right" class="ss__num"></el-input-number>
                    </div>
                    <div class="ss__row">
                        <span>Sale a la agencia en</span>
                        <el-input-number v-model="f.province_handling_days" :min="0" :max="15" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>días hábiles</span>
                    </div>
                    <div class="ss__row">
                        <span>Viaje aprox.</span>
                        <el-input-number v-model="f.province_transit_min" :min="0" :max="30" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>a</span>
                        <el-input-number v-model="f.province_transit_max" :min="0" :max="30" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                        <span>días</span>
                    </div>
                    <div class="ss__row">
                        <span>Agencias</span>
                        <el-select v-model="f.province_agencies" multiple filterable allow-create default-first-option
                                   :multiple-limit="12"
                                   size="small" placeholder="Shalom, Olva…" class="ss__sel ss__sel--wide">
                            <el-option v-for="a in catalog" :key="a" :label="a" :value="a"></el-option>
                        </el-select>
                    </div>
                </section>

                <!-- Recojo -->
                <section class="ss__block">
                    <div class="ss__row ss__row--between">
                        <h4 class="ss__h" style="margin:0">Recojo en tienda</h4>
                        <el-switch v-model="f.pickup_enabled"></el-switch>
                    </div>
                    <template v-if="f.pickup_enabled">
                        <div class="ss__row">
                            <span>Distrito</span>
                            <el-input v-model="f.pickup_district" size="small" maxlength="120" placeholder="Ej. Surco" class="ss__sel"></el-input>
                        </div>
                        <div class="ss__row">
                            <span>Listo en</span>
                            <el-input-number v-model="f.pickup_ready_hours" :min="1" :max="720" size="small" controls-position="right" class="ss__num ss__num--s"></el-input-number>
                            <span>horas</span>
                        </div>
                    </template>
                </section>

                <!-- Extras -->
                <section class="ss__block">
                    <h4 class="ss__h">Opcional</h4>
                    <div class="ss__row">
                        <span>Envío gratis desde</span>
                        <el-input-number v-model="f.free_from" :min="0" :max="99999" :precision="2" size="small" controls-position="right" class="ss__num"></el-input-number>
                    </div>
                    <div class="ss__row">
                        <span>Hora de corte</span>
                        <el-time-select v-model="f.cutoff_time" size="small" class="ss__sel"
                                        :picker-options="{ start: '08:00', step: '00:30', end: '22:00' }"
                                        placeholder="Sin hora de corte"></el-time-select>
                    </div>
                    <p class="ss__small">Pedidos antes de esa hora salen el mismo día hábil.</p>
                </section>

                <!-- Vista previa -->
                <section class="ss__preview" aria-live="polite">
                    <div class="ss__preview-t">Así lo verá tu cliente</div>
                    <div v-for="l in preview.lines" :key="l.key" class="ss__pl">
                        <span class="ss__pl-t">{{ l.title }}</span>
                        <span class="ss__pl-p" :class="{ 'ss__pl-p--free': l.free }">{{ l.price }}</span>
                        <span v-if="l.detail" class="ss__pl-d">{{ l.detail }}</span>
                    </div>
                    <div v-for="n in preview.notes" :key="n" class="ss__pn">{{ n }}</div>
                </section>
            </template>
        </div>

        <span slot="footer">
            <el-button size="small" @click="close">Cancelar</el-button>
            <el-button type="primary" size="small" :loading="saving" @click="save">Guardar envíos</el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    name: 'StoreShippingSettings',
    props: {
        visible: { type: Boolean, default: false },
        // Por qué se abre (p. ej. al intentar publicar en el marketplace).
        reason: { type: String, default: '' },
    },
    data() {
        return { loading: false, saving: false, f: null, catalog: [], preview: { lines: [], notes: [] }, timer: null };
    },
    watch: {
        visible(v) { if (v) this.load(); },
        f: { deep: true, handler() { this.schedulePreview(); } },
    },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/store-shipping')
                .then(r => {
                    const d = r.data || {};
                    if (!d.available) {
                        this.$message.warning('Esta función aún no está disponible para tu tienda.');
                        return this.close();
                    }
                    this.catalog = d.agencies_catalog || [];
                    this.f = {
                        lima_mode: d.lima_mode, lima_cost: d.lima_cost, lima_days_min: d.lima_days_min, lima_days_max: d.lima_days_max,
                        province_mode: d.province_mode, province_cost: d.province_cost,
                        province_handling_days: d.province_handling_days,
                        province_transit_min: d.province_transit_min, province_transit_max: d.province_transit_max,
                        province_agencies: d.province_agencies || [],
                        agency_fee_mode: d.agency_fee_mode || 'free', agency_fee: d.agency_fee,
                        pickup_enabled: !!d.pickup_enabled, pickup_district: d.pickup_district, pickup_ready_hours: d.pickup_ready_hours,
                        free_from: d.free_from, cutoff_time: d.cutoff_time,
                    };
                })
                .catch(() => { this.$message.error('No se pudo cargar la configuración de envíos'); this.close(); })
                .then(() => { this.loading = false; });
        },
        schedulePreview() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                if (!this.f) return;
                this.$http.post('/store-shipping/preview', this.f)
                    .then(r => { this.preview = r.data || { lines: [], notes: [] }; })
                    .catch(() => {});
            }, 250);
        },
        save() {
            // Las casillas numéricas confirman su valor al perder el foco.
            const el = document.activeElement;
            if (el && typeof el.blur === 'function') el.blur();
            this.$nextTick(() => {
                this.saving = true;
                this.$http.post('/store-shipping', this.f)
                    .then(r => {
                        this.$message.success((r.data && r.data.message) || 'Envíos guardados');
                        this.$emit('saved');
                        this.close();
                    })
                    .catch(e => {
                        this.$message.error(this.motivoDelError(e));
                    })
                    .then(() => { this.saving = false; });
            });
        },
        /**
         * El motivo que da el servidor, venga como venga.
         *
         * En el panel, `Handler::convertValidationExceptionToResponse` manda
         * los errores SIN el envoltorio de Laravel: `{"campo": ["msg"]}`, no
         * `{"errors": {...}}`. Buscando solo `errors` y `message`, cualquier
         * 422 acababa en «No se pudo guardar» y el vendedor no sabia que
         * corregir. Se aceptan las dos formas.
         */
        motivoDelError(e) {
            const d = e && e.response && e.response.data;
            if (d && typeof d === 'object') {
                const mapa = (d.errors && typeof d.errors === 'object') ? d.errors : d;
                for (const k of Object.keys(mapa)) {
                    const v = mapa[k];
                    if (Array.isArray(v) && typeof v[0] === 'string') return v[0];
                }
                if (typeof d.message === 'string' && d.message) return d.message;
            }
            return 'No se pudo guardar. Revisa los datos e inténtalo otra vez.';
        },
        close() {
            this.$emit('update:visible', false);
        },
    },
};
</script>

<style scoped>
.ss { font-size: 13px; word-break: normal; overflow-wrap: break-word; }
.ss__reason {
    background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; border-radius: 8px;
    padding: 8px 10px; margin: 0 0 10px; font-weight: 600;
}
.ss__hint { color: #64748b; margin: 0 0 12px; line-height: 1.4; }
.ss__block { border-top: 1px solid #f1f5f9; padding: 10px 0; }
.ss__h { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
.ss__modes { display: flex; flex-wrap: wrap; margin-bottom: 6px; }
.ss__row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; color: #334155; }
.ss__row--between { justify-content: space-between; margin-top: 0; }
.ss__small { color: #64748b; font-size: 12px; margin: 4px 0 0; }
.ss__preview { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 10px 12px; margin-top: 6px; }
.ss__preview-t { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 6px; }
.ss__pl { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; padding: 4px 0; }
.ss__pl-t { font-weight: 600; color: #1e293b; }
.ss__pl-p { font-weight: 700; color: #0f172a; }
.ss__pl-p--free { color: #16a34a; }
.ss__pl-d { flex-basis: 100%; color: #64748b; font-size: 12px; }
.ss__pn { color: #0c6b65; font-size: 12px; margin-top: 4px; }
</style>

<style>
/* erp-redesign.scss fuerza max-width 95vw y width 100% en los inputs de todo
   diálogo: sin especificidad extra se abría a pantalla completa. */
.el-dialog.ss-dialog { width: 520px !important; max-width: 94vw !important; }
.el-dialog.ss-dialog .el-dialog__body { padding: 8px 18px 4px !important; max-height: 72vh !important; overflow-y: auto !important; }
.el-dialog.ss-dialog .ss__num { width: 110px !important; margin: 0 !important; }
.el-dialog.ss-dialog .ss__num--s { width: 78px !important; }
.el-dialog.ss-dialog .ss__num .el-input,
.el-dialog.ss-dialog .ss__sel .el-input { margin: 0 !important; }
.el-dialog.ss-dialog .ss__sel { width: 170px !important; margin: 0 !important; }
.el-dialog.ss-dialog .ss__sel--wide { width: 100% !important; flex: 1 1 220px; }
.el-dialog.ss-dialog .el-dialog__footer .el-button { width: auto !important; margin: 0 0 0 6px !important; }
</style>
