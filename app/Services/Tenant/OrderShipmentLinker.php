<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingAuditLog;
use App\Models\Tenant\ShippingRequest;

/**
 * Puente entre un Pedido y su detalle logístico (Registro de Envíos).
 *
 * Unificación Pedidos ↔ Envíos: `orders` es la entidad principal y
 * `shipping_requests` pasa a ser el detalle de entrega DE ESE pedido. Este
 * servicio es el único punto por donde un pedido crea o recupera su envío, para
 * que la regla "1 pedido = 1 registro logístico" no dependa de que cada
 * pantalla se acuerde de comprobarla.
 *
 * NO valida datos de entrada: eso sigue siendo responsabilidad de
 * ShipmentController::validateShipment(), que conoce las reglas por modalidad.
 * Aquí solo se resuelve la vinculación y el prellenado.
 */
class OrderShipmentLinker
{
    /**
     * Envío VIGENTE del pedido (ignora los anulados), o null.
     *
     * Se consulta en vez de usar la relación cargada para que el llamador no
     * dependa de haber hecho el eager loading correcto.
     */
    public function current(Order $order): ?ShippingRequest
    {
        return ShippingRequest::where('order_id', $order->id)
            ->whereNull('cancelled_at')
            ->latest('id')
            ->first();
    }

    /**
     * Devuelve el envío del pedido, creándolo con los datos del pedido si aún
     * no existe. Idempotente: llamarlo dos veces NO crea dos envíos.
     *
     * @param array $overrides Datos ya validados que pisan al prellenado.
     */
    public function ensure(Order $order, array $overrides = []): ShippingRequest
    {
        if ($existing = $this->current($order)) {
            if ($overrides) {
                $existing->fill($overrides)->save();
            }
            return $existing;
        }

        $data = array_merge($this->prefill($order), $overrides);

        // El vínculo con el pedido no es negociable: este servicio existe
        // justamente para que no nazcan envíos huérfanos desde el flujo normal.
        $data['order_id'] = $order->id;
        $data['status']   = $data['status'] ?? ShippingRequest::STATUS_RECIBIDO;

        $shipment = ShippingRequest::create($data);

        $this->finalizeCreation($shipment, 'Alta desde el pedido #' . $this->orderCode($order));

        return $shipment;
    }

    /**
     * Cierre del alta de un envío: código legible, prioridad por modalidad y
     * asiento en la bitácora.
     *
     * Vive aquí —y no en el controlador— porque ahora hay tres puertas de
     * entrada (panel, formulario público y pedido) y las tres tienen que dejar
     * el registro en el mismo estado.
     */
    public function finalizeCreation(ShippingRequest $shipment, ?string $note = null): void
    {
        if (!$shipment->shipment_code) {
            $shipment->shipment_code = ShippingRequest::buildCode(
                $shipment->id,
                optional($shipment->created_at)->format('Ymd')
            );
            $shipment->save();
        }

        $priority = ShippingRequest::priorityFor($shipment->delivery_type);
        if ((int) $shipment->priority !== $priority) {
            $shipment->forceFill(['priority' => $priority])->save();
        }

        ShippingAuditLog::log(
            ShippingAuditLog::ACTION_STATUS,
            $shipment->id,
            'status',
            null,
            $shipment->status,
            $note ?: ('Alta del envío · ' . $shipment->delivery_label . ' · ' . $shipment->priority_label)
        );
    }

    // ── Sincronización de estados envío → pedido ───────────────────────────
    //
    // El pedido y el envío conservan SU estado (uno comercial, otro operativo):
    // "Pedido: Enviado / Logística: En agencia" es una lectura válida y útil.
    // Lo que se sincroniza son los HITOS que el cliente reconocería como un
    // cambio en su pedido: salió, y llegó.

    /** Estados logísticos que significan "el paquete ya salió de la tienda". */
    private const IN_TRANSIT = [
        ShippingRequest::STATUS_EN_CAMINO,
        ShippingRequest::STATUS_DESPACHADO,
        ShippingRequest::STATUS_EN_AGENCIA,
        ShippingRequest::STATUS_EN_RUTA,
        'enviado', // legado = entregado a agencia
    ];

