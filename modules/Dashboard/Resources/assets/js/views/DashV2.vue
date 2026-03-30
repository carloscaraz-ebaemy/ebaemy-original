<template>
  <div class="dv2">

    <!-- ═══════════════════════════════════════════════════════
         HEADER + FILTROS
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-header">
      <div class="dv2-header__left">
        <h2 class="dv2-title">Panel Operativo</h2>
        <span class="dv2-period-label">{{ periodLabel }}</span>
      </div>
      <div class="dv2-header__right">
        <el-select v-model="filters.establishment_id" size="small"
                   placeholder="Todas las sucursales" clearable
                   @change="onFilterChange" style="width:190px">
          <el-option v-for="e in establishments" :key="e.id"
                     :value="e.id" :label="e.name"/>
        </el-select>

        <el-date-picker v-model="filters.dateRange" type="daterange"
                        size="small" range-separator="–"
                        start-placeholder="Desde" end-placeholder="Hasta"
                        value-format="yyyy-MM-dd" format="dd/MM/yyyy"
                        :clearable="false" @change="onFilterChange"
                        style="width:230px"/>

        <el-button size="small" icon="el-icon-refresh"
                   :loading="anyLoading" @click="loadAll">
          Actualizar
        </el-button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         ALERTAS INTELIGENTES
    ════════════════════════════════════════════════════════ -->
    <div v-if="loading.alerts" class="dv2-alerts-skeleton">
      <div class="dv2-skeleton" style="height:38px;border-radius:10px;width:100%"></div>
    </div>
    <transition-group v-else-if="alerts.length"
                      name="dv2-fade" tag="div" class="dv2-alerts">
      <div v-for="a in alerts" :key="a.title"
           :class="['dv2-alert', 'dv2-alert--' + a.type]">
        <svg v-if="a.icon === 'trending-down'" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
          <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
          <polyline points="17 18 23 18 23 12"/>
        </svg>
        <svg v-else-if="a.icon === 'trending-up'" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
          <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
          <polyline points="17 6 23 6 23 12"/>
        </svg>
        <svg v-else-if="a.icon === 'package'" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
          <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/>
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div><strong>{{ a.title }}:</strong> {{ a.message }}</div>
      </div>
    </transition-group>

    <!-- ═══════════════════════════════════════════════════════
         KPIs
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-kpis">

      <div class="dv2-kpi dv2-kpi--green">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">
            <span v-if="loading.summary" class="dv2-skeleton dv2-skeleton--val"></span>
            <span v-else>S/ {{ kpis.sales_today.amount | fmt }}</span>
          </div>
          <div class="dv2-kpi__label">Ventas hoy</div>
          <div class="dv2-kpi__sub">{{ kpis.sales_today.count }} transacciones</div>
        </div>
      </div>

      <div class="dv2-kpi dv2-kpi--blue">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
            <polyline points="17 6 23 6 23 12"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">
            <span v-if="loading.summary" class="dv2-skeleton dv2-skeleton--val"></span>
            <span v-else>S/ {{ kpis.sales_month.amount | fmt }}</span>
          </div>
          <div class="dv2-kpi__label">Ventas del período</div>
          <div class="dv2-kpi__sub">{{ kpis.sales_month.count }} ventas · ticket S/ {{ kpis.avg_ticket | fmt }}</div>
        </div>
      </div>

      <div class="dv2-kpi dv2-kpi--purple">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"/>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">
            <span v-if="loading.summary" class="dv2-skeleton dv2-skeleton--val"></span>
            <span v-else :class="kpis.utility_month >= 0 ? 'kpi-positive' : 'kpi-negative'">
              S/ {{ kpis.utility_month | fmt }}
            </span>
          </div>
          <div class="dv2-kpi__label">Utilidad estimada</div>
          <div class="dv2-kpi__sub">Precio venta − costo unitario</div>
        </div>
      </div>

      <div class="dv2-kpi dv2-kpi--orange">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">
            <span v-if="loading.summary" class="dv2-skeleton dv2-skeleton--val"></span>
            <span v-else>S/ {{ kpis.purchases_month.amount | fmt }}</span>
          </div>
          <div class="dv2-kpi__label">Compras del mes</div>
          <div class="dv2-kpi__sub">{{ kpis.purchases_month.count }} órdenes</div>
        </div>
      </div>

      <div class="dv2-kpi dv2-kpi--teal">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">
            <span v-if="loading.summary" class="dv2-skeleton dv2-skeleton--val"></span>
            <span v-else>S/ {{ kpis.sales_year.amount | fmt }}</span>
          </div>
          <div class="dv2-kpi__label">Ventas del año</div>
          <div class="dv2-kpi__sub">{{ kpis.sales_year.count }} ventas acumuladas</div>
        </div>
      </div>

      <!-- KPI: cuentas por cobrar -->
      <div v-if="!loading.receivables && receivables.total_pending > 0" class="dv2-kpi dv2-kpi--amber">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value kpi-negative">S/ {{ receivables.total_pending | fmt }}</div>
          <div class="dv2-kpi__label">Por cobrar</div>
          <div class="dv2-kpi__sub">{{ receivables.customers.length }} clientes con saldo</div>
        </div>
      </div>

      <!-- KPI: tasa de conversión -->
      <div v-if="!loading.quotations" class="dv2-kpi dv2-kpi--indigo">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
            <polyline points="16 7 22 7 22 13"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value">{{ quotations.conversion_rate }}%</div>
          <div class="dv2-kpi__label">Conversión cotiz.</div>
          <div class="dv2-kpi__sub">{{ quotations.converted_count }}/{{ quotations.total_quotations }} cotizaciones</div>
        </div>
      </div>

      <!-- KPI: stock crítico -->
      <div v-if="!loading.stock && stockAlerts.length" class="dv2-kpi dv2-kpi--red">
        <div class="dv2-kpi__icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div class="dv2-kpi__body">
          <div class="dv2-kpi__value kpi-negative">{{ stockAlerts.length }}</div>
          <div class="dv2-kpi__label">Stock crítico</div>
          <div class="dv2-kpi__sub">productos necesitan reposición</div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════
         EVOLUCIÓN DE VENTAS
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-card dv2-card--chart">
      <div class="dv2-card__head">
        <div>
          <span class="dv2-card__title">Evolución de ventas</span>
          <span class="dv2-card__sub" style="margin-left:8px">
            {{ chartMode === 'daily' ? 'Últimos 30 días' : 'Últimos 12 meses' }}
          </span>
        </div>
        <div class="dv2-btn-group">
          <button :class="['dv2-btn-tab', chartMode === 'daily' && 'active']"
                  @click="chartMode = 'daily'">30 días</button>
          <button :class="['dv2-btn-tab', chartMode === 'monthly' && 'active']"
                  @click="chartMode = 'monthly'">12 meses</button>
        </div>
      </div>
      <div class="dv2-chart-wrap">
        <div v-if="loading.charts" class="dv2-chart-loader"><div class="dv2-spin"></div></div>
        <canvas v-show="!loading.charts" ref="salesCanvas" style="max-height:240px"></canvas>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         COMPARATIVO DE PERÍODOS
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-card dv2-card--chart">
      <div class="dv2-card__head">
        <span class="dv2-card__title">Comparativo de períodos</span>
        <div class="dv2-btn-group">
          <button :class="['dv2-btn-tab', periodView === 'months' && 'active']"
                  @click="periodView = 'months'">Mes actual vs anterior</button>
          <button :class="['dv2-btn-tab', periodView === 'years' && 'active']"
                  @click="periodView = 'years'">Año actual vs anterior</button>
        </div>
      </div>
      <div v-if="loading.periodComparison" class="dv2-chart-loader" style="height:120px">
        <div class="dv2-spin"></div>
      </div>
      <div v-else class="dv2-period-grid">
        <template v-if="periodView === 'months'">
          <div class="dv2-period-col">
            <div class="dv2-period-label2">{{ periodComparison.months.current.label }}</div>
            <div class="dv2-period-val">S/ {{ periodComparison.months.current.total | fmt }}</div>
            <div class="dv2-period-sub">{{ periodComparison.months.current.count }} ventas</div>
          </div>
          <div class="dv2-period-vs">
            <span :class="['dv2-pct', periodComparison.months.change_pct >= 0 ? 'dv2-pct--up' : 'dv2-pct--down']"
                  v-if="periodComparison.months.change_pct !== null">
              {{ periodComparison.months.change_pct >= 0 ? '+' : '' }}{{ periodComparison.months.change_pct }}%
            </span>
            <span class="dv2-vs-label">vs</span>
          </div>
          <div class="dv2-period-col dv2-period-col--prev">
            <div class="dv2-period-label2">{{ periodComparison.months.previous.label }}</div>
            <div class="dv2-period-val">S/ {{ periodComparison.months.previous.total | fmt }}</div>
            <div class="dv2-period-sub">{{ periodComparison.months.previous.count }} ventas</div>
          </div>
        </template>
        <template v-else>
          <div class="dv2-period-col">
            <div class="dv2-period-label2">Año {{ periodComparison.years.current.label }}</div>
            <div class="dv2-period-val">S/ {{ periodComparison.years.current.total | fmt }}</div>
            <div class="dv2-period-sub">{{ periodComparison.years.current.count }} ventas</div>
          </div>
          <div class="dv2-period-vs">
            <span :class="['dv2-pct', periodComparison.years.change_pct >= 0 ? 'dv2-pct--up' : 'dv2-pct--down']"
                  v-if="periodComparison.years.change_pct !== null">
              {{ periodComparison.years.change_pct >= 0 ? '+' : '' }}{{ periodComparison.years.change_pct }}%
            </span>
            <span class="dv2-vs-label">vs</span>
          </div>
          <div class="dv2-period-col dv2-period-col--prev">
            <div class="dv2-period-label2">Año {{ periodComparison.years.previous.label }}</div>
            <div class="dv2-period-val">S/ {{ periodComparison.years.previous.total | fmt }}</div>
            <div class="dv2-period-sub">{{ periodComparison.years.previous.count }} ventas</div>
          </div>
        </template>
      </div>
      <div class="dv2-chart-wrap" style="margin-top:12px">
        <canvas v-show="!loading.periodComparison" ref="periodCanvas" style="max-height:160px"></canvas>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         VENDEDORES  |  TOP PRODUCTOS
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-row-two">

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Ranking vendedores</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.sellers" class="dv2-table-skeleton">
          <div v-for="n in 5" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="apiErrors.sellers" class="dv2-error">
          Error al cargar vendedores
          <button class="dv2-retry" @click="loadSellers">Reintentar</button>
        </div>
        <table v-else class="dv2-table">
          <thead>
            <tr>
              <th style="width:32px">#</th>
              <th>Vendedor</th>
              <th class="text-right">Total</th>
              <th class="text-right">NV</th>
              <th class="text-right">Ticket</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!sellers.length">
              <td colspan="5" class="dv2-empty">Sin ventas en el período seleccionado</td>
            </tr>
            <tr v-for="(s, i) in sellers" :key="s.id">
              <td><span class="dv2-rank" :class="rankClass(i)">{{ i + 1 }}</span></td>
              <td>{{ s.name }}</td>
              <td class="text-right fw-600">S/ {{ s.total | fmt }}</td>
              <td class="text-right">{{ s.count }}</td>
              <td class="text-right text-muted">S/ {{ s.avg_ticket | fmt }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Productos más vendidos</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.products" class="dv2-table-skeleton">
          <div v-for="n in 5" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="apiErrors.products" class="dv2-error">
          Error al cargar productos
          <button class="dv2-retry" @click="loadProducts">Reintentar</button>
        </div>
        <table v-else class="dv2-table">
          <thead>
            <tr>
              <th style="width:32px">#</th>
              <th>Producto</th>
              <th class="text-right">Uds.</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!topProducts.length">
              <td colspan="4" class="dv2-empty">Sin ventas en el período</td>
            </tr>
            <tr v-for="(p, i) in topProducts" :key="p.item_id">
              <td><span class="dv2-rank dv2-rank--neutral">{{ i + 1 }}</span></td>
              <td class="dv2-cell-trunc" :title="p.name">{{ p.name }}</td>
              <td class="text-right fw-600">{{ p.qty | fmtQty }}</td>
              <td class="text-right text-muted">S/ {{ p.total | fmt }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         MÉTODOS DE PAGO  |  VENTAS POR HORA
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-row-two">

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Métodos de pago</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.paymentMethods" class="dv2-chart-loader" style="height:180px">
          <div class="dv2-spin"></div>
        </div>
        <div v-else-if="!paymentMethods.length" class="dv2-empty" style="padding:32px 0">Sin pagos en el período</div>
        <div v-else class="dv2-donut-wrap">
          <canvas ref="payCanvas" style="max-height:180px;max-width:180px;margin:0 auto"></canvas>
          <div class="dv2-legend">
            <div v-for="(m, i) in paymentMethods" :key="m.id" class="dv2-legend-item">
              <span class="dv2-legend-dot" :style="{background: payColors[i % payColors.length]}"></span>
              <span class="dv2-legend-label">{{ m.label }}</span>
              <span class="dv2-legend-val">S/ {{ m.total | fmt }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="dv2-card dv2-card--chart">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Ventas por hora del día</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.salesByHour" class="dv2-chart-loader" style="height:180px">
          <div class="dv2-spin"></div>
        </div>
        <div class="dv2-chart-wrap" style="height:180px">
          <canvas v-show="!loading.salesByHour" ref="hourCanvas" style="max-height:180px"></canvas>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         RENTABILIDAD POR PRODUCTO
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-card dv2-card--chart">
      <div class="dv2-card__head">
        <span class="dv2-card__title">Rentabilidad por producto</span>
        <span class="dv2-card__sub">Top 10 productos — {{ periodLabel }}</span>
      </div>
      <div v-if="loading.profitability" class="dv2-chart-loader" style="height:220px">
        <div class="dv2-spin"></div>
      </div>
      <div v-else-if="!profitability.length" class="dv2-empty" style="padding:40px 0">Sin datos en el período</div>
      <div v-else>
        <div class="dv2-chart-wrap" style="height:220px">
          <canvas ref="profitCanvas" style="max-height:220px"></canvas>
        </div>
        <table class="dv2-table dv2-table--sm" style="margin-top:8px">
          <thead>
            <tr>
              <th>Producto</th>
              <th class="text-right">Ingresos</th>
              <th class="text-right">Costo</th>
              <th class="text-right">Utilidad</th>
              <th class="text-right">Margen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in profitability" :key="p.item_id">
              <td class="dv2-cell-trunc" :title="p.name">{{ p.name }}</td>
              <td class="text-right">S/ {{ p.revenue | fmt }}</td>
              <td class="text-right text-muted">S/ {{ p.cost | fmt }}</td>
              <td class="text-right fw-600"
                  :class="p.profit >= 0 ? 'kpi-positive' : 'kpi-negative'">
                S/ {{ p.profit | fmt }}
              </td>
              <td class="text-right">
                <span :class="['dv2-chip', p.margin >= 20 ? 'dv2-chip--green' : p.margin >= 10 ? 'dv2-chip--amber' : 'dv2-chip--red']">
                  {{ p.margin }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         CLIENTES  |  CUENTAS POR COBRAR
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-row-two">

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Clientes</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.customers" class="dv2-table-skeleton">
          <div v-for="n in 4" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else>
          <!-- Mini stats -->
          <div class="dv2-cust-stats">
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">{{ customerData.period_count }}</div>
              <div class="dv2-cust-stat__lbl">Clientes activos</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val dv2-cust-stat__val--green">{{ customerData.new_count }}</div>
              <div class="dv2-cust-stat__lbl">Nuevos</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val dv2-cust-stat__val--blue">{{ customerData.returning_count }}</div>
              <div class="dv2-cust-stat__lbl">Recurrentes</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">S/ {{ customerData.avg_ticket | fmt }}</div>
              <div class="dv2-cust-stat__lbl">Ticket prom.</div>
            </div>
          </div>
          <!-- Top clientes -->
          <div class="dv2-card__sub" style="margin:10px 0 6px;font-weight:600;color:#64748b">Top clientes</div>
          <div v-for="(c, i) in customerData.top.slice(0,5)" :key="c.id" class="dv2-sup-row">
            <span class="dv2-rank dv2-rank--neutral" style="width:20px;font-size:10px">{{ i+1 }}</span>
            <span class="dv2-sup-name" :title="c.name">{{ c.name }}</span>
            <div class="dv2-sup-bar-wrap">
              <div class="dv2-sup-bar dv2-sup-bar--blue"
                   :style="{width: custPct(c.total) + '%'}"></div>
            </div>
            <span class="dv2-sup-amount">S/ {{ c.total | fmt }}</span>
          </div>
        </div>
      </div>

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Cuentas por cobrar</span>
          <span v-if="!loading.receivables && receivables.total_pending > 0"
                class="dv2-badge dv2-badge--red">S/ {{ receivables.total_pending | fmt }}</span>
        </div>
        <div v-if="loading.receivables" class="dv2-table-skeleton">
          <div v-for="n in 5" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="!receivables.customers.length" class="dv2-empty" style="padding:32px 0">
          ✓ Sin saldos pendientes en el período
        </div>
        <div v-else>
          <div class="dv2-recv-summary">
            <div>
              <span class="text-muted" style="font-size:11px">FACTURADO</span>
              <div class="fw-600">S/ {{ receivables.total_billed | fmt }}</div>
            </div>
            <div>
              <span class="text-muted" style="font-size:11px">COBRADO</span>
              <div class="fw-600 kpi-positive">S/ {{ receivables.total_paid | fmt }}</div>
            </div>
            <div>
              <span class="text-muted" style="font-size:11px">PENDIENTE</span>
              <div class="fw-600 kpi-negative">S/ {{ receivables.total_pending | fmt }}</div>
            </div>
          </div>
          <table class="dv2-table dv2-table--sm">
            <thead>
              <tr>
                <th>Cliente</th>
                <th class="text-right">Facturado</th>
                <th class="text-right">Pendiente</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in receivables.customers" :key="c.id">
                <td class="dv2-cell-trunc" :title="c.name">{{ c.name }}</td>
                <td class="text-right text-muted">S/ {{ c.billed | fmt }}</td>
                <td class="text-right fw-600 kpi-negative">S/ {{ c.pending | fmt }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         VENTAS POR CIUDAD / DEPARTAMENTO
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-card dv2-card--chart">
      <div class="dv2-card__head">
        <span class="dv2-card__title">Ventas por ciudad / departamento</span>
        <span class="dv2-card__sub">{{ periodLabel }}</span>
      </div>
      <div v-if="loading.salesByCity" class="dv2-chart-loader" style="height:200px">
        <div class="dv2-spin"></div>
      </div>
      <div v-else-if="!salesByCity.length" class="dv2-empty" style="padding:40px 0">
        Sin datos de ubicación (los clientes no tienen departamento asignado)
      </div>
      <div v-else>
        <div class="dv2-chart-wrap" style="height:200px">
          <canvas ref="cityCanvas" style="max-height:200px"></canvas>
        </div>
        <div class="dv2-city-list">
          <div v-for="(c, i) in salesByCity" :key="c.department_id" class="dv2-city-row">
            <span class="dv2-rank dv2-rank--neutral" style="width:20px;font-size:10px">{{ i+1 }}</span>
            <span class="dv2-city-name">{{ c.city }}</span>
            <div class="dv2-sup-bar-wrap">
              <div class="dv2-sup-bar dv2-sup-bar--teal"
                   :style="{width: cityPct(c.total) + '%'}"></div>
            </div>
            <span class="dv2-city-cnt text-muted">{{ c.count }} ventas</span>
            <span class="dv2-city-val">S/ {{ c.total | fmt }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         COMPRAS  |  STOCK CRÍTICO
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-row-two">

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Compras del mes</span>
          <span class="dv2-badge dv2-badge--blue">
            S/ {{ purchases.month_total | fmt }}
            <small style="font-weight:500;opacity:.8"> · {{ purchases.month_count }} órdenes</small>
          </span>
        </div>
        <div v-if="loading.purchases" class="dv2-table-skeleton">
          <div v-for="n in 4" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="apiErrors.purchases" class="dv2-error">
          Error al cargar compras
          <button class="dv2-retry" @click="loadPurchases">Reintentar</button>
        </div>
        <div v-else>
          <div class="dv2-card__sub" style="margin-bottom:6px;font-weight:600;color:#64748b">Top proveedores</div>
          <div v-for="s in purchases.top_suppliers" :key="s.id" class="dv2-sup-row">
            <span class="dv2-sup-name" :title="s.name">{{ s.name }}</span>
            <div class="dv2-sup-bar-wrap">
              <div class="dv2-sup-bar" :style="{width: supplierPct(s.total) + '%'}"></div>
            </div>
            <span class="dv2-sup-amount">S/ {{ s.total | fmt }}</span>
          </div>
          <div class="dv2-card__sub" style="margin:12px 0 6px;font-weight:600;color:#64748b">Últimas compras</div>
          <table class="dv2-table dv2-table--sm">
            <tbody>
              <tr v-for="r in purchases.recent" :key="r.id">
                <td class="dv2-cell-trunc">{{ r.supplier }}</td>
                <td class="text-muted" style="white-space:nowrap">{{ r.date_of_issue }}</td>
                <td class="text-right fw-600">S/ {{ r.total | fmt }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Stock crítico</span>
          <span v-if="!loading.stock && stockAlerts.length"
                class="dv2-badge dv2-badge--red">{{ stockAlerts.length }} productos</span>
        </div>
        <div v-if="loading.stock" class="dv2-table-skeleton">
          <div v-for="n in 5" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="apiErrors.stock" class="dv2-error">
          Error al cargar stock
          <button class="dv2-retry" @click="loadStock">Reintentar</button>
        </div>
        <table v-else class="dv2-table dv2-table--sm">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Almacén</th>
              <th class="text-right">Disponible</th>
              <th class="text-center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!stockAlerts.length">
              <td colspan="4" class="dv2-empty dv2-empty--ok">✓ Stock suficiente en todos los productos</td>
            </tr>
            <tr v-for="s in stockAlerts" :key="s.item_id + '_' + s.warehouse">
              <td>
                <span v-if="s.internal_id" class="dv2-code">{{ s.internal_id }}</span>
                <span class="dv2-cell-trunc" :title="s.name">{{ s.name }}</span>
              </td>
              <td class="text-muted" style="white-space:nowrap">{{ s.warehouse }}</td>
              <td class="text-right fw-600"
                  :class="s.status === 'out' ? 'kpi-negative' : 'text-warning'">
                {{ s.stock_available }}
              </td>
              <td class="text-center">
                <span :class="['dv2-chip', s.status === 'out' ? 'dv2-chip--red' : 'dv2-chip--amber']">
                  {{ s.status === 'out' ? 'Agotado' : 'Stock bajo' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!loading.stock && stockAlerts.length" class="dv2-card__footer">
          <a href="/purchases/create" class="dv2-btn-link">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Crear orden de compra
          </a>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         CONVERSIÓN COTIZACIONES  |  INVENTARIO AVANZADO
    ════════════════════════════════════════════════════════ -->
    <div class="dv2-row-two">

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Conversión de cotizaciones</span>
          <span class="dv2-card__sub">{{ periodLabel }}</span>
        </div>
        <div v-if="loading.quotations" class="dv2-table-skeleton">
          <div v-for="n in 3" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else-if="!quotations.total_quotations" class="dv2-empty" style="padding:24px 0">Sin cotizaciones en el período</div>
        <div v-else>
          <!-- Gauge visual -->
          <div class="dv2-conv-gauge">
            <div class="dv2-conv-rate" :class="quotations.conversion_rate >= 50 ? 'kpi-positive' : 'kpi-negative'">
              {{ quotations.conversion_rate }}%
            </div>
            <div class="dv2-conv-bar-bg">
              <div class="dv2-conv-bar-fill"
                   :style="{width: quotations.conversion_rate + '%',
                            background: quotations.conversion_rate >= 50 ? '#10b981' : '#f59e0b'}">
              </div>
            </div>
            <div class="dv2-conv-label">tasa de conversión</div>
          </div>
          <div class="dv2-cust-stats" style="margin-top:12px">
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">{{ quotations.total_quotations }}</div>
              <div class="dv2-cust-stat__lbl">Cotizaciones</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val dv2-cust-stat__val--green">{{ quotations.converted_count }}</div>
              <div class="dv2-cust-stat__lbl">Convertidas</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">{{ quotations.total_quotations - quotations.converted_count }}</div>
              <div class="dv2-cust-stat__lbl">Sin convertir</div>
            </div>
          </div>
          <div class="dv2-recv-summary" style="margin-top:10px">
            <div>
              <span class="text-muted" style="font-size:11px">MONTO COTIZADO</span>
              <div class="fw-600">S/ {{ quotations.total_amount | fmt }}</div>
            </div>
            <div>
              <span class="text-muted" style="font-size:11px">MONTO CONVERTIDO</span>
              <div class="fw-600 kpi-positive">S/ {{ quotations.converted_amount | fmt }}</div>
            </div>
            <div>
              <span class="text-muted" style="font-size:11px">MONTO PERDIDO</span>
              <div class="fw-600 kpi-negative">S/ {{ quotations.lost_amount | fmt }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="dv2-card">
        <div class="dv2-card__head">
          <span class="dv2-card__title">Inventario avanzado</span>
        </div>
        <div v-if="loading.inventory" class="dv2-table-skeleton">
          <div v-for="n in 4" :key="n" class="dv2-skeleton dv2-skeleton--row"></div>
        </div>
        <div v-else>
          <div class="dv2-cust-stats">
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">S/ {{ inventory.total_value | fmt }}</div>
              <div class="dv2-cust-stat__lbl">Valor del stock</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val">{{ inventory.product_count }}</div>
              <div class="dv2-cust-stat__lbl">Productos activos</div>
            </div>
            <div class="dv2-cust-stat">
              <div class="dv2-cust-stat__val" :class="inventory.no_movement_30d > 0 ? 'kpi-negative' : ''">
                {{ inventory.no_movement_30d }}
              </div>
              <div class="dv2-cust-stat__lbl">Sin mov. 30 días</div>
            </div>
          </div>
          <div class="dv2-card__sub" style="margin:12px 0 6px;font-weight:600;color:#64748b">Productos de mayor valor</div>
          <table class="dv2-table dv2-table--sm">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="text-right">Stock</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Valor</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in inventory.top_by_value" :key="p.id">
                <td class="dv2-cell-trunc" :title="p.name">{{ p.name }}</td>
                <td class="text-right">{{ p.stock | fmtQty }}</td>
                <td class="text-right text-muted">S/ {{ p.price | fmt }}</td>
                <td class="text-right fw-600">S/ {{ p.value | fmt }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import Chart from 'chart.js';
import moment from 'moment';

function debounce(fn, ms) {
  let t;
  return function (...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), ms);
  };
}

const PAY_COLORS = [
  '#3b82f6','#10b981','#f59e0b','#8b5cf6',
  '#ef4444','#14b8a6','#f97316','#6366f1',
];

export default {
  name: 'DashV2',

  props: {
    establishments: { type: Array, default: () => [] },
  },

  data() {
    return {
      chartMode: 'daily',
      periodView: 'months',
      _chart:  null,
      _charts: {},

      payColors: PAY_COLORS,

      filters: {
        establishment_id: null,
        dateRange: [
          moment().startOf('month').format('YYYY-MM-DD'),
          moment().endOf('month').format('YYYY-MM-DD'),
        ],
      },

      loading: {
        summary: true, charts: true,
        sellers: true, products: true,
        stock: true, purchases: true, alerts: true,
        receivables: true, customers: true, paymentMethods: true,
        profitability: true, periodComparison: true, inventory: true,
        salesByHour: true, quotations: true, salesByCity: true,
      },

      apiErrors: {
        summary: false, sellers: false, products: false,
        stock: false, purchases: false, alerts: false,
      },

      kpis: {
        sales_today:     { amount: 0, count: 0 },
        sales_month:     { amount: 0, count: 0 },
        sales_year:      { amount: 0, count: 0 },
        avg_ticket:      0,
        utility_month:   0,
        purchases_month: { amount: 0, count: 0 },
      },

      dailyChart:   { labels: [], totals: [], counts: [] },
      monthlyChart: { labels: [], totals: [] },

      sellers:     [],
      topProducts: [],
      stockAlerts: [],
      purchases:   { month_total: 0, month_count: 0, recent: [], top_suppliers: [] },
      alerts:      [],

      // Nuevos estados
      receivables: { total_pending: 0, total_billed: 0, total_paid: 0, customers: [] },
      customerData: { period_count: 0, new_count: 0, returning_count: 0, avg_ticket: 0, top: [] },
      paymentMethods: [],
      profitability:  [],
      periodComparison: {
        months: { current: { total: 0, count: 0, label: '' }, previous: { total: 0, count: 0, label: '' }, change_pct: null },
        years:  { current: { total: 0, count: 0, label: '' }, previous: { total: 0, count: 0, label: '' }, change_pct: null },
      },
      inventory: { total_value: 0, total_units: 0, product_count: 0, no_movement_30d: 0, top_by_value: [] },
      salesByHour: { labels: [], totals: [], counts: [] },
      quotations: { total_quotations: 0, total_amount: 0, converted_count: 0, converted_amount: 0, lost_amount: 0, conversion_rate: 0 },
      salesByCity: [],
    };
  },

  computed: {
    periodLabel() {
      if (!this.filters.dateRange || !this.filters.dateRange[0]) return 'Mes actual';
      const s = moment(this.filters.dateRange[0]).format('DD/MM/YY');
      const e = moment(this.filters.dateRange[1]).format('DD/MM/YY');
      return s === e ? s : `${s} – ${e}`;
    },
    anyLoading() {
      return Object.values(this.loading).some(Boolean);
    },
    maxSupplier() {
      const s = this.purchases.top_suppliers;
      if (!s || !s.length) return 1;
      return s.reduce((m, r) => Math.max(m, parseFloat(r.total) || 0), 0) || 1;
    },
    maxCustomer() {
      const t = this.customerData.top;
      if (!t || !t.length) return 1;
      return t.reduce((m, r) => Math.max(m, parseFloat(r.total) || 0), 0) || 1;
    },
    maxCity() {
      if (!this.salesByCity.length) return 1;
      return this.salesByCity.reduce((m, r) => Math.max(m, parseFloat(r.total) || 0), 0) || 1;
    },
  },

  watch: {
    chartMode() { this.$nextTick(() => this.renderSalesChart()); },
    periodView() { this.$nextTick(() => this.renderPeriodChart()); },
  },

  mounted() {
    this.loadAll();
  },

  beforeDestroy() {
    if (this._chart) { this._chart.destroy(); this._chart = null; }
    Object.values(this._charts).forEach(c => c && c.destroy());
    this._charts = {};
  },

  methods: {
    onFilterChange: debounce(function () { this.loadAll(); }, 300),

    params() {
      return {
        establishment_id: this.filters.establishment_id || '',
        date_start: this.filters.dateRange ? this.filters.dateRange[0] : '',
        date_end:   this.filters.dateRange ? this.filters.dateRange[1] : '',
      };
    },

    loadAll() {
      this.loadSummary();
      this.loadCharts();
      this.loadSellers();
      this.loadProducts();
      this.loadStock();
      this.loadPurchases();
      this.loadAlerts();
      this.loadReceivables();
      this.loadCustomers();
      this.loadPaymentMethods();
      this.loadProfitability();
      this.loadPeriodComparison();
      this.loadInventory();
      this.loadSalesByHour();
      this.loadQuotations();
      this.loadSalesByCity();
    },

    loadSummary() {
      this.loading.summary = true;
      this.apiErrors.summary = false;
      this.$http.get('/dashboard/v2/summary', { params: this.params() })
        .then(({ data }) => { this.kpis = { ...this.kpis, ...data.kpis }; })
        .catch(() => { this.apiErrors.summary = true; })
        .finally(() => { this.loading.summary = false; });
    },

    loadCharts() {
      this.loading.charts = true;
      const p = { establishment_id: this.filters.establishment_id || '' };
      Promise.all([
        this.$http.get('/dashboard/v2/daily-chart',   { params: p }),
        this.$http.get('/dashboard/v2/monthly-chart', { params: p }),
      ]).then(([daily, monthly]) => {
        this.dailyChart   = daily.data;
        this.monthlyChart = monthly.data;
        this.$nextTick(() => this.renderSalesChart());
      }).catch(() => {}).finally(() => { this.loading.charts = false; });
    },

    loadSellers() {
      this.loading.sellers = true;
      this.apiErrors.sellers = false;
      this.$http.get('/dashboard/v2/sellers', { params: this.params() })
        .then(({ data }) => { this.sellers = Array.isArray(data) ? data : []; })
        .catch(() => { this.apiErrors.sellers = true; })
        .finally(() => { this.loading.sellers = false; });
    },

    loadProducts() {
      this.loading.products = true;
      this.apiErrors.products = false;
      this.$http.get('/dashboard/v2/top-products', { params: this.params() })
        .then(({ data }) => { this.topProducts = Array.isArray(data) ? data : []; })
        .catch(() => { this.apiErrors.products = true; })
        .finally(() => { this.loading.products = false; });
    },

    loadStock() {
      this.loading.stock = true;
      this.apiErrors.stock = false;
      this.$http.get('/dashboard/v2/stock-alerts', {
        params: { establishment_id: this.filters.establishment_id || '' },
      }).then(({ data }) => { this.stockAlerts = Array.isArray(data) ? data : []; })
        .catch(() => { this.apiErrors.stock = true; })
        .finally(() => { this.loading.stock = false; });
    },

    loadPurchases() {
      this.loading.purchases = true;
      this.apiErrors.purchases = false;
      this.$http.get('/dashboard/v2/purchases', {
        params: { establishment_id: this.filters.establishment_id || '' },
      }).then(({ data }) => { this.purchases = data || this.purchases; })
        .catch(() => { this.apiErrors.purchases = true; })
        .finally(() => { this.loading.purchases = false; });
    },

    loadAlerts() {
      this.loading.alerts = true;
      this.$http.get('/dashboard/v2/alerts', {
        params: { establishment_id: this.filters.establishment_id || '' },
      }).then(({ data }) => { this.alerts = Array.isArray(data) ? data : []; })
        .catch(() => { this.alerts = []; })
        .finally(() => { this.loading.alerts = false; });
    },

    loadReceivables() {
      this.loading.receivables = true;
      this.$http.get('/dashboard/v2/receivables', { params: this.params() })
        .then(({ data }) => { this.receivables = data || this.receivables; })
        .catch(() => {})
        .finally(() => { this.loading.receivables = false; });
    },

    loadCustomers() {
      this.loading.customers = true;
      this.$http.get('/dashboard/v2/customers', { params: this.params() })
        .then(({ data }) => { this.customerData = data || this.customerData; })
        .catch(() => {})
        .finally(() => { this.loading.customers = false; });
    },

    loadPaymentMethods() {
      this.loading.paymentMethods = true;
      this.$http.get('/dashboard/v2/payment-methods', { params: this.params() })
        .then(({ data }) => {
          this.paymentMethods = Array.isArray(data) ? data : [];
          this.$nextTick(() => this.renderDonutChart());
        })
        .catch(() => {})
        .finally(() => { this.loading.paymentMethods = false; });
    },

    loadProfitability() {
      this.loading.profitability = true;
      this.$http.get('/dashboard/v2/profitability', { params: this.params() })
        .then(({ data }) => {
          this.profitability = Array.isArray(data) ? data : [];
          this.$nextTick(() => this.renderProfitChart());
        })
        .catch(() => {})
        .finally(() => { this.loading.profitability = false; });
    },

    loadPeriodComparison() {
      this.loading.periodComparison = true;
      this.$http.get('/dashboard/v2/period-comparison', {
        params: { establishment_id: this.filters.establishment_id || '' },
      }).then(({ data }) => {
        this.periodComparison = data || this.periodComparison;
        this.$nextTick(() => this.renderPeriodChart());
      })
        .catch(() => {})
        .finally(() => { this.loading.periodComparison = false; });
    },

    loadInventory() {
      this.loading.inventory = true;
      this.$http.get('/dashboard/v2/inventory-advanced', {
        params: { establishment_id: this.filters.establishment_id || '' },
      }).then(({ data }) => { this.inventory = data || this.inventory; })
        .catch(() => {})
        .finally(() => { this.loading.inventory = false; });
    },

    loadSalesByHour() {
      this.loading.salesByHour = true;
      this.$http.get('/dashboard/v2/sales-by-hour', { params: this.params() })
        .then(({ data }) => {
          this.salesByHour = data || this.salesByHour;
          this.$nextTick(() => this.renderHourChart());
        })
        .catch(() => {})
        .finally(() => { this.loading.salesByHour = false; });
    },

    loadQuotations() {
      this.loading.quotations = true;
      this.$http.get('/dashboard/v2/quotation-conversion', { params: this.params() })
        .then(({ data }) => { this.quotations = data || this.quotations; })
        .catch(() => {})
        .finally(() => { this.loading.quotations = false; });
    },

    loadSalesByCity() {
      this.loading.salesByCity = true;
      this.$http.get('/dashboard/v2/sales-by-city', { params: this.params() })
        .then(({ data }) => {
          this.salesByCity = Array.isArray(data) ? data : [];
          this.$nextTick(() => this.renderCityChart());
        })
        .catch(() => {})
        .finally(() => { this.loading.salesByCity = false; });
    },

    // ── Helpers de chart lifecycle ──────────────────────────────────────
    _destroyChart(key) {
      if (this._charts[key]) { this._charts[key].destroy(); delete this._charts[key]; }
    },

    renderSalesChart() {
      const canvas = this.$refs.salesCanvas;
      if (!canvas) return;
      if (this._chart) { this._chart.destroy(); this._chart = null; }
      const isDaily = this.chartMode === 'daily';
      const src = isDaily ? this.dailyChart : this.monthlyChart;
      if (!src || !src.labels || !src.labels.length) return;
      const ctx  = canvas.getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, 240);
      grad.addColorStop(0, 'rgba(59,130,246,.22)');
      grad.addColorStop(1, 'rgba(59,130,246,.0)');
      this._chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: src.labels,
          datasets: [{
            label: 'Ventas (S/)',
            data: src.totals,
            borderColor: '#3b82f6', backgroundColor: grad,
            borderWidth: 2, pointRadius: isDaily ? 2 : 4,
            pointHoverRadius: 6, pointBackgroundColor: '#fff',
            pointBorderColor: '#3b82f6', pointBorderWidth: 2,
            fill: true, tension: 0.35,
          }],
        },
        options: this._lineOptions(isDaily ? 10 : 12),
      });
    },

    renderPeriodChart() {
      this._destroyChart('period');
      const canvas = this.$refs.periodCanvas;
      if (!canvas) return;
      const view = this.periodView;
      const data = this.periodComparison[view];
      if (!data) return;
      const ctx = canvas.getContext('2d');
      this._charts.period = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Ventas', 'Transacciones', 'Ticket promedio'],
          datasets: [
            {
              label: String(data.current.label),
              data: [data.current.total, data.current.count * 100, data.current.avg_ticket],
              backgroundColor: 'rgba(59,130,246,.7)',
              borderColor: '#3b82f6', borderWidth: 1,
            },
            {
              label: String(data.previous.label),
              data: [data.previous.total, data.previous.count * 100, data.previous.avg_ticket],
              backgroundColor: 'rgba(148,163,184,.5)',
              borderColor: '#94a3b8', borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          legend: { position: 'top', labels: { fontSize: 11 } },
          scales: {
            xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 11 } }],
            yAxes: [{ ticks: { fontSize: 11, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }],
          },
          tooltips: {
            callbacks: {
              label: ctx => {
                const di = ctx.index;
                if (di === 1) return `${ctx.dataset.label}: ${parseInt(ctx.yLabel/100)} transacciones`;
                return `${ctx.dataset.label}: S/ ${Number(ctx.yLabel).toLocaleString('es-PE',{minimumFractionDigits:2})}`;
              },
            },
          },
        },
      });
    },

    renderDonutChart() {
      this._destroyChart('pay');
      const canvas = this.$refs.payCanvas;
      if (!canvas || !this.paymentMethods.length) return;
      const ctx = canvas.getContext('2d');
      this._charts.pay = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: this.paymentMethods.map(m => m.label),
          datasets: [{
            data: this.paymentMethods.map(m => m.total),
            backgroundColor: this.paymentMethods.map((_, i) => PAY_COLORS[i % PAY_COLORS.length]),
            borderWidth: 2, borderColor: '#fff',
          }],
        },
        options: {
          responsive: true, maintainAspectRatio: true,
          legend: { display: false },
          tooltips: {
            callbacks: {
              label: ctx => {
                const m = this.paymentMethods[ctx.index];
                return ` ${m.label}: S/ ${Number(m.total).toLocaleString('es-PE',{minimumFractionDigits:2})}`;
              },
            },
          },
          cutoutPercentage: 65,
        },
      });
    },

    renderProfitChart() {
      this._destroyChart('profit');
      const canvas = this.$refs.profitCanvas;
      if (!canvas || !this.profitability.length) return;
      const ctx = canvas.getContext('2d');
      const labels  = this.profitability.map(p => p.name.length > 22 ? p.name.slice(0,22)+'…' : p.name);
      const revenue = this.profitability.map(p => p.revenue);
      const cost    = this.profitability.map(p => p.cost);
      const profit  = this.profitability.map(p => p.profit);
      this._charts.profit = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
          labels,
          datasets: [
            { label: 'Ingresos', data: revenue, backgroundColor: 'rgba(59,130,246,.6)', borderColor: '#3b82f6', borderWidth: 1 },
            { label: 'Costo',    data: cost,    backgroundColor: 'rgba(239,68,68,.5)',  borderColor: '#ef4444', borderWidth: 1 },
            { label: 'Utilidad', data: profit,  backgroundColor: 'rgba(16,185,129,.7)', borderColor: '#10b981', borderWidth: 1 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          legend: { position: 'top', labels: { fontSize: 11 } },
          scales: {
            xAxes: [{ ticks: { fontSize: 10, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }],
            yAxes: [{ ticks: { fontSize: 10 } }],
          },
          tooltips: {
            callbacks: {
              label: ctx => ` ${ctx.dataset.label}: S/ ${Number(ctx.xLabel).toLocaleString('es-PE',{minimumFractionDigits:2})}`,
            },
          },
        },
      });
    },

    renderHourChart() {
      this._destroyChart('hour');
      const canvas = this.$refs.hourCanvas;
      if (!canvas || !this.salesByHour.labels || !this.salesByHour.labels.length) return;
      const ctx  = canvas.getContext('2d');
      const grad = ctx.createLinearGradient(0, 0, 0, 180);
      grad.addColorStop(0, 'rgba(139,92,246,.7)');
      grad.addColorStop(1, 'rgba(139,92,246,.2)');
      this._charts.hour = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: this.salesByHour.labels,
          datasets: [{
            label: 'Ventas (S/)',
            data: this.salesByHour.totals,
            backgroundColor: grad,
            borderColor: '#8b5cf6', borderWidth: 1, borderRadius: 4,
          }],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          legend: { display: false },
          scales: {
            xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10 } }],
            yAxes: [{ ticks: { fontSize: 10, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }],
          },
          tooltips: {
            callbacks: {
              label: ctx => ` S/ ${Number(ctx.yLabel).toLocaleString('es-PE',{minimumFractionDigits:2})}`,
              afterLabel: ctx => ` ${this.salesByHour.counts[ctx.index] || 0} ventas`,
            },
          },
        },
      });
    },

    renderCityChart() {
      this._destroyChart('city');
      const canvas = this.$refs.cityCanvas;
      if (!canvas || !this.salesByCity.length) return;
      const ctx  = canvas.getContext('2d');
      const labels = this.salesByCity.map(c => c.city);
      const totals = this.salesByCity.map(c => c.total);
      const colors = totals.map((_, i) => {
        const hue = (200 + i * 25) % 360;
        return `hsla(${hue},70%,55%,.75)`;
      });
      this._charts.city = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
          labels,
          datasets: [{
            label: 'Ventas (S/)',
            data: totals,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('.75','1')),
            borderWidth: 1,
          }],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          legend: { display: false },
          scales: {
            xAxes: [{ ticks: { fontSize: 10, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }],
            yAxes: [{ ticks: { fontSize: 11 } }],
          },
          tooltips: {
            callbacks: {
              label: ctx => {
                const c = this.salesByCity[ctx.index];
                return ` S/ ${Number(ctx.xLabel).toLocaleString('es-PE',{minimumFractionDigits:2})} · ${c.count} ventas`;
              },
            },
          },
        },
      });
    },

    _lineOptions(maxTicks) {
      return {
        responsive: true, maintainAspectRatio: false,
        legend: { display: false },
        tooltips: {
          mode: 'index', intersect: false,
          callbacks: {
            label: ctx => `S/ ${Number(ctx.yLabel).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2})}`,
          },
        },
        scales: {
          xAxes: [{ gridLines: { display: false }, ticks: { maxTicksLimit: maxTicks, fontSize: 11, fontColor: '#94a3b8' } }],
          yAxes: [{ gridLines: { color: 'rgba(0,0,0,.04)' }, ticks: { fontSize: 11, fontColor: '#94a3b8', callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }],
        },
      };
    },

    supplierPct(total) { return Math.round((parseFloat(total) / this.maxSupplier) * 100); },
    custPct(total)     { return Math.round((parseFloat(total) / this.maxCustomer) * 100); },
    cityPct(total)     { return Math.round((parseFloat(total) / this.maxCity)     * 100); },
    rankClass(i) {
      return { 'dv2-rank--1': i === 0, 'dv2-rank--2': i === 1, 'dv2-rank--3': i === 2 };
    },
  },

  filters: {
    fmt(v) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0.00';
      if (Math.abs(n) >= 1000000) return (n / 1000000).toFixed(1) + 'M';
      if (Math.abs(n) >= 1000)    return (n / 1000).toFixed(1)    + 'K';
      return n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    fmtQty(v) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n % 1 === 0 ? String(Math.round(n)) : n.toFixed(2);
    },
  },
};
</script>

