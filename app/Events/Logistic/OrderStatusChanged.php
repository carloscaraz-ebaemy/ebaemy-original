<?php

namespace App\Events\Logistic;

use App\Enums\OrderStatusEnum;
use App\Models\Tenant\LogisticOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento genérico para cualquier cambio de estado de un pedido logístico.
 * Útil para actualizar dashboards en tiempo real.
 */
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LogisticOrder  $order,
        public readonly OrderStatusEnum $previousStatus,
        public readonly string         $tenantUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("warehouse.{$this->tenantUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'        => $this->order->id,
            'previous_status' => $this->previousStatus->value,
            'new_status'      => $this->order->status->value,
            'new_status_label'=> $this->order->status->label(),
            'badge_color'     => $this->order->status->badgeColor(),
            'updated_at'      => now()->toISOString(),
        ];
    }
}
