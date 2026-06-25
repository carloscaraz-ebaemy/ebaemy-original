@extends('tenant.layouts.app')

@section('content')
<div class="container-fluid py-3" style="max-width: 880px;">
    <div class="mb-3">
        <h3 class="mb-0">⚖️ Reglas de margen</h3>
        <small class="text-muted">
            Define el margen mínimo de tu tienda. Protege tus productos de venderse con poca o ninguna ganancia.
        </small>
    </div>

    @if(session('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.pricing.settings') }}">
        @csrf

        {{-- ═══════════ GENERAL ═══════════ --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">General</h6>

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label mb-1">Margen mínimo por defecto</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="99" name="default_min_margin_pct"
                                   value="{{ old('default_min_margin_pct', $settings->default_min_margin_pct) }}"
                                   class="form-control" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Se aplica a los productos sin regla propia ni de categoría.</small>
                    </div>

                    <div class="col-12 col-md-7">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="blockBelowCost" name="block_sales_below_cost" value="1"
                                   {{ old('block_sales_below_cost', $settings->block_sales_below_cost) ? 'checked' : '' }}>
                            <label class="form-check-label" for="blockBelowCost">
                                <strong>Bloquear venta bajo costo</strong>
                            </label>
                        </div>
                        <small class="text-muted">
                            Si está activo, no podrás guardar un producto con precio menor al costo
                            (salvo que actives "Modo liquidación" en ese producto).
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════ POR CATEGORÍA ═══════════ --}}
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">Margen mínimo por categoría <span class="text-muted fw-normal">(opcional)</span></h6>
                <p class="text-muted small mb-3">
                    Define un margen mínimo distinto para una categoría. Déjalo en blanco para usar el
                    margen por defecto ({{ number_format($settings->default_min_margin_pct, 2) }}%).
                </p>

                @if($categories->isEmpty())
                    <div class="text-muted">No tienes categorías creadas todavía.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th style="width: 180px;">Margen mínimo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $cat)
                                    <tr>
                                        <td>{{ $cat->name }}</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0" max="99"
                                                       name="category_min_margins[{{ $cat->id }}]"
                                                       value="{{ old('category_min_margins.'.$cat->id, $cat->min_margin_override) }}"
                                                       placeholder="{{ number_format($settings->default_min_margin_pct, 0) }} (default)"
                                                       class="form-control">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Guardar reglas</button>
            <a href="{{ route('tenant.pricing.margin_report') }}" class="btn btn-outline-secondary">
                Ver margen erosionado →
            </a>
        </div>
    </form>
</div>
@endsection
