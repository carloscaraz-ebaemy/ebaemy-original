@extends('system.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <h2>Dominios del Cliente: {{ $client->name }}</h2>
        <a href="{{ route('system.clients.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div id="domains-app">

        {{-- Dominios activos --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-globe"></i> Dominios Activos</h5>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('addDomainModal').style.display='block'">
                    <i class="fas fa-plus"></i> Agregar Dominio
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Dominio</th><th>Tipo</th><th>SSL</th><th>Principal</th><th>Acciones</th></tr></thead>
                    <tbody id="domains-tbody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Verificaciones pendientes --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt"></i> Verificaciones DNS</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Dominio</th><th>Método</th><th>Estado</th><th>Instrucciones</th><th>Acciones</th></tr></thead>
                    <tbody id="verifications-tbody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal agregar dominio --}}
        <div id="addDomainModal" style="display:none;position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.5);display:none">
            <div style="background:#fff;max-width:480px;margin:100px auto;border-radius:8px;padding:24px">
                <h5>Agregar Dominio Personalizado</h5>
                <div class="form-group mt-3">
                    <label>Dominio</label>
                    <input type="text" class="form-control" id="newDomain" placeholder="tienda.micliente.com">
                </div>
                <div class="form-group">
                    <label>Método de verificación</label>
                    <select class="form-control" id="newMethod">
                        <option value="dns_cname">CNAME (recomendado)</option>
                        <option value="dns_txt">TXT Record</option>
                    </select>
                </div>
                <div class="mt-3 d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary" onclick="document.getElementById('addDomainModal').style.display='none'">Cancelar</button>
                    <button class="btn btn-primary" id="btnAddDomain">Agregar</button>
                </div>
                <p id="addDomainMsg" class="mt-2 small" style="display:none"></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var clientId = {{ $client->id }};
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var headers = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'};

    loadData();

    function loadData(){
        fetch('/clients/'+clientId+'/domains',{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json()})
        .then(function(data){
            renderDomains(data.data||[]);
            renderVerifications(data.verifications||[]);
        });
    }

    function renderDomains(domains){
        var tbody=document.getElementById('domains-tbody');
        if(!domains.length){tbody.innerHTML='<tr><td colspan="6" class="text-center text-muted py-3">Sin dominios</td></tr>';return;}
        var html='';
        domains.forEach(function(d){
            var typeBadge=d.domain_type==='custom'
                ?'<span class="badge badge-info">Custom</span>'
                :'<span class="badge badge-secondary">Subdominio</span>';
            var sslBadge={'active':'<span class="badge badge-success">Activo</span>','pending':'<span class="badge badge-warning">Pendiente</span>','none':'<span class="badge badge-light">—</span>'}[d.ssl_status]||'<span class="badge badge-light">—</span>';
            var primaryCol=d.is_primary
                ?'<span class="badge badge-success"><i class="fas fa-check"></i> Principal</span>'
                :'<button class="btn btn-xs btn-outline-primary" onclick="setPrimary('+d.id+')">Hacer principal</button>';
            var redirectCol=d.is_primary
                ?''
                :'<button class="btn btn-xs '+(d.redirect_to_primary?'btn-warning':'btn-outline-secondary')+'" onclick="toggleRedirect('+d.id+')" title="'+(d.redirect_to_primary?'Redirección activa':'Activar redirección al principal')+'">'
                +'<i class="fas fa-'+(d.redirect_to_primary?'exchange-alt':'arrow-right')+'"></i> '+(d.redirect_to_primary?'Redirigiendo':'Redirigir')
                +'</button>';

            var actions='<div class="d-flex gap-1 flex-wrap">';
            // Eliminar (solo custom, no el subdominio principal)
            if(d.domain_type==='custom'){
                actions+='<button class="btn btn-xs btn-outline-danger" onclick="removeDomain('+d.id+',\''+d.fqdn+'\')" title="Eliminar"><i class="fas fa-trash"></i> Eliminar</button>';
            }
            // Cambiar subdominio
            if(d.domain_type!=='custom'){
                actions+='<button class="btn btn-xs btn-outline-info" onclick="changeSubdomain('+d.id+',\''+d.fqdn+'\')" title="Cambiar subdominio"><i class="fas fa-edit"></i> Cambiar</button>';
            }
            actions+='</div>';

            html+='<tr>'
                +'<td><strong>'+d.fqdn+'</strong></td>'
                +'<td>'+typeBadge+'</td>'
                +'<td>'+sslBadge+'</td>'
                +'<td>'+primaryCol+' '+redirectCol+'</td>'
                +'<td>'+actions+'</td>'
                +'</tr>';
        });
        tbody.innerHTML=html;
    }

    function renderVerifications(vfs){
        var tbody=document.getElementById('verifications-tbody');
        if(!vfs.length){tbody.innerHTML='<tr><td colspan="5" class="text-center text-muted py-3">Sin verificaciones</td></tr>';return;}
        var html='';
        vfs.forEach(function(v){
            var statusBadge={'pending':'<span class="badge badge-warning">Pendiente</span>','verified':'<span class="badge badge-success">Verificado</span>','failed':'<span class="badge badge-danger">Fallido</span>'}[v.status]||v.status;
            var instr=v.instructions?'<code>'+v.instructions.type+': '+v.instructions.value+'</code>':'';
            var actions=v.status==='pending'?'<button class="btn btn-xs btn-primary" onclick="verifyDomain('+v.id+')">Verificar ahora</button>':'';
            html+='<tr><td>'+v.domain+'</td><td>'+v.method+'</td><td>'+statusBadge+'</td><td style="max-width:300px;overflow:hidden;text-overflow:ellipsis">'+instr+'</td><td>'+actions+'</td></tr>';
        });
        tbody.innerHTML=html;
    }

    document.getElementById('btnAddDomain').addEventListener('click',function(){
        var domain=document.getElementById('newDomain').value;
        var method=document.getElementById('newMethod').value;
        var msg=document.getElementById('addDomainMsg');
        if(!domain){msg.style.display='block';msg.className='mt-2 small text-danger';msg.textContent='Ingrese un dominio';return;}
        fetch('/clients/'+clientId+'/domains',{method:'POST',headers:headers,body:JSON.stringify({domain:domain,method:method})})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){document.getElementById('addDomainModal').style.display='none';loadData();}
            else{msg.style.display='block';msg.className='mt-2 small text-danger';msg.textContent=data.message;}
        });
    });

    window.setPrimary=function(id){
        fetch('/hostnames/'+id+'/set-primary',{method:'POST',headers:headers}).then(function(){loadData();});
    };
    window.removeDomain=function(id, fqdn){
        if(!confirm('¿Eliminar el dominio "'+fqdn+'"? Esta acción no se puede deshacer.'))return;
        fetch('/hostnames/'+id,{method:'DELETE',headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){loadData();}
            else{alert(data.message||'No se pudo eliminar');}
        });
    };

    window.toggleRedirect=function(id){
        fetch('/hostnames/'+id+'/toggle-redirect',{method:'POST',headers:headers})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success) loadData();
        });
    };

    window.changeSubdomain=function(id, currentFqdn){
        var newSub = prompt('Ingrese el nuevo subdominio (sin el dominio base).\n\nActual: '+currentFqdn+'\n\nEjemplo: miempresa', '');
        if(!newSub || !newSub.trim()) return;
        newSub = newSub.trim().toLowerCase().replace(/[^a-z0-9-]/g,'');
        if(!newSub){alert('Subdominio inválido. Solo letras, números y guiones.');return;}
        if(!confirm('¿Cambiar subdominio de "'+currentFqdn+'" a "'+newSub+'"?\n\nEl cliente deberá usar la nueva URL.')){return;}
        fetch('/hostnames/'+id+'/change-subdomain',{method:'POST',headers:headers,body:JSON.stringify({subdomain:newSub})})
        .then(function(r){return r.json()})
        .then(function(data){
            alert(data.message);
            if(data.success) loadData();
        });
    };
    window.verifyDomain=function(id){
        fetch('/domains/'+id+'/verify',{method:'POST',headers:headers}).then(function(r){return r.json()}).then(function(data){
            alert(data.message);loadData();
        });
    };
});
</script>
@endsection
