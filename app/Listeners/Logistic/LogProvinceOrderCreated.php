<?php

namespace App\Listeners\Logistic;

use App\Events\Logistic\ProvinceOrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Registra en log cuando llega un nuevo pedido de provincia.
 * Listener liviano: solo logging. Sin riesgo de romper el flujo principal.
 *
 * Implementa ShouldQueue para no bloquear el request del usuario.
 */
class LogProvinceOrderCreated implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ProvinceOrderCreated $event): void
    {
        $order = $event->order;

        Log::channel('daily')->info('[Logistic] Nueva orden provincia recibida', [
            'order_id'     => $order->id,
            'tenant_uuid'  => $event->tenantUuid,
            'customer_id'  => $order->customer_id,
            'source'       => $order->source,
            'delivery'     => $order->delivery_type->value,
            'total'        => $order->total,
            'items_count'  => $order->items->count(),
            'district'     => $order->destination_district,
        ]);
    }

    /**
     * Si el listener falla, no debe interrumpir el flujo del pedido.
     */
    public function failed(ProvinceOrderCreated $event, \Throwable $exception): void
    {
        Log::error('[Logistic] LogProvinceOrderCreated listener falló', [
            'order_id' => $event->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}
