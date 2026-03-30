<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Valor de una opción (ej: "Rojo", "M").
 *
 * @property int $id
 * @property int $item_option_id
 * @property string $value
 * @property string|null $color_hex
 * @property int $position
 */
class ItemOptionValue extends ModelTenant
{
    protected $table = 'item_option_values';

    protected $fillable = [
        'item_option_id',
        'value',
        'color_hex',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ItemOption::class, 'item_option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemVariant::class,
            'item_variant_value_map',
            'item_option_value_id',
            'item_variant_id'
        );
    }
}
