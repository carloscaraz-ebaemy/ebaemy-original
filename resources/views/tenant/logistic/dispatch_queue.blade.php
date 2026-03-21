@extends('tenant.layouts.app')

@section('title', 'Cola de Despacho — Almacén')

@section('content')
{{-- Auto-recarga + alertas solo para el perfil almacén --}}
@if(auth()->user()->type === 'warehouse')
<style>
@keyframes screen-flash {
    0%,100%  { background: inherit; }
    20%,60%  { background: #ff000033; }
    40%,80%  { background: #ff000088; }
}
body.urgent-flash { animation: screen-flash 0.6s ease-in-out 4; }
</style>
<script>
// ── Audio ──────────────────────────────────────────────────────────────
// Beep corto en base64 (WAV 440 Hz 0.3s) — funciona sin gesto del usuario
// en la mayoría de navegadores modernos con audio de baja duración
var _beepSrc = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';

var _audioCtx = null;

// Activar AudioContext con el primer clic (exigido por Chrome/Firefox)
document.addEventListener('click', function(){
    if (!_audioCtx) {
        try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
        catch(e){}
    }
    if (_audioCtx && _audioCtx.state === 'suspended') _audioCtx.resume();
}, { once: false });

function _beep(freq, start, dur, ctx) {
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.6, ctx.currentTime + start);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + dur);
}

function playAlert(urgent) {
    // Intenta AudioContext primero
    if (_audioCtx && _audioCtx.state === 'running') {
        var freqs = urgent ? [880, 1100, 880, 1100] : [660, 880];
        var step  = urgent ? 0.20 : 0.22;
        freqs.forEach(function(f, i){ _beep(f, i * step, step - 0.02, _audioCtx); });
        return;
    }
    // Fallback: Audio HTML5
    try {
        var a = new Audio(_beepSrc);
        a.volume = 0.8;
        a.play().catch(function(){});
    } catch(e){}
}

// ── Parpadeo de pantalla ───────────────────────────────────────────────
function flashScreen() {
    document.body.classList.remove('urgent-flash');
    // forzar reflow para reiniciar animación
    void document.body.offsetWidth;
    document.body.classList.add('urgent-flash');
    setTimeout(function(){ document.body.classList.remove('urgent-flash'); }, 2800);
}

// Si ya hay urgentes en la carga inicial → parpadear sin sonido
@if(($counters['PENDIENTE'] ?? 0) > 0)
document.addEventListener('DOMContentLoaded', function(){
    // Solo parpadear; no sonido en carga (sin gesto del usuario)
    flashScreen();
});
@endif

// ── Polling cada 15 s ──────────────────────────────────────────────────
var _knownTotal = {{ (int)($counters['PENDIENTE'] ?? 0) + (int)($counters['PREPARANDO'] ?? 0) + (int)($counters['LISTO_DESPACHO'] ?? 0) }};
var _s = 15;
var _timer = null;

function _check() {
    fetch('/logistic/sale-notes/queue-count', {credentials:'same-origin'})
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
            if (!data) { window.location.reload(); return; }

            var newTotal = (data.counts['PENDIENTE']      || 0)
                         + (data.counts['PREPARANDO']     || 0)
                         + (data.counts['LISTO_DESPACHO'] || 0);

            if (newTotal > _knownTotal) {
                // Pedidos nuevos → alerta + parpadeo + recarga
                playAlert(!!data.has_urgent);
                if (data.has_urgent) flashScreen();
                setTimeout(function(){ window.location.reload(); }, 1800);
            } else {
                // Sin pedidos nuevos → recarga silenciosa igual
                window.location.reload();
            }
        })
        .catch(function(){ window.location.reload(); });
}

function _startTimer() {
    _s = 15;
    if (_timer) clearInterval(_timer);
    _timer = setInterval(function(){
        _s--;
        var el = document.getElementById('countdown');
        if (el) el.textContent = _s;
        if (_s <= 0) { _s = 15; _check(); }
    }, 1000);
}

