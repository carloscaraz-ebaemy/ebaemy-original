{{-- Cuerpo del rótulo (una etiqueta). Espera: $shipment, $company, $ubigeo, $qr. --}}
<div class="label">

    <div class="label-header">
        <div>
            <div class="section-title">N° Envío</div>
            <div class="env-code">{{ $shipment->shipment_code }}</div>
            @if(!empty($barcode))<img class="barcode-img" src="data:image/png;base64,{{ $barcode }}" alt="{{ $shipment->shipment_code }}">@endif
        </div>
        <div style="text-align:right;">
            <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            <div style="font-size:10px;color:#555;">{{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    {{-- Tipo de entrega --}}
    <div style="text-align:center;margin:2px 0 6px;">
        <span style="display:inline-block;font-size:11px;font-weight:bold;letter-spacing:.5px;padding:3px 12px;border-radius:20px;{{ $shipment->is_domicilio ? 'background:#ede9fe;color:#6d28d9;' : 'background:#dbeafe;color:#1d4ed8;' }}">
            {{ $shipment->is_domicilio ? '🏍️ ENTREGA A DOMICILIO (MOTORIZADO)' : '📦 ENVÍO POR AGENCIA' }}
        </span>
    </div>

    {{-- Destinatario --}}
    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="big-text">{{ $shipment->full_name }}</div>
        @if($shipment->phone)<div class="med-text">Cel: {{ $shipment->phone }}</div>@endif
        @if($shipment->dni)<div class="med-text">DNI: {{ $shipment->dni }}</div>@endif
    </div>

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
            <div class="big-text" style="font-size:15px;">{{ $shipment->formatted_address ?: $shipment->shipping_destination ?: '—' }}</div>
            @if($shipment->reference)<div class="med-text" style="font-weight:bold">Ref: {{ $shipment->reference }}</div>@endif
            @if($shipment->destination_city)<div class="med-text">{{ $shipment->destination_city }}</div>@endif
        </div>

        @if($distLine)
            <div style="text-align:center;margin:4px 0;font-size:13px;font-weight:bold;color:#3730a3;background:#eef2ff;border-radius:8px;padding:6px;">
                🛵 {{ $distLine }}
            </div>
        @endif

        @if($shipment->delivery_price)
            <div style="text-align:center;margin:4px 0;font-size:14px;font-weight:bold;color:#166534;background:#dcfce7;border-radius:8px;padding:6px;">
                💵 Cobro de envío: S/ {{ number_format($shipment->delivery_price, 2) }}
            </div>
        @endif

        @if($shipment->package_content)
            <div class="section"><div class="section-title">Contenido</div><div class="med-text">{{ $shipment->package_content }}</div></div>
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
            @if($shipment->shipping_destination)<div class="med-text" style="font-weight:bold">{{ $shipment->shipping_destination }}</div>@endif
            @if($shipment->reference)<div class="med-text">Ref: {{ $shipment->reference }}</div>@endif
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

        @if($shipment->package_content)
            <div class="section">
                <div class="section-title">Contenido del paquete</div>
                <div class="med-text">{{ $shipment->package_content }}</div>
            </div>
        @endif

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
