<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora del módulo de Envíos.
 *
 * Registra TODA acción con valor anterior y nuevo: cambio de modalidad, de
 * prioridad, de estado, impresión, reimpresión, anulación, restauración,
 * despacho, entrega y cierre de lote.
 *
 * `is_exception` marca las acciones que rompieron una regla de negocio a
 * propósito (p. ej. cambiar la modalidad de un envío ya impreso), para que se
 * puedan revisar aparte.
 *
 * Se escribe con `log()`, nunca se edita ni se borra.
 */
class ShippingAuditLog extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_audit_logs';

    protected $fillable = [
        'shipment_id',
        'batch_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'notes',
        'is_exception',
        'user_id',
        'user_name',
        'ip',
    ];

    protected $casts = [
        'shipment_id'  => 'integer',
        'batch_id'     => 'integer',
        'user_id'      => 'integer',
        'is_exception' => 'boolean',
    ];

    // ── Acciones ───────────────────────────────────────────────────────────
    public const ACTION_MODALITY   = 'modality_change';
    public const ACTION_PRIORITY   = 'priority_change';
    public const ACTION_STATUS     = 'status_change';
    public const ACTION_PRINT      = 'print';
    public const ACTION_REPRINT    = 'reprint';
    public const ACTION_CANCEL     = 'cancel';
    public const ACTION_RESTORE    = 'restore';
    public const ACTION_DISPATCH   = 'dispatch';
    public const ACTION_DELIVER    = 'deliver';
    public const ACTION_PICKUP     = 'pickup';
    // Guía de Remisión: se audita el intento y la emisión por separado,
    // porque entre uno y otro el operador puede corregir datos o desistir.
    public const ACTION_GUIDE_START  = 'dispatch_start';
    public const ACTION_GUIDE_ISSUED = 'dispatch_issued';

    public const ACTION_BATCH_OPEN = 'batch_open';
    public const ACTION_BATCH_ADD  = 'batch_add';
    public const ACTION_BATCH_PULL = 'batch_remove';
    public const ACTION_BATCH_CLOSE = 'batch_close';
    public const ACTION_PAYMENT    = 'payment_confirm';
    public const ACTION_GUIDE      = 'guide_upload';
    public const ACTION_EDIT       = 'edit';

    public const ACTION_LABELS = [
        self::ACTION_MODALITY    => 'Cambio de modalidad',
        self::ACTION_PRIORITY    => 'Cambio de prioridad',
        self::ACTION_STATUS      => 'Cambio de estado',
        self::ACTION_PRINT       => 'Impresión',
        self::ACTION_REPRINT     => 'Reimpresión',
        self::ACTION_CANCEL      => 'Anulación',
        self::ACTION_RESTORE     => 'Restauración',
        self::ACTION_DISPATCH    => 'Despacho',
        self::ACTION_DELIVER     => 'Entrega',
        self::ACTION_PICKUP      => 'Recojo en tienda',
        self::ACTION_BATCH_OPEN  => 'Lote creado',
        self::ACTION_BATCH_ADD   => 'Envío agregado al lote',
        self::ACTION_BATCH_PULL  => 'Envío retirado del lote',
        self::ACTION_BATCH_CLOSE => 'Cierre de lote',
        self::ACTION_PAYMENT     => 'Confirmación de pago',
        self::ACTION_GUIDE       => 'Carga de guía',
        self::ACTION_EDIT        => 'Edición de datos',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function shipment()
    {
        return $this->belongsTo(ShippingRequest::class, 'shipment_id');
    }

    public function batch()
    {
        return $this->belongsTo(ShippingPrintBatch::class, 'batch_id');
    }

    // ── Escritura ──────────────────────────────────────────────────────────

    /**
     * Registra una acción. Único punto de escritura de la bitácora.
     *
     * Nunca lanza: una auditoría que falle no debe tumbar la operación que
     * intentaba registrar (p. ej. tenant sin la tabla todavía).
     */
    public static function log(
        string $action,
        ?int $shipmentId = null,
        ?string $field = null,
        $oldValue = null,
        $newValue = null,
        ?string $notes = null,
        ?int $batchId = null,
        bool $isException = false
    ): ?self {
        try {
            $user = auth()->user();

            return static::create([
                'shipment_id'  => $shipmentId,
                'batch_id'     => $batchId,
                'action'       => $action,
                'field'        => $field,
                'old_value'    => self::stringify($oldValue),
                'new_value'    => self::stringify($newValue),
                'notes'        => $notes ? mb_substr($notes, 0, 500) : null,
                'is_exception' => $isException,
                'user_id'      => $user?->id,
                'user_name'    => $user?->name ?? 'sistema',
                'ip'           => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ShippingAudit] no se pudo registrar: ' . $e->getMessage());
            return null;
        }
    }

    private static function stringify($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'sí' : 'no';
        }
        if (is_array($value)) {
            return mb_substr(json_encode($value, JSON_UNESCAPED_UNICODE), 0, 255);
        }

        return mb_substr((string) $value, 0, 255);
    }

    // ── Lectura ────────────────────────────────────────────────────────────

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    /** Descripción del cambio en una línea, lista para la línea de tiempo. */
    public function getSummaryAttribute(): string
    {
        if ($this->old_value !== null && $this->new_value !== null) {
            return "{$this->action_label}: {$this->old_value} → {$this->new_value}";
        }
        if ($this->new_value !== null) {
            return "{$this->action_label}: {$this->new_value}";
        }

        return $this->action_label;
    }
}
