<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Modules\Item\Models\Category;

/**
 * Homologación categoría interna (categories.id) → categoría Saga Falabella.
 * Ver migración 2026_06_19_000001_create_saga_category_mapping_tables.
 */
class SagaCategoryMap extends Model
{
    use UsesTenantConnection;

    protected $table = 'saga_category_map';

    protected $fillable = [
        'channel_id', 'category_id', 'saga_category_id',
        'saga_category_name', 'saga_category_path',
    ];

    public function channel()
    {
        return $this->belongsTo(MarketplaceChannel::class, 'channel_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
