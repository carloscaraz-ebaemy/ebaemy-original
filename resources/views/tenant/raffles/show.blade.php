@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.raffles._tokens')
@endpush

@php
    $phase        = $raffle->phase();
    $phasePill    = $phase === 'proximamente' ? 'soon' : ($phase === 'en_curso' ? 'running' : 'over');
    [$open, $why] = $raffle->acceptanceWindow();
    $prizesLeft   = max(0, (int) $raffle->prize_quantity - $metrics['winners']);
@endphp

@section('content')
<div id="rfApp" class="container-fluid py-3">

    <div class="rf-head">
        <div>
            <h1 class="rf-title">{{ $raffle->name }}</h1>
            <p class="rf-sub">
                {{ $raffle->code }} ·
                <span class="rf-pill rf-pill--{{ $raffle->status }}">{{ $raffle->status_label }}</span>
                <span class="rf-pill rf-pill--{{ $phasePill }}">{{ $raffle->phase_label }}</span>
            </p>
        </div>
        <div class="rf-actions">
            <a href="{{ route('raffles.index') }}" class="rf-btn">← Sorteos</a>
            <a href="{{ route('raffles.edit', $raffle) }}" class="rf-btn">Editar</a>
            <a href="{{ route('raffles.export', $raffle) }}" target="_blank" class="rf-btn">Exportar CSV</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if(!$open && $raffle->status !== 'finished')
        <div class="alert alert-warning py-2 mb-3">
            <strong>No se aceptan participaciones ahora.</strong> {{ $why }}
        </div>
    @endif

    @if($productFilterExcludesOrders)
        <div class="alert alert-info py-2 mb-3">
            El sorteo filtra por categoría o producto, así que los <strong>pedidos de tienda virtual</strong> quedan fuera
            del universo (guardan sus ítems en un campo JSON y no son consultables por producto). Sus comprobantes y
            notas de venta asociadas sí cuentan.
        </div>
    @endif

    {{-- ── Indicadores ─────────────────────────────────────────── --}}
    <div class="rf-kpis">
        <div class="rf-kpi rf-kpi--brand">
            <div class="rf-kpi__label">Clientes elegibles</div>
            <div class="rf-kpi__value">{{ $metrics['eligible'] !== null ? $metrics['eligible'] : '—' }}</div>
            <div class="rf-kpi__hint">según los criterios</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Invitaciones enviadas</div>
            <div class="rf-kpi__value">{{ $raffle->participants()->whereNotNull('invited_at')->count() }}</div>
            <div class="rf-kpi__hint">de {{ $metrics['invited'] }} generadas</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Aceptaron</div>
            <div class="rf-kpi__value">{{ $metrics['accepted'] }}</div>
            <div class="rf-kpi__hint">{{ $metrics['acceptance'] }}% de aceptación</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Pendientes</div>
            <div class="rf-kpi__value">{{ $metrics['pending'] }}</div>
            <div class="rf-kpi__hint">{{ $metrics['declined'] }} rechazaron</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Participantes</div>
            <div class="rf-kpi__value">{{ $metrics['accepted'] }}</div>
            <div class="rf-kpi__hint">entran al sorteo</div>
        </div>
        <div class="rf-kpi rf-kpi--gold">
            <div class="rf-kpi__label">Ganadores</div>
            <div class="rf-kpi__value">{{ $metrics['winners'] }}</div>
            <div class="rf-kpi__hint">de {{ $raffle->prize_quantity }} premio(s)</div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ── Premio y vigencia ───────────────────────────────── --}}
        <div class="col-lg-5">
            <div class="rf-card">
                <div class="rf-card__head"><h2 class="rf-card__title">🏆 Premio</h2></div>
                <div class="rf-card__body">
                    <div class="rf-prize">
                        @if($raffle->prize_image)
                            <img src="{{ $raffle->prizeImageUrl('medium') }}" alt="{{ $raffle->prize_name }}" class="rf-prize__img">
                        @endif
                        <div class="flex-grow-1">
                            <div class="rf-strong" style="font-size:1rem">{{ $raffle->prize_name ?: '—' }}</div>
                            @if($raffle->prize_description)
                                <div class="rf-note">{{ $raffle->prize_description }}</div>
                            @endif
                            <div class="rf-note mt-1">
                                Cantidad: <strong>{{ $raffle->prize_quantity }}</strong>
                                @if($raffle->prize_value)
                                    · Valor referencial: <strong>S/ {{ number_format((float) $raffle->prize_value, 2) }}</strong>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($raffle->galleryUrls('small'))
                        <div class="rf-thumbs">
                            @foreach($raffle->galleryUrls('small') as $url)
                                <div class="rf-thumb"><img src="{{ $url }}" alt=""></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="rf-card">
                <div class="rf-card__head"><h2 class="rf-card__title">📅 Vigencia</h2></div>
                <div class="rf-card__body">
                    <table class="rf-table">
                        <tbody>
                            <tr><td>Inicio</td><td class="text-end rf-strong">{{ $raffle->starts_at ? $raffle->starts_at->format('d/m/Y H:i') : '—' }}</td></tr>
                            <tr><td>Cierre de registro</td><td class="text-end rf-strong">{{ $raffle->registration_closes_at ? $raffle->registration_closes_at->format('d/m/Y H:i') : '—' }}</td></tr>
                            <tr><td>Sorteo</td><td class="text-end rf-strong">{{ $raffle->draw_at ? $raffle->draw_at->format('d/m/Y H:i') : '—' }}</td></tr>
                            <tr><td>Publicación del ganador</td><td class="text-end rf-strong">{{ $raffle->winner_published_at ? $raffle->winner_published_at->format('d/m/Y H:i') : '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rf-card">
                <div class="rf-card__head"><h2 class="rf-card__title">🎯 Criterios de elegibilidad</h2></div>
                <div class="rf-card__body">
                    <table class="rf-table">
                        <tbody>
                            <tr>
                                <td>Origen</td>
                                <td class="text-end rf-strong">
                                    @php $srcs = $raffle->sources ?: ['documents','sale_notes']; @endphp
                                    {{ collect($srcs)->map(fn ($s) => \App\Models\Tenant\Raffle::SOURCES[$s] ?? $s)->implode(', ') }}
                                </td>
                            </tr>
                            <tr><td>Pago confirmado</td><td class="text-end rf-strong">{{ $raffle->require_paid ? 'Sí' : 'No' }}</td></tr>
                            <tr>
                                <td>Rango de compra</td>
                                <td class="text-end rf-strong">
                                    {{ $raffle->purchase_from ? $raffle->purchase_from->format('d/m/Y') : 'Sin límite' }}
                                    →
                                    {{ $raffle->purchase_to ? $raffle->purchase_to->format('d/m/Y') : 'Sin límite' }}
                                </td>
                            </tr>
                            <tr><td>Monto mínimo</td><td class="text-end rf-strong">{{ $raffle->min_amount ? 'S/ ' . number_format((float) $raffle->min_amount, 2) : '—' }}</td></tr>
                            <tr><td>Sucursal</td><td class="text-end rf-strong">{{ optional($raffle->establishment)->description ?: 'Todas' }}</td></tr>
                            <tr><td>Canal de venta</td><td class="text-end rf-strong">{{ optional($raffle->channel)->name ?: 'Todos' }}</td></tr>
                            <tr><td>Categorías / productos</td><td class="text-end rf-strong">{{ count((array) $raffle->category_ids) }} cat. · {{ count((array) $raffle->item_ids) }} prod.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Acciones y ganadores ────────────────────────────── --}}
        <div class="col-lg-7">
            <div class="rf-card">
                <div class="rf-card__head"><h2 class="rf-card__title">⚙️ Acciones</h2></div>
                <div class="rf-card__body">
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('raffles.sync', $raffle) }}">
                            @csrf
                            <button class="rf-btn rf-btn--primary" type="submit">Generar participantes</button>
                        </form>

                        <form method="POST" action="{{ route('raffles.invite', $raffle) }}">
                            @csrf
                            <button class="rf-btn" type="submit" @disabled(!$open)>Enviar invitaciones (WhatsApp)</button>
                        </form>

                        <form method="POST" action="{{ route('raffles.draw', $raffle) }}"
                              onsubmit="return confirm('¿Realizar el sorteo? Se elegirá al azar entre quienes aceptaron participar. Esta acción queda registrada.');">
                            @csrf
                            <input type="hidden" name="quantity" value="{{ max(1, $prizesLeft) }}">
                            <button class="rf-btn rf-btn--primary" type="submit"
                                    @disabled($raffle->status !== 'active' || $prizesLeft < 1 || $metrics['accepted'] < 1)>
                                🎲 Realizar sorteo
                            </button>
                        </form>

                        <form method="POST" action="{{ route('raffles.status', $raffle) }}" class="d-flex gap-1">
                            @csrf
                            <select name="status" class="rf-input" style="width:auto">
                                @foreach(\App\Models\Tenant\Raffle::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected($raffle->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rf-btn" type="submit">Cambiar estado</button>
                        </form>

                        @if($metrics['winners'] === 0)
                            <form method="POST" action="{{ route('raffles.destroy', $raffle) }}"
                                  onsubmit="return confirm('¿Eliminar este sorteo y todos sus participantes?');">
                                @csrf
                                <button class="rf-btn rf-btn--danger" type="submit">Eliminar</button>
                            </form>
                        @endif
                    </div>

                    <div class="rf-note mt-2">
                        <strong>Generar participantes</strong> lee los pedidos que cumplen los criterios y crea una invitación
                        por cliente (sin duplicados). Es acumulativo: los ya existentes conservan su enlace y su aceptación.
                        Solo entran al sorteo quienes <strong>aceptaron</strong> desde su enlace.
                    </div>
                </div>
            </div>

            @if($winners->count())
                <div class="rf-card">
                    <div class="rf-card__head"><h2 class="rf-card__title">🏆 Ganadores</h2></div>
                    <div class="rf-card__body">
                        @foreach($winners as $winner)
                            @php $p = $winner->participant; @endphp
                            <div class="rf-winner">
                                @if($winner->prize_image)
                                    <img src="{{ $winner->prizeImageUrl('small') }}" alt="" class="rf-winner__img">
                                @endif
                                <div class="flex-grow-1">
                                    <div class="rf-winner__name">#{{ $winner->position }} · {{ $p->full_name ?? 'Participante eliminado' }}</div>
                                    <div class="rf-winner__meta">
                                        {{ $p->document ?? '—' }} · {{ $p->phone ?? '—' }} · {{ $p->email ?? '—' }}
                                    </div>
                                    <div class="rf-winner__meta">
                                        Premio: <strong>{{ $winner->prize_name }}</strong> ·
                                        Sorteado el {{ optional($winner->drawn_at)->format('d/m/Y H:i') }}
                                        @if($winner->drawn_by_name) por {{ $winner->drawn_by_name }} @endif
                                        @if(!empty($winner->draw_snapshot['pool_size']))
                                            · entre {{ $winner->draw_snapshot['pool_size'] }} participantes
                                        @endif
                                    </div>
                                    @if($p && $p->person_id)
                                        <div class="rf-winner__meta">
                                            <a href="{{ route('tenant.persons.index', ['type' => 'customers']) }}">Ver ficha del cliente</a>
                                        </div>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('raffles.delivery', [$raffle, $winner]) }}" class="d-flex gap-1 align-items-center">
                                    @csrf
                                    <select name="delivery_status" class="rf-input" style="width:auto">
                                        @foreach(\App\Models\Tenant\RaffleWinner::DELIVERY_LABELS as $key => $label)
                                            <option value="{{ $key }}" @selected($winner->delivery_status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="delivery_note" class="rf-input" style="width:150px"
                                           value="{{ $winner->delivery_note }}" placeholder="Nota">
                                    <button class="rf-btn" type="submit">Guardar</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── Participantes ───────────────────────────────── --}}
            <div class="rf-card">
                <div class="rf-card__head">
                    <h2 class="rf-card__title">Participantes ({{ $participants->total() }})</h2>
                    <form method="GET" action="{{ route('raffles.show', $raffle) }}" class="d-flex gap-2 flex-wrap">
                        <select name="p" class="rf-input" style="width:auto" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            @foreach(\App\Models\Tenant\RaffleParticipant::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($pStatus === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="search" name="q" value="{{ request('q') }}" class="rf-input" style="width:auto" placeholder="Buscar">
                        <button class="rf-btn">Buscar</button>
                    </form>
                </div>

                <div class="rf-scroll">
                    <table class="rf-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th class="text-end">Pedidos</th>
                                <th class="text-end">Monto</th>
                                <th>Estado</th>
                                <th>Enlace</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($participants as $p)
                            <tr>
                                <td>
                                    <div class="rf-strong">
                                        @if($p->is_winner)🏆 @endif{{ $p->full_name }}
                                    </div>
                                    <div class="rf-kpi__hint">{{ $p->document ?: '—' }}</div>
                                </td>
                                <td>
                                    <div>{{ $p->phone ?: '—' }}</div>
                                    <div class="rf-kpi__hint">{{ $p->email ?: '—' }}</div>
                                </td>
                                <td class="text-end">{{ $p->orders_count }}</td>
                                <td class="text-end">S/ {{ number_format((float) $p->total_amount, 2) }}</td>
                                <td>
                                    <span class="rf-pill rf-pill--{{ $p->status }}">{{ $p->status_label }}</span>
                                    @if($p->accepted_at)
                                        <div class="rf-kpi__hint">{{ $p->accepted_at->format('d/m/Y H:i') }}</div>
                                    @elseif($p->invited_at)
                                        <div class="rf-kpi__hint">Invitado {{ $p->invited_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="rf-btn js-rf-copy" data-url="{{ $p->invitationUrl() }}">Copiar</button>
                                    @if($p->whatsappPhone())
                                        <a class="rf-btn" target="_blank" rel="noopener"
                                           href="https://wa.me/{{ $p->whatsappPhone() }}?text={{ rawurlencode('Participa en ' . $raffle->name . ': ' . $p->invitationUrl()) }}">WhatsApp</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="rf-empty">
                                        Aún no hay participantes.<br>
                                        Pulsa <strong>Generar participantes</strong> para traer a los clientes elegibles.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $participants->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* Copiar el enlace de invitación. Delegación en document (ver feedback_vue_mainwrapper_rerender). */
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('.js-rf-copy') : null;
    if (!btn) return;
    ev.preventDefault();

    var url = btn.getAttribute('data-url');
    var done = function () {
        var prev = btn.textContent;
        btn.textContent = '¡Copiado!';
        setTimeout(function () { btn.textContent = prev; }, 1400);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copia el enlace:', url); });
    } else {
        window.prompt('Copia el enlace:', url);
    }
});
</script>
@endpush
