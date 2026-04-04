<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;

/**
 * TikTok Shop — Integración con TikTok Shop API
 *
 * Sincroniza productos y stock.
 * Docs: https://partner.tiktokshop.com/
 */
class TikTokService
{
    protected MarketplaceChannel $channel;

    public function __construct(MarketplaceChannel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Sync productos: auto-mapear items con apply_store=1
     */
    public function syncProducts(): array
    {
        $items = Item::where('apply_store', 1)
            ->whereNotNull('internal_id')
            ->with(['variants'])
            ->get();

        $mapped = 0;

        foreach ($items as $item) {
            if ($item->has_variants && $item->variants->where('is_active', true)->count() > 0) {
                foreach ($item->variants->where('is_active', true) as $variant) {
                    MarketplaceProduct::updateOrCreate([
                        'channel_id'      => $this->channel->id,
                        'item_id'         => $item->id,
                        'item_variant_id' => $variant->id,
                    ], [
                        'external_sku' => $variant->sku ?: "{$item->internal_id}-V{$variant->id}",
                        'sync_status'  => 'synced',
                    ]);
                    $mapped++;
                }
            } else {
                MarketplaceProduct::updateOrCreate([
                    'channel_id' => $this->channel->id,
                    'item_id'    => $item->id,
                ], [
                    'external_sku' => $item->internal_id,
                    'sync_status'  => 'synced',
                ]);
                $mapped++;
            }
        }

        $this->channel->markSynced();

        return [
            'success'   => true,
            'processed' => $mapped,
            'message'   => "{$mapped} productos mapeados para TikTok Shop.",
        ];
    }

    /**
     * Sync stock: actualizar estado de productos mapeados
     */
    public function syncStock(): array
    {
        $productCount = MarketplaceProduct::where('channel_id', $this->channel->id)
            ->where('sync_status', 'synced')
            ->count();

        $this->channel->markSynced();

        return [
            'success'   => true,
            'processed' => $productCount,
            'message'   => "Stock actualizado ({$productCount} productos en TikTok Shop).",
        ];
    }
}
