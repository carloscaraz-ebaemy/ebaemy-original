@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3" id="sellerApplicationsPanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🛍️ Solicitudes de vendedores</h3>
        <div class="text-muted small">Onboarding de sellers del marketplace</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" id="saSearch" class="form-control form-control-sm" placeholder="Buscar por RUC, razón social, email, subdominio…">
        </div>
        <div class="col-md-3">
            <select id="saStatus" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="pending">Pendientes</option>
                <option value="under_review">En revisión</option>
                <option value="requires_documents">Requiere documentos</option>
                <option value="requires_review">Requiere revisión RUC</option>
                <option value="approved">Aprobadas</option>
                <option value="rejected">Rechazadas</option>
                <option value="cancelled">Canceladas</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" onclick="saLoad()">Filtrar</button>
        </div>
    </div>

    <div class="card">
        <table class="table mb-0 table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>RUC / Razón social</th>
                    <th>Subdominio</th>
                    <th>Responsable</th>
                    <th>Contacto</th>
                    <th class="text-center">RUC</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody id="saRows">
                <tr><td colspan="8" class="text-center text-muted py-4">Cargando...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small" id="saPaginationInfo"></div>
        <div id="saPagination"></div>
    </div>
</div>

{{-- ══════════════════════ Modal Detalle ══════════════════════ --}}
<div class="modal fade" id="saDetailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de solicitud</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="saDetailBody">
        <div class="text-center text-muted py-5">Cargando…</div>
      </div>
    </div>
  </div>
</div>

<script>
const SA_CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
const SA_ROUTE = @json(url('/admin/seller-applications'));
let saCurrentPage = 1;

function saBadge(status) {
    const map = {
        pending:            ['bg-secondary', 'Pendiente'],
        under_review:       ['bg-info',      'En revisión'],
        requires_documents: ['bg-warning text-dark', 'Doc. pendiente'],
        requires_review:    ['bg-warning text-dark', 'Revisar RUC'],
        approved:           ['bg-success',   'Aprobada'],
        rejected:           ['bg-danger',    'Rechazada'],
        cancelled:          ['bg-dark',      'Cancelada'],
    };
    const [cls, label] = map[status] || ['bg-light text-dark', status];
    return `<span class="badge ${cls}">${label}</span>`;
}

function saRucBadge(row) {
    if (!row.ruc_status) return '<span class="badge bg-light text-dark">—</span>';
    const ok = row.ruc_status === 'ACTIVO' && row.ruc_condition === 'HABIDO';
    const cls = ok ? 'bg-success' : (row.ruc_status === 'UNKNOWN' ? 'bg-secondary' : 'bg-danger');
    const label = ok ? '✓' : (row.ruc_status === 'UNKNOWN' ? '?' : '⚠');
    return `<span class="badge ${cls}" title="${row.ruc_status}/${row.ruc_condition || '-'}">${label}</span>`;
}

async function saLoad(page = 1) {
    saCurrentPage = page;
    const search = document.getElementById('saSearch').value.trim();
    const status = document.getElementById('saStatus').value;
    const params = new URLSearchParams({ page });
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const tbody = document.getElementById('saRows');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Cargando…</td></tr>';

    try {
        const res = await fetch(`${SA_ROUTE}/records?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay solicitudes.</td></tr>';
        } else {
            tbody.innerHTML = json.data.map(saRowHtml).join('');
        }

        document.getElementById('saPaginationInfo').textContent =
            `Mostrando página ${json.current_page} de ${json.last_page} (${json.total} total)`;

        document.getElementById('saPagination').innerHTML = `
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page<=1?'disabled':''} onclick="saLoad(${json.current_page-1})">←</button>
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page>=json.last_page?'disabled':''} onclick="saLoad(${json.current_page+1})">→</button>
        `;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error: ' + e.message + '</td></tr>';
    }
}

function saRowHtml(row) {
    const fecha = new Date(row.created_at).toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'2-digit' });
    const typeBadge = row.is_activation_request
        ? '<span class="badge bg-info text-white" title="Activación de tienda virtual para tenant existente">🛍️ Activación</span>'
        : '<span class="badge bg-light text-dark" title="Onboarding nuevo (crea tenant)">🆕 Nuevo</span>';
    // En solicitudes de activación el requested_subdomain es un centinela;
    // mostramos "(existente)" para no confundir al revisor.
    const subCell = row.is_activation_request
        ? '<small class="text-muted">(tenant existente)</small>'
        : `<code>${saEscape(row.requested_subdomain)}</code>`;
    return `
    <tr>
        <td><small>${fecha}</small></td>
        <td>
            <strong>${row.ruc}</strong><br>
            <small class="text-muted">${saEscape(row.business_name)}</small>
            <br>${typeBadge}
        </td>
        <td>${subCell}</td>
        <td>
            ${saEscape(row.legal_representative_name)}<br>
            <small class="text-muted">DNI ${saEscape(row.legal_representative_dni)}</small>
        </td>
        <td>
            <small>✉️ ${saEscape(row.email)}</small><br>
            <small>📱 ${saEscape(row.phone)}</small>
        </td>
        <td class="text-center">${saRucBadge(row)}</td>
        <td class="text-center">${saBadge(row.status)}</td>
        <td class="text-end">
            <button class="btn btn-sm btn-outline-primary" onclick="saShowDetail(${row.id})">Ver</button>
        </td>
    </tr>`;
}

function saEscape(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function saShowDetail(id) {
    const body = document.getElementById('saDetailBody');
    body.innerHTML = '<div class="text-center text-muted py-5">Cargando…</div>';
    $('#saDetailModal').modal('show');

    try {
        const res = await fetch(`${SA_ROUTE}/${id}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        body.innerHTML = saDetailHtml(data);
    } catch (e) {
        body.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
    }
}

