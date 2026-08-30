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

        return $this->collection->transform(function($row, $key) use ($maxDays, $skipHolidays) {
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
                    $d = data_get($customer, 'numero_documento') ?? data_get($customer, 'document') ?? '';
                    return $d !== '' ? (string) $d : null;
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
                // ── Detalle logístico (Registro de Envíos) ────────────────
                // `shipment` es null cuando el pedido todavía no tiene envío
                // configurado: la tabla lo pinta como "Sin envío" y ofrece el
                // botón de configurarlo. NO es un error de datos.
                'shipment'             => $this->shipmentPayload($row, $maxDays, $skipHolidays),
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
    private function shipmentPayload($row, int $maxDays, bool $skipHolidays): ?array
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
            'tracking_number'  => $s->tracking_number,
            'has_guide'        => (bool) $s->shipping_guide_path,
            'batch_id'         => $s->print_batch_id,
            'batch_label'      => $s->batch_label,
            'printed_at'       => optional($s->printed_at)->format('Y-m-d H:i:s'),
            'sent_at'          => optional($s->sent_at)->format('Y-m-d H:i:s'),
            'picked_up_at'     => optional($s->picked_up_at)->format('Y-m-d H:i:s'),
            'priority'         => (int) $s->priority,
            'priority_label'   => $s->priority_label,
            'is_pickup'        => $s->is_pickup,
            'payment_confirmed'=> (bool) $s->payment_confirmed,
            // Semáforo de antigüedad: level null = el reloj ya se detuvo.
            'aging_days'       => $aging['days'],
            'aging_level'      => $aging['level'],
            'aging_meta'       => $aging['level'] !== null
                ? \App\Models\Tenant\ShippingRequest::AGING_META[$aging['level']]
                : null,
        ];
    }
}

