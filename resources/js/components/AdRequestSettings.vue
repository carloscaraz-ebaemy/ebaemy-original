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
        <div v-loading="loading" class="ss ar">
            <p class="ss__hint">
                ebaemy anuncia tus productos en Facebook e Instagram. Quien hace clic llega a tu tienda en
                ebaemy.com y los pedidos te llegan al panel como siempre.
            </p>

            <!-- Cómo funciona: siempre visible, para que nadie tenga que preguntar -->
            <ol class="ar__how" aria-label="Cómo funciona">
                <li v-for="(p, i) in how" :key="i" class="ar__how-step">
                    <span class="ar__how-n">{{ i + 1 }}</span>
                    <span class="ar__how-t">{{ p }}</span>
                </li>
            </ol>

            <template v-if="data">
                <section v-if="data.records.length" class="ar__card">
                    <h4 class="ar__h">Tus solicitudes</h4>
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
                    <section class="ar__card">
                        <h4 class="ar__h"><span class="ar__hn">1</span> ¿Qué quieres anunciar?</h4>
                        <div class="ar__opts" role="radiogroup" aria-label="Qué anunciar">
                            <button v-for="o in scopes" :key="o.value" type="button" role="radio"
                                    class="ar__opt" :class="{ 'is-on': f.scope === o.value }"
                                    :aria-checked="f.scope === o.value ? 'true' : 'false'"
                                    @click="f.scope = o.value">
                                <span class="ar__opt-ico" aria-hidden="true">{{ o.icon }}</span>
                                <span class="ar__opt-txt">
                                    <b>{{ o.label }}</b>
                                    <small>{{ o.value === 'tienda' ? 'Elegimos los de mejor foto y precio de tus ' + data.published : o.hint }}</small>
                                </span>
                            </button>
                        </div>
                        <el-select v-if="f.scope === 'categoria'" v-model="f.scope_detail" size="small" placeholder="Elige la categoría" class="ss__sel ss__sel--wide ar__mt">
                            <el-option v-for="c in data.categories" :key="c" :label="c" :value="c"></el-option>
                        </el-select>
                        <el-input v-if="f.scope === 'productos'" v-model="f.scope_detail" type="textarea" :rows="2" maxlength="500"
                                  placeholder="Ej. palmeras, árboles de cerezo y el pino navideño" class="ar__mt"></el-input>
                    </section>

                    <section class="ar__card">
                        <h4 class="ar__h"><span class="ar__hn">2</span> Presupuesto y duración</h4>
                        <div class="ar__lbl">Presupuesto total</div>
                        <div class="ar__budget">
                            <span class="ar__budget-cur">S/</span>
                            <el-input-number v-model="f.budget" :min="minBudget" :max="maxBudget" :step="1" :precision="2"
                                             controls-position="right" aria-label="Presupuesto total en soles" />
                        </div>
                        <div class="ar__hint">Escribe el monto que quieras invertir, desde S/ {{ minBudget }}.</div>
                        <div class="ar__lbl">Durante</div>
                        <div class="ar__chips">
                            <button v-for="d in [7, 14, 30]" :key="d" type="button" class="ar__chip"
                                    :class="{ 'is-on': f.days === d }" @click="f.days = d">{{ d }} días</button>
                        </div>
                        <div class="ar__lbl">Mostrar a personas de</div>
                        <div class="ar__chips">
                            <button type="button" class="ar__chip" :class="{ 'is-on': f.zone === 'lima' }" @click="f.zone = 'lima'">Lima</button>
                            <button type="button" class="ar__chip" :class="{ 'is-on': f.zone === 'peru' }" @click="f.zone = 'peru'">Todo el Perú</button>
                        </div>
                        <p class="ar__sum">
                            Unos <b>S/ {{ money((f.budget || 0) / f.days) }}</b> al día. Todo se invierte en Facebook e Instagram.
                        </p>
                        <p v-if="f.budget && f.budget / f.days < minPerDay" class="ar__warn">
                            Menos de S/ {{ minPerDay }} al día casi no se muestra. Con este monto, elige menos días.
                        </p>
                    </section>

                    <section class="ar__card">
                        <h4 class="ar__h"><span class="ar__hn">3</span> ¿Dónde te escribimos?</h4>
                        <div class="ss__row">
                            <span>WhatsApp</span>
                            <el-input v-model="f.contact_phone" size="small" maxlength="30" placeholder="Ej. 987 654 321" class="ss__sel"></el-input>
                        </div>
                        <el-input v-model="f.notes" type="textarea" :rows="2" maxlength="500" class="ar__mt"
                                  placeholder="Algo que debamos saber (opcional). Ej. tengo stock grande de palmeras"></el-input>
                    </section>

                    <p class="ar__foot">
                        🔒 Aquí no se cobra nada. Te escribimos por WhatsApp para confirmar y coordinar el pago;
                        la campaña empieza cuando esté pagada.
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
            // Mismos topes que AdRequestController::store.
            minBudget: 20,
            maxBudget: 20000,
            // Por debajo de esto Meta apenas entrega el anuncio (aviso, no bloqueo).
            minPerDay: 4,
            how: ['Pides aquí', 'Te escribimos por WhatsApp y pagas', 'Lanzamos tu campaña', 'Ves tus pedidos y consultas aquí'],
            scopes: [
                { value: 'tienda', icon: '⭐', label: 'Lo más atractivo de mi tienda', hint: '' },
                { value: 'categoria', icon: '🗂️', label: 'Una categoría', hint: 'Por ejemplo, solo Decoración' },
                { value: 'productos', icon: '🎯', label: 'Productos concretos', hint: 'Nos dices cuáles' },
            ],
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
.ar__mt { margin-top: 8px; }
.ar__how { list-style: none; margin: 0 0 12px; padding: 10px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
    background: linear-gradient(135deg, #eef2ff, #f5f3ff); border: 1px solid #e0e7ff; border-radius: 12px; }
.ar__how-step { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px; }
.ar__how-n { width: 24px; height: 24px; border-radius: 50%; background: #4f46e5; color: #fff; font-weight: 700; font-size: 12px;
    display: inline-flex; align-items: center; justify-content: center; }
.ar__how-t { font-size: 11.5px; line-height: 1.3; color: #3730a3; font-weight: 600; }
.ar__card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 10px; background: #fff; }
.ar__h { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
.ar__hn { width: 22px; height: 22px; border-radius: 6px; background: #eef2ff; color: #4f46e5; font-size: 12px;
    display: inline-flex; align-items: center; justify-content: center; flex: none; }
.ar__opts { display: flex; flex-direction: column; gap: 8px; }
.ar__opt { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; cursor: pointer;
    border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff; padding: 10px 12px; min-height: 48px;
    transition: border-color .15s, background .15s; }
.ar__opt:hover { border-color: #a5b4fc; }
.ar__opt.is-on { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 3px rgba(79, 70, 229, .12); }
.ar__opt-ico { font-size: 20px; flex: none; }
.ar__opt-txt { display: flex; flex-direction: column; min-width: 0; }
.ar__opt-txt b { font-size: 13.5px; color: #1e293b; }
.ar__opt-txt small { font-size: 12px; color: #64748b; }
.ar__lbl { font-size: 12px; font-weight: 600; color: #475569; margin: 10px 0 6px; }
.ar__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ar__budget { display: flex; align-items: center; gap: 8px; }
.ar__budget-cur { font-weight: 700; color: #334155; }
.ar__budget .el-input-number { width: 180px; max-width: 100%; }
.ar__hint { font-size: 12px; color: #64748b; margin-top: 4px; }
.ar__warn { font-size: 12.5px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 6px 10px; margin: 6px 0 0; }
.ar__chip { border: 1.5px solid #e2e8f0; background: #fff; color: #334155; border-radius: 999px; padding: 6px 14px;
    font-size: 13px; font-weight: 600; cursor: pointer; min-height: 36px; }
.ar__chip:hover { border-color: #a5b4fc; }
.ar__chip.is-on { background: #4f46e5; border-color: #4f46e5; color: #fff; }
.ar__sum { margin: 12px 0 0; padding: 8px 10px; border-radius: 8px; background: #f0fdf4; color: #166534; font-size: 12.5px; line-height: 1.4; }
.ar__foot { background: #f8fafc; border-radius: 8px; padding: 8px 10px; color: #475569; font-size: 12px; margin: 0; }
.ar__opt:focus-visible, .ar__chip:focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px; }
.ar__card .ar__rec:last-child { margin-bottom: 0; }
@media (max-width: 480px) {
    .ar__how { grid-template-columns: repeat(2, 1fr); }
}
.ar__rec { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; margin-bottom: 8px; }
.ar__rec-top { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; color: #1e293b; font-weight: 600; }
.ar__res { margin-top: 4px; color: #0c6b65; }
.ar__st { font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; background: #e2e8f0; color: #334155; }
.ar__st--solicitada { background: #fef3c7; color: #92400e; }
.ar__st--pagada { background: #dbeafe; color: #1e40af; }
.ar__st--activa { background: #dcfce7; color: #166534; }
.ar__st--rechazada { background: #fee2e2; color: #991b1b; }
</style>
