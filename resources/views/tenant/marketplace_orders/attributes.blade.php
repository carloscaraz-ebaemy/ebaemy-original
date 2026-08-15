@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #atrApp { --atr-brand:#4f46e5; --atr-ink:#1f2430; --atr-muted:#697084; --atr-faint:#9aa1b4;
        --atr-line:#e5e7f0; --atr-soft:#eef0f7; --atr-surface:#fff; --atr-hover:#f5f6fc; }
    #atrApp .atr-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #atrApp .atr-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--atr-brand);
        display:flex; align-items:center; justify-content:center; }
    #atrApp .atr-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px; line-height:1.5; }
    #atrApp .atr-note--warn { background:#fffbeb; border:1px solid #fde68a; color:#78350f; }
    #atrApp .atr-note--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #atrApp .atr-card { background:var(--atr-surface); border:1px solid var(--atr-line); border-radius:14px; overflow:hidden; }
    #atrApp table { width:100%; font-size:.82rem; margin:0; }
    #atrApp thead th { background:#fafbff; color:var(--atr-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.55rem .7rem; border-bottom:1px solid var(--atr-line); font-weight:700; }
    #atrApp tbody td { padding:.5rem .7rem; border-bottom:1px solid var(--atr-soft); vertical-align:middle; }
    #atrApp tbody tr:hover { background:var(--atr-hover); }
    #atrApp .atr-name { font-weight:600; color:var(--atr-ink); }
    #atrApp .atr-sub { font-size:.7rem; color:var(--atr-faint); }
    #atrApp .atr-chip { display:inline-block; font-size:.66rem; font-weight:700; padding:.14rem .45rem;
        border-radius:99px; background:#fef2f2; color:#b91c1c; margin:1px 2px 1px 0; }
    #atrApp .atr-chip--ok { background:#f0fdf4; color:#15803d; }
    #atrApp .atr-btn { border:1px solid var(--atr-line); background:var(--atr-surface); color:var(--atr-ink);
        border-radius:8px; padding:.3rem .6rem; font-size:.75rem; font-weight:600; cursor:pointer; }
    #atrApp .atr-btn:hover { background:var(--atr-hover); color:#3730a3; }
    #atrApp .atr-editor td { background:#fbfcff; }
    #atrApp .atr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:.6rem; padding:.4rem 0 .6rem; }
    #atrApp .atr-f label { display:block; font-size:.7rem; font-weight:700; color:var(--atr-muted); margin-bottom:.15rem; }
    #atrApp .atr-f small { display:block; font-size:.66rem; color:var(--atr-faint); margin-top:.15rem; line-height:1.3; }
    #atrApp .atr-f input, #atrApp .atr-f select, #atrApp .atr-f textarea {
        width:100%; border:1px solid var(--atr-line); border-radius:8px; padding:.32rem .5rem; font-size:.78rem; }
    #atrApp .atr-f--req label::after { content:' *'; color:#b91c1c; }
    #atrApp .atr-bar { display:flex; gap:.5rem; margin-bottom:12px; flex-wrap:wrap; }
    #atrApp .atr-bar input { flex:1 1 220px; max-width:320px; border:1px solid var(--atr-line);
        border-radius:9px; padding:.45rem .7rem; font-size:.82rem; }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="atrApp"
     @if($channel) data-channel="{{ $channel->id }}" @endif>

    <div class="atr-head">
        <div class="atr-head__ic"><i class="fas fa-list-check"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Atributos obligatorios · Saga</h4>
            <small class="text-muted">Registro sanitario, cantidad neta, condición… varían según la categoría.</small>
        </div>
    </div>

    @if(!$channel)
        <div class="atr-note atr-note--warn">No hay un canal de Saga Falabella activo en esta tienda.</div>
    @else

        @if($sinHomologar > 0)
            {{-- Sin categoria homologada no se sabe QUE atributos pide Saga:
                 los exige por categoria. Por eso ese paso va primero. --}}
            <div class="atr-note atr-note--warn">
                <strong>{{ $sinHomologar }} producto(s) no aparecen aquí</strong> porque su categoría todavía
                no está homologada. Saga define los atributos <em>por categoría</em>, así que hasta
                homologarla no se sabe qué pide.
                <a href="{{ route('tenant.saga.categories_panel') }}">Ir a homologar categorías →</a>
            </div>
        @endif

        <div class="atr-note atr-note--info">
            Estos datos <strong>no se guardan en la ficha del producto</strong> sino en su publicación de Saga:
            son atributos que solo le importan a este canal y cambian según la categoría.
            Al guardar, el producto queda marcado para reenviarse.
        </div>

        <form method="GET" class="atr-bar">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar producto o código…">
            <button class="atr-btn" type="submit">Buscar</button>
        </form>

        <div class="atr-card">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:38%">Producto</th>
                        <th>Categoría en Saga</th>
                        <th>Falta</th>
                        <th class="text-end" style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr data-product="{{ $r->id }}">
                        <td>
                            <div class="atr-name">{{ \Illuminate\Support\Str::limit($r->nombre, 55) }}</div>
                            <div class="atr-sub">{{ $r->codigo }}</div>
                        </td>
                        <td class="atr-sub">{{ \Illuminate\Support\Str::limit($r->categoria, 40) }}</td>
                        <td>
                            @forelse($r->faltan as $f)
                                <span class="atr-chip">{{ $f }}</span>
                            @empty
                                <span class="atr-chip atr-chip--ok">completo</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            <button type="button" class="atr-btn js-atr-open"
                                    data-load="{{ url('ecommerce/marketplace/channels/' . $channel->id . '/products/' . $r->id . '/attributes') }}"
                                    data-save="{{ url('ecommerce/marketplace/channels/' . $channel->id . '/products/' . $r->id . '/attributes') }}">
                                Editar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">
                        No hay productos con la categoría homologada todavía.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
