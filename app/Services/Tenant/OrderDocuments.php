<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;

/**
 * Qué documentos tiene, puede y no puede tener un pedido.
 *
 * ── Por qué existe ────────────────────────────────────────────────────────
 *
 * La respuesta a «¿este pedido ya tiene boleta?» estaba repartida en cinco
 * sitios: `orders.number_document`, `orders.document_external_id`,
 * `sale_notes.order_id`, `documents.sale_note_id` y
 * `shipping_requests.dispatch_id`. Cada pantalla miraba los que se acordaba, y
 * por eso faltaban dos guardas anti-duplicado que nadie podía escribir sin
 * antes juntar las cinco: nada impedía emitir boleta Y factura del mismo
 * pedido, ni una segunda guía de remisión.
 *
 * Este servicio es ese punto único. No emite nada: responde qué hay, qué
 * corresponde y —cuando algo no se puede— POR QUÉ, con el texto que el
 * operador necesita leer.
 *
 * ── Lo que NO hace ────────────────────────────────────────────────────────
 *
 * No genera documentos. La nota de venta la construye `OrderToSaleNoteService`,
 * el comprobante electrónico sale por Facturalo desde `DocumentController` y la
 * guía de remisión por `DispatchController`. Ese código lleva años emitiendo
 * ante SUNAT y no se toca ni se duplica aquí.
 *
 * ── Sin consultas por fila ────────────────────────────────────────────────
 *
 * El listado de pedidos pinta 20 filas por página. Todo lo que lee este
 * servicio sale de relaciones YA cargadas (`sale_note.documents`,
 * `shipment.dispatch`); si alguna no viene precargada, el campo se responde
 * como desconocido en vez de disparar la consulta. Ver
 * `OrderController::buildOrdersQuery()`, que es quien las precarga.
 */
class OrderDocuments
{
    /** Nota de venta: documento interno, no va a SUNAT. */
    public const NOTA_VENTA = 'nota_venta';
    /** Boleta electrónica (tipo 03). */
    public const BOLETA = 'boleta';
    /** Factura electrónica (tipo 01). */
    public const FACTURA = 'factura';
    /** Guía de remisión electrónica (tipo 09). */
    public const GUIA = 'guia';

    /** Código SUNAT de cada tipo, para buscar en `documents.document_type_id`. */
    private const TIPO_SUNAT = [
        self::BOLETA  => '03',
        self::FACTURA => '01',
    ];

    /** Etiqueta corta para el chip de la tabla. */
    private const CHIP = [
        self::NOTA_VENTA => 'NV',
        self::BOLETA     => 'B',
        self::FACTURA    => 'F',
        self::GUIA       => 'GR',
    ];

    private const NOMBRE = [
        self::NOTA_VENTA => 'Nota de venta',
        self::BOLETA     => 'Boleta',
        self::FACTURA    => 'Factura',
        self::GUIA       => 'Guía de remisión',
    ];

    /**
     * Catálogo `state_types` del tenant, copiado aquí a propósito.
     *
     * Es una tabla de 7 filas que no cambia desde 2018 y se consulta una vez
     * por fila del listado. Una constante evita 20 consultas por página; si
     * algún día el catálogo crece, este arreglo se queda corto de forma
     * visible (sale el código crudo) en vez de romper la pantalla.
     */
    private const ESTADO_CPE = [
        '01' => 'Registrado',
        '03' => 'Enviado',
        '05' => 'Aceptado',
        '07' => 'Observado',
        '09' => 'Rechazado',
        '11' => 'Anulado',
        '13' => 'Por anular',
    ];

    /** Estados en los que el documento ya no vale: se puede volver a emitir. */
    private const ESTADO_MUERTO = ['09', '11'];

    private ?BillingDocumentResolver $resolver = null;
    private ?array $propuesta = null;

    public function __construct(private Order $order)
    {
    }

