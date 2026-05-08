<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una opción del producto en el marketplace central — espejo de
 * item_options del tenant. Ej: "Color", "Talla", "Material".
 */
class MarketplaceListingOption extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_listing_options';

    protected $fillable = [
        'listing_id',
        'tenant_option_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MarketplaceListingOptionValue::class, 'option_id')
                    ->orderBy('position');
    }
}
