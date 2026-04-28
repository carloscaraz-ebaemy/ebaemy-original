<?php

namespace App\Listeners\Ecommerce;

use App\Events\Ecommerce\OrderCreated;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Tenant\Company;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification
{
    public function handle(OrderCreated $event): void
    {
        $order       = $event->order;
        $orderNumber = strtoupper(substr($order->external_id, 0, 8));
        $total       = (float) $order->total;
        $items       = is_array($order->items) ? $order->items : (array) $order->items;
        $storeName   = optional(Company::first())->trade_name ?? optional(Company::first())->name ?? 'Tienda';

        if ($event->customerPhone) {
            dispatch(SendWhatsAppMessage::customerOrder(
                $event->customerPhone,
                $event->customerName,
                $orderNumber,
                $total,
                $storeName
            ));
        }

        dispatch(SendWhatsAppMessage::vendorOrder(
            $event->customerName,
            $orderNumber,
            $total,
            $items
        ));
    }

    public function failed(OrderCreated $event, \Throwable $e): void
    {
        Log::error('SendWhatsAppNotification permanently failed', [
            'order_id' => $event->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