    public static function for(Order $order): self
    {
        return new self($order);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Salida
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Los cuatro documentos del pedido, listos para pintar.
     *
     * Un tipo que NO corresponde a este pedido sale como `null`, no como una
     * casilla vacía: pintarlo en gris invitaría al operador a intentar algo
     * que el sistema va a rechazar. Ver `aplica()`.
     */
    public function toArray(): array
    {
        $salida = [];

        foreach ([self::NOTA_VENTA, self::BOLETA, self::FACTURA, self::GUIA] as $tipo) {
            $salida[$tipo] = $this->aplica($tipo) ? $this->slot($tipo) : null;
        }

        return $salida;
    }

    /** ¿Existe ya un documento vivo de este tipo? */
    public function tiene(string $tipo): bool
    {
        return $this->registro($tipo) !== null;
    }

    /**
     * Por qué NO se puede emitir este tipo ahora, o null si sí se puede.
     *
     * Este es el corazón del servicio: las guardas anti-duplicado. Devuelve
     * texto para el operador, no un código, porque el único consumidor útil de
     * un «no se puede» es alguien que necesita saber qué hacer a continuación.
     */
    public function motivoBloqueo(string $tipo): ?string
    {
        if (!$this->aplica($tipo)) {
            return 'Este pedido no admite ' . mb_strtolower(self::NOMBRE[$tipo] ?? $tipo) . '.';
        }

        if ($this->order->status_order_id == 5) {
            return 'El pedido está anulado.';
        }

        if ($existente = $this->registro($tipo)) {
            return self::NOMBRE[$tipo] . ' ya emitida: ' . $this->numeroDe($tipo, $existente) . '.';
        }

        return match ($tipo) {
            self::NOTA_VENTA => $this->bloqueoSinCliente(),
            self::BOLETA     => $this->bloqueoEmitidoFuera()
                                ?? $this->bloqueoComprobante(self::FACTURA)
                                ?? $this->bloqueoPorDatos(BillingDocumentResolver::BOLETA),
            self::FACTURA    => $this->bloqueoEmitidoFuera()
                                ?? $this->bloqueoComprobante(self::BOLETA)
                                ?? $this->bloqueoPorDatos(BillingDocumentResolver::FACTURA),
            self::GUIA       => $this->bloqueoGuia(),
            default          => null,
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // Qué corresponde a este pedido
    // ══════════════════════════════════════════════════════════════════════

    /**
     * ¿Corresponde este tipo de documento a este pedido?
     *
     * La regla que importa: **el pedido espejo de un encargo logístico no
     * documenta una venta**. Se crea con 0 líneas y total 0 porque el envío
     * guarda su contenido como texto libre (ver `OrderShipmentLinker`), así que
     * no hay nada que facturar — hay un traslado que sustentar. Para esos el
     * único documento que cabe es la guía de remisión.
     *
     * Sin esta regla el operador vería los cuatro chips, intentaría facturar
     * S/ 0.00 y recibiría un rechazo que no puede interpretar.
     */
    public function aplica(string $tipo): bool
    {
        if ($tipo === self::GUIA) {
            return $this->guiaAplica();
        }

        return $this->tieneContenidoFacturable();
    }

    /**
     * ¿Hay una venta que documentar?
     *
     * Se mira el contenido y no el canal: un pedido con líneas e importe es
     * facturable venga de donde venga, y uno vacío no lo es aunque su canal
     * diga «ecommerce». Vale también para un pedido manual mal cargado.
     */
    private function tieneContenidoFacturable(): bool
    {
        return (float) $this->order->total > 0 && count($this->lineas()) > 0;
    }

    /**
     * ¿Corresponde guía de remisión?
     *
     * Solo si el pedido tiene un envío que de verdad viaja. El recojo en tienda
     * no lleva guía —el paquete no se traslada a un destinatario externo, lo
     * retira el cliente del mostrador— y esa regla ya está escrita en
     * `ShippingRequest::canGenerateDispatch()`; aquí se consulta, no se copia.
     */
    private function guiaAplica(): bool
    {
        $envio = $this->envio();

        if (!$envio) {
            return false;
        }

        // Un envío anulado no habilita guía nueva, pero si YA tenía una emitida
        // hay que seguir mostrándola: es un documento ante SUNAT que existe.
        return $envio->has_dispatch || $envio->canGenerateDispatch();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Guardas anti-duplicado
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Boleta y factura son excluyentes.
     *
     * Una venta se documenta con UN comprobante. Emitir los dos significa
     * declarar la misma operación dos veces ante SUNAT, y deshacerlo exige una
     * nota de crédito. Esta guarda no existía en ninguna parte.
     */
    private function bloqueoComprobante(string $elOtro): ?string
    {
        $otro = $this->registro($elOtro);

        if (!$otro) {
            return null;
        }

        return 'Este pedido ya tiene ' . mb_strtolower(self::NOMBRE[$elOtro])
             . ' ' . $this->numeroDe($elOtro, $otro)
             . '. Una venta se documenta con un solo comprobante: para cambiarlo hay que '
             . 'anular el emitido con nota de crédito.';
    }

    /**
     * Sin documento del cliente no hay nota de venta.
     *
     * `sale_notes.customer_id` es NOT NULL y `Person::resolveCustomer()` no crea
     * ficha sin documento —decisión tomada: SUNAT lo exige y una cartera de
     * «CLIENTE» sin número es peor—. Antes esto acababa en un 1048 que se
     * tragaba el `catch` de `OrderToSaleNoteService`, y el pedido se quedaba sin
     * nota sin que nadie supiera por qué. Se dice aquí, antes de intentarlo.
     */
    private function bloqueoSinCliente(): ?string
    {
        if ($this->order->person_id) {
            return null;
        }

        if ($this->resolver()->documento($this->order) !== '') {
            return null;
        }

        return 'Falta el documento del cliente: la nota de venta necesita a quién emitirla.';
    }

    /**
     * El comprobante ya se emitió FUERA de EBAEMY.
     *
     * En Saga, el vendedor puede emitir la boleta desde el portal del canal;
     * el pedido queda con `invoice_uploaded_at` y sin ningún `Document` local.
     * Como aquí no hay fila que encontrar, los tres caminos del comprobante
     * dan «no existe» y la pantalla diría «se puede emitir» sobre una venta ya
     * documentada. Esa es la vía más fácil de duplicar un comprobante ante
     * SUNAT, y no la cubría nada.
     */
    private function bloqueoEmitidoFuera(): ?string
    {
        if (!$this->order->relationLoaded('marketplaceOrder')) {
            return null;
        }

        $mo = $this->order->marketplaceOrder;

        if (!$mo || !$mo->invoice_uploaded_at) {
            return null;
        }

        return 'El comprobante de este pedido ya se emitió en el portal del canal, '
             . 'fuera de EBAEMY. Emitir otro aquí duplicaría la venta ante SUNAT.';
    }

    /**
     * Faltan datos tributarios para este tipo.
     *
     * La lista la arma `BillingDocumentResolver`, que es donde viven las reglas
     * de SUNAT (RUC para factura, tope de la boleta sin identificar). Aquí solo
     * se traduce a una frase; tener las reglas en dos sitios garantizaría que un
     * día la propuesta y el bloqueo se contradigan.
     */
    private function bloqueoPorDatos(string $tipoSunat): ?string
    {
        $faltan = $this->resolver()->faltanDatos($this->order, $tipoSunat);

        if (empty($faltan)) {
            return null;
        }

        return 'Falta ' . implode(' y ', $faltan) . '.';
    }

    /**
     * Una guía por despacho.
     *
     * El corte por envío ya existe en `ShipmentController::generateDispatch()`.
     * Lo que faltaba —y es lo que se cubre aquí— es el corte por PEDIDO: desde
     * Pedidos se puede llegar a la guía por dos caminos (el envío y la nota de
     * venta), y sin este cheque el mismo despacho podía terminar con dos guías.
     */
    private function bloqueoGuia(): ?string
    {
        $envio = $this->envio();

        if (!$envio) {
            return 'El pedido no tiene envío configurado: la guía se genera desde el envío.';
        }

        if ($envio->cancelled_at) {
            return 'El envío está anulado. Restáuralo antes de generar la guía.';
        }

        if ($envio->is_pickup) {
            return 'El recojo en tienda no requiere guía de remisión: el paquete no viaja.';
        }

        // El envío ya avisa qué datos le faltan al rótulo, y la guía necesita
        // los mismos (destinatario, documento, destino). Reutilizar ese aviso
        // evita que el operador descubra el problema recién en Facturalo.
        $faltan = $envio->missingLabelData();
        if (!empty($faltan)) {
            return 'Faltan datos del envío: ' . implode(', ', $faltan) . '.';
        }

        // Una guía sin ítems no existe: SUNAT exige el detalle de lo que viaja.
        // El envío lo guarda como TEXTO LIBRE en `package_content`, que muchas
        // veces llega vacío — en alasitas son 113 de 237. Sin este corte, el
        // menú ofrecía «Generar guía», abría una pestaña y el formulario la
        // rechazaba allí; el operador descubría el problema después de cambiar
        // de pantalla en vez de antes de salir de la fila.
        //
        // Se mira `contentLines()`, la MISMA función que parte el texto para el
        // prefill: contar líneas útiles no es lo mismo que «el campo no está
        // vacío», y dos criterios distintos darían dos respuestas distintas.
        if (!count($envio->contentLines())) {
            return 'El envío no tiene el detalle de lo que se traslada. '
                 . 'Complétalo en el envío: la guía necesita al menos un ítem.';
        }

        // En agencia, el transportista es bloqueante: `generateDispatch()` ni
        // siquiera abre el formulario sin él. Antes eso se descubría en la
        // pestaña nueva; ahora se dice en la fila, con el mismo texto y la misma
        // lógica de emparejado — se consulta `ShipmentDispatchPrefill`, no se
        // reimplementa. La lista de transportistas se lee una vez por petición.
        if ($envio->is_agencia) {
            $pre = new ShipmentDispatchPrefill();

            if (!$pre->transportista($envio)) {
                // `transportista()` sale sin aviso cuando la tabla no existe
                // (tenant sin el módulo de guías). Un bloqueo con el texto vacío
                // se vería como un chip apagado sin explicación, que es peor que
                // no ofrecerlo: mejor una frase genérica que un hueco.
                return implode(' ', $pre->avisos())
                    ?: 'No se pudo resolver el transportista del envío.';
            }
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Lectura de los documentos existentes
    // ══════════════════════════════════════════════════════════════════════

    /**
     * El documento vivo de este tipo, o null.
     *
     * «Vivo» excluye rechazados y anulados: un comprobante en estado 09 u 11 no
     * documenta nada, y bloquear la reemisión por su culpa dejaría al pedido sin
     * salida. Es el mismo criterio de `ModelTenant::VOIDED_REJECTED_IDS`.
     */
    private function registro(string $tipo)
    {
        return match ($tipo) {
            self::NOTA_VENTA => $this->order->sale_note,
            self::BOLETA, self::FACTURA => $this->comprobante($tipo),
            self::GUIA       => $this->guia(),
            default          => null,
        };
    }

    /**
     * El comprobante del pedido, por los TRES caminos que existen.
     *
     * 1. El normal: pedido → nota de venta → `documents.sale_note_id`.
     * 2. El directo: `orders.document_external_id`, que escribe el flujo
     *    antiguo del panel al pasar el pedido a «pago verificado».
     * 3. El de Saga: `marketplace_orders.document_id`, porque
     *    `MarketplaceInvoiceService` necesita el PDF para subirlo al portal en
     *    el mismo acto.
     *
     * Mirar solo el primero es exactamente cómo se cuela un comprobante
     * duplicado. Y no es hipotético: el pedido 27 de alasitas se enlaza por el
     * camino 1 y el 18 por el 2, en el mismo tenant y con dos meses de
     * diferencia.
     */
    private function comprobante(string $tipo)
    {
        foreach ($this->candidatosComprobante() as $documento) {
            if ($documento && $this->esComprobanteVivo($documento, $tipo)) {
                return $documento;
            }
        }

        return null;
    }

    /** Los documentos enlazados al pedido por cualquiera de los tres caminos. */
    private function candidatosComprobante(): iterable
    {
        $nv = $this->order->sale_note;

        if ($nv && $nv->relationLoaded('documents')) {
            foreach ($nv->documents as $d) {
                yield $d;
            }
        }

        if ($this->order->relationLoaded('document')) {
            yield $this->order->document;
        }

        $mo = $this->order->relationLoaded('marketplaceOrder')
            ? $this->order->marketplaceOrder
            : null;

        if ($mo && $mo->relationLoaded('document')) {
            yield $mo->document;
        }
    }

    private function esComprobanteVivo($documento, string $tipo): bool
    {
        return (string) $documento->document_type_id === self::TIPO_SUNAT[$tipo]
            && !in_array((string) $documento->state_type_id, self::ESTADO_MUERTO, true);
    }

    private function guia()
    {
        $envio = $this->envio();

        if (!$envio || !$envio->dispatch_id || !$envio->relationLoaded('dispatch')) {
            return null;
        }

        $g = $envio->dispatch;

        if (!$g || in_array((string) $g->state_type_id, self::ESTADO_MUERTO, true)) {
            return null;
        }

        return $g;
    }

    /**
     * La ficha del documento para la pantalla.
     */
    private function slot(string $tipo): array
    {
        $reg      = $this->registro($tipo);
        $bloqueo  = $this->motivoBloqueo($tipo);

        return [
            'tipo'        => $tipo,
            'chip'        => self::CHIP[$tipo],
            'nombre'      => self::NOMBRE[$tipo],
            'existe'      => $reg !== null,
            'id'          => $reg?->id,
            'external_id' => $reg?->external_id,
            'numero'      => $reg ? $this->numeroDe($tipo, $reg) : null,
            'fecha'       => $reg && $reg->date_of_issue
                             ? $reg->date_of_issue->format('Y-m-d')
                             : null,
            'estado'      => $this->estado($tipo, $reg),
            'estado_label'=> $this->estadoLabel($tipo, $reg),
            // Emitido e impreso son dos hechos distintos: un comprobante que
            // existe pero nunca fue a la impresora no esta en la caja del
            // paquete, y para despachar esa es la pregunta util.
            'impreso'     => (bool) ($reg->printed_at ?? null),
            'impreso_at'  => optional($reg?->printed_at)->format('Y-m-d H:i'),
            'veces'       => (int) ($reg->print_count ?? 0),
            'motivo_error'=> $this->motivoError($tipo, $reg),
            'pdf_url'     => $reg ? $this->pdfUrl($tipo, $reg) : null,
            // Null = se puede emitir. Con texto = no, y el texto dice por qué.
            'bloqueo'     => $reg ? null : $bloqueo,
            // El que corresponde a este pedido según el documento del cliente,
            // lo que pidió al comprar y lo que el operador haya corregido. La
            // pantalla lo destaca para que el operador no tenga que deducirlo.
            'sugerido'    => !$reg && $this->esSugerido($tipo),
        ];
    }

    private function numeroDe(string $tipo, $reg): string
    {
        return (string) ($reg->number_full ?? ($reg->series . '-' . $reg->number));
    }

    /**
     * Estado normalizado, común a los cuatro tipos, para que la pantalla no
     * tenga que saber que la NV no tiene `state_type_id` y la guía sí.
     */
    private function estado(string $tipo, $reg): string
    {
        if (!$reg) {
            return 'no_emitido';
        }

        if ($tipo === self::NOTA_VENTA) {
            // La NV no va a SUNAT. Su único estado relevante es si sigue vigente.
            return (string) $reg->state_type_id === '11' ? 'anulado' : 'emitido';
        }

        return match ((string) $reg->state_type_id) {
            '01'    => 'registrado',   // creado, todavía no enviado
            '03'    => 'enviado',
            '05'    => 'aceptado',
            '07'    => 'observado',
            '09'    => 'rechazado',
            '11'    => 'anulado',
            '13'    => 'por_anular',
            default => 'emitido',
        };
    }

    private function estadoLabel(string $tipo, $reg): string
    {
        if (!$reg) {
            return 'Sin emitir';
        }

        if ($tipo === self::NOTA_VENTA) {
            return (string) $reg->state_type_id === '11' ? 'Anulada' : 'Emitida';
        }

        return self::ESTADO_CPE[(string) $reg->state_type_id] ?? (string) $reg->state_type_id;
    }

    /**
     * Por qué lo rechazó SUNAT.
     *
     * OJO: el motivo NO está en `soap_shipping_response` sino en
     * `response_regularize_shipping`. Leer el campo equivocado devuelve vacío y
     * la pantalla acaba diciendo «error desconocido» sobre un error que sí está
     * guardado.
     */
    private function motivoError(string $tipo, $reg): ?string
    {
        if (!$reg || $tipo === self::NOTA_VENTA) {
            return null;
        }

        if (!in_array((string) $reg->state_type_id, ['01', '07', '09'], true)) {
            return null;
        }

        $resp = $reg->response_regularize_shipping;

        // El accesor del modelo devuelve un **objeto** (json_decode sin true),
        // no un arreglo. Leerlo como arreglo daba siempre null y la pantalla
        // acababa diciendo «sin motivo» sobre un rechazo que sí estaba escrito.
        if (is_object($resp)) {
            $resp = (array) $resp;
        }
        if (is_string($resp)) {
            $resp = json_decode($resp, true);
        }

        if (is_array($resp)) {
            $texto = $resp['description'] ?? $resp['message'] ?? null;

            return is_string($texto) && $texto !== '' ? $texto : null;
        }

        return null;
    }

    /**
     * El PDF ya impreso. Tres rutas distintas porque son tres módulos:
     * la NV tiene la suya y los electrónicos van por el descargador genérico
     * `/print/{model}/...`, que resuelve la clase con `ucfirst()` — y por eso
     * NO sirve para `sale_note`, que daría `Sale_note`.
     */
    private function pdfUrl(string $tipo, $reg): ?string
    {
        if (!$reg->external_id) {
            return null;
        }

        return match ($tipo) {
            self::NOTA_VENTA => '/sale-notes/print/' . $reg->external_id . '/a4',
            self::BOLETA, self::FACTURA => '/print/document/' . $reg->external_id . '/a4',
            self::GUIA       => '/print/dispatch/' . $reg->external_id . '/a4',
            default          => null,
        };
    }

    // ══════════════════════════════════════════════════════════════════════
    // Datos del pedido
    // ══════════════════════════════════════════════════════════════════════

    /**
     * El envío VIGENTE si lo hay; si no, el último aunque esté anulado.
     *
     * Un envío anulado importa: puede tener una guía emitida que sigue viva
     * ante SUNAT, y ocultarla haría creer que no existe.
     */
    private function envio()
    {
        if ($this->order->relationLoaded('shipment')) {
            return $this->order->shipment;
        }

        return null;
    }

    private function lineas(): array
    {
        $items = $this->order->items;

        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (is_object($items)) {
            $items = json_decode(json_encode($items), true);
        }

        return is_array($items) ? $items : [];
    }

    /**
     * El resolutor del tipo de comprobante, instanciado una sola vez.
     *
     * Sabe qué corresponde emitir y con qué datos, incluida la corrección del
     * operador. Se comparte con la fila y con el endpoint de corrección para
     * que los tres digan lo mismo.
     */
    private function resolver(): BillingDocumentResolver
    {
        return $this->resolver ??= new BillingDocumentResolver();
    }

    /** Qué corresponde emitir para este pedido, y qué falta. */
    public function propuesta(): ?array
    {
        if (!$this->tieneContenidoFacturable()) {
            return null;
        }

        return $this->propuesta ??= $this->resolver()->resolve($this->order);
    }

    /** Correspondencia entre los tipos de este servicio y los códigos SUNAT. */
    private const CODIGO_SUNAT = [
        self::NOTA_VENTA => BillingDocumentResolver::NOTA_VENTA,
        self::BOLETA     => BillingDocumentResolver::BOLETA,
        self::FACTURA    => BillingDocumentResolver::FACTURA,
    ];

    private function esSugerido(string $tipo): bool
    {
        $propuesta = $this->propuesta();

        return $propuesta !== null
            && isset(self::CODIGO_SUNAT[$tipo])
            && self::CODIGO_SUNAT[$tipo] === $propuesta['tipo'];
    }
}