// Esperar a que la página esté completamente cargada antes de iniciar el conteo
window.addEventListener('load', function(){ _startTimer(); });
</script>
@endif
<div class="container-fluid">

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-boxes text-primary me-2"></i>
                Cola de Despacho — Notas de Venta
            </h4>
            <small class="text-muted">
                Solo pedidos que requieren despacho por almacén
                <span class="ms-2 text-success" id="live-indicator" title="Se actualiza automáticamente">
                    <i class="fas fa-circle" style="font-size:8px; animation: blink 1s step-start infinite"></i>
                    <style>@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}</style>
                    Verificando en <strong id="countdown">15</strong>s
                </span>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('logistic.sale_notes.history') }}" class="btn btn-outline-success btn-sm">
                <i class="fas fa-history me-1"></i> Historial
            </a>
            <a href="{{ route('logistic.couriers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-truck me-1"></i> Couriers
            </a>
            <a href="{{ route('logistic.warehouse_queue') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-layer-group me-1"></i> Órdenes Logísticas
            </a>
        </div>
    </div>

    {{-- Alertas de sesión --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
            @if(session('guide_id'))
                &nbsp;
                <a href="{{ route('logistic.shipping_guide.pdf', session('guide_id')) }}"
                   target="_blank" class="btn btn-sm btn-outline-success ms-2">
                    <i class="fas fa-file-pdf me-1"></i> Ver Guía de Remisión PDF
                </a>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Contadores de estado --}}
    <div class="row g-3 mb-4">
        @foreach(\App\Enums\LogisticStatusEnum::queueStatuses() as $status)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fw-bold fs-3 text-{{ $status->badgeColor() }}">
                        {{ $counters[$status->value] ?? 0 }}
                    </div>
                    <small class="text-muted">{{ $status->label() }}</small>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Dashboard métricas del día --}}
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-success bg-opacity-10 text-center py-2 px-3">
                <div class="fw-bold fs-4 text-success">{{ $metrics['dispatched_today'] }}</div>
                <small class="text-muted"><i class="fas fa-check-circle me-1"></i>Despachados hoy</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info bg-opacity-10 text-center py-2 px-3">
                @php
                    $avg = $metrics['avg_minutes'];
                    $avgTxt = $avg >= 60
                        ? round($avg / 60, 1) . ' h'
                        : ($avg > 0 ? $avg . ' min' : '—');
                @endphp
                <div class="fw-bold fs-4 text-info">{{ $avgTxt }}</div>
                <small class="text-muted"><i class="fas fa-clock me-1"></i>Tiempo promedio (hoy)</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-primary bg-opacity-10 py-2 px-3">
                <small class="text-muted d-block mb-1"><i class="fas fa-user-cog me-1"></i>Despachos hoy por persona</small>
                @forelse($metrics['by_user'] as $u)
                    <div class="d-flex justify-content-between">
                        <span class="small">{{ $u->warehouseUser?->name ?? 'Sin asignar' }}</span>
                        <span class="badge bg-primary">{{ $u->total }}</span>
                    </div>
                @empty
                    <span class="small text-muted">Sin actividad hoy</span>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('logistic.sale_notes.queue') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\LogisticStatusEnum::queueStatuses() as $status)
                            <option value="{{ $status->value }}"
                                {{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="{{ route('logistic.sale_notes.queue') }}" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de pedidos --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($saleNotes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    <p>No hay pedidos en cola</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Documento</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">En cola</th>
                                <th class="text-center">Asignado a</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($saleNotes as $sn)
                                @php
                                    $mins = \Carbon\Carbon::parse($sn->created_at)->diffInMinutes(now());
                                    if ($mins < 30)        { $timeColor = 'success'; $timeTxt = $mins . ' min'; }
                                    elseif ($mins < 60)    { $timeColor = 'warning'; $timeTxt = $mins . ' min'; }
                                    elseif ($mins < 120)   { $timeColor = 'orange';  $timeTxt = round($mins/60,1) . ' h'; }
                                    else                   { $timeColor = 'danger';  $timeTxt = round($mins/60,1) . ' h'; }
                                @endphp
                                <tr class="{{ $sn->is_urgent ? 'table-danger' : '' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            @if($sn->is_urgent)
                                                <span class="badge bg-danger"><i class="fas fa-bolt"></i> URGENTE</span>
                                            @endif
                                            <span class="fw-semibold">{{ $sn->number_full ?? $sn->series . '-' . str_pad($sn->number, 8, '0', STR_PAD_LEFT) }}</span>
                                        </div>
                                        @if($sn->delivery_type)
                                            <small>
                                                <span class="badge bg-{{ $sn->delivery_type->badgeColor() }} bg-opacity-75">
                                                    <i class="{{ $sn->delivery_type->icon() }} me-1"></i>{{ $sn->delivery_type->label() }}
                                                </span>
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $sn->customer->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $sn->customer->number ?? '' }}</small>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($sn->date_of_issue)->format('d/m/Y') }}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ $sn->currency_type_id }} {{ number_format($sn->total, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if($sn->logistic_status)
                                            <span class="badge bg-{{ $sn->logistic_status->badgeColor() }}">
                                                {{ $sn->logistic_status->label() }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-dark">Sin estado</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $badgeClass = match($timeColor) {
                                                'success' => 'badge bg-success',
                                                'warning' => 'badge bg-warning text-dark',
                                                'orange'  => 'badge text-white',
                                                default   => 'badge bg-danger',
                                            };
                                            $badgeStyle = $timeColor === 'orange' ? 'background:var(--bs-orange)' : '';
                                        @endphp
                                        <span class="{{ $badgeClass }}" style="{{ $badgeStyle }}">
                                            <i class="fas fa-clock me-1"></i>{{ $timeTxt }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            {{ $sn->user?->name ?? '—' }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1 flex-wrap">
                                            {{-- Ver detalle --}}
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-detail"
                                                    title="Ver detalle"
                                                    data-id="{{ $sn->id }}">
                                                <i class="fas fa-eye me-1"></i> Detalle
                                            </button>

                                            {{-- PENDIENTE → Procesar --}}
                                            @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::PENDIENTE)
                                                <form method="POST"
                                                      action="{{ route('logistic.sale_notes.process', $sn) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-primary"
                                                            title="Iniciar preparación"
                                                            onclick="return confirm('¿Iniciar preparación del pedido?')">
                                                        <i class="fas fa-box-open"></i> Procesar
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- PREPARANDO → Listo + Devolver a cola --}}
                                            @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::PREPARANDO)
                                                <form method="POST"
                                                      action="{{ route('logistic.sale_notes.ready', $sn) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-info text-white"
                                                            title="Marcar listo"
                                                            onclick="return confirm('¿Marcar el pedido como listo?')">
                                                        <i class="fas fa-check"></i> Listo
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('logistic.sale_notes.cancel', $sn) }}"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline-warning"
                                                            title="Devolver a Pendiente"
                                                            onclick="return confirm('¿Devolver el pedido a la cola de Pendientes?')">
                                                        <i class="fas fa-undo me-1"></i> Devolver
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- LISTO_DESPACHO → Ir al form (courier o pickup) --}}
                                            @if($sn->logistic_status === \App\Enums\LogisticStatusEnum::LISTO_DESPACHO)
                                                @if($sn->delivery_type === \App\Enums\DeliveryTypeEnum::PICKUP)
                                                    <a href="{{ route('logistic.sale_notes.show', $sn) }}"
                                                       class="btn btn-xs btn-info text-white"
                                                       title="Confirmar recojo del cliente">
                                                        <i class="fas fa-hand-holding-box"></i> Entregar
                                                    </a>
                                                @else
                                                    <a href="{{ route('logistic.sale_notes.show', $sn) }}"
                                                       class="btn btn-xs btn-success"
                                                       title="Registrar despacho courier">
                                                        <i class="fas fa-truck"></i> Despachar
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="d-flex justify-content-center py-3">
                    {{ $saleNotes->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Modal Detalle NV --}}
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-box me-2"></i> Detalle de Nota de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetailBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Modal de detalle ──────────────────────────────────────────────────────────
document.querySelectorAll('.btn-detail').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.dataset.id;
        var modal = new bootstrap.Modal(document.getElementById('modalDetail'));
        document.getElementById('modalDetailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        modal.show();
        fetch('/logistic/sale-notes/queue/' + id + '/detail')
            .then(function(r) { return r.text(); })
            .then(function(html) { document.getElementById('modalDetailBody').innerHTML = html; })
            .catch(function() { document.getElementById('modalDetailBody').innerHTML = '<p class="text-danger">Error al cargar el detalle.</p>'; });
    });
});
</script>
@endpush
