<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogisticOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'delivery_type'        => $this->delivery_type->value,
            'delivery_type_label'  => $this->delivery_type->label(),
            'status'               => $this->status->value,
            'status_label'         => $this->status->label(),
            'badge_color'          => $this->status->badgeColor(),
            'source'               => $this->source,

            // Cliente
            'customer'             => $this->whenLoaded('customer', fn() => [
                'id'     => $this->customer->id,
                'name'   => $this->customer->name,
                'number' => $this->customer->number,
            ]),

            // Destino
            'destination_district' => $this->destination_district,
            'destination_address'  => $this->destination_address,
            'recipient_name'       => $this->recipient_name,
            'recipient_phone'      => $this->recipient_phone,

            // Almacén
            'warehouse'            => $this->whenLoaded('warehouse', fn() => [
                'id'          => $this->warehouse->id,
                'description' => $this->warehouse->description,
            ]),

            // Montos
            'subtotal'             => $this->subtotal,
            'igv'                  => $this->igv,
            'total'                => $this->total,
            'currency_type_id'     => $this->currency_type_id,

            // Ítems
            'items'                => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'item_id'     => $item->item_id,
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'unit_type'   => $item->unit_type_id,
                    'unit_price'  => $item->unit_price,
                    'total'       => $item->total,
                ])
            ),

            // Comprobante vinculado
            'document'             => $this->whenLoaded('document', fn() => $this->document ? [
                'id'              => $this->document->id,
                'document_type'   => $this->document->document_type_id,
                'series'          => $this->document->series,
                'number'          => $this->document->number,
                'total'           => $this->document->total,
            ] : null),

            // Guía de remisión
            'shipping_guide'       => $this->whenLoaded('shippingGuide', fn() => $this->shippingGuide ? [
                'id'             => $this->shippingGuide->id,
                'full_number'    => $this->shippingGuide->full_number,
                'carrier_name'   => $this->shippingGuide->carrier_name,
                'tracking_code'  => $this->shippingGuide->tracking_code,
                'dispatch_date'  => $this->shippingGuide->dispatch_date?->format('d/m/Y'),
                'pdf_url'        => $this->shippingGuide->pdf_url,
            ] : null),

            // Timestamps de estados
            'confirmed_at'         => $this->confirmed_at?->format('d/m/Y H:i'),
            'preparation_at'       => $this->preparation_at?->format('d/m/Y H:i'),
            'dispatched_at'        => $this->dispatched_at?->format('d/m/Y H:i'),
            'delivered_at'         => $this->delivered_at?->format('d/m/Y H:i'),
            'cancelled_at'         => $this->cancelled_at?->format('d/m/Y H:i'),
            'cancel_reason'        => $this->cancel_reason,

            'notes'                => $this->notes,
            'created_at'           => $this->created_at->format('d/m/Y H:i'),
        ];
    }
}
