<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Lote de impresión de rótulos.
 *
 * Ciclo: open → printed → closed (o cancelled si se descarta antes de imprimir).
 *
 *   open      el encargado armó la selección; todavía se puede sacar envíos
 *             y cambiarles la modalidad.
 *   printed   ya se imprimió: los envíos quedan BLOQUEADOS (no cambian de
 *             modalidad ni entran envíos nuevos al lote).
 *   closed    el lote se despachó; queda solo como historial.
 *
 * El manifiesto se numera aparte (`manifest_code`) porque los lotes de solo
 * "recojo en tienda" no generan manifiesto de despacho.
 */
class ShippingPrintBatch extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_print_batches';

    protected $fillable = [
        'code',
        'manifest_code',
        'status',
        'delivery_type',
        'label_count',
        'sheet_count',
        'package_count',
        'label_range',
        'format',
        'printed_at',
        'printed_by',
        'printed_by_name',
        'closed_at',
        'closed_by',
        'closed_by_name',
        'created_by',
        'created_by_name',
        'notes',
    ];

    protected $casts = [
        'label_count'   => 'integer',
        'sheet_count'   => 'integer',
        'package_count' => 'integer',
        'printed_at'    => 'datetime',
        'closed_at'     => 'datetime',
        'printed_by'    => 'integer',
        'closed_by'     => 'integer',
        'created_by'    => 'integer',
    ];

    public const STATUS_OPEN      = 'open';
    public const STATUS_PRINTED   = 'printed';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN      => 'Abierto',
        self::STATUS_PRINTED   => 'Impreso',
        self::STATUS_CLOSED    => 'Cerrado',
        self::STATUS_CANCELLED => 'Descartado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_OPEN      => 'warning',
        self::STATUS_PRINTED   => 'primary',
        self::STATUS_CLOSED    => 'success',
        self::STATUS_CANCELLED => 'secondary',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    public function shipments()
    {
        return $this->hasMany(ShippingRequest::class, 'print_batch_id');
    }

    public function printEvents()
    {
        return $this->hasMany(ShippingPrintEvent::class, 'batch_id')->orderBy('sequence');
    }

    public function auditLogs()
    {
        return $this->hasMany(ShippingAuditLog::class, 'batch_id')->latest('id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopePrintedToday($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED)
                     ->whereDate('printed_at', now()->toDateString());
    }

    // ── Estado ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /** ¿Sigue admitiendo cambios (agregar/quitar envíos, cambiar modalidad)? */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPrinted(): bool
    {
        return in_array($this->status, [self::STATUS_PRINTED, self::STATUS_CLOSED], true);
    }

    /**
     * ¿Genera manifiesto de despacho? El recojo en tienda no se despacha, así
     * que un lote exclusivamente de recojos no lleva manifiesto.
     */
    public function generatesManifest(): bool
    {
        if ($this->delivery_type === ShippingRequest::DELIVERY_TIENDA) {
            return false;
        }

        return $this->shipments()
                    ->where('delivery_type', '!=', ShippingRequest::DELIVERY_TIENDA)
                    ->exists();
    }

    /** Reimpresiones registradas (la impresión original no cuenta). */
    public function reprintCount(): int
    {
        return $this->printEvents()->where('is_reprint', true)->count();
    }

    // ── Numeración ─────────────────────────────────────────────────────────

    /** Siguiente código correlativo LOTE-000125. */
    public static function nextCode(): string
    {
        $last = static::orderByDesc('id')->value('code');
        $n    = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return sprintf('LOTE-%06d', $n);
    }

    /** Siguiente correlativo de manifiesto MAN-000125. */
    public static function nextManifestCode(): string
    {
        $last = static::whereNotNull('manifest_code')->orderByDesc('id')->value('manifest_code');
        $n    = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return sprintf('MAN-%06d', $n);
    }

    /**
     * Rango de etiquetas del lote, en códigos de envío.
     * Ej. "ENV-20260726-000012 → ENV-20260726-000031".
     */
    public function computeLabelRange(): ?string
    {
        $codes = $this->shipments()->orderBy('id')->pluck('shipment_code')->filter();

        if ($codes->isEmpty()) {
            return null;
        }

        return $codes->count() === 1
            ? $codes->first()
            : $codes->first() . ' → ' . $codes->last();
    }
}
