<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Pedido</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .tracking-card { max-width: 640px; margin: 48px auto; }
        .timeline { position: relative; padding-left: 36px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-step { position: relative; margin-bottom: 28px; }
        .timeline-step .dot {
            position: absolute;
            left: -29px;
            top: 2px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            background: #dee2e6;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }
        .timeline-step.completed .dot {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        .timeline-step.active .dot {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(13,110,253,.15);
        }
        .timeline-step .label { font-weight: 600; color: #212529; }
        .timeline-step.pending .label { color: #adb5bd; }
        .timeline-step .desc { font-size: 13px; color: #6c757d; margin-top: 2px; }
    </style>
</head>
<body>
<div class="tracking-card">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0">🚚 Seguimiento de Pedido</h5>
        </div>
        <div class="card-body p-4">

            {{-- Buscador --}}
            <form method="GET" action="{{ route('logistic.tracking') }}" class="mb-4">
                <label class="form-label fw-semibold">N° de guía o número de pedido</label>
                <div class="input-group">
                    <input type="text" name="q" class="form-control"
                           placeholder="Ej: NV-001-00012 o código de courier"
                           value="{{ e($query ?? '') }}" autofocus>
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
            </form>

            @if($error)
                <div class="alert alert-warning">{{ $error }}</div>
            @endif

            @if($saleNote)
                {{-- Resumen del pedido --}}
                <div class="mb-4 p-3 bg-light rounded border">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="text-muted small">Pedido</div>
                            <div class="fw-bold">{{ $saleNote->number_full }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Cliente</div>
                            <div class="fw-semibold">{{ $saleNote->customer->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Fecha</div>
                            <div>{{ $saleNote->created_at?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    @if($saleNote->courier_name)
                        <hr class="my-2">
                        <div class="d-flex gap-3 flex-wrap">
                            <div>
                                <span class="text-muted small">Courier:</span>
                                <strong>{{ $saleNote->courier_name }}</strong>
                            </div>
                            @if($saleNote->tracking_number)
                                <div>
                                    <span class="text-muted small">N° guía:</span>
                                    <strong>{{ $saleNote->tracking_number }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Línea de tiempo --}}
                <div class="timeline">
                    @foreach($timeline as $step)
                        @php
                            $cls = $step['active'] ? 'active' : ($step['completed'] ? 'completed' : 'pending');
                            $icon = $step['completed'] ? '✓' : ($step['active'] ? '●' : '○');
                        @endphp
                        <div class="timeline-step {{ $cls }}">
                            <div class="dot">{{ $step['completed'] || $step['active'] ? $step['icon'] : '' }}</div>
                            <div class="label">{{ $step['label'] }}</div>
                            <div class="desc">{{ $step['description'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
        <div class="card-footer text-muted text-center small py-2">
            Para consultas, contacta a la tienda.
        </div>
    </div>
</div>
</body>
</html>
