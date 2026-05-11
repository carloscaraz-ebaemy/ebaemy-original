@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3" id="mcPanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📂 Categorías oficiales del marketplace</h3>
        <div>
            <a href="{{ url('/admin/marketplace/category-requests') }}" class="btn btn-outline-secondary btn-sm">
                📥 Solicitudes
            </a>
            <a href="{{ url('/admin/marketplace/categories/bulk-assign') }}" class="btn btn-outline-info btn-sm">
                🗂️ Asignar productos sin categoría
            </a>
            <button class="btn btn-success btn-sm" onclick="mcOpenCreate(null)">
                + Crear raíz
            </button>
        </div>
    </div>

    <div class="alert alert-info py-2 mb-3 small">
        <strong>Nota:</strong> Estas son las categorías OFICIALES del marketplace público (ebaemy.com/marketplace).
        Los sellers seleccionan una de estas al publicar un producto. NO se mezclan con las categorías
        privadas que cada tenant usa internamente en su tienda.
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" id="mcSearch" class="form-control form-control-sm" placeholder="Buscar por nombre o slug…">
        </div>
        <div class="col-md-3">
            <select id="mcStatusFilter" class="form-select form-select-sm">
                <option value="">Todas (activas + inactivas)</option>
                <option value="1">Solo activas</option>
                <option value="0">Solo inactivas</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" onclick="mcLoadTree()">🔄 Recargar</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div id="mcTree" style="padding: 12px;">
                <div class="text-center text-muted py-4">Cargando árbol…</div>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════ Modal Crear/Editar ════════════════ --}}
<div class="modal fade" id="mcEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mcEditTitle">Categoría</h5>
                <button type="button" class="btn-close" onclick="mcHideModal('mcEditModal')" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="mcEditError"></div>
                <input type="hidden" id="mcEditId">
                <input type="hidden" id="mcEditParentId">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Nombre *</label>
                        <input type="text" id="mcEditName" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Slug <small class="text-muted fw-normal">(auto si vacío)</small></label>
                        <input type="text" id="mcEditSlug" class="form-control" maxlength="80" placeholder="auto">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Ícono <small class="text-muted fw-normal">(emoji)</small></label>
                        <input type="text" id="mcEditIcon" class="form-control" maxlength="80" placeholder="🛍️">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Orden</label>
                        <input type="number" id="mcEditSort" class="form-control" min="0">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mcEditActive" checked>
                            <label class="form-check-label small" for="mcEditActive">Activa</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Descripción</label>
                        <textarea id="mcEditDescription" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="col-md-6 d-flex align-items-center gap-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mcEditVisible" checked>
                            <label class="form-check-label small" for="mcEditVisible">Visible en marketplace</label>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center gap-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mcEditAllowPublish" checked>
                            <label class="form-check-label small" for="mcEditAllowPublish">Permitir que sellers publiquen aquí</label>
                        </div>
                    </div>
                </div>

                <div id="mcEditParentInfo" class="mt-3 small text-muted"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="mcHideModal('mcEditModal')">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="mcSubmit()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
const MC_CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
const MC_BASE  = @json(url('/admin/marketplace/categories'));
let mcTree = [];

// Helpers de modal vanilla — el layout no carga bootstrap.js, así que no
// podemos usar `new bootstrap.Modal()`. Manipulamos clases directamente.
function mcShowModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.style.display = 'block';
    m.classList.add('show');
    m.removeAttribute('aria-hidden');
    m.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    let bd = document.getElementById(id + '_backdrop');
    if (!bd) {
        bd = document.createElement('div');
        bd.id = id + '_backdrop';
        bd.className = 'modal-backdrop fade show';
        bd.style.zIndex = '1040';
        document.body.appendChild(bd);
    }
}
function mcHideModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.style.display = 'none';
    m.classList.remove('show');
    m.setAttribute('aria-hidden', 'true');
    m.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    const bd = document.getElementById(id + '_backdrop');
    if (bd) bd.remove();
}

