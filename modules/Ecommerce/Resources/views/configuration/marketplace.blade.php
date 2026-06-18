@extends('tenant.layouts.app')

@section('content')
<style>
.mp-dispatch .card-header{display:block}
.mp-d-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.mp-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.mp-tab{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:5px 14px;font-size:13px;font-weight:600;color:#475569;cursor:pointer;transition:all .15s}
.mp-tab:hover{border-color:#c7d2fe;color:#4f46e5}
.mp-tab.active{background:#4f46e5;border-color:#4f46e5;color:#fff}
.mp-tab-n{display:inline-block;min-width:18px;text-align:center;background:rgba(0,0,0,.08);border-radius:999px;padding:0 6px;font-size:11px;margin-left:4px}
.mp-tab.active .mp-tab-n{background:rgba(255,255,255,.25)}
.mp-table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;border-top:0}
.mp-table td{vertical-align:middle}
.mp-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.mp-b-warn{background:#fef3c7;color:#92400e}
.mp-b-info{background:#dbeafe;color:#1e40af}
.mp-b-primary{background:#e0e7ff;color:#3730a3}
.mp-b-success{background:#dcfce7;color:#166534}
.mp-b-muted{background:#f1f5f9;color:#64748b}
.mp-sub{font-size:11.5px;color:#94a3b8;margin-top:2px}
.mp-sub i{margin-right:3px}
/* Stepper de cumplimiento */
.mp-steps{display:flex;align-items:center;gap:2px;flex-wrap:wrap;margin-bottom:10px}
.mp-step{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:#94a3b8}
.mp-step .dot{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;border:1.5px solid #cbd5e1;background:#fff;color:#94a3b8;flex:0 0 auto}
.mp-step.done{color:#166534}
.mp-step.done .dot{background:#16a34a;border-color:#16a34a;color:#fff}
.mp-step.warn{color:#b45309}
.mp-step.warn .dot{border-color:#f59e0b;color:#f59e0b;background:#fffbeb}
.mp-step.cur .dot{border-color:#4f46e5;color:#4f46e5;background:#eef2ff;box-shadow:0 0 0 3px rgba(79,70,229,.12)}
.mp-step.cur{color:#4f46e5}
.mp-step-sep{width:14px;height:1.5px;background:#e2e8f0;margin:0 1px}
.mp-nextwrap{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.mp-warn-note{font-size:11.5px;color:#b45309;font-weight:600}
.mp-warn-note i{margin-right:3px}
.mp-ok-note{font-size:11.5px;color:#166534;font-weight:600}
.mp-ok-note i{margin-right:3px}
.mp-dl{font-size:13px;font-weight:600;white-space:nowrap}
.mp-dl-ok{color:#475569}.mp-dl-soon{color:#b45309}.mp-dl-over{color:#dc2626}
.mp-actions .btn{margin-left:4px;margin-bottom:4px}
.mp-btn-go{background:#16a34a;color:#fff}.mp-btn-go:hover{background:#15803d;color:#fff}
.mp-btn-ship{background:#4f46e5;color:#fff}.mp-btn-ship:hover{background:#4338ca;color:#fff}
.mp-btn-doc,.mp-btn-ghost{background:#fff;border:1px solid #e2e8f0;color:#475569}
.mp-btn-doc:hover,.mp-btn-ghost:hover{border-color:#4f46e5;color:#4f46e5}
.mp-cards{padding:12px}
.mp-card{border:1px solid #eef2f7;border-radius:12px;padding:14px;margin-bottom:10px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.mp-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.mp-card-cust{color:#334155;font-size:14px;margin-bottom:8px}
.mp-card-meta{display:flex;justify-content:space-between;font-size:13px;color:#64748b;margin-bottom:12px}
.mp-card-actions{display:flex;flex-wrap:wrap;gap:8px}
.mp-card-actions .btn{flex:1;min-width:120px}
.mp-toast-wrap{position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.mp-toast{background:#1e293b;color:#fff;padding:12px 16px;border-radius:10px;font-size:14px;box-shadow:0 8px 24px rgba(0,0,0,.18);opacity:0;transform:translateY(10px);transition:all .25s;max-width:340px}
.mp-toast.show{opacity:1;transform:translateY(0)}
.mp-toast-success{background:#16a34a}.mp-toast-error{background:#dc2626}
</style>
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
                    <span class="badge badge-warning" id="mp-import-updated">Precios actualizados: 0</span>
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

    {{-- Pedidos de Saga (despacho) --}}
    <div class="col-12">
        <div class="card mp-dispatch">
            <div class="card-header">
                <div class="mp-d-head">
                    <h4 class="card-title mb-0"><i class="fas fa-shipping-fast mr-2"></i> Pedidos de Marketplace</h4>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadOrders()"><i class="fas fa-sync"></i> Actualizar</button>
                </div>
                <div class="mp-tabs" id="mp-order-tabs"></div>
            </div>
            <div class="card-body p-0">
                {{-- Escritorio: tabla --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0 mp-table">
                        <thead><tr>
                            <th>Pedido</th><th>Cliente</th><th>Total</th>
                            <th>Límite de despacho</th><th>Progreso del pedido</th>
                        </tr></thead>
                        <tbody id="mp-orders-tbody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Cargando pedidos…</td></tr>
                        </tbody>
                    </table>
                </div>
                {{-- Móvil: tarjetas --}}
                <div class="mp-cards d-md-none" id="mp-orders-cards">
                    <div class="text-center text-muted py-4">Cargando pedidos…</div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="mp-toast-wrap" class="mp-toast-wrap"></div>

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
        if (!confirm('Esto traerá tus productos de Saga Falabella: crea los nuevos (con imágenes) y ACTUALIZA los precios (oferta/regular) de los que ya tienes. Puede tardar varios minutos. ¿Continuar?')) return;

        mpImporting = true;
        var panel = document.getElementById('mp-import-panel');
        var bar = document.getElementById('mp-import-bar');
        var status = document.getElementById('mp-import-status');
        panel.style.display = 'block';
        panel.scrollIntoView({behavior:'smooth', block:'center'});

        var totals = {created:0, updated:0, linked:0, skipped:0, failed:0, processed:0};
        var offset = 0;
        var limit = 10;
        var totalFetchedSoFar = 0;

        function setBadges(){
            document.getElementById('mp-import-created').textContent = 'Creados: ' + totals.created;
            document.getElementById('mp-import-updated').textContent = 'Precios actualizados: ' + totals.updated;
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
                totals.updated += data.updated||0;
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
                    finish('Importación completada: ' + totals.created + ' creados, ' + totals.updated + ' con precio actualizado, ' + totals.linked + ' enlazados, ' + totals.failed + ' fallidos.', true);
                } else {
                    offset = data.next_offset;
                    nextBatch();
                }
            })
            .catch(function(e){ finish('Error de red: '+e.message, false); });
        }

        nextBatch();
    };

    // ── Pedidos / Despacho ──────────────────────────────────────
    var ORDER_STATUS = {
        'pending':       {label:'Pendiente',         cls:'mp-b-warn'},
        'ready_to_ship': {label:'Listo p/ despacho', cls:'mp-b-info'},
        'shipped':       {label:'Enviado',           cls:'mp-b-primary'},
        'delivered':     {label:'Entregado',         cls:'mp-b-success'},
        'processed':     {label:'Procesado',         cls:'mp-b-success'},
        'canceled':      {label:'Cancelado',         cls:'mp-b-muted'}
    };
    var MP_TABS = [
        {key:'todispatch', label:'Por despachar', match:function(s){return s==='pending'||s==='ready_to_ship';}},
        {key:'shipped',    label:'Enviados',      match:function(s){return s==='shipped';}},
        {key:'delivered',  label:'Entregados',    match:function(s){return s==='delivered';}},
        {key:'canceled',   label:'Cancelados',    match:function(s){return s==='canceled';}},
        {key:'all',        label:'Todos',         match:function(){return true;}}
    ];
    var mpOrders = [], mpFilter = 'todispatch';

    function mpToast(msg, type){
        var wrap = document.getElementById('mp-toast-wrap');
        var t = document.createElement('div');
        t.className = 'mp-toast mp-toast-'+(type||'info');
        t.textContent = msg;
        wrap.appendChild(t);
        setTimeout(function(){ t.classList.add('show'); }, 10);
        setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){ t.remove(); }, 300); }, 3800);
    }
    function mpBadge(status){
        var s = ORDER_STATUS[status] || {label:status, cls:'mp-b-muted'};
        return '<span class="mp-badge '+s.cls+'">'+s.label+'</span>';
    }
    function mpDeadline(o){
        var raw = (o.shipping_data && o.shipping_data.promised_shipping_time) || null;
        if(!raw) return '<span class="text-muted">—</span>';
        var d = new Date(String(raw).replace(' ','T'));
        if(isNaN(d.getTime())) return '<span class="text-muted">'+raw+'</span>';
        var fmt = d.toLocaleDateString('es-PE',{day:'2-digit',month:'short'})+' '+d.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
        // Si ya está enviado/entregado/cancelado, el deadline ya no urge.
        if(o.status==='shipped'||o.status==='delivered'||o.status==='canceled'){
            return '<span class="mp-dl mp-dl-ok">'+fmt+'</span>';
        }
        var diffH = (d - new Date())/36e5;
        var cls = diffH < 0 ? 'mp-dl-over' : (diffH < 24 ? 'mp-dl-soon' : 'mp-dl-ok');
        return '<span class="mp-dl '+cls+'">'+fmt+(diffH<0?' (vencido)':'')+'</span>';
    }
    function mpShipType(o){
        var m = (o.shipping_data && o.shipping_data.method) || '';
        return m ? '<div class="mp-sub"><i class="fas fa-truck-loading"></i>'+m+'</div>' : '';
    }
    function mpTracking(o){
        var items = o.items_data;
        if(!Array.isArray(items)) items = items ? [items] : [];
        var tc = null;
        items.forEach(function(it){ if(it && it.TrackingCode) tc = it.TrackingCode; });
        return tc ? '<div class="mp-sub"><i class="fas fa-barcode"></i>'+tc+'</div>' : '';
    }
    function mpFlags(o){
        var s=o.status;
        return {
            canceled: s==='canceled',
            ready:   (s==='ready_to_ship'||s==='shipped'||s==='delivered'),
            shipped: (s==='shipped'||s==='delivered'),
            // El tenant genera su boleta: detectamos si el Order enlazado ya tiene comprobante.
            boleta:  !!(o.order && (o.order.number_document || o.order.document_external_id)),
            invoiceUploaded: !!o.invoice_uploaded_at
        };
    }
    function mpStepper(o){
        var f=mpFlags(o);
        if(f.canceled){
            return '<div class="mp-steps"><span class="mp-step warn"><span class="dot">✕</span>Cancelado</span></div>';
        }
        var steps=[
            {label:'Recibido', done:true},
            {label:'Listo',    done:f.ready},
            {label:'Boleta',   done:f.invoiceUploaded, warn:(f.ready && !f.boleta)},
            {label:'Enviado',  done:f.shipped}
        ];
        var curIdx=-1;
        for(var i=0;i<steps.length;i++){ if(!steps[i].done){ curIdx=i; break; } }
        var html='<div class="mp-steps">';
        steps.forEach(function(st,i){
            if(i>0) html+='<span class="mp-step-sep"></span>';
            var cls = st.done?'done':(st.warn?'warn':(i===curIdx?'cur':''));
            var ico = st.done?'<i class="fas fa-check"></i>':(st.warn?'!':(i+1));
            html+='<span class="mp-step '+cls+'"><span class="dot">'+ico+'</span>'+st.label+'</span>';
        });
        return html+'</div>';
    }
    function mpNext(o){
        var ch=o.channel_id, id=o.id, f=mpFlags(o);
        if(f.canceled) return '';
        var btns='';
        if(!f.ready){
            btns+='<button class="btn btn-sm mp-btn-go" onclick="orderReady('+ch+','+id+')"><i class="fas fa-check"></i> Marcar listo</button>';
        } else {
            if(!f.shipped){
                btns+='<button class="btn btn-sm mp-btn-doc" onclick="downloadDoc('+ch+','+id+',\'shippingLabel\')"><i class="fas fa-file-pdf"></i> Hoja</button>'
                    + '<button class="btn btn-sm mp-btn-ship" onclick="orderShipped('+ch+','+id+')"><i class="fas fa-truck"></i> Marcar enviado</button>';
            } else {
                btns+='<button class="btn btn-sm mp-btn-ghost" onclick="downloadDoc('+ch+','+id+',\'shippingLabel\')"><i class="fas fa-file-pdf"></i> Hoja</button>';
            }
            // Cumplimiento: boleta SUNAT + carga a Saga
            if(!f.boleta && !f.invoiceUploaded){
                btns+='<button class="btn btn-sm mp-btn-go" onclick="genInvoice('+ch+','+id+')"><i class="fas fa-file-invoice"></i> Generar boleta</button>'
                    + '<button class="btn btn-sm mp-btn-ghost" onclick="markInvoiced('+ch+','+id+')" title="El pedido ya fue facturado en otro sistema"><i class="fas fa-check"></i> Ya tiene boleta</button>';
            } else if(f.boleta && !f.invoiceUploaded){
                btns+='<button class="btn btn-sm mp-btn-ship" onclick="uploadInvoice('+ch+','+id+')"><i class="fas fa-cloud-upload-alt"></i> Subir a Saga</button>';
            }
        }
        var note='';
        if(f.ready && !f.boleta) note='<span class="mp-warn-note"><i class="fas fa-exclamation-triangle"></i>Falta generar boleta</span>';
        else if(f.boleta && !f.invoiceUploaded) note='<span class="mp-warn-note"><i class="fas fa-exclamation-triangle"></i>Falta subir a Saga</span>';
        else if(f.invoiceUploaded) note='<span class="mp-ok-note"><i class="fas fa-check-circle"></i>Boleta subida</span>';
        return '<div class="mp-nextwrap">'+btns+note+'</div>';
    }
    function renderTabs(){
        var html='';
        MP_TABS.forEach(function(t){
            var n=mpOrders.filter(function(o){return t.match(o.status);}).length;
            html+='<button class="mp-tab'+(mpFilter===t.key?' active':'')+'" onclick="mpSetFilter(\''+t.key+'\')">'+t.label+' <span class="mp-tab-n">'+n+'</span></button>';
        });
        document.getElementById('mp-order-tabs').innerHTML=html;
    }
    window.mpSetFilter=function(k){ mpFilter=k; renderTabs(); renderOrders(); };
    function renderOrders(){
        var tab=MP_TABS.filter(function(t){return t.key===mpFilter;})[0]||MP_TABS[0];
        var list=mpOrders.filter(function(o){return tab.match(o.status);});
        var tbody=document.getElementById('mp-orders-tbody');
        var cards=document.getElementById('mp-orders-cards');
        if(!list.length){
            var empty='No hay pedidos en esta vista.';
            tbody.innerHTML='<tr><td colspan="5" class="text-center text-muted py-4">'+empty+'</td></tr>';
            cards.innerHTML='<div class="text-center text-muted py-4">'+empty+'</div>';
            return;
        }
        var rows='', cs='';
        list.forEach(function(o){
            var cust=o.customer_data||{};
            var num='#'+(o.external_order_id||o.id);
            var total='S/ '+(parseFloat(o.total||0).toFixed(2));
            rows+='<tr>'
                +'<td><strong>'+num+'</strong>'+mpShipType(o)+'</td>'
                +'<td>'+(cust.name||'-')+mpTracking(o)+'</td>'
                +'<td>'+total+'</td>'
                +'<td>'+mpDeadline(o)+'</td>'
                +'<td>'+mpStepper(o)+mpNext(o)+'</td>'
                +'</tr>';
            cs+='<div class="mp-card">'
                +'<div class="mp-card-top"><strong>'+num+'</strong><span>'+total+'</span></div>'
                +'<div class="mp-card-cust">'+(cust.name||'-')+mpShipType(o)+mpTracking(o)+'</div>'
                +mpStepper(o)
                +'<div class="mp-card-meta"><span>'+mpDeadline(o)+'</span></div>'
                +'<div class="mp-card-actions">'+mpNext(o)+'</div>'
                +'</div>';
        });
        tbody.innerHTML=rows;
        cards.innerHTML=cs;
    }

    window.loadOrders = function(){
        document.getElementById('mp-orders-tbody').innerHTML='<tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>';
        fetch('/ecommerce/marketplace/orders', {headers:{'Accept':'application/json'}})
        .then(function(r){return r.json()})
        .then(function(resp){
            mpOrders = resp.data || resp || [];
            renderTabs(); renderOrders();
        })
        .catch(function(e){ document.getElementById('mp-orders-tbody').innerHTML='<tr><td colspan="6" class="text-danger text-center py-3">Error: '+e.message+'</td></tr>'; });
    };

    window.orderReady = function(channelId, orderId){
        if(!confirm('¿Marcar este pedido como LISTO para despacho en Saga? Después podrás descargar la hoja de despacho.')) return;
        fetch('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/ready', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(d){ mpToast(d.message || d.error || 'Listo', d.error?'error':'success'); loadOrders(); })
        .catch(function(e){ mpToast('Error: '+e.message, 'error'); });
    };
    window.orderShipped = function(channelId, orderId){
        if(!confirm('¿Confirmar a Saga que este pedido ya fue ENVIADO?')) return;
        fetch('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/shipped', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(d){ mpToast(d.message || d.error || 'Listo', d.error?'error':'success'); loadOrders(); })
        .catch(function(e){ mpToast('Error: '+e.message, 'error'); });
    };
    window.downloadDoc = function(channelId, orderId, type){
        window.open('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/document/'+type, '_blank');
    };

    window.genInvoice = function(channelId, orderId){
        if(!confirm('¿Generar la BOLETA electrónica (SUNAT) de este pedido? Se emitirá un comprobante fiscal.')) return;
        fetch('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/invoice', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(d){ mpToast(d.message || d.error || 'Listo', d.error?'error':'success'); loadOrders(); })
        .catch(function(e){ mpToast('Error: '+e.message, 'error'); });
    };
    window.uploadInvoice = function(channelId, orderId){
        if(!confirm('¿Subir la boleta de este pedido a Saga Falabella?')) return;
        fetch('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/upload-invoice', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(d){ mpToast(d.message || d.error || 'Listo', d.error?'error':'success'); loadOrders(); })
        .catch(function(e){ mpToast('Error: '+e.message, 'error'); });
    };
    window.markInvoiced = function(channelId, orderId){
        if(!confirm('¿Marcar que este pedido YA tiene boleta (emitida en otro sistema)? No se generará comprobante en EBAEMY.')) return;
        fetch('/ecommerce/marketplace/channels/'+channelId+'/orders/'+orderId+'/mark-invoiced', {method:'POST', headers:headers})
        .then(function(r){return r.json()})
        .then(function(d){ mpToast(d.message || d.error || 'Listo', d.error?'error':'success'); loadOrders(); })
        .catch(function(e){ mpToast('Error: '+e.message, 'error'); });
    };

    loadOrders();
});
</script>
@endsection
