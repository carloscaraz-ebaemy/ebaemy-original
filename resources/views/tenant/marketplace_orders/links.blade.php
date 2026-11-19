@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #lnkApp { --lk-brand:#4f46e5; --lk-ink:#1f2430; --lk-muted:#697084; --lk-faint:#9aa1b4;
        --lk-line:#e5e7f0; --lk-soft:#eef0f7; --lk-surface:#fff; --lk-hover:#f5f6fc; }
    #lnkApp .lk-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #lnkApp .lk-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--lk-brand);
        display:flex; align-items:center; justify-content:center; }
    #lnkApp .lk-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px;
        line-height:1.5; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #lnkApp .lk-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:12px; }
    #lnkApp .lk-btn { border:1px solid var(--lk-line); background:var(--lk-surface); color:var(--lk-ink);
        border-radius:8px; padding:.42rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; text-decoration:none; white-space:nowrap; }
    #lnkApp .lk-btn:hover { background:var(--lk-hover); color:#3730a3; }
    #lnkApp .lk-btn.is-on { background:var(--lk-brand); border-color:var(--lk-brand); color:#fff; }
    #lnkApp .lk-btn--danger { color:#b91c1c; }
    #lnkApp .lk-btn--danger:hover { background:#fef2f2; color:#991b1b; }
    #lnkApp .lk-count { display:inline-block; min-width:1.4rem; padding:0 .35rem; margin-left:.3rem; border-radius:99px;
        background:rgba(0,0,0,.08); font-size:.7rem; text-align:center; }
    #lnkApp .lk-card { background:var(--lk-surface); border:1px solid var(--lk-line); border-radius:14px; overflow:hidden; }
    #lnkApp .lk-desc { font-size:.78rem; color:var(--lk-muted); padding:.6rem .8rem; border-bottom:1px solid var(--lk-soft); }
    #lnkApp table { width:100%; font-size:.82rem; margin:0; }
    #lnkApp thead th { background:#fafbff; color:var(--lk-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.55rem .6rem; border-bottom:1px solid var(--lk-line); font-weight:700; }
    #lnkApp tbody td { padding:.45rem .6rem; border-bottom:1px solid var(--lk-soft); vertical-align:middle; }
    #lnkApp tbody tr:hover { background:var(--lk-hover); }
    #lnkApp .lk-name { font-weight:600; color:var(--lk-ink); }
    #lnkApp .lk-sub { font-size:.7rem; color:var(--lk-faint); }
    #lnkApp .lk-sug { font-size:.68rem; color:#15803d; }
    #lnkApp input.lk-in { width:100%; min-width:200px; border:1px solid var(--lk-line); border-radius:7px;
        padding:.32rem .5rem; font-size:.78rem; }
    #lnkApp input.lk-in:focus { outline:none; border-color:#cdd4f8; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    #lnkApp .lk-acts { display:flex; gap:.35rem; justify-content:flex-end; }
    #lnkApp .lk-progress { height:7px; border-radius:99px; background:var(--lk-soft); overflow:hidden; margin-bottom:6px; }
    #lnkApp .lk-progress span { display:block; height:100%; background:#22c55e; }
    #lnkApp .lk-pane { display:none; }
    #lnkApp .lk-pane.is-on { display:block; }
    #lnkApp tr.is-done { opacity:.4; pointer-events:none; }
    @media (max-width:768px) {
        #lnkApp .lk-hide-sm { display:none; }
        #lnkApp thead { display:none; }
        #lnkApp tbody tr { display:block; padding:.5rem .4rem; border-bottom:1px solid var(--lk-line); }
        #lnkApp tbody td { display:block; border:0; padding:.2rem .4rem; }
        #lnkApp .lk-acts { justify-content:flex-start; }
    }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="lnkApp"
     data-link="{{ route('tenant.saga.links_link') }}"
     data-unlink="{{ route('tenant.saga.links_unlink') }}">

    <div class="lk-head">
        <div class="lk-head__ic"><i class="fas fa-link"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Enlazar con Saga</h4>
            <small class="text-muted">Cada producto de Saga debe estar enlazado con su producto del sistema para que su stock y sus ventas cuadren.</small>
        </div>
    </div>

    @if($sinCanal)
        <div class="lk-note">Esta tienda no tiene Saga Falabella conectado.</div>
    @elseif(!empty($error))
        <div class="alert alert-danger py-2 px-3 small">No se pudo leer el catálogo de Saga: {{ $error }}</div>
    @else
        @php $pct = $totalSaga > 0 ? round($totalOk * 100 / $totalSaga) : 0; @endphp
        <div class="lk-progress"><span style="width:{{ $pct }}%"></span></div>
        <div class="lk-sub mb-2">{{ $totalOk }} de {{ $totalSaga }} productos de Saga enlazados ({{ $pct }}%)</div>

        <div class="lk-note">
            Solo se <strong>lee</strong> de Saga: aquí no se cambia nada en Saga. Al enlazar, el stock del sistema se
            iguala con el de Saga en la siguiente sincronización (máximo 15 minutos) y sus ventas pasan a descontar stock.
            Escribe en la casilla para buscar; las sugerencias en verde salen por código o por parecido del nombre, revísalas antes de enlazar.
        </div>

        <div class="lk-tabs">
            <button type="button" class="lk-btn is-on" data-tab="saga">En Saga, sin enlazar <span class="lk-count" data-count="saga">{{ count($soloSaga) }}</span></button>
            <button type="button" class="lk-btn" data-tab="rotos">Enlaces que Saga no tiene <span class="lk-count" data-count="rotos">{{ count($rotos) }}</span></button>
            <button type="button" class="lk-btn" data-tab="sistema">En el sistema, sin Saga <span class="lk-count" data-count="sistema">{{ count($soloSistema) }}</span></button>
            <a class="lk-btn ms-auto" href="{{ route('tenant.saga.links_panel', ['refrescar' => 1]) }}">↻ Volver a leer Saga</a>
        </div>

        <datalist id="erpOpts">
            @foreach($opciones as $o)<option value="{{ $o['label'] }}"></option>@endforeach
        </datalist>
        <datalist id="sagaOpts">
            @foreach($soloSaga as $p)<option value="{{ $p['sku'] }}" label="{{ $p['name'] }}"></option>@endforeach
        </datalist>

        {{-- 1. En Saga, sin enlazar --}}
        <div class="lk-pane is-on" data-pane="saga">
            <div class="lk-card">
                <div class="lk-desc">Productos que vendes en Saga pero que el sistema no reconoce: sus ventas no descuentan stock y su stock no se sincroniza. Elige a qué producto del sistema corresponde cada uno.</div>
                <div class="table-responsive"><table>
                    <thead><tr><th>Producto en Saga</th><th class="lk-hide-sm">Stock Saga</th><th>Producto del sistema</th><th></th></tr></thead>
                    <tbody>
                    @forelse($soloSaga as $p)
                        <tr data-sku="{{ $p['sku'] }}" data-pane-row="saga">
                            <td><div class="lk-name">{{ \Illuminate\Support\Str::limit($p['name'], 70) }}</div>
                                <div class="lk-sub">SKU {{ $p['sku'] }}{{ $p['status'] ? ' · ' . $p['status'] : '' }}</div></td>
                            <td class="lk-hide-sm">{{ $p['stock'] ?? '—' }}</td>
                            <td><input class="lk-in" list="erpOpts" placeholder="Buscar producto del sistema…"
                                       value="{{ $p['sugerencia']['label'] ?? '' }}">
                                @if($p['sugerencia'])<div class="lk-sug">Sugerido por {{ $p['sugerencia']['por'] }}</div>@endif</td>
                            <td><div class="lk-acts"><button type="button" class="lk-btn" data-act="link-saga">Enlazar</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">🎉 Todos los productos de Saga están enlazados.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        {{-- 2. Enlaces que Saga ya no tiene --}}
        <div class="lk-pane" data-pane="rotos">
            <div class="lk-card">
                <div class="lk-desc">Productos del sistema enlazados con un SKU que Saga ya no tiene (lo borraste o cambiaste el SKU en Saga), o enlaces a productos borrados. Cámbialos al SKU correcto o quita el enlace.</div>
                <div class="table-responsive"><table>
                    <thead><tr><th>Producto del sistema</th><th class="lk-hide-sm">SKU enlazado</th><th>Nuevo SKU de Saga</th><th></th></tr></thead>
                    <tbody>
                    @forelse($rotos as $r)
                        <tr data-pane-row="rotos" data-mapping="{{ $r['mapping_id'] }}" data-variant="{{ $r['variant_id'] }}"
                            data-old-sku="{{ $r['sku'] }}"
                            data-destino="{{ $r['item_id'] ? ($r['variant_id'] ? '[#v' . $r['variant_id'] . ']' : '[#i' . $r['item_id'] . ']') : '' }}"
                            data-expanded="{{ $r['expanded'] ? 1 : 0 }}">
                            <td><div class="lk-name">{{ \Illuminate\Support\Str::limit($r['nombre'], 70) }}</div>
                                <div class="lk-sub">{{ $r['motivo'] }}{{ $r['codigo'] ? ' · código ' . $r['codigo'] : '' }}</div></td>
                            <td class="lk-hide-sm">{{ $r['sku'] }}</td>
                            <td>@if($r['item_id'])<input class="lk-in" list="sagaOpts" placeholder="SKU de Saga…">@endif</td>
                            <td><div class="lk-acts">
                                @if($r['item_id'])<button type="button" class="lk-btn" data-act="relink">Cambiar</button>@endif
                                <button type="button" class="lk-btn lk-btn--danger" data-act="unlink">Quitar enlace</button>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">🎉 Ningún enlace apunta a un SKU que Saga no tenga.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        {{-- 3. En el sistema, sin Saga --}}
        <div class="lk-pane" data-pane="sistema">
            <div class="lk-card">
                <div class="lk-desc">Productos activos del sistema sin enlace a Saga. Si ya los vendes en Saga, elige su SKU. Si no están en Saga, puedes dejarlos así o publicarlos desde Productos.</div>
                <div class="table-responsive"><table>
                    <thead><tr><th>Producto del sistema</th><th class="lk-hide-sm">Código</th><th>SKU de Saga</th><th></th></tr></thead>
                    <tbody>
                    @forelse($soloSistema as $o)
                        <tr data-pane-row="sistema" data-destino="[#{{ $o['key'] }}]">
                            <td><div class="lk-name">{{ \Illuminate\Support\Str::limit($o['nombre'], 70) }}</div></td>
                            <td class="lk-hide-sm lk-sub">{{ $o['codigo'] ?: '—' }}</td>
                            <td><input class="lk-in" list="sagaOpts" placeholder="SKU de Saga…"></td>
                            <td><div class="lk-acts"><button type="button" class="lk-btn" data-act="link-sistema">Enlazar</button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Todos los productos del sistema están enlazados con Saga.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
