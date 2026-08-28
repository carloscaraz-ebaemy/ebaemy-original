{{-- Cuerpo del rótulo (una etiqueta). Espera: $shipment, $company, $ubigeo, $qr. --}}
<div class="label">

    @php
        // El logo de la empresa vive en storage/app/public/uploads/logos y se
        // sirve por el symlink public/storage. Se resuelve ANTES de la cabecera
        // porque su presencia cambia como se maqueta. Si el archivo no esta
        // (tenant sin logo, o borrado a mano), cae al nombre en texto: el rotulo
        // nunca debe salir con un recuadro de imagen rota.
        $logoUrl = null;
        if (!empty($company->logo)) {
            $logoRel = 'uploads/logos/' . $company->logo;
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($logoRel)) {
                $logoUrl = asset('storage/' . $logoRel);
            }
        }

        $marca = $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy';

        // La razon social completa ocupa dos lineas y no aporta nada en un
        // rotulo: "IMPORTACIONES DEYWA SOCIEDAD ANONIMA CERRADA" es la misma
        // marca que ya dice el logo, con el tipo societario escrito largo.
        // Se abrevia como se abrevia en la calle.
        // Reemplazo literal y no regex: son cuatro formas fijas y asi no hay
        // sutilezas de PCRE que hagan que no aplique sin avisar.
        foreach ([
            'SOCIEDAD ANONIMA CERRADA' => 'S.A.C.',
            'SOCIEDAD ANÓNIMA CERRADA' => 'S.A.C.',
            'EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA' => 'E.I.R.L.',
            'SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA' => 'S.R.L.',
            'SOCIEDAD DE RESPONSABILIDAD LIMITADA' => 'S.R.L.',
            'SOCIEDAD ANONIMA' => 'S.A.',
            'SOCIEDAD ANÓNIMA' => 'S.A.',
        ] as $largo => $corto) {
            $marca = str_ireplace($largo, $corto, $marca);
        }
        $marca = trim(preg_replace('/\s+/u', ' ', $marca));
    @endphp

    {{-- `has-logo` degrada el nombre a pie de firma: cuando hay logo la marca
         ya la comunica la imagen, y el texto en grande solo compite con ella
         y le roba ancho al codigo de envio, que es lo que de verdad manda. --}}
    <div class="label-header{{ $logoUrl ? ' has-logo' : '' }}">
        <div class="hdr-code">
            <div class="section-title">N° Envío</div>
            <div class="env-code">{{ $shipment->shipment_code }}</div>
            @if(!empty($barcode))<img class="barcode-img" src="data:image/png;base64,{{ $barcode }}" alt="{{ $shipment->shipment_code }}">@endif
        </div>
        <div class="hdr-brand">
            @if($logoUrl)
                <img class="brand-logo" src="{{ $logoUrl }}" alt="{{ $marca }}">
            @endif
            <div class="brand">{{ $marca }}</div>
            @if($shipment->created_at)<div class="hdr-date">Registrado: {{ $shipment->created_at->format('d/m/Y H:i') }}</div>@endif
        </div>
    </div>

    {{-- Tipo de entrega --}}
    <div style="text-align:center;margin:2px 0 6px;">
        <span style="display:inline-block;font-size:11px;font-weight:bold;letter-spacing:.5px;padding:3px 12px;border-radius:20px;{{ $shipment->is_domicilio ? 'background:#ede9fe;color:#6d28d9;' : 'background:#dbeafe;color:#1d4ed8;' }}">
            {{ $shipment->is_domicilio ? '🏍️ ENTREGA A DOMICILIO (MOTORIZADO)' : '📦 ENVÍO POR AGENCIA' }}
        </span>
    </div>

    {{-- Destinatario --}}
    @php
        // Cliente EMPRESA: la agencia no le entrega a un RUC, entrega a la
        // persona designada. Se imprime destacada para que el counter la vea.
        $recoge = $shipment->is_company ? trim((string) $shipment->pickup_person_name) : '';
        $recogeDni = $shipment->is_company ? trim((string) $shipment->pickup_person_dni) : '';
    @endphp
    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="big-text">{{ $shipment->full_name }}</div>
        @if($shipment->phone)<div class="med-text">Cel: {{ $shipment->phone }}</div>@endif
        @if($shipment->dni)<div class="med-text">{{ $shipment->document_label }}: {{ $shipment->dni }}</div>@endif
    </div>

    @if($recoge !== '' || $recogeDni !== '')
        <div class="section">
            <div class="section-title">Recoge</div>
            <div class="big-text">{{ $recoge ?: '—' }}</div>
            @if($recogeDni !== '')<div class="med-text">DNI: {{ $recogeDni }}</div>@endif
            @if($shipment->pickup_person_phone)<div class="med-text">Cel: {{ $shipment->pickup_person_phone }}</div>@endif
        </div>
    @endif

    @if($shipment->is_domicilio)
        {{-- ════════ RÓTULO DOMICILIO (hoja de reparto del motorizado) ════════ --}}
        @php
            $distLine = $shipment->distance_km
                ? (($shipment->distance_text ?: ($shipment->distance_km.' km')) . ' desde la tienda'
                    . ($shipment->duration_text ? ' · ~'.$shipment->duration_text : ''))
                : null;
            $coordLine = $shipment->has_coords
                ? number_format($shipment->latitude, 5) . ', ' . number_format($shipment->longitude, 5)
                : null;
        @endphp
        <div class="section">
            <div class="section-title">Dirección de entrega</div>
            {{-- Manda lo que escribió el cliente: ahí van el número de puerta,
                 dpto o interior que el geocodificador de Google no devuelve.
                 La dirección de Google va debajo solo si aporta algo distinto. --}}
            @php
                $addrMain = $shipment->shipping_destination ?: $shipment->formatted_address;
                $addrAlt  = ($shipment->formatted_address && $shipment->formatted_address !== $addrMain)
                    ? $shipment->formatted_address : null;
            @endphp
            <div class="big-text" style="font-size:15px;">{{ $addrMain ?: '—' }}</div>
            @if($addrAlt)<div class="med-text" style="color:#555;">({{ $addrAlt }})</div>@endif
            @if($shipment->reference)<div class="med-text" style="font-weight:bold">Ref: {{ $shipment->reference }}</div>@endif
            @if($shipment->destination_city)<div class="med-text">{{ $shipment->destination_city }}</div>@endif
        </div>

        @if($distLine)
            <div style="text-align:center;margin:4px 0;font-size:13px;font-weight:bold;color:#3730a3;background:#eef2ff;border-radius:8px;padding:6px;">
                🛵 {{ $distLine }}
            </div>
        @endif

        @if($shipment->priceLabel())
            <div style="text-align:center;margin:4px 0;font-size:14px;font-weight:bold;color:#166534;background:#dcfce7;border-radius:8px;padding:6px;">
                💵 Cobro de envío: {{ $shipment->priceLabel() }}
            </div>
        @endif

        <div class="divider"></div>

        @if(!empty($qr))
            <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                <img class="qr-img" src="data:image/png;base64,{{ $qr }}" alt="QR navegacion">
                <div style="font-size:11px;color:#222;line-height:1.35;">
                    <strong>Escanea para navegar</strong><br>
                    abre la ruta directo en<br>Google Maps 🗺️
                    {!! $coordLine ? '<br><span style="color:#666;">'.e($coordLine).'</span>' : '' !!}
                </div>
            </div>
        @endif
    @else
        {{-- ════════ RÓTULO AGENCIA ════════ --}}
        <div class="section">
            {{-- Sin dirección = el cliente recoge en la agencia (es el caso normal).
                 Se dice explícito para que el almacén no lo lea como dato faltante. --}}
            @if($shipment->shipping_destination)
                <div class="med-text" style="font-weight:bold">Reparto a domicilio: {{ $shipment->shipping_destination }}</div>
            @else
                <div class="med-text" style="font-weight:bold">RECOJO EN AGENCIA</div>
            @endif
            {{-- En agencia, `reference` guarda la oficina/local de recojo. --}}
            @if($shipment->reference)<div class="med-text" style="font-weight:bold">Oficina: {{ $shipment->reference }}</div>@endif
            @php
                $ubigeoLine = null;
                if (!empty($ubigeo)) {
                    $ubigeoLine = $ubigeo['district'];
                    if (!empty($ubigeo['province']))   $ubigeoLine .= ', ' . $ubigeo['province'];
                    if (!empty($ubigeo['department'])) $ubigeoLine .= ', ' . $ubigeo['department'];
                } elseif ($shipment->destination_city) {
                    $ubigeoLine = $shipment->destination_city;
                }
            @endphp
            @if($ubigeoLine)<div class="med-text" style="font-weight:bold">{{ $ubigeoLine }}</div>@endif
        </div>

        @include('tenant.shipments.partials.package-content', ['shipment' => $shipment])

        <div class="divider"></div>

        {{-- Agencia / Bulto N° (se escribe a mano) --}}
        <div class="grid">
            <div class="box">
                <div class="section-title">Agencia</div>
                <div class="v">{{ strtoupper($shipment->shipping_agency ?: '—') }}</div>
            </div>
            <div class="box">
                <div class="section-title">Bulto N° (escribir a mano)</div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
                    <span class="bulto-box"></span>
                    <span class="bulto-total">/</span>
                    <span class="bulto-box"></span>
                </div>
            </div>
        </div>

        @if($shipment->weight)
            <div style="text-align:right;font-size:11px;margin-top:3px;"><strong>Peso:</strong> {{ rtrim(rtrim(number_format($shipment->weight, 2), '0'), '.') }} kg</div>
        @endif

        @if($shipment->tracking_number)
            <div class="guide-box">
                <div class="l">Guía</div>
                <div class="n">{{ $shipment->tracking_number }}</div>
            </div>
        @else
            <div class="guide-box" style="border-style:dashed;">
                <div class="l">Guía</div>
                <div class="n" style="font-size:13px;letter-spacing:1px;color:#888;">PENDIENTE DE CARGAR</div>
            </div>
        @endif

        @if(!empty($qr))
            <div style="display:flex;align-items:center;gap:10px;margin-top:8px;border-top:1px dashed #999;padding-top:8px;">
                <img class="qr-img" src="data:image/png;base64,{{ $qr }}" alt="QR estado del envío">
                <div style="font-size:11px;color:#222;line-height:1.35;">
                    <strong>Escanea el QR</strong><br>
                    para registrar el estado del paquete:<br>preparando · listo · enviado.
                </div>
            </div>
        @endif
    @endif

    @if($shipment->is_domicilio)
        @include('tenant.shipments.partials.package-content', [
            'shipment' => $shipment,
            'title'    => 'Contenido',
        ])
    @endif

    @if($shipment->notes)
        <div class="section" style="margin-top:8px;">
            <div class="section-title">Información adicional</div>
            <div class="med-text">{{ $shipment->notes }}</div>
        </div>
    @endif

    @if($shipment->observation)
        <div class="section" style="margin-top:8px;">
            <div class="section-title">Observación</div>
            <div class="med-text">{{ $shipment->observation }}</div>
        </div>
    @endif

    <div class="footer">
        Registro y Control de Envíos · {{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}
    </div>

</div>
