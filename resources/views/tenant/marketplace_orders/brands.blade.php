@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #mrcApp { --mrc-brand:#4f46e5; --mrc-ink:#1f2430; --mrc-muted:#697084; --mrc-faint:#9aa1b4;
        --mrc-line:#e5e7f0; --mrc-soft:#eef0f7; --mrc-surface:#fff; --mrc-hover:#f5f6fc; }
    #mrcApp .mrc-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #mrcApp .mrc-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--mrc-brand);
        display:flex; align-items:center; justify-content:center; }
    #mrcApp .mrc-note { font-size:.8rem; border-radius:10px; padding:.65rem .8rem; margin-bottom:12px; line-height:1.5; }
    #mrcApp .mrc-note--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    #mrcApp .mrc-note--ok { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
    #mrcApp .mrc-card { background:var(--mrc-surface); border:1px solid var(--mrc-line); border-radius:14px; overflow:hidden; }
    #mrcApp table { width:100%; font-size:.82rem; margin:0; }
    #mrcApp thead th { background:#fafbff; color:var(--mrc-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.55rem .7rem; border-bottom:1px solid var(--mrc-line); font-weight:700; }
    #mrcApp tbody td { padding:.5rem .7rem; border-bottom:1px solid var(--mrc-soft); vertical-align:middle; }
    #mrcApp tbody tr:hover { background:var(--mrc-hover); }
    #mrcApp .mrc-name { font-weight:600; color:var(--mrc-ink); }
    #mrcApp .mrc-sub { font-size:.7rem; color:var(--mrc-faint); }
    #mrcApp input.mrc-pick { width:100%; max-width:340px; border:1px solid var(--mrc-line);
        border-radius:8px; padding:.32rem .5rem; font-size:.78rem; }
    #mrcApp input.mrc-pick:focus { outline:none; border-color:#cdd4f8; box-shadow:0 0 0 3px rgba(79,70,229,.12); }
    #mrcApp .mrc-ok { color:#15803d; font-weight:700; }
    #mrcApp .mrc-btn { border:1px solid var(--mrc-line); background:var(--mrc-surface); color:var(--mrc-ink);
        border-radius:8px; padding:.35rem .7rem; font-size:.78rem; font-weight:600; cursor:pointer; }
    #mrcApp .mrc-btn:hover { background:var(--mrc-hover); color:#3730a3; }
    #mrcApp .mrc-btn--primary { background:var(--mrc-brand); border-color:var(--mrc-brand); color:#fff; }
    #mrcApp .mrc-btn--primary:hover { background:#4338ca; color:#fff; }
    #mrcApp .mrc-sug { font-size:.7rem; color:#15803d; margin-top:.15rem; }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="mrcApp"
     @if($channel) data-save="{{ route('tenant.saga.brand_save', $channel->id) }}" @endif>

    <div class="mrc-head">
        <div class="mrc-head__ic"><i class="fas fa-tags"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Homologación de marcas · Saga</h4>
            <small class="text-muted">La marca es obligatoria: Saga solo acepta marcas de su propio catálogo.</small>
        </div>
    </div>

    @if(!$channel)
        <div class="mrc-note mrc-note--info">No hay un canal de Saga Falabella activo en esta tienda.</div>
    @else

        @if(!count($sagaBrands))
            <div class="mrc-note mrc-note--info">
                No se pudo traer el catálogo de marcas de Saga. Puedes escribir el nombre a mano,
                pero conviene reintentar: sin el catálogo no hay forma de validar que la marca exista.
            </div>
        @endif

        <div class="mrc-note mrc-note--info">
            Escribe y elige de la lista ({{ number_format(count($sagaBrands)) }} marcas de Saga). Se guarda al salir del campo.
            @if($sugeridas > 0)
                <strong>{{ $sugeridas }} marca(s) coinciden por nombre</strong> y ya están propuestas:
                revísalas y confírmalas con un botón.
            @endif
        </div>

        @if($sugeridas > 0)
            <div class="mb-3">
                <button type="button" class="mrc-btn mrc-btn--primary" id="mrcAplicarSug">
                    Aplicar las {{ $sugeridas }} coincidencias exactas
                </button>
                <span class="mrc-sub ms-2">Solo las que coinciden literalmente por nombre.</span>
            </div>
        @endif

        {{-- Una sola lista compartida: con 10.814 marcas, un <select> por fila
             serian cientos de miles de nodos y la pantalla no abriria. --}}
        <datalist id="mrcSagaList">
            @foreach($sagaBrands as $sb)
                <option value="{{ $sb['name'] }}" data-id="{{ $sb['id'] }}"></option>
            @endforeach
        </datalist>

        <div class="mrc-card">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:32%">Marca de la tienda</th>
                        <th>Marca en Saga</th>
                        <th class="text-end" style="width:60px">Estado</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr data-brand="{{ $r->brand_id }}" data-sug="{{ $r->sug_name }}">
                        <td><div class="mrc-name">{{ $r->name }}</div></td>
                        <td>
                            <input type="text" class="mrc-pick" list="mrcSagaList"
                                   value="{{ $r->saga_name }}" placeholder="Escribe para buscar…"
                                   data-current="{{ $r->saga_name }}">
                            @if($r->sug_name && !$r->saga_name)
                                <div class="mrc-sug">Coincidencia exacta: <strong>{{ $r->sug_name }}</strong></div>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="mrc-ok" style="{{ $r->saga_name ? '' : 'display:none' }}">✓</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">No hay marcas registradas en la tienda.</td></tr>
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
 * Homologacion de marcas.
 *
 * Se usa <datalist> y no <select>: son 10.814 marcas y un select por fila
 * generaria cientos de miles de nodos. El datalist va una sola vez y ademas
 * da busqueda nativa mientras se escribe.
 *
 * Delegacion en `document` (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var app = document.getElementById('mrcApp');
    if (!app || !app.dataset.save) return;

    var token = document.querySelector('meta[name="csrf-token"]');

    // nombre → id de Saga, para mandar el id y no solo el texto escrito.
    var porNombre = {};
    document.querySelectorAll('#mrcSagaList option').forEach(function (o) {
        porNombre[o.value.trim().toUpperCase()] = o.dataset.id;
    });

    function guardar(fila, valor) {
        var input = fila.querySelector('.mrc-pick');
        var tick  = fila.querySelector('.mrc-ok');
        var id    = porNombre[(valor || '').trim().toUpperCase()] || '';

        // Si escribio algo que NO esta en el catalogo, se avisa: Saga
        // rechazaria el producto y el error aparecería recien al publicar.
        if (valor && !id) {
            window.alert('"' + valor + '" no está en el catálogo de marcas de Saga. '
                       + 'Elige una de la lista o déjalo vacío.');
            input.value = input.dataset.current || '';
            return;
        }

        input.disabled = true;

        var cuerpo = new URLSearchParams();
        cuerpo.append('brand_id', fila.dataset.brand);
        cuerpo.append('saga_brand_id', id);
        cuerpo.append('saga_brand_name', valor || '');

        return fetch(app.dataset.save, {
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
            input.disabled = false;
            if (d.error) throw new Error(d.error);
            input.dataset.current = valor || '';
            if (tick) tick.style.display = valor ? '' : 'none';
            var sug = fila.querySelector('.mrc-sug');
            if (sug && valor) sug.remove();
        })
        .catch(function (e) {
            input.disabled = false;
            window.alert('No se pudo guardar: ' + e.message);
            input.value = input.dataset.current || '';
        });
    }

    document.addEventListener('change', function (ev) {
        var i = ev.target;
        if (!i.classList || !i.classList.contains('mrc-pick')) return;
        guardar(i.closest('tr'), i.value);
    });

    // Aplicar todas las coincidencias exactas de una vez.
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#mrcAplicarSug')) return;
        var btn = ev.target.closest('#mrcAplicarSug');

        var filas = Array.prototype.slice.call(document.querySelectorAll('tr[data-sug]'))
            .filter(function (f) {
                var i = f.querySelector('.mrc-pick');
                return f.dataset.sug && i && !i.value;
            });

        if (!filas.length) { window.alert('No quedan coincidencias por aplicar.'); return; }
        if (!window.confirm('Se homologarán ' + filas.length + ' marca(s) por coincidencia exacta de nombre. ¿Continuar?')) return;

        btn.disabled = true;
        btn.textContent = 'Aplicando…';

        // En serie y no en paralelo: son pocas y asi no se satura el servidor
        // ni se pierde el orden de los avisos si alguna falla.
        var i = 0;
        (function siguiente() {
            if (i >= filas.length) { window.location.reload(); return; }
            var f = filas[i++];
            f.querySelector('.mrc-pick').value = f.dataset.sug;
            Promise.resolve(guardar(f, f.dataset.sug)).then(siguiente).catch(siguiente);
        })();
    });
})();
</script>
@endpush
@endsection
