@extends('tenant.layouts.app')

@section('content')
<div class="page-header pr-0">
    <h2><a href="/ecommerce/configuration"><i class="fas fa-palette"></i></a></h2>
    <ol class="breadcrumbs">
        <li><a href="/ecommerce/configuration">Tienda Virtual</a></li>
        <li class="active"><span>Temas</span></li>
    </ol>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="fas fa-palette mr-2"></i> Elige el tema de tu tienda
                </h4>
                <span class="text-muted" id="theme-current-label" style="font-size:13px"></span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4" style="font-size:13px">
                    Selecciona un tema para personalizar la apariencia de tu tienda online.
                    El cambio se aplica inmediatamente.
                </p>

                <div class="row" id="themes-gallery">
                    <div class="col-12 text-center py-4 text-muted">Cargando temas...</div>
                </div>

                <p id="theme-msg" style="display:none;margin-top:12px;font-size:13px" class="text-center"></p>
            </div>
        </div>
    </div>
</div>

<style>
.theme-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s, transform .15s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.theme-card:hover {
    border-color: #9ca3af;
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    transform: translateY(-2px);
}
.theme-card--active {
    border-color: #16a34a !important;
    box-shadow: 0 0 0 3px rgba(22,163,74,.15) !important;
}
.theme-card__preview {
    height: 160px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.theme-card__preview-icon {
    font-size: 48px;
    color: #d1d5db;
}
.theme-card__preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.theme-card__badge {
    position: absolute;
    top: 8px;
    right: 8px;
}
.theme-card__body {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.theme-card__name {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}
.theme-card__desc {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 8px;
    flex: 1;
    line-height: 1.5;
}
.theme-card__tags {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.theme-card__tag {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.theme-card__tag--nicho { background: #dbeafe; color: #1d4ed8; }
.theme-card__tag--general { background: #f3f4f6; color: #374151; }
.theme-card__tag--premium { background: #fef3c7; color: #92400e; }
.theme-card__active-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 700;
    color: #16a34a;
}
.theme-card__btn {
    width: 100%;
    padding: 8px;
    border: 1.5px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
}
.theme-card__btn:hover {
    background: #111827;
    color: #fff;
    border-color: #111827;
}
.theme-card--active .theme-card__btn {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
    cursor: default;
}

/* Preview colors por theme */
.theme-preview--default { background: linear-gradient(135deg, #f9fafb, #e5e7eb); }
.theme-preview--ropa { background: linear-gradient(135deg, #fdf2f8, #e5e7eb); }
.theme-preview--ropa-urbana { background: linear-gradient(135deg, #fce7f3, #c084fc); }
.theme-preview--ropa-elegante { background: linear-gradient(135deg, #f5f5f4, #d6d3d1); }
.theme-preview--tecnologia { background: linear-gradient(135deg, #0f172a, #1e3a5f); }
.theme-preview--alimentos { background: linear-gradient(135deg, #fef7ed, #fed7aa); }
.theme-preview--deportes { background: linear-gradient(135deg, #111827, #374151); }
.theme-preview--lujo { background: linear-gradient(135deg, #0c0a09, #44403c); }
.theme-preview--farmacia { background: linear-gradient(135deg, #f0f9ff, #bae6fd); }
.theme-preview--ferreteria { background: linear-gradient(135deg, #1c1917, #44403c); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var grid = document.getElementById('themes-gallery');
    var msg = document.getElementById('theme-msg');
    var label = document.getElementById('theme-current-label');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};
    var currentId = null;
    var allThemes = [];

    // Iconos por theme slug
    var themeIcons = {
        'default': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
        'ropa': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="1"><path d="M4 3h16l-2 7h-12z"/><path d="M6 10v11h12V10"/><path d="M10 3v4m4-4v4"/></svg>',
        'ropa-urbana': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1"><path d="M12 3l8 4v8l-8 4-8-4V7z"/><path d="M12 11l8-4M12 11v10M12 11L4 7"/></svg>',
        'ropa-elegante': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#78716c" stroke-width="1"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg>',
        'tecnologia': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="1"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'alimentos': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="1"><path d="M19 3v12h-5c-.023-3.681.184-7.406 5-12zm0 12v6h-1v-3m-10-14v17m-3-17v3a3 3 0 106 0v-3"/></svg>',
        'deportes': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1"><circle cx="12" cy="12" r="9"/><path d="M12 3a15 15 0 014 9 15 15 0 01-4 9 15 15 0 01-4-9 15 15 0 014-9z"/></svg>',
        'lujo': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#a18248" stroke-width="1"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>',
        'farmacia': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="1"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/><line x1="12" y1="2" x2="12" y2="4"/></svg>',
        'ferreteria': '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#a8a29e" stroke-width="1"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>',
    };

    fetch('/ecommerce/configuration_themes', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json()})
    .then(function(data){
        currentId = data.current_theme_id;
        allThemes = data.themes;
        render();
    })
    .catch(function(e){
        grid.innerHTML = '<div class="col-12 text-danger text-center py-4">Error al cargar temas: '+e.message+'</div>';
    });

    function render(){
        var activeTheme = allThemes.find(function(t){return t.id == currentId});
        label.textContent = activeTheme ? 'Tema actual: ' + activeTheme.name : '';

        var html = '';
        allThemes.forEach(function(t){
            var isActive = currentId == t.id;
            var icon = themeIcons[t.slug] || themeIcons['default'];
            var tags = '';
            tags += '<span class="theme-card__tag theme-card__tag--' + t.category + '">' + (t.category==='nicho'?'Especializado':'General') + '</span>';
            if(t.is_premium) tags += '<span class="theme-card__tag theme-card__tag--premium">Premium</span>';

            html += '<div class="col-md-4 col-lg-3 mb-4">'
                + '<div class="theme-card' + (isActive?' theme-card--active':'') + '" onclick="selectTheme('+t.id+',\''+t.slug+'\',\''+t.category+'\')">'
                + '<div class="theme-card__preview theme-preview--'+t.slug+'">' + icon + '</div>'
                + '<div class="theme-card__body">'
                + '<div class="theme-card__name">' + t.name + '</div>'
                + '<div class="theme-card__desc">' + (t.description||'') + '</div>'
                + '<div class="theme-card__tags">' + tags + '</div>'
                + (isActive
                    ? '<div class="theme-card__active-badge"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Tema activo</div>'
                    : '<button class="theme-card__btn">Activar este tema</button>')
                + '</div></div></div>';
        });
        grid.innerHTML = html;
    }

    window.selectTheme = function(id, slug, category){
        if(id == currentId) return;
        var body = JSON.stringify({
            theme_id: id,
            ecommerce_mode: category === 'nicho' ? 'nicho' : 'general',
            business_type: slug !== 'default' ? slug : null
        });
        fetch('/ecommerce/configuration_theme', {method:'POST', headers:headers, body:body})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                currentId = id;
                render();
                msg.style.display = 'block';
                msg.className = 'text-center text-success';
                msg.innerHTML = '<i class="fas fa-check-circle"></i> Tema actualizado. <a href="/ecommerce" target="_blank" style="text-decoration:underline">Ver tienda</a>';
                setTimeout(function(){ msg.style.display = 'none'; }, 8000);
            }
        })
        .catch(function(e){ alert('Error: ' + e.message); });
    };
});
</script>
@endsection
