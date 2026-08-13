@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #dimApp { --dim-brand:#4f46e5; --dim-ink:#1f2430; --dim-muted:#697084; --dim-faint:#9aa1b4;
        --dim-line:#e5e7f0; --dim-soft:#eef0f7; --dim-surface:#fff; --dim-hover:#f5f6fc; }
    #dimApp .dim-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #dimApp .dim-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--dim-brand);
        display:flex; align-items:center; justify-content:center; }
    #dimApp .dim-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px;
        line-height:1.5; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #dimApp .dim-bar { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
    #dimApp .dim-bar input[type=text] { flex:1 1 220px; max-width:320px; border:1px solid var(--dim-line);
        border-radius:9px; padding:.45rem .7rem; font-size:.82rem; }
    #dimApp .dim-btn { border:1px solid var(--dim-line); background:var(--dim-surface); color:var(--dim-ink);
        border-radius:8px; padding:.42rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; text-decoration:none; }
    #dimApp .dim-btn:hover { background:var(--dim-hover); color:#3730a3; }
    #dimApp .dim-btn.is-on { background:var(--dim-brand); border-color:var(--dim-brand); color:#fff; }
    #dimApp .dim-card { background:var(--dim-surface); border:1px solid var(--dim-line); border-radius:14px; overflow:hidden; }
    #dimApp table { width:100%; font-size:.82rem; margin:0; }
    #dimApp thead th { background:#fafbff; color:var(--dim-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.55rem .6rem; border-bottom:1px solid var(--dim-line); font-weight:700; }
    #dimApp tbody td { padding:.4rem .6rem; border-bottom:1px solid var(--dim-soft); vertical-align:middle; }
    #dimApp tbody tr:hover { background:var(--dim-hover); }
    #dimApp .dim-name { font-weight:600; color:var(--dim-ink); }
    #dimApp .dim-sub { font-size:.7rem; color:var(--dim-faint); }
    #dimApp input.dim-n { width:74px; border:1px solid var(--dim-line); border-radius:7px;
        padding:.26rem .4rem; font-size:.78rem; text-align:right; }
    #dimApp input.dim-n:focus { outline:none; border-color:#cdd4f8; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    #dimApp input.dim-n.is-empty { background:#fffbeb; border-color:#fde68a; }
    #dimApp .dim-ok { color:#15803d; font-weight:700; }
    #dimApp .dim-progress { height:7px; border-radius:99px; background:var(--dim-soft); overflow:hidden; margin-bottom:12px; }
    #dimApp .dim-progress span { display:block; height:100%; background:#22c55e; }
    @media (max-width:768px) { #dimApp .dim-hide-sm { display:none; } #dimApp input.dim-n { width:58px; } }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="dimApp"
     data-save="{{ route('tenant.saga.dimensions_save') }}">

    <div class="dim-head">
        <div class="dim-head__ic"><i class="fas fa-ruler-combined"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Peso y medidas del paquete</h4>
            <small class="text-muted">Saga los exige en todas las categorías. Sin esto el producto no se publica.</small>
        </div>
    </div>

    @php $pct = $total > 0 ? round($completos * 100 / $total) : 0; @endphp
    <div class="dim-progress"><span style="width:{{ $pct }}%"></span></div>
    <div class="dim-sub mb-2">{{ number_format($completos) }} de {{ number_format($total) }} productos completos ({{ $pct }}%)</div>

    <div class="dim-note">
        Se guarda solo al salir de cada casilla. Los cuatro datos son obligatorios:
        tener únicamente el peso <strong>no sirve</strong>. Para cargarlos en bloque,
        exporta el CSV, complétalo en Excel y súbelo — la columna <code>id</code> es la
        que manda, no la cambies.
    </div>

    <div class="dim-bar">
        <form method="GET" style="display:contents;">
            @if(!$soloFaltan)<input type="hidden" name="todos" value="1">@endif
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar producto o código…">
            <button class="dim-btn" type="submit">Buscar</button>
        </form>
        <a class="dim-btn {{ $soloFaltan ? 'is-on' : '' }}"
           href="{{ route('tenant.saga.dimensions_panel', ['q' => $q]) }}">Solo incompletos</a>
        <a class="dim-btn {{ !$soloFaltan ? 'is-on' : '' }}"
           href="{{ route('tenant.saga.dimensions_panel', ['q' => $q, 'todos' => 1]) }}">Todos</a>

        <a class="dim-btn ms-auto" href="{{ route('tenant.saga.dimensions_export') }}">⬇ Exportar CSV</a>

        <form method="POST" action="{{ route('tenant.saga.dimensions_import') }}"
              enctype="multipart/form-data" style="display:flex;gap:.4rem;align-items:center;">
            @csrf
            <input type="file" name="archivo" accept=".csv,text/csv" required
                   style="font-size:.74rem;max-width:200px;">
            <button class="dim-btn" type="submit">Subir CSV</button>
        </form>
    </div>

    @if(session('success'))<div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>@endif

    <div class="dim-card">
        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="dim-hide-sm">Código</th>
                    <th class="text-end">Peso (kg)</th>
                    <th class="text-end">Largo (cm)</th>
                    <th class="text-end">Ancho (cm)</th>
                    <th class="text-end">Alto (cm)</th>
                    <th class="text-end" style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $it)
                @php
                    $completo = $it->weight > 0 && $it->length > 0 && $it->width > 0 && $it->height > 0;
                    $v = fn ($x) => ($x !== null && (float) $x > 0) ? rtrim(rtrim(number_format((float) $x, 3, '.', ''), '0'), '.') : '';
                @endphp
                <tr data-item="{{ $it->id }}">
                    <td><div class="dim-name">{{ \Illuminate\Support\Str::limit($it->description, 60) }}</div></td>
                    <td class="dim-hide-sm dim-sub">{{ $it->internal_id }}</td>
                    @foreach(['weight','length','width','height'] as $campo)
                        <td class="text-end">
                            <input type="number" step="0.01" min="0" class="dim-n {{ $v($it->{$campo}) === '' ? 'is-empty' : '' }}"
                                   data-campo="{{ $campo }}" value="{{ $v($it->{$campo}) }}">
                        </td>
                    @endforeach
                    <td class="text-end"><span class="dim-ok" style="{{ $completo ? '' : 'display:none' }}">✓</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">
                    @if($soloFaltan) 🎉 No queda ningún producto incompleto. @else Sin resultados. @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>

