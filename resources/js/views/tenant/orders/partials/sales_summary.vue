<template>
    <el-dialog
        :visible="showDialog"
        title="Ventas"
        width="560px"
        top="6vh"
        custom-class="vs-dialog"
        @close="$emit('update:showDialog', false)"
        @open="periodo = periodoInicial; cargar()"
    >
        <!-- Periodo: lo de todos los días a un toque; el rango, para lo demás. -->
        <div class="vs-periodos" role="radiogroup" aria-label="Periodo">
            <button
                v-for="p in periodos"
                :key="p.k"
                type="button"
                class="vs-per"
                :class="{ 'is-on': periodo === p.k }"
                @click="elegir(p.k)"
            >{{ p.l }}</button>
        </div>
        <el-date-picker
            v-if="periodo === 'rango'"
            v-model="rango"
            type="daterange"
            size="small"
            range-separator="a"
            start-placeholder="Desde"
            end-placeholder="Hasta"
            value-format="yyyy-MM-dd"
            style="width:100%;margin-top:10px"
            @change="cargar"
        ></el-date-picker>

        <div v-loading="cargando" class="vs-body">
            <template v-if="datos">
                <!-- Un número grande y tres bloques: lo que se pidió, sin tabla. -->
                <div class="vs-total">
                    <span class="vs-total__l">Cobrado {{ etiquetaPeriodo }}</span>
                    <span class="vs-total__v">{{ soles(datos.total) }}</span>
                    <span class="vs-total__s">{{ datos.pedidos }} {{ datos.pedidos === 1 ? "pedido nuevo" : "pedidos nuevos" }}</span>
                </div>

                <div class="vs-origenes">
                    <div v-for="o in origenes" :key="o.k" class="vs-org">
                        <span class="vs-org__i" :style="{ background: o.color }"></span>
                        <span class="vs-org__l">{{ o.l }}</span>
                        <span class="vs-org__v">{{ soles(datos.origenes[o.k].cobrado) }}</span>
                        <span class="vs-org__s">{{ datos.origenes[o.k].pedidos }} {{ datos.origenes[o.k].pedidos === 1 ? "pedido" : "pedidos" }} · {{ parte(o.k) }}</span>
                    </div>
                </div>

                <p v-if="datos.origenes.panel.cobrado > 0" class="vs-nota">
                    No incluye {{ soles(datos.origenes.panel.cobrado) }} de pedidos registrados desde el panel.
                </p>

                <!-- El detalle por día, plegado: solo si el periodo tiene varios. -->
                <template v-if="datos.dias.length > 1">
                    <button type="button" class="vs-toggle" @click="verDias = !verDias">
                        {{ verDias ? "Ocultar" : "Ver" }} por día ({{ datos.dias.length }})
                    </button>
                    <ul v-if="verDias" class="vs-dias">
                        <li v-for="d in datos.dias" :key="d.fecha" class="vs-dia">
                            <span class="vs-dia__f">{{ dia(d.fecha) }}</span>
                            <span class="vs-dia__bar">
                                <span
                                    v-for="o in origenes"
                                    :key="o.k"
                                    :style="{ width: (d[o.k] / maxDia * 100) + '%', background: o.color }"
                                    :title="o.l + ': ' + soles(d[o.k])"
                                ></span>
                            </span>
                            <span class="vs-dia__v">{{ soles(d.total) }}</span>
                        </li>
                    </ul>
                </template>

                <p v-if="!datos.total && !datos.pedidos" class="vs-vacio">Sin ventas en este periodo.</p>
            </template>
        </div>

        <p class="vs-pie">
            Suma los pagos registrados de la tienda online, el marketplace y el registro de pedidos,
            según la fecha del pago. No cuenta pedidos anulados.
        </p>
    </el-dialog>
</template>

<script>
const hoyISO = () => {
    const d = new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};
const masDias = (iso, n) => {
    const d = new Date(iso + "T12:00:00");
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
};

