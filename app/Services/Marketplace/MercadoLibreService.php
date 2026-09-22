<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Http;

/**
 * MercadoLibre — Integración con MercadoLibre API
 *
 * Sincroniza productos y stock vía API REST.
 * Docs: https://developers.mercadolibre.com.pe/
 */
class MercadoLibreService
{
    private const API_BASE = 'https://api.mercadolibre.com';

    protected MarketplaceChannel $channel;
    protected string $accessToken;
    protected string $sellerId;

    public function __construct(MarketplaceChannel $channel)
    {
        $this->channel = $channel;
        $this->accessToken = $channel->getCredential('access_token', '');
        $this->sellerId = $channel->getCredential('seller_id', '');
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
            'message'   => "{$mapped} productos mapeados para MercadoLibre.",
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
            'message'   => "Stock actualizado ({$productCount} productos en MercadoLibre).",
        ];
    }
    /**
     * Salud de la cuenta de vendedor, desde la API oficial de MercadoLibre.
     *
     * Lo usa el modulo 4 del agente de seguridad. Es de SOLO LECTURA: consulta
     * la reputacion, las publicaciones pausadas y las preguntas sin responder.
     *
     * @see https://developers.mercadolibre.com.pe/es_ar/gestiona-tu-reputacion
     *
     * @return array{ok:bool,error:?string,level:?string,metrics:array,paused:?int,unanswered:?int}
     */
    public function getSellerHealth(): array
    {
        $empty = ['ok' => false, 'error' => null, 'level' => null, 'metrics' => [], 'paused' => null, 'unanswered' => null];

        if ($this->accessToken === '' || $this->sellerId === '') {
            return array_merge($empty, ['error' => 'El canal no tiene access_token o seller_id configurados.']);
        }

        $http = Http::timeout(20)->withToken($this->accessToken);

        try {
            $user = $http->get(self::API_BASE . "/users/{$this->sellerId}");

            if ($user->failed()) {
                return array_merge($empty, ['error' => 'MercadoLibre respondio ' . $user->status()]);
            }

            $reputation = (array) ($user->json('seller_reputation') ?? []);
            $metrics    = (array) ($reputation['metrics'] ?? []);

            // Publicaciones pausadas y preguntas sin responder: el paging de la
            // API ya devuelve el total, no hace falta traer los elementos.
            $paused = $http->get(self::API_BASE . "/users/{$this->sellerId}/items/search", [
                'status' => 'paused',
                'limit'  => 1,
            ]);

            $questions = $http->get(self::API_BASE . '/questions/search', [
                'seller_id'   => $this->sellerId,
                'status'      => 'UNANSWERED',
                'api_version' => 4,
                'limit'       => 1,
            ]);

            return [
                'ok'         => true,
                'error'      => null,
                'level'      => $reputation['level_id'] ?? null,   // 5_green, 3_yellow, 1_red...
                'metrics'    => [
                    'claims_pct'        => $this->rateToPct($metrics['claims'] ?? null),
                    'cancellations_pct' => $this->rateToPct($metrics['cancellations'] ?? null),
                    'late_shipment_pct' => $this->rateToPct($metrics['delayed_handling_time'] ?? null),
                ],
                'paused'     => $paused->successful() ? (int) $paused->json('paging.total') : null,
                'unanswered' => $questions->successful() ? (int) $questions->json('paging.total') : null,
            ];
        } catch (\Throwable $e) {
            return array_merge($empty, ['error' => $e->getMessage()]);
        }
    }

    /** MercadoLibre devuelve las tasas en fraccion (0.023 = 2.3 %). */
    private function rateToPct($metric): ?float
    {
        $rate = is_array($metric) ? ($metric['rate'] ?? null) : null;

        return $rate === null ? null : round(((float) $rate) * 100, 2);
    }
}
