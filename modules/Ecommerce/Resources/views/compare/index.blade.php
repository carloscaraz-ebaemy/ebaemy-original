@extends('ecommerce::layouts.master')

@section('page_title', 'Comparar productos | ' . ($company->trade_name ?? $company->name ?? 'Tienda Online'))
@section('meta_description', 'Compara productos lado a lado para tomar la mejor decision.')

@section('content')
<div class="container" style="padding-top: 8rem; padding-bottom: 3rem">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700">Comparar productos</h1>
            <p class="text-muted mb-0" id="compare-subtitle">Selecciona hasta 4 productos para comparar</p>
        </div>
        <a href="/ecommerce" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Seguir comprando</a>
    </div>

    {{-- Estado vacio --}}
    <div id="compare-empty" style="display:none" class="text-center py-5">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M3 3h18v18H3z"/><path d="M12 3v18"/><path d="M3 12h18"/></svg>
        <h4 class="mt-3 text-muted">No hay productos para comparar</h4>
        <p class="text-muted">Agrega productos desde la tienda usando el boton <i class="fas fa-exchange-alt"></i></p>
        <a href="/ecommerce" class="btn btn-primary mt-2">Ir a la tienda</a>
    </div>

    {{-- Tabla comparativa --}}
    <div id="compare-table-wrap" style="display:none">
        <div class="table-responsive">
            <table class="table table-bordered text-center" id="compare-table">
                <thead id="compare-thead"></thead>
                <tbody id="compare-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
#compare-table th, #compare-table td { vertical-align: middle; min-width: 200px; }
#compare-table .compare-img { width: 120px; height: 120px; object-fit: contain; border-radius: 8px; }
#compare-table .compare-price { font-size: 1.3rem; font-weight: 700; color: #4F46E5; }
#compare-table .compare-stock-ok { color: #10B981; font-weight: 600; }
#compare-table .compare-stock-no { color: #EF4444; font-weight: 600; }
#compare-table .compare-remove { cursor: pointer; color: #EF4444; font-size: 0.85rem; }
#compare-table .compare-add-btn { display: inline-block; padding: 6px 16px; background: #4F46E5; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85rem; }
.compare-label { font-weight: 600; background: #f8f9fa; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var KEY = 'ec_compare_ids';

    function getIds(){
        try { return JSON.parse(localStorage.getItem(KEY) || '[]').slice(0,4); }
        catch(e){ return []; }
    }

    function render(){
        var ids = getIds();
        if(!ids.length){
            document.getElementById('compare-empty').style.display = 'block';
            document.getElementById('compare-table-wrap').style.display = 'none';
            return;
        }
        document.getElementById('compare-empty').style.display = 'none';
        document.getElementById('compare-table-wrap').style.display = 'block';
        document.getElementById('compare-subtitle').textContent = ids.length + ' producto' + (ids.length>1?'s':'') + ' seleccionado' + (ids.length>1?'s':'');

        fetch('/ecommerce/api/items-compare?ids=' + ids.join(','), {headers:{'Accept':'application/json'}})
        .then(function(r){ return r.json(); })
        .then(function(items){
            if(!items.length){
                document.getElementById('compare-empty').style.display = 'block';
                document.getElementById('compare-table-wrap').style.display = 'none';
                return;
            }
            var thead = '<tr><th class="compare-label" style="width:120px">Producto</th>';
            items.forEach(function(it){
                thead += '<th>'
                    + '<img src="'+it.image+'" class="compare-img mb-2" alt="'+it.description+'"><br>'
                    + '<a href="/ecommerce/item/'+it.slug+'" style="font-weight:600;color:#333">'+it.description+'</a><br>'
                    + '<span class="compare-remove" onclick="removeCompare('+it.id+')"><i class="fas fa-times"></i> Quitar</span>'
                    + '</th>';
            });
            thead += '</tr>';
            document.getElementById('compare-thead').innerHTML = thead;

            var rows = [
                {label: 'Precio', key: function(it){ return '<span class="compare-price">'+it.currency+' '+Number(it.price).toFixed(2)+'</span>'; }},
                {label: 'Disponibilidad', key: function(it){ return it.stock > 0 ? '<span class="compare-stock-ok"><i class="fas fa-check-circle"></i> En stock ('+it.stock+')</span>' : '<span class="compare-stock-no"><i class="fas fa-times-circle"></i> Agotado</span>'; }},
                {label: 'Categoria', key: function(it){ return it.category; }},
                {label: 'Marca', key: function(it){ return it.brand; }},
                {label: 'SKU', key: function(it){ return it.internal_id || '-'; }},
                {label: 'Variantes', key: function(it){
                    if(!it.has_variants || !it.variants.length) return '<span class="text-muted">Sin variantes</span>';
                    return it.variants.map(function(v){ return v.name + ' ('+it.currency+' '+Number(v.price).toFixed(2)+')'; }).join('<br>');
                }},
                {label: '', key: function(it){ return '<a href="/ecommerce/item/'+it.slug+'" class="compare-add-btn"><i class="fas fa-shopping-cart"></i> Ver producto</a>'; }},
            ];

            var tbody = '';
            rows.forEach(function(r){
                tbody += '<tr><td class="compare-label">'+r.label+'</td>';
                items.forEach(function(it){ tbody += '<td>'+r.key(it)+'</td>'; });
                tbody += '</tr>';
            });
            document.getElementById('compare-tbody').innerHTML = tbody;
        });
    }

    window.removeCompare = function(id){
        var ids = getIds().filter(function(x){ return x !== id; });
        localStorage.setItem(KEY, JSON.stringify(ids));
        render();
        window.dispatchEvent(new Event('compare-updated'));
    };

    render();
});
</script>
@endsection
