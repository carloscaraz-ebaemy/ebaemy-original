<?php
namespace App\Jobs;

use App\Models\Tenant\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPostPurchaseReviewRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order || $order->status_order_id == 5) return;

        $customer = is_array($order->customer) ? $order->customer : json_decode($order->customer, true);
        $email = $customer['correo_electronico'] ?? $customer['email'] ?? null;
        if (!$email) return;

        $name = $customer['apellidos_y_nombres_o_razon_social'] ?? 'Cliente';
        $firstName = explode(' ', trim($name))[0];
        $items = is_array($order->items) ? $order->items : json_decode($order->items, true);

        try {
            Mail::send(
                'tenant.templates.email.review_request',
                [
                    'firstName' => $firstName,
                    'items' => array_slice($items ?? [], 0, 3),
                    'orderId' => $order->id,
                    'reviewUrl' => url('/ecommerce'),
                ],
                function ($message) use ($email, $firstName) {
                    $message->to($email)
                        ->subject("¡{$firstName}, cuéntanos tu experiencia!");
                }
            );
            Log::channel('payments')->info('Review request sent', ['order_id' => $this->orderId, 'email' => $email]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send review request', ['order_id' => $this->orderId, 'error' => $e->getMessage()]);
        }
    }
}
