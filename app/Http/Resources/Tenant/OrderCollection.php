<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        // Parámetros del semáforo de antigüedad. Se leen UNA vez para toda la
        // página: son iguales para todas las filas y consultarlos por fila
        // convertía el listado en una tormenta de queries.
        // `currentOrNull` y no `current`: en un tenant sin el módulo de Envíos
        // esto es un listado de pedidos normal, no un error.
        $shippingSetting = \App\Models\Tenant\ShippingSetting::currentOrNull();
        $maxDays         = $shippingSetting ? $shippingSetting->max_days : 4;
        $skipHolidays    = (bool) ($shippingSetting->aging_skip_holidays ?? true);

        // Si la tienda exige cobrar antes de rotular. Se lee una vez para toda
        // la pagina, igual que el resto de la configuracion.
        $requirePayment  = (bool) ($shippingSetting->require_payment ?? false);

        return $this->collection->transform(function($row, $key) use ($maxDays, $skipHolidays, $requirePayment) {
            $customer = $row->customer ?? [];
            if (is_object($customer)) {
                $customer = (array) $customer;
            }

            // En pedidos de Saga, customer_data y shipping_data provienen del
            // marketplace y contienen mejor informacion que los placeholders
            // del pedido ERP creado para trazabilidad.
            $marketplaceCustomer = is_array(optional($row->marketplaceOrder)->customer_data)
                ? $row->marketplaceOrder->customer_data : [];
            $marketplaceShipping = is_array(optional($row->marketplaceOrder)->shipping_data)
                ? $row->marketplaceOrder->shipping_data : [];

            $customerName = data_get($marketplaceCustomer, 'name')
                ?? data_get($customer, 'apellidos_y_nombres_o_razon_social')
                ?? data_get($customer, 'name')
                ?? 'Invitado';
            $customerEmail = data_get($marketplaceCustomer, 'email')
                ?? data_get($customer, 'correo_electronico')
                ?? data_get($customer, 'email')
                ?? '';
            $customerPhone = data_get($marketplaceCustomer, 'phone')
                ?? data_get($customer, 'telefono')
                ?? data_get($customer, 'phone')
                ?? data_get($customer, 'telephone')
                ?? '';
            $customerAddress = data_get($marketplaceShipping, 'address')
                ?? data_get($marketplaceCustomer, 'billing.address')
                ?? data_get($customer, 'direccion')
                ?? data_get($customer, 'address')
                ?? ($row->shipping_address ?? '');

            // No presentar el placeholder interno como si fuera una direccion
            // real. Saga no siempre entrega el telefono/direccion por API.
            if (mb_strtolower(trim((string) $customerAddress)) === 'marketplace') {
                $customerAddress = '';
            }

            $items    = is_array($row->items) ? $row->items : (array)($row->items ?? []);

            // `$row` es un OrderResource, no el modelo: se desenvuelve porque el
            // servicio se tipa contra `Order` a propósito — aceptar «lo que sea
            // que responda a ->total» es como se cuelan los errores que solo
            // aparecen en producción. Se construye UNA vez: los chips y la
            // propuesta de facturación comparten la misma resolución.
            $docs = \App\Services\Tenant\OrderDocuments::for($row->resource);

            // Una sola vez por fila. Antes se memorizaba en una `static` del
            // metodo, y una static NO se limpia al cambiar de tenant: en un
            // comando que recorre varios, el pedido 27 del segundo heredaba el
            // estado del pedido 27 del primero. Lo cazo el barrido comparando
            // los recuentos del SQL con los del payload.
            $estadoPago = $this->paymentState($row->resource);

            return [
                'id'                   => $row->id,
                'external_id'          => $row->external_id,
                'number_document'      => $row->number_document,
                // Estado de la boleta para pedidos de marketplace (Saga):
                //   ebaemy   = boleta emitida desde EBAEMY (muestra number_document)
                //   external = ya facturado fuera de EBAEMY (marca/carga)
                //   pending  = pedido de marketplace SIN boleta aún
                //   null     = pedido normal del ecommerce (no marketplace)
                'mp_invoice_state'     => (function () use ($row) {
                    $mp = $row->marketplaceOrder;
                    if (!$mp) {
                        return null;
                    }
                    // Facturado y despues devuelto/cancelado: es la excepcion
                    // que hay que ver el mismo dia, no cuadrando a fin de mes.
                    // Requiere Nota de Credito.
                    if (($row->number_document || $mp->document_id)
                        && in_array($mp->status, ['returned', 'canceled'], true)) {
                        return 'alert';
                    }
                    if ($row->number_document || $mp->document_id) {
                        return 'ebaemy';
                    }
                    if ($mp->invoice_uploaded_at) {
                        return 'external';
                    }
                    return 'pending';
                })(),
                // Datos del pedido de marketplace para descargar el rótulo (Saga).
                // Se expone si la boleta YA se cargo en Saga: es lo que
                // decide si falta el paso de subirla.
                'mp_invoice_uploaded'  => (bool) optional($row->marketplaceOrder)->invoice_uploaded_at,
                'mp_order_id'          => optional($row->marketplaceOrder)->id,
                // N° del pedido en el canal: es el que el operador reconoce,
                // el id interno no le dice nada al confirmar la emision.
                'mp_external_order_id' => optional($row->marketplaceOrder)->external_order_id,
                'mp_channel_id'        => optional($row->marketplaceOrder)->channel_id,
                'mp_platform'          => optional(optional($row->marketplaceOrder)->channel)->platform,
                'mp_status'            => optional($row->marketplaceOrder)->status,
                'order_id'             => str_pad($row->id, 6, "0", STR_PAD_LEFT),
                'customer'             => $customerName,
                // Documento del comprador: es a quien se le va a emitir la
                // boleta. Sin verlo, el operador no puede detectar que un
                // pedido saldria como "Cliente Final 00000000".
                'customer_doc'         => (function () use ($row, $customer) {
                    $mp = $row->marketplaceOrder;
                    if ($mp) {
                        $cd = is_array($mp->customer_data) ? $mp->customer_data
                            : (json_decode((string) $mp->customer_data, true) ?: []);
                        $d = preg_replace('/\D+/', '', (string) ($cd['document'] ?? ''));
                        if ($d !== '') {
                            $t = strlen($d) === 11 ? 'RUC' : (strlen($d) === 8 ? 'DNI' : 'C.E.');
                            return $t . ' ' . $d;
                        }
                        return null;   // el panel lo pinta como "sin documento"
                    }
                    // OJO con las claves: el JSON de `customer` NO usa la misma
                    // en todos los origenes. El checkout y el espejo del encargo
                    // logistico escriben `numero` (ver OrderShipmentLinker), y
                    // esta funcion solo miraba `numero_documento` y `document`.
                    // Resultado: el documento estaba guardado y la fila lo
                    // pintaba vacio — en alasitas, en los 298 pedidos.
                    // Es el mismo juego de claves que resuelve
                    // `BillingDocumentResolver::documento()`.
                    $d = data_get($customer, 'numero')
                      ?? data_get($customer, 'number')
                      ?? data_get($customer, 'numero_documento')
                      ?? data_get($customer, 'document')
                      ?? '';
                    $d = preg_replace('/\D+/', '', (string) $d);

                    if ($d === '') {
                        return null;
                    }

                    // Con el tipo delante se lee de un vistazo, igual que en la
                    // rama de marketplace.
                    $t = strlen($d) === 11 ? 'RUC' : (strlen($d) === 8 ? 'DNI' : 'C.E.');

                    return $t . ' ' . $d;
                })(),
                'customer_email'       => $customerEmail,
                'customer_telefono'    => $customerPhone,
                'customer_direccion'   => $customerAddress,
                'items'                => $row->items,
                'item_count'           => count($items),
                'total'                => $row->total,
                'reference_payment'    => strtoupper($row->reference_payment ?? ''),
                'document_external_id' => $row->document_external_id,
                'created_at'           => $row->created_at->format('Y-m-d H:i:s'),
                'status_order_id'      => $row->status_order_id,
                'status_description'   => optional($row->status_order)->description ?? '',
                'purchase'             => $row->purchase,
                'document_type_id'     => optional($row->purchase)->codigo_tipo_documento,
                'has_sale_note'        => !is_null($row->sale_note),
                'sale_note_number_full'=> optional($row->sale_note)->number_full,
                'sale_note_id'         => optional($row->sale_note)->id,
                'sale_note_external_id'=> optional($row->sale_note)->external_id,
                'points_earned'        => (float) $row->points_earned,
                'points_redeemed'      => (float) $row->points_redeemed,
                // Canal de venta
                'channel_id'           => $row->channel_id,
                'channel_name'         => optional($row->channel)->name ?? null,
                'channel_type'         => optional($row->channel)->type ?? null,
                'channel_code'         => optional($row->channel)->code ?? null,
                // Almacén asignado
                'warehouse_id'         => $row->warehouse_id,
                'warehouse_description'=> optional($row->warehouse)->description ?? null,
                // Fechas de negocio (nullable en pedidos históricos).
                'paid_at'              => optional($row->paid_at)->format('Y-m-d H:i:s'),
                'prepared_at'          => optional($row->prepared_at)->format('Y-m-d H:i:s'),
                'dispatched_at'        => optional($row->dispatched_at)->format('Y-m-d H:i:s'),
                'delivered_at'         => optional($row->delivered_at)->format('Y-m-d H:i:s'),
                'payment_status'       => $row->payment_status,
                // ── Cobro del PEDIDO (`order_payments`) ───────────────────
                // Distinto del cobro del ENCARGO, que vive en el envio (ver
                // `shipment.paid_total`). Antes la tabla solo mostraba el
                // total: se podia cobrar 150 de 160 y la pantalla seguia
                // diciendo 160 a secas, sin rastro del saldo.
                //
                // `paid_total` lo agrega la consulta (withSum), no un accesor.
                // Llega null cuando el pedido no tiene ningun pago Y tambien
                // cuando el tenant no tiene la tabla; en ambos casos vale 0.
                'paid_total'           => round((float) ($row->paid_total ?? 0), 2),
                // El saldo NO se expone cuando no hay ningun cobro registrado.
                // Un pedido sin pagos no significa "debe todo": los 630 de Saga
                // se cobran fuera de EBAEMY y marcarlos como deudores seria
                // mentir en la pantalla mas mirada del panel.
                'pending_total'        => $this->orderPending($row),
                // ── Estado economico del cobro ────────────────────────────
                // Cuanto dinero entro respecto al total. Se DERIVA, no se
                // declara: hasta ahora el unico estado del pago era la
                // etiqueta «Pago verificado» del catalogo comercial, que se
                // pone a mano y no mira el dinero. Con 1.045 pedidos y UN
                // pago registrado, esa etiqueta no informaba de nada.
                //
                // Se resuelve en PHP y no en Vue por la misma razon que el
                // resto: la regla —y sobre todo la de donde sale el dinero de
                // un encargo— no puede vivir en dos idiomas.
                'payment_state'        => $estadoPago['state'],
                'payment_state_label'  => $estadoPago['label'],
                // ── Detalle logístico (Registro de Envíos) ────────────────
                // `shipment` es null cuando el pedido todavía no tiene envío
                // configurado: la tabla lo pinta como "Sin envío" y ofrece el
                // botón de configurarlo. NO es un error de datos.
                'shipment'             => $this->shipmentPayload($row, $maxDays, $skipHolidays, $requirePayment),
                // Un envio ANULADO se sigue dejando fuera de `shipment`, a
                // proposito: no es la entrega vigente y no debe entrar en los
                // chips ni en la columna de logistica. Pero la fila decia
                // «Configurar envio», como si nunca hubiera existido — y eso no
                // es neutral, es falso. Se expone aparte para poder decir la
                // verdad y ofrecer restaurarlo.
                'shipment_cancelled'   => $this->cancelledShipmentPayload($row),
                // Nota de venta, boleta, factura y guía de remisión: qué hay,
                // qué corresponde y por qué no se puede emitir lo que falta.
                // Todo resuelto en `OrderDocuments` y no en Vue, por lo mismo
                // que el bloque logístico: las reglas de SUNAT no pueden vivir
                // en dos idiomas. Un tipo que no aplica a este pedido viene
                // como `null` (el espejo de un encargo no factura nada).
                'documents'            => $docs->toArray(),
                // Con qué corresponde facturar y qué falta para poder hacerlo.
                // Null cuando no hay venta que documentar (el espejo de un
                // encargo). El operador puede corregirlo: hasta ahora lo decidía
                // el comprador en el checkout y nadie más podía tocarlo.
                'billing'              => $docs->propuesta(),
            ];
        });

    }

    /**
     * Resumen logístico del pedido para la tabla unificada.
     *
     * Se envía RESUELTO desde PHP (etiquetas, colores, semáforo) para cumplir la
     * regla de no duplicar la lógica de negocio en Vue: los días hábiles y los
     * feriados se calculan en un solo sitio, `ShippingRequest`.
     *
     * @return array<string, mixed>|null
     */
    /**
     * Por que NO se puede imprimir ahora mismo, o null si se puede.
     *
     * Refleja las guardas de `ShipmentController::printLabel()`, que es quien
     * manda. Se queda solo con las que no tienen salida: un anulado no se
     * rotula nunca, y sin cobrar tampoco cuando la tienda lo exige. Un envio ya
     * despachado SI se puede reimprimir indicando el motivo — eso no es un
     * bloqueo sino un paso mas, y lo cubre `needs_reason`.
     */
    /**
     * El envio anulado del pedido, si lo hay y no ha sido reemplazado.
     *
     * Solo se informa cuando NO existe un envio vigente: si el operador ya
     * configuro uno nuevo, el anulado es historia y no tiene que asomar en la
     * fila.
     *
     * @return array{id: int, code: ?string}|null
     */
    private function cancelledShipmentPayload($row): ?array
    {
        if (!$row->relationLoaded('shipment')) {
            return null;
        }

        $s = $row->shipment;

        if (!$s || !$s->cancelled_at) {
            return null;
        }

        return ['id' => $s->id, 'code' => $s->shipment_code];
    }

    /**
     * Saldo del pedido, o null si no hay nada que afirmar.
     *
     * Solo devuelve numero cuando existe al menos un cobro registrado: es la
     * unica situacion en la que el sistema sabe de verdad cuanto falta.
     */
    private function orderPending($row): ?float
    {
        $paid = (float) ($row->paid_total ?? 0);
        if ($paid <= 0) {
            return null;
        }

        return round(max(0, (float) $row->total - $paid), 2);
    }

    /**
     * Estado economico del cobro: cuanto entro frente a cuanto hay que cobrar.
     *
     * Cuatro respuestas y no tres, porque hay un caso que no es ninguna de las
     * del encargo: un envio al que nadie le cargo el importe. Decir
     * «pendiente» de algo cuyo precio no se conoce seria inventarse una deuda.
     *
     * ── De donde sale el dinero ───────────────────────────────────────────
     *
     * De los dos sitios, sumados. Un pedido normal cobra en `order_payments`;
     * el espejo de un encargo logistico no tiene importe propio —nace con
     * total 0— y su dinero vive en `shipping_payments`. Sumar los dos cubre
     * los dos casos sin preguntar de que tipo es el pedido, y tambien el
     * hibrido si algun dia existe.
     *
     * La MISMA regla, en SQL, es la que filtra el listado
     * (`OrderController::applyPaymentStateFilter`). Si divergen, el operador
     * filtra por «parcial» y le salen pedidos que la fila pinta «pagado».
     */
    /**
     * Estado economico del cobro: cuanto entro frente a cuanto hay que cobrar.
     *
     * Cuatro respuestas y no tres, porque hay un caso que no es ninguna de las
     * otras: un encargo al que nadie le cargo el importe. Decir «pendiente» de
     * algo cuyo precio no se conoce seria inventarse una deuda.
     *
     * ── De donde sale el dinero ───────────────────────────────────────────
     *
     * De los DOS sitios, sumados, siempre. Un pedido normal cobra en
     * `order_payments`; el espejo de un encargo logistico no tiene importe
     * propio —nace con total 0— y su dinero vive en `shipping_payments`. Pero
     * existe tambien el caso mixto, y es real: en importacionesdeywa los 30
     * pedidos tienen total propio Y su envio tiene cobros registrados. Mirar
     * solo uno de los dos origenes daba 30 pedidos «pendientes» que en verdad
     * estaban cobrados.
     *
     * La MISMA regla, en SQL, es la que filtra el listado
     * (`OrderController::applyPaymentStateFilter`). Si divergen, el operador
     * filtra por «parcial» y le salen pedidos que la fila pinta «pagado», sin
     * forma de saber cual de las dos miente.
     */
    private function paymentState($row): array
    {
        // ── Marketplace: el dinero no pasa por la tienda ──────────────────
        //
        // En Saga el cliente le paga AL CANAL. Nunca va a existir un
        // `order_payment`, asi que la regla normal daria «pago pendiente» para
        // siempre: los 710 pedidos de carolayimport, todos, pidiendo un cobro
        // que nadie va a registrar.
        //
        // OJO con la premisa: `marketplace_orders` NO trae estado de pago —
        // tiene 19 columnas y ninguna lo es—. Lo que hay es el estado del
        // pedido en el canal. Asi que esto es una DECISION DE CONFIANZA
        // declarada aqui, no un dato que llegue: se asume que el canal no
        // despacha lo que no cobro. Si algun dia Saga expone el estado de pago,
        // este es el sitio donde debe leerse en vez de suponerse.
        $mp = $row->relationLoaded('marketplaceOrder') ? $row->marketplaceOrder : null;

        if ($mp) {
            return in_array($mp->status, ['canceled', 'returned'], true)
                ? ['state' => 'canal_anulado', 'label' => 'Sin cobro del canal']
                : ['state' => 'canal',         'label' => 'Cobrado por el canal'];
        }

        // El envio VIGENTE, y no `shipment`, que es el ultimo aunque este
        // anulado. Un envio anulado no cuenta —su importe ya no hay que
        // cobrarlo— pero si el pedido tuvo otro antes que sigue en pie, ESE es
        // el que manda. Es exactamente lo que hace el SQL del filtro; con
        // `shipment` a secas las dos reglas divergian en un pedido de alasitas.
        $envio = $row->relationLoaded('activeShipment') ? $row->activeShipment : null;

        // A cobrar: el total del pedido; si vale 0, el importe del encargo.
        $aCobrar = (float) $row->total > 0
            ? (float) $row->total
            : ($envio && $envio->has_amount ? (float) $envio->amount_to_collect : 0.0);

        $cobrado = (float) ($row->paid_total ?? 0)
                 + ($envio ? (float) $envio->paid_total : 0.0);

        return match (true) {
            $aCobrar <= 0               => ['state' => 'sin_monto', 'label' => 'Sin monto'],
            $cobrado <= 0               => ['state' => 'pendiente', 'label' => 'Pago pendiente'],
            // Un centimo de margen: sin el, un redondeo deja como «parcial» un
            // pedido cobrado al completo.
            $cobrado + 0.009 < $aCobrar => ['state' => 'parcial',   'label' => 'Pago parcial'],
            default                     => ['state' => 'pagado',    'label' => 'Pagado'],
        };
    }

    private function printBlockReason($s, bool $requirePayment): ?string
    {
        if ($s->status === \App\Models\Tenant\ShippingRequest::STATUS_ANULADO) {
            return 'El envio esta anulado.';
        }

        if ($requirePayment && !$s->payment_confirmed) {
            return 'Falta confirmar el pago del envio.';
        }

        return null;
    }

    private function shipmentPayload($row, int $maxDays, bool $skipHolidays, bool $requirePayment = false): ?array
    {
        // Solo si la relación vino EAGER LOADED. En un tenant sin el módulo de
        // Envíos no se precarga, y tocarla aquí dispararía una consulta contra
        // una tabla que no existe — una fila por pedido.
        if (!$row->relationLoaded('shipment')) {
            return null;
        }

        $s = $row->shipment;

        // Un envío anulado no representa la entrega vigente del pedido: para el
        // listado el pedido vuelve a estar "sin envío configurado".
        if (!$s || $s->cancelled_at) {
            return null;
        }

        $aging = $s->aging($maxDays, $skipHolidays);

        return [
            'id'               => $s->id,
            'code'             => $s->shipment_code,
            'delivery_type'    => $s->delivery_type,
            'delivery_short'   => $s->delivery_short,
            'delivery_label'   => $s->delivery_label,
            'delivery_meta'    => $s->delivery_meta,
            'status'           => $s->status,
            'status_label'     => \App\Models\Tenant\ShippingRequest::STATUSES[$s->status] ?? $s->status,
            // Destino resumido: la agencia manda en provincia, la dirección en
            // Lima. Es lo que el operador necesita leer de un vistazo.
            'destination'      => $s->shipping_agency ?: ($s->destination_city ?: $s->shipping_destination),
            // La ciudad, aparte de `destination`. Con agencia, «Shalom» a secas
            // no dice a donde va el paquete; la fila necesita las dos.
            // El detalle del paquete, ya partido en lineas.
            //
            // El espejo de un encargo nace con `items` vacio —el envio guarda su
            // contenido como TEXTO en `package_content`— y la columna Productos
            // leia solo `items`: 151 pedidos decian «0 productos» teniendo el
            // detalle cargado en su envio.
            //
            // Se DERIVA, no se copia: `contentLines()` es la misma funcion que
            // parte el texto para el rotulo y para la guia, asi que editar el
            // detalle en Envios se ve en Pedidos sin nada que sincronizar.
            'content_lines'    => $s->contentLines(),
            'destination_city' => $s->destination_city,
            'agency'           => $s->shipping_agency,
            'tracking_number'  => $s->tracking_number,
            'has_guide'        => (bool) $s->shipping_guide_path,
            // URL de la guia que dio la agencia. La servia solo el panel de
            // Envios, asi que desde Pedidos habia que salir de la pantalla
            // para ver un PDF que ya estaba cargado.
            'guide_url'        => $s->shipping_guide_path
                ? url('registro-envio/' . $s->id . '/guia')
                : null,
            'batch_id'         => $s->print_batch_id,
            'batch_label'      => $s->batch_label,
            'printed_at'       => optional($s->printed_at)->format('Y-m-d H:i:s'),
            // Rotulado. Pedidos NO reimplementa la impresion: llama al
            // `printLabel` del envio, que es donde viven el conteo, la
            // reimpresion con motivo, el bloqueo por estado, los formatos y la
            // bitacora. Aqui se expone lo justo para no ofrecer una accion
            // condenada a fallar; el servidor lo comprueba todo otra vez.
            'print_count'      => (int) $s->print_count,
            // Reimprimir exige motivo. Sin avisar antes, el operador se comia
            // el error DESPUES de abrir la pestaña.
            'needs_reason'     => (int) $s->print_count > 0,
            // Recojo en tienda no lleva rotulo: printLabel redirige al
            // comprobante de entrega, asi que la accion cambia de nombre.
            'label_kind'       => $s->is_pickup ? 'receipt' : 'label',
            'print_block'      => $this->printBlockReason($s, $requirePayment),
            // Que le falta al envio para poder rotularlo. El servidor imprime
            // igual —no es un error, es un rotulo malo— asi que avisar aqui es
            // la unica forma de que el operador se entere ANTES y no cuando el
            // paquete ya esta en la agencia sin saber a que oficina va.
            'missing_data'     => $s->missingLabelData(),
            'sent_at'          => optional($s->sent_at)->format('Y-m-d H:i:s'),
            'picked_up_at'     => optional($s->picked_up_at)->format('Y-m-d H:i:s'),
            'priority'         => (int) $s->priority,
            'priority_label'   => $s->priority_label,
            'is_pickup'        => $s->is_pickup,
            'payment_confirmed'=> (bool) $s->payment_confirmed,
            // El dinero del encargo vive en el ENVIO (`amount_due` +
            // `shipping_payments`), no en el pedido: el pedido espejo se creo
            // con total 0 y la pantalla mostraba S/ 0 mientras habia cobros
            // reales del otro lado. Se expone DERIVADO y no copiado: el
            // operador puede cargar el monto despues del alta, y una copia en
            // `orders.total` se quedaria vieja sin que nadie lo note.
            'has_amount'       => $s->has_amount,
            'amount_to_collect'=> $s->amount_to_collect,
            'paid_total'       => (float) $s->paid_total,
            'pending_total'    => $s->pending_total,
            'is_fully_paid'    => $s->is_fully_paid,
            // Semáforo de antigüedad: level null = el reloj ya se detuvo.
            'aging_days'       => $aging['days'],
            'aging_level'      => $aging['level'],
            'aging_meta'       => $aging['level'] !== null
                ? \App\Models\Tenant\ShippingRequest::AGING_META[$aging['level']]
                : null,
        ];
    }
}

