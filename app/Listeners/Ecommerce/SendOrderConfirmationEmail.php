<?php

namespace App\Listeners\Ecommerce;

use App\Events\Ecommerce\OrderCreated;
use App\Mail\Tenant\OrderConfirmationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public int $tries  = 3;
    public int $delay  = 5; // seconds after event

    public function handle(OrderCreated $event): void
    {
        if (!$event->customerEmail) {
            return;
        }

        try {
            Mail::to($event->customerEmail)->send(
                new OrderConfirmationEmail(
                    $event->order,
                    $event->customerName,
                    $event->customerEmail
                )
            );
        } catch (\Exception $e) {
            Log::warning('SendOrderConfirmationEmail failed: ' . $e->getMessage(), [
                'order_id' => $event->order->id,
                'email'    => $event->customerEmail,
            ]);

            // Re-throw so the job retries (queue will back off)
            throw $e;
        }
    }

    public function failed(OrderCreated $event, \Throwable $e): void
    {
        Log::error('SendOrderConfirmationEmail permanently failed', [
            'order_id' => $event->order->id,
            'email'    => $event->customerEmail,
            'error'    => $e->getMessage(),
        ]);
    }
}
