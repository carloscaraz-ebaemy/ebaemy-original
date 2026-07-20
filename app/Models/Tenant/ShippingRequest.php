<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Envío registrado en el módulo "Registro y Control de Envíos".
 *
 * @property int $id
 * @property string|null $shipment_code
 * @property int|null $order_id
 * @property string $full_name
 * @property string|null $dni
 * @property string|null $phone
 * @property string|null $shipping_destination
 * @property string|null $destination_city
 * @property string|null $shipping_agency
 * @property string|null $tracking_number
 * @property string|null $shipping_guide_path
 * @property string|null $observation
 * @property string $status
 * @property bool $accepted_terms
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int|null $created_by
 */
class ShippingRequest extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_requests';

    protected $fillable = [
        'shipment_code',
        'order_id',
        'delivery_type',
        'full_name',
        'dni',
        'phone',
        'shipping_destination',
        'reference',
        'destination_city',
        'department_id',
        'province_id',
        'district_id',
        'shipping_agency',
        // Google Maps (solo entregas a domicilio / motorizado)
        'latitude',
        'longitude',
        'google_place_id',
        'google_maps_url',
        'formatted_address',
        'distance_km',
        'distance_text',
        'duration_text',
        'courier_name',
        'courier_phone',
        'package_content',
        'package_count',
        'weight',
        'notes',
        'tracking_number',
        'shipping_guide_path',
        'observation',
        'status',
        'accepted_terms',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'accepted_terms' => 'boolean',
        'sent_at'        => 'datetime',
        'order_id'       => 'integer',
        'created_by'     => 'integer',
        'package_count'  => 'integer',
        'weight'         => 'decimal:2',
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'distance_km'    => 'decimal:2',
    ];

    /**
     * Distancia en línea recta (haversine) entre dos coordenadas, en km.
     * Sirve de fallback cuando no hay distancia de manejo de Google.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371; // radio terrestre km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    // ── Tipo de entrega ────────────────────────────────────────────────────
    /** Motorizado propio a domicilio (usa Google Maps + coordenadas). */
    public const DELIVERY_DOMICILIO = 'domicilio';
    /** Envío por agencia de transporte a provincia (usa ubigeo). */
    public const DELIVERY_AGENCIA   = 'agencia';

    public const DELIVERY_TYPES = [
        self::DELIVERY_DOMICILIO => 'Entrega a domicilio (Motorizado)',
        self::DELIVERY_AGENCIA   => 'Envío por agencia',
    ];

    // ── Estados del paquete (flujo completo) ───────────────────────────────
    public const STATUS_RECIBIDO   = 'recibido';
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_PREPARANDO = 'preparando';
    public const STATUS_EMBALANDO  = 'embalando';
    public const STATUS_DESPACHADO = 'despachado';
    public const STATUS_EN_AGENCIA = 'en_agencia';
    public const STATUS_EN_RUTA    = 'en_ruta';
    public const STATUS_ENTREGADO  = 'entregado';
    public const STATUS_ANULADO    = 'anulado';
    // Estados exclusivos de la entrega a domicilio (motorizado).
    public const STATUS_ASIGNADO   = 'asignado_motorizado';
    public const STATUS_EN_CAMINO  = 'en_camino';

    public const STATUSES = [
        self::STATUS_RECIBIDO   => 'Registro recibido',
        self::STATUS_CONFIRMADO => 'Pedido confirmado',
        self::STATUS_PREPARANDO => 'Preparando pedido',
        self::STATUS_ASIGNADO   => 'Asignado a motorizado',
        self::STATUS_EN_CAMINO  => 'Motorizado en camino',
        self::STATUS_EMBALANDO  => 'Embalando',
        self::STATUS_DESPACHADO => 'Despachado',
        self::STATUS_EN_AGENCIA => 'Entregado a agencia',
        self::STATUS_EN_RUTA    => 'En tránsito',
        self::STATUS_ENTREGADO  => 'Entregado',
        self::STATUS_ANULADO    => 'Anulado',
    ];

    /**
     * Secuencia del flujo por TIPO de entrega (para la línea de tiempo).
     * Cada tipo tiene su propio recorrido de estados.
     */
    public const STATUS_FLOWS = [
        self::DELIVERY_DOMICILIO => [
            self::STATUS_RECIBIDO, self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO,
            self::STATUS_ASIGNADO, self::STATUS_EN_CAMINO, self::STATUS_ENTREGADO,
        ],
        self::DELIVERY_AGENCIA => [
            self::STATUS_RECIBIDO, self::STATUS_PREPARANDO, self::STATUS_EMBALANDO,
            self::STATUS_DESPACHADO, self::STATUS_EN_AGENCIA, self::STATUS_EN_RUTA,
            self::STATUS_ENTREGADO,
        ],
    ];

    /** Secuencia por defecto (agencia) — se mantiene por compatibilidad. */
    public const STATUS_ORDER = self::STATUS_FLOWS[self::DELIVERY_AGENCIA];

    /**
     * Secuencia de estados según el tipo de entrega del envío.
     * @return string[]
     */
    public static function statusOrderFor(?string $deliveryType): array
    {
        return self::STATUS_FLOWS[$deliveryType] ?? self::STATUS_FLOWS[self::DELIVERY_AGENCIA];
    }

    /** Estados elegibles desde el panel para este envío (según su tipo, sin 'anulado'). */
    public function selectableStatuses(): array
    {
        return self::statusOrderFor($this->delivery_type);
    }

    /** Etiquetas de valores legados (compatibilidad con envíos previos a Fase 2). */
    public const LEGACY_LABELS = [
        'pendiente' => 'Registro recibido',
        'listo'     => 'Embalando',
        'enviado'   => 'Entregado a agencia',
    ];

    /**
     * Mensaje de WhatsApp por estado (o null si ese estado no notifica).
     * El tipo de entrega ajusta el texto (motorizado vs agencia).
     */
    public static function statusWhatsappMessage(string $status, ?string $deliveryType = null): ?string
    {
        $map = [
            self::STATUS_CONFIRMADO => 'Tu pedido fue *confirmado*. En breve lo prepararemos. ✅',
            self::STATUS_PREPARANDO => 'Estamos *preparando* tu pedido. 📦',
            self::STATUS_ASIGNADO   => 'Tu pedido fue *asignado a un motorizado* y saldrá pronto. 🏍️',
            self::STATUS_EN_CAMINO  => 'Nuestro *motorizado está en camino* a tu dirección. 🏍️💨',
            self::STATUS_EMBALANDO  => 'Tu pedido ya fue *embalado*. 📦✅',
            self::STATUS_DESPACHADO => 'Tu pedido fue *despachado*. 🚚',
            self::STATUS_EN_AGENCIA => 'Tu pedido fue *entregado a la agencia*. 🏢',
            self::STATUS_EN_RUTA    => 'Tu pedido se encuentra *en tránsito*. 🛣️',
            self::STATUS_ENTREGADO  => 'Tu pedido fue *entregado correctamente*. 🎉',
        ];
        return $map[$status] ?? null;
    }

    /** Etiqueta legible del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? self::LEGACY_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** ¿Ya tiene la guía de envío cargada? */
    public function getHasGuideAttribute(): bool
    {
        return !empty($this->shipping_guide_path);
    }

    /** ¿Está anulado? */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    /** ¿Es una entrega a domicilio (motorizado con Google Maps)? */
    public function getIsDomicilioAttribute(): bool
    {
        return $this->delivery_type === self::DELIVERY_DOMICILIO;
    }

    /** Etiqueta legible del tipo de entrega. */
    public function getDeliveryTypeLabelAttribute(): string
    {
        return self::DELIVERY_TYPES[$this->delivery_type] ?? 'Envío por agencia';
    }

    /** ¿Tiene coordenadas geográficas (para el mapa/motorizado)? */
    public function getHasCoordsAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Enlace a Google Maps para ver la ubicación (URL guardada o construida). */
    public function getMapsLinkAttribute(): ?string
    {
        if ($this->google_maps_url) {
            return $this->google_maps_url;
        }
        if ($this->has_coords) {
            return 'https://www.google.com/maps/search/?api=1&query=' . $this->latitude . ',' . $this->longitude;
        }
        return null;
    }

    /** Enlace de NAVEGACIÓN para el motorizado (abre la ruta en Google Maps). */
    public function getCourierDirectionsUrlAttribute(): ?string
    {
        if (!$this->has_coords) {
            return null;
        }
        return 'https://www.google.com/maps/dir/?api=1&destination=' . $this->latitude . ',' . $this->longitude;
    }

    // ── Scopes por tipo de entrega ────────────────────────────────────────
    public function scopeDomicilio($q)
    {
        return $q->where('delivery_type', self::DELIVERY_DOMICILIO);
    }

    public function scopeAgencia($q)
    {
        return $q->where('delivery_type', self::DELIVERY_AGENCIA);
    }

    /** Envíos a domicilio activos (para el tablero del motorizado). */
    public function scopeCourierActive($q)
    {
        return $q->domicilio()->whereIn('status', [
            self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO,
            self::STATUS_ASIGNADO, self::STATUS_EN_CAMINO,
        ]);
    }

    // ── Scopes para los filtros del panel ─────────────────────────────────
    public function scopeWithoutGuide($q)
    {
        // Los anulados no cuentan como "pendientes de guía".
        return $q->whereNull('shipping_guide_path')
                 ->where('status', '!=', self::STATUS_ANULADO);
    }

    public function scopeWithGuide($q)
    {
        return $q->whereNotNull('shipping_guide_path');
    }

    public function scopePending($q)
    {
        // "Pendientes" = aún no salió a la agencia (incluye valores legados).
        return $q->whereIn('status', [
            self::STATUS_RECIBIDO, self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO,
            self::STATUS_EMBALANDO, self::STATUS_DESPACHADO, 'pendiente', 'listo',
        ]);
    }

    public function scopeSentToday($q)
    {
        // "Enviados hoy" = entregados a la agencia / en ruta / entregados hoy.
        return $q->whereIn('status', [self::STATUS_EN_AGENCIA, self::STATUS_EN_RUTA, self::STATUS_ENTREGADO, 'enviado'])
                 ->whereDate('sent_at', now()->toDateString());
    }

    /** Agencias de envío frecuentes en Perú (para el desplegable del form). */
    public const AGENCIES = [
        'Shalom', 'Olva Courier', 'Marvisur', 'Cruz del Sur Cargo', 'Móvil Tours',
        'Tepsa', 'Civa', 'Flores', 'Transportes Línea', 'GH Bus', 'Ittsa', 'Oltursa',
    ];

    /**
     * Genera el código legible del envío: ENV-AAAAMMDD-000015 (fecha + id).
     * El número final es el id, garantizando unicidad global.
     */
    public static function buildCode(int $id, ?string $date = null): string
    {
        $date = $date ?: now()->format('Ymd');
        return 'ENV-' . $date . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
