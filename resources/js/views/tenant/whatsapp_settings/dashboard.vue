<template>
  <div class="wa-dash" v-loading="loading">
    <!-- Header -->
    <div class="page-header pe-0">
      <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top:-4px">
          <path d="M3 3v18h18"></path>
          <path d="M7 12l4-4 4 4 5-5"></path>
        </svg>
        WhatsApp — Dashboard
      </h2>
      <ol class="breadcrumbs">
        <li><a href="/whatsapp/settings">Configuración</a></li>
        <li class="active"><span>Métricas</span></li>
      </ol>
    </div>

    <!-- Filtro de período -->
    <div class="wa-filters">
      <button v-for="opt in ranges" :key="opt" class="wa-chip" :class="{ 'is-active': range === opt }" @click="setRange(opt)">
        Últimos {{ opt }} días
      </button>
      <span class="wa-muted" v-if="data">
        {{ data.from }} → {{ data.to }} · driver activo: <b>{{ data.active_driver }}</b>
      </span>
    </div>

    <!-- KPIs principales -->
    <div class="wa-kpi-grid">
      <div class="wa-kpi-card wa-kpi--blue">
        <div class="wa-kpi-card__icon">📨</div>
        <div>
          <div class="wa-kpi-card__label">Total enviados</div>
          <div class="wa-kpi-card__value">{{ data ? data.totals.total : 0 }}</div>
        </div>
      </div>
      <div class="wa-kpi-card wa-kpi--green">
        <div class="wa-kpi-card__icon">✓</div>
        <div>
          <div class="wa-kpi-card__label">Exitosos</div>
          <div class="wa-kpi-card__value">{{ data ? data.totals.sent : 0 }}</div>
        </div>
      </div>
      <div class="wa-kpi-card wa-kpi--red">
        <div class="wa-kpi-card__icon">✗</div>
        <div>
          <div class="wa-kpi-card__label">Fallidos</div>
          <div class="wa-kpi-card__value">{{ data ? data.totals.failed : 0 }}</div>
        </div>
      </div>
      <div class="wa-kpi-card wa-kpi--amber">
        <div class="wa-kpi-card__icon">%</div>
        <div>
          <div class="wa-kpi-card__label">Tasa de éxito</div>
          <div class="wa-kpi-card__value">{{ data ? data.totals.success_rate : 0 }}%</div>
        </div>
      </div>
    </div>

    <!-- Serie temporal -->
    <div class="wa-card">
      <div class="wa-card__header">
        <h3>Envíos por día</h3>
        <span class="wa-muted">Enviados (verde) vs Fallidos (rojo)</span>
      </div>
      <div class="wa-chart"><canvas ref="chartTimeseries"></canvas></div>
    </div>

    <!-- Fila: distribución driver + tipo -->
    <div class="wa-grid-2">
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Distribución por driver</h3>
        </div>
        <div class="wa-chart wa-chart--small"><canvas ref="chartDriver"></canvas></div>
      </div>
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Tipo de mensaje</h3>
        </div>
        <div class="wa-chart wa-chart--small"><canvas ref="chartType"></canvas></div>
      </div>
    </div>

    <!-- Fila: horas pico + mensajes por origen -->
    <div class="wa-grid-2">
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Horas pico de envío</h3>
          <span class="wa-muted">0h → 23h</span>
        </div>
        <div class="wa-chart"><canvas ref="chartHourly"></canvas></div>
      </div>
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Por origen</h3>
          <span class="wa-muted">Pedido, carrito, campaña, etc.</span>
        </div>
        <table class="table wa-table-clean">
          <thead>
            <tr><th>Origen</th><th class="text-end">Mensajes</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in (data ? data.by_source : [])" :key="s.source">
              <td><span class="wa-pill">{{ s.source || 'otros' }}</span></td>
              <td class="text-end"><b>{{ s.count }}</b></td>
            </tr>
            <tr v-if="data && !data.by_source.length">
              <td colspan="2" class="wa-empty">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Fila: top destinatarios + errores recientes -->
    <div class="wa-grid-2">
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Top destinatarios</h3>
          <span class="wa-muted">Los 10 más mensajes recibieron</span>
        </div>
        <table class="table wa-table-clean">
          <thead>
            <tr>
              <th>Teléfono</th>
              <th class="text-end">Enviados</th>
              <th class="text-end">Fallidos</th>
              <th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in (data ? data.top_recipients : [])" :key="r.phone">
              <td>{{ r.phone }}</td>
              <td class="text-end wa-num-green">{{ r.sent }}</td>
              <td class="text-end wa-num-red">{{ r.failed }}</td>
              <td class="text-end"><b>{{ r.total }}</b></td>
            </tr>
            <tr v-if="data && !data.top_recipients.length">
              <td colspan="4" class="wa-empty">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="wa-card">
        <div class="wa-card__header">
          <h3>Errores recientes</h3>
          <span class="wa-muted">Últimos 8 con mensaje de error</span>
        </div>
        <div class="wa-error-list">
          <div v-for="(e, i) in (data ? data.recent_errors : [])" :key="i" class="wa-error-item">
            <div class="wa-error-item__head">
              <span class="wa-muted">{{ e.created_at }}</span>
              <span class="wa-pill">{{ e.source || 'otros' }}</span>
              <span class="wa-muted">· {{ e.driver }}</span>
            </div>
            <div class="wa-error-item__phone">{{ e.phone }}</div>
            <div class="wa-error-item__msg">{{ e.error }}</div>
          </div>
          <div v-if="data && !data.recent_errors.length" class="wa-empty">
            🎉 Sin errores recientes.
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import Chart from 'chart.js';