async function mcLoadTree() {
    const cont = document.getElementById('mcTree');
    cont.innerHTML = '<div class="text-center text-muted py-4">Cargando…</div>';
    try {
        const res = await fetch(`${MC_BASE}/tree`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        mcTree = json.tree || [];
        mcRenderTree();
    } catch (e) {
        cont.innerHTML = `<div class="alert alert-danger m-3">Error: ${mcEsc(e.message)}</div>`;
    }
}

function mcRenderTree() {
    const search = document.getElementById('mcSearch').value.trim().toLowerCase();
    const statusFilter = document.getElementById('mcStatusFilter').value;
    const cont = document.getElementById('mcTree');

    const matches = (node) => {
        let ok = true;
        if (search) {
            const text = (node.name + ' ' + node.full_slug).toLowerCase();
            ok = text.includes(search);
        }
        if (ok && statusFilter !== '') {
            ok = (statusFilter === '1') === !!node.is_active;
        }
        return ok;
    };

    // Si hay filtro, expandir todos los nodos que matchean o tienen descendientes que matchean
    const renderNode = (node, depth) => {
        const childRendered = (node.children || []).map(c => renderNode(c, depth + 1)).filter(Boolean);
        const selfMatches = matches(node);
        if (!selfMatches && childRendered.length === 0) return null;

        const indent = depth * 20;
        const badges = [];
        if (!node.is_active) badges.push('<span class="badge bg-secondary">inactiva</span>');
        if (!node.is_visible_in_marketplace) badges.push('<span class="badge bg-warning text-dark">oculta</span>');
        if (!node.allow_seller_publish) badges.push('<span class="badge bg-info text-dark">no publicable</span>');
        if (node.is_leaf) badges.push('<span class="badge bg-light text-muted border">hoja</span>');

        return `
            <div style="border-bottom:1px solid #eef; padding:6px 8px ${depth>0?'6px '+indent+'px':'6px 8px'};" class="d-flex align-items-center gap-2">
                <span style="display:inline-block; width:${indent}px; flex-shrink:0;"></span>
                <span style="font-size:14px;">${node.icon ? mcEsc(node.icon) + ' ' : ''}<strong>${mcEsc(node.name)}</strong></span>
                <code class="text-muted small">${mcEsc(node.full_slug)}</code>
                ${badges.join(' ')}
                <span class="text-muted small" title="Productos asignados">📦 ${node.listings_count_cache}</span>
                <div class="ms-auto d-flex gap-1">
                    <button class="btn btn-outline-success btn-sm" onclick="mcOpenCreate(${node.id})" title="Crear subcategoría">+ Sub</button>
                    <button class="btn btn-outline-primary btn-sm" onclick="mcOpenEdit(${node.id})" title="Editar">✏️</button>
                    <button class="btn btn-outline-${node.is_active ? 'warning' : 'success'} btn-sm" onclick="mcToggle(${node.id}, 'is_active')" title="${node.is_active?'Desactivar':'Activar'}">
                        ${node.is_active ? '🔒' : '🔓'}
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="mcDelete(${node.id})" title="Eliminar">🗑️</button>
                </div>
            </div>
            ${childRendered.join('')}
        `;
    };

    if (mcTree.length === 0) {
        cont.innerHTML = '<div class="text-center text-muted py-4">Sin categorías. Crea la primera.</div>';
        return;
    }
    const html = mcTree.map(n => renderNode(n, 0)).filter(Boolean).join('');
    cont.innerHTML = html || '<div class="text-center text-muted py-4">No hay coincidencias.</div>';
}

function mcEsc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function mcFindNode(id, list = mcTree) {
    for (const n of list) {
        if (n.id === id) return n;
        const sub = mcFindNode(id, n.children || []);
        if (sub) return sub;
    }
    return null;
}

function mcOpenCreate(parentId) {
    document.getElementById('mcEditId').value = '';
    document.getElementById('mcEditParentId').value = parentId || '';
    document.getElementById('mcEditTitle').textContent = parentId ? 'Nueva subcategoría' : 'Nueva categoría raíz';
    document.getElementById('mcEditName').value = '';
    document.getElementById('mcEditSlug').value = '';
    document.getElementById('mcEditIcon').value = '';
    document.getElementById('mcEditSort').value = '';
    document.getElementById('mcEditDescription').value = '';
    document.getElementById('mcEditActive').checked = true;
    document.getElementById('mcEditVisible').checked = true;
    document.getElementById('mcEditAllowPublish').checked = true;
    document.getElementById('mcEditError').innerHTML = '';

    let info = '';
    if (parentId) {
        const parent = mcFindNode(parentId);
        if (parent) info = `Bajo: <code>${mcEsc(parent.full_slug)}</code>`;
    } else {
        info = 'Categoría raíz (nivel 0).';
    }
    document.getElementById('mcEditParentInfo').innerHTML = info;

    mcShowModal('mcEditModal');
}

function mcOpenEdit(id) {
    const node = mcFindNode(id);
    if (!node) return alert('Categoría no encontrada.');

    document.getElementById('mcEditId').value = node.id;
    document.getElementById('mcEditParentId').value = node.parent_id || '';
    document.getElementById('mcEditTitle').textContent = `Editar: ${node.name}`;
    document.getElementById('mcEditName').value = node.name;
    document.getElementById('mcEditSlug').value = node.slug;
    document.getElementById('mcEditIcon').value = node.icon || '';
    document.getElementById('mcEditSort').value = node.sort_order;
    document.getElementById('mcEditDescription').value = '';  // no viene en tree, vacío
    document.getElementById('mcEditActive').checked = !!node.is_active;
    document.getElementById('mcEditVisible').checked = !!node.is_visible_in_marketplace;
    document.getElementById('mcEditAllowPublish').checked = !!node.allow_seller_publish;
    document.getElementById('mcEditError').innerHTML = '';
    document.getElementById('mcEditParentInfo').innerHTML = `<code>${mcEsc(node.full_slug)}</code>`;

    mcShowModal('mcEditModal');
}

async function mcSubmit() {
    const id = document.getElementById('mcEditId').value;
    const isNew = !id;
    const body = {
        name:                        document.getElementById('mcEditName').value.trim(),
        slug:                        document.getElementById('mcEditSlug').value.trim(),
        parent_id:                   document.getElementById('mcEditParentId').value || null,
        icon:                        document.getElementById('mcEditIcon').value.trim(),
        description:                 document.getElementById('mcEditDescription').value.trim(),
        sort_order:                  parseInt(document.getElementById('mcEditSort').value, 10) || 0,
        is_active:                   document.getElementById('mcEditActive').checked,
        is_visible_in_marketplace:   document.getElementById('mcEditVisible').checked,
        allow_seller_publish:        document.getElementById('mcEditAllowPublish').checked,
    };
    if (!body.name) return mcShowEditError('El nombre es obligatorio.');
    if (body.slug === '') delete body.slug;
    if (body.icon === '') body.icon = null;
    if (body.description === '') body.description = null;

    try {
        const url = isNew ? MC_BASE : `${MC_BASE}/${id}`;
        const method = isNew ? 'POST' : 'PUT';
        const res = await fetch(url, {
            method,
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': MC_CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            return mcShowEditError(data.message || 'Error al guardar.');
        }
        mcHideModal('mcEditModal');
        mcLoadTree();
    } catch (e) {
        mcShowEditError('Error: ' + e.message);
    }
}

function mcShowEditError(msg) {
    document.getElementById('mcEditError').innerHTML = `<div class="alert alert-danger py-2 small">${mcEsc(msg)}</div>`;
}

async function mcToggle(id, flag) {
    try {
        const res = await fetch(`${MC_BASE}/${id}/toggle`, {
            method:'POST',
            headers:{ 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': MC_CSRF },
            body: JSON.stringify({ flag }),
        });
        const data = await res.json();
        if (!data.success) return alert(data.message || 'Error');
        mcLoadTree();
    } catch (e) { alert('Error: ' + e.message); }
}

async function mcDelete(id) {
    const node = mcFindNode(id);
    const name = node ? node.name : `#${id}`;
    if (!confirm(`¿Eliminar la categoría "${name}"?\n\nNo se puede eliminar si tiene subcategorías o productos asignados.`)) return;
    try {
        const res = await fetch(`${MC_BASE}/${id}`, {
            method:'DELETE',
            headers:{ 'Accept':'application/json', 'X-CSRF-TOKEN': MC_CSRF },
        });
        const data = await res.json();
        if (!data.success) return alert(data.message || 'Error');
        mcLoadTree();
    } catch (e) { alert('Error: ' + e.message); }
}

document.addEventListener('DOMContentLoaded', mcLoadTree);
document.getElementById('mcSearch').addEventListener('input', mcRenderTree);
document.getElementById('mcStatusFilter').addEventListener('change', mcRenderTree);
</script>
@endsection
