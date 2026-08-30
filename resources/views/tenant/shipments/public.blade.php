<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de envío — {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</title>
    <style>
        :root { --brand:#2563eb; --brand-d:#1d4ed8; --ink:#0f172a; --line:#e5e7eb; --muted:#6b7280; --ok:#16a34a; --bg:#f1f5f9; --moto:#7c3aed; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 18px 14px 48px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:20px; padding:22px; box-shadow:0 10px 40px -16px rgba(15,23,42,.2); }
        .head { text-align:center; margin-bottom:16px; }
        .brand { font-size:13px; font-weight:800; text-transform:uppercase; color:var(--brand); letter-spacing:.4px; }
        .head h1 { font-size:20px; margin:6px 0 2px; }
        .head p { margin:0; color:var(--muted); font-size:13px; }

        /* ── Stepper ── */
        .stepper { display:flex; align-items:center; justify-content:space-between; margin:18px 2px 22px; }
        .st { display:flex; flex-direction:column; align-items:center; gap:6px; flex:0 0 auto; width:70px; text-align:center; }
        .st .dot { width:32px; height:32px; border-radius:999px; background:#e2e8f0; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; transition:.25s; }
        .st .t { font-size:11px; color:#94a3b8; font-weight:600; transition:.25s; }
        .st.active .dot { background:var(--brand); color:#fff; box-shadow:0 0 0 5px rgba(37,99,235,.15); }
        .st.active .t { color:var(--ink); }
        .st.done .dot { background:var(--ok); color:#fff; }
        .st.done .t { color:var(--ink); }
        .st-line { flex:1; height:3px; background:#e2e8f0; border-radius:2px; margin:0 -4px; align-self:flex-start; margin-top:15px; }
        .st-line.done { background:var(--ok); }

        label { display:block; font-size:13px; font-weight:600; margin:14px 0 5px; }
        .step > label:first-of-type { margin-top:0; }
        .req::after { content:" *"; color:#dc2626; }
        input[type=text], input[type=tel], select, textarea {
            width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:12px; font-size:15px; background:#fff; transition:.15s;
        }
        input:focus, select:focus, textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .hint { font-size:12px; color:var(--muted); margin-top:3px; display:block; }
        /* Tipo de documento */
        .doc-types { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; }
        .doc-opt { position:relative; margin:0; }
        .doc-opt input { position:absolute; opacity:0; width:0; height:0; }
        .doc-opt span { display:inline-block; padding:8px 14px; border:1.5px solid var(--line); border-radius:11px;
            font-size:13.5px; font-weight:600; color:#475569; background:#fff; cursor:pointer; transition:.15s; }
        .doc-opt span:hover { border-color:#cbd5e1; }
        .doc-opt input:checked + span { border-color:var(--brand); background:#eff6ff; color:var(--brand-d); }
        .doc-opt input:focus-visible + span { box-shadow:0 0 0 3px rgba(37,99,235,.15); }
        /* Campo que llena el sistema (no se escribe a mano) */
        input.is-auto { background:#f8fafc; color:#0f172a; font-weight:600; cursor:not-allowed; }
        input.is-auto:focus { border-color:var(--line); box-shadow:none; }

        .btn { width:100%; padding:14px; border:none; border-radius:14px; background:var(--brand); color:#fff; font-size:16px; font-weight:700; cursor:pointer; transition:.15s; }
        .btn:hover { background:var(--brand-d); } .btn:active { transform:scale(.99); }
        .btn-ghost { background:#f1f5f9; color:#334155; } .btn-ghost:hover { background:#e2e8f0; }
        .row-btns { display:flex; gap:10px; margin-top:20px; }
        .row-btns .btn { flex:1; }

        /* ── Paso 0: tarjetas de tipo de entrega ── */
        .dtype { display:flex; flex-direction:column; gap:14px; margin-top:4px; }
        .dcard { display:flex; align-items:center; gap:14px; text-align:left; width:100%; border:2px solid var(--line); background:#fff; border-radius:18px; padding:18px; cursor:pointer; transition:.18s; }
        .dcard:hover { border-color:var(--brand); transform:translateY(-2px); box-shadow:0 12px 28px -18px rgba(37,99,235,.5); }
        .dcard .ic { flex:0 0 auto; width:56px; height:56px; border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:28px; }
        .dcard.moto .ic { background:#f3e8ff; } .dcard.ag .ic { background:#dbeafe; }
        .dcard.tienda .ic { background:#dcfce7; }
        .dcard .tx { flex:1; }
        .dcard .tx b { display:block; font-size:16px; }
        .dcard .tx span { display:block; font-size:12.5px; color:var(--muted); margin-top:2px; line-height:1.35; }
        .dcard .go { flex:0 0 auto; font-size:13px; font-weight:800; color:var(--brand); border:1.5px solid var(--brand); border-radius:10px; padding:8px 12px; }
        .dcard.moto .go { color:var(--moto); border-color:var(--moto); }
        .dcard.tienda .go { color:var(--ok); border-color:var(--ok); }
        .dcard.tienda:hover { border-color:var(--ok); box-shadow:0 12px 28px -18px rgba(22,163,74,.5); }

        .tag { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; margin-bottom:6px; }
        .tag.moto { background:#f3e8ff; color:var(--moto); } .tag.ag { background:#dbeafe; color:var(--brand-d); }
        .tag.tienda { background:#dcfce7; color:#15803d; }

        /* ── Recojo en tienda ── */
        .pickup-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:14px 15px; margin-bottom:14px; }
        .pickup-box__t { font-size:14.5px; font-weight:800; color:#15803d; }
        .pickup-box__addr { font-size:13.5px; color:var(--ink); margin-top:5px; font-weight:600; }
        .pickup-box__s { font-size:12.5px; color:#166534; margin-top:6px; line-height:1.4; }

        /* ── Cliente encontrado ── */
        .found { background:#eff6ff; border:1px solid #bfdbfe; border-radius:14px; padding:12px 14px; margin-top:10px; }
        .found .t { font-size:12px; color:var(--brand); font-weight:700; text-transform:uppercase; }
        .found .n { font-size:16px; font-weight:700; margin:2px 0 8px; }
        .found .acts { display:flex; gap:8px; }
        .found .acts button { flex:1; padding:9px; border-radius:10px; border:none; font-weight:700; font-size:13px; cursor:pointer; }
        .found .use { background:var(--brand); color:#fff; } .found .new { background:#e2e8f0; color:#334155; }

        /* ── Google Maps ── */
        .map-search { position:relative; }
        #shipMap { width:100%; height:260px; border-radius:14px; border:1.5px solid var(--line); margin-top:10px; background:#e5e7eb; }
        .map-picked { margin-top:8px; padding:9px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:11px; font-size:13px; display:none; }
        .map-picked.show { display:block; }
        .map-picked .c { color:#15803d; font-weight:700; }
        .map-note { font-size:12px; color:var(--muted); margin-top:6px; }
        .map-off { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:12px 14px; font-size:13px; color:#92400e; margin-top:8px; }

        /* ── Confirmación ── */
        .conf { border:1.5px solid var(--line); border-radius:16px; overflow:hidden; }
        .conf .h { background:#f8fafc; padding:10px 14px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--brand); border-bottom:1px solid var(--line); }
        .conf .rows { padding:6px 14px; }
        .conf .r { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed #eef2f7; font-size:14px; }
        .conf .r:last-child { border-bottom:none; }
        .conf .r .k { color:var(--muted); flex:0 0 40%; }
        .conf .r .v { text-align:right; font-weight:600; word-break:break-word; }
        .conf .r[hidden] { display:none; }
        /* ── Aviso de tiempos de despacho (destacado, por modalidad) ── */
        .eta { display:flex; gap:10px; border-radius:14px; padding:13px 15px; margin-bottom:14px;
               border:1px solid; align-items:flex-start; }
        .eta__t { font-weight:800; font-size:14px; white-space:nowrap; }
        .eta__b { font-size:13px; line-height:1.5; }
        .eta__s { margin-top:5px; opacity:.85; }
        .eta--prov   { background:#fffbeb; border-color:#fde68a; color:#92400e; }
        .eta--lima   { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
        .eta--tienda { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
        @media (max-width: 460px) { .eta { flex-direction:column; gap:4px; } }

        /* ── Sorteo dentro del formulario de envío ── */
        .rfz { border:1px solid #ddd2fb; background:linear-gradient(135deg,#faf7ff,#fff);
               border-radius:16px; padding:14px; margin-top:14px; }
        .rfz__head { display:flex; gap:11px; align-items:center; }
        .rfz__img { width:62px; height:62px; object-fit:cover; border-radius:12px;
                    border:1px solid #ddd2fb; flex-shrink:0; }
        .rfz__tx { flex:1; min-width:0; }
        .rfz__tag { font-size:10.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#7c3aed; }
        .rfz__name { font-size:15px; font-weight:800; line-height:1.25; margin-top:1px; }
        .rfz__prize { font-size:12.5px; color:var(--muted); margin-top:2px; }
        .rfz__desc { font-size:12.5px; color:#475569; line-height:1.45; margin-top:9px; }
        .rfz__dates { font-size:12px; color:#5b21b6; background:#f5f1ff; border-radius:9px;
                      padding:6px 9px; margin-top:9px; }
        .rfz__terms { margin-top:9px; font-size:12px; }
        .rfz__terms summary { cursor:pointer; color:#7c3aed; font-weight:700; }
        .rfz__terms div { margin-top:6px; max-height:170px; overflow-y:auto; background:#fff;
                          border:1px solid var(--line); border-radius:10px; padding:9px;
                          white-space:pre-line; color:#475569; line-height:1.5; }
        .rfz__chk { margin-top:11px; background:#fff; border:1px solid #ddd2fb; border-radius:12px; padding:10px; }
        .rfz__cond { font-size:11.5px; color:#7c3aed; margin-top:6px; text-align:center; }

        .terms-box { margin-top:16px; }
        .chk { display:flex; align-items:flex-start; gap:10px; background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:12px; font-size:14px; font-weight:600; }
        .chk input { margin-top:3px; width:18px; height:18px; }
        /* Casilla ligera dentro del formulario (no es el bloque de términos). */
        .chk-inline { background:#f8fafc; border-color:var(--line); font-weight:600; font-size:13.5px; align-items:center; margin-top:14px; cursor:pointer; }
        .chk-inline input { margin-top:0; }
        /* ── Resumen de costos del envio ───────────────────────────────
           Una sola tarjeta con el recorrido del paquete. Reemplaza dos
           recuadros de colores que competian entre si y repetian datos. */
        .cost-box { margin:14px 0 4px; border:1px solid var(--line); border-radius:14px;
            background:#fff; overflow:hidden; }
        .cost-box__t { padding:10px 14px; background:#f8fafc; border-bottom:1px solid var(--line);
            font-size:12.5px; font-weight:700; color:#0f172a; letter-spacing:.01em; }
        .cost-row { display:flex; align-items:flex-start; gap:12px; padding:11px 14px;
            border-bottom:1px solid #f1f5f9; }
        .cost-row:last-child { border-bottom:0; }
        .cost-row__l { flex:1; min-width:0; font-size:13px; color:#1e293b; line-height:1.4; }
        .cost-row__l small { display:block; margin-top:2px; font-size:11.5px; color:#64748b;
            line-height:1.45; font-weight:400; }
        /* El monto no envuelve: es el dato que se busca de un vistazo. */
        .cost-row__v { flex:0 0 auto; font-size:13.5px; font-weight:700; color:#0f172a;
            white-space:nowrap; padding-top:1px; }
        .cost-row__v.is-free { color:#15803d; }
        .cost-row__v.is-soft { font-size:11.5px; font-weight:600; color:#64748b; }
        .cost-row__v.is-warn { font-size:11.5px; font-weight:700; color:#b45309; }
        /* El tramo a domicilio se destaca: es el unico que el cliente elige,
           y el unico que puede evitar. */
        .cost-row--extra { background:#fffbeb; }
        .cost-row--extra .cost-row__l { color:#78350f; }
        .cost-row--extra .cost-row__l small { color:#92400e; }

        /* Recordatorio en el paso de confirmacion. */
        .conf-warn { display:flex; gap:9px; align-items:flex-start; margin-top:10px;
            padding:10px 12px; border-radius:10px; background:#fffbeb;
            border:1px solid #fde68a; color:#78350f; font-size:12.5px; line-height:1.45; }
        .cond { font-size:12px; color:var(--muted); margin-top:12px; line-height:1.5; background:#f8fafc; border:1px solid var(--line); border-radius:12px; padding:12px 14px; }
        .cond strong { color:#334155; }
        .cond ul { margin:6px 0 0; padding-left:18px; }

        /* ── Éxito ── */
        .ok-wrap { text-align:center; padding:6px 0; }
        .ok-check { width:78px; height:78px; border-radius:999px; background:var(--ok); color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; margin:6px auto 10px; box-shadow:0 10px 30px -8px rgba(22,163,74,.5); animation:pop .35s ease; }
        @keyframes pop { from{ transform:scale(.6); opacity:0;} to{ transform:scale(1); opacity:1;} }
        .ok-wrap h2 { font-size:20px; margin:4px 0; }
        .ok-code { font-size:24px; font-weight:800; letter-spacing:1px; color:var(--brand); background:#eff6ff; border:1px dashed var(--brand); border-radius:14px; padding:14px; margin:12px 0; }
        .ok-btns { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
        .ok-btns a { text-decoration:none; display:block; }
        .btn-wa { background:#22c55e; } .btn-wa:hover { background:#16a34a; }

        .foot { text-align:center; color:var(--muted); font-size:11.5px; margin-top:18px; }
        .step[hidden], [hidden] { display:none; }
        .fade-in { animation:fade .25s ease; }
        @keyframes fade { from{ opacity:0; transform:translateY(6px);} to{ opacity:1; transform:none;} }
        .alert-err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:11px 13px; border-radius:12px; font-size:13.5px; margin-bottom:14px; }
        /* Banner del pedido: solo aparece cuando el formulario se abre desde
           el enlace de una compra concreta. */
        .order-ctx { background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; padding:11px 13px; border-radius:12px; margin-bottom:14px; }
        .order-ctx b { display:block; font-size:15px; }
        .order-ctx span { font-size:13px; opacity:.85; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            @if(!empty($company->logo))
                <img src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="{{ $company->trade_name ?? $company->name ?? '' }}" style="max-height:56px;max-width:70%;margin:0 auto 8px;display:block;object-fit:contain;">
            @else
                <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            @endif
            <h1>📦 Registro de envío</h1>
            <p>Elige cómo deseas recibir tu pedido.</p>
        </div>

        {{-- ── Barra de progreso ── --}}
        <div class="stepper" id="stepper">
            <div class="st {{ $sent ? 'done' : 'active' }}" data-n="1"><div class="dot">1</div><div class="t">Tipo</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="2"><div class="dot">2</div><div class="t">Datos</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'done' : '' }}" data-n="3"><div class="dot">3</div><div class="t">Confirmar</div></div>
            <div class="st-line {{ $sent ? 'done' : '' }}"></div>
            <div class="st {{ $sent ? 'active' : '' }}" data-n="4"><div class="dot">4</div><div class="t">Listo</div></div>
        </div>

        @if($sent)
            {{-- ══════════ PASO 4: Éxito ══════════ --}}
            @php
                $isMoto   = ($sentType === \App\Models\Tenant\ShippingRequest::DELIVERY_DOMICILIO);
                $isPickup = ($sentType === \App\Models\Tenant\ShippingRequest::DELIVERY_TIENDA);
            @endphp
            <div class="ok-wrap fade-in">
                <div class="ok-check">✓</div>
                <h2>Registro realizado correctamente</h2>
                <p style="color:var(--muted);font-size:14px;margin:2px 0;">
                    @if($isPickup)
                        Hemos recibido tus datos. Prepararemos tu pedido y te avisaremos por WhatsApp <b>apenas esté listo para recoger</b>.
                    @elseif($isMoto)
                        Hemos recibido tus datos. Tu pedido será entregado por nuestro <b>motorizado</b> hasta tu dirección.
                    @else
                        Hemos recibido tus datos. Tu pedido será enviado mediante <b>agencia de transporte</b>. Cuando sea despachado recibirás la guía de envío.
                    @endif
                </p>
                <div class="ok-code">{{ $sent }}</div>

                {{-- Confirmación de la solicitud de participación. La inscripción
                     se hace efectiva cuando la tienda valida el pago. --}}
                @if(session('joined_raffle'))
                    <div class="rfz" style="text-align:left;margin-top:14px;">
                        <div class="rfz__tag">🎁 Solicitud registrada</div>
                        <div class="rfz__name">{{ session('joined_raffle') }}</div>
                        <div class="rfz__desc">
                            Anotamos que quieres participar con este pedido. Tu inscripción
                            <strong>queda confirmada cuando validemos el pago</strong>. Si sales sorteado,
                            te contactaremos al celular que registraste.
                        </div>
                    </div>
                @endif
                <div class="ok-btns">
                    <a href="{{ route('shipments.public.tracking', ['code' => $sent]) }}"><button type="button" class="btn">🔎 Ver seguimiento</button></a>
                    @php
                        // OJO: este armado es un duplicado del de
                        // ShipmentController::waSummary(). Si tocas uno, toca el otro
                        // (o unificalos): ya se desincronizaron una vez con el precio.
                        $tiendaNombre = $company->title_web ?: ($company->trade_name ?: ($company->name ?: 'la tienda'));
                        $s = $sentShipment ?? null;
                        $L = [];
                        $L[] = "📦 *PEDIDO REGISTRADO* — {$tiendaNombre}";
                        $L[] = "Código: *{$sent}*";
                        if ($s) {
                            $L[] = "";
                            $L[] = "👤 Cliente: {$s->full_name}";
                            if ($s->dni)   $L[] = "🪪 {$s->document_label}: {$s->dni}";
                            if ($s->phone) $L[] = "📱 Celular: {$s->phone}";
                            $L[] = "";
                            if ($s->is_pickup) {
                                $L[] = "🏬 *Recojo en tienda*";
                                if ($storeAddress) $L[] = "📍 Dirección de la tienda: {$storeAddress}";
                                if ($s->reference) $L[] = "🕒 Piensa pasar: {$s->reference}";
                            } elseif ($s->is_domicilio) {
                                $L[] = "🏍️ *Entrega a domicilio*";
                                if ($s->formatted_address || $s->shipping_destination) $L[] = "📍 Dirección: " . ($s->formatted_address ?: $s->shipping_destination);
                                if ($s->reference)      $L[] = "📌 Referencia: {$s->reference}";
                                if ($s->maps_link)      $L[] = "🗺️ Ubicación: {$s->maps_link}";
                                if ($p = $s->priceLabel('GRATIS')) $L[] = "💵 Costo aprox. de envío: {$p}";
                            } else {
                                $L[] = "📦 *Envío por agencia*";
                                if ($s->destination_city)     $L[] = "🏙️ Destino: {$s->destination_city}";
                                if ($s->shipping_agency)      $L[] = "🏢 Agencia: {$s->shipping_agency}";
                                if ($s->shipping_destination) $L[] = "📍 Dirección: {$s->shipping_destination}";
                                if ($s->reference)            $L[] = "📌 Referencia: {$s->reference}";
                                if ($p = $s->priceLabel('🎁 GRATIS')) $L[] = "💵 Servicio tienda→agencia: {$p}";
                            }
                            if ($s->package_content) $L[] = "📦 Contenido: {$s->package_content}";
                            if ($s->notes)           $L[] = "📝 Nota: {$s->notes}";
                        }
                        $L[] = "";
                        $L[] = "🔎 Seguimiento: " . route('shipments.public.tracking', ['code' => $sent]);
                        $waText = rawurlencode(implode("\n", $L));
                        $waHref = !empty($ordersWa) ? "https://wa.me/{$ordersWa}?text={$waText}" : "https://wa.me/?text={$waText}";
                    @endphp
                    <a href="{{ $waHref }}" target="_blank"><button type="button" class="btn btn-wa">💬 Enviar mi pedido por WhatsApp</button></a>
                    @if(empty($order))
                        <a href="{{ route('shipments.public.form') }}"><button type="button" class="btn btn-ghost">Registrar otro envío</button></a>
                    @endif
                </div>
            </div>
        @else
            @if($errors->any())
                <div class="alert-err"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            {{-- Cuando el formulario se abre desde el enlace de un pedido, el
                 cliente tiene que ver DE QUE compra estamos hablando: sin esto
                 el enlace parece un registro suelto mas y no lo asocia. --}}
            @if(!empty($order))
                <div class="order-ctx">
                    <b>Pedido #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</b>
                    <span>Completa aquí los datos de entrega de tu compra.</span>
                </div>
            @endif

            <form method="POST" action="{{ $formAction }}" id="shipForm">
                @csrf
                <input type="hidden" name="delivery_type" id="delivery_type" value="{{ old('delivery_type') }}">

                {{-- ══════════ PASO 0: Tipo de entrega ══════════ --}}
                <div class="step fade-in" data-step="0">
                    <div class="dtype">
                        <button type="button" class="dcard moto" data-type="domicilio">
                            <div class="ic">🏍️</div>
                            <div class="tx"><b>Entrega a domicilio &mdash; LIMA</b><span>Un motorizado lleva tu pedido hasta tu dirección. Ubicación por mapa.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard ag" data-type="agencia">
                            <div class="ic">📦</div>
                            <div class="tx"><b>Envío por agencia &mdash; PROVINCIA</b><span>Enviamos tu pedido por agencia de transporte a tu provincia.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                        <button type="button" class="dcard tienda" data-type="tienda">
                            <div class="ic">🏬</div>
                            <div class="tx"><b>Recojo en tienda</b><span>Preparamos tu pedido y te avisamos cuando esté listo para recogerlo.</span></div>
                            <div class="go">Elegir</div>
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 1: Datos ══════════ --}}
                <div class="step" data-step="1" hidden>
                    <span class="tag moto" id="tag-moto" hidden>🏍️ Entrega a domicilio · LIMA</span>
                    <span class="tag ag" id="tag-ag" hidden>📦 Envío por agencia · PROVINCIA</span>
                    <span class="tag tienda" id="tag-tienda" hidden>🏬 Recojo en tienda</span>

                    <label>Documento</label>
                    <div class="doc-types">
                        @foreach(\App\Models\Tenant\ShippingRequest::DOC_TYPES as $dv => $dl)
                            <label class="doc-opt">
                                <input type="radio" name="document_type" value="{{ $dv }}" {{ old('document_type', 'dni') === $dv ? 'checked' : '' }}>
                                <span>{{ $dl }}</span>
                            </label>
                        @endforeach
                    </div>
                    <input type="text" name="dni" id="pub_dni" value="{{ old('dni') }}"
                           maxlength="11" inputmode="numeric" autocomplete="off" placeholder="8 dígitos">
                    <small class="js-doc-status hint"></small>
                    <div id="clientFound" class="found" hidden>
                        <div class="t">Cliente encontrado</div>
                        <div class="n" id="cf_name">—</div>
                        <div style="font-size:13px;color:var(--muted);margin-bottom:8px;">¿Deseas utilizar esta información?</div>
                        <div class="acts">
                            <button type="button" class="use" id="cf_use">Usar datos</button>
                            <button type="button" class="new" id="cf_new">Ingresar nuevos</button>
                        </div>
                    </div>

                    <label class="req">Nombre completo</label>
                    <input type="text" name="full_name" id="pub_full_name" value="{{ old('full_name') }}" required maxlength="160">
                    <small class="hint" id="pub_name_hint">🔒 Se completa automáticamente al ingresar tu documento.</small>

                    <label class="req">Celular (WhatsApp)</label>
                    <input type="tel" name="phone" id="pub_phone" value="{{ old('phone') }}" required maxlength="9" inputmode="numeric" placeholder="999 999 999" class="js-phone-pe">
                    <small class="js-phone-err" style="color:#dc2626;display:block;font-size:12px;margin-top:2px;"></small>

                    {{-- Cliente EMPRESA (RUC): la agencia no le entrega el paquete a
                         una razón social, pide el DNI y el nombre de una persona.
                         Este bloque aparece solo cuando el documento tiene 11
                         dígitos, que es como se distingue el RUC en este formulario. --}}
                    <div id="pub_pickup_box" hidden
                         style="margin-top:14px;padding:12px 14px;border:1px solid #bfdbfe;background:#f8fbff;border-radius:12px;">
                        <div style="font-weight:700;font-size:14px;color:#1e3a8a;margin-bottom:2px;">
                            🧾 ¿Quién recoge el paquete?
                        </div>
                        <div style="font-size:12.5px;color:#475569;margin-bottom:10px;">
                            Registraste un <b>RUC</b>. La agencia de transporte entrega solo a una
                            <b>persona con DNI</b>, así que necesitamos sus datos.
                        </div>

                        <label class="req">Nombre de quien recoge</label>
                        <input type="text" name="pickup_person_name" id="pub_pickup_name"
                               value="{{ old('pickup_person_name') }}" maxlength="160"
                               placeholder="Nombre y apellidos de la persona">

                        <label class="req">DNI de quien recoge</label>
                        <input type="text" name="pickup_person_dni" id="pub_pickup_dni"
                               value="{{ old('pickup_person_dni') }}" maxlength="20"
                               inputmode="numeric" placeholder="8 dígitos">

                        <label>Celular de quien recoge <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                        <input type="tel" name="pickup_person_phone" id="pub_pickup_phone"
                               value="{{ old('pickup_person_phone') }}" maxlength="9"
                               inputmode="numeric" placeholder="999 999 999">

                        <small class="hint" id="pub_pickup_err" hidden style="color:#dc2626;"></small>
                    </div>

                    {{-- ─────── Rama DOMICILIO (Google Maps) ─────── --}}
                    <div class="branch-domicilio" hidden>
                        {{-- Un solo campo de dirección: es el buscador de Google Y el
                             dato que se guarda. El cliente puede corregirlo a mano
                             (número de puerta, dpto) sin perder el pin del mapa. --}}
                        <label class="req">Dirección de entrega</label>
                        <div class="map-search">
                            <input type="text" name="shipping_destination" id="pub_addr_domicilio" value="{{ old('shipping_destination') }}"
                                   maxlength="500" autocomplete="off" placeholder="Ej. Av. Arequipa 1234, Miraflores">
                        </div>
                        <small class="hint">Escribe y elige tu dirección de la lista; luego ajusta el marcador si hace falta.</small>
                        @if(!empty($mapsKey))
                            <div id="shipMap"></div>
                            <div class="map-note">Arrastra el marcador para ajustar la ubicación exacta.</div>
                            <div class="map-picked" id="mapPicked">
                                <div class="c" id="mp_city">—</div>
                            </div>
                        @else
                            <div class="map-off">⚠️ El mapa no está disponible por ahora. Escribe tu dirección completa y una referencia clara.</div>
                        @endif

                        {{-- Campos ocultos que llena Google Maps --}}
                        <input type="hidden" name="latitude" id="pub_lat" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="pub_lng" value="{{ old('longitude') }}">
                        <input type="hidden" name="google_place_id" id="pub_place_id" value="{{ old('google_place_id') }}">
                        <input type="hidden" name="formatted_address" id="pub_formatted" value="{{ old('formatted_address') }}">
                        <input type="hidden" name="google_maps_url" id="pub_maps_url" value="{{ old('google_maps_url') }}">
                        <input type="hidden" name="destination_city" id="pub_city_domicilio" value="{{ old('destination_city') }}">
                        <input type="hidden" name="distance_km" id="pub_dist_km" value="{{ old('distance_km') }}">
                        <input type="hidden" name="distance_text" id="pub_dist_text" value="{{ old('distance_text') }}">
                        <input type="hidden" name="duration_text" id="pub_dur_text" value="{{ old('duration_text') }}">
                        <input type="hidden" name="delivery_price" id="pub_delivery_price" value="{{ old('delivery_price') }}">
                        <div id="priceBox" style="display:none;margin-top:10px;padding:12px 14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;font-size:14px;color:#065f46;">
                            💵 Costo <b>aproximado</b> del servicio de envío: <b style="font-size:16px;">S/ <span id="priceText">—</span></b>
                            <div style="font-size:11.5px;color:#059669;margin-top:2px;">Es un <b>precio referencial</b> según la distancia. El costo final puede variar y se confirma al coordinar la entrega.</div>
                        </div>

                        <label>Referencia e indicaciones</label>
                        <input type="text" name="reference" id="pub_reference_dom" value="{{ old('reference') }}" maxlength="255" placeholder="Dpto 302, portón negro, frente al parque…">
                    </div>

                    {{-- ─────── Rama AGENCIA (ubigeo) ─────── --}}
                    <div class="branch-agencia" hidden>
                        <label class="req">Destino (Departamento / Provincia / Distrito)</label>
                        <div class="ubigeo-field" data-ubigeo-group="pub">
                            <div class="ubigeo-display" tabindex="0">Seleccionar departamento / provincia / distrito…</div>
                            <input type="hidden" name="department_id" data-ub="department">
                            <input type="hidden" name="province_id"   data-ub="province">
                            <input type="hidden" name="district_id"   data-ub="district">
                            <div class="ubigeo-pop" hidden>
                                <div class="ubigeo-col" data-col="dep"></div>
                                <div class="ubigeo-col" data-col="prov"></div>
                                <div class="ubigeo-col" data-col="dist"></div>
                            </div>
                        </div>

                        <label>Agencia de transporte <span style="color:#dc2626;">*</span></label>
                        <div class="agency-field">
                            <select class="agency-select">
                                <option value="">— Selecciona —</option>
                                @foreach(\App\Models\Tenant\ShippingRequest::AGENCIES as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
                                <option value="__otra__">Otra…</option>
                            </select>
                            <input type="text" class="agency-input" name="shipping_agency" id="pub_shipping_agency" value="{{ old('shipping_agency') }}" maxlength="120" placeholder="Nombre de la agencia" style="display:none;margin-top:8px;">
                        </div>
                        <small class="hint" id="pub_agency_err" hidden style="color:#dc2626;">
                            Elige la agencia de transporte: es obligatoria para los envíos a provincia.
                        </small>

                        {{-- Las agencias tienen varias oficinas por ciudad (Shalom
                             tiene nombre por local): saber en cuál recoge el cliente
                             es más útil que una referencia genérica. Reutiliza la
                             columna `reference`, que en agencia significa "oficina". --}}
                        <label id="pub_office_label">Oficina donde recogerás <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                        <input type="text" name="reference" id="pub_reference_ag" value="{{ old('reference') }}" maxlength="255" placeholder="Ej. Terminal Terrestre, Av. Aviación 123…">
                        <small class="hint" id="pub_office_hint">Si ya sabes en qué local vas a recoger, escríbelo. Si no, déjalo en blanco: la agencia te lo indicará.</small>
                        <small class="hint" id="pub_dest_err" hidden style="color:#dc2626;"></small>

                        {{-- El paquete normalmente solo viaja hasta la agencia: el
                             cliente lo recoge ahí y su dirección no la usa nadie.
                             Solo pedimos dirección si la agencia hace reparto. --}}
                        <label class="chk chk-inline">
                            <input type="checkbox" id="pub_ag_home" {{ old('delivery_type') === 'agencia' && old('shipping_destination') ? 'checked' : '' }}>
                            <span>La agencia lleva el paquete hasta mi domicilio</span>
                        </label>
                        <div id="agHomeWrap" hidden>
                            <label>Dirección de reparto</label>
                            <input type="text" name="shipping_destination" id="pub_addr_agencia" value="{{ old('shipping_destination') }}" maxlength="255" placeholder="Av./Jr./Calle y número">
                        </div>

                        {{-- ── Resumen de costos ────────────────────────────────
                             Antes eran DOS recuadros sueltos: uno advertia del
                             recargo a domicilio y otro informaba la tarifa
                             tienda→agencia. Decian cosas que se pisaban ("el
                             flete se paga aparte" en los dos) y el cliente tenia
                             que armar el cuadro solo.

                             Ahora es UNA tabla con el recorrido del paquete en
                             orden, y en cada tramo QUIEN cobra. Esa es la
                             confusion real: el cliente no distingue lo que nos
                             paga a nosotros de lo que paga en la agencia. --}}
                        @php
                            $tramoTienda = !empty($agencyFree)
                                ? 'GRATIS'
                                : ((!empty($agencyShow) && !empty($agencyFee) && $agencyFee > 0)
                                    ? 'S/ ' . number_format($agencyFee, 2)
                                    : null);
                        @endphp
                        <div class="cost-box">
                            <div class="cost-box__t">💰 Cómo se cobra tu envío</div>

                            @if($tramoTienda)
                                <div class="cost-row">
                                    <div class="cost-row__l">
                                        <b>1. De nuestra tienda a la agencia</b>
                                        <small>Lo cobramos nosotros, al registrar el envío.</small>
                                    </div>
                                    <div class="cost-row__v {{ !empty($agencyFree) ? 'is-free' : '' }}">{{ $tramoTienda }}</div>
                                </div>
                            @endif

                            <div class="cost-row">
                                <div class="cost-row__l">
                                    <b>{{ $tramoTienda ? '2.' : '1.' }} De la agencia a tu ciudad</b>
                                    <small>Lo cobra la agencia. Depende del destino y del peso.</small>
                                </div>
                                <div class="cost-row__v is-soft">Lo pagas allá</div>
                            </div>

                            {{-- Solo aparece si pidio reparto: si no, es ruido. --}}
                            <div class="cost-row cost-row--extra" id="costHome" hidden>
                                <div class="cost-row__l">
                                    <b>{{ $tramoTienda ? '3.' : '2.' }} De la agencia hasta tu puerta</b>
                                    <small>Cobro <b>adicional</b> de la agencia, bastante mayor que
                                    recoger en su oficina. Si prefieres pagar menos, desmarca la opción
                                    de reparto a domicilio.</small>
                                </div>
                                <div class="cost-row__v is-warn">Costo extra</div>
                            </div>
                        </div>
                    </div>

                    {{-- ─────── Rama RECOJO EN TIENDA ───────
                         No viaja: sin dirección, sin agencia, sin ubigeo y sin
                         cobro de envío. Solo hace falta saber a quién se le
                         entrega y, si acaso, cuándo piensa pasar. --}}
                    <div class="branch-tienda" hidden>
                        <div class="pickup-box">
                            <div class="pickup-box__t">🏬 Recogerás tu pedido en la tienda</div>
                            @if(!empty($storeAddress))
                                <div class="pickup-box__addr">{{ $storeAddress }}</div>
                            @endif
                            <div class="pickup-box__s">
                                Te avisamos por WhatsApp apenas esté listo. Acércate con tu documento de identidad.
                            </div>
                        </div>

                        <label>¿Cuándo piensas pasar? (opcional)</label>
                        <input type="text" name="reference" id="pub_reference_tienda" value="{{ old('reference') }}"
                               maxlength="255" placeholder="Ej. mañana por la tarde, el sábado…">
                        <small class="hint">Nos ayuda a tenerlo listo a tiempo. No es un compromiso.</small>
                    </div>

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep0">← Volver</button>
                        <button type="button" class="btn" id="toStep2">Continuar →</button>
                    </div>
                </div>

                {{-- ══════════ PASO 2: Confirmación ══════════ --}}
                <div class="step" data-step="2" hidden>
                    <div class="conf">
                        <div class="h" id="c_type_h">Resumen</div>
                        <div class="rows">
                            <div class="r"><span class="k">Tipo de entrega</span><span class="v" id="c_type">—</span></div>
                            <div class="r"><span class="k">Nombre</span><span class="v" id="c_name">—</span></div>
                            <div class="r"><span class="k">Documento</span><span class="v" id="c_doc">—</span></div>
                            <div class="r"><span class="k">Celular</span><span class="v" id="c_phone">—</span></div>
                            <div class="r" id="r_pickup"><span class="k">Recoge</span><span class="v" id="c_pickup">—</span></div>
                            <div class="r" id="r_ubigeo"><span class="k">Ubigeo</span><span class="v" id="c_ubigeo">—</span></div>
                            <div class="r"><span class="k">Dirección</span><span class="v" id="c_dir">—</span></div>
                            <div class="r"><span class="k" id="k_ref">Referencia</span><span class="v" id="c_ref">—</span></div>
                            <div class="r" id="r_ag"><span class="k">Agencia</span><span class="v" id="c_ag">—</span></div>
                            <div class="r" id="r_coords"><span class="k">Ubicación GPS</span><span class="v" id="c_coords">—</span></div>
                            <div class="r" id="r_price"><span class="k">Costo aprox. de envío</span><span class="v" id="c_price" style="color:#059669;">—</span></div>
                        </div>
                        {{-- Se repite aca a proposito: el paso 1 se llena rapido
                             y este es el momento en que el cliente confirma. --}}
                        <div class="conf-warn" id="r_home_extra" hidden>
                            <span>🏠</span>
                            <div>Pediste <b>reparto a domicilio</b>: la agencia te cobrará un
                                <b>adicional</b> al entregarte, además del flete a tu ciudad.</div>
                        </div>
                    </div>

                    {{-- ── Tiempos de despacho: recuadro visible, uno por
                         modalidad. Antes iba en letra chica dentro de las
                         condiciones y la gente no lo leía. El JS muestra el
                         que corresponde a la modalidad elegida. --}}
                    <div class="eta eta--prov" id="eta-agencia" hidden>
                        <div class="eta__t">⚠️ Importante</div>
                        <div class="eta__b">
                            Los pedidos con destino a <strong>provincia</strong> se preparan y despachan
                            <strong>entre 2 y {{ max(2, (int) ($maxDays ?? 4)) }} días hábiles</strong>, según la
                            disponibilidad de materiales de embalaje y el proceso logístico.
                            <div class="eta__s">Agradecemos su comprensión.</div>
                        </div>
                    </div>

                    <div class="eta eta--lima" id="eta-domicilio" hidden>
                        <div class="eta__t">🚚 Entregas en Lima</div>
                        <div class="eta__b">
                            Los pedidos para Lima tienen <strong>prioridad logística</strong> y normalmente se
                            preparan para despacho <strong>el mismo día o al siguiente día hábil</strong>.
                        </div>
                    </div>

                    <div class="eta eta--tienda" id="eta-tienda" hidden>
                        <div class="eta__t">🏬 Recojo en tienda</div>
                        <div class="eta__b">
                            Preparamos tu pedido cuanto antes y te avisamos por WhatsApp
                            <strong>apenas esté listo para recoger</strong>.
                        </div>
                    </div>

                    <div class="terms-box">
                        <label class="chk">
                            <input type="checkbox" name="accepted_terms" id="pub_terms" value="1" required>
                            <span>Confirmo que todos los datos ingresados son correctos.</span>
                        </label>
                        <div class="cond">
                            Autorizo el uso de mis datos únicamente para gestionar el envío de mi pedido.<br><br>
                            El tiempo de despacho puede variar dependiendo de:
                            <ul>
                                <li>Disponibilidad del producto.</li>
                                <li>Disponibilidad de materiales para embalaje.</li>
                                <li>Horarios de despacho.</li>
                                <li>Agencia de transporte o disponibilidad del motorizado.</li>
                                <li>Eventos externos que puedan retrasar la entrega.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── Participación en el sorteo, dentro del mismo flujo ──
                         Solo aparece si hay una campaña vigente. Al marcar la
                         casilla el cliente queda inscrito al confirmar, sin
                         necesidad de recibir ningún enlace. --}}
                    @if(!empty($raffle))
                        <div class="rfz">
                            <div class="rfz__head">
                                @if($raffle->prizeImageUrl('small'))
                                    <img class="rfz__img" src="{{ $raffle->prizeImageUrl('small') }}" alt="{{ $raffle->prize_name }}">
                                @endif
                                <div class="rfz__tx">
                                    <div class="rfz__tag">🎁 Sorteo vigente</div>
                                    <div class="rfz__name">{{ $raffle->name }}</div>
                                    @if($raffle->prize_name)
                                        <div class="rfz__prize">Premio: <strong>{{ $raffle->prize_name }}</strong></div>
                                    @endif
                                </div>
                            </div>

                            @if($raffle->description)
                                <div class="rfz__desc">{{ \Illuminate\Support\Str::limit($raffle->description, 180) }}</div>
                            @endif

                            <div class="rfz__dates">
                                @if($raffle->registration_closes_at)
                                    Participa hasta el <strong>{{ $raffle->registration_closes_at->format('d/m/Y') }}</strong>
                                @endif
                                @if($raffle->draw_at)
                                    · Sorteo el <strong>{{ $raffle->draw_at->format('d/m/Y') }}</strong>
                                @endif
                            </div>

                            @if($raffle->terms)
                                <details class="rfz__terms">
                                    <summary>Ver bases y condiciones</summary>
                                    <div>{{ $raffle->terms }}</div>
                                </details>
                            @endif

                            <label class="chk rfz__chk">
                                <input type="checkbox" name="join_raffle" id="pub_join_raffle" value="1">
                                <span>
                                    Deseo participar en el sorteo y autorizo el uso de los datos de este pedido
                                    <strong>exclusivamente para esta campaña</strong>, de acuerdo con las bases y condiciones.
                                </span>
                            </label>
                            <div class="rfz__cond">
                                Tu participación queda confirmada cuando validemos el pago de este pedido.
                            </div>
                        </div>
                    @endif

                    <div class="row-btns">
                        <button type="button" class="btn btn-ghost" id="backStep1">← Volver</button>
                        <button type="submit" class="btn" id="confirmBtn">Confirmar registro</button>
                    </div>
                </div>
            </form>

            <div style="text-align:center;margin-top:18px;padding-top:14px;border-top:1px solid var(--line);">
                <a href="{{ route('shipments.public.tracking') }}" style="color:var(--brand);font-size:14px;text-decoration:none;font-weight:600;">🔎 ¿Ya te registraste? Consulta el estado de tu envío</a>
            </div>
        @endif

        <div class="foot">Powered by ebaemy · Registro y Control de Envíos</div>
    </div>

    {{-- Venta cruzada: los productos de la tienda en el marketplace. Va FUERA
         de la tarjeta para que se vea igual mientras se llena el formulario y
         en la pantalla de éxito. --}}
    @include('tenant.shipments.partials.marketplace-reel')
</div>

@include('tenant.shipments.partials.ubigeo-cascader-js')
@include('tenant.shipments.partials.agency-select-js')
@include('tenant.shipments.partials.phone-validate-js')

<script>
(function () {
    var DTYPE = { DOM: 'domicilio', AG: 'agencia', TIENDA: 'tienda' };
    var form = document.getElementById('shipForm');
    if (!form) return;
    var dtInput = document.getElementById('delivery_type');
    var stepper = document.getElementById('stepper');
    var step0 = document.querySelector('.step[data-step="0"]');
    var step1 = document.querySelector('.step[data-step="1"]');
    var step2 = document.querySelector('.step[data-step="2"]');
    var branchDom = document.querySelector('.branch-domicilio');
    var branchAg = document.querySelector('.branch-agencia');
    var branchTienda = document.querySelector('.branch-tienda');
    var selectedType = null;

    function setStep(n) {
        var sts = stepper.querySelectorAll('.st'), lines = stepper.querySelectorAll('.st-line');
        sts.forEach(function (s) {
            var k = parseInt(s.getAttribute('data-n'), 10);
            s.classList.toggle('active', k === n);
            s.classList.toggle('done', k < n);
        });
        lines.forEach(function (l, i) { l.classList.toggle('done', (i + 1) < n); });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function show(el){ if(el) el.hidden=false; } function hide(el){ if(el) el.hidden=true; }
    function txt(id){ var el=document.getElementById(id); return el?(el.value||'').trim():''; }

    // ── Paso 0: elegir tipo ──
    document.querySelectorAll('.dcard').forEach(function (c) {
        c.addEventListener('click', function () {
            selectedType = c.getAttribute('data-type');
            dtInput.value = selectedType;

            var isDom = selectedType === DTYPE.DOM;

            // Tres modalidades: se muestra la rama elegida y se ocultan las otras.
            branchDom.hidden    = selectedType !== DTYPE.DOM;
            branchAg.hidden     = selectedType !== DTYPE.AG;
            if (branchTienda) branchTienda.hidden = selectedType !== DTYPE.TIENDA;

            document.getElementById('tag-moto').hidden = selectedType !== DTYPE.DOM;
            document.getElementById('tag-ag').hidden   = selectedType !== DTYPE.AG;
            var tagT = document.getElementById('tag-tienda');
            if (tagT) tagT.hidden = selectedType !== DTYPE.TIENDA;

            // Evitar que campos ocultos "required" bloqueen el submit del navegador.
            syncRequired();
            hide(step0); show(step1); step1.classList.add('fade-in');
            setStep(2);
            if (isDom && window.__initShipMapIfReady) window.__initShipMapIfReady();
        });
    });

    // Solo la rama VISIBLE envía datos: las ocultas se deshabilitan para que sus
    // `required` no bloqueen el submit ni se manden campos de otra modalidad
    // (hay names repetidos entre ramas, como shipping_destination y reference).
    function syncRequired() {
        [[branchDom, DTYPE.DOM], [branchAg, DTYPE.AG], [branchTienda, DTYPE.TIENDA]]
            .forEach(function (pair) {
                var el = pair[0], type = pair[1];
                if (!el) return;
                var off = selectedType !== type;
                el.querySelectorAll('input,select,textarea').forEach(function (f) { f.disabled = off; });
            });
        syncAgHome();
    }

    // Rama agencia: la dirección solo aparece si la agencia hace reparto. Si no,
    // el paquete se queda en la agencia y no hay dirección que registrar.
    var agHome = document.getElementById('pub_ag_home');
    var agHomeWrap = document.getElementById('agHomeWrap');
    function syncAgHome() {
        if (!agHome || !agHomeWrap) return;
        var on = agHome.checked && !agHome.disabled;
        agHomeWrap.hidden = !on;
        // La fila "hasta tu puerta" del resumen de costos aparece con el check:
        // si no, el cliente ve un costo extra que no pidio.
        var filaHome = document.getElementById('costHome');
        if (filaHome) filaHome.hidden = !on;
        var a = document.getElementById('pub_addr_agencia');
        if (a) { if (!on) a.value = ''; a.disabled = !on; }
    }
    if (agHome) agHome.addEventListener('change', syncAgHome);

    // El campo "oficina" nombra la agencia elegida: "¿En qué oficina de Shalom…?".
    // Las oficinas tienen nombre propio y es el dato que necesita el almacén.
    function syncOfficeLabel() {
        var lbl = document.getElementById('pub_office_label');
        var inp = document.getElementById('pub_reference_ag');
        if (!lbl || !inp) return;
        var ag = txt('pub_shipping_agency');
        // El "(opcional)" se reescribe junto con el nombre de la agencia: si se
        // pierde, el campo vuelve a parecer obligatorio.
        lbl.innerHTML = (ag ? ('Oficina de ' + ag + ' donde recogerás') : 'Oficina donde recogerás')
            + ' <span style="color:#94a3b8;font-weight:400;">(opcional)</span>';
        inp.placeholder = ag
            ? ('Ej. ' + ag + ' Terminal Terrestre, Av. Aviación 123…')
            : 'Ej. Terminal Terrestre, Av. Aviación 123…';
    }
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.classList && ev.target.classList.contains('agency-select')) {
            setTimeout(syncOfficeLabel, 0);
        }
    });
    var agInp = document.getElementById('pub_shipping_agency');
    if (agInp) agInp.addEventListener('input', syncOfficeLabel);
    syncOfficeLabel();

    var back0 = document.getElementById('backStep0');
    if (back0) back0.addEventListener('click', function () { hide(step1); show(step0); setStep(1); });

    // ── Consulta DNI/RUC (RENIEC/SUNAT) + cliente existente ──
    var LOOKUP = '{{ url("envio/consulta") }}', CLIENT = '{{ url("envio/cliente") }}';
    var dni = document.getElementById('pub_dni');
    var found = document.getElementById('clientFound');
    var lastClient = null, t = null;

    function fillFromClient(d) {
        if (!d) return;
        var set = function (id, v) { var el = document.getElementById(id); if (el && v) el.value = v; };
        var nmEl = document.getElementById('pub_full_name');
        if (nmEl && d.full_name) nmEl.value = d.full_name;
        set('pub_phone', d.phone);
        // Dirección/referencia según la rama activa.
        if (selectedType === DTYPE.DOM) { set('pub_addr_domicilio', d.shipping_destination); set('pub_reference_dom', d.reference); }
        else {
            set('pub_addr_agencia', d.shipping_destination); set('pub_reference_ag', d.reference);
            set('pub_shipping_agency', d.shipping_agency);
            if (window.__syncAgency) window.__syncAgency();
            if (window.__ubPreset && (d.department_id || d.district_id)) window.__ubPreset('pub', d.department_id, d.province_id, d.district_id);
        }
    }

    // Tipo de documento: adapta el campo y decide si se puede consultar en línea.
    function docType() {
        var r = document.querySelector('input[name="document_type"]:checked');
        return r ? r.value : 'dni';
    }
    function syncDocField() {
        if (!dni) return;
        var tp = docType();
        var cfg = {
            dni:       { ml: 11, im: 'numeric', ph: '8 dígitos (DNI) u 11 (RUC)' },
            ce:        { ml: 20, im: 'text',    ph: 'N° de carné de extranjería' },
            pasaporte: { ml: 20, im: 'text',    ph: 'N° de pasaporte' }
        }[tp] || { ml: 11, im: 'numeric', ph: '8 dígitos (DNI) u 11 (RUC)' };
        dni.maxLength = cfg.ml;
        dni.setAttribute('inputmode', cfg.im);
        dni.placeholder = cfg.ph;
        var st = document.querySelector('.js-doc-status');
        if (st) st.textContent = '';
        if (found) found.hidden = true;

        // Con DNI/RUC el nombre lo trae RENIEC/SUNAT: no se escribe a mano.
        var auto = (tp === 'dni' || tp === 'ruc');
        var nm = document.getElementById('pub_full_name');
        var nh = document.getElementById('pub_name_hint');
        if (nm) {
            nm.readOnly = auto;
            nm.classList.toggle('is-auto', auto);
            nm.placeholder = auto ? 'Se completa con tu documento' : 'Escribe tu nombre completo';
            if (auto) nm.value = '';
        }
        if (nh) nh.style.display = auto ? '' : 'none';
    }
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.name === 'document_type') syncDocField();
    });
    syncDocField();

    /* Empresa (RUC = 11 digitos) -> hay que decir QUIEN recoge. En este
       formulario DNI y RUC comparten opcion, asi que se distingue por la
       cantidad de digitos, igual que lo hace el servidor. */
    function esRuc() {
        var tp = docType();
        if (tp !== 'dni') return false;                 // CE/pasaporte no son empresa
        return (txt('pub_dni').replace(/\D+/g, '')).length === 11;
    }
    function syncPickupBox() {
        var box = document.getElementById('pub_pickup_box');
        if (!box) return;
        var on = esRuc();
        box.hidden = !on;
        // Los campos ocultos no deben viajar con datos de un cliente anterior.
        if (!on) {
            ['pub_pickup_name', 'pub_pickup_dni', 'pub_pickup_phone'].forEach(function (id) {
                var el = document.getElementById(id); if (el) el.value = '';
            });
            var err = document.getElementById('pub_pickup_err'); if (err) err.hidden = true;
        }
    }
    if (dni) dni.addEventListener('input', syncPickupBox);
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.name === 'document_type') syncPickupBox();
    });
    syncPickupBox();

    if (dni) dni.addEventListener('input', function () {
        var num = (dni.value || '').replace(/\D+/g, '');
        var status = document.querySelector('.js-doc-status');
        if (found) found.hidden = true;
        clearTimeout(t);
        // Solo DNI y RUC se consultan contra RENIEC/SUNAT.
        var tp = docType();
        if (tp !== 'dni' && tp !== 'ruc') { if (status) status.textContent = ''; return; }
        if (num.length !== 8 && num.length !== 11) { if (status) status.textContent = ''; return; }
        var kind = num.length === 8 ? 'dni' : 'ruc';
        if (status) { status.style.color = '#6b7280'; status.textContent = 'Consultando ' + kind.toUpperCase() + '…'; }
        t = setTimeout(function () {
            fetch(CLIENT + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.found) {
                        lastClient = res.data;
                        document.getElementById('cf_name').textContent = res.name || '—';
                        if (found) found.hidden = false;
                    }
                }).catch(function () {});
            fetch(LOOKUP + '/' + kind + '/' + num, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false || !res.data) { if (status) { status.style.color = '#dc2626'; status.textContent = (res && res.message) ? res.message : 'No se encontraron datos.'; } return; }
                    var d = res.data, full = d.name || [d.first_name, d.last_name].filter(Boolean).join(' ');
                    var nameEl = document.getElementById('pub_full_name'); if (nameEl && full) nameEl.value = full;
                    if (d.address) {
                        var a = selectedType === DTYPE.DOM ? document.getElementById('pub_addr_domicilio') : document.getElementById('pub_addr_agencia');
                        if (a && !a.value) a.value = d.address;
                    }
                    if (selectedType === DTYPE.AG) {
                        var loc = d.location_id, dep = (loc && loc[0]) || d.department_id || '', prov = (loc && loc[1]) || d.province_id || '', dist = (loc && loc[2]) || d.district_id || '';
                        if ((dep || dist) && window.__ubPreset) window.__ubPreset('pub', dep, prov, dist);
                    }
                    if (status) { status.style.color = '#16a34a'; status.textContent = '✓ ' + (full || 'encontrado'); }
                }).catch(function () { if (status) { status.style.color = '#dc2626'; status.textContent = 'No se pudo consultar.'; } });
        }, 450);
    });

    var cfUse = document.getElementById('cf_use'), cfNew = document.getElementById('cf_new');
    if (cfUse) cfUse.addEventListener('click', function () { fillFromClient(lastClient); if (found) found.hidden = true; });
    if (cfNew) cfNew.addEventListener('click', function () { if (found) found.hidden = true; });

    // ── Validación Paso 1 ──
    function validStep1() {
        var ok = true;
        var name = document.getElementById('pub_full_name');
        var phone = document.getElementById('pub_phone');
        var pdig = (phone.value || '').replace(/\D+/g, '');
        if (!name.value.trim()) { name.style.borderColor = '#dc2626'; ok = false; } else name.style.borderColor = '';
        if (!(pdig.length === 9 && pdig[0] === '9')) { phone.style.borderColor = '#dc2626'; var e = document.querySelector('.js-phone-err'); if (e) e.textContent = 'Ingresa un celular válido (9 dígitos).'; ok = false; } else phone.style.borderColor = '';

        // Empresa: sin la persona que recoge, la agencia no entrega el paquete.
        if (esRuc()) {
            var pn = document.getElementById('pub_pickup_name');
            var pd = document.getElementById('pub_pickup_dni');
            var pe = document.getElementById('pub_pickup_err');
            var pdig2 = pd ? (pd.value || '').replace(/\D+/g, '') : '';
            var falta = [];
            if (!pn || !pn.value.trim()) { if (pn) pn.style.borderColor = '#dc2626'; falta.push('el nombre'); }
            else if (pn) pn.style.borderColor = '';
            if (pdig2.length < 8) { if (pd) pd.style.borderColor = '#dc2626'; falta.push('el DNI'); }
            else if (pd) pd.style.borderColor = '';
            if (falta.length) {
                if (pe) {
                    pe.textContent = 'Falta ' + falta.join(' y ') + ' de la persona que recoge el paquete.';
                    pe.hidden = false;
                }
                ok = false;
            } else if (pe) {
                pe.hidden = true;
            }
        }

        // El recojo en tienda no pide dirección ni ubigeo: con nombre y celular
        // basta para tener el pedido listo y avisarle.
        if (selectedType === DTYPE.TIENDA) {
            return ok;
        }

        if (selectedType === DTYPE.DOM) {
            var addr = document.getElementById('pub_addr_domicilio');
            if (!addr.value.trim()) { addr.style.borderColor = '#dc2626'; ok = false; } else addr.style.borderColor = '';
        } else {
            var dist = document.querySelector('[data-ubigeo-group="pub"] [data-ub="district"]');
            if (!dist || !dist.value) { var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (disp) disp.style.borderColor = '#dc2626'; ok = false; }
            else { var d2 = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display'); if (d2) d2.style.borderColor = ''; }

            // La AGENCIA es obligatoria: sin ella el almacén no sabe dónde dejar
            // el paquete y salía un rótulo de provincia sin destino.
            var agSel = document.querySelector('.branch-agencia .agency-select');
            var agVal = txt('pub_shipping_agency');
            var agErr = document.getElementById('pub_agency_err');
            if (!agVal) {
                if (agSel) agSel.style.borderColor = '#dc2626';
                if (agErr) agErr.hidden = false;
                ok = false;
            } else {
                if (agSel) agSel.style.borderColor = '';
                if (agErr) agErr.hidden = true;
            }

            // En provincia lo único obligatorio es la AGENCIA y el ubigeo. Ni la
            // oficina de recojo ni la dirección bloquean el registro: son datos
            // que el cliente muchas veces no tiene todavía y que el encargado
            // completa después. Se avisa, pero se deja continuar.
            var casa = document.getElementById('pub_addr_agencia');
            var de   = document.getElementById('pub_dest_err');
            if (casa) casa.style.borderColor = '';
            if (de) {
                var faltaDir = agHome && agHome.checked && !(casa && casa.value.trim());
                de.style.color = '#a16207';
                de.textContent = faltaDir
                    ? 'Pediste que la agencia lleve el paquete a tu domicilio pero no escribiste la dirección; podrás indicarla después.'
                    : '';
                de.hidden = !faltaDir;
            }
        }
        return ok;
    }

    function buildConfirm() {
        var isDom    = selectedType === DTYPE.DOM;
        var isPickup = selectedType === DTYPE.TIENDA;

        // Aviso de tiempos: se muestra el de la modalidad elegida. Van los
        // tres en el HTML y aquí se decide, igual que las ramas del paso 1.
        ['domicilio', 'agencia', 'tienda'].forEach(function (t) {
            var box = document.getElementById('eta-' + t);
            if (box) box.hidden = (selectedType !== t);
        });

        // Solo aplica a agencia con reparto pedido; se apaga por defecto para
        // que no sobreviva al cambiar de modalidad.
        var homeExtra = document.getElementById('r_home_extra');
        if (homeExtra) homeExtra.hidden = true;

        document.getElementById('c_type').textContent = isPickup
            ? '🏬 Recojo en tienda'
            : (isDom ? '🏍️ Entrega a domicilio · LIMA' : '📦 Envío por agencia · PROVINCIA');
        document.getElementById('c_name').textContent = txt('pub_full_name') || '—';
        var dt = document.querySelector('input[name="document_type"]:checked');
        var dtv = dt ? dt.value : 'dni';
        var dnum = txt('pub_dni');
        var dtl = dtv === 'dni'
            ? (dnum.replace(/\D+/g, '').length === 11 ? 'RUC' : 'DNI')
            : (dt ? dt.parentNode.querySelector('span').textContent : '');
        document.getElementById('c_doc').textContent = dnum ? (dtl + ' ' + dnum) : '—';

        // Empresa: se confirma tambien quien recoge, que es a quien la agencia
        // le va a entregar el paquete.
        var rp = document.getElementById('r_pickup');
        if (rp) {
            var pnom = txt('pub_pickup_name'), pdoc = txt('pub_pickup_dni');
            rp.hidden = !esRuc();
            document.getElementById('c_pickup').textContent =
                (pnom || pdoc) ? (pnom + (pdoc ? ' · DNI ' + pdoc : '')) : '—';
        }
        document.getElementById('c_phone').textContent = txt('pub_phone') || '—';

        if (isPickup) {
            // Recojo: no hay ubigeo, agencia, coordenadas ni costo de envío.
            document.getElementById('r_ubigeo').hidden = true;
            document.getElementById('r_ag').hidden     = true;
            document.getElementById('r_coords').hidden = true;
            document.getElementById('r_price').hidden  = true;
            {{-- json_encode y no @json(): el directive se rompe con ternarios
                 (ver feedback_blade_json_parser_trap). --}}
            document.getElementById('c_dir').textContent = {!! json_encode($storeAddress ?: 'Recojo en la tienda') !!};
            document.getElementById('k_ref').textContent = 'Piensa pasar';
            document.getElementById('c_ref').textContent = txt('pub_reference_tienda') || '—';
        } else if (isDom) {
            document.getElementById('r_ubigeo').hidden = true;
            document.getElementById('r_ag').hidden = true;
            document.getElementById('c_dir').textContent = txt('pub_addr_domicilio') || txt('pub_formatted') || '—';
            document.getElementById('k_ref').textContent = 'Referencia';
            document.getElementById('c_ref').textContent = txt('pub_reference_dom') || '—';
            var lat = txt('pub_lat'), lng = txt('pub_lng');
            document.getElementById('r_coords').hidden = !(lat && lng);
            document.getElementById('c_coords').textContent = (lat && lng) ? (parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5)) : '—';
            var price = txt('pub_delivery_price');
            document.getElementById('r_price').hidden = !price;
            document.querySelector('#r_price .k').textContent = 'Costo aprox. de envío';
            document.getElementById('c_price').textContent = price ? ('S/ ' + price) : '—';
        } else {
            document.getElementById('r_ubigeo').hidden = false;
            document.getElementById('r_ag').hidden = false;
            document.getElementById('r_coords').hidden = true;
            // Costo tienda→agencia (fijo por paquete). "Gratis" es un estado
            // propio: se muestra la fila diciendo GRATIS, no se esconde.
            var af    = {{ (float) ($agencyFee ?? 0) }};
            var afFree = {{ !empty($agencyFree) ? 'true' : 'false' }};
            document.getElementById('r_price').hidden = !(afFree || af > 0);
            document.querySelector('#r_price .k').textContent = 'Servicio tienda→agencia';
            document.getElementById('c_price').textContent =
                afFree ? '¡GRATIS!' : (af > 0 ? ('S/ ' + af.toFixed(2)) : '—');
            var disp = document.querySelector('[data-ubigeo-group="pub"] .ubigeo-display');
            document.getElementById('c_ubigeo').textContent = (disp && disp.classList.contains('has-value')) ? disp.textContent.trim() : '—';
            var pidioReparto = !!(agHome && agHome.checked && txt('pub_addr_agencia'));
            if (homeExtra) homeExtra.hidden = !pidioReparto;
            document.getElementById('c_dir').textContent = txt('pub_addr_agencia') || 'Recojo en la agencia';
            document.getElementById('k_ref').textContent = 'Oficina de recojo';
            document.getElementById('c_ref').textContent = txt('pub_reference_ag') || '—';
            document.getElementById('c_ag').textContent = txt('pub_shipping_agency') || '—';
        }
    }

    var toStep2 = document.getElementById('toStep2');
    if (toStep2) toStep2.addEventListener('click', function () {
        if (!validStep1()) return;
        buildConfirm();
        hide(step1); show(step2); step2.classList.add('fade-in');
        setStep(3);
    });
    var back1 = document.getElementById('backStep1');
    if (back1) back1.addEventListener('click', function () { hide(step2); show(step1); setStep(2); });

    if (form) form.addEventListener('submit', function () {
        var b = document.getElementById('confirmBtn');
        if (b) { b.disabled = true; b.textContent = 'Registrando…'; }
    });

    // Restaurar tipo si hubo error de validación (old input).
    var oldType = dtInput.value;
    if (oldType) { var c = document.querySelector('.dcard[data-type="' + oldType + '"]'); if (c) c.click(); }
})();
</script>

@if(!empty($mapsKey))
<script>
    // ── Google Maps: Autocomplete + marcador arrastrable + geocoding ──
    (function () {
        var LIMA = { lat: -12.0464, lng: -77.0428 };
        @if(!empty($storeLat) && !empty($storeLng))
        var STORE = { lat: {{ $storeLat }}, lng: {{ $storeLng }} };
        @else
        var STORE = null;
        @endif
        @if(!empty($pricePerKm))
        var PRICE = { perKm: {{ $pricePerKm }}, base: {{ $basePrice ?? 0 }}, min: {{ $minPrice ?? 0 }} };
        @else
        var PRICE = null;
        @endif
        var map, marker, geocoder, ac, ready = false, pending = false, distSvc = null;
        // Última dirección que devolvió Google: sirve para saber si el cliente
        // editó el campo a mano y no pisarle lo que escribió.
        var lastFormatted = '';

        // Cotiza el precio del envío según los km (base + km × tarifa, con mínimo).
        function quotePrice(km) {
            if (!PRICE) return;
            var p = PRICE.base + km * PRICE.perKm;
            if (p < PRICE.min) p = PRICE.min;
            p = Math.round(p * 100) / 100;
            document.getElementById('pub_delivery_price').value = p.toFixed(2);
            var box = document.getElementById('priceBox');
            if (box) { box.style.display = 'block'; document.getElementById('priceText').textContent = p.toFixed(2); }
        }

        // Distancia de manejo tienda → cliente (Google Distance Matrix).
        function computeDistance(lat, lng) {
            if (!STORE) return;
            try {
                if (!distSvc) distSvc = new google.maps.DistanceMatrixService();
                distSvc.getDistanceMatrix({
                    origins: [STORE], destinations: [{ lat: lat, lng: lng }],
                    travelMode: 'DRIVING', unitSystem: google.maps.UnitSystem.METRIC
                }, function (res, status) {
                    if (status !== 'OK') return;
                    var el = res.rows[0] && res.rows[0].elements[0];
                    if (!el || el.status !== 'OK') return;
                    var km = (el.distance.value / 1000);
                    // La distancia se guarda (la ven motorizado y panel, no el cliente).
                    document.getElementById('pub_dist_km').value = km.toFixed(2);
                    document.getElementById('pub_dist_text').value = el.distance.text;
                    document.getElementById('pub_dur_text').value = el.duration.text;
                    // El precio SÍ se le muestra al cliente.
                    quotePrice(km);
                });
            } catch (e) {}
        }

        function pickComponent(components, type) {
            for (var i = 0; i < components.length; i++) {
                if (components[i].types.indexOf(type) !== -1) return components[i].long_name;
            }
            return '';
        }
        function applyPlace(lat, lng, formatted, components, placeId) {
            document.getElementById('pub_lat').value = lat;
            document.getElementById('pub_lng').value = lng;
            document.getElementById('pub_place_id').value = placeId || '';
            document.getElementById('pub_formatted').value = formatted || '';
            document.getElementById('pub_maps_url').value = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
            var city = '';
            if (components) {
                city = pickComponent(components, 'locality') || pickComponent(components, 'sublocality') ||
                       pickComponent(components, 'administrative_area_level_2') || pickComponent(components, 'administrative_area_level_1');
            }
            document.getElementById('pub_city_domicilio').value = city;
            // El input de dirección es el mismo que el buscador: al mover el pin
            // manda el pin, salvo que el cliente ya haya escrito algo distinto
            // de la última dirección que devolvió Google (su número/dpto a mano).
            var addrInput = document.getElementById('pub_addr_domicilio');
            if (formatted && (!addrInput.value.trim() || addrInput.value.trim() === lastFormatted)) {
                addrInput.value = formatted;
            }
            lastFormatted = formatted || '';
            addrInput.style.borderColor = '';
            var box = document.getElementById('mapPicked');
            if (box) {
                box.classList.add('show');
                document.getElementById('mp_city').textContent = '📍 Ubicación fijada' + (city ? ' · ' + city : '');
            }
            computeDistance(lat, lng);
        }
        function reverse(latlng) {
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    applyPlace(latlng.lat(), latlng.lng(), results[0].formatted_address, results[0].address_components, results[0].place_id);
                } else {
                    applyPlace(latlng.lat(), latlng.lng(), '', null, '');
                }
            });
        }
        function placeMarker(latlng) {
            marker.setPosition(latlng); map.panTo(latlng); if (map.getZoom() < 16) map.setZoom(16);
        }

        window.initShipMap = function () {
            geocoder = new google.maps.Geocoder();
            map = new google.maps.Map(document.getElementById('shipMap'), { center: LIMA, zoom: 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
            marker = new google.maps.Marker({ map: map, position: LIMA, draggable: true });
            marker.addListener('dragend', function () { reverse(marker.getPosition()); });
            map.addListener('click', function (e) { placeMarker(e.latLng); reverse(e.latLng); });

            var input = document.getElementById('pub_addr_domicilio');
            // Enter dentro del autocompletado no debe enviar el formulario.
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
            ac = new google.maps.places.Autocomplete(input, { componentRestrictions: { country: 'pe' }, fields: ['geometry', 'formatted_address', 'address_components', 'place_id'] });
            ac.bindTo('bounds', map);
            ac.addListener('place_changed', function () {
                var p = ac.getPlace();
                if (!p.geometry) return;
                var loc = p.geometry.location;
                placeMarker(loc);
                applyPlace(loc.lat(), loc.lng(), p.formatted_address, p.address_components, p.place_id);
            });
            ready = true;
            if (pending) { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }
        };
        // El mapa está oculto hasta elegir "domicilio": forzar resize al mostrarlo.
        window.__initShipMapIfReady = function () {
            if (ready && map) { setTimeout(function () { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition() || LIMA); }, 120); }
            else { pending = true; }
        };
    })();
</script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initShipMap&language=es&region=PE"></script>
@endif

{{-- Prellenado desde el pedido. json_encode y NO @json: la directiva se rompe
     con las comas anidadas de los valores. --}}
@if(!empty($orderPrefill))
    @php $shipPrefillJson = json_encode($orderPrefill, JSON_UNESCAPED_UNICODE); @endphp
    <script>
        (function () {
            var prefill = {!! $shipPrefillJson !!} || {};

            function set(id, value) {
                if (value === null || value === undefined || value === '') return null;
                var el = document.getElementById(id);
                // Nunca pisar lo que el cliente ya escribio, ni el old() que
                // vuelve tras un error de validacion.
                if (!el || el.value) return null;
                el.value = value;
                return el;
            }

            document.addEventListener('DOMContentLoaded', function () {
                set('pub_phone', prefill.phone);

                // La direccion del checkout sirve a las dos ramas (Lima y
                // agencia) y cada una tiene su propio campo con el MISMO
                // name: hay que fijar los dos por id, o el dato acaba en la
                // rama que el cliente no eligio.
                set('pub_addr_domicilio', prefill.shipping_destination);
                set('pub_addr_agencia',   prefill.shipping_destination);

                // El NOMBRE no se prellena: cuando el documento es DNI/RUC el
                // formulario lo pone en solo lectura y lo trae de
                // RENIEC/SUNAT, que es la fuente valida. Escribirlo aqui
                // metia en un campo bloqueado un nombre sin verificar.
                //
                // En su lugar se rellena el documento y se AVISA al
                // formulario con un evento 'input', que es lo que dispara su
                // consulta: asi el nombre, la direccion y el ubigeo los
                // completa el como si el cliente lo hubiera tecleado. Sin el
                // evento, el documento quedaba puesto pero el nombre vacio y
                // bloqueado, y el cliente no podia avanzar.
                var doc = set('pub_dni', prefill.dni);
                if (doc) {
                    doc.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        })();
    </script>
@endif
</body>
</html>
