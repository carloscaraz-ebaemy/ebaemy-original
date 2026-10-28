{{-- Rótulos (o tickets) OFICIALES de Shalom de toda una tanda, en un solo
     PDF: una copia por bulto y en el papel elegido. Imprimir registra la
     impresión de cada envío igual que el rótulo suelto. --}}
@php
    $ids = request('ids');
    $fuentes = $listos->map(fn ($s) => [
        'url'    => url('orders/envio/' . $s->id . ($doc === 'ticket' ? '/shalom-ticket' : '/shalom-rotulo')),
        'copies' => $doc === 'rotulo' ? max(1, (int) ($s->package_count ?: 1)) : 1,
        'nombre' => $s->shipment_code,
    ])->values()->all();
    $bultos = $listos->sum(fn ($s) => max(1, (int) ($s->package_count ?: 1)));
@endphp
@include('tenant.shipments.partials.shalom-print-ui', [
    'titulo'    => ($doc === 'ticket' ? 'Tickets de Shalom' : 'Rótulos de Shalom') . ' del lote',
    'subtitulo' => $doc === 'ticket'
        ? 'Uno por envío: la agencia escanea el QR para recibir cada paquete.'
        : 'Uno por caja, en el papel que elijas.',
    'doc'       => $doc,
    'docUrls'   => ['rotulo' => '?' . http_build_query(['ids' => $ids, 'doc' => 'rotulo']), 'ticket' => '?' . http_build_query(['ids' => $ids, 'doc' => 'ticket'])],
    'fuentes'   => $fuentes,
    'resumen'   => [['Envíos', (string) $listos->count()], ['Bultos', (string) $bultos]],
    'incluidos' => $listos->map(fn ($s) => [$s->shipment_code ?: ('#' . $s->id), trim(($s->tracking_number ?: '') . ' · ' . max(1, (int) ($s->package_count ?: 1)) . ' bulto(s)', ' ·')])->all(),
    'fuera'     => $fuera,
    'registrar' => $doc === 'rotulo' ? $listos->map(fn ($s) => ['id' => $s->id, 'reimp' => (int) $s->print_count > 0])->values()->all() : [],
    'original'  => null,
    'vacio'     => 'No hay envíos de Shalom con guía en esta selección. Crea las guías que faltan desde el lote o desde cada pedido.',
])
