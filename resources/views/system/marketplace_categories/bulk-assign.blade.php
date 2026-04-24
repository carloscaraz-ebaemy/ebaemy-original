@extends('system.layouts.app')

@section('content')
<div class="container-fluid py-3" id="bulkPanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">🗂️ Asignar productos sin categoría</h3>
        <a href="{{ url('/admin/marketplace/categories') }}" class="btn btn-outline-secondary btn-sm">
            ← Volver al árbol
        </a>
    </div>

    <div class="alert alert-warning py-2 mb-3 small">
        <strong>Productos legacy:</strong> Esta lista muestra todos los listings publicados que aún
        no tienen <code>marketplace_category_id</code> asignado. Selecciona varios y asígnalos a una
        categoría oficial en bloque. Mientras no estén clasificados, NO aparecerán en los filtros del
        nuevo marketplace.
    </div>

    <!-- Progreso migración Fase A→D→E -->
    <div id="migrationPanel" class="card mb-3" style="border:1px solid #e5e7eb">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div>
                    <div class="small text-muted">Progreso migración (FK oficial)</div>
                    <div style="font-size:20px;font-weight:700" id="migrationPct">—</div>
                </div>
                <div class="flex-grow-1" style="min-width:200px">
                    <div class="progress" style="height:10px">
                        <div id="migrationBar" class="progress-bar bg-success" role="progressbar" style="width:0%"></div>
                    </div>
                    <div class="small text-muted mt-1" id="migrationDetail">Cargando…</div>
                </div>
                <div id="phaseEBadge" style="display:none">
                    <span class="badge bg-success" style="font-size:12px">✓ Listo para Fase E (≥95%)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" id="bulkSearch" class="form-control form-control-sm" placeholder="Buscar por título o categoría legacy…">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" onclick="bulkLoad(1)">Filtrar</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-light d-flex align-items-center gap-2">
            <input type="checkbox" id="bulkSelectAll" onchange="bulkToggleAll()">
            <label for="bulkSelectAll" class="mb-0 small">Seleccionar todos los visibles</label>
            <span class="badge bg-secondary ms-2" id="bulkSelectedCount">0 seleccionados</span>

            <div class="ms-auto d-flex gap-2 align-items-center">
                <select id="bulkTargetCategory" class="form-select form-select-sm" style="min-width:300px">
                    <option value="">Selecciona categoría oficial…</option>
                </select>
                <button class="btn btn-success btn-sm" onclick="bulkAssign()">Asignar a seleccionados</button>
            </div>
        </div>
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th width="40"></th>
                    <th>Producto</th>
                    <th>Tienda</th>
                    <th>Categoría legacy (string)</th>
                    <th class="text-end">Precio</th>
                </tr>
            </thead>
            <tbody id="bulkRows">
                <tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small" id="bulkPagInfo"></div>
        <div id="bulkPag"></div>
    </div>
</div>

<script>
const BULK_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const BULK_BASE = @json(url('/admin/marketplace/categories'));
let bulkCurrentPage = 1;
const bulkSelected = new Set();

