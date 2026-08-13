@extends('tenant.layouts.app')

@section('content')
@push('styles')
<style>
    #mpoApp { --mpo-brand:#4f46e5; --mpo-ink:#1f2430; --mpo-muted:#697084; --mpo-faint:#9aa1b4;
        --mpo-line:#e5e7f0; --mpo-soft:#eef0f7; --mpo-surface:#fff; --mpo-hover:#f5f6fc; }
    #mpoApp .mpo-head { display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
    #mpoApp .mpo-head__ic { width:38px; height:38px; border-radius:11px; background:#eef1fe; color:var(--mpo-brand);
        display:flex; align-items:center; justify-content:center; font-size:1rem; }
    #mpoApp .mpo-tabs { display:flex; gap:3px; padding:4px; background:#f1f2f8; border-radius:12px;
        margin-bottom:12px; overflow-x:auto; }
    #mpoApp .mpo-tab { padding:.5rem .85rem; font-size:.82rem; font-weight:600; color:var(--mpo-muted);
        border-radius:9px; text-decoration:none; white-space:nowrap; }
    #mpoApp .mpo-tab.is-on { background:var(--mpo-surface); color:var(--mpo-brand); box-shadow:0 1px 3px rgba(15,23,42,.08); }
    #mpoApp .mpo-tab__n { margin-left:.35rem; font-size:.72rem; background:#ebedf6; border-radius:99px; padding:1px 7px; }
    #mpoApp .mpo-card { background:var(--mpo-surface); border:1px solid var(--mpo-line); border-radius:14px; overflow:hidden; }
    #mpoApp table { width:100%; font-size:.82rem; margin:0; }
    #mpoApp thead th { background:#fafbff; color:var(--mpo-muted); font-size:.7rem; text-transform:uppercase;
        letter-spacing:.04em; padding:.6rem .7rem; border-bottom:1px solid var(--mpo-line); font-weight:700; }
    #mpoApp tbody td { padding:.6rem .7rem; border-bottom:1px solid var(--mpo-soft); vertical-align:middle; }
    #mpoApp tbody tr:hover { background:var(--mpo-hover); }
    #mpoApp .mpo-num { font-weight:700; color:var(--mpo-ink); }
    #mpoApp .mpo-sub { font-size:.7rem; color:var(--mpo-faint); }
    #mpoApp .mpo-badge { display:inline-block; font-size:.68rem; font-weight:700; padding:.16rem .5rem;
        border-radius:99px; white-space:nowrap; }
    #mpoApp .mpo-badge--ok   { background:#f0fdf4; color:#15803d; }
    #mpoApp .mpo-badge--warn { background:#fffbeb; color:#92400e; }
    #mpoApp .mpo-badge--none { background:#f1f2f8; color:#697084; }
    #mpoApp .mpo-btn { border:1px solid var(--mpo-line); background:var(--mpo-surface); color:var(--mpo-ink);
        border-radius:8px; padding:.3rem .6rem; font-size:.75rem; font-weight:600; cursor:pointer; white-space:nowrap; }
    #mpoApp .mpo-btn:hover { background:var(--mpo-hover); border-color:#cdd4f8; color:#3730a3; }
    #mpoApp .mpo-btn--primary { background:var(--mpo-brand); border-color:var(--mpo-brand); color:#fff; }
    #mpoApp .mpo-btn--primary:hover { background:#4338ca; color:#fff; }
    #mpoApp .mpo-btn[disabled] { opacity:.5; cursor:not-allowed; }
    #mpoApp .mpo-tools { display:flex; gap:.5rem; margin-bottom:12px; flex-wrap:wrap; }
    #mpoApp .mpo-tools input { flex:1 1 240px; max-width:360px; border:1px solid var(--mpo-line);
        border-radius:9px; padding:.45rem .7rem; font-size:.82rem; }
    #mpoApp .mpo-note { font-size:.78rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a;
        border-radius:10px; padding:.6rem .75rem; margin-bottom:12px; line-height:1.45; }
    @media (max-width:768px) {
        #mpoApp .mpo-hide-sm { display:none; }
        #mpoApp table { font-size:.78rem; }
    }
</style>
@endpush

