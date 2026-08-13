@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #sgcApp { --sgc-brand:#4f46e5; --sgc-ink:#1f2430; --sgc-muted:#697084; --sgc-faint:#9aa1b4;
        --sgc-line:#e5e7f0; --sgc-soft:#eef0f7; --sgc-surface:#fff; --sgc-hover:#f5f6fc; }
    #sgcApp .sgc-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #sgcApp .sgc-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--sgc-brand);
        display:flex; align-items:center; justify-content:center; }
    #sgcApp .sgc-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px; line-height:1.5; }
    #sgcApp .sgc-note--warn { background:#fffbeb; border:1px solid #fde68a; color:#78350f; }
    #sgcApp .sgc-note--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #sgcApp .sgc-card { background:var(--sgc-surface); border:1px solid var(--sgc-line); border-radius:14px; overflow:hidden; }
    #sgcApp table { width:100%; font-size:.82rem; margin:0; }
    #sgcApp thead th { background:#fafbff; color:var(--sgc-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.6rem .7rem; border-bottom:1px solid var(--sgc-line); font-weight:700; }
    #sgcApp tbody td { padding:.55rem .7rem; border-bottom:1px solid var(--sgc-soft); vertical-align:middle; }
    #sgcApp tbody tr:hover { background:var(--sgc-hover); }
    #sgcApp tbody tr.is-urgent { background:#fffdf5; }
    #sgcApp .sgc-name { font-weight:600; color:var(--sgc-ink); }
    #sgcApp .sgc-sub { font-size:.7rem; color:var(--sgc-faint); }
    #sgcApp .sgc-badge { display:inline-block; font-size:.66rem; font-weight:700; padding:.14rem .45rem;
        border-radius:99px; background:#fef2f2; color:#b91c1c; margin-left:.35rem; }
    #sgcApp select.sgc-pick { width:100%; max-width:420px; border:1px solid var(--sgc-line); border-radius:8px;
        padding:.32rem .5rem; font-size:.78rem; background:var(--sgc-surface); }
    #sgcApp .sgc-ok { color:#15803d; font-size:.7rem; font-weight:700; }
    #sgcApp .sgc-tools { display:flex; gap:.5rem; margin-bottom:12px; flex-wrap:wrap; align-items:center; }
    #sgcApp .sgc-tools input { flex:1 1 220px; max-width:320px; border:1px solid var(--sgc-line);
        border-radius:9px; padding:.45rem .7rem; font-size:.82rem; }
    #sgcApp .sgc-btn { border:1px solid var(--sgc-line); background:var(--sgc-surface); border-radius:8px;
        padding:.4rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; }
    #sgcApp .sgc-btn:hover { background:var(--sgc-hover); }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="sgcApp"
     @if($channel) data-channel="{{ $channel->id }}"
     data-tree="{{ route('tenant.saga.category_tree', $channel->id) }}"
     data-save="{{ route('tenant.saga.category_save', $channel->id) }}" @endif>

    <div class="sgc-head">
        <div class="sgc-head__ic"><i class="fas fa-sitemap"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Homologación de categorías · Saga</h4>
            <small class="text-muted">Saga exige su propia categoría: sin esto el producto se rechaza.</small>
        </div>
    </div>

    @if(!$channel)
        <div class="sgc-note sgc-note--warn">No hay un canal de Saga Falabella activo en esta tienda.</div>
    @else

        @if($pendientes->count())
            {{-- Lo urgente arriba: estas son las que estan frenando publicaciones
                 ahora mismo, no una lista generica de pendientes. --}}
            <div class="sgc-note sgc-note--warn">
                <strong>{{ $pendientes->sum() }} producto(s) no se publican</strong> por
                {{ $pendientes->count() }} categoría(s) sin homologar. Están marcadas abajo y ordenadas primero:
                <strong>{{ $pendientes->keys()->take(4)->implode(', ') }}</strong>{{ $pendientes->count() > 4 ? '…' : '' }}
            </div>
        @endif

        <div class="sgc-note sgc-note--info">
            Elige la categoría de Saga que corresponda y se guarda sola. Al asignarla se traen sus
            atributos obligatorios. <strong>Ojo:</strong> algunos productos además necesitan
            <strong>peso del paquete</strong>, que se carga en la ficha del producto.
        </div>

        <div class="sgc-tools">
            <input type="text" id="sgcSearch" placeholder="Filtrar categoría…" autocomplete="off">
            <label class="sgc-sub" style="display:flex;align-items:center;gap:.35rem;cursor:pointer;">
                <input type="checkbox" id="sgcOnlyPending"> solo sin homologar
            </label>
            <span class="sgc-sub ms-auto" id="sgcCount">{{ $mapped }} de {{ $rows->count() }} homologadas</span>
        </div>

        <div class="sgc-card">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:34%">Categoría de la tienda</th>
                        <th>Categoría en Saga</th>
                        <th style="width:90px" class="text-end">Estado</th>
                    </tr>
                </thead>
                <tbody id="sgcBody">
                @php $orden = $rows->sortByDesc(fn ($r) => $pendientes[$r->name] ?? 0); @endphp
                @foreach($orden as $r)
                    @php $frena = $pendientes[$r->name] ?? 0; @endphp
                    <tr class="{{ $frena ? 'is-urgent' : '' }}"
                        data-name="{{ mb_strtolower($r->name) }}" data-mapped="{{ $r->saga_id ? 1 : 0 }}">
                        <td>
                            <div class="sgc-name">{{ $r->name }}</div>
                            @if($frena)
                                <span class="sgc-badge">frena {{ $frena }} producto(s)</span>
                            @endif
                        </td>
                        <td>
                            <select class="sgc-pick" data-category="{{ $r->category_id }}"
                                    data-current="{{ $r->saga_id }}">
                                <option value="">— sin homologar —</option>
                                @if($r->saga_id)
                                    {{-- Se conserva la actual aunque el arbol aun no
                                         haya cargado, para no mostrarla como vacia. --}}
                                    <option value="{{ $r->saga_id }}" selected
                                            data-path="{{ $r->saga_path }}">{{ $r->saga_path ?: $r->saga_name ?: $r->saga_id }}</option>
                                @endif
                            </select>
                        </td>
                        <td class="text-end">
                            <span class="sgc-ok" style="{{ $r->saga_id ? '' : 'display:none' }}">✓</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