/*
 * Editor de atributos por producto.
 *
 * El formulario se arma con lo que Saga dice que pide para ESA categoria
 * (lista cerrada donde hay opciones, texto donde no), en vez de una lista
 * fija: los atributos cambian por categoria y hay 2835 categorias.
 *
 * Delegacion en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function cerrarEditor() {
        var ab = document.querySelector('.atr-editor');
        if (ab) ab.remove();
    }

    function campo(a, valores) {
        var v = valores[a.name] != null ? valores[a.name] : '';
        var req = a.mandatory ? ' atr-f--req' : '';
        var h = '<div class="atr-f' + req + '"><label>' + esc(a.label || a.name) + '</label>';

        if (a.options && a.options.length) {
            h += '<select data-attr="' + esc(a.name) + '"><option value="">—</option>';
            a.options.forEach(function (o) {
                h += '<option value="' + esc(o) + '"' + (String(o) === String(v) ? ' selected' : '') + '>' + esc(o) + '</option>';
            });
            h += '</select>';
        } else if ((a.input_type || '') === 'textarea') {
            h += '<textarea rows="2" data-attr="' + esc(a.name) + '">' + esc(v) + '</textarea>';
        } else {
            h += '<input type="text" data-attr="' + esc(a.name) + '" value="' + esc(v) + '">';
        }

        return h + '</div>';
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.js-atr-open') : null;
        if (btn) {
            var fila = btn.closest('tr');
            var abierta = document.querySelector('.atr-editor');
            // Segundo clic en el mismo: cerrar.
            if (abierta && abierta.previousElementSibling === fila) { cerrarEditor(); return; }
            cerrarEditor();

            var tr = document.createElement('tr');
            tr.className = 'atr-editor';
            tr.innerHTML = '<td colspan="4">Cargando atributos…</td>';
            fila.parentNode.insertBefore(tr, fila.nextSibling);

            fetch(btn.dataset.load, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.error) { tr.innerHTML = '<td colspan="4" class="text-danger">' + esc(d.error) + '</td>'; return; }

                    var attrs = d.attributes || [];
                    if (!attrs.length) {
                        tr.innerHTML = '<td colspan="4" class="atr-sub">'
                            + 'Saga no devolvió atributos para esta categoría. Vuelve a guardar la homologación '
                            + 'para refrescarlos.</td>';
                        return;
                    }

                    // Los obligatorios primero: es lo que bloquea la publicación.
                    attrs.sort(function (a, b) { return (b.mandatory ? 1 : 0) - (a.mandatory ? 1 : 0); });

                    var vals = d.values || {};
                    var html = '<td colspan="4"><div class="atr-grid">';
                    attrs.forEach(function (a) { html += campo(a, vals); });
                    html += '</div><button type="button" class="atr-btn js-atr-save" data-save="'
                          + esc(btn.dataset.save) + '">Guardar atributos</button> '
                          + '<button type="button" class="atr-btn js-atr-close">Cancelar</button></td>';
                    tr.innerHTML = html;
                })
                .catch(function () {
                    tr.innerHTML = '<td colspan="4" class="text-danger">No se pudieron cargar los atributos.</td>';
                });
            return;
        }

        if (ev.target.closest && ev.target.closest('.js-atr-close')) { cerrarEditor(); return; }

        var save = ev.target.closest ? ev.target.closest('.js-atr-save') : null;
        if (!save) return;

        var caja = save.closest('.atr-editor');
        var cuerpo = new URLSearchParams();
        caja.querySelectorAll('[data-attr]').forEach(function (i) {
            if (i.value !== '') cuerpo.append('values[' + i.dataset.attr + ']', i.value);
        });

        save.disabled = true;
        save.textContent = 'Guardando…';

        fetch(save.dataset.save, {
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
            // Se recarga para recalcular que sigue faltando: el chip de la fila
            // se calcula en el servidor y quedaria mintiendo si no.
            window.location.reload();
        })
        .catch(function (e) {
            window.alert('No se pudo guardar: ' + e.message);
            save.disabled = false;
            save.textContent = 'Guardar atributos';
        });
    });
})();
</script>
@endpush
@endsection
