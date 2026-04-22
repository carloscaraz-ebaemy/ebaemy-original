@extends('tenant.layouts.app')

@section('content')
<div class="page-header pe-0">
    <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="margin-top:-5px"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
    </h2>
    <ol class="breadcrumbs"><li class="active"><span>Zonas de envío</span></li></ol>
    <div class="right-wrapper pull-right">
        <button type="button" class="btn btn-custom btn-sm mt-2 me-2" onclick="sz.openCreate()">
            <i class="fa fa-plus-circle"></i> Nueva zona
        </button>
    </div>
</div>

<div class="card tab-content-default row-new mb-0">
    <div class="card-body">
        <p class="text-muted small mb-3">
            Define los costos de envío por zona. Los distritos que no pertenezcan a ninguna
            zona usan la zona marcada como <strong>default</strong>. La zona de
            <strong>recojo en tienda</strong> siempre tiene costo 0.
        </p>

        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">Orden</th>
                    <th>Nombre</th>
                    <th class="text-end" style="width:100px">Costo</th>
                    <th class="text-center" style="width:90px">Días</th>
                    <th class="text-center" style="width:120px">Distritos</th>
                    <th class="text-center" style="width:100px">Tipo</th>
                    <th class="text-center" style="width:90px">Activa</th>
                    <th class="text-end" style="width:130px">Acciones</th>
                </tr>
            </thead>
            <tbody id="sz-tbody">
                <tr><td colspan="8" class="text-center text-muted py-4">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Modal de edición/creación --}}
<div class="modal fade" id="sz-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sz-modal-title">Nueva zona de envío</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sz-form">
                    <input type="hidden" name="id" id="sz-id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="name" id="sz-name" class="form-control form-control-sm" required maxlength="80">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Costo (S/)</label>
                            <input type="number" name="cost" id="sz-cost" class="form-control form-control-sm" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Días estimados</label>
                            <input type="number" name="estimated_days" id="sz-days" class="form-control form-control-sm" min="0" max="60" value="2">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="sz-active" checked>
                                <label class="form-check-label" for="sz-active">Activa</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="sz-default">
                                <label class="form-check-label" for="sz-default">Zona por defecto</label>
                                <small class="d-block text-muted">Fallback si distrito no matchea</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="sz-pickup">
                                <label class="form-check-label" for="sz-pickup">Recojo en tienda</label>
                                <small class="d-block text-muted">Fuerza costo a 0</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orden</label>
                            <input type="number" id="sz-sort" class="form-control form-control-sm" value="0">
                        </div>
                    </div>

                    <hr class="my-3">
                    <label class="form-label">Distritos que cubre esta zona</label>
                    <div class="mb-2">
                        <input type="text" id="sz-district-search" class="form-control form-control-sm" placeholder="Buscar distrito...">
                        <small class="text-muted">Deja vacío si es la zona default o recojo en tienda.</small>
                    </div>
                    <div id="sz-districts-list"
                         style="max-height:280px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:10px;background:#f9fafb;">
                        <div class="text-muted small">Cargando catálogo...</div>
                    </div>
                    <div class="mt-2"><small class="text-muted"><span id="sz-district-count">0</span> distritos seleccionados</small></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="sz-save-btn" onclick="sz.save()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
