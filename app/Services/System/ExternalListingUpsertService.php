<?php

namespace App\Services\System;

use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceExternalSeller;
use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceListingOption;
use App\Models\System\MarketplaceListingOptionValue;
use App\Models\System\MarketplaceListingVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda en el índice del marketplace un producto que manda un vendedor
 * externo (PRO 9). Es el equivalente a MarketplaceListingSyncService::syncItem
 * para tiendas cuya BD no está en este servidor: en lugar de leer el tenant,
 * recibe el producto ya armado.
 *
 * No se confía en lo que define la identidad del listing en ebaemy: hostname,
 * slug, texto de búsqueda y categoría se calculan aquí. Del payload solo se
 * toman los campos de LISTING_FIELDS, y las imágenes solo si viven en el
 * dominio de la propia tienda.
 */
class ExternalListingUpsertService
{
    private const LISTING_FIELDS = [
        'title', 'internal_id', 'description', 'image_url', 'thumb_image_url',
        'secondary_image_url', 'gallery_image_urls', 'category_name', 'brand_name',
        'price', 'mp_price', 'is_on_offer', 'original_price', 'offer_ends_at',
        'discount_pct', 'discount_source', 'has_variants', 'min_price', 'max_price',
        'stock', 'status',
    ];

    public function upsert(MarketplaceExternalSeller $seller, int $remoteItemId, array $payload): MarketplaceListing
    {
        $in = (array) ($payload['listing'] ?? []);
        $this->validate($in);

        $data = array_intersect_key($in, array_flip(self::LISTING_FIELDS));

        foreach (['image_url', 'thumb_image_url', 'secondary_image_url'] as $field) {
            $data[$field] = $this->ownImage($seller, $data[$field] ?? null);
        }
        $gallery = array_values(array_filter(array_map(
            fn ($url) => $this->ownImage($seller, $url),
            (array) ($data['gallery_image_urls'] ?? [])
        )));
        $data['gallery_image_urls'] = count($gallery) >= 2 ? $gallery : null;

        foreach (['category_name' => 150, 'brand_name' => 150, 'internal_id' => 80, 'discount_source' => 20] as $field => $max) {
            $data[$field] = isset($data[$field]) && $data[$field] !== '' ? Str::limit((string) $data[$field], $max, '') : null;
        }

        $brand    = (string) ($data['brand_name'] ?? '');
        $category = (string) ($data['category_name'] ?? '');
        $sellerInfo = (array) ($payload['seller'] ?? []);

        $data = array_merge($data, [
            'hostname_id'     => $seller->hostname_id,
            'tenant_fqdn'     => $seller->fqdn,
            'client_id'       => null,
            'remote_item_id'  => $remoteItemId,
            'tenant_name'     => Str::limit((string) ($sellerInfo['name'] ?? $seller->name), 150, ''),
            'tenant_logo_url' => $this->ownImage($seller, $sellerInfo['logo_url'] ?? null),
            'seller_whatsapp' => $this->digits($sellerInfo['whatsapp'] ?? null),
            'tenant_verified' => false,
            'slug'            => Str::limit(Str::slug($data['title']) ?: 'producto', 180, '')
                                 . '-t' . $seller->hostname_id . '-' . $remoteItemId,
            'search_text'     => Str::limit(MarketplaceListingSyncService::normalizeForSearch(
                $data['title'] . ' ' . $brand . ' ' . $category
            ), 500, ''),
            'marketplace_category_id' => $this->categoryId($in['category_full_slug'] ?? null),
            'status'          => ($data['status'] ?? 'active') === 'paused' ? 'paused' : 'active',
            'is_pack'         => false,
            'pack_contents'   => null,
            'pack_stock'      => null,
            // Oculto hasta que el administrador apruebe la tienda (changeStatus lo destapa).
            'is_active'       => $seller->is_active,
            'synced_at'       => now(),
        ]);

        return DB::connection('system')->transaction(function () use ($seller, $remoteItemId, $data, $payload) {
            $listing = MarketplaceListing::updateOrCreate(
                ['hostname_id' => $seller->hostname_id, 'remote_item_id' => $remoteItemId],
                $data
            );

            if (!empty($data['has_variants'])) {
                $this->syncVariants($seller, $listing, (array) ($payload['options'] ?? []), (array) ($payload['variants'] ?? []));
            } else {
                MarketplaceListingVariant::where('listing_id', $listing->id)->update(['is_active' => false]);
            }

            return $listing;
        });
    }

    /** El vendedor despublicó o borró el producto: se pausa, no se borra (conserva histórico). */
    public function pause(MarketplaceExternalSeller $seller, int $remoteItemId): bool
    {
        return (bool) MarketplaceListing::where('hostname_id', $seller->hostname_id)
            ->where('remote_item_id', $remoteItemId)
            ->update(['is_active' => false, 'status' => 'paused', 'synced_at' => now()]);
    }

