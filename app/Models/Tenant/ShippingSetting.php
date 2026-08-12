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
        'agency_fee_mode',
        'require_payment',
        'max_business_days',
        'aging_skip_holidays',
        'cutoff_time',
    ];

    protected $casts = [
        'store_latitude'  => 'decimal:7',
        'store_longitude' => 'decimal:7',
        'price_per_km'    => 'decimal:2',
        'base_price'      => 'decimal:2',
        'min_price'       => 'decimal:2',
        'agency_fee'      => 'decimal:2',
        'require_payment' => 'boolean',
        'max_business_days'   => 'integer',
        'aging_skip_holidays' => 'boolean',
    ];

    // ── Cobro del servicio tienda → agencia ────────────────────────────────
    //
    // Tres estados, porque el monto solo no alcanzaba: con un número, "0" y
    // "vacío" se ven igual y el cliente no lee nada. Y no decir nada NO es
    // decir gratis — el cliente asume que se lo cobrarán en la agencia.

    public const FEE_AMOUNT = 'amount';   // Cobra un monto fijo
    public const FEE_FREE   = 'free';     // Gratis, y se le muestra al cliente
    public const FEE_HIDDEN = 'hidden';   // No mencionar el tema

    public const FEE_MODES = [
        self::FEE_AMOUNT => 'Cobro un monto fijo',
        self::FEE_FREE   => 'Es gratis (mostrar “GRATIS” al cliente)',
        self::FEE_HIDDEN => 'No mencionar nada en el formulario',
    ];

    public function getFeeModeAttribute(): string
    {
        $m = (string) ($this->agency_fee_mode ?? '');

        // Tenants que aún no corrieron la migración: deducir del monto para
        // que se sigan viendo como hasta ahora.
        if (!array_key_exists($m, self::FEE_MODES)) {
            return ((float) $this->agency_fee > 0) ? self::FEE_AMOUNT : self::FEE_HIDDEN;
        }

        // Coherencia: "cobro un monto" sin monto cargado no es cobrar nada.
        if ($m === self::FEE_AMOUNT && (float) $this->agency_fee <= 0) {
            return self::FEE_HIDDEN;
        }

        return $m;
    }

    /** ¿El envío tienda→agencia se regala (y hay que decirlo)? */
    public function getAgencyIsFreeAttribute(): bool
    {
        return $this->fee_mode === self::FEE_FREE;
    }

    /** ¿Hay algo que mostrarle al cliente sobre este cobro? */
    public function getShowsAgencyFeeAttribute(): bool
    {
        return $this->fee_mode !== self::FEE_HIDDEN;
    }

    /**
     * Lo que se cobra por el servicio: 0.00 si es gratis, el monto si cobra,
     * null si la tienda prefiere no fijar nada todavía.
     */
    public function getAgencyChargeAttribute(): ?float
    {
        return match ($this->fee_mode) {
            self::FEE_FREE   => 0.0,
            self::FEE_AMOUNT => round((float) $this->agency_fee, 2),
            default          => null,
        };
    }

    /** Plazo máximo de atención en días hábiles (para el semáforo de prioridad). */
    public function getMaxDaysAttribute(): int
    {
        $v = (int) ($this->max_business_days ?? 0);
        return $v >= 1 ? $v : 4;
    }

    /**
     * Hora de corte operativo en HH:MM, o null si no está configurada.
     * MySQL devuelve TIME como "18:00:00"; el <input type="time"> quiere "18:00".
     */
    public function getCutoffHhmmAttribute(): ?string
    {
        return $this->cutoff_time ? substr((string) $this->cutoff_time, 0, 5) : null;
    }

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