export default {
  name: 'WhatsAppDashboard',
  data() {
    return {
      loading: false,
      range: 30,
      ranges: [7, 30, 90],
      data: null,
      charts: {
        timeseries: null,
        driver: null,
        type: null,
        hourly: null,
      },
    };
  },
  created() {
    this.load();
  },
  beforeDestroy() {
    this.destroyCharts();
  },
  methods: {
    setRange(days) {
      this.range = days;
      this.load();
    },
    async load() {
      this.loading = true;
      try {
        const r = await this.$http.get('/whatsapp/dashboard/data', { params: { range: this.range } });
        this.data = r.data;
        this.$nextTick(() => this.renderAllCharts());
      } catch (e) {
        this.$message.error('No se pudieron cargar las métricas');
      } finally {
        this.loading = false;
      }
    },
    destroyCharts() {
      for (const key in this.charts) {
        if (this.charts[key]) {
          try { this.charts[key].destroy(); } catch (e) {}
          this.charts[key] = null;
        }
      }
    },
    renderAllCharts() {
      this.destroyCharts();
      this.renderTimeseries();
      this.renderDriverChart();
      this.renderTypeChart();
      this.renderHourlyChart();
    },
    renderTimeseries() {
      const ctx = this.$refs.chartTimeseries?.getContext('2d');
      if (!ctx || !this.data) return;
      const labels = this.data.timeseries.map(d => d.label);
      const sent = this.data.timeseries.map(d => d.sent);
      const failed = this.data.timeseries.map(d => d.failed);
      this.charts.timeseries = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Enviados',
              data: sent,
              borderColor: '#10b981',
              backgroundColor: 'rgba(16,185,129,0.1)',
              fill: true,
              tension: 0.3,
              borderWidth: 2,
              pointRadius: 3,
            },
            {
              label: 'Fallidos',
              data: failed,
              borderColor: '#ef4444',
              backgroundColor: 'rgba(239,68,68,0.1)',
              fill: true,
              tension: 0.3,
              borderWidth: 2,
              pointRadius: 3,
            },
          ],
        },
        options: this.commonLineOptions(),
      });
    },
    renderDriverChart() {
      const ctx = this.$refs.chartDriver?.getContext('2d');
      if (!ctx || !this.data) return;
      const labels = this.data.by_driver.map(d => this.driverLabel(d.driver));
      const counts = this.data.by_driver.map(d => d.count);
      this.charts.driver = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data: counts,
            backgroundColor: ['#1fb1a6', '#c9962b', '#94a3b8', '#6366f1'],
            borderWidth: 0,
          }],
        },
        options: this.commonPieOptions(),
      });
    },
    renderTypeChart() {
      const ctx = this.$refs.chartType?.getContext('2d');
      if (!ctx || !this.data) return;
      const labels = this.data.by_type.map(d => d.type);
      const counts = this.data.by_type.map(d => d.count);
      this.charts.type = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data: counts,
            backgroundColor: ['#0f8a82', '#d4a93c', '#a78bfa'],
            borderWidth: 0,
          }],
        },
        options: this.commonPieOptions(),
      });
    },
    renderHourlyChart() {
      const ctx = this.$refs.chartHourly?.getContext('2d');
      if (!ctx || !this.data) return;
      const labels = this.data.hourly.map(h => h.hour + 'h');
      const counts = this.data.hourly.map(h => h.count);
      this.charts.hourly = new Chart(ctx, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Mensajes',
            data: counts,
            backgroundColor: 'rgba(15,138,130,0.65)',
            borderColor: '#0f8a82',
            borderWidth: 1,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: { display: false },
          scales: {
            yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
            xAxes: [{ gridLines: { display: false } }],
          },
        },
      });
    },
    commonLineOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } },
        tooltips: { mode: 'index', intersect: false },
        scales: {
          yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
          xAxes: [{ gridLines: { display: false } }],
        },
      };
    },
    commonPieOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 10 } },
        cutoutPercentage: 65,
      };
    },
    driverLabel(name) {
      return { meta_cloud: 'Meta Cloud', qr_api: 'QR API', none: 'Deshabilitado' }[name] || name;
    },
  },
};
</script>

