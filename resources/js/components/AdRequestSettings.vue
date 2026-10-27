<template>
    <!-- «Publicidad»: la tienda pide a ebaemy una campaña en Facebook/Instagram
         (/ad-requests, Tenant\AdRequestController). ebaemy la gestiona en
         /admin/marketplace/publicidad y aquí se ve el estado y los resultados. -->
    <el-dialog
        title="Publicidad en Facebook e Instagram"
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
                ebaemy anuncia tus productos en Facebook e Instagram. Quien hace clic llega a tu tienda en
                ebaemy.com y los pedidos te llegan al panel como siempre.
            </p>

            <template v-if="data">
                <section v-if="data.records.length" class="ss__block">
                    <h4 class="ss__h">Tus solicitudes</h4>
                    <div v-for="r in data.records" :key="r.id" class="ar__rec">
                        <div class="ar__rec-top">
                            <span>{{ r.created_at }} · S/ {{ money(r.budget) }} · {{ r.days }} días</span>
                            <span class="ar__st" :class="'ar__st--' + r.status">{{ r.status_label }}</span>
                        </div>
                        <div class="ss__small">{{ r.scope }}<template v-if="r.detail"> — {{ r.detail }}</template></div>
                        <div v-if="r.starts_at" class="ss__small">
                            Empezó el {{ r.starts_at }}<template v-if="r.ends_at"> · terminó el {{ r.ends_at }}</template>
                        </div>
                        <div v-if="r.results" class="ar__res">
                            <b>{{ r.results.orders }}</b> {{ r.results.orders === 1 ? 'pedido' : 'pedidos' }}
                            (S/ {{ money(r.results.revenue) }}) ·
                            <b>{{ r.results.leads }}</b> {{ r.results.leads === 1 ? 'consulta' : 'consultas' }}
                            <template v-if="r.spent"> · invertido S/ {{ money(r.spent) }}</template>
                        </div>
                        <div v-if="r.status === 'solicitada'" class="ss__small">Te escribiremos por WhatsApp para confirmar el presupuesto y el pago.</div>
                    </div>
                </section>

                <p v-if="!data.published" class="ss__reason">
                    Aún no tienes productos publicados en ebaemy.com. Publícalos primero: el anuncio lleva a ellos.
                </p>
                <p v-else-if="hasOpen" class="ss__reason">
                    Tienes una solicitud en curso. Cuando termine podrás pedir otra.
                </p>

                <template v-else>
                    <section class="ss__block">
                        <h4 class="ss__h">¿Qué quieres anunciar?</h4>
                        <el-radio-group v-model="f.scope" size="small" class="ar__scope">
                            <el-radio label="tienda">Lo más atractivo de mi tienda</el-radio>
                            <el-radio label="categoria">Una categoría</el-radio>
                            <el-radio label="productos">Productos concretos</el-radio>
                        </el-radio-group>
                        <p v-if="f.scope === 'tienda'" class="ss__small">Elegimos los productos con mejor foto y precio de tus {{ data.published }} publicados.</p>
                        <el-select v-if="f.scope === 'categoria'" v-model="f.scope_detail" size="small" placeholder="Elige la categoría" class="ss__sel ss__sel--wide ar__mt">
                            <el-option v-for="c in data.categories" :key="c" :label="c" :value="c"></el-option>
                        </el-select>
                        <el-input v-if="f.scope === 'productos'" v-model="f.scope_detail" type="textarea" :rows="2" maxlength="500"
                                  placeholder="Ej. palmeras, árboles de cerezo y el pino navideño" class="ar__mt"></el-input>
                    </section>

                    <section class="ss__block">
                        <h4 class="ss__h">Presupuesto y duración</h4>
                        <div class="ss__row">
                            <span>Presupuesto total</span>
                            <el-radio-group v-model="f.budget" size="small">
                                <el-radio-button v-for="b in budgets" :key="b" :label="b">S/ {{ b }}</el-radio-button>
                            </el-radio-group>
                        </div>
                        <div class="ss__row">
                            <span>Durante</span>
                            <el-radio-group v-model="f.days" size="small">
                                <el-radio-button :label="7">7 días</el-radio-button>
                                <el-radio-button :label="14">14 días</el-radio-button>
                                <el-radio-button :label="30">30 días</el-radio-button>
                            </el-radio-group>
                        </div>
                        <p class="ss__small">Unos S/ {{ money(f.budget / f.days) }} al día. Es lo que se invierte en Facebook e Instagram.</p>
                        <div class="ss__row">
                            <span>Mostrar a personas de</span>
                            <el-radio-group v-model="f.zone" size="small">
                                <el-radio-button label="lima">Lima</el-radio-button>
                                <el-radio-button label="peru">Todo el Perú</el-radio-button>
                            </el-radio-group>
                        </div>
                    </section>

                    <section class="ss__block">
                        <h4 class="ss__h">¿Dónde te escribimos?</h4>
                        <div class="ss__row">
                            <span>WhatsApp</span>
                            <el-input v-model="f.contact_phone" size="small" maxlength="30" placeholder="Ej. 987 654 321" class="ss__sel"></el-input>
                        </div>
                        <el-input v-model="f.notes" type="textarea" :rows="2" maxlength="500" class="ar__mt"
                                  placeholder="Algo que debamos saber (opcional). Ej. tengo stock grande de palmeras"></el-input>
                    </section>

                    <p class="ss__small ar__foot">
                        Aquí no se cobra nada. Te escribimos por WhatsApp para confirmar y coordinar el pago;
                        la campaña empieza cuando esté pagada y verás sus resultados en esta ventana.
                    </p>
                </template>
            </template>
        </div>

        <span slot="footer">
            <el-button size="small" @click="close">Cerrar</el-button>
            <el-button v-if="canRequest" type="primary" size="small" :loading="saving" @click="save">Enviar solicitud</el-button>
        </span>
    </el-dialog>
