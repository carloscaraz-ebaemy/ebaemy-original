<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Caché por canal de los atributos que Saga exige para una categoría
 * (GetCategoryAttributes). Lo consume el builder del payload (Fase 2) para
 * saber qué campos son obligatorios sin re-llamar a la API en cada push.
 */
class SagaCategoryAttribute extends Model
{
    use UsesTenantConnection;

    protected $table = 'saga_category_attributes';

    protected $fillable = [
        'channel_id', 'saga_category_id', 'attributes', 'fetched_at',
    ];

    protected $casts = [
        'attributes' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(MarketplaceChannel::class, 'channel_id');
    }

    /**
     * Solo los atributos obligatorios (los que el builder debe garantizar).
     */
    public function mandatory(): array
    {
        return collect($this->attributes ?? [])
            ->filter(fn ($a) => !empty($a['mandatory']))
            ->values()
            ->all();
    }
}
