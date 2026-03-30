<template>
  <div class="kpi-grid">

    <!-- 1° VENTAS TOTALES — mayor peso visual, siempre visible -->
    <div class="kpi-card" style="--kpi-accent:#10b981">
      <div class="kpi-icon kpi-green"><i class="fas fa-chart-line"></i></div>
      <el-tooltip :content="'S/ ' + String(total)" placement="top">
        <div class="kpi-value">{{ total | formatNumber }}</div>
      </el-tooltip>
      <div class="kpi-label">Ventas totales</div>
      <div class="kpi-sub">CPE + Notas de venta</div>
    </div>

    <!-- 2° UTILIDAD NETA — métrica de salud del negocio -->
    <div class="kpi-card" v-if="utilities && utilities.totals" style="--kpi-accent:#14b8a6">
      <div class="kpi-icon kpi-teal"><i class="fas fa-coins"></i></div>
      <el-tooltip :content="'S/ ' + String(utilities.totals.utility)" placement="top">
        <div class="kpi-value">{{ utilities.totals.utility | formatNumber }}</div>
      </el-tooltip>
      <div class="kpi-label">Utilidad neta</div>
      <div class="kpi-sub">Ingresos − Costos</div>
    </div>

    <!-- 3° CPE EMITIDOS — volumen de facturación electrónica -->
    <div class="kpi-card" style="--kpi-accent:#3b82f6">
      <div class="kpi-icon kpi-blue"><i class="fas fa-file-invoice"></i></div>
      <el-tooltip :content="String(total_cpe)" placement="top">
        <div class="kpi-value">{{ total_cpe | formatNumber(0, 0) }}</div>
      </el-tooltip>
      <div class="kpi-label">CPE Emitidos</div>
      <div class="kpi-sub">Comprobantes electrónicos</div>
    </div>

    <!-- 4° COMPROBANTES (S/) — monto de facturas/boletas -->
    <div class="kpi-card" style="--kpi-accent:#8b5cf6">
      <div class="kpi-icon kpi-purple"><i class="fas fa-receipt"></i></div>
      <el-tooltip :content="'S/ ' + String(document_total_global)" placement="top">
        <div class="kpi-value">{{ document_total_global | formatNumber }}</div>
      </el-tooltip>
      <div class="kpi-label">Comprobantes</div>
      <div class="kpi-sub">Facturas y boletas</div>
    </div>

    <!-- 5° NOTAS DE VENTA — canal alternativo de venta -->
    <div class="kpi-card" style="--kpi-accent:#f59e0b">
      <div class="kpi-icon kpi-amber"><i class="fas fa-clipboard-list"></i></div>
      <el-tooltip :content="'S/ ' + String(sale_note_total_global)" placement="top">
        <div class="kpi-value">{{ sale_note_total_global | formatNumber }}</div>
      </el-tooltip>
      <div class="kpi-label">Notas de venta</div>
      <div class="kpi-sub">Del periodo</div>
    </div>

    <!-- 6° CERTIFICADO DIGITAL — alerta operativa crítica -->
    <div class="kpi-card" v-if="company.certificate_due"
         :style="isDueWarning ? '--kpi-accent:#ef4444' : '--kpi-accent:#10b981'">
      <div class="kpi-icon" :class="isDueWarning ? 'kpi-red' : 'kpi-green'">
        <i class="fas fa-shield-alt"></i>
      </div>
      <div class="kpi-value" :class="isDueWarning ? 'text-danger' : ''">
        {{ company.certificate_due }}
      </div>
      <div class="kpi-label">Certificado digital</div>
      <div class="kpi-sub" :style="isDueWarning ? 'color:#ef4444;font-weight:600' : ''">
        {{ isDueWarning ? '⚠ Próximo a vencer' : 'Vigente' }}
      </div>
    </div>

  </div>
</template>

<script>
import moment from "moment";

export default {
  props: ["company", 'utilities'],
  data() {
    return {
      document_total_global: 0,
      total_cpe: 0,
      sale_note_total_global: 0,
      total: 0,
    };
  },
  mounted() {
    this.onFetchData();
  },
  computed: {
    isDueWarning() {
      if (this.company.certificate_due) {
        const dueDate = moment(this.company.certificate_due);

        const now = moment();
        const diffInDays = dueDate.diff(now, 'days')
        return diffInDays <= 15;
      }
      return false;
    },
  },
  methods: {
    onFetchData() {
      this.$http.get("/dashboard/global-data").then((response) => {
        const data = response.data;
        this.document_total_global = Number(data.document_total_global) || 0;
        this.total_cpe = Number(data.total_cpe) || 0;
        this.sale_note_total_global = Number(data.sale_note_total_global) || 0;
        this.total = this.document_total_global + this.sale_note_total_global;
      });
    },
  },
  filters: {
    formatNumber(value, baseDecimals = 2, suffixDecimals = 1) {
      const numericValue = Number(value);
      const defaultString = (0).toLocaleString("en-US", {
        minimumFractionDigits: baseDecimals,
        maximumFractionDigits: baseDecimals,
      });

      if (!Number.isFinite(numericValue)) {
        return defaultString;
      }

      if (Math.abs(numericValue) >= 1000000) {
        const millions = (numericValue / 1000000)
          .toFixed(suffixDecimals)
          .replace(/\.0+$/, "");
        return `${millions}M`;
      }

      if (Math.abs(numericValue) >= 1000) {
        const thousands = (numericValue / 1000)
          .toFixed(suffixDecimals)
          .replace(/\.0+$/, "");
        return `${thousands}K`;
      }

      return numericValue.toLocaleString("en-US", {
        minimumFractionDigits: baseDecimals,
        maximumFractionDigits: baseDecimals,
      });
    },
  },
};
</script>
<style>
.card-green {
  background-color: green;
  color: white;
}
.is-due-warning {
  background-color: red;
}
.card-green .card-title {
  color: white;
}
.row.top .card.card-dashboard i.fas {
    position: absolute;
    right: 10px;
    opacity: 0.075;
    overflow: hidden;
    z-index: 0;
    font-size: 24px;
    top: 10px;
}
</style>