</template>

<script>
export default {
    name: 'AdRequestSettings',
    props: { visible: { type: Boolean, default: false } },
    data() {
        return {
            loading: false,
            saving: false,
            data: null,
            budgets: [100, 150, 300, 500],
            f: { scope: 'tienda', scope_detail: '', budget: 150, days: 7, zone: 'lima', contact_phone: '', notes: '' },
        };
    },
    computed: {
        hasOpen() {
            return !!this.data && this.data.records.some(r => ['solicitada', 'pagada', 'activa'].includes(r.status));
        },
        canRequest() {
            return !!this.data && this.data.published > 0 && !this.hasOpen;
        },
    },
    mounted() {
        // Abierta desde el enlace ?publicidad=1: el watch no salta al montar.
        if (this.visible) this.load();
    },
    watch: {
        visible(v) { if (v) this.load(); },
        'f.scope'() { this.f.scope_detail = ''; },
    },
    methods: {
        load() {
            this.loading = true;
            this.$http.get('/ad-requests')
                .then(r => {
                    this.data = r.data;
                    const phone = (r.data.phone || '').trim();
                    if (!this.f.contact_phone && /\d{6,}/.test(phone.replace(/\D/g, ''))) this.f.contact_phone = phone;
                })
                .catch(() => { this.$message.error('No se pudo cargar la publicidad'); this.close(); })
                .then(() => { this.loading = false; });
        },
        save() {
            const el = document.activeElement;
            if (el && typeof el.blur === 'function') el.blur();
            this.$nextTick(() => {
                this.saving = true;
                this.$http.post('/ad-requests', this.f)
                    .then(r => {
                        this.$message.success((r.data && r.data.message) || 'Solicitud enviada');
                        this.load();
                    })
                    .catch(e => { this.$message.error(this.motivoDelError(e)); })
                    .then(() => { this.saving = false; });
            });
        },
        /**
         * En el panel los 422 llegan de tres formas: {"campo": ["msg"]},
         * {"errors": {...}} y {"success": false, "message": {"campo": [...]}}.
         */
        motivoDelError(e) {
            const d = e && e.response && e.response.data;
            if (d && typeof d === 'object') {
                const mapa = (d.errors && typeof d.errors === 'object') ? d.errors
                    : (d.message && typeof d.message === 'object') ? d.message : d;
                for (const k of Object.keys(mapa)) {
                    const v = mapa[k];
                    if (Array.isArray(v) && typeof v[0] === 'string') return v[0];
                }
                if (typeof d.message === 'string' && d.message) return d.message;
            }
            return 'No se pudo enviar. Revisa los datos e inténtalo otra vez.';
        },
        money(n) {
            return Number(n || 0).toFixed(2);
        },
        close() { this.$emit('update:visible', false); },
    },
};
</script>

<style scoped>
.ss { font-size: 13px; word-break: normal; overflow-wrap: break-word; }
.ss__reason { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 8px; padding: 8px 10px; margin: 8px 0; }
.ss__hint { color: #64748b; margin: 0 0 12px; line-height: 1.4; }
.ss__block { border-top: 1px solid #f1f5f9; padding: 10px 0; }
.ss__h { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
.ss__row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 6px; color: #334155; }
.ss__small { color: #64748b; font-size: 12px; margin: 4px 0 0; }
.ar__scope { display: flex; flex-direction: column; gap: 6px; }
.ar__mt { margin-top: 8px; }
.ar__foot { background: #f8fafc; border-radius: 8px; padding: 8px 10px; }
.ar__rec { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; margin-bottom: 8px; }
.ar__rec-top { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; color: #1e293b; font-weight: 600; }
.ar__res { margin-top: 4px; color: #0c6b65; }
.ar__st { font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; background: #e2e8f0; color: #334155; }
.ar__st--solicitada { background: #fef3c7; color: #92400e; }
.ar__st--pagada { background: #dbeafe; color: #1e40af; }
.ar__st--activa { background: #dcfce7; color: #166534; }
.ar__st--rechazada { background: #fee2e2; color: #991b1b; }
</style>
