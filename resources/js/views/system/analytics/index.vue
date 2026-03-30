<template>
  <div class="container-fluid">
    <div class="page-header pr-0 mb-3">
      <h2><i class="fas fa-chart-line mr-2"></i>Analytics SaaS</h2>
      <ol class="breadcrumbs">
        <li class="active"><span>Data Warehouse</span></li>
      </ol>
    </div>

    <!-- KPIs globales -->
    <div class="row mb-4" v-if="kpis">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Total tenants</div>
            <div class="h3 mb-0 text-primary">{{ kpis.total_tenants }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Activos (30d)</div>
            <div class="h3 mb-0 text-success">{{ kpis.active_tenants }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Ventas netas 30d</div>
            <div class="h3 mb-0 text-warning">S/ {{ formatAmount(kpis.sales_30d) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Ventas netas 7d</div>
            <div class="h3 mb-0 text-info">S/ {{ formatAmount(kpis.sales_7d) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-4" v-if="kpis">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Con Ecommerce</div>
            <div class="h4 mb-0">{{ kpis.with_ecommerce }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Con Logística</div>
            <div class="h4 mb-0">{{ kpis.with_logistic }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Total items</div>
            <div class="h4 mb-0">{{ kpis.total_items.toLocaleString() }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body p-3">
            <div class="text-muted small">Total clientes</div>
            <div class="h4 mb-0">{{ kpis.total_customers.toLocaleString() }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Ventas diarias -->
      <div class="col-md-8 mb-4">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Ventas diarias (todos los tenants)</strong>
            <div class="d-flex gap-2">
              <el-date-picker v-model="dateRange" type="daterange"
                range-separator="–" start-placeholder="Inicio" end-placeholder="Fin"
                size="small" value-format="yyyy-MM-dd"
                @change="loadDailySales">
              </el-date-picker>
            </div>
          </div>
          <div class="card-body">
            <div v-if="loadingChart" class="text-center py-5">
              <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            </div>
            <canvas v-else ref="salesChart" height="100"></canvas>
          </div>
        </div>
      </div>

      <!-- Distribución por plan -->
      <div class="col-md-4 mb-4">
        <div class="card h-100">
          <div class="card-header"><strong>Tenants por plan</strong></div>
          <div class="card-body">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>Plan</th><th class="text-right">Tenants</th><th class="text-right">Ventas 30d</th></tr></thead>
              <tbody>
                <tr v-for="row in planDistribution" :key="row.plan">
                  <td>{{ row.plan }}</td>
                  <td class="text-right">{{ row.tenants }}</td>
                  <td class="text-right">S/ {{ formatAmount(row.sales_30d) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Top tenants -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Top tenants por ventas (30d)</strong>
            <el-button size="mini" @click="loadTopTenants" :loading="loadingTop">
              <i class="fas fa-sync-alt"></i>
            </el-button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>#</th>
                    <th>Hostname</th>
                    <th>Plan</th>
                    <th class="text-right">Ventas 30d</th>
                    <th class="text-right">Ventas 12m</th>
                    <th class="text-center">Items</th>
                    <th class="text-center">Clientes</th>
                    <th class="text-center">Módulos</th>
                    <th>Última venta</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in topTenants" :key="i">
                    <td>{{ i + 1 }}</td>
                    <td><strong>{{ row.tenant_hostname || '—' }}</strong></td>
                    <td><el-tag size="mini" type="info">{{ row.plan_name || '—' }}</el-tag></td>
                    <td class="text-right text-success font-weight-bold">S/ {{ formatAmount(row.sales_last_30d) }}</td>
                    <td class="text-right">S/ {{ formatAmount(row.sales_last_12m) }}</td>
                    <td class="text-center">{{ row.total_items }}</td>
                    <td class="text-center">{{ row.total_customers }}</td>
                    <td class="text-center">
                      <el-tag v-if="row.has_ecommerce"   size="mini" type="success" class="mr-1">EC</el-tag>
                      <el-tag v-if="row.has_logistic"    size="mini" type="warning" class="mr-1">LOG</el-tag>
                      <el-tag v-if="row.has_smart_stock" size="mini" type="danger">SS</el-tag>
                    </td>
                    <td class="text-muted small">{{ row.last_sale_at | formatDate }}</td>
                  </tr>
                  <tr v-if="!topTenants.length">
                    <td colspan="9" class="text-center text-muted py-3">Sin datos — ejecutar warehouse:sync-etl primero</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ETL Log -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header"><strong>Últimas corridas ETL</strong></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr>
                  <th>Inicio</th>
                  <th>Fin</th>
                  <th>Tipo</th>
                  <th>Tenant UUID</th>
                  <th class="text-center">Estado</th>
                  <th class="text-right">Insertados</th>
                  <th class="text-right">Actualizados</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in etlLog" :key="log.id">
                  <td class="small">{{ log.started_at }}</td>
                  <td class="small">{{ log.finished_at || '—' }}</td>
                  <td><el-tag size="mini">{{ log.job_type }}</el-tag></td>
                  <td class="text-muted small" style="font-size:0.75em">{{ log.tenant_uuid || 'global' }}</td>
                  <td class="text-center">
                    <el-tag size="mini" :type="statusType(log.status)">{{ log.status }}</el-tag>
                  </td>
                  <td class="text-right">{{ log.rows_inserted }}</td>
                  <td class="text-right">{{ log.rows_updated }}</td>
                  <td class="small text-danger">{{ log.error_message || '' }}</td>
                </tr>
                <tr v-if="!etlLog.length">
                  <td colspan="8" class="text-center text-muted py-3">Sin registros ETL</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      kpis: null,
      planDistribution: [],
      topTenants: [],
      etlLog: [],
      dateRange: [
        this.daysAgo(30),
        this.today(),
      ],
      loadingChart: false,
      loadingTop:   false,
      chart: null,
    };
  },

  async created() {
    await Promise.all([
      this.loadKpis(),
      this.loadDailySales(),
      this.loadTopTenants(),
      this.loadPlanDistribution(),
      this.loadEtlLog(),
    ]);
  },

  filters: {
    formatDate(val) {
      if (!val) return '—';
      return val.substring(0, 10);
    },
  },

  methods: {
    today() {
      return new Date().toISOString().substring(0, 10);
    },
    daysAgo(n) {
      const d = new Date();
      d.setDate(d.getDate() - n);
      return d.toISOString().substring(0, 10);
    },
    formatAmount(val) {
      return Number(val || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 });
    },
    statusType(status) {
      return { success: 'success', failed: 'danger', running: 'warning' }[status] || 'info';
    },

    async loadKpis() {
      const r = await this.$http.get('/system/analytics/global-kpis');
      this.kpis = r.data;
    },

    async loadDailySales() {
      this.loadingChart = true;
      const [from, to] = this.dateRange || [this.daysAgo(30), this.today()];
      const r = await this.$http.get('/system/analytics/daily-sales', { params: { from, to } });
      this.loadingChart = false;
      this.$nextTick(() => this.renderChart(r.data));
    },

    renderChart(data) {
      const ctx = this.$refs.salesChart;
      if (!ctx) return;

      if (this.chart) {
        this.chart.destroy();
      }

      /* global Chart */
      if (typeof Chart === 'undefined') return;

      this.chart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            {
              label: 'Ventas netas (S/)',
              data: data.sales,
              backgroundColor: 'rgba(52, 168, 83, 0.5)',
              borderColor: '#34A853',
              borderWidth: 1,
              yAxisID: 'y',
            },
            {
              label: 'Comprobantes',
              data: data.docs,
              type: 'line',
              borderColor: '#4285F4',
              backgroundColor: 'transparent',
              tension: 0.3,
              yAxisID: 'y1',
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y:  { position: 'left',  title: { display: true, text: 'S/' } },
            y1: { position: 'right', title: { display: true, text: 'Docs' }, grid: { drawOnChartArea: false } },
          },
        },
      });
    },

    async loadTopTenants() {
      this.loadingTop = true;
      const r = await this.$http.get('/system/analytics/top-tenants', { params: { limit: 15 } });
      this.topTenants = r.data.data;
      this.loadingTop = false;
    },

    async loadPlanDistribution() {
      const r = await this.$http.get('/system/analytics/plan-distribution');
      this.planDistribution = r.data.data;
    },

    async loadEtlLog() {
      const r = await this.$http.get('/system/analytics/etl-log');
      this.etlLog = r.data.data;
    },
  },
};
</script>
