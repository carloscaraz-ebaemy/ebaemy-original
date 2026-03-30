@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><i class="fas fa-boxes"></i></h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/marketplace">Marketplace</a></li>
        <li class="active"><span>Productos por Canal</span></li>
    </ol>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">
                    <i class="fas fa-th-list mr-2"></i> Asignar Productos a Canales
                </h4>
                <div class="d-flex gap-2 align-items-center">
                    <select id="channel-select" class="form-control form-control-sm" style="width:220px">
                        <option value="">Selecciona un canal</option>
                    </select>
                    <button class="btn btn-sm btn-success" id="btn-save-all" style="display:none" disabled>
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </div>
            </div>
            <div class="card-body p-0" id="products-container">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-arrow-up" style="font-size:24px;color:#d1d5db"></i>
                    <p class="mt-2">Selecciona un canal de marketplace para ver y asignar productos</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.mp-product-row { display:flex; align-items:center; padding:10px 20px; border-bottom:1px solid #f3f4f6; transition:background .1s; }
.mp-product-row:hover { background:#f9fafb; }
.mp-product-row:last-child { border-bottom:none; }
.mp-product-img { width:40px; height:40px; border-radius:6px; object-fit:cover; border:1px solid #e5e7eb; margin-right:12px; flex-shrink:0; }
.mp-product-info { flex:1; min-width:0; }
.mp-product-name { font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mp-product-code { font-size:11px; color:#9ca3af; }
.mp-product-price { font-size:13px; font-weight:700; color:#111827; min-width:80px; text-align:right; }
.mp-product-stock { font-size:12px; color:#6b7280; min-width:60px; text-align:center; }
.mp-product-sku { min-width:120px; }
.mp-product-sku input { width:100%; padding:4px 8px; border:1px solid #d1d5db; border-radius:4px; font-size:12px; }
.mp-product-toggle { min-width:50px; text-align:center; }
.mp-header { background:#f9fafb; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#6b7280; padding:10px 20px; border-bottom:1px solid #e5e7eb; }
.mp-stats { display:flex; gap:1.5rem; padding:12px 20px; background:#f0fdf4; border-bottom:1px solid #dcfce7; font-size:13px; }
.mp-stats strong { color:#16a34a; }
.mp-select-all { display:flex; align-items:center; gap:8px; padding:8px 20px; background:#fefce8; border-bottom:1px solid #fef08a; font-size:12px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};
    var container = document.getElementById('products-container');
    var btnSave = document.getElementById('btn-save-all');
    var channelSelect = document.getElementById('channel-select');
    var currentChannelId = null;
    var allProducts = [];
    var mappedProducts = {};
    var changes = {};

    // Cargar canales
    fetch('/ecommerce/marketplace/channels', {headers:{'Accept':'application/json'}})
    .then(function(r){return r.json()})
    .then(function(channels){
        channels.forEach(function(ch){
            if(ch.status === 'active') {
                var opt = document.createElement('option');
                opt.value = ch.id;
                opt.textContent = ch.name + ' (' + ch.platform + ')';
                channelSelect.appendChild(opt);
            }
        });
    });

    channelSelect.addEventListener('change', function(){
        currentChannelId = this.value;
        if(!currentChannelId) {
            container.innerHTML = '<div class="text-center text-muted py-5">Selecciona un canal</div>';
            btnSave.style.display = 'none';
            return;
        }
        loadProducts();
    });

    function loadProducts(){
        container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
        btnSave.style.display = 'inline-block';
        btnSave.disabled = true;
        changes = {};

        // Cargar productos del catálogo y los ya mapeados en paralelo
        Promise.all([
            fetch('/ecommerce/items_ecommerce/records_all', {headers:{'Accept':'application/json'}}).then(function(r){return r.json()}),
            fetch('/ecommerce/marketplace/channels/'+currentChannelId+'/products', {headers:{'Accept':'application/json'}}).then(function(r){return r.json()})
        ]).then(function(results){
            allProducts = results[0].data || results[0] || [];
            var mapped = results[1] || [];
            mappedProducts = {};
            mapped.forEach(function(mp){ mappedProducts[mp.item_id] = mp; });
            renderProducts();
        }).catch(function(e){
            container.innerHTML = '<div class="text-center text-danger py-4">Error: '+e.message+'</div>';
        });
    }

    function renderProducts(){
        var assignedCount = Object.keys(mappedProducts).length;
        var totalCount = allProducts.length;

        var html = '';

        // Stats
        html += '<div class="mp-stats">'
            + '<span><strong>'+assignedCount+'</strong> asignados</span>'
            + '<span><strong>'+totalCount+'</strong> productos totales</span>'
            + '<span><strong>'+(totalCount-assignedCount)+'</strong> sin asignar</span>'
            + '</div>';

        // Select all
        html += '<div class="mp-select-all">'
            + '<input type="checkbox" id="mp-select-all-chk" '+(assignedCount === totalCount ? 'checked' : '')+'>'
            + '<label for="mp-select-all-chk" style="cursor:pointer;margin:0">Seleccionar / Deseleccionar todos</label>'
            + '</div>';

        // Header
        html += '<div class="mp-header d-flex">'
            + '<div style="width:50px"></div>'
            + '<div style="flex:1">Producto</div>'
            + '<div style="min-width:80px;text-align:right">Precio</div>'
            + '<div style="min-width:60px;text-align:center">Stock</div>'
            + '<div style="min-width:120px;text-align:center">SKU externo</div>'
            + '<div style="min-width:50px;text-align:center">Activo</div>'
            + '</div>';

        // Products
        allProducts.forEach(function(p){
            var mapped = mappedProducts[p.id];
            var isActive = !!mapped;
            var extSku = mapped ? (mapped.external_sku || '') : '';
            var imgSrc = (p.image && p.image !== 'imagen-no-disponible.jpg')
                ? '/storage/uploads/items/' + (p.image_small || p.image)
                : '/logo/imagen-no-disponible.jpg';

            html += '<div class="mp-product-row" data-id="'+p.id+'">'
                + '<div class="mp-product-toggle"><input type="checkbox" class="mp-chk" data-id="'+p.id+'" '+(isActive?'checked':'')+'></div>'
                + '<img src="'+imgSrc+'" class="mp-product-img" alt="" onerror="this.src=\'/logo/imagen-no-disponible.jpg\'">'
                + '<div class="mp-product-info">'
                + '<div class="mp-product-name">'+p.description+'</div>'
                + '<div class="mp-product-code">'+(p.internal_id||'')+'</div>'
                + '</div>'
                + '<div class="mp-product-price">S/ '+parseFloat(p.sale_unit_price).toFixed(2)+'</div>'
                + '<div class="mp-product-stock">'+(p.stock||0)+'</div>'
                + '<div class="mp-product-sku"><input type="text" class="mp-sku" data-id="'+p.id+'" value="'+extSku+'" placeholder="SKU externo"></div>'
                + '</div>';
        });

        container.innerHTML = html;

        // Event: checkbox change
        container.querySelectorAll('.mp-chk').forEach(function(chk){
            chk.addEventListener('change', function(){
                changes[this.dataset.id] = {active: this.checked, id: parseInt(this.dataset.id)};
                btnSave.disabled = false;
            });
        });

        // Event: SKU change
        container.querySelectorAll('.mp-sku').forEach(function(input){
            input.addEventListener('input', function(){
                if(!changes[this.dataset.id]) changes[this.dataset.id] = {id: parseInt(this.dataset.id), active: !!mappedProducts[this.dataset.id]};
                changes[this.dataset.id].sku = this.value;
                btnSave.disabled = false;
            });
        });

        // Event: select all
        var selectAllChk = document.getElementById('mp-select-all-chk');
        if(selectAllChk) {
            selectAllChk.addEventListener('change', function(){
                var checked = this.checked;
                container.querySelectorAll('.mp-chk').forEach(function(chk){
                    chk.checked = checked;
                    changes[chk.dataset.id] = {active: checked, id: parseInt(chk.dataset.id)};
                });
                btnSave.disabled = false;
            });
        }
    }

    // Save
    btnSave.addEventListener('click', function(){
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        var items = Object.values(changes).map(function(c){
            var skuInput = container.querySelector('.mp-sku[data-id="'+c.id+'"]');
            return {item_id: c.id, active: c.active, external_sku: skuInput ? skuInput.value : ''};
        });

        fetch('/ecommerce/marketplace/channels/'+currentChannelId+'/save-products', {
            method: 'POST', headers: headers,
            body: JSON.stringify({items: items})
        })
        .then(function(r){return r.json()})
        .then(function(data){
            btnSave.innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
            if(data.success) {
                btnSave.innerHTML = '<i class="fas fa-check"></i> Guardado';
                setTimeout(function(){ btnSave.innerHTML = '<i class="fas fa-save"></i> Guardar cambios'; }, 2000);
                loadProducts();
            } else {
                alert(data.message || 'Error');
                btnSave.disabled = false;
            }
        });
    });
});
</script>
@endsection
