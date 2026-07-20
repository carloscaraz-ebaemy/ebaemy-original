<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración del módulo de envíos (1 fila por tenant).
 * Guarda el ORIGEN de la tienda para calcular la distancia al cliente.
 */
class ShippingSetting extends Model
{
    protected $connection = 'tenant';
    protected $table = 'shipping_settings';

    protected $fillable = [
        'store_latitude',
        'store_longitude',
        'store_address',
    ];

    protected $casts = [
        'store_latitude'  => 'decimal:7',
        'store_longitude' => 'decimal:7',
    ];

    /** Fila única de configuración (la crea si no existe). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /** ¿Ya está fijada la ubicación de la tienda? */
    public function getHasOriginAttribute(): bool
    {
        return $this->store_latitude !== null && $this->store_longitude !== null;
    }
}
