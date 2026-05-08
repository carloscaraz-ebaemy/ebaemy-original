<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceListingVariant;
use App\Services\Tenant\PromotionEngine;
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

            $listing = MarketplaceListing::updateOrCreate(
                ['hostname_id' => $hostnameId, 'remote_item_id' => $remoteItemId],
                $payload
            );

            // Si el item tiene variantes, espejarlas en marketplace_listing_variants.
            // Cambiamos a tenant para leer item_variants y volvemos a system para
            // upsertar — el helper se encarga de las conexiones internamente.
            if ($listing && !empty($payload['has_variants'])) {
                $tenancy->tenant($client->hostname->website);
                $this->syncVariants($listing, $row);
                $tenancy->tenant(null);
            } elseif ($listing) {
                // Si dejó de tener variantes (las desactivó todas en el tenant),
                // marcamos las espejadas como inactivas.
                MarketplaceListingVariant::where('listing_id', $listing->id)
                    ->update(['is_active' => false]);
            }

            return $listing;
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

        $fqdn = $client->hostname->fqdn;

        $imageUrl = $item->image
            ? 'https://' . $fqdn . '/storage/uploads/items/' . $item->image
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

        // Tienda vendedora: trade_name comercial y logo desde Company del tenant
        [$tenantName, $tenantLogoUrl] = $this->resolveTenantBranding($fqdn, $client);

        // Variantes y promo: calculamos en helpers separados para mantener
        // este método legible. El orden importa — si has_variants=true,
        // saltamos PromotionEngine (las promos por variante quedan para
        // Fase 0.B con marketplace_listing_variants).
        $variantInfo = $this->resolveVariantInfo($item, (int) $stock);
        $offerInfo   = $variantInfo['has_variants']
            ? $this->emptyOfferInfo()
            : $this->resolveOfferInfo($item, (float) ($item->sale_unit_price ?? 0));

        return [
            'hostname_id'       => $hostnameId,
            'tenant_fqdn'       => $fqdn,
            'tenant_name'       => $tenantName,
            'tenant_logo_url'   => $tenantLogoUrl,
            'tenant_verified'   => (bool) ($client->is_verified ?? false),
            'client_id'         => $client->id,
            'remote_item_id'    => $item->id,
            'title'             => Str::limit((string) ($item->description ?: $item->name ?: 'Producto'), 250, ''),
            'slug'              => $this->buildSlug($item, $hostnameId),
            'internal_id'       => $item->internal_id ?? null,
            'short_description' => null,
            'description'       => $item->mp_notes ?? null,
            'image_url'         => $imageUrl,
            'category_name'     => $categoryName,
            'marketplace_category_id' => $item->marketplace_category_id ?? null,
            'brand_name'        => $brandName,

            // Precio "principal" mostrado en cards. Para items con variantes
            // usamos el min — el cliente verá "Desde S/X" derivado de min_price.
            'price'             => $variantInfo['has_variants']
                ? (float) ($variantInfo['min_price'] ?? $item->sale_unit_price ?? 0)
                : (float) ($item->sale_unit_price ?? 0),

            // Override manual del tenant. NULL si no es válido (>0).
            'mp_price'          => (isset($item->mp_price) && (float) $item->mp_price > 0) ? (float) $item->mp_price : null,

            // ── Bloque OFERTAS ──
            'is_on_offer'       => $offerInfo['is_on_offer'],
            'original_price'    => $offerInfo['original_price'],
            'offer_ends_at'     => $offerInfo['offer_ends_at'],
            'discount_pct'      => $offerInfo['discount_pct'],

            // ── Bloque VARIANTES ──
            'has_variants'      => $variantInfo['has_variants'],
            'min_price'         => $variantInfo['min_price'],
            'max_price'         => $variantInfo['max_price'],

            'stock'             => max(0, (int) ($variantInfo['has_variants'] ? $variantInfo['stock'] : $stock)),
            'status'            => $item->mp_status ?? 'active',
            'is_active'         => true,
            'synced_at'         => now(),
        ];
    }

    /**
     * Si el item tiene variantes activas, devuelve has_variants=true con
     * min/max/sum sobre item_variants. NULL en sale_unit_price de variante
     * = hereda del padre (lógica del modelo ItemVariant).
     */
    private function resolveVariantInfo(object $item, int $fallbackStock): array
    {
        if (empty($item->has_variants)) {
            return [
                'has_variants' => false,
                'min_price'    => null,
                'max_price'    => null,
                'stock'        => $fallbackStock,
            ];
        }

        $parentPrice = (float) ($item->sale_unit_price ?? 0);

        $row = DB::connection('tenant')->table('item_variants')
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->selectRaw('
                MIN(COALESCE(sale_unit_price, ?)) AS min_p,
                MAX(COALESCE(sale_unit_price, ?)) AS max_p,
                SUM(stock) AS sum_stock,
                COUNT(*)   AS n
            ', [$parentPrice, $parentPrice])
            ->first();

        // Si has_variants=true pero no hay variantes activas, tratamos como
        // sin variantes para no quedarnos sin precio mostrable.
        if (!$row || (int) $row->n === 0) {
            return [
                'has_variants' => false,
                'min_price'    => null,
                'max_price'    => null,
                'stock'        => $fallbackStock,
            ];
        }

        return [
            'has_variants' => true,
            'min_price'    => (float) $row->min_p,
            'max_price'    => (float) $row->max_p,
            'stock'        => (int) $row->sum_stock,
        ];
    }

    /**
     * Calcula la oferta efectiva del item para el canal `marketplace`.
     *
     * Prioridad:
     *   1. mp_price manual (override del tenant). Si está debajo del precio,
     *      es oferta sin fecha de expiración.
     *   2. PromotionEngine + DiscountRule activas con channel='marketplace':
     *      simula cart de 1 unidad, calcula con commit=false y persiste el
     *      ends_at más cercano de las reglas aplicables.
     *   3. Sin promo → todos los flags en NULL/false.
     */
    private function resolveOfferInfo(object $item, float $salePrice): array
    {
        $mpPrice = (isset($item->mp_price) && (float) $item->mp_price > 0)
            ? (float) $item->mp_price : null;

        // Caso 1: override manual del tenant
        if ($mpPrice !== null && $mpPrice < $salePrice && $salePrice > 0) {
            return [
                'is_on_offer'    => true,
                'original_price' => $salePrice,
                'offer_ends_at'  => null,
                'discount_pct'   => (int) round((1 - $mpPrice / $salePrice) * 100),
            ];
        }

        // Caso 2: PromotionEngine. Solo si hay un canal 'marketplace' configurado
        // y reglas activas que apliquen al item.
        if ($salePrice > 0) {
            try {
                $channel = DB::connection('tenant')->table('sales_channels')
                    ->where('type', 'marketplace')
                    ->where('is_active', true)
                    ->first();

                if ($channel) {
                    $cart = [[
                        'id'              => $item->id,
                        'item_id'         => $item->id,
                        'sale_unit_price' => $salePrice,
                        'quantity'        => 1,
                        'subtotal'        => $salePrice,
                    ]];

                    $promo = PromotionEngine::make($cart, $salePrice)
                        ->withChannel($channel->id, 'marketplace')
                        ->calculate(false); // commit=false → no incrementa used_count

                    $ruleDiscount = (float) ($promo['rule_discount'] ?? 0);
                    if ($ruleDiscount > 0) {
                        // Buscar la fecha de expiración más cercana entre reglas activas
                        // del canal que apliquen al item / categoría.
                        $earliestEnd = DB::connection('tenant')->table('discount_rules')
                            ->where('is_active', true)
                            ->whereNotNull('ends_at')
                            ->where('ends_at', '>', now())
                            ->where(function ($q) use ($channel) {
                                $q->whereNull('channel_id')->orWhere('channel_id', $channel->id);
                            })
                            ->where(function ($q) use ($item) {
                                $q->whereNull('apply_item_id')
                                  ->orWhere('apply_item_id', $item->id)
                                  ->orWhere(function ($q2) use ($item) {
                                      if (!empty($item->category_id)) {
                                          $q2->where('apply_category_id', $item->category_id);
                                      }
                                  });
                            })
                            ->min('ends_at');

                        return [
                            'is_on_offer'    => true,
                            'original_price' => $salePrice,
                            'offer_ends_at'  => $earliestEnd,
                            'discount_pct'   => (int) round(($ruleDiscount / $salePrice) * 100),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('MarketplaceListingSyncService: PromotionEngine fallback', [
                    'item_id' => $item->id,
                    'error'   => $e->getMessage(),
                ]);
                // Sigue al fallback sin oferta
            }
        }

        return $this->emptyOfferInfo();
    }

    private function emptyOfferInfo(): array
    {
        return [
            'is_on_offer'    => false,
            'original_price' => null,
            'offer_ends_at'  => null,
            'discount_pct'   => null,
        ];
    }

    /**
     * Wrapper público para que el comando de sync masivo (que itera ya
     * dentro del contexto tenant) pueda invocar syncVariants sin tener
     * que conocer el método privado. El caller es responsable de estar
     * en contexto tenant antes de llamar.
     */
    public function syncVariantsForListing(MarketplaceListing $listing, object $item): void
    {
        $this->syncVariants($listing, $item);
    }

    /**
     * Espeja item_variants del tenant en marketplace_listing_variants.
     * Requiere estar conectado al tenant al entrar; cambia explícitamente
     * a system para los upserts y vuelve a tenant antes de salir (el caller
     * gestiona el ciclo del Environment).
     *
     * Estrategia:
     *   1. Lee variantes activas del tenant + sus precios efectivos
     *      (PromotionEngine canal 'marketplace' por cada una).
     *   2. Upsert en system por (listing_id, tenant_variant_id).
     *   3. Marca como is_active=false las variantes espejadas que ya no
     *      vinieron en este sync (variant desactivada en tenant) — sin
     *      borrar para preservar referencias en pedidos pasados.
     */
    private function syncVariants(MarketplaceListing $listing, object $item): void
    {
        $parentPrice = (float) ($item->sale_unit_price ?? 0);
        $fqdn = $listing->tenant_fqdn;

        $variants = DB::connection('tenant')->table('item_variants')
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->get();

        // Stock por variante desde item_variant_warehouse (si aplica el modelo)
        $variantIds = $variants->pluck('id')->all();
        $stockByVariant = [];
        if (!empty($variantIds)) {
            $rows = DB::connection('tenant')->table('item_variant_warehouse')
                ->whereIn('item_variant_id', $variantIds)
                ->select('item_variant_id', DB::raw('SUM(stock) AS total_stock'))
                ->groupBy('item_variant_id')
                ->get();
            foreach ($rows as $r) {
                $stockByVariant[$r->item_variant_id] = (int) $r->total_stock;
            }
        }

        // Calcular oferta por variante usando PromotionEngine (mismo canal)
        $channel = DB::connection('tenant')->table('sales_channels')
            ->where('type', 'marketplace')
            ->where('is_active', true)
            ->first();

        $payloads = [];
        foreach ($variants as $v) {
            $price = $v->sale_unit_price !== null ? (float) $v->sale_unit_price : $parentPrice;
            $offer = $this->resolveVariantOffer($item, $v, $price, $channel);

            $imageUrl = !empty($v->image)
                ? 'https://' . $fqdn . '/storage/uploads/items/' . $v->image
                : null; // si no tiene imagen propia, la UI cae al image_url del listing

            // Stock: tabla item_variant_warehouse → fallback al stock global de variant
            $stock = $stockByVariant[$v->id] ?? (int) ($v->stock ?? 0);

            $payloads[] = [
                'listing_id'        => $listing->id,
                'tenant_variant_id' => (int) $v->id,
                'sku'               => $v->sku ?? null,
                'display_name'      => (string) ($v->display_name ?: 'Variante ' . $v->id),
                'image_url'         => $imageUrl,
                'price'             => $offer['price'],
                'original_price'    => $offer['original_price'],
                'is_on_offer'       => $offer['is_on_offer'],
                'discount_pct'      => $offer['discount_pct'],
                'offer_ends_at'     => $offer['offer_ends_at'],
                'stock'             => max(0, $stock),
                'is_active'         => true,
            ];
        }

        // Upsert en system
        $tenancy = app(Environment::class);
        $tenancy->tenant(null);

        $seenIds = [];
        foreach ($payloads as $p) {
            $row = MarketplaceListingVariant::updateOrCreate(
                ['listing_id' => $p['listing_id'], 'tenant_variant_id' => $p['tenant_variant_id']],
                $p
            );
            $seenIds[] = $row->id;
        }

        // Desactivar variantes espejadas que ya no aparecen en el tenant
        MarketplaceListingVariant::where('listing_id', $listing->id)
            ->whereNotIn('id', $seenIds ?: [0])
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Mismo patrón que resolveOfferInfo() pero por variante. PromotionEngine
     * recibe un cart simulado de 1 unidad de la variante con su sale_unit_price.
     */
    private function resolveVariantOffer(object $item, object $variant, float $price, ?object $channel): array
    {
        if ($price <= 0 || !$channel) {
            return [
                'price'          => $price,
                'is_on_offer'    => false,
                'original_price' => null,
                'offer_ends_at'  => null,
                'discount_pct'   => null,
            ];
        }

        try {
            $cart = [[
                'id'              => $item->id,
                'item_id'         => $item->id,
                'item_variant_id' => $variant->id,
                'sale_unit_price' => $price,
                'quantity'        => 1,
                'subtotal'        => $price,
            ]];

            $promo = PromotionEngine::make($cart, $price)
                ->withChannel($channel->id, 'marketplace')
                ->calculate(false);

            $ruleDiscount = (float) ($promo['rule_discount'] ?? 0);
            if ($ruleDiscount > 0) {
                $effective = max(0, $price - $ruleDiscount);

                $earliestEnd = DB::connection('tenant')->table('discount_rules')
                    ->where('is_active', true)
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now())
                    ->where(function ($q) use ($channel) {
                        $q->whereNull('channel_id')->orWhere('channel_id', $channel->id);
                    })
                    ->where(function ($q) use ($item) {
                        $q->whereNull('apply_item_id')
                          ->orWhere('apply_item_id', $item->id)
                          ->orWhere(function ($q2) use ($item) {
                              if (!empty($item->category_id)) {
                                  $q2->where('apply_category_id', $item->category_id);
                              }
                          });
                    })
                    ->min('ends_at');

                return [
                    'price'          => $effective,
                    'is_on_offer'    => true,
                    'original_price' => $price,
                    'offer_ends_at'  => $earliestEnd,
                    'discount_pct'   => (int) round(($ruleDiscount / $price) * 100),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingSyncService::resolveVariantOffer fallback', [
                'item_id'    => $item->id,
                'variant_id' => $variant->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return [
            'price'          => $price,
            'is_on_offer'    => false,
            'original_price' => null,
            'offer_ends_at'  => null,
            'discount_pct'   => null,
        ];
    }

    /**
     * Obtiene el nombre comercial y logo del tenant. Prioridad:
     *   1. companies.title_web (nombre SEO/web — el más cercano a la marca pública)
     *   2. companies.name (razón social — en muchos tenants coincide con la marca)
     *   3. companies.trade_name (último recurso — algunos tenants guardan aquí el
     *      nombre personal del titular por mal llenado de datos)
     *   4. $client->name → $fqdn (fallbacks externos a la BD del tenant)
     *
     * Logo: se resuelve desde companies.logo.
     */
    private function resolveTenantBranding(string $fqdn, Client $client): array
    {
        $name = null;
        $logoFile = null;

        try {
            $company = DB::connection('tenant')->table('companies')->first();
            if ($company) {
                $name = ($company->title_web ?? null)
                    ?: ($company->name ?? null)
                    ?: ($company->trade_name ?? null);
                $logoFile = $company->logo ?? null;
            }
        } catch (\Throwable $e) {
            // Si la tabla companies no existe, caer al client->name
        }

        $name = $name ?: ($client->name ?: $fqdn);

        $logoUrl = null;
        if ($logoFile) {
            $logoUrl = 'https://' . $fqdn . '/storage/uploads/logos/' . $logoFile;
        }

        return [Str::limit($name, 140, ''), $logoUrl];
    }

    public function buildSlug(object $item, int $hostnameId): string
    {
        $base = $item->slug ?? Str::slug($item->description ?? $item->name ?? 'producto');
        return Str::limit($base, 180, '') . '-t' . $hostnameId . '-' . $item->id;
    }
}
