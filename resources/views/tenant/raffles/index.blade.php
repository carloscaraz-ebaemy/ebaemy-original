@extends('tenant.layouts.app')

@push('styles')
    @include('tenant.raffles._tokens')
@endpush

@section('content')
<div id="rfApp" class="container-fluid py-3">

    <div class="rf-head">
        <div>
            <h1 class="rf-title">🎁 Sorteos</h1>
            <p class="rf-sub">Campañas para clientes con pedidos pagados. Invitación por enlace único y selección aleatoria del ganador.</p>
        </div>
        <div class="rf-actions">
            <a href="{{ route('raffles.create') }}" class="rf-btn rf-btn--primary">+ Nuevo sorteo</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Indicadores globales del módulo --}}
    <div class="rf-kpis">
        <div class="rf-kpi rf-kpi--brand">
            <div class="rf-kpi__label">Sorteos activos</div>
            <div class="rf-kpi__value">{{ $globals['active'] }}</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Finalizados</div>
            <div class="rf-kpi__value">{{ $globals['finished'] }}</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Participantes</div>
            <div class="rf-kpi__value">{{ $globals['participants'] }}</div>
            <div class="rf-kpi__hint">invitaciones generadas</div>
        </div>
        <div class="rf-kpi">
            <div class="rf-kpi__label">Aceptaron</div>
            <div class="rf-kpi__value">{{ $globals['accepted'] }}</div>
            <div class="rf-kpi__hint">{{ $globals['acceptance'] }}% de aceptación</div>
        </div>
        <div class="rf-kpi rf-kpi--gold">
            <div class="rf-kpi__label">Ganadores</div>
            <div class="rf-kpi__value">{{ $globals['winners'] }}</div>
        </div>
    </div>

    <div class="rf-card">
        <div class="rf-card__head">
            <h2 class="rf-card__title">Campañas</h2>
            <form method="GET" action="{{ route('raffles.index') }}" class="d-flex gap-2 flex-wrap">
                <select name="status" class="rf-input" style="width:auto" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    @foreach(\App\Models\Tenant\Raffle::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ request('q') }}" class="rf-input" style="width:auto"
                       placeholder="Buscar nombre, código o premio">
                <button class="rf-btn">Buscar</button>
            </form>
        </div>

        <div class="rf-scroll">
            <table class="rf-table">
                <thead>
                    <tr>
                        <th>Sorteo</th>
                        <th>Premio</th>
                        <th>Estado</th>
                        <th>Fase</th>
                        <th>Sorteo el</th>
                        <th class="text-end">Invitados</th>
                        <th class="text-end">Aceptaron</th>
                        <th class="text-end">Ganadores</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($raffles as $raffle)
                    @php $phase = $raffle->phase(); @endphp
                    <tr>
                        <td>
                            <div class="rf-strong">{{ $raffle->name }}</div>
                            <div class="rf-kpi__hint">{{ $raffle->code }}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($raffle->prize_image)
                                    <img src="{{ $raffle->prizeImageUrl('small') }}" alt=""
                                         style="width:36px;height:36px;object-fit:cover;border-radius:7px;border:1px solid var(--rf-line)">
                                @endif
                                <span>{{ $raffle->prize_name ?: '—' }}</span>
                            </div>
                        </td>
                        <td><span class="rf-pill rf-pill--{{ $raffle->status }}">{{ $raffle->status_label }}</span></td>
                        <td>
                            <span class="rf-pill rf-pill--{{ $phase === 'proximamente' ? 'soon' : ($phase === 'en_curso' ? 'running' : 'over') }}">
                                {{ $raffle->phase_label }}
                            </span>
                        </td>
                        <td>{{ $raffle->draw_at ? $raffle->draw_at->format('d/m/Y H:i') : '—' }}</td>
                        <td class="text-end">{{ $raffle->participants_count }}</td>
                        <td class="text-end">{{ $raffle->accepted_count }}</td>
                        <td class="text-end">{{ $raffle->winners_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('raffles.show', $raffle) }}" class="rf-btn">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="rf-empty">
                                Aún no hay sorteos.<br>
                                <a href="{{ route('raffles.create') }}" class="rf-btn rf-btn--primary mt-2">Crear el primero</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $raffles->links() }}
</div>
@endsection