function saDetailHtml(data) {
    const app = data.application;
    const reviewable = ['pending','under_review','requires_documents','requires_review'].includes(app.status);

    const planOptions = data.plans.map(p => `<option value="${p.id}">${saEscape(p.name)} — docs:${p.limit_documents} usu:${p.limit_users}</option>`).join('');

    const logs = data.logs.map(l => `
        <div class="border-start border-4 border-primary ps-3 mb-2">
            <div class="small text-muted">${new Date(l.created_at).toLocaleString('es-PE')} · ${saEscape(l.action)}</div>
            ${l.old_status ? `<div class="small">${l.old_status} → <strong>${l.new_status}</strong></div>` : ''}
            ${l.notes ? `<div>${saEscape(l.notes)}</div>` : ''}
        </div>
    `).join('') || '<div class="text-muted">Sin actividad registrada.</div>';

    const isActivation = !!app.is_activation_request;
    const approveBlock = isActivation ? `
        <div class="mb-2">
            <div class="alert alert-info py-2 mb-2 small">
                <strong>🛍️ Solicitud de activación</strong><br>
                Esta empresa ya es cliente. Al aprobar solo se activará la tienda virtual
                (marketplace_enabled=true) en el tenant existente y se agregará el módulo
                ecommerce al usuario admin. <strong>No se creará un tenant nuevo.</strong>
            </div>
            <button class="btn btn-success btn-sm w-100" onclick="saApproveActivation(${app.id})">🛍️ Aprobar activación</button>
        </div>
    ` : `
        <div class="mb-2">
            <label class="form-label small mb-1"><strong>Aprobar y crear tenant</strong></label>
            <div class="mb-2">
                <select id="saApprovePlan_${app.id}" class="form-select form-select-sm">${planOptions}</select>
            </div>
            <details class="mb-2">
                <summary class="small text-muted" style="cursor:pointer;">⚙ Corregir subdominio, email o contraseña (opcional)</summary>
                <div class="mt-2">
                    <input id="saOverrideSubdomain_${app.id}" type="text" class="form-control form-control-sm mb-1" placeholder="Nuevo subdominio (dejar vacío para usar: ${saEscape(app.requested_subdomain)})">
                    <input id="saOverrideEmail_${app.id}" type="email" class="form-control form-control-sm mb-1" placeholder="Nuevo email (dejar vacío para usar: ${saEscape(app.email)})">
                    <input id="saOverridePassword_${app.id}" type="text" class="form-control form-control-sm" placeholder="Nueva contraseña (dejar vacío para usar la del seller)">
                    <div class="form-text small">Solo llena estos campos si quieres reemplazar lo que el seller registró.</div>
                </div>
            </details>
            <button class="btn btn-success btn-sm w-100" onclick="saApprove(${app.id})">✓ Aprobar y crear tenant</button>
            <div class="form-text">Se creará el tenant, se enviará correo al seller.</div>
        </div>
    `;

    const actions = reviewable ? `
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Acciones</strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <button class="btn btn-info btn-sm w-100" onclick="saAction(${app.id}, 'under-review')">Marcar en revisión</button>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-warning btn-sm w-100" onclick="saPromptDocs(${app.id})">Solicitar documentos</button>
                    </div>
                </div>
                <hr>
                ${approveBlock}
                <hr>
                <div>
                    <button class="btn btn-danger btn-sm w-100" onclick="saPromptReject(${app.id})">✗ Rechazar solicitud</button>
                </div>
                <hr>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="saPromptNote(${app.id})">+ Agregar nota interna</button>
                </div>
            </div>
        </div>
    ` : `<div class="alert alert-secondary">Esta solicitud ya no es revisable (estado: <strong>${app.status}</strong>).</div>`;

    const logoPreview = app.logo_path
        ? `<div class="text-center mb-3"><img src="/storage/${saEscape(app.logo_path)}" style="max-height:80px; max-width:180px; object-fit:contain;" alt="Logo"></div>`
        : '';

    return `
    <div class="row g-3">
        <div class="col-md-7">
            ${logoPreview}
            <div class="card mb-3">
                <div class="card-header bg-light"><strong>Empresa</strong> ${saBadge(app.status)} ${saRucBadge(app)}</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>RUC</th><td>${app.ruc}</td></tr>
                        <tr><th>Razón social</th><td>${saEscape(app.business_name)}</td></tr>
                        <tr><th>Nombre comercial</th><td>${saEscape(app.trade_name)}</td></tr>
                        <tr><th>Dirección fiscal</th><td>${saEscape(app.fiscal_address)}</td></tr>
                        <tr><th>Subdominio solicitado</th><td><code>${saEscape(app.requested_subdomain)}</code></td></tr>
                        <tr><th>RUC SUNAT</th><td>${app.ruc_status || '—'} / ${app.ruc_condition || '—'}</td></tr>
                    </table>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header bg-light"><strong>Responsable legal</strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Nombre</th><td>${saEscape(app.legal_representative_name)}</td></tr>
                        <tr><th>DNI</th><td>${saEscape(app.legal_representative_dni)}</td></tr>
                        <tr><th>Cargo</th><td>${saEscape(app.legal_representative_position)}</td></tr>
                        <tr><th>Email</th><td>${saEscape(app.email)}</td></tr>
                        <tr><th>Teléfono</th><td>${saEscape(app.phone)}</td></tr>
                    </table>
                </div>
            </div>
            ${app.rejection_reason ? `<div class="alert alert-danger"><strong>Motivo de rechazo:</strong><br>${saEscape(app.rejection_reason)}</div>` : ''}
            ${app.review_notes ? `<div class="alert alert-warning"><strong>Notas de revisión:</strong><br>${saEscape(app.review_notes)}</div>` : ''}
        </div>
        <div class="col-md-5">
            ${actions}
            <div class="card">
                <div class="card-header bg-light"><strong>Historial</strong></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">${logs}</div>
            </div>
        </div>
    </div>`;
}