/*
 * Enlace con Saga. Delegación en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var app = document.getElementById('lnkApp');
    if (!app) return;
    var token = document.querySelector('meta[name="csrf-token"]');

    function post(url, datos) {
        var cuerpo = new URLSearchParams();
        Object.keys(datos).forEach(function (k) { if (datos[k] !== null && datos[k] !== undefined) cuerpo.append(k, datos[k]); });
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: cuerpo.toString()
        }).then(function (r) { return r.json(); });
    }

    function hecho(fila, msg) {
        fila.classList.add('is-done');
        var c = app.querySelector('[data-count="' + fila.dataset.paneRow + '"]');
        if (c) c.textContent = Math.max(0, parseInt(c.textContent, 10) - 1);
        if (window.toastr) window.toastr.success(msg);
    }

    function enviar(fila, url, datos) {
        fila.style.opacity = '.55';
        post(url, datos).then(function (d) {
            fila.style.opacity = '';
            if (d.error || d.message && d.success === false) { window.alert(d.error || d.message); return; }
            if (d.errors) { window.alert(Object.values(d.errors)[0][0]); return; }
            hecho(fila, d.message || 'Listo');
        }).catch(function () {
            fila.style.opacity = '';
            window.alert('No se pudo guardar. Revisa tu conexión.');
        });
    }

    document.addEventListener('click', function (ev) {
        var tab = ev.target.closest('#lnkApp [data-tab]');
        if (tab) {
            app.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('is-on', b === tab); });
            app.querySelectorAll('[data-pane]').forEach(function (p) { p.classList.toggle('is-on', p.dataset.pane === tab.dataset.tab); });
            return;
        }

        var btn = ev.target.closest('#lnkApp [data-act]');
        if (!btn) return;
        var fila = btn.closest('tr');
        var input = fila.querySelector('.lk-in');
        var valor = input ? input.value.trim() : '';

        switch (btn.dataset.act) {
            case 'link-saga':
                if (!valor) { window.alert('Elige el producto del sistema.'); return; }
                enviar(fila, app.dataset.link, { sku: fila.dataset.sku, destino: valor });
                break;
            case 'link-sistema':
            case 'relink':
                if (!valor) { window.alert('Elige el SKU de Saga.'); return; }
                enviar(fila, app.dataset.link, { sku: valor, destino: fila.dataset.destino });
                break;
            case 'unlink':
                if (!window.confirm('¿Quitar este enlace? El producto dejará de sincronizarse con Saga.')) return;
                enviar(fila, app.dataset.unlink, {
                    mapping_id: fila.dataset.mapping,
                    variant_id: fila.dataset.expanded === '1' ? fila.dataset.variant : null,
                    sku: fila.dataset.oldSku
                });
                break;
        }
    });
})();
</script>
@endpush
@endsection
