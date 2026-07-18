{{-- Cuerpo del rótulo (una etiqueta). Espera: $shipment, $company, $ubigeo, $qr. --}}
<div class="label">

    <div class="label-header">
        <div>
            <div class="section-title">N° Envío</div>
            <div class="env-code">{{ $shipment->shipment_code }}</div>
        </div>
        <div style="text-align:right;">
            <div class="brand">{{ $company->title_web ?? $company->trade_name ?? $company->name ?? 'ebaemy' }}</div>
            <div style="font-size:10px;color:#555;">{{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    {{-- Destinatario --}}
    <div class="section">
        <div class="section-title">Destinatario</div>
        <div class="big-text">{{ $shipment->full_name }}</div>
        @if($shipment->phone)<div class="med-text">Cel: {{ $shipment->phone }}</div>@endif
        @if($shipment->dni)<div class="med-text">DNI: {{ $shipment->dni }}</div>@endif
        @if($shipment->shipping_destination)<div class="med-text" style="font-weight:bold">{{ $shipment->shipping_destination }}</div>@endif
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
