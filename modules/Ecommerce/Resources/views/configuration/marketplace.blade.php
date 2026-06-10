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

    {{-- Progreso de importación de catálogo --}}
    <div class="col-12 mb-3" id="mp-import-panel" style="display:none">
        <div class="card border-primary">
            <div class="card-body">
                <h5 class="mb-2"><i class="fas fa-cloud-download-alt mr-2"></i> Importando catálogo de Saga Falabella…</h5>
                <div class="progress mb-2" style="height:22px">
                    <div id="mp-import-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                         role="progressbar" style="width:0%">0%</div>
                </div>
                <div id="mp-import-status" class="text-muted small">Iniciando…</div>
                <div class="mt-2">
                    <span class="badge badge-success" id="mp-import-created">Creados: 0</span>
                    <span class="badge badge-info" id="mp-import-linked">Enlazados: 0</span>
                    <span class="badge badge-secondary" id="mp-import-skipped">Saltados: 0</span>
                    <span class="badge badge-danger" id="mp-import-failed">Fallidos: 0</span>
                </div>
                <small class="text-muted d-block mt-2">No cierres esta página mientras importa. Puede tardar varios minutos.</small>
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
            var statusBadge;
            if (ch.status === 'active') {
                statusBadge = '<span class="badge badge-success">Conectado</span>';
            } else if (ch.status === 'error') {
                var err = (ch.last_error_message || 'Error de conexión').replace(/"/g, '&quot;');
                statusBadge = '<span class="badge badge-danger" title="'+err+'">Error</span>'
                    + '<br><small class="text-danger">'+err.substring(0,60)+'</small>';
            } else {
                statusBadge = '<span class="badge badge-secondary">'+(ch.status || 'inactivo')+'</span>';
            }
            filter.innerHTML += '<option value="'+ch.id+'">'+ch.name+' ('+ch.platform+')</option>';
            // Botón destacado: traer el catálogo de Saga hacia EBAEMY (solo Falabella)
            var importBtn = ch.platform === 'falabella'
                ? '<button class="btn btn-xs btn-primary mr-1 mb-1" onclick="importCatalog('+ch.id+')"><i class="fas fa-cloud-download-alt"></i> Importar de Saga</button>'
                : '';
            html += '<tr>'
                + '<td><strong>'+ch.name+'</strong><br><small class="text-muted">'+ch.platform+'</small></td>'
                + '<td>'+statusBadge+'</td>'
                + '<td id="mp-count-'+ch.id+'">-</td>'
                + '<td>'+(ch.last_sync_at || '<span class="text-muted">Nunca</span>')+'</td>'
                + '<td>'
                + importBtn
                + '<button class="btn btn-xs btn-outline-primary mr-1 mb-1" onclick="syncProducts('+ch.id+')"><i class="fas fa-sync"></i> Sync productos</button>'
                + '<button class="btn btn-xs btn-outline-success mr-1 mb-1" onclick="syncStock('+ch.id+')"><i class="fas fa-boxes"></i> Sync stock</button>'
                + '<button class="btn btn-xs btn-outline-warning mr-1 mb-1" onclick="fetchOrders('+ch.id+')"><i class="fas fa-download"></i> Traer órdenes</button>'
                + '<button class="btn btn-xs btn-outline-info mb-1" onclick="loadProducts('+ch.id+')"><i class="fas fa-list"></i> Ver productos</button>'
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
    window.fetchOrders = function(channelId){
        fetch('/ecommerce/marketplace/channels/'+channelId+'/fetch-orders', {headers:{'Accept':'application/json'}})
        .then(function(r){return r.json()})
        .then(function(data){
            var msg;
            if (data.error) {
                msg = 'Error: ' + data.error;
            } else if (data.success !== undefined) {
                msg = (data.success||0) + ' órdenes nuevas, ' + (data.failed||0) + ' con error';
            } else {
                msg = data.message || 'Órdenes sincronizadas';
            }
            alert(msg);
        })
        .catch(function(e){ alert('Error al traer órdenes: '+e.message); });
    };
    window.autoMap = function(channelId){
        fetch('/ecommerce/marketplace/channels/'+channelId+'/auto-map', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){ alert(data.message || 'Productos mapeados'); loadProducts(channelId); });
    };

    // Importar catálogo de Saga → EBAEMY, por lotes con barra de progreso.
    var mpImporting = false;
    window.importCatalog = function(channelId){
        if (mpImporting) { alert('Ya hay una importación en curso.'); return; }
        if (!confirm('Esto traerá tus productos de Saga Falabella y los creará en tu tienda (con sus imágenes). Puede tardar varios minutos. ¿Continuar?')) return;

        mpImporting = true;
        var panel = document.getElementById('mp-import-panel');
        var bar = document.getElementById('mp-import-bar');
        var status = document.getElementById('mp-import-status');
        panel.style.display = 'block';
        panel.scrollIntoView({behavior:'smooth', block:'center'});

        var totals = {created:0, linked:0, skipped:0, failed:0, processed:0};
        var offset = 0;
        var limit = 10;
        var totalFetchedSoFar = 0;

        function setBadges(){
            document.getElementById('mp-import-created').textContent = 'Creados: ' + totals.created;
            document.getElementById('mp-import-linked').textContent = 'Enlazados: ' + totals.linked;
            document.getElementById('mp-import-skipped').textContent = 'Saltados: ' + totals.skipped;
            document.getElementById('mp-import-failed').textContent = 'Fallidos: ' + totals.failed;
        }

        function finish(msg, ok){
            mpImporting = false;
            bar.classList.remove('progress-bar-animated','progress-bar-striped');
            bar.classList.toggle('bg-success', ok !== false);
            bar.classList.toggle('bg-danger', ok === false);
            bar.style.width = '100%';
            bar.textContent = ok === false ? 'Error' : '100%';
            status.innerHTML = '<strong>'+msg+'</strong>';
            // refrescar conteo de productos del canal
            fetch('/ecommerce/marketplace/channels/'+channelId+'/products', {headers:{'Accept':'application/json'}})
            .then(function(r){return r.json()}).then(function(prods){
                var el = document.getElementById('mp-count-'+channelId);
                var count = prods.total || prods.length || (prods.data ? prods.data.length : 0);
                if(el) el.textContent = count + ' productos';
            });
        }

        function nextBatch(){
            fetch('/ecommerce/marketplace/channels/'+channelId+'/import-catalog', {
                method:'POST', headers:headers,
                body: JSON.stringify({offset:offset, limit:limit, with_images:true})
            })
            .then(function(r){return r.json()})
            .then(function(data){
                if (data.error) { finish('Error: '+data.error, false); return; }

                totals.created += data.created||0;
                totals.linked  += data.linked||0;
                totals.skipped += data.skipped||0;
                totals.failed  += data.failed||0;
                totals.processed += data.fetched||0;
                totalFetchedSoFar += data.fetched||0;
                setBadges();

                // No conocemos el total exacto de antemano: barra animada (indeterminada)
                // y mostramos el conteo real procesado, que es la señal honesta de avance.
                status.textContent = 'Procesados ' + totals.processed + ' productos…';
                if (!data.done) {
                    bar.style.width = '100%';
                    bar.textContent = totals.processed + ' productos';
                }

                if (data.done) {
                    finish('Importación completada: ' + totals.created + ' creados, ' + totals.linked + ' enlazados, ' + totals.skipped + ' ya existían, ' + totals.failed + ' fallidos.', true);
                } else {
                    offset = data.next_offset;
                    nextBatch();
                }
            })
            .catch(function(e){ finish('Error de red: '+e.message, false); });
        }

        nextBatch();
    };
});
</script>
@endsection
