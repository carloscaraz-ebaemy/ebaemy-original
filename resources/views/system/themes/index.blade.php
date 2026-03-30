@extends('system.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <h2>Gestión de Themes</h2>
        <p class="text-muted">Administra los themes disponibles para las empresas del sistema.</p>
    </div>

    <div class="mb-3 d-flex justify-content-between align-items-center">
        <span class="text-muted" id="themes-count"></span>
        <button class="btn btn-primary" id="btn-new-theme">
            <i class="fa fa-plus"></i> Nuevo Theme
        </button>
    </div>

    <div class="row" id="themes-grid">
        <div class="col-12 text-center py-4 text-muted">Cargando themes...</div>
    </div>
</div>

{{-- Modal formulario --}}
<div id="themeFormOverlay" style="display:none;position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.5)">
    <div style="background:#fff;max-width:520px;margin:80px auto;border-radius:8px;padding:24px;max-height:80vh;overflow-y:auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" id="formTitle">Nuevo Theme</h5>
            <button type="button" style="background:none;border:none;font-size:24px;cursor:pointer" onclick="closeForm()">&times;</button>
        </div>
        <input type="hidden" id="frmId">
        <div class="form-group mb-2">
            <label class="form-label fw-bold">Nombre</label>
            <input type="text" class="form-control" id="frmName" placeholder="Moda & Ropa">
        </div>
        <div class="form-group mb-2">
            <label class="form-label fw-bold">Slug</label>
            <input type="text" class="form-control" id="frmSlug" placeholder="ropa">
        </div>
        <div class="form-group mb-2">
            <label class="form-label fw-bold">Carpeta</label>
            <input type="text" class="form-control" id="frmPath" placeholder="ropa">
            <small class="text-muted">Carpeta en resources/views/themes/</small>
        </div>
        <div class="form-group mb-2">
            <label class="form-label fw-bold">CSS Template</label>
            <select class="form-control" id="frmCss">
                <option value="">generic (sin CSS extra)</option>
                <option value="fashion">fashion</option>
                <option value="tech">tech</option>
                <option value="food">food</option>
                <option value="sports">sports</option>
                <option value="luxury">luxury</option>
                <option value="pharmacy">pharmacy</option>
                <option value="hardware">hardware</option>
            </select>
        </div>
        <div class="form-group mb-2">
            <label class="form-label fw-bold">Descripción</label>
            <textarea class="form-control" id="frmDesc" rows="2"></textarea>
        </div>
        <div class="form-group mb-2">
            <label class="form-label fw-bold">Categoría</label>
            <select class="form-control" id="frmCategory">
                <option value="general">General</option>
                <option value="nicho">Nicho</option>
            </select>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="frmActive" checked>
            <label class="form-check-label" for="frmActive">Activo</label>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="frmPremium">
            <label class="form-check-label" for="frmPremium">Premium</label>
        </div>
        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-secondary" onclick="closeForm()">Cancelar</button>
            <button class="btn btn-primary" id="btnSaveTheme">Guardar</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};
    var allThemes = [];

    loadThemes();

    function loadThemes(){
        fetch('/themes/records',{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json()})
        .then(function(data){
            allThemes = data.data || [];
            document.getElementById('themes-count').textContent = allThemes.length + ' themes registrados';
            renderGrid();
        })
        .catch(function(e){
            document.getElementById('themes-grid').innerHTML = '<div class="col-12 text-danger">Error: '+e.message+'</div>';
        });
    }

    function renderGrid(){
        var html = '';
        allThemes.forEach(function(t){
            var statusBadge = t.is_active
                ? '<span class="badge badge-success">Activo</span>'
                : '<span class="badge badge-secondary">Inactivo</span>';
            var premiumBadge = t.is_premium ? ' <span class="badge badge-warning">Premium</span>' : '';
            var categoryBadge = '<span class="badge badge-info">' + t.category + '</span>';
            var folderIcon = t.folder_exists
                ? '<span class="text-success"><i class="fa fa-check-circle"></i> Carpeta</span>'
                : '<span class="text-danger"><i class="fa fa-times-circle"></i> Carpeta</span>';
            var cssIcon = t.css_exists
                ? ' <span class="text-success"><i class="fa fa-check-circle"></i> CSS</span>'
                : ' <span class="text-muted"><i class="fa fa-minus-circle"></i> CSS</span>';

            var toggleBtn = t.slug !== 'default'
                ? '<button class="btn btn-sm '+(t.is_active?'btn-outline-warning':'btn-outline-success')+'" onclick="toggleTheme('+t.id+')">'+(t.is_active?'Desactivar':'Activar')+'</button>'
                : '';
            var deleteBtn = t.slug !== 'default'
                ? '<button class="btn btn-sm btn-outline-danger" onclick="deleteTheme('+t.id+',\''+t.name+'\')"><i class="fa fa-trash"></i></button>'
                : '';

            html += '<div class="col-md-4 col-lg-3 mb-4">'
                + '<div class="card h-100" style="border-width:2px;border-color:'+(t.is_active?'#22c55e':'#d1d5db')+'">'
                + '<div class="card-body">'
                + '<div class="d-flex justify-content-between align-items-start mb-2">'
                + '<h5 class="card-title mb-0">'+t.name+'</h5>'
                + statusBadge
                + '</div>'
                + '<p class="text-muted small mb-1">Carpeta: <code>'+t.path+'</code></p>'
                + '<p class="text-muted small mb-1">CSS: <code>'+(t.css_template||'generic')+'</code></p>'
                + '<p class="small mb-2">'+categoryBadge+premiumBadge+'</p>'
                + '<p class="small mb-2">'+(t.description||'Sin descripción')+'</p>'
                + '<div class="small mb-2">'+folderIcon+' &nbsp; '+cssIcon+'</div>'
                + '</div>'
                + '<div class="card-footer bg-transparent d-flex justify-content-between">'
                + '<button class="btn btn-sm btn-outline-primary" onclick="editTheme('+t.id+')"><i class="fa fa-edit"></i> Editar</button>'
                + toggleBtn + ' ' + deleteBtn
                + '</div>'
                + '</div></div>';
        });
        document.getElementById('themes-grid').innerHTML = html || '<div class="col-12 text-muted text-center py-4">No hay themes</div>';
    }

    // Nuevo
    document.getElementById('btn-new-theme').addEventListener('click', function(){
        document.getElementById('frmId').value = '';
        document.getElementById('frmName').value = '';
        document.getElementById('frmSlug').value = '';
        document.getElementById('frmPath').value = '';
        document.getElementById('frmCss').value = '';
        document.getElementById('frmDesc').value = '';
        document.getElementById('frmCategory').value = 'general';
        document.getElementById('frmActive').checked = true;
        document.getElementById('frmPremium').checked = false;
        document.getElementById('formTitle').textContent = 'Nuevo Theme';
        document.getElementById('themeFormOverlay').style.display = 'block';
    });

    // Editar
    window.editTheme = function(id){
        var t = allThemes.find(function(x){return x.id===id});
        if(!t) return;
        document.getElementById('frmId').value = t.id;
        document.getElementById('frmName').value = t.name;
        document.getElementById('frmSlug').value = t.slug;
        document.getElementById('frmPath').value = t.path;
        document.getElementById('frmCss').value = t.css_template||'';
        document.getElementById('frmDesc').value = t.description||'';
        document.getElementById('frmCategory').value = t.category;
        document.getElementById('frmActive').checked = t.is_active;
        document.getElementById('frmPremium').checked = t.is_premium;
        document.getElementById('formTitle').textContent = 'Editar Theme';
        document.getElementById('themeFormOverlay').style.display = 'block';
    };

    // Guardar
    document.getElementById('btnSaveTheme').addEventListener('click', function(){
        var body = {
            id: document.getElementById('frmId').value||null,
            name: document.getElementById('frmName').value,
            slug: document.getElementById('frmSlug').value,
            path: document.getElementById('frmPath').value,
            css_template: document.getElementById('frmCss').value||null,
            description: document.getElementById('frmDesc').value,
            category: document.getElementById('frmCategory').value,
            is_active: document.getElementById('frmActive').checked,
            is_premium: document.getElementById('frmPremium').checked,
        };
        fetch('/themes',{method:'POST',headers:headers,body:JSON.stringify(body)})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){closeForm();loadThemes();}
            else alert(data.message||'Error');
        }).catch(function(e){alert('Error: '+e.message)});
    });

    // Toggle
    window.toggleTheme = function(id){
        fetch('/themes/toggle/'+id,{method:'POST',headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){if(data.success)loadThemes();});
    };

    // Delete
    window.deleteTheme = function(id,name){
        if(!confirm('¿Eliminar "'+name+'"?'))return;
        fetch('/themes/'+id,{method:'DELETE',headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){if(data.success)loadThemes();});
    };

    // Close form
    window.closeForm = function(){
        document.getElementById('themeFormOverlay').style.display = 'none';
    };
});
</script>
@endsection
