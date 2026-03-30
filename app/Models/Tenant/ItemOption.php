<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opción de variante de un producto (ej: "Color", "Talla").
 *
 * @property int $id
 * @property int $item_id
 * @property string $name
 * @property int $position
 */
class ItemOption extends ModelTenant
{
    protected $table = 'item_options';

    protected $fillable = [
        'item_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ItemOptionValue::class)->orderBy('position');
    }
}
