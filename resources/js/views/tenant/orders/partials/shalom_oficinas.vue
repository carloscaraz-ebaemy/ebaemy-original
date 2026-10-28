<template>
    <div class="sho">
        <div class="sho-tools">
            <small class="sho-scope">{{ alcance }}</small>
            <button v-if="oficinas.length > 1 && geo" type="button" class="sho-near" :class="{ on: origen }" @click="cercana">
                {{ ubicando ? "Ubicando…" : origen ? "✓ Ordenadas por cercanía" : "📍 Ordenar por cercanía" }}
            </button>
        </div>

        <p v-if="cargando" class="sho-empty">Buscando oficinas de Shalom…</p>
        <p v-else-if="!district" class="sho-empty">Primero elige la ciudad de destino y te mostramos sus oficinas.</p>
        <p v-else-if="!oficinas.length" class="sho-empty">
            No hay oficinas de Shalom registradas para esa zona. Escribe abajo en cuál recoge, o déjalo en blanco.
        </p>

        <div v-else class="sho-list" role="radiogroup" aria-label="Oficinas de Shalom">
            <button
                v-for="(o, i) in oficinas"
                :key="o.id"
                type="button"
                role="radio"
                class="sho-opt"
                :aria-checked="String(o.id) === String(value)"
                @click="elegir(o)"
            >
                <b>{{ o.name }}</b>
                <span v-if="o.address">{{ o.address }}</span>
                <span v-if="o.hours">🕒 {{ o.hours }}</span>
                <i v-if="origen && i === 0 && km(o) !== null" class="sho-best">Más cercana</i>
                <i v-if="km(o) !== null">a {{ km(o) < 10 ? km(o).toFixed(1) : Math.round(km(o)) }} km</i>
                <i v-if="scope === 'province' && o.district">{{ o.district }}</i>
                <i v-if="o.home_delivery">Reparto a domicilio</i>
            </button>
        </div>

        <label v-if="oficinas.length" class="sho-chk">
            <input type="checkbox" :checked="unknown" @change="noEsta($event.target.checked)" />
            <span>No está su oficina en la lista</span>
        </label>
    </div>
</template>

<script>
/**
 * Las oficinas de Shalom del distrito, como en el enlace del cliente: mismo
 * endpoint (`/envio/agencias-shalom/{distrito}`, catálogo guardado en el
 * sistema) y misma regla: si el distrito no tiene oficina, las de su
 * provincia. El envío guarda la oficina exacta (`agency_office_id`), no un
 * texto libre que el almacén tenga que interpretar.
 */
export default {
    props: {
        district: { type: [String, Number], default: "" },
        value: { type: [String, Number], default: null },
        unknown: { type: Boolean, default: false },
    },
    data() {
        return { oficinas: [], scope: "", cargando: false, origen: null, ubicando: false, geo: !!navigator.geolocation, pedido: 0 };
    },
    computed: {
        alcance() {
            if (!this.oficinas.length) return "";
            if (this.scope === "province") return "Su distrito no tiene oficina de Shalom: estas son las de su provincia.";
            return this.oficinas.length === 1 ? "1 oficina en su distrito" : this.oficinas.length + " oficinas en su distrito";
        },
    },
    watch: {
        district: { immediate: true, handler: "cargar" },
    },
    methods: {
        cargar() {
            const d = this.district;
            this.oficinas = [];
            this.scope = "";
            if (!d) return;
            const n = ++this.pedido;
            this.cargando = true;
            this.$http
                .get(`/envio/agencias-shalom/${encodeURIComponent(d)}`)
                .then(r => {
                    if (n !== this.pedido) return;
                    const x = r.data || {};
                    this.oficinas = Array.isArray(x.offices) ? x.offices : [];
                    this.scope = x.scope || "";
                    if (this.origen) this.ordenar();
                    // Una oficina de otra zona no sirve: se suelta.
                    if (this.value && !this.oficinas.some(o => String(o.id) === String(this.value))) this.$emit("input", null);
                    this.$emit("loaded", this.oficinas.length);
                })
                .catch(() => { if (n === this.pedido) this.oficinas = []; })
                .then(() => { if (n === this.pedido) this.cargando = false; });
        },
        elegir(o) {
            this.$emit("input", o.id);
            this.$emit("update:unknown", false);
            this.$emit("picked", o);
        },
        noEsta(marcado) {
            this.$emit("update:unknown", marcado);
            if (marcado) this.$emit("input", null);
        },
        km(o) {
            if (!this.origen || o.lat == null || o.lng == null) return null;
            const r = Math.PI / 180, a = this.origen;
            const h = Math.sin(((o.lat - a.lat) * r) / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(o.lat * r) * Math.sin(((o.lng - a.lng) * r) / 2) ** 2;
            return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
        },
        ordenar() {
            this.oficinas = this.oficinas.slice().sort((x, y) => (this.km(x) ?? 1e9) - (this.km(y) ?? 1e9));
        },
        cercana() {
            this.ubicando = true;
            navigator.geolocation.getCurrentPosition(
                p => { this.origen = { lat: p.coords.latitude, lng: p.coords.longitude }; this.ordenar(); this.ubicando = false; },
                () => { this.ubicando = false; this.$message.warning("No se pudo usar la ubicación de este equipo."); },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 600000 }
            );
        },
    },
};
</script>

<style scoped>
.sho-tools { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.sho-scope { font-size: 12.5px; color: #64748b; }
.sho-near { border: 1.5px solid #c7d2fe; background: #eef2ff; color: #3730a3; border-radius: 999px; padding: 5px 12px; font-size: 12.5px; font-weight: 700; cursor: pointer; }
.sho-near.on { border-color: #e2e8f0; background: #fff; color: #475569; }
.sho-list { display: grid; gap: 8px; max-height: 320px; overflow: auto; padding-right: 2px; }
.sho-opt { text-align: left; border: 1.5px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 10px 12px; cursor: pointer; font: inherit; color: #0f172a; }
.sho-opt:hover { border-color: #a5b4fc; }
.sho-opt[aria-checked="true"] { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 1px #4f46e5; }
.sho-opt b { display: block; font-size: 14px; line-height: 1.3; }
.sho-opt span { display: block; font-size: 12.5px; color: #475569; margin-top: 2px; line-height: 1.4; }
.sho-opt i { font-style: normal; display: inline-block; font-size: 11.5px; font-weight: 650; border-radius: 999px; padding: 2px 8px; margin: 6px 4px 0 0; background: #f1f5f9; color: #475569; }
.sho-opt i.sho-best { background: #dcfce7; color: #166534; }
.sho-empty { font-size: 13px; color: #475569; margin: 0; padding: 10px 12px; background: #f8fafc; border-radius: 10px; }
.sho-chk { display: flex; gap: 8px; align-items: center; margin-top: 10px; font-size: 13.5px; cursor: pointer; }
</style>