    /**
     * Refleja en el pedido el hito logístico que acaba de ocurrir.
     *
     * Reglas duras, para que la automatización nunca destruya trabajo manual:
     *   · Solo AVANZA. Nunca retrocede el estado del pedido.
     *   · No toca pedidos cancelados (5): la cancelación es una decisión
     *     comercial y un movimiento de almacén no puede revertirla.
     *   · No toca pedidos ya entregados (6).
     *   · Best-effort: es un efecto secundario, no puede tumbar la operación
     *     logística que ya se guardó.
     *
     * @return bool ¿Cambió algo en el pedido?
     */
    public function syncOrderFromShipment(ShippingRequest $shipment): bool
    {
        if (!$shipment->order_id) {
            return false;
        }

        $order = Order::find($shipment->order_id);
        if (!$order) {
            return false;
        }

        $current = (int) $order->status_order_id;

        // Estados terminales del pedido: fuera del alcance de la sincronización.
        if (in_array($current, [self::ORDER_CANCELLED, self::ORDER_DELIVERED], true)) {
            return false;
        }

        $changes = [];

        if ($shipment->status === ShippingRequest::STATUS_ENTREGADO) {
            $changes['status_order_id'] = self::ORDER_DELIVERED;
            $changes['delivered_at']    = $order->delivered_at
                ?? $shipment->picked_up_at ?? $shipment->updated_at ?? now();
            // Un pedido entregado pasó por "enviado" aunque nadie lo marcara.
            $changes['dispatched_at'] = $order->dispatched_at ?? $shipment->sent_at;
        } elseif (in_array($shipment->status, self::IN_TRANSIT, true)) {
            // Solo avanza: si el pedido ya está más adelante, no se retrocede.
            if ($current < self::ORDER_SHIPPED) {
                $changes['status_order_id'] = self::ORDER_SHIPPED;
            }
            $changes['dispatched_at'] = $order->dispatched_at ?? $shipment->sent_at ?? now();
        }

        // Descartar lo que ya estaba igual: evita updates y logs vacíos.
        $changes = array_filter(
            $changes,
            fn($value, $key) => $value !== null && $order->{$key} != $value,
            ARRAY_FILTER_USE_BOTH
        );

        if (!$changes) {
            return false;
        }

        $newStatus = (int) ($changes['status_order_id'] ?? $current);

        $order->forceFill($changes)->save();

        // El historial unificado tiene que poder explicar POR QUÉ se movió el
        // pedido si nadie lo tocó: la causa fue el envío.
        if ($newStatus !== $current) {
            $this->logOrderTransition($order, $current, $newStatus, $shipment);
        }

        return true;
    }

