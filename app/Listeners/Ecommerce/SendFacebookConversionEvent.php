<?php

namespace App\Listeners\Ecommerce;

use App\Events\Ecommerce\OrderCreated;
use App\Services\Tenant\FacebookConversionsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFacebookConversionEvent implements ShouldQueue
{
    public $queue = 'default';
    public $tries = 2;

    public function handle(OrderCreated $event): void
    {
        $capi = FacebookConversionsApiService::fromConfig();

        if (!$capi) {
            return;
        }

        $order = $event->order;
        $items = $order->items ?? [];

        $contentIds = [];
        $contents   = [];
        $numItems   = 0;

        foreach ($items as $item) {
            $id  = (string) ($item['id'] ?? $item['item_id'] ?? '');
            $qty = (int) ($item['quantity'] ?? 1);

            $contentIds[] = $id;
            $contents[]   = [
                'id'       => $id,
                'quantity' => $qty,
                'item_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
            ];
            $numItems += $qty;
        }

        $capi->sendPurchaseEvent([
            'event_id'     => 'order_' . $order->id . '_' . $order->created_at->timestamp,
            'value'        => (float) $order->total,
            'currency'     => 'PEN',
            'content_ids'  => $contentIds,
            'content_type' => 'product',
            'contents'     => $contents,
            'num_items'    => $numItems,
            'order_id'     => (string) $order->id,
            'email'        => $event->customerEmail,
            'phone'        => $event->customerPhone,
            'source_url'   => request()?->headers?->get('referer'),
            'client_ip'    => request()?->ip(),
            'user_agent'   => request()?->userAgent(),
        ]);
    }
}
