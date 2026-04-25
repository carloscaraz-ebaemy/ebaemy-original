<?php

namespace App\Jobs;

use App\Models\System\MarketingCampaign;
use App\Services\System\MarketingCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa una campaña de marketing en background. Despacha lotes hasta
 * agotar los targets pending, re-encolando el job entre lotes para no
 * bloquear el worker indefinidamente.
 *
 * Usage:
 *   ProcessMarketingCampaign::dispatch($campaign->id);
 *
 * Cada job procesa `batchSize` targets y, si quedan más pending, se
 * re-encola con un delay corto para distribuir carga.
 */
class ProcessMarketingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public int $campaignId,
        public int $batchSize = 100,
    ) {}

    public function handle(MarketingCampaignService $service): void
    {
        $campaign = MarketingCampaign::query()->find($this->campaignId);
        if (!$campaign) {
            Log::warning('ProcessMarketingCampaign: campaña no encontrada', ['id' => $this->campaignId]);
            return;
        }

        if (in_array($campaign->status, [MarketingCampaign::STATUS_SENT, MarketingCampaign::STATUS_CANCELLED])) {
            return;
        }

        $result = $service->process($campaign, $this->batchSize);

        Log::info('ProcessMarketingCampaign batch done', [
            'campaign_id' => $this->campaignId,
            'sent'    => $result['sent'],
            'failed'  => $result['failed'],
            'skipped' => $result['skipped'],
        ]);

        // Si quedan pending, re-encolar con delay para repartir el envío
        $remaining = $campaign->targets()
            ->where('status', 'pending')
            ->count();

        if ($remaining > 0) {
            self::dispatch($this->campaignId, $this->batchSize)
                ->delay(now()->addSeconds(30));
        }
    }
}
