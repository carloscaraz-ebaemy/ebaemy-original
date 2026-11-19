@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #cmpApp { --cp-brand:#4f46e5; --cp-ink:#1f2430; --cp-muted:#697084; --cp-faint:#9aa1b4;
        --cp-line:#e5e7f0; --cp-soft:#eef0f7; --cp-surface:#fff; --cp-hover:#f5f6fc; }
    #cmpApp .cp-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #cmpApp .cp-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--cp-brand);
        display:flex; align-items:center; justify-content:center; }
    #cmpApp .cp-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px;
        line-height:1.5; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #cmpApp .cp-stats { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.5rem; margin-bottom:12px; }
    #cmpApp .cp-stat { background:var(--cp-surface); border:1px solid var(--cp-line); border-radius:12px; padding:.55rem .7rem; text-decoration:none; color:inherit; }
    #cmpApp .cp-stat:hover { background:var(--cp-hover); }
    #cmpApp .cp-stat.is-on { border-color:var(--cp-brand); box-shadow:0 0 0 2px rgba(79,70,229,.15); }
    #cmpApp .cp-stat b { display:block; font-size:1.15rem; color:var(--cp-ink); }
    #cmpApp .cp-stat span { font-size:.72rem; color:var(--cp-muted); }
    #cmpApp .cp-bar { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
    #cmpApp .cp-bar input[type=text] { flex:1 1 220px; max-width:320px; border:1px solid var(--cp-line);
        border-radius:9px; padding:.45rem .7rem; font-size:.82rem; }
    #cmpApp .cp-btn { border:1px solid var(--cp-line); background:var(--cp-surface); color:var(--cp-ink);
        border-radius:8px; padding:.38rem .65rem; font-size:.76rem; font-weight:600; cursor:pointer; text-decoration:none; white-space:nowrap; }
    #cmpApp .cp-btn:hover { background:var(--cp-hover); color:#3730a3; }
    #cmpApp .cp-btn.is-on { background:var(--cp-brand); border-color:var(--cp-brand); color:#fff; }
    #cmpApp .cp-card { background:var(--cp-surface); border:1px solid var(--cp-line); border-radius:14px; overflow:hidden; }
    #cmpApp .cp-prod { padding:.65rem .8rem; border-bottom:1px solid var(--cp-line); }
    #cmpApp .cp-row { display:grid; grid-template-columns:52px minmax(0,1fr) 220px 150px; gap:.7rem; align-items:center; }
    #cmpApp .cp-thumb { width:52px; height:52px; border-radius:9px; object-fit:contain; background:#f7f8fb; border:1px solid var(--cp-soft); }
    #cmpApp .cp-thumb--none { display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1.1rem; background:#fffbeb; border-color:#fde68a; }
    #cmpApp .cp-name { font-weight:600; color:var(--cp-ink); font-size:.84rem; }
    #cmpApp .cp-sub { font-size:.7rem; color:var(--cp-faint); }
    #cmpApp .cp-lbl { font-size:.66rem; color:var(--cp-muted); text-transform:uppercase; letter-spacing:.04em; font-weight:700; margin-bottom:2px; }
    #cmpApp input.cp-in { width:100%; border:1px solid var(--cp-line); border-radius:7px; padding:.3rem .5rem; font-size:.78rem; }
    #cmpApp input.cp-in:focus { outline:none; border-color:#cdd4f8; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    #cmpApp input.cp-in.is-empty { background:#fffbeb; border-color:#fde68a; }
    #cmpApp .cp-ok { color:#15803d; font-weight:700; font-size:.76rem; }
    #cmpApp .cp-miss { color:#b45309; font-weight:700; font-size:.76rem; }
    #cmpApp .cp-vars { margin:.55rem 0 0 62px; border-left:3px solid var(--cp-soft); padding-left:.7rem; }
    #cmpApp .cp-vars__head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.3rem; }
    #cmpApp .cp-var { display:grid; grid-template-columns:minmax(0,1fr) 200px 28px; gap:.6rem; align-items:center; padding:.2rem 0; }
    #cmpApp .cp-progress { height:7px; border-radius:99px; background:var(--cp-soft); overflow:hidden; margin-bottom:6px; }
    #cmpApp .cp-progress span { display:block; height:100%; background:#22c55e; }
    @media (max-width:768px) {
        #cmpApp .cp-stats { grid-template-columns:repeat(2, minmax(0,1fr)); }
        #cmpApp .cp-row { grid-template-columns:52px minmax(0,1fr); }
        #cmpApp .cp-row > .cp-col { grid-column:1 / -1; }
        #cmpApp .cp-vars { margin-left:0; }
        #cmpApp .cp-var { grid-template-columns:minmax(0,1fr) 150px 24px; }
    }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="cmpApp"
     data-brand="{{ route('tenant.items.completar.brand') }}"
     data-sku="{{ route('tenant.items.completar.sku') }}">

    <div class="cp-head">
        <div class="cp-head__ic"><i class="fas fa-clipboard-check"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Completar productos</h4>
            <small class="text-muted">Marca, foto y SKU de cada variante: lo que piden la tienda online, el marketplace y Saga para publicar bien.</small>
        </div>
    </div>

    @php
        $r = $resumen;
        $pct = $r['total'] > 0 ? round(($r['total'] - $r['incompletos']) * 100 / $r['total']) : 0;
        $url = fn ($f) => route('tenant.items.completar', array_filter(['falta' => $f, 'q' => $q]));
    @endphp
    <div class="cp-progress"><span style="width:{{ $pct }}%"></span></div>
    <div class="cp-sub mb-2">{{ $r['total'] - $r['incompletos'] }} de {{ $r['total'] }} productos completos ({{ $pct }}%)</div>

    <div class="cp-stats">
        <a class="cp-stat {{ $falta === 'algo' ? 'is-on' : '' }}" href="{{ $url(null) }}"><b>{{ $r['incompletos'] }}</b><span>Con algo pendiente</span></a>
        <a class="cp-stat {{ $falta === 'marca' ? 'is-on' : '' }}" href="{{ $url('marca') }}"><b>{{ $r['sin_marca'] }}</b><span>Sin marca</span></a>
        <a class="cp-stat {{ $falta === 'foto' ? 'is-on' : '' }}" href="{{ $url('foto') }}"><b>{{ $r['sin_foto'] }}</b><span>Sin foto</span></a>
        <a class="cp-stat {{ $falta === 'sku' ? 'is-on' : '' }}" href="{{ $url('sku') }}"><b>{{ $r['var_sin_sku'] }}</b><span>Variantes sin SKU</span></a>
    </div>

    <div class="cp-note">
        Se guarda solo al salir de cada casilla. <strong>Marca:</strong> escribe o elige; si no existe, se crea.
        <strong>SKU de variante:</strong> sin él, todas las variantes comparten el código del producto y Saga o
        Mercado Libre no pueden distinguirlas — «Proponer SKUs» los arma solos a partir del código y la variante.
        <strong>Foto:</strong> se sube desde la ficha del producto (botón «Subir foto»).
    </div>

    <div class="cp-bar">
        <form method="GET" style="display:contents;">
            @if($falta !== 'algo')<input type="hidden" name="falta" value="{{ $falta }}">@endif
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar producto o código…">
            <button class="cp-btn" type="submit">Buscar</button>
        </form>
        <a class="cp-btn {{ $falta === 'todos' ? 'is-on' : '' }} ms-auto" href="{{ $url('todos') }}">Ver todos</a>
    </div>

    <datalist id="brandOpts">
        @foreach($marcas as $m)<option value="{{ $m }}"></option>@endforeach
    </datalist>

    <div class="cp-card">
        @forelse($items as $it)
            @php
                $tieneFoto = $it->image && !in_array($it->image, $sinFoto, true);
                $variantes = $it->has_variants ? $it->variants : collect();
                $codigo = $it->internal_id ?: $it->item_code;
            @endphp
            <div class="cp-prod" data-item="{{ $it->id }}" data-code="{{ $codigo }}">
                <div class="cp-row">
                    @if($tieneFoto)
                        <img class="cp-thumb" src="{{ $it->getImageUrl() }}" alt="" loading="lazy">
                    @else
                        <div class="cp-thumb cp-thumb--none" title="Sin foto"><i class="fas fa-camera"></i></div>
                    @endif
                    <div>
                        <div class="cp-name">{{ \Illuminate\Support\Str::limit($it->description, 80) }}</div>
                        <div class="cp-sub">Código {{ $codigo ?: '—' }}{{ $variantes->count() ? ' · ' . $variantes->count() . ' variantes' : '' }}</div>
                    </div>
                    <div class="cp-col">
                        <div class="cp-lbl">Marca</div>
                        <input class="cp-in cp-brand {{ $it->brand ? '' : 'is-empty' }}" list="brandOpts"
                               value="{{ optional($it->brand)->name }}" placeholder="Escribe la marca…">
                    </div>
                    <div class="cp-col">
                        <div class="cp-lbl">Foto</div>
                        @if($tieneFoto)
                            <span class="cp-ok">✓ Tiene foto</span>
                        @else
                            <a class="cp-btn" href="{{ route('tenant.items_ecommerce.index', ['editar' => $it->id]) }}" target="_blank" rel="noopener">📷 Subir foto</a>
                        @endif
                    </div>
                </div>

                @if($variantes->count())
                    @php $faltanSku = $variantes->filter(fn ($v) => !$v->sku)->count(); @endphp
                    <div class="cp-vars">
                        <div class="cp-vars__head">
                            <span class="cp-lbl mb-0">Variantes · SKU {!! $faltanSku ? '<span class="cp-miss">(faltan ' . $faltanSku . ')</span>' : '<span class="cp-ok">✓</span>' !!}</span>
                            @if($faltanSku)<button type="button" class="cp-btn" data-act="proponer">✨ Proponer SKUs</button>@endif
                        </div>
                        @foreach($variantes as $v)
                            <div class="cp-var" data-variant="{{ $v->id }}" data-name="{{ $v->display_name }}">
                                <div class="cp-sub" style="color:var(--cp-ink);font-size:.78rem;">{{ $v->display_name ?: 'Variante #' . $v->id }}
                                    @if(!$v->image)<span class="cp-sub"> · usa la foto del producto</span>@endif</div>
                                <input class="cp-in cp-sku {{ $v->sku ? '' : 'is-empty' }}" value="{{ $v->sku }}"
                                       placeholder="Hereda {{ $codigo }}">
                                <span class="cp-ok cp-var-ok" style="{{ $v->sku ? '' : 'display:none' }}">✓</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center text-muted py-4">
                @if($falta === 'todos') Sin resultados. @else 🎉 No queda nada pendiente aquí. @endif
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
    @if($items->total() > 20)
        <div class="mt-2 mb-2 d-flex justify-content-center">
            @include('partials.per_page_select', ['paginator' => $items, 'key' => 'items_completar'])
        </div>
    @endif