async function bulkLoadCategories() {
    try {
        const res = await fetch(`${BULK_BASE}/tree`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        const sel = document.getElementById('bulkTargetCategory');
        sel.innerHTML = '<option value="">Selecciona categoría oficial…</option>';
        const flatten = (nodes, depth = 0) => {
            for (const n of nodes) {
                if (n.is_leaf && n.allow_seller_publish && n.is_active) {
                    const indent = '— '.repeat(depth);
                    sel.innerHTML += `<option value="${n.id}">${indent}${n.full_slug}</option>`;
                }
                if (n.children) flatten(n.children, depth + 1);
            }
        };
        flatten(json.tree || []);
    } catch (e) { console.error(e); }
}

async function bulkLoad(page = 1) {
    bulkCurrentPage = page;
    const params = new URLSearchParams({ page });
    const search = document.getElementById('bulkSearch').value.trim();
    if (search) params.set('search', search);

    const tbody = document.getElementById('bulkRows');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr>';

    try {
        const res = await fetch(`${BULK_BASE}/unclassified?${params}`, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">🎉 ¡Todos los productos ya están clasificados!</td></tr>';
        } else {
            tbody.innerHTML = json.data.map(bulkRowHtml).join('');
        }

        document.getElementById('bulkPagInfo').textContent = `Página ${json.current_page}/${json.last_page} · ${json.total} productos sin clasificar`;
        document.getElementById('bulkPag').innerHTML = `
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page<=1?'disabled':''} onclick="bulkLoad(${json.current_page-1})">←</button>
            <button class="btn btn-outline-secondary btn-sm" ${json.current_page>=json.last_page?'disabled':''} onclick="bulkLoad(${json.current_page+1})">→</button>
        `;
        bulkUpdateSelectedCount();

        if (json.migration) renderMigration(json.migration);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error: ${e.message}</td></tr>`;
    }
}

function renderMigration(m) {
    const pct = m.fk_progress_pct || 0;
    document.getElementById('migrationPct').textContent = pct + '%';
    document.getElementById('migrationBar').style.width = pct + '%';
    document.getElementById('migrationBar').className = 'progress-bar ' + (pct >= 95 ? 'bg-success' : pct >= 50 ? 'bg-info' : 'bg-warning');
    document.getElementById('migrationDetail').textContent =
        `${m.with_fk} con FK · ${m.legacy_only} solo legacy · ${m.without_category} sin categoría · ${m.total} total`;
    document.getElementById('phaseEBadge').style.display = m.ready_for_phase_e ? 'block' : 'none';
}

function bulkRowHtml(row) {
    const checked = bulkSelected.has(row.id) ? 'checked' : '';
    const img = row.image_url ? `<img src="${row.image_url}" style="width:36px;height:36px;object-fit:cover;border-radius:6px" onerror="this.style.display='none'">` : '';
    return `
    <tr>
        <td><input type="checkbox" data-id="${row.id}" onchange="bulkToggle(${row.id})" ${checked}></td>
        <td>
            <div class="d-flex gap-2 align-items-center">
                ${img}
                <div>
                    <strong style="font-size:13px">${bulkEsc(row.title)}</strong><br>
                    <small class="text-muted">#${row.id} · slug: <code>${bulkEsc(row.slug)}</code></small>
                </div>
            </div>
        </td>
        <td><small>${bulkEsc(row.tenant_fqdn || row.tenant_name || '—')}</small></td>
        <td><small class="text-muted">${row.category_name ? bulkEsc(row.category_name) : '<em>(sin categoría)</em>'}</small></td>
        <td class="text-end"><small>S/ ${parseFloat(row.display_price || row.price).toFixed(2)}</small></td>
    </tr>`;
}

function bulkEsc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function bulkToggle(id) {
    if (bulkSelected.has(id)) bulkSelected.delete(id);
    else bulkSelected.add(id);
    bulkUpdateSelectedCount();
}

function bulkToggleAll() {
    const all = document.getElementById('bulkSelectAll').checked;
    document.querySelectorAll('#bulkRows input[type="checkbox"][data-id]').forEach(cb => {
        const id = parseInt(cb.dataset.id, 10);
        if (all) bulkSelected.add(id); else bulkSelected.delete(id);
        cb.checked = all;
    });
    bulkUpdateSelectedCount();
}

function bulkUpdateSelectedCount() {
    document.getElementById('bulkSelectedCount').textContent = `${bulkSelected.size} seleccionados`;
}

async function bulkAssign() {
    const targetId = parseInt(document.getElementById('bulkTargetCategory').value, 10);
    if (!targetId) return alert('Selecciona una categoría oficial primero.');
    if (bulkSelected.size === 0) return alert('Selecciona al menos un producto.');
    if (!confirm(`¿Asignar ${bulkSelected.size} producto(s) a la categoría seleccionada?`)) return;

    try {
        const res = await fetch(`${BULK_BASE}/assign-bulk`, {
            method: 'POST',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': BULK_CSRF },
            body: JSON.stringify({
                listing_ids: Array.from(bulkSelected),
                marketplace_category_id: targetId,
            }),
        });
        const data = await res.json();
        if (!data.success) return alert(data.message || 'Error');
        alert(data.message);
        bulkSelected.clear();
        bulkLoad(bulkCurrentPage);
    } catch (e) { alert('Error: ' + e.message); }
}

document.addEventListener('DOMContentLoaded', () => {
    bulkLoadCategories();
    bulkLoad(1);
});
document.getElementById('bulkSearch').addEventListener('keypress', e => { if (e.key === 'Enter') bulkLoad(1); });
</script>
@endsection