async function saPost(id, path, body = {}) {
    try {
        const res = await fetch(`${SA_ROUTE}/${id}/${path}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': SA_CSRF,
            },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) {
            alert('Error: ' + (json.message || 'desconocido'));
            return false;
        }
        alert(json.message);
        $('#saDetailModal').modal('hide');
        saLoad(saCurrentPage);
        return true;
    } catch (e) {
        alert('Error: ' + e.message);
        return false;
    }
}

function saAction(id, path) {
    if (!confirm('¿Confirmas la acción?')) return;
    saPost(id, path);
}

async function saApprove(id) {
    const planId = document.getElementById(`saApprovePlan_${id}`).value;
    if (!planId) return alert('Selecciona un plan.');

    const subdomainOverride = (document.getElementById(`saOverrideSubdomain_${id}`)?.value || '').trim().toLowerCase();
    const emailOverride = (document.getElementById(`saOverrideEmail_${id}`)?.value || '').trim();
    const passwordOverride = (document.getElementById(`saOverridePassword_${id}`)?.value || '').trim();

    if (subdomainOverride && !/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/.test(subdomainOverride)) {
        return alert('Subdominio inválido. Solo minúsculas, números y guiones (no al inicio ni al final).');
    }
    if (passwordOverride && passwordOverride.length < 8) {
        return alert('La contraseña de override debe tener al menos 8 caracteres.');
    }
    if (passwordOverride && !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/.test(passwordOverride)) {
        return alert('La contraseña de override debe incluir mayúscula, minúscula y número.');
    }

    const msg = (subdomainOverride || emailOverride || passwordOverride)
        ? '¿Aprobar con los overrides ingresados? Se reemplazarán los datos del seller.'
        : '¿Aprobar esta solicitud? Se creará el tenant y se enviará correo al seller.';
    if (!confirm(msg)) return;

    const body = { plan_id: parseInt(planId, 10), type: 'admin' };
    if (subdomainOverride) body.subdomain_override = subdomainOverride;
    if (emailOverride)     body.email_override     = emailOverride;
    if (passwordOverride)  body.password_override  = passwordOverride;

    await saPost(id, 'approve', body);
}

async function saApproveActivation(id) {
    if (!confirm('¿Aprobar esta solicitud de activación? Se habilitará la tienda virtual del tenant existente.')) return;
    // El endpoint es el mismo /approve; el service detecta is_activation_request
    // internamente y se desvía a approveActivation — no requiere plan ni overrides.
    await saPost(id, 'approve', {});
}

async function saPromptReject(id) {
    const reason = prompt('Motivo del rechazo (mínimo 10 caracteres):');
    if (!reason || reason.length < 10) return alert('Motivo inválido.');
    await saPost(id, 'reject', { rejection_reason: reason });
}

async function saPromptDocs(id) {
    const docs = prompt('Describe qué documentos necesitas del seller (mínimo 10 caracteres):');
    if (!docs || docs.length < 10) return alert('Descripción inválida.');
    await saPost(id, 'request-documents', { documents_requested: docs });
}

async function saPromptNote(id) {
    const note = prompt('Nota interna (mínimo 3 caracteres):');
    if (!note || note.length < 3) return;
    await saPost(id, 'notes', { note });
}

document.addEventListener('DOMContentLoaded', () => saLoad(1));
document.getElementById('saSearch').addEventListener('keypress', e => { if (e.key === 'Enter') saLoad(1); });
document.getElementById('saStatus').addEventListener('change', () => saLoad(1));
</script>
@endsection
