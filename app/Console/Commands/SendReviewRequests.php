<?php
namespace App\Console\Commands;

use App\Jobs\SendPostPurchaseReviewRequest;
use App\Models\Tenant\Order;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReviewRequests extends Command
{
    protected $signature = 'ecommerce:send-review-requests';
    protected $description = 'Send review request emails for delivered orders';

    public function handle(Environment $tenancy): void
    {
        $total = 0;

        Website::chunk(10, function ($websites) use ($tenancy, &$total) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);

                    $orders = Order::whereIn('status_order_id', [3, 4]) // dispatched or delivered
                        ->whereBetween('updated_at', [now()->subDays(5), now()->subDays(3)])
                        ->whereDoesntHave('reviews')
                        ->get();

                    foreach ($orders as $order) {
                        SendPostPurchaseReviewRequest::dispatch($order->id);
                        $total++;
                    }

                } catch (\Throwable $e) {
                    Log::error('[SendReviewRequests] Error en tenant.', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->info("Dispatched review requests for {$total} orders.");
    }
}
