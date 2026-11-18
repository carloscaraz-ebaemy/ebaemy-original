<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto ADICIONAL de una variante. La principal vive en `item_variants.image`;
 * estas la acompañan en la galería de la ficha (tienda y marketplace).
 *
 * `image` es el nombre del archivo principal que devolvió
 * ImageProcessingService::processAndStore (sus versiones _medium, _small, _mp
 * y _mobile se derivan del nombre). Nunca se escribe sin pasar por el service.
 *
 * @property int $id
 * @property int $item_variant_id
 * @property string $image
 * @property int $position
 */
class ItemVariantImage extends ModelTenant
{
    protected $table = 'item_variant_images';

    protected $fillable = [
        'item_variant_id',
        'image',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