@push('scripts')
<script>
/*
 * Edicion en linea de peso y medidas.
 *
 * Se guarda al SALIR de la casilla y no en cada tecla: escribir "12.5"
 * dispararia cuatro guardados, y uno de ellos con el valor a medio escribir.
 *
 * Delegacion en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var app = document.getElementById('dimApp');
    if (!app) return;
    var token = document.querySelector('meta[name="csrf-token"]');

    function guardar(fila) {
        var cuerpo = new URLSearchParams();
        cuerpo.append('item_id', fila.dataset.item);

        fila.querySelectorAll('.dim-n').forEach(function (i) {
            cuerpo.append(i.dataset.campo, i.value || '');
        });

        fila.style.opacity = '.55';

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
            fila.style.opacity = '';
            if (d.error) { window.alert(d.error); return; }

            var tick = fila.querySelector('.dim-ok');
            if (tick) tick.style.display = d.complete ? '' : 'none';

            // El amarillo marca lo que aun falta: con cuatro columnas hay que
            // poder ver de un vistazo cual quedo vacia.
            fila.querySelectorAll('.dim-n').forEach(function (i) {
                i.classList.toggle('is-empty', !i.value || parseFloat(i.value) <= 0);
            });
        })
        .catch(function () {
            fila.style.opacity = '';
            window.alert('No se pudo guardar. Revisa tu conexión.');
        });
    }

    document.addEventListener('change', function (ev) {
        var i = ev.target;
        if (!i.classList || !i.classList.contains('dim-n')) return;
        var fila = i.closest('tr');
        if (fila) guardar(fila);
    });

    // Enter pasa a la casilla siguiente en vez de recargar: se carga en serie
    // y levantar la mano al mouse por cada campo es media hora perdida.
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var i = ev.target;
        if (!i.classList || !i.classList.contains('dim-n')) return;
        ev.preventDefault();
        var todas = Array.prototype.slice.call(document.querySelectorAll('.dim-n'));
        var sig = todas[todas.indexOf(i) + 1];
        if (sig) { sig.focus(); sig.select(); }
        else { i.blur(); }
    });
})();
</script>
@endpush
@endsection
