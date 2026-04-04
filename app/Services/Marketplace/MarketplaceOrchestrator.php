<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la sincronización entre todos los marketplaces conectados.
 *
 * Regla de oro: ERP es la fuente de verdad.
 * Cuando stock cambia en ERP → push a TODOS los marketplaces.
 * Cuando llega orden de marketplace → descontar en ERP → push a los demás.
 */
class MarketplaceOrchestrator
{
    /**
     * Sincronizar stock de un item a TODOS los marketplaces activos.
     * Llamar después de cualquier cambio de stock en el ERP.
     */
    public static function syncItemStock(int $itemId, ?int $variantId = null): void
    {
        $channels = MarketplaceChannel::active()->get();

        foreach ($channels as $channel) {
            try {
                $service = self::resolveService($channel);
                if ($service && method_exists($service, 'syncStock')) {
                    // Dispatch como job para no bloquear
                    dispatch(new \App\Jobs\Marketplace\SyncMarketplaceStockJob($channel->id, $itemId, $variantId));
                }
            } catch (\Throwable $e) {
                Log::warning("Marketplace stock sync failed for channel {$channel->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Sincronizar TODOS los productos de todos los canales.
     */
    public static function syncAllProducts(): array
    {
        $results = [];
        $channels = MarketplaceChannel::active()->get();

        foreach ($channels as $channel) {
            try {
                $service = self::resolveService($channel);
                if ($service && method_exists($service, 'syncProducts')) {
                    $results[$channel->platform] = $service->syncProducts();
                }
            } catch (\Throwable $e) {
                $results[$channel->platform] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Obtener órdenes de todos los canales.
     */
    public static function fetchAllOrders(): array
    {
        $results = [];
        $channels = MarketplaceChannel::active()->get();

        foreach ($channels as $channel) {
            try {
                $service = self::resolveService($channel);
                if ($service && method_exists($service, 'fetchOrders')) {
                    $results[$channel->platform] = $service->fetchOrders();
                }
            } catch (\Throwable $e) {
                $results[$channel->platform] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Resolver el servicio correcto según la plataforma.
     */
    public static function resolveService(MarketplaceChannel $channel): ?object
    {
        return match ($channel->platform) {
            'falabella'    => new FalabellaService($channel),
            'meta'         => new MetaFeedService($channel),
            'mercadolibre' => new MercadoLibreService($channel),
            'tiktok'       => new TikTokService($channel),
            default        => null,
        };
    }
}
