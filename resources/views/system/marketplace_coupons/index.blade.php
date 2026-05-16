@extends('tenant.layouts.app')

@section('content')
<div class="container-fluid" id="mkt-coupons-app" style="padding:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
        <h3 style="margin:0">Cupones de Plataforma</h3>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('mkt-coupon-form').style.display='block'">+ Nuevo cupon</button>
    </div>

    <p style="color:#64748b;font-size:13.5px;margin:0 0 18px">
        Cupones gestionados por SuperAdmin asignables a usuarios del marketplace. Distintos a los <code>tenant.coupons</code> (publicos por tienda).
    </p>

    <div id="mkt-coupon-form" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px;margin-bottom:18px">
        <h5>Nuevo cupon</h5>
        <form onsubmit="return mktCreateCoupon(event)">
            <div class="row" style="gap:10px 0">
                <div class="col-md-4">
                    <label>Codigo</label>
                    <input name="code" required maxlength="40" class="form-control" placeholder="VERANO25">
                </div>
                <div class="col-md-8">
                    <label>Nombre interno</label>
                    <input name="name" required maxlength="100" class="form-control" placeholder="Promo verano 25%">
                </div>
                <div class="col-md-3">
                    <label>Tipo</label>
                    <select name="type" class="form-control"><option value="percent">% Porcentual</option><option value="fixed">S/ Fijo</option></select>
                </div>
                <div class="col-md-3">
                    <label>Valor</label>
                    <input name="value" type="number" step="0.01" min="0.01" required class="form-control" placeholder="25">
                </div>
                <div class="col-md-3">
                    <label>Subtotal min (opcional)</label>
                    <input name="min_subtotal" type="number" step="0.01" min="0" class="form-control" placeholder="100">
                </div>
                <div class="col-md-3">
                    <label>Cap descuento (% only)</label>
                    <input name="max_discount" type="number" step="0.01" min="0" class="form-control" placeholder="50">
                </div>
                <div class="col-md-3">
                    <label>Scope</label>
                    <select name="scope" class="form-control" onchange="document.getElementById('mkt-tid').style.display=this.value==='tenant'?'':'none'"><option value="platform">Plataforma (todas tiendas)</option><option value="tenant">Solo una tienda</option></select>
                </div>
                <div class="col-md-3" id="mkt-tid" style="display:none">
                    <label>hostname_id</label>
                    <input name="tenant_id" type="number" class="form-control" placeholder="1">
                </div>
                <div class="col-md-3">
                    <label>Valido desde</label>
                    <input name="valid_from" type="datetime-local" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Valido hasta</label>
                    <input name="valid_until" type="datetime-local" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Max redenciones (total)</label>
                    <input name="max_redemptions" type="number" min="1" class="form-control" placeholder="ilimitado">
                </div>
                <div class="col-md-3">
                    <label>Max por usuario</label>
                    <input name="max_per_user" type="number" min="1" value="1" required class="form-control">
                </div>
                <div class="col-md-12">
                    <label>Descripcion (visible al comprador)</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px">
                <button type="submit" class="btn btn-success">Crear cupon</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('mkt-coupon-form').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>

    <table class="table" style="background:#fff">
        <thead><tr>
            <th>Codigo</th><th>Nombre</th><th>Tipo / Valor</th><th>Scope</th>
            <th>Vence</th><th>Asignados</th><th>Usados</th><th>Activo</th><th></th>
        </tr></thead>
        <tbody id="mkt-coupon-list"><tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:24px">Cargando...</td></tr></tbody>
    </table>

    <div id="mkt-assign-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:12px;padding:24px;max-width:500px;width:90%">
            <h5>Asignar cupon <span id="mkt-assign-code"></span></h5>
            <p style="font-size:13.5px;color:#64748b">Pega aqui los emails de los usuarios (uno por linea o separados por coma).</p>
            <form onsubmit="return mktAssign(event)">
                <input type="hidden" name="coupon_id" id="mkt-assign-cid">
                <textarea name="emails" rows="6" required class="form-control" placeholder="usuario1@email.com&#10;usuario2@email.com"></textarea>
                <label style="margin-top:10px">Vence el (opcional, sobrescribe valid_until del coupon)</label>
                <input name="expires_at" type="datetime-local" class="form-control">
                <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('mkt-assign-modal').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const recordsUrl = @json(route('system.marketplace_coupons.records'));
const storeUrl   = @json(route('system.marketplace_coupons.store'));
const csrf = @json(csrf_token());

function load() {
    fetch(recordsUrl).then(r => r.json()).then(j => {
        const tbody = document.getElementById('mkt-coupon-list');
        if (!j.data.length) { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:24px">Sin cupones aun</td></tr>'; return; }
        tbody.innerHTML = j.data.map(c => `
            <tr>
                <td><strong>${c.code}</strong></td>
                <td>${c.name}</td>
                <td>${c.type === 'percent' ? c.value+'%' : 'S/ '+c.value}</td>
                <td>${c.scope === 'tenant' ? 'Tienda #'+c.tenant_id : 'Plataforma'}</td>
                <td>${c.valid_until || '—'}</td>
                <td>${c.assigned_count}</td>
                <td>${c.used_count}</td>
                <td>${c.is_active ? '<span style="color:#047857">SI</span>' : '<span style="color:#dc2626">NO</span>'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="openAssign(${c.id}, '${c.code}')">+ Asignar</button>
                    <button class="btn btn-sm btn-warning" onclick="toggleC(${c.id})">${c.is_active ? 'Pausar' : 'Activar'}</button>
                </td>
            </tr>
        `).join('');
    });
}

function mktCreateCoupon(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd.entries());
    data.is_active = true;
    fetch(storeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(j => {
        if (!j.success) { alert(j.message || 'Error'); return; }
        document.getElementById('mkt-coupon-form').style.display = 'none';
        e.target.reset();
        load();
    });
    return false;
}

function toggleC(id) {
    fetch(`/admin/marketplace/coupons/${id}/toggle`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    }).then(r => r.json()).then(_ => load());
}

function openAssign(id, code) {
    document.getElementById('mkt-assign-cid').value = id;
    document.getElementById('mkt-assign-code').textContent = code;
    document.getElementById('mkt-assign-modal').style.display = 'flex';
}

function mktAssign(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const id = fd.get('coupon_id');
    const rawEmails = fd.get('emails') || '';
    const emails = rawEmails.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
    const expires_at = fd.get('expires_at') || null;
    fetch(`/admin/marketplace/coupons/${id}/assign`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ emails, expires_at })
    })
    .then(r => r.json())
    .then(j => {
        let msg = `Asignados: ${j.assigned}.`;
        if (j.missing_emails && j.missing_emails.length) {
            msg += `\n\nNo encontrados (sin cuenta en ebaemy):\n${j.missing_emails.join(', ')}`;
        }
        alert(msg);
        document.getElementById('mkt-assign-modal').style.display = 'none';
        e.target.reset();
        load();
    });
    return false;
}

load();
</script>
@endsection
