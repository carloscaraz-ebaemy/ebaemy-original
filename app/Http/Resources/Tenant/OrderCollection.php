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

        return $this->collection->transform(function($row, $key) {
            $customer = $row->customer ?? [];
            if (is_object($customer)) {
                $customer = (array) $customer;
            }

            $customerName = data_get($customer, 'apellidos_y_nombres_o_razon_social')
                ?? data_get($customer, 'name')
                ?? 'Invitado';
            $customerEmail = data_get($customer, 'correo_electronico')
                ?? data_get($customer, 'email')
                ?? '';
            $customerPhone = data_get($customer, 'telefono')
                ?? data_get($customer, 'phone')
                ?? data_get($customer, 'telephone')
                ?? '';
            $customerAddress = data_get($customer, 'direccion')
                ?? data_get($customer, 'address')
                ?? ($row->shipping_address ?? '');

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
                'mp_order_id'          => optional($row->marketplaceOrder)->id,
                // N° del pedido en el canal: es el que el operador reconoce,
                // el id interno no le dice nada al confirmar la emision.
                'mp_external_order_id' => optional($row->marketplaceOrder)->external_order_id,
                'mp_channel_id'        => optional($row->marketplaceOrder)->channel_id,
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
            ];
        });

    }
}

