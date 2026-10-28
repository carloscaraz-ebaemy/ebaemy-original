{{-- Rótulo (o ticket) OFICIAL de Shalom de un envío. Imprimirlo cuenta como
     «rótulo impreso» (contador, chip y bitácora), igual que el de ebaemy; el
     ticket no, porque es para la agencia. Una copia por bulto. --}}
@php
    $bultos = max(1, (int) ($shipment->package_count ?: 1));
    $copias = $doc === 'rotulo' ? $bultos : 1;
    $guia = $shipment->tracking_number ?: '—';
@endphp
@include('tenant.shipments.partials.shalom-print-ui', [
    'titulo'    => ($doc === 'ticket' ? 'Ticket de Shalom' : 'Rótulo de Shalom') . ' · ' . $guia,
    'subtitulo' => $doc === 'ticket'
        ? 'La agencia escanea este QR para recibir el paquete.'
        : ($bultos > 1 ? 'Sale un rótulo para cada caja.' : 'Pégalo en la caja.'),
    'doc'       => $doc,
    'docUrls'   => ['rotulo' => '?doc=rotulo', 'ticket' => '?doc=ticket'],
    'fuentes'   => [['url' => $pdfUrl, 'copies' => $copias, 'nombre' => $shipment->shipment_code]],
    'resumen'   => array_values(array_filter([
        ['Guía', $guia],
        $shipment->tracking_code ? ['Código', $shipment->tracking_code] : null,
        ['Bultos', (string) $bultos],
        $shipment->agency_office_name ? ['Oficina', $shipment->agency_office_name] : null,
        !empty($shipment->shalom_air) ? ['Vía', 'Aérea'] : null,
    ])),
    'incluidos' => [],
    'fuera'     => [],
    'registrar' => $doc === 'rotulo' ? [['id' => $shipment->id, 'reimp' => (int) $shipment->print_count > 0]] : [],
    'original'  => $pdfUrl,
])
