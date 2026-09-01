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
            @include('tenant.shipments.partials.shipment-form-body', ['p' => 'pub_', 'context' => 'public'])
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

@include('tenant.shipments.partials.shipment-form-js', ['p' => 'pub_'])

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