</div>

@push('scripts')
<script>
/*
 * Completar productos: guardado en línea al salir de cada casilla.
 * Delegación en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var app = document.getElementById('cmpApp');
    if (!app) return;
    var token = document.querySelector('meta[name="csrf-token"]');

    function post(url, datos) {
        var cuerpo = new URLSearchParams();
        Object.keys(datos).forEach(function (k) { cuerpo.append(k, datos[k] == null ? '' : datos[k]); });
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

    function guardarMarca(input) {
        var prod = input.closest('[data-item]');
        input.style.opacity = '.55';
        return post(app.dataset.brand, { item_id: prod.dataset.item, marca: input.value.trim() }).then(function (d) {
            input.style.opacity = '';
            if (d.error) { window.alert(d.error); return; }
            if (d.marca) input.value = d.marca;
            input.classList.toggle('is-empty', !d.complete);
        }).catch(function () { input.style.opacity = ''; window.alert('No se pudo guardar. Revisa tu conexión.'); });
    }

    function guardarSku(input) {
        var fila = input.closest('[data-variant]');
        input.style.opacity = '.55';
        return post(app.dataset.sku, { variant_id: fila.dataset.variant, sku: input.value.trim() }).then(function (d) {
            input.style.opacity = '';
            if (d.error) { window.alert(d.error); input.focus(); return; }
            if (d.errors) { window.alert(Object.values(d.errors)[0][0]); return; }
            input.classList.toggle('is-empty', !d.complete);
            fila.querySelector('.cp-var-ok').style.display = d.complete ? '' : 'none';
        }).catch(function () { input.style.opacity = ''; window.alert('No se pudo guardar. Revisa tu conexión.'); });
    }

    // «Rojo / Talla M» → «ROJO-TALLA-M»: legible y sin tildes ni espacios.
    function trozo(texto) {
        return (texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 24);
    }

    function proponer(prod) {
        var base = trozo(prod.dataset.code) || ('P' + prod.dataset.item);
        var usados = {};
        prod.querySelectorAll('.cp-sku').forEach(function (i) { if (i.value.trim()) usados[i.value.trim()] = true; });

        var cadena = Promise.resolve();
        prod.querySelectorAll('[data-variant]').forEach(function (fila) {
            var input = fila.querySelector('.cp-sku');
            if (input.value.trim()) return;
            var sku = base + '-' + (trozo(fila.dataset.name) || ('V' + fila.dataset.variant));
            var n = 2, propuesto = sku;
            while (usados[propuesto]) { propuesto = sku + '-' + n++; }
            usados[propuesto] = true;
            input.value = propuesto;
            // En serie: si el servidor rechaza uno por repetido, el aviso sale una sola vez y en orden.
            cadena = cadena.then(function () { return guardarSku(input); });
        });
    }

    document.addEventListener('change', function (ev) {
        var i = ev.target;
        if (!i.classList || !app.contains(i)) return;
        if (i.classList.contains('cp-brand')) guardarMarca(i);
        else if (i.classList.contains('cp-sku')) guardarSku(i);
    });

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('#cmpApp [data-act="proponer"]');
        if (!btn) return;
        proponer(btn.closest('[data-item]'));
        btn.style.display = 'none';
    });

    // Enter pasa a la casilla siguiente en vez de recargar la página.
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var i = ev.target;
        if (!i.classList || !i.classList.contains('cp-in') || !app.contains(i)) return;
        ev.preventDefault();
        var todas = Array.prototype.slice.call(app.querySelectorAll('.cp-in'));
        var sig = todas[todas.indexOf(i) + 1];
        if (sig) { sig.focus(); sig.select(); } else { i.blur(); }
    });
})();
</script>
@endpush
@endsection
