<?php

namespace App\Listeners\Logistic;

use App\Events\Logistic\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Registra en log cada cambio de estado de orden logística.
 * Proporciona audit trail en log para operaciones de warehouse.
 */
class LogOrderStatusChanged implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        Log::channel('daily')->info('[Logistic] Estado de orden actualizado', [
            'order_id'    => $order->id,
            'tenant_uuid' => $event->tenantUuid,
            'status_new'  => $order->status->value,
            'status_label'=> $order->status->label(),
        ]);
    }

    public function failed(OrderStatusChanged $event, \Throwable $exception): void
    {
        Log::error('[Logistic] LogOrderStatusChanged listener falló', [
            'order_id' => $event->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}