/*
 * Homologacion de categorias.
 *
 * El arbol de Saga se pide UNA vez y se reparte a todos los selects: son
 * cientos de hojas y pedirlo por fila colgaria la pantalla.
 *
 * Delegacion en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var app = document.getElementById('sgcApp');
    if (!app || !app.dataset.tree) return;

    var token = document.querySelector('meta[name="csrf-token"]');
    var hojas = null;

    function cargarArbol() {
        var selects = document.querySelectorAll('.sgc-pick');
        if (!selects.length) return;

        selects.forEach(function (s) {
            if (!s.querySelector('option[value=""]')) return;
            s.querySelector('option[value=""]').textContent = 'Cargando categorías de Saga…';
        });

        fetch(app.dataset.tree, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error || !d.leaves) throw new Error(d.error || 'sin datos');
                hojas = d.leaves;
                pintar();
            })
            .catch(function (e) {
                selects.forEach(function (s) {
                    var v = s.querySelector('option[value=""]');
                    if (v) v.textContent = 'No se pudo cargar el árbol de Saga';
                });
                window.alert('No se pudo traer el árbol de categorías de Saga: ' + e.message);
            });
    }

    function pintar() {
        document.querySelectorAll('.sgc-pick').forEach(function (sel) {
            var actual = sel.dataset.current || '';
            var frag = document.createDocumentFragment();

            var vacio = document.createElement('option');
            vacio.value = ''; vacio.textContent = '— sin homologar —';
            frag.appendChild(vacio);

            hojas.forEach(function (h) {
                var o = document.createElement('option');
                o.value = h.id;
                o.textContent = h.path || h.name;
                o.dataset.path = h.path || '';
                o.dataset.name = h.name || '';
                if (String(h.id) === String(actual)) o.selected = true;
                frag.appendChild(o);
            });

            sel.innerHTML = '';
            sel.appendChild(frag);
        });
    }

    // Guardado inmediato: son decenas de categorias y un boton "guardar todo"
    // obligaria a rehacer el trabajo entero si algo falla a la mitad.
    document.addEventListener('change', function (ev) {
        var sel = ev.target;
        if (!sel.classList || !sel.classList.contains('sgc-pick')) return;

        var op = sel.options[sel.selectedIndex];
        var fila = sel.closest('tr');
        var tick = fila ? fila.querySelector('.sgc-ok') : null;

        sel.disabled = true;

        var cuerpo = new URLSearchParams();
        cuerpo.append('category_id', sel.dataset.category);
        cuerpo.append('saga_category_id', sel.value || '');
        cuerpo.append('saga_category_name', op ? (op.dataset.name || '') : '');
        cuerpo.append('saga_category_path', op ? (op.dataset.path || '') : '');

        fetch(app.dataset.save, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: cuerpo.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.error) throw new Error(d.error);
            sel.dataset.current = sel.value;
            if (tick) tick.style.display = sel.value ? '' : 'none';
            if (fila) fila.dataset.mapped = sel.value ? '1' : '0';
            sel.disabled = false;
        })
        .catch(function (e) {
            window.alert('No se pudo guardar: ' + e.message);
            // Se vuelve al valor anterior: dejar el select mostrando algo que
            // no se guardo haria creer que la categoria quedo homologada.
            sel.value = sel.dataset.current || '';
            sel.disabled = false;
        });
    });

    // Filtros de la tabla
    function filtrar() {
        var t = (document.getElementById('sgcSearch').value || '').toLowerCase().trim();
        var soloPend = document.getElementById('sgcOnlyPending').checked;
        document.querySelectorAll('#sgcBody tr').forEach(function (tr) {
            var okTexto = !t || (tr.dataset.name || '').indexOf(t) !== -1;
            var okPend  = !soloPend || tr.dataset.mapped === '0';
            tr.style.display = (okTexto && okPend) ? '' : 'none';
        });
    }
    document.addEventListener('input', function (e) { if (e.target.id === 'sgcSearch') filtrar(); });
    document.addEventListener('change', function (e) { if (e.target.id === 'sgcOnlyPending') filtrar(); });

    cargarArbol();
})();
</script>
@endpush
@endsection