    private function syncVariants(MarketplaceExternalSeller $seller, MarketplaceListing $listing, array $options, array $variants): void
    {
        // Opciones y valores se reconstruyen (el cascade limpia los valores) y
        // se guarda el mapa id-del-vendedor → id-local para el pivote.
        MarketplaceListingOption::where('listing_id', $listing->id)->delete();
        $valueIdMap = [];
        foreach ($options as $opt) {
            $sysOpt = MarketplaceListingOption::create([
                'listing_id'       => $listing->id,
                'tenant_option_id' => (int) ($opt['id'] ?? 0),
                'name'             => Str::limit((string) ($opt['name'] ?? ''), 80, ''),
                'position'         => (int) ($opt['position'] ?? 0),
            ]);
            foreach ((array) ($opt['values'] ?? []) as $val) {
                $sysVal = MarketplaceListingOptionValue::create([
                    'option_id'       => $sysOpt->id,
                    'tenant_value_id' => (int) ($val['id'] ?? 0),
                    'value'           => Str::limit((string) ($val['value'] ?? ''), 80, ''),
                    'color_hex'       => preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($val['color_hex'] ?? '')) ? $val['color_hex'] : null,
                    'image_url'       => null,
                    'position'        => (int) ($val['position'] ?? 0),
                ]);
                $valueIdMap[(int) ($val['id'] ?? 0)] = $sysVal->id;
            }
        }

        $seen = [];
        foreach ($variants as $v) {
            $sysVar = MarketplaceListingVariant::updateOrCreate(
                ['listing_id' => $listing->id, 'tenant_variant_id' => (int) ($v['id'] ?? 0)],
                [
                    'sku'            => isset($v['sku']) ? Str::limit((string) $v['sku'], 80, '') : null,
                    'display_name'   => Str::limit((string) ($v['display_name'] ?? 'Variante'), 150, ''),
                    'image_url'      => $this->ownImage($seller, $v['image_url'] ?? null),
                    'price'          => max(0, (float) ($v['price'] ?? 0)),
                    'original_price' => null,
                    'is_on_offer'    => false,
                    'discount_pct'   => null,
                    'offer_ends_at'  => null,
                    'stock'          => max(0, (int) ($v['stock'] ?? 0)),
                    'is_active'      => true,
                    'is_primary'     => (bool) ($v['is_primary'] ?? false),
                ]
            );
            $seen[] = $sysVar->id;

            DB::connection('system')->table('marketplace_listing_variant_values')
                ->where('listing_variant_id', $sysVar->id)->delete();
            foreach ((array) ($v['value_ids'] ?? []) as $remoteValueId) {
                if ($localId = $valueIdMap[(int) $remoteValueId] ?? null) {
                    DB::connection('system')->table('marketplace_listing_variant_values')->insert([
                        'listing_variant_id' => $sysVar->id,
                        'option_value_id'    => $localId,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        }

        MarketplaceListingVariant::where('listing_id', $listing->id)
            ->whereNotIn('id', $seen ?: [0])->where('is_active', true)->update(['is_active' => false]);
    }

    private function validate(array $in): void
    {
        $validator = validator($in, [
            'title'      => 'required|string|max:250',
            'price'      => 'required|numeric|min:0',
            'mp_price'   => 'nullable|numeric|min:0',
            'stock'      => 'required|integer|min:0',
            'min_price'  => 'nullable|numeric|min:0',
            'max_price'  => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'discount_pct'   => 'nullable|integer|min:0|max:100',
            'offer_ends_at'  => 'nullable|date',
            'has_variants'   => 'boolean',
            'is_on_offer'    => 'boolean',
            'status'         => 'nullable|in:active,paused',
            'gallery_image_urls' => 'nullable|array|max:10',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /** Solo se aceptan imágenes servidas por el dominio de la propia tienda. */
    private function ownImage(MarketplaceExternalSeller $seller, $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $parts = parse_url($url);
        $ok = in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && strcasecmp($parts['host'] ?? '', $seller->fqdn) === 0;

        return $ok ? Str::limit($url, 500, '') : null;
    }

    /** Solo categorías hoja abiertas a vendedores; una ruta desconocida deja el producto sin categoría. */
    private function categoryId($fullSlug): ?int
    {
        if (!is_string($fullSlug) || $fullSlug === '') {
            return null;
        }

        return MarketplaceCategory::where('full_slug', $fullSlug)
            ->where('is_leaf', true)->where('allow_seller_publish', true)->where('is_active', true)
            ->value('id');
    }

    private function digits($raw): ?string
    {
        // Misma regla que MarketplaceListingSyncService::normalizeWhatsapp.
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if (strlen($digits) < 9 || strlen($digits) > 20) {
            return null;
        }
        if (strlen($digits) === 9 && $digits[0] === '9') {
            $digits = '51' . $digits;
        }
        return $digits;
    }
}
