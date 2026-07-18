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

    // ── Estados del paquete ────────────────────────────────────────────────
    public const STATUS_PENDIENTE  = 'pendiente';
    public const STATUS_PREPARANDO = 'preparando';
    public const STATUS_LISTO      = 'listo';
    public const STATUS_ENVIADO    = 'enviado';
    public const STATUS_ENTREGADO  = 'entregado';
    public const STATUS_ANULADO    = 'anulado';

    public const STATUSES = [
        self::STATUS_PENDIENTE  => 'Pendiente',
        self::STATUS_PREPARANDO => 'Preparando embalaje',
        self::STATUS_LISTO      => 'Listo para envío',
        self::STATUS_ENVIADO    => 'Enviado',
        self::STATUS_ENTREGADO  => 'Entregado',
        self::STATUS_ANULADO    => 'Anulado',
    ];

    /** Estados que el usuario puede elegir desde el dropdown (sin 'anulado', que tiene su propia acción). */
    public const SELECTABLE_STATUSES = [
        self::STATUS_PENDIENTE,
        self::STATUS_PREPARANDO,
        self::STATUS_LISTO,
        self::STATUS_ENVIADO,
        self::STATUS_ENTREGADO,
    ];

    /** Etiqueta legible del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
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
        return $q->where('status', self::STATUS_PENDIENTE);
    }

    public function scopeSentToday($q)
    {
        return $q->where('status', self::STATUS_ENVIADO)
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