<div class="container-fluid px-2 px-md-3 py-3" id="mpoApp">

    <div class="mpo-head">
        <div class="mpo-head__ic"><i class="fas fa-store"></i></div>
        <div>
            <h4 class="mb-0" style="font-size:1.05rem;font-weight:700;">Pedidos de Saga Falabella</h4>
            <small class="text-muted">Emite el comprobante y súbelo al canal, sin retipear los datos.</small>
        </div>
    </div>

    @if(!$channel)
        <div class="mpo-note">No hay un canal de Saga Falabella activo en esta tienda.</div>
    @else

        {{-- El comprobante historico se cargo en el panel de Saga y la API no
             lo expone, asi que el sistema NO puede saber cuales ya estan. Se
             dice explicito para que nadie lea "sin comprobante" como "falta". --}}
        @if(($metrics['externos'] ?? 0) > 0)
            <div class="mpo-note">
                <strong>Ojo con los pedidos anteriores.</strong>
                {{ $metrics['externos'] }} pedidos están marcados como facturados fuera del sistema.
                Saga no expone los comprobantes por su API, así que «sin comprobante» aquí significa
                <em>no emitido desde EBAEMY</em>, no necesariamente que falte.
            </div>
        @endif

        @php
            $tabs = [
                'todos'    => ['Todos',              $metrics['todos']    ?? 0],
                'sin_doc'  => ['Sin comprobante',    $metrics['sin_doc']  ?? 0],
                'emitidos' => ['Emitidos aquí',      $metrics['emitidos'] ?? 0],
                'externos' => ['Facturados fuera',   $metrics['externos'] ?? 0],
            ];
        @endphp
        <div class="mpo-tabs">
            @foreach($tabs as $k => [$label, $n])
                <a href="{{ request()->fullUrlWithQuery(['filter' => $k, 'page' => null]) }}"
                   class="mpo-tab {{ $filter === $k ? 'is-on' : '' }}">
                    {{ $label }}<span class="mpo-tab__n">{{ number_format($n) }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="mpo-tools">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por N° de pedido o cliente…">
            <button class="mpo-btn" type="submit">Buscar</button>
            @if($q !== '')
                <a class="mpo-btn" href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}">Limpiar</a>
            @endif
        </form>

        <div class="mpo-card">
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th class="mpo-hide-sm">Documento</th>
                        <th class="text-end">Total</th>
                        <th>Comprobante</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $o)
                    @php
                        $cli = is_array($o->customer_data) ? $o->customer_data
                             : (json_decode((string) $o->customer_data, true) ?: []);
                        $doc  = $cli['document'] ?? null;
                        $tipo = ($cli['document_type'] ?? null) === '6' ? 'RUC'
                              : ((($cli['document_type'] ?? null) === '4') ? 'C.E.' : 'DNI');
                        $emitido = (bool) $o->document_id;
                        $externo = !$emitido && $o->invoice_uploaded_at;
                    @endphp
                    <tr>
                        <td>
                            <div class="mpo-num">{{ $o->external_order_id }}</div>
                            <div class="mpo-sub">{{ optional($o->ordered_at)->format('d/m/Y H:i') }} · {{ $o->status }}</div>
                        </td>
                        <td>
                            {{ $cli['name'] ?? 'Cliente' }}
                            @if(!empty($cli['invoice_required']))
                                <span class="mpo-badge mpo-badge--warn">pidió factura</span>
                            @endif
                        </td>
                        <td class="mpo-hide-sm">
                            @if($doc)
                                {{ $tipo }} {{ $doc }}
                            @else
                                {{-- Sin documento la boleta sale sin identificar al cliente. --}}
                                <span class="mpo-sub">sin documento</span>
                            @endif
                        </td>
                        <td class="text-end">S/ {{ number_format((float) $o->total, 2) }}</td>
                        <td>
                            @if($emitido)
                                <span class="mpo-badge mpo-badge--ok">Emitido</span>
                                @if($o->invoice_uploaded_at)
                                    <div class="mpo-sub">subido a Saga</div>
                                @endif
                            @elseif($externo)
                                <span class="mpo-badge mpo-badge--warn">Fuera del sistema</span>
                            @else
                                <span class="mpo-badge mpo-badge--none">Sin comprobante</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @unless($emitido)
                                <button type="button" class="mpo-btn mpo-btn--primary js-mpo"
                                        data-url="{{ route('tenant.saga.order_invoice', [$channel->id, $o->id]) }}"
                                        data-confirm="Se emitirá la boleta del pedido {{ $o->external_order_id }} por S/ {{ number_format((float) $o->total, 2) }}. Es un comprobante ante SUNAT y no se puede deshacer. ¿Continuar?">
                                    Generar boleta
                                </button>
                                @unless($externo)
                                    <button type="button" class="mpo-btn js-mpo"
                                            data-url="{{ route('tenant.saga.order_mark_invoiced', [$channel->id, $o->id]) }}"
                                            data-confirm="Marcar el pedido {{ $o->external_order_id }} como ya facturado FUERA del sistema. No se emite nada. ¿Continuar?">
                                        Ya facturado fuera
                                    </button>
                                @endunless
                            @else
                                <a class="mpo-btn" target="_blank"
                                   href="{{ route('tenant.saga.order_document', [$channel->id, $o->id, 'pdf']) }}">PDF</a>
                                @unless($o->invoice_uploaded_at)
                                    <button type="button" class="mpo-btn js-mpo"
                                            data-url="{{ route('tenant.saga.order_upload_invoice', [$channel->id, $o->id]) }}"
                                            data-confirm="Subir el PDF de la boleta al pedido {{ $o->external_order_id }} en Saga. ¿Continuar?">
                                        Subir a Saga
                                    </button>
                                @endunless
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay pedidos con este filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-3">{{ $orders->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
/*
 * Acciones del panel. Los endpoints devuelven JSON, asi que se llaman por
 * fetch y se recarga al terminar.
 *
 * Delegacion en `document`: Vue monta sobre #main-wrapper y regenera el DOM
 * (ver feedback_vue_mainwrapper_rerender).
 */
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest ? ev.target.closest('.js-mpo') : null;
        if (!btn) return;

        // Emitir es irreversible: se confirma con el monto a la vista.
        if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) return;

        btn.disabled = true;
        var textoOriginal = btn.textContent;
        btn.textContent = 'Procesando…';

        fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (!res.ok || res.d.error) {
                // El boton se reactiva: el fallo puede ser transitorio y hay
                // que poder reintentar sin recargar a mano.
                window.alert(res.d.error || res.d.message || 'No se pudo completar la acción.');
                btn.disabled = false;
                btn.textContent = textoOriginal;
                return;
            }
            window.alert(res.d.message || 'Listo.');
            window.location.reload();
        })
        .catch(function () {
            window.alert('No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.');
            btn.disabled = false;
            btn.textContent = textoOriginal;
        });
    });
})();
</script>
@endpush
@endsection
