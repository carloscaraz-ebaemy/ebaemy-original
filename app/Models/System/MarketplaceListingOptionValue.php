<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un valor de opción — Ej: "Rojo" (con color_hex y/o image_url),
 * "Talla M" (sin imagen). Espejo de item_option_values del tenant.
 */
class MarketplaceListingOptionValue extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_listing_option_values';

    protected $fillable = [
        'option_id',
        'tenant_value_id',
        'value',
        'color_hex',
        'image_url',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListingOption::class, 'option_id');
    }
}
