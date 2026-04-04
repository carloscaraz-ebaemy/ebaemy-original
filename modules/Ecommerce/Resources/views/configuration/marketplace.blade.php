@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><i class="fas fa-store"></i></h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Tienda Virtual</a></li>
        <li class="active"><span>Marketplace</span></li>
    </ol>
</div>

<div class="row">
    {{-- Canales activos --}}
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="fas fa-plug mr-2"></i> Canales de Marketplace</h4>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Canal</th><th>Estado</th><th>Productos</th><th>Último sync</th><th>Acciones</th></tr></thead>
                    <tbody id="mp-channels-tbody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Cargando canales...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Productos mapeados --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="fas fa-boxes mr-2"></i> Productos en Marketplace</h4>
                <div>
                    <select id="mp-channel-filter" class="form-control form-control-sm d-inline-block" style="width:200px">
                        <option value="">Todos los canales</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Producto</th><th>Canal</th><th>SKU externo</th><th>Estado sync</th><th>Última sync</th></tr></thead>
                    <tbody id="mp-products-tbody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Selecciona un canal para ver los productos</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};

    // Cargar canales
    fetch('/ecommerce/marketplace/channels', {headers:{'Accept':'application/json'}})
    .then(function(r){return r.json()})
    .then(function(channels){
        var tbody = document.getElementById('mp-channels-tbody');
        var filter = document.getElementById('mp-channel-filter');
        if(!channels.length){
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay canales de marketplace configurados.<br><a href="/ecommerce/configuration" class="text-primary">Ir a Configuración → Marketplaces</a></td></tr>';
            return;
        }
        var html = '';
        channels.forEach(function(ch){
            var statusBadge = ch.status === 'active' ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">' + ch.status + '</span>';
            filter.innerHTML += '<option value="'+ch.id+'">'+ch.name+' ('+ch.platform+')</option>';
            html += '<tr>'
                + '<td><strong>'+ch.name+'</strong><br><small class="text-muted">'+ch.platform+'</small></td>'
                + '<td>'+statusBadge+'</td>'
                + '<td id="mp-count-'+ch.id+'">-</td>'
                + '<td>'+(ch.last_sync_at || '<span class="text-muted">Nunca</span>')+'</td>'
                + '<td>'
                + '<button class="btn btn-xs btn-outline-primary mr-1" onclick="syncProducts('+ch.id+')"><i class="fas fa-sync"></i> Sync productos</button>'
                + '<button class="btn btn-xs btn-outline-success mr-1" onclick="syncStock('+ch.id+')"><i class="fas fa-boxes"></i> Sync stock</button>'
                + '<button class="btn btn-xs btn-outline-info" onclick="loadProducts('+ch.id+')"><i class="fas fa-list"></i> Ver productos</button>'
                + '</td></tr>';
        });
        tbody.innerHTML = html;

        // Cargar conteo de productos por canal
        channels.forEach(function(ch){
            fetch('/ecommerce/marketplace/channels/'+ch.id+'/products', {headers:{'Accept':'application/json'}})
            .then(function(r){return r.json()})
            .then(function(prods){
                var el = document.getElementById('mp-count-'+ch.id);
                var count = prods.total || prods.length || (prods.data ? prods.data.length : 0);
                if(el) el.textContent = count + ' productos';
            });
        });
    })
    .catch(function(e){
        document.getElementById('mp-channels-tbody').innerHTML = '<tr><td colspan="5" class="text-danger text-center py-3">Error: '+e.message+'</td></tr>';
    });

    // Filtro de canal
    document.getElementById('mp-channel-filter').addEventListener('change', function(){
        if(this.value) loadProducts(parseInt(this.value));
    });

    window.loadProducts = function(channelId){
        var tbody = document.getElementById('mp-products-tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3">Cargando...</td></tr>';
        fetch('/ecommerce/marketplace/channels/'+channelId+'/products', {headers:{'Accept':'application/json'}})
        .then(function(r){return r.json()})
        .then(function(response){
            var prods = response.data || response;
            if(!prods.length){
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay productos mapeados en este canal.<br><button class="btn btn-sm btn-primary mt-2" onclick="autoMap('+channelId+')"><i class="fas fa-magic"></i> Auto-mapear productos</button></td></tr>';
                return;
            }
            var html = '';
            prods.forEach(function(p){
                var syncBadge = {'synced':'<span class="badge badge-success">Sincronizado</span>','pending':'<span class="badge badge-warning">Pendiente</span>','error':'<span class="badge badge-danger">Error</span>'}[p.sync_status] || '<span class="badge badge-secondary">'+p.sync_status+'</span>';
                var itemName = p.item ? p.item.description : ('Item #'+p.item_id);
                html += '<tr><td>'+itemName+'</td><td>'+(p.channel?p.channel.name:'')+'</td><td><code>'+(p.external_sku||'-')+'</code></td><td>'+syncBadge+'</td><td>'+(p.synced_at||'<span class="text-muted">-</span>')+'</td></tr>';
            });
            tbody.innerHTML = html;
        });
    };

    window.syncProducts = function(channelId){
        fetch('/ecommerce/marketplace/channels/'+channelId+'/sync-products', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){ alert(data.message || 'Sincronización iniciada'); loadProducts(channelId); });
    };
    window.syncStock = function(channelId){
        fetch('/ecommerce/marketplace/channels/'+channelId+'/sync-stock', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){ alert(data.message || 'Stock sincronizado'); });
    };
    window.autoMap = function(channelId){
        fetch('/ecommerce/marketplace/channels/'+channelId+'/auto-map', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){ alert(data.message || 'Productos mapeados'); loadProducts(channelId); });
    };
});
</script>
@endsection
