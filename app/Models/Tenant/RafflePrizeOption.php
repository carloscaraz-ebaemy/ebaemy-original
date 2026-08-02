<?php

namespace App\Models\Tenant;

use App\Services\Tenant\ImageProcessingService;
use Illuminate\Database\Eloquent\Model;

/**
 * Alternativa de premio de un sorteo.
 *
 * Cuando el sorteo tiene opciones, el cliente elige la que quiere al aceptar
 * participar; si no tiene ninguna, el sorteo funciona con el premio único de
 * las columnas `prize_*` de `raffles`.
 */
class RafflePrizeOption extends Model
{
    protected $connection = 'tenant';

    protected $table = 'raffle_prize_options';

    protected $fillable = [
        'raffle_id',
        'name',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'raffle_id'  => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function raffle()
    {
        return $this->belongsTo(Raffle::class, 'raffle_id');
    }

    public function participants()
    {
        return $this->hasMany(RaffleParticipant::class, 'prize_option_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** URL de la foto de la opción, o la del premio general como respaldo. */
    public function imageUrl(?string $variant = 'medium'): ?string
    {
        if ($this->image) {
            return ImageProcessingService::getUrl($this->image, $variant);
        }

        return optional($this->raffle)->prizeImageUrl($variant);
    }

    /** Cuántos participantes eligieron esta opción (para el panel). */
    public function getChosenCountAttribute(): int
    {
        return $this->participants()->count();
    }
}
