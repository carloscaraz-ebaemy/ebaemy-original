<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado — {{ $shipment->shipment_code }}</title>
    <style>
        :root { --brand:#4f46e5; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f1f5f9; color:var(--ink); }
        .wrap { max-width: 460px; margin:0 auto; padding: 16px 14px 40px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:18px; box-shadow:0 6px 24px -12px rgba(15,23,42,.15); }
        .brand { font-size:12px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; text-align:center; }
        .code { font-size:26px; font-weight:800; letter-spacing:1px; text-align:center; margin:4px 0 10px; }
        .dest { border-top:1px solid var(--line); border-bottom:1px solid var(--line); padding:12px 0; margin-bottom:14px; }
        .dest .n { font-size:17px; font-weight:700; }
        .dest .m { font-size:13px; color:var(--muted); margin-top:2px; }
        .cur { text-align:center; margin-bottom:16px; }
        .chip { display:inline-block; padding:6px 14px; border-radius:999px; font-weight:700; font-size:14px; color:#fff; }
        .b-pendiente{background:#6b7280;} .b-preparando{background:#f59e0b;} .b-listo{background:#0ea5e9;} .b-enviado{background:#16a34a;} .b-entregado{background:#4f46e5;} .b-anulado{background:#111827;}
        .lbl { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
        .btn { display:block; width:100%; padding:15px; border:2px solid var(--line); border-radius:14px; background:#fff; font-size:16px; font-weight:700; color:var(--ink); cursor:pointer; margin-bottom:10px; text-align:center; }
        .btn.is-current { border-color:var(--brand); background:#eef2ff; color:var(--brand); }
        .btn:active { transform:scale(.99); }
        .ok { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:12px; padding:11px 13px; font-size:14px; margin-bottom:14px; text-align:center; font-weight:600; }
        .foot { text-align:center; color:var(--muted); font-size:11.5px; margin-top:16px; }
        form { margin:0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        @if(!empty($company->logo))
            <img src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="{{ $company->trade_name ?? '' }}" style="max-height:48px;max-width:70%;margin:0 auto 6px;display:block;object-fit:contain;">
        @else
            <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
        @endif
        <div class="code">{{ $shipment->shipment_code }}</div>

        @if(session('success'))
            <div class="ok">✅ {{ session('success') }}</div>
        @endif

        <div class="dest">
            <div class="n">{{ $shipment->full_name }}</div>
            <div class="m">
                @if($shipment->phone)📱 {{ $shipment->phone }} · @endif
                {{ $shipment->destination_city ?: '—' }}@if($shipment->shipping_agency) · 🚚 {{ $shipment->shipping_agency }}@endif
            </div>
        </div>

        <div class="cur">
            <div class="lbl">Estado actual</div>
            <span class="chip b-{{ $shipment->status }}">{{ $shipment->status_label }}</span>
        </div>

        <div class="lbl">Marcar como:</div>
        @foreach($statuses as $val => $lbl)
            @if($val !== 'anulado')
                <form method="POST" action="{{ route('shipments.status', $shipment->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $val }}">
                    <button type="submit" class="btn {{ $shipment->status === $val ? 'is-current' : '' }}">
                        {{ $lbl }} @if($shipment->status === $val) ✓ @endif
                    </button>
                </form>
            @endif
        @endforeach

        <div class="foot">Registro y Control de Envíos · ebaemy</div>
    </div>
</div>
</body>
</html>
