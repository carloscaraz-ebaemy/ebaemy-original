<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\MarketplaceListing;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mantiene sincronizado el índice central `marketplace_listings` con los items
 * publicables de cada tenant. Se usa desde:
 *   - ebaemy-marketplace:sync (cron cada 30 min, full sweep)
 *   - ItemController::marketplaceToggle (sync instantáneo al activar toggle)
 *
 * El servicio cambia la conexión via hyn Environment entre el tenant y el
 * central según corresponda, y siempre restaura el estado original al salir.
 */
class MarketplaceListingSyncService
{
    /**
     * Sincroniza UN item de UN tenant. Usado para feedback inmediato al
     * activar el toggle desde el tenant.
     *
     * @return MarketplaceListing|null  listing actualizado o null si se borró
     */
    public function syncItem(int $hostnameId, int $remoteItemId): ?MarketplaceListing
    {
        $client = Client::where('hostname_id', $hostnameId)->with('hostname.website')->first();
        if (!$client || !$client->hostname || !$client->hostname->website) {
            Log::warning('MarketplaceListingSyncService: tenant no encontrado', compact('hostnameId'));
            return null;
        }

        $tenancy = app(Environment::class);
        $originalTenant = $tenancy->tenant();

        try {
            $tenancy->tenant($client->hostname->website);

            $row = DB::connection('tenant')->table('items')->where('id', $remoteItemId)->first();
            if (!$row) {
                return null;
            }

            $payload = $this->buildPayload($row, $client, $hostnameId);

            $tenancy->tenant(null);

            if (!$row->marketplace_publishable || $row->active == 0 || ($row->mp_status ?? '') === 'rejected') {
                // Pausar el listing sin borrarlo para conservar histórico
                $listing = MarketplaceListing::where('hostname_id', $hostnameId)
                    ->where('remote_item_id', $remoteItemId)
                    ->first();
                if ($listing) {
                    $listing->update([
                        'is_active' => false,
                        'status'    => 'paused',
                        'synced_at' => now(),
                    ]);
                }
                return $listing;
            }

            return MarketplaceListing::updateOrCreate(
                ['hostname_id' => $hostnameId, 'remote_item_id' => $remoteItemId],
                $payload
            );
        } catch (\Throwable $e) {
            Log::error('MarketplaceListingSyncService::syncItem failed', [
                'hostname_id'  => $hostnameId,
                'remote_item_id' => $remoteItemId,
                'error'        => $e->getMessage(),
            ]);
            return null;
        } finally {
            $tenancy->tenant($originalTenant ?: null);
        }
    }

    /**
     * Construye el payload para insertar/actualizar en marketplace_listings.
     * Requiere estar conectado al tenant antes de llamar.
     */
    public function buildPayload(object $item, Client $client, int $hostnameId): array
    {
        $stock = DB::connection('tenant')->table('item_warehouse')
            ->where('item_id', $item->id)
            ->sum('stock');

        $imageUrl = $item->image
            ? 'https://' . $client->hostname->fqdn . '/storage/uploads/items/' . $item->image
            : null;

        $categoryName = null;
        if (!empty($item->category_id)) {
            $categoryName = DB::connection('tenant')->table('categories')
                ->where('id', $item->category_id)
                ->value('name');
        }

        $brandName = null;
        if (!empty($item->brand_id)) {
            $brandName = DB::connection('tenant')->table('brands')
                ->where('id', $item->brand_id)
                ->value('name');
        }

        return [
            'hostname_id'       => $hostnameId,
            'tenant_fqdn'       => $client->hostname->fqdn,
            'client_id'         => $client->id,
            'remote_item_id'    => $item->id,
            'title'             => Str::limit((string) ($item->description ?: $item->name ?: 'Producto'), 250, ''),
            'slug'              => $this->buildSlug($item, $hostnameId),
            'internal_id'       => $item->internal_id ?? null,
            'short_description' => null,
            'description'       => $item->mp_notes ?? null,
            'image_url'         => $imageUrl,
            'category_name'     => $categoryName,
            'brand_name'        => $brandName,
            'price'             => (float) ($item->sale_unit_price ?? 0),
            'mp_price'          => isset($item->mp_price) && $item->mp_price !== null ? (float) $item->mp_price : null,
            'stock'             => max(0, (int) $stock),
            'status'            => $item->mp_status ?? 'active',
            'is_active'         => true,
            'synced_at'         => now(),
        ];
    }

    public function buildSlug(object $item, int $hostnameId): string
    {
        $base = $item->slug ?? Str::slug($item->description ?? $item->name ?? 'producto');
        return Str::limit($base, 180, '') . '-t' . $hostnameId . '-' . $item->id;
    }
}
