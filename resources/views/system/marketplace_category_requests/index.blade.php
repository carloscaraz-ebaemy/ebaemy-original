@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3" id="mcrPanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📥 Solicitudes de nuevas categorías</h3>
        <a href="{{ url('/admin/marketplace/categories') }}" class="btn btn-outline-secondary btn-sm">
            ← Volver al árbol
        </a>
    </div>

    <div class="alert alert-info py-2 mb-3 small">
        Cuando un seller no encuentra una categoría adecuada al publicar un producto, puede solicitar
        una nueva. Aquí las apruebas (creando una nueva entrada en el árbol oficial) o las rechazas
        con motivo.
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" id="mcrSearch" class="form-control form-control-sm" placeholder="Buscar por nombre…">
        </div>
        <div class="col-md-3">
            <select id="mcrStatus" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="pending">Pendientes</option>
                <option value="approved">Aprobadas</option>
                <option value="rejected">Rechazadas</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" onclick="mcrLoad()">Filtrar</button>
        </div>
    </div>

    <div class="card">
        <table class="table mb-0 table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tenant ID</th>
                    <th>Categoría sugerida</th>
                    <th>Padre sugerido</th>
                    <th>Producto</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody id="mcrRows">
                <tr><td colspan="7" class="text-center text-muted py-4">Cargando…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small" id="mcrPagInfo"></div>
        <div id="mcrPag"></div>
    </div>
</div>

{{-- ════════════════ Modal Detalle ════════════════ --}}
<div class="modal fade" id="mcrDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="mcrDetailBody">
                <div class="text-center text-muted py-4">Cargando…</div>
            </div>
        </div>
    </div>
</div>

<script>
const MCR_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const MCR_BASE = @json(url('/admin/marketplace/category-requests'));
let mcrCurrentPage = 1;

function mcrBadge(status) {
    const map = {
        pending: ['bg-warning text-dark', 'Pendiente'],
        approved: ['bg-success', 'Aprobada'],
        rejected: ['bg-danger', 'Rechazada'],
    };
    const [cls, label] = map[status] || ['bg-secondary', status];
    return `<span class="badge ${cls}">${label}</span>`;
}

async function mcrLoad(page = 1) {
    mcrCurrentPage = page;
    const params = new URLSearchParams({ page });
    const search = document.getElementById('mcrSearch').value.trim();
    const status = document.getElementById('mcrStatus').value;
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const tbody = document.getElementById('mcrRows');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Cargando…</td></tr>';

    try {
        const res = await fetch(`${MCR_BASE}/records?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin solicitudes.</td></tr>';
        } else {
            tbody.innerHTML = json.data.map(mcrRowHtml).join('');
        }

        document.getElementById('mcrPagInfo').textContent = `Página ${json.current_page}/${json.last_page} · ${json.total} total`;
        document.getElementById('mcrPag').innerHTML = `
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page<=1?'disabled':''} onclick="mcrLoad(${json.current_page-1})">←</button>
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page>=json.last_page?'disabled':''} onclick="mcrLoad(${json.current_page+1})">→</button>
        `;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error: ${e.message}</td></tr>`;
    }
}

function mcrRowHtml(row) {
    const fecha = new Date(row.created_at).toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'2-digit' });
    return `
    <tr>
        <td><small>${fecha}</small></td>
        <td><small>#${row.tenant_id}</small></td>
        <td><strong>${mcrEsc(row.suggested_name)}</strong></td>
        <td><small class="text-muted">${row.suggested_parent_id ? '#' + row.suggested_parent_id : '(raíz)'}</small></td>
        <td><small>${row.product_id ? '#' + row.product_id : '—'}</small></td>
        <td class="text-center">${mcrBadge(row.status)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick="mcrShow(${row.id})">Ver</button>
        </td>
    </tr>`;
}

