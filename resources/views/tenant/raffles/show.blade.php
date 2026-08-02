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
    {{-- Resultado del ENSAYO: se distingue del sorteo real a propósito. --}}
    @if(session('simulation'))
        <div class="alert alert-info alert-dismissible fade show py-2" style="border-left:4px solid var(--rf-brand)">
            {{ session('simulation') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(!$open && $raffle->status !== 'finished')
        <div class="alert alert-warning py-2 mb-3">
            <strong>No se aceptan participaciones ahora.</strong> {{ $why }}
        </div>
    @endif

    @if($statsFail)
        <div class="alert alert-danger py-2 mb-3">
            No se pudo calcular el universo de participantes: {{ $statsFail }}
        </div>
    @endif

    {{-- ── Indicadores ─────────────────────────────────────────── --}}
    <div class="rf-kpis">
        <div class="rf-kpi rf-kpi--brand">
            <div class="rf-kpi__label">Clientes elegibles</div>
            <div class="rf-kpi__value">{{ $metrics['eligible'] !== null ? $metrics['eligible'] : '—' }}</div>
            <div class="rf-kpi__hint">{{ $source->label() }}</div>
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

                    {{-- Reparto de preferencias: qué opción está eligiendo la gente. --}}
                    @php $opts = $raffle->prizeOptions()->get(); @endphp
                    @if($opts->count())
                        <div class="rf-note mt-2 mb-1">Opciones que puede elegir el cliente:</div>
                        <table class="rf-table">
                            <tbody>
                                @foreach($opts as $opt)
                                    <tr>
                                        <td style="width:52px">
                                            @if($opt->imageUrl('small'))
                                                <img src="{{ $opt->imageUrl('small') }}" alt=""
                                                     style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--rf-line)">
                                            @endif
                                        </td>
                                        <td>
                                            <div class="rf-strong">{{ $opt->name }}</div>
                                            @if(!$opt->is_active)<div class="rf-note">Retirada (se conserva por los que ya la eligieron)</div>@endif
                                        </td>
                                        <td class="text-end rf-strong">{{ $opt->chosen_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

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
                <div class="rf-card__head">
                    <h2 class="rf-card__title">🎯 Origen de participantes</h2>
                    <a href="{{ route('raffles.edit', $raffle) }}" class="rf-btn rf-btn--ghost">Cambiar</a>
                </div>
                <div class="rf-card__body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span style="font-size:1.4rem">{{ $source->icon() }}</span>
                        <div>
                            <div class="rf-strong">{{ $source->label() }}</div>
                            <div class="rf-note">{{ $source->description() }}</div>
                        </div>
                    </div>

                    <table class="rf-table">
                        <tbody>
                            @forelse($activeFilters as $f)
                                <tr><td>{{ $f['label'] }}</td><td class="text-end rf-strong">{{ $f['value'] }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="rf-note">Sin filtros: entra todo lo que el origen devuelva.</td></tr>
                            @endforelse
                            <tr>
                                <td>Monto acumulado mínimo</td>
                                <td class="text-end rf-strong">{{ $raffle->min_amount ? 'S/ ' . number_format((float) $raffle->min_amount, 2) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Acciones y ganadores ────────────────────────────── --}}
        <div class="col-lg-7">
            {{-- ── Vista previa del universo (paso 4) ──────────────── --}}
            <div class="rf-card">
                <div class="rf-card__head">
                    <h2 class="rf-card__title">🔎 Vista previa del universo</h2>
                    <button type="button" class="rf-btn" id="rfRefreshPreview">Recalcular</button>
                </div>
                <div class="rf-card__body">
                    <div class="rf-preview" id="rfPreview">
                        <div class="rf-stat">
                            <div class="rf-stat__value">{{ $stats['found'] ?? '—' }}</div>
                            <div class="rf-stat__label">Registros encontrados</div>
                        </div>
                        <div class="rf-stat">
                            <div class="rf-stat__value">{{ $stats['unique'] ?? '—' }}</div>
                            <div class="rf-stat__label">Clientes únicos</div>
                        </div>
                        <div class="rf-stat rf-stat--warn">
                            <div class="rf-stat__value">{{ $stats['duplicates'] ?? '—' }}</div>
                            <div class="rf-stat__label">Duplicados eliminados</div>
                        </div>
                        <div class="rf-stat rf-stat--warn">
                            <div class="rf-stat__value">{{ $stats['rejected'] ?? '—' }}</div>
                            <div class="rf-stat__label">No cumplen requisitos</div>
                        </div>
                        <div class="rf-stat rf-stat--ok">
                            <div class="rf-stat__value">{{ $stats['eligible'] ?? '—' }}</div>
                            <div class="rf-stat__label">Participantes elegibles</div>
                        </div>
                    </div>

                    @if(!empty($stats['rejected']))
                        <div class="rf-note mt-2">
                            Descartados: {{ $stats['rejected_no_contact'] }} sin teléfono ni correo
                            @if($stats['rejected_min_amount'])
                                · {{ $stats['rejected_min_amount'] }} por no alcanzar el monto mínimo
                            @endif
                            .
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <form method="POST" action="{{ route('raffles.sync', $raffle) }}">
                            @csrf
                            @if($raffle->status === 'draft')
                                <input type="hidden" name="activate" value="1">
                            @endif
                            <button class="rf-btn rf-btn--primary" type="submit">
                                ✅ Confirmar participantes @if($raffle->status === 'draft') y activar @endif
                            </button>
                        </form>
                        @if($raffle->participants_confirmed_at)
                            <span class="rf-note align-self-center">
                                Última confirmación: {{ $raffle->participants_confirmed_at->format('d/m/Y H:i') }}
                            </span>
                        @endif
                    </div>

                    <div class="rf-note mt-2">
                        Confirmar es acumulativo: los participantes que ya existen conservan su enlace y su
                        aceptación, y solo se agregan los clientes nuevos que cumplan los criterios.
                    </div>
                </div>
            </div>

            {{-- ── Enlace público (paso 5) ─────────────────────────── --}}
            @if($raffle->status === 'active' && $metrics['invited'] > 0)
                <div class="rf-card">
                    <div class="rf-card__head"><h2 class="rf-card__title">🔗 Enlace de participación</h2></div>
                    <div class="rf-card__body">
                        <div class="rf-link">
                            <code>{{ url('/sorteo') }}/<em>CÓDIGO-DE-CADA-CLIENTE</em></code>
                        </div>
                        <div class="rf-note mt-2">
                            Cada cliente tiene su <strong>propio enlace</strong>: así el sistema sabe quién aceptó y
                            evita que un tercero se inscriba. Envíalos con "Enviar invitaciones", o cópialos uno a uno
                            desde la tabla de participantes.
                        </div>
                    </div>
                </div>
            @endif

            <div class="rf-card">
                <div class="rf-card__head"><h2 class="rf-card__title">⚙️ Acciones</h2></div>
                <div class="rf-card__body">
                    <div class="d-flex flex-wrap gap-2">

                        <form method="POST" action="{{ route('raffles.invite', $raffle) }}">
                            @csrf
                            <button class="rf-btn" type="submit" @disabled(!$open)>Enviar invitaciones (WhatsApp)</button>
                        </form>

                        {{-- ENSAYO: elige al azar sin guardar nada. Sirve para
                             probar el flujo con el sorteo en borrador. --}}
                        <form method="POST" action="{{ route('raffles.simulate', $raffle) }}">
                            @csrf
                            <button class="rf-btn" type="submit" @disabled($metrics['invited'] < 1)>
                                🎲 Simular sorteo (ensayo)
                            </button>
                        </form>

                        <form method="POST" action="{{ route('raffles.draw', $raffle) }}"
                              onsubmit="return confirm('¿Realizar el sorteo REAL? Se elegirá al azar entre quienes aceptaron participar y quedará registrado. Esta acción no se puede deshacer.');">
                            @csrf
                            <input type="hidden" name="quantity" value="{{ max(1, $prizesLeft) }}">
                            <button class="rf-btn rf-btn--primary" type="submit"
                                    @disabled($raffle->status !== 'active' || $prizesLeft < 1 || $metrics['accepted'] < 1)>
                                🏆 Realizar sorteo REAL
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

                    {{-- Por qué el sorteo real está bloqueado: si no se dice, el
                         botón gris no explica nada y el admin se queda atascado. --}}
                    @php
                        $faltan = [];
                        if ($raffle->status !== 'active')   $faltan[] = 'poner la campaña en <strong>Activo</strong>';
                        if ($metrics['accepted'] < 1)       $faltan[] = 'que <strong>al menos un cliente acepte</strong> desde su enlace';
                        if ($prizesLeft < 1)                $faltan[] = 'que queden premios por asignar';
                        [$openNow, $whyClosed] = $raffle->acceptanceWindow();
                    @endphp

                    @if($faltan)
                        <div class="alert alert-warning py-2 mt-3 mb-0" style="font-size:.85rem">
                            <strong>Para el sorteo REAL falta:</strong> {!! implode(' · ', $faltan) !!}.
                            @if(!$openNow)
                                <div class="mt-1">
                                    ⚠️ Además, <strong>nadie puede aceptar ahora mismo</strong>: {{ $whyClosed }}
                                    Edita las fechas del sorteo para reabrir el registro.
                                </div>
                            @endif
                            <div class="mt-1">
                                Mientras tanto puedes usar <strong>Simular sorteo</strong>: elige al azar igual que
                                el real pero <strong>no guarda nada</strong>, y funciona con la campaña en borrador.
                            </div>
                        </div>
                    @endif

                    <div class="rf-note mt-2">
                        Solo entran al sorteo real quienes <strong>aceptaron</strong> desde su enlace.
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
                                        Premio: <strong>{{ $winner->prize_name }}</strong>
                                        @if($winner->prize_option_name)
                                            · Eligió: <strong>{{ $winner->prize_option_name }}</strong>
                                        @endif
                                        ·
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
                                    @if($p->prize_option_id)
                                        <div class="rf-kpi__hint">🎁 {{ optional($p->prizeOption)->name }}</div>
                                    @endif
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
/* Recalcular la vista previa sin recargar la ficha. */
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('#rfRefreshPreview') : null;
    if (!btn) return;
    ev.preventDefault();

    var prev = btn.textContent;
    btn.textContent = 'Calculando…';
    btn.disabled = true;

    fetch({!! json_encode(route('raffles.preview', $raffle)) !!}, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) throw new Error(data.message || 'Error');

            var s = data.stats;
            var order = ['found', 'unique', 'duplicates', 'rejected', 'eligible'];
            var cells = document.querySelectorAll('#rfPreview .rf-stat__value');

            order.forEach(function (k, i) {
                if (cells[i]) cells[i].textContent = s[k];
            });
        })
        .catch(function (e) { window.alert('No se pudo recalcular: ' + e.message); })
        .finally(function () { btn.textContent = prev; btn.disabled = false; });
});

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
