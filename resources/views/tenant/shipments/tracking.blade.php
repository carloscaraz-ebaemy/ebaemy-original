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
        .code-sub { font-size:12px; font-weight:600; text-align:center; letter-spacing:.5px; color:var(--muted); margin-top:3px; }
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
        .now { margin:14px 0 6px; padding:14px 16px; border-radius:14px; }
        .now--wait { background:#f1f5f9; } .now--go { background:#eff6ff; } .now--ok { background:#ecfdf5; } .now--muted { background:#f3f4f6; }
        .now-t { font-size:18px; font-weight:800; line-height:1.25; }
        .now--go .now-t { color:#1d4ed8; } .now--ok .now-t { color:#047857; }
        .now-d { font-size:14px; color:#334155; margin-top:3px; }
        .now-eta { display:inline-block; margin-top:8px; font-size:13px; font-weight:700; color:#1d4ed8; background:#fff; border-radius:999px; padding:4px 10px; }
        .now-delay { margin-top:8px; font-size:13px; color:#7a4a00; background:#fff5e0; border-radius:10px; padding:7px 10px; }
        .tl { list-style:none; margin:12px 0 0; padding:0; }
        .tl-s { position:relative; display:flex; gap:12px; padding-bottom:14px; }
        .tl-s:not(:last-child)::before { content:''; position:absolute; left:8px; top:18px; bottom:-2px; width:2px; background:var(--line); }
        .tl-s--done:not(:last-child)::before { background:#16a34a; }
        .tl-dot { flex:0 0 auto; width:18px; height:18px; margin-top:2px; border-radius:999px; border:2px solid var(--line); background:#fff; }
        .tl-s--done .tl-dot { background:#16a34a; border-color:#16a34a; }
        .tl-s--current .tl-dot { border-color:var(--brand); background:var(--brand); box-shadow:0 0 0 4px rgba(37,99,235,.18); }
        .tl-l { font-size:15px; font-weight:600; color:#94a3b8; }
        .tl-s--done .tl-l, .tl-s--current .tl-l { color:var(--ink); }
        .tl-s--current .tl-l { font-weight:800; }
        .tl-at { font-size:12.5px; color:var(--muted); margin-top:1px; }
        .tl-h { font-size:13px; color:#475569; margin-top:2px; }
        .tl-upd { font-size:11.5px; color:var(--muted); margin:-4px 0 4px 30px; }
        .office { margin-top:14px; padding:14px 16px; border:1px solid var(--line); border-radius:14px; }
        .office-k { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--muted); }
        .office-n { font-size:16px; font-weight:800; margin-top:3px; }
        .office-a { font-size:13.5px; color:#475569; margin-top:2px; line-height:1.4; }
        .office-btn { display:block; text-align:center; margin-top:10px; padding:11px; border-radius:10px; background:var(--brand); color:#fff; font-weight:700; text-decoration:none; font-size:14.5px; }
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
            <input type="text" name="code" value="{{ $code }}" placeholder="N° de pedido o ENV-..." autocomplete="off" autofocus>
            <button type="submit" class="btn">Buscar</button>
        </form>

        @if($notFound)
            <div class="err">No encontramos ningún envío con ese número. Revisa tu número de pedido e inténtalo de nuevo.</div>
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
                {{-- Titular: el numero de PEDIDO, que es el que el cliente
                     tiene en su confirmacion y el que el operador ve en el
                     panel. El ENV- queda debajo, en chico: sigue siendo lo que
                     resuelve esta pagina, pero ya no es lo unico visible. --}}
                <div class="code">{{ $shipment->orderRef() ? 'Pedido #' . $shipment->orderRef() : $shipment->shipment_code }}</div>
                @if($shipment->orderRef())
                    <div class="code-sub">{{ $shipment->shipment_code }}</div>
                @endif
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
                @if($isDom && $shipment->maps_link && empty($limited))
                    <div style="text-align:center;margin:8px 0 2px;">
                        <a href="{{ $shipment->maps_link }}" target="_blank" style="font-size:13px;color:#7c3aed;font-weight:700;text-decoration:none;">📍 Ver mi ubicación en el mapa</a>
                    </div>
                @endif

                {{-- Dónde está el pedido, en palabras del cliente (ShipmentTimeline):
                     junta el estado del almacén y los hitos de la agencia. --}}
                @php $tl = $timeline ?? app(\App\Services\Tenant\ShipmentTimeline::class)->for($shipment); @endphp
                <div class="now now--{{ $tl['tone'] }}">
                    <div class="now-t">{{ $tl['title'] }}</div>
                    @if($tl['detail'])<div class="now-d">{{ $tl['detail'] }}</div>@endif
                    @if($tl['eta'])<div class="now-eta">Llega aprox. {{ $tl['eta'] }}</div>@endif
                    @if($tl['delay'])<div class="now-delay">La agencia reporta demora en esta ruta.</div>@endif
                </div>
                @if(!$tl['cancelled'])
                    <ol class="tl">
                        @foreach($tl['steps'] as $st)
                            <li class="tl-s tl-s--{{ $st['state'] }}">
                                <span class="tl-dot" aria-hidden="true"></span>
                                <div class="tl-b">
                                    <div class="tl-l">{{ $st['label'] }}</div>
                                    @if($st['at'])<div class="tl-at">{{ $st['at']->copy()->locale('es')->isoFormat('ddd D [de] MMM, h:mm a') }}</div>@endif
                                    @if($st['hint'])<div class="tl-h">{{ $st['hint'] }}</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if($shipment->carrier_checked_at && empty($limited))
                        <div class="tl-upd">Seguimiento de {{ $shipment->shipping_agency }} actualizado {{ $shipment->carrier_checked_at->diffForHumans() }}</div>
                    @endif
                    @if(!empty($limited))
                        <div style="margin-top:14px;font-size:13px;color:var(--muted);background:#f8fafc;border-radius:12px;padding:10px 12px;">
                            Para ver la guía y el detalle del envío, ingresa el código de seguimiento completo
                            (empieza con <b>ENV-</b>) que te enviamos por WhatsApp.
                        </div>
                    @endif
                    @if($shipment->tracking_number && empty($limited))
                        <div class="guide">
                            <div style="font-size:11px;text-transform:uppercase;color:#16a34a;">N° de guía · {{ $shipment->shipping_agency }}</div>
                            <div class="g">{{ $shipment->tracking_number }}@if($shipment->tracking_code ?? null) <span style="font-size:14px;color:#15803d;">· Código {{ $shipment->tracking_code }}</span>@endif</div>
                            @if($shipment->sent_at)<div style="font-size:12px;color:#15803d;margin-top:2px;">Enviado el {{ \Carbon\Carbon::parse($shipment->sent_at)->format('d/m/Y') }}</div>@endif
                        </div>
                    @endif

                    {{-- Dónde recoge: la oficina de la agencia, con cómo llegar. --}}
                    @if(!empty($tl['office']) && empty($limited))
                        @php $of = $tl['office']; @endphp
                        <div class="office">
                            <div class="office-k">Recoges en · {{ $of['agency'] }}</div>
                            <div class="office-n">{{ $of['name'] }}</div>
                            @if($of['address'])<div class="office-a">{{ $of['address'] }}</div>@endif
                            @if($of['hours'])<div class="office-a">{{ $of['hours'] }}</div>@endif
                            @if($of['maps'])<a class="office-btn" href="{{ $of['maps'] }}" target="_blank" rel="noopener">Cómo llegar</a>@endif
                        </div>
                    @endif

                    @if($shipment->has_guide && empty($limited))
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