window.sz = (function() {
    let zones = [];
    let districts = [];
    let selectedDistricts = new Set();

    async function loadData() {
        const [rz, rd] = await Promise.all([
            fetch('/shipping-zones/records').then(r => r.json()),
            fetch('/shipping-zones/tables').then(r => r.json()),
        ]);
        zones = rz;
        districts = rd.districts || [];
        renderTable();
    }

    function renderTable() {
        const tbody = document.getElementById('sz-tbody');
        if (!zones.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay zonas. Crea la primera.</td></tr>';
            return;
        }
        tbody.innerHTML = zones.map(z => `
            <tr>
                <td>${z.sort_order || 0}</td>
                <td><strong>${esc(z.name)}</strong>${z.is_default ? ' <span class="badge bg-info">default</span>' : ''}</td>
                <td class="text-end">S/ ${parseFloat(z.cost).toFixed(2)}</td>
                <td class="text-center">${z.estimated_days}d</td>
                <td class="text-center">${(z.district_ids || []).length}</td>
                <td class="text-center">${z.is_pickup ? '<span class="badge bg-success">Recojo</span>' : '<span class="badge bg-secondary">Envío</span>'}</td>
                <td class="text-center">${z.is_active ? '✓' : '✖'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick="sz.edit(${z.id})">Editar</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="sz.remove(${z.id}, '${esc(z.name)}')">✖</button>
                </td>
            </tr>
        `).join('');
    }

    function renderDistricts(filter) {
        const box = document.getElementById('sz-districts-list');
        const f = (filter || '').toLowerCase();
        const filtered = f ? districts.filter(d => d.description.toLowerCase().includes(f)) : districts;
        box.innerHTML = filtered.slice(0, 300).map(d => `
            <label style="display:block;margin-bottom:4px;font-size:12px">
                <input type="checkbox" data-id="${d.id}" ${selectedDistricts.has(d.id) ? 'checked' : ''}
                       onchange="sz.toggleDistrict('${d.id}', this.checked)">
                ${esc(d.description)} <small class="text-muted">(${d.id})</small>
            </label>
        `).join('') + (filtered.length > 300 ? `<div class="text-muted small mt-2">Mostrando 300 de ${filtered.length}. Usa búsqueda.</div>` : '');
    }

    function openCreate() {
        document.getElementById('sz-modal-title').textContent = 'Nueva zona de envío';
        document.getElementById('sz-id').value = '';
        document.getElementById('sz-name').value = '';
        document.getElementById('sz-cost').value = '0';
        document.getElementById('sz-days').value = '2';
        document.getElementById('sz-sort').value = '0';
        document.getElementById('sz-active').checked = true;
        document.getElementById('sz-default').checked = false;
        document.getElementById('sz-pickup').checked = false;
        selectedDistricts = new Set();
        renderDistricts('');
        updateCount();
        new bootstrap.Modal(document.getElementById('sz-modal')).show();
    }

    async function edit(id) {
        const z = await fetch('/shipping-zones/record/' + id).then(r => r.json());
        document.getElementById('sz-modal-title').textContent = 'Editar zona';
        document.getElementById('sz-id').value = z.id;
        document.getElementById('sz-name').value = z.name;
        document.getElementById('sz-cost').value = z.cost;
        document.getElementById('sz-days').value = z.estimated_days;
        document.getElementById('sz-sort').value = z.sort_order || 0;
        document.getElementById('sz-active').checked = !!z.is_active;
        document.getElementById('sz-default').checked = !!z.is_default;
        document.getElementById('sz-pickup').checked = !!z.is_pickup;
        selectedDistricts = new Set(z.district_ids || []);
        renderDistricts('');
        updateCount();
        new bootstrap.Modal(document.getElementById('sz-modal')).show();
    }

    function toggleDistrict(id, checked) {
        if (checked) selectedDistricts.add(id); else selectedDistricts.delete(id);
        updateCount();
    }

    function updateCount() {
        document.getElementById('sz-district-count').textContent = selectedDistricts.size;
    }

    async function save() {
        const btn = document.getElementById('sz-save-btn');
        btn.disabled = true;
        const id = document.getElementById('sz-id').value;
        const payload = {
            name: document.getElementById('sz-name').value,
            cost: parseFloat(document.getElementById('sz-cost').value) || 0,
            estimated_days: parseInt(document.getElementById('sz-days').value) || 0,
            sort_order: parseInt(document.getElementById('sz-sort').value) || 0,
            is_active: document.getElementById('sz-active').checked,
            is_default: document.getElementById('sz-default').checked,
            is_pickup: document.getElementById('sz-pickup').checked,
            district_ids: Array.from(selectedDistricts),
        };
        const url = id ? '/shipping-zones/' + id : '/shipping-zones';
        const method = id ? 'PUT' : 'POST';
        try {
            const r = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            }).then(r => r.json());
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('sz-modal')).hide();
                await loadData();
            } else {
                alert(r.message || 'Error al guardar');
            }
        } catch(e) { alert('Error de conexión'); }
        btn.disabled = false;
    }

    async function remove(id, name) {
        if (!confirm('¿Eliminar la zona "' + name + '"?')) return;
        try {
            const r = await fetch('/shipping-zones/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
            }).then(r => r.json());
            if (r.success) await loadData();
        } catch(e) { alert('Error al eliminar'); }
    }

    function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function getCsrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }

    // Búsqueda en tiempo real
    document.addEventListener('DOMContentLoaded', function() {
        const search = document.getElementById('sz-district-search');
        if (search) search.addEventListener('input', e => renderDistricts(e.target.value));
    });

    loadData();

    return { openCreate, edit, save, remove, toggleDistrict };
})();
</script>
@endsection
