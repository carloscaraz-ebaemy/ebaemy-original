<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de envío — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#2563eb; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:#f1f5f9; color:var(--ink); }
        .wrap { max-width:480px; margin:0 auto; padding:18px 14px 44px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:20px; box-shadow:0 6px 24px -12px rgba(15,23,42,.15); }
        .head { text-align:center; margin-bottom:16px; }
        .brand { font-size:13px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; }
        .head h1 { font-size:19px; margin:6px 0 2px; }
        .head p { margin:0; color:var(--muted); font-size:13px; }
        form { display:flex; gap:8px; margin-bottom:6px; }
        input[type=text] { flex:1; padding:11px 12px; border:1px solid var(--line); border-radius:10px; font-size:16px; }
        input:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .btn { padding:11px 18px; border:none; border-radius:10px; background:var(--brand); color:#fff; font-size:15px; font-weight:700; cursor:pointer; }
        .err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:11px 13px; border-radius:10px; font-size:13.5px; margin-top:12px; text-align:center; }
        .res { margin-top:16px; }
        .code { font-size:22px; font-weight:800; text-align:center; letter-spacing:1px; }
        .meta { text-align:center; color:var(--muted); font-size:13px; margin:4px 0 16px; }
        .steps { list-style:none; margin:0; padding:0; }
        .step { display:flex; gap:12px; align-items:flex-start; padding-bottom:14px; position:relative; }
        .step:not(:last-child)::before { content:''; position:absolute; left:13px; top:26px; bottom:-2px; width:2px; background:var(--line); }
        .step.done:not(:last-child)::before { background:var(--brand); }
        .dot { flex:0 0 auto; width:28px; height:28px; border-radius:999px; border:2px solid var(--line); background:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; color:#cbd5e1; }
        .step.done .dot { background:var(--brand); border-color:var(--brand); color:#fff; }
        .step.current .dot { border-color:var(--brand); color:var(--brand); box-shadow:0 0 0 4px rgba(37,99,235,.15); }
        .step .lbl { font-size:15px; font-weight:600; padding-top:3px; color:#94a3b8; }
        .step.done .lbl, .step.current .lbl { color:var(--ink); }
        .guide { margin-top:14px; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; text-align:center; }
        .guide .g { font-size:20px; font-weight:800; letter-spacing:1px; color:#15803d; }
        .cancel { margin-top:14px; padding:12px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:12px; text-align:center; color:#374151; font-weight:600; }
        .guide-img-box { margin-top:14px; padding:14px; border:1px solid var(--line); border-radius:14px; background:#f8fafc; }
        .gib-h { font-size:13px; font-weight:700; color:#334155; margin-bottom:8px; }
        .gib-img { width:100%; border-radius:12px; border:1px solid var(--line); display:block; }
        .gib-btns { display:flex; gap:8px; margin-top:10px; }
        .gib-btn { flex:1; text-align:center; padding:10px; border-radius:10px; background:var(--brand); color:#fff; text-decoration:none; font-weight:700; font-size:13.5px; }
        .gib-btn.ghost { background:#e2e8f0; color:#334155; }
        .foot { text-align:center; color:var(--muted); font-size:11.5px; margin-top:16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            @if(!empty($company->logo))
                <img src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="{{ $company->trade_name ?? $company->name ?? '' }}" style="max-height:56px;max-width:75%;margin:0 auto 8px;display:block;object-fit:contain;">
            @else
                <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            @endif
            <h1>📦 Seguimiento de envío</h1>
            <p>Ingresa tu código de envío (ej. ENV-000005).</p>
        </div>

        <form method="GET" action="{{ route('shipments.public.tracking') }}">
            <input type="text" name="code" value="{{ $code }}" placeholder="ENV-000005" autocomplete="off" autofocus>
            <button type="submit" class="btn">Buscar</button>
        </form>

        @if($notFound)
            <div class="err">No encontramos ningún envío con ese código. Verifícalo e intenta de nuevo.</div>
        @endif

        @if($shipment)
            @php
                // Línea de tiempo según el TIPO de entrega del envío.
                $order = $statusOrder ?? \App\Models\Tenant\ShippingRequest::statusOrderFor($shipment->delivery_type);
                // Mapear valores legados al nuevo flujo para posicionar el paso.
                $legacyMap = ['pendiente' => 'recibido', 'listo' => 'embalando', 'enviado' => 'en_agencia'];
                $curStatus = $legacyMap[$shipment->status] ?? $shipment->status;
                $curIdx = array_search($curStatus, $order);
                if ($curIdx === false) $curIdx = 0;
                $isCancelled = $shipment->status === 'anulado';
                $isDom = $shipment->delivery_type === \App\Models\Tenant\ShippingRequest::DELIVERY_DOMICILIO;
            @endphp
            <div class="res">
                <div class="code">{{ $shipment->shipment_code }}</div>
                <div style="text-align:center;margin:2px 0 4px;">
                    <span style="display:inline-block;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:999px;{{ $isDom ? 'background:#f3e8ff;color:#7c3aed;' : 'background:#dbeafe;color:#1d4ed8;' }}">
                        {{ $isDom ? '🏍️ Entrega a domicilio' : '📦 Envío por agencia' }}
                    </span>
                </div>
                <div class="meta">
                    {{ \Illuminate\Support\Str::of($shipment->full_name)->before(' ') }}
                    · {{ $shipment->destination_city ?: '—' }}
                    @if($shipment->shipping_agency) · {{ $shipment->shipping_agency }}@endif
                </div>
                @if($shipment->created_at)
                    <div class="meta" style="font-size:12px;">🗓️ Registrado el {{ \Carbon\Carbon::parse($shipment->created_at)->format('d/m/Y H:i') }}</div>
                @endif
                @if($isDom && $shipment->maps_link)
                    <div style="text-align:center;margin:8px 0 2px;">
                        <a href="{{ $shipment->maps_link }}" target="_blank" style="font-size:13px;color:#7c3aed;font-weight:700;text-decoration:none;">📍 Ver mi ubicación en el mapa</a>
                    </div>
                @endif

                @if($isCancelled)
                    <div class="cancel">🚫 Este envío fue anulado. Contacta con la tienda.</div>
                @else
                    <ul class="steps">
                        @foreach($order as $i => $st)
                            @php $cls = $i < $curIdx ? 'done' : ($i === $curIdx ? 'current' : ''); @endphp
                            <li class="step {{ $cls }}">
                                <span class="dot">{{ $i < $curIdx ? '✓' : $i + 1 }}</span>
                                <span class="lbl">{{ $statuses[$st] }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if($shipment->tracking_number)
                        <div class="guide">
                            <div style="font-size:11px;text-transform:uppercase;color:#16a34a;">N° de guía · {{ $shipment->shipping_agency }}</div>
                            <div class="g">{{ $shipment->tracking_number }}</div>
                            @if($shipment->sent_at)<div style="font-size:12px;color:#15803d;margin-top:2px;">Enviado el {{ \Carbon\Carbon::parse($shipment->sent_at)->format('d/m/Y') }}</div>@endif
                        </div>
                    @endif

                    @if($shipment->has_guide)
                        @php $gext = strtolower(pathinfo($shipment->shipping_guide_path, PATHINFO_EXTENSION)); @endphp
                        <div class="guide-img-box">
                            <div class="gib-h">📄 Guía de envío disponible</div>
                            @if($gext !== 'pdf')
                                <a href="{{ route('shipments.public.guide', ['code' => $shipment->shipment_code]) }}" target="_blank">
                                    <img src="{{ route('shipments.public.guide', ['code' => $shipment->shipment_code]) }}" alt="Guía de envío" class="gib-img">
                                </a>
                            @endif
                            <div class="gib-btns">
                                <a class="gib-btn" href="{{ route('shipments.public.guide', ['code' => $shipment->shipment_code]) }}" target="_blank">👁 Ver imagen</a>
                                <a class="gib-btn ghost" href="{{ route('shipments.public.guide', ['code' => $shipment->shipment_code, 'download' => 1]) }}">⬇ Descargar</a>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <div style="text-align:center;margin-top:16px;padding-top:14px;border-top:1px solid var(--line);">
            <a href="{{ route('shipments.public.form') }}" style="color:var(--brand);font-size:14px;text-decoration:none;font-weight:600;">📦 Registrar un nuevo envío</a>
        </div>

        <div class="foot">Powered by ebaemy · Registro y Control de Envíos</div>
    </div>
</div>
</body>
</html>