export default {
    props: {
        showDialog: { type: Boolean, default: false },
        periodoInicial: { type: String, default: "hoy" },
    },
    data() {
        return {
            periodos: [
                { k: "hoy", l: "Hoy" },
                { k: "ayer", l: "Ayer" },
                { k: "7", l: "7 días" },
                { k: "mes", l: "Este mes" },
                // Los primeros días del mes «Este mes» sale casi vacío y no
                // había forma de ver el anterior sin armar el rango a mano.
                { k: "mesPasado", l: "Mes pasado" },
                { k: "rango", l: "Rango" },
            ],
            origenes: [
                { k: "tienda", l: "Tienda online", color: "#2563eb" },
                { k: "marketplace", l: "Marketplace", color: "#7c3aed" },
                { k: "registro", l: "Registro de pedidos", color: "#059669" },
            ],
            periodo: this.periodoInicial,
            rango: null,
            datos: null,
            cargando: false,
            verDias: false,
        };
    },
    computed: {
        etiquetaPeriodo() {
            return { hoy: "hoy", ayer: "ayer", 7: "en 7 días", mes: "este mes", mesPasado: "el mes pasado", rango: "en el rango" }[this.periodo];
        },
        maxDia() {
            return Math.max(1, ...((this.datos && this.datos.dias) || []).map(d => d.total));
        },
    },
    methods: {
        elegir(k) {
            this.periodo = k;
            if (k !== "rango") this.cargar();
        },
        fechas() {
            const hoy = hoyISO();
            if (this.periodo === "ayer") return [masDias(hoy, -1), masDias(hoy, -1)];
            if (this.periodo === "7") return [masDias(hoy, -6), hoy];
            if (this.periodo === "mes") return [hoy.slice(0, 8) + "01", hoy];
            if (this.periodo === "mesPasado") {
                const fin = masDias(hoy.slice(0, 8) + "01", -1);
                return [fin.slice(0, 8) + "01", fin];
            }
            if (this.periodo === "rango" && this.rango) return this.rango;
            return [hoy, hoy];
        },
        cargar() {
            const [desde, hasta] = this.fechas();
            this.cargando = true;
            this.$http
                .get("/orders/ventas", { params: { desde, hasta } })
                .then(r => { this.datos = r.data; this.$emit("cargado", r.data); })
                .catch(() => { this.$message.error("No se pudieron cargar las ventas."); })
                .then(() => { this.cargando = false; });
        },
        soles(n) {
            return "S/ " + Number(n || 0).toLocaleString("es-PE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        parte(k) {
            const t = this.datos.total;
            return t > 0 ? Math.round(this.datos.origenes[k].cobrado / t * 100) + "%" : "0%";
        },
        dia(iso) {
            const d = new Date(iso + "T12:00:00");
            return d.toLocaleDateString("es-PE", { weekday: "short", day: "numeric", month: "short" });
        },
    },
};
</script>

<style scoped>
.vs-periodos { display: flex; flex-wrap: wrap; gap: 6px; }
.vs-per { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 6px 14px; font: 600 13px/1 inherit; cursor: pointer; }
.vs-per.is-on { background: #0f172a; border-color: #0f172a; color: #fff; }
.vs-body { min-height: 120px; margin-top: 18px; }
.vs-total { display: flex; flex-direction: column; align-items: flex-start; padding: 4px 2px 16px; border-bottom: 1px solid #f1f5f9; }
.vs-total__l { font-size: 13px; color: #64748b; }
.vs-total__v { font-size: 34px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; line-height: 1.15; font-variant-numeric: tabular-nums; }
.vs-total__s { font-size: 13px; color: #64748b; }
.vs-origenes { display: grid; gap: 2px; margin-top: 10px; }
.vs-org { display: grid; grid-template-columns: 10px 1fr auto; grid-template-rows: auto auto; column-gap: 10px; align-items: center; padding: 10px 2px; border-bottom: 1px solid #f1f5f9; }
.vs-org__i { grid-row: 1 / span 2; width: 10px; height: 10px; border-radius: 3px; }
.vs-org__l { font-size: 14px; font-weight: 600; color: #0f172a; }
.vs-org__v { font-size: 16px; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.vs-org__s { grid-column: 2 / span 2; font-size: 12px; color: #64748b; }
.vs-nota { margin: 10px 2px 0; font-size: 12.5px; color: #64748b; }
.vs-toggle { margin-top: 12px; border: 0; background: none; color: #2563eb; font: 600 13px/1 inherit; padding: 4px 0; cursor: pointer; }
.vs-dias { list-style: none; margin: 8px 0 0; padding: 0; max-height: 260px; overflow-y: auto; }
.vs-dia { display: grid; grid-template-columns: 92px 1fr 92px; gap: 10px; align-items: center; padding: 5px 0; font-size: 12.5px; }
.vs-dia__f { color: #475569; text-transform: capitalize; }
.vs-dia__bar { display: flex; height: 8px; border-radius: 4px; background: #f1f5f9; overflow: hidden; }
.vs-dia__v { text-align: right; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; }
.vs-vacio { color: #64748b; font-size: 13px; margin-top: 12px; }
.vs-pie { margin: 16px 0 0; font-size: 12px; color: #94a3b8; line-height: 1.45; }
</style>
