@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><i class="fas fa-chart-line"></i></h2>
    <ol class="breadcrumbs">
        <li><a href="/dashboard">Dashboard</a></li>
        <li class="active"><span>Reporte Ecommerce</span></li>
    </ol>
</div>

{{-- Filtro de fechas --}}
<div class="row mb-3">
    <div class="col-md-8 d-flex gap-2 align-items-center">
        <input type="date" id="rep-from" class="form-control form-control-sm" style="width:160px">
        <span>a</span>
        <input type="date" id="rep-to" class="form-control form-control-sm" style="width:160px">
        <button class="btn btn-sm btn-primary" onclick="loadAll()"><i class="fas fa-sync"></i> Actualizar</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="setQuick(30)">30d</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="setQuick(7)">7d</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="setQuick(90)">90d</button>
    </div>
</div>

{{-- KPIs --}}
<div class="row mb-3" id="kpi-row">
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Ventas</h5><h3 id="kpi-revenue" class="mb-0">-</h3><small id="kpi-revenue-change" class="text-muted"></small></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Pedidos</h5><h3 id="kpi-orders" class="mb-0">-</h3></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Ticket promedio</h5><h3 id="kpi-avg" class="mb-0">-</h3></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Conversion</h5><h3 id="kpi-conv" class="mb-0">-</h3></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Cancelados</h5><h3 id="kpi-cancel" class="mb-0">-</h3></div></div>
    <div class="col-md-2"><div class="card text-center p-3"><h5 class="text-muted mb-1" style="font-size:11px">Carritos abandonados</h5><h3 id="kpi-abandoned" class="mb-0">-</h3></div></div>
</div>