<style scoped>
/* ── Tokens ──────────────────────────────────────── */
.dv2 {
  --g: #10b981; --b: #3b82f6; --p: #8b5cf6;
  --o: #f59e0b; --t: #14b8a6; --r: #ef4444;
  --card-r: 12px;
  --card-sh: 0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --card-sh-h: 0 4px 16px rgba(0,0,0,.09);
  font-family: 'Inter','Nunito',-apple-system,sans-serif;
  display: flex; flex-direction: column; gap: 18px;
  padding-bottom: 32px;
}

/* ── Header ──────────────────────────────────────── */
.dv2-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding-bottom:4px; }
.dv2-header__left  { display:flex;align-items:center;gap:10px; }
.dv2-header__right { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.dv2-title { margin:0;font-size:16px;font-weight:800;letter-spacing:-.025em; }
.dv2-period-label { font-size:12px;color:#94a3b8;font-weight:500; }

/* ── KPI grid ────────────────────────────────────── */
.dv2-kpis { display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px; }
.dv2-kpi {
  display:flex;align-items:flex-start;gap:10px;
  padding:14px 16px;border-radius:var(--card-r);
  background:#fff;box-shadow:var(--card-sh);
  border-left:3px solid transparent;
  transition:box-shadow .2s;
}
.dv2-kpi:hover { box-shadow:var(--card-sh-h); }
.dv2-kpi--green  { border-left-color:var(--g); }
.dv2-kpi--blue   { border-left-color:var(--b); }
.dv2-kpi--purple { border-left-color:var(--p); }
.dv2-kpi--orange { border-left-color:var(--o); }
.dv2-kpi--teal   { border-left-color:var(--t); }
.dv2-kpi--red    { border-left-color:var(--r); }
.dv2-kpi--amber  { border-left-color:#f59e0b; }
.dv2-kpi--indigo { border-left-color:#6366f1; }
.dv2-kpi__icon {
  flex-shrink:0;width:34px;height:34px;border-radius:8px;
  background:rgba(0,0,0,.04);
  display:flex;align-items:center;justify-content:center;color:#64748b;
}
.dv2-kpi__body { flex:1;min-width:0; }
.dv2-kpi__value { font-size:20px;font-weight:800;line-height:1.15;margin-bottom:2px; }
.dv2-kpi__label { font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em; }
.dv2-kpi__sub   { font-size:11px;color:#94a3b8;margin-top:2px; }

/* ── Cards ───────────────────────────────────────── */
.dv2-card {
  background:#fff;border-radius:var(--card-r);
  box-shadow:var(--card-sh);padding:16px;
  transition:box-shadow .2s;
}
.dv2-card:hover { box-shadow:var(--card-sh-h); }
.dv2-card--chart { }
.dv2-card__head { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:6px; }
.dv2-card__title { font-size:13px;font-weight:700;color:#1e293b; }
.dv2-card__sub   { font-size:11px;color:#94a3b8; }
.dv2-card__footer { margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9; }

/* ── 2-col layout ────────────────────────────────── */
.dv2-row-two { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
@media(max-width:768px){ .dv2-row-two { grid-template-columns:1fr; } }

/* ── Charts ──────────────────────────────────────── */
.dv2-chart-wrap { position:relative; }
.dv2-chart-loader {
  display:flex;align-items:center;justify-content:center;
  height:240px;
}
.dv2-spin {
  width:28px;height:28px;border-radius:50%;
  border:3px solid #e2e8f0;border-top-color:var(--b);
  animation:dv2-spin .7s linear infinite;
}
@keyframes dv2-spin { to { transform:rotate(360deg); } }

/* ── Alerts ──────────────────────────────────────── */
.dv2-alerts-skeleton { margin-bottom:4px; }
.dv2-alerts { display:flex;flex-direction:column;gap:6px; }
.dv2-alert {
  display:flex;align-items:flex-start;gap:8px;padding:8px 12px;
  border-radius:8px;font-size:12px;font-weight:500;
}
.dv2-alert--warning { background:#fef9c3;color:#92400e; }
.dv2-alert--success { background:#dcfce7;color:#166534; }
.dv2-alert--danger  { background:#fee2e2;color:#991b1b; }

/* ── Tables ──────────────────────────────────────── */
.dv2-table { width:100%;border-collapse:collapse;font-size:12px; }
.dv2-table--sm { font-size:11px; }
.dv2-table th { padding:6px 8px;text-align:left;color:#64748b;font-weight:600;font-size:10px;text-transform:uppercase;border-bottom:1px solid #f1f5f9; }
.dv2-table td { padding:7px 8px;border-bottom:1px solid #f8fafc;vertical-align:middle; }
.dv2-table tbody tr:hover td { background:#f8fafc; }
.dv2-table-skeleton { display:flex;flex-direction:column;gap:6px;padding:4px 0; }
.dv2-empty { padding:24px;text-align:center;color:#94a3b8;font-size:12px; }
.dv2-empty--ok { color:#10b981; }

/* ── Skeleton ────────────────────────────────────── */
.dv2-skeleton {
  background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
  background-size:200% 100%;
  border-radius:4px;
  animation:dv2-shimmer 1.4s infinite;
}
.dv2-skeleton--val { display:inline-block;width:90px;height:22px;border-radius:4px; }
.dv2-skeleton--row { height:28px;border-radius:4px; }
@keyframes dv2-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* ── Rank ────────────────────────────────────────── */
.dv2-rank {
  display:inline-flex;align-items:center;justify-content:center;
  width:22px;height:22px;border-radius:50%;
  font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b;
}
.dv2-rank--1 { background:#fbbf24;color:#fff; }
.dv2-rank--2 { background:#94a3b8;color:#fff; }
.dv2-rank--3 { background:#b45309;color:#fff; }
.dv2-rank--neutral { background:#e2e8f0;color:#475569; }

/* ── Badges ──────────────────────────────────────── */
.dv2-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600; }
.dv2-badge--red  { background:#fee2e2;color:#991b1b; }
.dv2-badge--blue { background:#dbeafe;color:#1e40af; }

/* ── Chips ───────────────────────────────────────── */
.dv2-chip { padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700; }
.dv2-chip--red   { background:#fee2e2;color:#991b1b; }
.dv2-chip--amber { background:#fef9c3;color:#92400e; }
.dv2-chip--green { background:#dcfce7;color:#166534; }

/* ── Bar rows (proveedores, clientes, ciudades) ──── */
.dv2-sup-row { display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:12px; }
.dv2-sup-name { flex:0 0 120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#475569; }
.dv2-sup-bar-wrap { flex:1;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden; }
.dv2-sup-bar { height:100%;background:var(--b);border-radius:3px;transition:width .5s ease; }
.dv2-sup-bar--blue { background:var(--b); }
.dv2-sup-bar--teal { background:var(--t); }
.dv2-sup-amount { flex:0 0 70px;text-align:right;font-weight:600;color:#1e293b;font-size:11px; }

/* ── Btn group ───────────────────────────────────── */
.dv2-btn-group { display:flex;gap:2px; }
.dv2-btn-tab {
  padding:3px 10px;font-size:11px;font-weight:500;border:1px solid #e2e8f0;
  border-radius:6px;background:#fff;color:#64748b;cursor:pointer;
  transition:all .15s;
}
.dv2-btn-tab:hover { background:#f8fafc; }
.dv2-btn-tab.active { background:var(--b);color:#fff;border-color:var(--b); }

/* ── Links ───────────────────────────────────────── */
.dv2-btn-link {
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:600;color:var(--b);text-decoration:none;
}
.dv2-btn-link:hover { text-decoration:underline; }

/* ── Utility ─────────────────────────────────────── */
.fw-600     { font-weight:600; }
.text-right { text-align:right; }
.text-center{ text-align:center; }
.text-muted { color:#94a3b8; }
.text-warning { color:#f59e0b; }
.kpi-positive { color:#10b981; }
.kpi-negative { color:#ef4444; }
.dv2-cell-trunc { display:inline-block;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle; }
.dv2-code { display:inline-block;font-size:10px;background:#f1f5f9;color:#64748b;border-radius:3px;padding:1px 4px;margin-right:4px; }

/* ── Error / retry ───────────────────────────────── */
.dv2-error { padding:16px;text-align:center;font-size:12px;color:#94a3b8; }
.dv2-retry { margin-left:8px;padding:2px 8px;font-size:11px;border:1px solid #e2e8f0;border-radius:4px;cursor:pointer;background:#fff; }

/* ── Transition ──────────────────────────────────── */
.dv2-fade-enter-active,.dv2-fade-leave-active { transition:opacity .3s; }
.dv2-fade-enter,.dv2-fade-leave-to { opacity:0; }

/* ���─ Donut + legend ──────────────────────────────── */
.dv2-donut-wrap { display:flex;flex-direction:column;align-items:center;gap:12px; }
.dv2-legend { width:100%;display:flex;flex-direction:column;gap:5px; }
.dv2-legend-item { display:flex;align-items:center;gap:6px;font-size:11px; }
.dv2-legend-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
.dv2-legend-label { flex:1;color:#475569; }
.dv2-legend-val { font-weight:600;color:#1e293b; }

/* ── Period comparison ───────────────────────────── */
.dv2-period-grid { display:flex;align-items:center;gap:12px;justify-content:center;flex-wrap:wrap; }
.dv2-period-col { text-align:center;padding:8px 16px; }
.dv2-period-col--prev { opacity:.65; }
.dv2-period-label2 { font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.04em; }
.dv2-period-val { font-size:22px;font-weight:800;color:#1e293b;margin:2px 0; }
.dv2-period-sub { font-size:11px;color:#64748b; }
.dv2-period-vs { text-align:center;display:flex;flex-direction:column;align-items:center;gap:4px; }
.dv2-vs-label { font-size:11px;color:#94a3b8;font-weight:600; }
.dv2-pct { font-size:16px;font-weight:800;padding:3px 10px;border-radius:20px; }
.dv2-pct--up   { background:#dcfce7;color:#166534; }
.dv2-pct--down { background:#fee2e2;color:#991b1b; }

/* ── Customer stats mini ─────────────────────────── */
.dv2-cust-stats { display:flex;gap:0;border-radius:8px;overflow:hidden;border:1px solid #f1f5f9;margin-bottom:8px; }
.dv2-cust-stat { flex:1;text-align:center;padding:10px 6px;border-right:1px solid #f1f5f9; }
.dv2-cust-stat:last-child { border-right:none; }
.dv2-cust-stat__val { font-size:18px;font-weight:800;color:#1e293b; }
.dv2-cust-stat__val--green { color:#10b981; }
.dv2-cust-stat__val--blue  { color:#3b82f6; }
.dv2-cust-stat__lbl { font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.03em; }

/* ── Receivables summary ─────────────────────────── */
.dv2-recv-summary { display:flex;gap:0;border-radius:8px;overflow:hidden;border:1px solid #f1f5f9;margin-bottom:10px; }
.dv2-recv-summary > div { flex:1;padding:8px 10px;border-right:1px solid #f1f5f9;text-align:center; }
.dv2-recv-summary > div:last-child { border-right:none; }

/* ── Conversion gauge ────────────────────────────── */
.dv2-conv-gauge { text-align:center;margin-bottom:4px; }
.dv2-conv-rate { font-size:32px;font-weight:800;margin-bottom:6px; }
.dv2-conv-bar-bg { height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;margin:0 auto 4px;max-width:240px; }
.dv2-conv-bar-fill { height:100%;border-radius:5px;transition:width .6s ease; }
.dv2-conv-label { font-size:11px;color:#94a3b8;font-weight:500; }

/* ── City list ───────────────────────────────────── */
.dv2-city-list { margin-top:8px;display:flex;flex-direction:column;gap:5px; }
.dv2-city-row { display:flex;align-items:center;gap:7px;font-size:11px; }
.dv2-city-name { flex:0 0 100px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.dv2-city-cnt  { flex:0 0 60px;text-align:right; }
.dv2-city-val  { flex:0 0 75px;text-align:right;font-weight:600;color:#1e293b; }
</style>