    /**
     * Asiento en el historial del pedido de un cambio disparado por logística.
     * Best-effort: perder la traza no puede tumbar la operación que ya se guardó.
     */
    private function logOrderTransition(Order $order, int $from, int $to, ShippingRequest $shipment): void
    {
        try {
            \App\Models\Tenant\OrderStatusLog::create([
                'order_id'       => $order->id,
                'from_status'    => $from,
                'to_status'      => $to,
                'payment_status' => $order->payment_status,
                'actor_id'       => auth()->id(),
                'payload'        => [
                    'source'          => 'shipment',
                    'shipment_id'     => $shipment->id,
                    'shipment_code'   => $shipment->shipment_code,
                    'shipment_status' => $shipment->status,
                ],
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo registrar la transición del pedido desde el envío', [
                'order_id'    => $order->id,
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /** IDs de `status_orders`. Fijos por compatibilidad — ver migración 2026_03_23_000004. */
    public const ORDER_SHIPPED   = 4;
    public const ORDER_CANCELLED = 5;
    public const ORDER_DELIVERED = 6;

    /**
     * Datos logísticos deducibles del pedido.
     *
     * Todo lo que el pedido ya sabe (quién compra, a dónde, cuánto se cobró de
     * envío, qué lleva la caja) no se le vuelve a preguntar al operador. Lo que
     * el pedido NO sabe —modalidad, agencia, ubigeo— queda vacío para que el
     * formulario lo pida: adivinarlo produciría rótulos mal dirigidos.
     *
     * @return array<string, mixed>
     */
    public function prefill(Order $order): array
    {
        $customer = $this->customerData($order);
        $document = $this->firstFilled([
            data_get($customer, 'numero_documento'),
            data_get($customer, 'document'),
            data_get($customer, 'number'),
        ]);
        $document = preg_replace('/\D+/', '', (string) $document);

        $address = $this->firstFilled([
            data_get($this->marketplaceShipping($order), 'address'),
            data_get($customer, 'direccion'),
            data_get($customer, 'address'),
            $order->shipping_address,
        ]);

        // El ERP guarda "Marketplace" como marcador cuando el canal no entrega
        // la dirección real. Copiarlo al rótulo sería mandar el paquete a una
        // dirección que no existe.
        if (mb_strtolower(trim((string) $address)) === 'marketplace') {
            $address = null;
        }

        return [
            'full_name'            => $this->customerName($order, $customer),
            'dni'                  => $document !== '' ? $document : null,
            'document_type'        => $document !== '' ? (strlen($document) === 11 ? 'ruc' : 'dni') : null,
            'phone'                => $this->firstFilled([
                data_get($customer, 'telefono'),
                data_get($customer, 'phone'),
                data_get($customer, 'telephone'),
            ]),
            'shipping_destination' => $address,
            'package_content'      => $this->packageContent($order),
            'package_count'        => 1,
            'delivery_price'       => $order->shipping_cost !== null ? (float) $order->shipping_cost : null,
            'notes'                => 'Pedido #' . $this->orderCode($order)
                . ($order->channel ? ' · ' . $order->channel->name : ''),
            'created_by'           => auth()->id(),
        ];
    }

    /**
     * Resumen del contenido del paquete a partir de los items del pedido.
     * Se corta a 255 caracteres porque es el ancho de la columna.
     */
    private function packageContent(Order $order): ?string
    {
        $items = is_array($order->items) ? $order->items : [];
        if (!$items) {
            return null;
        }

        $parts = [];
        foreach ($items as $item) {
            $name = data_get($item, 'description')
                ?? data_get($item, 'name')
                ?? data_get($item, 'item.description');
            if (!$name) {
                continue;
            }
            $qty = (float) (data_get($item, 'quantity') ?? 1);
            $parts[] = ($qty > 1 ? (rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . ' x ') : '') . $name;
        }

        if (!$parts) {
            return null;
        }

        return mb_substr(implode(' · ', $parts), 0, 255);
    }

    /** Nombre del comprador, con la misma prelación que usa OrderCollection. */
    private function customerName(Order $order, array $customer): string
    {
        return (string) ($this->firstFilled([
            data_get($this->marketplaceCustomer($order), 'name'),
            data_get($customer, 'apellidos_y_nombres_o_razon_social'),
            data_get($customer, 'name'),
        ]) ?? 'Cliente');
    }

    private function customerData(Order $order): array
    {
        $customer = $order->customer;
        if (is_object($customer)) {
            $customer = (array) $customer;
        }

        return is_array($customer) ? $customer : [];
    }

    private function marketplaceCustomer(Order $order): array
    {
        $data = optional($order->marketplaceOrder)->customer_data;

        return is_array($data) ? $data : [];
    }

    private function marketplaceShipping(Order $order): array
    {
        $data = optional($order->marketplaceOrder)->shipping_data;

        return is_array($data) ? $data : [];
    }

    /** Código del pedido tal como lo ve el operador (000125). */
    public function orderCode(Order $order): string
    {
        return str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    }

    /** Primer valor no vacío de la lista, o null. */
    private function firstFilled(array $candidates)
    {
        foreach ($candidates as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
