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
        'price_per_km',
        'base_price',
        'min_price',
        'orders_whatsapp',
        'agency_fee',
        'require_payment',
    ];

    protected $casts = [
        'store_latitude'  => 'decimal:7',
        'store_longitude' => 'decimal:7',
        'price_per_km'    => 'decimal:2',
        'base_price'      => 'decimal:2',
        'min_price'       => 'decimal:2',
        'agency_fee'      => 'decimal:2',
        'require_payment' => 'boolean',
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

    /** Número de WhatsApp de la tienda (51XXXXXXXXX) para recibir pedidos, o null. */
    public function getOrdersWaAttribute(): ?string
    {
        $p = preg_replace('/\D+/', '', (string) $this->orders_whatsapp);
        if (strlen($p) === 9 && $p[0] === '9') {
            $p = '51' . $p;
        }
        return strlen($p) >= 11 ? $p : null;
    }

    /** ¿Hay una tarifa por km configurada (para cotizar el envío)? */
    public function getHasPricingAttribute(): bool
    {
        return $this->price_per_km !== null && (float) $this->price_per_km > 0;
    }

    /**
     * Cotiza el precio del envío a domicilio para una distancia en km:
     * precio = base + km × tarifa, nunca menor al mínimo. Null si no hay tarifa.
     */
    public function quotePrice(?float $km): ?float
    {
        if (!$this->has_pricing || $km === null) {
            return null;
        }
        $price = (float) $this->base_price + $km * (float) $this->price_per_km;
        $price = max($price, (float) $this->min_price);
        return round($price, 2);
    }
}
