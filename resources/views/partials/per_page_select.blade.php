{{--
    «Filas por página» para los listados Blade paginados (SuperAdmin y
    marketplace). Mismo comportamiento que las tablas Vue (mixin per_page.js):
    20 / 50 / 100 más el tamaño de siempre de la pantalla, y la elección se
    recuerda en este equipo (localStorage, con try/catch).

    Uso:  @include('partials.per_page_select', ['paginator' => $listings, 'key' => 'admin_mp_listings'])
    El controlador debe paginar con \App\Support\PerPage::resolve(<por defecto>)
    y ->withQueryString() para que los enlaces de página conserven per_page.
--}}
@php
    $ppActual  = (int) $paginator->perPage();
    $ppOpciones = collect([20, 50, 100, $ppActual])->filter(fn ($n) => $n > 0)->unique()->sort()->values();
    $ppKey     = 'per_page_' . ($key ?? request()->path());
    $ppId      = 'pp-' . \Illuminate\Support\Str::random(6);
@endphp
@once
<style>
    /* Móvil: sin zoom de iOS al tocar el select (16 px) y área táctil de 44 px */
    @media (max-width: 600px) { .per-page-select select { font-size: 16px; min-height: 44px; } }
</style>
@endonce
<form method="GET" class="per-page-select d-inline-flex align-items-center gap-1 m-0" id="{{ $ppId }}" data-pp-key="{{ $ppKey }}">
    @foreach(request()->except(['per_page', 'page']) as $ppName => $ppValue)
        @if(is_array($ppValue))
            @foreach($ppValue as $ppV)
                @if(!is_array($ppV))
                    <input type="hidden" name="{{ $ppName }}[]" value="{{ $ppV }}">
                @endif
            @endforeach
        @else
            <input type="hidden" name="{{ $ppName }}" value="{{ $ppValue }}">
        @endif
    @endforeach
    <label for="{{ $ppId }}-sel" class="small text-muted mb-0 text-nowrap">Filas</label>
    <select id="{{ $ppId }}-sel" name="per_page" class="form-control form-control-sm" style="width:auto">
        @foreach($ppOpciones as $ppN)
            <option value="{{ $ppN }}" @selected($ppN === $ppActual)>{{ $ppN }}</option>
        @endforeach
    </select>
</form>
<script>
(function () {
    var form = document.getElementById(@json($ppId));
    if (!form) return;
    var sel = form.querySelector('select');
    var key = form.getAttribute('data-pp-key');
    sel.addEventListener('change', function () {
        try { localStorage.setItem(key, sel.value); } catch (e) {}
        form.submit();
    });
    // Sin per_page en la URL: aplicar el guardado en este equipo (si existe y es otro).
    try {
        var params = new URLSearchParams(window.location.search);
        var guardado = localStorage.getItem(key);
        if (!params.has('per_page') && guardado && guardado !== sel.value
            && Array.prototype.some.call(sel.options, function (o) { return o.value === guardado; })) {
            params.set('per_page', guardado);
            params.delete('page');
            window.location.replace(window.location.pathname + '?' + params.toString());
        }
    } catch (e) {}
})();
</script>