<style scoped>
.wa-dash { padding: 20px; }

.wa-filters {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-bottom: 20px; padding: 12px 16px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
}
.wa-chip {
  padding: 7px 14px; background: #fff; border: 1.5px solid #e2e8f0;
  border-radius: 999px; font-size: 13px; font-weight: 500; color: #475569;
  cursor: pointer; transition: all .18s ease;
}
.wa-chip:hover { border-color: #1fb1a6; color: #0f8a82; }
.wa-chip.is-active { background: #1fb1a6; border-color: #1fb1a6; color: #fff; }

.wa-muted { color: #94a3b8; font-size: 12px; margin-left: 8px; }

.wa-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
.wa-kpi-card {
  display: flex; align-items: center; gap: 14px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
  padding: 16px 18px; box-shadow: 0 1px 2px rgba(15,23,42,.04);
}
.wa-kpi-card__icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 700;
}
.wa-kpi-card__label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.wa-kpi-card__value { font-size: 26px; font-weight: 700; color: #0f172a; }
.wa-kpi--blue  .wa-kpi-card__icon { background: #dbeafe; color: #1e40af; }
.wa-kpi--green .wa-kpi-card__icon { background: #d1fae5; color: #065f46; }
.wa-kpi--red   .wa-kpi-card__icon { background: #fee2e2; color: #991b1b; }
.wa-kpi--amber .wa-kpi-card__icon { background: #fef3c7; color: #92400e; }

.wa-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  padding: 18px 20px; margin-bottom: 18px;
}
.wa-card__header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 14px; }
.wa-card__header h3 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }

.wa-chart { position: relative; height: 300px; }
.wa-chart--small { height: 240px; }

.wa-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 900px) { .wa-grid-2 { grid-template-columns: 1fr; } }

.wa-table-clean { font-size: 13px; margin-bottom: 0; }
.wa-table-clean th { background: #f9fafb; font-weight: 600; color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
.wa-table-clean td, .wa-table-clean th { padding: 10px 12px; border-top: 1px solid #f1f5f9; }
.wa-num-green { color: #065f46; font-weight: 600; }
.wa-num-red { color: #991b1b; font-weight: 600; }

.wa-pill {
  display: inline-block; padding: 3px 10px; border-radius: 999px;
  background: #eef6f8; color: #0f8a82; font-size: 11px; font-weight: 600;
}

.wa-empty { padding: 24px; text-align: center; color: #94a3b8; }

.wa-error-list { display: flex; flex-direction: column; gap: 10px; max-height: 360px; overflow-y: auto; }
.wa-error-item {
  padding: 10px 12px; background: #fef2f2; border-left: 3px solid #ef4444;
  border-radius: 8px; font-size: 12px;
}
.wa-error-item__head { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.wa-error-item__phone { font-weight: 600; color: #0f172a; margin-bottom: 2px; }
.wa-error-item__msg { color: #991b1b; font-family: ui-monospace, monospace; font-size: 11px; }
</style>
