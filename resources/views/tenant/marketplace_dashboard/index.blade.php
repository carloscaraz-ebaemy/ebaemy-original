@extends('tenant.layouts.app')

@section('content')
<div class="container-fluid py-3" id="mpDash">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">🌐 Dashboard Marketplace</h3>
            <small class="text-muted">Cómo le va a tu tienda en <strong>ebaemy.com/marketplace</strong> (últimos 30 días)</small>
        </div>
        <div>
            <a href="https://ebaemy.com/marketplace/tienda/{{ strtolower(strtok(($vc_company->hostname ?? request()->getHost()), '.')) }}"
               target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                Ver mi tienda en marketplace →
            </a>
        </div>
    </div>

    <div id="mpDashLoading" class="text-center text-muted py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 small">Cargando métricas…</div>
    </div>

    <div id="mpDashContent" style="display:none">
        {{-- ═══════════════════════ KPI CARDS ═══════════════════════ --}}
        <div class="mpd-kpi-grid">
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">📦 Publicados</div>
                <div class="mpd-kpi__value" data-k="published">0</div>
                <div class="mpd-kpi__sub text-muted">en marketplace público</div>
            </div>
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">👁️ Vistas totales</div>
                <div class="mpd-kpi__value" data-k="views_total">0</div>
                <div class="mpd-kpi__sub text-muted">acumulado histórico</div>
            </div>
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">🛒 Pedidos 30d</div>
                <div class="mpd-kpi__value" data-k="orders_30d">0</div>
                <div class="mpd-kpi__sub" data-delta="orders"></div>
            </div>
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">💰 Ventas 30d</div>
                <div class="mpd-kpi__value" data-k="revenue_30d_fmt">S/ 0.00</div>
                <div class="mpd-kpi__sub" data-delta="revenue"></div>
            </div>
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">📞 Leads 30d</div>
                <div class="mpd-kpi__value" data-k="leads_30d">0</div>
                <div class="mpd-kpi__sub" data-delta="leads"></div>
            </div>
            <div class="mpd-kpi">
                <div class="mpd-kpi__label">⭐ Rating</div>
                <div class="mpd-kpi__value" data-k="avg_rating">—</div>
                <div class="mpd-kpi__sub text-muted"><span data-k="reviews_total">0</span> reviews</div>
            </div>
        </div>

        {{-- ═══════════════════════ FUNNEL + CHART ═══════════════════════ --}}
        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">📈 Tendencia 30 días</h5>
                        <canvas id="mpDashChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="mb-3">🎯 Embudo de conversión</h5>
                        <div id="mpDashFunnel"></div>
                        <div class="mt-3 small text-muted">
                            Cada paso muestra el % del tráfico total que llegó hasta ahí.
                            Si la tasa Vistas→Clicks es baja: mejora título/imagen. Si Clicks→Pedidos es baja: revisa precio o descripción.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════ TOP PRODUCTOS ═══════════════════════ --}}
        <div class="row g-3 mt-1">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">👁️ Top productos por vistas</h6>
                        <ul class="mpd-top-list" id="mpDashTopViews"></ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">🖱️ Top productos por clicks</h6>
                        <ul class="mpd-top-list" id="mpDashTopClicks"></ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">📞 Top productos por leads</h6>
                        <ul class="mpd-top-list" id="mpDashTopLeads"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.mpd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.mpd-kpi {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
}
.mpd-kpi__label { font-size: 12px; color: #6b7280; font-weight: 600; }
.mpd-kpi__value { font-size: 26px; font-weight: 800; color: #111827; margin-top: 2px; line-height: 1.1; }
.mpd-kpi__sub { font-size: 11.5px; margin-top: 4px; }
.mpd-delta-up   { color: #16a34a; font-weight: 600; }
.mpd-delta-down { color: #dc2626; font-weight: 600; }
.mpd-delta-flat { color: #6b7280; }

.mpd-funnel-row { display: flex; flex-direction: column; gap: 8px; }
.mpd-funnel-step { display: flex; align-items: center; gap: 12px; }
.mpd-funnel-bar  {
    flex: 1; height: 28px; background: #f3f4f6; border-radius: 6px;
    position: relative; overflow: hidden;
}
.mpd-funnel-fill {
    position: absolute; left: 0; top: 0; bottom: 0;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    display: flex; align-items: center; padding: 0 10px;
    color: #fff; font-size: 12px; font-weight: 700;
    border-radius: 6px;
    transition: width .6s ease;
}
.mpd-funnel-label { width: 80px; font-size: 13px; font-weight: 600; color: #374151; }
.mpd-funnel-rate { width: 56px; font-size: 12px; color: #6b7280; text-align: right; }

.mpd-top-list { list-style: none; padding: 0; margin: 0; }
.mpd-top-list li {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 0; border-bottom: 1px dashed #e5e7eb;
}
.mpd-top-list li:last-child { border-bottom: 0; }
.mpd-top-list img {
    width: 36px; height: 36px; object-fit: cover; border-radius: 6px;
    background: #f3f4f6; flex-shrink: 0;
}
.mpd-top-list .name {
    flex: 1; font-size: 12.5px; color: #374151;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mpd-top-list .metric {
    font-size: 13px; font-weight: 700; color: #1f2937;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function(){
    const DATA_URL = @json(url('/items/marketplace-dashboard-data'));
    const loading  = document.getElementById('mpDashLoading');
    const content  = document.getElementById('mpDashContent');

    const fmtMoney = n => 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtNum   = n => (Number(n) || 0).toLocaleString('es-PE');
    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function deltaHtml(pct) {
        if (pct === 0 || pct === null || pct === undefined) return '<span class="mpd-delta-flat">sin cambio</span>';
        if (pct > 0)   return `<span class="mpd-delta-up">▲ ${pct}%</span> vs 30 días anteriores`;
        return `<span class="mpd-delta-down">▼ ${Math.abs(pct)}%</span> vs 30 días anteriores`;
    }

    function renderFunnel(funnel) {
        const wrap = document.getElementById('mpDashFunnel');
        const html = '<div class="mpd-funnel-row">' + funnel.map(s => {
            const w = Math.max(2, Math.min(100, s.rate));
            return `
                <div class="mpd-funnel-step">
                    <span class="mpd-funnel-label">${esc(s.stage)}</span>
                    <div class="mpd-funnel-bar">
                        <div class="mpd-funnel-fill" style="width:${w}%">${fmtNum(s.value)}</div>
                    </div>
                    <span class="mpd-funnel-rate">${s.rate}%</span>
                </div>`;
        }).join('') + '</div>';
        wrap.innerHTML = html;
    }

    function renderTopList(elId, items, metric) {
        const el = document.getElementById(elId);
        if (!items || items.length === 0) {
            el.innerHTML = '<li class="text-muted small">Sin datos aún</li>';
            return;
        }
        el.innerHTML = items.map(p => `
            <li>
                ${p.image_url
                    ? `<img src="${esc(p.image_url)}" alt="" loading="lazy">`
                    : '<div class="mpd-top-list-thumb" style="width:36px;height:36px;background:#f3f4f6;border-radius:6px"></div>'}
                <span class="name" title="${esc(p.title)}">${esc(p.title)}</span>
                <span class="metric">${fmtNum(p[metric])}</span>
            </li>`).join('');
    }

    function renderChart(daily) {
        const ctx = document.getElementById('mpDashChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: daily.map(d => d.date.slice(5)), // "MM-DD"
                datasets: [
                    {
                        label: 'Pedidos', data: daily.map(d => d.orders),
                        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)',
                        tension: .3, fill: true, borderWidth: 2,
                    },
                    {
                        label: 'Leads (WhatsApp)', data: daily.map(d => d.leads),
                        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)',
                        tension: .3, fill: true, borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    fetch(DATA_URL, { headers: { 'Accept':'application/json' }, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || data.error) throw new Error('no data');

            // KPI cards
            const k = data.kpi || {};
            k.revenue_30d_fmt = fmtMoney(k.revenue_30d);
            Object.entries(k).forEach(([key, val]) => {
                document.querySelectorAll(`[data-k="${key}"]`).forEach(el => {
                    el.textContent = (typeof val === 'number' && !key.endsWith('_fmt') && !key.endsWith('_pct') && key !== 'avg_rating')
                        ? fmtNum(val) : val;
                });
            });
            // Deltas
            document.querySelector('[data-delta="orders"]').innerHTML  = deltaHtml(k.orders_delta_pct);
            document.querySelector('[data-delta="revenue"]').innerHTML = deltaHtml(k.revenue_delta_pct);
            document.querySelector('[data-delta="leads"]').innerHTML   = deltaHtml(k.leads_delta_pct);

            renderFunnel(data.funnel);
            renderTopList('mpDashTopViews',  data.top.views,  'view_count');
            renderTopList('mpDashTopClicks', data.top.clicks, 'click_count');
            renderTopList('mpDashTopLeads',  data.top.leads,  'lead_count');
            renderChart(data.daily);

            loading.style.display = 'none';
            content.style.display = 'block';
        })
        .catch(() => {
            loading.innerHTML = '<div class="alert alert-danger">No se pudieron cargar las métricas. Recarga la página.</div>';
        });
})();
</script>
@endsection
