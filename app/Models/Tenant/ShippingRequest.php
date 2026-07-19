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

    public const STATUSES = [
        self::STATUS_RECIBIDO   => 'Registro recibido',
        self::STATUS_CONFIRMADO => 'Confirmado',
        self::STATUS_PREPARANDO => 'Preparando pedido',
        self::STATUS_EMBALANDO  => 'Embalando',
        self::STATUS_DESPACHADO => 'Despachado',
        self::STATUS_EN_AGENCIA => 'Entregado a agencia',
        self::STATUS_EN_RUTA    => 'En ruta',
        self::STATUS_ENTREGADO  => 'Entregado',
        self::STATUS_ANULADO    => 'Anulado',
    ];

    /** Secuencia del flujo (para la línea de tiempo del seguimiento). */
    public const STATUS_ORDER = [
        self::STATUS_RECIBIDO, self::STATUS_CONFIRMADO, self::STATUS_PREPARANDO,
        self::STATUS_EMBALANDO, self::STATUS_DESPACHADO, self::STATUS_EN_AGENCIA,
        self::STATUS_EN_RUTA, self::STATUS_ENTREGADO,
    ];

    /** Estados elegibles desde el panel (sin 'anulado', que tiene su propia acción). */
    public const SELECTABLE_STATUSES = self::STATUS_ORDER;

    /** Etiquetas de valores legados (compatibilidad con envíos previos a Fase 2). */
    public const LEGACY_LABELS = [
        'pendiente' => 'Registro recibido',
        'listo'     => 'Embalando',
        'enviado'   => 'Entregado a agencia',
    ];

    /** Mensaje de WhatsApp por estado (o null si ese estado no notifica). */
    public static function statusWhatsappMessage(string $status): ?string
    {
        $map = [
            self::STATUS_CONFIRMADO => 'Tu pedido fue *confirmado*. En breve lo prepararemos. ✅',
            self::STATUS_PREPARANDO => 'Estamos *preparando* tu pedido. 📦',
            self::STATUS_EMBALANDO  => 'Tu pedido ya fue *embalado*. 📦✅',
            self::STATUS_DESPACHADO => 'Tu pedido fue *despachado*. 🚚',
            self::STATUS_EN_AGENCIA => 'Tu pedido fue *entregado a la agencia*. 🏢',
            self::STATUS_EN_RUTA    => 'Tu pedido se encuentra *en ruta*. 🛣️',
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
     * Genera el código legible del envío a partir del id (ENV-000125).
     */
    public static function buildCode(int $id): string
    {
        return 'ENV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
