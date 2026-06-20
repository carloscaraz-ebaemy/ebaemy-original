<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Modules\Item\Models\Brand;

/**
 * Homologación marca interna (brands.id) → marca Saga Falabella.
 * Ver migración 2026_06_20_000001_create_saga_brand_map_table.
 */
class SagaBrandMap extends Model
{
    use UsesTenantConnection;

    protected $table = 'saga_brand_map';

    protected $fillable = [
        'channel_id', 'brand_id', 'saga_brand_id', 'saga_brand_name',
    ];

    public function channel()
    {
        return $this->belongsTo(MarketplaceChannel::class, 'channel_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
