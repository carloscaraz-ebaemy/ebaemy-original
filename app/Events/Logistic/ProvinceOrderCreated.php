<?php

namespace App\Events\Logistic;

use App\Models\Tenant\LogisticOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que notifica al almacenero en tiempo real cuando llega
 * un nuevo pedido de provincia a su cola.
 *
 * Canal: private-warehouse.{tenant_uuid}
 * El almacenero se suscribe filtrado por su tenant → aislamiento total.
 *
 * Frontend Vue 3 (Echo):
 *   Echo.private(`warehouse.${tenantUuid}`)
 *     .listen('ProvinceOrderCreated', (e) => {
 *       playAlert();
 *       addOrderToQueue(e.order);
 *     });
 */
class ProvinceOrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LogisticOrder $order,
        public readonly string $tenantUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("warehouse.{$this->tenantUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProvinceOrderCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id'                  => $this->order->id,
                'status'              => $this->order->status->value,
                'status_label'        => $this->order->status->label(),
                'delivery_type'       => $this->order->delivery_type->value,
                'destination_address' => $this->order->destination_address,
                'destination_district'=> $this->order->destination_district,
                'recipient_name'      => $this->order->recipient_name,
                'recipient_phone'     => $this->order->recipient_phone,
                'total'               => $this->order->total,
                'currency_type_id'    => $this->order->currency_type_id,
                'source'              => $this->order->source,
                'confirmed_at'        => $this->order->confirmed_at?->toISOString(),
                'items_count'         => $this->order->items->count(),
                'items'               => $this->order->items->map(fn($i) => [
                    'description' => $i->description,
                    'quantity'    => $i->quantity,
                    'unit_type'   => $i->unit_type_id,
                ]),
            ],
        ];
    }
}
