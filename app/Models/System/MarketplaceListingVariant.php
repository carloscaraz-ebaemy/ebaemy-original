<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variante espejo en el marketplace central — refleja item_variants del tenant.
 * Se llena en MarketplaceListingSyncService::syncVariants() (Fase 0.B) cuando
 * el item del tenant tiene has_variants=true.
 *
 * NO es FK con item_variants (vive en otra BD). El campo `tenant_variant_id`
 * preserva el id remoto para que MarketplaceOrderDispatcher pueda crear el
 * Order del tenant con la variante correcta.
 */
class MarketplaceListingVariant extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_listing_variants';

    protected $fillable = [
        'listing_id',
        'tenant_variant_id',
        'sku',
        'display_name',
        'image_url',
        'price',
        'original_price',
        'is_on_offer',
        'discount_pct',
        'offer_ends_at',
        'stock',
        'is_active',
        'is_primary',
    ];

    protected $casts = [
        'price'          => 'float',
        'original_price' => 'float',
        'is_on_offer'    => 'boolean',
        'discount_pct'   => 'integer',
        'offer_ends_at'  => 'datetime',
        'stock'          => 'integer',
        'is_active'      => 'boolean',
        'is_primary'     => 'boolean',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
