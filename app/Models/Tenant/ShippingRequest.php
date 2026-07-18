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
        'shipping_agency',
        'package_content',
        'package_count',
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
    ];

    // ── Estados del paquete ────────────────────────────────────────────────
    public const STATUS_PENDIENTE  = 'pendiente';
    public const STATUS_PREPARANDO = 'preparando';
    public const STATUS_LISTO      = 'listo';
    public const STATUS_ENVIADO    = 'enviado';
    public const STATUS_ENTREGADO  = 'entregado';

    public const STATUSES = [
        self::STATUS_PENDIENTE  => 'Pendiente',
        self::STATUS_PREPARANDO => 'Preparando embalaje',
        self::STATUS_LISTO      => 'Listo para envío',
        self::STATUS_ENVIADO    => 'Enviado',
        self::STATUS_ENTREGADO  => 'Entregado',
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

    // ── Scopes para los filtros del panel ─────────────────────────────────
    public function scopeWithoutGuide($q)
    {
        return $q->whereNull('shipping_guide_path');
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

    /**
     * Genera el código legible del envío a partir del id (ENV-000125).
     */
    public static function buildCode(int $id): string
    {
        return 'ENV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
