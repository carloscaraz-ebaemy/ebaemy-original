<template>
    <!-- Recorrido guiado de novedades, reutilizable en cualquier pantalla:
         <feature-tour ref="tour" tour-key="pantalla-novedad-2026-10" :steps="[...]" />
         Cada paso: { target: 'selector css', title, body }. Los pasos cuyo
         elemento no está en pantalla se saltan. Sale una sola vez por usuario
         (se guarda en su cuenta: /feature-tours/{key}); `start()` lo reabre. -->
    <div v-if="active && step" class="ft" role="dialog" aria-modal="true" :aria-label="step.title">
        <div class="ft__shade" @click="finish('completed')"></div>
        <div class="ft__hole" :style="holeStyle"></div>
        <div class="ft__pop" :class="'ft__pop--' + placement" :style="popStyle">
            <div class="ft__badge">Nuevo</div>
            <h4 class="ft__title">{{ step.title }}</h4>
            <p class="ft__body">{{ step.body }}</p>
            <div class="ft__foot">
                <button type="button" class="ft__skip" @click="finish('dismissed')">No volver a mostrar</button>
                <span class="ft__count" v-if="steps_.length > 1">{{ index + 1 }} / {{ steps_.length }}</span>
                <span class="ft__actions">
                    <button v-if="index > 0" type="button" class="ft__btn ft__btn--ghost" @click="go(index - 1)">Atrás</button>
                    <button type="button" class="ft__btn" @click="next">
                        {{ index < steps_.length - 1 ? 'Siguiente' : 'Entendido' }}
                    </button>
                </span>
            </div>
        </div>
    </div>
</template>

<script>
const PAD = 6;
const GAP = 12;

export default {
    name: 'FeatureTour',
    props: {
        tourKey: { type: String, required: true },
        steps: { type: Array, required: true },
        // Arranca solo si el usuario aún no la vio.
        autoStart: { type: Boolean, default: true },
        // Espera a que la pantalla pinte los elementos (tablas que cargan).
        delay: { type: Number, default: 900 },
    },
    data() {
        return {
            active: false,
            index: 0,
            steps_: [],
            rect: null,
            placement: 'bottom',
            popW: 320,
        };
    },
    computed: {
        step() {
            return this.steps_[this.index] || null;
        },
        holeStyle() {
            if (!this.rect) return { display: 'none' };
            return {
                top: (this.rect.top - PAD) + 'px',
                left: (this.rect.left - PAD) + 'px',
                width: (this.rect.width + PAD * 2) + 'px',
                height: (this.rect.height + PAD * 2) + 'px',
            };
        },
        popStyle() {
            if (!this.rect) return {};
            const vw = window.innerWidth;
            const w = Math.min(this.popW, vw - 24);
            let left = this.rect.left + this.rect.width / 2 - w / 2;
            left = Math.max(12, Math.min(left, vw - w - 12));
            const s = { width: w + 'px', left: left + 'px' };
            if (this.placement === 'bottom') s.top = (this.rect.bottom + PAD + GAP) + 'px';
            else s.bottom = (window.innerHeight - this.rect.top + PAD + GAP) + 'px';
            return s;
        },
    },
    mounted() {
        window.addEventListener('resize', this.measure);
        window.addEventListener('scroll', this.measure, true);
        window.addEventListener('keydown', this.onKey);
        if (this.autoStart) this.checkAndStart();
    },
    beforeDestroy() {
        window.removeEventListener('resize', this.measure);
        window.removeEventListener('scroll', this.measure, true);
        window.removeEventListener('keydown', this.onKey);
    },
    methods: {
        checkAndStart() {
            this.$http.get(`/feature-tours/${this.tourKey}`)
                .then(r => { if (r.data && r.data.seen === false) setTimeout(() => this.start(), this.delay); })
                .catch(() => { /* sin guía: nunca debe estorbar a la pantalla */ });
        },
        // Público: también lo llama el botón «¿Qué hay de nuevo?».
        start(intentos = 15) {
            this.steps_ = this.steps.filter(s => this.find(s.target));
            if (!this.steps_.length) {
                // La tabla puede seguir cargando: se reintenta unos segundos.
                if (intentos > 0) setTimeout(() => this.start(intentos - 1), 800);
                return;
            }
            this.active = true;
            this.go(0);
        },
        find(sel) {
            const list = Array.from(document.querySelectorAll(sel));
            return list.find(el => el.offsetParent !== null && el.getBoundingClientRect().width > 0) || null;
        },
        go(i) {
            this.index = i;
            const el = this.find(this.step.target);
            if (!el) return this.next();
            el.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
            // El desplazamiento suave tarda: se mide al llegar.
            this.rect = null;
            setTimeout(this.measure, 350);
        },
        measure() {
            if (!this.active || !this.step) return;
            const el = this.find(this.step.target);
            if (!el) return;
            const r = el.getBoundingClientRect();
            this.rect = { top: r.top, left: r.left, width: r.width, height: r.height, bottom: r.bottom };
            // Abajo si cabe; si no, arriba.
            this.placement = (window.innerHeight - r.bottom) > 200 || r.top < 200 ? 'bottom' : 'top';
        },
        next() {
            if (this.index < this.steps_.length - 1) this.go(this.index + 1);
            else this.finish('completed');
        },
        onKey(e) {
            if (!this.active) return;
            if (e.key === 'Escape') this.finish('completed');
            if (e.key === 'ArrowRight' || e.key === 'Enter') this.next();
            if (e.key === 'ArrowLeft' && this.index > 0) this.go(this.index - 1);
        },
        finish(status) {
            this.active = false;
            this.rect = null;
            this.$http.post(`/feature-tours/${this.tourKey}`, { status }).catch(() => {});
            this.$emit('finished', status);
        },
    },
};
</script>

<style scoped>
.ft { position: fixed; inset: 0; z-index: 3000; }
.ft__shade { position: absolute; inset: 0; }
/* El «agujero»: una sombra gigante oscurece todo menos el elemento. */
.ft__hole {
    position: fixed; border-radius: 8px; pointer-events: none;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, .58), 0 0 0 3px #818cf8;
    transition: top .2s, left .2s, width .2s, height .2s;
}
.ft__pop {
    position: fixed; background: #fff; border-radius: 10px; padding: 14px 16px 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .28); color: #1e293b;
}
.ft__badge {
    display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: #fff; background: #6366f1; border-radius: 4px; padding: 2px 6px;
}
.ft__title { font-size: 15px; font-weight: 700; margin: 8px 0 4px; }
.ft__body { font-size: 13px; line-height: 1.45; color: #475569; margin: 0 0 12px; }
.ft__foot { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.ft__actions { display: flex; gap: 8px; margin-left: auto; }
.ft__skip {
    border: 0; background: none; color: #94a3b8; font-size: 12px; padding: 0;
    cursor: pointer; text-decoration: underline; white-space: nowrap;
}
.ft__count { font-size: 12px; color: #94a3b8; white-space: nowrap; }
.ft__btn {
    border: 0; background: #4f46e5; color: #fff; font-size: 13px; font-weight: 600;
    border-radius: 6px; padding: 7px 14px; cursor: pointer; min-height: 34px;
}
.ft__btn--ghost { background: #eef2ff; color: #4f46e5; }
@media (max-width: 576px) {
    .ft__skip { font-size: 11px; }
}
</style>