function mcrEsc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function mcrShow(id) {
    const body = document.getElementById('mcrDetailBody');
    body.innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    new bootstrap.Modal(document.getElementById('mcrDetailModal')).show();

    try {
        const res = await fetch(`${MCR_BASE}/${id}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        body.innerHTML = mcrDetailHtml(data);
    } catch (e) {
        body.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
    }
}

function mcrDetailHtml(data) {
    const r = data.request;
    const cats = data.root_categories || [];

    const parentOptions = '<option value="">(raíz — sin padre)</option>' +
        cats.map(c => {
            const indent = '— '.repeat(c.level);
            const sel = (r.suggested_parent_id == c.id) ? 'selected' : '';
            return `<option value="${c.id}" ${sel}>${indent}${mcrEsc(c.full_slug)}</option>`;
        }).join('');

    const isPending = r.status === 'pending';
    const actions = isPending ? `
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Aprobar</strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-7">
                        <label class="form-label small">Nombre final <span class="text-muted">(deja vacío para usar el sugerido)</span></label>
                        <input type="text" id="mcrApproveName_${r.id}" class="form-control form-control-sm" placeholder="${mcrEsc(r.suggested_name)}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">Categoría padre</label>
                        <select id="mcrApproveParent_${r.id}" class="form-select form-select-sm">${parentOptions}</select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small">Mensaje al seller (opcional)</label>
                    <textarea id="mcrApproveMsg_${r.id}" class="form-control form-control-sm" rows="2" placeholder="Ej: 'Aprobada, ya puedes usarla.'"></textarea>
                </div>
                <button class="btn btn-success btn-sm w-100 mt-2" onclick="mcrApprove(${r.id})">✓ Aprobar y crear categoría</button>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Rechazar</strong></div>
            <div class="card-body">
                <label class="form-label small">Motivo (visible al seller) *</label>
                <textarea id="mcrRejectMsg_${r.id}" class="form-control form-control-sm" rows="3" placeholder="Ej: 'Ya existe una categoría similar: Hogar > Decoración.'"></textarea>
                <button class="btn btn-danger btn-sm w-100 mt-2" onclick="mcrReject(${r.id})">✗ Rechazar</button>
            </div>
        </div>
    ` : `<div class="alert alert-secondary small">Esta solicitud ya fue procesada.</div>`;

    return `
        <div class="row g-3">
            <div class="col-md-7">
                <div class="card mb-3">
                    <div class="card-header bg-light"><strong>Solicitud</strong> ${mcrBadge(r.status)}</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr><th width="180">Tenant ID</th><td>#${r.tenant_id}</td></tr>
                            <tr><th>Producto</th><td>${r.product_id ? '#' + r.product_id : '—'}</td></tr>
                            <tr><th>Sugerido</th><td><strong>${mcrEsc(r.suggested_name)}</strong></td></tr>
                            <tr><th>Padre sugerido</th><td>${r.suggested_parent_id ? '#' + r.suggested_parent_id : '(raíz)'}</td></tr>
                            <tr><th>Descripción</th><td>${r.description ? mcrEsc(r.description) : '<em class="text-muted">(sin descripción)</em>'}</td></tr>
                            <tr><th>Solicitada</th><td><small>${new Date(r.created_at).toLocaleString('es-PE')}</small></td></tr>
                        </table>
                    </div>
                </div>
                ${r.admin_response ? `
                <div class="card mb-3">
                    <div class="card-header bg-light"><strong>Respuesta del admin</strong></div>
                    <div class="card-body">
                        <p class="mb-1">${mcrEsc(r.admin_response)}</p>
                        <small class="text-muted">por usuario #${r.reviewed_by} el ${new Date(r.reviewed_at).toLocaleString('es-PE')}</small>
                    </div>
                </div>` : ''}
                ${r.created_marketplace_category_id ? `
                <div class="alert alert-success small">✓ Categoría creada: <code>#${r.created_marketplace_category_id}</code></div>` : ''}
            </div>
            <div class="col-md-5">${actions}</div>
        </div>`;
}

async function mcrApprove(id) {
    const name   = document.getElementById(`mcrApproveName_${id}`)?.value.trim() || null;
    const parent = document.getElementById(`mcrApproveParent_${id}`)?.value || null;
    const msg    = document.getElementById(`mcrApproveMsg_${id}`)?.value.trim() || null;

    if (!confirm('¿Aprobar y crear esta categoría en el árbol oficial?')) return;

    try {
        const res = await fetch(`${MCR_BASE}/${id}/approve`, {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': MCR_CSRF },
            body: JSON.stringify({
                override_name:      name,
                override_parent_id: parent ? parseInt(parent, 10) : null,
                admin_response:     msg,
            }),
        });
        const data = await res.json();
        if (!data.success) return alert(data.message || 'Error');
        alert(data.message);
        bootstrap.Modal.getInstance(document.getElementById('mcrDetailModal'))?.hide();
        mcrLoad(mcrCurrentPage);
    } catch (e) { alert('Error: ' + e.message); }
}

async function mcrReject(id) {
    const msg = document.getElementById(`mcrRejectMsg_${id}`)?.value.trim();
    if (!msg || msg.length < 10) return alert('Indica un motivo (mín. 10 caracteres) que será visible al seller.');
    try {
        const res = await fetch(`${MCR_BASE}/${id}/reject`, {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': MCR_CSRF },
            body: JSON.stringify({ admin_response: msg }),
        });
        const data = await res.json();
        if (!data.success) return alert(data.message || 'Error');
        alert(data.message);
        bootstrap.Modal.getInstance(document.getElementById('mcrDetailModal'))?.hide();
        mcrLoad(mcrCurrentPage);
    } catch (e) { alert('Error: ' + e.message); }
}

document.addEventListener('DOMContentLoaded', () => mcrLoad(1));
document.getElementById('mcrSearch').addEventListener('keypress', e => { if (e.key === 'Enter') mcrLoad(1); });
document.getElementById('mcrStatus').addEventListener('change', () => mcrLoad(1));
</script>
@endsection
