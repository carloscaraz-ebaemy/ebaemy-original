@extends('tenant.layouts.app')

@section('content')
<div class="container-fluid py-3" id="marginReport">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">📉 Margen erosionado</h3>
            <small class="text-muted">
                Productos que venden con pérdida o por debajo de tu margen mínimo.
                @if($summary['last_run'])
                    Última revisión: <strong>{{ \Carbon\Carbon::parse($summary['last_run'])->diffForHumans() }}</strong>
                @else
                    <span class="text-warning">Aún sin revisión nocturna — corre <code>php artisan pricing:monitor-floor</code></span>
                @endif
            </small>
        </div>
    </div>

    {{-- ═══════════════ RESUMEN ═══════════════ --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <a href="{{ route('tenant.pricing.margin_report') }}"
               class="card text-decoration-none {{ !$severity ? 'border-primary' : '' }}">
                <div class="card-body py-2">
                    <div class="text-muted small">Total alertas</div>
                    <div class="h4 mb-0">{{ $summary['loss'] + $summary['below_floor'] }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('tenant.pricing.margin_report', ['severity' => 'loss']) }}"
               class="card text-decoration-none {{ $severity === 'loss' ? 'border-danger' : '' }}">
                <div class="card-body py-2">
                    <div class="text-muted small">🔴 Con pérdida</div>
                    <div class="h4 mb-0 text-danger">{{ $summary['loss'] }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('tenant.pricing.margin_report', ['severity' => 'below_floor']) }}"
               class="card text-decoration-none {{ $severity === 'below_floor' ? 'border-warning' : '' }}">
                <div class="card-body py-2">
                    <div class="text-muted small">🟡 Bajo margen mín.</div>
                    <div class="h4 mb-0 text-warning">{{ $summary['below_floor'] }}</div>
                </div>
            </a>
        </div>
    </div>

    @if($alerts->isEmpty())
        <div class="alert alert-success">
            ✅ {{ $severity ? 'Sin productos en esta categoría de alerta.' : 'Ningún producto vende con pérdida ni bajo tu margen mínimo. ¡Bien!' }}
        </div>
    @else
        {{-- ═══════════════ TABLA (desktop) ═══════════════ --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="text-end">Costo efec.</th>
                        <th class="text-end">Precio venta</th>
                        <th class="text-end">Piso (floor)</th>
                        <th class="text-end">Margen</th>
                        <th class="text-end">Déficit/u</th>
                        <th>Canal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts as $a)
                        <tr class="{{ $a->severity === 'loss' ? 'table-danger' : 'table-warning' }}">
                            <td>
                                <span class="badge {{ $a->severity === 'loss' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ $a->severity === 'loss' ? 'PÉRDIDA' : 'FLOOR' }}
                                </span>
                                <span title="ID {{ $a->item_id }}">{{ $a->item_description ?? '(sin nombre)' }}</span>
                                @if($a->liquidation_mode)
                                    <span class="badge bg-secondary">liquidación</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $a->category_name ?? '—' }}</td>
                            <td class="text-end">S/ {{ number_format($a->effective_cost, 2) }}</td>
                            <td class="text-end fw-bold">S/ {{ number_format($a->sale_price, 2) }}</td>
                            <td class="text-end">S/ {{ number_format($a->floor_price, 2) }}</td>
                            <td class="text-end {{ $a->margin_pct < 0 ? 'text-danger fw-bold' : '' }}">
                                {{ number_format($a->margin_pct, 1) }}%
                            </td>
                            <td class="text-end text-danger">S/ {{ number_format($a->loss_per_unit, 2) }}</td>
                            <td>
                                @if($a->marketplace_publishable)<span title="Marketplace">🌐</span>@endif
                                @if($a->apply_store)<span title="Tienda virtual">🏪</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ═══════════════ CARDS (móvil) ═══════════════ --}}
        <div class="d-md-none">
            @foreach($alerts as $a)
                <div class="card mb-2 {{ $a->severity === 'loss' ? 'border-danger' : 'border-warning' }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong class="me-2">{{ $a->item_description ?? '(sin nombre)' }}</strong>
                            <span class="badge {{ $a->severity === 'loss' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                {{ $a->severity === 'loss' ? 'PÉRDIDA' : 'FLOOR' }}
                            </span>
                        </div>
                        <div class="small text-muted mb-1">
                            {{ $a->category_name ?? 'Sin categoría' }}
                            @if($a->liquidation_mode) · <span class="badge bg-secondary">liquidación</span>@endif
                            @if($a->marketplace_publishable) · 🌐@endif
                            @if($a->apply_store) · 🏪@endif
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Costo: <strong>S/ {{ number_format($a->effective_cost, 2) }}</strong></span>
                            <span>Venta: <strong>S/ {{ number_format($a->sale_price, 2) }}</strong></span>
                            <span>Piso: <strong>S/ {{ number_format($a->floor_price, 2) }}</strong></span>
                        </div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="{{ $a->margin_pct < 0 ? 'text-danger fw-bold' : '' }}">
                                Margen: {{ number_format($a->margin_pct, 1) }}%
                            </span>
                            <span class="text-danger">Déficit/u: S/ {{ number_format($a->loss_per_unit, 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            {{ $alerts->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
