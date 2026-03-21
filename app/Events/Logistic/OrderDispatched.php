<?php

namespace App\Events\Logistic;

use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\LogisticShippingGuide;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al cliente (y admin) cuando un pedido fue despachado con guía de remisión.
 * Canal admin: private-warehouse.{tenant_uuid}
 * Canal cliente: private-customer.{customer_id} (si tiene cuenta en el sistema)
 */
class OrderDispatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LogisticOrder       $order,
        public readonly LogisticShippingGuide $guide,
        public readonly string              $tenantUuid
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("warehouse.{$this->tenantUuid}"),
        ];

        if ($this->order->customer_id) {
            $channels[] = new PrivateChannel("customer.{$this->order->customer_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'OrderDispatched';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'       => $this->order->id,
            'status'         => $this->order->status->value,
            'status_label'   => $this->order->status->label(),
            'dispatched_at'  => $this->order->dispatched_at?->toISOString(),
            'guide' => [
                'full_number'    => $this->guide->full_number,
                'carrier_name'   => $this->guide->carrier_name,
                'tracking_code'  => $this->guide->tracking_code,
                'dispatch_date'  => $this->guide->dispatch_date?->format('d/m/Y'),
                'pdf_url'        => $this->guide->pdf_url,
            ],
        ];
    }
}