{{-- Graficos --}}
<div class="row mb-3">
    {{-- Ventas diarias --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Ventas diarias</h5></div>
            <div class="card-body" style="height:320px"><canvas id="chart-daily"></canvas></div>
        </div>
    </div>
    {{-- Ventas por canal --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Ventas por canal</h5></div>
            <div class="card-body" style="height:320px"><canvas id="chart-channels"></canvas></div>
        </div>
    </div>
</div>

<div class="row mb-3">
    {{-- Tabla canales --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Desglose por canal</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Canal</th><th>Tipo</th><th>Pedidos</th><th>Ingresos</th><th>Ticket prom.</th><th>%</th></tr></thead>
                    <tbody id="channels-tbody"><tr><td colspan="6" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- Ventas por hora --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Ventas por hora del dia</h5></div>
            <div class="card-body" style="height:280px"><canvas id="chart-hours"></canvas></div>
        </div>
    </div>
</div>

<div class="row mb-3">
    {{-- Top productos --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Top productos</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Producto</th><th>Unidades</th><th>Ingresos</th></tr></thead>
                    <tbody id="products-tbody"><tr><td colspan="4" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- Top clientes --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Top clientes (LTV)</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Cliente</th><th>Pedidos</th><th>Valor total</th><th>Ticket prom.</th></tr></thead>
                    <tbody id="ltv-tbody"><tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
var headers = {'Accept':'application/json'};
var chartDaily, chartChannels, chartHours;
var channelColors = ['#4F46E5','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#84CC16'];

function qs(from, to){ return '?from='+from+'&to='+to; }
function fmt(n){ return 'S/ ' + Number(n||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// Fechas
var today = new Date();
var d30 = new Date(); d30.setDate(d30.getDate()-30);
document.getElementById('rep-from').value = d30.toISOString().slice(0,10);
document.getElementById('rep-to').value = today.toISOString().slice(0,10);

function setQuick(days){
    var d = new Date(); d.setDate(d.getDate()-days);
    document.getElementById('rep-from').value = d.toISOString().slice(0,10);
    document.getElementById('rep-to').value = today.toISOString().slice(0,10);
    loadAll();
}

function getRange(){
    return { from: document.getElementById('rep-from').value, to: document.getElementById('rep-to').value };
}

function loadAll(){
    var r = getRange(), q = qs(r.from, r.to);
    loadKpis(q); loadDaily(q); loadChannels(q); loadHours(q); loadProducts(q); loadLtv(q);
}

// KPIs
function loadKpis(q){
    fetch('/reports/ecommerce/kpis'+q, {headers:headers}).then(r=>r.json()).then(d=>{
        document.getElementById('kpi-revenue').textContent = fmt(d.total_revenue);
        var ch = d.revenue_change;
        document.getElementById('kpi-revenue-change').innerHTML = ch > 0 ? '<span class="text-success">+'+ch+'%</span>' : ch < 0 ? '<span class="text-danger">'+ch+'%</span>' : '';
        document.getElementById('kpi-orders').textContent = d.total_orders;
        document.getElementById('kpi-avg').textContent = fmt(d.avg_ticket);
        document.getElementById('kpi-conv').textContent = d.conversion_rate + '%';
        document.getElementById('kpi-cancel').textContent = d.cancel_rate + '%';
        document.getElementById('kpi-abandoned').textContent = d.abandoned_carts;
    });
}

// Daily sales chart
function loadDaily(q){
    fetch('/reports/ecommerce/daily-sales'+q, {headers:headers}).then(r=>r.json()).then(d=>{
        var labels = d.daily_sales.map(x=>x.date);
        var revenue = d.daily_sales.map(x=>x.revenue);
        var orders = d.daily_sales.map(x=>x.orders);
        if(chartDaily) chartDaily.destroy();
        chartDaily = new Chart(document.getElementById('chart-daily'),{
            type:'bar',
            data:{
                labels:labels,
                datasets:[
                    {label:'Ingresos (S/)',data:revenue,backgroundColor:'rgba(79,70,229,0.3)',borderColor:'#4F46E5',borderWidth:1,yAxisID:'y'},
                    {label:'Pedidos',data:orders,type:'line',borderColor:'#10B981',backgroundColor:'transparent',tension:0.3,yAxisID:'y1'}
                ]
            },
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,position:'left'},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}}}
        });
    });
}

// Channels donut
function loadChannels(q){
    fetch('/reports/ecommerce/channels'+q, {headers:headers}).then(r=>r.json()).then(d=>{
        var ch = d.channels || [];
        // Chart
        if(chartChannels) chartChannels.destroy();
        chartChannels = new Chart(document.getElementById('chart-channels'),{
            type:'doughnut',
            data:{labels:ch.map(c=>c.channel_name),datasets:[{data:ch.map(c=>c.revenue),backgroundColor:channelColors.slice(0,ch.length)}]},
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
        });
        // Table
        var html = '';
        ch.forEach(function(c){
            var typeBadge = {'ecommerce':'primary','pos':'success','marketplace':'warning','whatsapp':'info'}[c.channel_type] || 'secondary';
            html += '<tr><td><strong>'+c.channel_name+'</strong></td><td><span class="badge badge-'+typeBadge+'">'+c.channel_type+'</span></td><td>'+c.orders+'</td><td>'+fmt(c.revenue)+'</td><td>'+fmt(c.avg_ticket)+'</td><td>'+c.revenue_share+'%</td></tr>';
        });
        document.getElementById('channels-tbody').innerHTML = html || '<tr><td colspan="6" class="text-center text-muted py-3">Sin datos de canales</td></tr>';
    });
}

// Sales by hour
function loadHours(q){
    fetch('/reports/ecommerce/sales-by-hour'+q, {headers:headers}).then(r=>r.json()).then(d=>{
        var data = d.sales_by_hour || [];
        var labels = Array.from({length:24},(_,i)=>i+':00');
        var values = Array(24).fill(0);
        data.forEach(x=>{ values[x.hour] = x.orders; });
        if(chartHours) chartHours.destroy();
        chartHours = new Chart(document.getElementById('chart-hours'),{
            type:'bar',
            data:{labels:labels,datasets:[{label:'Pedidos',data:values,backgroundColor:'rgba(16,185,129,0.4)',borderColor:'#10B981',borderWidth:1}]},
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
        });
    });
}

// Top products
function loadProducts(q){
    fetch('/reports/ecommerce/top-products'+q+'&limit=10', {headers:headers}).then(r=>r.json()).then(d=>{
        var html = '';
        (d.top_products||[]).forEach(function(p,i){
            html += '<tr><td>'+(i+1)+'</td><td>'+p.description+'</td><td>'+p.quantity+'</td><td>'+fmt(p.revenue)+'</td></tr>';
        });
        document.getElementById('products-tbody').innerHTML = html || '<tr><td colspan="4" class="text-center text-muted py-3">Sin datos</td></tr>';
    });
}

// Customer LTV
function loadLtv(q){
    fetch('/reports/ecommerce/customer-ltv'+q+'&limit=10', {headers:headers}).then(r=>r.json()).then(d=>{
        var html = '';
        (d.top_customers||[]).forEach(function(c,i){
            html += '<tr><td>'+(i+1)+'</td><td>'+c.name+'</td><td>'+c.total_orders+'</td><td>'+fmt(c.lifetime_value)+'</td><td>'+fmt(c.avg_ticket)+'</td></tr>';
        });
        document.getElementById('ltv-tbody').innerHTML = html || '<tr><td colspan="5" class="text-center text-muted py-3">Sin datos</td></tr>';
    });
}

// Init
document.addEventListener('DOMContentLoaded', loadAll);
</script>
@endsection
