<?php

namespace App\Jobs\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\Item;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Publica/actualiza UN producto del ERP en Saga Falabella (Fase 4).
 *
 * Lo encola el MarketplaceItemObserver al crear/editar un producto. Re-establece
 * el contexto del tenant explícitamente (el worker corre fuera del request web),
 * igual que el comando marketplace:sync y el importador.
 */
class PublishProductToSagaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantUuid,
        public int $channelId,
        public int $itemId,
        public ?int $variantId = null,
        // true cuando viene del toggle ON: si el producto quedó inactive por un
        // OFF previo, lo reactivamos en Saga antes de re-publicar.
        public bool $reactivate = false
    ) {}

    public function handle(): void
    {
        $website = Website::where('uuid', $this->tenantUuid)->first();
        if (!$website) {
            return;
        }
        app(Environment::class)->tenant($website);

        $channel = MarketplaceChannel::find($this->channelId);
        if (!$channel || $channel->status !== 'active' || $channel->platform !== 'falabella') {
            return;
        }

        $item = Item::find($this->itemId);
        if (!$item) {
            return;
        }

        // SKU por defecto al crear el mapeo (productos simples). Las variantes
        // resuelven su propio SKU en el builder.
        $defaultSku = $this->variantId ? null : ($item->internal_id ?: $item->item_code);

        $mapping = MarketplaceProduct::firstOrCreate(
            [
                'channel_id'      => $this->channelId,
                'item_id'         => $this->itemId,
                'item_variant_id' => $this->variantId,
            ],
            [
                'external_sku' => $defaultSku,
                'sync_status'  => 'pending',
            ]
        );

        if (!$mapping->external_sku && $defaultSku) {
            $mapping->update(['external_sku' => $defaultSku]);
        }

        $service = new FalabellaService($channel);
        if ($this->reactivate && $mapping->external_id) {
            $service->setProductActive($mapping, true);
        }
        $result = $service->publishOne($mapping);

        if (!($result['success'] ?? false)) {
            Log::channel('payments')->warning('Saga auto-publish: producto no publicado', [
                'item_id'    => $this->itemId,
                'channel_id' => $this->channelId,
                'error'      => $result['error'] ?? 'desconocido',
            ]);
        }
    }
}
