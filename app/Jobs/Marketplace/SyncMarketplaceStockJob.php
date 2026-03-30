<?php

namespace App\Jobs\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\MarketplaceOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $channelId,
        public int $itemId,
        public ?int $variantId = null
    ) {}

    public function handle(): void
    {
        $channel = MarketplaceChannel::find($this->channelId);
        if (!$channel || $channel->status !== 'active') return;

        try {
            $service = MarketplaceOrchestrator::resolveService($channel);
            if ($service && method_exists($service, 'syncStock')) {
                $service->syncStock();
                $channel->markSynced();
            }
        } catch (\Throwable $e) {
            Log::channel('payments')->error('Marketplace stock sync job failed', [
                'channel_id' => $this->channelId,
                'item_id' => $this->itemId,
                'error' => $e->getMessage(),
            ]);
            $channel->markError($e->getMessage());
            throw $e; // Re-throw para que el job se reintente
        }
    }
}
